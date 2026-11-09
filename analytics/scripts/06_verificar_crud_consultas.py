#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Youth Employment Bridge (YEB) — Módulo de Persistencia NoSQL
Issue #06 [NoSQL/CRUD]: Verificación automatizada del CRUD aislado sobre `TMP-*`
y del catálogo de consultas C01–C10 para reclutamiento.

Cubre:
  - CA-ISS-06-01: Operaciones atómicas `$set` / `$inc` limitadas a documentos `TMP-*`
  - CA-ISS-06-02: Catálogo C01–C10 con contraste esperado vs. obtenido
  - Casos de prueba: CP-046, CP-046B
  - Riesgos: SEC-013, OPE-006

A diferencia de los verificadores de los Issues #02 y #03, este script necesita un
servidor MongoDB real: $elemMatch, $type, explain() y la huella de datos productivos
no se pueden demostrar con el motor en memoria de mongosh.
"""

from __future__ import annotations

import csv
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


def find_mongosh_binary() -> str | None:
    found = shutil.which("mongosh")
    if found:
        return found
    candidate = Path.home() / "AppData" / "Local" / "Programs" / "mongosh" / "mongosh.exe"
    if candidate.exists():
        return str(candidate)
    return None


def run_crud_script(repo_root: Path) -> str:
    print("=" * 78)
    print("1. EJECUCIÓN EN MONGOSH DE `03_crud_consultas_reclutamiento.js` (CP-046 / CP-046B)")
    print("=" * 78)

    js_script = repo_root / "analytics" / "scripts" / "03_crud_consultas_reclutamiento.js"
    assert js_script.exists(), f"[ERROR] No existe {js_script}"

    mongosh_bin = find_mongosh_binary()
    assert mongosh_bin is not None, "[ERROR] No se encontró `mongosh` en el sistema."
    assert is_port_open("127.0.0.1", 27017), (
        "[ERROR] MongoDB no responde en 127.0.0.1:27017. Inicie el servicio con `docker compose up -d mongodb`."
    )

    proc = subprocess.run(
        [mongosh_bin, MONGO_URI, "--quiet", "--file", str(js_script)],
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="replace",
        cwd=repo_root,
    )
    if proc.returncode != 0:
        print(proc.stdout)
        print(proc.stderr)
        raise RuntimeError(f"Fallo al ejecutar 03_crud_consultas_reclutamiento.js (código {proc.returncode})")

    output = proc.stdout
    for expected in [
        "Barrera SEC-013 verificada",
        "$inc verificado",
        "Guardia de estado verificada",
        "0 fallas",
        "(idéntica)",
        "RESULTADO FINAL ISSUE #06",
    ]:
        assert expected in output, f"[ERROR] No se encontró '{expected}' en la salida de mongosh."
    print(output.strip())
    return output


def verify_contrast_report(repo_root: Path) -> None:
    print("\n" + "=" * 78)
    print("2. AUDITORÍA DEL REPORTE `outputs/contraste_consultas_c01_c10.csv` (CA-ISS-06-02)")
    print("=" * 78)

    report = repo_root / "analytics" / "outputs" / "contraste_consultas_c01_c10.csv"
    assert report.exists(), f"[ERROR] No se generó {report}"

    with report.open(encoding="utf-8-sig", newline="") as fh:
        rows = list(csv.DictReader(fh))

    ids = [r["consulta_id"] for r in rows]
    assert ids == [f"C{n:02d}" for n in range(1, 11)], f"[ERROR] Catálogo incompleto o desordenado: {ids}"
    for r in rows:
        assert r["resultado"] == "PASA", f"[ERROR] {r['consulta_id']} no coincide: {r['valor_esperado']} vs {r['valor_obtenido']}"
        assert r["valor_esperado"] == r["valor_obtenido"] or r["consulta_id"] not in ("C09", "C10"), (
            f"[ERROR] {r['consulta_id']} debe conservar el orden exacto de la paginación"
        )

    operadores = " ".join(r["operadores"] for r in rows)
    for op in ("$in", "$gte", "$lte", "$elemMatch", "$type", "$exists", "skip", "limit"):
        assert op in operadores, f"[ERROR] El catálogo no ejercita el operador {op}"

    print(f"  [OK] 10 consultas registradas (C01–C10) y 10/10 con resultado PASA")
    print("  [OK] Operadores cubiertos: $in, $gte/$lte, $elemMatch, $type null, $exists, skip/limit y cursor (fecha, _id)")


def main() -> int:
    repo_root = resolve_repo_root()
    run_crud_script(repo_root)
    verify_contrast_report(repo_root)

    print("\n" + "=" * 78)
    print("RESULTADO GLOBAL ISSUE #06: TODOS LOS CRITERIOS Y PRUEBAS (CP-046, CP-046B) PASAN")
    print("=" * 78)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
