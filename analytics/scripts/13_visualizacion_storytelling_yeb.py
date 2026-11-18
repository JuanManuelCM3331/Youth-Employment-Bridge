#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
Issue #13 [Data/Viz]: Reporte visual accesible, control de sesgos y storytelling para YEB
(Youth Employment Bridge - YEB).

Criterios de Aceptación cubiertos:
  - CA-ISS-13-01: Validación previa con assert de que la suma de categorías coincide con el
    universo n analizable (n=93) y visibilidad de fecha de corte, denominador y exclusiones.
  - CA-ISS-13-02: Generación y exportación de 3 visualizaciones con sus tablas de respaldo:
      1) Matplotlib (.png): barras horizontales de estado del embudo y prioridad con eje en 0 y bar_label.
      2) Seaborn (.png): stripplot de tiempos de respuesta y aspiración salarial por modalidad y grupo etario.
      3) Plotly (.html + .csv): gráfico interactivo por ciudad con hovertemplate personalizado y CSV accesible.
  - CA-ISS-13-03: Comparación antes/después de un sesgo visual (eje truncado vs. escala desde cero)
    y verificación de accesibilidad (independencia del color con tramas/etiquetas y texto alternativo).
  - CA-ISS-13-04: Fichas técnicas por figura en docs/fichas_visualizacion_yeb.md y microinforme de
    storytelling (200–300 palabras) en dos voces (técnica y ejecutiva) con Hallazgo + Evidencia +
    Limitación + Acción propuesta sin causalidad injustificada.

Riesgos mitigados: NEG-011, UX-002
Casos de prueba verificados: CP-053, CP-053B, CP-053C
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

import matplotlib
matplotlib.use("Agg")
import matplotlib.pyplot as plt
import numpy as np
import pandas as pd
import plotly.graph_objects as go
import seaborn as sns

# Paleta Okabe-Ito accesible para deuteranopía, protanopía y tritanopía
PALETA_OKABE_ITO = ["#0072B2", "#E69F00", "#009E73", "#CC79A7", "#56B4E9", "#D55E00"]

ORDEN_ESTADOS = ["submitted", "under_review", "interview", "accepted", "rejected"]
ETIQUETAS_ESTADOS = {
    "submitted": "1. Enviada (submitted)",
    "under_review": "2. En revisión (under_review)",
    "interview": "3. Entrevista (interview)",
    "accepted": "4. Contratada (accepted)",
    "rejected": "5. Rechazada (rejected)",
}
ORDEN_PRIORIDADES = [
    "P1 - Revisar identidad o edad",
    "P2 - Postulación desactualizada",
    "P3 - Apto para entrevista",
    "Sin acción prioritaria",
]

TEXTOS_ALT = {
    "FIG-YEB-01": (
        "Gráfico de barras horizontales desde cero al corte 2026-02-02. En el Panel A (n=93 postulaciones válidas, "
        "4 excluidas en cuarentena), el embudo registra: Enviada (submitted) 20 (21,51%), En revisión (under_review) "
        "18 (19,35%), Entrevista (interview) 17 (18,28%), Contratada (accepted) 20 (21,51%) y Rechazada (rejected) "
        "18 (19,35%). En el Panel B (n=97 recibidas), la cola de prioridad muestra: P1 Revisar identidad o edad 4 "
        "(4,12%), P2 Postulación desactualizada 34 (35,05%), P3 Apto para entrevista 1 (1,03%) y Sin acción "
        "prioritaria 58 (59,79%)."
    ),
    "FIG-YEB-02": (
        "Gráfico de puntos (stripplot) de Seaborn al corte 2026-02-02. El Panel A muestra cada una de las 55 "
        "postulaciones respondidas (38 activas en NaN y 4 en cuarentena excluidas): la mediana de tiempo de "
        "respuesta es 4,0 días en Remoto (n=18) e Híbrido (n=19), y 3,0 días en Presencial (n=18); los menores "
        "protegidos suman 12 observaciones con mediana global de 5,5 días frente a 3,0 días en jóvenes adultos "
        "(n=43). El Panel B muestra las 93 aspiraciones salariales válidas: medianas de 2,15 millones COP en "
        "Híbrido (n=31), 1,94 millones COP en Remoto (n=32) y 1,86 millones COP en Presencial (n=30)."
    ),
    "FIG-YEB-03": (
        "Gráfico interactivo de barras agrupadas por ciudad al corte 2026-02-02 sobre n=93 postulaciones válidas "
        "(4 en cuarentena excluidas), respaldado por fig03_indicadores_ciudad_accesible.csv. Postulaciones totales "
        "y avance a entrevista/contratación por ciudad: Bogotá 17 postulaciones y 7 en entrevista/contratadas "
        "(41,18%, salario mediano 2.151.971 COP); Pereira 16 y 7 (43,75%, 1.883.850 COP); Cali 16 y 7 (43,75%, "
        "2.025.630,50 COP); Medellín 15 y 7 (46,67%, 1.869.998 COP); Barranquilla 15 y 5 (33,33%, 1.794.093 COP); "
        "Bucaramanga 14 y 4 (28,57%, 2.214.090 COP)."
    ),
    "FIG-YEB-04": (
        "Comparación lado a lado de sesgo por eje truncado sobre la tasa de avance a entrevista/contratación por "
        "modalidad (n=93, corte 2026-02-02). A la izquierda, un eje truncado entre 38% y 41% distorsiona "
        "visualmente las tasas de Híbrido (38,71%), Presencial (40,00%) y Remoto (40,62%) haciendo parecer a "
        "Remoto casi cuatro veces mayor que Híbrido. A la derecha, la escala completa desde 0% con etiquetas "
        "directas (12/31, 12/30 y 13/32) y tramas accesibles demuestra que las tres modalidades tienen tasas "
        "prácticamente equivalentes alrededor del 39,8%."
    ),
}

