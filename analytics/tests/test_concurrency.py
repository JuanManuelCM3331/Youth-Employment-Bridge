#!/usr/bin/env python3
# -*- coding: utf-8 -*-
r"""
Youth Employment Bridge (YEB) — Suite de Pruebas Unitarias, de Integración y Concurrencia
Issue #16 [NoSQL/Concurrency]: Servicio PyMongo, atomicidad condicional, transacciones ACID
en Replica Set, idempotencia por id_operacion, cancelación atómica y regresión Semana 7.

Casos de prueba cubiertos: CP-056, CP-056B, CP-056C | Riesgos: TEC-020, OPE-009, SEC-012

Ejecución:
  .\analytics\.venv\Scripts\pytest.exe analytics/tests/test_concurrency.py -v
"""

from __future__ import annotations

import json
import multiprocessing
import os
import sys
import threading
import time
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timezone
from pathlib import Path
from typing import Any

import pytest

ANALYTICS_ROOT = Path(__file__).resolve().parents[1]
if str(ANALYTICS_ROOT) not in sys.path:
    sys.path.insert(0, str(ANALYTICS_ROOT))

from services.interview_scheduling_service import (  # noqa: E402
    AgendaPaginationRequest,
    CancelInterviewRequest,
    CreateSlotRequest,
    IdempotencyConflictError,
    InterviewNotFoundError,
    InterviewSchedulingRepository,
    InterviewSchedulingService,
    InvalidStateTransitionError,
    MongoSettings,
    ReserveSlotRequest,
    SimulatedTransactionFailureError,
    SlotUnavailableError,
    ValidationError,
    build_mongo_client,
    compute_payload_hash,
    ensure_replica_set_ready,
    main as cli_main,
    verify_replica_set,
)

TEST_DB_NAME = "yeb_analytics_p05_test_w08"


def _make_test_settings() -> MongoSettings:
    return MongoSettings.from_env(database_override=TEST_DB_NAME)


def _multiprocessing_worker(args: tuple[str, str, int, int, int, str, int, float]) -> dict[str, Any]:
    """Worker de nivel superior (picklable en Windows spawn) para probar concurrencia multi-proceso real."""
    uri, db_name, srv_to, conn_to, sock_to, slot_id, worker_idx, start_epoch = args
    settings = MongoSettings(
        uri=uri,
        database=db_name,
        replica_set="rs0",
        server_selection_timeout_ms=srv_to,
        connect_timeout_ms=conn_to,
        socket_timeout_ms=sock_to,
    )
    client = build_mongo_client(settings)
    try:
        repo = InterviewSchedulingRepository(client[db_name])
        service = InterviewSchedulingService(client, repo)

        # Esperar hasta el instante compartido para maximizar la colisión entre procesos
        remaining = start_epoch - time.time()
        if remaining > 0:
            time.sleep(remaining)

        req = ReserveSlotRequest(
            slot_id=slot_id,
            application_id=f"APP-MP-{worker_idx:03d}",
            candidate_id=f"CAND-MP-{worker_idx:03d}",
            vacancy_id="JOB-2026-001",
            id_operacion=f"OP-MP-{slot_id}-{worker_idx:03d}",
        )
        try:
            res = service.reserve_slot_transactional(req)
            return {"outcome": "success", "worker": worker_idx, "result": res}
        except SlotUnavailableError as exc:
            return {"outcome": "unavailable", "worker": worker_idx, "error": str(exc)}
    finally:
        client.close()


# =============================================================================
# FIXTURES DE INTEGRACIÓN CONTRA MONGODB REPLICA SET REAL
# =============================================================================
@pytest.fixture(scope="module")
def mongo_client():
    settings = _make_test_settings()
    try:
        ensure_replica_set_ready(settings)
    except Exception as exc:
        pytest.fail(str(exc))
    client = build_mongo_client(settings)
    try:
        verify_replica_set(client)
    except Exception as exc:
        client.close()
        pytest.fail(
            f"MongoDB Replica Set (rs0) no está disponible en {settings.uri}: {exc}. "
            "Ejecute 'docker compose up -d --force-recreate mongodb' antes de correr la suite."
        )
    yield client
    # Limpieza de la base temporal de pruebas al finalizar el módulo
    client.drop_database(TEST_DB_NAME)
    client.close()


