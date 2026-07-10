"""Runner de fases "inteligentes": ejecuta subagentes vía Claude Code headless.

Modo principal: `claude -p` con cwd en el clon de stackposts-analysis
(config.REPO_DIR) para que las fases de generación puedan crear archivos del
tema usando el agente del repo (.claude/agents/stackpost-theme-builder.md).

Fallback (solo fases de texto, nunca F07): API de Anthropic directa si el
CLI no está disponible y hay ANTHROPIC_API_KEY.
"""

from __future__ import annotations

import json
import logging
import subprocess
import urllib.request
from pathlib import Path

from .. import config, storage
from .base import PhaseResult, Runner

log = logging.getLogger("a73.claude")

SUBAGENTS_DIR = Path(__file__).resolve().parent.parent / "subagents"
CONTRACTS_DIR = Path(__file__).resolve().parent.parent / "contracts"

# Artefactos previos que se inyectan como contexto en cada fase.
CONTEXT_ARTIFACTS = (
    "brief.md", "investigacion.md", "arquitectura.md", "copy.md", "diseno.md",
)
MAX_CONTEXT_CHARS = 12000
GENERATION_PHASE = 7


def _read(path: Path, limit: int = MAX_CONTEXT_CHARS) -> str:
    try:
        return path.read_text(encoding="utf-8", errors="replace")[:limit]
    except OSError:
        return ""


class ClaudeRunner(Runner):
    def __init__(self, repo_dir: str | None = None):
        self.repo_dir = Path(repo_dir or config.REPO_DIR)

    # --- prompt -----------------------------------------------------------

    def build_prompt(self, job: dict, phase: dict) -> str:
        parts: list[str] = [
            f"Eres parte del Agente 73 v4.0 'Con Alma' (pipeline de {job['kind']}s "
            f"para StackPost/analytee.com). Estás ejecutando la Fase "
            f"{phase['id']:02d} — {phase['name']} del job {job['id']}.",
            f"\n## Encargo original\n{json.dumps(job['spec'], ensure_ascii=False, indent=2)}",
        ]

        if job.get("feedback"):
            parts.append(f"\n## Feedback pendiente de corregir\n{job['feedback']}")

        jdir = storage.job_dir(job)
        context = [
            f"### {name}\n{_read(jdir / name)}"
            for name in CONTEXT_ARTIFACTS
            if name != phase.get("output") and (jdir / name).is_file()
        ]
        if context:
            parts.append("\n## Trabajo de fases anteriores\n" + "\n\n".join(context))

        if phase.get("contract"):
            contract_name = (
                "web-generica.md" if job["kind"] == "web" else phase["contract"]
            )
            parts.append(
                "\n## CONTRATO OBLIGATORIO\n" + _read(CONTRACTS_DIR / contract_name)
            )

        for name in phase.get("subagents", []):
            body = _read(SUBAGENTS_DIR / f"{name}.md")
            if body:
                parts.append(f"\n## Subagente: {name}\n{body}")

        if phase["id"] == GENERATION_PHASE and job["kind"] == "tema":
            parts.append(
                "\n## Instrucción de ejecución\n"
                "Estás dentro del repositorio stackposts-analysis. Crea el tema "
                f"hijo `guest/{job['spec'].get('theme_id')}` siguiendo el contrato "
                "y las reglas de .claude/agents/stackpost-theme-builder.md. "
                "Usa los textos, arquitectura y diseño de las fases anteriores. "
                "NO ejecutes git. NO toques nada fuera de "
                f"resources/themes/guest/{job['spec'].get('theme_id')}/. "
                "Termina con un resumen de los archivos creados."
            )
        else:
            parts.append(
                "\n## Instrucción de ejecución\n"
                "Produce el entregable de esta fase como texto markdown claro y "
                "accionable para las fases siguientes. No ejecutes comandos."
            )

        return "\n".join(parts)

    # --- ejecución ----------------------------------------------------------

    def run(self, job: dict, phase: dict) -> PhaseResult:
        prompt = self.build_prompt(job, phase)
        output, err = self._run_cli(prompt, allow_writes=phase["id"] == GENERATION_PHASE)

        if output is None and phase["id"] != GENERATION_PHASE:
            output, err = self._run_api(prompt)
        if output is None:
            return PhaseResult.fail(f"subagentes no disponibles: {err}")

        artifacts = {}
        if phase.get("output"):
            out_name = phase["output"]
            (storage.job_dir(job) / out_name).write_text(output, encoding="utf-8")
            artifacts[out_name] = out_name

        summary = output.strip().splitlines()[-1][:160] if output.strip() else "sin salida"
        return PhaseResult("ok", summary, artifacts)

    def _run_cli(self, prompt: str, allow_writes: bool) -> tuple[str | None, str]:
        cmd = [config.CLAUDE_BIN, "-p", prompt, "--output-format", "text"]
        if allow_writes:
            # Sandbox de confianza: clon dedicado, usuario dedicado (RUNBOOK).
            cmd.append("--dangerously-skip-permissions")
        try:
            proc = subprocess.run(
                cmd,
                cwd=self.repo_dir if self.repo_dir.is_dir() else None,
                capture_output=True,
                text=True,
                timeout=config.CLAUDE_TIMEOUT,
            )
        except FileNotFoundError:
            return None, f"CLI '{config.CLAUDE_BIN}' no encontrado"
        except subprocess.TimeoutExpired:
            return None, f"timeout de {config.CLAUDE_TIMEOUT}s"
        if proc.returncode != 0:
            return None, (proc.stderr or proc.stdout)[-400:]
        return proc.stdout, ""

    def _run_api(self, prompt: str) -> tuple[str | None, str]:
        if not config.ANTHROPIC_API_KEY:
            return None, "sin CLI y sin ANTHROPIC_API_KEY"
        payload = json.dumps(
            {
                "model": config.ANTHROPIC_MODEL,
                "max_tokens": 4096,
                "messages": [{"role": "user", "content": prompt}],
            }
        ).encode()
        req = urllib.request.Request(
            "https://api.anthropic.com/v1/messages",
            data=payload,
            headers={
                "Content-Type": "application/json",
                "x-api-key": config.ANTHROPIC_API_KEY,
                "anthropic-version": "2023-06-01",
            },
        )
        try:
            with urllib.request.urlopen(req, timeout=300) as resp:
                data = json.loads(resp.read())
            return "".join(
                b.get("text", "") for b in data.get("content", [])
            ), ""
        except Exception as exc:
            return None, f"API: {exc}"
