from __future__ import annotations

import json
import re
from html.parser import HTMLParser
from typing import Any
from urllib.parse import unquote, urljoin, urlparse

from .website_analyzer import AnalyzerError, WebsiteFetcher, WebsiteSnapshot


class ContactDiscoveryError(RuntimeError):
    pass


class _ContactParser(HTMLParser):
    EMAIL_HINT_ATTRIBUTES = (
        "data-email",
        "data-mail",
        "data-contact-email",
    )

    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.links: list[tuple[str, str]] = []
        self.text_parts: list[str] = []
        self.json_ld_blocks: list[str] = []
        self.email_hints: list[str] = []
        self.cfemail_tokens: list[str] = []
        self._skip_depth = 0
        self._active_href: str | None = None
        self._active_text: list[str] = []
        self._in_json_ld = False
        self._json_ld_parts: list[str] = []

    def handle_starttag(self, tag: str, attrs) -> None:
        attrs_dict = {str(key).lower(): value for key, value in attrs}
        lowered = tag.lower()

        if lowered == "script":
            script_type = str(attrs_dict.get("type") or "").lower()

            if "ld+json" in script_type:
                self._in_json_ld = True
                self._json_ld_parts = []
                return

            self._skip_depth += 1
            return

        if lowered in {"style", "noscript", "svg"}:
            self._skip_depth += 1
            return

        if self._skip_depth:
            return

        if lowered == "a":
            self._active_href = str(attrs_dict.get("href") or "")
            self._active_text = []

        for attribute in self.EMAIL_HINT_ATTRIBUTES:
            value = str(attrs_dict.get(attribute) or "").strip()
            if value:
                self.email_hints.append(value)

        cfemail = str(attrs_dict.get("data-cfemail") or "").strip()
        if cfemail:
            self.cfemail_tokens.append(cfemail)

        itemprop = str(attrs_dict.get("itemprop") or "").strip().lower()
        if itemprop == "email":
            for attribute in ("content", "value", "href"):
                value = str(attrs_dict.get(attribute) or "").strip()
                if value:
                    self.email_hints.append(value)

    def handle_endtag(self, tag: str) -> None:
        lowered = tag.lower()

        if lowered == "script":
            if self._in_json_ld:
                block = "".join(self._json_ld_parts).strip()
                if block:
                    self.json_ld_blocks.append(block)
                self._in_json_ld = False
                self._json_ld_parts = []
                return

            if self._skip_depth > 0:
                self._skip_depth -= 1
            return

        if lowered in {"style", "noscript", "svg"}:
            if self._skip_depth > 0:
                self._skip_depth -= 1
            return

        if self._skip_depth:
            return

        if lowered == "a" and self._active_href is not None:
            self.links.append((self._active_href, " ".join(self._active_text).strip()))
            self._active_href = None
            self._active_text = []

    def handle_data(self, data: str) -> None:
        if self._in_json_ld:
            self._json_ld_parts.append(data)
            return

        if self._skip_depth:
            return

        clean = " ".join(data.split())
        if not clean:
            return

        self.text_parts.append(clean)

        if self._active_href is not None:
            self._active_text.append(clean)