@pytest.fixture()
def service(mongo_client):
    db = mongo_client[TEST_DB_NAME]
    for col in ("franjas_entrevista", "entrevistas", "operaciones"):
        db[col].delete_many({})
    repo = InterviewSchedulingRepository(db)
    svc = InterviewSchedulingService(mongo_client, repo)
    svc.initialize(ensure_w07_dataset=False)
    return svc


def _create_slot(svc: InterviewSchedulingService, slot_id: str, vacancy_id: str = "JOB-2026-001") -> dict[str, Any]:
    return svc.create_slot(
        CreateSlotRequest(
            slot_id=slot_id,
            vacancy_id=vacancy_id,
            company_name="TalentoLab Analítica S.A.S.",
            starts_at=datetime(2026, 12, 1, 15, 0, tzinfo=timezone.utc),
            ends_at=datetime(2026, 12, 1, 15, 45, tzinfo=timezone.utc),
            modality="Híbrido",
        )
    )


# =============================================================================
# 1. PRUEBAS UNITARIAS: CONFIGURACIÓN .env Y VALIDACIÓN DE ENTRADAS TIPADAS (CA-ISS-16-01)
# =============================================================================
def test_mongo_settings_from_env_parses_explicit_timeouts(monkeypatch, tmp_path):
    env_file = tmp_path / ".env.test"
    env_file.write_text(
        "YEB_MONGO_URI=mongodb://127.0.0.1:27017/?replicaSet=rs0&directConnection=true\n"
        "YEB_MONGO_CONCURRENCY_DB=yeb_unit_db\n"
        "YEB_MONGO_SERVER_SELECTION_TIMEOUT_MS=3500\n"
        "YEB_MONGO_CONNECT_TIMEOUT_MS=2500\n"
        "YEB_MONGO_SOCKET_TIMEOUT_MS=8000\n",
        encoding="utf-8",
    )
    for k in (
        "YEB_MONGO_URI",
        "YEB_MONGO_CONCURRENCY_DB",
        "YEB_MONGO_SERVER_SELECTION_TIMEOUT_MS",
        "YEB_MONGO_CONNECT_TIMEOUT_MS",
        "YEB_MONGO_SOCKET_TIMEOUT_MS",
    ):
        monkeypatch.delenv(k, raising=False)

    settings = MongoSettings.from_env(env_path=env_file)
    assert settings.database == "yeb_unit_db"
    assert settings.server_selection_timeout_ms == 3500
    assert settings.connect_timeout_ms == 2500
    assert settings.socket_timeout_ms == 8000


def test_mongo_settings_rejects_invalid_uri_and_timeouts():
    with pytest.raises(ValidationError):
        MongoSettings(uri="http://127.0.0.1:27017", database="yeb", replica_set="rs0").validate()
    with pytest.raises(ValidationError):
        MongoSettings(
            uri="mongodb://127.0.0.1:27017",
            database="yeb",
            replica_set="rs0",
            server_selection_timeout_ms=0,
        ).validate()


