# Matriz de Riesgos y Defectos - Youth Employment Bridge (YEB)

**Versión:** 2.0 | **Fecha de revisión:** 19/10/2026 | **Alcance:** prototipo funcional (no apto para producción sin cerrar los riesgos marcados como Alta).

## 1. Método de valoración

| Valor | Probabilidad (P) | Impacto (I) |
| :-: | :-- | :-- |
| 1 | Poco probable en el uso previsto | Molestia menor, sin pérdida de datos |
| 2 | Posible | Afecta una función o expone datos limitados |
| 3 | Probable o ya presente | Expone datos personales, incumple la ley o bloquea el servicio |

**Severidad = P × I.** 1–2: **Baja** · 3–4: **Media** · 6–9: **Alta**.

**Estados:** *Abierto* (sin mitigación), *Parcial* (mitigación incompleta), *Mitigado* (control presente en el código, por confirmar con el plan de pruebas), *No aplica aún* (depende de una función pendiente), *Cerrado*.

**Convención de IDs:** `SEC` seguridad · `RSK` privilegios y cuentas · `LEG` cumplimiento legal · `NEG` reglas de negocio · `TEC` técnico y datos · `OPE` rendimiento y operación · `UX` experiencia de usuario. Cada ID es **un solo riesgo**. Un riesgo es algo que *puede* pasar; lo que *ya pasa* está en la sección 3 (defectos).

## 2. Matriz de riesgos

### 2.1 Seguridad y privacidad

| ID | Riesgo | P | I | Sev. | Mitigación | Estado | ID anterior |
| :-- | :-- | :-: | :-: | :-: | :-- | :-- | :-- |
| SEC-001 | Vulneración de credenciales (contraseñas débiles o robadas) | 2 | 3 | **Alta (6)** | Hash con `password_hash()` (presente); subir mínimo a 8 caracteres (DEF-005); HTTPS en cualquier despliegue | Parcial | SEC-001, RSK-02 |
| SEC-002 | Descarga de hojas de vida por empresas sin postulación | 2 | 3 | **Alta (6)** | Validación de postulación en `cvForDownload`; CP-009B | Mitigado | SEC-002 |
| SEC-003 | Inyección SQL por los filtros del buscador | 1 | 3 | Media (3) | Consultas preparadas PDO; CP-024 | Mitigado | SEC-003 |
| SEC-004 | Secuestro de sesión por cookie sin flags de seguridad | 2 | 3 | **Alta (6)** | `session_regenerate_id` en login (presente); falta configurar `HttpOnly`, `SameSite` y `Secure` | Abierto | SEC-004 |
| SEC-005 | Fuerza bruta contra el login | 3 | 2 | **Alta (6)** | Límite de intentos y bloqueo temporal (HU-003 CA-03); el login ya audita `login_failed` | Abierto | SEC-005 |
| SEC-006 | Lectura o escritura de conversaciones por usuarios ajenos a la postulación | 2 | 3 | **Alta (6)** | Control de acceso en `ChatService`; `ChatTest.php` | Mitigado | SEC-002 (parte chat) |
| SEC-007 | XSS almacenado en campos de texto | 2 | 2 | Media (4) | Escape con `self::e()` en vistas; CP-025 | Mitigado | SEC-007 |
| SEC-008 | CSRF en formularios POST | 2 | 2 | Media (4) | Token por sesión validado con `hash_equals`; CP-006 | Mitigado | SEC-008 |
| SEC-010 | Despliegue con credenciales demo (`password`) y MySQL `root` sin contraseña | 2 | 3 | **Alta (6)** | No cargar `seed.sql` fuera de pruebas; usuario de BD dedicado con contraseña; cambiar cuentas demo | Abierto (aceptado en prototipo) | Nuevo |
| SEC-011 | Subida de archivos maliciosos como CV | 2 | 2 | Media (4) | Lista de tipos MIME permitidos y límite de 5 MB (presentes); confirmar que los archivos se guardan fuera del acceso público y con nombre generado; CP-008B | Parcial | Nuevo |

### 2.2 Privilegios, cuentas y cumplimiento

