"""Runner de fases mecánicas: build+validación (F08) y empaquetado (F10).

Reutiliza las herramientas del repo de temas: `npm run build --theme=` y
`php scripts/validate-theme.php`. El ZIP de F10 cumple el contrato del
import endurecido del admin (un único directorio raíz = id del tema, sin
basura ni artefactos de desarrollo).
"""

from __future__ import annotations

import logging
import subprocess
import zipfile
from pathlib import Path

from .. import config, storage
from .base import PhaseResult, Runner

log = logging.getLogger("a73.shell")

EXCLUDE_DIRS = {"node_modules", ".git", "__pycache__"}
EXCLUDE_SUFFIXES = (".map", ".bak")
BUILD_PHASE, PACKAGE_PHASE = 8, 10


class ShellRunner(Runner):
    def __init__(self, repo_dir: str | None = None):
        self.repo_dir = Path(repo_dir or config.REPO_DIR)

    def run(self, job: dict, phase: dict) -> PhaseResult:
        if phase["id"] == BUILD_PHASE:
            return self.build_and_validate(job, phase)
        if phase["id"] == PACKAGE_PHASE:
            return self.package(job, phase)
        return PhaseResult.ok("fase shell sin acción definida")

    # --- F08 ----------------------------------------------------------------

    def build_and_validate(self, job: dict, phase: dict) -> PhaseResult:
        theme_id = job["spec"].get("theme_id", "")
        theme_dir = self.repo_dir / "resources/themes/guest" / theme_id
        if not theme_dir.is_dir():
            return PhaseResult.fail(f"el tema guest/{theme_id} no existe tras la generación")

        report = [f"# QA automático — guest/{theme_id}", ""]

        has_assets = (theme_dir / "assets/css/app.css").is_file() or (
            theme_dir / "assets/js/app.js"
        ).is_file()
        if has_assets:
            ok, out = self._cmd(
                ["npm", "run", "build", f"--theme=guest/{theme_id}"], timeout=600
            )
            report += ["## Build Vite", "```", out[-2000:], "```"]
            if not ok:
                self._write_report(job, phase, report)
                return PhaseResult.fail(f"build de Vite falló: {out[-200:]}")
        else:
            report.append("## Build Vite\nSin assets propios: hereda el build del padre.")

        ok, out = self._cmd(
            ["php", "scripts/validate-theme.php", f"guest/{theme_id}"], timeout=60
        )
        report += ["## Validador de contrato", "```", out, "```"]
        self._write_report(job, phase, report)
        if not ok:
            return PhaseResult.fail(f"validador: {out.strip().splitlines()[0] if out else 'error'}")

        artifacts = {phase["output"]: phase["output"]} if phase.get("output") else {}
        return PhaseResult("ok", "build y validación OK", artifacts)

    # --- F10 ----------------------------------------------------------------

    def package(self, job: dict, phase: dict) -> PhaseResult:
        theme_id = job["spec"].get("theme_id", "")
        theme_dir = self.repo_dir / "resources/themes/guest" / theme_id
        if job["kind"] != "tema":
            return PhaseResult.ok("vertical web: empaquetado delegado a entrega manual")
        if not theme_dir.is_dir():
            return PhaseResult.fail(f"no existe guest/{theme_id} para empaquetar")

        zip_path = storage.job_dir(job) / f"{theme_id}.zip"
        count = 0
        with zipfile.ZipFile(zip_path, "w", zipfile.ZIP_DEFLATED) as zf:
            for path in sorted(theme_dir.rglob("*")):
                if path.is_dir():
                    continue
                rel = path.relative_to(theme_dir)
                if any(part in EXCLUDE_DIRS for part in rel.parts):
                    continue
                if rel.name.startswith(".env") or str(rel).endswith(EXCLUDE_SUFFIXES):
                    continue
                zf.write(path, f"{theme_id}/{rel}")   # raíz única = id del tema
                count += 1

        if count == 0:
            return PhaseResult.fail("el ZIP quedó vacío")
        return PhaseResult(
            "ok",
            f"{zip_path.name} con {count} archivos",
            {"paquete.zip": zip_path.name},
        )

    # --- util -----------------------------------------------------------------

    def _cmd(self, cmd: list[str], timeout: int) -> tuple[bool, str]:
        try:
            proc = subprocess.run(
                cmd, cwd=self.repo_dir, capture_output=True, text=True, timeout=timeout
            )
        except FileNotFoundError as exc:
            return False, str(exc)
        except subprocess.TimeoutExpired:
            return False, f"timeout {timeout}s: {' '.join(cmd)}"
        return proc.returncode == 0, (proc.stdout + proc.stderr)

    def _write_report(self, job: dict, phase: dict, lines: list[str]) -> None:
        if phase.get("output"):
            (storage.job_dir(job) / phase["output"]).write_text(
                "\n".join(lines), encoding="utf-8"
            )
