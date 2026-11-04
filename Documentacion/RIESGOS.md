# Matriz de Riesgos y Defectos - Youth Employment Bridge (YEB)

**Versión:** 2.2 | **Fecha de revisión:** 04/11/2026 | **Alcance:** prototipo funcional y módulo analítico/NoSQL (Semana 1 a Semana 5).

## 1. Método de valoración

| Valor | Probabilidad (P) | Impacto (I) |
| :-: | :-- | :-- |
| 1 | Poco probable en el uso previsto | Molestia menor, sin pérdida de datos |
| 2 | Posible | Afecta una función o expone datos limitados |
| 3 | Probable o ya presente | Expone datos personales, incumple la ley o bloquea el servicio |

**Severidad = P × I.** 1–2: **Baja** · 3–4: **Media** · 6–9: **Alta**.

**Estados:** *Abierto* (sin mitigación), *Parcial* (mitigación incompleta), *Mitigado* (control implementado en el código), *Pendiente* (requerido para los nuevos issues), *No aplica aún* (depende de un servicio externo fuera del prototipo), *Cerrado*.

**Convención de IDs:** `SEC` seguridad · `RSK` privilegios y cuentas · `LEG` cumplimiento legal · `NEG` reglas de negocio · `TEC` técnico y datos · `OPE` rendimiento y operación · `UX` experiencia de usuario.

## 2. Matriz de riesgos

### 2.1 Seguridad y privacidad

