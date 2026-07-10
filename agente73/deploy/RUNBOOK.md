# RUNBOOK — Despliegue del Agente 73 v4.0 "Con Alma" en el VPS

**Destino:** VPS de analytee (82.223.151.120, Plesk). Convive con `wd-agent`
(8090), `whatsapp-bridge` (8080) y Redis (6379) **sin tocar ninguno**.
El Agente 73 escucha en **127.0.0.1:8091**.

## 0. Prerrequisitos

- [ ] Acceso shell (SSH con clave, o terminal web de Plesk).
- [ ] `python3.11+`, `git`, `node 20+/npm`, `php8.3` en PATH (ya presentes:
      los usan wd-agent y analytee-worker).
- [ ] Claude Code CLI instalado y autenticado para el usuario del servicio
      (`claude --version`; si falta: `npm install -g @anthropic-ai/claude-code`
      y `claude login` una vez, de forma interactiva).
- [ ] **App password de Gmail nueva** para `holawebdoctor@gmail.com`
      (myaccount.google.com → Seguridad → Verificación en 2 pasos →
      Contraseñas de aplicaciones). El error `535 BadCredentials` pendiente
      viene de una app password revocada.
- [ ] El `whatsapp-bridge` debe poder reenviar mensajes entrantes a
      `http://127.0.0.1:8091/webhook` firmándolos (ver §4).

## 1. Usuario y directorios

```bash
sudo useradd -m -s /bin/bash agente73 || true
sudo mkdir -p /opt/agente73 /var/log/agente73
sudo chown -R agente73:agente73 /opt/agente73 /var/log/agente73
```

## 2. Código y dependencias

```bash
sudo -u agente73 bash -c '
  cd /opt/agente73 &&
  git clone https://github.com/leonidas433/stackposts-analysis.git &&
  python3 -m venv venv &&
  ./venv/bin/pip install --upgrade pip &&
  ./venv/bin/pip install flask pyyaml gunicorn redis &&
  ln -s stackposts-analysis/agente73 agente73 &&
  cd stackposts-analysis && npm install --no-audit --no-fund
'
```

> El clon de `/opt/agente73/stackposts-analysis` es el **taller** del agente
> (ahí genera y compila temas). No es el checkout de producción.

## 3. Configuración

```bash
sudo -u agente73 cp /opt/agente73/agente73/deploy/env.example /opt/agente73/agente73/.env
sudo -u agente73 nano /opt/agente73/agente73/.env
#  - A73_HMAC_SECRET: openssl rand -hex 32   (compartido con el bridge)
#  - A73_WHITELIST: tu número en E.164
#  - A73_GMAIL_APP_PASSWORD: la nueva app password
sudo chmod 600 /opt/agente73/agente73/.env
```

## 4. Conectar el whatsapp-bridge

El bridge debe reenviar cada mensaje entrante como:

```
POST http://127.0.0.1:8091/webhook
Content-Type: application/json
X-Signature-256: <hmac_sha256_hex(cuerpo_crudo, A73_HMAC_SECRET)>

{"id": "<id único del mensaje>", "from": "+34...", "message": "TEMA ..."}
```

Añade esa ruta/hook en la config del bridge (junto a la que ya alimenta a
wd-agent) usando el MISMO secreto que pusiste en `A73_HMAC_SECRET`.

## 5. Supervisor

```bash
sudo cp /opt/agente73/agente73/deploy/agente73.conf /etc/supervisor/conf.d/
sudo supervisorctl reread && sudo supervisorctl update
sudo supervisorctl status agente73     # → RUNNING
```

## 6. Smoke tests (en orden)

```bash
# 1) Servicio vivo
curl -s http://127.0.0.1:8091/health
#    → {"service":"agente73","version":"4.0.0","active_jobs":0}

# 2) Seguridad: sin firma debe dar 401
curl -s -o /dev/null -w "%{http_code}\n" -X POST http://127.0.0.1:8091/webhook -d '{}'

# 3) Webhook firmado end-to-end (sustituye SECRET por el real)
BODY='{"id":"smoke1","from":"+34TU_NUMERO","message":"AYUDA"}'
SIG=$(printf '%s' "$BODY" | openssl dgst -sha256 -hmac "SECRET" | awk '{print $2}')
curl -s -X POST http://127.0.0.1:8091/webhook -H "Content-Type: application/json" -H "X-Signature-256: $SIG" -d "$BODY"
#    → {"status":"ok"} y te llega la AYUDA por WhatsApp

# 4) Motor Claude headless (como usuario agente73)
sudo -u agente73 bash -c 'cd /opt/agente73/stackposts-analysis && claude -p "di ok" --output-format text'

# 5) Primer job real desde WhatsApp:
#    TEMA pilot73 #0f766e tema piloto para validar el pipeline
#    → ack con id de job → esperar QA (F09) → APRUEBA <id> → ZIP por email
```

## 7. Operación

- Logs: `/var/log/agente73/*.log` y `sudo supervisorctl tail -f agente73`.
- Estado de jobs: comando `ESTADO` por WhatsApp o `curl :8091/health`.
- Artefactos de cada job: `/opt/agente73/agente73/var/jobs/<id>/`.
- Actualizar código: `cd /opt/agente73/stackposts-analysis && sudo -u agente73 git pull && sudo supervisorctl restart agente73`.

## 8. Rollback

```bash
sudo supervisorctl stop agente73
sudo rm /etc/supervisor/conf.d/agente73.conf && sudo supervisorctl reread && sudo supervisorctl update
# El resto del VPS (wd-agent, bridge, workers) no se ha tocado.
```

## Garantías de diseño

- **QA stop F09 sin bypass**: ninguna secuencia de comandos WhatsApp llega a
  producción; la entrega es un ZIP (+ rama/PR opcional). Activar un tema
  siempre es un acto humano en el admin tras el checklist de staging.
- **Gramática cerrada**: cualquier texto fuera de los 7 comandos se responde
  con la ayuda; el contenido libre del usuario solo viaja como *datos*
  (descripción del encargo), nunca como instrucciones de sistema.
- **Sin secretos en el repo**: todo secreto vive en `/opt/agente73/agente73/.env`
  (600, usuario del servicio).
