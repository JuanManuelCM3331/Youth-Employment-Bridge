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
   - *(Opcional)* Si tenías una versión previa de la base de datos, ejecuta también `database/migrate_company_audit.sql` y `database/migrate_chat.sql`.
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
│   │   └── Database.php             # Conexión PDO Singleton
│   └── Http/
│       ├── Portal.php               # Front controller y orquestador de rutas
│       └── PortalViewRenderer.php   # Renderizador de vistas, componentes y layout UI
├── config/
│   └── database.php                 # Parámetros de conexión a BD y carga de .env
├── database/
│   ├── schema.sql                   # Estructura DDL completa de la base de datos
│   ├── seed.sql                     # Datos semilla para pruebas
│   ├── migrate_company_audit.sql    # Migración de auditoría y empresas
│   └── migrate_chat.sql             # Migración para la tabla de mensajería (messages)
├── Modules/
│   ├── Applications/                # Postulaciones y gestión de estados
│   ├── Auditoria/                   # Bitácora de eventos del sistema y seguridad
│   ├── Auth/                        # Autenticación, registro y control de sesiones
│   ├── Chat/                        # Mensajería bidireccional candidatos-empresas
│   ├── Companies/                   # Perfiles corporativos y logos
│   ├── Dashboard/                   # Métricas, reportes y gestión administrativa
│   ├── Jobs/                        # Gestión, edición y publicación de vacantes
│   ├── Notifications/               # Notificaciones internas y alertas
│   ├── Search/                      # Buscador de empleo con filtros y favoritos
│   └── Users/                       # Hojas de vida (CV), perfiles y fotos
├── public/
│   ├── index.php                    # Punto de entrada HTTP y PSR-4 autoloader
│   └── uploads/                     # Almacenamiento seguro de archivos
├── tests/
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

## Base de Datos

Motor relacional: **MySQL 8.0+ / MariaDB 10.4+**.

### Tablas Principales

| Tabla | Propósito |
| :--- | :--- |
| `users` | Cuentas de candidatos, reclutadores y administradores con datos de perfil. |
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

El proyecto incluye una suite de pruebas para el módulo de chat en `tests/ChatTest.php`.

Para ejecutar las pruebas:

```powershell
& C:\xampp\php\php.exe tests\ChatTest.php
```

### Casos de prueba cubiertos:
1. **Control de acceso**: Verificación de que solo candidatos y empresas correspondientes accedan a su chat.
2. **Envío regular de mensajes**: Validación de inserción de mensajes en postulaciones activas.
3. **Bloqueo de postulación rechazada**: Verificación de que el envío de mensajes lance `DomainException` al estar rechazada.
4. **Validación de contenido**: Rechazo de mensajes vacíos o con solo espacios en blanco.
5. **Listado de conversaciones y contadores**: Prueba de conteo de no leídos e historial.

---

## Paleta de Colores Corporativa

- **Azul Primario**: `#17457c`
- **Azul Oscuro / Fondo**: `#0f2d52`
- **Acento / Resaltado**: `#f59e0b` / `#fca311`
- **Fondo de Interfaz**: `#f8fafc`
- **Superficies**: `#ffffff`
- **Texto Principal**: `#0f172a`
- **Bordes y Divisores**: `#e2e8f0`
