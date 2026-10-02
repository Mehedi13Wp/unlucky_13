from __future__ import annotations

import ipaddress
import re
import socket
from dataclasses import dataclass
from difflib import SequenceMatcher
from html.parser import HTMLParser
from typing import Any
from urllib.error import HTTPError, URLError
from urllib.parse import urljoin, urlparse
from urllib.request import HTTPRedirectHandler, Request, build_opener


class AnalyzerError(RuntimeError):
    pass


def _is_public_ip(value: str) -> bool:
    try:
        address = ipaddress.ip_address(value)
    except ValueError:
        return False

    return not (
        address.is_private
        or address.is_loopback
        or address.is_link_local
        or address.is_reserved
        or address.is_multicast
        or address.is_unspecified
    )


def ensure_public_url(url: str) -> None:
    parsed = urlparse(url)

    if parsed.scheme not in {"http", "https"}:
        raise AnalyzerError("Only HTTP and HTTPS website URLs are allowed.")

    hostname = (parsed.hostname or "").lower().rstrip(".")

    if not hostname:
        raise AnalyzerError("Website URL does not contain a hostname.")

    if hostname == "localhost" or hostname.endswith(".local"):
        raise AnalyzerError("Local network website targets are not allowed.")

    try:
        literal = ipaddress.ip_address(hostname)
    except ValueError:
        literal = None

    if literal is not None:
        if not _is_public_ip(hostname):
            raise AnalyzerError("Private or reserved IP website targets are not allowed.")
        return

    try:
        addresses = {
            result[4][0]
            for result in socket.getaddrinfo(hostname, None, type=socket.SOCK_STREAM)
        }
    except socket.gaierror as error:
        raise AnalyzerError(f"Could not resolve website hostname: {hostname}") from error

    if not addresses:
        raise AnalyzerError(f"Could not resolve website hostname: {hostname}")

    if any(not _is_public_ip(address) for address in addresses):
        raise AnalyzerError("Website hostname resolves to a private or reserved network.")


class _SafeRedirectHandler(HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl):
        target = urljoin(req.full_url, newurl)
        ensure_public_url(target)
        return super().redirect_request(req, fp, code, msg, headers, target)


class _PageParser(HTMLParser):
    def __init__(self) -> None:
        super().__init__(convert_charrefs=True)
        self.title_parts: list[str] = []
        self.text_parts: list[str] = []
        self.links: list[tuple[str, str]] = []
        self.meta_viewport = False
        self.form_count = 0
        self._in_title = False
        self._skip_depth = 0
        self._active_href: str | None = None
        self._active_link_text: list[str] = []

    def handle_starttag(self, tag: str, attrs) -> None:
        attrs_dict = {str(key).lower(): value for key, value in attrs}
        lowered = tag.lower()

        if lowered in {"script", "style", "noscript", "svg"}:
            self._skip_depth += 1

        if lowered == "title":
            self._in_title = True

        if lowered == "meta":
            name = str(attrs_dict.get("name") or "").lower()
            if name == "viewport":
                self.meta_viewport = True

        if lowered == "form":
            self.form_count += 1

        if lowered == "a":
            self._active_href = str(attrs_dict.get("href") or "")
            self._active_link_text = []

    def handle_endtag(self, tag: str) -> None:
        lowered = tag.lower()

        if lowered == "title":
            self._in_title = False

        if lowered == "a" and self._active_href is not None:
            text = " ".join(self._active_link_text).strip()
            self.links.append((self._active_href, text))
            self._active_href = None
            self._active_link_text = []

        if lowered in {"script", "style", "noscript", "svg"} and self._skip_depth > 0:
            self._skip_depth -= 1

    def handle_data(self, data: str) -> None:
        if self._skip_depth:
            return

        clean = " ".join(data.split())

        if not clean:
            return

        if self._in_title:
            self.title_parts.append(clean)

        self.text_parts.append(clean)

        if self._active_href is not None:
            self._active_link_text.append(clean)


@dataclass(frozen=True)
class WebsiteSnapshot:
    requested_url: str
    final_url: str
    status_code: int
    content_type: str
    html: str


class WebsiteFetcher:
    def __init__(self, timeout_seconds: float = 15.0, max_bytes: int = 1_000_000) -> None:
        self.timeout_seconds = timeout_seconds
        self.max_bytes = max_bytes
        self.opener = build_opener(_SafeRedirectHandler())

    def fetch(self, url: str) -> WebsiteSnapshot:
        ensure_public_url(url)

        request = Request(
            url,
            method="GET",
            headers={
                "Accept": "text/html,application/xhtml+xml",
                "User-Agent": "Unlucky13-Website-Analyzer/0.1 (+single-page public inspection)",
            },
        )

        try:
            with self.opener.open(request, timeout=self.timeout_seconds) as response:
                final_url = response.geturl()
                ensure_public_url(final_url)

                content_type = str(response.headers.get("Content-Type") or "").lower()
                if "text/html" not in content_type and "application/xhtml+xml" not in content_type:
                    raise AnalyzerError(
                        f"Website returned unsupported content type: {content_type or 'unknown'}"
                    )

                body = response.read(self.max_bytes + 1)
                if len(body) > self.max_bytes:
                    raise AnalyzerError("Website response exceeded the analyzer size limit.")

                charset = response.headers.get_content_charset() or "utf-8"
                html = body.decode(charset, errors="replace")

                return WebsiteSnapshot(
                    requested_url=url,
                    final_url=final_url,
                    status_code=int(response.getcode() or 200),
                    content_type=content_type,
                    html=html,
                )

        except HTTPError as error:
            raise AnalyzerError(f"Website returned HTTP {error.code}.") from error
        except (URLError, TimeoutError) as error:
            reason = getattr(error, "reason", None) or str(error) or "request timed out"
            raise AnalyzerError(f"Website request failed: {reason}") from error


