# Fichas Técnicas de Indicadores (KPI) — Youth Employment Bridge (YEB)

**Módulo:** `analytics/` (Capa Analítica *Split–Apply–Combine*) | **Issue:** `#11 [Data/KPI]` | **Criterios cubiertos:** `CA-ISS-11-01` a `CA-ISS-11-06`  
**Fuente inmutable (Semana 5):** [`analytics/data/processed/yeb_postulaciones_transformado.csv`](../data/processed/yeb_postulaciones_transformado.csv)  
**Notebook:** [`analytics/notebooks/06_kpi_agregaciones_yeb.ipynb`](../notebooks/06_kpi_agregaciones_yeb.ipynb) | **Script verificador:** [`analytics/scripts/11_kpi_agregaciones_yeb.py`](../scripts/11_kpi_agregaciones_yeb.py)  
**Salida consolidada:** [`analytics/outputs/yeb_kpis_semana6.csv`](../outputs/yeb_kpis_semana6.csv) | **Riesgos mitigados:** `NEG-009`, `TEC-016`, `LEG-001` | **Casos de prueba:** `CP-051`, `CP-051B`, `CP-051C`

---

## 1. Definición del Universo Analizable y Regla de Denominadores (`CA-ISS-11-01` / `NEG-009`)

Antes de calcular cualquier indicador para el panel administrativo (`Modules/Dashboard`), el flujo separa las filas recibidas en la fuente transformada de la Semana 5 y distingue los tres operadores de conteo de Pandas:

| Concepto | Valor en Semana 6 | Definición técnica y uso como denominador |
| :-- | :-: | :-- |
| **Total de filas recibidas** | `97` | Total de registros en `yeb_postulaciones_transformado.csv` (`93` de `dataset_preparado` + `4` de `cuarentena_issue_05`). |
| **Exclusiones de calidad (`P1`)** | `4` | Registros con `origen_registro == "cuarentena_issue_05"` aislados por colisión de identificadores o contradicción de edad (`APP-2026-0025`, `APP-2026-9002` y los dos registros en conflicto de `APP-2026-0010`). |
| **`size()` — Universo analizable ($N$)** | `93` | Total de filas/postulaciones válidas (`origen_registro == "dataset_preparado"`), incluyendo aquellas que tienen `NaN` en variables condicionales como `tiempo_respuesta_dias`. |
| **`count()` — Valores no nulos** | `55` (en `tiempo_respuesta_dias`) / `38` (en `dias_sin_actualizar`) | Cuenta únicamente las celdas con dato presente (`notna()`). Es el denominador obligatorio para promedios y desviaciones de tiempos de respuesta (`55` postulaciones respondidas), evitando diluir el cálculo entre las `38` postulaciones aún activas (`NaN`). |
| **`nunique()` — Entidades únicas** | `93` candidatos (`candidate_hash`) / `18` vacantes (`job_id`) | Cuenta identificadores distintos sin repetir. En el total recibido de `97` filas, `size() = 97` difiere de `application_id.nunique() = 96` y `documento_hash.nunique() = 96` debido a las colisiones detectadas en cuarentena. |

---

## 2. Catálogo de Fichas Técnicas de KPI (`CA-ISS-11-05`)

### Ficha Técnica #01 — `KPI-YEB-01`: Tasa de Avance a Entrevista o Contratación

