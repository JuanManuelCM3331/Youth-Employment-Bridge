# CRUD Aislado y Catálogo de Consultas C01–C10 — Youth Employment Bridge (YEB)

**Módulo:** `analytics/` (Persistencia NoSQL) | **Issue:** `#06 [NoSQL/CRUD]` | **Criterios cubiertos:** `CA-ISS-06-01`, `CA-ISS-06-02`  
**Base de datos:** `yeb_analytics_lab` | **Riesgos mitigados:** `SEC-013`, `OPE-006` | **Casos de prueba:** `CP-046`, `CP-046B`  
**Script:** [`analytics/scripts/03_crud_consultas_reclutamiento.js`](../scripts/03_crud_consultas_reclutamiento.js) | **Verificador:** [`analytics/scripts/06_verificar_crud_consultas.py`](../scripts/06_verificar_crud_consultas.py)  
**Evidencia generada:** [`analytics/outputs/contraste_consultas_c01_c10.csv`](../outputs/contraste_consultas_c01_c10.csv)

---

## 1. Aislamiento de las pruebas con el prefijo `TMP-*` (`SEC-013`)

Todas las escrituras del script trabajan sobre documentos cuyo código empieza por `TMP-` (`TMP-CAND-*`, `TMP-VAC-*`, `TMP-POST-*`). El aislamiento no depende solo de la buena intención de quien ejecuta el script, sino de tres controles que se comprueban en cada corrida:

| Control | Cómo funciona | Evidencia en la salida |
| :-- | :-- | :-- |
| Barrera de escritura | La función `assertFiltroTemporal()` revisa el filtro de cada `insert`, `update` y `delete`. Si el código no está limitado a `^TMP-`, lanza un error antes de llegar al motor. El script prueba la barrera intentando escribir sobre `POST-YEB-001`. | `Barrera SEC-013 verificada` |
| Huella de datos productivos | Antes y después del CRUD se calcula un SHA-256 sobre todos los documentos que **no** son `TMP-*` de `candidatos`, `vacantes` y `postulaciones_historial`. Las dos huellas deben ser idénticas. | `Huella SHA-256 ... (idéntica)` |
| Limpieza al inicio y al final | Se borran residuos `TMP-*` de corridas interrumpidas y, al terminar, los temporales creados. Con `YEB_KEEP_TMP = true` se conservan para revisarlos en Compass o mongosh. | `Documentos TMP-* eliminados` |

---

## 2. Operaciones atómicas con `$set` e `$inc` (`CA-ISS-06-01` / `CP-046`)

| Operación | Comando principal | Resultado verificado |
| :-- | :-- | :-- |
| **Create** — registrar postulación | `insertOne` de la postulación + `updateOne` sobre la vacante con `$inc: { "metricas.postulaciones_recibidas": 1 }` y `$set: { "metricas.ultima_postulacion": fecha }` | `TMP-VAC-01` termina con 4 postulaciones y `TMP-VAC-02` con 3, igual al número de postulaciones creadas para cada una. |
| **Update** — contacto del reclutador | `findOneAndUpdate` con filtro `estado_actual: { $in: ["pending", "review"] }`, `$inc: { intentos_contacto: 1 }` y `$set: { estado_actual: "review" }` | Después de tres contactos, `TMP-POST-03` queda con `intentos_contacto = 3` y estado `review`. |
| **Update** — guardia de estado | El mismo `findOneAndUpdate` sobre `TMP-POST-06`, que ya está en `rejected` | 0 documentos coinciden: una postulación cerrada no acumula contactos. |
| **Delete** — limpieza | `deleteMany({ codigo_*: /^TMP-/ })` en las tres colecciones | Se eliminan 6 candidatos, 2 vacantes y 8 postulaciones temporales; la huella productiva no cambia. |

`$inc` resuelve el contador en el servidor en una sola operación. Si dos reclutadores registran un contacto al mismo tiempo, MongoDB aplica los dos incrementos; con un patrón "leer, sumar en la aplicación y guardar", uno de los dos se perdería.

---

## 3. Datos temporales usados por el catálogo

| Candidato | Edad | Ciudad | Expectativa (COP) | Habilidades (nivel, meses) | Experiencia previa | `contacto.telefono_fijo` |
| :-- | :-: | :-- | --: | :-- | :-- | :-- |
| `TMP-CAND-01` | 17 | Medellín | 1.450.000 | Python (intermedio, 8), SQL (básico, 6) | — | `null` explícito |
| `TMP-CAND-02` | 16 | Envigado | 1.350.000 | Atención al Cliente (avanzado, 14), Excel (básico, 4) | Comercio, 6 meses | *campo ausente* |
| `TMP-CAND-03` | 20 | Medellín | 1.900.000 | Python (avanzado, 24), SQL (intermedio, 18), Power BI (intermedio, 10) | Tecnología, 6 meses; Educación, 12 meses | `604-555-0103` |
| `TMP-CAND-04` | 22 | Itagüí | 1.650.000 | Excel (avanzado, 30), SQL (básico, 5) | Servicios Financieros, 18 meses | `null` explícito |
| `TMP-CAND-05` | 19 | Bello | 1.750.000 | JavaScript (intermedio, 12), Git (intermedio, 12) | Tecnología, 4 meses | *campo ausente* |
| `TMP-CAND-06` | 24 | Medellín | 2.200.000 | Python (básico, 3), Excel (avanzado, 20) | Tecnología, 3 meses; Comercio, 24 meses | `604-555-0106` |

`TMP-CAND-06` se diseñó a propósito como caso trampa para `$elemMatch`: cumple cada condición por separado, pero nunca todas en el mismo elemento del arreglo.

---

## 4. Catálogo C01–C10 con contraste esperado vs. obtenido (`CA-ISS-06-02` / `CP-046B`)

