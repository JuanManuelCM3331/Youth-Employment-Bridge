# Fichas Técnicas de Visualización, Control de Sesgos y Storytelling — Youth Employment Bridge (YEB)

**Módulo:** `analytics/` (Visualización Accesible y Storytelling) | **Issue:** `#13 [Data/Viz]` | **Criterios cubiertos:** `CA-ISS-13-01` a `CA-ISS-13-04`  
**Fuentes validadas (Semanas 5 y 6):** [`analytics/data/processed/yeb_postulaciones_transformado.csv`](../data/processed/yeb_postulaciones_transformado.csv), [`analytics/outputs/yeb_kpis_semana6.csv`](../outputs/yeb_kpis_semana6.csv)  
**Notebook:** [`analytics/notebooks/07_visualizacion_storytelling_yeb.ipynb`](../notebooks/07_visualizacion_storytelling_yeb.ipynb) | **Script verificador:** [`analytics/scripts/13_visualizacion_storytelling_yeb.py`](../scripts/13_visualizacion_storytelling_yeb.py)  
**Figuras y tablas exportadas:**
- [`analytics/outputs/fig01_embudo_postulaciones_matplotlib.png`](../outputs/fig01_embudo_postulaciones_matplotlib.png) + [`fig01_embudo_postulaciones_respaldo.csv`](../outputs/fig01_embudo_postulaciones_respaldo.csv)
- [`analytics/outputs/fig02_distribucion_tiempos_stripplot_seaborn.png`](../outputs/fig02_distribucion_tiempos_stripplot_seaborn.png) + [`fig02_distribucion_tiempos_respaldo.csv`](../outputs/fig02_distribucion_tiempos_respaldo.csv)
- [`analytics/outputs/fig03_indicadores_ciudad_plotly.html`](../outputs/fig03_indicadores_ciudad_plotly.html) + [`fig03_indicadores_ciudad_accesible.csv`](../outputs/fig03_indicadores_ciudad_accesible.csv)
- [`analytics/outputs/fig04_comparativa_sesgo_eje_truncado.png`](../outputs/fig04_comparativa_sesgo_eje_truncado.png)  
**Riesgos mitigados:** `NEG-011`, `UX-002` | **Casos de prueba:** `CP-053`, `CP-053B`, `CP-053C`

---

## 1. Validación Previa del Universo Analizable antes de Graficar (`CA-ISS-13-01` / `NEG-011`)

Antes de construir cualquiera de las figuras, el flujo verifica mediante aserciones (`assert`) que:
1. La suma de postulaciones por estado del embudo (`20 + 18 + 17 + 20 + 18`), por modalidad (`32 + 31 + 30`) y por ciudad (`17 + 16 + 16 + 15 + 15 + 14`) coincide exactamente con el **universo analizable `n = 93`** (`origen_registro == "dataset_preparado"`).
2. En la visualización de tiempos de respuesta (Figura 2), el subconjunto graficado corresponde exactamente a **`count = 55`** postulaciones con respuesta (`interview`, `accepted`, `rejected`), declarando de forma visible en el subtítulo las **`38` postulaciones activas excluidas (`NaN`)** y las **`4` filas de cuarentena (`P1`) excluidas** al corte `2026-02-02`.

---

## 2. Fichas Técnicas por Figura (`CA-ISS-13-02` / `CA-ISS-13-04`)

### Ficha Técnica de Visualización #01 — `FIG-YEB-01` (Matplotlib `.png`)

