# Nota Técnica de Arquitectura: Concurrencia, Transacciones ACID, Modelo CAP y Estrategia ante Particiones de Red — Youth Employment Bridge (YEB)

**Módulo:** `analytics/` (Persistencia NoSQL Transaccional y Concurrencia con PyMongo) | **Issue:** `#16 [NoSQL/Concurrency]` | **Criterios cubiertos:** `CA-ISS-16-01` a `CA-ISS-16-08`  
**Servicio desacoplado:** [`analytics/services/interview_scheduling_service.py`](../services/interview_scheduling_service.py)  
**Suite de pruebas:** [`analytics/tests/test_concurrency.py`](../tests/test_concurrency.py) | **Verificador:** [`analytics/scripts/16_verificar_concurrencia_pymongo.py`](../scripts/16_verificar_concurrencia_pymongo.py)  
**Evidencia verificada:** [`analytics/outputs/evidencia_concurrencia_pymongo_semana8.json`](../outputs/evidencia_concurrencia_pymongo_semana8.json)  
**Riesgos mitigados:** `TEC-020`, `OPE-009`, `SEC-012` | **Casos de prueba:** `CP-056`, `CP-056B`, `CP-056C`

---

## 1. Arquitectura Desacoplada del Servicio PyMongo (`CA-ISS-16-01`)

Para evitar el acoplamiento entre reglas de negocio, acceso a base de datos y presentación por consola, el módulo [`interview_scheduling_service.py`](../services/interview_scheduling_service.py) separa cinco capas con responsabilidades estrictas:

1. **Configuración y Fábrica de Cliente (`MongoSettings`, `build_mongo_client`):** lee variables de entorno desde `.env` (excluido en `.gitignore` y documentado en `.env.example`), configurando explícitamente `serverSelectionTimeoutMS=5000`, `connectTimeoutMS=5000`, `socketTimeoutMS=10000`, `retryWrites=True` y `tz_aware=True` sobre loopback (`127.0.0.1:27017`, `SEC-012`).
2. **Validación Tipada de Entradas (`CreateSlotRequest`, `ReserveSlotRequest`, `CancelInterviewRequest`, `AgendaPaginationRequest`):** `dataclasses` inmutables (`frozen=True`) que validan formatos, fechas UTC, modalidades permitidas y tokens compuestos antes de abrir sesiones de red.
3. **Repositorio MongoDB (`InterviewSchedulingRepository`):** encapsula índices únicos y parciales (`ux_slot_id`, `ux_active_interview_per_slot` con `partialFilterExpression: {status: "confirmada"}`, `ux_id_operacion`), la prueba de regresión de lecturas de la Semana 7 (`Consulta A`, `Consulta B` y token compuesto `(fecha_agenda, _id)`) y todas las operaciones CRUD/sesión.
4. **Servicio de Negocio (`InterviewSchedulingService`):** coordina la exclusión mutua por documento, las transacciones ACID multi-documento en Replica Set (reserva y cancelación) y la tabla de idempotencia `operaciones`.
5. **Capa de Presentación CLI (`build_cli_parser`, `main`):** comandos `init-demo`, `list-slots`, `reserve-atomic`, `reserve-tx`, `cancel-tx`, `regression-w7` e `inspect-audit` con salidas JSON estructuradas y códigos de retorno explícitos.

---

## 2. Control de Concurrencia: Del Patrón Vulnerable a Transacciones ACID e Idempotencia (`CA-ISS-16-03` a `CA-ISS-16-06`)

### 2.1 Por qué falla el antipatrón `find()` + `updateOne()` (Condición de Carrera TOCTOU)

Si dos candidatos (`C1` y `C2`) ejecutan `findOne({slot_id: "S1"})` simultáneamente, ambos observan `status: "disponible"` (*Time-Of-Check*) antes de emitir su `updateOne({slot_id: "S1"}, {$set: {status: "reservado", candidate_id: ...}})` (*Time-Of-Use*). El segundo sobrescribe silenciosamente al primero (*Lost Update*), violando el invariante del portal (**máximo 1 candidato confirmado por horario**).

### 2.2 Dos niveles complementarios de protección implementados en YEB

