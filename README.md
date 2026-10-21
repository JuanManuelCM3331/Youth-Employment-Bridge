# Youth-Employment-Bridge (YEB)

Portal de empleo juvenil desarrollado con arquitectura monolítica modular en PHP 8.2 y MySQL.

## Autores
- **Juan Manuel Cardona Molina**
- **Maria Paula Herrera**

---

## Descripción del Proyecto

**Youth Employment Bridge (YEB)** es una plataforma web orientada a conectar a jóvenes postulantes con oportunidades laborales y empresas verificadas. El sistema cuenta con paneles dedicados para candidatos, reclutadores corporativos y administradores, un buscador inteligente de vacantes, auditoría integral y un sistema de mensajería/chat directo entre empresas y postulantes vinculado al ciclo de vida de cada postulación.

---

## Puesta en Marcha con XAMPP

### Requisitos previos
- **PHP 8.2** o superior con extensiones `pdo`, `pdo_mysql`, `mbstring`.
- **MySQL / MariaDB** (incluido en XAMPP).
- **Servidor Apache** (o servidor web embebido de PHP).

### Pasos de instalación

1. Inicia los servicios de **Apache** y **MySQL** desde el Panel de Control de XAMPP.
2. Clona o copia el repositorio en la carpeta `C:\xampp\htdocs\Youth-Employment-Bridge`.
3. Importa los esquemas de base de datos en MySQL:
   - `database/schema.sql`: Crea la base de datos `yeb_portal` y todas las tablas principales (`users`, `companies`, `jobs`, `applications`, `messages`, `notifications`, etc.).
   - `database/seed.sql`: Inserta los datos demo de prueba (usuarios, empresas y vacantes).
   - *(Opcional)* Si tenías una versión previa de la base de datos, ejecuta también `database/migrate_company_audit.sql`, `database/migrate_chat.sql` y `database/migrate_cedula.sql`.
4. Configura el archivo `.env`:
   - Copia `.env.example` a `.env` si requieres personalizar credenciales.
   ```ini
   YEB_DB_HOST=127.0.0.1
   YEB_DB_PORT=3306
   YEB_DB_DATABASE=yeb_portal
   YEB_DB_USERNAME=root
   YEB_DB_PASSWORD=
   ```
5. Abre en tu navegador web:
   ```
   http://localhost/Youth-Employment-Bridge/
   ```

### Carga rápida desde PowerShell

```powershell
Get-Content .\database\schema.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -u root
Get-Content .\database\migrate_chat.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -u root
Get-Content .\database\migrate_cedula.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -u root
Get-Content .\database\seed.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -u root
```

### Cuentas Demo

| Rol | Correo | Contraseña | Descripción |
| :--- | :--- | :--- | :--- |
| **Candidato** | `ana@yeb.test` | `password` | Perfil de postulante con CV cargado y aplicaciones activas. |
| **Reclutador** | `laura@talentolab.test` | `password` | Empresa verificada con vacantes y postulantes recibidos. |
| **Administrador** | `admin@yeb.test` | `password` | Panel global, métricas del portal y registros de auditoría. |

---

## Arquitectura de Software

El sistema sigue una arquitectura **Monolítica Modular** con separación de responsabilidades en tres capas fundamentales por módulo:

```
[ HTTP Request (Portal.php) ]
              │
              ▼
    ┌───────────────────┐
    │    Controller     │   ──> Recibe peticiones, valida entrada y coordina el flujo.
    └─────────┬─────────┘
              │
              ▼
    ┌───────────────────┐
    │     Service       │   ──> Reglas y lógica de negocio, validaciones y permisos.
    └─────────┬─────────┘
              │
              ▼
    ┌───────────────────┐
    │    Repository     │   ──> Capa de persistencia, consultas PDO / SQL y transacciones.
    └─────────┬─────────┘
              │
              ▼
     [ MySQL Database ]
```

### Estructura de Directorios

