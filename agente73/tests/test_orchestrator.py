import pytest

from agente73 import storage
from agente73.orchestrator import Orchestrator
from agente73.runners.base import PhaseResult, Runner


class OkRunner(Runner):
    def __init__(self):
        self.calls = []

    def run(self, job, phase):
        self.calls.append(phase["id"])
        return PhaseResult.ok(f"fase {phase['id']} ok")


class FailOnceRunner(OkRunner):
    """Falla la primera vez que se ejecuta la fase 8, luego ok."""

    def __init__(self):
        super().__init__()
        self.failed = False

    def run(self, job, phase):
        self.calls.append(phase["id"])
        if phase["id"] == 8 and not self.failed:
            self.failed = True
            return PhaseResult.fail("build roto")
        return PhaseResult.ok()


@pytest.fixture()
def store(tmp_path):
    return storage.SqliteStore(str(tmp_path / "t.db"))


def make_orch(store, runner=None, notifier=None):
    r = runner or OkRunner()
    runners = {"claude": r, "shell": r, "notify": r, "internal": r}
    return Orchestrator(store, runners, notifier), r


def test_happy_path_para_en_qa_y_aprueba_hasta_el_final(store):
    msgs = []
    orch, runner = make_orch(store, notifier=lambda j, t: msgs.append(t))
    job = storage.new_job("tema", "+34600000000", {"theme_id": "demo"})

    job = orch.start(job)
    assert job["state"] == "waiting_qa"
    assert job["phase"] == 9
    assert runner.calls[:9] == [1, 2, 3, 4, 5, 6, 7, 8, 9]
    assert any("APRUEBA" in m for m in msgs)

    job = orch.approve(job)
    assert job["state"] == "done"
    assert runner.calls == [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13]

    # persistido
    assert store.get(job["id"])["state"] == "done"
    assert store.list_active() == []


def test_gate_sin_bypass(store):
    orch, _ = make_orch(store)
    job = storage.new_job("tema", "+34", {})
    job = orch.start(job)
    assert job["state"] == "waiting_qa"
    # approve es la ÚNICA transición: advance no avanza un job en waiting_qa
    job2 = orch.advance(job)
    assert job2["state"] == "waiting_qa" and job2["phase"] == 9
    # approve sobre un job que no está en QA es un no-op
    done = orch.approve(orch.approve(job))
    assert done["state"] == "done"
    assert orch.approve(done)["state"] == "done"


def test_retry_de_f8_vuelve_a_f7(store):
    orch, runner = make_orch(store, FailOnceRunner())
    job = storage.new_job("tema", "+34", {})
    job = orch.start(job)
    # F8 falla una vez -> vuelve a F7 -> F8 ok -> F9 gate
    assert job["state"] == "waiting_qa"
    assert runner.calls.count(7) == 2 and runner.calls.count(8) == 2
    assert job["retries"]["8"] == 1


class AlwaysFail8(OkRunner):
    def run(self, job, phase):
        self.calls.append(phase["id"])
        if phase["id"] == 8:
            return PhaseResult.fail("irrecuperable")
        return PhaseResult.ok()


def test_agota_reintentos_y_falla(store):
    orch, runner = make_orch(store, AlwaysFail8())
    job = orch.start(storage.new_job("tema", "+34", {}))
    assert job["state"] == "failed"
    assert runner.calls.count(8) == 3  # 1 intento + 2 reintentos


def test_rechaza_regenera_y_segundo_rechazo_cierra(store):
    orch, runner = make_orch(store)
    job = orch.start(storage.new_job("tema", "+34", {}))
    assert job["state"] == "waiting_qa"

    job = orch.reject(job, "el hero no me gusta")
    assert job["state"] == "waiting_qa"       # regeneró 7-8 y volvió al gate
    assert job["feedback"] == "el hero no me gusta"
    assert runner.calls.count(7) == 2

    job = orch.reject(job, "sigue sin gustarme")
    assert job["state"] == "rejected"


def test_cancela(store):
    orch, _ = make_orch(store)
    job = orch.start(storage.new_job("tema", "+34", {}))
    job = orch.cancel(job)
    assert job["state"] == "cancelled"
    assert orch.approve(job)["state"] == "cancelled"


def test_vertical_web_omite_f8(store):
    orch, runner = make_orch(store)
    job = orch.start(storage.new_job("web", "+34", {"domain": "x.com"}))
    assert job["state"] == "waiting_qa"
    assert 8 not in runner.calls


def test_idempotencia_mensajes(store):
    assert store.first_seen("msg-1") is True
    assert store.first_seen("msg-1") is False
    assert store.first_seen("msg-2") is True


def test_status_text(store):
    orch, _ = make_orch(store)
    job = orch.start(storage.new_job("tema", "+34", {}))
    text = orch.status_text(job)
    assert job["id"] in text and "QA" in text
