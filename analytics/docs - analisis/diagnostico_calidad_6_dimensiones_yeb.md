# Diagnóstico y Tratamiento de las 6 Dimensiones de Calidad de Datos — Youth Employment Bridge (YEB)

**Issue:** `#05 [Data/Quality]`  
**Riesgos mitigados:** `NEG-007`, `TEC-011`  
**Casos de prueba:** `CP-045`, `CP-045B`

---

## 1. Resumen Ejecutivo del Perfilamiento

Se sometió a perfilamiento el dataset sintético con errores inducidos [`analytics/data/raw/yeb_postulaciones_calidad_raw.csv`](../data/raw/yeb_postulaciones_calidad_raw.csv) (`115` registros crudos), evaluando las **6 dimensiones de calidad de datos** y generando:
* **Dataset preparado:** [`analytics/data/processed/yeb_postulaciones_preparado.csv`](../data/processed/yeb_postulaciones_preparado.csv) (`93` registros saneados y certificados).
* **Bitácora de incidencias:** [`analytics/outputs/incidencias_calidad.csv`](../outputs/incidencias_calidad.csv) (`27` incidencias documentadas con valor original, regla vulnerada y justificación técnica).
* **Bandeja de cuarentena:** [`analytics/outputs/cuarentena_identificadores_conflicto.csv`](../outputs/cuarentena_identificadores_conflicto.csv) (`4` registros involucrados en colisiones de identidad).

---

## 2. Evaluación y Tratamiento por Dimensión (`CA-ISS-05-01` y `CA-ISS-05-02`)

| # | Dimensión de Calidad | Reglas Evaluadas | Incidencias Detectadas | Tratamiento Aplicado y Justificación Técnica |
| :-: | :--- | :--- | :-: | :--- |
| **1** | **Completitud** | `COM-01`: Presencia obligatoria de `email_contacto` y `telefono_contacto` verificables. | `3` | **Exclusión documentada:** Postulaciones sin medio de contacto impiden notificar citaciones a entrevista. |
| **2** | **Unicidad** | `UNI-01`: Filas duplicadas exactas.<br>`UNI-02`: Colisión en `application_id` con atributos distintos.<br>`UNI-03`: Misma tupla `(documento_hash, rol)` con identidades contradictorias. | `7`<br>(`3` exactos + `4` en conflicto) | **Tratamiento diferenciado (`TEC-011`):**<br>• Duplicados exactos: se descarta la copia (`keep='first'`).<br>• Identificadores en conflicto: **no se eliminan a ciegas ni se sobrescriben**; se aíslan ambos registros del conflicto en `outputs/cuarentena_identificadores_conflicto.csv` para revisión de auditoría. |
| **3** | **Validez** | `VAL-01`/`VAL-03`: Tipado numérico con `pd.to_numeric(errors='coerce')`.<br>`VAL-02`: Edad legal juvenil en `[16, 24]` años.<br>`VAL-04`: Salarios `>= 0` y `salary_min <= salary_max`. | `6` | **Conversión segura y exclusión (`NEG-007`):** Textos corruptos (`"veinte"`, `"UN_MILLON_800"`), salarios negativos (`-1500000`, `-950000`) y edades ilegales (`14` y `29` años) se excluyen dejando traza en `incidencias_calidad.csv`. |
| **4** | **Consistencia** | `CON-01`: Modalidad en `{'Remoto', 'Híbrido', 'Presencial'}`.<br>`CON-02`: Coherencia `candidate_age < 18` vs `is_protected_minor`.<br>`CON-03`: Grafía canónica de ciudades. | `6`<br>(`5` corregidas + `1` excluida) | **Homologación determinística:** Sinónimos válidos (`teletrabajo` → `Remoto`, `in-office` → `Presencial`, `hybrid` → `Híbrido`, `medellin ` → `Medellín`) y bandera de menor de 17 años se corrigen con trazabilidad; la modalidad irreconocible (`PRESENCIAL_NOCTURNO_ILEGAL`) se excluye. |
| **5** | **Oportunidad** | `OPO-02`: `applied_at >= job_published_at`.<br>`OPO-03`: `updated_at >= applied_at`.<br>`OPO-04`: Fechas no futuras (`<= 2026-12-31`). | `3` | **Exclusión por anacronismo:** Registros con postulación previa a la apertura de la vacante, actualización previa a la postulación o fecha en `2027` se excluyen para no distorsionar `tiempo_respuesta_dias`. |
| **6** | **Trazabilidad** | `TRA-01`: Presencia obligatoria de `source_system` y `audit_batch_id`. | `2` | **Exclusión de registros huérfanos:** Ningún registro sin lote de auditoría ni sistema origen puede ingresar a `data/processed/`. |
