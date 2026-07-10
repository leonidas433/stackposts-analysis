# =================================================================
# Autor:   Leo / Agente 73
# Módulo:  app
# Versión: 4.0.0 "Con Alma"
# Método:  Servicio Flask (127.0.0.1:8091). Recibe mensajes del
#          whatsapp-bridge en POST /webhook (HMAC + whitelist +
#          idempotencia + rate-limit), parsea la gramática cerrada
#          y orquesta el pipeline de 13 fases con QA stop en F09.
# =================================================================

from __future__ import annotations

import hashlib
import hmac
import logging
import threading
import time
from collections import defaultdict, deque

from flask import Flask, abort, jsonify, request

from . import config, notify, storage
from .grammar import GrammarError, HELP_TEXT, parse
from .orchestrator import Orchestrator
from .runners.claude_runner import ClaudeRunner
from .runners.internal_runner import InternalRunner
from .runners.notify_runner import NotifyRunner
from .runners.shell_runner import ShellRunner

logging.basicConfig(
    level=config.LOG_LEVEL,
    format="%(asctime)s [%(levelname)s] %(name)s: %(message)s",
)
log = logging.getLogger("agente73")

app = Flask(__name__)
store = storage.make_store()
orchestrator = Orchestrator(
    store,
    runners={
        "claude": ClaudeRunner(),
        "shell": ShellRunner(),
        "notify": NotifyRunner(),
        "internal": InternalRunner(),
    },
    notifier=lambda job, text: notify.send_wa(job["sender"], text),
)

_rate: dict[str, deque] = defaultdict(deque)
_rate_lock = threading.Lock()


def _verify_hmac(raw_body: bytes, signature: str | None) -> bool:
    if not config.HMAC_SECRET:
        log.error("A73_HMAC_SECRET vacío: rechazo todo por seguridad")
        return False
    if not signature:
        return False
    expected = hmac.new(
        config.HMAC_SECRET.encode(), raw_body, hashlib.sha256
    ).hexdigest()
    return hmac.compare_digest(expected, signature.strip().lower())


def _rate_limited(sender: str) -> bool:
    now = time.time()
    with _rate_lock:
        q = _rate[sender]
        while q and q[0] < now - 3600:
            q.popleft()
        if len(q) >= config.RATE_LIMIT_PER_HOUR:
            return True
        q.append(now)
    return False


def _handle_command(sender: str, text: str) -> str:
    """Devuelve la respuesta inmediata para WhatsApp. Los pipelines corren
    en un hilo aparte: WhatsApp recibe el ack al instante."""
    cmd = parse(text)
    if isinstance(cmd, GrammarError):
        return cmd.message

    if cmd.kind == "AYUDA":
        return HELP_TEXT

    if cmd.kind == "ESTADO":
        if cmd.args["job_id"]:
            job = store.get(cmd.args["job_id"])
            if not job or job["sender"] != sender:
                return "No encuentro ese job."
            return orchestrator.status_text(job)
        jobs = [j for j in store.list_active() if j["sender"] == sender]
        if not jobs:
            return "No tienes trabajos activos."
        return "\n".join(orchestrator.status_text(j) for j in jobs[:10])

    if cmd.kind in ("APRUEBA", "RECHAZA", "CANCELA"):
        job = store.get(cmd.args["job_id"])
        if not job or job["sender"] != sender:
            return "No encuentro ese job."
        action = {
            "APRUEBA": lambda: orchestrator.approve(job),
            "RECHAZA": lambda: orchestrator.reject(job, cmd.args.get("reason", "")),
            "CANCELA": lambda: orchestrator.cancel(job),
        }[cmd.kind]
        threading.Thread(target=action, daemon=True).start()
        return f"Recibido: {cmd.kind} {job['id']}. Te voy contando."

    if cmd.kind in ("TEMA", "WEB"):
        # ¿Respuesta a un job esperando input? La gramática cerrada manda:
        # un TEMA/WEB nuevo siempre crea un job nuevo.
        job = storage.new_job(cmd.kind.lower(), sender, cmd.args)
        threading.Thread(target=orchestrator.start, args=(job,), daemon=True).start()
        return (
            f"🎬 En marcha: job {job['id']} ({cmd.kind.lower()}). "
            f"El QA (F09) te pedirá aprobación — no hay entrega sin tu OK."
        )

    return HELP_TEXT


@app.post("/webhook")
def webhook():
    raw = request.get_data()
    if not _verify_hmac(raw, request.headers.get("X-Signature-256")):
        abort(401)

    data = request.get_json(silent=True) or {}
    sender = str(data.get("from", "")).strip()
    text = str(data.get("message", "")).strip()
    message_id = str(data.get("id", "")).strip()

    if sender not in config.WHITELIST:
        log.warning("remitente no autorizado: %s", sender)
        return jsonify({"status": "ignored"})

    if message_id and not store.first_seen(f"{sender}:{message_id}"):
        return jsonify({"status": "duplicate"})

    if _rate_limited(sender):
        notify.send_wa(sender, "⏳ Demasiados comandos esta hora. Prueba más tarde.")
        return jsonify({"status": "rate_limited"})

    reply = _handle_command(sender, text)
    notify.send_wa(sender, reply)
    return jsonify({"status": "ok"})


@app.get("/health")
def health():
    return jsonify(
        {
            "service": "agente73",
            "version": "4.0.0",
            "active_jobs": len(store.list_active()),
        }
    )


if __name__ == "__main__":
    log.info("Agente 73 v4.0 'Con Alma' en %s:%s", config.HOST, config.PORT)
    app.run(host=config.HOST, port=config.PORT)
