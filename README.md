# Youth Employment Bridge (YEB)

Plataforma web de empleabilidad juvenil desarrollada bajo una **Arquitectura Monolítica Modular** en **PHP 8.2+** y **MySQL 8.0 / MariaDB 10.4+**.

## Autores
- **Juan Manuel Cardona Molina**
- **Maria Paula Herrera**

---

## Descripción del Proyecto

**Youth Employment Bridge (YEB)** conecta a jóvenes postulantes (entre **16 y 24 años**) con empresas reclutadoras y oportunidades de primer empleo, prácticas o vacantes junior. El sistema integra cuatro entornos especializados (**Candidato Adulto**, **Candidato Menor de Edad con Verificación Legal**, **Empresa Reclutadora** y **Panel Administrativo con Métricas y Auditoría**), garantizando trazabilidad, control de acceso por roles, protección de menores de edad y comunicación directa integrada en el flujo de selección.

### Alcance del Prototipo Funcional
Este repositorio constituye un **prototipo funcional completo y autocontenido** para ejecución local (XAMPP o Docker).
- **Incluido en el prototipo**: Autenticación y bloqueo por fuerza bruta, lectura asistida en navegador del código PDF417/MRZ del documento de identidad, flujo legal de autorización y bloqueo preventivo para menores de edad (16–17 años), buscador multicriterio con panel interactivo dividido, publicación y gestión de vacantes, ciclo de vida regulado de postulaciones, chat en tiempo real vinculado a la etapa de entrevista, notificaciones internas y Web Push (`Service Worker`), almacenamiento binario en base de datos (`LONGBLOB`), métricas graficadas y bitácora de auditoría.
- **Fuera del alcance del prototipo (servicios externos de terceros)**: Envío de correos transaccionales vía proveedores SMTP externos, recuperación de contraseña por correo electrónico y validación criptográfica directa contra bases de datos reservadas de la Registraduría Nacional.

---

## Puesta en Marcha

### Opción 1: Ejecución con XAMPP (Windows)

#### 1. Requisitos previos
- **PHP 8.2 o superior** con extensiones `pdo`, `pdo_mysql`, `mbstring`, `fileinfo` y `gd` / `curl`.
- **MySQL 8.0+ / MariaDB 10.4+** (incluido en XAMPP).
- **Apache 2.4+**.
- *(Recomendado)* En `C:\xampp\mysql\bin\my.ini` (sección `[mysqld]`), configurar `max_allowed_packet=64M` para permitir la carga de documentos y hojas de vida en columnas `LONGBLOB` de hasta 10 MB.

#### 2. Instalación de la Base de Datos
Inicia **Apache** y **MySQL** en el panel de XAMPP y ejecuta en PowerShell desde la raíz del proyecto (`C:\xampp\htdocs\Youth-Employment-Bridge`):

```powershell
# Instalación limpia desde cero (esquema íntegro + datos semilla de demostración)
Get-Content .\database\schema.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -u root
Get-Content .\database\seed.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -u root
```

> **Nota sobre migraciones incrementales**: `database/schema.sql` ya incluye toda la estructura DDL actualizada del proyecto. Si deseas actualizar una base de datos creada con una versión anterior sin perder datos, puedes ejecutar en orden:
> ```powershell
> Get-Content .\database\migrate_chat.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -u root
> Get-Content .\database\migrate_cedula.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -u root
> Get-Content .\database\migrate_id_scan.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -u root
> Get-Content .\database\migrate_push_notifications.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -u root
> Get-Content .\database\migrate_minor.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -u root
> Get-Content .\database\migrate_file_storage.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -u root
> ```

#### 3. Configuración de Variables de Entorno (`.env`)
El sistema funciona de inmediato con los valores por defecto de XAMPP. Si deseas personalizar la conexión, copia `.env.example` a `.env`:

```ini
YEB_DB_HOST=127.0.0.1
YEB_DB_PORT=3306
YEB_DB_DATABASE=yeb_portal
YEB_DB_USERNAME=root
YEB_DB_PASSWORD=
```

#### 4. Acceso al Portal
Abre en tu navegador:
```text
http://localhost/Youth-Employment-Bridge/
```

---

