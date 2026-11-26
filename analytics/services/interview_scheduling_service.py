#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Youth Employment Bridge (YEB) — Módulo de Persistencia NoSQL y Concurrencia
Issue #16 [NoSQL/Concurrency]: Servicio PyMongo desacoplado, control de concurrencia
en entrevistas, transacciones multi-documento ACID en Replica Set e idempotencia.

Arquitectura desacoplada por capas:
  1. Configuración y fábrica de cliente PyMongo (.env + timeouts explícitos).
  2. Modelos tipados de entrada, validación estricta y excepciones de dominio.
  3. Repositorio MongoDB (índices, consultas de regresión Semana 7 y operaciones CRUD/sesión).
  4. Servicio de negocio (atomicidad condicional por documento, transacciones ACID
     multi-documento con rollback verificable, idempotencia por id_operacion y cancelación).
  5. Capa de presentación CLI (argparse).

Criterios de Aceptación cubiertos: CA-ISS-16-01 a CA-ISS-16-08
Riesgos mitigados: TEC-020, OPE-009, SEC-012 | Casos de prueba: CP-056, CP-056B, CP-056C
"""

from __future__ import annotations

import argparse
import hashlib
import json
import os
import random
import subprocess
import sys
import time
import uuid
from dataclasses import asdict, dataclass
from datetime import datetime, timezone
from pathlib import Path
from typing import Any


def _ensure_venv_interpreter() -> None:
    """Re-ejecuta con analytics/.venv cuando se invoca el CLI directamente con Python global."""
    if __name__ != "__main__":
        return
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

from bson import ObjectId
from dotenv import load_dotenv
from pymongo import ASCENDING, MongoClient, ReadPreference
from pymongo.client_session import ClientSession
from pymongo.collection import Collection
from pymongo.database import Database
from pymongo.errors import DuplicateKeyError, OperationFailure, PyMongoError
from pymongo.read_concern import ReadConcern
from pymongo.write_concern import WriteConcern

# =============================================================================
# CONSTANTES DE DOMINIO Y REGRESIÓN SEMANA 7
# =============================================================================
COL_SLOTS = "franjas_entrevista"
COL_INTERVIEWS = "entrevistas"
COL_OPERATIONS = "operaciones"
COL_SYNTH_W07 = "postulaciones_sinteticas_perf"

IDX_COMPUESTO_W07 = "idx_comp_dataset_estado_fecha_id"
IDX_MULTIKEY_W07 = "idx_multikey_habilidades_estado"

DATASET_TAG_W07 = "YEB_SYNTH_W07"
TOTAL_DOCS_W07 = 6000
RANGO_W07_INICIO = datetime(2026, 10, 10, 0, 0, 0, tzinfo=timezone.utc)
RANGO_W07_FIN = datetime(2026, 10, 20, 23, 59, 59, tzinfo=timezone.utc)
FECHA_EMPATE_W07 = datetime(2026, 10, 12, 14, 0, 0, tzinfo=timezone.utc)

MODALIDADES_VALIDAS = frozenset({"Remoto", "Híbrido", "Presencial"})
ESTADOS_FRANJA = frozenset({"disponible", "reservado"})
ESTADOS_ENTREVISTA = frozenset({"confirmada", "cancelada"})
MAX_TX_RETRIES = 12


# =============================================================================
# CAPA 1: EXCEPCIONES DE DOMINIO
# =============================================================================
class SchedulingServiceError(Exception):
    """Error base del servicio de agendamiento de entrevistas YEB."""


class ValidationError(SchedulingServiceError):
    """Parámetros de entrada inválidos o configuración incompleta."""


class ReplicaSetRequiredError(SchedulingServiceError):
    """El servidor MongoDB no opera como Replica Set (requisito para transacciones ACID)."""


class SlotUnavailableError(SchedulingServiceError):
    """La franja solicitada no existe o ya fue reservada por otro candidato."""


class IdempotencyConflictError(SchedulingServiceError):
    """Se reutilizó un id_operacion existente con parámetros distintos."""


class InterviewNotFoundError(SchedulingServiceError):
    """No existe una entrevista registrada con el identificador indicado."""


class InvalidStateTransitionError(SchedulingServiceError):
    """La transición de estado solicitada no es válida para la entidad actual."""


class SimulatedTransactionFailureError(SchedulingServiceError):
    """Excepción controlada inducida para verificar abort_transaction() y rollback ACID."""


# =============================================================================
# CAPA 2: CONFIGURACIÓN DESACOPLADA Y FÁBRICA DE CLIENTE PYMONGO (CA-ISS-16-01)
# =============================================================================
def resolve_repo_root() -> Path:
    here = Path(__file__).resolve()
    if here.parent.name == "services" and here.parent.parent.name == "analytics":
        return here.parent.parent.parent
    return Path.cwd()


@dataclass(frozen=True)
class MongoSettings:
    """Configuración desacoplada del cliente PyMongo con tiempos de espera explícitos."""

    uri: str
    database: str
    replica_set: str
    server_selection_timeout_ms: int = 5000
    connect_timeout_ms: int = 5000
    socket_timeout_ms: int = 10000

    def validate(self) -> None:
        if not self.uri or not self.uri.startswith(("mongodb://", "mongodb+srv://")):
            raise ValidationError("YEB_MONGO_URI debe comenzar por 'mongodb://' o 'mongodb+srv://'.")
        if not self.database or not self.database.strip():
            raise ValidationError("YEB_MONGO_CONCURRENCY_DB no puede estar vacío.")
        for field_name in ("server_selection_timeout_ms", "connect_timeout_ms", "socket_timeout_ms"):
            val = getattr(self, field_name)
            if not isinstance(val, int) or val <= 0:
                raise ValidationError(f"{field_name} debe ser un entero positivo en milisegundos.")

    @classmethod
    def from_env(cls, env_path: Path | None = None, database_override: str | None = None) -> "MongoSettings":
        target_env = env_path or (resolve_repo_root() / ".env")
        if target_env.exists():
            load_dotenv(dotenv_path=target_env, override=False)

        host = os.getenv("YEB_MONGO_HOST", "127.0.0.1").strip()
        port = os.getenv("YEB_MONGO_PORT", "27017").strip()
        replica_set = os.getenv("YEB_MONGO_REPLICA_SET", "rs0").strip()
        default_uri = f"mongodb://{host}:{port}/?replicaSet={replica_set}&directConnection=true"

        uri = os.getenv("YEB_MONGO_URI", default_uri).strip()
        database = (database_override or os.getenv("YEB_MONGO_CONCURRENCY_DB", "yeb_analytics_p05")).strip()

        def _parse_ms(key: str, default: int) -> int:
            raw = os.getenv(key, str(default)).strip()
            try:
                value = int(raw)
            except ValueError as exc:
                raise ValidationError(f"{key} debe ser un entero en milisegundos (recibido: '{raw}').") from exc
            if value <= 0:
                raise ValidationError(f"{key} debe ser mayor que 0 ms (recibido: {value}).")
            return value

        settings = cls(
            uri=uri,
            database=database,
            replica_set=replica_set,
            server_selection_timeout_ms=_parse_ms("YEB_MONGO_SERVER_SELECTION_TIMEOUT_MS", 5000),
            connect_timeout_ms=_parse_ms("YEB_MONGO_CONNECT_TIMEOUT_MS", 5000),
            socket_timeout_ms=_parse_ms("YEB_MONGO_SOCKET_TIMEOUT_MS", 10000),
        )
        settings.validate()
        return settings


def build_mongo_client(settings: MongoSettings) -> MongoClient:
    """Construye un MongoClient parametrizado con timeouts explícitos y fechas tz-aware UTC."""
    settings.validate()
    return MongoClient(
        settings.uri,
        serverSelectionTimeoutMS=settings.server_selection_timeout_ms,
        connectTimeoutMS=settings.connect_timeout_ms,
        socketTimeoutMS=settings.socket_timeout_ms,
        retryWrites=True,
        tz_aware=True,
    )


def verify_replica_set(client: MongoClient) -> dict[str, Any]:
    """Confirma que el nodo MongoDB opera como Replica Set primario apto para transacciones ACID."""
    hello = client.admin.command("hello")
    set_name = hello.get("setName")
    if not set_name:
        raise ReplicaSetRequiredError(
            "El servidor MongoDB conectado es standalone (sin --replSet) y no soporta transacciones "
            "multi-documento ACID. Recrea el contenedor con:\n"
            "  docker compose up -d --force-recreate mongodb"
        )
    return {
        "setName": set_name,
        "isWritablePrimary": bool(hello.get("isWritablePrimary")),
        "me": hello.get("me"),
        "hosts": hello.get("hosts", []),
    }


def ensure_replica_set_ready(settings: MongoSettings, wait_seconds: float = 15.0) -> dict[str, Any]:
    """
    Diagnostica la conectividad directa al nodo MongoDB antes de abrir sesiones transaccionales:
      - Si el servidor está en modo standalone (sin --replSet), informa claramente cómo recrear el contenedor.
      - Si el servidor tiene --replSet activo pero aún no fue inicializado (NotYetInitialized),
        ejecuta automáticamente `replSetInitiate` y espera a que el nodo sea Primary.
      - Si ya está inicializado, espera brevemente a que termine la elección de Primary.
    """
    from urllib.parse import urlsplit

    parsed = urlsplit(settings.uri)
    host = parsed.hostname or "127.0.0.1"
    port = parsed.port or 27017
    direct_uri = f"mongodb://{host}:{port}/?directConnection=true"

    probe = MongoClient(
        direct_uri,
        serverSelectionTimeoutMS=settings.server_selection_timeout_ms,
        connectTimeoutMS=settings.connect_timeout_ms,
        socketTimeoutMS=settings.socket_timeout_ms,
        tz_aware=True,
    )
    try:
        try:
            hello = probe.admin.command("hello")
        except PyMongoError as exc:
            raise ReplicaSetRequiredError(
                f"No se pudo conectar con MongoDB en {host}:{port} ({exc}). "
                "Verifica que Docker Desktop esté iniciado y ejecuta:\n"
                "  docker compose up -d --force-recreate mongodb"
            ) from exc

        set_name = hello.get("setName")
        if not set_name:
            # Distinguir entre: (a) mongod arrancado con --replSet pero sin rs.initiate(), vs. (b) standalone puro
            is_rs_configured = False
            try:
                opts = probe.admin.command("getCmdLineOpts")
                parsed_opts = opts.get("parsed", {})
                argv = opts.get("argv", [])
                is_rs_configured = bool(
                    parsed_opts.get("replication", {}).get("replSetName")
                    or any("--replSet" in str(a) for a in argv)
                )
            except PyMongoError:
                pass

            if not is_rs_configured:
                raise ReplicaSetRequiredError(
                    f"MongoDB en {host}:{port} está corriendo en modo STANDALONE (sin '--replSet {settings.replica_set}'), "
                    "por lo que no soporta transacciones multi-documento ACID.\n"
                    "Ejecuta en la raíz del proyecto para recrear el contenedor con Replica Set:\n"
                    "  docker compose up -d --force-recreate mongodb"
                )

            # Tiene --replSet configurado pero aún no se ha inicializado -> inicializar automáticamente
            try:
                probe.admin.command(
                    "replSetInitiate",
                    {
                        "_id": settings.replica_set,
                        "members": [{"_id": 0, "host": f"{host}:{port}"}],
                    },
                )
            except OperationFailure as init_exc:
                # Código 23 = AlreadyInitialized (carrera inofensiva con el healthcheck de Docker)
                if init_exc.code != 23:
                    raise ReplicaSetRequiredError(
                        f"No se pudo inicializar el Replica Set '{settings.replica_set}': {init_exc}"
                    ) from init_exc

        # Esperar hasta que el nodo tenga setName activo y sea Primary escribible
        deadline = time.monotonic() + wait_seconds
        last_hello = hello
        while time.monotonic() < deadline:
            last_hello = probe.admin.command("hello")
            if last_hello.get("setName") and last_hello.get("isWritablePrimary"):
                if last_hello.get("setName") != settings.replica_set:
                    raise ReplicaSetRequiredError(
                        f"El servidor pertenece al Replica Set '{last_hello.get('setName')}', "
                        f"pero YEB_MONGO_REPLICA_SET espera '{settings.replica_set}'."
                    )
                return {
                    "setName": last_hello.get("setName"),
                    "isWritablePrimary": True,
                    "me": last_hello.get("me"),
                    "hosts": last_hello.get("hosts", []),
                }
            time.sleep(0.35)

        raise ReplicaSetRequiredError(
            f"El Replica Set '{settings.replica_set}' no eligió un Primary escribible en {wait_seconds:.0f}s "
            f"(último estado: setName={last_hello.get('setName')}, isWritablePrimary={last_hello.get('isWritablePrimary')})."
        )
    finally:
        probe.close()


# =============================================================================
# CAPA 3: MODELOS DE ENTRADA TIPADOS Y VALIDACIÓN ESTRICTA
# =============================================================================
def _require_non_empty_str(value: Any, field_name: str, min_len: int = 2) -> str:
    if not isinstance(value, str) or len(value.strip()) < min_len:
        raise ValidationError(f"El campo '{field_name}' debe ser una cadena no vacía (mín. {min_len} caracteres).")
    return value.strip()


def _ensure_utc_datetime(value: datetime | str, field_name: str) -> datetime:
    if isinstance(value, str):
        try:
            dt = datetime.fromisoformat(value.replace("Z", "+00:00"))
        except ValueError as exc:
            raise ValidationError(f"El campo '{field_name}' no es una fecha ISO-8601 válida: '{value}'.") from exc
    elif isinstance(value, datetime):
        dt = value
    else:
        raise ValidationError(f"El campo '{field_name}' debe ser datetime o cadena ISO-8601.")
    if dt.tzinfo is None:
        dt = dt.replace(tzinfo=timezone.utc)
    return dt.astimezone(timezone.utc)


def compute_payload_hash(operation_type: str, canonical_params: dict[str, Any]) -> str:
    """Genera una huella SHA-256 determinista de los parámetros de la operación para validar idempotencia."""
    canonical = {"tipo_operacion": operation_type, "parametros": canonical_params}
    raw = json.dumps(canonical, sort_keys=True, ensure_ascii=True, separators=(",", ":"))
    return hashlib.sha256(raw.encode("utf-8")).hexdigest()


@dataclass(frozen=True)
class CreateSlotRequest:
    slot_id: str
    vacancy_id: str
    company_name: str
    starts_at: datetime
    ends_at: datetime
    modality: str = "Remoto"

    def validated(self) -> "CreateSlotRequest":
        slot_id = _require_non_empty_str(self.slot_id, "slot_id", min_len=3)
        vacancy_id = _require_non_empty_str(self.vacancy_id, "vacancy_id", min_len=3)
        company_name = _require_non_empty_str(self.company_name, "company_name", min_len=2)
        starts_at = _ensure_utc_datetime(self.starts_at, "starts_at")
        ends_at = _ensure_utc_datetime(self.ends_at, "ends_at")
        if ends_at <= starts_at:
            raise ValidationError("La fecha 'ends_at' debe ser estrictamente posterior a 'starts_at'.")
        if self.modality not in MODALIDADES_VALIDAS:
            raise ValidationError(f"Modalidad inválida '{self.modality}'. Válidas: {sorted(MODALIDADES_VALIDAS)}.")
        return CreateSlotRequest(
            slot_id=slot_id,
            vacancy_id=vacancy_id,
            company_name=company_name,
            starts_at=starts_at,
            ends_at=ends_at,
            modality=self.modality,
        )


@dataclass(frozen=True)
class ReserveSlotRequest:
    slot_id: str
    application_id: str
    candidate_id: str
    vacancy_id: str
    id_operacion: str
    interviewer_name: str = "Equipo Reclutamiento YEB"

    def validated(self) -> "ReserveSlotRequest":
        return ReserveSlotRequest(
            slot_id=_require_non_empty_str(self.slot_id, "slot_id", min_len=3),
            application_id=_require_non_empty_str(self.application_id, "application_id", min_len=3),
            candidate_id=_require_non_empty_str(self.candidate_id, "candidate_id", min_len=3),
            vacancy_id=_require_non_empty_str(self.vacancy_id, "vacancy_id", min_len=3),
            id_operacion=_require_non_empty_str(self.id_operacion, "id_operacion", min_len=4),
            interviewer_name=_require_non_empty_str(self.interviewer_name, "interviewer_name", min_len=2),
        )

    def canonical_params(self) -> dict[str, str]:
        return {
            "slot_id": self.slot_id,
            "application_id": self.application_id,
            "candidate_id": self.candidate_id,
            "vacancy_id": self.vacancy_id,
        }


@dataclass(frozen=True)
class CancelInterviewRequest:
    interview_id: str
    id_operacion: str
    reason: str = "Cancelación solicitada en el portal YEB"

    def validated(self) -> "CancelInterviewRequest":
        return CancelInterviewRequest(
            interview_id=_require_non_empty_str(self.interview_id, "interview_id", min_len=3),
            id_operacion=_require_non_empty_str(self.id_operacion, "id_operacion", min_len=4),
            reason=_require_non_empty_str(self.reason, "reason", min_len=3),
        )

    def canonical_params(self) -> dict[str, str]:
        return {
            "interview_id": self.interview_id,
        }


@dataclass(frozen=True)
class AgendaPaginationRequest:
    dataset: str = DATASET_TAG_W07
    estado_actual: str = "interview"
    fecha_inicio: datetime = RANGO_W07_INICIO
    fecha_fin: datetime = RANGO_W07_FIN
    page_size: int = 10
    cursor_fecha: datetime | None = None
    cursor_id: ObjectId | str | None = None

    def validated(self) -> "AgendaPaginationRequest":
        dataset = _require_non_empty_str(self.dataset, "dataset", min_len=2)
        estado = _require_non_empty_str(self.estado_actual, "estado_actual", min_len=2)
        inicio = _ensure_utc_datetime(self.fecha_inicio, "fecha_inicio")
        fin = _ensure_utc_datetime(self.fecha_fin, "fecha_fin")
        if fin < inicio:
            raise ValidationError("'fecha_fin' no puede ser anterior a 'fecha_inicio'.")
        if not isinstance(self.page_size, int) or not (1 <= self.page_size <= 500):
            raise ValidationError("'page_size' debe ser un entero entre 1 y 500.")
        if (self.cursor_fecha is None) != (self.cursor_id is None):
            raise ValidationError("El token de paginación requiere ambos campos: 'cursor_fecha' y 'cursor_id'.")
        c_fecha = _ensure_utc_datetime(self.cursor_fecha, "cursor_fecha") if self.cursor_fecha is not None else None
        c_id: ObjectId | None = None
        if self.cursor_id is not None:
            try:
                c_id = self.cursor_id if isinstance(self.cursor_id, ObjectId) else ObjectId(str(self.cursor_id))
            except Exception as exc:
                raise ValidationError(f"'cursor_id' no es un ObjectId válido: '{self.cursor_id}'.") from exc
        return AgendaPaginationRequest(
            dataset=dataset,
            estado_actual=estado,
            fecha_inicio=inicio,
            fecha_fin=fin,
            page_size=self.page_size,
            cursor_fecha=c_fecha,
            cursor_id=c_id,
        )


# =============================================================================
# GENERADOR DETERMINISTA DE LA BASE CONGELADA DE SEMANA 7 (6.000 DOCS)
# =============================================================================
def build_week7_synthetic_documents(total_docs: int = TOTAL_DOCS_W07) -> list[dict[str, Any]]:
    """
    Replica exactamente el generador determinista de `07_indices_y_paginacion_yeb.js` (Semana 7)
    para poder sembrar o validar la colección congelada `postulaciones_sinteticas_perf` en PyMongo.
    """
    ciudades = ["Bogotá", "Medellín", "Cali", "Barranquilla", "Bucaramanga", "Pereira"]
    estados = ["pending", "review", "interview", "accepted", "rejected"]
    paquetes = [
        ["Python", "SQL", "Git"],
        ["JavaScript", "HTML/CSS", "Git"],
        ["Excel", "Facturación Electrónica"],
        ["Atención al Cliente", "Comunicación Asertiva", "Canva"],
        ["Control de Inventarios", "Excel"],
        ["Python", "Power BI", "Excel"],
    ]

    docs: list[dict[str, Any]] = []
    for i in range(1, total_docs + 1):
        hex_id = f"6728d000000000000000{i:04d}"
        codigo = f"SYN-POST-{i:04d}"
        ciudad = ciudades[i % len(ciudades)]

        if i <= 9:
            estado = "interview"
            fecha_agenda = datetime(2026, 10, 10, 8 + i, 0, 0, tzinfo=timezone.utc)
            habilidades = ["Python", "SQL", "Git"] if i % 2 == 0 else ["JavaScript", "HTML/CSS"]
        elif i in (10, 11):
            estado = "interview"
            fecha_agenda = FECHA_EMPATE_W07
            habilidades = ["Python", "SQL", "Power BI"]
        else:
            estado = estados[i % len(estados)]
            dia_octubre = 1 + (i % 28)
            hora = (i * 7) % 24
            minuto = (i * 13) % 60
            dia_ajustado = (13 + (i % 7)) if (estado == "interview" and 10 <= dia_octubre <= 12) else dia_octubre
            fecha_agenda = datetime(2026, 10, dia_ajustado, hora, minuto, 0, tzinfo=timezone.utc)
            habilidades = paquetes[i % len(paquetes)]

        docs.append(
            {
                "_id": ObjectId(hex_id),
                "dataset": DATASET_TAG_W07,
                "codigo_postulacion": codigo,
                "candidato_hash": f"CAND-SYN-{(i % 1500) + 1:04d}",
                "vacante_codigo": f"VAC-SYN-{(i % 120) + 1:03d}",
                "estado_actual": estado,
                "ciudad": ciudad,
                "fecha_agenda": fecha_agenda,
                "habilidades_candidato": habilidades,
                "puntaje_ajuste": 60 + (i % 40),
            }
        )
    return docs


# =============================================================================
# CAPA 4: REPOSITORIO MONGODB (ACCESO A DATOS DESACOPLADO)
# =============================================================================
class InterviewSchedulingRepository:
    """Repositorio MongoDB responsable de colecciones, índices y operaciones CRUD/transaccionales."""

    def __init__(self, db: Database) -> None:
        self._db = db

    @property
    def db(self) -> Database:
        return self._db

    @property
    def slots(self) -> Collection:
        return self._db[COL_SLOTS]

    @property
    def interviews(self) -> Collection:
        return self._db[COL_INTERVIEWS]

    @property
    def operations(self) -> Collection:
        return self._db[COL_OPERATIONS]

    @property
    def synthetic_w07(self) -> Collection:
        return self._db[COL_SYNTH_W07]

    def ensure_collections_and_indexes(self) -> None:
        """
        Pre-crea colecciones e índices antes de ejecutar transacciones multi-documento,
        evitando errores de catálogo dentro de bloques start_transaction() en Replica Set.
        """
        existing = set(self._db.list_collection_names())
        for name in (COL_SLOTS, COL_INTERVIEWS, COL_OPERATIONS):
            if name not in existing:
                try:
                    self._db.create_collection(name)
                except OperationFailure:
                    pass

        self.slots.create_index([("slot_id", ASCENDING)], name="ux_slot_id", unique=True)
        self.slots.create_index(
            [("vacancy_id", ASCENDING), ("status", ASCENDING), ("starts_at", ASCENDING)],
            name="ix_slot_vacancy_status_start",
        )
        self.interviews.create_index([("interview_id", ASCENDING)], name="ux_interview_id", unique=True)
        # Invariante reforzado en motor: máximo 1 entrevista en estado 'confirmada' por slot_id
        self.interviews.create_index(
            [("slot_id", ASCENDING)],
            name="ux_active_interview_per_slot",
            unique=True,
            partialFilterExpression={"status": "confirmada"},
        )
        self.interviews.create_index([("application_id", ASCENDING), ("status", ASCENDING)], name="ix_interview_app_status")
        self.operations.create_index([("id_operacion", ASCENDING)], name="ux_id_operacion", unique=True)

    def ensure_week7_frozen_dataset(self) -> int:
        """Asegura que la colección congelada de Semana 7 (`postulaciones_sinteticas_perf`, 6.000 docs) e índices existan."""
        count = self.synthetic_w07.count_documents({"dataset": DATASET_TAG_W07})
        if count != TOTAL_DOCS_W07:
            self.synthetic_w07.delete_many({"dataset": DATASET_TAG_W07})
            self.synthetic_w07.insert_many(build_week7_synthetic_documents(TOTAL_DOCS_W07))
        self.synthetic_w07.create_index(
            [("dataset", ASCENDING), ("estado_actual", ASCENDING), ("fecha_agenda", ASCENDING), ("_id", ASCENDING)],
            name=IDX_COMPUESTO_W07,
        )
        self.synthetic_w07.create_index(
            [("habilidades_candidato", ASCENDING), ("dataset", ASCENDING), ("estado_actual", ASCENDING), ("codigo_postulacion", ASCENDING)],
            name=IDX_MULTIKEY_W07,
        )
        return self.synthetic_w07.count_documents({"dataset": DATASET_TAG_W07})

    # --- Consultas de regresión Semana 7 en sintaxis PyMongo ---
    def find_week7_consulta_a(self, req: AgendaPaginationRequest) -> tuple[list[dict[str, Any]], dict[str, Any]]:
        filtro: dict[str, Any] = {
            "dataset": req.dataset,
            "estado_actual": req.estado_actual,
            "fecha_agenda": {"$gte": req.fecha_inicio, "$lte": req.fecha_fin},
        }
        if req.cursor_fecha is not None and req.cursor_id is not None:
            filtro = {
                "$and": [
                    filtro,
                    {
                        "$or": [
                            {"fecha_agenda": {"$gt": req.cursor_fecha}},
                            {"fecha_agenda": req.cursor_fecha, "_id": {"$gt": req.cursor_id}},
                        ]
                    },
                ]
            }
        proyeccion = {"_id": 1, "codigo_postulacion": 1, "estado_actual": 1, "fecha_agenda": 1, "ciudad": 1}
        orden = [("fecha_agenda", ASCENDING), ("_id", ASCENDING)]
        cursor = self.synthetic_w07.find(filtro, proyeccion).sort(orden)
        if req.page_size is not None and req.page_size < TOTAL_DOCS_W07:
            cursor = cursor.limit(req.page_size)
        docs = list(cursor)

        explain_cursor = self.synthetic_w07.find(filtro, proyeccion).sort(orden)
        if req.page_size is not None and req.page_size < TOTAL_DOCS_W07:
            explain_cursor = explain_cursor.limit(req.page_size)
        explain = explain_cursor.explain()
        return docs, explain

    def find_week7_consulta_a_full(self) -> tuple[list[dict[str, Any]], dict[str, Any]]:
        filtro = {
            "dataset": DATASET_TAG_W07,
            "estado_actual": "interview",
            "fecha_agenda": {"$gte": RANGO_W07_INICIO, "$lte": RANGO_W07_FIN},
        }
        proyeccion = {"_id": 1, "codigo_postulacion": 1, "estado_actual": 1, "fecha_agenda": 1, "ciudad": 1}
        orden = [("fecha_agenda", ASCENDING), ("_id", ASCENDING)]
        docs = list(self.synthetic_w07.find(filtro, proyeccion).sort(orden))
        explain = self.synthetic_w07.find(filtro, proyeccion).sort(orden).explain()
        return docs, explain

    def find_week7_consulta_b_full(self, habilidad: str = "Python") -> tuple[list[dict[str, Any]], dict[str, Any]]:
        filtro = {
            "dataset": DATASET_TAG_W07,
            "habilidades_candidato": habilidad,
            "estado_actual": "interview",
        }
        proyeccion = {"_id": 1, "codigo_postulacion": 1, "estado_actual": 1, "habilidades_candidato": 1, "ciudad": 1}
        orden = [("codigo_postulacion", ASCENDING), ("_id", ASCENDING)]
        docs = list(self.synthetic_w07.find(filtro, proyeccion).sort(orden))
        explain = self.synthetic_w07.find(filtro, proyeccion).sort(orden).explain()
        return docs, explain

    # --- Operaciones sobre franjas, entrevistas y auditoría ---
    def upsert_slot(self, req: CreateSlotRequest) -> dict[str, Any]:
        now = datetime.now(timezone.utc)
        doc = {
            "slot_id": req.slot_id,
            "vacancy_id": req.vacancy_id,
            "company_name": req.company_name,
            "starts_at": req.starts_at,
            "ends_at": req.ends_at,
            "modality": req.modality,
            "status": "disponible",
            "interview_id": None,
            "application_id": None,
            "candidate_id": None,
            "version": 1,
            "created_at": now,
            "updated_at": now,
        }
        self.slots.replace_one({"slot_id": req.slot_id}, doc, upsert=True)
        return self.slots.find_one({"slot_id": req.slot_id})  # type: ignore[return-value]

    def get_slot(self, slot_id: str, session: ClientSession | None = None) -> dict[str, Any] | None:
        return self.slots.find_one({"slot_id": slot_id}, session=session)

    def list_slots(self, status: str | None = None) -> list[dict[str, Any]]:
        query = {"status": status} if status else {}
        return list(self.slots.find(query).sort([("starts_at", ASCENDING), ("slot_id", ASCENDING)]))

    def reserve_slot_conditional(
        self,
        *,
        slot_id: str,
        application_id: str,
        candidate_id: str,
        interview_id: str | None,
        now: datetime,
        session: ClientSession | None = None,
    ) -> int:
        """Actualización condicional atómica a nivel de documento exigiendo status == 'disponible'."""
        result = self.slots.update_one(
            {"slot_id": slot_id, "status": "disponible"},
            {
                "$set": {
                    "status": "reservado",
                    "interview_id": interview_id,
                    "application_id": application_id,
                    "candidate_id": candidate_id,
                    "updated_at": now,
                },
                "$inc": {"version": 1},
            },
            session=session,
        )
        return int(result.modified_count)

    def release_slot_conditional(
        self,
        *,
        slot_id: str,
        interview_id: str,
        now: datetime,
        session: ClientSession | None = None,
    ) -> int:
        result = self.slots.update_one(
            {"slot_id": slot_id, "status": "reservado", "interview_id": interview_id},
            {
                "$set": {
                    "status": "disponible",
                    "interview_id": None,
                    "application_id": None,
                    "candidate_id": None,
                    "updated_at": now,
                },
                "$inc": {"version": 1},
            },
            session=session,
        )
        return int(result.modified_count)

    def insert_interview(self, doc: dict[str, Any], session: ClientSession | None = None) -> None:
        self.interviews.insert_one(doc, session=session)

    def get_interview(self, interview_id: str, session: ClientSession | None = None) -> dict[str, Any] | None:
        return self.interviews.find_one({"interview_id": interview_id}, session=session)

    def cancel_interview_conditional(
        self,
        *,
        interview_id: str,
        id_operacion: str,
        reason: str,
        now: datetime,
        session: ClientSession | None = None,
    ) -> int:
        result = self.interviews.update_one(
            {"interview_id": interview_id, "status": "confirmada"},
            {
                "$set": {
                    "status": "cancelada",
                    "id_operacion_cancelacion": id_operacion,
                    "cancel_reason": reason,
                    "updated_at": now,
                },
                "$push": {
                    "historial_estados": {
                        "estado": "cancelada",
                        "motivo": reason,
                        "id_operacion": id_operacion,
                        "fecha": now,
                    }
                },
            },
            session=session,
        )
        return int(result.modified_count)

    def find_operation(self, id_operacion: str, session: ClientSession | None = None) -> dict[str, Any] | None:
        return self.operations.find_one({"id_operacion": id_operacion}, session=session)

    def insert_operation(self, doc: dict[str, Any], session: ClientSession | None = None) -> None:
        self.operations.insert_one(doc, session=session)

    def list_operations(self, limit: int = 50) -> list[dict[str, Any]]:
        return list(self.operations.find({}).sort([("created_at", ASCENDING)]).limit(limit))


# =============================================================================
# CAPA 5: SERVICIO DE NEGOCIO (CONCURRENCIA, ACID E IDEMPOTENCIA)
# =============================================================================
def _extract_winning_stage(plan: dict[str, Any] | None) -> str:
    if not plan:
        return "UNKNOWN"
    stage = plan.get("stage", "")
    if stage in ("COLLSCAN", "IXSCAN"):
        return stage
    if "inputStage" in plan:
        return _extract_winning_stage(plan["inputStage"])
    if "queryPlan" in plan:
        return _extract_winning_stage(plan["queryPlan"])
    return stage or "UNKNOWN"


def _canonical_sha256_docs(docs: list[dict[str, Any]]) -> str:
    """Hash SHA-256 determinista del conjunto de documentos proyectados y su orden exacto."""
    normalized = []
    for d in docs:
        item: dict[str, Any] = {}
        for k, v in d.items():
            if isinstance(v, ObjectId):
                item[k] = {"$oid": str(v)}
            elif isinstance(v, datetime):
                item[k] = {"$date": v.astimezone(timezone.utc).strftime("%Y-%m-%dT%H:%M:%S.%f")[:-3] + "Z"}
            else:
                item[k] = v
        normalized.append(item)
    payload = json.dumps(normalized, ensure_ascii=False, separators=(",", ":"))
    return hashlib.sha256(payload.encode("utf-8")).hexdigest()


class InterviewSchedulingService:
    """
    Servicio de agendamiento y cancelación de entrevistas de YEB con:
      - Prueba de regresión de lecturas de Semana 7 en PyMongo.
      - Atomicidad condicional a nivel de documento (status='disponible').
      - Transacciones ACID multi-documento en Replica Set (reserva y cancelación).
      - Manejo estricto de idempotencia mediante `id_operacion` y `payload_hash`.
    """

    def __init__(self, client: MongoClient, repository: InterviewSchedulingRepository) -> None:
        self._client = client
        self._repo = repository

    @property
    def repository(self) -> InterviewSchedulingRepository:
        return self._repo

    def initialize(self, ensure_w07_dataset: bool = False) -> dict[str, Any]:
        rs_info = verify_replica_set(self._client)
        self._repo.ensure_collections_and_indexes()
        w07_count = self._repo.ensure_week7_frozen_dataset() if ensure_w07_dataset else None
        return {"replica_set": rs_info, "week7_docs": w07_count}

    def create_slot(self, request: CreateSlotRequest) -> dict[str, Any]:
        req = request.validated()
        self._repo.ensure_collections_and_indexes()
        slot = self._repo.upsert_slot(req)
        return self._serialize_slot(slot)

    # -------------------------------------------------------------------------
    # CA-ISS-16-02: PRUEBA DE REGRESIÓN DE LECTURAS DE SEMANA 7 EN PYMONGO
    # -------------------------------------------------------------------------
    def run_week7_read_regression(self, analytics_root: Path | None = None) -> dict[str, Any]:
        """
        Traslada a PyMongo las consultas optimizadas de Semana 7 (Consulta A compuesta,
        Consulta B multikey sin $unwind y paginación por token compuesto `(fecha_agenda, _id)`)
        y las valida contra la evidencia congelada de Semana 7.
        """
        self._repo.ensure_week7_frozen_dataset()

        docs_a, exp_a = self._repo.find_week7_consulta_a_full()
        docs_b, exp_b = self._repo.find_week7_consulta_b_full("Python")

        req_p1 = AgendaPaginationRequest(page_size=10).validated()
        page1, exp_p1 = self._repo.find_week7_consulta_a(req_p1)
        last_p1 = page1[-1]
        req_p2 = AgendaPaginationRequest(
            page_size=10,
            cursor_fecha=last_p1["fecha_agenda"],
            cursor_id=last_p1["_id"],
        ).validated()
        page2, exp_p2 = self._repo.find_week7_consulta_a(req_p2)

        # Contraste directo contra outputs congelados de Semana 7
        root = analytics_root or (resolve_repo_root() / "analytics")
        ejson_w07_path = root / "outputs" / "experimento_indices_paginacion_semana7.ejson"
        try:
            ref_rel = ejson_w07_path.relative_to(resolve_repo_root()).as_posix()
        except ValueError:
            ref_rel = ejson_w07_path.name
        w07_checks: dict[str, Any] = {"archivo_referencia": ref_rel}
        if ejson_w07_path.exists():
            ref_payload = json.loads(ejson_w07_path.read_text(encoding="utf-8"))
            ref_exp_a = ref_payload["matriz_explain"][1]
            ref_exp_b = ref_payload["matriz_explain"][4]
            ref_empate = ref_payload["empate_frontera_paginacion"]

            expected_n_a = int(ref_exp_a["nReturned"]["$numberInt"])
            expected_n_b = int(ref_exp_b["nReturned"]["$numberInt"])
            expected_last_p1 = ref_empate["ultimo_pagina_1"]["codigo_postulacion"]
            expected_first_p2 = ref_empate["primero_pagina_2"]["codigo_postulacion"]

            assert len(docs_a) == expected_n_a == 482, f"Consulta A esperada={expected_n_a}, obtenida={len(docs_a)}"
            assert len(docs_b) == expected_n_b == 406, f"Consulta B esperada={expected_n_b}, obtenida={len(docs_b)}"
            assert page1[-1]["codigo_postulacion"] == expected_last_p1 == "SYN-POST-0010"
            assert page2[0]["codigo_postulacion"] == expected_first_p2 == "SYN-POST-0011"
            assert page1[-1]["fecha_agenda"] == page2[0]["fecha_agenda"] == FECHA_EMPATE_W07
            assert str(page1[-1]["_id"]) < str(page2[0]["_id"])
            assert [d["codigo_postulacion"] for d in page2] == [d["codigo_postulacion"] for d in docs_a[10:20]]

            w07_checks["coincidencia_exacta_semana7"] = True
            w07_checks["expected_n_a"] = expected_n_a
            w07_checks["expected_n_b"] = expected_n_b

        stage_a = _extract_winning_stage(exp_a.get("queryPlanner", {}).get("winningPlan"))
        stage_b = _extract_winning_stage(exp_b.get("queryPlanner", {}).get("winningPlan"))
        stage_p2 = _extract_winning_stage(exp_p2.get("queryPlanner", {}).get("winningPlan"))
        assert stage_a == "IXSCAN", f"Consulta A debía usar IXSCAN, usó {stage_a}"
        assert stage_b == "IXSCAN", f"Consulta B debía usar IXSCAN, usó {stage_b}"
        assert stage_p2 == "IXSCAN", f"Paginación por token debía usar IXSCAN, usó {stage_p2}"

        return {
            "consulta_a": {
                "n_returned": len(docs_a),
                "etapa_plan": stage_a,
                "docs_examined": exp_a["executionStats"]["totalDocsExamined"],
                "keys_examined": exp_a["executionStats"]["totalKeysExamined"],
                "sha256_pymongo": _canonical_sha256_docs(docs_a),
            },
            "consulta_b": {
                "n_returned": len(docs_b),
                "etapa_plan": stage_b,
                "docs_examined": exp_b["executionStats"]["totalDocsExamined"],
                "keys_examined": exp_b["executionStats"]["totalKeysExamined"],
                "sha256_pymongo": _canonical_sha256_docs(docs_b),
            },
            "paginacion_token_compuesto": {
                "pagina_1_tamano": len(page1),
                "ultimo_pagina_1": page1[-1]["codigo_postulacion"],
                "ultimo_pagina_1_fecha": page1[-1]["fecha_agenda"].isoformat(),
                "pagina_2_tamano": len(page2),
                "primero_pagina_2": page2[0]["codigo_postulacion"],
                "primero_pagina_2_fecha": page2[0]["fecha_agenda"].isoformat(),
                "etapa_plan_p2": stage_p2,
                "docs_examined_p2": exp_p2["executionStats"]["totalDocsExamined"],
                "keys_examined_p2": exp_p2["executionStats"]["totalKeysExamined"],
                "filas_perdidas_en_empate": 0,
            },
            "verificacion_semana7": w07_checks,
        }

    # -------------------------------------------------------------------------
    # CA-ISS-16-03: ATOMICIDAD A NIVEL DE DOCUMENTO (FILTRO CON ESTADO ESPERADO)
    # -------------------------------------------------------------------------
    def reserve_slot_atomic(self, request: ReserveSlotRequest) -> dict[str, Any]:
        """
        Reserva atómica de un único documento usando el estado esperado (`status: 'disponible'`)
        dentro del predicado de `update_one`. Ante peticiones simultáneas, exactamente una
        obtiene `modified_count == 1` y las demás se rechazan con `SlotUnavailableError`.
        """
        req = request.validated()
        now = datetime.now(timezone.utc)
        modified = self._repo.reserve_slot_conditional(
            slot_id=req.slot_id,
            application_id=req.application_id,
            candidate_id=req.candidate_id,
            interview_id=f"ATOMIC-{req.id_operacion}",
            now=now,
        )
        if modified != 1:
            raise SlotUnavailableError(
                f"La franja '{req.slot_id}' no está disponible (modified_count={modified})."
            )
        return {
            "mode": "atomic_single_document",
            "modified_count": modified,
            "slot_id": req.slot_id,
            "application_id": req.application_id,
            "candidate_id": req.candidate_id,
            "status": "reservado",
            "updated_at": now.isoformat(),
        }

    # -------------------------------------------------------------------------
    # CA-ISS-16-04 & CA-ISS-16-05: TRANSACCIÓN ACID MULTI-DOCUMENTO E IDEMPOTENCIA
    # -------------------------------------------------------------------------
    def _resolve_existing_operation(
        self,
        id_operacion: str,
        expected_type: str,
        payload_hash: str,
        session: ClientSession | None = None,
    ) -> dict[str, Any] | None:
        existing = self._repo.find_operation(id_operacion, session=session)
        if existing is None:
            return None
        if existing.get("tipo_operacion") != expected_type or existing.get("payload_hash") != payload_hash:
            raise IdempotencyConflictError(
                f"Conflicto de clave de idempotencia: '{id_operacion}' ya fue registrada con "
                f"parámetros o tipo diferentes (tipo existente='{existing.get('tipo_operacion')}')."
            )
        replay = dict(existing["respuesta"])
        replay["idempotent_replay"] = True
        return replay

    def _commit_with_retry(self, session: ClientSession) -> None:
        for attempt in range(MAX_TX_RETRIES):
            try:
                session.commit_transaction()
                return
            except PyMongoError as exc:
                if exc.has_error_label("UnknownTransactionCommitResult") and attempt < MAX_TX_RETRIES - 1:
                    time.sleep(0.015 * (attempt + 1))
                    continue
                raise

    def reserve_slot_transactional(
        self,
        request: ReserveSlotRequest,
        *,
        simulate_failure_after_step: int | None = None,
    ) -> dict[str, Any]:
        """
        Reserva coordinada mediante transacción multi-documento ACID sobre Replica Set:
          1. Marca la franja como `reservado` (predicado atómico `status: 'disponible'`).
          2. Inserta el documento de entrevista en `entrevistas` enlazado a la postulación.
          3. Registra la auditoría/idempotencia con `id_operacion` en `operaciones`.

        Si se reintenta con el mismo `id_operacion` y los mismos parámetros, retorna la respuesta
        histórica original sin duplicar registros. Si cambian los parámetros, lanza `IdempotencyConflictError`.
        """
        req = request.validated()
        if simulate_failure_after_step not in (None, 1, 2):
            raise ValidationError("simulate_failure_after_step solo admite None, 1 o 2.")

        canonical_params = req.canonical_params()
        payload_hash = compute_payload_hash("reserva", canonical_params)

        # Lectura rápida previa para reintentos idempotentes o conflicto inmediato
        fast_replay = self._resolve_existing_operation(req.id_operacion, "reserva", payload_hash)
        if fast_replay is not None:
            return fast_replay

        for attempt in range(MAX_TX_RETRIES):
            try:
                with self._client.start_session() as session:
                    session.start_transaction(
                        read_concern=ReadConcern("snapshot"),
                        write_concern=WriteConcern("majority"),
                        read_preference=ReadPreference.PRIMARY,
                    )
                    try:
                        # Verificación de idempotencia dentro de la sesión snapshot
                        tx_replay = self._resolve_existing_operation(
                            req.id_operacion, "reserva", payload_hash, session=session
                        )
                        if tx_replay is not None:
                            session.abort_transaction()
                            return tx_replay

                        now = datetime.now(timezone.utc)
                        interview_id = f"INT-{uuid.uuid4().hex[:10].upper()}"

                        # Operación 1: Reservar cupo condicionalmente (status == 'disponible')
                        modified = self._repo.reserve_slot_conditional(
                            slot_id=req.slot_id,
                            application_id=req.application_id,
                            candidate_id=req.candidate_id,
                            interview_id=interview_id,
                            now=now,
                            session=session,
                        )
                        if modified != 1:
                            # Si otro hilo concurrente acaba de confirmar exactamente el mismo id_operacion,
                            # devolvemos su respuesta idempotente tras cerrar esta sesión snapshot.
                            session.abort_transaction()
                            replay = self._resolve_existing_operation(req.id_operacion, "reserva", payload_hash)
                            if replay is not None:
                                return replay
                            raise SlotUnavailableError(
                                f"La franja '{req.slot_id}' ya no está disponible para reserva."
                            )

                        if simulate_failure_after_step == 1:
                            raise SimulatedTransactionFailureError(
                                "Fallo controlado inducido tras Operación 1 (cupo marcado en sesión; debe revertirse)."
                            )

                        # Operación 2: Crear documento de entrevista enlazado a la postulación
                        interview_doc = {
                            "interview_id": interview_id,
                            "slot_id": req.slot_id,
                            "application_id": req.application_id,
                            "candidate_id": req.candidate_id,
                            "vacancy_id": req.vacancy_id,
                            "interviewer_name": req.interviewer_name,
                            "status": "confirmada",
                            "id_operacion_reserva": req.id_operacion,
                            "id_operacion_cancelacion": None,
                            "cancel_reason": None,
                            "historial_estados": [
                                {
                                    "estado": "confirmada",
                                    "motivo": "Reserva transaccional confirmada",
                                    "id_operacion": req.id_operacion,
                                    "fecha": now,
                                }
                            ],
                            "created_at": now,
                            "updated_at": now,
                        }
                        self._repo.insert_interview(interview_doc, session=session)

                        if simulate_failure_after_step == 2:
                            raise SimulatedTransactionFailureError(
                                "Fallo controlado inducido tras Operación 2 (entrevista insertada en sesión; debe revertirse)."
                            )

                        response_payload = {
                            "operation_type": "reserva",
                            "id_operacion": req.id_operacion,
                            "interview_id": interview_id,
                            "slot_id": req.slot_id,
                            "application_id": req.application_id,
                            "candidate_id": req.candidate_id,
                            "vacancy_id": req.vacancy_id,
                            "slot_status": "reservado",
                            "interview_status": "confirmada",
                            "created_at": now.isoformat(),
                            "idempotent_replay": False,
                        }

                        # Operación 3: Registrar auditoría e idempotencia en `operaciones`
                        op_doc = {
                            "id_operacion": req.id_operacion,
                            "tipo_operacion": "reserva",
                            "payload_hash": payload_hash,
                            "parametros": canonical_params,
                            "estado": "completada",
                            "respuesta": response_payload,
                            "created_at": now,
                        }
                        self._repo.insert_operation(op_doc, session=session)

                        self._commit_with_retry(session)
                        return response_payload

                    except DuplicateKeyError as dup_exc:
                        if session.in_transaction:
                            session.abort_transaction()
                        # Puede ocurrir por reintento concurrente con el mismo id_operacion
                        replay = self._resolve_existing_operation(req.id_operacion, "reserva", payload_hash)
                        if replay is not None:
                            return replay
                        raise SlotUnavailableError(
                            f"Colisión de unicidad en la franja '{req.slot_id}': {dup_exc}"
                        ) from dup_exc
                    except PyMongoError as mongo_exc:
                        if session.in_transaction:
                            session.abort_transaction()
                        if mongo_exc.has_error_label("TransientTransactionError") and attempt < MAX_TX_RETRIES - 1:
                            # Antes de reintentar, verificar si un hilo paralelo ya completó el mismo id_operacion
                            replay = self._resolve_existing_operation(req.id_operacion, "reserva", payload_hash)
                            if replay is not None:
                                return replay
                            time.sleep(random.uniform(0.008, 0.025) * (attempt + 1))
                            continue
                        raise
                    except Exception:
                        if session.in_transaction:
                            session.abort_transaction()
                        raise
            except PyMongoError as outer_exc:
                if outer_exc.has_error_label("TransientTransactionError") and attempt < MAX_TX_RETRIES - 1:
                    replay = self._resolve_existing_operation(req.id_operacion, "reserva", payload_hash)
                    if replay is not None:
                        return replay
                    time.sleep(random.uniform(0.008, 0.025) * (attempt + 1))
                    continue
                raise

        final_replay = self._resolve_existing_operation(req.id_operacion, "reserva", payload_hash)
        if final_replay is not None:
            return final_replay
        raise SlotUnavailableError(
            f"No se pudo completar la transacción para '{req.slot_id}' tras {MAX_TX_RETRIES} reintentos por contención."
        )

    # -------------------------------------------------------------------------
    # CA-ISS-16-06: FLUJO DE CANCELACIÓN ATÓMICA AUDITADA E IDEMPOTENTE
    # -------------------------------------------------------------------------
    def cancel_interview_transactional(
        self,
        request: CancelInterviewRequest,
        *,
        simulate_failure_after_step: int | None = None,
    ) -> dict[str, Any]:
        """
        Cancela una entrevista bajo transacción multi-documento ACID:
          1. Actualiza el estado de la entrevista a `cancelada` añadiendo traza al historial.
          2. Libera la franja asociada en `franjas_entrevista` regresándola a `disponible`.
          3. Registra la auditoría de cancelación con `id_operacion` en `operaciones`.
        """
        req = request.validated()
        if simulate_failure_after_step not in (None, 1, 2):
            raise ValidationError("simulate_failure_after_step solo admite None, 1 o 2.")

        canonical_params = req.canonical_params()
        payload_hash = compute_payload_hash("cancelacion", canonical_params)

        fast_replay = self._resolve_existing_operation(req.id_operacion, "cancelacion", payload_hash)
        if fast_replay is not None:
            return fast_replay

        for attempt in range(MAX_TX_RETRIES):
            try:
                with self._client.start_session() as session:
                    session.start_transaction(
                        read_concern=ReadConcern("snapshot"),
                        write_concern=WriteConcern("majority"),
                        read_preference=ReadPreference.PRIMARY,
                    )
                    try:
                        tx_replay = self._resolve_existing_operation(
                            req.id_operacion, "cancelacion", payload_hash, session=session
                        )
                        if tx_replay is not None:
                            session.abort_transaction()
                            return tx_replay

                        interview = self._repo.get_interview(req.interview_id, session=session)
                        if interview is None:
                            raise InterviewNotFoundError(f"No existe la entrevista '{req.interview_id}'.")
                        if interview.get("status") != "confirmada":
                            raise InvalidStateTransitionError(
                                f"La entrevista '{req.interview_id}' está en estado '{interview.get('status')}' y no puede cancelarse."
                            )

                        now = datetime.now(timezone.utc)
                        slot_id = interview["slot_id"]

                        # Operación 1: Marcar entrevista como cancelada y anexar al historial
                        mod_int = self._repo.cancel_interview_conditional(
                            interview_id=req.interview_id,
                            id_operacion=req.id_operacion,
                            reason=req.reason,
                            now=now,
                            session=session,
                        )
                        if mod_int != 1:
                            raise InvalidStateTransitionError(
                                f"La entrevista '{req.interview_id}' cambió de estado concurrentemente."
                            )

                        if simulate_failure_after_step == 1:
                            raise SimulatedTransactionFailureError(
                                "Fallo controlado inducido tras Operación 1 de cancelación (debe revertirse)."
                            )

                        # Operación 2: Liberar cupo a 'disponible'
                        mod_slot = self._repo.release_slot_conditional(
                            slot_id=slot_id,
                            interview_id=req.interview_id,
                            now=now,
                            session=session,
                        )
                        if mod_slot != 1:
                            raise InvalidStateTransitionError(
                                f"La franja '{slot_id}' no coincide con la entrevista '{req.interview_id}'."
                            )

                        if simulate_failure_after_step == 2:
                            raise SimulatedTransactionFailureError(
                                "Fallo controlado inducido tras Operación 2 de cancelación (debe revertirse)."
                            )

                        response_payload = {
                            "operation_type": "cancelacion",
                            "id_operacion": req.id_operacion,
                            "interview_id": req.interview_id,
                            "slot_id": slot_id,
                            "application_id": interview["application_id"],
                            "candidate_id": interview["candidate_id"],
                            "slot_status": "disponible",
                            "interview_status": "cancelada",
                            "reason": req.reason,
                            "cancelled_at": now.isoformat(),
                            "idempotent_replay": False,
                        }

                        # Operación 3: Auditoría e idempotencia en `operaciones`
                        op_doc = {
                            "id_operacion": req.id_operacion,
                            "tipo_operacion": "cancelacion",
                            "payload_hash": payload_hash,
                            "parametros": canonical_params,
                            "estado": "completada",
                            "respuesta": response_payload,
                            "created_at": now,
                        }
                        self._repo.insert_operation(op_doc, session=session)

                        self._commit_with_retry(session)
                        return response_payload

                    except DuplicateKeyError:
                        if session.in_transaction:
                            session.abort_transaction()
                        replay = self._resolve_existing_operation(req.id_operacion, "cancelacion", payload_hash)
                        if replay is not None:
                            return replay
                        raise
                    except PyMongoError as mongo_exc:
                        if session.in_transaction:
                            session.abort_transaction()
                        if mongo_exc.has_error_label("TransientTransactionError") and attempt < MAX_TX_RETRIES - 1:
                            replay = self._resolve_existing_operation(req.id_operacion, "cancelacion", payload_hash)
                            if replay is not None:
                                return replay
                            time.sleep(random.uniform(0.008, 0.025) * (attempt + 1))
                            continue
                        raise
                    except Exception:
                        if session.in_transaction:
                            session.abort_transaction()
                        raise
            except PyMongoError as outer_exc:
                if outer_exc.has_error_label("TransientTransactionError") and attempt < MAX_TX_RETRIES - 1:
                    replay = self._resolve_existing_operation(req.id_operacion, "cancelacion", payload_hash)
                    if replay is not None:
                        return replay
                    time.sleep(random.uniform(0.008, 0.025) * (attempt + 1))
                    continue
                raise

        final_replay = self._resolve_existing_operation(req.id_operacion, "cancelacion", payload_hash)
        if final_replay is not None:
            return final_replay
        raise InvalidStateTransitionError(
            f"No se pudo completar la cancelación de '{req.interview_id}' tras {MAX_TX_RETRIES} reintentos."
        )

    # --- Helpers de serialización limpia para CLI y auditoría ---
    @staticmethod
    def _serialize_slot(doc: dict[str, Any]) -> dict[str, Any]:
        return {
            "slot_id": doc["slot_id"],
            "vacancy_id": doc["vacancy_id"],
            "company_name": doc["company_name"],
            "starts_at": doc["starts_at"].isoformat() if isinstance(doc["starts_at"], datetime) else str(doc["starts_at"]),
            "ends_at": doc["ends_at"].isoformat() if isinstance(doc["ends_at"], datetime) else str(doc["ends_at"]),
            "modality": doc["modality"],
            "status": doc["status"],
            "interview_id": doc.get("interview_id"),
            "application_id": doc.get("application_id"),
            "candidate_id": doc.get("candidate_id"),
            "version": int(doc.get("version", 1)),
        }

    def list_slots(self, status: str | None = None) -> list[dict[str, Any]]:
        if status is not None and status not in ESTADOS_FRANJA:
            raise ValidationError(f"Estado de franja inválido '{status}'. Válidos: {sorted(ESTADOS_FRANJA)}.")
        return [self._serialize_slot(s) for s in self._repo.list_slots(status=status)]

    def inspect_audit(self, limit: int = 20) -> list[dict[str, Any]]:
        ops = self._repo.list_operations(limit=limit)
        out = []
        for op in ops:
            out.append(
                {
                    "id_operacion": op["id_operacion"],
                    "tipo_operacion": op["tipo_operacion"],
                    "payload_hash": op["payload_hash"],
                    "estado": op["estado"],
                    "parametros": op["parametros"],
                    "created_at": op["created_at"].isoformat() if isinstance(op["created_at"], datetime) else str(op["created_at"]),
                }
            )
        return out


# =============================================================================
# CAPA 6: INTERFAZ CLI DESACOPLADA
# =============================================================================
def build_cli_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        prog="interview_scheduling_service",
        description="CLI del servicio PyMongo de agendamiento de entrevistas YEB (ACID + Idempotencia).",
    )
    parser.add_argument("--db", default=None, help="Sobrescribe YEB_MONGO_CONCURRENCY_DB (default desde .env).")
    sub = parser.add_subparsers(dest="command", required=True)

    sub.add_parser("init-demo", help="Verifica Replica Set, crea índices y siembra 3 franjas de entrevista demo.")

    p_list = sub.add_parser("list-slots", help="Lista franjas de entrevista.")
    p_list.add_argument("--status", choices=sorted(ESTADOS_FRANJA), default=None)

    p_atom = sub.add_parser("reserve-atomic", help="Reserva atómica a nivel de documento (status='disponible').")
    p_atom.add_argument("--slot-id", required=True)
    p_atom.add_argument("--application-id", required=True)
    p_atom.add_argument("--candidate-id", required=True)
    p_atom.add_argument("--vacancy-id", required=True)
    p_atom.add_argument("--id-operacion", required=True)

    p_tx = sub.add_parser("reserve-tx", help="Reserva coordinada en transacción ACID multi-documento con idempotencia.")
    p_tx.add_argument("--slot-id", required=True)
    p_tx.add_argument("--application-id", required=True)
    p_tx.add_argument("--candidate-id", required=True)
    p_tx.add_argument("--vacancy-id", required=True)
    p_tx.add_argument("--id-operacion", required=True)
    p_tx.add_argument("--interviewer", default="Equipo Reclutamiento YEB")

    p_can = sub.add_parser("cancel-tx", help="Cancelación atómica en transacción ACID liberando el cupo.")
    p_can.add_argument("--interview-id", required=True)
    p_can.add_argument("--id-operacion", required=True)
    p_can.add_argument("--reason", default="Cancelación solicitada vía CLI YEB")

    sub.add_parser("regression-w7", help="Ejecuta la prueba de regresión de lecturas Semana 7 en PyMongo.")

    p_aud = sub.add_parser("inspect-audit", help="Inspecciona la bitácora de idempotencia `operaciones`.")
    p_aud.add_argument("--limit", type=int, default=20)

    return parser


def main(argv: list[str] | None = None) -> int:
    parser = build_cli_parser()
    args = parser.parse_args(argv)

    try:
        settings = MongoSettings.from_env(database_override=args.db)
        if args.command in ("init-demo", "reserve-tx", "cancel-tx", "regression-w7"):
            ensure_replica_set_ready(settings)
        client = build_mongo_client(settings)
        try:
            repo = InterviewSchedulingRepository(client[settings.database])
            service = InterviewSchedulingService(client, repo)

            if args.command == "init-demo":
                info = service.initialize(ensure_w07_dataset=True)
                demo_slots = [
                    CreateSlotRequest(
                        slot_id="SLOT-YEB-2026-01",
                        vacancy_id="JOB-2026-001",
                        company_name="TalentoLab Analítica S.A.S.",
                        starts_at=datetime(2026, 11, 30, 14, 0, tzinfo=timezone.utc),
                        ends_at=datetime(2026, 11, 30, 14, 45, tzinfo=timezone.utc),
                        modality="Híbrido",
                    ),
                    CreateSlotRequest(
                        slot_id="SLOT-YEB-2026-02",
                        vacancy_id="JOB-2026-002",
                        company_name="ConectaJoven Digital S.A.S.",
                        starts_at=datetime(2026, 11, 30, 15, 0, tzinfo=timezone.utc),
                        ends_at=datetime(2026, 11, 30, 15, 45, tzinfo=timezone.utc),
                        modality="Remoto",
                    ),
                    CreateSlotRequest(
                        slot_id="SLOT-YEB-2026-03",
                        vacancy_id="JOB-2026-003",
                        company_name="Pacífico Software Studio",
                        starts_at=datetime(2026, 11, 30, 16, 0, tzinfo=timezone.utc),
                        ends_at=datetime(2026, 11, 30, 16, 45, tzinfo=timezone.utc),
                        modality="Presencial",
                    ),
                ]
                created = [service.create_slot(s) for s in demo_slots]
                output: Any = {"status": "ok", "database": settings.database, "init": info, "slots": created}
            elif args.command == "list-slots":
                output = {"status": "ok", "slots": service.list_slots(status=args.status)}
            elif args.command == "reserve-atomic":
                req = ReserveSlotRequest(
                    slot_id=args.slot_id,
                    application_id=args.application_id,
                    candidate_id=args.candidate_id,
                    vacancy_id=args.vacancy_id,
                    id_operacion=args.id_operacion,
                )
                output = {"status": "ok", "result": service.reserve_slot_atomic(req)}
            elif args.command == "reserve-tx":
                service.initialize(ensure_w07_dataset=False)
                req = ReserveSlotRequest(
                    slot_id=args.slot_id,
                    application_id=args.application_id,
                    candidate_id=args.candidate_id,
                    vacancy_id=args.vacancy_id,
                    id_operacion=args.id_operacion,
                    interviewer_name=args.interviewer,
                )
                output = {"status": "ok", "result": service.reserve_slot_transactional(req)}
            elif args.command == "cancel-tx":
                service.initialize(ensure_w07_dataset=False)
                req = CancelInterviewRequest(
                    interview_id=args.interview_id,
                    id_operacion=args.id_operacion,
                    reason=args.reason,
                )
                output = {"status": "ok", "result": service.cancel_interview_transactional(req)}
            elif args.command == "regression-w7":
                output = {"status": "ok", "regression": service.run_week7_read_regression()}
            elif args.command == "inspect-audit":
                output = {"status": "ok", "operations": service.inspect_audit(limit=args.limit)}
            else:
                raise ValidationError(f"Comando no soportado: {args.command}")

            print(json.dumps(output, indent=2, ensure_ascii=False))
            return 0
        finally:
            client.close()
    except SchedulingServiceError as exc:
        print(json.dumps({"status": "error", "error_type": type(exc).__name__, "message": str(exc)}, ensure_ascii=False))
        return 2
    except PyMongoError as exc:
        print(json.dumps({"status": "error", "error_type": type(exc).__name__, "message": str(exc)}, ensure_ascii=False))
        return 3


if __name__ == "__main__":
    raise SystemExit(main())
