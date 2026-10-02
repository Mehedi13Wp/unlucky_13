from __future__ import annotations

import json
import re
import time
from typing import Any, Callable
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode, urlparse
from urllib.request import Request, urlopen


class DiscoverySourceError(RuntimeError):
    pass


JsonRequester = Callable[[str, str, bytes | None], Any]


class OpenStreetMapDiscoverySource:
    INDUSTRY_SELECTORS: tuple[tuple[tuple[str, ...], tuple[tuple[str, str], ...]], ...] = (
        (("restaurant", "restaurants"), (("amenity", "restaurant"),)),
        (("cafe", "coffee"), (("amenity", "cafe"),)),
        (("salon", "hairdresser", "beauty"), (("shop", "hairdresser"), ("shop", "beauty"))),
        (("real estate", "estate agent", "realtor"), (("office", "estate_agent"),)),
        (("recruitment", "employment", "staffing"), (("office", "employment_agency"),)),
        (("dentist", "dental"), (("amenity", "dentist"),)),
        (("clinic", "medical"), (("amenity", "clinic"),)),
        (("law firm", "lawyer", "legal"), (("office", "lawyer"),)),
        (("hotel", "hotels"), (("tourism", "hotel"),)),
        (("gym", "fitness"), (("leisure", "fitness_centre"),)),
    )

    WEBSITE_TAGS = ("website", "contact:website", "url")
    OVERPASS_FALLBACK_URLS = (
        "https://overpass-api.de/api/interpreter",
        "https://overpass.kumi.systems/api/interpreter",
    )

    def __init__(
        self,
        user_agent: str,
        nominatim_url: str = "https://nominatim.openstreetmap.org",
        overpass_url: str = "https://overpass-api.de/api/interpreter",
        timeout_seconds: float = 15.0,
        delay_seconds: float = 1.1,
        requester: JsonRequester | None = None,
        sleeper: Callable[[float], None] = time.sleep,
    ) -> None:
        self.user_agent = user_agent
        self.nominatim_url = nominatim_url.rstrip("/")
        self.overpass_urls = tuple(
            dict.fromkeys(
                [
                    overpass_url,
                    *self.OVERPASS_FALLBACK_URLS,
                ]
            )
        )
        self.timeout_seconds = timeout_seconds
        self.delay_seconds = max(1.0, delay_seconds)
        self.requester = requester or self._request_json
        self.sleeper = sleeper

    def discover(self, campaign: dict[str, Any], limit: int = 10) -> list[dict[str, Any]]:
        safe_limit = max(1, min(int(limit), 50))
        selectors = self._selectors_for_industry(str(campaign.get("target_industry") or ""))
        locations = self._locations(campaign)

        if not selectors:
            raise DiscoverySourceError(
                "OpenStreetMap discovery does not yet have a safe category mapping for "
                f"industry: {campaign.get('target_industry') or 'blank'}"
            )

        if not locations:
            raise DiscoverySourceError(
                "Campaign needs at least one target city or country for OpenStreetMap discovery."
            )

        candidates: list[dict[str, Any]] = []
        seen_domains: set[str] = set()

        for location_query, country, city in locations:
            if len(candidates) >= safe_limit:
                break

            bbox = self._geocode(location_query)
            self.sleeper(self.delay_seconds)

            elements = self._query_overpass(bbox, selectors)

            for element in elements:
                candidate = self._candidate_from_element(
                    element=element,
                    campaign=campaign,
                    country=country,
                    city=city,
                )

                if candidate is None:
                    continue

                domain = self._domain(candidate["website_url"])
                if not domain or domain in seen_domains:
                    continue

                seen_domains.add(domain)
                candidates.append(candidate)

                if len(candidates) >= safe_limit:
                    break

            if len(candidates) < safe_limit:
                self.sleeper(self.delay_seconds)

        return candidates

    def _geocode(self, location_query: str) -> tuple[float, float, float, float]:
        query = urlencode(
            {
                "q": location_query,
                "format": "jsonv2",
                "limit": 1,
            }
        )
        response = self.requester("GET", f"{self.nominatim_url}/search?{query}", None)

        if not isinstance(response, list) or not response:
            raise DiscoverySourceError(f"Could not locate target geography: {location_query}")

        boundingbox = response[0].get("boundingbox") if isinstance(response[0], dict) else None

        if not isinstance(boundingbox, list) or len(boundingbox) != 4:
            raise DiscoverySourceError(f"Location has no usable bounding box: {location_query}")

        try:
            south, north, west, east = (float(value) for value in boundingbox)
        except (TypeError, ValueError) as error:
            raise DiscoverySourceError(
                f"Location returned an invalid bounding box: {location_query}"
            ) from error

        return south, west, north, east

    def _query_overpass(
        self,
        bbox: tuple[float, float, float, float],
        selectors: tuple[tuple[str, str], ...],
    ) -> list[dict[str, Any]]:
        south, west, north, east = bbox

        # Filter for website-bearing businesses at the provider so dense cities do not
        # require downloading every POI in the selected industry.
        selector_lines = "".join(
            f'nwr["{key}"="{value}"]["{website_tag}"]({south},{west},{north},{east});'
            for key, value in selectors
            for website_tag in self.WEBSITE_TAGS
        )
        query = f"[out:json][timeout:25];({selector_lines});out center tags;"
        payload = urlencode({"data": query}).encode("utf-8")

        errors: list[str] = []

        for index, endpoint in enumerate(self.overpass_urls):
            try:
                response = self.requester("POST", endpoint, payload)
            except DiscoverySourceError as error:
                errors.append(f"{endpoint}: {error}")
                if index < len(self.overpass_urls) - 1:
                    self.sleeper(self.delay_seconds)
                continue

            if not isinstance(response, dict):
                errors.append(f"{endpoint}: invalid JSON shape")
                if index < len(self.overpass_urls) - 1:
                    self.sleeper(self.delay_seconds)
                continue

            elements = response.get("elements", [])
            return [item for item in elements if isinstance(item, dict)]

        detail = "; ".join(errors) if errors else "no provider response"
        raise DiscoverySourceError(f"All Overpass providers failed: {detail}")

    def _candidate_from_element(
        self,
        element: dict[str, Any],
        campaign: dict[str, Any],
        country: str | None,
        city: str | None,
    ) -> dict[str, Any] | None:
        tags = element.get("tags")
        if not isinstance(tags, dict):
            return None

        name = str(tags.get("name") or tags.get("brand") or tags.get("operator") or "").strip()
        website = str(
            tags.get("website")
            or tags.get("contact:website")
            or tags.get("url")
            or ""
        ).strip()

        if not name or not website:
            return None

        website = self._normalize_website(website)
        if not self._domain(website):
            return None

        element_type = str(element.get("type") or "").strip()
        element_id = element.get("id")
        source_url = None

        if element_type in {"node", "way", "relation"} and element_id:
            source_url = f"https://www.openstreetmap.org/{element_type}/{element_id}"

        return {
            "business_name": name,
            "website_url": website,
            "industry": campaign.get("target_industry"),
            "country": country,
            "city": city,
            "source": "openstreetmap",
            "source_url": source_url,
            "observed_signals": [
                "Discovered from OpenStreetMap public business data (ODbL).",
            ],
        }

    def _request_json(self, method: str, url: str, data: bytes | None) -> Any:
        headers = {
            "Accept": "application/json",
            "User-Agent": self.user_agent,
        }

        if method == "POST":
            headers["Content-Type"] = "application/x-www-form-urlencoded"

        request = Request(url, data=data, method=method, headers=headers)

        try:
            with urlopen(request, timeout=self.timeout_seconds) as response:
                body = response.read().decode("utf-8")
                return json.loads(body)
        except HTTPError as error:
            raise DiscoverySourceError(
                f"Discovery provider returned HTTP {error.code}."
            ) from error
        except (URLError, TimeoutError) as error:
            reason = getattr(error, "reason", None) or str(error) or "request timed out"
            raise DiscoverySourceError(
                f"Could not reach discovery provider: {reason}"
            ) from error
        except json.JSONDecodeError as error:
            raise DiscoverySourceError("Discovery provider returned invalid JSON.") from error

    @classmethod
    def _selectors_for_industry(cls, industry: str) -> tuple[tuple[str, str], ...]:
        normalized = re.sub(r"[^a-z0-9]+", " ", industry.lower()).strip()

        for keywords, selectors in cls.INDUSTRY_SELECTORS:
            if any(keyword in normalized for keyword in keywords):
                return selectors

        return ()

    @staticmethod
    def _locations(campaign: dict[str, Any]) -> list[tuple[str, str | None, str | None]]:
        countries = [
            str(value).strip()
            for value in campaign.get("target_countries", [])
            if str(value).strip()
        ]
        cities = [
            str(value).strip()
            for value in campaign.get("target_cities", [])
            if str(value).strip()
        ]

        if cities:
            default_country = countries[0] if countries else None
            return [
                (
                    f"{city}, {default_country}" if default_country else city,
                    default_country,
                    city,
                )
                for city in cities
            ]

        return [(country, country, None) for country in countries]

    @staticmethod
    def _normalize_website(value: str) -> str:
        website = value.strip()

        if website.startswith("//"):
            return "https:" + website

        if "://" not in website:
            return "https://" + website

        return website

    @staticmethod
    def _domain(value: str) -> str | None:
        try:
            parsed = urlparse(value)
        except ValueError:
            return None

        host = (parsed.hostname or "").lower().rstrip(".")
        if host.startswith("www."):
            host = host[4:]

        return host or None
