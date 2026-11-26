#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Issue #15 [Data/Integration]: Integración relacional, control de duplicación en joins,
reestructuración tabular (melt/pivot) y enlace contextual con Datos Abiertos (datos.gov.co)
para Youth Employment Bridge (YEB).

Criterios de Aceptación cubiertos:
  - CA-ISS-15-01: Recuperación de la línea base limpia de Semanas 5–7 (n=93 postulaciones aptas)
                  garantizando inmutabilidad del archivo origen (SHA-256) y preservación exacta
                  del universo con assert tras todos los cruces.
  - CA-ISS-15-02: Integración con el catálogo maestro de vacantes/empresas mediante merge(how="left",
                  validate="many_to_one", indicator=True), prueba negativa de rechazo de duplicados
                  (MergeError) y auditoría de vacantes huérfanas/sin postulantes activos.
  - CA-ISS-15-03: Prevención de multiplicación de KPI ante eventos 1:N (chat, revisiones de CV):
                  demostración controlada del sesgo por join ingenuo y solución correcta agregando
                  primero con groupby(["application_id"]).agg(...) + validación assert de KPIs S6/S7.
  - CA-ISS-15-04: Concatenación longitudinal con concat() de dos cohortes semestrales (2026-A y 2026-B)
                  de esquema compatible, verificando con assert 0 duplicados en la llave compuesta
                  (candidate_hash + job_id + periodo).
  - CA-ISS-15-05: Reestructuración bidireccional ancho <-> largo con melt() y pivot() sobre puntajes
                  de evaluación (score_habilidades, score_experiencia, score_disponibilidad),
                  demostrando reversibilidad matemática exacta con assert.
  - CA-ISS-15-06: Consulta de Datos Abiertos de Colombia (datos.gov.co, SENA 28vu-5tx7), comparación
                  territorial macro sin mezclar granularidades y verificación de docs/metadatos_fuentes_yeb.md.

