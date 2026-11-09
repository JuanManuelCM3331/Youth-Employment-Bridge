# Contratos `$jsonSchema`, Índices Únicos y Migración Idempotente — Youth Employment Bridge (YEB)

**Módulo:** `analytics/` (Persistencia NoSQL) | **Issue:** `#07 [NoSQL/Schema]` | **Criterios cubiertos:** `CA-ISS-07-01` a `CA-ISS-07-05`  
**Base de datos:** `yeb_analytics_lab` | **Riesgos mitigados:** `NEG-002`, `NEG-003`, `TEC-012` | **Casos de prueba:** `CP-047`, `CP-047B`, `CP-047C`  
**Scripts:** [`04_contratos_schema_migracion.js`](../scripts/04_contratos_schema_migracion.js), [`migraciones/M04_001_canal_publicacion.js`](../scripts/migraciones/M04_001_canal_publicacion.js) | **Verificador:** [`07_verificar_contratos_schema.py`](../scripts/07_verificar_contratos_schema.py)  
**Evidencias:** [`outputs/matriz_validacion_jsonschema.csv`](../outputs/matriz_validacion_jsonschema.csv), [`outputs/cuarentena_vacantes_staging.csv`](../outputs/cuarentena_vacantes_staging.csv)

---

## 1. Contrato estricto de `vacantes` (`CA-ISS-07-01` / `NEG-002`)

En el portal PHP las reglas de una vacante se validan en `JobManagementService`. En MongoDB no existe esa capa, así que la regla tiene que vivir en el propio motor: si una carga masiva, un script o una persona desde Compass intenta guardar una oferta mal formada, la base la rechaza.

| Regla | Implementación en el validador | Por qué |
| :-- | :-- | :-- |
| Campos obligatorios | `required`: `codigo_vacante`, `titulo`, `ciudad`, `modalidad`, `estado`, `empresa`, `rango_salarial`, `fecha_publicacion`, `schema_version` | Una vacante sin empresa o sin salario no se puede mostrar ni filtrar. |
| Catálogo de modalidades | `modalidad: { enum: ["Remoto", "Híbrido", "Presencial"] }` | Es el mismo catálogo canónico del Issue #05; evita valores como `remote` o `Teletrabajo` que rompen los filtros. |
| Longitud del título | `titulo: { bsonType: "string", minLength: 10, maxLength: 120 }` | Títulos como "Aux" o "Mesero" no describen el cargo para el joven que busca empleo. |
| Tipos numéricos | `rango_salarial.minimo/maximo: { bsonType: "decimal", minimum: 0 }` | Conserva la precisión de `Decimal128` y bloquea salarios guardados como texto. |
| Salario coherente | `{ $expr: { $lte: ["$rango_salarial.minimo", "$rango_salarial.maximo"] } }` | `$jsonSchema` no compara dos campos entre sí; por eso el validador combina `$jsonSchema` y `$expr` dentro de un `$and`. |
| Versionado | `schema_version: { bsonType: "int", enum: [1, 2] }` y `canal_publicacion: { enum: ["portal_web"] }` opcional | El contrato acepta las dos versiones mientras dura la migración `M04_001`. |

El contrato se aplica con `validationLevel: "strict"` (todas las inserciones y actualizaciones se validan) y `validationAction: "error"` (el motor rechaza la escritura con el código `121`, no solo la registra en el log). Antes de aplicarlo, el script cuenta cuántas vacantes existentes lo incumplen y aborta si hay alguna, para no endurecer el motor sobre datos que ya están sucios.

---

## 2. Índices únicos (`CA-ISS-07-02` / `NEG-003`)

| Colección | Índice | Regla de negocio |
| :-- | :-- | :-- |
| `postulaciones_historial` | `uq_postulacion_candidato_vacante` → `{ candidato_id: 1, vacante_id: 1 }`, `unique: true` | Un joven solo puede postularse una vez a la misma vacante. Es el equivalente documental de la restricción `UNIQUE (job_id, user_id)` de la tabla `applications` del portal. |
| `postulaciones_historial` | `uq_codigo_postulacion` → `{ codigo_postulacion: 1 }` | Código legible único para auditoría. |
| `vacantes` | `uq_codigo_vacante` → `{ codigo_vacante: 1 }` | Evita dos ofertas con el mismo código, por ejemplo en cargas masivas. |
| `candidatos` | `uq_codigo_candidato` → `{ codigo_candidato: 1 }` | Mismo criterio para candidatos. |

**Prueba `CP-047B`:** el script intenta insertar una segunda postulación con el mismo `candidato_id` y `vacante_id` de `POST-YEB-001`. MongoDB responde `E11000 duplicate key` y el documento no queda guardado.

---

## 3. Matriz de 8 pruebas de validación (`CA-ISS-07-04` / `CP-047`)

Los documentos de prueba usan el prefijo `TMP-VAL-*` y se retiran al terminar.

| Caso | Documento enviado | Esperado | Obtenido | Regla que lo rechazó |
| :-: | :-- | :-: | :-: | :-- |
| V1 | Vacante completa, Híbrido, 1.400.000 – 1.800.000 | Aceptado | Aceptado | — |
| V2 | Salario mínimo igual al máximo (1.500.000) | Aceptado | Aceptado | — |
| V3 | Documento ya en `schema_version: 2` con `canal_publicacion` | Aceptado | Aceptado | — |
| I1 | Salario invertido: mínimo 2.000.000 > máximo 1.500.000 | Rechazado | Rechazado | `$expr` mínimo ≤ máximo |
| I2 | Modalidad `Teletrabajo` | Rechazado | Rechazado | `modalidad: enum` |
| I3 | Título `Aux` (3 caracteres) | Rechazado | Rechazado | `titulo: minLength` |
| I4 | Sin subdocumento `empresa` | Rechazado | Rechazado | `required empresa` |
| I5 | Salarios guardados como texto (`"1400000"`) | Rechazado | Rechazado | `minimo/maximo: bsonType` |

