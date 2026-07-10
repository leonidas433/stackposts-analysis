from agente73.grammar import Command, GrammarError, parse


def test_tema_valido():
    cmd = parse("TEMA blackfriday #b91c1c landing oscura de rebajas")
    assert isinstance(cmd, Command) and cmd.kind == "TEMA"
    assert cmd.args["theme_id"] == "blackfriday"
    assert cmd.args["color"] == "#b91c1c"
    assert "rebajas" in cmd.args["description"]


def test_tema_case_insensitive_y_espacios():
    cmd = parse("  tema   verano24 #FFAA00 tema claro de verano ")
    assert isinstance(cmd, Command)
    assert cmd.args["theme_id"] == "verano24"
    assert cmd.args["color"] == "#ffaa00"


def test_tema_id_invalido():
    assert isinstance(parse("TEMA black.friday #fff desc larga"), GrammarError)
    assert isinstance(parse("TEMA años24 #fff desc larga"), GrammarError)
    assert isinstance(parse("TEMA _shared #fff descripcion aqui"), GrammarError)
    assert isinstance(parse("TEMA a #fff descripcion"), GrammarError)  # muy corto


def test_tema_id_se_normaliza_a_minusculas():
    cmd = parse("TEMA Black-Friday #fff desc larga")
    assert isinstance(cmd, Command) and cmd.args["theme_id"] == "black-friday"


def test_tema_color_invalido():
    assert isinstance(parse("TEMA vera #rojo hola que tal"), GrammarError)


def test_tema_incompleto():
    assert isinstance(parse("TEMA soloid"), GrammarError)


def test_web():
    cmd = parse("WEB clinicadental.com web para clínica dental moderna")
    assert isinstance(cmd, Command) and cmd.kind == "WEB"
    assert cmd.args["domain"] == "clinicadental.com"


def test_web_dominio_invalido():
    assert isinstance(parse("WEB no_es_dominio hola"), GrammarError)


def test_estado_aprueba_rechaza_cancela():
    assert parse("ESTADO").args["job_id"] is None
    assert parse("estado a1b2c3").args["job_id"] == "a1b2c3"
    assert parse("APRUEBA a1b2c3").kind == "APRUEBA"
    r = parse("RECHAZA a1b2c3 el hero no gusta")
    assert r.kind == "RECHAZA" and r.args["reason"] == "el hero no gusta"
    assert parse("CANCELA a1b2c3").kind == "CANCELA"
    assert isinstance(parse("APRUEBA"), GrammarError)
    assert isinstance(parse("APRUEBA ../../etc"), GrammarError)


def test_gramatica_cerrada():
    assert isinstance(parse("hazme una web bonita"), GrammarError)
    assert isinstance(parse(""), GrammarError)
    assert parse("AYUDA").kind == "AYUDA"
