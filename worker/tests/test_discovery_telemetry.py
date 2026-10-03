import json
import tempfile
import unittest
from pathlib import Path

from worker.unlucky13_worker.discovery_telemetry import write_discovery_telemetry


class DiscoveryTelemetryTest(unittest.TestCase):
    def test_writer_persists_latest_discovery_summary(self):
        with tempfile.TemporaryDirectory() as directory:
            path = write_discovery_telemetry(
                {
                    "campaign_uuid": "campaign-1",
                    "candidates_scanned": 30,
                    "fresh_created": 7,
                    "api_duplicates": 1,
                    "known_duplicates_skipped": 12,
                    "duplicates_skipped": 13,
                    "failed": 0,
                },
                repo_root=Path(directory),
            )

            data = json.loads(path.read_text(encoding="utf-8"))

            self.assertEqual("campaign-1", data["campaign_uuid"])
            self.assertEqual(7, data["fresh_created"])
            self.assertEqual(13, data["duplicates_skipped"])
            self.assertIn("attempted_at", data)


if __name__ == "__main__":
    unittest.main()
