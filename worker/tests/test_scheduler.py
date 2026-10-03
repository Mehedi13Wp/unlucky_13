import unittest
from datetime import datetime, timezone

from worker.unlucky13_worker.client import WorkerApiError
from worker.unlucky13_worker.scheduler import SchedulerConfig, WorkerScheduler


class FakeClient:
    def __init__(self, campaigns, fail_record=False):
        self.campaigns = campaigns
        self.fail_record = fail_record
        self.recorded = []

    def active_campaigns(self):
        return {"count": len(self.campaigns), "campaigns": self.campaigns}

    def record_campaign_run(self, campaign_uuid):
        if self.fail_record:
            raise WorkerApiError(409, "Campaign paused")
        self.recorded.append(campaign_uuid)
        return {"status": "recorded"}


class WorkerSchedulerTest(unittest.TestCase):
    def test_cycle_runs_due_campaigns_then_processing_queues(self):
        client = FakeClient(
            [
                {
                    "uuid": "fresh-campaign",
                    "name": "Fresh Campaign",
                    "last_run_at": None,
                },
                {
                    "uuid": "recent-campaign",
                    "name": "Recent Campaign",
                    "last_run_at": "2026-10-03T21:30:00+00:00",
                },
            ]
        )
        calls = []

        scheduler = WorkerScheduler(
            client,
            command_runner=lambda args: calls.append(list(args)) or 0,
            clock=lambda: datetime(2026, 10, 3, 22, 0, tzinfo=timezone.utc),
            sleeper=lambda _: None,
        )

        stats = scheduler.run_cycle(
            SchedulerConfig(
                discovery_interval_minutes=360,
                discovery_limit=7,
                pipeline_limit=4,
            )
        )

        self.assertEqual(["fresh-campaign"], client.recorded)
        self.assertEqual(1, stats["discovery_due"])
        self.assertEqual(1, stats["discovery_completed"])
        self.assertEqual(
            [
                ["discover-osm", "--campaign", "fresh-campaign", "--limit", "7"],
                ["analyze", "--limit", "4"],
                ["contacts", "--limit", "4"],
                ["drafts", "--limit", "4"],
            ],
            calls,
        )

    def test_failed_discovery_is_not_recorded_but_pipeline_continues(self):
        client = FakeClient(
            [
                {
                    "uuid": "campaign-1",
                    "name": "Campaign 1",
                    "last_run_at": None,
                }
            ]
        )
        calls = []

        def runner(args):
            calls.append(list(args))
            return 1 if args[0] == "discover-osm" else 0

        scheduler = WorkerScheduler(
            client,
            command_runner=runner,
            clock=lambda: datetime(2026, 10, 3, 22, 0, tzinfo=timezone.utc),
            sleeper=lambda _: None,
        )

        stats = scheduler.run_cycle(SchedulerConfig())

        self.assertEqual([], client.recorded)
        self.assertEqual(1, stats["discovery_failed"])
        self.assertEqual(
            ["analyze", "contacts", "drafts"],
            [call[0] for call in calls[1:]],
        )

    def test_no_active_campaigns_runs_no_commands(self):
        calls = []
        scheduler = WorkerScheduler(
            FakeClient([]),
            command_runner=lambda args: calls.append(list(args)) or 0,
            sleeper=lambda _: None,
        )

        stats = scheduler.run_cycle(SchedulerConfig())

        self.assertEqual(0, stats["active_campaigns"])
        self.assertEqual([], calls)

    def test_due_calculation_uses_persistent_last_run_timestamp(self):
        now = datetime(2026, 10, 3, 22, 0, tzinfo=timezone.utc)

        self.assertTrue(
            WorkerScheduler.is_discovery_due(
                "2026-10-03T15:00:00+00:00",
                360,
                now=now,
            )
        )
        self.assertFalse(
            WorkerScheduler.is_discovery_due(
                "2026-10-03T21:00:00+00:00",
                360,
                now=now,
            )
        )
        self.assertTrue(
            WorkerScheduler.is_discovery_due(None, 360, now=now)
        )

    def test_run_forever_reports_completed_cycle_to_runtime_callback(self):
        stopped = {"value": False}
        snapshots = []

        scheduler = WorkerScheduler(
            FakeClient([]),
            command_runner=lambda args: 0,
            sleeper=lambda _: None,
        )

        def on_cycle_complete(stats):
            snapshots.append(stats)
            stopped["value"] = True

        scheduler.run_forever(
            SchedulerConfig(),
            should_stop=lambda: stopped["value"],
            on_cycle_complete=on_cycle_complete,
        )

        self.assertEqual(1, len(snapshots))
        self.assertEqual(0, snapshots[0]["active_campaigns"])

    def test_record_failure_does_not_stop_later_pipeline_work(self):
        client = FakeClient(
            [
                {
                    "uuid": "campaign-1",
                    "name": "Campaign 1",
                    "last_run_at": None,
                }
            ],
            fail_record=True,
        )
        calls = []

        scheduler = WorkerScheduler(
            client,
            command_runner=lambda args: calls.append(list(args)) or 0,
            sleeper=lambda _: None,
        )

        stats = scheduler.run_cycle(SchedulerConfig())

        self.assertEqual(1, stats["discovery_failed"])
        self.assertEqual(
            ["analyze", "contacts", "drafts"],
            [call[0] for call in calls[1:]],
        )


if __name__ == "__main__":
    unittest.main()
