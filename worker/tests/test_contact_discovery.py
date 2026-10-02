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

        result = finder.discover({"website_url": "https://restaurant.example"})
        pairs = {(item["type"], item["value"]) for item in result["contacts"]}

        self.assertIn(("email", "hello@restaurant.example"), pairs)
        self.assertIn(("phone", "+12125550123"), pairs)
        self.assertIn(("contact_page", "https://restaurant.example/contact"), pairs)
        self.assertNotIn(("contact_page", "https://outside.example/contact"), pairs)
        self.assertEqual(2, len(result["pages_inspected"]))

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


if __name__ == "__main__":
    unittest.main()