### Opción 2: Ejecución con Docker (`docker-compose`)

El proyecto incluye configuración completa para levantar la aplicación PHP/Apache, MySQL 8.0 y phpMyAdmin en contenedores:

```bash
docker compose up -d --build
```
- **Portal Web**: `http://localhost:8080`
- **phpMyAdmin**: `http://localhost:8081`

Al iniciar por primera vez, el contenedor de MySQL ejecuta automáticamente `database/schema.sql`, las migraciones incrementales y `database/seed.sql`.

---

## Cuentas Demo Incluidas (`database/seed.sql`)

Todas las cuentas de demostración utilizan la contraseña **`password`** (8 caracteres):

| Rol | Correo Electrónico | Contraseña | Propósito de Prueba |
| :--- | :--- | :--- | :--- |
| **Candidato (Adulto)** | `ana@yeb.test` | `password` | Joven postulante con perfil profesional, búsqueda de empleo, postulaciones y chat. |
| **Candidato (Menor de Edad)** | `menor@yeb.test` | `password` | Aspirante de 17 años (`is_minor = 1`, `minor_blocked = 1`) con vista de bloqueo legal preventivo y formulario de carga de los 3 documentos obligatorios. |
| **Empresa / Reclutador** | `laura@talentolab.test` | `password` | Cuenta asociada a *TalentoLab S.A.S.* con vacantes activas, recepción de hojas de vida, gestión de estados y chat corporativo. |
| **Administrador** | `admin@yeb.test` | `password` | Control total del portal, métricas graficadas, bitácora de auditoría, moderación de cuentas y autorización/desbloqueo de menores de edad. |

---

## Arquitectura del Sistema

El proyecto implementa una arquitectura **Monolítica Modular** donde cada dominio de negocio reside en `Modules/<Modulo>` y separa sus responsabilidades en cuatro capas:

```text
[ Petición HTTP ] ──> public/index.php ──> Session::start() ──> Portal::run()
                                                                      │
       ┌──────────────────────────────────────────────────────────────┘
       ▼
┌──────────────┐     ┌──────────────┐     ┌──────────────┐     ┌──────────────┐
│  Controller  │ ──> │   Service    │ ──> │  Repository  │ ──> │  MySQL (PDO) │
└──────────────┘     └──────────────┘     └──────────────┘     └──────────────┘
       │
       ▼
┌──────────────┐
│   UI View    │ ──> Renderizado HTML/CSS modular heredando de PortalViewRenderer
└──────────────┘
```

### Estructura Real del Proyecto

