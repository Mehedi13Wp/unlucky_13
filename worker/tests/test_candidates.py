import json
import tempfile
import unittest
from pathlib import Path

from worker.unlucky13_worker.candidates import load_candidates


class CandidateLoaderTest(unittest.TestCase):
    def test_loads_valid_json_candidates(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "candidates.json"
            path.write_text(
                json.dumps(
                    [
                        {
                            "business_name": "Example Business",
                            "website_url": "https://example.com",
                        }
                    ]
                ),
                encoding="utf-8",
            )

            candidates = load_candidates(path)

        self.assertEqual(1, len(candidates))
        self.assertEqual("Example Business", candidates[0]["business_name"])

    def test_rejects_candidate_without_required_fields(self):
        with tempfile.TemporaryDirectory() as directory:
            path = Path(directory) / "candidates.json"
            path.write_text(
                json.dumps([{"business_name": "Missing Website"}]),
                encoding="utf-8",
            )

            with self.assertRaises(ValueError):
                load_candidates(path)


if __name__ == "__main__":
    unittest.main()
