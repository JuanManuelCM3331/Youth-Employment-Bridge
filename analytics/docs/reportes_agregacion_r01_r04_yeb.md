# Pipelines de Agregación de Solo Lectura y Contraste Manual (R01–R04) — Youth Employment Bridge (YEB)

**Módulo:** `analytics/` (Persistencia NoSQL — *Aggregation Framework*) | **Issue:** `#12 [NoSQL/Aggregation]` | **Criterios cubiertos:** `CA-ISS-12-01` a `CA-ISS-12-04`  
**Base de datos:** `yeb_analytics_p05` | **Zona horaria:** `America/Bogota` (`UTC-05:00`) | **Fecha de corte fija:** `2026-10-05T00:00:00.000Z` (`2026-10-04T19:00:00-05:00`)  
**Script:** [`analytics/scripts/06_reportes_agregacion_yeb.js`](../scripts/06_reportes_agregacion_yeb.js) | **Verificador:** [`analytics/scripts/12_verificar_reportes_agregacion.py`](../scripts/12_verificar_reportes_agregacion.py)  
**Salidas:** [`analytics/outputs/contraste_reportes_r01_r04.csv`](../outputs/contraste_reportes_r01_r04.csv), [`analytics/outputs/reportes_r01_r04_salidas.ejson`](../outputs/reportes_r01_r04_salidas.ejson)  
**Riesgos mitigados:** `NEG-010`, `TEC-017` | **Casos de prueba:** `CP-052`, `CP-052B`, `CP-052C`

---

## 1. Regresión Inicial tras el Cierre v1.0 (`CA-ISS-12-01` / `CP-052` / `TEC-017`)

En la revisión posterior al cierre v1.0 (`Issue #10`) se identificó el defecto **`DEF-NOSQL-10-01`**:
1. **Zona horaria implícita en UTC:** `$dateToString` sin `timezone: "America/Bogota"` desplazaba al mes siguiente las postulaciones cerradas después de las `19:00` hora local del último día del mes (por ejemplo, `2026-10-01T02:30:00.000Z` corresponde a `2026-09-30 21:30:00` en Bogotá, pero en UTC caía en `2026-10`).
2. **Snapshots históricos sin `sector`:** Cuando un snapshot capturado en v1.0 carecía del atributo `sector` (como `POST-YEB-006` o `CTRL-POST-02`), el agrupamiento devolvía `sector: null` en lugar de clasificarlo explícitamente bajo `"sin_sector"`.
3. **Frontera del corte en vencidas (`R03`):** El uso de `$lte` en lugar de `$lt` incluía erróneamente como vencidas aquellas postulaciones cuya última actividad coincidía exactamente con el instante de corte (`0,00` horas de atraso).

| Caso de regresión | Comportamiento v1.0 (con defecto) | Comportamiento corregido (`06_reportes_agregacion_yeb.js`) | Estado |
| :-- | :-- | :-- | :-: |
| `CTRL-POST-01` (`2026-10-01T02:30:00Z`) | Clasificado en `2026-10` (UTC) | Clasificado en `2026-09` (`America/Bogota`) | **PASA** |
| `CTRL-POST-02` / `POST-YEB-006` (snapshot sin `sector`) | Agrupado con `_id.sector: null` | Separado explícitamente en `sector: "sin_sector"` | **PASA** |
| `CTRL-POST-05` (`fecha == 2026-10-05T00:00:00Z`) | Incluida como vencida (`0h`) con `$lte` | Excluida de `R03` por filtro estricto `$lt: FECHA_CORTE` | **PASA** |

---

## 2. Parámetros de Foto Congelada y Garantía de Solo Lectura (`CA-ISS-12-02` / `TEC-017`)

- **Zona horaria explícita:** `America/Bogota` (`UTC-05:00`).
- **Ventana de análisis:** desde `2026-09-15T00:00:00.000Z` hasta la **fecha de corte fija** `2026-10-05T00:00:00.000Z`.
- **Solo lectura verificada:** la función `assertPipelineSoloLectura()` inspecciona todas las etapas de `R01`–`R04` y aborta si detecta `$out` o `$merge`. Además, se calcula la huella `SHA-256` de `vacantes` y `postulaciones_historial` antes y después de la corrida, comprobando que permanece idéntica.