Riesgos mitigados: NEG-012, TEC-019
Casos de prueba verificados: CP-055, CP-055B, CP-055C
"""

from __future__ import annotations

import hashlib
import os
import subprocess
import sys
from pathlib import Path


def _ensure_venv_interpreter() -> None:
    """Re-ejecuta automáticamente con el Python de analytics/.venv si se invoca desde el Python global."""
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

import numpy as np
import pandas as pd
import requests

# Constantes de referencia de Semanas 5–7
FECHA_CORTE_2026A = "2026-02-02"
DATOS_GOV_DATASET_ID = "28vu-5tx7"
DATOS_GOV_RESOURCE_URL = f"https://www.datos.gov.co/resource/{DATOS_GOV_DATASET_ID}.json"
DATOS_GOV_CATALOG_URL = f"https://www.datos.gov.co/d/{DATOS_GOV_DATASET_ID}"

MAPA_DEP_YEB_A_SENA = {
    "Bogotá D.C.": "BOGOTA D.C.",
    "Antioquia": "ANTIOQUIA",
    "Valle del Cauca": "VALLE DEL CAUCA",
    "Atlántico": "ATLANTICO",
    "Santander": "SANTANDER",
    "Risaralda": "RISARALDA",
}

CATALOGO_VACANTES_SPECS = [
    ("JOB-2026-001", "EMP-YEB-01", "TalentoLab Analítica S.A.S.", "Mediana", True, "NIT-ANON-900101", "Medellín", "Antioquia", "Tecnología", True, 3, "published", "2026-01-02"),
    ("JOB-2026-002", "EMP-YEB-02", "ConectaJoven Digital S.A.S.", "Mediana", True, "NIT-ANON-900102", "Bogotá", "Bogotá D.C.", "BPO y Contact Center", True, 4, "published", "2026-01-02"),
    ("JOB-2026-003", "EMP-YEB-03", "Pacífico Software Studio", "Pequeña", True, "NIT-ANON-900103", "Cali", "Valle del Cauca", "Tecnología", True, 3, "published", "2026-01-03"),
    ("JOB-2026-004", "EMP-YEB-04", "Logística Caribe Express", "Grande", True, "NIT-ANON-900104", "Barranquilla", "Atlántico", "Logística", True, 4, "published", "2026-01-03"),
    ("JOB-2026-005", "EMP-YEB-05", "Santander Innovación Digital", "Pequeña", True, "NIT-ANON-900105", "Bucaramanga", "Santander", "Servicios Financieros", True, 2, "published", "2026-01-04"),
    ("JOB-2026-006", "EMP-YEB-06", "Eje Cafetero Tech Labs", "Pequeña", True, "NIT-ANON-900106", "Pereira", "Risaralda", "Tecnología", True, 2, "published", "2026-01-04"),
    ("JOB-2026-007", "EMP-YEB-07", "Verde Digital Antioquia", "Mediana", True, "NIT-ANON-900107", "Medellín", "Antioquia", "Comercio", True, 3, "published", "2026-01-02"),
    ("JOB-2026-008", "EMP-YEB-08", "Grupo Finanzas Andinas", "Grande", True, "NIT-ANON-900108", "Bogotá", "Bogotá D.C.", "Servicios Financieros", False, 3, "published", "2026-01-03"),
    ("JOB-2026-009", "EMP-YEB-03", "Pacífico Software Studio", "Pequeña", True, "NIT-ANON-900103", "Cali", "Valle del Cauca", "Tecnología", False, 2, "published", "2026-01-02"),
    ("JOB-2026-010", "EMP-YEB-04", "Logística Caribe Express", "Grande", True, "NIT-ANON-900104", "Barranquilla", "Atlántico", "Comercio", True, 3, "published", "2026-01-02"),
    ("JOB-2026-011", "EMP-YEB-09", "SaludVital IPS Oriente", "Mediana", True, "NIT-ANON-900109", "Bucaramanga", "Santander", "BPO y Contact Center", True, 3, "published", "2026-01-03"),
    ("JOB-2026-012", "EMP-YEB-06", "Eje Cafetero Tech Labs", "Pequeña", True, "NIT-ANON-900106", "Pereira", "Risaralda", "Logística", False, 3, "published", "2026-01-04"),
    ("JOB-2026-013", "EMP-YEB-01", "TalentoLab Analítica S.A.S.", "Mediana", True, "NIT-ANON-900101", "Medellín", "Antioquia", "Tecnología", True, 2, "published", "2026-01-02"),
    ("JOB-2026-014", "EMP-YEB-08", "Grupo Finanzas Andinas", "Grande", True, "NIT-ANON-900108", "Bogotá", "Bogotá D.C.", "Servicios Financieros", True, 3, "published", "2026-01-03"),
    ("JOB-2026-015", "EMP-YEB-10", "Valle Conecta BPO", "Grande", True, "NIT-ANON-900110", "Cali", "Valle del Cauca", "BPO y Contact Center", True, 3, "published", "2026-01-04"),
    ("JOB-2026-016", "EMP-YEB-11", "Puerto Digital Atlántico", "Mediana", True, "NIT-ANON-900111", "Barranquilla", "Atlántico", "Tecnología", True, 2, "published", "2026-01-03"),
    ("JOB-2026-017", "EMP-YEB-05", "Santander Innovación Digital", "Pequeña", True, "NIT-ANON-900105", "Bucaramanga", "Santander", "Tecnología", False, 2, "published", "2026-01-07"),
    ("JOB-2026-018", "EMP-YEB-12", "Risaralda Comercio Joven", "Mediana", True, "NIT-ANON-900112", "Pereira", "Risaralda", "Comercio", True, 3, "published", "2026-01-05"),
    # 3 vacantes sin postulantes activos en 2026-A (detectables en auditoría de cobertura con indicator=True)
    ("JOB-2026-019", "EMP-YEB-07", "Verde Digital Antioquia", "Mediana", True, "NIT-ANON-900107", "Medellín", "Antioquia", "Tecnología", True, 2, "published", "2026-01-25"),
    ("JOB-2026-020", "EMP-YEB-02", "ConectaJoven Digital S.A.S.", "Mediana", True, "NIT-ANON-900102", "Bogotá", "Bogotá D.C.", "BPO y Contact Center", True, 5, "published", "2026-01-27"),
    ("JOB-2026-021", "EMP-YEB-10", "Valle Conecta BPO", "Grande", True, "NIT-ANON-900110", "Cali", "Valle del Cauca", "Servicios Financieros", False, 1, "draft", "2026-01-29"),
]

CONCLUSION_DATOS_ABIERTOS = (
    "Conclusión técnica sobre el enlace contextual con Datos Abiertos (SENA 28vu-5tx7, datos.gov.co): "
    "El conjunto público de Certificación de la Formación Profesional Integral del SENA (periodo 202402, "
    "8.538 fichas agregadas y 61.420 aprendices certificados a nivel nacional, de los cuales 7.014 pertenecen "
    "a la categoría específica de población joven focalizada) confirma que los seis departamentos atendidos "
    "por YEB (Bogotá D.C., Antioquia, Valle del Cauca, Atlántico, Santander y Risaralda) concentran más de la "
    "mitad de la oferta certificada nacional (31.195 aprendices, 50,79%), con fuerte predominio de formación "
    "presencial (93,29%). Sin embargo, sus cifras macro agregadas por ficha NO deben cruzarse fila a fila ni "
    "sumarse con los registros individuales del portal YEB (n=93 postulaciones en cohorte 2026-A): difieren en "
    "unidad de observación (ficha agregada vs. postulación individual), ventana temporal (2024 vs. 2026), "
    "definición operativa de juventud (marca poblacional vulnerable del SENA vs. rango etario 16–24 años en YEB) "
    "y naturaleza de la modalidad (formación académica vs. modalidad laboral). Mezclarlos introduciría falacia "
    "ecológica y distorsión de denominadores; por ello el conjunto público se emplea exclusivamente como "
    "referencia macro territorial separada para contextualizar la cobertura potencial del portal."
)


def resolve_analytics_root() -> Path:
    analytics_root = Path(__file__).resolve().parent.parent
    for sub in ("data/processed", "data/raw", "outputs"):
        assert (analytics_root / sub).exists(), f"No se encontró analytics/{sub}/"
    return analytics_root


def sha256_archivo(ruta: Path) -> str:
    return hashlib.sha256(ruta.read_bytes()).hexdigest()


def cargar_linea_base_semana7(analytics_root: Path) -> tuple[pd.DataFrame, pd.DataFrame, Path, str]:
    """CA-ISS-15-01: Recupera la base limpia de Semanas 5–7 sin modificarla y valida sus invariantes."""
    ruta_fuente = analytics_root / "data" / "processed" / "yeb_postulaciones_transformado.csv"
    assert ruta_fuente.exists(), f"Falta la base procesada: {ruta_fuente}"
    huella = sha256_archivo(ruta_fuente)

    df_total = pd.read_csv(ruta_fuente, parse_dates=["job_published_at", "applied_at", "updated_at"])
    for col in ("is_protected_minor", "minor_permit_verified"):
        df_total[col] = df_total[col].astype(str).str.strip().str.lower().eq("true")

    df_u = df_total.loc[df_total["origen_registro"] == "dataset_preparado"].copy().reset_index(drop=True)

    # Aserciones de línea base (Semanas 6 y 7)
    assert len(df_total) == 97, f"Total recibido inesperado: {len(df_total)}"
    assert len(df_u) == 93, f"Universo analizable inesperado: {len(df_u)}"
    assert df_u["application_id"].nunique() == 93, "Llave application_id debe ser única en el universo"
    assert df_u["candidate_hash"].nunique() == 93, "Candidatos únicos esperados: 93"
    assert df_u["job_id"].nunique() == 18, "Vacantes activas esperadas en base: 18"
    assert str(df_u["updated_at"].max().date()) == FECHA_CORTE_2026A
    return df_total, df_u, ruta_fuente, huella


def calcular_firma_kpis_referencia(df: pd.DataFrame) -> dict[str, object]:
    """Calcula la firma exacta de KPIs de Semanas 6 y 7 para garantizar no distorsión tras los joins."""
    return {
        "n_filas": int(len(df)),
        "n_application_id": int(df["application_id"].nunique()),
        "n_candidatos": int(df["candidate_hash"].nunique()),
        "n_vacantes": int(df["job_id"].nunique()),
        "conteo_estados": {k: int(v) for k, v in sorted(df["status"].value_counts().items())},
        "conteo_modalidad": {k: int(v) for k, v in sorted(df["modality"].value_counts().items())},
        "conteo_ciudad": {k: int(v) for k, v in sorted(df["ciudad_normalizada"].value_counts().items())},
        "n_respondidas": int(df["tiempo_respuesta_dias"].count()),
        "tiempo_resp_media": round(float(df["tiempo_respuesta_dias"].mean()), 2),
        "tiempo_resp_mediana": round(float(df["tiempo_respuesta_dias"].median()), 2),
        "salario_esp_media": round(float(df["expected_salary_cop"].mean()), 2),
        "brecha_salarial_media_pct": round(float(df["brecha_salarial_aspiracion_pct"].mean()), 2),
        "tasa_entrevista_pct": round(100.0 * float(df["status"].isin(["interview", "accepted"]).mean()), 2),
        "tasa_contratacion_pct": round(100.0 * float((df["status"] == "accepted").mean()), 2),
    }


def generar_o_cargar_catalogo_vacantes(analytics_root: Path, force_regen: bool = False) -> tuple[pd.DataFrame, Path]:
    """Construye/lee el catálogo maestro determinista de vacantes y empresas (21 vacantes: 18 activas + 3 sin postulantes)."""
    ruta = analytics_root / "data" / "raw" / "yeb_catalogo_vacantes_empresas.csv"
    if force_regen or not ruta.exists():
        cols = [
            "job_id",
            "company_id",
            "company_name",
            "company_size",
            "company_verified",
            "nit_anon",
            "ciudad_sede",
            "departamento_sede",
            "sector_empresa",
            "admite_menores_autorizados",
            "cupos_vacante",
            "estado_vacante_catalogo",
            "fecha_creacion_catalogo",
        ]
        df_cat = pd.DataFrame(CATALOGO_VACANTES_SPECS, columns=cols)
        df_cat.to_csv(ruta, index=False, encoding="utf-8", lineterminator="\n")
    df_cat = pd.read_csv(ruta, parse_dates=["fecha_creacion_catalogo"])
    for col in ("company_verified", "admite_menores_autorizados"):
        df_cat[col] = df_cat[col].astype(str).str.strip().str.lower().eq("true")
    return df_cat, ruta


def generar_o_cargar_eventos_seguimiento(
    analytics_root: Path,
    df_u: pd.DataFrame,
    force_regen: bool = False,
) -> tuple[pd.DataFrame, Path]:
    """Construye/lee un log 1:N realista de eventos de seguimiento (mensajes de chat, revisiones de CV y alertas)."""
    ruta = analytics_root / "data" / "raw" / "yeb_eventos_seguimiento.csv"
    if force_regen or not ruta.exists():
        rng = np.random.default_rng(20260815)
        eventos = []
        event_counter = 1

        # 5 postulaciones en 'submitted' sin eventos aún (para demostrar left join 1:1 con 0 eventos legítimos)
        submitted_ids = df_u.loc[df_u["status"] == "submitted", "application_id"].tolist()
        ids_sin_eventos = set(submitted_ids[-5:])

        for row in df_u.itertuples(index=False):
            if row.application_id in ids_sin_eventos:
                continue

            base_ts = pd.Timestamp(row.applied_at) + pd.Timedelta(hours=2)
            # Cantidad realista de eventos según etapa del embudo
            if row.status == "submitted":
                tipos = ["profile_checked", "cv_viewed"]
            elif row.status == "under_review":
                tipos = ["cv_viewed", "profile_checked", "chat_message_recruiter"]
            elif row.status == "rejected":
                tipos = ["cv_viewed", "profile_checked", "status_notification"]
            elif row.status == "interview":
                tipos = [
                    "cv_viewed",
                    "chat_message_recruiter",
                    "chat_message_candidate",
                    "interview_invite",
                    "chat_message_candidate",
                ]
            else:  # accepted
                tipos = [
                    "cv_viewed",
                    "profile_checked",
                    "chat_message_recruiter",
                    "chat_message_candidate",
                    "interview_invite",
                    "status_notification",
                ]

            # Variación determinista de 0 a 2 mensajes de chat adicionales
            extras = int(rng.integers(0, 3))
            for k in range(extras):
                tipos.append("chat_message_candidate" if k % 2 == 0 else "chat_message_recruiter")

            for step_idx, ev_type in enumerate(tipos):
                actor = (
                    "candidate"
                    if ev_type == "chat_message_candidate"
                    else ("system" if ev_type == "status_notification" else "recruiter")
                )
                channel = "chat_portal" if "chat_" in ev_type else ("portal_web" if ev_type != "status_notification" else "push_web")
                latency = round(float(rng.uniform(12.0, 480.0)), 1)
                ev_ts = base_ts + pd.Timedelta(hours=int(step_idx * 9 + rng.integers(1, 6)))
                eventos.append(
                    {
                        "event_id": f"EVT-2026-{event_counter:04d}",
                        "application_id": row.application_id,
                        "event_type": ev_type,
                        "actor_role": actor,
                        "event_timestamp": ev_ts.strftime("%Y-%m-%d %H:%M:%S"),
                        "response_latency_min": latency,
                        "channel": channel,
                    }
                )
                event_counter += 1

        df_ev = pd.DataFrame(eventos)
        df_ev.to_csv(ruta, index=False, encoding="utf-8", lineterminator="\n")

    df_ev = pd.read_csv(ruta, parse_dates=["event_timestamp"])
    return df_ev, ruta


def generar_o_cargar_cohorte_2026b(
    analytics_root: Path,
    df_u: pd.DataFrame,
    force_regen: bool = False,
) -> tuple[pd.DataFrame, Path]:
    """Genera/carga una segunda cohorte semestral (2026-B, 60 registros) con esquema 100% compatible para concat()."""
    ruta = analytics_root / "data" / "raw" / "yeb_postulaciones_cohorte_2026B.csv"
    if force_regen or not ruta.exists():
        rng = np.random.default_rng(20260820)
        muestra = df_u.sample(n=60, random_state=20260820).reset_index(drop=True).copy()

        # Reasignar llaves de postulación y fechas al segundo semestre 2026-B (julio–agosto 2026)
        muestra["raw_record_id"] = [f"RAW-26B-{i:04d}" for i in range(1, 61)]
        muestra["application_id"] = [f"APP-2026B-{i:04d}" for i in range(1, 61)]
        muestra["audit_batch_id"] = "BATCH-2026-W28"

        # 12 candidatos recurrentes de 2026-A vuelven a postularse en 2026-B (demostrando valor de la llave compuesta con periodo)
        # Los otros 48 son nuevos candidatos en 2026-B
        for idx in range(12, 60):
            muestra.loc[idx, "candidate_hash"] = f"CAND-HASH-26B-{idx + 1:04d}"
            muestra.loc[idx, "documento_hash"] = f"DOC-CC-26B-{idx + 1:04d}"

        shift_days = pd.Timedelta(days=181)  # Ene 2026 -> Jul 2026
        muestra["job_published_at"] = muestra["job_published_at"] + shift_days
        muestra["applied_at"] = muestra["applied_at"] + shift_days
        muestra["updated_at"] = muestra["updated_at"] + shift_days

        # Ajustar ligeramente salarios esperados (±2%) de forma determinista y recalcular brecha
        factores = rng.uniform(0.98, 1.03, size=len(muestra))
        muestra["expected_salary_cop"] = np.round(muestra["expected_salary_cop"] * factores, 0).astype(int)
        muestra["brecha_salarial_aspiracion_cop"] = muestra["expected_salary_cop"] - muestra["oferta_media_cop"]
        muestra["brecha_salarial_aspiracion_pct"] = np.round(
            (muestra["brecha_salarial_aspiracion_cop"] / muestra["oferta_media_cop"]) * 100.0, 2
        )
        muestra["periodo"] = "2026-B"
        muestra.to_csv(ruta, index=False, encoding="utf-8", lineterminator="\n")

    df_26b = pd.read_csv(ruta, parse_dates=["job_published_at", "applied_at", "updated_at"])
    for col in ("is_protected_minor", "minor_permit_verified"):
        df_26b[col] = df_26b[col].astype(str).str.strip().str.lower().eq("true")
    return df_26b, ruta


def integrar_con_catalogo(df_u: pd.DataFrame, catalogo: pd.DataFrame) -> dict[str, object]:
    """CA-ISS-15-02: merge() auditado con how='left', validate='many_to_one', indicator=True y prueba negativa."""
    # 1. Prueba defensiva: inyectar duplicado en copia del catálogo debe lanzar MergeError
    catalogo_dup = pd.concat([catalogo, catalogo.iloc[[0]]], ignore_index=True)
    merge_error_capturado = False
    try:
        df_u.merge(catalogo_dup, on="job_id", how="left", validate="many_to_one")
    except pd.errors.MergeError:
        merge_error_capturado = True
    assert merge_error_capturado, "validate='many_to_one' debió rechazar el catálogo con job_id duplicado"

    # 2. Cruce analítico principal (how='left', validate='many_to_one', indicator=True)
    df_con_cat = df_u.merge(
        catalogo,
        on="job_id",
        how="left",
        validate="many_to_one",
        indicator="_merge_catalogo",
    )
    assert len(df_con_cat) == len(df_u) == 93, f"El merge con catálogo alteró el universo: {len(df_con_cat)}"
    conteo_left = df_con_cat["_merge_catalogo"].value_counts().to_dict()
    assert int(conteo_left.get("both", 0)) == 93, f"Todas las 93 postulaciones deben cruzar con catálogo: {conteo_left}"
    assert int(conteo_left.get("left_only", 0)) == 0, "No deben existir postulaciones con job_id huérfano"

    # 3. Auditoría de cobertura de vacantes (how='outer', validate='many_to_one', indicator=True)
    audit_outer = df_u[["application_id", "job_id"]].merge(
        catalogo[["job_id", "company_name", "ciudad_sede", "estado_vacante_catalogo"]],
        on="job_id",
        how="outer",
        validate="many_to_one",
        indicator="_merge_auditoria",
    )
    vacantes_sin_postulantes = audit_outer.loc[
        audit_outer["_merge_auditoria"] == "right_only",
        ["job_id", "company_name", "ciudad_sede", "estado_vacante_catalogo"],
    ].reset_index(drop=True)
    assert len(vacantes_sin_postulantes) == 3, (
        f"Se esperaban exactamente 3 vacantes sin postulantes activos, se hallaron {len(vacantes_sin_postulantes)}"
    )

    return {
        "df_con_catalogo": df_con_cat,
        "merge_error_capturado": merge_error_capturado,
        "conteo_merge_left": {str(k): int(v) for k, v in conteo_left.items()},
        "vacantes_sin_postulantes": vacantes_sin_postulantes,
    }


def integrar_eventos_sin_multiplicacion(
    df_con_cat: pd.DataFrame,
    eventos: pd.DataFrame,
    kpi_base: dict[str, object],
) -> dict[str, object]:
    """CA-ISS-15-03: demuestra la explosión 1:N ingenua y ejecuta la agregación previa groupby().agg() + join 1:1."""
    # 1. Demostración controlada del antipatrón (join ingenuo sin agregar -> multiplica filas y sesga KPIs)
    df_ingenuo = df_con_cat.merge(eventos, on="application_id", how="left")
    kpi_ingenuo = calcular_firma_kpis_referencia(df_ingenuo)
    assert len(df_ingenuo) > len(df_con_cat), "El join 1:N ingenuo debía multiplicar filas"
    assert kpi_ingenuo["tasa_entrevista_pct"] != kpi_base["tasa_entrevista_pct"], (
        "El join 1:N ingenuo debía distorsionar la tasa de entrevista de Semana 6"
    )

    # 2. Patrón correcto: agregar primero por llave primaria application_id
    eventos_prep = eventos.assign(
        es_chat=eventos["event_type"].isin(["chat_message_candidate", "chat_message_recruiter"]).astype(int),
        es_cv=eventos["event_type"].eq("cv_viewed").astype(int),
        es_perfil=eventos["event_type"].eq("profile_checked").astype(int),
    )
    eventos_agg = eventos_prep.groupby(["application_id"], as_index=False).agg(
        total_eventos=("event_id", "count"),
        n_mensajes_chat=("es_chat", "sum"),
        n_revisiones_cv=("es_cv", "sum"),
        n_revisiones_perfil=("es_perfil", "sum"),
        latencia_media_eventos_min=("response_latency_min", "mean"),
        primer_evento_at=("event_timestamp", "min"),
        ultimo_evento_at=("event_timestamp", "max"),
    )
    eventos_agg["latencia_media_eventos_min"] = eventos_agg["latencia_media_eventos_min"].round(2)

    # Aserción de unicidad previa al cruce
    assert len(eventos_agg) == eventos_agg["application_id"].nunique(), "eventos_agg debe ser estrictamente 1:1"

    # 3. Join seguro one_to_one sobre la tabla con catálogo
    df_integrado = df_con_cat.merge(
        eventos_agg,
        on="application_id",
        how="left",
        validate="one_to_one",
        indicator="_merge_eventos",
    )

    conteo_merge_ev = {str(k): int(v) for k, v in df_integrado["_merge_eventos"].value_counts().items()}
    assert conteo_merge_ev.get("both", 0) == 88 and conteo_merge_ev.get("left_only", 0) == 5

    # Imputar 0 únicamente en conteos de eventos ausentes (nunca en latencia media: permanece NaN)
    for col_cnt in ("total_eventos", "n_mensajes_chat", "n_revisiones_cv", "n_revisiones_perfil"):
        df_integrado[col_cnt] = df_integrado[col_cnt].fillna(0).astype(int)
    assert int(df_integrado["latencia_media_eventos_min"].isna().sum()) == 5

    # 4. Aserciones estrictas: exactamente 93 filas y 100% de preservación de KPIs de Semanas 6 y 7
    kpi_integrado = calcular_firma_kpis_referencia(df_integrado)
    assert len(df_integrado) == 93, f"Universo alterado tras join de eventos: {len(df_integrado)}"
    assert kpi_integrado == kpi_base, (
        f"Los KPIs de Semanas 6 y 7 cambiaron tras la integración:\nBase={kpi_base}\nPost={kpi_integrado}"
    )

    return {
        "df_integrado": df_integrado,
        "eventos_agg": eventos_agg,
        "filas_join_ingenuo": int(len(df_ingenuo)),
        "tasa_entrevista_ingenua_pct": kpi_ingenuo["tasa_entrevista_pct"],
        "conteo_merge_eventos": conteo_merge_ev,
        "kpi_integrado": kpi_integrado,
    }


def concatenar_cohortes_longitudinales(df_u: pd.DataFrame, df_26b: pd.DataFrame) -> dict[str, object]:
    """CA-ISS-15-04: apila cohortes 2026-A y 2026-B con concat() validando esquema y llave compuesta."""
    cohorte_a = df_u.copy()
    cohorte_a["periodo"] = "2026-A"
    cohorte_b = df_26b.copy()

    # Verificar esquemas 100% compatibles antes de apilar
    assert list(cohorte_a.columns) == list(cohorte_b.columns), "Los esquemas de 2026-A y 2026-B no coinciden"

    df_long = pd.concat([cohorte_a, cohorte_b], ignore_index=True)

    # Aserciones longitudinales
    assert len(df_long) == len(cohorte_a) + len(cohorte_b) == 153
    llave_compuesta = ["candidate_hash", "job_id", "periodo"]
    duplicados_llave = int(df_long.duplicated(subset=llave_compuesta).sum())
    assert duplicados_llave == 0, f"Se detectaron {duplicados_llave} duplicados en llave compuesta {llave_compuesta}"
    assert df_long["application_id"].nunique() == 153, "application_id debe ser único en el apilado longitudinal"

    candidatos_recurrentes = int(
        len(set(cohorte_a["candidate_hash"]).intersection(set(cohorte_b["candidate_hash"])))
    )
    resumen_periodos = df_long.groupby("periodo", as_index=False).agg(
        n_postulaciones=("application_id", "size"),
        n_candidatos_unicos=("candidate_hash", "nunique"),
        tasa_entrevista_pct=("status", lambda s: round(100.0 * float(s.isin(["interview", "accepted"]).mean()), 2)),
        salario_mediano_cop=("expected_salary_cop", "median"),
    )

    return {
        "df_longitudinal": df_long,
        "duplicados_llave_compuesta": duplicados_llave,
        "candidatos_recurrentes": candidatos_recurrentes,
        "resumen_periodos": resumen_periodos,
    }


def reestructurar_melt_y_pivot(df_integrado: pd.DataFrame) -> dict[str, object]:
    """CA-ISS-15-05: transformación bidireccional ancho -> largo (melt) -> ancho (pivot) con reversibilidad exacta."""
    df_scores = df_integrado[["application_id", "candidate_hash", "job_id"]].copy()

    # Puntajes reproducibles de evaluación por componente (escala 0–100, 2 decimales)
    bonus_edu = df_integrado["education_level"].map(
        {
            "Bachiller": 62.0,
            "Técnico": 74.0,
            "Tecnólogo": 82.0,
            "Universitario en curso": 86.0,
            "Profesional Jr": 92.0,
        }
    ).fillna(70.0)
    bonus_skill = df_integrado["primary_skill"].map(
        {
            "Python": 9.5,
            "SQL": 9.0,
            "Power BI": 8.5,
            "PHP": 8.0,
            "JavaScript": 8.0,
            "Excel": 7.5,
            "Git": 7.0,
            "Atención al Cliente": 6.5,
        }
    ).fillna(6.0)

    df_scores["score_habilidades"] = np.clip(
        np.round(0.85 * df_integrado["profile_completeness_pct"] + bonus_skill, 2), 0.0, 100.0
    )
    df_scores["score_experiencia"] = np.clip(
        np.round(bonus_edu + (df_integrado["candidate_age"] - 16) * 1.15, 2), 0.0, 100.0
    )
    df_scores["score_disponibilidad"] = np.clip(
        np.round(
            90.0
            - np.abs(df_integrado["brecha_salarial_aspiracion_pct"]) * 0.75
            + np.where(df_integrado["minor_permit_verified"], 4.0, 0.0),
            2,
        ),
        0.0,
        100.0,
    )

    cols_id = ["application_id", "candidate_hash", "job_id"]
    cols_valor = ["score_habilidades", "score_experiencia", "score_disponibilidad"]

    # 1. Formato ancho -> largo con melt()
    df_largo = pd.melt(
        df_scores,
        id_vars=cols_id,
        value_vars=cols_valor,
        var_name="componente",
        value_name="puntaje",
    )
    assert df_largo.shape == (len(df_scores) * len(cols_valor), 5), f"Forma inesperada tras melt(): {df_largo.shape}"
    assert int(df_largo["puntaje"].isna().sum()) == 0, "No debe haber NaNs tras melt()"

    # 2. Reconstrucción largo -> ancho con pivot()
    df_reconstruido = (
        df_largo.pivot(index=cols_id, columns="componente", values="puntaje")
        .reset_index()
        .rename_axis(None, axis=1)
    )
    df_reconstruido = df_reconstruido[cols_id + cols_valor].sort_values("application_id").reset_index(drop=True)
    df_original_ord = df_scores[cols_id + cols_valor].sort_values("application_id").reset_index(drop=True)

    # 3. Demostración matemática de reversibilidad exacta con assert
    max_diff = float(np.abs(df_original_ord[cols_valor].to_numpy() - df_reconstruido[cols_valor].to_numpy()).max())
    assert max_diff == 0.0, f"Diferencia numérica no nula tras pivot(): {max_diff}"
    pd.testing.assert_frame_equal(df_original_ord, df_reconstruido, check_exact=True)

    resumen_componentes = df_largo.groupby("componente", as_index=False).agg(
        n=("puntaje", "count"),
        media=("puntaje", "mean"),
        mediana=("puntaje", "median"),
        std=("puntaje", "std"),
        minimo=("puntaje", "min"),
        maximo=("puntaje", "max"),
    )
    resumen_componentes[["media", "mediana", "std"]] = resumen_componentes[["media", "mediana", "std"]].round(2)

    return {
        "df_ancho": df_original_ord,
        "df_largo": df_largo,
        "df_reconstruido": df_reconstruido,
        "max_diff_absoluta": max_diff,
        "resumen_componentes": resumen_componentes,
    }


def consultar_datos_abiertos_sena(analytics_root: Path, df_u: pd.DataFrame) -> dict[str, object]:
    """
    CA-ISS-15-06: Consulta el conjunto público SENA 28vu-5tx7 en datos.gov.co (Socrata API),
    conserva respaldo versionado local para reproducibilidad offline y construye la tabla
    territorial contextualizada SIN mezclar microdatos YEB con macro-agregados SENA.
    """
    external_dir = analytics_root / "data" / "external"
    external_dir.mkdir(parents=True, exist_ok=True)
    ruta_cache = external_dir / "datos_abiertos_sena_28vu_5tx7_departamentos.csv"

    origen_consulta = "cache_local_versionado"
    http_status = None
    try:
        resp = requests.get(
            DATOS_GOV_RESOURCE_URL,
            params={
                "$select": (
                    "nombre_departamento_curso, count(*) as fichas_sena, "
                    "sum(gran_total_total_aprendices) as total_aprendices_sena, "
                    "sum(tipo_poblacion__jovenes) as total_jovenes_focalizados_sena"
                ),
                "$group": "nombre_departamento_curso",
                "$order": "total_aprendices_sena DESC",
                "$limit": 50,
            },
            timeout=15,
        )
        http_status = resp.status_code
        if resp.status_code == 200 and isinstance(resp.json(), list) and len(resp.json()) > 0:
            rows = []
            for item in resp.json():
                dep = str(item.get("nombre_departamento_curso", "")).strip().strip('"').upper()
                rows.append(
                    {
                        "departamento_sena": dep,
                        "fichas_sena": int(float(item.get("fichas_sena", 0))),
                        "total_aprendices_sena": int(float(item.get("total_aprendices_sena", 0))),
                        "total_jovenes_focalizados_sena": int(float(item.get("total_jovenes_focalizados_sena", 0))),
                    }
                )
            df_sena_nac = pd.DataFrame(rows)
            df_sena_nac["dataset_id"] = DATOS_GOV_DATASET_ID
            df_sena_nac["periodo_sena"] = "202402"
            df_sena_nac["fuente_url"] = DATOS_GOV_CATALOG_URL
            df_sena_nac.to_csv(ruta_cache, index=False, encoding="utf-8", lineterminator="\n")
            origen_consulta = "api_live_datos_gov_co"
    except Exception:
        pass

    assert ruta_cache.exists(), "No se pudo consultar datos.gov.co ni existe caché local de SENA 28vu-5tx7"
    df_sena_nac = pd.read_csv(ruta_cache)

    total_nac_aprendices = int(df_sena_nac["total_aprendices_sena"].sum())
    total_nac_fichas = int(df_sena_nac["fichas_sena"].sum())
    total_nac_jovenes = int(df_sena_nac["total_jovenes_focalizados_sena"].sum())
    assert total_nac_aprendices > 0 and total_nac_fichas > 0

    # Resumen agregado de YEB a grano departamento (solo para lectura contextual paralela, nunca merge a microdatos)
    yeb_dep = (
        df_u.groupby("departamento", as_index=False)
        .agg(
            yeb_postulaciones_2026A=("application_id", "size"),
            yeb_tasa_entrevista_pct=("status", lambda s: round(100.0 * float(s.isin(["interview", "accepted"]).mean()), 2)),
            yeb_salario_mediano_cop=("expected_salary_cop", "median"),
        )
    )
    yeb_dep["departamento_sena"] = yeb_dep["departamento"].map(MAPA_DEP_YEB_A_SENA)
    yeb_dep["yeb_participacion_pct"] = np.round(100.0 * yeb_dep["yeb_postulaciones_2026A"] / len(df_u), 2)

    tabla_contexto = yeb_dep.merge(
        df_sena_nac[["departamento_sena", "fichas_sena", "total_aprendices_sena", "total_jovenes_focalizados_sena"]],
        on="departamento_sena",
        how="left",
        validate="one_to_one",
    )
    tabla_contexto["sena_participacion_nacional_pct"] = np.round(
        100.0 * tabla_contexto["total_aprendices_sena"] / total_nac_aprendices, 2
    )
    tabla_contexto["nota_metodologica"] = (
        "Lectura territorial paralela: microdatos YEB (2026-A) vs. macro-agregados SENA (periodo 202402); no combinar denominadores"
    )
    tabla_contexto = tabla_contexto.sort_values("total_aprendices_sena", ascending=False).reset_index(drop=True)

    assert len(tabla_contexto) == 6, "Deben cruzarse exactamente los 6 departamentos del portal YEB"
    assert int(tabla_contexto["total_aprendices_sena"].isna().sum()) == 0, "Todos los departamentos YEB deben existir en SENA"

    return {
        "origen_consulta": origen_consulta,
        "http_status": http_status,
        "ruta_cache": ruta_cache,
        "df_sena_nacional": df_sena_nac,
        "total_nac_fichas": total_nac_fichas,
        "total_nac_aprendices": total_nac_aprendices,
        "total_nac_jovenes": total_nac_jovenes,
        "participacion_6_deps_pct": round(100.0 * float(tabla_contexto["total_aprendices_sena"].sum()) / total_nac_aprendices, 2),
        "tabla_contexto": tabla_contexto,
    }


def verificar_metadatos_doc(analytics_root: Path) -> list[Path]:
    """CA-ISS-15-06 / CP-055C: comprueba la existencia y completitud de docs/metadatos_fuentes_yeb.md."""
    candidatos = [
        analytics_root / "docs" / "metadatos_fuentes_yeb.md",
        analytics_root / "docs - analisis" / "metadatos_fuentes_yeb.md",
    ]
    existentes = [p for p in candidatos if p.exists() and p.is_file()]
    assert len(existentes) >= 1, "Falta docs/metadatos_fuentes_yeb.md"

    marcadores = [
        "yeb_postulaciones_transformado.csv",
        "yeb_catalogo_vacantes_empresas.csv",
        "yeb_eventos_seguimiento.csv",
        "yeb_postulaciones_cohorte_2026B.csv",
        "28vu-5tx7",
        "datos.gov.co",
        "CC BY-SA 4.0",
        "many_to_one",
        "falacia ecológica",
    ]
    for p in existentes:
        contenido = p.read_text(encoding="utf-8")
        for m in marcadores:
            assert m in contenido, f"Falta marcador '{m}' en {p.relative_to(analytics_root)}"
    return existentes


def ejecutar_flujo_completo(analytics_root: Path | None = None, verify_docs: bool = True) -> dict[str, object]:
    """Ejecuta de extremo a extremo el Issue #15 y exporta todos los artefactos auditados."""
    root = analytics_root or resolve_analytics_root()
    outputs_dir = root / "outputs"

    df_total, df_u, ruta_fuente, huella_antes = cargar_linea_base_semana7(root)
    kpi_base = calcular_firma_kpis_referencia(df_u)

    # Verificar coherencia exacta contra yeb_kpis_semana6.csv
    ruta_kpi_s6 = outputs_dir / "yeb_kpis_semana6.csv"
    assert ruta_kpi_s6.exists(), f"Falta {ruta_kpi_s6}"
    df_kpi_s6 = pd.read_csv(ruta_kpi_s6)
    fila_global_s6 = df_kpi_s6.loc[df_kpi_s6["dimension"] == "Global"].iloc[0]
    assert int(fila_global_s6["n_filas_size"]) == kpi_base["n_filas"] == 93
    assert round(float(fila_global_s6["tasa_entrevista_pct"]), 2) == kpi_base["tasa_entrevista_pct"] == 39.78
    assert round(float(fila_global_s6["tiempo_resp_media"]), 2) == kpi_base["tiempo_resp_media"] == 4.0

    catalogo, ruta_cat = generar_o_cargar_catalogo_vacantes(root)
    eventos, ruta_ev = generar_o_cargar_eventos_seguimiento(root, df_u)
    cohorte_26b, ruta_26b = generar_o_cargar_cohorte_2026b(root, df_u)

    res_cat = integrar_con_catalogo(df_u, catalogo)
    res_ev = integrar_eventos_sin_multiplicacion(res_cat["df_con_catalogo"], eventos, kpi_base)
    res_long = concatenar_cohortes_longitudinales(df_u, cohorte_26b)
    res_melt = reestructurar_melt_y_pivot(res_ev["df_integrado"])
    res_sena = consultar_datos_abiertos_sena(root, df_u)

    # Enriquecer tabla final integrada con los puntajes de evaluación (1:1 por application_id)
    df_final = res_ev["df_integrado"].merge(
        res_melt["df_ancho"][["application_id", "score_habilidades", "score_experiencia", "score_disponibilidad"]],
        on="application_id",
        how="left",
        validate="one_to_one",
    )
    assert len(df_final) == 93
    assert calcular_firma_kpis_referencia(df_final) == kpi_base

    # Exportar artefactos auditados
    ruta_out_integrado = outputs_dir / "yeb_postulaciones_integradas_semana8.csv"
    ruta_out_auditoria = outputs_dir / "auditoria_integracion_joins_semana8.csv"
    ruta_out_long = outputs_dir / "yeb_longitudinal_2026A_2026B.csv"
    ruta_out_sena = outputs_dir / "contextualizacion_datos_abiertos_sena.csv"

    df_final.to_csv(ruta_out_integrado, index=False, encoding="utf-8", lineterminator="\n")
    res_long["df_longitudinal"].to_csv(ruta_out_long, index=False, encoding="utf-8", lineterminator="\n")
    res_sena["tabla_contexto"].to_csv(ruta_out_sena, index=False, encoding="utf-8", lineterminator="\n")

    auditoria_rows = [
        {
            "etapa": "1_linea_base_semana7",
            "operacion": "Carga de solo lectura (origen_registro == dataset_preparado)",
            "filas_antes": len(df_total),
            "filas_despues": len(df_u),
            "validacion_cardinalidad": "application_id único (93/93)",
            "tasa_entrevista_pct": kpi_base["tasa_entrevista_pct"],
            "hallazgo": "Universo analizable n=93 verificado contra yeb_kpis_semana6.csv",
        },
        {
            "etapa": "2_merge_catalogo_vacantes",
            "operacion": "merge(on='job_id', how='left', validate='many_to_one', indicator=True)",
            "filas_antes": len(df_u),
            "filas_despues": len(res_cat["df_con_catalogo"]),
            "validacion_cardinalidad": "many_to_one OK + MergeError probado en duplicado",
            "tasa_entrevista_pct": calcular_firma_kpis_referencia(res_cat["df_con_catalogo"])["tasa_entrevista_pct"],
            "hallazgo": f"93 both, 0 left_only; auditoría outer detecta {len(res_cat['vacantes_sin_postulantes'])} vacantes sin postulantes activos (JOB-2026-019..021)",
        },
        {
            "etapa": "3a_antipatron_join_1N_ingenuo",
            "operacion": "merge directo sin pre-agregar eventos (solo diagnóstico en memoria)",
            "filas_antes": len(res_cat["df_con_catalogo"]),
            "filas_despues": res_ev["filas_join_ingenuo"],
            "validacion_cardinalidad": "FALLA ESPERADA: explosión 1:N",
            "tasa_entrevista_pct": res_ev["tasa_entrevista_ingenua_pct"],
            "hallazgo": f"Multiplicó filas de 93 a {res_ev['filas_join_ingenuo']} y distorsionó tasa_entrevista de 39.78% a {res_ev['tasa_entrevista_ingenua_pct']}%",
        },
        {
            "etapa": "3b_join_eventos_preagregados",
            "operacion": "groupby(['application_id']).agg(...) + merge(how='left', validate='one_to_one')",
            "filas_antes": len(res_cat["df_con_catalogo"]),
            "filas_despues": len(res_ev["df_integrado"]),
            "validacion_cardinalidad": "one_to_one OK (88 both + 5 sin eventos)",
            "tasa_entrevista_pct": res_ev["kpi_integrado"]["tasa_entrevista_pct"],
            "hallazgo": "0 filas multiplicadas; KPIs de Semanas 6 y 7 100% idénticos por assert",
        },
        {
            "etapa": "4_concat_longitudinal",
            "operacion": "concat([cohorte_2026A, cohorte_2026B], ignore_index=True)",
            "filas_antes": len(df_u),
            "filas_despues": len(res_long["df_longitudinal"]),
            "validacion_cardinalidad": "0 duplicados en (candidate_hash, job_id, periodo)",
            "tasa_entrevista_pct": round(
                100.0 * float(res_long["df_longitudinal"]["status"].isin(["interview", "accepted"]).mean()), 2
            ),
            "hallazgo": f"93 (2026-A) + 60 (2026-B) = 153 filas; {res_long['candidatos_recurrentes']} candidatos recurrentes entre periodos",
        },
        {
            "etapa": "5_melt_y_pivot_reversible",
            "operacion": "melt() -> 279 filas largas -> pivot() -> 93 filas anchas",
            "filas_antes": len(res_melt["df_ancho"]),
            "filas_despues": len(res_melt["df_reconstruido"]),
            "validacion_cardinalidad": f"max_diff_absoluta = {res_melt['max_diff_absoluta']}",
            "tasa_entrevista_pct": kpi_base["tasa_entrevista_pct"],
            "hallazgo": "Reversibilidad exacta verificada con assert_frame_equal(check_exact=True)",
        },
        {
            "etapa": "6_datos_abiertos_sena",
            "operacion": f"Consulta Socrata datos.gov.co ({DATOS_GOV_DATASET_ID}, {res_sena['origen_consulta']})",
            "filas_antes": len(df_u),
            "filas_despues": len(res_sena["tabla_contexto"]),
            "validacion_cardinalidad": "Tabla territorial paralela (6 departamentos, sin mezclar microdatos)",
            "tasa_entrevista_pct": kpi_base["tasa_entrevista_pct"],
            "hallazgo": f"SENA 202402: {res_sena['total_nac_aprendices']} aprendices en {res_sena['total_nac_fichas']} fichas; 6 deptos YEB concentran {res_sena['participacion_6_deps_pct']}%",
        },
    ]
    pd.DataFrame(auditoria_rows).to_csv(ruta_out_auditoria, index=False, encoding="utf-8", lineterminator="\n")

    # Confirmar inmutabilidad de la fuente original de Semanas 5–7
    huella_despues = sha256_archivo(ruta_fuente)
    assert huella_antes == huella_despues, "La fuente base de Semanas 5–7 fue modificada indebidamente"

    docs_verificados = verificar_metadatos_doc(root) if verify_docs else []

    return {
        "kpi_base": kpi_base,
        "huella_fuente_sha256": huella_despues,
        "rutas_fuentes": {"catalogo": ruta_cat, "eventos": ruta_ev, "cohorte_2026b": ruta_26b},
        "rutas_salidas": {
            "integrado": ruta_out_integrado,
            "auditoria": ruta_out_auditoria,
            "longitudinal": ruta_out_long,
            "sena": ruta_out_sena,
        },
        "catalogo": res_cat,
        "eventos": res_ev,
        "longitudinal": res_long,
        "melt_pivot": res_melt,
        "sena": res_sena,
        "df_final": df_final,
        "docs_verificados": docs_verificados,
    }


