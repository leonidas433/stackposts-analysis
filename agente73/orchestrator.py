"""Orquestador del Agente 73: máquina de estados de 13 fases.

Diseño:
- Las fases viven en phases.yaml; el orquestador no sabe QUÉ hace cada una,
  solo CÓMO encadenarlas (avance, reintentos, gate humano, cancelación).
- `advance(job)` ejecuta fases en orden hasta terminar, fallar o toparse con
  un gate (F09) o una espera de input (F02). Es reentrante: se puede volver
  a llamar tras APRUEBA/RECHAZA o tras un reinicio del servicio.
- El gate humano NO tiene bypass: la única transición desde `waiting_qa`
  es approve()/reject()/cancel() disparadas por la gramática cerrada.
"""

from __future__ import annotations

import logging
from pathlib import Path

import yaml

from . import storage
from .runners.base import PhaseResult, Runner

log = logging.getLogger("a73.orchestrator")

PHASES_FILE = Path(__file__).resolve().parent / "phases.yaml"
QA_PHASE = 9


def load_phases() -> list[dict]:
    with open(PHASES_FILE, encoding="utf-8") as f:
        data = yaml.safe_load(f)
    phases = data["phases"]
    assert [p["id"] for p in phases] == list(range(1, len(phases) + 1)), (
        "phases.yaml debe tener ids consecutivos desde 1"
    )
    return phases


class Orchestrator:
    def __init__(self, store, runners: dict[str, Runner], notifier=None):
        """
        store:    storage.SqliteStore | storage.RedisStore
        runners:  {"claude": ..., "shell": ..., "notify": ..., "internal": ...}
        notifier: callable(job, text) para mensajes de progreso (WhatsApp).
        """
        self.store = store
        self.runners = runners
        self.notify = notifier or (lambda job, text: None)
        self.phases = load_phases()

    # --- ciclo principal ------------------------------------------------

    def start(self, job: dict) -> dict:
        self.store.save(job)
        return self.advance(job)

    def advance(self, job: dict) -> dict:
        while job["state"] == "running" and job["phase"] <= len(self.phases):
            phase = self.phases[job["phase"] - 1]

            if job["kind"] not in phase.get("verticals", []):
                storage.log_event(job, f"F{phase['id']:02d} omitida (vertical)")
                job["phase"] += 1
                self.store.save(job)
                continue

            runner = self.runners[phase["runner"]]
            try:
                result = runner.run(job, phase)
            except Exception as exc:  # un runner nunca debe tumbar el servicio
                log.exception("F%02d reventó", phase["id"])
                result = PhaseResult.fail(f"excepción: {exc}")

            job["artifacts"].update(result.artifacts)
            storage.log_event(
                job, f"F{phase['id']:02d} {phase['name']}: {result.status} {result.summary}".strip()
            )

            if result.status == "ok":
                if phase.get("gate") == "human":
                    job["state"] = "waiting_qa"
                    self.store.save(job)
                    self.notify(
                        job,
                        f"🛑 Job {job['id']} parado en QA (F09). "
                        f"Responde APRUEBA {job['id']} o RECHAZA {job['id']} <motivo>.",
                    )
                    return job
                job["phase"] += 1
                self.store.save(job)
                continue

            if result.status == "wait":
                job["state"] = "waiting_input"
                self.store.save(job)
                return job

            # fail → política de reintentos de la fase
            return self._handle_fail(job, phase, result)

        if job["state"] == "running":
            job["state"] = "done"
            self.store.save(job)
            self.notify(job, f"✅ Job {job['id']} completado.")
        return job

    def _handle_fail(self, job: dict, phase: dict, result: PhaseResult) -> dict:
        policy = phase.get("on_fail") or {}
        retry_phase = policy.get("retry_phase")
        max_retries = int(policy.get("max_retries", 0))
        key = str(phase["id"])
        used = int(job["retries"].get(key, 0))

        if retry_phase and used < max_retries:
            job["retries"][key] = used + 1
            job["feedback"] = result.summary
            job["phase"] = int(retry_phase)
            storage.log_event(
                job, f"reintento {used + 1}/{max_retries}: vuelta a F{retry_phase:02d}"
            )
            self.store.save(job)
            return self.advance(job)

        job["state"] = "failed"
        self.store.save(job)
        self.notify(job, f"❌ Job {job['id']} falló en F{phase['id']:02d}: {result.summary}")
        return job

    # --- transiciones humanas (gramática cerrada) -------------------------

    def approve(self, job: dict) -> dict:
        if job["state"] != "waiting_qa":
            return job
        storage.log_event(job, "QA aprobado")
        job["state"] = "running"
        job["phase"] = QA_PHASE + 1
        self.store.save(job)
        return self.advance(job)

    def reject(self, job: dict, reason: str = "") -> dict:
        """Un rechazo devuelve el job a Generación (F07) con el feedback.
        El segundo rechazo cierra el job como `rejected`."""
        if job["state"] != "waiting_qa":
            return job
        job["qa_rejections"] += 1
        job["feedback"] = reason or "rechazado sin motivo"
        storage.log_event(job, f"QA rechazado ({job['qa_rejections']}): {job['feedback']}")
        if job["qa_rejections"] > 1:
            job["state"] = "rejected"
            self.store.save(job)
            self.notify(job, f"🚫 Job {job['id']} cerrado tras segundo rechazo.")
            return job
        job["state"] = "running"
        job["phase"] = 7
        self.store.save(job)
        self.notify(job, f"🔁 Job {job['id']}: regenerando con tu feedback.")
        return self.advance(job)

    def cancel(self, job: dict) -> dict:
        if job["state"] in storage.FINAL_STATES:
            return job
        job["state"] = "cancelled"
        storage.log_event(job, "cancelado por el usuario")
        self.store.save(job)
        return job

    def resume_input(self, job: dict, text: str) -> dict:
        """Continúa un job en waiting_input con la respuesta del usuario (F02)."""
        if job["state"] != "waiting_input":
            return job
        job["spec"]["extra_input"] = job["spec"].get("extra_input", "") + "\n" + text
        storage.log_event(job, "input recibido")
        job["state"] = "running"
        self.store.save(job)
        return self.advance(job)

    # --- consultas ---------------------------------------------------------

    def status_text(self, job: dict) -> str:
        phase = self.phases[min(job["phase"], len(self.phases)) - 1]
        icons = {
            "running": "⚙️", "waiting_qa": "🛑", "waiting_input": "❓",
            "done": "✅", "failed": "❌", "cancelled": "🚫", "rejected": "🚫",
        }
        return (
            f"{icons.get(job['state'], '•')} Job {job['id']} [{job['kind']}] "
            f"F{job['phase']:02d} {phase['name']} — {job['state']}"
        )