Todas las consultas incluyen el filtro `codigo_candidato: /^TMP-/` o `codigo_postulacion: /^TMP-/`, que se omite abajo para facilitar la lectura.

| ID | Necesidad del reclutador | Consulta (mongosh) | Esperado | Obtenido |
| :-: | :-- | :-- | :-- | :-- |
| C01 | Candidatos que saben Python | `{ "perfil_profesional.habilidades.nombre": "Python" }` | CAND-01, 03, 06 | CAND-01, 03, 06 |
| C02 | Candidatos con Power BI o Git | `{ "perfil_profesional.habilidades.nombre": { $in: ["Power BI", "Git"] } }` | CAND-03, 05 | CAND-03, 05 |
| C03 | Expectativa salarial entre 1,4 y 1,8 millones | `{ "perfil_profesional.expectativa_salarial_cop": { $gte: NumberDecimal("1400000.00"), $lte: NumberDecimal("1800000.00") } }` | CAND-01, 04, 05 | CAND-01, 04, 05 |
| C04 | Expectativa ≤ 1,8 millones y residencia en Medellín o Itagüí | `{ ...expectativa: { $lte: NumberDecimal("1800000.00") }, "perfil_profesional.ciudad_residencia": { $in: ["Medellín", "Itagüí"] } }` | CAND-01, 04 | CAND-01, 04 |
| C05 | Python intermedio o avanzado con al menos 6 meses de práctica | `{ "perfil_profesional.habilidades": { $elemMatch: { nombre: "Python", nivel: { $in: ["intermedio", "avanzado"] }, meses_practica: { $gte: 6 } } } }` | CAND-01, 03 | CAND-01, 03 |
| C06 | Seis meses o más en una misma experiencia del sector Tecnología | `{ "perfil_profesional.experiencia_laboral": { $elemMatch: { sector: "Tecnología", meses: { $gte: 6 } } } }` | CAND-03 | CAND-03 |
| C07 | Teléfono fijo reportado como `null` | `{ "contacto.telefono_fijo": { $type: "null" } }` | CAND-01, 04 | CAND-01, 04 |
| C08 | Teléfono fijo nunca reportado | `{ "contacto.telefono_fijo": { $exists: false } }` | CAND-02, 05 | CAND-02, 05 |
| C09 | Página 2 de postulaciones recientes con `skip/limit` | `.find(f).sort({ fecha_postulacion: -1, _id: -1 }).skip(3).limit(3)` | POST-04, 03, 02 | POST-04, 03, 02 |
| C10 | Página 2 con cursor de continuación, después de llegar una postulación nueva | `.find({ $or: [{ fecha_postulacion: { $lt: f } }, { fecha_postulacion: f, _id: { $lt: id } }] }).sort(...).limit(3)` | POST-04, 03, 02 | POST-04, 03, 02 |

### 4.1 Por qué `$elemMatch` es obligatorio en C05 y C06

Sin `$elemMatch`, MongoDB evalúa cada condición contra **cualquier** elemento del arreglo. En C05, la versión con notación de punto devuelve también a `TMP-CAND-06`, porque une su Python básico con los 20 meses de su Excel avanzado. En C06 ocurre lo mismo: suma los 3 meses en Tecnología con los 24 meses en Comercio. `$elemMatch` obliga a que las condiciones se cumplan en el mismo elemento y elimina ese falso positivo.

### 4.2 `null` explícito frente a campo ausente (C07 y C08)

El filtro `{ "contacto.telefono_fijo": null }` devuelve cuatro candidatos (CAND-01, 02, 04 y 05) porque mezcla dos situaciones distintas. Con `$type: "null"` se obtiene solo a quien respondió que no tiene teléfono fijo, y con `$exists: false` a quien nunca reportó el dato. En el caso de menores de edad, como `TMP-CAND-02`, el campo se deja ausente en lugar de inventar un valor, de acuerdo con la minimización de datos de la [matriz ética](matriz_preguntas_etica_yeb.md).

### 4.3 Paginación: `skip/limit` frente a cursor de continuación (`OPE-006`)

Las dos estrategias usan el índice `idx_paginacion_fecha_id` (`{ fecha_postulacion: -1, _id: -1 }`); `_id` sirve de desempate porque `TMP-POST-04` y `TMP-POST-05` tienen la misma fecha.

| Situación | `skip/limit` (C09) | Cursor por (fecha, `_id`) (C10) |
| :-- | :-- | :-- |
| Página 1 | POST-07, 06, 05 | POST-07, 06, 05 |
| Página 2 sin cambios en los datos | POST-04, 03, 02 | POST-04, 03, 02 |
| Página 2 después de llegar `TMP-POST-08` | POST-05, 04, 03 (**POST-05 se repite**) | POST-04, 03, 02 (sin repetidos) |
| Claves del índice examinadas (`explain`) | 6 (recorre y descarta las 3 omitidas) | 3 (salta directo a la continuación) |

`skip` obliga al motor a recorrer y descartar los documentos omitidos, así que el costo crece con el número de página; además, si entra una postulación nueva mientras el reclutador navega, la página siguiente repite registros. El cursor de continuación arranca justo después del último documento visto, por lo que su costo no depende de la página y no repite ni omite registros. Por eso se recomienda para listados largos de postulaciones en el portal.

---

## 5. Ejecución

```powershell
docker compose up -d mongodb
mongosh "mongodb://127.0.0.1:27017/yeb_analytics_lab" --file analytics/scripts/03_crud_consultas_reclutamiento.js
python analytics/scripts/06_verificar_crud_consultas.py
```

El script carga las semillas del Issue #03 si la base está vacía, por lo que puede ejecutarse en un entorno recién creado.