def test_typed_request_validation_rejects_malformed_inputs():
    with pytest.raises(ValidationError):
        CreateSlotRequest(
            slot_id="SL-1",
            vacancy_id="JOB-01",
            company_name="YEB",
            starts_at=datetime(2026, 12, 1, 15, 0, tzinfo=timezone.utc),
            ends_at=datetime(2026, 12, 1, 14, 0, tzinfo=timezone.utc),  # ends <= starts
            modality="Remoto",
        ).validated()

    with pytest.raises(ValidationError):
        CreateSlotRequest(
            slot_id="SL-2",
            vacancy_id="JOB-01",
            company_name="YEB",
            starts_at=datetime(2026, 12, 1, 14, 0, tzinfo=timezone.utc),
            ends_at=datetime(2026, 12, 1, 15, 0, tzinfo=timezone.utc),
            modality="ModalidadInvalida",
        ).validated()

    with pytest.raises(ValidationError):
        ReserveSlotRequest(
            slot_id="SL-2",
            application_id="APP-1",
            candidate_id="CAND-1",
            vacancy_id="JOB-1",
            id_operacion="ab",  # demasiado corta (< 4)
        ).validated()

    with pytest.raises(ValidationError):
        # Token parcial: cursor_fecha sin cursor_id
        AgendaPaginationRequest(cursor_fecha=datetime(2026, 10, 12, 14, 0, tzinfo=timezone.utc)).validated()


def test_payload_hash_is_deterministic_and_detects_parameter_changes():
    p1 = {"slot_id": "S1", "application_id": "A1", "candidate_id": "C1", "vacancy_id": "V1"}
    p2 = {"vacancy_id": "V1", "candidate_id": "C1", "application_id": "A1", "slot_id": "S1"}
    p3 = {"slot_id": "S1", "application_id": "A1", "candidate_id": "C2", "vacancy_id": "V1"}
    assert compute_payload_hash("reserva", p1) == compute_payload_hash("reserva", p2)
    assert compute_payload_hash("reserva", p1) != compute_payload_hash("reserva", p3)


# =============================================================================
# 2. PRUEBA DE REGRESIÓN DE LECTURAS SEMANA 7 EN PYMONGO (CA-ISS-16-02 / CP-056)
# =============================================================================
def test_week7_read_regression_matches_frozen_counts_order_and_token_tie(service):
    reg = service.run_week7_read_regression(analytics_root=ANALYTICS_ROOT)

    assert reg["consulta_a"]["n_returned"] == 482
    assert reg["consulta_a"]["etapa_plan"] == "IXSCAN"
    assert reg["consulta_a"]["docs_examined"] == 482
    assert reg["consulta_a"]["keys_examined"] == 482

    assert reg["consulta_b"]["n_returned"] == 406
    assert reg["consulta_b"]["etapa_plan"] == "IXSCAN"
    assert reg["consulta_b"]["docs_examined"] == 406

    pag = reg["paginacion_token_compuesto"]
    assert pag["ultimo_pagina_1"] == "SYN-POST-0010"
    assert pag["primero_pagina_2"] == "SYN-POST-0011"
    assert pag["ultimo_pagina_1_fecha"] == pag["primero_pagina_2_fecha"]
    assert pag["filas_perdidas_en_empate"] == 0
    assert pag["etapa_plan_p2"] == "IXSCAN"
    assert reg["verificacion_semana7"]["coincidencia_exacta_semana7"] is True


# =============================================================================
# 3. ATOMICIDAD A NIVEL DE DOCUMENTO — FILTRO status='disponible' (CA-ISS-16-03)
# =============================================================================
def test_atomic_single_document_filter_concurrent_threads_single_winner(service):
    slot_id = "SLOT-ATOMIC-01"
    _create_slot(service, slot_id)

    n_threads = 16
    barrier = threading.Barrier(n_threads)

    def _attempt(idx: int) -> dict[str, Any]:
        req = ReserveSlotRequest(
            slot_id=slot_id,
            application_id=f"APP-ATOM-{idx:02d}",
            candidate_id=f"CAND-ATOM-{idx:02d}",
            vacancy_id="JOB-2026-001",
            id_operacion=f"OP-ATOM-{idx:02d}",
        )
        barrier.wait()
        try:
            res = service.reserve_slot_atomic(req)
            return {"status": "won", "res": res}
        except SlotUnavailableError:
            return {"status": "rejected"}

    with ThreadPoolExecutor(max_workers=n_threads) as pool:
        outcomes = list(pool.map(_attempt, range(n_threads)))

    winners = [o for o in outcomes if o["status"] == "won"]
    rejected = [o for o in outcomes if o["status"] == "rejected"]

    assert len(winners) == 1
    assert len(rejected) == n_threads - 1
    assert winners[0]["res"]["modified_count"] == 1

    slot_doc = service.repository.get_slot(slot_id)
    assert slot_doc is not None
    assert slot_doc["status"] == "reservado"
    assert slot_doc["version"] == 2
    assert slot_doc["candidate_id"] == winners[0]["res"]["candidate_id"]


