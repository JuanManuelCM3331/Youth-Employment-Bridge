#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Issue #05 [Data/Quality]: Diagnóstico y tratamiento de las 6 dimensiones de calidad
en el dataset de empleo juvenil (Youth Employment Bridge - YEB).

Criterios de Aceptación cubiertos:
  - CA-ISS-05-01: Evaluación de las 6 dimensiones de calidad:
      1. Completitud (campos obligatorios de contacto)
      2. Unicidad (cédula/documento único por rol y claves de postulación)
      3. Validez (salarios no negativos, edades entre 16 y 24 años)
      4. Consistencia (modalidades 'Remoto'/'Híbrido'/'Presencial' y coherencia menor de edad)
      5. Oportunidad (postulaciones en ventana temporal válida)
      6. Trazabilidad (identificador de lote, sistema origen y vínculo de auditoría)
  - CA-ISS-05-02: Tratamiento argumentado:
      * Descarte de duplicados exactos (keep='first').
      * Aislamiento en cuarentena de identificadores en conflicto sin eliminarlos a ciegas.
      * Conversión numérica segura mediante pd.to_numeric(..., errors='coerce').
  - CA-ISS-05-03: Generación de:
      * analytics/data/processed/yeb_postulaciones_preparado.csv
      * analytics/outputs/incidencias_calidad.csv

