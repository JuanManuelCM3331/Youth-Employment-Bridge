#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Issue #09 [Data/Transform]: Variables derivadas vectorizadas y cálculo de indicadores
de empleabilidad (Youth Employment Bridge - YEB).

Criterios de Aceptación cubiertos:
  - CA-ISS-09-01: Variables vectorizadas `tiempo_respuesta_dias` y `brecha_salarial_aspiracion`.
  - CA-ISS-09-02: Normalización controlada de cargos y ciudades con diccionarios y .map(),
    sin inventar equivalencias (lo que no está en el diccionario queda marcado, no adivinado).
  - CA-ISS-09-03: Prioridad de atención con np.select() y precedencia explícita:
    1° inconsistencias de identidad/edad > 2° postulaciones desactualizadas > 3° aptos para entrevista.
  - CA-ISS-09-04: Reporte consolidado outputs/metricas_empleabilidad_yeb.csv con conversión del
    embudo, tasa de rechazo y tiempo medio de respuesta (numerador y denominador explícitos).

Riesgos mitigados: NEG-008, TEC-014
Casos de prueba verificados: CP-049, CP-049B, CP-049C
"""

from __future__ import annotations

import os
import subprocess
import sys
import unicodedata
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

EDAD_MIN, EDAD_MAX, EDAD_MAYORIA = 16, 24, 18
ESTADOS_ACTIVOS = ["submitted", "under_review"]
ESTADOS_RESPONDIDOS = ["interview", "accepted", "rejected"]
UMBRAL_PERFIL_COMPLETO = 85  # % de completitud para considerar el perfil listo para entrevista
SIN_EQUIVALENCIA = "SIN_EQUIVALENCIA"

# ---------------------------------------------------------------------------
# Diccionarios de normalización (CA-ISS-09-02). Las claves se comparan ya
# normalizadas: minúsculas, sin tildes y con un solo espacio entre palabras.
# Solo se incluyen equivalencias verificables; nada se completa por parecido.
# ---------------------------------------------------------------------------
CARGO_CANONICO = {
    "analista de datos jr": "Analista de Datos Junior",
    "desarrollador backend php jr": "Desarrollador Backend PHP Junior",
    "aprendiz sena en sistemas": "Aprendiz SENA en Sistemas",
    "agente de soporte bilingue": "Agente de Soporte Bilingüe",
    "auxiliar de bodega e inventarios": "Auxiliar de Bodega e Inventarios",
    "asistente de marketing digital": "Asistente de Marketing Digital",
    "auxiliar contable jr": "Auxiliar Contable Junior",
    "ejecutivo comercial juvenil": "Ejecutivo Comercial Juvenil",
}
FAMILIA_CARGO = {
    "Analista de Datos Junior": "Tecnología y datos",
    "Desarrollador Backend PHP Junior": "Tecnología y datos",
    "Aprendiz SENA en Sistemas": "Tecnología y datos",
    "Agente de Soporte Bilingüe": "Servicio al cliente",
    "Auxiliar de Bodega e Inventarios": "Logística",
    "Asistente de Marketing Digital": "Mercadeo y ventas",
    "Ejecutivo Comercial Juvenil": "Mercadeo y ventas",
    "Auxiliar Contable Junior": "Contable y financiero",
}
CIUDAD_CANONICA = {
    "medellin": "Medellín",
    "bogota": "Bogotá",
    "cali": "Cali",
    "barranquilla": "Barranquilla",
    "bucaramanga": "Bucaramanga",
    "pereira": "Pereira",
}
DEPARTAMENTO = {
    "Medellín": "Antioquia",
    "Bogotá": "Bogotá D.C.",
    "Cali": "Valle del Cauca",
    "Barranquilla": "Atlántico",
    "Bucaramanga": "Santander",
    "Pereira": "Risaralda",
}

PRIORIDADES = [
    "P1 - Revisar identidad o edad",
    "P2 - Postulación desactualizada",
    "P3 - Apto para entrevista",
]
SIN_PRIORIDAD = "Sin acción prioritaria"


def resolve_analytics_root() -> Path:
    analytics_root = Path(__file__).resolve().parent.parent
    for sub in ("data/processed", "outputs"):
        assert (analytics_root / sub).exists(), f"No se encontró analytics/{sub}/"
    return analytics_root


def clave_normalizada(serie: pd.Series) -> pd.Series:
    """Minúsculas, sin tildes, sin espacios sobrantes. No cambia el significado del texto."""
    sin_tildes = serie.astype("string").map(
        lambda s: "".join(c for c in unicodedata.normalize("NFKD", s) if not unicodedata.combining(c)) if pd.notna(s) else s
    )
    return sin_tildes.str.lower().str.strip().str.replace(r"\s+", " ", regex=True)


def division_segura(numerador, denominador):
    """Divide sin producir inf: si el denominador es 0 o nulo, el resultado es NaN."""
    num = np.asarray(numerador, dtype="float64")
    den = np.asarray(denominador, dtype="float64")
    return np.divide(num, den, out=np.full(np.broadcast(num, den).shape, np.nan), where=(den != 0) & ~np.isnan(den))


def cargar_poblacion(analytics_root: Path) -> pd.DataFrame:
    fechas = ["job_published_at", "applied_at", "updated_at"]
    preparado = pd.read_csv(analytics_root / "data" / "processed" / "yeb_postulaciones_preparado.csv", parse_dates=fechas)
    preparado["origen_registro"] = "dataset_preparado"

    # Los registros en cuarentena del Issue #05 son justamente conflictos de identidad: se incluyen para
    # que la cola de atención los muestre como P1. Se descartan sus datos de contacto (minimización).
    cuarentena = pd.read_csv(analytics_root / "outputs" / "cuarentena_identificadores_conflicto.csv", parse_dates=fechas)
    cuarentena = cuarentena.drop(columns=["email_contacto", "telefono_contacto"], errors="ignore")
    cuarentena["origen_registro"] = "cuarentena_issue_05"

    # Solo columnas comunes: el estado de verificación del contacto no se usa en este análisis.
    comunes = [c for c in preparado.columns if c in cuarentena.columns]
    poblacion = pd.concat([preparado[comunes], cuarentena[comunes]], ignore_index=True)
    for col in ("is_protected_minor", "minor_permit_verified"):
        poblacion[col] = poblacion[col].astype(str).str.strip().str.lower().eq("true")
    return poblacion


def derivar_variables(df: pd.DataFrame, fecha_corte: pd.Timestamp) -> pd.DataFrame:
    """CA-ISS-09-01: columnas calculadas en bloque, sin bucles fila a fila."""
    out = df.copy()
    respondida = out["status"].isin(ESTADOS_RESPONDIDOS)

    # El dataset tabular no guarda el historial de estados: updated_at es la fecha del último cambio,
    # que para una postulación respondida corresponde al paso a entrevista o al cierre.
    dias = (out["updated_at"] - out["applied_at"]).dt.days
    out["tiempo_respuesta_dias"] = dias.where(respondida)

    out["dias_sin_actualizar"] = (fecha_corte - out["updated_at"]).dt.days.where(out["status"].isin(ESTADOS_ACTIVOS))

    oferta_media = (out["salary_min_offered"] + out["salary_max_offered"]) / 2
    out["oferta_media_cop"] = oferta_media
    out["brecha_salarial_aspiracion_cop"] = out["expected_salary_cop"] - oferta_media
    out["brecha_salarial_aspiracion_pct"] = np.round(division_segura(out["brecha_salarial_aspiracion_cop"], oferta_media) * 100, 2)
    out["posicion_aspiracion"] = np.select(
        [out["expected_salary_cop"] < out["salary_min_offered"], out["expected_salary_cop"] > out["salary_max_offered"]],
        ["Por debajo del rango", "Por encima del rango"],
        default="Dentro del rango",
    )
    return out


def normalizar_catalogos(df: pd.DataFrame) -> pd.DataFrame:
    """CA-ISS-09-02: .map() con diccionarios cerrados; lo desconocido queda marcado como SIN_EQUIVALENCIA."""
    out = df.copy()
    out["cargo_normalizado"] = clave_normalizada(out["job_title"]).map(CARGO_CANONICO).fillna(SIN_EQUIVALENCIA)
    out["familia_cargo"] = out["cargo_normalizado"].map(FAMILIA_CARGO).fillna(SIN_EQUIVALENCIA)
    out["ciudad_normalizada"] = clave_normalizada(out["ciudad_candidato"]).map(CIUDAD_CANONICA).fillna(SIN_EQUIVALENCIA)
    out["departamento"] = out["ciudad_normalizada"].map(DEPARTAMENTO).fillna(SIN_EQUIVALENCIA)
    return out


def condiciones_prioridad(df: pd.DataFrame, umbral_dias: int) -> list[pd.Series]:
    inconsistencia = (
        ~df["candidate_age"].between(EDAD_MIN, EDAD_MAX)
        | (df["is_protected_minor"] != (df["candidate_age"] < EDAD_MAYORIA))
        | (df["is_protected_minor"] & ~df["minor_permit_verified"])
        | df.duplicated("application_id", keep=False)
        | (df.groupby("documento_hash")["candidate_hash"].transform("nunique") > 1)
    )
    activa = df["status"].isin(ESTADOS_ACTIVOS)
    desactualizada = activa & (df["dias_sin_actualizar"] > umbral_dias)
    apta = activa & (df["profile_completeness_pct"] >= UMBRAL_PERFIL_COMPLETO) & (df["familia_cargo"] != SIN_EQUIVALENCIA)
    return [inconsistencia, desactualizada, apta]


def clasificar_prioridad(df: pd.DataFrame, umbral_dias: int) -> pd.Series:
    """CA-ISS-09-03: np.select evalúa las condiciones en orden; la primera que se cumple gana."""
    return pd.Series(np.select(condiciones_prioridad(df, umbral_dias), PRIORIDADES, default=SIN_PRIORIDAD), index=df.index)


def probar_precedencia(base: pd.DataFrame, umbral_dias: int) -> list[dict[str, str]]:
    """CP-049B / TEC-014: registros que cumplen varias condiciones a la vez."""
    plantilla = base.loc[base["status"].eq("under_review") & base["profile_completeness_pct"].ge(UMBRAL_PERFIL_COMPLETO)].iloc[[0]].copy()
    casos = []

    triple = plantilla.copy()  # anomalía + desactualizada + perfil completo
    triple[["application_id", "candidate_hash", "documento_hash"]] = ["APP-PREC-01", "CAND-PREC-01", "DOC-PREC-01"]
    triple[["candidate_age", "is_protected_minor"]] = [17, False]
    triple["dias_sin_actualizar"] = umbral_dias + 10
    casos.append(("Edad contradictoria + desactualizada + perfil completo", triple, PRIORIDADES[0]))

    doble = plantilla.copy()  # desactualizada + perfil completo
    doble[["application_id", "candidate_hash", "documento_hash"]] = ["APP-PREC-02", "CAND-PREC-02", "DOC-PREC-02"]
    doble["dias_sin_actualizar"] = umbral_dias + 10
    casos.append(("Desactualizada + perfil completo", doble, PRIORIDADES[1]))

    simple = plantilla.copy()  # solo perfil completo y al día
    simple[["application_id", "candidate_hash", "documento_hash"]] = ["APP-PREC-03", "CAND-PREC-03", "DOC-PREC-03"]
    simple["dias_sin_actualizar"] = 0
    casos.append(("Solo perfil completo y al día", simple, PRIORIDADES[2]))

    resultados = []
    for descripcion, fila, esperado in casos:
        obtenido = clasificar_prioridad(fila, umbral_dias).iloc[0]
        invertido = np.select(condiciones_prioridad(fila, umbral_dias)[::-1], PRIORIDADES[::-1], default=SIN_PRIORIDAD)[0]
        resultados.append({"caso": descripcion, "esperado": esperado, "obtenido": obtenido, "con_orden_invertido": str(invertido)})
    return resultados


def construir_metricas(df: pd.DataFrame) -> pd.DataFrame:
    """CA-ISS-09-04: cada indicador declara su numerador, su denominador y su definición."""
    filas = []

    def agregar(segmento, indicador, numerador, denominador, unidad, definicion, factor=100.0):
        valor = division_segura(numerador, denominador).item() * (factor if unidad == "%" else 1.0)
        filas.append({
            "segmento": segmento, "indicador": indicador,
            "numerador": round(float(numerador), 2), "denominador": float(denominador),
            "valor": round(valor, 2) if not np.isnan(valor) else np.nan, "unidad": unidad, "definicion": definicion,
        })

    segmentos = {"Global": df}
    segmentos.update({f"Cohorte: {c}": g for c, g in df.groupby(df["is_protected_minor"].map({True: "Menor protegido (16-17)", False: "Joven adulto (18-24)"}))})
    segmentos.update({f"Familia: {f}": g for f, g in df.groupby("familia_cargo")})

    for nombre, g in segmentos.items():
        n = len(g)
        st = g["status"]
        entrevista_o_mas = st.isin(["interview", "accepted"]).sum()
        decididas = st.isin(["accepted", "rejected"]).sum()
        resp = g["tiempo_respuesta_dias"].dropna()
        activas = st.isin(ESTADOS_ACTIVOS).sum()

        filas.append({
            "segmento": nombre, "indicador": "postulaciones_analizadas", "numerador": float(n), "denominador": np.nan,
            "valor": float(n), "unidad": "postulaciones", "definicion": "Conteo de postulaciones del dataset preparado en el segmento",
        })
        agregar(nombre, "tasa_avance_a_revision", (st != "submitted").sum(), n, "%", "Postulaciones que salieron de 'submitted' / total")
        agregar(nombre, "tasa_avance_a_entrevista", entrevista_o_mas, n, "%", "Postulaciones en 'interview' o 'accepted' / total")
        agregar(nombre, "tasa_contratacion", (st == "accepted").sum(), n, "%", "Postulaciones 'accepted' / total")
        agregar(nombre, "conversion_entrevista_a_contratacion", (st == "accepted").sum(), entrevista_o_mas, "%", "'accepted' / ('interview' + 'accepted')")
        agregar(nombre, "tasa_rechazo", (st == "rejected").sum(), decididas, "%", "'rejected' / postulaciones con decisión final ('accepted' + 'rejected')")
        agregar(nombre, "tiempo_medio_respuesta", resp.sum(), len(resp), "días", "Suma de tiempo_respuesta_dias / postulaciones respondidas")
        filas.append({
            "segmento": nombre, "indicador": "tiempo_mediano_respuesta", "numerador": np.nan, "denominador": len(resp),
            "valor": float(resp.median()) if len(resp) else np.nan, "unidad": "días", "definicion": "Mediana de tiempo_respuesta_dias en postulaciones respondidas",
        })
        agregar(nombre, "tasa_postulaciones_desactualizadas", (g["prioridad_atencion"] == PRIORIDADES[1]).sum(), activas, "%", "Postulaciones activas clasificadas P2 / postulaciones activas")
        agregar(nombre, "brecha_salarial_media", g["brecha_salarial_aspiracion_pct"].sum(), g["brecha_salarial_aspiracion_pct"].notna().sum(), "%", "Promedio de (aspiración - punto medio ofertado) / punto medio ofertado", factor=1.0)
    metricas = pd.DataFrame(filas)
    metricas["denominador"] = metricas["denominador"].astype("Int64")
    return metricas


def main() -> int:
    analytics_root = resolve_analytics_root()
    outputs_dir = analytics_root / "outputs"

    print("=" * 80)
    print("1. POBLACIÓN DE ANÁLISIS Y FECHA DE CORTE")
    print("=" * 80)
    poblacion = cargar_poblacion(analytics_root)
    fecha_corte = poblacion.loc[poblacion["origen_registro"] == "dataset_preparado", "updated_at"].max()
    print(f"  [OK] Registros del dataset preparado : {(poblacion['origen_registro'] == 'dataset_preparado').sum()}")
    print(f"  [OK] Registros en cuarentena (Issue #05, sin datos de contacto) : {(poblacion['origen_registro'] == 'cuarentena_issue_05').sum()}")
    print(f"  [OK] Fecha de corte reproducible (máximo updated_at) : {fecha_corte.date()}")

    print("\n" + "=" * 80)
    print("2. VARIABLES DERIVADAS VECTORIZADAS (CA-ISS-09-01 / CP-049)")
    print("=" * 80)
    enriquecido = derivar_variables(poblacion, fecha_corte)
    resp = enriquecido["tiempo_respuesta_dias"]
    assert (resp.dropna() >= 0).all(), "tiempo_respuesta_dias negativo: cronología inválida"
    assert resp.notna().eq(enriquecido["status"].isin(ESTADOS_RESPONDIDOS)).all(), "tiempo_respuesta_dias solo aplica a postulaciones respondidas"
    assert not np.isinf(enriquecido["brecha_salarial_aspiracion_pct"]).any(), "División por cero en la brecha salarial"
    assert np.isnan(division_segura([5.0], [0.0])[0]), "division_segura debe devolver NaN si el denominador es 0"
    print(f"  [OK] tiempo_respuesta_dias calculado en {resp.notna().sum()} postulaciones respondidas (media {resp.mean():.2f} días, rango {resp.min():.0f}-{resp.max():.0f})")
    print(f"  [OK] brecha_salarial_aspiracion_pct: media {enriquecido['brecha_salarial_aspiracion_pct'].mean():.2f} % | 0 valores infinitos (división segura)")
    print("  [OK] Posición de la aspiración frente al rango ofertado: " +
          ", ".join(f"{k}={v}" for k, v in enriquecido["posicion_aspiracion"].value_counts().items()))

    print("\n" + "=" * 80)
    print("3. NORMALIZACIÓN CONTROLADA DE CARGOS Y CIUDADES CON .map() (CA-ISS-09-02 / NEG-008)")
    print("=" * 80)
    enriquecido = normalizar_catalogos(enriquecido)
    for col in ("cargo_normalizado", "familia_cargo", "ciudad_normalizada", "departamento"):
        sin_eq = int((enriquecido[col] == SIN_EQUIVALENCIA).sum())
        assert sin_eq == 0, f"{col}: {sin_eq} valores sin equivalencia en el diccionario; ampliar el diccionario con soporte, no adivinar"
        print(f"  [OK] {col:<20}: {enriquecido[col].nunique()} valores canónicos, 0 sin equivalencia")

    variantes = pd.DataFrame({"job_title": ["  ANALISTA DE DATOS JR ", "Agente de Soporte Bilingue", "Community Manager"],
                              "ciudad_candidato": ["MEDELLIN", " bogotá ", "Envigado"]})
    prueba = normalizar_catalogos(variantes)
    assert prueba["cargo_normalizado"].tolist() == ["Analista de Datos Junior", "Agente de Soporte Bilingüe", SIN_EQUIVALENCIA]
    assert prueba["ciudad_normalizada"].tolist() == ["Medellín", "Bogotá", SIN_EQUIVALENCIA]
    print("  [OK] Variantes de escritura ('  ANALISTA DE DATOS JR ', 'MEDELLIN', ' bogotá ') se resuelven al valor canónico")
    print("  [OK] Valores sin soporte ('Community Manager', 'Envigado') quedan como SIN_EQUIVALENCIA: no se inventa una equivalencia")

    print("\n" + "=" * 80)
    print("4. PRIORIDAD DE ATENCIÓN CON np.select() Y PRECEDENCIA EXPLÍCITA (CA-ISS-09-03 / CP-049B / TEC-014)")
    print("=" * 80)
    resp_validadas = enriquecido.loc[enriquecido["origen_registro"] == "dataset_preparado", "tiempo_respuesta_dias"]
    umbral_dias = int(np.ceil(resp_validadas.quantile(0.90)))
    print(f"  [i] Umbral de desactualización = percentil 90 de tiempo_respuesta_dias (dataset preparado) = {umbral_dias} días")
    enriquecido["prioridad_atencion"] = clasificar_prioridad(enriquecido, umbral_dias)
    conteo = enriquecido["prioridad_atencion"].value_counts().reindex(PRIORIDADES + [SIN_PRIORIDAD], fill_value=0)
    for etiqueta, n in conteo.items():
        print(f"    {etiqueta:<34}: {n:>3}")
    assert (enriquecido.loc[enriquecido["origen_registro"] == "cuarentena_issue_05", "prioridad_atencion"] == PRIORIDADES[0]).all(), \
        "Los conflictos de identidad en cuarentena deben quedar como P1"

    for r in probar_precedencia(enriquecido, umbral_dias):
        ok = r["obtenido"] == r["esperado"]
        print(f"  [{'OK' if ok else 'FALLA'}] {r['caso']:<55} -> {r['obtenido']} (con orden invertido sería: {r['con_orden_invertido']})")
        assert ok, f"Precedencia violada en '{r['caso']}'"

    print("\n" + "=" * 80)
    print("5. REPORTE CONSOLIDADO DE INDICADORES (CA-ISS-09-04 / CP-049C)")
    print("=" * 80)
    validados = enriquecido.loc[enriquecido["origen_registro"] == "dataset_preparado"]
    metricas = construir_metricas(validados)
    assert not np.isinf(metricas["valor"].astype(float)).any()
    glob = metricas.loc[metricas["segmento"] == "Global"].set_index("indicador")
    n = len(validados)
    st = validados["status"]
    assert glob.loc["postulaciones_analizadas", "valor"] == n
    assert np.isclose(glob.loc["tasa_rechazo", "valor"], 100 * (st == "rejected").sum() / st.isin(["accepted", "rejected"]).sum(), atol=0.01)
    assert np.isclose(glob.loc["tiempo_medio_respuesta", "valor"], validados["tiempo_respuesta_dias"].mean(), atol=0.01)
    for ind in ("tasa_avance_a_revision", "tasa_avance_a_entrevista", "tasa_contratacion", "conversion_entrevista_a_contratacion", "tasa_rechazo", "tiempo_medio_respuesta"):
        fila = glob.loc[ind]
        print(f"  [OK] {ind:<38}: {fila['valor']:>6.2f} {fila['unidad']:<5} ({fila['numerador']:g} / {fila['denominador']})")

    ruta_metricas = outputs_dir / "metricas_empleabilidad_yeb.csv"
    metricas.to_csv(ruta_metricas, index=False, encoding="utf-8", lineterminator="\n")
    columnas_cola = [
        "application_id", "candidate_hash", "origen_registro", "prioridad_atencion", "status", "candidate_age",
        "is_protected_minor", "cargo_normalizado", "familia_cargo", "ciudad_normalizada", "departamento",
        "tiempo_respuesta_dias", "dias_sin_actualizar", "oferta_media_cop", "brecha_salarial_aspiracion_cop",
        "brecha_salarial_aspiracion_pct", "posicion_aspiracion", "profile_completeness_pct",
    ]
    orden = {p: i for i, p in enumerate(PRIORIDADES + [SIN_PRIORIDAD])}
    cola = enriquecido[columnas_cola].sort_values(
        by=["prioridad_atencion", "dias_sin_actualizar", "application_id"],
        key=lambda s: s.map(orden) if s.name == "prioridad_atencion" else s,
        ascending=[True, False, True],
    )
    ruta_cola = outputs_dir / "prioridad_atencion_postulaciones.csv"
    cola.to_csv(ruta_cola, index=False, encoding="utf-8", lineterminator="\n")
    print(f"\n  [OK] Indicadores : {ruta_metricas.relative_to(analytics_root)} ({len(metricas)} filas, {metricas['segmento'].nunique()} segmentos)")
    print(f"  [OK] Cola de atención : {ruta_cola.relative_to(analytics_root)} ({len(cola)} postulaciones ordenadas por prioridad)")

    print("\n[RESULTADO] Issue #09 verificado satisfactoriamente (CP-049, CP-049B y CP-049C: PASA).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
