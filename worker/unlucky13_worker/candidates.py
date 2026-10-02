from __future__ import annotations

import json
from pathlib import Path
from typing import Any

REQUIRED_FIELDS = ("business_name", "website_url")


def load_candidates(path: str | Path) -> list[dict[str, Any]]:
    source = Path(path)

    if not source.exists():
        raise ValueError(f"Candidate file not found: {source}")

    raw = source.read_text(encoding="utf-8").strip()

    if not raw:
        return []

    if source.suffix.lower() == ".jsonl":
        candidates = [json.loads(line) for line in raw.splitlines() if line.strip()]
    else:
        parsed = json.loads(raw)
        if isinstance(parsed, dict):
            candidates = [parsed]
        elif isinstance(parsed, list):
            candidates = parsed
        else:
            raise ValueError("Candidate JSON must be an object or an array.")

    clean: list[dict[str, Any]] = []

    for index, candidate in enumerate(candidates, start=1):
        if not isinstance(candidate, dict):
            raise ValueError(f"Candidate #{index} must be a JSON object.")

        missing = [field for field in REQUIRED_FIELDS if not str(candidate.get(field, "")).strip()]

        if missing:
            raise ValueError(
                f"Candidate #{index} is missing required field(s): {', '.join(missing)}"
            )

        clean.append(candidate)

    return clean
