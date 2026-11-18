#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Youth Employment Bridge (YEB) — Módulo de Persistencia NoSQL
Issue #14 [NoSQL/Performance]: Verificación automatizada de índices compuestos/multikey,
análisis explain(), control de empates en paginación por token y prueba de regresión R03.

Cubre:
  - CA-ISS-14-01: Prueba de regresión reejecutando R03 sobre la colección de referencia.
  - CA-ISS-14-02: Colección sintética independiente (6.000 docs) y Consultas A (compuesto) y B (multikey sin $unwind).
  - CA-ISS-14-03: Matriz comparativa explain() (nReturned, totalDocsExamined, totalKeysExamined),
                  salida idéntica antes/después y contraste plan automático vs. hint.
  - CA-ISS-14-04: Control de empates en paginación con token compuesto (fecha_agenda, _id) frente a skip()
                  y cursor simple por fecha sobre registros empatados en el límite de página.
  - CA-ISS-14-05: Recomendación técnica documentada en docs/indices_explain_paginacion_yeb.md.
  - Casos de prueba: CP-054, CP-054B, CP-054C | Riesgos: OPE-008, TEC-018
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


def run_performance_script(repo_root: Path) -> str:
    print("=" * 82)
    print("1. EJECUCIÓN EN MONGOSH DE `07_indices_y_paginacion_yeb.js` (CP-054 / CP-054B / CP-054C)")
    print("=" * 82)

    js_script = repo_root / "analytics" / "scripts" / "07_indices_y_paginacion_yeb.js"
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
        raise RuntimeError(f"Fallo al ejecutar 07_indices_y_paginacion_yeb.js (código {proc.returncode})")

    output = proc.stdout
    for expected in [
        "Regresión R03 verificada sobre referencia",
        "Documentos sintéticos generados : 6000",
        "Salida proyectada y orden 100% idénticos antes y después",
        "Empate en frontera localizado : Fin Pág 1 = SYN-POST-0010",
        "Token compuesto (fecha, _id)  : 0 filas perdidas",
        "RESULTADO FINAL ISSUE #14",
    ]:
        assert expected in output, f"[ERROR] No se encontró '{expected}' en la salida de mongosh."
    print(output.strip())
    return output


def verify_outputs_and_docs(repo_root: Path) -> None:
    print("\n" + "=" * 82)
    print("2. AUDITORÍA DE MATRIZ explain(), PAGINACIÓN CON EMPATES Y RECOMENDACIÓN (CA-ISS-14-03 a 05)")
    print("=" * 82)

    outputs_dir = repo_root / "analytics" / "outputs"
    explain_csv = outputs_dir / "matriz_explain_indices_semana7.csv"
    pag_csv = outputs_dir / "comparativa_paginacion_empates_semana7.csv"
    ejson_path = outputs_dir / "experimento_indices_paginacion_semana7.ejson"

    for p in (explain_csv, pag_csv, ejson_path):
        assert p.exists() and p.stat().st_size > 0, f"[ERROR] No se generó {p}"

    with explain_csv.open(encoding="utf-8-sig", newline="") as fh:
        rows_exp = list(csv.DictReader(fh))
    assert len(rows_exp) == 5, f"[ERROR] La matriz explain debe tener 5 filas, tiene {len(rows_exp)}"

    # Verificar que los hashes de salida de Consulta A (3 filas) son idénticos y los de Consulta B (2 filas) también
    hashes_a = {r["hash_salida"] for r in rows_exp if r["consulta"].startswith("Consulta A")}
    hashes_b = {r["hash_salida"] for r in rows_exp if r["consulta"].startswith("Consulta B")}
    assert len(hashes_a) == 1, "[ERROR] La salida de Consulta A cambió entre planes"
    assert len(hashes_b) == 1, "[ERROR] La salida de Consulta B cambió entre planes"

    with pag_csv.open(encoding="utf-8-sig", newline="") as fh:
        rows_pag = {r["estrategia"]: r for r in csv.DictReader(fh)}
    assert int(rows_pag["cursor_simple_solo_fecha"]["filas_perdidas_en_empate"]) == 1
    assert int(rows_pag["token_compuesto_fecha_id"]["filas_perdidas_en_empate"]) == 0
    assert int(rows_pag["token_compuesto_fecha_id"]["claves_indice_examinadas"]) < int(rows_pag["skip_limit"]["claves_indice_examinadas"])

    payload = json.loads(ejson_path.read_text(encoding="utf-8"))
    assert int(payload["metadatos"]["total_documentos_sinteticos"]["$numberInt"]) == 6000
    assert payload["metadatos"]["regresion_r03_referencia"]["estado"] == "PASA"
    assert payload["empate_frontera_paginacion"]["misma_fecha_verificada"] is True
    assert "SYN-POST-0011" in payload["empate_frontera_paginacion"]["registro_perdido_por_cursor_simple"]

    doc_paths = [
        repo_root / "analytics" / "docs" / "indices_explain_paginacion_yeb.md",
        repo_root / "analytics" / "docs - analisis" / "indices_explain_paginacion_yeb.md",
    ]
    existing_docs = [p for p in doc_paths if p.exists()]
    assert len(existing_docs) >= 1, "[ERROR] Falta documento indices_explain_paginacion_yeb.md"
    for p in existing_docs:
        content = p.read_text(encoding="utf-8")
        for marker in ("idx_comp_dataset_estado_fecha_id", "idx_multikey_habilidades_estado", "SYN-POST-0010", "SYN-POST-0011", "Limitaciones del ensayo"):
            assert marker in content, f"Falta '{marker}' en {p.name}"

    print("  [OK] matriz_explain_indices_semana7.csv        : 5 planes comparados, hashes SHA-256 idénticos")
    print("  [OK] comparativa_paginacion_empates_semana7.csv: token compuesto (fecha, _id) con 0 pérdidas en empate")
    print("  [OK] experimento_indices_paginacion_semana7.ejson: 6.000 docs, regresión R03 y recomendación verificados")
    for p in existing_docs:
        print(f"  [OK] Documentación verificada                  : {p.relative_to(repo_root).as_posix()}")


def main() -> int:
    repo_root = resolve_repo_root()
    run_performance_script(repo_root)
    verify_outputs_and_docs(repo_root)
    print("\n" + "=" * 82)
    print("RESULTADO GLOBAL ISSUE #14: TODOS LOS CRITERIOS Y PRUEBAS (CP-054, CP-054B, CP-054C) PASAN")
    print("=" * 82)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