```text
Youth-Employment-Bridge/
├── app/
│   ├── Core/
│   │   ├── Database.php             # Conexión PDO Singleton
│   │   └── Env.php                  # Carga centralizada de variables de entorno (.env)
│   └── Http/
│       ├── Portal.php               # Front controller y orquestador de rutas
│       └── PortalViewRenderer.php   # Renderizador de vistas, componentes y layout UI
├── config/
│   ├── cedula.php                   # Parámetros de la API de validación de cédulas
│   └── database.php                 # Parámetros de conexión a BD y carga de .env
├── database/
│   ├── schema.sql                   # Estructura DDL completa de la base de datos
│   ├── seed.sql                     # Datos semilla para pruebas
│   ├── migrate_company_audit.sql    # Migración de auditoría y empresas
│   ├── migrate_chat.sql             # Migración para la tabla de mensajería (messages)
│   └── migrate_cedula.sql           # Migración de la columna users.cedula y su índice único
├── Modules/
│   ├── Applications/                # Postulaciones y gestión de estados
│   ├── Auditoria/                   # Bitácora de eventos del sistema y seguridad
│   ├── Auth/                        # Autenticación, registro y control de sesiones
│   ├── Chat/                        # Mensajería bidireccional candidatos-empresas
│   ├── Companies/                   # Perfiles corporativos y logos
│   ├── Dashboard/                   # Métricas, reportes y gestión administrativa
│   ├── Identity/                    # Validación de cédulas contra el registro de identidad
│   ├── Jobs/                        # Gestión, edición y publicación de vacantes
│   ├── Notifications/               # Notificaciones internas y alertas
│   ├── Search/                      # Buscador de empleo con filtros y favoritos
│   └── Users/                       # Hojas de vida (CV), perfiles y fotos
├── public/
│   ├── index.php                    # Punto de entrada HTTP y PSR-4 autoloader
│   └── uploads/                     # Almacenamiento seguro de archivos
├── tests/
│   ├── CedulaTest.php               # Suite de pruebas de la validación de cédulas
│   └── ChatTest.php                 # Suite de pruebas automatizadas del chat
├── index.php                        # Redirección raíz a public/index.php
└── README.md
```

---

## Módulos del Sistema y Funcionalidades

### 1. Chat & Mensajería (`Modules/Chat`)
- **Comunicación directa**: Canal privado bidireccional entre postulantes y reclutadores para cada vacante a la que se han postulado.
- **Regla de negocio estricta (Bloqueo por Rechazo)**: Si el estado de la postulación pasa a `rejected` (Rechazado), el sistema bloquea inmediatamente el canal tanto en la interfaz de usuario como a nivel de servicio (`DomainException`). No se permite el envío de nuevos mensajes.
- **Bandeja de entrada**:
  - Candidatos: Pestaña "Mensajes" con historial de chats, preview del último mensaje y contador de no leídos.
  - Empresas: Pestaña corporativa "Mensajes" y acceso directo desde el listado de candidatos.
- **Notificaciones automáticas**: Al recibir un mensaje nuevo, la contraparte recibe una notificación en el portal.
- **Confirmación de lectura**: Marca de mensajes leídos con timestamp (`read_at`).