```text
Youth-Employment-Bridge/
├── app/
│   ├── Core/
│   │   ├── Database.php                      # Singleton de conexión PDO (prepared statements reales)
│   │   ├── Env.php                           # Lector de variables de entorno (.env)
│   │   └── Session.php                       # Inicialización de sesión con cookies HttpOnly, SameSite=Lax y Secure
│   └── Http/
│       ├── Portal.php                        # Front Controller central, guardias de seguridad, CSRF y cabeceras HTTP
│       └── PortalViewRenderer.php            # Layout base, sistema de estilos modulares y vistas públicas
├── config/
│   ├── cedula.php                            # Configuración del validador de formato/proveedor de documento
│   ├── database.php                          # Credenciales PDO parametrizadas por entorno
│   └── id_scan.php                           # Reglas del lector asistido de documentos (rango de edad, umbral de nombre)
├── database/
│   ├── schema.sql                            # Esquema DDL íntegro (12 tablas, claves foráneas y columnas LONGBLOB)
│   ├── seed.sql                              # Datos semilla para demostración (empresas, usuarios, vacantes, postulaciones)
│   ├── migrate_company_audit.sql             # Migración de perfil de empresa, auditoría y FK en users.company_id
│   ├── migrate_chat.sql                      # Migración de la tabla messages
│   ├── migrate_cedula.sql                    # Migración de documento e índice único (cedula, role)
│   ├── migrate_id_scan.sql                   # Migración de metadatos de lectura del documento
│   ├── migrate_push_notifications.sql        # Migración de la tabla push_subscriptions
│   ├── migrate_minor.sql                     # Migración de status, banderas de menor y tabla user_minor_documents
│   ├── migrate_file_storage.sql              # Migración de almacenamiento binario LONGBLOB en BD
│   └── backfill_file_storage.php             # Script CLI para migrar archivos previos en disco hacia LONGBLOB
├── Documentacion/
│   ├── Arquitectura_Piloto.md                # Documento de diseño arquitectónico
│   ├── Guia_Docker.md                        # Guía detallada de despliegue en contenedores
│   ├── HU.md                                 # Historias de usuario y criterios de aceptación
│   ├── PLAN_PRUEBAS.md                       # Plan de pruebas funcionales y de seguridad
│   ├── PruebasUnitTest.md                    # Documentación de casos de prueba
│   └── RIESGOS.md                            # Matriz de riesgos y registro de defectos cerrados
├── Modules/
│   ├── Applications/                         # Postulaciones y máquina de estados (pending -> review -> interview -> accepted/rejected)
│   ├── Auditoria/                            # Registro de eventos de negocio y seguridad en audit_logs
│   ├── Auth/                                 # Login, bloqueo por fuerza bruta, registro y cambio de contraseña
│   ├── Chat/                                 # Mensajería bidireccional reclutador-candidato por postulación
│   ├── Companies/                            # Perfil corporativo, logo en LONGBLOB y vista pública para postulantes
│   ├── Dashboard/                            # Panel de administración, métricas graficadas (Chart.js) y moderación
│   ├── Identity/                             # Lectura asistida PDF417/MRZ y gestión de documentos legales de menores
│   ├── Jobs/                                 # Creación, edición, validación salarial y cierre de vacantes
│   ├── Notifications/                        # Alertas internas del portal y suscripciones Web Push
│   ├── Search/                               # Buscador multicriterio, favoritos (saved_jobs) y ocultos (hidden_jobs)
│   └── Users/                                # Perfil profesional del candidato, carga/descarga de CV y foto de perfil
├── public/
│   ├── assets/
│   │   ├── id-reader/                        # Lector en navegador de PDF417 (ZXing) y MRZ (Tesseract OCR)
│   │   ├── icons/                            # Iconografía para notificaciones push
│   │   └── push-notifications.js             # Cliente JS de alertas en tiempo real y Service Worker
│   ├── index.php                             # Punto de entrada HTTP y autoloader PSR-4
│   └── sw.js                                 # Service Worker para notificaciones nativas
├── docker-compose.yml                        # Orquestación de contenedores (app, db, phpmyadmin)
├── Dockerfile                                # Imagen PHP 8.2 Apache con extensiones requeridas
├── index.php                                 # Punto de entrada raíz hacia public/index.php
└── sw.js                                     # Service Worker en raíz para compatibilidad con rutas XAMPP
```

---

## Módulos y Reglas de Negocio Implementadas

### 1. Autenticación y Protección contra Fuerza Bruta (`Modules/Auth`)
- **Contraseñas seguras**: Longitud mínima obligatoria de **8 caracteres** (`MIN_PASSWORD_LENGTH = 8`), almacenadas con `password_hash(..., PASSWORD_DEFAULT)`.
- **Bloqueo temporal por intentos fallidos**: Tras **5 intentos fallidos consecutivos** (`MAX_LOGIN_ATTEMPTS = 5`) en una ventana de **15 minutos** (`LOGIN_LOCK_MINUTES = 15`), la cuenta queda bloqueada temporalmente tanto en el inicio de sesión como en el cambio de contraseña, informando al usuario los minutos restantes de espera.
- **Registro transaccional de empresas**: La creación de la empresa y su usuario reclutador se ejecuta dentro de una transacción PDO (`beginTransaction` / `commit` / `rollBack`) para garantizar atomicidad.

### 2. Verificación Asistida de Identidad y Protección de Menores (`Modules/Identity`)
- **Verificación asistida en navegador (PDF417 / MRZ)**: Durante el registro de candidatos, el navegador decodifica de forma asistida el código de barras PDF417 (o la zona MRZ inferior) del reverso de la cédula o tarjeta de identidad para autocompletar los datos y verificar que la edad esté en el rango juvenil de la plataforma.
  - *Alcance técnico*: Dado que la lectura óptica ocurre en el navegador y no existe un servicio público de firma criptográfica de la Registraduría, este mecanismo opera como **verificación asistida de captura**, y el servidor calcula la condición de menor de edad directamente desde la fecha extraída del documento (`$idScan['birth_date']`).
