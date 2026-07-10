"""Persistencia de jobs del Agente 73.

Dos backends con la misma interfaz: Redis (producción, ya corre en el VPS)
y SQLite (desarrollo/tests y fallback). Un Job es un dict serializado JSON;
la fuente de verdad del esquema está en `new_job()`.
"""

from __future__ import annotations

import json
import secrets
import sqlite3
import threading
import time
from pathlib import Path

from . import config

ACTIVE_STATES = ("running", "waiting_input", "waiting_qa")
FINAL_STATES = ("done", "failed", "cancelled", "rejected")


def new_job(kind: str, sender: str, spec: dict) -> dict:
    return {
        "id": secrets.token_hex(3),          # 6 hex chars, fácil de teclear
        "kind": kind,                         # tema | web
        "sender": sender,                     # E.164 del solicitante
        "spec": spec,                         # salida de la gramática
        "state": "running",
        "phase": 1,                           # 1..13
        "retries": {},                        # por fase
        "qa_rejections": 0,
        "feedback": "",                      # motivo del último RECHAZA
        "history": [],                        # [(ts, fase, evento)]
        "artifacts": {},                      # nombre -> ruta relativa al job dir
        "created_at": time.time(),
        "updated_at": time.time(),
    }


def job_dir(job: dict) -> Path:
    d = Path(config.JOBS_DIR) / job["id"]
    d.mkdir(parents=True, exist_ok=True)
    return d


def log_event(job: dict, event: str) -> None:
    job["history"].append([time.time(), job["phase"], event])
    job["updated_at"] = time.time()


class SqliteStore:
    def __init__(self, path: str | None = None):
        self.path = path or config.SQLITE_PATH
        Path(self.path).parent.mkdir(parents=True, exist_ok=True)
        self._lock = threading.Lock()
        with self._conn() as c:
            c.execute(
                "CREATE TABLE IF NOT EXISTS jobs (id TEXT PRIMARY KEY, state TEXT, data TEXT)"
            )
            c.execute(
                "CREATE TABLE IF NOT EXISTS seen_msgs (id TEXT PRIMARY KEY, ts REAL)"
            )

    def _conn(self):
        return sqlite3.connect(self.path)

    def save(self, job: dict) -> None:
        with self._lock, self._conn() as c:
            c.execute(
                "INSERT INTO jobs (id, state, data) VALUES (?, ?, ?) "
                "ON CONFLICT(id) DO UPDATE SET state=excluded.state, data=excluded.data",
                (job["id"], job["state"], json.dumps(job)),
            )

    def get(self, job_id: str) -> dict | None:
        with self._conn() as c:
            row = c.execute("SELECT data FROM jobs WHERE id=?", (job_id,)).fetchone()
        return json.loads(row[0]) if row else None

    def list_active(self) -> list[dict]:
        with self._conn() as c:
            rows = c.execute(
                "SELECT data FROM jobs WHERE state IN (?,?,?)", ACTIVE_STATES
            ).fetchall()
        return [json.loads(r[0]) for r in rows]

    def first_seen(self, message_id: str) -> bool:
        """True solo la primera vez que se ve message_id (idempotencia)."""
        with self._lock, self._conn() as c:
            try:
                c.execute(
                    "INSERT INTO seen_msgs (id, ts) VALUES (?, ?)",
                    (message_id, time.time()),
                )
                return True
            except sqlite3.IntegrityError:
                return False


class RedisStore:
    PREFIX = "a73:"

    def __init__(self, url: str | None = None):
        import redis  # import perezoso: solo en producción

        self.r = redis.Redis.from_url(url or config.REDIS_URL, decode_responses=True)
        self.r.ping()

    def save(self, job: dict) -> None:
        pipe = self.r.pipeline()
        pipe.set(self.PREFIX + "job:" + job["id"], json.dumps(job))
        if job["state"] in ACTIVE_STATES:
            pipe.sadd(self.PREFIX + "active", job["id"])
        else:
            pipe.srem(self.PREFIX + "active", job["id"])
        pipe.execute()

    def get(self, job_id: str) -> dict | None:
        data = self.r.get(self.PREFIX + "job:" + job_id)
        return json.loads(data) if data else None

    def list_active(self) -> list[dict]:
        ids = self.r.smembers(self.PREFIX + "active")
        jobs = [self.get(i) for i in ids]
        return [j for j in jobs if j]

    def first_seen(self, message_id: str) -> bool:
        return bool(
            self.r.set(self.PREFIX + "msg:" + message_id, "1", nx=True, ex=86400)
        )


def make_store():
    """Elige backend según config (auto: Redis si responde, si no SQLite)."""
    mode = config.STORAGE
    if mode in ("redis", "auto"):
        try:
            return RedisStore()
        except Exception:
            if mode == "redis":
                raise
    return SqliteStore()
