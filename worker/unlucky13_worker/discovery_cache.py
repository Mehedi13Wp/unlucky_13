from __future__ import annotations

import json
from datetime import datetime, timezone
from pathlib import Path
from typing import Any


class DiscoveryCacheError(RuntimeError):
    pass


class DiscoveryCache:
    def __init__(self, root: Path) -> None:
        self.root = root

    @classmethod
    def default(cls) -> "DiscoveryCache":
        repo_root = Path(__file__).resolve().parents[2]
        return cls(repo_root / "storage" / "app" / "worker-cache" / "discovery")

    def save(
        self,
        campaign: dict[str, Any],
        candidates: list[dict[str, Any]],
    ) -> Path:
        campaign_uuid = str(campaign.get("uuid") or "").strip()
        if not campaign_uuid:
            raise DiscoveryCacheError("Campaign UUID is missing; snapshot cannot be saved.")

        self.root.mkdir(parents=True, exist_ok=True)
        path = self._path(campaign_uuid)
        payload = {
            "campaign_uuid": campaign_uuid,
            "campaign_name": campaign.get("name"),
            "saved_at": datetime.now(timezone.utc).isoformat(),
            "candidates": candidates,
        }
        temporary = path.with_suffix(".tmp")
        temporary.write_text(
            json.dumps(payload, indent=2, ensure_ascii=False),
            encoding="utf-8",
        )
        temporary.replace(path)
        return path

    def load(self, campaign_uuid: str, limit: int = 10) -> dict[str, Any]:
        path = self._path(campaign_uuid)

        if not path.exists():
            raise DiscoveryCacheError("No successful discovery snapshot exists yet.")

        try:
            payload = json.loads(path.read_text(encoding="utf-8"))
        except (OSError, json.JSONDecodeError) as error:
            raise DiscoveryCacheError("The discovery snapshot is unreadable.") from error

        if payload.get("campaign_uuid") != campaign_uuid:
            raise DiscoveryCacheError("The discovery snapshot belongs to another campaign.")

        candidates = payload.get("candidates")
        if not isinstance(candidates, list):
            raise DiscoveryCacheError("The discovery snapshot has no valid candidate list.")

        safe_limit = max(1, min(int(limit), 50))

        return {
            "campaign_uuid": campaign_uuid,
            "campaign_name": payload.get("campaign_name"),
            "saved_at": payload.get("saved_at") or "unknown time",
            "candidates": candidates[:safe_limit],
        }

    def exists(self, campaign_uuid: str) -> bool:
        return self._path(campaign_uuid).exists()

    def _path(self, campaign_uuid: str) -> Path:
        safe_uuid = "".join(
            character
            for character in campaign_uuid
            if character.isalnum() or character in {"-", "_"}
        )

        if not safe_uuid:
            raise DiscoveryCacheError("Campaign UUID is invalid.")

        return self.root / f"{safe_uuid}.json"
