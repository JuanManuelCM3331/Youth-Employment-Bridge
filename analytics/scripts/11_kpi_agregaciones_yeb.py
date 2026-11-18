#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Issue #11 [Data/KPI]: Capa analítica Split–Apply–Combine y fichas técnicas de KPI para YEB
(Youth Employment Bridge - YEB).

Criterios de Aceptación cubiertos:
  - CA-ISS-11-01: Definición explícita del universo analizable (n=93) frente al total recibido (97),
    diferenciando size() (filas), count() (no nulos) y nunique() (candidatos/vacantes únicos).
  - CA-ISS-11-02: Agregaciones groupby().agg() con conteos, media, mediana y desviación estándar
    muestral (ddof=1), preservando NaN en grupos con un solo registro sin convertirlos en 0.
  - CA-ISS-11-03: Tablas de participación con value_counts(normalize=True, dropna=False) y tablas
    cruzadas con crosstab(..., normalize="index", margins=True).
  - CA-ISS-11-04: Tabla dinámica pivot_table() por ciudad y modalidad conservando celdas ausentes (NaN)
    sin reemplazarlas por ceros falsos.
  - CA-ISS-11-05: Verificación de las 3 Fichas Técnicas de KPI en docs/fichas_kpi_yeb.md y exportación
    consolidada a outputs/yeb_kpis_semana6.csv validando sumas totales con assert.
  - CA-ISS-11-06: Análisis escrito (150–200 palabras) sobre hallazgos descriptivos, grupos pequeños y
    la decisión humana no automatizable de aprobación legal de menores de edad.

Riesgos mitigados: NEG-009, TEC-016, LEG-001
Casos de prueba verificados: CP-051, CP-051B, CP-051C
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

ANALISIS_ESCRITO_KPI = (
    "El resumen descriptivo sobre el universo analizable de 93 postulaciones válidas al corte del "
    "2026-02-02 muestra una distribución equilibrada por modalidad (Remoto 34,41%, Híbrido 33,33% "
    "y Presencial 32,26%) y una tasa global de avance a entrevista o contratación del 39,78%, con "
    "un tiempo mediano de respuesta de 4,0 días sobre las 55 postulaciones respondidas y una brecha "
    "salarial media de -2,28% frente al punto medio ofertado. Al cruzar ciudad y modalidad aparecen "
    "nueve combinaciones vacías (NaN) y tres celdas con un único registro (Barranquilla-Híbrido, "
    "Bogotá-Remoto y Cali-Presencial), donde la desviación estándar muestral queda legítimamente en "
    "NaN; estos grupos pequeños describen casos puntuales y no permiten comparar ni inferir efectos "
    "causales. Asimismo, aunque la cohorte de menores protegidos (16–17 años, n=21) registra un "
    "avance descriptivo del 28,57% frente al 43,06% en adultos jóvenes (18–24 años, n=72), ninguna "
    "métrica del panel puede automatizar la habilitación ni el descarte de un menor: la verificación "
    "de la autorización parental, el registro civil y el permiso de trabajo ante el Ministerio del "
    "Trabajo exige revisión humana obligatoria del administrador para proteger los derechos laborales "
    "juveniles conforme a la ley."
)


def resolve_analytics_root() -> Path:
    analytics_root = Path(__file__).resolve().parent.parent
    for sub in ("data/processed", "outputs"):
        assert (analytics_root / sub).exists(), f"No se encontró analytics/{sub}/"
    return analytics_root


def sha256_archivo(ruta: Path) -> str:
    return hashlib.sha256(ruta.read_bytes()).hexdigest()


def cargar_fuente_semana5(analytics_root: Path) -> tuple[pd.DataFrame, Path, str]:
    """Carga data/processed/yeb_postulaciones_transformado.csv registrando su huella SHA-256 (TEC-016)."""
    ruta = analytics_root / "data" / "processed" / "yeb_postulaciones_transformado.csv"
    assert ruta.exists() and ruta.is_file(), f"No existe la fuente de Semana 5: {ruta}"
    huella = sha256_archivo(ruta)
    df = pd.read_csv(ruta, parse_dates=["job_published_at", "applied_at", "updated_at"])
    for col in ("is_protected_minor", "minor_permit_verified"):
        df[col] = df[col].astype(str).str.strip().str.lower().eq("true")
    df["grupo_etario"] = df["is_protected_minor"].map(
        {True: "Menor protegido (16-17)", False: "Joven adulto (18-24)"}
    )
    return df, ruta, huella


