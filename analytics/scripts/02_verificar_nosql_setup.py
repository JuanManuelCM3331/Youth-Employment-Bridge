#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Youth Employment Bridge (YEB) — Módulo de Persistencia NoSQL
Issue #02 [NoSQL/Setup]: Verificación automatizada del catálogo de accesos,
configuración de `yeb_analytics_lab` y ejecución de `01_explorar_yeb.js` en mongosh.

Cubre:
  - CA-ISS-02-01: Validación de las 6 consultas esenciales en `docs/patrones_acceso_yeb.md`
  - CA-ISS-02-02: Verificación de configuración de `yeb_analytics_lab` en Docker/.env
  - CA-ISS-02-03: Ejecución de `01_explorar_yeb.js` en `mongosh` verificando tipos BSON
  - Casos de prueba: CP-042, CP-042B, CP-042C
"""

from __future__ import annotations

import shutil
import socket
import subprocess
import sys
from pathlib import Path


def resolve_repo_root() -> Path:
    script_path = Path(__file__).resolve()
    if script_path.parent.name == "scripts" and script_path.parent.parent.name == "analytics":
        return script_path.parent.parent.parent
    return Path.cwd()


def verify_access_patterns_doc(repo_root: Path) -> None:
    """Verifica CA-ISS-02-01 / CP-042C: Existencia y completitud de las 6 consultas en lenguaje natural."""
    print("=" * 78)
    print("1. VERIFICACIÓN DE CATÁLOGO DE ACCESOS ANALÍTICOS (CA-ISS-02-01 / CP-042C)")
    print("=" * 78)

    candidate_paths = [
        repo_root / "analytics" / "docs - analisis" / "patrones_acceso_yeb.md",
        repo_root / "analytics" / "docs" / "patrones_acceso_yeb.md",
        repo_root / "docs" / "patrones_acceso_yeb.md",
    ]
    doc_paths = [p for p in candidate_paths if p.exists() and p.is_file()]
    assert len(doc_paths) >= 1, (
        "[ERROR] No se encontró patrones_acceso_yeb.md en analytics/docs - analisis/ ni en analytics/docs/"
    )

    for doc_path in doc_paths:
        assert doc_path.exists() and doc_path.is_file(), (
            f"[ERROR] No existe el archivo requerido: {doc_path.relative_to(repo_root)}"
        )
        content = doc_path.read_text(encoding="utf-8")
        for idx in range(1, 7):
            marker = f"Consulta Esencial #0{idx}"
            assert marker in content, (
                f"[ERROR] Falta '{marker}' en {doc_path.relative_to(repo_root)}"
            )
        print(f"  [OK] Catálogo verificado (6 consultas esenciales): {doc_path.relative_to(repo_root).as_posix()}")


def verify_mongodb_config(repo_root: Path) -> None:
    """Verifica CA-ISS-02-02 / CP-042 / SEC-012: Configuración de yeb_analytics_lab en Docker y .env.example."""
    print("\n" + "=" * 78)
    print("2. VERIFICACIÓN DE CONFIGURACIÓN MONGODB `yeb_analytics_lab` (CA-ISS-02-02)")
    print("=" * 78)

    compose_path = repo_root / "docker-compose.yml"
    env_example_path = repo_root / ".env.example"

    assert compose_path.exists(), "[ERROR] No se encontró docker-compose.yml"
    compose_text = compose_path.read_text(encoding="utf-8")
    assert "yeb_analytics_lab" in compose_text, (
        "[ERROR] docker-compose.yml no define la base yeb_analytics_lab"
    )
    assert "127.0.0.1:${MONGO_PORT:-27017}:27017" in compose_text, (
        "[ERROR] SEC-012: El puerto de MongoDB debe estar restringido a loopback 127.0.0.1"
    )
    print("  [OK] Servicio `mongodb` (`yeb_mongo`) configurado en docker-compose.yml")
    print("  [OK] Mitigación SEC-012 activa: binding restringido a 127.0.0.1:27017")

    assert env_example_path.exists(), "[ERROR] No se encontró .env.example"
    env_text = env_example_path.read_text(encoding="utf-8")
    assert "YEB_MONGO_DATABASE=yeb_analytics_lab" in env_text, (
        "[ERROR] .env.example no contiene YEB_MONGO_DATABASE=yeb_analytics_lab"
    )
    print("  [OK] Variables de entorno `YEB_MONGO_*` documentadas en .env.example")


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


def verify_explore_script(repo_root: Path) -> None:
    """Verifica CA-ISS-02-03 / CP-042B: Ejecución de 01_explorar_yeb.js validando tipos BSON."""
    print("\n" + "=" * 78)
    print("3. EJECUCIÓN DE `01_explorar_yeb.js` Y TIPADO BSON (CA-ISS-02-03 / CP-042B)")
    print("=" * 78)

    js_script = repo_root / "analytics" / "scripts" / "01_explorar_yeb.js"
    assert js_script.exists(), f"[ERROR] No existe el script {js_script}"

    mongosh_bin = find_mongosh_binary()
    assert mongosh_bin is not None, "[ERROR] No se encontró el binario `mongosh` en el sistema."

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
        raise RuntimeError(f"Fallo al ejecutar 01_explorar_yeb.js (código {proc.returncode})")

    output = proc.stdout
    for required_token in [
        "$oid",
        "$date",
        "$numberInt",
        "$numberDecimal",
        "perfil_profesional",
        "evento_navegacion",
        "RESULTADO FINAL: EXPLORACIÓN Y TIPADO BSON EN yeb_analytics_lab EXITOSOS",
    ]:
        assert required_token in output, (
            f"[ERROR] Salida de mongosh no contiene la evidencia esperada: {required_token}"
        )

    print(output.strip())


def main() -> int:
    repo_root = resolve_repo_root()
    verify_access_patterns_doc(repo_root)
    verify_mongodb_config(repo_root)
    verify_explore_script(repo_root)

    print("\n" + "=" * 78)
    print("RESULTADO GLOBAL ISSUE #02: TODOS LOS CRITERIOS Y PRUEBAS (CP-042, CP-042B, CP-042C) PASAN")
    print("=" * 78)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
