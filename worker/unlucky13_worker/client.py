from __future__ import annotations

import json
import time
from dataclasses import dataclass
from typing import Any, Callable
from urllib.error import HTTPError, URLError
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

    def ingest_lead(self, campaign_uuid: str, candidate: dict[str, Any]) -> dict[str, Any]:
        return self._request("POST", f"/api/worker/campaigns/{campaign_uuid}/leads", candidate)

    def analysis_queue(self, limit: int = 10) -> dict[str, Any]:
        safe_limit = max(1, min(limit, 25))
        return self._request("GET", f"/api/worker/analysis/queue?limit={safe_limit}")

    def contact_discovery_queue(self, limit: int = 10) -> dict[str, Any]:
        safe_limit = max(1, min(limit, 25))
        return self._request("GET", f"/api/worker/contact-discovery/queue?limit={safe_limit}")

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
                "contacts": contacts,
                "notes": notes or [],
            },
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

        for attempt in range(self.max_retries + 1):
            try:
                with self.opener(request, timeout=self.timeout_seconds) as response:
                    body = response.read().decode("utf-8")
                    return json.loads(body) if body else {}
            except HTTPError as error:
                body = error.read().decode("utf-8", errors="replace")
                parsed = self._parse_json(body)
                message = str(parsed.get("message") or error.reason or "API request failed")

                if self._should_retry(error.code) and attempt < self.max_retries:
                    self.sleeper(self._retry_delay(attempt))
                    continue

                raise WorkerApiError(error.code, message, parsed) from error
            except URLError as error:
                if attempt < self.max_retries:
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