def construir_tabla_groupby(df_u: pd.DataFrame, cols_grupo: list[str], nombre_dimension: str) -> pd.DataFrame:
    """CA-ISS-11-02: aplica Split-Apply-Combine conservando NaN en grupos con count <= 1."""
    agrupado = df_u.groupby(cols_grupo, dropna=False, observed=False).agg(
        n_filas_size=("application_id", "size"),
        n_candidatos_nunique=("candidate_hash", "nunique"),
        n_vacantes_nunique=("job_id", "nunique"),
        n_respondidas_count=("tiempo_respuesta_dias", "count"),
        tiempo_resp_media=("tiempo_respuesta_dias", "mean"),
        tiempo_resp_mediana=("tiempo_respuesta_dias", "median"),
        tiempo_resp_std=("tiempo_respuesta_dias", "std"),
        n_salarios_count=("expected_salary_cop", "count"),
        salario_esp_media=("expected_salary_cop", "mean"),
        salario_esp_mediana=("expected_salary_cop", "median"),
        salario_esp_std=("expected_salary_cop", "std"),
        brecha_salarial_media_pct=("brecha_salarial_aspiracion_pct", "mean"),
        brecha_salarial_mediana_pct=("brecha_salarial_aspiracion_pct", "median"),
        brecha_salarial_std_pct=("brecha_salarial_aspiracion_pct", "std"),
        n_entrevista_o_contratado=("status", lambda s: int(s.isin(["interview", "accepted"]).sum())),
        tasa_entrevista_pct=("status", lambda s: round(100.0 * float(s.isin(["interview", "accepted"]).mean()), 2) if len(s) else np.nan),
        n_contratados=("status", lambda s: int((s == "accepted").sum())),
        tasa_contratacion_pct=("status", lambda s: round(100.0 * float((s == "accepted").mean()), 2) if len(s) else np.nan),
    )
    agrupado = agrupado[agrupado["n_filas_size"] > 0].reset_index()

    # Invariante CA-ISS-11-02: si count <= 1, la desviación estándar muestral DEBE ser NaN, nunca 0.
    mask_resp_1 = agrupado["n_respondidas_count"] <= 1
    assert agrupado.loc[mask_resp_1, "tiempo_resp_std"].isna().all(), (
        f"[{nombre_dimension}] Se reemplazó NaN por número en tiempo_resp_std para grupos con count <= 1"
    )
    mask_sal_1 = agrupado["n_salarios_count"] <= 1
    assert agrupado.loc[mask_sal_1, "salario_esp_std"].isna().all(), (
        f"[{nombre_dimension}] Se reemplazó NaN por número en salario_esp_std para grupos con count <= 1"
    )

    agrupado.insert(0, "dimension", nombre_dimension)
    agrupado.insert(
        1,
        "segmento",
        agrupado[cols_grupo].astype(str).agg(" | ".join, axis=1),
    )
    return agrupado