| ID | Riesgo | P | I | Sev. | Mitigación | Estado | ID anterior |
| :-- | :-- | :-: | :-: | :-: | :-- | :-- | :-- |
| RSK-04 | Escalación de privilegios (acciones de otro rol) | 2 | 3 | **Alta (6)** | Verificación de rol por acción; CP-005 | Mitigado | RSK-04 |
| RSK-05 | Edición de perfiles ajenos | 1 | 2 | Baja (2) | El perfil se edita solo con el ID de la sesión; CP-005 | Mitigado | RSK-04 |
| RSK-06 | Eliminación no intencionada de cuentas administradoras | 2 | 2 | Media (4) | El sistema impide borrar la cuenta propia; falta confirmación y protección del último administrador | Parcial | RSK-04 |
| LEG-001 | Incumplimiento de la Ley 1581 de 2012 (Habeas Data), en especial con datos de menores | 3 | 3 | **Alta (9)** | Implementar HU-021: política de datos, fecha de nacimiento, autorización del representante legal, registro con versión | Abierto | BLQ-03, RSK-08, SEC-009 |

### 2.3 Negocio y datos

| ID | Riesgo | P | I | Sev. | Mitigación | Estado | ID anterior |
| :-- | :-- | :-: | :-: | :-: | :-- | :-- | :-- |
| NEG-001 | Empresas falsas y vacantes fraudulentas sin moderación | 2 | 3 | **Alta (6)** | NIT obligatorio (DEF-008), moderación de administrador (HU-022), campo de verificación de empresa | Abierto | RT-006 |
| NEG-002 | Publicar vacantes con perfil de empresa incompleto | 3 | 2 | **Alta (6)** | Exigir razón social y NIT antes de publicar (HU-013 CA-02) | Abierto | RN-003 |
| NEG-003 | Postulaciones duplicadas | 1 | 1 | Baja (1) | Restricción `UNIQUE (job_id, user_id)` y verificación previa | Mitigado | RN-005 |
| NEG-004 | Sin historial de fechas en los cambios de estado (no hay trazabilidad del proceso) | 3 | 2 | **Alta (6)** | Registrar fecha por transición (DEF-007); hoy solo se audita en `audit_logs` | Abierto | RN-009 |
| NEG-005 | Exposición de candidatos de otras empresas | 2 | 3 | **Alta (6)** | Consultas filtradas por `company_id`; CP-020 | Mitigado | RN-010 |
| TEC-001 | Datos inconsistentes por falta de transacciones (p. ej. empresa creada sin su reclutador) | 2 | 2 | Media (4) | Envolver registro de empresa y usuario en una transacción | Abierto | RT-005 |
| TEC-002 | Pérdida o corrupción de CV almacenados en disco | 2 | 2 | Media (4) | Respaldos periódicos del directorio de archivos | Abierto | RT-008 |
| TEC-003 | El borrado en cascada elimina postulaciones y puede afectar la auditoría, que el README describe como «inmutable» | 2 | 3 | **Alta (6)** | Revisar las claves foráneas de `audit_logs`; preferir suspensión o borrado lógico; conservar la bitácora | Abierto | Nota sin ID |
| TEC-004 | El ENUM `jobs.status` no incluye «pausada» | 3 | 1 | Media (3) | Quitar «pausar» del alcance (ya hecho en HU-017) o ampliar el ENUM | Cerrado (por alcance) | Nota sin ID |
| TEC-005 | Enlaces de recuperación sin vencimiento o reutilizables | 2 | 3 | **Alta (6)** | Token de un solo uso con expiración corta, guardado como hash | No aplica aún (HU-019) | Nota sin ID |
| TEC-006 | Favoritos y ocultas inconsistentes entre sesiones | 1 | 1 | Baja (1) | Persisten en `saved_jobs` y `hidden_jobs`; CP-015, CP-015B | Mitigado | Nota sin ID |

### 2.4 Rendimiento, operación y experiencia