| Atributo | Especificación |
| :-- | :-- |
| **Código y Nombre** | `KPI-YEB-01` — Tasa de Avance a Entrevista o Contratación (`tasa_entrevista_pct`) |
| **Pregunta de negocio** | ¿Qué proporción de las postulaciones válidas alcanza una etapa de contacto efectivo con la empresa (`interview` o `accepted`) según ciudad, modalidad y grupo etario? |
| **Fórmula matemática** | $$\text{KPI-YEB-01}_g = \left( \frac{\sum_{i \in g} \mathbb{I}(\text{status}_i \in \{\text{interview}, \text{accepted}\})}{\text{size}(g)} \right) \times 100$$ |
| **Numerador** | Conteo de postulaciones del segmento $g$ cuyo `status` es `interview` o `accepted` (global: `37` postulaciones = `17` en `interview` + `20` en `accepted`). |
| **Denominador** | `size(g)`: total de postulaciones del universo analizable en el segmento $g$ (global: `93` postulaciones; `72` en jóvenes adultos 18–24 y `21` en menores protegidos 16–17). |
| **Unidad de medida** | Porcentaje (`%`), redondeado a 2 decimales. |
| **Fecha de corte** | `2026-02-02` (máximo `updated_at` reproducible del universo analizable). |
| **Universo** | `93` postulaciones válidas (`origen_registro == "dataset_preparado"`), correspondientes a `93` candidatos únicos (`nunique(candidate_hash) = 93`) sobre `18` vacantes (`nunique(job_id) = 18`). |
| **Exclusiones** | Se excluyen `4` registros de `cuarentena_issue_05` (`P1 - Revisar identidad o edad`). En desgloses `ciudad_normalizada × modality`, las `9` celdas sin observaciones se mantienen como `NaN` y nunca como `0.0%`. |
| **Resultados de referencia** | **Global:** `39.78%` (`37/93`) · **Modalidad:** Remoto `40.62%` (`13/32`), Presencial `40.00%` (`12/30`), Híbrido `38.71%` (`12/31`) · **Grupo etario:** Joven adulto (18–24) `43.06%` (`31/72`) vs. Menor protegido (16–17) `28.57%` (`6/21`). |
| **Advertencia de uso no causal** | Este indicador es estrictamente **descriptivo**. Una menor tasa de avance en el grupo de menores protegidos (`28.57%` vs. `43.06%`) **no demuestra causalidad ni sesgo de selección**, ya que está condicionada por variables de confusión como la disponibilidad de media jornada, el tipo de vacante que admite menores y los tiempos de verificación documental (`LEG-001`). Tampoco debe interpretarse en subgrupos de tamaño unitario (`Barranquilla × Híbrido`, `Bogotá × Remoto`, `Cali × Presencial`, donde `size = 1`). |

---

### Ficha Técnica #02 — `KPI-YEB-02`: Tiempo de Respuesta al Candidato en Días

| Atributo | Especificación |
| :-- | :-- |
| **Código y Nombre** | `KPI-YEB-02` — Tiempo de Respuesta al Candidato (`tiempo_resp_media`, `tiempo_resp_mediana`, `tiempo_resp_std`) |
| **Pregunta de negocio** | ¿Cuántos días calendario transcurren entre la postulación (`applied_at`) y la respuesta de la empresa (`updated_at`) en las postulaciones que ya salieron de etapa activa? |
| **Fórmula matemática** | $$\bar{t}_g = \frac{\sum_{i \in R_g} (\text{updated\_at}_i - \text{applied\_at}_i)}{\text{count}(\text{tiempo\_respuesta\_dias}_g)}, \quad s_g = \sqrt{\frac{1}{\text{count}_g - 1}\sum_{i \in R_g}(t_i - \bar{t}_g)^2}$$ |
| **Numerador** | Suma (para la media) o valores ordenados (para la mediana) de `tiempo_respuesta_dias` sobre el subconjunto respondido $R_g$ (global: suma de `220.0` días). |
| **Denominador** | `count(tiempo_respuesta_dias)` en el segmento $g$ (global: `55` postulaciones respondidas). Para la desviación estándar muestral ($s_g$, `ddof=1`) el denominador es $\text{count}_g - 1$; si $\text{count}_g \le 1$, $s_g$ es `NaN` por definición matemática. |
| **Unidad de medida** | Días calendario (`días`). |
| **Fecha de corte** | `2026-02-02`. |
| **Universo** | Subconjunto de `55` postulaciones con respuesta (`status` en `interview`, `accepted` o `rejected`) dentro del universo analizable de `93` postulaciones. |
| **Exclusiones** | Se excluyen: (a) los `4` registros en `cuarentena_issue_05`, y (b) las `38` postulaciones activas (`20` en `submitted` y `18` en `under_review`), cuyo `tiempo_respuesta_dias` permanece como `NaN` para no sesgar el promedio hacia cero. En grupos con `count == 1` (`Barranquilla × Híbrido`, `Cali × Presencial`, `Bogotá × Menor protegido`, `Medellín × Menor protegido`) la desviación estándar se conserva como `NaN`, y en `Bogotá × Remoto` (`count == 0`) media, mediana y desviación quedan como `NaN`. |
| **Resultados de referencia** | **Global (`count = 55`):** media `4.00` días, mediana `4.00` días, desv. estándar `2.48` días · **Modalidad:** Híbrido (`count = 19`) media `4.37` / mediana `4.00` / `std = 2.31`; Remoto (`count = 18`) media `3.83` / mediana `4.00` / `std = 2.55`; Presencial (`count = 18`) media `3.78` / mediana `3.00` / `std = 2.67` · **Grupo etario:** Joven adulto (`count = 43`) media `3.79` / mediana `3.00` / `std = 2.52` vs. Menor protegido (`count = 12`) media `4.75` / mediana `5.50` / `std = 2.26`. |
| **Advertencia de uso no causal** | Indicador **descriptivo de oportunidad operativa**. No mide la duración total de contratación ni evalúa el desempeño individual del reclutador, dado que `updated_at` refleja el último cambio de estado registrado y el volumen de vacantes varía por sector y ciudad. Nunca debe reemplazarse un `NaN` de desviación estándar (`count <= 1`) por `0`, pues indicaría falsamente variabilidad nula verificada. |