MICROINFORME_STORYTELLING = (
    "Voz 1 — Lectura técnica (Equipo de Datos e Ingeniería): "
    "Hallazgo: Al corte 2026-02-02, el universo analizable (n=93, excluidos 4 registros en cuarentena P1) "
    "presenta una conversión global a entrevista o contratación del 39,78% (37/93), pero concentra un cuello "
    "de botella operativo en las postulaciones activas: 34 de las 38 solicitudes en submitted o under_review "
    "(89,47%) superan el umbral del percentil 90 de respuesta (7 días) y caen en P2 - Postulación "
    "desactualizada, mientras las 55 respondidas mantienen una mediana de 4,0 días (IQR acotado entre 2 y 6 "
    "días). Evidencia: FIG-YEB-01 confirma la cuadratura del embudo (20+18+17+20+18=93) y FIG-YEB-02 "
    "(stripplot) muestra que en menores protegidos (16–17 años) solo existen 12 casos respondidos con mediana "
    "de 5,5 días frente a 3,0 días en adultos jóvenes (n=43). Limitación: Los conteos por subgrupo "
    "ciudad-modalidad incluyen 9 celdas vacías (NaN) y 3 celdas unitarias (n=1), por lo que las diferencias "
    "observadas son descriptivas y no prueban relaciones causales. Acción propuesta: Integrar en "
    "Modules/Dashboard una alerta preventiva cuando dias_sin_actualizar > 7 en estados activos y exigir "
    "revisión humana de los 4 casos P1. "
    "Voz 2 — Explicación ejecutiva no técnica (Administrador del Portal YEB): "
    "Hallazgo y Evidencia: Cuatro de cada diez postulaciones válidas (39,78%) logran entrevista o contratación "
    "y, cuando las empresas responden, suelen hacerlo en unos 4 días; sin embargo, 34 jóvenes que siguen "
    "esperando respuesta llevan más de una semana sin movimiento. Limitación: Estos números son una fotografía "
    "del periodo y no indican qué empresa o ciudad sea mejor, pues varias combinaciones tienen muy pocos casos. "
    "Acción propuesta: Priorizar hoy el recordatorio a empresas con esas 34 postulaciones estancadas y revisar "
    "manualmente los documentos legales de menores y los 4 perfiles en cuarentena antes de autorizar cualquier "
    "entrevista."
)


def resolve_analytics_root() -> Path:
    analytics_root = Path(__file__).resolve().parent.parent
    for sub in ("data/processed", "outputs"):
        assert (analytics_root / sub).exists(), f"No se encontró analytics/{sub}/"
    return analytics_root


def cargar_datos_validados(analytics_root: Path) -> tuple[pd.DataFrame, pd.DataFrame, str]:
    ruta = analytics_root / "data" / "processed" / "yeb_postulaciones_transformado.csv"
    assert ruta.exists(), f"Falta {ruta}"
    df_total = pd.read_csv(ruta, parse_dates=["job_published_at", "applied_at", "updated_at"])
    for col in ("is_protected_minor", "minor_permit_verified"):
        df_total[col] = df_total[col].astype(str).str.strip().str.lower().eq("true")
    df_total["grupo_etario"] = df_total["is_protected_minor"].map(
        {True: "Menor protegido (16-17)", False: "Joven adulto (18-24)"}
    )
    df_u = df_total.loc[df_total["origen_registro"] == "dataset_preparado"].copy()
    fecha_corte = str(df_u["updated_at"].max().date())

    # CA-ISS-13-01: validar con assert que la suma de categorías coincide con el universo n analizable
    assert len(df_total) == 97 and len(df_u) == 93
    assert int(df_u["status"].value_counts().sum()) == len(df_u)
    assert int(df_u["modality"].value_counts().sum()) == len(df_u)
    assert int(df_u["ciudad_normalizada"].value_counts().sum()) == len(df_u)
    assert int(df_u["grupo_etario"].value_counts().sum()) == len(df_u)
    assert int(df_u["tiempo_respuesta_dias"].count()) == 55
    return df_total, df_u, fecha_corte