def construir_kpis_consolidados(df_u: pd.DataFrame) -> pd.DataFrame:
    """Construye la tabla consolidada outputs/yeb_kpis_semana6.csv y valida sumas con assert (CA-ISS-11-05)."""
    df_global = df_u.assign(_global="Total universo analizable")
    particiones = [
        (["_global"], "Global"),
        (["ciudad_normalizada"], "Ciudad"),
        (["modality"], "Modalidad"),
        (["grupo_etario"], "Grupo etario"),
        (["ciudad_normalizada", "modality"], "Ciudad x Modalidad"),
        (["ciudad_normalizada", "grupo_etario"], "Ciudad x Grupo etario"),
        (["ciudad_normalizada", "modality", "grupo_etario"], "Ciudad x Modalidad x Grupo etario"),
    ]
    tablas = []
    universo_n = len(df_u)
    respondidas_n = int(df_u["tiempo_respuesta_dias"].count())

    for cols, nombre in particiones:
        fuente = df_global if cols == ["_global"] else df_u
        t = construir_tabla_groupby(fuente, cols, nombre)
        # Validación de cuadratura aritmética con assert (CA-ISS-11-05 / TEC-016)
        assert int(t["n_filas_size"].sum()) == universo_n, (
            f"Descuadre de size() en {nombre}: {t['n_filas_size'].sum()} != {universo_n}"
        )
        assert int(t["n_respondidas_count"].sum()) == respondidas_n, (
            f"Descuadre de count(tiempo_respuesta_dias) en {nombre}: {t['n_respondidas_count'].sum()} != {respondidas_n}"
        )
        assert int(t["n_salarios_count"].sum()) == universo_n, (
            f"Descuadre de count(expected_salary_cop) en {nombre}: {t['n_salarios_count'].sum()} != {universo_n}"
        )
        tablas.append(t)

    columnas_salida = [
        "dimension", "segmento",
        "n_filas_size", "n_candidatos_nunique", "n_vacantes_nunique",
        "n_respondidas_count", "tiempo_resp_media", "tiempo_resp_mediana", "tiempo_resp_std",
        "n_salarios_count", "salario_esp_media", "salario_esp_mediana", "salario_esp_std",
        "brecha_salarial_media_pct", "brecha_salarial_mediana_pct", "brecha_salarial_std_pct",
        "n_entrevista_o_contratado", "tasa_entrevista_pct", "n_contratados", "tasa_contratacion_pct",
    ]
    consolidado = pd.concat([t[columnas_salida] for t in tablas], ignore_index=True)
    cols_redondeo = [
        "tiempo_resp_media", "tiempo_resp_mediana", "tiempo_resp_std",
        "salario_esp_media", "salario_esp_mediana", "salario_esp_std",
        "brecha_salarial_media_pct", "brecha_salarial_mediana_pct", "brecha_salarial_std_pct",
    ]
    consolidado[cols_redondeo] = consolidado[cols_redondeo].round(2)
    return consolidado


def verificar_fichas_kpi(analytics_root: Path) -> list[Path]:
    """CA-ISS-11-05 / CP-051C: verifica la existencia y completitud de las 3 Fichas Técnicas de KPI."""
    rutas = [
        analytics_root / "docs" / "fichas_kpi_yeb.md",
        analytics_root / "docs - analisis" / "fichas_kpi_yeb.md",
    ]
    existentes = [r for r in rutas if r.exists() and r.is_file()]
    assert len(existentes) >= 1, "No se encontró docs/fichas_kpi_yeb.md"

    marcadores_obligatorios = [
        "Ficha Técnica #01",
        "Ficha Técnica #02",
        "Ficha Técnica #03",
        "KPI-YEB-01",
        "KPI-YEB-02",
        "KPI-YEB-03",
        "Fórmula matemática",
        "Numerador",
        "Denominador",
        "Unidad de medida",
        "Fecha de corte",
        "Universo",
        "Exclusiones",
        "Advertencia de uso no causal",
        "2026-02-02",
    ]
    for ruta in existentes:
        contenido = ruta.read_text(encoding="utf-8")
        for marcador in marcadores_obligatorios:
            assert marcador in contenido, f"Falta '{marcador}' en {ruta.relative_to(analytics_root)}"
    return existentes


