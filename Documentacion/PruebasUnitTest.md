## Ejecución de pruebas unitarias con PHPUnit

Este proyecto utiliza **Composer** para administrar las dependencias de PHP y **PHPUnit** para ejecutar las pruebas unitarias.

### 1. Requisitos previos

Antes de ejecutar las pruebas, es necesario tener instalado:

- **PHP**, compatible con la versión requerida por el proyecto.
- **Composer**, para instalar las dependencias de PHP.
- **Git**, para clonar el repositorio.

Si las pruebas requieren conexión a la base de datos, también será necesario configurar e iniciar el servicio correspondiente, como MySQL en XAMPP.

### 2. Clonar el repositorio

Si todavía no tienes el proyecto en tu equipo, ejecuta:

```bash
git clone URL_DEL_REPOSITORIO
cd Youth-Employment-Bridge
```

Reemplaza `URL_DEL_REPOSITORIO` por la URL real del repositorio.

### 3. Instalar las dependencias

Si acabas de clonar el proyecto y no tienes las dependencias instaladas, ejecuta desde la carpeta raíz:

```bash
composer install
```

Este comando lee los archivos `composer.json` y `composer.lock` e instala las dependencias necesarias, incluyendo PHPUnit si está declarado como dependencia de desarrollo.

**Importante:** no es necesario instalar PHPUnit manualmente si ya está incluido en las dependencias del proyecto. Tampoco se debe subir la carpeta `vendor/` al repositorio; Composer se encarga de generarla localmente.

Si Composer no está instalado, primero debes instalarlo desde su página oficial: https://getcomposer.org/download/

Si PHP o Composer no se reconocen desde la terminal, verifica que estén instalados y agregados a la variable de entorno `PATH`.

### 4. Verificar la instalación

Comprueba que PHPUnit esté disponible:

```powershell
.\vendor\bin\phpunit --version
```

Si el comando muestra la versión de PHPUnit, la instalación está lista.

### 5. Ejecutar las pruebas unitarias

Para ejecutar todas las pruebas de la carpeta `tests`:

```powershell
.\vendor\bin\phpunit tests
```

Para ejecutar un archivo específico:

```powershell
.\vendor\bin\phpunit tests/CedulaTest.php
```

```powershell
.\vendor\bin\phpunit tests/ChatTest.php
```

```powershell
.\vendor\bin\phpunit tests/IdScanTest.php
```

Para ejecutar un método específico, reemplaza `nombreDelMetodo` por el nombre real del método de prueba:

```powershell
.\vendor\bin\phpunit tests/ChatTest.php --filter nombreDelMetodo
```

### 6. Interpretar los resultados

- **OK:** las pruebas ejecutadas finalizaron correctamente.
- **FAILURES:** una o más pruebas se ejecutaron, pero sus resultados no fueron los esperados.
- **ERRORS:** ocurrió un error durante la ejecución, por ejemplo, una dependencia faltante o un problema de configuración.

### 7. Si las pruebas no se ejecutan

Si acabas de clonar el repositorio o no existe la carpeta `vendor/`, ejecuta:

```powershell
composer install
```

Si sigue apareciendo un error, comprueba que:

- PHP y Composer estén disponibles desde la terminal.
- Las dependencias estén declaradas correctamente en `composer.json`.
- Exista el archivo `composer.lock` y esté actualizado con las dependencias del proyecto.
- La configuración de la base de datos sea correcta, si las pruebas la necesitan.
- Los servicios requeridos estén iniciados, cuando corresponda.

Si modificas las dependencias del proyecto, actualiza los archivos de Composer según corresponda y comparte los cambios de `composer.json` y `composer.lock` en el repositorio.

### 8. Ejecutar las pruebas en futuras sesiones

Una vez instaladas las dependencias, no necesitas reinstalarlas cada vez que cierres la terminal o reinicies el equipo. Solo debes abrir una terminal en la carpeta raíz del proyecto y ejecutar:

```powershell
.\vendor\bin\phpunit tests
```

Solo será necesario ejecutar nuevamente `composer install` si falta la carpeta `vendor/` o si necesitas instalar las dependencias declaradas en el proyecto.
