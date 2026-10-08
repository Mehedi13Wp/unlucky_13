from __future__ import annotations

import json
import time
from dataclasses import dataclass
from typing import Any, Callable
from urllib.error import HTTPError, URLError
from urllib.parse import urlencode
from urllib.request import Request, urlopen


@dataclass
class WorkerApiError(RuntimeError):
    status_code: int | None
    message: str
    payload: dict[str, Any] | None = None

    def __str__(self) -> str:
        prefix = f"HTTP {self.status_code}: " if self.status_code else ""
        return prefix + self.message


class WorkerApiClient:
    def __init__(
        self,
        base_url: str,
        token: str,
        timeout_seconds: float = 15.0,
        max_retries: int = 3,
        opener: Callable[..., Any] = urlopen,
        sleeper: Callable[[float], None] = time.sleep,
    ) -> None:
        self.base_url = base_url.rstrip("/")
        self.token = token
        self.timeout_seconds = timeout_seconds
        self.max_retries = max_retries
        self.opener = opener
        self.sleeper = sleeper

    def health(self) -> dict[str, Any]:
        return self._request("GET", "/api/worker/health")

    def active_campaigns(self) -> dict[str, Any]:
        return self._request("GET", "/api/worker/campaigns")

    def record_campaign_run(self, campaign_uuid: str) -> dict[str, Any]:
        return self._request(
            "POST",
            f"/api/worker/campaigns/{campaign_uuid}/run-complete",
        )

    def known_domains(self, domains: list[str]) -> dict[str, Any]:
        clean = sorted({str(domain).strip().lower() for domain in domains if str(domain).strip()})
        if not clean:
            return {"count": 0, "domains": []}

        return self._request(
            "POST",
            "/api/worker/discovery/known-domains",
            {"domains": clean[:200]},
        )

    def ingest_lead(self, campaign_uuid: str, candidate: dict[str, Any]) -> dict[str, Any]:
        return self._request("POST", f"/api/worker/campaigns/{campaign_uuid}/leads", candidate)

    def analysis_queue(self, limit: int = 10) -> dict[str, Any]:
        safe_limit = max(1, min(limit, 25))
        return self._request("GET", f"/api/worker/analysis/queue?limit={safe_limit}")

    def contact_discovery_queue(
        self,
        limit: int = 10,
        lead_uuid: str | None = None,
    ) -> dict[str, Any]:
        safe_limit = max(1, min(limit, 25))
        params: dict[str, str | int] = {"limit": safe_limit}

        if lead_uuid and lead_uuid.strip():
            params["lead"] = lead_uuid.strip()

        return self._request(
            "GET",
            "/api/worker/contact-discovery/queue?" + urlencode(params),
        )

    def complete_contact_discovery(
        self,
        lead_uuid: str,
        contacts: list[dict[str, Any]],
        notes: list[str] | None = None,
    ) -> dict[str, Any]:
        return self._request(
            "POST",
            f"/api/worker/leads/{lead_uuid}/contacts/complete",
            {
                "contacts": contacts[:10],
                "notes": [
                    str(note)[:500]
                    for note in (notes or [])[:20]
                ],
            },
        )

    def outreach_draft_queue(self, limit: int = 5) -> dict[str, Any]:
        safe_limit = max(1, min(limit, 25))
        return self._request("GET", f"/api/worker/outreach-drafts/queue?limit={safe_limit}")

    def create_outreach_draft(
        self,
        lead_uuid: str,
        draft: dict[str, Any],
    ) -> dict[str, Any]:
        return self._request(
            "POST",
            f"/api/worker/leads/{lead_uuid}/outreach-drafts",
            draft,
        )

    def delivery_queue(self, limit: int = 5) -> dict[str, Any]:
        safe_limit = max(1, min(limit, 25))
        return self._request("GET", f"/api/worker/delivery/queue?limit={safe_limit}")

    def send_approved_draft(self, draft_id: int) -> dict[str, Any]:
        return self._request(
            "POST",
            f"/api/worker/outreach-drafts/{int(draft_id)}/send",
            allow_retries=False,
        )

    def run_delivery_automation(self) -> dict[str, Any]:
        return self._request(
            "POST",
            "/api/worker/delivery/automation/run",
            allow_retries=False,
        )

    def start_analysis(self, lead_uuid: str) -> dict[str, Any]:
        return self._request("POST", f"/api/worker/leads/{lead_uuid}/analysis/start")

    def complete_analysis(
        self,
        lead_uuid: str,
        score: int,
        reason: str,
        observed_signals: list[str],
    ) -> dict[str, Any]:
        return self._request(
            "POST",
            f"/api/worker/leads/{lead_uuid}/analysis/complete",
            {
                "qualification_score": score,
                "qualification_reason": reason,
                "observed_signals": observed_signals,
            },
        )

    def _request(
        self,
        method: str,
        path: str,
        payload: dict[str, Any] | None = None,
        allow_retries: bool = True,
    ) -> dict[str, Any]:
        data = None if payload is None else json.dumps(payload).encode("utf-8")
        request = Request(
            self.base_url + path,
            data=data,
            method=method,
            headers={
                "Accept": "application/json",
                "Authorization": f"Bearer {self.token}",
                "Content-Type": "application/json",
                "User-Agent": "Unlucky13-Python-Worker/0.1",
            },
        )

        max_attempts = self.max_retries if allow_retries else 0

        for attempt in range(max_attempts + 1):
            try:
                with self.opener(request, timeout=self.timeout_seconds) as response:
                    body = response.read().decode("utf-8")
                    return json.loads(body) if body else {}
            except HTTPError as error:
                body = error.read().decode("utf-8", errors="replace")
                parsed = self._parse_json(body)
                message = str(parsed.get("message") or error.reason or "API request failed")

                if (
                    allow_retries
                    and self._should_retry(error.code)
                    and attempt < max_attempts
                ):
                    self.sleeper(self._retry_delay(attempt))
                    continue

                raise WorkerApiError(error.code, message, parsed) from error
            except URLError as error:
                if allow_retries and attempt < max_attempts:
                    self.sleeper(self._retry_delay(attempt))
                    continue

                raise WorkerApiError(None, f"Could not reach Laravel API: {error.reason}") from error

        raise WorkerApiError(None, "Worker API request failed.")

    @staticmethod
    def _parse_json(body: str) -> dict[str, Any]:
        try:
            value = json.loads(body)
            return value if isinstance(value, dict) else {"response": value}
        except json.JSONDecodeError:
            return {"message": body or "API request failed"}

    @staticmethod
    def _should_retry(status_code: int) -> bool:
        return status_code == 429 or status_code >= 500

    @staticmethod
    def _retry_delay(attempt: int) -> float:
        return min(4.0, 0.5 * (2**attempt))
