#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Youth Employment Bridge (YEB) — Módulo de Persistencia NoSQL
Issue #12 [NoSQL/Aggregation]: Verificación automatizada de los 4 pipelines de agregación
de solo lectura (R01–R04), regresión v1.0, zona horaria America/Bogota y contraste manual.

Cubre:
  - CA-ISS-12-01: Regresión inicial sobre defecto v1.0 (DEF-NOSQL-10-01)
  - CA-ISS-12-02: Zona horaria explícita ('America/Bogota') y fecha de corte fija
  - CA-ISS-12-03: 4 pipelines de solo lectura (R01, R02, R03, R04) sin etapas de escritura
  - CA-ISS-12-04: Tabla de contraste cálculo manual vs. obtenido (control y referencia) y EJSON con metadatos
  - Casos de prueba: CP-052, CP-052B, CP-052C | Riesgos: NEG-010, TEC-017
"""

from __future__ import annotations

import csv
import json
import shutil
import socket
import subprocess
from pathlib import Path

MONGO_URI = "mongodb://127.0.0.1:27017/yeb_analytics_p05"


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


def run_aggregation_script(repo_root: Path) -> str:
    print("=" * 80)
    print("1. EJECUCIÓN EN MONGOSH DE `06_reportes_agregacion_yeb.js` (CP-052 / CP-052B / CP-052C)")
    print("=" * 80)

    js_script = repo_root / "analytics" / "scripts" / "06_reportes_agregacion_yeb.js"
    assert js_script.exists(), f"[ERROR] No existe {js_script}"

    mongosh_bin = find_mongosh_binary()
    if is_port_open("127.0.0.1", 27017):
        cmd = [mongosh_bin, MONGO_URI, "--quiet", "--file", str(js_script)]
    else:
        cmd = [mongosh_bin, "--nodb", "--quiet", "--file", str(js_script)]

    proc = subprocess.run(
        cmd,
        capture_output=True,
        text=True,
        encoding="utf-8",
        errors="replace",
        cwd=repo_root,
    )
    if proc.returncode != 0:
        print(proc.stdout)
        print(proc.stderr)
        raise RuntimeError(f"Fallo al ejecutar 06_reportes_agregacion_yeb.js (código {proc.returncode})")

    output = proc.stdout
    for expected in [
        "Defecto v1.0 reproducido",
        "Pipeline R01 corregido (America/Bogota + sin_sector)",
        "Barrera TEC-017 activa",
        "(8/8 PASA)",
        "RESULTADO FINAL ISSUE #12",
    ]:
        assert expected in output, f"[ERROR] No se encontró '{expected}' en la salida de mongosh."
    print(output.strip())
    return output


def verify_outputs_and_docs(repo_root: Path) -> None:
    print("\n" + "=" * 80)
    print("2. AUDITORÍA DE TABLA DE CONTRASTE CSV, SALIDAS EJSON Y DOCUMENTACIÓN (CA-ISS-12-04)")
    print("=" * 80)

    outputs_dir = repo_root / "analytics" / "outputs"
    csv_path = outputs_dir / "contraste_reportes_r01_r04.csv"
    ejson_path = outputs_dir / "reportes_r01_r04_salidas.ejson"

    assert csv_path.exists(), f"[ERROR] No se generó {csv_path}"
    assert ejson_path.exists(), f"[ERROR] No se generó {ejson_path}"

    with csv_path.open(encoding="utf-8-sig", newline="") as fh:
        rows = list(csv.DictReader(fh))
    assert len(rows) == 8, f"[ERROR] La tabla de contraste debe tener 8 filas (4 control + 4 referencia), tiene {len(rows)}"
    assert all(r["resultado"] == "PASA" for r in rows), "[ERROR] Alguna fila del contraste no está en PASA"
    assert all(r["esperado_manual"] == r["obtenido_motor"] for r in rows), "[ERROR] Descuadre entre esperado y obtenido"

    payload = json.loads(ejson_path.read_text(encoding="utf-8"))
    meta = payload["metadatos"]
    assert meta["base_datos"] == "yeb_analytics_p05"
    assert meta["zona_horaria"] == "America/Bogota"
    assert meta["solo_lectura_verificado"] is True
    assert meta["huella_sha256_referencia_antes"] == meta["huella_sha256_referencia_despues"]
    assert meta["regresion_v1_0"]["estado"] == "PASA"

    # Verificar invariantes de negocio en EJSON
    ctrl = payload["escenario_control"]
    ref = payload["escenario_referencia"]
    assert any(r["codigo_vacante"] == "Sin coincidencia" and r["coincidencia_lookup"] is False for r in ctrl["R02_top5_vacantes"])
    assert len(ctrl["R03_vencidas_al_corte"]) == 1 and ctrl["R03_vencidas_al_corte"][0]["codigo_postulacion"] == "CTRL-POST-04"
    assert ctrl["R04_duracion_procesos_cerrados"]["resumen_validas"]["n_valida"]["$numberInt"] == "3"
    assert len(ctrl["R04_duracion_procesos_cerrados"]["detalle_excluidas"]) == 3
    assert ref["R04_duracion_procesos_cerrados"]["resumen_validas"]["n_valida"]["$numberInt"] == "3"
    assert len(ref["R04_duracion_procesos_cerrados"]["detalle_excluidas"]) == 0

    doc_paths = [
        repo_root / "analytics" / "docs" / "reportes_agregacion_r01_r04_yeb.md",
        repo_root / "analytics" / "docs - analisis" / "reportes_agregacion_r01_r04_yeb.md",
    ]
    existing_docs = [p for p in doc_paths if p.exists()]
    assert len(existing_docs) >= 1, "[ERROR] Falta documento reportes_agregacion_r01_r04_yeb.md"

    print("  [OK] contraste_reportes_r01_r04.csv : 8 filas (4 control + 4 referencia), 8/8 PASA")
    print("  [OK] reportes_r01_r04_salidas.ejson : metadatos, tipos BSON extendidos y huella SHA-256 verificados")
    for p in existing_docs:
        print(f"  [OK] Documentación verificada       : {p.relative_to(repo_root).as_posix()}")


def main() -> int:
    repo_root = resolve_repo_root()
    run_aggregation_script(repo_root)
    verify_outputs_and_docs(repo_root)
    print("\n" + "=" * 80)
    print("RESULTADO GLOBAL ISSUE #12: TODOS LOS CRITERIOS Y PRUEBAS (CP-052, CP-052B, CP-052C) PASAN")
    print("=" * 80)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
