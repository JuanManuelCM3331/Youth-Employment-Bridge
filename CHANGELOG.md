# Changelog — Youth Employment Bridge (YEB)

Registro histórico exhaustivo y cronológico de **absolutamente todos los commits** (78 commits en total, incluyendo cada commit individual y cada Merge de Pull Request) del repositorio oficial [`JuanManuelCM3331/Youth-Employment-Bridge`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge).

El formato combina el estándar [Keep a Changelog](https://keepachangelog.com/es-ES/1.1.0/) con una **línea de tiempo detallada commit por commit**, indicando fecha y hora exacta, autor, archivos intervenidos, estadísticas de líneas (`+insertions / -deletions`) y el impacto técnico en la arquitectura del sistema.

---

## Resumen de Hitos por Etapa

| Versión / Etapa | Rango de Fechas | Commits | Descripción Principal |
| :--- | :--- | :---: | :--- |
| **[v1.0.1]** | `2026-11-10` | 4 | **Parche de seguridad y despliegue en hosting compartido (InfinityFree)**: soporte de `Env` en memoria sin `putenv`/`getenv`, aislamiento de carpetas con `.htaccess` (`Require all denied`), redirección HTTPS forzada, HSTS, `X-XSS-Protection`, `upgrade-insecure-requests` en CSP y script SQL unificado (`database/infinityfree_full.sql`). |
| **[v1.0.0]** | `2026-11-01` | Release | **Lanzamiento oficial de la primera versión del prototipo funcional**: consolidación integral de módulos, controles de seguridad, artefactos de entrega (`CHANGELOG.md`, `LICENSE`, `composer.lock`, `.gitignore`, `.env.example`) y documentación final. |
| **[v0.6.0]** | `2026-11-01` | 5 | Endurecimiento de seguridad (fuerza bruta, Push con sesión/CSRF, cabeceras HTTP, MIME `finfo`), reglas de negocio, FK/transacciones, vista responsive de CV y rediseño visual anti-fatiga. |
| **[v0.5.0]** | `2026-10-30` – `2026-10-31` | 9 | Almacenamiento binario (`LONGBLOB`) en MySQL para fotos/CVs/documentos, desacoplamiento de vistas en `Modules/*/UI/*`, mejoras QoL y correcciones en filtros y chat. |
| **[v0.4.0]** | `2026-10-26` – `2026-10-27` | 11 | Panel administrativo de métricas graficadas, recomendaciones por coincidencias, flujo legal y bloqueo para menores de edad (16–17 años), centro legal colombiano y Dockerización completa. |
| **[v0.3.0]** | `2026-10-19` – `2026-10-24` | 20 | Notificaciones Push en tiempo real, lectura asistida PDF417/MRZ (`IdScan`), validación de cédula/NIT, mensajería directa (`Chat`), cumplimiento de Historias de Usuario y pruebas unitarias. |
| **[v0.2.0]** | `2026-10-05` – `2026-10-14` | 24 | Subida del primer prototipo base, modularización en capas (`Controllers`, `Services`, `Repositories`), extracción de `PortalViewRenderer`, reversión de capa experimental AJAX y filtros de auditoría. |
| **[v0.1.0]** | `2026-05-13` – `2026-05-26` | 9 | Creación del repositorio, definición de la arquitectura modular inicial, estructura de carpetas base y maquetación preliminar de aterrizaje. |

---

## [v1.0.1] — 10 de noviembre de 2026: Parche de Seguridad y Despliegue en Hosting Compartido (InfinityFree)

### Resumen del Parche (`v1.0.1`)
- **Compatibilidad con Hosting Compartido (`app/Core/Env.php`)**:
  - Implementación de almacenamiento en memoria (`self::$values`) y respaldo en `$_ENV` / `$_SERVER` cuando las funciones nativas `putenv()` y `getenv()` están deshabilitadas por directiva `disable_functions` en `php.ini` (caso de InfinityFree en PHP 8.2+), preservando intacta la precedencia de variables de entorno reales en Docker y XAMPP.
- **Detección de Proxy Inverso HTTPS y Rutas en Raíz de Dominio**:
  - Soporte de `$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https'` en `app/Core/Session.php` para cookies `Secure` detrás de proxies SSL/TLS.
  - Ajuste en `PortalViewRenderer::assetUrl()` para resolver correctamente `/public/assets/...` tanto al servir desde la raíz `htdocs/index.php` como desde el subdirectorio `public/index.php`.
- **Aislamiento de Directorios y Endurecimiento de Cabeceras HTTP (`.htaccess` y `app/Http/Portal.php`)**:
  - Incorporación de `.htaccess` raíz con `Options -Indexes`, bloqueo de archivos ocultos (`^\.(?!well-known)`), bloqueo de archivos sensibles (`.env`, `.sql`, `composer.json`, `Dockerfile`, `docker-compose.yml`, `.md`) y redirección `301` forzada de HTTP a HTTPS en dominios públicos.
  - Creación de archivos `.htaccess` con `Require all denied` en todos los directorios internos no públicos (`app/`, `Modules/`, `config/`, `database/`, `storage/`, `vendor/`, `analytics/`, `docker/`, `Documentacion/`).
  - Cabeceras HTTP reforzadas en PHP y Apache (`Strict-Transport-Security: max-age=31536000; includeSubDomains`, `X-XSS-Protection: 1; mode=block`, `X-Content-Type-Options: nosniff`, `X-Frame-Options: DENY`, `Referrer-Policy`, `Permissions-Policy` y `Content-Security-Policy` con `upgrade-insecure-requests`).
- **Base de Datos Unificada (`database/infinityfree_full.sql`)**:
  - Script SQL consolidado sin sentencias `CREATE DATABASE` ni `USE`, con motor `InnoDB` y `utf8mb4_unicode_ci`, integrando el esquema base, las 7 migraciones e inserciones semilla idempotentes listo para importación directa en phpMyAdmin.

---

## [v1.0.0] — 1 de noviembre de 2026: Lanzamiento de la Primera Versión del Prototipo Funcional

### Consolidación del Prototipo Funcional (`v1.0.0`)
- **Alcance funcional completo por rol**:
  - **Candidato Joven (16 a 28 años)**: Registro con verificación asistida de documento (lectura PDF417/MRZ en navegador), flujo de protección legal y radicación de soportes para menores de edad (16 y 17 años), perfil profesional con foto y hoja de vida PDF almacenados en base de datos (`LONGBLOB`), buscador multicriterio con recomendaciones por afinidad, guardado/ocultamiento de ofertas, postulación a vacantes, seguimiento de estados, chat con reclutadores en etapa de entrevista y notificaciones push en tiempo real.
  - **Empresa / Reclutador**: Registro atómico transaccional de empresa y reclutador, gestión de perfil e identidad corporativa, publicación, edición y cierre de vacantes con validación salarial, gestión regulada de candidatos postulados mediante máquina de estados (`pending` -> `review` -> `interview` -> `accepted` / `rejected`), descarga autorizada de hojas de vida y mensajería directa.
  - **Administrador**: Panel de métricas graficadas e indicadores clave (KPIs), moderación de cuentas (`active` / `suspended` con cierre inmediato de sesión), protección contra eliminación o suspensión del último administrador activo, validación/aprobación de documentos legales de menores de edad, verificación de empresas y bitácora completa de auditoría (`audit_logs`).
- **Seguridad e integridad consolidada**:
  - Políticas de contraseñas de mínimo 8 caracteres con hash `bcrypt` y bloqueo automático por fuerza bruta tras 5 intentos fallidos durante 15 minutos.
  - Protección CSRF en todas las peticiones `POST`, regeneración de sesión, cabeceras globales de seguridad HTTP (`Content-Security-Policy`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy`, `Permissions-Policy`), validación de archivos por contenido real (`finfo`) y ocultación de errores internos de base de datos (`PDOException`).
- **Artefactos oficiales de entrega incluidos en la versión `v1.0.0`**:
  - `CHANGELOG.md` con trazabilidad histórica completa del proyecto.
  - `LICENSE` bajo licencia **MIT** (2026 Juan Manuel Cardona Molina).
  - `composer.json` y archivo de dependencias bloqueadas `composer.lock`.
  - `.gitignore` y `.env.example` configurados para despliegue local (XAMPP) y en contenedores (`Dockerfile` / `docker-compose.yml`).
  - `README.md` y documentación técnica en `Documentacion/` actualizados, incluyendo la declaración de asistencia con herramientas de IA (**Google Gemini** y **Anthropic Claude**).

---

## [v0.6.0] — 1 de noviembre de 2026: Endurecimiento de Seguridad, Validación de Reglas de Negocio y Rediseño Visual

### 1. [`71faece`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/71faece8ed04759522809054b9dd1996b0a6c960) — `2026-11-01 12:14:52 -0500`
- **Mensaje**: `fix(auth,jobs,docs): exigir contrasena de 8 caracteres, transaccion en empresas y actualizar README y RIESGOS`
- **Autor**: `juanma3331`
- **Estadísticas**: `7 files changed, 274 insertions(+), 393 deletions(-)`
- **Archivos intervenidos**:
  - `Modules/Auth/Services/AuthService.php`
  - `Modules/Dashboard/Services/DashboardService.php`
  - `Modules/Jobs/Repositories/JobManagementRepository.php`
  - `Modules/Jobs/Services/JobManagementService.php`
  - `app/Http/PortalViewRenderer.php`
  - `Documentacion/RIESGOS.md`
  - `README.md`
- **Detalle técnico**:
  - Se elevó la longitud mínima de contraseña a **8 caracteres** (`AuthService::MIN_PASSWORD_LENGTH = 8`) en el registro de candidatos, registro de empresas, cambio de contraseña y creación de administradores desde el panel administrativo (`DashboardService::createAdmin`), actualizando los atributos `minlength="8"` y textos de ayuda en `PortalViewRenderer`.
  - Se envolvió el registro de empresas (`AuthService::registerCompany`) en una **transacción PDO (`beginTransaction` / `commit` / `rollBack`)** para evitar registros huérfanos en `companies` si falla la inserción del usuario reclutador.
  - Se añadió el método `JobManagementRepository::belongsToCompany(int $id, int $companyId)` y se validó en `JobManagementService::update()` y `JobManagementService::close()` para lanzar excepción si un reclutador intenta editar o cerrar una vacante ajena.
  - Se reescribió íntegramente `README.md` y se actualizó `Documentacion/RIESGOS.md` para reflejar los controles de seguridad implementados.

---

### 2. [`3346b26`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/3346b2627897f40023b6f49baaaa29ccf874b8bf) — `2026-11-01 11:56:44 -0500`
- **Mensaje**: `fix(security,validation,db): reforzar seguridad en auth, push, descargas, cabeceras HTTP e integridad de datos`
- **Autor**: `juanma3331`
- **Estadísticas**: `17 files changed, 1017 insertions(+), 900 deletions(-)`
- **Archivos intervenidos**:
  - `app/Http/Portal.php`, `public/assets/push-notifications.js`
  - `Modules/Auth/Services/AuthService.php`, `Modules/Auth/Repositories/AuthRepository.php`
  - `Modules/Notifications/Controllers/NotificationsController.php`, `Modules/Notifications/Repositories/PushSubscriptionRepository.php`
  - `Modules/Jobs/Repositories/JobManagementRepository.php`, `Modules/Jobs/Services/JobManagementService.php`, `Modules/Jobs/UI/JobsUI.php`
  - `Modules/Applications/Repositories/ApplicationRepository.php`, `Modules/Applications/Services/ApplicationService.php`, `Modules/Applications/UI/ApplicationsUI.php`
  - `Modules/Dashboard/Repositories/DashboardRepository.php`, `Modules/Dashboard/Services/DashboardService.php`
  - `Modules/Identity/Services/MinorDocumentService.php`
  - `database/schema.sql`, `database/migrate_company_audit.sql`
- **Detalle técnico**:
  - **Bloqueo por fuerza bruta**: Implementación de bloqueo tras **5 intentos fallidos** (`MAX_LOGIN_ATTEMPTS = 5`, ventana de 15 minutos) tanto en `AuthService::login` como en `AuthService::changePassword` (`countRecentFailedPasswordChanges`).
  - **Seguridad en Notificaciones Push**: Generación del `device_token` en el servidor con `bin2hex(random_bytes(32))` dentro de `PushSubscriptionRepository::save()`, exigencia de sesión activa en `push_subscribe`, `push_unsubscribe`, `push_poll` y `push_test`, y cambio de `push_test` a petición `POST` protegida con CSRF (`X-CSRF-Token`).
  - **Ocultación de errores crudos (`PDOException`)**: Creación de `Portal::resolveUserErrorMessage(\Throwable $exception)` para registrar el detalle técnico en `error_log` y mostrar un mensaje genérico seguro al usuario ante excepciones inesperadas.
  - **Protección de vacantes en borrador o cerradas**: Filtrado por Defecto (`onlyPublished = true`) en `JobManagementRepository::find()` para que `GET ?action=job_detail` solo retorne ofertas en estado `published`, ocultando el botón de postulación en `JobsUI::jobDetail()` si el estado no es `published`.
  - **Eliminación de cambio de estado por GET**: Remoción de la llamada a `markInReview()` al abrir `GET ?action=candidate_public_profile` en `Portal.php`.
  - **Reglas estrictas de transición de estado (`ApplicationService::ALLOWED_TRANSITIONS`)**: Bloqueo de transiciones regresivas o desde estados finales (`accepted`, `rejected`) tanto en `ApplicationService::updateStatus()` como en el selector `<select>` de `ApplicationsUI::companyApplications()`.
  - **Validación salarial en vacantes (`JobManagementService::parseSalaryRange`)**: Validación numérica estricta de `salary_min` y `salary_max` y exigencia de `salary_min <= salary_max`.
  - **Integridad referencial y protección del último administrador**: Adición de clave foránea `ON DELETE CASCADE` en `users.company_id`, eliminación en cascada de reclutadores al borrar una empresa (`deleteUsersByCompany`) y validación `countActiveAdmins() <= 1` para impedir eliminar o suspender al último administrador activo.
  - **Validación MIME y descarga segura de documentos de menores (`MinorDocumentService`)**: Uso exclusivo de `finfo` (`FILEINFO_MIME_TYPE`), sanitización del nombre original y entrega con `Content-Disposition: attachment` y `X-Content-Type-Options: nosniff`.
  - **Cabeceras de seguridad HTTP globales (`Portal::sendSecurityHeaders`)**: Envío de `Content-Security-Policy`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy` y `Permissions-Policy`.

---

### 3. [`530ad4f`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/530ad4f1e8dc433f19ffb2d05c8fba68b6ea7bff) — `2026-11-01 11:24:33 -0500`
- **Mensaje**: `fix(security,db,ui): corregir 5 fallos criticos de seguridad/BD`
- **Autor**: `juanma3331`
- **Estadísticas**: `13 files changed, 179 insertions(+), 88 deletions(-)`
- **Archivos intervenidos**:
  - `Modules/Auth/Services/AuthService.php`
  - `Modules/Applications/Controllers/ApplicationsController.php`, `Modules/Applications/Repositories/ApplicationRepository.php`, `Modules/Applications/Services/ApplicationService.php`
  - `Modules/Identity/UI/IdentityUI.php`
  - `app/Http/Portal.php`, `app/Http/PortalViewRenderer.php`
  - `database/schema.sql`, `database/migrate_minor.sql`, `database/migrate_file_storage.sql`, `docker-compose.yml`, `README.md`
  - Eliminado: `add_status.php`
- **Detalle técnico**:
  - Eliminación de la puerta trasera en `AuthService::login` e `isLoginLocked` que sobrescribía la contraseña cuando se intentaba ingresar con `menor@yeb.test` o `menor@gmail.com`.
  - Integración completa del esquema en `database/schema.sql` (columnas `status`, `is_minor`, `minor_blocked`, `birth_date`, columnas binarias `LONGBLOB` y tabla `user_minor_documents`), actualización del orden de montaje en `docker-compose.yml` y eliminación del script auxiliar `add_status.php`.
  - Aplicación de verificación en tiempo real al inicio de `Portal::run()` para invalidar la sesión si el usuario pasa a `status = 'suspended'` y restringir acciones GET/POST de menores bloqueados (`minor_blocked = 1`) exclusivamente a `upload_minor_docs` y `logout`.
  - Verificación de propiedad de la vacante (`j.company_id = :company_id`) en `ApplicationRepository::updateStatus()` antes de modificar una postulación o emitir notificaciones.
  - Cálculo de `birth_date`, `is_minor` y `minor_blocked` en `AuthService::register()` directamente desde los datos extraídos del documento escaneado (`$idScan['birth_date']`) y documentación del lector PDF417/MRZ como verificación asistida en navegador.

---

### 4. [`ff59a62`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/ff59a62c6c50dec434672b582c901feb9c25c3a2) — `2026-11-01 10:59:32 -0500`
- **Mensaje**: `fix(cv): solucion de la vista del perfil, se rompia el diseño responsive`
- **Autor**: `juanma3331`
- **Estadísticas**: `2 files changed, 20 insertions(+), 11 deletions(-)`
- **Archivos intervenidos**: `Modules/Users/UI/UsersUI.php`, `app/Http/PortalViewRenderer.php`
- **Detalle técnico**:
  - Adición de `min-w-0`, `overflow-hidden`, `break-all` y ajuste de grillas flexibles en la vista de Hoja de Vida y perfil público del candidato para evitar que nombres largos de archivos PDF, correos electrónicos o enlaces externos rompieran el contenedor en pantallas móviles.

---

### 5. [`fa83286`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/fa832869a18c273ea6a7e1370aef074c45091acd) — `2026-11-01 10:51:06 -0500`
- **Mensaje**: `enhanced(frontend): mejora en el diseño visual de la pagina para evitar la fatiga visual`
- **Autor**: `juanma3331`
- **Estadísticas**: `8 files changed, 525 insertions(+), 455 deletions(-)`
- **Archivos intervenidos**:
  - `Modules/Applications/UI/ApplicationsUI.php`, `Modules/Auditoria/UI/AuditoriaUI.php`, `Modules/Companies/UI/CompaniesUI.php`, `Modules/Dashboard/UI/DashboardUI.php`, `Modules/Jobs/UI/JobsUI.php`, `Modules/Search/UI/SearchUI.php`, `Modules/Users/UI/UsersUI.php`, `app/Http/PortalViewRenderer.php`
- **Detalle técnico**:
  - Rediseño cromático anti-fatiga sustituyendo blancos puros por tonos suaves (`#f1f5f9` / `#f8fafc`), cabeceras con contraste definido, bordes diferenciados entre tarjetas y vista maestra-detalle en el buscador de empleos (`SearchUI`) con desplazamiento vertical independiente en el listado izquierdo y el panel de detalle derecho.

---

## [v0.5.0] — 30 al 31 de octubre de 2026: Almacenamiento Binario en BD, Desacoplamiento de Vistas (`UI`) y Mejoras en Chat/Búsqueda

### 6. [`568bb9d`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/568bb9d4f1dfdc033aa7027d9d8cf03a9ac31b2a) — `2026-10-31 20:22:23 -0500`
- **Mensaje**: `Merge pull request #50 from JuanManuelCM3331/feat/JMCM`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Integración a la rama principal de las mejoras de almacenamiento binario en base de datos y refactorización de la capa de interfaz (`UI`).

---

### 7. [`b14cb55`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/b14cb55784c19d1deafbef52350c0fcf1775fe46) — `2026-10-31 20:20:42 -0500`
- **Mensaje**: `fix(db): arreglo de la subida de documentos y fotos, se subian a local storage y no a la db, por lo que otros usuarios no podian acceder a ellos`
- **Autor**: `juanma3331`
- **Estadísticas**: `24 files changed, 413 insertions(+), 110 deletions(-)`
- **Archivos intervenidos**:
  - `database/migrate_file_storage.sql`, `database/backfill_file_storage.php`, `database/migrate_minor.sql`, `database/schema.sql`
  - `Modules/Users/*`, `Modules/Companies/*`, `Modules/Identity/*`, `Modules/Dashboard/*`, `Modules/Applications/*`, `Modules/Chat/*`, `app/Http/Portal.php`, `app/Http/PortalViewRenderer.php`, `README.md`
- **Detalle técnico**:
  - Creación de `database/migrate_file_storage.sql` para añadir columnas `LONGBLOB` en `users` (`profile_photo_data`, `cv_data`), `companies` (`profile_photo_data`) y `user_minor_documents` (`civil_registry_data`, `parent_id_data`, `authorization_letter_data`).
  - Creación del procedimiento CLI `database/backfill_file_storage.php` para reconstruir los binarios en MySQL a partir de archivos existentes en disco.
  - Actualización de servicios y repositorios para servir avatares, hojas de vida y soportes legales de menores directamente desde MySQL con respaldo automático a disco.

---

### 8. [`fda423c`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/fda423c0c4346e85c613ef9dd3c3400279af9ea9) — `2026-10-31 20:03:56 -0500`
- **Mensaje**: `enhanced(frontend): mejoras de calidad de vida en la interfaz de usuario`
- **Autor**: `juanma3331`
- **Estadísticas**: `11 files changed, 543 insertions(+), 5 deletions(-)`
- **Archivos intervenidos**: Todas las clases `Modules/*/UI/*UI.php` y `app/Http/PortalViewRenderer.php`.
- **Detalle técnico**: Mejoras de experiencia de usuario, retroalimentación visual, estados vacíos informativos y consistencia de navegación entre todas las vistas modulares.

---

### 9. [`6a6593a`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/6a6593a0c1bee9f6efc5d47358669e13e4f23911) — `2026-10-31 15:38:51 -0500`
- **Mensaje**: `enhanced(aplicationsUI): mejor organizacion de los candidatos por candidatura`
- **Autor**: `juanma3331`
- **Estadísticas**: `1 file changed, 59 insertions(+), 85 deletions(-)`
- **Archivos intervenidos**: `Modules/Applications/UI/ApplicationsUI.php`
- **Detalle técnico**: Reestructuración de la vista de postulaciones recibidas por la empresa para agrupar y presentar de forma más clara a los candidatos asociados a cada oferta laboral.

---

### 10. [`7b63ea3`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/7b63ea3625a23de6f3ac10851838f0228c2315dc) — `2026-10-31 15:29:23 -0500`
- **Mensaje**: `enhanced(frontend): mejora en la organizacion del frontend de la pagina`
- **Autor**: `juanma3331`
- **Estadísticas**: `12 files changed, 8696 insertions(+), 8707 deletions(-)`
- **Archivos intervenidos**:
  - Creación de 10 clases de interfaz por módulo: `ApplicationsUI.php`, `AuditoriaUI.php`, `ChatUI.php`, `CompaniesUI.php`, `DashboardUI.php`, `IdentityUI.php`, `JobsUI.php`, `NotificationsUI.php`, `SearchUI.php`, `UsersUI.php`.
  - Reducción masiva de `app/Http/PortalViewRenderer.php` y actualización de llamadas en `app/Http/Portal.php`.
- **Detalle técnico**: Desacoplamiento arquitectónico de la capa de presentación, extrayendo las vistas HTML de cada dominio fuera de `PortalViewRenderer` hacia su respectivo módulo `Modules/<Modulo>/UI/<Modulo>UI.php`.

---

### 11. [`436ce6d`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/436ce6d8550619844141972fc72a636678e701a7) — `2026-10-30 09:13:39 -0500`
- **Mensaje**: `Merge pull request #49 from JuanManuelCM3331/feat/JMCM`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión de las mejoras en el buscador de ciudades, sistema de chat y corrección de filtros.

---

### 12. [`b303e97`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/b303e977f2c05ab57055bea09c433ab3b90a7983) — `2026-10-30 08:25:50 -0500`
- **Mensaje**: `fix(search): arreglo del bug de ciudad en el buscador`
- **Autor**: `juanma3331`
- **Estadísticas**: `2 files changed, 20 insertions(+), 17 deletions(-)`
- **Archivos intervenidos**: `Modules/Jobs/Repositories/JobRepository.php`, `Modules/Search/Services/SearchService.php`
- **Detalle técnico**: Corrección de la consulta SQL y normalización de nombres de ciudades al filtrar vacantes por ubicación geográfica.

---

### 13. [`02e6e08`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/02e6e080c37135400fb37fb23e7e03f28f13560f) — `2026-10-30 08:11:07 -0500`
- **Mensaje**: `enhanced(chat): mejora en el sistema de chat y estados`
- **Autor**: `juanma3331`
- **Estadísticas**: `7 files changed, 140 insertions(+), 26 deletions(-)`
- **Archivos intervenidos**: `Modules/Applications/*`, `Modules/Chat/*`, `app/Http/Portal.php`, `app/Http/PortalViewRenderer.php`
- **Detalle técnico**: Sincronización entre el estado de la postulación y los permisos de envío de mensajes en el chat entre reclutador y candidato, además de mejoras en el paso a estado de revisión.

---

### 14. [`613d9a3`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/613d9a398c09ced6068b7ec20936d86e8de9a7de) — `2026-10-30 07:14:14 -0500`
- **Mensaje**: `fix(frontend): solucion de errores en los filtros`
- **Autor**: `juanma3331`
- **Estadísticas**: `1 file changed, 780 insertions(+), 41 deletions(-)`
- **Archivos intervenidos**: `app/Http/PortalViewRenderer.php`
- **Detalle técnico**: Corrección del comportamiento interactivo de los filtros de búsqueda de vacantes (modalidad, experiencia, salario y ciudad) y preservación de parámetros seleccionados.

---

## [v0.4.0] — 26 al 27 de octubre de 2026: Panel de Métricas, Recomendaciones, Protección de Menores, Marco Legal y Docker

### 15. [`f0970c8`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/f0970c843de91d93056f1e5a0bbc537cf85401e5) — `2026-10-27 17:01:31 -0500`
- **Mensaje**: `Merge pull request #48 from JuanManuelCM3331/feat/JMCM`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión del panel administrativo de métricas graficadas y corrección del buscador de la portada.

---

### 16. [`1bbe0b2`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/1bbe0b2492b567e7db347a9b136f62ecd5b6fc5d) — `2026-10-27 17:00:21 -0500`
- **Mensaje**: `feat(admin): implementacion de panel de metricas graficadas`
- **Autor**: `juanma3331`
- **Estadísticas**: `1 file changed, 1268 insertions(+), 13 deletions(-)`
- **Archivos intervenidos**: `app/Http/PortalViewRenderer.php`
- **Detalle técnico**: Implementación de la vista administrativa de estadísticas y métricas (`?action=admin_metrics`) con visualización gráfica de distribución de usuarios, vacantes por modalidad, estado de postulaciones, ciudades con mayor oferta y seguimiento de validación de menores.

---

### 17. [`ab0ae54`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/ab0ae54a8fa85cac3b24e40bacdc0c3510508f2f) — `2026-10-27 16:28:52 -0500`
- **Mensaje**: `fix(landing): arreglo del buscador de la landing page`
- **Autor**: `juanma3331`
- **Estadísticas**: `1 file changed, 8971 insertions(+), 4470 deletions(-)`
- **Archivos intervenidos**: `app/Http/PortalViewRenderer.php`
- **Detalle técnico**: Corrección del formulario de búsqueda rápida en la página de inicio para redirigir correctamente a `?action=search` conservando los términos de cargo y ciudad.

---

### 18. [`799e37e`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/799e37eed4d1e5dcba2f14ba6fbe87d0b22233a9) — `2026-10-27 16:00:13 -0500`
- **Mensaje**: `Merge pull request #47 from JuanManuelCM3331/feat/JMCM`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión del sistema de filtros por coincidencias, sección legal y gestión de menores de edad.

---

### 19. [`93e9652`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/93e9652abee5a56f66fa1df018dd5d5a86806dd8) — `2026-10-27 15:54:55 -0500`
- **Mensaje**: `feat(search): integracion de sistema de filtros por coincidencias en las postulaciones y recomendaciones`
- **Autor**: `juanma3331`
- **Estadísticas**: `5 files changed, 5170 insertions(+), 4988 deletions(-)`
- **Archivos intervenidos**: `Modules/Jobs/Repositories/JobRepository.php`, `Modules/Search/Controllers/SearchController.php`, `Modules/Search/Services/SearchService.php`, `app/Http/Portal.php`, `app/Http/PortalViewRenderer.php`
- **Detalle técnico**: Implementación de algoritmo de coincidencia y recomendación de vacantes según las habilidades (`skills`), título profesional y ubicación del candidato autenticado.

---

### 20. [`1f90407`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/1f90407dfd40f85882e77abacbdf59f0cb331cfc) — `2026-10-26 10:53:41 -0500`
- **Mensaje**: `enhanced(login): boton de autorrellenado para el usuario de prueba: menor de edad`
- **Autor**: `juanma3331`
- **Estadísticas**: `1 file changed, 5 insertions(+), 1 deletion(-)`
- **Archivos intervenidos**: `app/Http/PortalViewRenderer.php`
- **Detalle técnico**: Adición de botón de acceso rápido en la vista de inicio de sesión para autocompletar las credenciales de la cuenta demostrativa de menor de edad (`menor@yeb.test`).

---

### 21. [`6a6bb09`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/6a6bb092a1b517975b9cebab9a3cc8c5faac306c) — `2026-10-26 10:46:50 -0500`
- **Mensaje**: `Merge pull request #46 from JuanManuelCM3331/feat/JMCM`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión del módulo de protección de menores de edad y sección de información legal.

---

### 22. [`1652c8a`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/1652c8a97a8eb721863c6713185395e586212d13) — `2026-10-26 10:45:12 -0500`
- **Mensaje**: `feat(minors): implementacion de bloqueo para menores de edad y gestion administrativa`
- **Autor**: `juanma3331`
- **Estadísticas**: `13 files changed, 1028 insertions(+), 47 deletions(-)`
- **Archivos intervenidos**:
  - `database/migrate_minor.sql`, `database/seed.sql`
  - `Modules/Identity/Services/MinorDocumentService.php`, `Modules/Identity/Providers/SandboxCedulaProvider.php`
  - `Modules/Auth/Repositories/AuthRepository.php`, `Modules/Auth/Services/AuthService.php`
  - `Modules/Dashboard/Controllers/DashboardController.php`, `Modules/Dashboard/Repositories/DashboardRepository.php`, `Modules/Dashboard/Services/DashboardService.php`
  - `app/Http/Portal.php`, `app/Http/PortalViewRenderer.php`, `.gitignore`, `README.md`
- **Detalle técnico**:
  - Creación de la migración `database/migrate_minor.sql` con las columnas `is_minor`, `minor_blocked` y `birth_date` en `users` y la tabla `user_minor_documents`.
  - Creación de `MinorDocumentService` para gestionar la carga de los 3 documentos obligatorios (Registro Civil de Nacimiento, Cédula del Padre/Madre o Acudiente y Permiso de Trabajo / Carta de Autorización).
  - Incorporación del flujo administrativo en `DashboardService` para revisar los soportes radicados, aprobar y desbloquear al joven (`minor_blocked = 0`) o rechazar con motivo y notificarle.

---

### 23. [`d8c184f`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/d8c184f5c1575d49c65c4b13f3bc7119521db36d) — `2026-10-26 09:58:16 -0500`
- **Mensaje**: `feat(legal): Implementacion de seccion para informacion legal pertinente`
- **Autor**: `juanma3331`
- **Estadísticas**: `2 files changed, 660 insertions(+)`
- **Archivos intervenidos**: `app/Http/Portal.php`, `app/Http/PortalViewRenderer.php`
- **Detalle técnico**: Creación de la vista pública `?action=legal` con el marco normativo colombiano aplicable al empleo juvenil y adolescentes (Código de la Infancia y la Adolescencia — Ley 1098 de 2006, Resolución 1796 de 2018 sobre trabajos prohibidos, Ley 1780 de 2016 de empleo joven y Ley 1581 de 2012 de Habeas Data).

---

### 24. [`aa0f4bb`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/aa0f4bb0835204def05643f2eff86268ce1cc9a3) — `2026-10-26 08:45:03 -0500`
- **Mensaje**: `Merge pull request #45 from JuanManuelCM3331/feat/JMCM`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión de la configuración de contenedores Docker y guía de despliegue.

---

### 25. [`e033025`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/e0330258c45b01d2636ded88a3d840f938d89291) — `2026-10-26 08:40:49 -0500`
- **Mensaje**: `feat(docker): dockerizar aplicacion con PHP 8.2.35, MySQL 8.4, seeders y documentacion`
- **Autor**: `juanma3331`
- **Estadísticas**: `9 files changed, 432 insertions(+), 2 deletions(-)`
- **Archivos intervenidos**: `.dockerignore`, `.gitignore`, `Dockerfile`, `docker-compose.yml`, `docker/apache/vhost.conf`, `docker/mysql/init.sh`, `docker/php/php.ini`, `Documentacion/Guia_Docker.md`, `database/schema.sql`
- **Detalle técnico**: Empaquetado completo de la aplicación en contenedores con PHP 8.2 + Apache (`mod_rewrite`, `pdo_mysql`) y MySQL 8.4 con healthcheck, inicialización automática de esquema/migraciones/seeders y documentación en `Documentacion/Guia_Docker.md`.

---

## [v0.3.0] — 19 al 24 de octubre de 2026: Notificaciones Push, Escaneo de Documento (`IdScan`), Validación de Cédula, Chat e Historias de Usuario

### 26. [`cbf3881`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/cbf3881b92b1e0d4329c9b33266424a92c7493f2) — `2026-10-24 16:33:07 -0500`
- **Mensaje**: `Merge pull request #44 from JuanManuelCM3331/feat/JMCM`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión del sistema de notificaciones push en tiempo real.

---

### 27. [`61ed949`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/61ed9494a33811b9749f04b3dc89950b418522bf) — `2026-10-24 16:31:33 -0500`
- **Mensaje**: `feat(notifications): implementar sistema de notificaciones push en tiempo real y vinculacion de dispositivo`
- **Autor**: `juanma3331`
- **Estadísticas**: `15 files changed, 1630 insertions(+), 60 deletions(-)`
- **Archivos intervenidos**:
  - `Modules/Notifications/Controllers/NotificationsController.php`, `Modules/Notifications/Repositories/NotificationRepository.php`, `Modules/Notifications/Repositories/PushSubscriptionRepository.php`, `Modules/Notifications/Services/NotificationService.php`, `Modules/Notifications/Services/PushNotificationService.php`
  - `Modules/Chat/Services/ChatService.php`, `app/Http/Portal.php`, `app/Http/PortalViewRenderer.php`
  - `database/migrate_push_notifications.sql`, `database/schema.sql`
  - `public/assets/push-notifications.js`, `public/sw.js`, `sw.js`, `public/assets/icons/*`
- **Detalle técnico**: Implementación de la tabla `push_subscriptions`, Service Worker para recepción de eventos push en segundo plano, sondeo en tiempo real (`push_poll`) para actualizar contadores de alertas y mensajes sin recargar la página, y botón de prueba de notificaciones.

---

### 28. [`8019fc3`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/8019fc37c1a31e4aac1deb9838db40dee738832c) — `2026-10-24 13:30:08 -0500`
- **Mensaje**: `Merge pull request #43 from JuanManuelCM3331/feat/JMCM`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión de los ajustes de backend para el cumplimiento de Historias de Usuario.

---

### 29. [`3201a55`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/3201a55904d24e4fea2887b1fd73240dd4d867a6) — `2026-10-24 13:10:03 -0500`
- **Mensaje**: `fix(backend): cambios para el cumplimiento de las HU Pt.2`
- **Autor**: `juanma3331`
- **Estadísticas**: `8 files changed, 122 insertions(+), 9 deletions(-)`
- **Archivos intervenidos**: `Modules/Applications/Services/ApplicationService.php`, `Modules/Auth/Services/AuthService.php`, `Modules/Dashboard/*`, `app/Http/Portal.php`, `app/Http/PortalViewRenderer.php`, `add_status.php`
- **Detalle técnico**: Incorporación de estados de cuenta de usuario (`active` / `suspended`), gestión administrativa de estados y validaciones de negocio alineadas con las Historias de Usuario.

---

### 30. [`6412aab`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/6412aab836031707b40470cdcd36e53612e6cfcb) — `2026-10-23 08:38:10 -0500`
- **Mensaje**: `fix(backend): cambios para el cumplimiento de las HU`
- **Autor**: `juanma3331`
- **Estadísticas**: `6 files changed, 141 insertions(+), 3 deletions(-)`
- **Archivos intervenidos**: `Modules/Applications/Repositories/ApplicationRepository.php`, `Modules/Auth/Repositories/AuthRepository.php`, `Modules/Auth/Services/AuthService.php`, `Modules/Jobs/Repositories/JobManagementRepository.php`, `app/Core/Session.php`, `public/index.php`
- **Detalle técnico**: Creación de la clase `App\Core\Session` con expiración por inactividad, cookies `HttpOnly`/`SameSite=Lax`, regeneración de ID de sesión tras login y conteo de intentos fallidos recientes en `AuthRepository`.

---

### 31. [`0a1a47f`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/0a1a47f4bff9538ac347c944967a7f70c025add8) — `2026-10-23 06:58:39 -0500`
- **Mensaje**: `enhanced(test): eliminacion de las test (no son necesarias para el usuario)`
- **Autor**: `juanma3331`
- **Estadísticas**: `5 files changed, 7 insertions(+), 808 deletions(-)`
- **Archivos intervenidos**: `.gitignore`, `README.md`, eliminación de `tests/CedulaTest.php`, `tests/ChatTest.php`, `tests/IdScanTest.php`
- **Detalle técnico**: Limpieza de scripts de prueba local del árbol de despliegue principal, conservando la documentación de pruebas en `Documentacion/PruebasUnitTest.md` y `Documentacion/PLAN_PRUEBAS.md`.

---

### 32. [`0a28112`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/0a28112e71eed98bfeeed56dab9a116f186d3a71) — `2026-10-22 10:38:38 -0500`
- **Mensaje**: `feat(Test): implementacion de compose y UnitTest de php`
- **Autor**: `juanma3331`
- **Estadísticas**: `6 files changed, 117 insertions(+), 1 deletion(-)`
- **Archivos intervenidos**: `Documentacion/PruebasUnitTest.md`, `composer.json`, `routes/.gitkeep`, `storage/company-avatars/.gitkeep`, `storage/id-scans/.gitkeep`
- **Detalle técnico**: Adición de `composer.json` con autoload PSR-4 (`App\` y `Modules\`) y documentación de ejecución de pruebas unitarias en `Documentacion/PruebasUnitTest.md`.

---

### 33. [`0621081`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/06210810b5db4055eaf17bed61346d31cb106efc) — `2026-10-22 09:19:00 -0500`
- **Mensaje**: `Merge pull request #42 from JuanManuelCM3331/feat/JMCM`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión del sistema de registro con lectura óptica del documento de identidad (`IdScan`).

---

### 34. [`870b80b`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/870b80bc1d943513dbef3c99868e107ed505b2ee) — `2026-10-22 09:17:21 -0500`
- **Mensaje**: `refactor(auth): gran cambio en el modo de registro por cedula`
- **Autor**: `juanma3331`
- **Estadísticas**: `29 files changed, 2566 insertions(+), 135 deletions(-)`
- **Archivos intervenidos**:
  - `Modules/Identity/Controllers/IdScanController.php`, `Modules/Identity/Dto/IdScanConfig.php`, `Modules/Identity/Dto/ScannedId.php`, `Modules/Identity/Repositories/IdScanRepository.php`, `Modules/Identity/Services/IdScanService.php`, `Modules/Identity/Services/IdScanStorage.php`
  - `public/assets/id-reader/form.js`, `public/assets/id-reader/mrz.js`, `public/assets/id-reader/reader.js`
  - `config/id_scan.php`, `database/migrate_id_scan.sql`, `database/schema.sql`, `storage/id-scans/.htaccess`, `tests/IdScanTest.php`
  - `Modules/Auth/*`, `Modules/Users/*`, `Modules/Companies/*`, `app/Http/Portal.php`, `app/Http/PortalViewRenderer.php`
- **Detalle técnico**: Implementación del lector del reverso de la cédula o tarjeta de identidad mediante decodificación de código PDF417 y MRZ en el navegador, validación de rango de edad juvenil (16 a 28 años) en `IdScanService`, almacenamiento protegido de la imagen del documento (`IdScanStorage`) y descarga autenticada.

---

### 35. [`4ec8c59`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/4ec8c595aca727b84dbd48495f296974469be404) — `2026-10-21 10:40:00 -0500`
- **Mensaje**: `Merge pull request #41 from JuanManuelCM3331/feat/JMCM`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión del flujo de cambio de contraseña y mejoras de interfaz.

---

### 36. [`e68b83f`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/e68b83f469d83d933783603ad145308f9f1dcb8b) — `2026-10-21 10:38:46 -0500`
- **Mensaje**: `enhanced(frontend): eliminacion de botones duplicados, agregacion de cambio de contraseña y arreglo de bugs relacionados al frontend`
- **Autor**: `juanma3331`
- **Estadísticas**: `9 files changed, 670 insertions(+), 80 deletions(-)`
- **Archivos intervenidos**: `Modules/Auth/*`, `Modules/Dashboard/*`, `app/Http/Portal.php`, `app/Http/PortalViewRenderer.php`, `database/seed.sql`
- **Detalle técnico**: Adición de la acción `change_password` en `AuthService` y `Portal.php`, eliminación de botones redundantes en las vistas de perfil y mejoras en las acciones administrativas del panel.

---

### 37. [`f697abc`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/f697abc4e22aadd8b1bc739879eb01a95b7cf77b) — `2026-10-21 08:58:47 -0500`
- **Mensaje**: `Merge pull request #40 from JuanManuelCM3331/feat/JMCM`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión de la integración del servicio validador de cédula/NIT y mejoras en la documentación de Historias de Usuario.

---

### 38. [`c1c7e3c`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/c1c7e3cac64a043223247dec1731ee69492d67ce) — `2026-10-21 08:57:10 -0500`
- **Mensaje**: `feat(backend): integracion de api verificadora de cedula`
- **Autor**: `juanma3331`
- **Estadísticas**: `26 files changed, 1999 insertions(+), 74 deletions(-)`
- **Archivos intervenidos**:
  - `Modules/Identity/Clients/CurlHttpTransport.php`, `Modules/Identity/Contracts/*`, `Modules/Identity/Dto/*`, `Modules/Identity/Providers/HttpCedulaProvider.php`, `Modules/Identity/Providers/SandboxCedulaProvider.php`, `Modules/Identity/Services/CedulaProviderFactory.php`, `Modules/Identity/Services/CedulaValidationService.php`, `Modules/Identity/Support/CedulaResponseMapper.php`
  - `app/Core/Env.php`, `config/cedula.php`, `config/database.php`, `database/migrate_cedula.sql`, `tests/CedulaTest.php`, `.env.example`, `README.md`
- **Detalle técnico**: Creación de la arquitectura de validación de documentos (`CedulaProvider`) con soporte para proveedor HTTP externo (`HttpCedulaProvider`) y proveedor local de pruebas (`SandboxCedulaProvider`), cargador de variables de entorno `App\Core\Env` y migración `database/migrate_cedula.sql`.

---

### 39. [`645d88e`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/645d88e725e1e2a93852cf497adf1dba7e84f220) — `2026-10-21 06:45:57 -0500`
- **Mensaje**: `enhanced(docs): mejora en la documentacion de las HU`
- **Autor**: `juanma3331`
- **Estadísticas**: `4 files changed, 460 insertions(+), 214 deletions(-)`
- **Archivos intervenidos**: Renombrado `Documentacion/Arquitectura.md` -> `Documentacion/Arquitectura_Piloto.md`, ampliación de `Documentacion/HU.md`, `Documentacion/PLAN_PRUEBAS.md` y `Documentacion/RIESGOS.md`.
- **Detalle técnico**: Detalle de criterios de aceptación por cada Historia de Usuario, casos de prueba funcionales y matriz de riesgos del proyecto.

---

### 40. [`ce413bd`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/ce413bd4c651be2978e4e398920bd77303c29e47) — `2026-10-19 11:12:41 -0500`
- **Mensaje**: `Merge pull request #14 from JuanManuelCM3331/feat/JMCM`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión de la documentación inicial de Historias de Usuario, plan de pruebas y riesgos.

---

### 41. [`bf867e4`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/bf867e443b35f0b1929e1a6fa4ee7978e8ee502d) — `2026-10-19 11:08:51 -0500`
- **Mensaje**: `feat(docs): subida de las Historias de Usuario`
- **Autor**: `juanma3331`
- **Estadísticas**: `6 files changed, 561 insertions(+)`
- **Archivos intervenidos**: `Documentacion/HU.md`, `Documentacion/PLAN_PRUEBAS.md`, `Documentacion/RIESGOS.md`, `public/.gitkeep`, `storage/.gitkeep`, `tests/.gitkeep`
- **Detalle técnico**: Creación de los documentos formales de especificación de requisitos (`HU.md`), plan de pruebas (`PLAN_PRUEBAS.md`) y análisis de riesgos (`RIESGOS.md`).

---

### 42. [`38fa260`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/38fa2602932c3bed10f7c090d90d02a0c83797e4) — `2026-10-19 07:38:26 -0500`
- **Mensaje**: `Merge pull request #13 from JuanManuelCM3331/feat/JMCM`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión de la actualización de arquitectura en `README.md` y optimización de `.gitignore`.

---

### 43. [`bb2ea99`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/bb2ea9942e7dd6e141a00a36c8d8e606cb89f825) — `2026-10-19 07:35:41 -0500`
- **Mensaje**: `feat(docs): actualizacion del readme con arquitectura del sistema y optimizacion del gitignore`
- **Autor**: `juanma3331`
- **Estadísticas**: `2 files changed, 251 insertions(+), 398 deletions(-)`
- **Archivos intervenidos**: `.gitignore`, `README.md`
- **Detalle técnico**: Actualización de las instrucciones de instalación y estructura de módulos en `README.md` y exclusión de archivos temporales/subidas locales en `.gitignore`.

---

### 44. [`f9757b0`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/f9757b0bc66e3db5e5353513e20d016cc75abad7) — `2026-10-19 07:25:34 -0500`
- **Mensaje**: `Merge pull request #12 from JuanManuelCM3331/feat/JMCM`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión del módulo de mensajería interna (`Chat`) entre empresas y candidatos.

---

### 45. [`9c82396`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/9c82396e078171d750c6d009ef734376cd05c1a7) — `2026-10-19 07:24:05 -0500`
- **Mensaje**: `feat(chat): integracion de mensajeria entre empresas y candidatos con bloqueo por estado rechazado`
- **Autor**: `juanma3331`
- **Estadísticas**: `8 files changed, 1043 insertions(+), 18 deletions(-)`
- **Archivos intervenidos**: `Modules/Chat/Controllers/ChatController.php`, `Modules/Chat/Repositories/ChatRepository.php`, `Modules/Chat/Services/ChatService.php`, `app/Http/Portal.php`, `app/Http/PortalViewRenderer.php`, `database/migrate_chat.sql`, `database/schema.sql`, `tests/ChatTest.php`
- **Detalle técnico**: Creación de la tabla `messages`, módulo completo `Modules/Chat` (controlador, servicio y repositorio), bandeja de conversaciones asociadas a postulaciones, conteo de mensajes no leídos y bloqueo automático del envío cuando la postulación está en estado `rejected`.

---

## [v0.2.0] — 5 al 14 de octubre de 2026: Prototipo Funcional Base, Modularización en Capas y Panel Administrativo

### 46. [`882e235`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/882e235bfb6e3ba8e9074382c1594ed7b4d69fec) — `2026-10-14 15:28:41 -0500`
- **Mensaje**: `Merge pull request #11 from JuanManuelCM3331/feat/JMCM`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión de los filtros administrativos de auditoría y gestión de empresas.

---

### 47. [`2d8d032`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/2d8d032fe96b2956a1c9bf1ac8714855468c0d3e) — `2026-10-14 15:25:48 -0500`
- **Mensaje**: `feat(admin): agregacion de funciones como filtro en el panel de auditoria y empresas`
- **Autor**: `juanma3331`
- **Estadísticas**: `5 files changed, 2323 insertions(+), 85 deletions(-)`
- **Archivos intervenidos**: `Modules/Dashboard/Controllers/DashboardController.php`, `Modules/Dashboard/Repositories/DashboardRepository.php`, `Modules/Dashboard/Services/DashboardService.php`, `app/Http/Portal.php`, `app/Http/PortalViewRenderer.php`
- **Detalle técnico**: Ampliación del panel de administración con filtros avanzados en la bitácora `audit_logs` (por módulo, acción, estado y texto), gestión de usuarios/administradores y verificación de empresas.

---

### 48. [`4c6e325`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/4c6e3258cfcf4a3cb237e9aa22ed3b8aaa7beb1f) — `2026-10-14 09:05:52 -0500`
- **Mensaje**: `Merge pull request #10 from JuanManuelCM3331/feat/JMCM`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Sincronización de ramas (`feat/JMCM` y `main`).

---

### 49. [`d6c234c`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/d6c234c52fbe21e076e599bc38a5357e2451238d) — `2026-10-14 09:05:14 -0500`
- **Mensaje**: `Merge pull request #9 from JuanManuelCM3331/main`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión de sincronización desde `main`.

---

### 50. [`92031e1`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/92031e1586409915e904f42d3f15ab6ec0584a59) — `2026-10-13 20:33:31 -0500`
- **Mensaje**: `Merge pull request #8 from JuanManuelCM3331/feat/JMCM`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión de actualizaciones de documentación y mejoras en gestión de vacantes, notificaciones y perfiles.

---

### 51. [`39b1a96`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/39b1a96e03e2ca770cc8382a068549bb7021b4ac) — `2026-10-13 20:31:54 -0500`
- **Mensaje**: `update(docs): actualizacion del readme`
- **Autor**: `juanma3331`
- **Estadísticas**: `1 file changed, 4 insertions(+)`
- **Archivos intervenidos**: `README.md`
- **Detalle técnico**: Ajuste puntual en la documentación del archivo `README.md`.

---

### 52. [`c4fd5fe`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/c4fd5fe97ceb3dad7e6e835a71aacd1c9a3d0bac) — `2026-10-13 20:22:51 -0500`
- **Mensaje**: `Merge pull request #7 from JuanManuelCM3331/main`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Sincronización de cambios desde `main`.

---

### 53. [`1ba83c1`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/1ba83c17281c02656d2a2cf22919580cbaba7b2c) — `2026-10-13 20:14:46 -0500`
- **Mensaje**: `firx`
- **Autor**: `juanma3331`
- **Estadísticas**: `19 files changed, 543 insertions(+), 32 deletions(-)`
- **Archivos intervenidos**: `Modules/Applications/*`, `Modules/Auth/*`, `Modules/Companies/*`, `Modules/Jobs/*`, `Modules/Notifications/*`, `Modules/Users/*`, `app/Http/Portal.php`, `app/Http/PortalViewRenderer.php`, `database/schema.sql`, `.gitignore`
- **Detalle técnico**: Ampliación de métodos en repositorios y servicios para edición/cierre de vacantes, marcado de notificaciones como leídas, consulta de perfil público de candidatos postulados y registro de empresas.

---

### 54. [`8e17b72`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/8e17b7280d6b9b0e9471a427b9df38df93db8ce1) — `2026-10-13 20:10:12 -0500`
- **Mensaje**: `Merge pull request #6 from JuanManuelCM3331/feat/JMCM`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión de ajustes de almacenamiento de avatares corporativos.

---

### 55. [`13ae046`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/13ae0464db401063b401e40d1a94e2f7bc430f9c) — `2026-10-13 20:08:11 -0500`
- **Mensaje**: `fix(backend): correcion de errores varios`
- **Autor**: `juanma3331`
- **Estadísticas**: `2 files changed`
- **Archivos intervenidos**: `storage/company-avatars/.gitkeep` y archivo de imagen de prueba.
- **Detalle técnico**: Creación del directorio `storage/company-avatars/` para soportar el guardado de logos de empresa.

---

### 56. [`d24830b`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/d24830b29bdd7b0d75536b4a8f7f7c143b6c9b59) — `2026-10-07 21:14:26 -0500`
- **Mensaje**: `Merge pull request #5 from JuanManuelCM3331/revert-4-copilot/implement-frontend-interaction-layer`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión del Pull Request #5 que revierte la capa experimental AJAX introducida en el PR #4.

---

### 57. [`17ce304`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/17ce304793fb8cc39c9627a62a6d3e41522197c0) — `2026-10-07 21:14:11 -0500`
- **Mensaje**: `Revert "[WIP] Implement frontend interaction layer with AJAX support"`
- **Autor**: `Juan M. Cardona M.`
- **Estadísticas**: `25 files changed, 75 insertions(+), 1510 deletions(-)`
- **Archivos intervenidos**: Eliminación de `public/js/app.js`, `public/js/core/http.js`, `public/js/core/ui.js`, `public/js/modules/*` y restauración del flujo SSR en `app/Http/Portal.php` y `app/Http/PortalViewRenderer.php`.
- **Detalle técnico**: Reversión de la capa experimental AJAX para preservar el comportamiento síncrono y estable del renderizado desde servidor (SSR).

---

### 58. [`8fcc81d`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/8fcc81d864f5b92416aa61d960b17742ca80948f) — `2026-10-07 21:05:41 -0500`
- **Mensaje**: `Merge pull request #4 from JuanManuelCM3331/copilot/implement-frontend-interaction-layer`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión del Pull Request #4 con la propuesta experimental de interacción AJAX en el frontend.

---

### 59. [`ce690a1`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/ce690a11d87c227c1a93e5bf54286ebd924676cb) — `2026-10-08 02:04:42 +0000`
- **Mensaje**: `feat: add modular ajax frontend and json portal actions`
- **Autor**: `copilot-swe-agent[bot]` (Co-authored-by: `JuanManuelCM3331`)
- **Estadísticas**: `25 files changed, 1510 insertions(+), 75 deletions(-)`
- **Archivos intervenidos**: `public/js/app.js`, `public/js/core/http.js`, `public/js/core/ui.js`, `public/js/modules/admin-dashboard.js`, `public/js/modules/applications.js`, `public/js/modules/companies.js`, `public/js/modules/jobs.js`, `public/js/modules/search.js`, `app/Http/Portal.php`, `app/Http/PortalViewRenderer.php` y módulos de dominio.
- **Detalle técnico**: Implementación experimental de clientes JS modulares para consumir acciones del portal mediante respuestas JSON (posteriormente revertida en `17ce304`).

---

### 60. [`b9ea73c`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/b9ea73cc977664279829ce206fbe94cd2d5529cb) — `2026-10-08 01:57:08 +0000`
- **Mensaje**: `Initial plan`
- **Autor**: `copilot-swe-agent[bot]`
- **Detalle técnico**: Commit inicial de planificación para la rama `copilot/implement-frontend-interaction-layer` (PR #4).

---

### 61. [`9e4e04e`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/9e4e04e35cbf64d625b51c941fe710f25eb76314) — `2026-10-07 09:03:00 -0500`
- **Mensaje**: `Merge pull request #3 from JuanManuelCM3331/copilot/research-code-inspection`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión del Pull Request #3 con la extracción de `PortalViewRenderer` y endurecimiento inicial de `Portal.php`.

---

### 62. [`2c0a18a`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/2c0a18ae82eae981b693a6b6869232ddf3aee2e4) — `2026-10-07 14:00:09 +0000`
- **Mensaje**: `refactor: extract portal renderer and remove dead artifacts`
- **Autor**: `copilot-swe-agent[bot]` (Co-authored-by: `JuanManuelCM3331`)
- **Estadísticas**: `8 files changed, 239 insertions(+), 740 deletions(-)`
- **Archivos intervenidos**: Creación de `app/Http/PortalViewRenderer.php`, simplificación de `app/Http/Portal.php`, actualización de `README.md` y `Documentacion/Arquitectura.md`, y eliminación de `Modules/Landing/landingController.php`, `Modules/Jobs/Services/JobService.php` y `Modules/Users/Controllers/UsersController.php`.
- **Detalle técnico**: Separación de la capa de renderizado HTML en `PortalViewRenderer` y limpieza de clases preliminares que habían quedado sin referencias.

---

### 63. [`6db0667`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/6db06672a11ea32d7fe4bda1893cd6aeb60a6c8b) — `2026-10-07 13:53:08 +0000`
- **Mensaje**: `fix: harden portal actions and align docs/schema`
- **Autor**: `copilot-swe-agent[bot]` (Co-authored-by: `JuanManuelCM3331`)
- **Estadísticas**: `7 files changed, 83 insertions(+), 97 deletions(-)`
- **Archivos intervenidos**: `app/Http/Portal.php`, `Modules/Auth/Services/AuthService.php`, `database/migrate_company_audit.sql`, `database/schema.sql`, `database/seed.sql`, `Documentacion/Arquitectura.md`, `README.md`.
- **Detalle técnico**: Incorporación de protección CSRF en peticiones POST, validación de redirecciones internas (`safeReturn`) y alineación entre `schema.sql`, `seed.sql` y la documentación.

---

### 64. [`5b98768`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/5b987686e2d561257ea4494ae751992840403785) — `2026-10-07 08:38:35 -0500`
- **Mensaje**: `Merge pull request #2 from JuanManuelCM3331/feat/JMCM`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión del Pull Request #2 correspondiente a la segunda parte de la modularización del proyecto.

---

### 65. [`7bd5869`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/7bd586903d9195b0e11ae58587b3cbfe72295bb2) — `2026-10-07 08:32:59 -0500`
- **Mensaje**: `feat(all): modularizacion del proyecto parte 2`
- **Autor**: `juanma3331`
- **Estadísticas**: `17 files changed, 704 insertions(+), 125 deletions(-)`
- **Archivos intervenidos**: `Modules/Applications/*`, `Modules/Auth/*`, `Modules/Companies/*`, `Modules/Jobs/*`, `Modules/Search/*`, `Modules/Users/*`, `app/Http/Portal.php`
- **Detalle técnico**: Segunda fase de modularización creando `CompanyFileService` para la subida de logos de empresa, ampliando `UserProfileService` y `AuthService`, y delegando la lógica de negocio desde `Portal.php` hacia los servicios de cada módulo.

---

### 66. [`3160fb4`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/3160fb4ab9eaff48739a39d6f6f0776d70170dc4) — `2026-10-07 08:04:03 -0500`
- **Mensaje**: `Merge pull request #1 from JuanManuelCM3331/feat/JMCM`
- **Autor**: `Juan M. Cardona M.`
- **Detalle técnico**: Fusión del Pull Request #1 con el primer prototipo, vistas por rol y primera parte de la modularización.

---

### 67. [`93fd933`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/93fd9336367eb180c9fbf13922451f107cd4aecb) — `2026-10-07 08:02:50 -0500`
- **Mensaje**: `feat(app): modularizacion de la app parte 1`
- **Autor**: `juanma3331`
- **Estadísticas**: `4 files changed, 204 insertions(+), 60 deletions(-)`
- **Archivos intervenidos**: `Modules/Search/Repositories/JobInteractionRepository.php`, `Modules/Search/Services/JobInteractionService.php`, `Modules/Users/Services/UserFileService.php`, `app/Http/Portal.php`
- **Detalle técnico**: Primera fase de modularización extrayendo la gestión de vacantes guardadas/ocultas (`JobInteractionService`, `JobInteractionRepository`) y el servicio de archivos de usuario (`UserFileService` para fotos de perfil y hojas de vida en PDF).

---

### 68. [`be25ed5`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/be25ed5e50d9ee6a8648a0c0edca36800f490350) — `2026-10-06 17:04:40 -0500`
- **Mensaje**: `feat(all): agregacion de vistas, roles y diseño visual`
- **Autor**: `juanma3331`
- **Estadísticas**: `35 files changed, 861 insertions(+), 33 deletions(-)`
- **Archivos intervenidos**: Creación de controladores, servicios y repositorios iniciales en `Modules/Applications`, `Modules/Auth`, `Modules/Companies`, `Modules/Dashboard`, `Modules/Jobs`, `Modules/Notifications`, `Modules/Search`, `Modules/Users`, además de `app/Http/Portal.php`, `database/schema.sql` y `database/migrate_company_audit.sql`.
- **Detalle técnico**: Incorporación de los tres roles del sistema (`candidate`, `recruiter`, `admin`), vistas completas con Tailwind CSS, paneles de usuario/empresa/administrador y tablas de auditoría e interacciones.

---

### 69. [`0aeeb88`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/0aeeb88a6d9bf6c0773847fec906adbb39a74e73) — `2026-10-05 19:43:15 -0500`
- **Mensaje**: `feat(all): Subida del primer prototipo`
- **Autor**: `juanma3331`
- **Estadísticas**: `19 files changed, 577 insertions(+), 147 deletions(-)`
- **Archivos intervenidos**: `app/Core/Database.php`, `app/Http/Portal.php`, `config/database.php`, `database/schema.sql`, `database/seed.sql`, `database/migrate_company_audit.sql`, `index.php`, `public/index.php`, `Modules/Auth/Services/AuthService.php`, `Modules/Auditoria/Services/AuditService.php`, `Modules/Jobs/*`, `.env.example`, `.gitignore`, `README.md`.
- **Detalle técnico**: Subida del primer prototipo funcional conectado a MySQL mediante PDO (`App\Core\Database`), esquema relacional inicial (`database/schema.sql`), semillas de prueba (`database/seed.sql`) y controlador frontal `Portal.php`.

---

## [v0.1.0] — 13 al 26 de mayo de 2026: Fundación del Repositorio y Arquitectura Modular Inicial

### 70. [`1695bce`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/1695bce86d7cffe31cf85943932ca87f46ad091a) — `2026-05-26 09:48:08 -0500`
- **Mensaje**: `Update README.md`
- **Autor**: `Juan M. Cardona M.`
- **Estadísticas**: `1 file changed, 13 deletions(-)`
- **Archivos intervenidos**: `README.md`
- **Detalle técnico**: Limpieza de secciones redundantes en el `README.md` tras la refactorización de módulos.

---

### 71. [`e85e514`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/e85e51491f52d6aa015cd1359344fa614508433f) — `2026-05-26 09:37:34 -0500`
- **Mensaje**: `refactor(Modules): refactorizacion en los modulos que componen el sistema`
- **Autor**: `juanma3331`
- **Estadísticas**: `12 files changed, 610 insertions(+), 14 deletions(-)`
- **Archivos intervenidos**: `Documentacion/Arquitectura.md`, `Modules/Landing/landing.php`, `Modules/Auditoria/auditoria.php`, `Modules/CV/cv.php`, `Modules/Configuracion/configuracion.php`, `Modules/Dashboard/dashboard.php`, `Modules/Historial/historial.php`, `Modules/Login/login.php`, `Modules/Perfil/perfil.php`, `README.md`.
- **Detalle técnico**: Reorganización de los módulos funcionales preliminares e implementación de la primera maqueta de aterrizaje (`Modules/Landing/landing.php`).

---

### 72. [`36d10d9`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/36d10d95df985120926508c95d73d1a70d172292) — `2026-05-14 07:35:00 -0500`
- **Mensaje**: `Clarify module layers in README`
- **Autor**: `Juan M. Cardona M.`
- **Estadísticas**: `1 file changed, 3 insertions(+), 3 deletions(-)`
- **Archivos intervenidos**: `README.md`
- **Detalle técnico**: Precisión en la descripción de las responsabilidades de cada capa dentro de los módulos en `README.md`.

---

### 73. [`83a5fb3`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/83a5fb3dcbddc3afcabae2809cba74c6a3695860) — `2026-05-14 07:30:48 -0500`
- **Mensaje**: `Update README.md`
- **Autor**: `Juan M. Cardona M.`
- **Estadísticas**: `1 file changed, 1 insertion(+), 2 deletions(-)`
- **Archivos intervenidos**: `README.md`
- **Detalle técnico**: Corrección de formato en `README.md`.

---

### 74. [`8b59e2c`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/8b59e2c6c83828d6b33673b49a942ada13a39acc) — `2026-05-13 20:31:51 -0500`
- **Mensaje**: `Feat(README.md): agregacion de documentacion sobre la arquitectura`
- **Autor**: `juanma3331`
- **Estadísticas**: `2 files changed, 363 insertions(+)`
- **Archivos intervenidos**: Movimiento de `Arquitectura.md` a `Documentacion/Arquitectura.md` e incorporación de la documentación arquitectónica en `README.md`.
- **Detalle técnico**: Organización de la carpeta `Documentacion/` y publicación de la guía de arquitectura en la portada del repositorio.

---

### 75. [`980e387`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/980e3871f523d27dd7e7c3cced33f5cf33e764cc) — `2026-05-13 20:28:39 -0500`
- **Mensaje**: `cambios varios`
- **Autor**: `juanma3331`
- **Estadísticas**: `18 files changed, 1 insertion(+), 2 deletions(-)`
- **Archivos intervenidos**: Creación del esqueleto de directorios (`Modules/*`, `app/`, `bootstrap/`, `config/`, `database/`, `public/`, `resources/`, `routes/`, `storage/`, `tests/`) con archivos `.gitkeep`.
- **Detalle técnico**: Estructuración física del árbol de directorios del proyecto en Git.

---

### 76. [`05d91b6`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/05d91b64e768196730b4b66d9828ec1a6c3d09c1) — `2026-05-13 20:23:44 -0500`
- **Mensaje**: `feat: creacion del gitignore`
- **Autor**: `juanma3331`
- **Estadísticas**: `1 file changed, 3 insertions(+)`
- **Archivos intervenidos**: `.gitignore`
- **Detalle técnico**: Creación inicial del archivo `.gitignore` para excluir dependencias y archivos de entorno local.

---

### 77. [`dc3f897`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/dc3f897f256c643454227f9e453bf94ceec8924b) — `2026-05-13 20:19:14 -0500`
- **Mensaje**: `Feat(all): arquitectura del sistema`
- **Autor**: `juanma3331`
- **Estadísticas**: `1 file changed, 363 insertions(+)`
- **Archivos intervenidos**: `Arquitectura.md`
- **Detalle técnico**: Redacción del documento fundacional de arquitectura del sistema definiendo el enfoque de monolito modular en PHP.

---

### 78. [`1837db3`](https://github.com/JuanManuelCM3331/Youth-Employment-Bridge/commit/1837db3de8b3b90ab41da721d7de8d51f953e9ce) — `2026-05-13 20:18:31 -0500`
- **Mensaje**: `first commit`
- **Autor**: `juanma3331`
- **Estadísticas**: `1 file changed, 1 insertion(+)`
- **Archivos intervenidos**: `README.md`
- **Detalle técnico**: Commit inicial de creación del repositorio `Youth-Employment-Bridge`.