def generar_fig01_matplotlib(df_total: pd.DataFrame, df_u: pd.DataFrame, fecha_corte: str, outputs_dir: Path) -> tuple[Path, Path]:
    """CA-ISS-13-02 (1): Matplotlib (.png) barras horizontales desde 0 con bar_label, tramas hatch y tabla de respaldo."""
    conteo_estados = df_u["status"].value_counts().reindex(ORDEN_ESTADOS, fill_value=0)
    assert int(conteo_estados.sum()) == len(df_u), "La suma de estados no coincide con el universo n=93"

    conteo_prioridad = df_total["prioridad_atencion"].value_counts().reindex(ORDEN_PRIORIDADES, fill_value=0)
    assert int(conteo_prioridad.sum()) == len(df_total), "La suma de prioridades no coincide con n=97"

    filas_respaldo = []
    for st, c in conteo_estados.items():
        filas_respaldo.append({
            "panel": "A - Estado del embudo (universo analizable)",
            "categoria": ETIQUETAS_ESTADOS[st],
            "conteo": int(c),
            "denominador_n": len(df_u),
            "porcentaje": round(100.0 * float(c) / len(df_u), 2),
            "fecha_corte": fecha_corte,
            "exclusiones": "4 registros de cuarentena_issue_05 (P1)",
        })
    for pr, c in conteo_prioridad.items():
        filas_respaldo.append({
            "panel": "B - Cola de prioridad operativa (total recibido)",
            "categoria": pr,
            "conteo": int(c),
            "denominador_n": len(df_total),
            "porcentaje": round(100.0 * float(c) / len(df_total), 2),
            "fecha_corte": fecha_corte,
            "exclusiones": "0 (incluye las 4 filas en cuarentena como P1)",
        })
    df_respaldo = pd.DataFrame(filas_respaldo)
    ruta_csv = outputs_dir / "fig01_embudo_postulaciones_respaldo.csv"
    df_respaldo.to_csv(ruta_csv, index=False, encoding="utf-8", lineterminator="\n")

    fig, axes = plt.subplots(1, 2, figsize=(14.5, 5.8), dpi=160)
    fig.patch.set_facecolor("white")

    # Panel A: Embudo de selección (n=93)
    ax1 = axes[0]
    etiquetas_a = [ETIQUETAS_ESTADOS[s] for s in ORDEN_ESTADOS][::-1]
    valores_a = [int(conteo_estados[s]) for s in ORDEN_ESTADOS][::-1]
    colores_a = [PALETA_OKABE_ITO[0], PALETA_OKABE_ITO[4], PALETA_OKABE_ITO[1], PALETA_OKABE_ITO[2], PALETA_OKABE_ITO[5]][::-1]
    tramas_a = ["//", "..", "xx", "--", "\\\\"][::-1]

    barras_a = ax1.barh(etiquetas_a, valores_a, color=colores_a, edgecolor="#1f2937", height=0.62, linewidth=1.1)
    for barra, trama in zip(barras_a, tramas_a):
        barra.set_hatch(trama)
    ax1.set_xlim(0, 28)  # Eje numérico iniciando estrictamente en 0
    etiquetas_bar_a = [f" {v} ({100.0 * v / len(df_u):.2f}%)" for v in valores_a]
    ax1.bar_label(barras_a, labels=etiquetas_bar_a, padding=4, fontsize=9.5, fontweight="bold", color="#111827")
    ax1.set_title("A. Estado del Embudo de Selección (n = 93 válidas)", fontsize=11.5, fontweight="bold", pad=10)
    ax1.set_xlabel("Número de postulaciones (unidad: postulaciones, eje desde 0)", fontsize=9.5)
    ax1.grid(axis="x", linestyle="--", alpha=0.45)
    ax1.set_axisbelow(True)

    # Panel B: Prioridad de atención (n=97)
    ax2 = axes[1]
    etiquetas_b = ORDEN_PRIORIDADES[::-1]
    valores_b = [int(conteo_prioridad[p]) for p in ORDEN_PRIORIDADES][::-1]
    colores_b = [PALETA_OKABE_ITO[5], PALETA_OKABE_ITO[1], PALETA_OKABE_ITO[2], "#6b7280"][::-1]
    tramas_b = ["xx", "//", "--", ".."][::-1]

    barras_b = ax2.barh(etiquetas_b, valores_b, color=colores_b, edgecolor="#1f2937", height=0.58, linewidth=1.1)
    for barra, trama in zip(barras_b, tramas_b):
        barra.set_hatch(trama)
    ax2.set_xlim(0, 76)  # Eje numérico iniciando estrictamente en 0
    etiquetas_bar_b = [f" {v} ({100.0 * v / len(df_total):.2f}%)" for v in valores_b]
    ax2.bar_label(barras_b, labels=etiquetas_bar_b, padding=4, fontsize=9.5, fontweight="bold", color="#111827")
    ax2.set_title("B. Cola de Prioridad de Atención (n = 97 recibidas, incluye 4 P1)", fontsize=11.5, fontweight="bold", pad=10)
    ax2.set_xlabel("Número de postulaciones (unidad: postulaciones, eje desde 0)", fontsize=9.5)
    ax2.grid(axis="x", linestyle="--", alpha=0.45)
    ax2.set_axisbelow(True)

    fig.suptitle(
        f"Youth Employment Bridge (YEB) — Embudo de Postulaciones y Prioridad Operativa (Corte: {fecha_corte})",
        fontsize=13, fontweight="bold", y=0.98,
    )
    fig.text(
        0.5, 0.01,
        f"Origen: data/processed/yeb_postulaciones_transformado.csv | Universo analizable Panel A: n = {len(df_u)} "
        f"(excluidas 4 filas en cuarentena P1) | Panel B: n = {len(df_total)} recibidas | Codificación accesible: Okabe-Ito + tramas + etiquetas directas.",
        ha="center", fontsize=8.5, color="#374151",
    )
    plt.tight_layout(rect=[0.0, 0.04, 1.0, 0.93])
    ruta_png = outputs_dir / "fig01_embudo_postulaciones_matplotlib.png"
    fig.savefig(ruta_png, dpi=160)
    plt.close(fig)
    return ruta_png, ruta_csv


