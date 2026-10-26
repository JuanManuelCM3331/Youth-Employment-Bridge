#!/bin/bash
# docker/mysql/init.sh
# Este script es ejecutado por el contenedor MySQL como root
# en el primer arranque. Crea la base de datos y ejecuta
# todos los archivos de migración en orden.

set -e

DB="${MYSQL_DATABASE:-yeb_portal}"

echo "[init] Creando base de datos '$DB' (si no existe)..."
mysql -u root -p"${MYSQL_ROOT_PASSWORD}" <<-EOSQL
    CREATE DATABASE IF NOT EXISTS \`${DB}\`
        CHARACTER SET utf8mb4
        COLLATE utf8mb4_unicode_ci;
    GRANT ALL PRIVILEGES ON \`${DB}\`.* TO '${MYSQL_USER}'@'%';
    FLUSH PRIVILEGES;
EOSQL

echo "[init] Base de datos lista."
