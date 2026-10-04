import json
import tempfile
import unittest
from pathlib import Path

from worker.unlucky13_worker.analysis_telemetry import write_analysis_telemetry


class AnalysisTelemetryTest(unittest.TestCase):
    def test_writes_last_analysis_snapshot(self):
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            path = write_analysis_telemetry(
                {
                    "queued": 10,
                    "completed": 6,
                    "skipped": 4,
                    "failed": 0,
                },
                repo_root=root,
            )

            payload = json.loads(path.read_text(encoding="utf-8"))

            self.assertEqual(10, payload["queued"])
            self.assertEqual(6, payload["completed"])
            self.assertEqual(4, payload["skipped"])
            self.assertEqual(0, payload["failed"])
            self.assertTrue(payload["attempted_at"])


if __name__ == "__main__":
    unittest.main()