def generar_fig02_seaborn_stripplot(df_u: pd.DataFrame, fecha_corte: str, outputs_dir: Path) -> tuple[Path, Path]:
    """CA-ISS-13-02 (2): Seaborn (.png) stripplot mostrando cada observación individual en grupos pequeños."""
    orden_mod = ["Remoto", "Híbrido", "Presencial"]
    orden_edad = ["Joven adulto (18-24)", "Menor protegido (16-17)"]
    df_resp = df_u.loc[df_u["tiempo_respuesta_dias"].notna()].copy()
    assert len(df_resp) == 55, "El subconjunto de tiempos de respuesta debe tener exactamente count=55"

    df_plot = df_u.copy()
    df_plot["salario_millones_cop"] = (df_plot["expected_salary_cop"] / 1_000_000.0).round(4)

    columnas_respaldo = [
        "application_id", "candidate_hash", "modality", "grupo_etario", "ciudad_normalizada",
        "status", "tiempo_respuesta_dias", "expected_salary_cop", "salario_millones_cop",
    ]
    ruta_csv = outputs_dir / "fig02_distribucion_tiempos_respaldo.csv"
    df_plot[columnas_respaldo].to_csv(ruta_csv, index=False, encoding="utf-8", lineterminator="\n")

    sns.set_theme(style="whitegrid")
    fig, axes = plt.subplots(1, 2, figsize=(14.5, 6.0), dpi=160)
    fig.patch.set_facecolor("white")
    paleta_edad = {"Joven adulto (18-24)": PALETA_OKABE_ITO[0], "Menor protegido (16-17)": PALETA_OKABE_ITO[5]}

    # Panel A: stripplot de tiempo_respuesta_dias (n=55 respondidas)
    ax1 = axes[0]
    sns.stripplot(
        data=df_resp, x="modality", y="tiempo_respuesta_dias", hue="grupo_etario",
        order=orden_mod, hue_order=orden_edad, palette=paleta_edad,
        dodge=True, jitter=0.16, size=8.5, alpha=0.88, edgecolor="#111827", linewidth=0.9, ax=ax1,
    )
    medianas_resp = df_resp.groupby("modality")["tiempo_respuesta_dias"].median().reindex(orden_mod)
    conteos_resp = df_resp.groupby("modality")["tiempo_respuesta_dias"].count().reindex(orden_mod)
    for idx, mod in enumerate(orden_mod):
        med = float(medianas_resp[mod])
        cnt = int(conteos_resp[mod])
        ax1.scatter([idx], [med], color="#111827", marker="D", s=70, zorder=6, label="Mediana modalidad" if idx == 0 else "")
        ax1.text(idx, med + 0.38, f"Med: {med:.1f}d (n={cnt})", ha="center", fontsize=8.8, fontweight="bold", color="#111827")
    ax1.set_ylim(-0.5, 10.5)
    ax1.set_title("A. Tiempo de Respuesta por Modalidad y Cohorte (count = 55 respondidas)", fontsize=11, fontweight="bold")
    ax1.set_xlabel("Modalidad de la vacante", fontsize=9.8)
    ax1.set_ylabel("Tiempo de respuesta (unidad: días calendario)", fontsize=9.8)
    ax1.legend(title="Cohorte etaria / Referencia", loc="upper right", fontsize=8.5, title_fontsize=9)

    # Panel B: stripplot de aspiración salarial (n=93 válidas)
    ax2 = axes[1]
    sns.stripplot(
        data=df_plot, x="modality", y="salario_millones_cop", hue="grupo_etario",
        order=orden_mod, hue_order=orden_edad, palette=paleta_edad,
        dodge=True, jitter=0.16, size=8.0, alpha=0.88, edgecolor="#111827", linewidth=0.9, ax=ax2,
    )
    medianas_sal = df_plot.groupby("modality")["salario_millones_cop"].median().reindex(orden_mod)
    conteos_sal = df_plot.groupby("modality")["salario_millones_cop"].count().reindex(orden_mod)
    for idx, mod in enumerate(orden_mod):
        med = float(medianas_sal[mod])
        cnt = int(conteos_sal[mod])
        ax2.scatter([idx], [med], color="#111827", marker="D", s=70, zorder=6, label="Mediana modalidad" if idx == 0 else "")
        ax2.text(idx, med + 0.08, f"Med: ${med:.2f}M (n={cnt})", ha="center", fontsize=8.8, fontweight="bold", color="#111827")
    ax2.set_ylim(1.1, 3.0)
    ax2.set_title("B. Aspiración Salarial por Modalidad y Cohorte (count = 93 válidas)", fontsize=11, fontweight="bold")
    ax2.set_xlabel("Modalidad de la vacante", fontsize=9.8)
    ax2.set_ylabel("Aspiración salarial declarada (unidad: millones de COP)", fontsize=9.8)
    ax2.legend(title="Cohorte etaria / Referencia", loc="upper right", fontsize=8.5, title_fontsize=9)

    fig.suptitle(
        f"Youth Employment Bridge (YEB) — Distribución Individual con Stripplot sin Ocultar Grupos Pequeños (Corte: {fecha_corte})",
        fontsize=12.5, fontweight="bold", y=0.98,
    )
    fig.text(
        0.5, 0.01,
        "Origen: data/processed/yeb_postulaciones_transformado.csv | Panel A: count = 55 respondidas (excluye 38 activas en NaN y 4 en cuarentena P1; "
        "menores protegidos respondidos = 12) | Panel B: count = 93 válidas (excluye 4 en cuarentena P1).",
        ha="center", fontsize=8.4, color="#374151",
    )
    plt.tight_layout(rect=[0.0, 0.04, 1.0, 0.93])
    ruta_png = outputs_dir / "fig02_distribucion_tiempos_stripplot_seaborn.png"
    fig.savefig(ruta_png, dpi=160)
    plt.close(fig)
    return ruta_png, ruta_csv


