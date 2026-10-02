import unittest

from worker.unlucky13_worker.outreach_drafter import (
    OutreachDraftError,
    OutreachDrafter,
)


class OutreachDrafterTest(unittest.TestCase):
    def test_prefers_public_email_and_uses_only_observed_opportunity_signal(self):
        drafter = OutreachDrafter()

        result = drafter.generate(
            {
                "business_name": "Red Bowl",
                "industry": "Restaurants",
                "city": "New York",
                "observed_signals": [
                    "Website uses HTTPS",
                    "Homepage has sparse visible text content",
                ],
                "contacts": [
                    {
                        "id": 2,
                        "type": "contact_page",
                        "value": "https://redbowl.example/contact",
                    },
                    {
                        "id": 1,
                        "type": "email",
                        "value": "hello@redbowl.example",
                    },
                ],
                "campaign": {
                    "service_offer": "I help restaurants improve their websites."
                },
            }
        )

        self.assertEqual("email", result["channel"])
        self.assertEqual("hello@redbowl.example", result["recipient"])
        self.assertEqual(1, result["contact_id"])
        self.assertIn("Quick website idea for Red Bowl", result["subject"])
        self.assertIn(
            "homepage has relatively little visible text content",
            result["body"],
        )
        self.assertNotIn("Website uses HTTPS", result["body"])

    def test_contact_page_is_used_when_email_is_not_publicly_available(self):
        drafter = OutreachDrafter()

        result = drafter.generate(
            {
                "business_name": "Leo's",
                "industry": "Restaurants",
                "city": "New York",
                "observed_signals": ["Website uses HTTPS"],
                "contacts": [
                    {
                        "id": 9,
                        "type": "contact_page",
                        "value": "https://leos.example/contact",
                    }
                ],
                "campaign": {
                    "service_offer": "I help restaurants improve their websites."
                },
            }
        )

        self.assertEqual("contact_form", result["channel"])
        self.assertIsNone(result["subject"])
        self.assertEqual("https://leos.example/contact", result["recipient"])
        self.assertNotIn("One thing I noticed:", result["body"])

    def test_requires_written_public_contact(self):
        drafter = OutreachDrafter()

        with self.assertRaises(OutreachDraftError):
            drafter.generate(
                {
                    "business_name": "Example",
                    "contacts": [
                        {
                            "id": 1,
                            "type": "phone",
                            "value": "+12125550123",
                        }
                    ],
                    "campaign": {
                        "service_offer": "I help businesses improve their websites."
                    },
                }
            )


if __name__ == "__main__":
    unittest.main()