| ID | Riesgo | P | I | Sev. | Mitigación | Estado | ID anterior |
| :-- | :-- | :-: | :-: | :-: | :-- | :-- | :-- |
| OPE-001 | Consultas lentas sobre `audit_logs` con mucho volumen | 2 | 2 | Media (4) | Índices por módulo, acción y fecha; paginación | Abierto | PERF-001 |
| OPE-002 | Búsqueda lenta con muchas solicitudes (`LIKE '%texto%'` no usa índice) | 2 | 2 | Media (4) | Índices o `FULLTEXT`; CP-028 con criterio definido | Abierto | RNF-001 |
| OPE-003 | Servicio de correo SMTP no disponible o mal configurado | 2 | 2 | Media (4) | Configuración y prueba de envío previas a HU-019 | No aplica aún (HU-019) | BLQ-02 |
| OPE-004 | La mensajería retrasa el cronograma del MVP | 1 | 2 | Baja (2) | Módulo ya implementado y probado | Cerrado | CHG-04 |
| UX-001 | Formularios desbordados en pantallas de 390 px | 2 | 1 | Baja (2) | CP-029 | Abierto | UXH-001 |
| UX-003 | Vocabulario de estados distinto entre secciones | 2 | 1 | Baja (2) | Un solo diccionario de etiquetas para `pending`…`rejected` | Abierto | UXH-003 |
| UX-005 | Tras «Ingresar para aplicar» no se vuelve al detalle de la vacante | 3 | 1 | Media (3) | Guardar la URL de retorno (ya existe `safeReturn` para otros flujos) | Abierto | UXH-005 |

### 2.5 Resumen

- **Alta:** SEC-001, SEC-002, SEC-004, SEC-005, SEC-006, SEC-010, RSK-04, LEG-001, NEG-001, NEG-002, NEG-004, NEG-005, TEC-003, TEC-005.
- **Prioridad de atención inmediata (Alta y *Abierto*):** LEG-001, SEC-004, SEC-005, SEC-010, NEG-001, NEG-002, NEG-004, TEC-003.

## 3. Defectos conocidos (confirmados en el código)

Los defectos ya ocurren, por eso no llevan probabilidad. Cada uno se verifica con un caso de prueba que hoy **debe fallar**; al corregirlo, el caso debe pasar y el defecto se cierra.

| ID | Defecto | Dónde | Severidad | HU / CA afectado | CP | Estado |
| :-- | :-- | :-- | :-: | :-- | :-- | :-- |
| DEF-001 | Error 500 al filtrar por ubicación: el parámetro `:location` se usa dos veces y `PDO::ATTR_EMULATE_PREPARES` es `false`. Solución: dos nombres (`:location_city`, `:location_mode`) | `Modules/Jobs/Repositories/JobRepository.php` (línea 24) | Alta | HU-005 CA-02 | CP-013B | Abierto |
| DEF-002 | Se puede postular a una vacante cerrada o en borrador: `ApplicationService::apply` solo revisa duplicados | `Modules/Applications/Services/ApplicationService.php` | Alta | HU-009 CA-03 | CP-018 | Abierto |
| DEF-003 | No se notifica al candidato cuando cambia el estado de su postulación | `app/Http/Portal.php` (acción `update_application`) | Media | HU-018 CA-02 | CP-021C | Abierto |
| DEF-004 | Mensaje de éxito falso: se muestra «Estado actualizado» y se audita `success` aunque el `UPDATE` no afecte filas (postulación de otra empresa). Mismo patrón por revisar en editar y cerrar vacantes | `Portal.php`, `ApplicationRepository::updateStatus` | Media | HU-016 CA-03, HU-017 CA-02 | CP-020D, CP-012C | Abierto |
| DEF-005 | La contraseña mínima es de 6 caracteres y la HU exige 8 | `Modules/Auth/Services/AuthService.php` (líneas 72 y 153) | Media | HU-001 CA-03 | CP-002B | Abierto |
| DEF-006 | La alerta de nueva postulación se envía al candidato, no a la empresa | `Portal.php` (acción `apply`) | Media | HU-018 CA-01 | CP-021 | Abierto |
| DEF-007 | Los cambios de estado no guardan fecha y hora (la tabla solo tiene `created_at`) | `database/schema.sql` | Media | HU-016 CA-01 | CP-020B | Abierto |
| DEF-008 | No existe el campo NIT, por lo que no puede exigirse para publicar | `database/schema.sql`, módulo Companies | Media | HU-013 CA-02 | CP-010 | Abierto |

### Observaciones de documentación

| ID | Observación | Estado |
| :-- | :-- | :-- |
| OBS-001 | El README dice que el buscador filtra por modalidad y salario; el código solo filtra por palabra clave y ubicación (ciudad o modalidad en un único campo) | Abierto: corregir README o implementar |
| OBS-004 | Formatos de CV: HU decía PDF, código y README aceptan PDF, DOC y DOCX | Cerrado: HU-007 alineada |