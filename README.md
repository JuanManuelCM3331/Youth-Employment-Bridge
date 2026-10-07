# Youth-Employment-Bridge

Portal de empleo desarrollado con arquitectura monolítica modular.

## Prototipo funcional

Este repositorio contiene un prototipo ejecutable del portal con PHP 8.2, PDO,
MySQL y separación modular por `Controller -> Service -> Repository -> Model`.
El esqueleto original no incluía una instalación Laravel ni `composer.json`, por
lo que el prototipo usa PHP modular sin dependencias externas y mantiene la
estructura de módulos definida en la arquitectura.

### Puesta en marcha con XAMPP

1. Inicia **Apache** y **MySQL** desde el panel de XAMPP.
2. Carga `database/schema.sql` en MySQL (crea la base `yeb_portal`).
3. Si ya tenías una base creada, ejecuta también `database/migrate_company_audit.sql`.
4. Carga `database/seed.sql` para insertar empresas, vacantes y usuarios demo.
5. Abre [http://localhost/YEB/](http://localhost/YEB/) en el navegador.

También puedes cargar los archivos desde PowerShell:

```powershell
Get-Content .\database\schema.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -u root
Get-Content .\database\migrate_company_audit.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -u root
Get-Content .\database\seed.sql -Raw | & C:\xampp\mysql\bin\mysql.exe -u root
```

La conexión por defecto usa `127.0.0.1`, puerto `3306`, usuario `root`, sin
contraseña y base `yeb_portal`. Se puede sobrescribir con `YEB_DB_HOST`,
`YEB_DB_PORT`, `YEB_DB_DATABASE`, `YEB_DB_USERNAME` y `YEB_DB_PASSWORD`.

Para desarrollo local, copia `.env.example` como `.env` y coloca allí las
credenciales de MySQL. `.env` está excluido de Git; nunca subas contraseñas,
tokens, dumps de producción ni archivos de configuración local.

Acceso demo: `ana@yeb.test` / `password`.

Cuenta de empresa: `laura@talentolab.test` / `password`.

Cuenta de administrador: `admin@yeb.test` / `password`.

Las credenciales anteriores son únicamente datos demo locales. Cámbialas o
elimina `database/seed.sql` antes de desplegar el sistema.

### Flujos incluidos

- Landing pública con búsqueda por cargo, empresa, ciudad o modalidad.
- Registro e inicio de sesión con contraseñas protegidas mediante `password_hash`.
- Postulación única a vacantes para usuarios autenticados.
- Perfil de candidato con carga privada de hoja de vida en PDF, DOC o DOCX,
   máximo 5 MB, almacenada fuera de `public`.
- Perfil de candidato editable con datos laborales, habilidades, experiencia,
   educación, enlaces profesionales, disponibilidad, teléfono, ubicación y foto.
- Perfil corporativo editable con descripción, sector, sitio web, contacto,
   tamaño, ubicación y logo o foto de empresa.
- Las empresas solo pueden descargar la hoja de vida de candidatos que se hayan
   postulado a una vacante de esa empresa; cada carga y descarga queda auditada.
- Dashboard con historial y estado de postulaciones.
- Panel de empresa para crear vacantes como publicadas o borradores y gestionar
   el estado de candidatos: pendiente, revisión, entrevista, aceptado o rechazado.
- Panel de administrador con métricas y los últimos 100 eventos de auditoría:
   usuario, acción, módulo, ruta, resultado, IP, navegador, fecha y payload.
- Protección CSRF en formularios y control de acceso por rol.
- Tablas MySQL para usuarios, empresas, vacantes, postulaciones, notificaciones,
   ciudades, habilidades y `audit_logs`.

## Tecnologías

- HTML
- Tailwind CSS
- JavaScript
- PHP
- MySQL

---

# Estructura del proyecto

```text
job-portal/
│
├── app/
├── bootstrap/
├── config/
├── database/
├── Modules/
│   ├── Auth/             # Controllers y Services de autenticación
│   ├── Users/            # Perfil, CV y datos del candidato
│   ├── Companies/        # Perfil y datos corporativos
│   ├── Jobs/             # Búsqueda y administración de vacantes
│   ├── Applications/     # Postulaciones y estados
│   ├── Dashboard/        # Métricas y auditoría
│   ├── Notifications/    # Notificaciones persistidas
│   └── Search/           # Búsqueda de vacantes
│
├── public/
├── resources/
├── routes/
├── storage/
└── tests/
```

---

# Directorios principales


## config/

Archivos de configuración.

---

## database/

Migraciones, seeders y factories.

---

## Modules/

Contiene los módulos del sistema.

Cada módulo tiene:

- Controllers
- Services
- Repositories
- Models
- Requests
- Views
- Routes
- Migrations

Los módulos de negocio siguen esta separación:

```text
Controller -> Service -> Repository -> Database
                         -> Views
```

`public/index.php` solo inicializa el autoload y delega la petición. `app/Http/Portal.php`
coordina la petición y las respuestas entre módulos; las operaciones de usuarios,
empresas, vacantes, postulaciones, dashboard, autenticación y búsqueda viven en
sus respectivos módulos.

---

## public/

Archivos públicos accesibles desde el navegador.

Ejemplos:

- index.php
- imágenes
- archivos compilados

---

## resources/

Vistas, estilos y scripts.

---

## routes/

Rutas globales del sistema.

---

## storage/

Logs, caché y archivos temporales.

---

## tests/

Pruebas automatizadas.

---

# Módulos

# Auth

Gestiona autenticación y autorización.

## Funciones

- Login
- Registro
- Recuperar contraseña
- Cierre de sesión
- Roles y permisos

---

# Users

Administra usuarios del sistema.

## Funciones

- Perfil
- Configuración
- Imagen de usuario
- Datos personales

## Tipos de usuario

- Administrador
- Reclutador
- Candidato

---

# Companies

Gestiona empresas reclutadoras.

## Funciones

- Registro empresarial
- Perfil corporativo
- Logo
- Información de contacto
- Estado de verificación

---

# Jobs

Administra vacantes laborales.

## Funciones

- Crear vacantes
- Editar vacantes
- Publicar ofertas
- Categorías
- Modalidad laboral
- Rango salarial

## Estados

- Borrador
- Publicada
- Cerrada

---

# Applications

Gestiona postulaciones.

## Funciones

- Aplicar a vacantes
- Ver historial
- Seguimiento de estado
- Gestión de candidatos

## Estados

- Pendiente
- En revisión
- Entrevista
- Aceptado
- Rechazado

---

# Dashboard

Panel administrativo y métricas.

## Funciones

- Estadísticas
- Reportes
- Actividad reciente
- Indicadores del sistema

---

# Notifications

Sistema de notificaciones.

## Funciones

- Correos
- Alertas internas
- Notificaciones del sistema

---

# Search

Motor de búsqueda de vacantes.

## Funciones

- Buscar empleos
- Filtrar resultados
- Ordenar vacantes

## Filtros

- Ciudad
- Salario
- Experiencia
- Modalidad

---

# Arquitectura

El sistema utiliza arquitectura monolítica modular.

Cada módulo está separado por capas:

```text
Controller (Frontend)
   ↓
Service (Backend)
   ↓
Repository (peticiones a la db y api para que las demas puedan acceder)
   ↓
Model
```

## Controllers

Reciben requests y retornan respuestas.

## Services

Contienen lógica de negocio.

## Repositories

Gestionan acceso a datos.

## Models

Representan entidades de base de datos.

---

# Base de datos

Motor utilizado:

- MySQL

## Tablas principales

- users
- roles
- companies
- jobs
- applications
- notifications
- cities
- skills

---

# Frontend

## Tecnologías

- HTML
- Tailwind CSS
- JavaScript

## Responsabilidades

- Interfaces
- Validaciones básicas
- Interacciones dinámicas
- Consumo de endpoints

---

# Backend

Desarrollado con Laravel y Laravel Modules.

## Responsabilidades

- Lógica de negocio
- Seguridad
- Persistencia
- Autenticación
- Gestión modular

---

# Seguridad

## Implementaciones

- Middleware
- CSRF Protection
- Validaciones
- Hash de contraseñas
- Policies
- Control de acceso

---

# Objetivo

Mantener un sistema:

- Escalable
- Modular
- Ordenado
- Fácil de mantener

# Colores a utilizar

Negro
#000000

Azul de Prusia 
#14213d

~Naranja para contraste
#fca311

Gris alabastro para destacar componentes
#efeded

Blanco
#ffffff