---

### Ficha Técnica #03 — `KPI-YEB-03`: Aspiración Salarial y Brecha frente a la Oferta Media

| Atributo | Especificación |
| :-- | :-- |
| **Código y Nombre** | `KPI-YEB-03` — Aspiración Salarial y Brecha Porcentual (`salario_esp_mediana`, `brecha_salarial_media_pct`) |
| **Pregunta de negocio** | ¿Cuál es el nivel de aspiración salarial en pesos colombianos (`COP`) y su diferencia porcentual respecto al punto medio del rango salarial ofrecido por la vacante en cada ciudad, modalidad y cohorte de edad? |
| **Fórmula matemática** | $$\text{OfertaMedia}_i = \frac{\text{salary\_min}_i + \text{salary\_max}_i}{2}, \quad \text{BrechaPct}_i = \left(\frac{\text{expected\_salary}_i - \text{OfertaMedia}_i}{\text{OfertaMedia}_i}\right)\times 100$$ |
| **Numerador** | En aspiración salarial: valores de `expected_salary_cop` en el segmento $g$. En brecha porcentual: suma de `brecha_salarial_aspiracion_pct` en el segmento $g$ (global: `-212.16%`). |
| **Denominador** | `count(expected_salary_cop)` = `count(brecha_salarial_aspiracion_pct)` en el segmento $g$ (global: `93` observaciones válidas con `oferta_media_cop > 0`). |
| **Unidad de medida** | Pesos colombianos (`COP`) para la aspiración salarial y porcentaje (`%`) para la brecha salarial relativa. |
| **Fecha de corte** | `2026-02-02`. |
| **Universo** | `93` postulaciones válidas del universo analizable (`origen_registro == "dataset_preparado"`). |
| **Exclusiones** | Se excluyen las `4` filas de `cuarentena_issue_05`. En la tabla dinámica (`pivot_table`) por `ciudad_normalizada × modality`, las `9` combinaciones sin vacantes/postulaciones se conservan como `NaN` (evitando insertar salarios falsos de `$0 COP`), y en las `3` celdas con un único registro (`Barranquilla × Híbrido`, `Bogotá × Remoto`, `Cali × Presencial`) la desviación estándar muestral se mantiene como `NaN`. |
| **Resultados de referencia** | **Global (`count = 93`):** aspiración media `2.000.924,37 COP`, mediana `1.966.224,00 COP`, `std = 386.652,18 COP`; brecha media `-2.28%`, mediana `-2.30%`, `std = 9.30%` · **Modalidad:** Híbrido mediana `2.151.971,00 COP` (brecha media `-1.54%`), Remoto mediana `1.938.169,50 COP` (brecha media `-2.91%`), Presencial mediana `1.856.500,00 COP` (brecha media `-2.37%`). |
| **Advertencia de uso no causal** | Indicador **descriptivo de alineación salarial**. Una brecha negativa (`-2.28%`) indica que, en promedio, la expectativa declarada por el joven se ubica ligeramente por debajo del punto medio ofertado, pero **no causa por sí sola la contratación ni el rechazo** y no debe utilizarse para reducir automáticamente la oferta salarial a candidatos juveniles ni a menores protegidos. |
