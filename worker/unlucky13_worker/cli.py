from __future__ import annotations

import argparse
import json
import sys
import time

from .candidates import load_candidates
from .client import WorkerApiClient, WorkerApiError
from .config import WorkerConfig
from .discovery_sources import DiscoverySourceError, OpenStreetMapDiscoverySource
from .website_analyzer import AnalyzerError, WebsiteAnalyzer


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="unlucky13-worker",
        description="Private Python client for the Unlucky 13 Laravel worker API.",
    )

    subparsers = parser.add_subparsers(dest="command", required=True)
    subparsers.add_parser("health", help="Verify API authentication and connectivity.")
    subparsers.add_parser("campaigns", help="List active campaigns available to the worker.")

    discover = subparsers.add_parser(
        "discover-osm",
        help="Discover public business websites from OpenStreetMap for active campaigns.",
    )
    discover.add_argument(
        "--campaign",
        help="Optional campaign UUID. Without it, all active campaigns are processed.",
    )
    discover.add_argument(
        "--limit",
        type=int,
        default=10,
        help="Maximum discovered candidates per campaign (default: 10, max: 50).",
    )
    discover.add_argument(
        "--dry-run",
        action="store_true",
        help="Discover and display candidates without ingesting them.",
    )

    ingest = subparsers.add_parser(
        "ingest",
        help="Send discovered business candidates to one active campaign.",
    )
    ingest.add_argument("--campaign", required=True, help="Campaign UUID")
    ingest.add_argument("--file", required=True, help="JSON or JSONL candidate file")
    ingest.add_argument("--limit", type=int, default=25, help="Maximum candidates per run")
    ingest.add_argument("--dry-run", action="store_true", help="Validate without sending")

    queue = subparsers.add_parser(
        "analysis-queue",
        help="Show leads waiting for analysis.",
    )
    queue.add_argument("--limit", type=int, default=10, help="Maximum leads to return")

    submit = subparsers.add_parser(
        "analysis-submit",
        help="Submit an auditable qualification result for one lead.",
    )
    submit.add_argument("--lead", required=True, help="Lead UUID")
    submit.add_argument("--score", required=True, type=int, help="Qualification score 0-100")
    submit.add_argument("--reason", required=True, help="Short factual qualification reason")
    submit.add_argument(
        "--signal",
        action="append",
        default=[],
        help="Observed public signal; repeat for multiple signals",
    )

    analyze = subparsers.add_parser(
        "analyze",
        help="Inspect queued public business websites and submit qualification results.",
    )
    analyze.add_argument("--limit", type=int, default=5, help="Maximum queued leads per run")
    analyze.add_argument(
        "--delay",
        type=float,
        default=1.0,
        help="Delay in seconds between website requests (default: 1.0)",
    )
    analyze.add_argument(
        "--dry-run",
        action="store_true",
        help="Inspect and score without changing lead status.",
    )

    return parser