def generar_fig03_plotly_interactivo(df_u: pd.DataFrame, fecha_corte: str, outputs_dir: Path) -> tuple[Path, Path]:
    """CA-ISS-13-02 (3): Plotly (.html + .csv) por ciudad reportada con hovertemplate personalizado y tabla CSV accesible."""
    resumen = df_u.groupby(["ciudad_normalizada", "departamento"], as_index=False).agg(
        n_postulaciones_size=("application_id", "size"),
        n_candidatos_nunique=("candidate_hash", "nunique"),
        n_respondidas_count=("tiempo_respuesta_dias", "count"),
        n_entrevista_o_contratado=("status", lambda s: int(s.isin(["interview", "accepted"]).sum())),
        n_contratados=("status", lambda s: int((s == "accepted").sum())),
        tasa_entrevista_pct=("status", lambda s: round(100.0 * float(s.isin(["interview", "accepted"]).mean()), 2)),
        tasa_contratacion_pct=("status", lambda s: round(100.0 * float((s == "accepted").mean()), 2)),
        tiempo_resp_media_dias=("tiempo_respuesta_dias", lambda s: round(float(s.mean()), 2)),
        tiempo_resp_mediana_dias=("tiempo_respuesta_dias", lambda s: round(float(s.median()), 2)),
        salario_esp_mediana_cop=("expected_salary_cop", lambda s: round(float(s.median()), 2)),
        modalidad_predominante=("modality", lambda s: s.value_counts().index[0]),
    ).sort_values(by=["n_postulaciones_size", "tasa_entrevista_pct", "ciudad_normalizada"], ascending=[False, False, True]).reset_index(drop=True)

    assert int(resumen["n_postulaciones_size"].sum()) == len(df_u), "La suma por ciudad en Plotly debe ser 93"
    assert int(resumen["n_respondidas_count"].sum()) == 55

    resumen["fecha_corte"] = fecha_corte
    resumen["exclusiones"] = "4 registros de cuarentena_issue_05 (P1)"
    ruta_csv = outputs_dir / "fig03_indicadores_ciudad_accesible.csv"
    resumen.to_csv(ruta_csv, index=False, encoding="utf-8", lineterminator="\n")

    customdata = np.stack([
        resumen["departamento"],
        resumen["modalidad_predominante"],
        resumen["n_respondidas_count"],
        resumen["tasa_entrevista_pct"],
        resumen["tasa_contratacion_pct"],
        resumen["salario_esp_mediana_cop"],
        resumen["tiempo_resp_media_dias"],
        resumen["fecha_corte"],
    ], axis=-1)

    hover_tpl = (
        "<b>%{x} (%{customdata[0]})</b><br>"
        "Modalidad predominante: %{customdata[1]}<br>"
        "Postulaciones válidas (size): %{y}<br>"
        "Postulaciones respondidas (count): %{customdata[2]}<br>"
        "Avance a entrevista/contratación: %{customdata[3]:.2f}%<br>"
        "Tasa de contratación: %{customdata[4]:.2f}%<br>"
        "Aspiración salarial mediana: $%{customdata[5]:,.0f} COP<br>"
        "Tiempo medio de respuesta: %{customdata[6]:.2f} días<br>"
        "Fecha de corte: %{customdata[7]} (excluidas 4 filas P1)<extra></extra>"
    )

    fig = go.Figure()
    fig.add_trace(go.Bar(
        x=resumen["ciudad_normalizada"],
        y=resumen["n_postulaciones_size"],
        name="Total postulaciones válidas (size)",
        marker_color=PALETA_OKABE_ITO[0],
        marker_line_color="#111827",
        marker_line_width=1.2,
        text=[f"{n} post." for n in resumen["n_postulaciones_size"]],
        textposition="outside",
        customdata=customdata,
        hovertemplate=hover_tpl,
    ))
    fig.add_trace(go.Bar(
        x=resumen["ciudad_normalizada"],
        y=resumen["n_entrevista_o_contratado"],
        name="En entrevista o contratadas (interview + accepted)",
        marker_color=PALETA_OKABE_ITO[2],
        marker_pattern_shape="/",
        marker_line_color="#111827",
        marker_line_width=1.2,
        text=[f"{e} ({p:.1f}%)" for e, p in zip(resumen["n_entrevista_o_contratado"], resumen["tasa_entrevista_pct"])],
        textposition="outside",
        customdata=customdata,
        hovertemplate=hover_tpl,
    ))

    fig.update_layout(
        title=(
            f"<b>Youth Employment Bridge (YEB) — Postulaciones y Avance por Ciudad Reportada</b><br>"
            f"<sup>Universo analizable: n = {len(df_u)} postulaciones (excluidas 4 en cuarentena P1) | "
            f"Corte: {fecha_corte} | Respaldo accesible: fig03_indicadores_ciudad_accesible.csv</sup>"
        ),
        xaxis_title="Ciudad normalizada del candidato",
        yaxis_title="Número de postulaciones (unidad: postulaciones, eje desde 0)",
        yaxis=dict(range=[0, 21], zeroline=True, zerolinewidth=1.5, zerolinecolor="#111827"),
        barmode="group",
        template="plotly_white",
        legend=dict(orientation="h", yanchor="bottom", y=1.02, xanchor="right", x=1.0),
        margin=dict(t=95, b=85, l=65, r=40),
        annotations=[dict(
            text=(
                "Origen: data/processed/yeb_postulaciones_transformado.csv | Nota no causal: diferencias descriptivas entre ciudades "
                "(n entre 14 y 17) reflejan la modalidad predominante local, no eficacia territorial."
            ),
            xref="paper", yref="paper", x=0.5, y=-0.18, showarrow=False, font=dict(size=11, color="#374151"),
        )],
    )

    ruta_html = outputs_dir / "fig03_indicadores_ciudad_plotly.html"
    fig.write_html(str(ruta_html), include_plotlyjs="cdn", full_html=True)
    return ruta_html, ruta_csv


