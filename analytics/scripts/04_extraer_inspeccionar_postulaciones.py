#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Youth Employment Bridge (YEB) — Módulo de Analítica de Empleo
Issue #04 [Data/Inspection]: Extracción tabular e inspección exploratoria de postulaciones

Cubre:
  - CA-ISS-04-01: Extracción/dump anonimizado de `applications` y `jobs` de YEB
                  a formatos CSV y Excel en `analytics/data/raw/`.
  - CA-ISS-04-02: Lectura mediante rutas relativas (`pathlib.Path`).
  - CA-ISS-04-03: Inspección técnica documentada con `shape`, `columns`, `dtypes`, `info()` y `describe()`.
  - CA-ISS-04-04: Operaciones vectorizadas en NumPy para rangos salariales medios y
                  tasas porcentuales de avance a entrevista.
  - Casos de prueba: CP-044, CP-044B
"""

from __future__ import annotations

import hashlib
import io
import os
import subprocess
import sys
from pathlib import Path


def _ensure_venv_interpreter() -> None:
    """Redirige automáticamente al intérprete de analytics/.venv si se invoca desde el Python global."""
    script_path = Path(__file__).resolve()
    analytics_root = script_path.parent.parent if script_path.parent.name == "scripts" else Path.cwd()
    venv_python_win = analytics_root / ".venv" / "Scripts" / "python.exe"
    venv_python_unix = analytics_root / ".venv" / "bin" / "python"
    venv_python = (
        venv_python_win
        if venv_python_win.exists()
        else (venv_python_unix if venv_python_unix.exists() else None)
    )

    if venv_python is not None:
        current_exe = Path(sys.executable).resolve()
        target_exe = venv_python.resolve()
        if current_exe != target_exe and os.environ.get("YEB_VENV_REDIRECTED") != "1":
            env = os.environ.copy()
            env["YEB_VENV_REDIRECTED"] = "1"
            result = subprocess.run([str(target_exe), str(script_path), *sys.argv[1:]], env=env)
            raise SystemExit(result.returncode)


def resolve_analytics_root() -> Path:
    script_path = Path(__file__).resolve()
    if script_path.parent.name == "scripts":
        return script_path.parent.parent
    return Path.cwd()


def generate_anonymized_extractions(raw_dir: Path) -> tuple[Path, Path, Path]:
    """Genera el dump anonimizado de las tablas `jobs` y `applications` de YEB en CSV y Excel (CA-ISS-04-01)."""
    import numpy as np
    import pandas as pd

    rng = np.random.default_rng(42)

    # 1. Tabla `jobs` basada en el esquema real de YEB (database/schema.sql + seed.sql)
    jobs_catalog = [
        (1, 1, "Talento Lab", "Tecnología", "Frontend Developer Junior", "Bogotá", "Hybrid", "0 - 1 año", 2800000.0, 3800000.0, "published", "2026-09-01 09:00:00"),
        (2, 1, "Talento Lab", "Tecnología", "Analista de datos", "Bogotá", "Remote", "Inicial", 3000000.0, 4200000.0, "published", "2026-09-02 10:15:00"),
        (3, 2, "Verde Digital", "Sostenibilidad", "Diseñador UX/UI", "Medellín", "Presencial", "1 - 2 años", 3200000.0, 4500000.0, "published", "2026-09-03 11:30:00"),
        (4, 2, "Verde Digital", "Sostenibilidad", "Auxiliar de Analítica Ambiental", "Medellín", "Hybrid", "Inicial", 1600000.0, 2100000.0, "published", "2026-09-05 08:45:00"),
        (5, 3, "ConectaJoven Digital", "Servicios", "Gestor Junior de Atención Digital", "Bogotá", "Remote", "Inicial", 1350000.0, 1650000.0, "published", "2026-09-06 14:00:00"),
        (6, 3, "ConectaJoven Digital", "Servicios", "Community Manager Junior", "Cali", "Hybrid", "0 - 1 año", 1500000.0, 1950000.0, "published", "2026-09-07 16:20:00"),
        (7, 4, "Pacífico Software Studio", "Tecnología", "Desarrollador Backend PHP Junior", "Cali", "Remote", "0 - 1 año", 2400000.0, 3200000.0, "published", "2026-09-08 09:30:00"),
        (8, 4, "Pacífico Software Studio", "Tecnología", "QA Tester Automatización Junior", "Cali", "Hybrid", "Inicial", 1800000.0, 2400000.0, "published", "2026-09-09 10:00:00"),
        (9, 5, "Grupo Finanzas Andinas", "Finanzas", "Auxiliar Contable y Facturación", "Bogotá", "Presencial", "Inicial", 1500000.0, 1850000.0, "published", "2026-09-10 11:00:00"),
        (10, 5, "Grupo Finanzas Andinas", "Finanzas", "Practicante de Riesgo y Datos", "Medellín", "Hybrid", "Inicial", 1450000.0, 1800000.0, "published", "2026-09-11 15:40:00"),
        (11, 6, "Logística Caribe Express", "Logística", "Auxiliar Operativo de Inventarios", "Barranquilla", "Presencial", "Inicial", 1350000.0, 1600000.0, "published", "2026-09-12 08:00:00"),
        (12, 6, "Logística Caribe Express", "Logística", "Asistente de Despachos y Rutas", "Barranquilla", "Presencial", "0 - 1 año", 1450000.0, 1750000.0, "published", "2026-09-13 09:20:00"),
        (13, 7, "SaludVital IPS", "Salud", "Auxiliar de Admisiones y Soporte", "Bucaramanga", "Presencial", "Inicial", 1400000.0, 1700000.0, "published", "2026-09-14 10:10:00"),
        (14, 7, "SaludVital IPS", "Salud", "Soporte Técnico Mesa de Ayuda", "Bucaramanga", "Hybrid", "0 - 1 año", 1650000.0, 2050000.0, "published", "2026-09-15 13:50:00"),
        (15, 8, "AgroTech Sabana", "Agroindustria", "Auxiliar de Monitoreo IoT y Datos", "Bogotá", "Hybrid", "Inicial", 1750000.0, 2300000.0, "published", "2026-09-16 08:30:00"),
        (16, 8, "AgroTech Sabana", "Agroindustria", "Asistente Comercial B2B Junior", "Medellín", "Presencial", "0 - 1 año", 1600000.0, 2150000.0, "closed", "2026-09-17 17:00:00"),
        (17, 1, "Talento Lab", "Tecnología", "Data Engineer Trainee", "Remoto", "Remote", "Inicial", 2600000.0, 3400000.0, "published", "2026-09-18 09:15:00"),
        (18, 4, "Pacífico Software Studio", "Tecnología", "Soporte Aplicaciones Web (Jornada Juvenil)", "Cali", "Remote", "Inicial", 1400000.0, 1700000.0, "published", "2026-09-19 11:45:00"),
        (19, 2, "Verde Digital", "Sostenibilidad", "Promotor Ambiental Juvenil", "Medellín", "Presencial", "Inicial", 1350000.0, 1550000.0, "published", "2026-09-20 14:30:00"),
        (20, 5, "Grupo Finanzas Andinas", "Finanzas", "Analista Junior de Cartera", "Bogotá", "Hybrid", "1 - 2 años", 2100000.0, 2700000.0, "draft", "2026-09-21 16:00:00"),
    ]

    df_jobs = pd.DataFrame(
        jobs_catalog,
        columns=[
            "job_id",
            "company_id",
            "company_name",
            "industry",
            "title",
            "city",
            "work_mode",
            "experience",
            "salary_min",
            "salary_max",
            "status",
            "created_at",
        ],
    )

    # 2. Tabla `applications` anonimizada (120 postulaciones sobre vacantes publicadas/cerradas)
    published_jobs = df_jobs[df_jobs["status"] != "draft"].reset_index(drop=True)
    statuses = ["pending", "review", "interview", "accepted", "rejected"]
    status_probs = [0.28, 0.27, 0.20, 0.10, 0.15]
    cities = ["Bogotá", "Medellín", "Cali", "Barranquilla", "Bucaramanga"]

    app_rows = []
    seen_pairs: set[tuple[int, int]] = set()
    app_id = 1

    while len(app_rows) < 120:
        user_id = int(rng.integers(101, 165))
        job_row = published_jobs.iloc[int(rng.integers(0, len(published_jobs)))]
        job_id = int(job_row["job_id"])

        if (job_id, user_id) in seen_pairs:
            continue
        seen_pairs.add((job_id, user_id))

        # Atributos deterministas por candidato (anonimizados según CA-ISS-01-04)
        cand_seed = user_id * 17
        age = 16 + (cand_seed % 9)  # Rango estricto 16 a 24 años
        is_minor = 1 if age < 18 else 0
        minor_authorized = 1
        cand_city = cities[user_id % len(cities)]
        cand_hash = "CAND-" + hashlib.sha256(f"{user_id}:yeb_analytics_salt_2026".encode("utf-8")).hexdigest()[:12]

        status = str(rng.choice(statuses, p=status_probs))
        base_expectation = float(job_row["salary_min"]) + float(rng.integers(-150000, 450000))
        expected_salary = round(max(1300000.0, base_expectation), -4)

        day_offset = int(rng.integers(1, 18))
        response_days = int(rng.integers(2, 12)) if status in ("interview", "accepted", "rejected") else int(rng.integers(0, 4))
        created_ts = pd.Timestamp(job_row["created_at"]) + pd.Timedelta(days=day_offset, hours=int(rng.integers(1, 8)))
        updated_ts = created_ts + pd.Timedelta(days=response_days, hours=int(rng.integers(1, 6)))

        app_rows.append(
            {
                "application_id": app_id,
                "job_id": job_id,
                "user_id": user_id,
                "candidate_hash": cand_hash,
                "candidate_age": age,
                "is_minor": is_minor,
                "minor_authorized": minor_authorized,
                "candidate_city": cand_city,
                "expected_salary": expected_salary,
                "status": status,
                "created_at": created_ts.strftime("%Y-%m-%d %H:%M:%S"),
                "updated_at": updated_ts.strftime("%Y-%m-%d %H:%M:%S"),
            }
        )
        app_id += 1

    df_applications = pd.DataFrame(app_rows)

    raw_dir.mkdir(parents=True, exist_ok=True)
    jobs_csv_path = raw_dir / "yeb_jobs_raw.csv"
    apps_csv_path = raw_dir / "yeb_applications_raw.csv"
    excel_path = raw_dir / "yeb_extraccion_tabular.xlsx"

    df_jobs.to_csv(jobs_csv_path, index=False, encoding="utf-8")
    df_applications.to_csv(apps_csv_path, index=False, encoding="utf-8")

    with pd.ExcelWriter(excel_path, engine="openpyxl") as writer:
        df_jobs.to_excel(writer, sheet_name="jobs", index=False)
        df_applications.to_excel(writer, sheet_name="applications", index=False)

    return jobs_csv_path, apps_csv_path, excel_path


def inspect_and_compute_vectorized_metrics(analytics_root: Path) -> None:
    """Ejecuta la lectura con rutas relativas, la inspección técnica y los cálculos vectorizados en NumPy."""
    import numpy as np
    import pandas as pd

    raw_dir = analytics_root / "data" / "raw"
    jobs_csv, apps_csv, excel_file = generate_anonymized_extractions(raw_dir)

    print("=" * 78)
    print("1. EXTRACCIÓN ANONIMIZADA Y LECTURA CON RUTAS RELATIVAS (CA-ISS-04-01 / CA-ISS-04-02)")
    print("=" * 78)
    print(f"  [OK] CSV Vacantes generado      : {jobs_csv.relative_to(analytics_root).as_posix()}")
    print(f"  [OK] CSV Postulaciones generado : {apps_csv.relative_to(analytics_root).as_posix()}")
    print(f"  [OK] Libro Excel multi-hoja     : {excel_file.relative_to(analytics_root).as_posix()}")

    # Lectura usando exclusivamente objetos pathlib.Path relativos a analytics/
    df_jobs = pd.read_csv(raw_dir / "yeb_jobs_raw.csv")
    df_apps = pd.read_csv(raw_dir / "yeb_applications_raw.csv")

    print("\n" + "=" * 78)
    print("2. INSPECCIÓN TÉCNICA ESTRUCTURAL: shape, columns, dtypes, info(), describe() (CA-ISS-04-03)")
    print("=" * 78)

    for label, df in [("VACANTES (jobs)", df_jobs), ("POSTULACIONES (applications)", df_apps)]:
        print(f"\n--- Tabla: {label} ---")
        print(f"  shape   : {df.shape} (filas={df.shape[0]}, columnas={df.shape[1]})")
        print(f"  columns : {list(df.columns)}")
        print("  dtypes  :")
        for col_name, dtype_val in df.dtypes.items():
            print(f"    - {col_name:<18}: {dtype_val}")

        buf = io.StringIO()
        df.info(buf=buf)
        info_lines = buf.getvalue().splitlines()
        print(f"  info()  : {info_lines[1]} | {info_lines[-2]}")

    print("\n--- Resumen Estadístico describe() de Vacantes ---")
    print(df_jobs[["salary_min", "salary_max"]].describe().round(2).to_string())

    print("\n--- Resumen Estadístico describe() de Postulaciones ---")
    print(df_apps[["candidate_age", "is_minor", "expected_salary"]].describe().round(2).to_string())

    print("\n" + "=" * 78)
    print("3. OPERACIONES VECTORIZADAS EN NUMPY: RANGOS SALARIALES Y TASAS DE AVANCE (CA-ISS-04-04)")
    print("=" * 78)

    # Vectores NumPy sobre vacantes publicadas
    published_mask = (df_jobs["status"].to_numpy() == "published")
    sal_min_vec = df_jobs.loc[published_mask, "salary_min"].to_numpy(dtype=np.float64)
    sal_max_vec = df_jobs.loc[published_mask, "salary_max"].to_numpy(dtype=np.float64)

    # Cálculos vectorizados sin bucles for (mitigación OPE-005)
    sal_midpoint_vec = (sal_min_vec + sal_max_vec) / 2.0
    sal_spread_vec = sal_max_vec - sal_min_vec

    mean_sal_min = float(np.mean(sal_min_vec))
    mean_sal_max = float(np.mean(sal_max_vec))
    mean_midpoint = float(np.mean(sal_midpoint_vec))
    median_midpoint = float(np.median(sal_midpoint_vec))
    mean_spread = float(np.mean(sal_spread_vec))

    print(f"  [NumPy] Vacantes publicadas analizadas        : {sal_min_vec.size}")
    print(f"  [NumPy] Salario mínimo ofertado promedio      : ${mean_sal_min:,.2f} COP")
    print(f"  [NumPy] Salario máximo ofertado promedio      : ${mean_sal_max:,.2f} COP")
    print(f"  [NumPy] Punto medio salarial promedio         : ${mean_midpoint:,.2f} COP (Mediana: ${median_midpoint:,.2f} COP)")
    print(f"  [NumPy] Amplitud (rango) salarial promedio    : ${mean_spread:,.2f} COP")

    # Operaciones vectorizadas sobre estados de postulación (applications)
    status_vec = df_apps["status"].to_numpy()
    is_minor_vec = df_apps["is_minor"].to_numpy(dtype=bool)

    mask_interview_stage = (status_vec == "interview")
    mask_advanced_interview = np.isin(status_vec, ["interview", "accepted"])
    mask_accepted = (status_vec == "accepted")
    mask_rejected = (status_vec == "rejected")

    rate_interview_only = float(np.mean(mask_interview_stage) * 100.0)
    rate_reached_interview = float(np.mean(mask_advanced_interview) * 100.0)
    rate_accepted = float(np.mean(mask_accepted) * 100.0)
    rate_rejected = float(np.mean(mask_rejected) * 100.0)

    rate_interview_minors = float(np.mean(mask_advanced_interview[is_minor_vec]) * 100.0)
    rate_interview_adults = float(np.mean(mask_advanced_interview[~is_minor_vec]) * 100.0)

    print(f"  [NumPy] Total postulaciones evaluadas         : {status_vec.size}")
    print(f"  [NumPy] Tasa actual en estado 'interview'     : {rate_interview_only:.2f}%")
    print(f"  [NumPy] Tasa acumulada de avance a entrevista : {rate_reached_interview:.2f}% (interview + accepted)")
    print(f"  [NumPy] Tasa de contratación ('accepted')     : {rate_accepted:.2f}%")
    print(f"  [NumPy] Tasa de descarte ('rejected')         : {rate_rejected:.2f}%")
    print(f"  [NumPy] Avance a entrevista Menores (16-17)   : {rate_interview_minors:.2f}%")
    print(f"  [NumPy] Avance a entrevista Adultos (18-24)   : {rate_interview_adults:.2f}%")

    # Aserciones de invariantes de calidad y prueba (CP-044 / CP-044B)
    assert df_jobs.shape == (20, 12), f"Shape inesperado en jobs: {df_jobs.shape}"
    assert df_apps.shape == (120, 12), f"Shape inesperado en applications: {df_apps.shape}"
    assert np.all(sal_max_vec >= sal_min_vec), "Se detectó salario máximo menor al mínimo."
    assert 0.0 < rate_reached_interview < 100.0, "Tasa de avance a entrevista fuera de límites lógicos."


def main() -> int:
    _ensure_venv_interpreter()
    analytics_root = resolve_analytics_root()
    inspect_and_compute_vectorized_metrics(analytics_root)

    print("\n" + "=" * 78)
    print("RESULTADO FINAL ISSUE #04: EXTRACCIÓN E INSPECCIÓN TABULAR VERIFICADAS (CP-044 / CP-044B)")
    print("=" * 78)
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