---

## 3. Diseño de los Cuatro Pipelines (`R01`–`R04`) (`CA-ISS-12-03` / `NEG-010`)

| ID | Objetivo | Reglas clave implementadas en el pipeline |
| :-: | :-- | :-- |
| **R01** | Postulaciones finalizadas por mes y sector/modalidad | Filtra `estado_actual` en `["accepted", "rejected"]` y fecha de cierre BSON `Date` en `[VENTANA_INICIO, FECHA_CORTE]`. Extrae `mes` con `$dateToString` y `timezone: "America/Bogota"` y lee `sector` y `modalidad` desde `vacante_snapshot` (asignando `"sin_sector"` si falta o está vacío). |
| **R02** | Top 5 vacantes con más postulaciones en el periodo | Filtra `fecha_postulacion` en la ventana y agrupa **primero** por `vacante_id` (`1 postulación = 1 unidad`, sin usar `$unwind` sobre `habilidades_requeridas` para no multiplicar conteos). Cruza con `$lookup` hacia `vacantes` y conserva referencias huérfanas con `codigo_vacante: "Sin coincidencia"` y `coincidencia_lookup: false`. |
| **R03** | Postulaciones en revisión o entrevista vencidas al corte | Filtra `estado_actual` en `["review", "interview"]` y fecha de actividad estrictamente menor al corte (`$lt: FECHA_CORTE`, excluyendo igualdad exacta `==`). Calcula `horas_atraso` redondeado a 2 decimales y ordena de mayor a menor atraso. |
| **R04** | Duración media de procesos cerrados en minutos | Sobre procesos en `["accepted", "rejected"]`, usa `$facet` para separar: (a) `resumen_validas` (solo cuando `fecha_postulacion` y `fecha_cierre` son BSON `Date` en `[VENTANA_INICIO, FECHA_CORTE]` inclusive y `fecha_cierre >= fecha_postulacion`), calculando `n_valida`, suma, media, mínimo y máximo en minutos; y (b) `detalle_excluidas` con el motivo exacto (`tipo_no_date`, `fecha_cierre_nula`, `despues_de_corte`). |

---

## 4. Tabla de Contraste: Cálculo Manual Esperado vs. Resultado Obtenido (`CA-ISS-12-04` / `CP-052B` / `CP-052C`)

### 4.1 Escenario de Control (`tmp_control_postulaciones_r01_r04`, 8 documentos con casos límite)

| Reporte | Cálculo manual esperado | Resultado obtenido en MongoDB | Veredicto | Justificación del caso de control |
| :-: | :-- | :-- | :-: | :-- |
| **R01** | `2026-09\|Tecnología\|Híbrido=1(A:1,R:0) ; 2026-09\|sin_sector\|Remoto=1(A:0,R:1) ; 2026-10\|Servicios\|Presencial=1(A:1,R:0)` | `2026-09\|Tecnología\|Híbrido=1(A:1,R:0) ; 2026-09\|sin_sector\|Remoto=1(A:0,R:1) ; 2026-10\|Servicios\|Presencial=1(A:1,R:0)` | **PASA** | `CTRL-POST-01` (`2026-10-01T02:30Z`) queda en `2026-09` en Bogotá; `CTRL-POST-02` queda en `sin_sector`; `CTRL-POST-06` (string), `07` (null) y `08` (> corte) quedan excluidas. |
| **R02** | `VAC-YEB-001:3[match=true] ; VAC-YEB-003:2[match=true] ; Sin coincidencia:1[match=false] ; VAC-YEB-002:1[match=true] ; VAC-YEB-004:1[match=true]` | `VAC-YEB-001:3[match=true] ; VAC-YEB-003:2[match=true] ; Sin coincidencia:1[match=false] ; VAC-YEB-002:1[match=true] ; VAC-YEB-004:1[match=true]` | **PASA** | `CTRL-POST-03` apunta a un `ObjectId` inexistente (`...0299`) y `$lookup` lo preserva como `"Sin coincidencia"` con `coincidencia_lookup = false`. |
| **R03** | `CTRL-POST-04:12.00h` | `CTRL-POST-04:12.00h` | **PASA** | `CTRL-POST-04` (`2026-10-04T12:00Z`) registra `12,00 h` de atraso; `CTRL-POST-05` (`2026-10-05T00:00Z == corte`) se excluye. |
| **R04** | `n_valida=3 \| media=4910.00m \| suma=14730.00m \| excluidas=3` | `n_valida=3 \| media=4910.00m \| suma=14730.00m \| excluidas=3` | **PASA** | Válidas: `CTRL-POST-01` (`7890m`) + `CTRL-POST-02` (`3000m`) + `CTRL-POST-03` (`3840m`, corte inclusivo) = `14730m / 3 = 4910,00m`. Excluidas: `3` (`CTRL-POST-06` string, `07` null, `08` > corte). |