# =============================================================================
# 4. TRANSACCIÓN MULTI-DOCUMENTO ACID Y ROLLBACK ANTE FALLO CONTROLADO (CA-ISS-16-04)
# =============================================================================
def test_transactional_reservation_commits_three_collections_atomically(service):
    slot_id = "SLOT-TX-OK-01"
    _create_slot(service, slot_id)

    res = service.reserve_slot_transactional(
        ReserveSlotRequest(
            slot_id=slot_id,
            application_id="APP-2026-0002",
            candidate_id="CAND-HASH-0002",
            vacancy_id="JOB-2026-001",
            id_operacion="OP-TX-OK-0001",
        )
    )
    assert res["slot_status"] == "reservado"
    assert res["interview_status"] == "confirmada"
    assert res["idempotent_replay"] is False

    slot_doc = service.repository.get_slot(slot_id)
    interview_doc = service.repository.get_interview(res["interview_id"])
    op_doc = service.repository.find_operation("OP-TX-OK-0001")

    assert slot_doc is not None and slot_doc["status"] == "reservado" and slot_doc["interview_id"] == res["interview_id"]
    assert interview_doc is not None and interview_doc["status"] == "confirmada" and interview_doc["slot_id"] == slot_id
    assert op_doc is not None and op_doc["tipo_operacion"] == "reserva" and op_doc["estado"] == "completada"


@pytest.mark.parametrize("fail_step", [1, 2])
def test_transactional_reservation_aborts_and_leaves_zero_orphans_on_fault(service, fail_step):
    slot_id = f"SLOT-TX-ABORT-{fail_step}"
    op_id = f"OP-TX-ABORT-{fail_step}"
    _create_slot(service, slot_id)

    with pytest.raises(SimulatedTransactionFailureError):
        service.reserve_slot_transactional(
            ReserveSlotRequest(
                slot_id=slot_id,
                application_id="APP-2026-0007",
                candidate_id="CAND-HASH-0007",
                vacancy_id="JOB-2026-001",
                id_operacion=op_id,
            ),
            simulate_failure_after_step=fail_step,
        )

    # Verificar rollback completo: cupo de vuelta en 'disponible' y 0 documentos huérfanos
    slot_doc = service.repository.get_slot(slot_id)
    assert slot_doc is not None
    assert slot_doc["status"] == "disponible"
    assert slot_doc["interview_id"] is None
    assert slot_doc["candidate_id"] is None
    assert service.repository.interviews.count_documents({"slot_id": slot_id}) == 0
    assert service.repository.operations.count_documents({"id_operacion": op_id}) == 0

    # Confirmar que el cupo sigue operativo para una reserva válida posterior
    ok = service.reserve_slot_transactional(
        ReserveSlotRequest(
            slot_id=slot_id,
            application_id="APP-2026-0008",
            candidate_id="CAND-HASH-0008",
            vacancy_id="JOB-2026-001",
            id_operacion=f"OP-TX-RECOVERY-{fail_step}",
        )
    )
    assert ok["slot_status"] == "reservado"


