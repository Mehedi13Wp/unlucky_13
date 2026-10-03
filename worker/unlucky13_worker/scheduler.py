from __future__ import annotations

import time
from dataclasses import dataclass
from datetime import datetime, timedelta, timezone
from typing import Callable, Sequence

from .client import WorkerApiClient, WorkerApiError


CommandRunner = Callable[[Sequence[str]], int]
Clock = Callable[[], datetime]
Sleeper = Callable[[float], None]


@dataclass(frozen=True)
class SchedulerConfig:
    poll_seconds: float = 60.0
    discovery_interval_minutes: int = 360
    discovery_limit: int = 10
    pipeline_limit: int = 10

    def normalized(self) -> "SchedulerConfig":
        return SchedulerConfig(
            poll_seconds=max(10.0, min(float(self.poll_seconds), 3600.0)),
            discovery_interval_minutes=max(
                60,
                min(int(self.discovery_interval_minutes), 10080),
            ),
            discovery_limit=max(1, min(int(self.discovery_limit), 50)),
            pipeline_limit=max(1, min(int(self.pipeline_limit), 25)),
        )


class WorkerScheduler:
    PIPELINE_COMMANDS = ("analyze", "contacts", "drafts")

    def __init__(
        self,
        client: WorkerApiClient,
        command_runner: CommandRunner,
        *,
        clock: Clock | None = None,
        sleeper: Sleeper = time.sleep,
    ) -> None:
        self.client = client
        self.command_runner = command_runner
        self.clock = clock or (lambda: datetime.now(timezone.utc))
        self.sleeper = sleeper

    def run_cycle(
        self,
        config: SchedulerConfig,
        *,
        should_stop: Callable[[], bool] | None = None,
    ) -> dict[str, int]:
        settings = config.normalized()
        stop_requested = should_stop or (lambda: False)
        response = self.client.active_campaigns()
        campaigns = list(response.get("campaigns") or [])

        stats = {
            "active_campaigns": len(campaigns),
            "discovery_due": 0,
            "discovery_completed": 0,
            "discovery_failed": 0,
            "pipeline_failed": 0,
        }

        if not campaigns:
            return stats

        now = self.clock()
        due = [
            campaign
            for campaign in campaigns
            if self.is_discovery_due(
                campaign.get("last_run_at"),
                settings.discovery_interval_minutes,
                now=now,
            )
        ]
        due.sort(key=self._campaign_run_sort_key)
        stats["discovery_due"] = len(due)

        for campaign in due:
            if stop_requested():
                break

            campaign_uuid = str(campaign.get("uuid") or "").strip()
            campaign_name = str(campaign.get("name") or campaign_uuid or "campaign")

            if not campaign_uuid:
                stats["discovery_failed"] += 1
                print(f"[scheduler] skipped campaign without UUID: {campaign_name}")
                continue

            print(f"[scheduler] discovery due: {campaign_name}")

            exit_code = self.command_runner(
                [
                    "discover-osm",
                    "--campaign",
                    campaign_uuid,
                    "--limit",
                    str(settings.discovery_limit),
                ]
            )

            if exit_code != 0:
                stats["discovery_failed"] += 1
                print(
                    f"[scheduler] discovery failed: {campaign_name} "
                    f"(exit={exit_code})"
                )
                continue

            try:
                self.client.record_campaign_run(campaign_uuid)
            except WorkerApiError as error:
                stats["discovery_failed"] += 1
                print(
                    f"[scheduler] could not record discovery run for "
                    f"{campaign_name}: {error}"
                )
                continue

            stats["discovery_completed"] += 1

        for command in self.PIPELINE_COMMANDS:
            if stop_requested():
                break

            print(f"[scheduler] running {command} queue")
            exit_code = self.command_runner(
                [command, "--limit", str(settings.pipeline_limit)]
            )

            if exit_code != 0:
                stats["pipeline_failed"] += 1
                print(
                    f"[scheduler] {command} queue returned exit={exit_code}; "
                    "the scheduler will continue"
                )

        return stats

    def run_forever(
        self,
        config: SchedulerConfig,
        *,
        should_stop: Callable[[], bool] | None = None,
    ) -> None:
        settings = config.normalized()
        stop_requested = should_stop or (lambda: False)

        print(
            "[scheduler] started "
            f"poll={settings.poll_seconds:.0f}s "
            f"discovery_interval={settings.discovery_interval_minutes}m "
            f"discovery_limit={settings.discovery_limit} "
            f"pipeline_limit={settings.pipeline_limit}"
        )
        print(
            "[scheduler] approved-email delivery is NOT automatic; "
            "human-approved sending remains a separate command"
        )

        while not stop_requested():
            try:
                stats = self.run_cycle(settings, should_stop=stop_requested)
                print(
                    "[scheduler] cycle complete "
                    f"active={stats['active_campaigns']} "
                    f"due={stats['discovery_due']} "
                    f"discovery_ok={stats['discovery_completed']} "
                    f"discovery_failed={stats['discovery_failed']} "
                    f"pipeline_failed={stats['pipeline_failed']}"
                )
            except WorkerApiError as error:
                print(f"[scheduler] API unavailable: {error}")
            except Exception as error:
                print(
                    "[scheduler] unexpected cycle error: "
                    f"{type(error).__name__}: {error}"
                )

            remaining = settings.poll_seconds

            while remaining > 0 and not stop_requested():
                step = min(1.0, remaining)
                self.sleeper(step)
                remaining -= step

        print("[scheduler] stop requested; managed worker exiting")

    @staticmethod
    def is_discovery_due(
        last_run_at: object,
        interval_minutes: int,
        *,
        now: datetime | None = None,
    ) -> bool:
        if last_run_at in (None, ""):
            return True

        try:
            parsed = datetime.fromisoformat(
                str(last_run_at).replace("Z", "+00:00")
            )
        except ValueError:
            return True

        if parsed.tzinfo is None:
            parsed = parsed.replace(tzinfo=timezone.utc)

        current = now or datetime.now(timezone.utc)

        if current.tzinfo is None:
            current = current.replace(tzinfo=timezone.utc)

        return current >= parsed.astimezone(timezone.utc) + timedelta(
            minutes=max(1, int(interval_minutes))
        )

    @staticmethod
    def _campaign_run_sort_key(campaign: dict[str, object]) -> tuple[int, str]:
        last_run = campaign.get("last_run_at")

        if last_run in (None, ""):
            return (0, "")

        return (1, str(last_run))
