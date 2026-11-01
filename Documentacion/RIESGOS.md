# Matriz de Riesgos y Defectos - Youth Employment Bridge (YEB)

**Versión:** 2.1 | **Fecha de revisión:** 01/11/2026 | **Alcance:** prototipo funcional (riesgos críticos y defectos de seguridad mitigados).

## 1. Método de valoración

| Valor | Probabilidad (P) | Impacto (I) |
| :-: | :-- | :-- |
| 1 | Poco probable en el uso previsto | Molestia menor, sin pérdida de datos |
| 2 | Posible | Afecta una función o expone datos limitados |
| 3 | Probable o ya presente | Expone datos personales, incumple la ley o bloquea el servicio |

**Severidad = P × I.** 1–2: **Baja** · 3–4: **Media** · 6–9: **Alta**.

**Estados:** *Abierto* (sin mitigación), *Parcial* (mitigación incompleta), *Mitigado* (control implementado en el código), *No aplica aún* (depende de un servicio externo fuera del prototipo), *Cerrado*.

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

### 2.2 Privilegios, cuentas y cumplimiento

| ID | Riesgo | P | I | Sev. | Mitigación | Estado | ID anterior |
| :-- | :-- | :-: | :-: | :-: | :-- | :-- | :-- |
| RSK-04 | Escalación de privilegios (acciones de otro rol o cuenta suspendida) | 2 | 3 | **Alta (6)** | Verificación de rol por acción, cierre inmediato de sesión si `status = 'suspended'` y bloqueo centralizado de acciones para menores no autorizados en `Portal::run()` | Mitigado | RSK-04 |
| RSK-05 | Edición de perfiles ajenos | 1 | 2 | Baja (2) | El perfil se edita solo con el ID de la sesión autenticada; CP-005 | Mitigado | RSK-04 |
| RSK-06 | Eliminación o suspensión no intencionada del último administrador | 2 | 2 | Media (4) | El sistema impide borrar o suspender la cuenta propia y bloquea la operación si `countActiveAdmins() <= 1` en `DashboardService` | Mitigado | RSK-04 |
| LEG-001 | Cumplimiento de protección de datos y autorización de menores de edad | 3 | 3 | **Alta (9)** | Detección de minoría de edad (16–17 años) desde el documento escaneado, bloqueo preventivo de la cuenta (`minor_blocked = 1`), carga obligatoria de 3 soportes legales (`user_minor_documents`) y aprobación expresa por administrador | Mitigado | BLQ-03, RSK-08, SEC-009 |

### 2.3 Negocio y datos

| ID | Riesgo | P | I | Sev. | Mitigación | Estado | ID anterior |
| :-- | :-- | :-: | :-: | :-: | :-- | :-- | :-- |
| NEG-001 | Empresas falsas y vacantes fraudulentas sin moderación | 2 | 3 | **Alta (6)** | Panel administrativo con suspensión/eliminación de empresas, usuarios y vacantes, más documento del reclutador único por rol | Parcial (prototipo) | RT-006 |
| NEG-002 | Publicar vacantes con datos salariales inválidos | 3 | 2 | **Alta (6)** | Validación de campos obligatorios, valores numéricos positivos y `salary_min <= salary_max` en `JobManagementService` | Mitigado | RN-003 |
| NEG-003 | Postulaciones duplicadas | 1 | 1 | Baja (1) | Restricción `UNIQUE (job_id, user_id)` y verificación previa en `ApplicationService::apply` | Mitigado | RN-005 |
| NEG-004 | Transiciones inválidas en el estado de postulaciones | 3 | 2 | **Alta (6)** | Máquina de estados estricta `ApplicationService::ALLOWED_TRANSITIONS` y trazabilidad en `audit_logs` | Mitigado | RN-009 |
| NEG-005 | Exposición o alteración de candidatos/vacantes de otras empresas | 2 | 3 | **Alta (6)** | Consultas y actualizaciones filtradas y verificadas por `company_id` en `ApplicationRepository` y `JobManagementService` | Mitigado | RN-010 |
| TEC-001 | Datos inconsistentes en registro de empresa y reclutador | 2 | 2 | Media (4) | Registro de empresa y reclutador envuelto en transacción PDO (`beginTransaction` / `commit` / `rollBack`) y clave foránea `ON DELETE CASCADE` en `users.company_id` | Mitigado | RT-005 |
| TEC-002 | Pérdida de archivos entre contenedores o entornos locales | 2 | 2 | Media (4) | Almacenamiento binario unificado (`LONGBLOB`) en base de datos para hojas de vida, fotos de perfil y documentos de menores | Mitigado | RT-008 |
| TEC-003 | Conservación de la bitácora de auditoría al eliminar usuarios | 2 | 3 | **Alta (6)** | `audit_logs.user_id` utiliza `ON DELETE SET NULL`, preservando el registro histórico de auditoría aunque se elimine un usuario | Mitigado | Nota sin ID |
| TEC-004 | El ENUM `jobs.status` no incluye «pausada» | 3 | 1 | Media (3) | Estados acotados a `draft`, `published` y `closed` | Cerrado (por alcance) | Nota sin ID |
| TEC-005 | Enlaces de recuperación de contraseña por correo externo | 2 | 3 | **Alta (6)** | Fuera del alcance del prototipo (depende de servicio externo SMTP) | No aplica aún | Nota sin ID |
| TEC-006 | Favoritos y ocultas inconsistentes entre sesiones | 1 | 1 | Baja (1) | Persisten en `saved_jobs` y `hidden_jobs`; CP-015, CP-015B | Mitigado | Nota sin ID |

### 2.4 Rendimiento, operación y experiencia

| ID | Riesgo | P | I | Sev. | Mitigación | Estado | ID anterior |
| :-- | :-- | :-: | :-: | :-: | :-- | :-- | :-- |
| OPE-001 | Consultas sobre `audit_logs` con volumen alto | 2 | 2 | Media (4) | Índices `audit_created_at` y `audit_user_id` con límite de registros en consulta | Mitigado | PERF-001 |
| OPE-002 | Búsqueda de vacantes con múltiples filtros | 2 | 2 | Media (4) | Filtros parametrizados por título, ciudad, modalidad, experiencia, salario mín/máx y empresa en `JobRepository::search` | Mitigado | RNF-001 |
| OPE-003 | Servicio de correo SMTP externo | 2 | 2 | Media (4) | Fuera del alcance del prototipo funcional local | No aplica aún | BLQ-02 |
| OPE-004 | La mensajería retrasa el cronograma del MVP | 1 | 2 | Baja (2) | Módulo ya implementado y probado | Cerrado | CHG-04 |
| UX-001 | Diseño responsive en dispositivos móviles y paneles divididos | 2 | 1 | Baja (2) | Ajuste de grillas responsive en Hoja de Vida y panel dividido con scroll independiente en el buscador de empleos | Cerrado | UXH-001 |

## 3. Historial de defectos corregidos

| ID | Defecto | Dónde | Severidad | Estado |
| :-- | :-- | :-- | :-: | :-- |
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
