"""Canales de salida: WhatsApp (via whatsapp-bridge local) y email (Gmail).

Ambos son "best effort": un fallo de notificación se registra pero no debe
tumbar el pipeline (la fase notify decide si es crítico).
"""

from __future__ import annotations

import json
import logging
import mimetypes
import smtplib
import urllib.request
from email.message import EmailMessage
from pathlib import Path

from . import config

log = logging.getLogger("a73.notify")


def send_wa(to: str, text: str) -> bool:
    """POST al whatsapp-bridge local (127.0.0.1:8080 por defecto)."""
    url = config.BRIDGE_URL.rstrip("/") + config.BRIDGE_SEND_PATH
    payload = json.dumps({"to": to, "message": text}).encode()
    req = urllib.request.Request(
        url, data=payload, headers={"Content-Type": "application/json"}
    )
    try:
        with urllib.request.urlopen(req, timeout=10) as resp:
            return 200 <= resp.status < 300
    except Exception as exc:
        log.warning("WhatsApp no entregado a %s: %s", to, exc)
        return False


def send_email(subject: str, body: str, attachments: list[str] | None = None) -> bool:
    """Email vía Gmail SMTP con app password (NO la contraseña normal:
    un 535 BadCredentials casi siempre significa app password caducada)."""
    if not (config.GMAIL_USER and config.GMAIL_APP_PASSWORD and config.NOTIFY_EMAIL_TO):
        log.info("Email no configurado; se omite '%s'", subject)
        return False

    msg = EmailMessage()
    msg["From"] = config.GMAIL_USER
    msg["To"] = config.NOTIFY_EMAIL_TO
    msg["Subject"] = subject
    msg.set_content(body)

    for path in attachments or []:
        p = Path(path)
        if not p.is_file():
            continue
        ctype = mimetypes.guess_type(p.name)[0] or "application/octet-stream"
        maintype, subtype = ctype.split("/", 1)
        msg.add_attachment(
            p.read_bytes(), maintype=maintype, subtype=subtype, filename=p.name
        )

    try:
        with smtplib.SMTP_SSL("smtp.gmail.com", 465, timeout=30) as smtp:
            smtp.login(config.GMAIL_USER, config.GMAIL_APP_PASSWORD)
            smtp.send_message(msg)
        return True
    except smtplib.SMTPAuthenticationError as exc:
        log.error(
            "Gmail 535 BadCredentials: regenera la app password de %s (%s)",
            config.GMAIL_USER, exc,
        )
        return False
    except Exception as exc:
        log.warning("Email no enviado '%s': %s", subject, exc)
        return False
