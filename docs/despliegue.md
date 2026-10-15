# Despliegue con Docker (Fase 10)

## Arquitectura

```text
                  ┌────────────── web (Nginx, único servicio expuesto: 80/443) ──────────────┐
navegador ───────▶│  /                      → SPA de Nuxt (archivos estáticos, nuxt generate) │
   HTTPS          │  /api /sanctum /up /logout /forgot-password /reset-password              │
                  │  /user/password, POST /login        → app (FastCGI)                      │
                  └──────────────────────────────────────────────────────────────────────────┘
                       app (Laravel, PHP-FPM)        worker (php artisan queue:work)
                                  └───────────────┬───────────────┘
                                          db (MariaDB 11.4, sin puerto público)
```

- **Mismo dominio** para la SPA y la API: sin CORS entre orígenes; cookies de
  sesión `Secure`, `HttpOnly`, `SameSite=Lax`.
- `GET /login` es la página de Nuxt y `POST /login` la acción de Fortify:
  Nginx las separa por método.
- Volúmenes: `storage` (evidencias, logs, sesiones) y `db` (datos de MariaDB).
  Sobreviven a `docker compose down` y a las actualizaciones de imagen.

| Archivo | Para qué |
|---|---|
| `compose.yaml` | Servicios `web`, `app`, `worker`, `db` |
| `docker/php/Dockerfile` | Imagen de la API (PHP 8.3-FPM con pdo_mysql, gd, intl, zip, bcmath, opcache) |
| `docker/php/entrypoint.sh` | Genera las cachés de Laravel al arrancar; no migra |
| `docker/nginx/default.conf` | Rutas, HTTPS y cabeceras de seguridad |
| `docker/produccion.env.example` | Plantilla de configuración (copiar a `docker/produccion.env`) |
| `../SistemaMatrizRiesgo-frontend/Dockerfile` | Imagen web: Nuxt generado + Nginx |

## Requisitos del servidor

- Docker Engine con el plugin Compose (`docker compose version`).
- Los dos repositorios clonados uno al lado del otro:
  ```text
  SistemaMatrizRiesgo/            ← aquí se ejecutan los comandos
  SistemaMatrizRiesgo-frontend/
  ```
- Certificado HTTPS del dominio (lo provee la institución).
- Datos del SMTP institucional.

## Primera instalación

```bash
cd SistemaMatrizRiesgo

# 1. Configuración (no se versiona)
cp docker/produccion.env.example docker/produccion.env
#    Edite todos los valores CAMBIAR: dominio, contraseñas de la base de datos, SMTP.

# 2. Certificado (nombres exactos)
mkdir -p docker/nginx/certs
cp /ruta/certificado.crt docker/nginx/certs/servidor.crt
cp /ruta/clave-privada.key docker/nginx/certs/servidor.key
chmod 600 docker/nginx/certs/servidor.key

# 3. Construir imágenes y generar la clave de la aplicación
docker compose build
docker compose run --rm --no-deps --entrypoint php app artisan key:generate --show
#    Copie el resultado (base64:...) en APP_KEY de docker/produccion.env.

# 4. Arrancar
docker compose up -d

# 5. Base de datos: tablas y catálogos (seguro de repetir; no crea usuarios)
docker compose exec app php artisan migrate --force
docker compose exec app php artisan db:seed --force

# 6. Primer Administrador (pide la contraseña sin mostrarla)
docker compose exec app php artisan usuarios:crear-administrador
```

El servicio `worker` se reinicia solo hasta que existen las tablas del paso 5;
es normal verlo en estado *Restarting* durante la primera instalación.

### Contraseñas en producción

Con `APP_ENV=production` las contraseñas deben tener al menos 12 caracteres,
mayúsculas, minúsculas, números y símbolos, y no figurar en filtraciones
conocidas (consulta a haveibeenpwned.com; si el servidor no tiene salida a
Internet, esa comprobación se omite).

## Cargar las matrices desde Excel

Desde la pantalla del proceso ("Importar desde Excel"), o por consola:

```bash
docker compose cp "Matriz Compras.xlsx" app:/tmp/compras.xlsx
docker compose exec app php artisan matrices:importar /tmp/compras.xlsx --proceso=ID --usuario=admin@dominio
docker compose exec app php artisan matrices:importar /tmp/compras.xlsx --proceso=ID --usuario=admin@dominio --confirmar
```

Ver `docs/importacion.md`.

## Actualizar a una versión nueva

```bash
git -C ../SistemaMatrizRiesgo-frontend pull
git pull
docker compose build
docker compose up -d                                   # recrea solo lo que cambió
docker compose exec app php artisan migrate --force    # si hay migraciones nuevas
```

Nginx vuelve a resolver la dirección de `app` cada 10 s, así que recrear la
API no deja al servidor web apuntando a un contenedor que ya no existe.

## Operación

| Tarea | Comando |
|---|---|
| Estado | `docker compose ps` |
| Logs (errores de Laravel incluidos) | `docker compose logs -f app worker web` |
| Trabajos fallidos de la cola | `docker compose exec app php artisan queue:failed` |
| Consola de Laravel | `docker compose exec app php artisan tinker` |
| Parar sin borrar datos | `docker compose down` |

`LOG_LEVEL=warning`: los correos y mensajes de depuración no se registran (un
enlace de recuperación nunca debe quedar en un log).

## Respaldo

Lo que hay que respaldar: **la base de datos** y **el volumen `storage`**
(evidencias adjuntas).

```bash
# Base de datos
docker compose exec db sh -c 'mariadb-dump -uroot -p"$MARIADB_ROOT_PASSWORD" --single-transaction "$MARIADB_DATABASE"' \
  | gzip > respaldo-bd-$(date +%F).sql.gz

# Evidencias
docker compose exec app tar czf - -C /var/www/html/storage app > respaldo-evidencias-$(date +%F).tar.gz
```

La programación periódica de estos respaldos (y dónde se guardan) se
define con TI del DRO.

## Prueba local

Para probar el conjunto en un equipo de desarrollo, con puertos que no
choquen con otros servicios y un certificado autofirmado:

```bash
mkdir -p docker/nginx/certs
openssl req -x509 -nodes -newkey rsa:2048 -days 30 -subj "/CN=localhost" \
  -addext "subjectAltName=DNS:localhost" \
  -keyout docker/nginx/certs/servidor.key -out docker/nginx/certs/servidor.crt
# docker/produccion.env con APP_URL/FRONTEND_URL=https://localhost:8443 y
# SANCTUM_STATEFUL_DOMAINS=localhost:8443
HTTP_PORT=8080 HTTPS_PORT=8443 docker compose up -d --build
```

Abra https://localhost:8443 (el navegador avisará del certificado autofirmado).

## Pruebas contra MariaDB

Las pruebas automáticas usan SQLite en memoria. Para ejecutarlas contra
MariaDB (el motor de producción):

```bash
docker run -d --rm --name mariadb-pruebas -e MARIADB_ROOT_PASSWORD=pruebas \
  -e MARIADB_DATABASE=testing -p 127.0.0.1:33306:3306 mariadb:11.4
DB_CONNECTION=mariadb DB_HOST=127.0.0.1 DB_PORT=33306 DB_DATABASE=testing \
  DB_USERNAME=root DB_PASSWORD=pruebas php artisan test
docker stop mariadb-pruebas
```

## Pendiente

- Política de seguridad de contenido (CSP): requiere revisar en el navegador
  los scripts que Nuxt genera.
- Si la institución termina HTTPS en un proxy propio delante de este
  servidor, hay que configurar los proxies de confianza de Laravel.
