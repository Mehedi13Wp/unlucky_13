from __future__ import annotations

import json
import os
import threading
from datetime import datetime, timezone
from pathlib import Path
from typing import Any, BinaryIO


class ManagedWorkerRuntime:
    def __init__(self, repo_root: Path | None = None) -> None:
        self.repo_root = repo_root or Path(__file__).resolve().parents[2]
        self.runtime_dir = self.repo_root / "storage" / "app" / "worker-runtime"
        self.state_path = self.runtime_dir / "worker-state.json"
        self.stop_path = self.runtime_dir / "worker-stop.request"
        self.lock_path = self.runtime_dir / "worker.lock"
        self._lock_handle: BinaryIO | None = None
        self._state_lock = threading.Lock()
        self._heartbeat_stop = threading.Event()
        self._heartbeat_thread: threading.Thread | None = None
        self._started_at: str | None = None

    def acquire(self) -> bool:
        self.runtime_dir.mkdir(parents=True, exist_ok=True)
        handle = self.lock_path.open("a+b")

        try:
            handle.seek(0)
            if handle.read(1) == b"":
                handle.seek(0)
                handle.write(b"0")
                handle.flush()
            handle.seek(0)

            if os.name == "nt":
                import msvcrt

                msvcrt.locking(handle.fileno(), msvcrt.LK_NBLCK, 1)
            else:
                import fcntl

                fcntl.flock(handle.fileno(), fcntl.LOCK_EX | fcntl.LOCK_NB)
        except (OSError, BlockingIOError):
            handle.close()
            return False

        self._lock_handle = handle
        return True

    def release(self) -> None:
        handle = self._lock_handle
        self._lock_handle = None

        if handle is None:
            return

        try:
            handle.seek(0)
            if os.name == "nt":
                import msvcrt

                msvcrt.locking(handle.fileno(), msvcrt.LK_UNLCK, 1)
            else:
                import fcntl

                fcntl.flock(handle.fileno(), fcntl.LOCK_UN)
        finally:
            handle.close()

    def clear_stop_request(self) -> None:
        try:
            self.stop_path.unlink()
        except FileNotFoundError:
            pass

    def stop_requested(self) -> bool:
        return self.stop_path.exists()

    def mark_running(self) -> None:
        now = self._now()
        self._started_at = now
        self._write_state(
            {
                "state": "running",
                "pid": os.getpid(),
                "started_at": now,
                "heartbeat_at": now,
                "message": "Worker is running.",
            }
        )

    def mark_stopped(self) -> None:
        now = self._now()
        self._write_state(
            {
                "state": "stopped",
                "pid": None,
                "started_at": self._started_at,
                "heartbeat_at": now,
                "stopped_at": now,
                "message": "Worker is stopped.",
            }
        )
        self.clear_stop_request()

    def start_heartbeat(self, interval_seconds: float = 5.0) -> None:
        self._heartbeat_stop.clear()

        def heartbeat() -> None:
            while not self._heartbeat_stop.wait(max(1.0, interval_seconds)):
                state = "stopping" if self.stop_requested() else "running"
                self._write_state(
                    {
                        "state": state,
                        "pid": os.getpid(),
                        "started_at": self._started_at,
                        "heartbeat_at": self._now(),
                        "message": (
                            "Worker is stopping after the current operation."
                            if state == "stopping"
                            else "Worker is running."
                        ),
                    }
                )

        self._heartbeat_thread = threading.Thread(
            target=heartbeat,
            name="unlucky13-worker-heartbeat",
            daemon=True,
        )
        self._heartbeat_thread.start()

    def stop_heartbeat(self) -> None:
        self._heartbeat_stop.set()

        if self._heartbeat_thread is not None:
            self._heartbeat_thread.join(timeout=2.0)
            self._heartbeat_thread = None

    def _write_state(self, payload: dict[str, Any]) -> None:
        self.runtime_dir.mkdir(parents=True, exist_ok=True)
        temp_path = self.state_path.with_suffix(".tmp")

        with self._state_lock:
            temp_path.write_text(
                json.dumps(payload, ensure_ascii=False),
                encoding="utf-8",
            )
            os.replace(temp_path, self.state_path)

    @staticmethod
    def _now() -> str:
        return datetime.now(timezone.utc).isoformat()
