import io
import json
import unittest
from urllib.error import HTTPError

from worker.unlucky13_worker.client import WorkerApiClient, WorkerApiError


class FakeResponse:
    def __init__(self, payload):
        self.payload = json.dumps(payload).encode("utf-8")

    def __enter__(self):
        return self

    def __exit__(self, exc_type, exc, tb):
        return False

    def read(self):
        return self.payload


class WorkerApiClientTest(unittest.TestCase):
    def test_health_sends_bearer_token(self):
        captured = {}

        def opener(request, timeout):
            captured["authorization"] = request.headers.get("Authorization")
            captured["timeout"] = timeout
            return FakeResponse({"status": "ok"})

        client = WorkerApiClient(
            "http://127.0.0.1:8000",
            "secret-token",
            opener=opener,
            sleeper=lambda _: None,
        )

        result = client.health()

        self.assertEqual("ok", result["status"])
        self.assertEqual("Bearer secret-token", captured["authorization"])
        self.assertEqual(15.0, captured["timeout"])

    def test_active_campaigns_uses_worker_campaign_endpoint(self):
        captured = {}

        def opener(request, timeout):
            captured["url"] = request.full_url
            return FakeResponse({"count": 1, "campaigns": [{"uuid": "abc"}]})

        client = WorkerApiClient(
            "http://127.0.0.1:8000",
            "secret-token",
            opener=opener,
            sleeper=lambda _: None,
        )

        result = client.active_campaigns()

        self.assertEqual(1, result["count"])
        self.assertEqual(
            "http://127.0.0.1:8000/api/worker/campaigns",
            captured["url"],
        )

    def test_contact_discovery_queue_uses_worker_endpoint(self):
        captured = {}

        def opener(request, timeout):
            captured["url"] = request.full_url
            return FakeResponse({"count": 1, "leads": [{"uuid": "lead-1"}]})

        client = WorkerApiClient(
            "http://127.0.0.1:8000",
            "secret-token",
            opener=opener,
            sleeper=lambda _: None,
        )

        result = client.contact_discovery_queue(limit=5)

        self.assertEqual(1, result["count"])
        self.assertEqual(
            "http://127.0.0.1:8000/api/worker/contact-discovery/queue?limit=5",
            captured["url"],
        )

    def test_non_retryable_api_error_is_exposed(self):
        def opener(request, timeout):
            payload = json.dumps({"message": "Invalid token"}).encode("utf-8")
            raise HTTPError(
                request.full_url,
                401,
                "Unauthorized",
                hdrs=None,
                fp=io.BytesIO(payload),
            )

        client = WorkerApiClient(
            "http://127.0.0.1:8000",
            "wrong-token",
            opener=opener,
            sleeper=lambda _: None,
        )

        with self.assertRaises(WorkerApiError) as context:
            client.health()

        self.assertEqual(401, context.exception.status_code)
        self.assertEqual("Invalid token", context.exception.message)


if __name__ == "__main__":
    unittest.main()
