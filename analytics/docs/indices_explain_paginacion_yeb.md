# Índices Compuestos y Multikey, Análisis `explain()` y Paginación por Token — Youth Employment Bridge (YEB)

**Módulo:** `analytics/` (Rendimiento e Indexación NoSQL) | **Issue:** `#14 [NoSQL/Performance]` | **Criterios cubiertos:** `CA-ISS-14-01` a `CA-ISS-14-05`  
**Base de datos:** `yeb_analytics_p05` | **Colección sintética de ensayo:** `postulaciones_sinteticas_perf` (`6.000` documentos deterministas, rango exigido `5.000` a `12.000`)  
**Script:** [`analytics/scripts/07_indices_y_paginacion_yeb.js`](../scripts/07_indices_y_paginacion_yeb.js) | **Verificador:** [`analytics/scripts/14_verificar_indices_paginacion.py`](../scripts/14_verificar_indices_paginacion.py)  
**Salidas:**
- [`analytics/outputs/matriz_explain_indices_semana7.csv`](../outputs/matriz_explain_indices_semana7.csv)
- [`analytics/outputs/comparativa_paginacion_empates_semana7.csv`](../outputs/comparativa_paginacion_empates_semana7.csv)
- [`analytics/outputs/experimento_indices_paginacion_semana7.ejson`](../outputs/experimento_indices_paginacion_semana7.ejson)  
**Riesgos mitigados:** `OPE-008`, `TEC-018` | **Casos de prueba:** `CP-054`, `CP-054B`, `CP-054C`

---

## 1. Prueba de Regresión Inicial (`R03` de Semana 6) (`CA-ISS-14-01` / `CP-054` / `TEC-018`)

Antes de crear la colección sintética o modificar índices, el script reejecuta la consulta `R03` de la Semana 6 (postulaciones en `review` o `interview` vencidas con fecha estrictamente menor al corte `2026-10-05T00:00:00.000Z`) sobre la colección de referencia `postulaciones_historial`:

- **Resultado esperado (Semana 6):** `POST-YEB-002:351.00h ; POST-YEB-001:321.00h ; POST-YEB-004:205.50h ; POST-YEB-008:158.00h`
- **Resultado obtenido (Semana 7):** `POST-YEB-002:351.00h ; POST-YEB-001:321.00h ; POST-YEB-004:205.50h ; POST-YEB-008:158.00h` (**`PASA`**)

---

## 2. Diseño de las Consultas Clave e Índices (`CA-ISS-14-02` / `OPE-008`)

El ensayo se ejecuta sobre una colección sintética independiente (`postulaciones_sinteticas_perf`) con **`6.000` documentos** deterministas etiquetados con `dataset: "YEB_SYNTH_W07"`, sin alterar las colecciones de referencia.

| Consulta | Caso de uso en YEB | Filtro, proyección y orden | Índice diseñado |
| :-- | :-- | :-- | :-- |
| **Consulta A** *(Agenda y Seguimiento)* | Listar entrevistas programadas en una ventana de fechas ordenadas cronológicamente con desempate determinista. | **Filtro:** `{ dataset: "YEB_SYNTH_W07", estado_actual: "interview", fecha_agenda: { $gte: 2026-10-10, $lte: 2026-10-20T23:59:59Z } }`<br>**Proyección:** `{ _id, codigo_postulacion, estado_actual, fecha_agenda, ciudad }`<br>**Orden:** `{ fecha_agenda: 1, _id: 1 }` | **Índice compuesto (regla ESR):**<br>`idx_comp_dataset_estado_fecha_id`<br>`{ dataset: 1, estado_actual: 1, fecha_agenda: 1, _id: 1 }` |
| **Consulta B** *(Habilidades / Temas)* | Buscar postulaciones en entrevista de candidatos que dominan `"Python"` dentro del arreglo `habilidades_candidato` **sin expandir documentos con `$unwind`**. | **Filtro:** `{ dataset: "YEB_SYNTH_W07", habilidades_candidato: "Python", estado_actual: "interview" }`<br>**Proyección:** `{ _id, codigo_postulacion, estado_actual, habilidades_candidato, ciudad }`<br>**Orden:** `{ codigo_postulacion: 1, _id: 1 }` | **Índice multikey:**<br>`idx_multikey_habilidades_estado`<br>`{ habilidades_candidato: 1, dataset: 1, estado_actual: 1, codigo_postulacion: 1 }` |

