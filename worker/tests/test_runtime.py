import json
import tempfile
import unittest
from pathlib import Path

from worker.unlucky13_worker.runtime import ManagedWorkerRuntime


class ManagedWorkerRuntimeTest(unittest.TestCase):
    def test_cycle_status_is_preserved_across_heartbeat_and_stop_updates(self):
        with tempfile.TemporaryDirectory() as directory:
            runtime = ManagedWorkerRuntime(Path(directory))

            runtime.mark_running()
            runtime.mark_cycle_complete(
                {
                    "active_campaigns": 2,
                    "discovery_due": 1,
                    "discovery_completed": 1,
                    "discovery_failed": 0,
                    "pipeline_failed": 0,
                }
            )
            runtime.mark_stopped()

            state = json.loads(runtime.state_path.read_text(encoding="utf-8"))

            self.assertEqual("stopped", state["state"])
            self.assertIsNotNone(state["last_cycle_at"])
            self.assertEqual(2, state["last_cycle"]["active_campaigns"])
            self.assertEqual(1, state["last_cycle"]["discovery_completed"])


if __name__ == "__main__":
    unittest.main()
