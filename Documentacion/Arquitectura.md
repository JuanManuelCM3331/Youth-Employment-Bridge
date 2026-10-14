# Job Portal

Portal de empleo desarrollado con arquitectura monolítica modular.

## Tecnologías

- HTML
- Tailwind CSS
- JavaScript
- PHP
- PHP 8.2
- PDO
- MySQL

---

# Estructura del proyecto

```text
job-portal/
│
├── app/
├── config/
├── database/
├── Modules/
│   ├── Applications
│   ├── Auditoria
│   ├── Auth
│   ├── Companies
│   ├── Dashboard
│   ├── Jobs
│   ├── Notifications
│   ├── Search
│   └── Users
│
├── public/
├── routes/
├── storage/
├── tests/
└── Documentacion/
```

---

# Directorios principales

## app/

Core de aplicación y punto de entrada HTTP (`app/Http/Portal.php`).

---

## config/

Archivos de configuración.

---

## database/

Migraciones, seeders y factories.

---

## Modules/

Contiene los módulos del sistema.

Cada módulo implementado en este repositorio usa principalmente:

- Controllers
- Services
- Repositories

---

## public/

Archivos públicos accesibles desde el navegador.

Ejemplos:

- index.php
- imágenes
- archivos compilados

---

## routes/

Rutas globales del sistema.

---

## storage/

Logs, caché y archivos temporales.

---

## tests/

Directorio reservado (actualmente sin suite automatizada).

---

# Módulos

# Auth

Gestiona autenticación y autorización.

## Funciones

- Login
- Registro
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
Controller
   ↓
Service
   ↓
Repository
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

Desarrollado con PHP 8.2, PDO y módulos propios (sin framework Laravel).

## Responsabilidades

- Lógica de negocio
- Seguridad
- Persistencia
- Autenticación
- Gestión modular

---

# Seguridad

## Implementaciones

- CSRF Protection
- Validaciones
- Hash de contraseñas
- Control de acceso

---

# Objetivo

Mantener un sistema:

- Escalable
- Modular
- Ordenado
- Fácil de mantener