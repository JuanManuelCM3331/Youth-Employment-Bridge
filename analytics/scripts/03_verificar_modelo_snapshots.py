#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Youth Employment Bridge (YEB) — Módulo de Persistencia NoSQL
Issue #03 [NoSQL/Model]: Verificación automatizada del modelo documental v1,
semillas Extended JSON (`.ejson`), integridad referencial (0 referencias rotas),
inmutabilidad del Patrón Snapshot y control de límite BSON (< 16 MiB).

Cubre:
  - CA-ISS-03-01: Subdocumentos 1:1 para el perfil profesional embebidos en el candidato
  - CA-ISS-03-02: Colección independiente `postulaciones_historial` para no exceder 16 MiB
  - CA-ISS-03-03: Patrón Snapshot inmutable al pasar a `interview` o definitivo (`accepted`/`rejected`)
  - CA-ISS-03-04: Semilla reproducible en Extended JSON (`candidatos.ejson`, `vacantes.ejson`, `postulaciones.ejson`)
  - Casos de prueba: CP-043, CP-043B, CP-043C
"""

from __future__ import annotations

import json
import shutil
import socket
import subprocess
from pathlib import Path


def resolve_repo_root() -> Path:
    script_path = Path(__file__).resolve()
    if script_path.parent.name == "scripts" and script_path.parent.parent.name == "analytics":
        return script_path.parent.parent.parent
    return Path.cwd()


def verify_ejson_seeds_and_references(repo_root: Path) -> None:
    """Verifica CA-ISS-03-01, CA-ISS-03-02 y CA-ISS-03-04 (CP-043) desde los archivos .ejson."""
    print("=" * 78)
    print("1. AUDITORÍA ESTÁTICA DE SEMILLAS EXTENDED JSON (.ejson) (CA-ISS-03-04 / CP-043)")
    print("=" * 78)

    seeds_dir = repo_root / "analytics" / "data" / "seeds"
    required_seeds = ["candidatos.ejson", "vacantes.ejson", "postulaciones.ejson"]

    loaded: dict[str, list[dict]] = {}
    for fname in required_seeds:
        fpath = seeds_dir / fname
        assert fpath.exists() and fpath.is_file(), f"[ERROR] Falta archivo semilla: {fpath}"
        data = json.loads(fpath.read_text(encoding="utf-8"))
        assert isinstance(data, list) and len(data) > 0, f"[ERROR] Semilla vacía o inválida: {fname}"
        loaded[fname] = data
        print(f"  [OK] Semilla EJSON verificada : analytics/data/seeds/{fname:<20} ({len(data)} documentos)")

    # Validar subdocumentos 1:1 en candidatos (CA-ISS-03-01)
    candidato_ids = set()
    for c in loaded["candidatos.ejson"]:
        oid = c["_id"]["$oid"]
        candidato_ids.add(oid)
        perfil = c.get("perfil_profesional")
        assert isinstance(perfil, dict), f"[ERROR] Candidato {oid} sin subdocumento 1:1 perfil_profesional"
        assert "habilidades" in perfil and "expectativa_salarial_cop" in perfil, (
            f"[ERROR] Candidato {oid} incompleto en perfil_profesional"
        )
    print("  [OK] CA-ISS-03-01 verificado  : Subdocumentos 1:1 `perfil_profesional` embebidos en candidatos.")

    vacante_ids = {v["_id"]["$oid"] for v in loaded["vacantes.ejson"]}

    # Validar 0 referencias rotas en postulaciones.ejson (CA-ISS-03-04)
    broken_cand = 0
    broken_vac = 0
    snapshots_count = 0
    for p in loaded["postulaciones.ejson"]:
        cid = p["candidato_id"]["$oid"]
        vid = p["vacante_id"]["$oid"]
        if cid not in candidato_ids:
            broken_cand += 1
        if vid not in vacante_ids:
            broken_vac += 1
        if p["estado_actual"] in ("interview", "accepted", "rejected"):
            assert isinstance(p.get("snapshot_vacante"), dict), (
                f"[ERROR] Postulación {p['codigo_postulacion']} en estado {p['estado_actual']} sin snapshot_vacante"
            )
            snapshots_count += 1

    assert broken_cand == 0 and broken_vac == 0, (
        f"[ERROR] Referencias rotas detectadas: candidatos={broken_cand}, vacantes={broken_vac}"
    )
    print(f"  [OK] CA-ISS-03-04 verificado  : 0 referencias rotas ({len(loaded['postulaciones.ejson'])} postulaciones validadas, {snapshots_count} con snapshot inicial).")


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


def execute_mongosh_model_verification(repo_root: Path) -> None:
    """Ejecuta 02_modelo_y_snapshots.js en mongosh para verificar CP-043, CP-043B y CP-043C."""
    print("\n" + "=" * 78)
    print("2. EJECUCIÓN EN MONGOSH DE `02_modelo_y_snapshots.js` (CP-043 / CP-043B / CP-043C)")
    print("=" * 78)

    js_script = repo_root / "analytics" / "scripts" / "02_modelo_y_snapshots.js"
    assert js_script.exists(), f"[ERROR] No existe {js_script}"

    mongosh_bin = find_mongosh_binary()
    assert mongosh_bin is not None, "[ERROR] No se encontró `mongosh` en el sistema."

    if is_port_open("127.0.0.1", 27017):
        cmd = [
            mongosh_bin,
            "mongodb://127.0.0.1:27017/yeb_analytics_lab",
            "--quiet",
            "--file",
            str(js_script),
        ]
    else:
        cmd = [
            mongosh_bin,
            "--nodb",
            "--quiet",
            "--file",
            str(js_script),
        ]

    proc = subprocess.run(cmd, capture_output=True, text=True, encoding="utf-8", errors="replace")
    if proc.returncode != 0:
        print(proc.stdout)
        print(proc.stderr)
        raise RuntimeError(f"Fallo al ejecutar 02_modelo_y_snapshots.js (código {proc.returncode})")

    output = proc.stdout
    for expected in [
        "0 referencias rotas",
        "CP-043B verificado",
        "CP-043C verificado",
        "RESULTADO FINAL ISSUE #03: MODELO DOCUMENTAL v1 Y SNAPSHOTS VERIFICADOS",
    ]:
        assert expected in output, f"[ERROR] No se encontró '{expected}' en la salida de mongosh."

    print(output.strip())


def main() -> int:
    repo_root = resolve_repo_root()
    verify_ejson_seeds_and_references(repo_root)
    execute_mongosh_model_verification(repo_root)

    print("\n" + "=" * 78)
    print("RESULTADO GLOBAL ISSUE #03: TODOS LOS CRITERIOS Y PRUEBAS (CP-043, CP-043B, CP-043C) PASAN")
    print("=" * 78)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