def main(argv: list[str] | None = None) -> int:
    args = build_parser().parse_args(argv)

    try:
        config = WorkerConfig.from_environment()
    except (ValueError, OSError) as error:
        print(f"Configuration error: {error}", file=sys.stderr)
        return 2

    client = WorkerApiClient(
        base_url=config.base_url,
        token=config.token,
        timeout_seconds=config.timeout_seconds,
        max_retries=config.max_retries,
    )

    try:
        if args.command == "health":
            print(json.dumps(client.health(), indent=2))
            return 0

        if args.command == "campaigns":
            print(json.dumps(client.active_campaigns(), indent=2))
            return 0

        if args.command == "discover-osm":
            response = client.active_campaigns()
            campaigns = response.get("campaigns", [])

            if args.campaign:
                campaigns = [
                    campaign
                    for campaign in campaigns
                    if campaign.get("uuid") == args.campaign
                ]

            if not campaigns:
                print("No matching active campaigns are available.")
                return 0

            source = OpenStreetMapDiscoverySource(
                user_agent=config.discovery_user_agent,
                nominatim_url=config.nominatim_url,
                overpass_url=config.overpass_url,
                timeout_seconds=config.discovery_timeout_seconds,
                delay_seconds=config.discovery_delay_seconds,
            )

            discovered_total = 0
            created_total = 0
            duplicate_total = 0
            failed_total = 0

            for campaign in campaigns:
                campaign_name = campaign.get("name") or campaign.get("uuid")

                try:
                    candidates = source.discover(campaign, limit=args.limit)
                except DiscoverySourceError as error:
                    failed_total += 1
                    print(f"{campaign_name}: discovery failed - {error}", file=sys.stderr)
                    continue

                discovered_total += len(candidates)
                print(f"{campaign_name}: discovered {len(candidates)} candidate(s)")

                for candidate in candidates:
                    if args.dry_run:
                        print(
                            "  dry-run: "
                            f"{candidate.get('business_name')} -> {candidate.get('website_url')}"
                        )
                        continue

                    try:
                        result = client.ingest_lead(campaign["uuid"], candidate)
                        status = result.get("status", "unknown")

                        if status == "created":
                            created_total += 1
                        elif status == "duplicate":
                            duplicate_total += 1

                        print(
                            f"  {status}: "
                            f"{candidate.get('business_name')} -> {candidate.get('website_url')}"
                        )
                    except WorkerApiError as error:
                        failed_total += 1
                        print(
                            f"  failed: {candidate.get('business_name')} - {error}",
                            file=sys.stderr,
                        )

            print(
                "Finished. "
                f"discovered={discovered_total} created={created_total} "
                f"duplicate={duplicate_total} failed={failed_total}"
            )
            return 1 if failed_total else 0

        if args.command == "analysis-queue":
            print(json.dumps(client.analysis_queue(args.limit), indent=2))
            return 0

        if args.command == "analysis-submit":
            started = client.start_analysis(args.lead)
            result = client.complete_analysis(
                args.lead,
                args.score,
                args.reason,
                args.signal,
            )
            print(json.dumps({"start": started, "result": result}, indent=2))
            return 0

        if args.command == "analyze":
            queue_result = client.analysis_queue(args.limit)
            leads = queue_result.get("leads", [])

            if not leads:
                print("No leads are waiting for analysis.")
                return 0

            analyzer = WebsiteAnalyzer(timeout_seconds=config.timeout_seconds)
            completed = 0
            failed = 0

            for index, lead in enumerate(leads, start=1):
                try:
                    result = analyzer.analyze(lead)
                    print(
                        f"[{index}/{len(leads)}] {lead.get('business_name')} "
                        f"score={result['score']} signals={len(result['signals'])}"
                    )

                    for signal in result["signals"]:
                        print(f"  - {signal}")

                    if args.dry_run:
                        print("  dry-run: result not submitted")
                    else:
                        client.start_analysis(lead["uuid"])
                        response = client.complete_analysis(
                            lead["uuid"],
                            result["score"],
                            result["reason"],
                            result["signals"],
                        )
                        decision = response.get("decision") or response.get("status")
                        print(f"  submitted: {decision}")
                        completed += 1

                except (AnalyzerError, WorkerApiError, ValueError) as error:
                    failed += 1
                    print(
                        f"[{index}/{len(leads)}] failed: "
                        f"{lead.get('business_name')} - {error}",
                        file=sys.stderr,
                    )

                if index < len(leads):
                    time.sleep(max(0.5, min(args.delay, 10.0)))

            print(
                f"Finished. analyzed={len(leads)} submitted={completed} failed={failed}"
            )
            return 1 if failed else 0

        candidates = load_candidates(args.file)[: max(1, min(args.limit, 100))]

        if args.dry_run:
            print(f"Validated {len(candidates)} candidate(s). Nothing was sent.")
            return 0

        created = 0
        duplicates = 0
        failed = 0

        for index, candidate in enumerate(candidates, start=1):
            try:
                result = client.ingest_lead(args.campaign, candidate)
                status = result.get("status", "unknown")

                if status == "created":
                    created += 1
                elif status == "duplicate":
                    duplicates += 1

                lead = result.get("lead", {})
                domain = lead.get("domain") or candidate.get("website_url")
                print(f"[{index}/{len(candidates)}] {status}: {domain}")
            except WorkerApiError as error:
                failed += 1
                print(
                    f"[{index}/{len(candidates)}] failed: {candidate.get('business_name')} - {error}",
                    file=sys.stderr,
                )

        print(f"Finished. created={created} duplicate={duplicates} failed={failed}")
        return 1 if failed else 0

    except (ValueError, json.JSONDecodeError) as error:
        print(f"Input error: {error}", file=sys.stderr)
        return 2
    except WorkerApiError as error:
        print(f"Worker API error: {error}", file=sys.stderr)
        return 1