| Atributo | Especificación |
| :-- | :-- |
| **Identificador y Archivo** | `FIG-YEB-01` — [`outputs/fig01_embudo_postulaciones_matplotlib.png`](../outputs/fig01_embudo_postulaciones_matplotlib.png) |
| **Tabla de respaldo** | [`outputs/fig01_embudo_postulaciones_respaldo.csv`](../outputs/fig01_embudo_postulaciones_respaldo.csv) |
| **Librería y Tipo de gráfico** | `Matplotlib` — Gráfico de barras horizontales en dos paneles complementarios: (A) Estado del embudo de selección (`n = 93`) y (B) Cola de prioridad de atención operativa (`n = 97`, separando explícitamente las `4` filas de cuarentena `P1`). |
| **Título descriptivo** | *Embudo de Selección Juvenil (`n = 93`) y Cola de Prioridad Operativa (`n = 97`) al Corte `2026-02-02`* |
| **Variables y Unidad** | Eje Y: categoría del estado / nivel de prioridad · Eje X: número de postulaciones (`postulaciones`, entero) y participación porcentual (`%`). |
| **Denominador, Corte y Exclusiones** | Panel A: denominador `size = 93` postulaciones válidas (`origen_registro == "dataset_preparado"`), corte `2026-02-02`, excluidas `4` filas de `cuarentena_issue_05`. Panel B: denominador `97` filas recibidas para visibilizar los `4` conflictos de identidad `P1`. |
| **Control de sesgos y Accesibilidad (`UX-002`)** | Eje numérico X anclado estrictamente en `0` (`set_xlim(0, ...)`), etiquetas directas en el extremo de cada barra (`ax.bar_label`) con formato `conteo (porcentaje%)`, tramas geométricas diferenciadas (`hatch`: `//`, `..`, `xx`, `--`, `\\`) para no depender solo del color y paleta Okabe-Ito apta para daltonismo. |
| **Texto alternativo (`alt text`)** | *Gráfico de barras horizontales desde cero al corte 2026-02-02. En el Panel A (n=93 postulaciones válidas, 4 excluidas en cuarentena), el embudo registra: Enviada (submitted) 20 (21,51%), En revisión (under_review) 18 (19,35%), Entrevista (interview) 17 (18,28%), Contratada (accepted) 20 (21,51%) y Rechazada (rejected) 18 (19,35%). En el Panel B (n=97 recibidas), la cola de prioridad muestra: P1 Revisar identidad o edad 4 (4,12%), P2 Postulación desactualizada 34 (35,05%), P3 Apto para entrevista 1 (1,03%) y Sin acción prioritaria 58 (59,79%).* |
| **Advertencia de lectura no causal** | La proporción de postulaciones en estado `accepted` (`21,51%`) o `P2 - Postulación desactualizada` (`36,56%` sobre el universo de `93`, o `89,47%` de las `38` activas) describe el estado acumulado a la foto de corte y no mide la velocidad ni la eficacia causal de una empresa específica. |

---

### Ficha Técnica de Visualización #02 — `FIG-YEB-02` (Seaborn `.png`)

| Atributo | Especificación |
| :-- | :-- |
| **Identificador y Archivo** | `FIG-YEB-02` — [`outputs/fig02_distribucion_tiempos_stripplot_seaborn.png`](../outputs/fig02_distribucion_tiempos_stripplot_seaborn.png) |
| **Tabla de respaldo** | [`outputs/fig02_distribucion_tiempos_respaldo.csv`](../outputs/fig02_distribucion_tiempos_respaldo.csv) |
| **Librería y Tipo de gráfico** | `Seaborn` — Gráfico de puntos (`sns.stripplot`) en dos paneles: (A) Tiempo de respuesta en días (`count = 55` respondidas) y (B) Aspiración salarial en millones de COP (`count = 93`), desglosados por modalidad (`Remoto`, `Híbrido`, `Presencial`) y grupo etario (`Joven adulto (18-24)` vs. `Menor protegido (16-17)`), con marcadores de mediana por segmento. |
| **Título descriptivo** | *Distribución Individual (`stripplot`) de Tiempos de Respuesta (`n = 55`) y Aspiración Salarial (`n = 93`) por Modalidad y Grupo Etario* |
| **Variables y Unidad** | Panel A: `tiempo_respuesta_dias` (`días`) · Panel B: `expected_salary_cop / 1e6` (`millones de COP`) · Eje X: `modality` · Subgrupo: `grupo_etario`. |
| **Denominador, Corte y Exclusiones** | Panel A: `count = 55` postulaciones respondidas (`interview`, `accepted`, `rejected`); excluye `38` postulaciones activas (`NaN`) y `4` registros en cuarentena (`P1`). Panel B: `count = 93` postulaciones válidas; excluye `4` registros en cuarentena (`P1`). Corte: `2026-02-02`. |
| **Control de sesgos y Accesibilidad (`NEG-011` / `UX-002`)** | Se utiliza `stripplot` en lugar de *boxplot* o *violinplot* porque la cohorte de menores protegidos respondidos tiene solo `12` observaciones en total (`3` en Híbrido, `4` en Remoto y `5` en Presencial): un *boxplot* ocultaría el tamaño muestral reducido tras cuartiles engañosos. Además del color Okabe-Ito, se superponen diamantes negros para las medianas con etiquetas numéricas directas. |
| **Texto alternativo (`alt text`)** | *Gráfico de puntos (stripplot) de Seaborn al corte 2026-02-02. El Panel A muestra cada una de las 55 postulaciones respondidas (38 activas en NaN y 4 en cuarentena excluidas): la mediana de tiempo de respuesta es 4,0 días en Remoto (n=18) e Híbrido (n=19), y 3,0 días en Presencial (n=18); los menores protegidos suman 12 observaciones con mediana global de 5,5 días frente a 3,0 días en jóvenes adultos (n=43). El Panel B muestra las 93 aspiraciones salariales válidas: medianas de 2,15 millones COP en Híbrido (n=31), 1,94 millones COP en Remoto (n=32) y 1,86 millones COP en Presencial (n=30).* |
| **Advertencia de lectura no causal** | La mayor mediana de tiempo de respuesta observada en los `12` casos respondidos de menores protegidos (`5,5` días vs. `3,0` días) proviene de un subgrupo pequeño y refleja tiempos de validación documental, no una demora atribuible a la modalidad de trabajo. |

