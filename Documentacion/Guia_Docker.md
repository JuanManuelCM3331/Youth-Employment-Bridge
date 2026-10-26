# Guía de Uso y Administración de Docker - Youth Employment Bridge

Esta guía contiene las instrucciones y comandos esenciales para ejecutar, mantener, reiniciar y administrar el entorno Docker de la aplicación **Youth Employment Bridge**.

---

## 1. Requisitos Previos

1. Tener instalado **Docker Desktop** y ejecutándose en Windows.
2. Contar con el archivo de variables de entorno configurado:
   ```powershell
   # Si aún no lo has creado:
   Copy-Item .env.docker .env
   ```

---

## 2. Iniciar y Levantar los Contenedores

### Modo Normal (App + Base de Datos MySQL)
Ejecuta en la raíz del proyecto (`c:\xampp\htdocs\Youth-Employment-Bridge`):
```bash
docker compose up -d
```
- `-d`: Ejecuta los contenedores en segundo plano (*detached mode*).

### Modo Desarrollo (Incluye phpMyAdmin)
Para levantar también la interfaz web de gestión de bases de datos:
```bash
docker compose --profile dev up -d
```

### URLs de Acceso:
- **Aplicación Web:** [http://localhost:8080](http://localhost:8080)
- **phpMyAdmin (si usas perfil dev):** [http://localhost:8081](http://localhost:8081)
- **MySQL directo:** `localhost:3307` (Usuario: `yeb_user` / Contraseña: `secret`, o Root: `root` / `rootsecret`)

### Credenciales de Prueba (cargadas por el Seed):
| Rol | Correo | Contraseña |
|---|---|---|
| **Administrador** | `admin@yeb.test` | `password` |
| **Candidato** | `ana@yeb.test` | `password` |
| **Reclutador** | `laura@talentolab.test` | `password` |

---

## 3. ¿Cómo Aplicar Cambios Realizados en el Código?

Dado que el código fuente se empaqueta dentro de la imagen de Docker durante el build:

### Opción A: Reconstruir la App tras modificar código PHP/HTML/CSS/JS (Recomendada)
Para recompilar la imagen de la app con tus cambios de código sin afectar ni reiniciar la base de datos:
```bash
docker compose up -d --build app
```
Este comando toma pocos segundos gracias a la caché de capas de Docker.

### Opción B: Reiniciar el contenedor sin recompilar
Si solo hiciste cambios en variables de entorno o configuraciones que no requieren recrear la imagen:
```bash
docker compose restart app
```

---

## 4. Detener y Pausar los Contenedores

### Pausar/Detener temporalmente (mantiene contenedores creados)
```bash
docker compose stop
```
Para volver a encenderlos después de un `stop`:
```bash
docker compose start
```

### Bajar contenedores (libera puertos y redes, pero conserva los datos)
```bash
docker compose down
```
> *Tus datos de base de datos y archivos subidos se conservan intactos en los volúmenes de Docker.*

---

## 5. Eliminar y Resetear el Entorno

### Eliminar contenedores, redes y volúmenes (Reset Total de Base de Datos y Storage)
Si deseas limpiar completamente la base de datos para que vuelva a correr las migraciones y el seed desde cero:
```bash
docker compose down -v
```
> **Advertencia:** `-v` borra los volúmenes (`db_data`, almacenamiento de CVs, avatares, etc.). Úsalo únicamente cuando quieras empezar de cero.

### Forzar nueva construcción limpia desde cero
```bash
docker compose down -v
docker compose build --no-cache
docker compose up -d
```

---

## 6. Monitoreo y Diagnóstico (Comandos Útiles)

### Ver estado de los contenedores
```bash
docker compose ps
```

### Ver logs en tiempo real
Para ver los registros de la aplicación (errores de PHP / Apache):
```bash
docker compose logs -f app
```
Para ver los registros de la base de datos MySQL:
```bash
docker compose logs -f db
```

### Entrar a la terminal interactiva de un contenedor
- **Consola del contenedor PHP/Apache (`app`):**
  ```bash
  docker exec -it yeb_app bash
  ```
- **Consola interactiva de MySQL:**
  ```bash
  docker exec -it yeb_db mysql -u root -prootsecret yeb_portal
  ```

### Ejecutar pruebas unitarias dentro del contenedor
```bash
docker exec -it yeb_app ./vendor/bin/phpunit
```

---

## 7. Estructura de Volúmenes y Persistencia

Docker gestiona la persistencia de forma automática mediante volúmenes nombrados:
- `db_data`: Datos físicos de MySQL.
- `storage_avatars`: Fotos de perfil de candidatos.
- `storage_company_avatars`: Logos de empresas.
- `storage_cvs`: Hojas de vida en PDF.
- `storage_id_scans`: Documentos escaneados de verificación de identidad.
