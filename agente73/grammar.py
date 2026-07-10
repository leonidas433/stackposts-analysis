"""Gramática CERRADA del Agente 73.

Solo se aceptan los comandos definidos aquí; cualquier otro mensaje se
responde con la ayuda. La gramática es deliberadamente estricta: el canal
de entrada es WhatsApp y no debe existir forma de inyectar instrucciones
libres en el pipeline.

Comandos:
    TEMA <id> <color> <descripción...>   crea un tema hijo de guest/nova
    WEB <dominio> <descripción...>       (experimental) crea una web
    ESTADO [job]                         estado de un job o de los activos
    APRUEBA <job>                        continúa un job parado en QA (F09)
    RECHAZA <job> [motivo...]            rechaza el QA; reintenta generación
    CANCELA <job>                        cancela un job
    AYUDA                                esta ayuda
"""

from __future__ import annotations

import re
from dataclasses import dataclass, field

THEME_ID_RE = re.compile(r"^[a-z0-9][a-z0-9_-]{1,49}$")
COLOR_RE = re.compile(r"^#(?:[0-9a-fA-F]{3}|[0-9a-fA-F]{6})$")
DOMAIN_RE = re.compile(r"^(?=.{4,253}$)([a-z0-9-]{1,63}\.)+[a-z]{2,24}$")
JOB_ID_RE = re.compile(r"^[a-z0-9]{6,12}$")

COMMANDS = ("TEMA", "WEB", "ESTADO", "APRUEBA", "RECHAZA", "CANCELA", "AYUDA")

HELP_TEXT = (
    "🤖 *Agente 73 — comandos*\n"
    "TEMA <id> <color> <descripción> — nuevo tema (ej: TEMA blackfriday #b91c1c landing oscura de rebajas)\n"
    "WEB <dominio> <descripción> — nueva web (experimental)\n"
    "ESTADO [job] — estado de tus trabajos\n"
    "APRUEBA <job> — aprobar el QA pendiente\n"
    "RECHAZA <job> [motivo] — rechazar el QA\n"
    "CANCELA <job> — cancelar un trabajo\n"
    "AYUDA — esta ayuda"
)


@dataclass
class Command:
    kind: str                    # uno de COMMANDS
    args: dict = field(default_factory=dict)


@dataclass
class GrammarError:
    message: str


def parse(text: str) -> Command | GrammarError:
    """Parsea un mensaje entrante. Gramática cerrada: o casa, o error."""
    if not text or not text.strip():
        return GrammarError(HELP_TEXT)

    parts = text.strip().split()
    verb = parts[0].upper()
    rest = parts[1:]

    if verb not in COMMANDS:
        return GrammarError("No entiendo ese comando.\n\n" + HELP_TEXT)

    if verb == "AYUDA":
        return Command("AYUDA")

    if verb == "TEMA":
        if len(rest) < 3:
            return GrammarError(
                "Uso: TEMA <id> <color> <descripción>\n"
                "Ej.: TEMA blackfriday #b91c1c landing oscura para las rebajas"
            )
        theme_id, color = rest[0].lower(), rest[1]
        description = " ".join(rest[2:])
        if not THEME_ID_RE.match(theme_id) or theme_id == "_shared":
            return GrammarError(
                "El id del tema debe ser minúsculas/números/guiones (2-50) "
                "y no puede ser un nombre reservado."
            )
        if not COLOR_RE.match(color):
            return GrammarError("El color debe ser hex, p. ej. #b91c1c")
        if len(description) > 500:
            return GrammarError("La descripción no puede superar 500 caracteres.")
        return Command(
            "TEMA",
            {"theme_id": theme_id, "color": color.lower(), "description": description},
        )

    if verb == "WEB":
        if len(rest) < 2:
            return GrammarError("Uso: WEB <dominio> <descripción>")
        domain = rest[0].lower()
        description = " ".join(rest[1:])
        if not DOMAIN_RE.match(domain):
            return GrammarError("Ese dominio no parece válido (ej.: miweb.com)")
        if len(description) > 500:
            return GrammarError("La descripción no puede superar 500 caracteres.")
        return Command("WEB", {"domain": domain, "description": description})

    if verb == "ESTADO":
        if not rest:
            return Command("ESTADO", {"job_id": None})
        if not JOB_ID_RE.match(rest[0].lower()):
            return GrammarError("Uso: ESTADO [job] — el job es el código corto del trabajo.")
        return Command("ESTADO", {"job_id": rest[0].lower()})

    if verb in ("APRUEBA", "CANCELA"):
        if len(rest) != 1 or not JOB_ID_RE.match(rest[0].lower()):
            return GrammarError(f"Uso: {verb} <job>")
        return Command(verb, {"job_id": rest[0].lower()})

    if verb == "RECHAZA":
        if not rest or not JOB_ID_RE.match(rest[0].lower()):
            return GrammarError("Uso: RECHAZA <job> [motivo]")
        return Command(
            "RECHAZA",
            {"job_id": rest[0].lower(), "reason": " ".join(rest[1:])[:500]},
        )

    return GrammarError(HELP_TEXT)  # inalcanzable; defensa en profundidad