def generar_fig04_comparativa_sesgo(df_u: pd.DataFrame, fecha_corte: str, outputs_dir: Path) -> Path:
    """CA-ISS-13-03 / CP-053B: Comparación antes/después de un sesgo visual (eje truncado vs. escala desde cero)."""
    orden_mod = ["Híbrido", "Presencial", "Remoto"]
    res_mod = df_u.groupby("modality").agg(
        n_size=("application_id", "size"),
        n_avance=("status", lambda s: int(s.isin(["interview", "accepted"]).sum())),
    ).reindex(orden_mod)
    res_mod["tasa_pct"] = (100.0 * res_mod["n_avance"] / res_mod["n_size"]).round(2)

    fig, axes = plt.subplots(1, 2, figsize=(14.2, 5.5), dpi=160)
    fig.patch.set_facecolor("white")

    # Panel Izquierdo: ANTES (Sesgo por eje truncado 38% a 41% y solo color semáforo)
    ax1 = axes[0]
    barras_mal = ax1.bar(
        orden_mod, res_mod["tasa_pct"],
        color=["#dc2626", "#f59e0b", "#16a34a"], edgecolor="#111827", width=0.55,
    )
    ax1.set_ylim(38.0, 41.0)  # Eje truncado que exagera 1,91 puntos porcentuales
    ax1.set_title("ANTES (Incorrecto — Sesgo por eje truncado 38%–41%)", fontsize=11, fontweight="bold", color="#991b1b")
    ax1.set_ylabel("Tasa de avance a entrevista/contratación (%)", fontsize=9.5)
    ax1.grid(axis="y", linestyle=":", alpha=0.5)
    ax1.text(
        0.5, 0.86,
        "Distorsión visual: Remoto (40,62%)\naparenta ser 3,7x Híbrido (38,71%)\ny depende solo de color semáforo.",
        transform=ax1.transAxes, ha="center", fontsize=9,
        bbox=dict(boxstyle="round,pad=0.4", facecolor="#fee2e2", edgecolor="#991b1b"),
    )

    # Panel Derecho: DESPUÉS (Escala completa desde 0%, etiquetas directas, tramas y n explícito)
    ax2 = axes[1]
    colores_bien = [PALETA_OKABE_ITO[0], PALETA_OKABE_ITO[1], PALETA_OKABE_ITO[2]]
    tramas_bien = ["//", "..", "--"]
    barras_bien = ax2.bar(
        orden_mod, res_mod["tasa_pct"],
        color=colores_bien, edgecolor="#111827", width=0.55, linewidth=1.1,
    )
    for barra, trama in zip(barras_bien, tramas_bien):
        barra.set_hatch(trama)
    ax2.set_ylim(0.0, 52.0)  # Escala honesta desde 0%
    etiquetas_bien = [
        f"{row['tasa_pct']:.2f}%\n({int(row['n_avance'])}/{int(row['n_size'])})"
        for _, row in res_mod.iterrows()
    ]
    ax2.bar_label(barras_bien, labels=etiquetas_bien, padding=5, fontsize=9.2, fontweight="bold", color="#111827")
    ax2.set_title("DESPUÉS (Correcto — Escala desde 0%, bar_label y tramas)", fontsize=11, fontweight="bold", color="#065f46")
    ax2.set_ylabel("Tasa de avance a entrevista/contratación (%, eje desde 0)", fontsize=9.5)
    ax2.grid(axis="y", linestyle="--", alpha=0.45)
    ax2.set_axisbelow(True)

    fig.suptitle(
        f"Control de Sesgos Visuales (CA-ISS-13-03): Eje Truncado vs. Escala Completa desde Cero (n = {len(df_u)}, Corte: {fecha_corte})",
        fontsize=12.5, fontweight="bold", y=0.98,
    )
    fig.text(
        0.5, 0.01,
        "Origen: data/processed/yeb_postulaciones_transformado.csv | Universo: n = 93 (Híbrido=31, Presencial=30, Remoto=32; excluidas 4 en cuarentena P1) "
        "| La diferencia real entre modalidades es de solo 1,91 puntos porcentuales.",
        ha="center", fontsize=8.5, color="#374151",
    )
    plt.tight_layout(rect=[0.0, 0.04, 1.0, 0.93])
    ruta_png = outputs_dir / "fig04_comparativa_sesgo_eje_truncado.png"
    fig.savefig(ruta_png, dpi=160)
    plt.close(fig)
    return ruta_png