- **Bloqueo preventivo para menores de edad (16 y 17 años)**:
  - Si el aspirante tiene entre 16 y 17 años, la cuenta se crea con `is_minor = 1` y `minor_blocked = 1`.
  - Un guardia central al inicio de `Portal::run()` impide que un menor con bloqueo activo ejecute cualquier acción `POST` o `GET` distinta de cargar sus documentos legales o cerrar sesión.
  - Para solicitar el desbloqueo, el menor debe radicar 3 documentos obligatorios (**Registro Civil de Nacimiento**, **Cédula del Padre/Madre o Representante Legal** y **Carta de Autorización Firmada**), los cuales son revisados y aprobados por el **Administrador** en la pestaña *Menores de Edad*.

### 3. Buscador Multicriterio y Gestión de Vacantes (`Modules/Jobs` y `Modules/Search`)
- **Filtros combinados**: Búsqueda por título/palabra clave, ciudad, modalidad (`Remoto`, `Híbrido`, `Presencial`), nivel de experiencia, salario mínimo, salario máximo y nombre de empresa.
- **Navegación de panel dividido**: Lista de vacantes a la izquierda y panel de detalle fijo con desplazamiento vertical independiente a la derecha.
- **Validación de ofertas**: Al crear o editar una vacante se valida que todos los campos estén completos y que `salary_min` y `salary_max` sean valores numéricos no negativos con `salary_min <= salary_max`.
- **Visibilidad controlada**: Solo las vacantes con estado `published` son visibles públicamente en el buscador y en la vista `job_detail`, y solo a ellas se permite postular.

### 4. Ciclo de Vida de Postulaciones y Chat (`Modules/Applications` y `Modules/Chat`)
- **Máquina de estados regulada (`ALLOWED_TRANSITIONS`)**:
  - `pending` → puede avanzar a `review`, `interview`, `accepted` o `rejected`.
  - `review` → puede avanzar a `interview`, `accepted` o `rejected`.
  - `interview` → puede finalizar en `accepted` o `rejected`.
  - `accepted` y `rejected` → son **estados definitivos** (no permiten retrocesos ni cambios posteriores).
- **Verificación estricta de propiedad (`company_id`)**: Una empresa solo puede actualizar postulaciones, editar/cerrar vacantes o descargar hojas de vida de candidatos postulados a sus propias ofertas.
- **Chat vinculado a entrevista**: El canal de mensajería bidireccional se habilita cuando la postulación pasa a `interview` y se cierra automáticamente en modo solo lectura cuando la postulación finaliza en `accepted` o `rejected`.

### 5. Notificaciones en Tiempo Real (`Modules/Notifications`)
- **Alertas automáticas**: Se generan al postularse a una vacante (para el candidato y los reclutadores de la empresa), al cambiar el estado de una postulación y al recibir nuevos mensajes de chat.
- **Seguridad en endpoints Push**: El `device_token` es generado exclusivamente por el servidor con `random_bytes(32)` y vinculado a la sesión autenticada. Las rutas `push_subscribe`, `push_unsubscribe`, `push_poll` y `push_test` exigen sesión activa, y `push_test` opera únicamente por `POST` con validación CSRF.

### 6. Panel Administrativo, Métricas y Auditoría (`Modules/Dashboard` y `Modules/Auditoria`)
- **Métricas graficadas**: Panel visual accesible desde el encabezado superior del administrador con gráficos interactivos de crecimiento de usuarios, embudo de conversión de postulaciones, distribución por ciudades/modalidades y tiempos de respuesta.
- **Moderación y estado de cuentas**: Suspensión en caliente (`status = 'suspended'`, que revoca de inmediato cualquier sesión activa del usuario en su siguiente petición), reactivación y eliminación de usuarios, empresas, vacantes y postulaciones.
- **Protección de integridad administrativa**: El sistema impide que un administrador elimine o suspenda su propia cuenta, y bloquea la eliminación o suspensión cuando solo queda un administrador activo en el sistema (`countActiveAdmins() <= 1`).