| Mecanismo | Alcance | Operación en PyMongo | Garantía verificada |
| :-- | :-- | :-- | :-- |
| **Nivel 1 — Atomicidad condicional a nivel de documento** | 1 colección (`franjas_entrevista`) | `update_one({"slot_id": s, "status": "disponible"}, {"$set": {"status": "reservado", ...}, "$inc": {"version": 1}})` | En MongoDB toda escritura de un documento toma un bloqueo exclusivo a nivel de documento en WiredTiger. Ante 16 hilos paralelos sobre el mismo cupo, exactamente uno obtiene `modified_count == 1` y los otros 15 reciben `SlotUnavailableError`. |
| **Nivel 2 — Transacción ACID multi-documento en Replica Set** | 3 colecciones (`franjas_entrevista`, `entrevistas`, `operaciones`) | `client.start_session()` → `session.start_transaction(read_concern=ReadConcern("snapshot"), write_concern=WriteConcern("majority"))` | **A**tomicidad (*todo o nada* mediante `abort_transaction()` ante fallos inducidos, 0 huérfanos), **C**onsistencia (invariantes + índice único parcial), **I**solamiento (*Snapshot Isolation* con detección de `WriteConflict` / `TransientTransactionError`), **D**urabilidad (`WriteConcern("majority")` replicada en el `oplog`). |

### 2.3 Idempotencia con `id_operacion` y `payload_hash`

Ante pérdidas de conexión justo después de un `commit_transaction()` (cuando el cliente no alcanza a recibir el `ACK` HTTP/TCP y reintenta), el servicio protege cada reserva y cancelación mediante:

1. **Índice único `ux_id_operacion`** sobre `operaciones.id_operacion`.
2. **Huella `payload_hash` (`SHA-256`)** calculada sobre los parámetros canónicos (`tipo_operacion`, `slot_id`, `application_id`, `candidate_id`, `vacancy_id` o `interview_id`).
3. **Semántica de resolución:**
   - **Mismo `id_operacion` + mismo `payload_hash`:** retorna la respuesta histórica original almacenada en `operaciones.respuesta` (`idempotent_replay: true`) sin duplicar entrevistas ni alterar estado.
   - **Mismo `id_operacion` + distinto `payload_hash`:** rechaza inmediatamente con `IdempotencyConflictError` (previniendo corrupción accidental o reutilización maliciosa de claves).

---

## 3. Análisis de Consistencia bajo el Teorema CAP (`CA-ISS-16-08`)

El teorema **CAP** establece que, ante una **Partición de red (`P`)** —inevitable en sistemas distribuidos reales—, un almacén de datos debe priorizar entre **Consistencia fuerte (`C`)** (toda lectura observa la escritura confirmada más reciente o falla limpiamente) o **Disponibilidad total (`A`)** (todo nodo no caído responde, aunque pueda devolver datos desactualizados o aceptar escrituras divergentes).

### Posicionamiento de MongoDB Replica Set para el Agendamiento de YEB: **Sistema `CP` en escrituras críticas**

En el portal Youth Employment Bridge (YEB), **agendar o cancelar una entrevista exige Consistencia fuerte (`CP`)**, nunca disponibilidad eventual (`AP`):

- **Por qué `AP` es inaceptable en agendamiento:** permitir que dos nodos aislados por una partición acepten reservas en paralelo sobre la misma franja (`SLOT-YEB-01`) produciría **doble agendamiento** (*split-brain*), dañando la confianza de jóvenes (incluyendo menores de edad con permisos programados) y reclutadores.
- **Cómo garantiza `CP` nuestra configuración de MongoDB Replica Set:**
  - **Elección por quórum mayoritario (`N/2 + 1`):** solo la partición que conserva la mayoría de nodos votantes (ej. 2 de 3 nodos en producción) puede mantener o elegir un nodo **Primary** habilitado para escrituras.
  - **`WriteConcern("majority")`:** la transacción solo se confirma cuando la mayoría del Replica Set ha persistido el cambio en su `oplog` y *journal*, evitando *rollbacks* de reservas ya confirmadas si el Primary cae instantes después.
  - **`ReadConcern("snapshot")` + `ReadPreference.PRIMARY`:** las transacciones leen una instantánea consistente confirmada por mayoría en el nodo Primary, eliminando lecturas sucias (*dirty reads*) o fantasma durante colisiones concurrentes.

---

## 4. Comparativa Técnica: Replicación vs. Sharding para YEB (`CA-ISS-16-08`)