---

## 3. Matriz Comparativa `explain()` y Plan Automático vs. `hint` (`CA-ISS-14-03` / `CP-054B`)

| Consulta | Escenario del plan | Etapa | Índice utilizado | `nReturned` | `totalDocsExamined` | `totalKeysExamined` | `SHA-256` salida ordenada |
| :-- | :-- | :-: | :-- | --: | --: | --: | :-- |
| **Consulta A** | 1. Antes de indexar | `COLLSCAN` | Ninguno | `482` | `6.000` | `0` | `cbed2de9be318c3a...` |
| **Consulta A** | 2. Después (plan automático) | `IXSCAN` | `idx_comp_dataset_estado_fecha_id` | `482` | `482` | `482` | `cbed2de9be318c3a...` |
| **Consulta A** | 3. Después (`hint` forzado) | `IXSCAN` | `idx_suboptimo_solo_fecha` (`{ fecha_agenda: 1 }`) | `482` | `2.362` | `2.362` | `cbed2de9be318c3a...` |
| **Consulta B** | 1. Antes de indexar | `COLLSCAN` | Ninguno | `406` | `6.000` | `0` | `775dffd68bda65bd...` |
| **Consulta B** | 2. Después (plan automático) | `IXSCAN` | `idx_multikey_habilidades_estado` (multikey) | `406` | `406` | `406` | `775dffd68bda65bd...` |

### Diferencia entre el plan automático del motor y el índice forzado con `hint`
- **Plan automático (`idx_comp_dataset_estado_fecha_id`):** el optimizador de MongoDB elige el índice compuesto que sigue la regla **ESR** (*Equality*: `dataset`, `estado_actual` → *Sort/Range*: `fecha_agenda`, `_id`), examinando únicamente las `482` claves y documentos que cumplen todas las condiciones (ratio `1,00` documento examinado por documento devuelto) y entregando los resultados ya ordenados sin etapa `SORT` en memoria.
- **Plan forzado con `hint("idx_suboptimo_solo_fecha")`:** al obligar al motor a usar un índice simple por fecha (`{ fecha_agenda: 1 }`), MongoDB recorre las `2.362` postulaciones del rango de fechas (incluyendo `pending`, `review`, `accepted` y `rejected`) para descartar en memoria el `79,6%` de ellas.
- **Regla operativa (`OPE-008`):** `hint` se utiliza exclusivamente en pruebas controladas de laboratorio para contrastar planes de ejecución y **nunca se traslada al código de producción**, ya que impide que el optimizador se adapte a cambios en la distribución de datos o a nuevos índices.

---

## 4. Control de Empates en Paginación: Token Compuesto `(fecha_agenda, _id)` vs. `skip()` (`CA-ISS-14-04` / `CP-054C` / `TEC-018`)

Sobre la Consulta A con tamaño de página `PAGE_SIZE = 10`, se localizan dos registros consecutivos que comparten exactamente la misma `fecha_agenda` (`2026-10-12T14:00:00.000Z`), separados justo por el límite entre la Página 1 y la Página 2:

- **Último registro de la Página 1 (posición 10):** `SYN-POST-0010` (`_id: 6728d0000000000000000010`, `fecha_agenda: 2026-10-12T14:00:00.000Z`)
- **Primer registro de la Página 2 (posición 11):** `SYN-POST-0011` (`_id: 6728d0000000000000000011`, `fecha_agenda: 2026-10-12T14:00:00.000Z`)