# =============================================================================
# 5. IDEMPOTENCIA (id_operacion): REINTENTOS Y CONFLICTO DE PARÁMETROS (CA-ISS-16-05)
# =============================================================================
def test_idempotency_replay_and_conflict_detection(service):
    slot_id = "SLOT-IDEMP-01"
    _create_slot(service, slot_id)

    req = ReserveSlotRequest(
        slot_id=slot_id,
        application_id="APP-2026-0017",
        candidate_id="CAND-HASH-0017",
        vacancy_id="JOB-2026-001",
        id_operacion="OP-IDEMP-0001",
    )
    first = service.reserve_slot_transactional(req)
    assert first["idempotent_replay"] is False

    # Reintento con el mismo id_operacion y exactamente los mismos parámetros
    second = service.reserve_slot_transactional(req)
    assert second["idempotent_replay"] is True
    assert second["interview_id"] == first["interview_id"]
    assert second["created_at"] == first["created_at"]
    assert service.repository.interviews.count_documents({"slot_id": slot_id}) == 1
    assert service.repository.operations.count_documents({"id_operacion": "OP-IDEMP-0001"}) == 1

    # Reutilización del mismo id_operacion con OTRO candidato -> debe rechazarse por conflicto
    conflicting_req = ReserveSlotRequest(
        slot_id=slot_id,
        application_id="APP-2026-0099",
        candidate_id="CAND-HASH-0099",
        vacancy_id="JOB-2026-001",
        id_operacion="OP-IDEMP-0001",
    )
    with pytest.raises(IdempotencyConflictError):
        service.reserve_slot_transactional(conflicting_req)

    # Verificar que el conflicto no alteró el estado previo
    slot_doc = service.repository.get_slot(slot_id)
    assert slot_doc is not None and slot_doc["candidate_id"] == "CAND-HASH-0017"


def test_concurrent_retry_storm_same_id_operacion_returns_single_interview(service):
    slot_id = "SLOT-IDEMP-STORM"
    _create_slot(service, slot_id)

    n_retries = 8
    barrier = threading.Barrier(n_retries)
    shared_req = ReserveSlotRequest(
        slot_id=slot_id,
        application_id="APP-STORM-01",
        candidate_id="CAND-STORM-01",
        vacancy_id="JOB-2026-001",
        id_operacion="OP-STORM-SAME-KEY",
    )

    def _retry(_: int) -> dict[str, Any]:
        barrier.wait()
        return service.reserve_slot_transactional(shared_req)

    with ThreadPoolExecutor(max_workers=n_retries) as pool:
        results = list(pool.map(_retry, range(n_retries)))

    interview_ids = {r["interview_id"] for r in results}
    assert len(interview_ids) == 1
    assert service.repository.interviews.count_documents({"slot_id": slot_id}) == 1
    assert service.repository.operations.count_documents({"id_operacion": "OP-STORM-SAME-KEY"}) == 1


