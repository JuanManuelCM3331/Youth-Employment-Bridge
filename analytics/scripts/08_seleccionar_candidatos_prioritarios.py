#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Issue #08 [Data/Filtering]: Selección avanzada, máscaras booleanas e invariantes analíticos
(Youth Employment Bridge - YEB).

Criterios de Aceptación cubiertos:
  - CA-ISS-08-01: Cohortes con filtros compuestos: menores protegidos (16-17) frente a
    jóvenes de 18-24, habilidades demandadas (.isin()) y salario viable (.between()).
  - CA-ISS-08-02: Consultas expresivas con .query() y parámetros @variable.
  - CA-ISS-08-03: Aserciones lógicas (assert) que abortan el pipeline si el subconjunto
    final contiene edades fuera de rango, banderas contradictorias o menores sin permiso.
  - CA-ISS-08-04: Exportación de outputs/candidatos_prioritarios_validados.csv y su
    bitácora técnica outputs/bitacora_candidatos_prioritarios.md.

Riesgos mitigados: LEG-001, TEC-013
Casos de prueba verificados: CP-048, CP-048B, CP-048C
"""

from __future__ import annotations

import json
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

import pandas as pd

# ---------------------------------------------------------------------------
# Parámetros de negocio (todos explícitos para que la selección sea auditable)
# ---------------------------------------------------------------------------
EDAD_MIN_PLATAFORMA = 16
EDAD_MAX_PLATAFORMA = 24
EDAD_MAYORIA = 18
ESTADOS_ACTIVOS = ["submitted", "under_review"]  # postulaciones sin decisión todavía
UMBRAL_PERFIL = 80  # % mínimo de completitud de la hoja de vida
COLUMNAS_CONTACTO_PROHIBIDAS = ["email_contacto", "telefono_contacto"]

COLUMNAS_REPORTE = [
    "application_id", "candidate_hash", "cohorte", "candidate_age", "is_protected_minor",
    "minor_permit_verified", "ciudad_candidato", "education_level", "primary_skill",
    "job_id", "job_title", "modality", "salary_min_offered", "salary_max_offered",
    "expected_salary_cop", "status", "job_published_at", "applied_at", "updated_at", "profile_completeness_pct",
]


def resolve_analytics_root() -> Path:
    analytics_root = Path(__file__).resolve().parent.parent
    for sub in ("data/processed", "data/seeds", "outputs"):
        assert (analytics_root / sub).exists(), f"No se encontró analytics/{sub}/"
    return analytics_root


def cargar_dataset(analytics_root: Path) -> pd.DataFrame:
    df = pd.read_csv(
        analytics_root / "data" / "processed" / "yeb_postulaciones_preparado.csv",
        parse_dates=["job_published_at", "applied_at", "updated_at"],
    )
    return df


def habilidades_demandadas(analytics_root: Path) -> list[str]:
    """Habilidades exigidas por las vacantes publicadas (semilla del Issue #03), no una lista inventada."""
    vacantes = json.loads((analytics_root / "data" / "seeds" / "vacantes.ejson").read_text(encoding="utf-8"))
    return sorted({h for v in vacantes if v.get("estado") == "published" for h in v["habilidades_requeridas"]})


def segmentar_cohortes(df: pd.DataFrame) -> tuple[pd.DataFrame, pd.DataFrame]:
    """CA-ISS-08-01: dos cohortes excluyentes con máscaras booleanas y .loc."""
    menores = df.loc[df["candidate_age"].between(EDAD_MIN_PLATAFORMA, EDAD_MAYORIA - 1) & df["is_protected_minor"]]
    adultos = df.loc[df["candidate_age"].between(EDAD_MAYORIA, EDAD_MAX_PLATAFORMA) & ~df["is_protected_minor"]]
    assert len(menores) + len(adultos) == len(df), "Las cohortes no cubren el dataset: hay registros con edad y bandera de menor contradictorias"
    assert menores.index.intersection(adultos.index).empty, "Un registro quedó en las dos cohortes"
    return menores, adultos


def mascara_prioridad(df: pd.DataFrame, habilidades: list[str]) -> pd.Series:
    """Selección con máscaras booleanas explícitas (cada condición entre paréntesis: TEC-013)."""
    return (
        (df["status"].isin(ESTADOS_ACTIVOS))
        & (df["primary_skill"].isin(habilidades))
        & (df["expected_salary_cop"].between(df["salary_min_offered"], df["salary_max_offered"]))
        & (df["profile_completeness_pct"] >= UMBRAL_PERFIL)
        & ((~df["is_protected_minor"]) | (df["minor_permit_verified"]))
    )


def seleccion_por_query(df: pd.DataFrame, habilidades: list[str]) -> pd.DataFrame:
    """CA-ISS-08-02: la misma selección escrita con .query() y parámetros @variable."""
    estados = ESTADOS_ACTIVOS
    umbral = UMBRAL_PERFIL
    edad_min, edad_max = EDAD_MIN_PLATAFORMA, EDAD_MAX_PLATAFORMA
    return df.query(
        "status in @estados"
        " and primary_skill in @habilidades"
        " and salary_min_offered <= expected_salary_cop <= salary_max_offered"
        " and profile_completeness_pct >= @umbral"
        " and @edad_min <= candidate_age <= @edad_max"
        " and (not is_protected_minor or minor_permit_verified)"
    )


def validar_invariantes(sub: pd.DataFrame, habilidades: list[str]) -> list[str]:
    """
    CA-ISS-08-03: invariantes que deben cumplirse en el subconjunto final.
    Si alguno falla, AssertionError detiene el pipeline antes de exportar.
    """
    assert not sub.empty, "El subconjunto final está vacío"
    assert sub["candidate_age"].between(EDAD_MIN_PLATAFORMA, EDAD_MAX_PLATAFORMA).all(), \
        "Edad fuera del rango permitido en la plataforma (16-24)"
    assert (sub["is_protected_minor"] == (sub["candidate_age"] < EDAD_MAYORIA)).all(), \
        "Bandera is_protected_minor contradice la edad declarada"
    assert sub.loc[sub["is_protected_minor"], "minor_permit_verified"].all(), \
        "LEG-001: menor de edad sin permiso de trabajo verificado en la lista de priorizados"
    assert not sub.loc[~sub["is_protected_minor"], "minor_permit_verified"].any(), \
        "Permiso de menor marcado en un candidato mayor de edad (dato contradictorio)"
    assert (sub["salary_min_offered"] <= sub["salary_max_offered"]).all(), \
        "Vacante con salario mínimo mayor que el máximo"
    assert sub["expected_salary_cop"].between(sub["salary_min_offered"], sub["salary_max_offered"]).all(), \
        "Aspiración salarial fuera del rango ofertado"
    assert ((sub["job_published_at"] <= sub["applied_at"]) & (sub["applied_at"] <= sub["updated_at"])).all(), \
        "Cronología imposible: postulación antes de la publicación o actualización antes de la postulación"
    assert sub["application_id"].is_unique and sub["candidate_hash"].is_unique, \
        "Identificador de postulación o de candidato repetido"
    assert sub["status"].isin(ESTADOS_ACTIVOS).all(), "Postulación ya decidida dentro de la lista de prioridad"
    assert sub["primary_skill"].isin(habilidades).all(), "Habilidad fuera de las demandadas por las vacantes"
    assert not set(COLUMNAS_CONTACTO_PROHIBIDAS) & set(sub.columns), \
        "Minimización de datos: el reporte no puede llevar correo ni teléfono"
    return [
        "Edad dentro de 16-24 años",
        "is_protected_minor coherente con la edad",
        "Todo menor priorizado tiene permiso verificado (LEG-001)",
        "Ningún mayor de edad tiene bandera de permiso de menor",
        "salary_min_offered <= salary_max_offered",
        "expected_salary_cop dentro del rango ofertado",
        "job_published_at <= applied_at <= updated_at",
        "application_id y candidate_hash únicos",
        "Solo postulaciones activas (submitted / under_review)",
        "Habilidad principal demandada por las vacantes",
        "Sin columnas de contacto directo (minimización)",
    ]


def probar_registros_forzados(base: pd.DataFrame, habilidades: list[str]) -> list[dict[str, str]]:
    """CP-048B: cada registro inconsistente forzado debe hacer abortar el pipeline."""
    fila = base.iloc[[0]].copy()
    casos = {
        "Candidato de 17 años sin bandera is_protected_minor": {"candidate_age": 17, "is_protected_minor": False, "minor_permit_verified": False},
        "Candidato de 16 años sin permiso de trabajo verificado": {"candidate_age": 16, "is_protected_minor": True, "minor_permit_verified": False},
        "Edad fuera de la plataforma (27 años)": {"candidate_age": 27},
        "Postulación anterior a la publicación de la vacante": {"applied_at": base.iloc[0]["job_published_at"] - pd.Timedelta(days=3)},
    }
    resultados = []
    for descripcion, cambios in casos.items():
        forzado = fila.copy()
        for col, val in cambios.items():
            forzado[col] = val
        forzado["application_id"] = "APP-FORZADO-01"
        forzado["candidate_hash"] = "CAND-FORZADO-01"
        prueba = pd.concat([base, forzado], ignore_index=True)
        try:
            validar_invariantes(prueba, habilidades)
            resultados.append({"caso": descripcion, "resultado": "NO ABORTÓ", "mensaje": "-"})
        except AssertionError as exc:
            resultados.append({"caso": descripcion, "resultado": "ABORTÓ", "mensaje": str(exc)})
    return resultados


def escribir_bitacora(ruta: Path, contexto: dict) -> None:
    filas_embudo = "\n".join(f"| {paso} | {n} |" for paso, n in contexto["embudo"])
    filas_inv = "\n".join(f"| {i} | {txt} | Cumple |" for i, txt in enumerate(contexto["invariantes"], 1))
    filas_forz = "\n".join(f"| {r['caso']} | {r['resultado']} | {r['mensaje']} |" for r in contexto["forzados"])
    texto = f"""# Bitácora técnica — Candidatos prioritarios validados (Issue #08)

Archivo generado por `analytics/scripts/08_seleccionar_candidatos_prioritarios.py`. Acompaña a
`outputs/candidatos_prioritarios_validados.csv` y se regenera en cada ejecución.

## Fuente y parámetros

| Parámetro | Valor |
| :-- | :-- |
| Dataset de entrada | `data/processed/yeb_postulaciones_preparado.csv` ({contexto['total']} postulaciones, salida del Issue #05) |
| Rango de edad de la plataforma | {EDAD_MIN_PLATAFORMA} a {EDAD_MAX_PLATAFORMA} años |
| Estados considerados activos | {', '.join(ESTADOS_ACTIVOS)} |
| Habilidades demandadas | {', '.join(contexto['habilidades'])} (tomadas de `habilidades_requeridas` en `data/seeds/vacantes.ejson`) |
| Salario viable | `expected_salary_cop` entre `salary_min_offered` y `salary_max_offered` (`.between()`, inclusivo) |
| Completitud mínima del perfil | {UMBRAL_PERFIL} % |
| Regla para menores | Solo entran si `minor_permit_verified = True` (LEG-001) |

## Cohortes de edad

| Cohorte | Registros en el dataset | Priorizados |
| :-- | :-: | :-: |
| Menor protegido (16-17) | {contexto['n_menores']} | {contexto['p_menores']} |
| Joven adulto (18-24) | {contexto['n_adultos']} | {contexto['p_adultos']} |

## Embudo de filtros (aplicados en orden acumulado)

| Paso | Registros que quedan |
| :-- | :-: |
{filas_embudo}

La selección con máscaras booleanas y la selección con `.query()` devolvieron exactamente los
mismos {contexto['final']} registros (mismo índice), lo que descarta errores de precedencia en los
operadores `&`, `|` y `~` (TEC-013).

## Invariantes verificados con `assert` sobre el subconjunto final

| # | Invariante | Estado |
| :-: | :-- | :-- |
{filas_inv}

## Prueba con registros inconsistentes forzados (CP-048B)

| Registro forzado | Pipeline | Mensaje de la aserción |
| :-- | :-- | :-- |
{filas_forz}

## Notas

- El script se niega a correr con `python -O`, porque esa opción elimina las sentencias `assert`.
- El reporte no incluye correo ni teléfono: se identifica a cada joven solo por `candidate_hash`.
- En el dataset preparado todos los menores tienen permiso verificado, por eso ninguno se excluye
  por la regla LEG-001; la prueba forzada demuestra que, si apareciera uno sin permiso, el pipeline
  se detiene en lugar de exportarlo.
"""
    ruta.write_text(texto, encoding="utf-8")


def main() -> int:
    # Con `python -O` Python elimina todas las sentencias assert y los invariantes dejarían de proteger el reporte.
    if not __debug__:
        raise SystemExit("[ERROR] Ejecute este script sin la opción -O: los invariantes dependen de assert (TEC-013).")
    analytics_root = resolve_analytics_root()
    outputs_dir = analytics_root / "outputs"

    print("=" * 80)
    print("1. CARGA DEL DATASET PREPARADO Y PARÁMETROS DE SELECCIÓN")
    print("=" * 80)
    df = cargar_dataset(analytics_root)
    habilidades = habilidades_demandadas(analytics_root)
    print(f"  [OK] Dataset : data/processed/yeb_postulaciones_preparado.csv (shape={df.shape})")
    print(f"  [OK] Habilidades demandadas por vacantes publicadas : {habilidades}")

    print("\n" + "=" * 80)
    print("2. COHORTES CON FILTROS COMPUESTOS (.loc, .isin(), .between()) (CA-ISS-08-01 / CP-048)")
    print("=" * 80)
    menores, adultos = segmentar_cohortes(df)
    print(f"  [OK] Menores protegidos (16-17) : {len(menores):>3} registros")
    print(f"  [OK] Jóvenes adultos (18-24)    : {len(adultos):>3} registros")
    print(f"  [OK] Partición exacta           : {len(menores)} + {len(adultos)} = {len(df)} (sin solapamiento)")

    pasos = [
        ("Dataset preparado", pd.Series(True, index=df.index)),
        ("Estado activo (.isin)", df["status"].isin(ESTADOS_ACTIVOS)),
        ("Habilidad demandada (.isin)", df["primary_skill"].isin(habilidades)),
        ("Aspiración dentro del rango ofertado (.between)", df["expected_salary_cop"].between(df["salary_min_offered"], df["salary_max_offered"])),
        (f"Perfil completo >= {UMBRAL_PERFIL} %", df["profile_completeness_pct"] >= UMBRAL_PERFIL),
        ("Menor solo con permiso verificado (LEG-001)", (~df["is_protected_minor"]) | df["minor_permit_verified"]),
    ]
    acumulado = pd.Series(True, index=df.index)
    embudo = []
    for nombre, mascara in pasos:
        acumulado &= mascara
        embudo.append((nombre, int(acumulado.sum())))
        print(f"    {nombre:<50} -> {int(acumulado.sum()):>3}")

    por_mascara = df.loc[mascara_prioridad(df, habilidades)]
    assert por_mascara.index.equals(df.loc[acumulado].index)

    print("\n" + "=" * 80)
    print("3. CONSULTA EXPRESIVA CON .query() Y PARÁMETROS @variable (CA-ISS-08-02)")
    print("=" * 80)
    por_query = seleccion_por_query(df, habilidades)
    assert por_query.index.equals(por_mascara.index), "La selección con .query() difiere de la selección con máscaras (TEC-013)"
    print(f"  [OK] .query() con @estados, @habilidades, @umbral, @edad_min, @edad_max -> {len(por_query)} registros")
    print("  [OK] Índices idénticos a la selección con máscaras booleanas (sin errores de precedencia)")
    top = por_query.sort_values(["profile_completeness_pct", "application_id"], ascending=[False, True]).iloc[:3]
    print("  [i] Top 3 por completitud de perfil (.iloc[:3]) : " + ", ".join(f"{r.application_id} ({r.profile_completeness_pct} %)" for r in top.itertuples()))

    print("\n" + "=" * 80)
    print("4. INVARIANTES ANALÍTICOS CON assert (CA-ISS-08-03 / CP-048B / LEG-001)")
    print("=" * 80)
    final = por_query.copy()
    final["cohorte"] = final["is_protected_minor"].map({True: "Menor protegido (16-17)", False: "Joven adulto (18-24)"})
    final = final[COLUMNAS_REPORTE].sort_values(["cohorte", "profile_completeness_pct", "application_id"], ascending=[True, False, True])
    invariantes = validar_invariantes(final, habilidades)
    for inv in invariantes:
        print(f"  [OK] {inv}")

    forzados = probar_registros_forzados(final, habilidades)
    for r in forzados:
        print(f"  [{'OK' if r['resultado'] == 'ABORTÓ' else 'FALLA'}] Forzado: {r['caso']:<52} -> {r['resultado']} ({r['mensaje']})")
    assert all(r["resultado"] == "ABORTÓ" for r in forzados), "Un registro inconsistente forzado no detuvo el pipeline"

    print("\n" + "=" * 80)
    print("5. EXPORTACIÓN DEL REPORTE Y BITÁCORA TÉCNICA (CA-ISS-08-04 / CP-048C)")
    print("=" * 80)
    ruta_csv = outputs_dir / "candidatos_prioritarios_validados.csv"
    ruta_bitacora = outputs_dir / "bitacora_candidatos_prioritarios.md"
    exportable = final.copy()
    for col in ("job_published_at", "applied_at", "updated_at"):
        exportable[col] = exportable[col].dt.strftime("%Y-%m-%d")
    exportable.to_csv(ruta_csv, index=False, encoding="utf-8", lineterminator="\n")

    releido = pd.read_csv(ruta_csv, parse_dates=["applied_at"])
    assert len(releido) == len(final) and releido["application_id"].tolist() == final["application_id"].tolist()

    escribir_bitacora(ruta_bitacora, {
        "total": len(df), "habilidades": habilidades, "embudo": embudo, "final": len(final),
        "n_menores": len(menores), "n_adultos": len(adultos),
        "p_menores": int(final["is_protected_minor"].sum()), "p_adultos": int((~final["is_protected_minor"]).sum()),
        "invariantes": invariantes, "forzados": forzados,
    })
    print(f"  [OK] Reporte  : {ruta_csv.relative_to(analytics_root)} (shape={final.shape})")
    print(f"  [OK] Bitácora : {ruta_bitacora.relative_to(analytics_root)}")
    print(f"  [OK] Priorizados por cohorte -> menores: {int(final['is_protected_minor'].sum())}, adultos: {int((~final['is_protected_minor']).sum())}")

    print("\n[RESULTADO] Issue #08 verificado satisfactoriamente (CP-048, CP-048B y CP-048C: PASA).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