| Estrategia para obtener la Página 2 | Filtro de continuación | Primer registro devuelto | Filas perdidas en el empate | Claves de índice examinadas | Veredicto |
| :-- | :-- | :-: | :-: | :-: | :-- |
| **1. `skip(10).limit(10)`** | `.sort({ fecha_agenda: 1, _id: 1 }).skip(10).limit(10)` | `SYN-POST-0011` | `0` | `20` *(crece linealmente con la página: $O(\text{skip} + \text{limit})$)* | Degradación lineal en páginas profundas e inestable ante inserciones. |
| **2. Cursor simple solo por fecha (Defectuoso)** | `{ fecha_agenda: { $gt: ISODate("2026-10-12T14:00:00Z") } }` | Siguiente fecha (`> 14:00Z`) | **`1` (`SYN-POST-0011` perdido)** | `10` | **FALLA:** pierde silenciosamente todas las filas empatadas en la fecha de corte de página. |
| **3. Token compuesto `(fecha_agenda, _id)` (Recomendado)** | `{ $or: [{ fecha_agenda: { $gt: f } }, { fecha_agenda: f, _id: { $gt: id } }] }` | `SYN-POST-0011` | **`0`** | **`10`** *($O(\text{limit})$ constante)* | **ÓPTIMO:** 0 filas perdidas, 0 duplicadas y costo constante apoyado en el índice compuesto. |

---

## 5. Recomendación Técnica Final: Índices a Conservar, Costo en Escrituras y Limitaciones (`CA-ISS-14-05`)

1. **Índices a conservar en `yeb_analytics_p05`:**
   - **Conservar `idx_comp_dataset_estado_fecha_id` (`{ dataset: 1, estado_actual: 1, fecha_agenda: 1, _id: 1 }`):** reduce los documentos examinados en la agenda de entrevistas de `6.000` (`COLLSCAN`) a `482` (`-91,97%`), cubre el ordenamiento `{ fecha_agenda: 1, _id: 1 }` sin etapa `SORT` bloqueante en RAM y resuelve en $O(\text{limit})$ la paginación por token compuesto `(fecha_agenda, _id)`. Además, su prefijo `{ dataset: 1, estado_actual: 1 }` sirve a cualquier filtro de conteo por estado sin requerir un índice adicional.
   - **Conservar `idx_multikey_habilidades_estado` (`{ habilidades_candidato: 1, dataset: 1, estado_actual: 1, codigo_postulacion: 1 }`):** permite filtrar candidatos/postulaciones por habilidad dentro del arreglo sin expandir documentos con `$unwind`, reduciendo `totalDocsExamined` de `6.000` a `406` (`-93,23%`).
2. **Índice a descartar:**
   - **Descartar `idx_suboptimo_solo_fecha` (`{ fecha_agenda: 1 }`):** fue eliminado con `dropIndex()` al finalizar el ensayo con `hint`. Su baja selectividad obliga a examinar `2.362` documentos para devolver `482` y duplica innecesariamente el mantenimiento de árbol B-Tree sobre `fecha_agenda`.
3. **Costo en almacenamiento y penalización sobre escrituras:**
   - Cada índice adicional mantiene una estructura B-Tree en RAM y disco (`WiredTiger`). En el índice multikey `idx_multikey_habilidades_estado`, un documento con $k$ habilidades en `habilidades_candidato` (promedio de `2,7` habilidades por documento en el lote sintético) genera **$k$ entradas de índice** (`~16.200` entradas de índice para `6.000` documentos). Por ello, toda operación `insertOne`, `updateOne` (que modifique `estado_actual`, `fecha_agenda` o `habilidades_candidato`) o `deleteOne` debe actualizar ambos árboles B-Tree, y se prohíbe indexar un segundo arreglo en el mismo índice multikey.
4. **Limitaciones del ensayo:**
   - El experimento se ejecutó sobre un lote sintético determinista de `6.000` documentos que cabe íntegramente en la caché en memoria de `WiredTiger`, por lo que los tiempos en milisegundos no reflejan latencia de lectura en disco (I/O) ni contención de bloqueos concurrentes de escritura; por esa razón la decisión técnica se fundamenta en la reducción verificable de `totalDocsExamined` y `totalKeysExamined`.