def main() -> int:
    res = ejecutar_flujo_completo(verify_docs=True)
    print("=" * 84)
    print("ISSUE #15 [Data/Integration]: INTEGRACIÓN MULTIFUENTE, MELT/PIVOT Y DATOS ABIERTOS")
    print("=" * 84)
    print(f"  [OK] Línea base S5-S7 intacta (SHA-256) : {res['huella_fuente_sha256'][:16]}... | n = {res['kpi_base']['n_filas']}")
    print(f"  [OK] Merge catálogo (many_to_one)       : {res['catalogo']['conteo_merge_left']} | MergeError duplicado = {res['catalogo']['merge_error_capturado']}")
    print(f"  [OK] Vacantes sin postulantes activos   : {list(res['catalogo']['vacantes_sin_postulantes']['job_id'])}")
    print(
        f"  [OK] Anti-multiplicación 1:N            : ingenuo={res['eventos']['filas_join_ingenuo']} filas "
        f"(tasa={res['eventos']['tasa_entrevista_ingenua_pct']}%) -> preagregado=93 filas "
        f"(tasa={res['eventos']['kpi_integrado']['tasa_entrevista_pct']}%)"
    )
    print(
        f"  [OK] Concatenación longitudinal         : {len(res['longitudinal']['df_longitudinal'])} filas (93 + 60) | "
        f"duplicados llave compuesta = {res['longitudinal']['duplicados_llave_compuesta']} | "
        f"recurrentes = {res['longitudinal']['candidatos_recurrentes']}"
    )
    print(
        f"  [OK] Reestructuración melt() <-> pivot(): {res['melt_pivot']['df_ancho'].shape} -> "
        f"{res['melt_pivot']['df_largo'].shape} -> {res['melt_pivot']['df_reconstruido'].shape} | "
        f"max_diff = {res['melt_pivot']['max_diff_absoluta']}"
    )
    print(
        f"  [OK] Datos Abiertos SENA ({DATOS_GOV_DATASET_ID})     : origen={res['sena']['origen_consulta']} | "
        f"aprendices_nac={res['sena']['total_nac_aprendices']} | "
        f"6 deptos YEB={res['sena']['participacion_6_deps_pct']}%"
    )
    for d in res["docs_verificados"]:
        print(f"  [OK] Metadatos verificados              : {d.as_posix()}")
    print("=" * 84)
    print("RESULTADO GLOBAL ISSUE #15: TODOS LOS CRITERIOS Y PRUEBAS (CP-055, CP-055B, CP-055C) PASAN")
    print("=" * 84)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