def main() -> int:
    analytics_root = resolve_analytics_root()
    outputs_dir = analytics_root / "outputs"

    print("=" * 82)
    print("1. UNIVERSO ANALIZABLE Y DIFERENCIACIÓN size() vs. count() vs. nunique() (CA-ISS-11-01)")
    print("=" * 82)
    df_total, ruta_fuente, huella_antes = cargar_fuente_semana5(analytics_root)
    df_u = df_total.loc[df_total["origen_registro"] == "dataset_preparado"].copy()
    df_excl = df_total.loc[df_total["origen_registro"] != "dataset_preparado"].copy()
    fecha_corte = df_u["updated_at"].max()

    assert len(df_total) == 97 and len(df_u) == 93 and len(df_excl) == 4
    # En el total recibido (97 filas), size() != nunique() por los registros en cuarentena
    assert df_total["application_id"].size == 97
    assert df_total["application_id"].nunique() == 96
    assert df_total["documento_hash"].nunique() == 96
    # En el universo analizable (93 filas), size() == 93 pero count(tiempo_respuesta_dias) == 55 y nunique(job_id) == 18
    assert df_u["application_id"].size == 93
    assert df_u["tiempo_respuesta_dias"].count() == 55
    assert df_u["dias_sin_actualizar"].count() == 38
    assert df_u["candidate_hash"].nunique() == 93
    assert df_u["job_id"].nunique() == 18

    print(f"  [OK] Filas recibidas en {ruta_fuente.relative_to(analytics_root)} : {len(df_total)}")
    print(f"  [OK] Exclusiones (cuarentena_issue_05 / P1)                   : {len(df_excl)}")
    print(f"  [OK] Universo analizable (dataset_preparado, corte {fecha_corte.date()}) : size() = {len(df_u)}")
    print(
        f"  [OK] Diferenciación verificada en universo analizable:\n"
        f"       - size()  (total postulaciones)                = {df_u['application_id'].size}\n"
        f"       - count() (tiempo_respuesta_dias no nulos)     = {df_u['tiempo_respuesta_dias'].count()} (38 activas en NaN)\n"
        f"       - count() (dias_sin_actualizar no nulos)       = {df_u['dias_sin_actualizar'].count()} (55 respondidas en NaN)\n"
        f"       - nunique(candidate_hash) (candidatos únicos)  = {df_u['candidate_hash'].nunique()}\n"
        f"       - nunique(job_id)         (vacantes únicas)    = {df_u['job_id'].nunique()}"
    )

    print("\n" + "=" * 82)
    print("2. AGREGACIONES groupby().agg() Y PRESERVACIÓN DE NaN EN GRUPOS UNITARIOS (CA-ISS-11-02)")
    print("=" * 82)
    consolidado = construir_kpis_consolidados(df_u)
    cxm = consolidado.loc[consolidado["dimension"] == "Ciudad x Modalidad"]
    unitarios_sal = cxm.loc[cxm["n_salarios_count"] == 1]
    unitarios_resp = cxm.loc[cxm["n_respondidas_count"] == 1]
    sin_resp = cxm.loc[cxm["n_respondidas_count"] == 0]

    assert len(unitarios_sal) == 3 and unitarios_sal["salario_esp_std"].isna().all()
    assert len(unitarios_resp) == 2 and unitarios_resp["tiempo_resp_std"].isna().all()
    assert len(sin_resp) == 1 and sin_resp[["tiempo_resp_media", "tiempo_resp_mediana", "tiempo_resp_std"]].isna().all().all()

    print(f"  [OK] Grupos en 'Ciudad x Modalidad' con n_salarios_count == 1    : {unitarios_sal['segmento'].tolist()} -> salario_esp_std = NaN")
    print(f"  [OK] Grupos en 'Ciudad x Modalidad' con n_respondidas_count == 1 : {unitarios_resp['segmento'].tolist()} -> tiempo_resp_std = NaN")
    print(f"  [OK] Grupos en 'Ciudad x Modalidad' con n_respondidas_count == 0 : {sin_resp['segmento'].tolist()} -> media/mediana/std = NaN")

    print("\n" + "=" * 82)
    print("3. TABLAS DE PARTICIPACIÓN value_counts() Y TABLAS CRUZADAS crosstab() (CA-ISS-11-03)")
    print("=" * 82)
    vc_mod = df_u["modality"].value_counts(normalize=True, dropna=False)
    vc_resp = df_u["tiempo_respuesta_dias"].value_counts(normalize=True, dropna=False)
    assert np.isclose(vc_mod.sum(), 1.0)
    assert np.isclose(vc_resp.sum(), 1.0)
    assert np.isnan(vc_resp.index).any(), "value_counts(dropna=False) debe conservar la categoría NaN"
    prop_nan_resp = float(vc_resp.loc[vc_resp.index.isna()].iloc[0])
    assert np.isclose(prop_nan_resp, 38 / 93)

    ct_mod_status = pd.crosstab(df_u["modality"], df_u["status"], normalize="index", margins=True)
    ct_edad_status = pd.crosstab(df_u["grupo_etario"], df_u["status"], normalize="index", margins=True)
    assert np.allclose(ct_mod_status.sum(axis=1), 1.0)
    assert np.allclose(ct_edad_status.sum(axis=1), 1.0)

    print("  [OK] value_counts(normalize=True, dropna=False) en modality: " +
          ", ".join(f"{k}={v * 100:.2f}%" for k, v in vc_mod.items()))
    print(f"  [OK] value_counts(normalize=True, dropna=False) en tiempo_respuesta_dias conserva NaN = {prop_nan_resp * 100:.2f}% (38/93)")
    print("  [OK] crosstab(modality, status, normalize='index', margins=True): todas las filas suman 100,00%")
    print("  [OK] crosstab(grupo_etario, status, normalize='index', margins=True): todas las filas suman 100,00%")

    print("\n" + "=" * 82)
    print("4. TABLA DINÁMICA pivot_table() CONSERVANDO CELDAS AUSENTES (NaN) (CA-ISS-11-04)")
    print("=" * 82)
    piv_conteo = pd.pivot_table(
        df_u, index="ciudad_normalizada", columns="modality", values="application_id", aggfunc="count"
    )
    piv_salario = pd.pivot_table(
        df_u, index="ciudad_normalizada", columns="modality", values="expected_salary_cop", aggfunc="median"
    )
    piv_tiempo = pd.pivot_table(
        df_u, index="ciudad_normalizada", columns="modality", values="tiempo_respuesta_dias", aggfunc="mean"
    )
    assert piv_conteo.shape == (6, 3)
    assert int(piv_conteo.isna().sum().sum()) == 9, "Deben conservarse las 9 celdas ausentes como NaN en piv_conteo"
    assert int(piv_salario.isna().sum().sum()) == 9, "Deben conservarse las 9 celdas ausentes como NaN en piv_salario"
    assert int(piv_tiempo.isna().sum().sum()) == 10, "Deben conservarse las 10 celdas sin respuesta como NaN en piv_tiempo"
    assert int(piv_conteo.sum().sum()) == len(df_u)
    assert not (piv_salario == 0).any().any(), "Ningún salario ausente debe convertirse en 0"

    print(f"  [OK] pivot_table (6 ciudades x 3 modalidades = 18 celdas): 9 celdas sin vacantes/postulaciones preservadas como NaN")
    print(f"  [OK] pivot_table tiempo_respuesta_dias: 10 celdas preservadas como NaN (9 sin postulaciones + Bogotá-Remoto sin respondidas)")
    print(f"  [OK] Suma de celdas no nulas en pivot_table de conteo = {int(piv_conteo.sum().sum())} (igual al universo n={len(df_u)})")

    print("\n" + "=" * 82)
    print("5. FICHAS TÉCNICAS DE KPI, EXPORTACIÓN CSV Y VALIDACIÓN DE INMUTABILIDAD (CA-ISS-11-05)")
    print("=" * 82)
    rutas_fichas = verificar_fichas_kpi(analytics_root)
    for rf in rutas_fichas:
        print(f"  [OK] Fichas técnicas verificadas (KPI-YEB-01 a KPI-YEB-03) : {rf.relative_to(analytics_root).as_posix()}")

    ruta_salida = outputs_dir / "yeb_kpis_semana6.csv"
    consolidado.to_csv(ruta_salida, index=False, encoding="utf-8", lineterminator="\n")
    huella_despues = sha256_archivo(ruta_fuente)
    assert huella_antes == huella_despues, "Se alteró el archivo fuente de Semana 5 (violación de TEC-016)"

    print(f"  [OK] Exportación verificada : {ruta_salida.relative_to(analytics_root).as_posix()} ({len(consolidado)} filas, {consolidado['dimension'].nunique()} dimensiones)")
    print(f"  [OK] Inmutabilidad Semana 5 : SHA-256 idéntico ({huella_despues[:16]}...)")

    print("\n" + "=" * 82)
    print("6. ANÁLISIS ESCRITO (150–200 PALABRAS) Y DECISIÓN NO AUTOMATIZABLE (CA-ISS-11-06)")
    print("=" * 82)
    palabras = ANALISIS_ESCRITO_KPI.split()
    n_palabras = len(palabras)
    assert 150 <= n_palabras <= 200, f"El análisis escrito debe tener entre 150 y 200 palabras (tiene {n_palabras})"
    for termino in ("grupos pequeños", "NaN", "menores", "Ministerio del Trabajo", "revisión humana"):
        assert termino in ANALISIS_ESCRITO_KPI, f"Falta el concepto '{termino}' en el análisis escrito"
    print(f"  [OK] Longitud del análisis escrito : {n_palabras} palabras (rango exigido: 150–200)")
    print("  [OK] Cubre hallazgos descriptivos, grupos pequeños (n=1, NaN) y decisión humana legal sobre menores (LEG-001)")

    print("\n[RESULTADO] Issue #11 verificado satisfactoriamente (CP-051, CP-051B y CP-051C: PASA).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