---

### Ficha Técnica de Visualización #03 — `FIG-YEB-03` (Plotly `.html` + `.csv`)

| Atributo | Especificación |
| :-- | :-- |
| **Identificador y Archivos** | `FIG-YEB-03` — [`outputs/fig03_indicadores_ciudad_plotly.html`](../outputs/fig03_indicadores_ciudad_plotly.html) y tabla accesible obligatoria [`outputs/fig03_indicadores_ciudad_accesible.csv`](../outputs/fig03_indicadores_ciudad_accesible.csv) |
| **Librería y Tipo de gráfico** | `Plotly` — Gráfico interactivo combinado por ciudad reportada (`ciudad_normalizada`): barras de volumen de postulaciones (`size`) y postulaciones en entrevista/contratadas, con `hovertemplate` enriquecido que expone todos los indicadores de la ciudad. |
| **Título descriptivo** | *Indicadores de Postulación, Avance y Aspiración Salarial por Ciudad Reportada (`n = 93`, Corte `2026-02-02`)* |
| **Variables y Unidad** | Eje X: `ciudad_normalizada` (`Bogotá`, `Pereira`, `Cali`, `Medellín`, `Barranquilla`, `Bucaramanga`) · Eje Y: conteo de postulaciones (`postulaciones`, eje desde `0`) · `hovertemplate`: departamento, modalidad predominante, `n_filas_size`, `n_respondidas_count`, `tasa_entrevista_pct` (`%`), `tasa_contratacion_pct` (`%`), `salario_esp_mediana` (`COP`) y `tiempo_resp_media` (`días`). |
| **Denominador, Corte y Exclusiones** | Universo `n = 93` postulaciones válidas (`suma de las 6 ciudades = 17 + 16 + 16 + 15 + 15 + 14 = 93`), corte `2026-02-02`, excluidas `4` filas de `cuarentena_issue_05`. |
| **Control de sesgos y Accesibilidad (`UX-002`)** | Eje Y anclado en `0`, etiquetas numéricas directas sobre cada barra (`textposition="outside"`), `hovertemplate` estructurado en lenguaje claro sin valores en notación científica y exportación sincronizada de [`fig03_indicadores_ciudad_accesible.csv`](../outputs/fig03_indicadores_ciudad_accesible.csv) con exactamente las mismas `6` filas y columnas para lectores de pantalla o consulta tabular sin JavaScript. |
| **Texto alternativo (`alt text`)** | *Gráfico interactivo de barras agrupadas por ciudad al corte 2026-02-02 sobre n=93 postulaciones válidas (4 en cuarentena excluidas), respaldado por fig03_indicadores_ciudad_accesible.csv. Postulaciones totales y avance a entrevista/contratación por ciudad: Bogotá 17 postulaciones y 7 en entrevista/contratadas (41,18%, salario mediano 2.151.971 COP); Pereira 16 y 7 (43,75%, 1.883.850 COP); Cali 16 y 7 (43,75%, 2.025.630,50 COP); Medellín 15 y 7 (46,67%, 1.869.998 COP); Barranquilla 15 y 5 (33,33%, 1.794.093 COP); Bucaramanga 14 y 4 (28,57%, 2.214.090 COP).* |
| **Advertencia de lectura no causal** | Las variaciones por ciudad (`28,57%` en Bucaramanga a `46,67%` en Medellín) corresponden a tamaños de muestra de `14` a `17` postulaciones donde cada ciudad concentra una modalidad predominante distinta, por lo que no permiten ranquear causalmente la empleabilidad territorial. |

---

## 3. Comparación Antes/Después de Sesgo Visual (`CA-ISS-13-03` / `CP-053B`)

El archivo [`outputs/fig04_comparativa_sesgo_eje_truncado.png`](../outputs/fig04_comparativa_sesgo_eje_truncado.png) contrasta dos representaciones del mismo indicador (`KPI-YEB-01`: tasa de avance a entrevista o contratación por modalidad: `Híbrido 38,71%`, `Presencial 40,00%`, `Remoto 40,62%`):

