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

    @classmethod
    def from_environment(cls) -> "WorkerConfig":
        repo_root = Path(__file__).resolve().parents[2]
        _load_env_file(repo_root / ".env")

        base_url = os.getenv("UNLUCKY13_API_URL", "http://127.0.0.1:8000").strip()
        token = os.getenv("UNLUCKY13_WORKER_TOKEN", "").strip()
        timeout = float(os.getenv("UNLUCKY13_WORKER_TIMEOUT", "15"))
        retries = int(os.getenv("UNLUCKY13_WORKER_RETRIES", "3"))

        if not token:
            raise ValueError("UNLUCKY13_WORKER_TOKEN is missing. Add it to the project .env file.")

        return cls(
            base_url=base_url.rstrip("/"),
            token=token,
            timeout_seconds=max(1.0, timeout),
            max_retries=max(0, min(retries, 5)),
        )
