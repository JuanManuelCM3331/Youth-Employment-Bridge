#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Youth Employment Bridge (YEB) — Módulo de Persistencia NoSQL y Concurrencia
Issue #16 [NoSQL/Concurrency]: Runner de verificación automatizada que ejecuta:
  1. Verificación de configuración desacoplada (.env.example, .gitignore, docker-compose.yml).
  2. Prueba de regresión de lecturas Semana 7 en PyMongo sobre la base congelada.
  3. Ejecución completa de pytest (`analytics/tests/test_concurrency.py`).
  4. Exportación de evidencia verificada en `analytics/outputs/evidencia_concurrencia_pymongo_semana8.json`.
  5. Auditoría de la nota técnica `docs/arquitectura_concurrencia_yeb.md`.

Casos de prueba: CP-056, CP-056B, CP-056C | Riesgos: TEC-020, OPE-009, SEC-012
"""

from __future__ import annotations

import json
import os
import subprocess
import sys
from datetime import datetime, timezone
from pathlib import Path


def _ensure_venv_interpreter() -> None:
    venv_python = Path(__file__).resolve().parents[1] / ".venv" / "Scripts" / "python.exe"
    if venv_python.exists():
        current_exe = Path(sys.executable).resolve()
        target_exe = venv_python.resolve()
        if current_exe != target_exe and not os.environ.get("YEB_VENV_REDIRECTED"):
            env = os.environ.copy()
            env["YEB_VENV_REDIRECTED"] = "1"
            result = subprocess.run([str(target_exe), *sys.argv], env=env)
            raise SystemExit(result.returncode)


_ensure_venv_interpreter()

ANALYTICS_ROOT = Path(__file__).resolve().parents[1]
REPO_ROOT = ANALYTICS_ROOT.parent
if str(ANALYTICS_ROOT) not in sys.path:
    sys.path.insert(0, str(ANALYTICS_ROOT))

from services.interview_scheduling_service import (  # noqa: E402
    InterviewSchedulingRepository,
    InterviewSchedulingService,
    MongoSettings,
    ReplicaSetRequiredError,
    build_mongo_client,
    ensure_replica_set_ready,
    verify_replica_set,
)


def verify_config_and_security() -> None:
    print("=" * 84)
    print("1. VERIFICACIÓN DE CONFIGURACIÓN DESACOPLADA Y SEGURIDAD (.env / Replica Set)")
    print("=" * 84)
    env_example = (REPO_ROOT / ".env.example").read_text(encoding="utf-8")
    gitignore = (REPO_ROOT / ".gitignore").read_text(encoding="utf-8")
    compose = (REPO_ROOT / "docker-compose.yml").read_text(encoding="utf-8")

    for token in (
        "YEB_MONGO_URI=",
        "YEB_MONGO_REPLICA_SET=rs0",
        "YEB_MONGO_SERVER_SELECTION_TIMEOUT_MS=",
        "YEB_MONGO_CONNECT_TIMEOUT_MS=",
    ):
        assert token in env_example, f"Falta '{token}' en .env.example"
    assert ".env\n" in gitignore or ".env\r\n" in gitignore, ".env debe estar excluido en .gitignore"
    assert "--replSet" in compose and "rs0" in compose, "docker-compose.yml debe configurar Replica Set rs0"
    print("  [OK] .env.example documenta URI, Replica Set y timeouts explícitos de PyMongo")
    print("  [OK] .gitignore excluye .env y docker-compose.yml habilita Replica Set rs0 en loopback")


def verify_docs() -> list[Path]:
    print("\n" + "=" * 84)
    print("4. AUDITORÍA DE NOTA TÉCNICA DE ARQUITECTURA (CA-ISS-16-08)")
    print("=" * 84)
    candidates = [
        ANALYTICS_ROOT / "docs" / "arquitectura_concurrencia_yeb.md",
        ANALYTICS_ROOT / "docs - analisis" / "arquitectura_concurrencia_yeb.md",
    ]
    existing = [p for p in candidates if p.exists() and p.is_file()]
    assert len(existing) >= 1, "Falta docs/arquitectura_concurrencia_yeb.md"

    markers = [
        "CAP",
        "Sharding",
        "Replicación",
        "particiones de red",
        "id_operacion",
        "abort_transaction",
        "Replica Set",
        "WriteConcern",
    ]
    for p in existing:
        text = p.read_text(encoding="utf-8")
        text_lower = text.lower()
        for m in markers:
            assert m.lower() in text_lower, f"Falta marcador '{m}' en {p.name}"
        print(f"  [OK] Nota técnica verificada : {p.relative_to(REPO_ROOT).as_posix()}")
    return existing


def main() -> int:
    verify_config_and_security()

    print("\n" + "=" * 84)
    print("2. CONEXIÓN REPLICA SET Y REGRESIÓN DE LECTURAS SEMANA 7 EN PYMONGO (CA-ISS-16-02)")
    print("=" * 84)
    settings = MongoSettings.from_env()
    try:
        ensure_replica_set_ready(settings)
    except ReplicaSetRequiredError as exc:
        print(f"\n  [ERROR REPLICA SET] {exc}\n")
        return 2
    client = build_mongo_client(settings)
    try:
        rs_info = verify_replica_set(client)
        repo = InterviewSchedulingRepository(client[settings.database])
        svc = InterviewSchedulingService(client, repo)
        svc.initialize(ensure_w07_dataset=True)
        regression = svc.run_week7_read_regression(analytics_root=ANALYTICS_ROOT)
    finally:
        client.close()

    print(f"  [OK] Replica Set activo      : setName={rs_info['setName']} | primary={rs_info['isWritablePrimary']}")
    print(
        f"  [OK] Regresión Consulta A    : n={regression['consulta_a']['n_returned']} | "
        f"plan={regression['consulta_a']['etapa_plan']} | docs={regression['consulta_a']['docs_examined']}"
    )
    print(
        f"  [OK] Regresión Consulta B    : n={regression['consulta_b']['n_returned']} | "
        f"plan={regression['consulta_b']['etapa_plan']} | docs={regression['consulta_b']['docs_examined']}"
    )
    pag = regression["paginacion_token_compuesto"]
    print(
        f"  [OK] Token compuesto W07     : Fin P1={pag['ultimo_pagina_1']} -> Inicio P2={pag['primero_pagina_2']} | "
        f"perdidas={pag['filas_perdidas_en_empate']}"
    )

    print("\n" + "=" * 84)
    print("3. SUITE DE PRUEBAS UNITARIAS, ACID, IDEMPOTENCIA Y CONCURRENCIA (pytest)")
    print("=" * 84)
    pytest_bin = ANALYTICS_ROOT / ".venv" / "Scripts" / "pytest.exe"
    cmd = [str(pytest_bin), str(ANALYTICS_ROOT / "tests" / "test_concurrency.py"), "-v"]
    proc = subprocess.run(cmd, capture_output=True, text=True, encoding="utf-8", errors="replace", cwd=REPO_ROOT)
    print(proc.stdout.strip())
    if proc.returncode != 0:
        print(proc.stderr.strip())
        raise RuntimeError(f"La suite pytest falló con código {proc.returncode}")

    docs = verify_docs()

    evidencia = {
        "issue": "Issue #16 [NoSQL/Concurrency]",
        "timestamp_utc": datetime.now(timezone.utc).isoformat(),
        "database": settings.database,
        "replica_set": rs_info,
        "timeouts_ms": {
            "serverSelectionTimeoutMS": settings.server_selection_timeout_ms,
            "connectTimeoutMS": settings.connect_timeout_ms,
            "socketTimeoutMS": settings.socket_timeout_ms,
        },
        "regresion_semana7_pymongo": regression,
        "pytest_suite": "analytics/tests/test_concurrency.py",
        "pytest_exit_code": proc.returncode,
        "documentacion_verificada": [p.relative_to(REPO_ROOT).as_posix() for p in docs],
        "estado_global": "PASA",
    }
    out_json = ANALYTICS_ROOT / "outputs" / "evidencia_concurrencia_pymongo_semana8.json"
    out_json.write_text(json.dumps(evidencia, indent=2, ensure_ascii=False) + "\n", encoding="utf-8")
    print(f"\n  [OK] Evidencia exportada     : {out_json.relative_to(REPO_ROOT).as_posix()}")

    print("\n" + "=" * 84)
    print("RESULTADO GLOBAL ISSUE #16: TODOS LOS CRITERIOS Y PRUEBAS (CP-056, CP-056B, CP-056C) PASAN")
    print("=" * 84)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
