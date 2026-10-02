from __future__ import annotations

import os
from dataclasses import dataclass
from pathlib import Path


def _load_env_file(path: Path) -> None:
    if not path.exists():
        return

    for raw_line in path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()

        if not line or line.startswith("#") or "=" not in line:
            continue

        key, value = line.split("=", 1)
        key = key.strip()
        value = value.strip().strip('"').strip("'")

        if key:
            os.environ.setdefault(key, value)


@dataclass(frozen=True)
class WorkerConfig:
    base_url: str
    token: str
    timeout_seconds: float = 15.0
    max_retries: int = 3
    discovery_user_agent: str = "Unlucky13-LeadWorker/0.1"
    nominatim_url: str = "https://nominatim.openstreetmap.org"
    overpass_url: str = "https://overpass-api.de/api/interpreter"
    discovery_delay_seconds: float = 1.1

    @classmethod
    def from_environment(cls) -> "WorkerConfig":
        repo_root = Path(__file__).resolve().parents[2]
        _load_env_file(repo_root / ".env")

        base_url = os.getenv("UNLUCKY13_API_URL", "http://127.0.0.1:8000").strip()
        token = os.getenv("UNLUCKY13_WORKER_TOKEN", "").strip()
        timeout = float(os.getenv("UNLUCKY13_WORKER_TIMEOUT", "15"))
        retries = int(os.getenv("UNLUCKY13_WORKER_RETRIES", "3"))
        discovery_user_agent = os.getenv(
            "UNLUCKY13_DISCOVERY_USER_AGENT",
            "Unlucky13-LeadWorker/0.1",
        ).strip()
        nominatim_url = os.getenv(
            "UNLUCKY13_NOMINATIM_URL",
            "https://nominatim.openstreetmap.org",
        ).strip()
        overpass_url = os.getenv(
            "UNLUCKY13_OVERPASS_URL",
            "https://overpass-api.de/api/interpreter",
        ).strip()
        discovery_delay = float(os.getenv("UNLUCKY13_DISCOVERY_DELAY", "1.1"))

        if not token:
            raise ValueError("UNLUCKY13_WORKER_TOKEN is missing. Add it to the project .env file.")

        return cls(
            base_url=base_url.rstrip("/"),
            token=token,
            timeout_seconds=max(1.0, timeout),
            max_retries=max(0, min(retries, 5)),
            discovery_user_agent=discovery_user_agent or "Unlucky13-LeadWorker/0.1",
            nominatim_url=nominatim_url.rstrip("/"),
            overpass_url=overpass_url,
            discovery_delay_seconds=max(1.0, discovery_delay),
        )
