# Agente 73 v4.0 "Con Alma"

Pipeline autónomo de creación de **temas StackPost** (y webs, experimental)
disparado por **WhatsApp** con gramática cerrada, **13 fases**, **20
subagentes** y **parada de QA humana obligatoria en la Fase 09**. No existe
ningún camino desde WhatsApp hasta un deploy: la entrega es siempre un ZIP
que un humano importa y activa tras el checklist de staging.

```
WhatsApp ──▶ whatsapp-bridge ──▶ POST /webhook (HMAC + whitelist + idempotencia)
                                      │ gramática cerrada (grammar.py)
                                      ▼
                          Orchestrator (13 fases, phases.yaml)
   F01 Intake ▶ F02 Brief ▶ F03 Investigación ▶ F04 Arquitectura ▶ F05 Copy
   ▶ F06 Diseño ▶ F07 Generación (claude -p + stackpost-theme-builder)
   ▶ F08 Build+Validación (npm + validate-theme.php; si falla ⟲ F07 ×2)
   ▶ F09 ⛔ QA HUMANO (APRUEBA / RECHAZA — RECHAZA regenera 1 vez)
   ▶ F10 ZIP (contrato del import) ▶ F11 Entrega (email/PR) ▶ F12 Registro ▶ F13 Cierre
```

## Comandos WhatsApp

| Comando | Efecto |
|---|---|
| `TEMA <id> <color> <descripción>` | Nuevo tema hijo de guest/nova |
| `WEB <dominio> <descripción>` | Nueva web estática (experimental) |
| `ESTADO [job]` | Estado de tus trabajos |
| `APRUEBA <job>` | Aprueba el QA pendiente (F09) |
| `RECHAZA <job> [motivo]` | Rechaza el QA; regenera con tu feedback (máx. 1 vez) |
| `CANCELA <job>` | Cancela un trabajo |
| `AYUDA` | Ayuda |

## Mapa del código

| Pieza | Archivo | Qué hace |
|---|---|---|
| Webhook | `app.py` | Flask 127.0.0.1:8091; HMAC-SHA256, whitelist E.164, idempotencia por mensaje, rate-limit |
| Gramática | `grammar.py` | Los 7 comandos; todo lo demás → ayuda |
| Orquestador | `orchestrator.py` + `phases.yaml` | Máquina de estados; reintentos F08→F07; gate F09 sin bypass |
| Jobs | `storage.py` | Redis (prod) o SQLite (dev); artefactos en `var/jobs/<id>/` |
| Motor IA | `runners/claude_runner.py` | `claude -p` headless en el clon del repo; fallback API en fases de texto |
| Mecánica | `runners/shell_runner.py` | Build Vite + `scripts/validate-theme.php` + ZIP conforme al import |
| Comunicación | `runners/notify_runner.py`, `notify.py` | WhatsApp vía bridge local; email Gmail (app password) |
| Subagentes | `subagents/*.md` (20) | Prompts por rol; se inyectan por fase según `phases.yaml` |
| Contratos | `contracts/` | `tema-stackpost.md` (fuente: docs/integracion-agente-73.md §2) y `web-generica.md` |

## Desarrollo

```bash
pip install flask pyyaml pytest
python3 -m pytest agente73/tests        # 29 tests
python3 -m agente73.app                 # servicio local (SQLite)
```

## Despliegue

Ver `deploy/RUNBOOK.md` (usuario dedicado, venv, Supervisor, conexión con el
bridge, smoke tests y rollback). Config por `.env` — plantilla en
`deploy/env.example`.

## Extender

- **Nueva fase**: añádela a `phases.yaml` (id consecutivo) y, si necesita
  lógica nueva, un runner en `runners/`.
- **Nuevo subagente**: un `.md` en `subagents/` + referencia en la fase.
- **Nuevo vertical**: contrato en `contracts/`, comando en `grammar.py`,
  y lista `verticals` por fase.