def _normalize(value: Any) -> str:
    text = str(value or "").strip().lower()
    text = re.sub(r"^[a-z]+\s*:\s*", "", text)
    return re.sub(r"[^a-z0-9]+", " ", text).strip()


def _similar(left: str, right: str) -> bool:
    left = _normalize(left)
    right = _normalize(right)

    if not left or not right:
        return False

    if left in right or right in left:
        return True

    return SequenceMatcher(None, left, right).ratio() >= 0.68


class WebsiteAnalyzer:
    CTA_WORDS = (
        "book",
        "booking",
        "reserve",
        "reservation",
        "appointment",
        "contact",
        "quote",
        "order",
        "shop",
        "buy",
        "schedule",
    )

    def __init__(
        self,
        timeout_seconds: float = 15.0,
        fetcher: WebsiteFetcher | None = None,
    ) -> None:
        self.fetcher = fetcher or WebsiteFetcher(timeout_seconds=timeout_seconds)

    def analyze(self, lead: dict[str, Any]) -> dict[str, Any]:
        website_url = str(lead.get("website_url") or "").strip()

        if not website_url:
            raise AnalyzerError("Lead has no website URL to inspect.")

        snapshot = self.fetcher.fetch(website_url)
        parser = _PageParser()
        parser.feed(snapshot.html)

        text = " ".join(parser.text_parts)
        lower_text = text.lower()
        link_text = " ".join(text for _, text in parser.links).lower()
        hrefs = [href.lower() for href, _ in parser.links]

        signals: list[str] = []
        score = 15
        signals.append(f"Public website responded with HTTP {snapshot.status_code}")

        if urlparse(snapshot.final_url).scheme == "https":
            score += 10
            signals.append("Website uses HTTPS")

        title = " ".join(parser.title_parts).strip()
        if title:
            score += 5
            signals.append("Page title is present")

        campaign = lead.get("campaign") or {}
        target_industry = str(campaign.get("target_industry") or "")
        lead_industry = str(lead.get("industry") or "")

        if target_industry and lead_industry and _similar(target_industry, lead_industry):
            score += 20
            signals.append(
                f"Lead industry '{lead_industry}' matches campaign target '{target_industry}'"
            )
        elif target_industry and _normalize(target_industry) in _normalize(lower_text):
            score += 20
            signals.append(f"Website content references target industry '{target_industry}'")

        target_places = [
            *(campaign.get("target_countries") or []),
            *(campaign.get("target_cities") or []),
        ]
        lead_places = [lead.get("country"), lead.get("city")]

        if any(
            _similar(str(target), str(actual))
            for target in target_places
            for actual in lead_places
            if target and actual
        ):
            score += 10
            signals.append("Lead location matches configured campaign geography")

        has_email = any(href.startswith("mailto:") for href in hrefs) or bool(
            re.search(r"\b[A-Z0-9._%+-]+@[A-Z0-9.-]+\.[A-Z]{2,}\b", text, re.I)
        )
        has_phone = any(href.startswith("tel:") for href in hrefs)
        has_contact_link = any(
            "contact" in href or "contact" in anchor
            for href, anchor in parser.links
        )
        has_public_contact = has_email or has_phone or has_contact_link

        if has_public_contact:
            score += 15
            signals.append("Public contact path is visible on the website")
        else:
            signals.append("No obvious public contact path found on the inspected page")

        has_cta = any(word in lower_text or word in link_text for word in self.CTA_WORDS)
        if has_cta:
            score += 5
            signals.append("A visible conversion or contact call-to-action was found")
        else:
            score += 15
            signals.append("No obvious conversion call-to-action found on the inspected page")

        if parser.meta_viewport:
            score += 5
            signals.append("Mobile viewport metadata is present")
        else:
            score += 10
            signals.append("Mobile viewport metadata was not found")

        visible_text_length = len(" ".join(text.split()))
        if visible_text_length < 400:
            score += 10
            signals.append("Homepage has sparse visible text content")

        rules = campaign.get("qualification_rules") or {}
        if bool(rules.get("requires_public_contact", False)) and not has_public_contact:
            score = min(score, 55)

        score = max(0, min(score, 100))
        unique_signals = list(dict.fromkeys(signals))

        reason = (
            "Automated single-page public website inspection produced "
            f"{len(unique_signals)} auditable signal(s). "
            "The score reflects campaign fit, geography, contact availability, "
            "and visible website opportunity signals; outreach still requires review."
        )

        return {
            "score": score,
            "reason": reason,
            "signals": unique_signals,
            "final_url": snapshot.final_url,
        }
