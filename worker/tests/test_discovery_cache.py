import tempfile
import unittest
from pathlib import Path

from worker.unlucky13_worker.discovery_cache import (
    DiscoveryCache,
    DiscoveryCacheError,
)


class DiscoveryCacheTest(unittest.TestCase):
    def test_saves_and_loads_successful_snapshot(self):
        with tempfile.TemporaryDirectory() as directory:
            cache = DiscoveryCache(Path(directory))
            campaign = {"uuid": "abc-123", "name": "Restaurant Campaign"}
            candidates = [
                {
                    "business_name": "Example Restaurant",
                    "website_url": "https://example.com",
                },
                {
                    "business_name": "Second Restaurant",
                    "website_url": "https://second.example",
                },
            ]

            cache.save(campaign, candidates)
            snapshot = cache.load("abc-123", limit=1)

            self.assertTrue(cache.exists("abc-123"))
            self.assertEqual("abc-123", snapshot["campaign_uuid"])
            self.assertEqual("Restaurant Campaign", snapshot["campaign_name"])
            self.assertEqual(1, len(snapshot["candidates"]))
            self.assertEqual(
                "Example Restaurant",
                snapshot["candidates"][0]["business_name"],
            )

    def test_missing_snapshot_has_clear_error(self):
        with tempfile.TemporaryDirectory() as directory:
            cache = DiscoveryCache(Path(directory))

            with self.assertRaises(DiscoveryCacheError):
                cache.load("missing-campaign")


if __name__ == "__main__":
    unittest.main()
