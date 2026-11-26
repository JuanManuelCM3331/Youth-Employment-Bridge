# Ficha de Metadatos de Fuentes Integradas y Análisis de Datos Abiertos — Youth Employment Bridge (YEB)

**Módulo:** `analytics/` (Integración Multifuente y Reestructuración Tabular) | **Issue:** `#15 [Data/Integration]` | **Criterios cubiertos:** `CA-ISS-15-01` a `CA-ISS-15-06`  
**Notebook:** [`analytics/notebooks/08_integracion_reestructuracion_yeb.ipynb`](../notebooks/08_integracion_reestructuracion_yeb.ipynb) | **Script verificador:** [`analytics/scripts/15_integracion_reestructuracion_yeb.py`](../scripts/15_integracion_reestructuracion_yeb.py)  
**Salidas auditadas:**
- [`analytics/outputs/yeb_postulaciones_integradas_semana8.csv`](../outputs/yeb_postulaciones_integradas_semana8.csv)
- [`analytics/outputs/auditoria_integracion_joins_semana8.csv`](../outputs/auditoria_integracion_joins_semana8.csv)
- [`analytics/outputs/yeb_longitudinal_2026A_2026B.csv`](../outputs/yeb_longitudinal_2026A_2026B.csv)
- [`analytics/outputs/contextualizacion_datos_abiertos_sena.csv`](../outputs/contextualizacion_datos_abiertos_sena.csv)  
**Riesgos mitigados:** `NEG-012`, `TEC-019` | **Casos de prueba:** `CP-055`, `CP-055B`, `CP-055C`

---

## 1. Inventario y Metadatos de Fuentes Integradas (`CA-ISS-15-06`)