# =============================================================================
# 6. FLUJO DE CANCELACIÓN ATÓMICA AUDITADA Y ROLLBACK (CA-ISS-16-06)
# =============================================================================
def test_atomic_cancellation_releases_slot_audits_and_allows_new_booking(service):
    slot_id = "SLOT-CANCEL-01"
    _create_slot(service, slot_id)

    booked = service.reserve_slot_transactional(
        ReserveSlotRequest(
            slot_id=slot_id,
            application_id="APP-CAN-01",
            candidate_id="CAND-CAN-01",
            vacancy_id="JOB-2026-001",
            id_operacion="OP-RES-CAN-01",
        )
    )
    interview_id = booked["interview_id"]

    # Inducir fallo controlado a mitad de la cancelación -> rollback completo
    with pytest.raises(SimulatedTransactionFailureError):
        service.cancel_interview_transactional(
            CancelInterviewRequest(
                interview_id=interview_id,
                id_operacion="OP-CAN-ABORT-01",
                reason="Fallo de red simulado",
            ),
            simulate_failure_after_step=2,
        )
    assert service.repository.get_slot(slot_id)["status"] == "reservado"  # type: ignore[index]
    assert service.repository.get_interview(interview_id)["status"] == "confirmada"  # type: ignore[index]
    assert service.repository.find_operation("OP-CAN-ABORT-01") is None

    # Cancelación válida
    cancel_req = CancelInterviewRequest(
        interview_id=interview_id,
        id_operacion="OP-CAN-OK-01",
        reason="Candidato reprograma entrevista por horario escolar",
    )
    cancelled = service.cancel_interview_transactional(cancel_req)
    assert cancelled["slot_status"] == "disponible"
    assert cancelled["interview_status"] == "cancelada"
    assert cancelled["idempotent_replay"] is False

    # Reintento idempotente de cancelación
    cancelled_replay = service.cancel_interview_transactional(cancel_req)
    assert cancelled_replay["idempotent_replay"] is True
    assert cancelled_replay["cancelled_at"] == cancelled["cancelled_at"]

    # Conflicto si se reutiliza OP-CAN-OK-01 para otra entrevista
    with pytest.raises(IdempotencyConflictError):
        service.cancel_interview_transactional(
            CancelInterviewRequest(interview_id="INT-OTRA-9999", id_operacion="OP-CAN-OK-01")
        )

    # Verificar estado en BD: franja disponible e historial de entrevista actualizado
    slot_after = service.repository.get_slot(slot_id)
    interview_after = service.repository.get_interview(interview_id)
    assert slot_after is not None and slot_after["status"] == "disponible" and slot_after["interview_id"] is None
    assert interview_after is not None and interview_after["status"] == "cancelada"
    assert len(interview_after["historial_estados"]) == 2

    # Un nuevo candidato puede reservar el cupo liberado
    rebooked = service.reserve_slot_transactional(
        ReserveSlotRequest(
            slot_id=slot_id,
            application_id="APP-CAN-02",
            candidate_id="CAND-CAN-02",
            vacancy_id="JOB-2026-001",
            id_operacion="OP-RES-CAN-02",
        )
    )
    assert rebooked["slot_status"] == "reservado"
    assert rebooked["candidate_id"] == "CAND-CAN-02"
    assert service.repository.interviews.count_documents({"slot_id": slot_id, "status": "confirmada"}) == 1
    assert service.repository.interviews.count_documents({"slot_id": slot_id, "status": "cancelada"}) == 1

    # Intentar cancelar entrevista inexistente o ya cancelada
    with pytest.raises(InterviewNotFoundError):
        service.cancel_interview_transactional(
            CancelInterviewRequest(interview_id="INT-NO-EXISTE", id_operacion="OP-CAN-MISSING")
        )
    with pytest.raises(InvalidStateTransitionError):
        service.cancel_interview_transactional(
            CancelInterviewRequest(interview_id=interview_id, id_operacion="OP-CAN-ALREADY")
        )


# =============================================================================
# 7. CONCURRENCIA SIMULADA ALTA CONTENCIÓN: THREADS + MULTIPROCESSING (CA-ISS-16-07)
# =============================================================================
def test_high_contention_threads_invariant_100_percent_across_10_rounds(service):
    """Ejecuta 10 rondas x 12 hilos paralelos sobre el mismo cupo: invariante <= 1 confirmado el 100% de las veces."""
    rounds = 10
    workers_per_round = 12

    for r in range(1, rounds + 1):
        slot_id = f"SLOT-STRESS-R{r:02d}"
        _create_slot(service, slot_id)
        barrier = threading.Barrier(workers_per_round)

        def _contender(idx: int) -> dict[str, Any]:
            req = ReserveSlotRequest(
                slot_id=slot_id,
                application_id=f"APP-R{r:02d}-W{idx:02d}",
                candidate_id=f"CAND-R{r:02d}-W{idx:02d}",
                vacancy_id="JOB-2026-001",
                id_operacion=f"OP-STRESS-R{r:02d}-W{idx:02d}",
            )
            barrier.wait()
            try:
                res = service.reserve_slot_transactional(req)
                return {"outcome": "won", "res": res}
            except SlotUnavailableError:
                return {"outcome": "unavailable"}

        with ThreadPoolExecutor(max_workers=workers_per_round) as pool:
            outcomes = list(pool.map(_contender, range(workers_per_round)))

        winners = [o for o in outcomes if o["outcome"] == "won"]
        rejected = [o for o in outcomes if o["outcome"] == "unavailable"]

        assert len(winners) == 1, f"Ronda {r}: se esperaba exactamente 1 ganador, hubo {len(winners)}"
        assert len(rejected) == workers_per_round - 1

        # Invariante global en MongoDB: exactamente 1 entrevista confirmada por horario
        confirmed_count = service.repository.interviews.count_documents({"slot_id": slot_id, "status": "confirmada"})
        assert confirmed_count == 1
        slot_doc = service.repository.get_slot(slot_id)
        assert slot_doc is not None and slot_doc["status"] == "reservado"
        assert slot_doc["candidate_id"] == winners[0]["res"]["candidate_id"]