### 4.2 Colección de Referencia (`postulaciones_historial` en `yeb_analytics_p05`, 8 documentos)

| Reporte | Cálculo manual esperado | Resultado obtenido en MongoDB | Veredicto | Desglose aritmético manual |
| :-: | :-- | :-- | :-: | :-- |
| **R01** | `2026-09\|Servicios\|Remoto=1(A:1,R:0) ; 2026-09\|sin_sector\|Presencial=1(A:0,R:1) ; 2026-10\|Tecnología\|Híbrido=1(A:1,R:0)` | `2026-09\|Servicios\|Remoto=1(A:1,R:0) ; 2026-09\|sin_sector\|Presencial=1(A:0,R:1) ; 2026-10\|Tecnología\|Híbrido=1(A:1,R:0)` | **PASA** | `POST-YEB-003` (`2026-09-28`, Servicios/Remoto, `accepted`), `POST-YEB-006` (`2026-09-30`, `sin_sector`/Presencial, `rejected`) y `POST-YEB-007` (`2026-10-03`, Tecnología/Híbrido, `accepted`). |
| **R02** | `VAC-YEB-001:2[match=true] ; VAC-YEB-002:2[match=true] ; VAC-YEB-003:2[match=true] ; VAC-YEB-004:1[match=true] ; VAC-YEB-005:1[match=true]` | `VAC-YEB-001:2[match=true] ; VAC-YEB-002:2[match=true] ; VAC-YEB-003:2[match=true] ; VAC-YEB-004:1[match=true] ; VAC-YEB-005:1[match=true]` | **PASA** | Total = `2 + 2 + 2 + 1 + 1 = 8` postulaciones (`1:1`, sin inflar conteos con las habilidades de las vacantes). |
| **R03** | `POST-YEB-002:351.00h ; POST-YEB-001:321.00h ; POST-YEB-004:205.50h ; POST-YEB-008:158.00h` | `POST-YEB-002:351.00h ; POST-YEB-001:321.00h ; POST-YEB-004:205.50h ; POST-YEB-008:158.00h` | **PASA** | Diferencias exactas en horas entre `2026-10-05T00:00:00Z` y `fecha_ultima_actualizacion` de las 4 postulaciones en `review`/`interview`. |
| **R04** | `n_valida=3 \| media=9906.67m \| suma=29720.00m \| excluidas=0` | `n_valida=3 \| media=9906.67m \| suma=29720.00m \| excluidas=0` | **PASA** | `POST-YEB-003` (`13475m`) + `POST-YEB-006` (`5925m`) + `POST-YEB-007` (`10320m`) = `29720m / 3 = 9906,67 minutos` (`n_valida = 3`, `excluidas = 0`). |

---

## 5. Ejecución Reproducible

```powershell
docker compose up -d mongodb
mongosh "mongodb://127.0.0.1:27017/yeb_analytics_p05" --file analytics/scripts/06_reportes_agregacion_yeb.js
python analytics/scripts/12_verificar_reportes_agregacion.py
```