Riesgos mitigados: NEG-007, TEC-011
Casos de prueba verificados: CP-045, CP-045B
"""

from __future__ import annotations

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

SEED = 202605

MODALIDADES_CANONICAS = ("Presencial", "Híbrido", "Remoto")
MAPA_MODALIDADES = {
    "presencial": "Presencial",
    "in-office": "Presencial",
    "on-site": "Presencial",
    "hibrido": "Híbrido",
    "híbrido": "Híbrido",
    "hybrid": "Híbrido",
    "mixto": "Híbrido",
    "remoto": "Remoto",
    "remote": "Remoto",
    "teletrabajo": "Remoto",
}


def resolve_analytics_root() -> Path:
    """Resuelve la carpeta raíz analytics/ usando rutas relativas pathlib.Path."""
    script_dir = Path(__file__).resolve().parent
    analytics_root = script_dir.parent
    assert (analytics_root / "data" / "raw").exists(), "No se encontró analytics/data/raw/"
    assert (analytics_root / "data" / "processed").exists(), "No se encontró analytics/data/processed/"
    assert (analytics_root / "outputs").exists(), "No se encontró analytics/outputs/"
    return analytics_root


def build_dirty_dataset(raw_dir: Path) -> Path:
    """
    Construye un dataset sintético de candidatos y postulaciones con errores inducidos
    controlados y reproducibles sobre las 6 dimensiones de calidad (CA-ISS-05-01).
    """
    rng = np.random.default_rng(SEED)

    ciudades_limpias = ["Medellín", "Bogotá", "Cali", "Barranquilla", "Bucaramanga", "Pereira"]
    sectores = ["Tecnología", "BPO y Contact Center", "Logística", "Comercio", "Servicios Financieros"]
    cargos = [
        "Desarrollador Backend PHP Jr",
        "Analista de Datos Jr",
        "Agente de Soporte Bilingüe",
        "Auxiliar de Bodega e Inventarios",
        "Asistente de Marketing Digital",
        "Aprendiz SENA en Sistemas",
        "Auxiliar Contable Jr",
        " Ejecutivo Comercial Juvenil ",
    ]
    habilidades = ["Python", "SQL", "PHP", "Excel", "JavaScript", "Atención al Cliente", "Git", "Power BI"]
    niveles_educativos = ["Bachiller", "Técnico", "Tecnólogo", "Universitario en curso", "Profesional Jr"]
    estados_postulacion = ["submitted", "under_review", "interview", "accepted", "rejected"]

    records: list[dict[str, object]] = []

    # 110 registros base válidos
    for idx in range(1, 111):
        age = int(rng.choice([16, 17, 18, 19, 20, 21, 22, 23, 24], p=[0.07, 0.08, 0.14, 0.15, 0.15, 0.13, 0.11, 0.09, 0.08]))
        is_minor = age < 18
        sal_min = int(rng.choice([1300000, 1450000, 1600000, 1800000, 2000000, 2300000]))
        sal_max = sal_min + int(rng.choice([300000, 500000, 700000]))
        exp_sal = sal_min + int(rng.integers(-100000, 450000))

        pub_day = int(rng.integers(1, 15))
        app_offset = int(rng.integers(1, 12))
        upd_offset = app_offset + int(rng.integers(0, 10))

        pub_date = pd.Timestamp("2026-01-01") + pd.Timedelta(days=pub_day)
        app_date = pub_date + pd.Timedelta(days=app_offset)
        upd_date = pub_date + pd.Timedelta(days=upd_offset)

        records.append(
            {
                "raw_record_id": f"RAW-{idx:04d}",
                "application_id": f"APP-2026-{idx:04d}",
                "job_id": f"JOB-2026-{(idx % 18) + 1:03d}",
                "candidate_hash": f"CAND-HASH-{idx:04d}",
                "documento_hash": f"DOC-CC-{1000 + idx:05d}",
                "rol": "candidate",
                "candidate_age": str(age),
                "is_protected_minor": is_minor,
                "minor_permit_verified": True if is_minor else False,
                "email_contacto": f"candidato_{idx:04d}@yeb-anon.co",
                "telefono_contacto": f"+57-300-{2000000 + idx:07d}",
                "ciudad_candidato": ciudades_limpias[idx % len(ciudades_limpias)],
                "education_level": niveles_educativos[idx % len(niveles_educativos)],
                "primary_skill": habilidades[idx % len(habilidades)],
                "job_title": cargos[idx % len(cargos)],
                "sector": sectores[idx % len(sectores)],
                "modality": MODALIDADES_CANONICAS[idx % len(MODALIDADES_CANONICAS)],
                "salary_min_offered": str(sal_min),
                "salary_max_offered": str(sal_max),
                "expected_salary_cop": str(exp_sal),
                "status": estados_postulacion[idx % len(estados_postulacion)],
                "job_published_at": pub_date.strftime("%Y-%m-%d"),
                "applied_at": app_date.strftime("%Y-%m-%d"),
                "updated_at": upd_date.strftime("%Y-%m-%d"),
                "profile_completeness_pct": int(rng.integers(72, 100)),
                "source_system": "YEB_MYSQL_PORTAL",
                "audit_batch_id": "BATCH-2026-W03",
            }
        )

    # =========================================================================
    # INYECCIÓN CONTROLADA DE ERRORES EN LAS 6 DIMENSIONES DE CALIDAD
    # =========================================================================

    # 1. UNICIDAD - Duplicados exactos (3 filas clonadas idénticamente de registros existentes)
    for clone_idx in (5, 18, 42):
        records.append(dict(records[clone_idx]))

    # 2. UNICIDAD - Identificadores en conflicto (misma cédula/rol o mismo application_id con datos distintos)
    # Conflicto A: Mismo application_id APP-2026-0010 pero distinto candidato y salario
    conflict_app = dict(records[9])
    conflict_app["raw_record_id"] = "RAW-9001"
    conflict_app["candidate_hash"] = "CAND-HASH-9901"
    conflict_app["documento_hash"] = "DOC-CC-99901"
    conflict_app["expected_salary_cop"] = "3800000"
    conflict_app["status"] = "accepted"
    records.append(conflict_app)

    # Conflicto B: Misma cédula (documento_hash = DOC-CC-01025) y mismo rol ('candidate') pero con otra edad y ciudad contradictoria
    conflict_doc = dict(records[24])
    conflict_doc["raw_record_id"] = "RAW-9002"
    conflict_doc["application_id"] = "APP-2026-9002"
    conflict_doc["candidate_hash"] = "CAND-HASH-9902"
    conflict_doc["candidate_age"] = "24"
    conflict_doc["ciudad_candidato"] = "Cali"
    records.append(conflict_doc)

    # 3. COMPLETITUD - Campos obligatorios de contacto nulos o vacíos
    records[11]["email_contacto"] = ""
    records[12]["telefono_contacto"] = None
    records[13]["email_contacto"] = None
    records[13]["telefono_contacto"] = ""

    # 4. VALIDEZ - Salarios negativos, textos corruptos en campos numéricos y edades fuera de [16, 24]
    records[20]["expected_salary_cop"] = "-1500000"       # Salario negativo
    records[21]["salary_min_offered"] = "-950000"         # Salario mínimo ofertado negativo
    records[22]["expected_salary_cop"] = "UN_MILLON_800"  # Texto no numérico (requiere pd.to_numeric coerce)
    records[23]["candidate_age"] = "14"                   # Menor de 16 años (ilegal en plataforma YEB)
    records[25]["candidate_age"] = "29"                   # Mayor de 24 años (fuera de cohorte juvenil YEB)
    records[26]["candidate_age"] = "veinte"               # Edad en texto no convertible

    # 5. CONSISTENCIA - Modalidades no canónicas y contradicción menor de edad vs. bandera
    records[30]["modality"] = "teletrabajo"
    records[31]["modality"] = "in-office"
    records[32]["modality"] = "hybrid"
    records[33]["modality"] = "PRESENCIAL_NOCTURNO_ILEGAL"  # Modalidad irreconocible
    records[34]["candidate_age"] = "17"
    records[34]["is_protected_minor"] = False               # Inconsistencia: tiene 17 años pero is_protected_minor=False
    records[35]["ciudad_candidato"] = "medellin "           # Ciudad mal tipada / sin tilde

    # 6. OPORTUNIDAD - Fechas de postulación anteriores a publicación o posteriores a actualización
    records[40]["applied_at"] = "2025-11-10"                # Postulación antes de que existiera la vacante (2026-01)
    records[41]["updated_at"] = "2025-12-01"                # Actualización anterior a la fecha de postulación
    records[43]["applied_at"] = "2027-08-15"                # Fecha futura inválida (fuera de ventana temporal)
    records[43]["updated_at"] = "2027-08-20"

    # 7. TRAZABILIDAD - Sin source_system o sin audit_batch_id
    records[50]["source_system"] = ""
    records[51]["audit_batch_id"] = None

    dirty_df = pd.DataFrame(records)
    dirty_csv_path = raw_dir / "yeb_postulaciones_calidad_raw.csv"
    dirty_df.to_csv(dirty_csv_path, index=False, encoding="utf-8")
    return dirty_csv_path


def profile_and_clean_dataset(
    dirty_csv_path: Path,
    processed_dir: Path,
    outputs_dir: Path,
) -> tuple[pd.DataFrame, pd.DataFrame, dict[str, object]]:
    """
    Ejecuta el diagnóstico de las 6 dimensiones de calidad, registra cada incidencia
    en outputs/incidencias_calidad.csv y genera data/processed/yeb_postulaciones_preparado.csv.
    """
    df_raw = pd.read_csv(dirty_csv_path, dtype=str, keep_default_na=False)
    total_raw_rows = len(df_raw)

    incidencias: list[dict[str, object]] = []

    def log_incidencia(
        raw_id: str,
        app_id: str,
        dimension: str,
        regla: str,
        campo: str,
        valor_original: object,
        accion: str,
        justificacion: str,
    ) -> None:
        incidencias.append(
            {
                "incidencia_id": f"INC-{len(incidencias) + 1:04d}",
                "raw_record_id": str(raw_id),
                "application_id": str(app_id),
                "dimension_calidad": dimension,
                "regla_evaluada": regla,
                "campo_afectado": campo,
                "valor_original": str(valor_original),
                "accion_tratamiento": accion,
                "justificacion_tecnica": justificacion,
            }
        )

    # =========================================================================
    # PASO 1: DIMENSIÓN 2 (UNICIDAD) — DUPLICADOS EXACTOS VS. CLAVES EN CONFLICTO
    # =========================================================================
    # 1A. Duplicados exactos en todas las columnas
    exact_dup_mask = df_raw.duplicated(keep="first")
    for _, row in df_raw[exact_dup_mask].iterrows():
        log_incidencia(
            row["raw_record_id"],
            row["application_id"],
            "2. Unicidad",
            "UNI-01: Registro duplicado exacto en todas las columnas",
            "ALL_COLUMNS",
            f"Clon exacto de {row['raw_record_id']}",
            "DESCARTE_DUPLICADO_EXACTO",
            "Se conserva la primera aparición (keep='first') y se descarta la copia idéntica producida por reintento de extracción.",
        )
    df_step1 = df_raw[~exact_dup_mask].copy()
    exact_dups_removed = int(exact_dup_mask.sum())

    # 1B. Identificadores en conflicto: mismo application_id con atributos divergentes
    app_id_Conflict_mask = df_step1.duplicated(subset=["application_id"], keep=False)
    # 1C. Identificadores en conflicto: misma tupla (documento_hash, rol) pero diferente candidate_hash/edad/ciudad
    doc_role_groups = df_step1.groupby(["documento_hash", "rol"])["candidate_hash"].transform("nunique")
    doc_role_conflict_mask = doc_role_groups > 1

    conflict_mask = app_id_Conflict_mask | doc_role_conflict_mask
    df_quarantine = df_step1[conflict_mask].copy()

    for _, row in df_quarantine.iterrows():
        motivo = (
            "UNI-02: Colisión de clave primaria application_id con atributos contradictorios"
            if app_id_Conflict_mask.loc[row.name]
            else "UNI-03: Cédula/documento repetido para el mismo rol con identidad contradictoria"
        )
        campo_conflicto = "application_id" if app_id_Conflict_mask.loc[row.name] else "documento_hash + rol"
        valor_conflicto = (
            row["application_id"]
            if app_id_Conflict_mask.loc[row.name]
            else f"{row['documento_hash']} ({row['rol']})"
        )
        log_incidencia(
            row["raw_record_id"],
            row["application_id"],
            "2. Unicidad",
            motivo,
            campo_conflicto,
            valor_conflicto,
            "AISLAMIENTO_EN_CUARENTENA",
            "No se elimina a ciegas ni se sobrescribe: se aísla el grupo conflictivo en cuarentena para auditoría humana (mitigación TEC-011).",
        )

    quarantine_path = outputs_dir / "cuarentena_identificadores_conflicto.csv"
    df_quarantine.to_csv(quarantine_path, index=False, encoding="utf-8")

    # Continuamos el pipeline sobre registros sin colisión de clave
    df = df_step1[~conflict_mask].copy()
    conflicts_isolated = int(conflict_mask.sum())

    # =========================================================================
    # PASO 2: DIMENSIÓN 1 (COMPLETITUD) — CAMPOS OBLIGATORIOS DE CONTACTO
    # =========================================================================
    missing_email = df["email_contacto"].str.strip() == ""
    missing_phone = df["telefono_contacto"].str.strip() == ""
    incomplete_contact_mask = missing_email | missing_phone

    for _, row in df[incomplete_contact_mask].iterrows():
        campos_faltantes = []
        if str(row["email_contacto"]).strip() == "":
            campos_faltantes.append("email_contacto")
        if str(row["telefono_contacto"]).strip() == "":
            campos_faltantes.append("telefono_contacto")
        log_incidencia(
            row["raw_record_id"],
            row["application_id"],
            "1. Completitud",
            "COM-01: Campos obligatorios de contacto del candidato ausentes",
            ", ".join(campos_faltantes),
            "VACÍO / NULL",
            "EXCLUSION_POR_INCOMPLETITUD_CRITICA",
            "Un candidato sin medio de contacto verificable no puede avanzar en el proceso de selección.",
        )

    df = df[~incomplete_contact_mask].copy()
    incomplete_removed = int(incomplete_contact_mask.sum())

    # =========================================================================
    # PASO 3: DIMENSIÓN 3 (VALIDEZ) — CONVERSIÓN NUMÉRICA SEGURA Y RANGOS LEGALES
    # =========================================================================
    # Conversión segura con pd.to_numeric(..., errors='coerce') exigida por CA-ISS-05-02
    df["candidate_age_num"] = pd.to_numeric(df["candidate_age"], errors="coerce")
    df["salary_min_num"] = pd.to_numeric(df["salary_min_offered"], errors="coerce")
    df["salary_max_num"] = pd.to_numeric(df["salary_max_offered"], errors="coerce")
    df["expected_salary_num"] = pd.to_numeric(df["expected_salary_cop"], errors="coerce")

    invalid_validity_indices: list[int] = []

    for idx, row in df.iterrows():
        age_val = row["candidate_age_num"]
        sal_min_val = row["salary_min_num"]
        sal_max_val = row["salary_max_num"]
        exp_sal_val = row["expected_salary_num"]

        if pd.isna(age_val):
            log_incidencia(
                row["raw_record_id"],
                row["application_id"],
                "3. Validez",
                "VAL-01: Edad no numérica detectada por pd.to_numeric(errors='coerce')",
                "candidate_age",
                row["candidate_age"],
                "EXCLUSION_VALOR_NO_NUMERICO",
                "Conversión segura a NaN mediante pd.to_numeric(errors='coerce'); imposible verificar mayoría/minoría de edad legal.",
            )
            invalid_validity_indices.append(idx)
            continue

        if age_val < 16 or age_val > 24:
            log_incidencia(
                row["raw_record_id"],
                row["application_id"],
                "3. Validez",
                "VAL-02: Edad fuera del rango legal de empleabilidad juvenil [16, 24] años",
                "candidate_age",
                row["candidate_age"],
                "EXCLUSION_FUERA_DE_RANGO_EDAD",
                "La plataforma YEB restringe postulaciones a jóvenes entre 16 y 24 años inclusive (Ley de Empleo Juvenil y Código de Infancia).",
            )
            invalid_validity_indices.append(idx)
            continue

        if pd.isna(exp_sal_val) or pd.isna(sal_min_val) or pd.isna(sal_max_val):
            log_incidencia(
                row["raw_record_id"],
                row["application_id"],
                "3. Validez",
                "VAL-03: Salario no numérico detectado por pd.to_numeric(errors='coerce')",
                "expected_salary_cop / salary_offered",
                f"exp={row['expected_salary_cop']}, min={row['salary_min_offered']}",
                "EXCLUSION_SALARIO_CORRUPTO",
                "Conversión segura a NaN con pd.to_numeric(errors='coerce'); registro descartado para evitar distorsión salarial.",
            )
            invalid_validity_indices.append(idx)
            continue

        if exp_sal_val < 0 or sal_min_val < 0 or sal_max_val < 0 or sal_min_val > sal_max_val:
            campo_sal = "expected_salary_cop" if exp_sal_val < 0 else "salary_min_offered"
            val_orig = row["expected_salary_cop"] if exp_sal_val < 0 else row["salary_min_offered"]
            log_incidencia(
                row["raw_record_id"],
                row["application_id"],
                "3. Validez",
                "VAL-04: Salario negativo o rango salarial invertido",
                campo_sal,
                val_orig,
                "EXCLUSION_SALARIO_NEGATIVO",
                "Los montos salariales en COP no pueden ser negativos ni tener mínimo mayor que máximo (mitigación NEG-007).",
            )
            invalid_validity_indices.append(idx)

    df = df.drop(index=invalid_validity_indices).copy()
    validity_removed = len(invalid_validity_indices)

    # =========================================================================
    # PASO 4: DIMENSIÓN 4 (CONSISTENCIA) — MODALIDADES, CIUDADES Y REGLA MENORES
    # =========================================================================
    invalid_consistency_indices: list[int] = []
    mapa_ciudades = {
        "medellin": "Medellín",
        "medellín": "Medellín",
        "bogota": "Bogotá",
        "bogotá": "Bogotá",
        "cali": "Cali",
        "barranquilla": "Barranquilla",
        "bucaramanga": "Bucaramanga",
        "pereira": "Pereira",
    }

    for idx, row in df.iterrows():
        # 4A. Modalidad
        raw_mod = str(row["modality"]).strip()
        if raw_mod not in MODALIDADES_CANONICAS:
            norm_mod = MAPA_MODALIDADES.get(raw_mod.lower())
            if norm_mod is not None:
                log_incidencia(
                    row["raw_record_id"],
                    row["application_id"],
                    "4. Consistencia",
                    "CON-01: Modalidad fuera del catálogo canónico ('Remoto'/'Híbrido'/'Presencial')",
                    "modality",
                    raw_mod,
                    f"ESTANDARIZACION_CATALOGO -> {norm_mod}",
                    "Se homologa el sinónimo operativo al dominio canónico de YEB sin perder el registro.",
                )
                df.at[idx, "modality"] = norm_mod
            else:
                log_incidencia(
                    row["raw_record_id"],
                    row["application_id"],
                    "4. Consistencia",
                    "CON-01: Modalidad irreconocible o prohibida",
                    "modality",
                    raw_mod,
                    "EXCLUSION_MODALIDAD_INVALIDA",
                    "El valor no corresponde a ninguna modalidad válida ('Remoto', 'Híbrido', 'Presencial').",
                )
                invalid_consistency_indices.append(idx)
                continue

        # 4B. Coherencia edad (16-17) vs bandera is_protected_minor
        age_int = int(row["candidate_age_num"])
        is_minor_bool = str(row["is_protected_minor"]).strip().lower() == "true"
        expected_minor_bool = age_int < 18
        if is_minor_bool != expected_minor_bool:
            log_incidencia(
                row["raw_record_id"],
                row["application_id"],
                "4. Consistencia",
                "CON-02: Contradicción entre candidate_age (16-17) y bandera is_protected_minor",
                "is_protected_minor",
                f"age={age_int}, is_protected_minor={row['is_protected_minor']}",
                f"RECTIFICACION_REGLA_DERIVADA -> {expected_minor_bool}",
                "Se recalcula determinísticamente is_protected_minor = (candidate_age < 18) para proteger al menor (LEG-001).",
            )
            df.at[idx, "is_protected_minor"] = str(expected_minor_bool)
            if expected_minor_bool:
                df.at[idx, "minor_permit_verified"] = "True"

        # 4C. Normalización ortográfica de ciudad
        raw_city = str(row["ciudad_candidato"])
        clean_city = mapa_ciudades.get(raw_city.strip().lower(), raw_city.strip())
        if raw_city != clean_city:
            log_incidencia(
                row["raw_record_id"],
                row["application_id"],
                "4. Consistencia",
                "CON-03: Ciudad con formato o tildes inconsistentes",
                "ciudad_candidato",
                raw_city,
                f"NORMALIZACION_TEXTO -> {clean_city}",
                "Se estandariza la grafía oficial del municipio colombiano.",
            )
            df.at[idx, "ciudad_candidato"] = clean_city

        # Limpieza de espacios laterales en job_title
        df.at[idx, "job_title"] = str(row["job_title"]).strip()

    df = df.drop(index=invalid_consistency_indices).copy()
    consistency_removed = len(invalid_consistency_indices)

    # =========================================================================
    # PASO 5: DIMENSIÓN 5 (OPORTUNIDAD) — VENTANA TEMPORAL VÁLIDA DE POSTULACIÓN
    # =========================================================================
    df["pub_dt"] = pd.to_datetime(df["job_published_at"], errors="coerce")
    df["app_dt"] = pd.to_datetime(df["applied_at"], errors="coerce")
    df["upd_dt"] = pd.to_datetime(df["updated_at"], errors="coerce")

    max_valid_date = pd.Timestamp("2026-12-31")
    invalid_timeliness_indices: list[int] = []

    for idx, row in df.iterrows():
        pub_dt = row["pub_dt"]
        app_dt = row["app_dt"]
        upd_dt = row["upd_dt"]

        if pd.isna(pub_dt) or pd.isna(app_dt) or pd.isna(upd_dt):
            log_incidencia(
                row["raw_record_id"],
                row["application_id"],
                "5. Oportunidad",
                "OPO-01: Fecha corrupta o nula en cronología de postulación",
                "applied_at / updated_at",
                f"pub={row['job_published_at']}, app={row['applied_at']}, upd={row['updated_at']}",
                "EXCLUSION_FECHA_INVALIDA",
                "Fechas ilegibles impiden calcular tiempos de respuesta del embudo.",
            )
            invalid_timeliness_indices.append(idx)
            continue

        if app_dt < pub_dt:
            log_incidencia(
                row["raw_record_id"],
                row["application_id"],
                "5. Oportunidad",
                "OPO-02: Postulación anterior a la fecha de publicación de la vacante (applied_at < job_published_at)",
                "applied_at",
                f"applied_at={row['applied_at']} < job_published_at={row['job_published_at']}",
                "EXCLUSION_ANACRONISMO_POSTULACION",
                "Inconsistencia cronológica: no es posible postularse antes de la apertura de la vacante.",
            )
            invalid_timeliness_indices.append(idx)
            continue

        if upd_dt < app_dt:
            log_incidencia(
                row["raw_record_id"],
                row["application_id"],
                "5. Oportunidad",
                "OPO-03: Fecha de actualización anterior a la fecha de postulación (updated_at < applied_at)",
                "updated_at",
                f"updated_at={row['updated_at']} < applied_at={row['applied_at']}",
                "EXCLUSION_CRONOLOGIA_INVERTIDA",
                "El cambio de estado no puede ocurrir antes del envío de la postulación.",
            )
            invalid_timeliness_indices.append(idx)
            continue

        if app_dt > max_valid_date or upd_dt > max_valid_date:
            log_incidencia(
                row["raw_record_id"],
                row["application_id"],
                "5. Oportunidad",
                "OPO-04: Fecha fuera de la ventana temporal operativa del corte analítico",
                "applied_at",
                row["applied_at"],
                "EXCLUSION_FECHA_FUTURA",
                "El registro presenta marcas de tiempo futuras fuera del periodo auditado.",
            )
            invalid_timeliness_indices.append(idx)

    df = df.drop(index=invalid_timeliness_indices).copy()
    timeliness_removed = len(invalid_timeliness_indices)

    # =========================================================================
    # PASO 6: DIMENSIÓN 6 (TRAZABILIDAD) — ORIGEN Y LOTE DE AUDITORÍA
    # =========================================================================
    invalid_traceability_indices: list[int] = []
    for idx, row in df.iterrows():
        src = str(row["source_system"]).strip()
        batch = str(row["audit_batch_id"]).strip()
        if src == "" or batch == "":
            campo_traza = "source_system" if src == "" else "audit_batch_id"
            log_incidencia(
                row["raw_record_id"],
                row["application_id"],
                "6. Trazabilidad",
                "TRA-01: Registro huérfano sin metadatos de origen o lote de auditoría",
                campo_traza,
                "VACÍO / NULL",
                "EXCLUSION_SIN_TRAZABILIDAD",
                "Todo registro procesado debe conservar vínculo verificable con su extracción y lote de origen.",
            )
            invalid_traceability_indices.append(idx)

    df = df.drop(index=invalid_traceability_indices).copy()
    traceability_removed = len(invalid_traceability_indices)

    # =========================================================================
    # CONSOLIDACIÓN DE DATASET PREPARADO Y VALIDACIÓN DE INVARIANTES (CP-045B)
    # =========================================================================
    df["candidate_age"] = df["candidate_age_num"].astype(int)
    df["salary_min_offered"] = df["salary_min_num"].astype(int)
    df["salary_max_offered"] = df["salary_max_num"].astype(int)
    df["expected_salary_cop"] = df["expected_salary_num"].astype(int)
    df["is_protected_minor"] = df["candidate_age"] < 18
    df["minor_permit_verified"] = df["minor_permit_verified"].astype(str).str.lower() == "true"
    df["profile_completeness_pct"] = pd.to_numeric(df["profile_completeness_pct"], errors="coerce").fillna(80).astype(int)

    # Anonimización de canales de contacto para el dataset analítico procesado
    df["email_contacto_estado"] = "VERIFICADO_ANON"
    df["telefono_contacto_estado"] = "VERIFICADO_ANON"

    columnas_finales = [
        "raw_record_id",
        "application_id",
        "job_id",
        "candidate_hash",
        "documento_hash",
        "rol",
        "candidate_age",
        "is_protected_minor",
        "minor_permit_verified",
        "ciudad_candidato",
        "education_level",
        "primary_skill",
        "email_contacto_estado",
        "telefono_contacto_estado",
        "job_title",
        "sector",
        "modality",
        "salary_min_offered",
        "salary_max_offered",
        "expected_salary_cop",
        "status",
        "job_published_at",
        "applied_at",
        "updated_at",
        "profile_completeness_pct",
        "source_system",
        "audit_batch_id",
    ]

    df_prepared = df[columnas_finales].reset_index(drop=True)
    df_incidencias = pd.DataFrame(incidencias)

    # Aserciones formales de calidad sobre el dataset preparado (CP-045 / CP-045B)
    assert not df_prepared.duplicated(subset=["application_id"]).any(), "Quedaron application_id duplicados"
    assert not df_prepared.duplicated(subset=["documento_hash", "rol"]).any(), "Quedaron cédulas duplicadas por rol"
    assert (df_prepared["candidate_age"].between(16, 24)).all(), "Existen edades fuera del rango [16, 24]"
    assert (df_prepared["expected_salary_cop"] >= 0).all(), "Existen salarios esperados negativos"
    assert (df_prepared["salary_min_offered"] >= 0).all(), "Existen salarios mínimos negativos"
    assert (df_prepared["salary_max_offered"] >= df_prepared["salary_min_offered"]).all(), "Rango salarial invertido"
    assert set(df_prepared["modality"].unique()).issubset(set(MODALIDADES_CANONICAS)), "Modalidades fuera de catálogo"

    processed_path = processed_dir / "yeb_postulaciones_preparado.csv"
    incidencias_path = outputs_dir / "incidencias_calidad.csv"

    df_prepared.to_csv(processed_path, index=False, encoding="utf-8")
    df_incidencias.to_csv(incidencias_path, index=False, encoding="utf-8")

    summary = {
        "total_raw_rows": total_raw_rows,
        "exact_dups_removed": exact_dups_removed,
        "conflicts_isolated": conflicts_isolated,
        "incomplete_removed": incomplete_removed,
        "validity_removed": validity_removed,
        "consistency_removed": consistency_removed,
        "timeliness_removed": timeliness_removed,
        "traceability_removed": traceability_removed,
        "total_prepared_rows": len(df_prepared),
        "total_incidencias_logged": len(df_incidencias),
        "processed_path": processed_path,
        "incidencias_path": incidencias_path,
        "quarantine_path": quarantine_path,
    }
    return df_prepared, df_incidencias, summary


def main() -> int:
    analytics_root = resolve_analytics_root()
    raw_dir = analytics_root / "data" / "raw"
    processed_dir = analytics_root / "data" / "processed"
    outputs_dir = analytics_root / "outputs"

    print("=" * 80)
    print("1. GENERACIÓN DE DATASET CON ERRORES INDUCIDOS (CA-ISS-05-01)")
    print("=" * 80)
    dirty_csv_path = build_dirty_dataset(raw_dir)
    print(f"  [OK] Dataset crudo con errores inducidos : {dirty_csv_path.relative_to(analytics_root)}")

    print("\n" + "=" * 80)
    print("2. PERFILAMIENTO DE LAS 6 DIMENSIONES DE CALIDAD Y TRATAMIENTO (CA-ISS-05-02)")
    print("=" * 80)
    df_prepared, df_incidencias, summary = profile_and_clean_dataset(
        dirty_csv_path, processed_dir, outputs_dir
    )

    print(f"  Filas totales recibidas (raw)             : {summary['total_raw_rows']}")
    print(f"  [-] Duplicados exactos descartados        : {summary['exact_dups_removed']}")
    print(f"  [!] Conflictos de ID aislados (cuarentena): {summary['conflicts_isolated']}")
    print(f"  [-] Excluidos por Completitud (contacto)  : {summary['incomplete_removed']}")
    print(f"  [-] Excluidos por Validez (edad/salario)  : {summary['validity_removed']}")
    print(f"  [-] Excluidos por Consistencia (modalidad): {summary['consistency_removed']}")
    print(f"  [-] Excluidos por Oportunidad (fechas)    : {summary['timeliness_removed']}")
    print(f"  [-] Excluidos por Trazabilidad (origen)   : {summary['traceability_removed']}")
    print(f"  [=] Filas válidas en dataset preparado    : {summary['total_prepared_rows']}")
    print(f"  [#] Total incidencias documentadas        : {summary['total_incidencias_logged']}")

    print("\n  Resumen de incidencias por Dimensión de Calidad:")
    resumen_dim = df_incidencias.groupby(["dimension_calidad", "accion_tratamiento"]).size().reset_index(name="conteo")
    for _, r in resumen_dim.iterrows():
        print(f"    • {r['dimension_calidad']:<20} | {r['accion_tratamiento']:<38} : {r['conteo']} caso(s)")

    print("\n" + "=" * 80)
    print("3. VERIFICACIÓN DE ENTREGABLES E INVARIANTES DE CALIDAD (CA-ISS-05-03 / CP-045B)")
    print("=" * 80)
    print(f"  [OK] Dataset preparado : {summary['processed_path'].relative_to(analytics_root)} (shape={df_prepared.shape})")
    print(f"  [OK] Reporte calidad   : {summary['incidencias_path'].relative_to(analytics_root)} (shape={df_incidencias.shape})")
    print(f"  [OK] Cuarentena IDs    : {summary['quarantine_path'].relative_to(analytics_root)}")
    print(f"  [OK] Rango de edad verificado       : [{df_prepared['candidate_age'].min()}, {df_prepared['candidate_age'].max()}] años (100% en 16..24)")
    print(f"  [OK] Salario esperado mínimo (COP)  : ${df_prepared['expected_salary_cop'].min():,d} (0 salarios negativos)")
    print(f"  [OK] Modalidades normalizadas       : {sorted(df_prepared['modality'].unique().tolist())}")
    print("\n[RESULTADO] Issue #05 verificado satisfactoriamente (CP-045 y CP-045B: PASA).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
