#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Youth Employment Bridge (YEB) — Módulo de Persistencia NoSQL
Issue #07 [NoSQL/Schema]: Verificación automatizada de contratos $jsonSchema,
índices únicos, flujo de cuarentena y migración idempotente M04_001.

Cubre:
  - CA-ISS-07-01: validationLevel 'strict' y validationAction 'error' en `vacantes`
  - CA-ISS-07-02: índice único compuesto { candidato_id, vacante_id }
  - CA-ISS-07-03: staging -> cuarentena -> carga de limpios sin pérdida de registros
  - CA-ISS-07-04: matriz de 8 pruebas (3 aceptadas, 5 rechazadas)
  - CA-ISS-07-05: M04_001 idempotente (segunda ejecución con 0 modificaciones)
  - Casos de prueba: CP-047, CP-047B, CP-047C
"""

from __future__ import annotations

import csv
import json
import shutil
import socket
import subprocess
from pathlib import Path

MONGO_URI = "mongodb://127.0.0.1:27017/yeb_analytics_lab"


def resolve_repo_root() -> Path:
    script_path = Path(__file__).resolve()
    if script_path.parent.name == "scripts" and script_path.parent.parent.name == "analytics":
        return script_path.parent.parent.parent
    return Path.cwd()


def is_port_open(host: str, port: int, timeout: float = 1.0) -> bool:
    with socket.socket(socket.AF_INET, socket.SOCK_STREAM) as sock:
        sock.settimeout(timeout)
        return sock.connect_ex((host, port)) == 0


def find_mongosh_binary() -> str:
    found = shutil.which("mongosh")
    if not found:
        candidate = Path.home() / "AppData" / "Local" / "Programs" / "mongosh" / "mongosh.exe"
        found = str(candidate) if candidate.exists() else None
    assert found, "[ERROR] No se encontró `mongosh` en el sistema."
    return found


def mongosh(args: list[str], cwd: Path) -> str:
    proc = subprocess.run(
        [find_mongosh_binary(), MONGO_URI, "--quiet", *args],
        capture_output=True, text=True, encoding="utf-8", errors="replace", cwd=cwd,
    )
    if proc.returncode != 0:
        print(proc.stdout)
        print(proc.stderr)
        raise RuntimeError(f"mongosh terminó con código {proc.returncode}")
    return proc.stdout


def read_csv(path: Path) -> list[dict[str, str]]:
    assert path.exists(), f"[ERROR] No se generó {path}"
    with path.open(encoding="utf-8-sig", newline="") as fh:
        return list(csv.DictReader(fh))


def run_contract_script(repo_root: Path) -> None:
    print("=" * 78)
    print("1. EJECUCIÓN DE `04_contratos_schema_migracion.js` (CP-047 / CP-047B / CP-047C)")
    print("=" * 78)
    assert is_port_open("127.0.0.1", 27017), (
        "[ERROR] MongoDB no responde en 127.0.0.1:27017. Inicie el servicio con `docker compose up -d mongodb`."
    )
    output = mongosh(["--file", str(repo_root / "analytics" / "scripts" / "04_contratos_schema_migracion.js")], repo_root)
    for expected in [
        "CP-047B verificado",
        "CP-047 verificado",
        "0 perdidos",
        "Ejecución 2 modificó 0 documentos (idempotente)",
        "RESULTADO FINAL ISSUE #07",
    ]:
        assert expected in output, f"[ERROR] No se encontró '{expected}' en la salida de mongosh."
    print(output.strip())


def verify_engine_state(repo_root: Path) -> None:
    print("\n" + "=" * 78)
    print("2. CONSULTA INDEPENDIENTE DEL ESTADO DEL MOTOR (CA-ISS-07-01 / CA-ISS-07-02 / CA-ISS-07-05)")
    print("=" * 78)
    probe = """
    const info = db.getCollectionInfos({ name: 'vacantes' })[0].options;
    const idx = db.postulaciones_historial.getIndexes().find(i => i.name === 'uq_postulacion_candidato_vacante');
    const copia = db.vacantes_v2_m04;
    print(JSON.stringify({
      level: info.validationLevel,
      action: info.validationAction,
      tiene_expr: JSON.stringify(info.validator).includes('$expr'),
      indice_unico: idx ? { key: Object.keys(idx.key), unique: idx.unique === true } : null,
      copia_total: copia.countDocuments({}),
      copia_v2: copia.countDocuments({ schema_version: 2, canal_publicacion: 'portal_web' }),
      origen_v1: db.vacantes.countDocuments({ schema_version: 1 }),
      ejecuciones_m04: (db.migraciones_esquema.findOne({ _id: 'M04_001' }) || {}).ejecuciones
    }));
    """
    state = json.loads(mongosh(["--eval", probe], repo_root).strip().splitlines()[-1])
    assert state["level"] == "strict" and state["action"] == "error", f"[ERROR] Contrato mal configurado: {state}"
    assert state["tiene_expr"], "[ERROR] El contrato no incluye la regla $expr de salarios"
    assert state["indice_unico"] == {"key": ["candidato_id", "vacante_id"], "unique": True}, state["indice_unico"]
    assert state["copia_total"] > 0 and state["copia_total"] == state["copia_v2"], "[ERROR] La copia no quedó 100% en v2"
    assert state["origen_v1"] > 0, "[ERROR] La migración alteró la colección original"
    assert state["ejecuciones_m04"] == 2, "[ERROR] La bitácora no registra las dos ejecuciones de M04_001"
    print(f"  [OK] vacantes -> validationLevel='{state['level']}', validationAction='{state['action']}', incluye $expr")
    print(f"  [OK] Índice único compuesto {state['indice_unico']['key']} activo")
    print(f"  [OK] vacantes_v2_m04 -> {state['copia_v2']}/{state['copia_total']} en schema_version 2; origen intacto en v1 ({state['origen_v1']})")


def verify_evidence(repo_root: Path) -> None:
    print("\n" + "=" * 78)
    print("3. AUDITORÍA DE EVIDENCIAS CSV (CA-ISS-07-03 / CA-ISS-07-04)")
    print("=" * 78)
    outputs = repo_root / "analytics" / "outputs"
    matriz = read_csv(outputs / "matriz_validacion_jsonschema.csv")
    assert len(matriz) == 8, f"[ERROR] La matriz debe tener 8 casos y tiene {len(matriz)}"
    assert sum(r["resultado_obtenido"] == "ACEPTADO" for r in matriz) == 3
    assert sum(r["resultado_obtenido"] == "RECHAZADO" for r in matriz) == 5
    assert all(r["veredicto"] == "PASA" for r in matriz)
    assert all(r["codigo_error"] == "121" for r in matriz if r["resultado_obtenido"] == "RECHAZADO")
    print("  [OK] matriz_validacion_jsonschema.csv: 8 casos, 3 aceptados, 5 rechazados con código 121")

    staging = json.loads((repo_root / "analytics" / "data" / "staging" / "vacantes_staging.ejson").read_text(encoding="utf-8"))
    cuarentena = read_csv(outputs / "cuarentena_vacantes_staging.csv")
    assert 0 < len(cuarentena) < len(staging), "[ERROR] La cuarentena debe aislar parte del lote, no todo ni nada"
    assert {r["etapa"] for r in cuarentena} == {"precarga", "motor"}, "[ERROR] Deben existir rechazos de precarga y del motor"
    print(f"  [OK] cuarentena_vacantes_staging.csv: {len(cuarentena)} de {len(staging)} registros aislados con motivo documentado")


def main() -> int:
    repo_root = resolve_repo_root()
    run_contract_script(repo_root)
    verify_engine_state(repo_root)
    verify_evidence(repo_root)
    print("\n" + "=" * 78)
    print("RESULTADO GLOBAL ISSUE #07: TODOS LOS CRITERIOS Y PRUEBAS (CP-047, CP-047B, CP-047C) PASAN")
    print("=" * 78)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