class ContactFinder:
    CONTACT_PAGE_TERMS = (
        "contact",
        "get in touch",
        "reach us",
        "support",
        "reservation",
        "reservations",
        "location",
        "locations",
        "visit us",
    )
    PAGE_KEYWORD_WEIGHTS = (
        ("contact", 100),
        ("get in touch", 100),
        ("reach us", 98),
        ("agents", 96),
        ("brokers", 96),
        ("our team", 94),
        ("team", 92),
        ("staff", 90),
        ("agent", 88),
        ("broker", 88),
        ("people", 86),
        ("office", 84),
        ("offices", 84),
        ("leadership", 80),
        ("support", 78),
        ("about us", 76),
        ("about", 72),
        ("company", 70),
        ("location", 68),
        ("locations", 68),
        ("visit", 64),
        ("reservation", 60),
        ("reservations", 60),
    )
    EXCLUDED_PAGE_TERMS = (
        "privacy",
        "terms",
        "accessibility",
        "accessible",
        "compliance",
        "cookie",
        "legal",
        "login",
        "sign in",
        "signin",
        "register",
        "cart",
        "checkout",
        "wishlist",
        "press",
        "blog",
        "news",
    )
    EMAIL_PATTERN = re.compile(
        r"\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b",
        re.I,
    )
    OBFUSCATED_AT_PATTERN = re.compile(r"\s*(?:\[\s*at\s*\]|\(\s*at\s*\))\s*", re.I)
    OBFUSCATED_DOT_PATTERN = re.compile(r"\s*(?:\[\s*dot\s*\]|\(\s*dot\s*\))\s*", re.I)
    SOCIAL_HOSTS = {
        "facebook": ("facebook.com", "fb.com"),
        "instagram": ("instagram.com",),
        "linkedin": ("linkedin.com",),
    }
    EXCLUDED_SOCIAL_PATH_PREFIXES = {
        "facebook": (
            "/sharer",
            "/share",
            "/dialog",
            "/plugins",
            "/login",
        ),
        "instagram": (
            "/p/",
            "/reel/",
            "/reels/",
            "/stories/",
            "/explore/",
            "/accounts/",
        ),
        "linkedin": (
            "/sharing/",
            "/sharearticle",
            "/feed/",
            "/login",
        ),
    }
    STRUCTURED_EMAIL_KEYS = {"email", "emailaddress"}
    STRUCTURED_PHONE_KEYS = {"telephone", "phone", "phonenumber"}

    def __init__(
        self,
        timeout_seconds: float = 15.0,
        max_pages: int = 5,
        fetcher: WebsiteFetcher | None = None,
    ) -> None:
        self.fetcher = fetcher or WebsiteFetcher(timeout_seconds=timeout_seconds)
        self.max_pages = max(1, min(int(max_pages), 8))

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
        country = str(lead.get("country") or "").strip()
        base_host = self._normalized_host(homepage.final_url)

        homepage_parser = self._extract_from_snapshot(
            homepage,
            contacts,
            inspected_urls,
            country,
        )

        candidates: dict[str, dict[str, Any]] = {}
        visited = {self._canonical_page_url(homepage.final_url)}

        self._merge_page_candidates(
            candidates,
            self._candidate_page_urls(homepage, homepage_parser, base_host),
            visited,
        )

        while candidates and len(inspected_urls) < self.max_pages:
            candidate_key, candidate = max(
                candidates.items(),
                key=lambda item: int(item[1]["score"]),
            )
            candidates.pop(candidate_key, None)
            visited.add(candidate_key)

            try:
                snapshot = self.fetcher.fetch(str(candidate["url"]))
            except (AnalyzerError, TimeoutError, KeyError) as error:
                notes.append(
                    f"Could not inspect public page {candidate['url']}: {error}"
                )
                continue

            final_key = self._canonical_page_url(snapshot.final_url)
            visited.add(final_key)

            if self._normalized_host(snapshot.final_url) != base_host:
                notes.append(
                    f"Skipped redirected page outside the business website: {snapshot.final_url}"
                )
                continue

            if bool(candidate["contact_page"]):
                self._append_contact(
                    contacts,
                    {
                        "type": "contact_page",
                        "value": snapshot.final_url,
                        "label": "Public contact page",
                        "source_url": str(candidate["source_url"]),
                    },
                )

            parser = self._extract_from_snapshot(
                snapshot,
                contacts,
                inspected_urls,
                country,
            )

            self._merge_page_candidates(
                candidates,
                self._candidate_page_urls(snapshot, parser, base_host),
                visited,
            )

        contacts = self._rank_and_limit_contacts(contacts, base_host)

        notes.insert(
            0,
            (
                f"Smart contact discovery inspected {len(inspected_urls)} public page(s) "
                "and checked visible text, mail links, structured data, encoded email "
                "attributes, social links, and high-value business pages."
            ),
        )

        if not any(contact["type"] == "email" for contact in contacts):
            notes.append("No public email address was found on the inspected pages.")

        if not any(contact["type"] == "phone" for contact in contacts):
            notes.append("No public telephone link or structured phone number was found.")

        return {
            "contacts": contacts,
            "notes": notes,
            "pages_inspected": inspected_urls,
        }

    def _extract_from_snapshot(
        self,
        snapshot: WebsiteSnapshot,
        contacts: list[dict[str, str | None]],
        inspected_urls: list[str],
        country: str = "",
    ) -> _ContactParser:
        parser = _ContactParser()
        parser.feed(snapshot.html)

        if snapshot.final_url not in inspected_urls:
            inspected_urls.append(snapshot.final_url)

        text = " ".join(parser.text_parts)

        for href, anchor in parser.links:
            raw_href = href.strip()
            lowered = raw_href.lower()

            if lowered.startswith("mailto:"):
                mail_value = unquote(raw_href.split(":", 1)[1].split("?", 1)[0])
                for email in self._emails_from_text(mail_value):
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
                phone = unquote(raw_href.split(":", 1)[1].split("?", 1)[0]).strip()
                normalized_phone = self._normalize_phone(phone, country)

                if normalized_phone:
                    self._append_contact(
                        contacts,
                        {
                            "type": "phone",
                            "value": normalized_phone,
                            "label": anchor or "Public phone",
                            "source_url": snapshot.final_url,
                        },
                    )

            social = self._social_contact(raw_href, snapshot.final_url)

            if social is not None:
                self._append_contact(contacts, social)

        for email in self._emails_from_text(text):
            self._append_contact(
                contacts,
                {
                    "type": "email",
                    "value": email,
                    "label": "Public email",
                    "source_url": snapshot.final_url,
                },
            )

        for hint in parser.email_hints:
            for email in self._emails_from_text(unquote(hint)):
                self._append_contact(
                    contacts,
                    {
                        "type": "email",
                        "value": email,
                        "label": "Public email",
                        "source_url": snapshot.final_url,
                    },
                )

        for token in parser.cfemail_tokens:
            email = self._decode_cfemail(token)
            if email and self._valid_email(email):
                self._append_contact(
                    contacts,
                    {
                        "type": "email",
                        "value": email.lower(),
                        "label": "Public email",
                        "source_url": snapshot.final_url,
                    },
                )

        for block in parser.json_ld_blocks:
            self._extract_structured_data(
                block,
                contacts,
                snapshot.final_url,
                country,
            )

        return parser

    def _candidate_page_urls(
        self,
        snapshot: WebsiteSnapshot,
        parser: _ContactParser,
        base_host: str,
    ) -> list[dict[str, Any]]:
        candidates: list[dict[str, Any]] = []

        for href, anchor in parser.links:
            raw = href.strip()

            if not raw or raw.lower().startswith(
                ("#", "mailto:", "tel:", "javascript:", "data:")
            ):
                continue

            joined = urljoin(snapshot.final_url, raw)
            parsed = urlparse(joined)

            if parsed.scheme not in {"http", "https"}:
                continue

            if self._normalized_host(joined) != base_host:
                continue

            normalized_search = self._searchable_page_text(parsed.path, anchor)

            if any(term in normalized_search for term in self.EXCLUDED_PAGE_TERMS):
                continue

            score = 0
            for keyword, weight in self.PAGE_KEYWORD_WEIGHTS:
                if keyword in normalized_search:
                    score = max(score, weight)

            if score <= 0:
                continue

            url = parsed._replace(fragment="").geturl()
            if self._canonical_page_url(url) == self._canonical_page_url(snapshot.final_url):
                continue

            candidates.append(
                {
                    "url": url,
                    "score": score,
                    "contact_page": any(
                        term in normalized_search
                        for term in self.CONTACT_PAGE_TERMS
                    ),
                    "source_url": snapshot.final_url,
                }
            )

        return candidates

    @staticmethod
    def _merge_page_candidates(
        candidates: dict[str, dict[str, Any]],
        additions: list[dict[str, Any]],
        visited: set[str],
    ) -> None:
        for candidate in additions:
            key = ContactFinder._canonical_page_url(str(candidate["url"]))

            if key in visited:
                continue

            existing = candidates.get(key)
            if existing is None or int(candidate["score"]) > int(existing["score"]):
                candidates[key] = candidate
            elif bool(candidate["contact_page"]) and not bool(existing["contact_page"]):
                existing["contact_page"] = True

    def _extract_structured_data(
        self,
        block: str,
        contacts: list[dict[str, str | None]],
        source_url: str,
        country: str,
    ) -> None:
        cleaned = block.strip().rstrip(";")

        try:
            payload = json.loads(cleaned)
        except (json.JSONDecodeError, TypeError):
            return

        self._walk_structured_data(payload, contacts, source_url, country)

    def _walk_structured_data(
        self,
        value: Any,
        contacts: list[dict[str, str | None]],
        source_url: str,
        country: str,
    ) -> None:
        if isinstance(value, list):
            for item in value:
                self._walk_structured_data(item, contacts, source_url, country)
            return

        if not isinstance(value, dict):
            return

        for key, item in value.items():
            normalized_key = re.sub(r"[^a-z0-9]+", "", str(key).lower())

            if normalized_key in self.STRUCTURED_EMAIL_KEYS:
                for candidate in self._structured_values(item):
                    for email in self._emails_from_text(candidate):
                        self._append_contact(
                            contacts,
                            {
                                "type": "email",
                                "value": email,
                                "label": "Public email (structured data)",
                                "source_url": source_url,
                            },
                        )

            elif normalized_key in self.STRUCTURED_PHONE_KEYS:
                for candidate in self._structured_values(item):
                    phone = self._normalize_phone(candidate, country)
                    if phone:
                        self._append_contact(
                            contacts,
                            {
                                "type": "phone",
                                "value": phone,
                                "label": "Public phone (structured data)",
                                "source_url": source_url,
                            },
                        )

            elif normalized_key == "sameas":
                for candidate in self._structured_values(item):
                    social = self._social_contact(candidate, source_url)
                    if social is not None:
                        self._append_contact(contacts, social)

            if isinstance(item, (dict, list)):
                self._walk_structured_data(item, contacts, source_url, country)

    @staticmethod
    def _structured_values(value: Any) -> list[str]:
        if isinstance(value, str):
            return [value]

        if isinstance(value, (int, float)):
            return [str(value)]

        if isinstance(value, list):
            return [str(item) for item in value if isinstance(item, (str, int, float))]

        return []

    @classmethod
    def _emails_from_text(cls, value: str) -> list[str]:
        text = unquote(str(value or ""))
        text = cls.OBFUSCATED_AT_PATTERN.sub("@", text)
        text = cls.OBFUSCATED_DOT_PATTERN.sub(".", text)

        emails: list[str] = []

        for match in cls.EMAIL_PATTERN.findall(text):
            normalized = match.strip().lower().rstrip(".,;:")
            if cls._valid_email(normalized) and normalized not in emails:
                emails.append(normalized)

        return emails

    @classmethod
    def _social_contact(
        cls,
        href: str,
        source_url: str,
    ) -> dict[str, str | None] | None:
        raw = href.strip()

        if not raw or raw.lower().startswith(
            ("#", "mailto:", "tel:", "javascript:", "data:")
        ):
            return None

        absolute = urljoin(source_url, raw)
        parsed = urlparse(absolute)

        if parsed.scheme not in {"http", "https"}:
            return None

        host = (parsed.hostname or "").lower().rstrip(".")
        if host.startswith("www."):
            host = host[4:]

        social_type: str | None = None

        for candidate_type, domains in cls.SOCIAL_HOSTS.items():
            if any(host == domain or host.endswith("." + domain) for domain in domains):
                social_type = candidate_type
                break

        if social_type is None:
            return None

        path = parsed.path or "/"
        lowered_path = path.lower()

        if lowered_path in {"", "/"}:
            return None

        if any(
            lowered_path.startswith(prefix)
            for prefix in cls.EXCLUDED_SOCIAL_PATH_PREFIXES[social_type]
        ):
            return None

        normalized = parsed._replace(query="", fragment="").geturl().rstrip("/")

        return {
            "type": social_type,
            "value": normalized,
            "label": f"Official {social_type.title()}",
            "source_url": source_url,
        }

    @classmethod
    def _rank_and_limit_contacts(
        cls,
        contacts: list[dict[str, str | None]],
        website_host: str,
    ) -> list[dict[str, str | None]]:
        type_priority = {
            "email": 0,
            "contact_page": 1,
            "phone": 2,
            "facebook": 3,
            "instagram": 4,
            "linkedin": 5,
        }
        caps = {
            "email": 4,
            "contact_page": 1,
            "phone": 2,
            "facebook": 1,
            "instagram": 1,
            "linkedin": 1,
        }

        indexed = list(enumerate(contacts))

        def sort_key(item: tuple[int, dict[str, str | None]]) -> tuple[int, int, int]:
            index, contact = item
            contact_type = str(contact["type"])
            domain_rank = 0

            if contact_type == "email":
                value = str(contact["value"] or "")
                email_domain = value.rsplit("@", 1)[-1].lower() if "@" in value else ""
                domain_rank = 0 if cls._domains_related(email_domain, website_host) else 1

            return (type_priority.get(contact_type, 9), domain_rank, index)

        selected: list[dict[str, str | None]] = []
        counts: dict[str, int] = {}

        for _, contact in sorted(indexed, key=sort_key):
            contact_type = str(contact["type"])
            limit = caps.get(contact_type, 1)

            if counts.get(contact_type, 0) >= limit:
                continue

            selected.append(contact)
            counts[contact_type] = counts.get(contact_type, 0) + 1

            if len(selected) >= 10:
                break

        return selected

    @staticmethod
    def _domains_related(left: str, right: str) -> bool:
        left = left.lower().strip(".")
        right = right.lower().strip(".")

        if not left or not right:
            return False

        return (
            left == right
            or left.endswith("." + right)
            or right.endswith("." + left)
        )

    @staticmethod
    def _decode_cfemail(value: str) -> str | None:
        token = value.strip()

        try:
            payload = bytes.fromhex(token)
        except ValueError:
            return None

        if len(payload) < 2:
            return None

        key = payload[0]

        try:
            return "".join(chr(byte ^ key) for byte in payload[1:])
        except (ValueError, OverflowError):
            return None

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
    def _searchable_page_text(path: str, anchor: str) -> str:
        combined = f"{path} {anchor}".lower()
        return re.sub(r"[^a-z0-9]+", " ", combined).strip()

    @staticmethod
    def _canonical_page_url(url: str) -> str:
        parsed = urlparse(url)
        path = parsed.path or "/"
        if path != "/":
            path = path.rstrip("/")

        return parsed._replace(
            scheme=parsed.scheme.lower(),
            netloc=parsed.netloc.lower(),
            path=path,
            fragment="",
        ).geturl()

    @staticmethod
    def _normalized_host(url: str) -> str:
        host = (urlparse(url).hostname or "").lower().rstrip(".")
        return host[4:] if host.startswith("www.") else host

    @staticmethod
    def _normalize_phone(value: str, country: str = "") -> str | None:
        raw = value.strip()
        if not raw:
            return None

        digits = re.sub(r"\D+", "", raw)
        if not digits:
            return None

        normalized_country = country.strip().lower()

        if normalized_country in {"united states", "usa", "us"}:
            if len(digits) == 10:
                return "+1" + digits

            if len(digits) == 11 and digits.startswith("1"):
                return "+" + digits

        if raw.startswith("+"):
            return "+" + digits

        return digits

    @staticmethod
    def _valid_email(value: str) -> bool:
        if not value or len(value) > 254:
            return False

        lowered = value.lower()

        if lowered.endswith(("@example.com", "@example.org", "@example.net")):
            return False

        if lowered.rsplit(".", 1)[-1] in {
            "png",
            "jpg",
            "jpeg",
            "gif",
            "svg",
            "webp",
            "css",
            "js",
        }:
            return False

        return bool(ContactFinder.EMAIL_PATTERN.fullmatch(value))