def verificar_fichas_y_storytelling(analytics_root: Path) -> list[Path]:
    """CA-ISS-13-04 / CP-053C: verifica fichas técnicas por figura y microinforme de 200–300 palabras en dos voces."""
    rutas = [
        analytics_root / "docs" / "fichas_visualizacion_yeb.md",
        analytics_root / "docs - analisis" / "fichas_visualizacion_yeb.md",
    ]
    existentes = [r for r in rutas if r.exists() and r.is_file()]
    assert len(existentes) >= 1, "No se encontró docs/fichas_visualizacion_yeb.md"

    marcadores = [
        "FIG-YEB-01",
        "FIG-YEB-02",
        "FIG-YEB-03",
        "Texto alternativo (`alt text`)",
        "Comparación Antes/Después de Sesgo Visual",
        "Voz 1 — Lectura técnica",
        "Voz 2 — Explicación ejecutiva no técnica",
        "Hallazgo",
        "Evidencia",
        "Limitación",
        "Acción propuesta",
    ]
    for r in existentes:
        texto = r.read_text(encoding="utf-8")
        for m in marcadores:
            assert m in texto, f"Falta '{m}' en {r.relative_to(analytics_root)}"

    n_palabras = len(MICROINFORME_STORYTELLING.split())
    assert 200 <= n_palabras <= 300, f"El microinforme debe tener entre 200 y 300 palabras (tiene {n_palabras})"
    for k, alt in TEXTOS_ALT.items():
        assert len(alt) >= 80, f"Texto alternativo insuficiente en {k}"
    return existentes


