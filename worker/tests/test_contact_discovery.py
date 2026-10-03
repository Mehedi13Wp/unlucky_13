import unittest

from worker.unlucky13_worker.contact_discovery import ContactFinder
from worker.unlucky13_worker.website_analyzer import WebsiteSnapshot


class FakeFetcher:
    def __init__(self, pages):
        self.pages = pages

    def fetch(self, url):
        return self.pages[url]


class ContactFinderTest(unittest.TestCase):
    def test_finds_public_email_phone_and_same_domain_contact_page(self):
        home = WebsiteSnapshot(
            requested_url="https://restaurant.example",
            final_url="https://restaurant.example",
            status_code=200,
            content_type="text/html",
            html='<html><body><a href="/contact">Contact us</a><a href="https://outside.example/contact">Outside</a></body></html>',
        )
        contact = WebsiteSnapshot(
            requested_url="https://restaurant.example/contact",
            final_url="https://restaurant.example/contact",
            status_code=200,
            content_type="text/html",
            html='<html><body><a href="mailto:hello@restaurant.example">Email us</a><a href="tel:+12125550123">Call us</a></body></html>',
        )

        finder = ContactFinder(
            fetcher=FakeFetcher(
                {
                    "https://restaurant.example": home,
                    "https://restaurant.example/contact": contact,
                }
            )
        )

        result = finder.discover(
            {
                "website_url": "https://restaurant.example",
                "country": "United States",
            }
        )
        pairs = {(item["type"], item["value"]) for item in result["contacts"]}

        self.assertIn(("email", "hello@restaurant.example"), pairs)
        self.assertIn(("phone", "+12125550123"), pairs)
        self.assertIn(("contact_page", "https://restaurant.example/contact"), pairs)
        self.assertNotIn(("contact_page", "https://outside.example/contact"), pairs)
        self.assertEqual(2, len(result["pages_inspected"]))

    def test_finds_official_social_links_from_the_business_website(self):
        home = WebsiteSnapshot(
            requested_url="https://agency.example",
            final_url="https://agency.example",
            status_code=200,
            content_type="text/html",
            html="""
                <html><body>
                    <a href="https://www.facebook.com/AgencyExample">Facebook</a>
                    <a href="https://instagram.com/agencyexample/?utm_source=site">Instagram</a>
                    <a href="https://www.linkedin.com/company/agency-example/">LinkedIn</a>
                    <a href="https://www.facebook.com/sharer/sharer.php?u=https://agency.example">Share</a>
                    <a href="https://www.instagram.com/p/ABC123/">Post</a>
                </body></html>
            """,
        )

        finder = ContactFinder(fetcher=FakeFetcher({"https://agency.example": home}))
        result = finder.discover({"website_url": "https://agency.example"})

        pairs = {(item["type"], item["value"]) for item in result["contacts"]}

        self.assertIn(("facebook", "https://www.facebook.com/AgencyExample"), pairs)
        self.assertIn(("instagram", "https://instagram.com/agencyexample"), pairs)
        self.assertIn(
            ("linkedin", "https://www.linkedin.com/company/agency-example"),
            pairs,
        )
        self.assertNotIn(
            ("facebook", "https://www.facebook.com/sharer/sharer.php"),
            pairs,
        )
        self.assertFalse(
            any(
                item["type"] == "instagram" and "/p/" in item["value"]
                for item in result["contacts"]
            )
        )

    def test_visible_email_on_homepage_is_recorded(self):
        home = WebsiteSnapshot(
            requested_url="https://restaurant.example",
            final_url="https://restaurant.example",
            status_code=200,
            content_type="text/html",
            html="<html><body>Write to bookings@restaurant.example</body></html>",
        )

        finder = ContactFinder(fetcher=FakeFetcher({"https://restaurant.example": home}))
        result = finder.discover({"website_url": "https://restaurant.example"})

        self.assertEqual("email", result["contacts"][0]["type"])
        self.assertEqual("bookings@restaurant.example", result["contacts"][0]["value"])

    def test_us_phone_numbers_are_normalized_for_deduplication(self):
        home = WebsiteSnapshot(
            requested_url="https://restaurant.example",
            final_url="https://restaurant.example",
            status_code=200,
            content_type="text/html",
            html="""
                <html><body>
                    <a href="tel:+13474920990">Call</a>
                    <a href="tel:3474920990">Call local</a>
                </body></html>
            """,
        )

        finder = ContactFinder(fetcher=FakeFetcher({"https://restaurant.example": home}))
        result = finder.discover(
            {
                "website_url": "https://restaurant.example",
                "country": "United States",
            }
        )

        phones = [
            item["value"]
            for item in result["contacts"]
            if item["type"] == "phone"
        ]

        self.assertEqual(["+13474920990"], phones)

    def test_failed_or_irrelevant_contact_pages_are_not_saved(self):
        home = WebsiteSnapshot(
            requested_url="https://restaurant.example",
            final_url="https://restaurant.example",
            status_code=200,
            content_type="text/html",
            html="""
                <html><body>
                    <a href="/contact">Contact</a>
                    <a href="/accessible-compliance.php">Accessibility Compliance</a>
                </body></html>
            """,
        )
        contact = WebsiteSnapshot(
            requested_url="https://restaurant.example/contact",
            final_url="https://restaurant.example/contact",
            status_code=200,
            content_type="text/html",
            html="<html><body>Contact form</body></html>",
        )

        finder = ContactFinder(
            fetcher=FakeFetcher(
                {
                    "https://restaurant.example": home,
                    "https://restaurant.example/contact": contact,
                }
            )
        )
        result = finder.discover({"website_url": "https://restaurant.example"})

        pages = [
            item["value"]
            for item in result["contacts"]
            if item["type"] == "contact_page"
        ]

        self.assertEqual(["https://restaurant.example/contact"], pages)

if __name__ == "__main__":
    unittest.main()
