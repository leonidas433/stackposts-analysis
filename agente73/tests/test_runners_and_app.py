import hashlib
import hmac
import json
import zipfile

import pytest

from agente73 import config, storage
from agente73.runners.claude_runner import ClaudeRunner
from agente73.runners.shell_runner import ShellRunner


@pytest.fixture(autouse=True)
def jobs_dir(tmp_path, monkeypatch):
    monkeypatch.setattr(config, "JOBS_DIR", tmp_path / "jobs")
    return tmp_path


# --- ClaudeRunner: construcción de prompt ------------------------------------

def test_prompt_incluye_spec_contrato_y_contexto(tmp_path):
    job = storage.new_job(
        "tema", "+34", {"theme_id": "demo", "color": "#123456", "description": "landing x"}
    )
    (storage.job_dir(job) / "brief.md").write_text("BRIEF-CONTENIDO", encoding="utf-8")
    runner = ClaudeRunner(repo_dir=str(tmp_path))

    phase7 = {
        "id": 7, "name": "Generación", "runner": "claude",
        "subagents": ["integrador-blade"], "contract": "tema-stackpost.md",
        "output": "generacion.md",
    }
    prompt = runner.build_prompt(job, phase7)
    assert "demo" in prompt and "#123456" in prompt
    assert "BRIEF-CONTENIDO" in prompt
    assert "CONTRATO OBLIGATORIO" in prompt
    assert "stackpost-theme-builder" in prompt          # instrucción F7
    assert "NO ejecutes git" in prompt


def test_prompt_web_usa_contrato_web(tmp_path):
    job = storage.new_job("web", "+34", {"domain": "x.com", "description": "d"})
    runner = ClaudeRunner(repo_dir=str(tmp_path))
    phase7 = {"id": 7, "name": "Generación", "runner": "claude",
              "contract": "tema-stackpost.md", "subagents": []}
    prompt = runner.build_prompt(job, phase7)
    assert "web" in prompt.lower()


def test_feedback_de_rechazo_entra_en_prompt(tmp_path):
    job = storage.new_job("tema", "+34", {"theme_id": "demo"})
    job["feedback"] = "el hero debe ser oscuro"
    runner = ClaudeRunner(repo_dir=str(tmp_path))
    prompt = runner.build_prompt(job, {"id": 7, "name": "G", "runner": "claude", "subagents": []})
    assert "el hero debe ser oscuro" in prompt


# --- ShellRunner: empaquetado (F10) -------------------------------------------

def make_fake_theme(repo: "pytest.TempPathFactory", theme_id="demo"):
    tdir = repo / "resources/themes/guest" / theme_id
    (tdir / "assets/css").mkdir(parents=True)
    (tdir / "node_modules/x").mkdir(parents=True)
    (tdir / "composer.json").write_text("{}")
    (tdir / "theme.json").write_text("{}")
    (tdir / "assets/css/app.css").write_text("body{}")
    (tdir / "assets/css/app.css.map").write_text("map")     # excluido
    (tdir / "node_modules/x/paquete.js").write_text("x")    # excluido
    (tdir / ".env").write_text("SECRET=1")                  # excluido
    return tdir


def test_package_zip_respeta_contrato(tmp_path):
    make_fake_theme(tmp_path)
    job = storage.new_job("tema", "+34", {"theme_id": "demo"})
    runner = ShellRunner(repo_dir=str(tmp_path))

    result = runner.package(job, {"id": 10, "output": "paquete.zip"})
    assert result.status == "ok"

    zpath = storage.job_dir(job) / "demo.zip"
    with zipfile.ZipFile(zpath) as zf:
        names = zf.namelist()
    # raíz única = id del tema; sin node_modules, .map ni .env
    assert all(n.startswith("demo/") for n in names)
    assert not any("node_modules" in n or n.endswith(".map") or ".env" in n for n in names)
    assert "demo/composer.json" in names and "demo/theme.json" in names


def test_package_falla_si_no_hay_tema(tmp_path):
    job = storage.new_job("tema", "+34", {"theme_id": "noexiste"})
    runner = ShellRunner(repo_dir=str(tmp_path))
    assert runner.package(job, {"id": 10}).status == "fail"


# --- Webhook: HMAC, whitelist, idempotencia ------------------------------------

@pytest.fixture()
def client(monkeypatch, tmp_path):
    monkeypatch.setattr(config, "HMAC_SECRET", "s3cr3t")
    monkeypatch.setattr(config, "WHITELIST", ("+34600000000",))
    monkeypatch.setattr(config, "SQLITE_PATH", str(tmp_path / "app.db"))
    import importlib
    from agente73 import app as appmod
    importlib.reload(appmod)
    monkeypatch.setattr(appmod.notify, "send_wa", lambda to, text: True)
    appmod.app.config["TESTING"] = True
    return appmod.app.test_client(), appmod


def _post(client, payload: dict, secret="s3cr3t"):
    raw = json.dumps(payload).encode()
    sig = hmac.new(secret.encode(), raw, hashlib.sha256).hexdigest()
    return client.post(
        "/webhook", data=raw,
        headers={"Content-Type": "application/json", "X-Signature-256": sig},
    )


def test_webhook_rechaza_sin_hmac_valido(client):
    c, _ = client
    resp = c.post("/webhook", json={"from": "+34600000000", "message": "AYUDA"})
    assert resp.status_code == 401
    resp = _post(c, {"from": "+34600000000", "message": "AYUDA"}, secret="otro")
    assert resp.status_code == 401


def test_webhook_ignora_no_whitelisteados(client):
    c, _ = client
    resp = _post(c, {"from": "+34999999999", "message": "AYUDA", "id": "m1"})
    assert resp.get_json()["status"] == "ignored"


def test_webhook_ayuda_y_duplicados(client):
    c, _ = client
    resp = _post(c, {"from": "+34600000000", "message": "AYUDA", "id": "m2"})
    assert resp.get_json()["status"] == "ok"
    resp = _post(c, {"from": "+34600000000", "message": "AYUDA", "id": "m2"})
    assert resp.get_json()["status"] == "duplicate"


def test_webhook_gramatica_cerrada(client):
    c, appmod = client
    sent = []
    appmod.notify.send_wa = lambda to, text: sent.append(text) or True
    _post(c, {"from": "+34600000000", "message": "hazme una web", "id": "m3"})
    assert any("No entiendo" in t for t in sent)


def test_health(client):
    c, _ = client
    data = c.get("/health").get_json()
    assert data["service"] == "agente73" and data["version"].startswith("4.0")