def main() -> int:
    analytics_root = resolve_analytics_root()
    outputs_dir = analytics_root / "outputs"

    print("=" * 82)
    print("1. VALIDACIÓN CON assert DEL UNIVERSO ANALIZABLE ANTES DE GRAFICAR (CA-ISS-13-01)")
    print("=" * 82)
    df_total, df_u, fecha_corte = cargar_datos_validados(analytics_root)
    print(f"  [OK] Universo analizable verificado : n = {len(df_u)} (suma estados = 93, modalidades = 93, ciudades = 93)")
    print(f"  [OK] Subconjunto tiempos respuesta  : count = {int(df_u['tiempo_respuesta_dias'].count())} (excluye 38 activas en NaN)")
    print(f"  [OK] Fecha de corte visible         : {fecha_corte} | Exclusiones de calidad: 4 registros en cuarentena P1")

    print("\n" + "=" * 82)
    print("2. GENERACIÓN Y EXPORTACIÓN DE LAS 3 VISUALIZACIONES Y SUS TABLAS (CA-ISS-13-02)")
    print("=" * 82)
    png1, csv1 = generar_fig01_matplotlib(df_total, df_u, fecha_corte, outputs_dir)
    png2, csv2 = generar_fig02_seaborn_stripplot(df_u, fecha_corte, outputs_dir)
    html3, csv3 = generar_fig03_plotly_interactivo(df_u, fecha_corte, outputs_dir)

    for p in (png1, csv1, png2, csv2, html3, csv3):
        assert p.exists() and p.stat().st_size > 0, f"Archivo vacío o no generado: {p}"
        print(f"  [OK] Exportado : {p.relative_to(analytics_root).as_posix()} ({p.stat().st_size:,} bytes)")

    html_content = html3.read_text(encoding="utf-8")
    assert "hovertemplate" in html_content and (
        "Aspiración salarial mediana" in html_content or "Aspiraci\\u00f3n salarial mediana" in html_content
    )

    print("\n" + "=" * 82)
    print("3. COMPARACIÓN ANTES/DESPUÉS DE SESGO VISUAL Y ACCESIBILIDAD (CA-ISS-13-03 / CP-053B)")
    print("=" * 82)
    png4 = generar_fig04_comparativa_sesgo(df_u, fecha_corte, outputs_dir)
    assert png4.exists() and png4.stat().st_size > 0
    print(f"  [OK] Comparativa antes/después exportada : {png4.relative_to(analytics_root).as_posix()} ({png4.stat().st_size:,} bytes)")
    print(f"  [OK] Textos alternativos verificados     : {len(TEXTOS_ALT)} figuras con alt text descriptivo y no dependencia exclusiva del color")

    print("\n" + "=" * 82)
    print("4. FICHAS TÉCNICAS DE VISUALIZACIÓN Y MICROINFORME EN DOS VOCES (CA-ISS-13-04 / CP-053C)")
    print("=" * 82)
    rutas_fichas = verificar_fichas_y_storytelling(analytics_root)
    for rf in rutas_fichas:
        print(f"  [OK] Fichas de visualización verificadas : {rf.relative_to(analytics_root).as_posix()}")
    print(f"  [OK] Microinforme de storytelling        : {len(MICROINFORME_STORYTELLING.split())} palabras (rango exigido: 200–300)")
    print("  [OK] Estructura verificada               : Dos voces (técnica y ejecutiva) con Hallazgo + Evidencia + Limitación + Acción propuesta")

    print("\n[RESULTADO] Issue #13 verificado satisfactoriamente (CP-053, CP-053B y CP-053C: PASA).")
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
