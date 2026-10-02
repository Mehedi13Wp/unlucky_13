from __future__ import annotations

import argparse
import json
import sys
import time

from .candidates import load_candidates
from .client import WorkerApiClient, WorkerApiError
from .contact_discovery import ContactDiscoveryError, ContactFinder
from .config import WorkerConfig
from .discovery_cache import DiscoveryCache, DiscoveryCacheError
from .discovery_sources import DiscoverySourceError, OpenStreetMapDiscoverySource
from .outreach_drafter import OutreachDraftError, OutreachDrafter
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
    discover.add_argument(
        "--use-last",
        action="store_true",
        help="Reuse the most recent successful discovery snapshot instead of calling providers.",
    )

    ingest = subparsers.add_parser(
        "ingest",
        help="Send discovered business candidates to one active campaign.",
    )
    ingest.add_argument("--campaign", required=True, help="Campaign UUID")
    ingest.add_argument("--file", required=True, help="JSON or JSONL candidate file")
    ingest.add_argument("--limit", type=int, default=25, help="Maximum candidates per run")
    ingest.add_argument("--dry-run", action="store_true", help="Validate without sending")

    contacts = subparsers.add_parser(
        "contacts",
        help="Discover public contact paths for qualified leads.",
    )
    contacts.add_argument("--limit", type=int, default=5, help="Maximum qualified leads per run")
    contacts.add_argument(
        "--delay",
        type=float,
        default=1.0,
        help="Delay in seconds between leads (default: 1.0)",
    )
    contacts.add_argument(
        "--dry-run",
        action="store_true",
        help="Inspect contacts without saving them to Laravel.",
    )

    drafts = subparsers.add_parser(
        "drafts",
        help="Prepare fact-grounded outreach drafts for human review.",
    )
    drafts.add_argument("--limit", type=int, default=5, help="Maximum qualified leads per run")
    drafts.add_argument(
        "--dry-run",
        action="store_true",
        help="Preview generated drafts without saving them to Laravel.",
    )

    send = subparsers.add_parser(
        "send-approved",
        help="Deliver approved email drafts under campaign and suppression controls.",
    )
    send.add_argument("--limit", type=int, default=5, help="Maximum approved email drafts per run")
    send.add_argument(
        "--dry-run",
        action="store_true",
        help="Show delivery eligibility without sending email.",
    )

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
            cache = DiscoveryCache.default()

            discovered_total = 0
            created_total = 0
            duplicate_total = 0
            failed_total = 0

            for campaign in campaigns:
                campaign_name = campaign.get("name") or campaign.get("uuid")

                if args.use_last:
                    try:
                        snapshot = cache.load(campaign["uuid"], limit=args.limit)
                        candidates = snapshot["candidates"]
                    except DiscoveryCacheError as error:
                        failed_total += 1
                        print(
                            f"{campaign_name}: cached discovery unavailable - {error}",
                            file=sys.stderr,
                        )
                        continue

                    discovered_total += len(candidates)
                    print(
                        f"{campaign_name}: loaded {len(candidates)} candidate(s) "
                        f"from snapshot saved {snapshot['saved_at']}"
                    )
                else:
                    try:
                        candidates = source.discover(campaign, limit=args.limit)
                    except DiscoverySourceError as error:
                        failed_total += 1
                        cache_hint = (
                            " Use --use-last to reuse the last successful snapshot."
                            if cache.exists(campaign["uuid"])
                            else ""
                        )
                        print(
                            f"{campaign_name}: discovery failed - {error}.{cache_hint}",
                            file=sys.stderr,
                        )
                        continue

                    cache.save(campaign, candidates)
                    discovered_total += len(candidates)
                    print(
                        f"{campaign_name}: discovered {len(candidates)} candidate(s) "
                        "(snapshot saved)"
                    )

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

        if args.command == "contacts":
            queue_result = client.contact_discovery_queue(args.limit)
            leads = queue_result.get("leads", [])

            if not leads:
                print("No qualified leads are waiting for contact discovery.")
                return 0

            finder = ContactFinder(timeout_seconds=config.timeout_seconds)
            submitted = 0
            failed = 0

            for index, lead in enumerate(leads, start=1):
                try:
                    result = finder.discover(lead)
                    contacts = result["contacts"]

                    print(
                        f"[{index}/{len(leads)}] {lead.get('business_name')} "
                        f"contacts={len(contacts)} pages={len(result['pages_inspected'])}"
                    )

                    for contact in contacts:
                        print(
                            f"  - {contact['type']}: {contact['value']} "
                            f"(source: {contact.get('source_url')})"
                        )

                    for note in result["notes"]:
                        print(f"  note: {note}")

                    if args.dry_run:
                        print("  dry-run: contacts not saved")
                    else:
                        response = client.complete_contact_discovery(
                            lead["uuid"],
                            contacts,
                            result["notes"],
                        )
                        print(
                            f"  submitted: {response.get('contact_count', 0)} contact(s)"
                        )
                        submitted += 1

                except (ContactDiscoveryError, WorkerApiError, ValueError) as error:
                    failed += 1
                    print(
                        f"[{index}/{len(leads)}] failed: "
                        f"{lead.get('business_name')} - {error}",
                        file=sys.stderr,
                    )

                if index < len(leads):
                    time.sleep(max(0.5, min(args.delay, 10.0)))

            print(
                f"Finished. inspected={len(leads)} submitted={submitted} failed={failed}"
            )
            return 1 if failed else 0

        if args.command == "drafts":
            queue_result = client.outreach_draft_queue(args.limit)
            leads = queue_result.get("leads", [])

            if not leads:
                print("No qualified leads are waiting for outreach drafting.")
                return 0

            drafter = OutreachDrafter()
            submitted = 0
            failed = 0

            for index, lead in enumerate(leads, start=1):
                try:
                    draft = drafter.generate(lead)

                    print(
                        f"[{index}/{len(leads)}] {lead.get('business_name')} "
                        f"channel={draft['channel']} recipient={draft['recipient']}"
                    )

                    if draft.get("subject"):
                        print(f"  subject: {draft['subject']}")

                    print("  body:")
                    for line in draft["body"].splitlines():
                        print(f"    {line}")

                    for note in draft["personalization_notes"]:
                        print(f"  note: {note}")

                    if args.dry_run:
                        print("  dry-run: draft not saved")
                    else:
                        response = client.create_outreach_draft(
                            lead["uuid"],
                            draft,
                        )
                        print(
                            f"  submitted: {response.get('status', 'unknown')}"
                        )
                        submitted += 1

                except (OutreachDraftError, WorkerApiError, ValueError) as error:
                    failed += 1
                    print(
                        f"[{index}/{len(leads)}] failed: "
                        f"{lead.get('business_name')} - {error}",
                        file=sys.stderr,
                    )

            print(
                f"Finished. drafted={len(leads)} submitted={submitted} failed={failed}"
            )
            return 1 if failed else 0

        if args.command == "send-approved":
            queue_result = client.delivery_queue(args.limit)
            drafts = queue_result.get("drafts", [])
            mailer = queue_result.get("mailer", "unknown")
            delivery_capable = bool(queue_result.get("delivery_capable"))

            print(
                f"Mailer: {mailer} "
                f"(delivery_capable={str(delivery_capable).lower()})"
            )

            if not drafts:
                print("No approved email drafts are waiting for controlled delivery.")
                return 0

            sent = 0
            blocked = 0
            failed = 0

            for index, draft in enumerate(drafts, start=1):
                campaign = draft.get("campaign") or {}

                print(
                    f"[{index}/{len(drafts)}] {draft.get('business_name')} "
                    f"-> {draft.get('recipient')} "
                    f"remaining_today={campaign.get('remaining_today')}"
                )

                if not draft.get("eligible"):
                    blocked += 1
                    print(
                        f"  blocked: "
                        f"{draft.get('blocked_reason') or 'delivery_policy'}"
                    )
                    continue

                if args.dry_run:
                    if delivery_capable:
                        print("  dry-run: eligible and not sent")
                    else:
                        print(
                            "  dry-run: eligible, but real mail delivery "
                            "is not configured"
                        )
                    continue

                if not delivery_capable:
                    blocked += 1
                    print(
                        "  blocked: configure a real delivery mailer before sending"
                    )
                    continue

                try:
                    response = client.send_approved_draft(int(draft["draft_id"]))
                    status = response.get("status", "unknown")
                    print(f"  result: {status}")

                    if status in {"sent", "already_sent"}:
                        sent += 1
                    else:
                        failed += 1
                except WorkerApiError as error:
                    failed += 1
                    print(f"  failed: {error}", file=sys.stderr)

            print(
                f"Finished. queued={len(drafts)} sent={sent} "
                f"blocked={blocked} failed={failed}"
            )
            return 1 if failed else 0

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
