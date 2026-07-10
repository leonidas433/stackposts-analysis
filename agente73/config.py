"""Configuración del Agente 73 (patrón wd-agent: .env plano, sin sorpresas)."""

from __future__ import annotations

import os
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent


def _load_dotenv(path: Path) -> None:
    if not path.is_file():
        return
    for line in path.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, _, value = line.partition("=")
        os.environ.setdefault(key.strip(), value.strip())


_load_dotenv(BASE_DIR / ".env")


def env(name: str, default: str = "") -> str:
    return os.environ.get(name, default)


# --- Servicio ---------------------------------------------------------------
HOST = env("A73_HOST", "127.0.0.1")
PORT = int(env("A73_PORT", "8091"))
LOG_LEVEL = env("A73_LOG_LEVEL", "INFO")

# --- Seguridad del webhook ---------------------------------------------------
# Secreto compartido con el whatsapp-bridge (HMAC-SHA256 del cuerpo crudo).
HMAC_SECRET = env("A73_HMAC_SECRET", "")
# Números E.164 autorizados, separados por comas: +34600000000,+34611111111
WHITELIST = tuple(n.strip() for n in env("A73_WHITELIST", "").split(",") if n.strip())
# Máx. comandos por remitente y hora (anti-abuso básico).
RATE_LIMIT_PER_HOUR = int(env("A73_RATE_LIMIT_PER_HOUR", "30"))

# --- Estado ------------------------------------------------------------------
REDIS_URL = env("A73_REDIS_URL", "redis://127.0.0.1:6379/3")
# Fallback / desarrollo: sqlite. "auto" usa Redis si responde, si no sqlite.
STORAGE = env("A73_STORAGE", "auto")  # auto | redis | sqlite
SQLITE_PATH = env("A73_SQLITE_PATH", str(BASE_DIR / "var" / "agente73.db"))
JOBS_DIR = Path(env("A73_JOBS_DIR", str(BASE_DIR / "var" / "jobs")))

# --- Motor de subagentes -----------------------------------------------------
# Clon de leonidas433/stackposts-analysis donde se generan/compilan los temas.
REPO_DIR = env("A73_REPO_DIR", "/opt/agente73/stackposts-analysis")
CLAUDE_BIN = env("A73_CLAUDE_BIN", "claude")
CLAUDE_TIMEOUT = int(env("A73_CLAUDE_TIMEOUT", "1800"))  # 30 min por fase
ANTHROPIC_API_KEY = env("ANTHROPIC_API_KEY", "")         # fallback API directa
ANTHROPIC_MODEL = env("A73_ANTHROPIC_MODEL", "claude-sonnet-5")

# --- Notificaciones ----------------------------------------------------------
BRIDGE_URL = env("A73_BRIDGE_URL", "http://127.0.0.1:8080")
BRIDGE_SEND_PATH = env("A73_BRIDGE_SEND_PATH", "/send")
GMAIL_USER = env("A73_GMAIL_USER", "holawebdoctor@gmail.com")
GMAIL_APP_PASSWORD = env("A73_GMAIL_APP_PASSWORD", "")   # app password, NO la normal
NOTIFY_EMAIL_TO = env("A73_NOTIFY_EMAIL_TO", "")

# --- Entrega opcional por PR --------------------------------------------------
DELIVER_GIT_PR = env("A73_DELIVER_GIT_PR", "0") == "1"