### 2. Autenticación & Autorización (`Modules/Auth`)
- Registro de candidatos y registro directo de empresas con sus datos institucionales.
- **Cédula obligatoria**: el registro exige número de cédula y fecha de nacimiento, validados contra el registro de identidad (ver [Validación de Cédulas](#validación-de-cédulas-modulesidentity)).
- Inicio de sesión con contraseñas encriptadas mediante `password_hash()` (Bcrypt).
- Control de acceso por roles: `candidate`, `recruiter`, `admin`.
- Protección contra ataques CSRF mediante tokens criptográficos aleatorios por sesión.

### 3. Postulaciones (`Modules/Applications`)
- Postulación única por vacante y candidato.
- Ciclo de estados:
  - `pending` (Pendiente)
  - `review` (En revisión)
  - `interview` (Entrevista)
  - `accepted` (Aceptado)
  - `rejected` (Rechazado)
- Notificaciones de avance de estado al postulante.

### 4. Vacantes Laborales (`Modules/Jobs` & `Modules/Search`)
- Publicación, edición y cierre de vacantes por parte de las empresas.
- Parámetros detallados: modalidad (`Presencial`, `Híbrido`, `Remoto`), experiencia (`Inicial`, `Junior`, etc.), rango salarial mínimo/máximo y ciudad.
- Buscador con filtros combinados por palabra clave, modalidad, salario y ubicación.
- Sistema de vacantes favoritas (`saved_jobs`) y vacantes ocultadas (`hidden_jobs`).

### 5. Candidatos y Hojas de Vida (`Modules/Users`)
- Perfil profesional completo: titular, habilidades, bio, educación, experiencia y redes (LinkedIn, portafolio).
- Carga segura de Hoja de Vida (CV) en formatos PDF, DOC y DOCX (máximo 5 MB), almacenados fuera del acceso público directo.
- Descarga controlada: Las empresas solo pueden descargar el CV de aquellos candidatos que se han postulado a sus ofertas activas.

### 6. Auditoría (`Modules/Auditoria`)
- Registro centralizado de eventos sensibles:
  - Inicios y cierres de sesión.
  - Envío de postulaciones y cambios de estado.
  - Creación y edición de vacantes.
  - Carga, descarga y eliminación de hojas de vida.
  - Envío de mensajes en el chat.
- Almacenamiento de: ID de usuario, acción, módulo, ruta solicitada, estado (`success`, `failed`, `error`), dirección IP, User-Agent y payload en JSON.
- Panel administrativo con filtrado por módulo, acción, fecha y estado.

### 7. Panel Administrativo (`Modules/Dashboard`)
- Métricas consolidadas: total de vacantes publicadas, usuarios registrados, empresas activas y postulaciones.
- Gestión de usuarios y empresas (eliminación segura con eliminación en cascada).
- Creación de nuevos usuarios con rol administrador.

---

## Validación de Cédulas (`Modules/Identity`)

El registro del portal **exige cédula** y la valida contra un registro de identidad antes de crear la cuenta. El módulo está preparado como un *adaptador de proveedores*: el flujo de negocio (orden de validaciones, reglas de duplicidad, auditoría) es siempre el mismo y solo cambia quién responde la consulta.

### Orden de validación (aplicado en `CedulaValidationService::validate`)

| # | Paso | Resultado si falla |
| :--- | :--- | :--- |
| 1 | Formato del documento (6 a 10 dígitos, se aceptan puntos, guiones y prefijos `CC`/`CE`/`TI`) | "La cédula debe tener entre 6 y 10 dígitos." |
| 2 | **Edad máxima (24 años)** calculada con la fecha de nacimiento capturada | "Esta plataforma es para jóvenes de hasta 24 años..." |
| 3 | Consulta a la API de cédulas (existencia en el registro) | "La cédula no se encuentra registrada en el registro civil..." |
| 4 | Coincidencia del **nombre** capturado con el registrado | "El nombre capturado no coincide con el registrado en tu cédula..." |
| 5 | Coincidencia de la **fecha de nacimiento** con la registrada | "La fecha de nacimiento no coincide con la registrada en tu cédula." |

El paso 2 se ejecuta **antes** de la consulta a la API, tal como exige el proceso: primero se descarta que la persona sea mayor de 24 años y solo después se verifica contra el registro civil.

### Reglas de duplicidad

- Una cédula **no puede repetirse dentro del mismo tipo de cuenta** (`candidate` o `recruiter`).
- Una misma cédula **sí puede** tener una cuenta de candidato y una cuenta de empresa (por ejemplo, unoung young developer que también representa a su empresa).
- Se fuerza en dos niveles: consulta previa en `AuthRepository::findByCedulaAndRole()` y restricción de base de datos `UNIQUE KEY unique_users_cedula_role (cedula, role)`.

### Proveedores disponibles

| Proveedor | Costo | Cuándo usarlo |
| :--- | :--- | :--- |
| `sandbox` | **Gratis**, sin credenciales | Por defecto. Simulador local con un registro ficticio de prueba. Ideal para desarrollar y demostrar el flujo sin depender de terceros. |
| `http` | **Gratis** en el plan gratuito del proveedor | Cuando quieras validar cédulas reales: se conecta a la API que elijas (RapidAPI, Didit, PlacApi o un endpoint propio) por HTTP. |

### Modo simulación (valor por defecto)

Con `YEB_CEDULA_PROVIDER=sandbox` el portal valida contra este registro **ficticio** (datos de prueba, no personas reales):

| Cédula | Nombre registrado | Fecha de nacimiento | Resultado |
| :--- | :--- | :--- | :--- |
| `1010203040` | ANA MARIA GOMEZ LOPEZ | 2004-03-12 | Válida (22 años) |
| `1020304050` | JUAN CARLOS PEREZ GOMEZ | 2001-07-25 | Mayor de 24 años |
| `1030405060` | LAURA SOFIA MARTINEZ CRUZ | 2003-11-08 | Válida (22 años) |
| `1040506070` | DAVID ANDRES RAMIREZ ORTIZ | 2005-05-19 | Válida (21 años) |
| `1050607080` | MARIA FERNANDA LOPEZ SANCHEZ | 1999-02-14 | Mayor de 24 años |
| `1060708090` | CARLOS ANDRES RUIZ MARTINEZ | 1985-06-20 | Mayor de 24 años |

Cualquier otra cédula responde "no encontrada" y la interfaz muestra el aviso de **modo simulación** con esta misma tabla. Para registrarte con una cédula real debes conectar una API (siguiente sección).

### Conectar una API real y gratuita

No existe un servicio público, gratuito y sin clave que confirme la existencia de una cédula colombiana contra el registro civil. Las opciones gratuitas reales son las que ofrecen **plan gratuito con cuota mensual** (RapidAPI: 100 consultas/mes; Didit: 500 verificaciones/mes). El portal ya trae el cliente HTTP listo para consumirlas: solo hay que configurar el `.env`.

```ini
# .env  →  cambiar el proveedor a http
YEB_CEDULA_PROVIDER=http
```

**Ejemplo con RapidAPI** (plan gratuito, sin tarjeta de crédito):

```ini
YEB_CEDULA_PROVIDER=http
YEB_CEDULA_API_URL=https://{host}/v1/cedula/{cedula}
YEB_CEDULA_API_METHOD=GET
YEB_CEDULA_API_KEY=tu_clave_de_rapidapi
YEB_CEDULA_API_KEY_HEADER=X-RapidAPI-Key
YEB_CEDULA_API_HOST=cedulas-colombia.p.rapidapi.com
```

**Ejemplo con un endpoint propio o cualquier proveedor**:

```ini
YEB_CEDULA_PROVIDER=http
YEB_CEDULA_API_URL=https://api.ejemplo.com/cedula/{cedula}
YEB_CEDULA_API_METHOD=GET
YEB_CEDULA_API_KEY=tu_clave
YEB_CEDULA_API_KEY_HEADER=x-api-key
```

Detalles del consumo de la API:

- La plantilla de URL admite los marcadores `{cedula}` (se reemplaza por el documento) y `{host}` (se reemplaza por `YEB_CEDULA_API_HOST`). Si no usas `{cedula}`, el número se envía como parámetro (`?cedula=`, `?documento=`, `?docNumber=`).
- El método `POST` envía el cuerpo JSON con los alias habituales (`cedula`, `documento`, `docNumber`, `numero`, `docType`, `tipoDocumento`).
- La respuesta se **normaliza automáticamente**: el portal reconoce nombres (`nombre`, `full_name`, `fullName`, `nombre_titular`...), fechas de nacimiento (`fecha_nacimiento`, `birthDate`, `dateOfBirth`, `dob`...), edades (`edad`, `age`), indicadores de existencia (`existe`, `exists`, `found`, `valid`) y respuestas anidadas (`data`, `persona`, `results`, `person`).
- Errores controlados: `404` = cédula no registrada, `401/403` = credenciales incorrectas, `429` = cuota agotada, `5xx` o timeout = API no disponible. En este último caso el registro se **bloquea** (comportamiento seguro) salvo que actives `YEB_CEDULA_FAIL_OPEN=1`.

### Variables de entorno

| Variable | Por defecto | Descripción |
| :--- | :--- | :--- |
| `YEB_CEDULA_PROVIDER` | `sandbox` | `sandbox` (simulador local) o `http` (API externa). |
| `YEB_CEDULA_API_URL` | *(vacío)* | Plantilla del endpoint. Admite `{cedula}` y `{host}`. |
| `YEB_CEDULA_API_METHOD` | `GET` | Método HTTP de la consulta (`GET` o `POST`). |
| `YEB_CEDULA_API_KEY` | *(vacío)* | Credencial de la API. |
| `YEB_CEDULA_API_KEY_HEADER` | `x-api-key` | Header donde se envía la credencial. |
| `YEB_CEDULA_API_HOST` | *(vacío)* | Host adicional (header `X-RapidAPI-Host`) y valor del marcador `{host}`. |
| `YEB_CEDULA_API_TIMEOUT` | `8` | Tiempo máximo de espera en segundos. |
| `YEB_CEDULA_DOCUMENT_TYPE` | `CC` | Tipo de documento consultado en la API. |
| `YEB_CEDULA_MAX_AGE` | `24` | Edad máxima para crear cuenta de candidato (`0` = sin límite). |
| `YEB_CEDULA_MAX_AGE_COMPANY` | `0` | Edad máxima para crear cuenta de empresa (`0` = sin límite). |
| `YEB_CEDULA_NAME_MATCH` | `1` | Verificar que el nombre capturado coincida con el de la cédula. |
| `YEB_CEDULA_NAME_THRESHOLD` | `0.8` | Umbral de coincidencia de nombres (0 a 1). |
| `YEB_CEDULA_FAIL_OPEN` | `0` | Permitir el registro si la API falla (`1`) o bloquearlo (`0`). |

> El límite de edad aplica a los **candidatos** (jóvenes). Las cuentas de empresa se validan sin límite de edad por defecto; actívalo con `YEB_CEDULA_MAX_AGE_COMPANY=24`.

### Auditoría

Cada validación deja rastro en `audit_logs` con el documento enmascarado: `cedula_validated`, `cedula_validation_failed` y `cedula_api_error`.

---

Motor relacional: **MySQL 8.0+ / MariaDB 10.4+**.

### Tablas Principales

| Tabla | Propósito |
| :--- | :--- |
| `users` | Cuentas de candidatos, reclutadores y administradores con datos de perfil y cédula validada (`cedula`, única por tipo de cuenta). |
| `companies` | Información empresarial, sector, tamaño, contacto y estado de verificación. |
| `jobs` | Vacantes creadas por las empresas con condiciones contractuales y salariales. |
| `applications` | Relación candidato-vacante con seguimiento de estado (`pending` a `rejected`). |
| `messages` | Mensajes del chat asociados a cada postulación con estado de lectura. |
| `notifications` | Alertas del sistema dirigidas a cada usuario. |
| `saved_jobs` | Ofertas guardadas como favoritas por los candidatos. |
| `hidden_jobs` | Ofertas que el postulante prefiere no visualizar en sus búsquedas. |
| `audit_logs` | Registro cronológico inmutable de actividades y auditoría de seguridad. |

---

## Seguridad Implementada

- **CSRF Token**: Validación en todas las peticiones `POST` contra `$_SESSION['csrf']`.
- **Prepared Statements (PDO)**: Consultas parametrizadas que mitigan inyecciones SQL.
- **Escape Contextual de Salida**: Función `self::e()` (`htmlspecialchars`) en todas las vistas contra Cross-Site Scripting (XSS).
- **Protección de Descargas**: Descarga de documentos con validación estricta de permisos de acceso.
- **Validación de Integridad en Chat**: Regla estricta a nivel de capa de servicio que rechaza envíos si la postulación ha finalizado en estado rechazado.

---

## Pruebas Automatizadas

El proyecto incluye dos suites de pruebas: el módulo de chat en `tests/ChatTest.php` y la validación de cédulas en `tests/CedulaTest.php`.

Para ejecutar las pruebas:

```powershell
& C:\xampp\php\php.exe tests\ChatTest.php
& C:\xampp\php\php.exe tests\CedulaTest.php
```

### Casos de prueba cubiertos (chat):
1. **Control de acceso**: Verificación de que solo candidatos y empresas correspondientes accedan a su chat.
2. **Envío regular de mensajes**: Validación de inserción de mensajes en postulaciones activas.
3. **Bloqueo de postulación rechazada**: Verificación de que el envío de mensajes lance `DomainException` al estar rechazada.
4. **Validación de contenido**: Rechazo de mensajes vacíos o con solo espacios en blanco.
5. **Listado de conversaciones y contadores**: Prueba de conteo de no leídos e historial.

### Casos de prueba cubiertos (cédulas):
1. **Normalización del documento**: Quita puntos, guiones, espacios y prefijos `CC`/`CE`.
2. **Formato**: Cédulas vacías, demasiado cortas o demasiado largas son rechazadas.
3. **Cálculo de edad**: Casos límite de cumpleaños (24 años exactos, un día antes y un día después).
4. **Orden de las validaciones**: La edad se evalúa antes de consultar la API (el proveedor no se invoca cuando el requisito de edad falla).
5. **Coincidencia de nombres**: Comparación sin tildes, sin partículas y sin importar el orden de las palabras.
6. **Coincidencia de fecha de nacimiento**: Rechazo cuando la fecha capturada difiere de la registrada.
7. **Cédula inexistente**: Simulador y proveedores externos rechazan el registro.
8. **Proveedor HTTP**: Construcción de la URL, envío de credenciales, sustitución de `{host}`, y errores controlados (`404`, `401`, respuesta ilegible, caída de red).
9. **Normalización de respuestas**: Distintos formatos de proveedor (`existe`, `found`, `fullName`, `dateOfBirth`, objetos anidados, estado `not_found`).
10. **Límite por tipo de cuenta**: El límite de edad de empresas se puede activar o desactivar por configuración.

---

## Paleta de Colores Corporativa

- **Azul Primario**: `#17457c`
- **Azul Oscuro / Fondo**: `#0f2d52`
- **Acento / Resaltado**: `#f59e0b` / `#fca311`
- **Fondo de Interfaz**: `#f8fafc`
- **Superficies**: `#ffffff`
- **Texto Principal**: `#0f172a`
- **Bordes y Divisores**: `#e2e8f0`
