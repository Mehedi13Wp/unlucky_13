import unittest

from worker.unlucky13_worker.discovery_sources import (
    DiscoverySourceError,
    OpenStreetMapDiscoverySource,
)


class OpenStreetMapDiscoverySourceTest(unittest.TestCase):
    def test_discovers_business_with_public_website(self):
        calls = []

        def requester(method, url, data):
            calls.append((method, url, data))

            if method == "GET":
                return [
                    {
                        "boundingbox": [
                            "40.7000",
                            "40.8000",
                            "-74.0500",
                            "-73.9000",
                        ]
                    }
                ]

            return {
                "elements": [
                    {
                        "type": "node",
                        "id": 123,
                        "tags": {
                            "name": "Example Restaurant",
                            "website": "www.example.com",
                        },
                    },
                    {
                        "type": "node",
                        "id": 456,
                        "tags": {
                            "name": "No Website Restaurant",
                        },
                    },
                ]
            }

        source = OpenStreetMapDiscoverySource(
            user_agent="Unlucky13-Test/1.0",
            requester=requester,
            sleeper=lambda _: None,
        )

        candidates = source.discover(
            {
                "target_industry": "Restaurants",
                "target_countries": ["United States"],
                "target_cities": ["New York"],
            },
            limit=10,
        )

        self.assertEqual(1, len(candidates))
        self.assertEqual("Example Restaurant", candidates[0]["business_name"])
        self.assertEqual("https://www.example.com", candidates[0]["website_url"])
        self.assertEqual("openstreetmap", candidates[0]["source"])
        self.assertEqual("New York", candidates[0]["city"])
        self.assertEqual("United States", candidates[0]["country"])
        self.assertEqual(2, len(calls))
        self.assertEqual("GET", calls[0][0])
        self.assertEqual("POST", calls[1][0])

    def test_unsupported_industry_fails_without_network_request(self):
        called = False

        def requester(method, url, data):
            nonlocal called
            called = True
            return {}

        source = OpenStreetMapDiscoverySource(
            user_agent="Unlucky13-Test/1.0",
            requester=requester,
            sleeper=lambda _: None,
        )

        with self.assertRaises(DiscoverySourceError):
            source.discover(
                {
                    "target_industry": "Custom SaaS buyers",
                    "target_countries": ["United States"],
                    "target_cities": [],
                }
            )

        self.assertFalse(called)


if __name__ == "__main__":
    unittest.main()
