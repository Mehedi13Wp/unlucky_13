from __future__ import annotations

import re
from html.parser import HTMLParser
from typing import Any
from urllib.parse import urljoin, urlparse

from .website_analyzer import AnalyzerError, WebsiteFetcher, WebsiteSnapshot


class ContactDiscoveryError(RuntimeError):
    pass


class _ContactParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.links: list[tuple[str, str]] = []
        self.text_parts: list[str] = []
        self._skip_depth = 0
        self._active_href: str | None = None
        self._active_text: list[str] = []

    def handle_starttag(self, tag: str, attrs) -> None:
        attrs_dict = {str(key).lower(): value for key, value in attrs}
        lowered = tag.lower()

        if lowered in {"script", "style", "noscript", "svg"}:
            self._skip_depth += 1

        if lowered == "a":
            self._active_href = str(attrs_dict.get("href") or "")
            self._active_text = []

    def handle_endtag(self, tag: str) -> None:
        lowered = tag.lower()

        if lowered == "a" and self._active_href is not None:
            self.links.append((self._active_href, " ".join(self._active_text).strip()))
            self._active_href = None
            self._active_text = []

        if lowered in {"script", "style", "noscript", "svg"} and self._skip_depth > 0:
            self._skip_depth -= 1

    def handle_data(self, data: str) -> None:
        if self._skip_depth:
            return

        clean = " ".join(data.split())
        if not clean:
            return

        self.text_parts.append(clean)

        if self._active_href is not None:
            self._active_text.append(clean)


class ContactFinder:
    CONTACT_KEYWORDS = (
        "contact",
        "about",
        "support",
        "reservation",
        "reservations",
        "location",
        "locations",
        "visit",
        "reach",
    )
    EMAIL_PATTERN = re.compile(
        r"\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b",
        re.I,
    )

    def __init__(
        self,
        timeout_seconds: float = 15.0,
        max_pages: int = 3,
        fetcher: WebsiteFetcher | None = None,
    ) -> None:
        self.fetcher = fetcher or WebsiteFetcher(timeout_seconds=timeout_seconds)
        self.max_pages = max(1, min(int(max_pages), 5))

    def discover(self, lead: dict[str, Any]) -> dict[str, Any]:
        website_url = str(lead.get("website_url") or "").strip()

        if not website_url:
            raise ContactDiscoveryError("Lead has no website URL.")

        try:
            homepage = self.fetcher.fetch(website_url)
        except (AnalyzerError, TimeoutError) as error:
            raise ContactDiscoveryError(str(error)) from error

        contacts: list[dict[str, str | None]] = []
        notes: list[str] = []
        inspected_urls: list[str] = []

        self._extract_from_snapshot(homepage, contacts, inspected_urls)
        contact_pages = self._contact_page_urls(homepage)

        for url in contact_pages[: max(0, self.max_pages - 1)]:
            self._append_contact(
                contacts,
                {
                    "type": "contact_page",
                    "value": url,
                    "label": "Public contact page",
                    "source_url": homepage.final_url,
                },
            )

            try:
                snapshot = self.fetcher.fetch(url)
            except (AnalyzerError, TimeoutError) as error:
                notes.append(f"Could not inspect contact page {url}: {error}")
                continue

            self._extract_from_snapshot(snapshot, contacts, inspected_urls)

        direct_contacts = [
            contact
            for contact in contacts
            if contact["type"] in {"email", "phone"}
        ]

        notes.insert(0, f"Inspected {len(inspected_urls)} public page(s) on the lead website.")

        if not direct_contacts:
            notes.append(
                "No public email address or telephone link was found on the inspected pages."
            )

        priority = {"email": 0, "contact_page": 1, "phone": 2}
        contacts.sort(key=lambda contact: priority.get(str(contact["type"]), 9))

        return {
            "contacts": contacts[:10],
            "notes": notes,
            "pages_inspected": inspected_urls,
        }

    def _extract_from_snapshot(
        self,
        snapshot: WebsiteSnapshot,
        contacts: list[dict[str, str | None]],
        inspected_urls: list[str],
    ) -> None:
        parser = _ContactParser()
        parser.feed(snapshot.html)
        inspected_urls.append(snapshot.final_url)

        text = " ".join(parser.text_parts)

        for href, anchor in parser.links:
            lowered = href.strip().lower()

            if lowered.startswith("mailto:"):
                email = href.split(":", 1)[1].split("?", 1)[0].strip().lower()
                if self._valid_email(email):
                    self._append_contact(
                        contacts,
                        {
                            "type": "email",
                            "value": email,
                            "label": anchor or "Public email",
                            "source_url": snapshot.final_url,
                        },
                    )

            if lowered.startswith("tel:"):
                phone = href.split(":", 1)[1].split("?", 1)[0].strip()
                if phone:
                    self._append_contact(
                        contacts,
                        {
                            "type": "phone",
                            "value": phone,
                            "label": anchor or "Public phone",
                            "source_url": snapshot.final_url,
                        },
                    )

        for email in self.EMAIL_PATTERN.findall(text):
            normalized = email.strip().lower().rstrip(".,;:")
            if self._valid_email(normalized):
                self._append_contact(
                    contacts,
                    {
                        "type": "email",
                        "value": normalized,
                        "label": "Public email",
                        "source_url": snapshot.final_url,
                    },
                )

    def _contact_page_urls(self, homepage: WebsiteSnapshot) -> list[str]:
        parser = _ContactParser()
        parser.feed(homepage.html)

        base_host = self._normalized_host(homepage.final_url)
        candidates: list[str] = []

        for href, anchor in parser.links:
            raw = href.strip()

            if not raw or raw.startswith(("#", "mailto:", "tel:", "javascript:")):
                continue

            joined = urljoin(homepage.final_url, raw)
            parsed = urlparse(joined)

            if parsed.scheme not in {"http", "https"}:
                continue

            if self._normalized_host(joined) != base_host:
                continue

            searchable = f"{parsed.path} {anchor}".lower()

            if not any(keyword in searchable for keyword in self.CONTACT_KEYWORDS):
                continue

            normalized = parsed._replace(fragment="").geturl()

            if normalized != homepage.final_url and normalized not in candidates:
                candidates.append(normalized)

        return candidates

    @staticmethod
    def _append_contact(
        contacts: list[dict[str, str | None]],
        contact: dict[str, str | None],
    ) -> None:
        key = (contact["type"], str(contact["value"]).strip().lower())

        if any(
            (existing["type"], str(existing["value"]).strip().lower()) == key
            for existing in contacts
        ):
            return

        contacts.append(contact)

    @staticmethod
    def _normalized_host(url: str) -> str:
        host = (urlparse(url).hostname or "").lower().rstrip(".")
        return host[4:] if host.startswith("www.") else host

    @staticmethod
    def _valid_email(value: str) -> bool:
        if not value or len(value) > 254:
            return False

        lowered = value.lower()

        if lowered.endswith(("@example.com", "@example.org", "@example.net")):
            return False

        return bool(ContactFinder.EMAIL_PATTERN.fullmatch(value))
