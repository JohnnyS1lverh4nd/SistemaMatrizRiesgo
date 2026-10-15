#!/bin/sh
# Arranque de los contenedores "app" y "worker".
# La configuración llega por variables de entorno en tiempo de ejecución, así
# que las cachés de Laravel se generan aquí y no al construir la imagen.
# Las migraciones NO se ejecutan solas: ver docs/despliegue.md.
set -e

if [ -z "$APP_KEY" ]; then
    echo "ERROR: falta APP_KEY en docker/produccion.env (ver docs/despliegue.md)." >&2
    exit 1
fi

php artisan config:cache
php artisan route:cache
php artisan event:cache
php artisan view:cache

exec "$@"
