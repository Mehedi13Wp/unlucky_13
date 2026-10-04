import unittest

from worker.unlucky13_worker.website_analyzer import (
    AnalyzerError,
    WebsiteAnalyzer,
    WebsiteFetcher,
    WebsiteSnapshot,
    ensure_public_url,
)


class FakeFetcher:
    def __init__(self, html):
        self.html = html

    def fetch(self, url):
        return WebsiteSnapshot(
            requested_url=url,
            final_url=url,
            status_code=200,
            content_type="text/html; charset=utf-8",
            html=self.html,
        )


class WebsiteAnalyzerTest(unittest.TestCase):
    def test_blocks_local_network_targets(self):
        for url in (
            "http://127.0.0.1",
            "http://localhost",
            "http://192.168.1.10",
            "ftp://example.com",
        ):
            with self.subTest(url=url):
                with self.assertRaises(AnalyzerError):
                    ensure_public_url(url)

    def test_fetcher_builds_www_and_apex_fallbacks_without_downgrading_https(self):
        self.assertEqual(
            [
                "https://agency.example/contact?from=home",
                "https://www.agency.example/contact?from=home",
            ],
            WebsiteFetcher.candidate_urls(
                "https://agency.example/contact?from=home"
            ),
        )
        self.assertEqual(
            [
                "https://www.agency.example",
                "https://agency.example",
            ],
            WebsiteFetcher.candidate_urls("https://www.agency.example"),
        )

    def test_scores_observed_public_signals(self):
        html = """
        <html>
        <head>
            <title>Boston Table Restaurant</title>
            <meta name="viewport" content="width=device-width, initial-scale=1">
        </head>
        <body>
            <h1>Boston Table Restaurant</h1>
            <p>Fresh dinner and lunch in Boston. Family restaurant.</p>
            <a href="/contact">Contact us</a>
            <a href="tel:+15555550123">Call</a>
        </body>
        </html>
        """

        analyzer = WebsiteAnalyzer(fetcher=FakeFetcher(html))
        result = analyzer.analyze(
            {
                "website_url": "https://example.com",
                "industry": "Restaurants",
                "country": "United States",
                "city": "Boston",
                "campaign": {
                    "target_industry": "restrunt",
                    "target_countries": ["usa"],
                    "target_cities": ["Boston"],
                    "qualification_rules": {
                        "requires_public_contact": True,
                    },
                },
            }
        )

        self.assertGreaterEqual(result["score"], 60)
        self.assertTrue(
            any("Public contact path" in signal for signal in result["signals"])
        )
        self.assertTrue(
            any("campaign geography" in signal for signal in result["signals"])
        )

    def test_required_public_contact_caps_score_when_missing(self):
        html = """
        <html>
        <head><title>Restaurant</title></head>
        <body><h1>Restaurant</h1><p>Restaurant dining menu.</p></body>
        </html>
        """

        analyzer = WebsiteAnalyzer(fetcher=FakeFetcher(html))
        result = analyzer.analyze(
            {
                "website_url": "https://example.com",
                "industry": "Restaurants",
                "country": "United States",
                "city": "Boston",
                "campaign": {
                    "target_industry": "Restaurants",
                    "target_countries": ["United States"],
                    "target_cities": ["Boston"],
                    "qualification_rules": {
                        "requires_public_contact": True,
                    },
                },
            }
        )

        self.assertLessEqual(result["score"], 55)
        self.assertTrue(
            any("No obvious public contact" in signal for signal in result["signals"])
        )


if __name__ == "__main__":
    unittest.main()