Resultado: 3 de 3 válidas aceptadas y 5 de 5 inválidas rechazadas por el motor con el código `121` (*Document failed validation*).

---

## 4. Flujo staging → cuarentena → carga de limpios (`CA-ISS-07-03`)

El lote [`data/staging/vacantes_staging.ejson`](../data/staging/vacantes_staging.ejson) simula una carga masiva de 10 ofertas de empresas aliadas. Llega primero a `vacantes_staging`, una colección sin contrato, y cada registro sigue este camino:

1. **Corrección con soporte.** Solo se corrige lo que tiene una regla documentada: quitar espacios sobrantes del título, traducir la modalidad con el diccionario cerrado del Issue #05 (`remote` → `Remoto`) y convertir a `Decimal128` un salario que llegó como texto pero es un número válido.
2. **Cuarentena en precarga.** Lo que no se puede corregir sin inventar datos se aísla en `vacantes_cuarentena` con su motivo y el documento original completo.
3. **Contrato del motor como última barrera.** Lo que pasa la precarga se inserta en `vacantes`; si el motor lo rechaza, también va a cuarentena con la regla incumplida.

| Fila | Código | Problema detectado | Decisión |
| :-: | :-- | :-- | :-- |
| 1 | `STG-VAC-01` | Ninguno | Cargada |
| 2 | `STG-VAC-02` | Modalidad `remote` | Cargada, corregida a `Remoto` por diccionario |
| 3 | `STG-VAC-03` | Espacios sobrantes en el título | Cargada, título recortado |
| 4 | `STG-VAC-04` | Salario mínimo 2.200.000 > máximo 1.600.000 | **Cuarentena (precarga):** no hay soporte para saber cuál de los dos valores es el correcto, así que no se intercambian |
| 5 | `STG-VAC-05` | Sin datos de la empresa | **Cuarentena (precarga):** no se sabe quién publica la oferta |
| 6 | `STG-VAC-06` | Salarios como texto `"1500000"` | Cargada, convertidos a `Decimal128` |
| 7 | `STG-VAC-07` | Modalidad `Por definir` | **Cuarentena (precarga):** sin equivalencia en el catálogo |
| 8 | `VAC-YEB-003` | Código que ya usa una vacante publicada | **Cuarentena (precarga):** conflicto de identificador |
| 9 | `STG-VAC-09` | Título `Mesero` | **Cuarentena (motor):** rechazada por `titulo: minLength` |
| 10 | `STG-VAC-10` | Ninguno | Cargada |

**Conciliación:** 10 recibidos = 5 cargados + 5 en cuarentena; ningún registro se pierde. Cada vacante cargada guarda en `trazabilidad_carga` el lote, la fila de origen y las correcciones aplicadas. El flujo es idempotente: en cada corrida se vacían `vacantes_staging` y `vacantes_cuarentena` y se retiran los `STG-VAC-*` cargados antes.

---

## 5. Migración idempotente `M04_001` (`CA-ISS-07-05` / `TEC-012`)

**Objetivo:** pasar las vacantes de `schema_version: 1` a `schema_version: 2` agregando `canal_publicacion: "portal_web"`, sin tocar la colección original.

| Paso | Qué hace | Garantía |
| :-- | :-- | :-- |
| 1. Copia | `aggregate([{ $merge: { into: "vacantes_v2_m04", on: "_id", whenMatched: "keepExisting", whenNotMatched: "insert" } }])` | Los documentos ya migrados no se sobrescriben con su versión vieja. La copia hereda el mismo contrato de `vacantes`. |
| 2. Migración | `updateMany({ schema_version: 1 }, { $set: { schema_version: 2, canal_publicacion: "portal_web", migrado_por: "M04_001" } })` | El filtro por versión hace que una segunda ejecución no encuentre nada que cambiar. |
| 3. Bitácora | `upsert` en `migraciones_esquema` con `$inc: { ejecuciones: 1 }` | Queda registro de cuántas veces se ejecutó y con qué resultado. |

**Prueba `CP-047C`:**

| Ejecución | Coincidencias | Modificados | Documentos en v2 |
| :-: | :-: | :-: | :-: |
| 1 | 10 | 10 | 10 / 10 |
| 2 (inmediata) | 0 | 0 | 10 / 10 |

La colección `vacantes` conserva sus documentos en `schema_version: 1`. No hay ventana de caída: cada documento cambia de versión en una sola escritura atómica y el contrato acepta las versiones 1 y 2 mientras conviven, así que el portal puede seguir leyendo durante la migración.

---

## 6. Ejecución

```powershell
docker compose up -d mongodb
mongosh "mongodb://127.0.0.1:27017/yeb_analytics_lab" --file analytics/scripts/04_contratos_schema_migracion.js
python analytics/scripts/07_verificar_contratos_schema.py
```

La migración también puede ejecutarse sola, cuantas veces se quiera:

```powershell
mongosh "mongodb://127.0.0.1:27017/yeb_analytics_lab" --file analytics/scripts/migraciones/M04_001_canal_publicacion.js
```