| ID | Riesgo | P | I | Sev. | Mitigación | Estado | ID anterior |
| :-- | :-- | :-: | :-: | :-: | :-- | :-- | :-- |
| SEC-001 | Vulneración de credenciales (contraseñas débiles o robadas) | 2 | 3 | **Alta (6)** | Hash con `password_hash()` (Bcrypt), mínimo de 8 caracteres exigido en registro, cambio de contraseña y creación de admin (`DEF-005` cerrado) | Mitigado | SEC-001, RSK-02 |
| SEC-002 | Descarga de hojas de vida por empresas sin postulación | 2 | 3 | **Alta (6)** | Validación de postulación en `cvForDownload`; CP-009B | Mitigado | SEC-002 |
| SEC-003 | Inyección SQL por los filtros del buscador | 1 | 3 | Media (3) | Consultas preparadas PDO con parámetros nominales independientes; CP-024 | Mitigado | SEC-003 |
| SEC-004 | Secuestro de sesión por cookie sin flags de seguridad | 2 | 3 | **Alta (6)** | `Session::start()` configura `HttpOnly`, `SameSite=Lax` y `Secure` en HTTPS; `session_regenerate_id(true)` en login y cambio de contraseña | Mitigado | SEC-004 |
| SEC-005 | Fuerza bruta contra el login y cambio de contraseña | 3 | 2 | **Alta (6)** | Bloqueo temporal de 15 minutos tras 5 intentos fallidos consecutivos en `AuthService::login` y `AuthService::changePassword`; auditoría `login_failed` | Mitigado | SEC-005 |
| SEC-006 | Lectura o escritura de conversaciones por usuarios ajenos a la postulación | 2 | 3 | **Alta (6)** | Control de acceso estricto por candidato y `company_id` en `ChatService` | Mitigado | SEC-002 (parte chat) |
| SEC-007 | XSS almacenado en campos de texto | 2 | 2 | Media (4) | Escape contextual con `self::e()` en vistas y cabecera `Content-Security-Policy` global en `Portal::sendSecurityHeaders` | Mitigado | SEC-007 |
| SEC-008 | CSRF en formularios POST y endpoints JSON | 2 | 2 | Media (4) | Token criptográfico por sesión validado con `hash_equals` en todas las acciones `POST` (incluyendo suscripciones y pruebas push) | Mitigado | SEC-008 |
| SEC-010 | Despliegue con credenciales demo (`password`) y MySQL `root` sin contraseña | 2 | 3 | **Alta (6)** | Uso exclusivo para entorno de demostración local; parametrización mediante `.env` | Aceptado en prototipo | Nuevo |
| SEC-011 | Subida de archivos maliciosos en CV o documentos de menores | 2 | 2 | Media (4) | Validación estricta de MIME mediante `finfo` (`FILEINFO_MIME_TYPE`), almacenamiento `LONGBLOB` en BD, sanitización de nombres y descarga con `Content-Disposition: attachment` y `X-Content-Type-Options: nosniff` | Mitigado | Nuevo |
| SEC-012 | Exposición de MongoDB analítico `yeb_analytics_lab` sin autenticación o en red abierta | 2 | 2 | Media (4) | Parámetros de red locales/Docker (`127.0.0.1:27017`) y credenciales en variables de entorno | Mitigado | Nuevo (Issue #02) |
| SEC-013 | Alteración accidental de datos analíticos o de producción durante pruebas CRUD | 2 | 3 | **Alta (6)** | Aislamiento con prefijo temporal obligatorio `TMP-*` en operaciones CRUD en mongosh | Pendiente | Nuevo (Issue #06) |

### 2.2 Privilegios, cuentas y cumplimiento

| ID | Riesgo | P | I | Sev. | Mitigación | Estado | ID anterior |
| :-- | :-- | :-: | :-: | :-: | :-- | :-- | :-- |
| RSK-04 | Escalación de privilegios (acciones de otro rol o cuenta suspendida) | 2 | 3 | **Alta (6)** | Verificación de rol por acción, cierre inmediato de sesión si `status = 'suspended'` y bloqueo centralizado de acciones para menores no autorizados en `Portal::run()` | Mitigado | RSK-04 |
| RSK-05 | Edición de perfiles ajenos | 1 | 2 | Baja (2) | El perfil se edita solo con el ID de la sesión autenticada; CP-005 | Mitigado | RSK-04 |
| RSK-06 | Eliminación o suspensión no intencionada del último administrador | 2 | 2 | Media (4) | El sistema impide borrar o suspender la cuenta propia y bloquea la operación si `countActiveAdmins() <= 1` en `DashboardService` | Mitigado | RSK-04 |
| LEG-001 | Cumplimiento de protección de datos y autorización de menores de edad | 3 | 3 | **Alta (9)** | Detección de minoría de edad (16–17 años), bloqueo preventivo, carga obligatoria de soportes legales, aprobación expresa y minimización/anonimización en analítica | Mitigado (portal) / Pendiente (analítica) | BLQ-03, RSK-08, SEC-009 |

### 2.3 Negocio y datos

| ID | Riesgo | P | I | Sev. | Mitigación | Estado | ID anterior |
| :-- | :-- | :-: | :-: | :-: | :-- | :-- | :-- |
| NEG-001 | Empresas falsas y vacantes fraudulentas sin moderación | 2 | 3 | **Alta (6)** | Panel administrativo con suspensión/eliminación de empresas, usuarios y vacantes, más documento del reclutador único por rol | Parcial (prototipo) | RT-006 |
| NEG-002 | Publicar vacantes con datos salariales inválidos | 3 | 2 | **Alta (6)** | Validación de campos obligatorios, valores numéricos positivos y `salary_min <= salary_max` en PHP y mediante `$jsonSchema` (`$expr`) en MongoDB | Mitigado (PHP) / Pendiente (NoSQL) | RN-003 |
| NEG-003 | Postulaciones duplicadas | 1 | 1 | Baja (1) | Restricción `UNIQUE (job_id, user_id)` en relacional e índice único compuesto en MongoDB | Mitigado (PHP) / Pendiente (NoSQL) | RN-005 |
| NEG-004 | Transiciones inválidas en el estado de postulaciones | 3 | 2 | **Alta (6)** | Máquina de estados estricta `ApplicationService::ALLOWED_TRANSITIONS` y trazabilidad en `audit_logs` | Mitigado | RN-009 |
| NEG-005 | Exposición o alteración de candidatos/vacantes de otras empresas | 2 | 3 | **Alta (6)** | Consultas y actualizaciones filtradas y verificadas por `company_id` en `ApplicationRepository` y `JobManagementService` | Mitigado | RN-010 |
| NEG-006 | Desacople de condiciones salariales/contractuales pactadas por cambios posteriores en la vacante | 3 | 2 | **Alta (6)** | Patrón Snapshot inmutable en la postulación al cambiar a `interview` o terminal (`02_modelo_y_snapshots.js`) | Mitigado | Nuevo (Issue #03) |
| NEG-007 | Contaminación del dataset con salarios negativos, duplicados o edades inválidas | 3 | 2 | **Alta (6)** | Perfilamiento de 6 dimensiones, reporte de incidencias y saneamiento seguro | Pendiente | Nuevo (Issue #05) |
| NEG-008 | Normalización distorsionada o sesgo en indicadores de empleabilidad juvenil | 2 | 2 | Media (4) | Mapeo mediante diccionarios canónicos y cálculo riguroso de numeradores y denominadores | Pendiente | Nuevo (Issue #09) |
| TEC-001 | Datos inconsistentes en registro de empresa y reclutador | 2 | 2 | Media (4) | Registro de empresa y reclutador envuelto en transacción PDO (`beginTransaction` / `commit` / `rollBack`) y clave foránea `ON DELETE CASCADE` en `users.company_id` | Mitigado | RT-005 |
| TEC-002 | Pérdida de archivos entre contenedores o entornos locales | 2 | 2 | Media (4) | Almacenamiento binario unificado (`LONGBLOB`) en base de datos para hojas de vida, fotos de perfil y documentos de menores | Mitigado | RT-008 |
| TEC-003 | Conservación de la bitácora de auditoría al eliminar usuarios | 2 | 3 | **Alta (6)** | `audit_logs.user_id` utiliza `ON DELETE SET NULL`, preservando el registro histórico de auditoría aunque se elimine un usuario | Mitigado | Nota sin ID |
| TEC-004 | El ENUM `jobs.status` no incluye «pausada» | 3 | 1 | Media (3) | Estados acotados a `draft`, `published` y `closed` | Cerrado (por alcance) | Nota sin ID |
| TEC-005 | Enlaces de recuperación de contraseña por correo externo | 2 | 3 | **Alta (6)** | Fuera del alcance del prototipo (depende de servicio externo SMTP) | No aplica aún | Nota sin ID |
| TEC-006 | Favoritos y ocultas inconsistentes entre sesiones | 1 | 1 | Baja (1) | Persisten en `saved_jobs` y `hidden_jobs`; CP-015, CP-015B | Mitigado | Nota sin ID |
| TEC-007 | Rompimiento de reproducibilidad en analítica por rutas absolutas o inconsistencia de dependencias | 2 | 2 | Media (4) | Uso de `pathlib.Path`, notebook de verificación `01_verificar_entorno` y `requirements.txt` | Mitigado | Nuevo (Issue #01) |
| TEC-008 | Fallo de conexión a MongoDB o pérdida de tipos BSON (`Date`, `ObjectId`, subdocumentos) | 2 | 2 | Media (4) | Script `01_explorar_yeb.js` con tipado explícito BSON | Mitigado | Nuevo (Issue #02) |
| TEC-009 | Desbordamiento del límite BSON de 16 MiB por crecimiento descontrolado de arreglos embebidos | 2 | 3 | **Alta (6)** | Colecciones independientes para historial de postulaciones referenciando candidatos y vacantes | Mitigado | Nuevo (Issue #03) |
| TEC-010 | Inconsistencia de esquemas y tipos al cargar extracciones tabulares de postulaciones en Pandas | 2 | 2 | Media (4) | Inspección técnica estandarizada (`shape`, `columns`, `dtypes`, `info()`, `describe()`) | Pendiente | Nuevo (Issue #04) |
| TEC-011 | Falsificación o pérdida silenciosa de registros con claves duplicadas en calidad de datos | 2 | 2 | Media (4) | Descarte de duplicados exactos y aislamiento en cuarentena de identificadores en conflicto | Pendiente | Nuevo (Issue #05) |
| TEC-012 | Inconsistencia de esquema documental por migraciones de versión no idempotentes | 2 | 3 | **Alta (6)** | Script `M04_001` con control de versión de esquema (`schema_version`) y ejecución idempotente | Pendiente | Nuevo (Issue #07) |
| TEC-013 | Errores de precedencia o fallas sintácticas en filtros booleanos y consultas `.query()` | 2 | 2 | Media (4) | Consultas parametrizadas con `@variable` y validación de invariantes con `assert` | Pendiente | Nuevo (Issue #08) |
| TEC-014 | Precedencia lógica invertida en clasificación de prioridad con `np.select()` | 2 | 2 | Media (4) | Ordenamiento explícito: 1° anomalías de identidad > 2° postulaciones desactualizadas > 3° aptos | Pendiente | Nuevo (Issue #09) |
| TEC-015 | Pérdida de tipos BSON nativos (`Int32`, `Decimal128`, `Date`) en export/import Extended JSON | 2 | 3 | **Alta (6)** | Comprobación de integridad round-trip con `mongoexport` y `mongoimport` en EJSON canónico | Pendiente | Nuevo (Issue #10) |

### 2.4 Rendimiento, operación y experiencia

| ID | Riesgo | P | I | Sev. | Mitigación | Estado | ID anterior |
| :-- | :-- | :-: | :-: | :-: | :-- | :-- | :-- |
| OPE-001 | Consultas sobre `audit_logs` con volumen alto | 2 | 2 | Media (4) | Índices `audit_created_at` y `audit_user_id` con límite de registros en consulta | Mitigado | PERF-001 |
| OPE-002 | Búsqueda de vacantes con múltiples filtros | 2 | 2 | Media (4) | Filtros parametrizados por título, ciudad, modalidad, experiencia, salario mín/máx y empresa en `JobRepository::search` | Mitigado | RNF-001 |
| OPE-003 | Servicio de correo SMTP externo | 2 | 2 | Media (4) | Fuera del alcance del prototipo funcional local | No aplica aún | BLQ-02 |
| OPE-004 | La mensajería retrasa el cronograma del MVP | 1 | 2 | Baja (2) | Módulo ya implementado y probado | Cerrado | CHG-04 |
| OPE-005 | Ineficiencia en procesamiento analítico por iteración fila a fila en lugar de vectorización | 2 | 2 | Media (4) | Operaciones vectorizadas en NumPy y Pandas sobre series salariales y tasas | Pendiente | Nuevo (Issue #04) |
| OPE-006 | Degradación de rendimiento en consultas de mongosh por paginación con `skip/limit` en alto volumen | 2 | 2 | Media (4) | Evaluación comparativa y adopción de cursores de continuación por ID/fecha | Pendiente | Nuevo (Issue #06) |
| OPE-007 | Entorno analítico documental no reproducible para terceros por scripts o pasos no documentados | 2 | 3 | **Alta (6)** | `README.md` exhaustivo de reconstrucción, semilla EJSON y release formal `git tag v1.0` | Pendiente | Nuevo (Issue #10) |
| UX-001 | Diseño responsive en dispositivos móviles y paneles divididos | 2 | 1 | Baja (2) | Ajuste de grillas responsive en Hoja de Vida y panel dividido con scroll independiente en el buscador de empleos | Cerrado | UXH-001 |

## 3. Historial de defectos corregidos

| ID | Defecto | Dónde | Severidad | Estado |
| :-- | :-- | :-- | :-: | :-: |
| DEF-001 | Parámetros PDO independientes en filtros de búsqueda | `Modules/Jobs/Repositories/JobRepository.php` | Alta | **Cerrado** |
| DEF-002 | Bloqueo de postulación y visualización pública de vacantes en borrador o cerradas | `ApplicationService::apply`, `JobManagementRepository::find`, `JobsUI::jobDetail` | Alta | **Cerrado** |
| DEF-003 | Notificación automática al candidato cuando cambia el estado de su postulación | `app/Http/Portal.php` (`update_application`) | Media | **Cerrado** |
| DEF-004 | Validación estricta de pertenencia (`company_id`) antes de actualizar postulaciones o editar/cerrar vacantes | `Portal.php`, `ApplicationRepository`, `JobManagementService` | Alta | **Cerrado** |
| DEF-005 | Longitud mínima de contraseña elevada a 8 caracteres | `AuthService::MIN_PASSWORD_LENGTH`, `DashboardService::createAdmin`, vistas UI | Media | **Cerrado** |
| DEF-006 | Envío de alerta de nueva postulación tanto al candidato como a los reclutadores de la empresa | `app/Http/Portal.php` (`apply`) | Media | **Cerrado** |

### Observaciones de documentación

| ID | Observación | Estado |
| :-- | :-- | :-- |
| OBS-001 | Sincronización del `README.md` con los filtros reales del buscador, esquema completo de BD y alcance del prototipo | **Cerrado** |
| OBS-004 | Formatos de CV y documentos: validación real por MIME con `finfo` | **Cerrado** |
