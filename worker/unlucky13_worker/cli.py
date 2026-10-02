from __future__ import annotations

import argparse
import json
import sys

from .candidates import load_candidates
from .client import WorkerApiClient, WorkerApiError
from .config import WorkerConfig


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="unlucky13-worker",
        description="Private Python client for the Unlucky 13 Laravel worker API.",
    )

    subparsers = parser.add_subparsers(dest="command", required=True)
    subparsers.add_parser("health", help="Verify API authentication and connectivity.")

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