| Dimensión de análisis | Replicación (*Replica Set*: 1 Primary + N Secondaries) | Sharding (*Sharded Cluster*: múltiples Replica Sets + `mongos` + Config Servers) |
| :-- | :-- | :-- |
| **Objetivo principal** | **Alta disponibilidad**, tolerancia a fallos de nodo, durabilidad de datos y soporte nativo de **transacciones ACID** (`oplog`). | **Escalabilidad horizontal** de almacenamiento (TB+) y throughput de escrituras distribuyendo documentos por una *shard key*. |
| **Capacidad de escritura** | Limitada a la capacidad de un único nodo Primary (miles de ops/seg en hardware moderado). | Escala linealmente al agregar *shards* independientes si la *shard key* evita *hotspots*. |
| **Transacciones multi-documento** | **Rápidas y de baja latencia:** ocurren dentro de un solo Replica Set sin coordinación entre nodos independientes. | **Costosas (*Two-Phase Commit / 2PC*):** las transacciones distribuidas entre distintos *shards* incrementan la latencia y la contención de bloqueos. |
| **Complejidad operativa y costo** | **Baja/Moderada:** 3 nodos (PSA o PSS), respaldo simple, fácil despliegue en Docker o Atlas M10+. | **Alta:** requiere `mongos`, Config Server Replica Set, planificación irreversible de *shard key*, balanceador de *chunks* y mayor costo de infraestructura. |
| **Riesgo de diseño** | Si crece el volumen por encima de la RAM/disco de un nodo, requiere escalado vertical o migración futura. | Una mala *shard key* (ej. fecha monótona o `status` de baja cardinalidad) concentra todas las escrituras en un solo *shard* (*jumbo chunks*). |
| **Veredicto para YEB** | **RECOMENDADO (Corto y Mediano Plazo):** un Replica Set de 3 nodos (PSS) cubre ampliamente el volumen proyectado de YEB (< 5 millones de postulaciones/entrevistas), ofrece failover automático en segundos y mantiene transacciones ACID rápidas. | **NO RECOMENDADO AÚN (Diferir):** sobredimensionado para el piloto y las primeras fases productivas. Solo se justificaría al superar cientos de GB en colecciones históricas o decenas de miles de escrituras/segundo; en tal caso se recomendaría *shard key* compuesta hasheada/acotada por `{ vacancy_id: 1, slot_id: 1 }` manteniendo co-localizados cupos y entrevistas de la misma vacante. |

---

## 5. Estrategia Recomendada ante Particiones de Red en el Portal YEB (`CA-ISS-16-08`)

Para operar con resiliencia real ante cortes de red, micro-particiones o reinicios del nodo Primary, el portal YEB debe aplicar una estrategia en cinco controles coordinados:

1. **Topología de quórum impar en producción (Replica Set PSS de 3 nodos):** desplegar 3 nodos portadores de datos (*Primary + 2 Secondaries*) en zonas de disponibilidad distintas (evitando topologías de 2 nodos donde una partición bloquea ambos lados, y evitando *Arbiters* con `WriteConcern("majority")` por degradación de caché en fallos).
2. **Prevención automática de *split-brain*:** si el Primary queda aislado en la partición minoritaria, pierde el *heartbeat* de la mayoría y se degrada automáticamente a **Secondary** (*stepDown*) en pocos segundos, rechazando cualquier intento de escritura mientras la partición mayoritaria elige un nuevo Primary.
3. **Timeouts explícitos y *fail-fast* en PyMongo (`CA-ISS-16-01`):** mantener acotados `serverSelectionTimeoutMS=5000`, `connectTimeoutMS=5000` y `socketTimeoutMS=10000` (parametrizados en `.env`) para evitar que los *workers* HTTP/CLI queden bloqueados indefinidamente cuando no existe Primary alcanzable, devolviendo un error claro (`503 Service Unavailable`) al usuario en menos de 5 segundos.
4. **Reintentos seguros e idempotencia obligatoria (`id_operacion`):**
   - Activar `retryWrites=True` en `MongoClient` para errores transitorios de red/elección.
   - En el cliente web/API de YEB, generar un `id_operacion` único (UUID v4) al iniciar el clic de «Confirmar entrevista» o «Cancelar entrevista» y reenviar **esa misma clave** en cada reintento automático o manual. El servicio PyMongo garantiza respuesta idéntica sin doble reserva y bloquea colisiones de parámetros con `IdempotencyConflictError`.
5. **Degradación controlada por criticidad de operación:**
   - **Escrituras críticas (reservar/cancelar entrevistas, crear postulaciones):** modo estrictamente **`CP`** (`ReadPreference.PRIMARY` + `WriteConcern("majority")`). Si hay partición sin quórum, **se rechazan limpiamente** con aviso de reintento; jamás se aceptan reservas en modo degradado sin consenso.
   - **Lecturas analíticas y catálogo de vacantes (dashboards, buscador):** pueden configurarse opcionalmente con `ReadPreference.SECONDARY_PREFERRED` y `maxStalenessSeconds=90` durante una ventana de elección de Primary, permitiendo que los jóvenes sigan consultando ofertas aunque el agendamiento esté pausado por unos segundos, indicando explícitamente en la interfaz la marca de actualización del catálogo.
