"""Runner de fases de comunicación: QA humano (F09), entrega (F11) y cierre (F13)."""

from __future__ import annotations

import logging

from .. import config, notify, storage
from .base import PhaseResult, Runner

log = logging.getLogger("a73.notifyrunner")

QA_PHASE, DELIVERY_PHASE, CLOSE_PHASE = 9, 11, 13
MAX_WA_CHARS = 3000


class NotifyRunner(Runner):
    def run(self, job: dict, phase: dict) -> PhaseResult:
        if phase["id"] == QA_PHASE:
            return self.qa_report(job, phase)
        if phase["id"] == DELIVERY_PHASE:
            return self.deliver(job)
        if phase["id"] == CLOSE_PHASE:
            return self.close(job)
        return PhaseResult.ok()

    def qa_report(self, job: dict, phase: dict) -> PhaseResult:
        jdir = storage.job_dir(job)
        qa_auto = (jdir / "qa-auto.md")
        report = [
            f"🧪 *QA del job {job['id']}* ({job['kind']}: "
            f"{job['spec'].get('theme_id') or job['spec'].get('domain')})",
            "",
            f"Encargo: {job['spec'].get('description', '')[:300]}",
        ]
        if qa_auto.is_file():
            tail = qa_auto.read_text(encoding="utf-8", errors="replace")[-800:]
            report += ["", "Validación automática (final):", tail]
        report += [
            "",
            f"➡️ APRUEBA {job['id']} para empaquetar y entregar",
            f"➡️ RECHAZA {job['id']} <motivo> para regenerar",
        ]
        text = "\n".join(report)[:MAX_WA_CHARS]

        if phase.get("output"):
            (jdir / phase["output"]).write_text(text, encoding="utf-8")

        notify.send_wa(job["sender"], text)
        notify.send_email(
            f"[Agente73] QA pendiente — job {job['id']}",
            text,
            [str(qa_auto)] if qa_auto.is_file() else None,
        )
        # El gate lo aplica el orquestador (phase.gate == human); aquí solo avisamos.
        artifacts = {phase["output"]: phase["output"]} if phase.get("output") else {}
        return PhaseResult("ok", "informe de QA enviado", artifacts)

    def deliver(self, job: dict) -> PhaseResult:
        jdir = storage.job_dir(job)
        zip_name = job["artifacts"].get("paquete.zip")
        zip_path = jdir / zip_name if zip_name else None

        body = (
            f"Entrega del job {job['id']} ({job['kind']}).\n"
            f"Encargo: {job['spec'].get('description', '')}\n\n"
            "El ZIP adjunto cumple el contrato del import del admin "
            "(Admin → Themes → Frontend → Import). Actívalo solo tras el "
            "checklist de staging (docs/guia-crear-un-tema.md §11)."
        )
        sent = notify.send_email(
            f"[Agente73] Entrega — job {job['id']}",
            body,
            [str(zip_path)] if zip_path and zip_path.is_file() else None,
        )
        notify.send_wa(
            job["sender"],
            f"📦 Job {job['id']} entregado"
            + (" por email." if sent else ". (Email no configurado: el ZIP queda en el servidor.)"),
        )

        if config.DELIVER_GIT_PR:
            log.info(
                "A73_DELIVER_GIT_PR activo: la rama/PR se crea con el comando "
                "documentado en el RUNBOOK (paso opcional post-entrega)."
            )
        return PhaseResult.ok("entrega realizada")

    def close(self, job: dict) -> PhaseResult:
        mins = int((job["updated_at"] - job["created_at"]) / 60)
        notify.send_wa(
            job["sender"],
            f"✅ Job {job['id']} cerrado. Fases: 13/13 · rechazos QA: "
            f"{job['qa_rejections']} · duración ≈{mins} min. ¡Gracias!",
        )
        return PhaseResult.ok("cierre notificado")