| # | Fuente / Archivo | Procedencia y Propietario | Granularidad (1 fila = …) | Llave primaria / foránea | Cardinalidad respecto a la base (`n=93`) | Licencia y Gobernanza | Limitaciones conocidas |
| :-: | :-- | :-- | :-- | :-- | :-- | :-- | :-- |
| **F1** | [`data/processed/yeb_postulaciones_transformado.csv`](../data/processed/yeb_postulaciones_transformado.csv) *(Línea base S5–S7)* | Extracción transformada del portal YEB (`YEB_MYSQL_PORTAL`, lote `BATCH-2026-W03`, corte `2026-02-02`). Consumo estrictamente de **solo lectura** verificado por `SHA-256`. | 1 postulación individual (`97` recibidas; universo analizable `n=93` con `origen_registro == "dataset_preparado"`). | **PK:** `application_id` · **FKs:** `job_id`, `candidate_hash` | `1:1` (universo de referencia inmutable). | Interna YEB — Datos seudonimizados (`candidate_hash`, `documento_hash`) bajo protocolo `LEG-001` (Ley 1581/2012 y protección de menores 16–17 años). | Excluye `4` registros en `cuarentena_issue_05` (`P1`). No almacena historial completo de transiciones de estado (`updated_at` refleja únicamente el último cambio). |
| **F2** | [`data/raw/yeb_catalogo_vacantes_empresas.csv`](../data/raw/yeb_catalogo_vacantes_empresas.csv) *(Catálogo maestro)* | Módulo de gestión de empresas y vacantes YEB (`Modules/Companies`, `Modules/Jobs`). | 1 vacante publicada o en borrador con atributos maestros de su empresa (`21` filas: `JOB-2026-001` a `JOB-2026-021`). | **PK:** `job_id` (estrictamente único) · **Atributo de agrupación:** `company_id` | **`many_to_one`** (`N` postulaciones → `1` vacante). Auditado con `merge(how="left", validate="many_to_one", indicator=True)`. | Interna YEB — Uso analítico restringido (`nit_anon` anonimizado). | Contiene `3` vacantes sin postulantes activos en `2026-A` (`JOB-2026-019`, `JOB-2026-020`, `JOB-2026-021`, una en `draft`), detectadas mediante auditoría `outer` (`right_only`). |
| **F3** | [`data/raw/yeb_eventos_seguimiento.csv`](../data/raw/yeb_eventos_seguimiento.csv) *(Log de seguimiento 1:N)* | Bitácora operativa de mensajería y seguimiento de YEB (`Modules/Applications`, chat y notificaciones push). | 1 evento individual de interacción (`426` filas: `chat_message_*`, `cv_viewed`, `profile_checked`, `interview_invite`, `status_notification`). | **PK:** `event_id` · **FK:** `application_id` | **`1:N`** (`1` postulación → `0..N` eventos). **Prohibido unir directamente**; exige pre-agregación `groupby(["application_id"]).agg(...)` y luego `validate="one_to_one"`. | Interna YEB — Sin contenido textual libre de mensajes (solo tipo de evento, rol actor, marca temporal y latencia en minutos). | `5` postulaciones recientes en `submitted` aún no registran eventos (`left_only`): sus conteos se imputan a `0`, mientras `latencia_media_eventos_min` se conserva legítimamente en `NaN`. |
| **F4** | [`data/raw/yeb_postulaciones_cohorte_2026B.csv`](../data/raw/yeb_postulaciones_cohorte_2026B.csv) *(Cohorte longitudinal 2026-B)* | Extracción del segundo periodo semestral YEB (`BATCH-2026-W28`, julio–agosto `2026`) con esquema canónico idéntico a `2026-A`. | 1 postulación individual del periodo `2026-B` (`60` filas válidas). | **PK:** `application_id` · **Llave longitudinal compuesta:** `(candidate_hash, job_id, periodo)` | **Apilado vertical (`concat()`)**: `93 (2026-A) + 60 (2026-B) = 153` filas con `0` duplicados en llave compuesta. | Interna YEB — Misma política de seudonimización y protección `LEG-001` que `F1`. | `12` candidatos de `2026-A` son recurrentes en `2026-B`; por ello `candidate_hash` no es único a lo largo del tiempo y toda deduplicación longitudinal exige incluir `periodo`. |
| **F5** | **Datos Abiertos de Colombia — SENA (`28vu-5tx7`)**<br>[`data/external/datos_abiertos_sena_28vu_5tx7_departamentos.csv`](../data/external/datos_abiertos_sena_28vu_5tx7_departamentos.csv) | Portal Nacional de Datos Abiertos [`datos.gov.co`](https://www.datos.gov.co/d/28vu-5tx7) — **Servicio Nacional de Aprendizaje (SENA, Bogotá D.C.)**, conjunto *«CERTIFICACIÓN DE LA FORMACIÓN PROFESIONAL INTEGRAL»* (API Socrata `/resource/28vu-5tx7.json`). | **Macro-agregado administrativo:** en origen, 1 fila = 1 ficha de formación (`8.538` fichas); en el resumen consultado, 1 fila = 1 departamento (`32` departamentos, corte vigencia `periodo = 202402`, actualizado `2024-03-14`). | **Llave territorial agregada:** `nombre_departamento_curso` (normalizada a `departamento_sena`). **Sin llave individual** compatible con `application_id`. | **Referencia macro paralela** (`1:1` únicamente a grano de los `6` departamentos agregados; **nunca** `merge` sobre microdatos individuales). | **CC BY-SA 4.0 International** (*Creative Commons Attribution – Share Alike 4.0*). | Desfase temporal (`202402` vs. `2026-A`), definición focalizada de `tipo_poblacion__jovenes` (población joven priorizada por SENA, no todo el universo `16–24`), y mezcla de cursos cortos (`CURSO ESPECIAL`, `40.640` aprendices) con formación titulada (`TECNICO` `7.253`, `TECNOLOGO` `5.833`). |

---

## 2. Auditoría de Cardinalidades, Prevención de Multiplicación de KPI y Reversibilidad (`CA-ISS-15-01` a `CA-ISS-15-05`)

| Etapa del pipeline | Operación aplicada | Filas antes → después | Resultado de auditoría y `assert` | `KPI-YEB-01` (`tasa_entrevista_pct`) |
| :-- | :-- | :-: | :-- | :-: |
| **1. Línea base S5–S7** | Lectura inmutable (`origen_registro == "dataset_preparado"`) | `97 → 93` | `SHA-256` intacto; cuadratura 100% con `yeb_kpis_semana6.csv` (`n=93`, `count=55`, `media=4.0 días`). | **`39,78%`** |
| **2. Catálogo vacantes** | `merge(on="job_id", how="left", validate="many_to_one", indicator=True)` | `93 → 93` | `93 both`, `0 left_only`. Prueba negativa con duplicado inyectado captura `pd.errors.MergeError`. Auditoría `outer` identifica `3` vacantes sin postulantes (`JOB-2026-019`, `JOB-2026-020`, `JOB-2026-021`). | **`39,78%`** |
| **3a. Antipatrón 1:N** *(solo diagnóstico)* | `merge` directo sin pre-agregar los `426` eventos de seguimiento | **`93 → 431`** *(+363,4%)* | **FALLA CONTROLADA:** las postulaciones en `interview`/`accepted` generan más eventos de chat y se sobre-representan, fabricando una falsa tasa de avance de **`56,38%`** (+16,60 pp de sesgo). | ~~`56,38%`~~ *(sesgado)* |
| **3b. Solución 1:N** | `groupby(["application_id"]).agg(...)` → `merge(how="left", validate="one_to_one")` | **`93 → 93`** | `88 both`, `5 left_only` (`0` eventos, latencia `NaN`). **`assert` confirma igualdad bit a bit con todos los KPIs de Semanas 6 y 7.** | **`39,78%`** |
| **4. Apilado longitudinal** | `pd.concat([cohorte_2026A, cohorte_2026B], ignore_index=True)` | `93 + 60 → 153` | Esquemas idénticos validados; **`0` duplicados** en llave compuesta `(candidate_hash, job_id, periodo)`; `12` candidatos recurrentes preservados con trazabilidad semestral. | `40,52%` *(acum. 2 semestres)* |
| **5. `melt()` y `pivot()`** | Ancho `(93, 6)` → Largo `(279, 5)` → Ancho `(93, 6)` | `93 → 279 → 93` | Puntajes `score_habilidades`, `score_experiencia` y `score_disponibilidad` reconstruidos con **`max_diff_absoluta = 0.0`** y `assert_frame_equal(..., check_exact=True)`. | **`39,78%`** |

---

## 3. Enlace Contextual con Datos Abiertos (`datos.gov.co` — SENA `28vu-5tx7`) (`CA-ISS-15-06` / `CP-055C`)

### 3.1 Lectura Territorial Paralela (Grano Departamento — Sin Mezclar Microdatos y Macro-agregados)

Consulta ejecutada vía API Socrata oficial (`https://www.datos.gov.co/resource/28vu-5tx7.json`, `HTTP 200`, `8.538` fichas y `61.420` aprendices certificados a nivel nacional en `32` departamentos, periodo `202402`):

| Departamento | Postulaciones YEB (`2026-A`, microdato) | Participación YEB (`%` sobre `n=93`) | Tasa avance YEB (`%`) | Fichas SENA (`202402`, macro) | Aprendices certificados SENA (`202402`) | Jóvenes focalizados SENA | Participación SENA sobre total nacional (`%`) |
| :-- | --: | --: | --: | --: | --: | --: | --: |
| **Bogotá D.C.** | `17` | `18,28%` | `41,18%` | `1.385` | `9.924` | `662` | `16,16%` |
| **Antioquia** | `15` | `16,13%` | `40,00%` | `1.256` | `9.911` | `830` | `16,14%` |
| **Valle del Cauca** | `16` | `17,20%` | `50,00%` | `636` | `5.447` | `687` | `8,87%` |
| **Atlántico** | `15` | `16,13%` | `40,00%` | `616` | `2.170` | `606` | `3,53%` |
| **Santander** | `14` | `15,05%` | `28,57%` | `388` | `2.110` | `90` | `3,44%` |
| **Risaralda** | `16` | `17,20%` | `37,50%` | `187` | `1.633` | `347` | `2,66%` |
| **Subtotal 6 departamentos YEB** | **`93`** | **`100,00%`** | **`39,78%`** | **`4.468`** | **`31.195`** | **`3.222`** | **`50,79%`** |

### 3.2 Conclusión Técnica Rigurosa (Separación de Cifras Macro y Registros Individuales)

1. **Coherencia territorial macro:** Los seis departamentos donde opera el piloto YEB concentran **`31.195` de los `61.420` aprendices certificados por el SENA (`50,79%`)** y **`4.468` de las `8.538` fichas (`52,33%`)** en la vigencia `202402`, encabezados por **Bogotá D.C. (`16,16%`)** y **Antioquia (`16,14%`)**. A nivel estructural, la oferta formativa SENA registrada en este conjunto es predominantemente **presencial (`57.297` aprendices, `93,29%`)** frente a **virtual (`4.047`, `6,59%`)**, lo que ofrece contexto útil al analizar la alta demanda por modalidades **remotas (`34,41%`)** e **híbridas (`33,33%`)** dentro del portal juvenil YEB.
2. **Prohibición metodológica de cruce directo (prevención de falacia ecológica):** Aunque ambos conjuntos comparten nombres de departamentos, **no es válido realizar un `merge` para imputar tasas SENA a postulaciones individuales de YEB ni sumar sus numeradores/denominadores**, por cuatro razones técnicas verificables:
   - **Unidad de observación distinta:** cada fila de YEB representa una postulación individual (`application_id`), mientras cada fila de `28vu-5tx7` representa un conteo agregado de una ficha formativa del SENA. Inferir atributos individuales de un postulante a partir de un promedio departamental constituiría **falacia ecológica**.
   - **Ventana temporal asincrónica:** los registros públicos del SENA corresponden al corte `periodo = 202402` (actualizado en marzo de `2024`), mientras la cohorte `2026-A` de YEB corresponde a enero–febrero de `2026`.
   - **Definición operativa no equivalente de juventud:** en YEB el universo es el rango etario legal `16–24` años (`n=93`), mientras en `28vu-5tx7` la columna `tipo_poblacion__jovenes` (`7.014` de `61.420`) registra únicamente aprendices marcados en una subcategoría poblacional focalizada, no la totalidad de jóvenes menores de 25 años certificados.
   - **Distinta naturaleza de la modalidad:** en SENA `modalidad_formacion` describe cómo se impartió el curso (`PRESENCIAL`/`VIRTUAL`), mientras en YEB `modality` describe las condiciones de contratación laboral (`Presencial`/`Híbrido`/`Remoto`).
3. **Uso analítico autorizado:** El conjunto público SENA `28vu-5tx7` se utiliza **exclusivamente como capa de contexto macro territorial paralela** para priorizar alianzas institucionales con centros SENA en departamentos de alta densidad formativa (Bogotá D.C., Antioquia y Valle del Cauca) y planear futuras cohortes (`2026-B` y posteriores), manteniendo intactos e independientes los KPI internos de YEB.
