"""Runner de fases internas: intake (F01) y registro (F12)."""

from __future__ import annotations

import json
import logging

from .. import notify, storage
from .base import PhaseResult, Runner

log = logging.getLogger("a73.internal")

INTAKE_PHASE, LOG_PHASE = 1, 12


class InternalRunner(Runner):
    def run(self, job: dict, phase: dict) -> PhaseResult:
        if phase["id"] == INTAKE_PHASE:
            jdir = storage.job_dir(job)
            (jdir / "spec.json").write_text(
                json.dumps(job["spec"], ensure_ascii=False, indent=2), encoding="utf-8"
            )
            notify.send_wa(
                job["sender"],
                f"🎬 Job {job['id']} aceptado ({job['kind']}). Te aviso en el QA (F09). "
                f"Consulta con: ESTADO {job['id']}",
            )
            return PhaseResult.ok("job creado", **{"spec.json": "spec.json"})

        if phase["id"] == LOG_PHASE:
            jdir = storage.job_dir(job)
            log_lines = [
                f"{ts:.0f}\tF{ph:02d}\t{event}" for ts, ph, event in job["history"]
            ]
            (jdir / "registro.log").write_text("\n".join(log_lines), encoding="utf-8")
            log.info("job %s registrado (%d eventos)", job["id"], len(log_lines))
            return PhaseResult.ok("registro escrito", **{"registro.log": "registro.log"})

        return PhaseResult.ok()
