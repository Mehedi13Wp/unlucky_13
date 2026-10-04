from __future__ import annotations

import json
import os
from datetime import datetime, timezone
from pathlib import Path
from typing import Any


def write_analysis_telemetry(
    payload: dict[str, Any],
    *,
    repo_root: Path | None = None,
) -> Path:
    root = repo_root or Path(__file__).resolve().parents[2]
    runtime_dir = root / "storage" / "app" / "worker-runtime"
    telemetry_path = runtime_dir / "last-analysis.json"
    temp_path = runtime_dir / "last-analysis.tmp"

    runtime_dir.mkdir(parents=True, exist_ok=True)

    data = {
        "attempted_at": datetime.now(timezone.utc).isoformat(),
        **payload,
    }

    temp_path.write_text(
        json.dumps(data, ensure_ascii=False),
        encoding="utf-8",
    )
    os.replace(temp_path, telemetry_path)

    return telemetry_path