| Dimensión evaluada | Antes (Con sesgo visual — Incorrecto) | Después (Corregido y accesible — Correcto) |
| :-- | :-- | :-- |
| **Escala del eje numérico** | Eje Y truncado en `[38,0%, 41,0%]`: la barra de `Remoto` (`40,62%`) aparenta ser **3,7 veces más alta** que la de `Híbrido` (`38,71%`), fabricando una falsa brecha crítica donde solo hay `1,91` puntos porcentuales de diferencia. | Eje Y completo iniciando en `0%` (`[0%, 50%]`): las longitudes de las barras guardan proporción real con los datos (`38,71%`, `40,00%` y `40,62%`), mostrando estabilidad entre las tres modalidades. |
| **Codificación visual y color** | Depende únicamente de colores saturados (rojo/amarillo/verde tipo semáforo) sin etiquetas ni patrones. | Paleta Okabe-Ito diferenciable en monocromo + tramas geométricas (`hatch`) + etiquetas directas `ax.bar_label` con porcentaje y fracción (`num/den`). |
| **Contexto metodológico** | Omite tamaño de muestra `n`, fecha de corte y registros excluidos. | Declara en título y nota al pie: `n = 93` (`Híbrido n=31`, `Presencial n=30`, `Remoto n=32`), corte `2026-02-02` y `4` exclusiones de cuarentena `P1`. |

- **Texto alternativo (`alt text` de `FIG-YEB-04`):** *Comparación lado a lado de sesgo por eje truncado sobre la tasa de avance a entrevista/contratación por modalidad (n=93, corte 2026-02-02). A la izquierda, un eje truncado entre 38% y 41% distorsiona visualmente las tasas de Híbrido (38,71%), Presencial (40,00%) y Remoto (40,62%) haciendo parecer a Remoto casi cuatro veces mayor que Híbrido. A la derecha, la escala completa desde 0% con etiquetas directas (12/31, 12/30 y 13/32) y tramas accesibles demuestra que las tres modalidades tienen tasas prácticamente equivalentes alrededor del 39,8%.*

---

## 4. Microinforme de Storytelling en Dos Voces (`CA-ISS-13-04` / `CP-053C`)

> **Longitud verificada automáticamente en `13_visualizacion_storytelling_yeb.py`:** `244` palabras (dentro del rango exigido de **200 a 300 palabras**).

**Voz 1 — Lectura técnica (Equipo de Datos e Ingeniería):**  
- **Hallazgo:** Al corte `2026-02-02`, el universo analizable (`n=93`, excluidos `4` registros en cuarentena `P1`) presenta una conversión global a entrevista o contratación del `39,78%` (`37/93`), pero concentra un cuello de botella operativo en las postulaciones activas: `34` de las `38` solicitudes en `submitted` o `under_review` (`89,47%`) superan el umbral del percentil 90 de respuesta (`7` días) y caen en `P2 - Postulación desactualizada`, mientras las `55` respondidas mantienen una mediana de `4,0` días (`IQR` acotado entre `2` y `6` días).  
- **Evidencia:** `FIG-YEB-01` confirma la cuadratura del embudo (`20+18+17+20+18=93`) y `FIG-YEB-02` (`stripplot`) muestra que en menores protegidos (`16–17` años) solo existen `12` casos respondidos con mediana de `5,5` días frente a `3,0` días en adultos jóvenes (`n=43`).  
- **Limitación:** Los conteos por subgrupo ciudad-modalidad incluyen `9` celdas vacías (`NaN`) y `3` celdas unitarias (`n=1`), por lo que las diferencias observadas son descriptivas y no prueban relaciones causales.  
- **Acción propuesta:** Integrar en `Modules/Dashboard` una alerta preventiva cuando `dias_sin_actualizar > 7` en estados activos y exigir revisión humana de los `4` casos `P1`.

**Voz 2 — Explicación ejecutiva no técnica (Administrador del Portal YEB):**  
- **Hallazgo y Evidencia:** Cuatro de cada diez postulaciones válidas (`39,78%`) logran entrevista o contratación y, cuando las empresas responden, suelen hacerlo en unos `4` días; sin embargo, `34` jóvenes que siguen esperando respuesta llevan más de una semana sin movimiento.  
- **Limitación:** Estos números son una fotografía del periodo y no indican qué empresa o ciudad sea mejor, pues varias combinaciones tienen muy pocos casos.  
- **Acción propuesta:** Priorizar hoy el recordatorio a empresas con esas `34` postulaciones estancadas y revisar manualmente los documentos legales de menores y los `4` perfiles en cuarentena antes de autorizar cualquier entrevista.
