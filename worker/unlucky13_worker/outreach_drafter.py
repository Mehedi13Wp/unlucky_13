from __future__ import annotations

import re
from typing import Any


class OutreachDraftError(RuntimeError):
    pass


class OutreachDrafter:
    OPPORTUNITY_SIGNALS = (
        "Homepage has sparse visible text content",
        "No obvious conversion call-to-action found on the inspected page",
        "Mobile viewport metadata was not found",
    )

    def generate(self, lead: dict[str, Any]) -> dict[str, Any]:
        business_name = self._clean(lead.get("business_name"))
        if not business_name:
            raise OutreachDraftError("Lead business name is missing.")

        campaign = lead.get("campaign") or {}
        service_offer = self._clean(campaign.get("service_offer"))
        if not service_offer:
            raise OutreachDraftError("Campaign service offer is missing.")

        contact = self._select_contact(lead.get("contacts") or [])
        if contact is None:
            raise OutreachDraftError(
                "Lead has no public email or contact page suitable for a written draft."
            )

        city = self._clean(lead.get("city"))
        industry = self._clean(lead.get("industry"))
        signal = self._select_opportunity_signal(lead.get("observed_signals") or [])

        location_phrase = f" in {city}" if city else ""
        industry_phrase = industry.lower() if industry else "businesses"

        intro = (
            f"I came across {business_name} while reviewing {industry_phrase}"
            f"{location_phrase}, and I took a look at your public website."
        )

        notes = [
            f"Business name from lead record: {business_name}",
        ]

        if city:
            notes.append(f"Campaign geography/lead location: {city}")

        if signal:
            intro += f" One thing I noticed: {self._humanize_signal(signal)}."
            notes.append(f"Observed website signal used: {signal}")

        body = (
            f"Hi {business_name} team,\n\n"
            f"{intro}\n\n"
            f"{service_offer}\n\n"
            "If this is relevant, I can send over 2-3 specific suggestions based on "
            "what is publicly visible on the site.\n\n"
            "Best,\n"
            "Mehedi"
        )

        channel = "email" if contact["type"] == "email" else "contact_form"
        subject = (
            f"Quick website idea for {business_name}"
            if channel == "email"
            else None
        )

        return {
            "contact_id": int(contact["id"]),
            "channel": channel,
            "recipient": str(contact["value"]),
            "subject": subject,
            "body": body,
            "personalization_notes": notes,
        }

    @staticmethod
    def _clean(value: Any) -> str:
        return re.sub(r"\s+", " ", str(value or "")).strip()

    @classmethod
    def _select_opportunity_signal(cls, signals: list[Any]) -> str | None:
        normalized = [cls._clean(signal) for signal in signals]

        for preferred in cls.OPPORTUNITY_SIGNALS:
            for signal in normalized:
                if signal == preferred:
                    return signal

        return None

    @staticmethod
    def _humanize_signal(signal: str) -> str:
        mapping = {
            "Homepage has sparse visible text content": (
                "the homepage has relatively little visible text content"
            ),
            "No obvious conversion call-to-action found on the inspected page": (
                "I did not see an obvious conversion call-to-action on the page I inspected"
            ),
            "Mobile viewport metadata was not found": (
                "mobile viewport metadata was not present on the page I inspected"
            ),
        }
        return mapping.get(signal, signal.rstrip(".").lower())

    @staticmethod
    def _select_contact(contacts: list[dict[str, Any]]) -> dict[str, Any] | None:
        for contact_type in ("email", "contact_page"):
            for contact in contacts:
                if (
                    contact.get("type") == contact_type
                    and contact.get("id")
                    and str(contact.get("value") or "").strip()
                ):
                    return contact

        return None