---

## Modelo de Base de Datos (`database/schema.sql`)

| Tabla | Descripción |
| :--- | :--- |
| `companies` | Empresas registradas con perfil institucional y logo almacenado en `LONGBLOB`. |
| `users` | Candidatos, reclutadores y administradores (`status`, `is_minor`, `minor_blocked`, `birth_date`, CV y foto en `LONGBLOB`, FK `ON DELETE CASCADE` hacia `companies`). |
| `user_minor_documents` | Soportes legales obligatorios de aspirantes menores de edad almacenados en `LONGBLOB` con trazabilidad de aprobación administrativa. |
| `jobs` | Vacantes laborales publicadas, en borrador o cerradas asociadas a cada empresa. |
| `applications` | Postulaciones únicas por candidato y vacante (`UNIQUE(job_id, user_id)`). |
| `messages` | Historial de mensajes del chat por postulación con marca de lectura (`read_at`). |
| `notifications` | Alertas internas dirigidas a cada usuario. |
| `push_subscriptions` | Suscripciones de navegador vinculadas por `user_id` y `device_token` generado en servidor. |
| `saved_jobs` / `hidden_jobs` | Vacantes marcadas como favoritas u ocultadas por cada candidato. |
| `audit_logs` | Bitácora de auditoría con acción, módulo, ruta, IP, User-Agent y payload JSON (`ON DELETE SET NULL` en `user_id` para preservar el historial). |
| `skills` / `cities` | Catálogos auxiliares de habilidades y ciudades. |

---

## Controles de Seguridad Implementados

- **Cabeceras HTTP de Seguridad**: Emisión centralizada en `Portal::sendSecurityHeaders()` de `Content-Security-Policy`, `X-Frame-Options: DENY`, `X-Content-Type-Options: nosniff`, `Referrer-Policy: strict-origin-when-cross-origin` y `Permissions-Policy`.
- **Protección contra CSRF**: Token por sesión (`$_SESSION['csrf']`) verificado con `hash_equals()` en todas las peticiones `POST` (formularios HTML y peticiones `fetch` JSON).
- **Consultas Preparadas PDO**: `PDO::ATTR_EMULATE_PREPARES => false` y `PDO::ERRMODE_EXCEPTION` en `Database::connection()`.
- **Validación de Archivos por Contenido (`finfo`)**: Los archivos subidos (CV, fotos de perfil, reverso de documento y soportes de menores) se validan inspeccionando su MIME real con `finfo(FILEINFO_MIME_TYPE)`, nunca confiando en `$_FILES['...']['type']`, y se sirven con nombres sanitizados y `X-Content-Type-Options: nosniff`.
- **Manejo Seguro de Excepciones**: `Portal::resolveUserErrorMessage()` oculta cualquier `PDOException` o fallo interno mostrando un mensaje genérico al usuario mientras registra el detalle técnico en el log del servidor (`error_log`).

---

## Declaración de Uso de Herramientas de Inteligencia Artificial

Este proyecto fue desarrollado con el apoyo y asistencia técnica de herramientas de Inteligencia Artificial generativa (**Google Gemini** y **Anthropic Claude**), empleadas como soporte en tareas de análisis arquitectónico, refactorización modular, revisión de seguridad, optimización de interfaz de usuario y documentación técnica, bajo la dirección, supervisión y validación de su autor.

---

## Licencia

Este proyecto es software libre y se distribuye bajo la licencia **GNU General Public License v3.0 (GPL-3.0-or-later)** a nombre del **Equipo Youth Employment Bridge (YEB)** (*Juan Manuel Cardona Molina* y *Maria Paula Herrera*).

- **Permitido**: Usar, estudiar, compartir y modificar el código fuente.
- **Condiciones (Copyleft)**: Reconocer la autoría original del equipo YEB, documentar los cambios realizados y distribuir cualquier modificación u obra derivada bajo la misma licencia abierta **GNU GPL v3**.
- **Sin garantía**: El software se entrega «tal cual» (*as-is*), sin garantías de ningún tipo.

Consulta el archivo bilingüe [`LICENSE`](./LICENSE) para revisar el texto completo en español e inglés.

