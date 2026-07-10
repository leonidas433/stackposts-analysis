"""Contrato de un runner de fase."""

from __future__ import annotations

from dataclasses import dataclass, field


@dataclass
class PhaseResult:
    status: str                  # ok | fail | wait  (wait = gate/espera humana)
    summary: str = ""            # una línea para el historial y WhatsApp
    artifacts: dict = field(default_factory=dict)   # nombre -> ruta relativa

    @classmethod
    def ok(cls, summary: str = "", **artifacts) -> "PhaseResult":
        return cls("ok", summary, dict(artifacts))

    @classmethod
    def fail(cls, summary: str) -> "PhaseResult":
        return cls("fail", summary)

    @classmethod
    def wait(cls, summary: str = "") -> "PhaseResult":
        return cls("wait", summary)


class Runner:
    """Un runner ejecuta UNA fase para UN job y devuelve PhaseResult."""

    def run(self, job: dict, phase: dict) -> PhaseResult:  # pragma: no cover
        raise NotImplementedError