def test_multiprocessing_parallel_processes_preserve_slot_invariant(service):
    """Valida exclusión mutua real entre 6 procesos OS independientes usando sus propios MongoClient."""
    slot_id = "SLOT-MULTIPROC-01"
    _create_slot(service, slot_id)
    settings = _make_test_settings()

    n_procs = 6
    start_epoch = time.time() + 1.5
    tasks = [
        (
            settings.uri,
            settings.database,
            settings.server_selection_timeout_ms,
            settings.connect_timeout_ms,
            settings.socket_timeout_ms,
            slot_id,
            i,
            start_epoch,
        )
        for i in range(n_procs)
    ]

    ctx = multiprocessing.get_context("spawn")
    with ctx.Pool(processes=n_procs) as pool:
        results = pool.map(_multiprocessing_worker, tasks)

    winners = [r for r in results if r["outcome"] == "success"]
    unavailable = [r for r in results if r["outcome"] == "unavailable"]

    assert len(winners) == 1, f"Multi-proceso debía producir 1 ganador exacto: {results}"
    assert len(unavailable) == n_procs - 1
    assert service.repository.interviews.count_documents({"slot_id": slot_id, "status": "confirmada"}) == 1


# =============================================================================
# 8. PRUEBA DE INTERFAZ CLI DESACOPLADA (CA-ISS-16-01)
# =============================================================================
def test_cli_end_to_end_reserve_replay_and_cancel(service, capsys):
    slot_id = "SLOT-CLI-01"
    _create_slot(service, slot_id)

    rc = cli_main(
        [
            "--db",
            TEST_DB_NAME,
            "reserve-tx",
            "--slot-id",
            slot_id,
            "--application-id",
            "APP-CLI-01",
            "--candidate-id",
            "CAND-CLI-01",
            "--vacancy-id",
            "JOB-2026-001",
            "--id-operacion",
            "OP-CLI-RES-01",
        ]
    )
    assert rc == 0
    out1 = json.loads(capsys.readouterr().out)
    assert out1["status"] == "ok"
    interview_id = out1["result"]["interview_id"]
    assert out1["result"]["idempotent_replay"] is False

    # Replay idempotente vía CLI
    rc2 = cli_main(
        [
            "--db",
            TEST_DB_NAME,
            "reserve-tx",
            "--slot-id",
            slot_id,
            "--application-id",
            "APP-CLI-01",
            "--candidate-id",
            "CAND-CLI-01",
            "--vacancy-id",
            "JOB-2026-001",
            "--id-operacion",
            "OP-CLI-RES-01",
        ]
    )
    assert rc2 == 0
    out2 = json.loads(capsys.readouterr().out)
    assert out2["result"]["idempotent_replay"] is True
    assert out2["result"]["interview_id"] == interview_id

    # Cancelación atómica vía CLI
    rc3 = cli_main(
        [
            "--db",
            TEST_DB_NAME,
            "cancel-tx",
            "--interview-id",
            interview_id,
            "--id-operacion",
            "OP-CLI-CAN-01",
        ]
    )
    assert rc3 == 0
    out3 = json.loads(capsys.readouterr().out)
    assert out3["result"]["slot_status"] == "disponible"
    assert out3["result"]["interview_status"] == "cancelada"
