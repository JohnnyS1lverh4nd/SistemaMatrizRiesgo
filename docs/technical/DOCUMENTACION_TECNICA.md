# Documentación técnica — Sistema de Gestión de Matrices de Riesgo (INFOTEP)

| | |
|---|---|
| **Destinatarios** | Personal técnico que mantiene, despliega o amplía el sistema |
| **Estado** | Vigente al 12/11/2026 (incluye la ampliación de noviembre de 2026) |
| **Ubicación** | `docs/technical/DOCUMENTACION_TECNICA.md` (repositorio de la API) |

Este documento es la **puerta de entrada técnica**: describe la arquitectura,
las decisiones y las reglas transversales, y enlaza la documentación detallada
de cada módulo en lugar de repetirla. Es material del repositorio, **no un
módulo de la aplicación**: no se publica en la SPA ni aparece en el menú. Para
los usuarios finales está el [manual de usuario](../manual/manual-usuario.html).

---

## Índice

1. [Mapa de la documentación](#1-mapa-de-la-documentación)
2. [Arquitectura](#2-arquitectura)
3. [Tecnologías y versiones](#3-tecnologías-y-versiones)
4. [Estructura de los repositorios](#4-estructura-de-los-repositorios)
5. [Modelo de dominio](#5-modelo-de-dominio)
6. [Autenticación y sesión](#6-autenticación-y-sesión)
7. [Autorización: roles y alcance](#7-autorización-roles-y-alcance)
8. [Auditoría e historial de movimientos](#8-auditoría-e-historial-de-movimientos)
9. [Módulos funcionales](#9-módulos-funcionales)
10. [Base de datos y migraciones](#10-base-de-datos-y-migraciones)
11. [Entorno de desarrollo](#11-entorno-de-desarrollo)
12. [Calidad y pruebas](#12-calidad-y-pruebas)
13. [Despliegue y operación](#13-despliegue-y-operación)
14. [Convenciones del código](#14-convenciones-del-código)
15. [Estado: implementado y pendiente](#15-estado-implementado-y-pendiente)
16. [Cambios de este documento](#16-cambios-de-este-documento)

---

## 1. Mapa de la documentación

Todas las rutas son relativas al repositorio de la API (`SistemaMatrizRiesgo/`).

| Documento | Qué explica |
|---|---|
| [docs/erd.md](../erd.md) | Diagrama entidad-relación (Mermaid) generado del esquema real, tablas, reglas de integridad |
| [docs/api.md](../api.md) | Convenciones de la API REST, tabla de endpoints con "quién puede", uso de Postman/Newman |
| [docs/api/](../api/) | Colección de Postman, su generador (`generar-coleccion.py`) y archivos de ejemplo |
| [docs/modelo-organizativo.md](../modelo-organizativo.md) | Departamentos, procesos, catálogos (factores, periodicidades) |
| [docs/reglas-nivel-riesgo.md](../reglas-nivel-riesgo.md) | Regla Probabilidad + Impacto → nivel (matriz de calor) |
| [docs/matrices-y-ediciones.md](../matrices-y-ediciones.md) | Ciclo de vida de las ediciones: borrador → vigente → archivada, clonado |
| [docs/riesgos.md](../riesgos.md) | Riesgos de una edición, nivel calculado y ajustado, cuándo se modifican |
| [docs/seguimiento-y-evidencias.md](../seguimiento-y-evidencias.md) | Seguimiento histórico, anulación, almacenamiento de evidencias, límites de PHP |
| [docs/reportes.md](../reportes.md) | Dashboard, filtros, comparación de ediciones, exportación PDF/Excel |
| [docs/importacion.md](../importacion.md) | Importación desde los Excel institucionales (comando y web) |
| [docs/auditoria.md](../auditoria.md) | Registro de auditoría e historial de movimientos por área |
| [docs/despliegue.md](../despliegue.md) | Docker, primera instalación, actualización, respaldo, seguridad |
| [docs/manual/](../manual/) | Manual de usuario (HTML fuente, PDF y su generador) |
| [docs/technical/Informe-tecnico.pdf](Informe-tecnico.pdf) | Informe técnico del proyecto en PDF, para presentar. Fuente: `informe-tecnico.html`; se regenera con `php docs/technical/generar-informe.php` |
| `docs/Requisitos/` | Documento de requisitos original del DRO (RF-1…RF-9) |

---

## 2. Arquitectura

```text
            ┌──────────────────────── mismo dominio (HTTPS) ────────────────────────┐
navegador ─▶│ Nginx ── /            → SPA Nuxt (archivos estáticos generados)       │
            │       └─ /api, /sanctum, /login (POST), /logout, contraseñas → Laravel │
            └───────────────────────────────────────────────────────────────────────┘
                     Laravel (PHP-FPM)  ·  worker de colas  ·  MariaDB 11.4
```

- **Dos repositorios hermanos**, que deben clonarse uno al lado del otro:
  - `SistemaMatrizRiesgo/` — **API** Laravel. No tiene vistas: solo JSON y las
    rutas web de Fortify/Sanctum (`routes/web.php` está vacío a propósito).
  - `SistemaMatrizRiesgo-frontend/` — **SPA** Nuxt con `ssr: false`: es una
    aplicación interna detrás de login y la sesión vive en cookies que el
    servidor de Nuxt no recibiría.
- **Mismo dominio** para SPA y API en producción: sin CORS entre orígenes,
  cookies `Secure`/`HttpOnly`/`SameSite=Lax`. En desarrollo son orígenes
  distintos (`:3000` y `:8000`) y CORS con credenciales lo resuelve
  `config/cors.php` a partir de `FRONTEND_URL`.
- **La API es la única autoridad.** El frontend oculta lo que el rol no puede
  usar, pero toda regla (permiso, alcance, estado, validación) se comprueba en
  el servidor. El frontend nunca es la barrera de seguridad.

Detalle de contenedores y Nginx: [docs/despliegue.md § Arquitectura](../despliegue.md#arquitectura).

---

## 3. Tecnologías y versiones

Versiones **instaladas** (lockfiles) al 12/11/2026. No cambiar versiones
principales sin autorización.

### API

| Pieza | Versión | Uso |
|---|---|---|
| PHP | 8.3 (8.3.36 en desarrollo) | `composer.json` exige `^8.3` |
| Laravel | 13.32.0 | Framework |
| Laravel Sanctum | 4.3.3 | Autenticación SPA por cookies de sesión |
| Laravel Fortify | 1.39.0 | Login, logout, recuperación y cambio de contraseña (sin vistas) |
| dompdf/dompdf | 3.1.6 | Exportación a PDF |
| phpoffice/phpspreadsheet | 5.10.0 | Exportación e importación de Excel |
| Pest | 4.7.8 | Pruebas |
| Larastan (PHPStan) | 3.12.1, nivel 7 | Análisis estático |
| Laravel Pint | 1.32.1 | Estilo de código |
| MariaDB | 11.4 | Base de datos de producción |
| SQLite | — | Base de desarrollo (`database/database.sqlite`) y de pruebas (en memoria) |

### SPA

| Pieza | Versión | Uso |
|---|---|---|
| Node.js | 24 (imagen `node:24-bookworm-slim`) | Construcción |
| Nuxt | 4.6.1 | Framework (modo SPA) |
| Vue | 3.5.43 | |
| vue-router | 5.4.0 | |
| Nuxt UI | 4.11.2 | Componentes (`UDashboard*`, formularios, modales, tablas) |
| Tailwind CSS | 4.3.3 | Estilos |
| TypeScript | 6.0.3 | `npm run typecheck` (vue-tsc) |
| driver.js | 1.10.2 (exacta) | Recorrido guiado; se carga solo al iniciarlo |
| @iconify-json/lucide | 1.2.x | Iconos empaquetados (sin llamadas a la API de Iconify) |
| Nginx | 1.28 (alpine) | Sirve la SPA y hace de proxy a la API |

`driver.js` está fijada a una versión exacta (`--save-exact`): al incorporarla,
las versiones 1.11 y 1.12 tenían menos de dos semanas de publicadas.

---

## 4. Estructura de los repositorios

### API (`SistemaMatrizRiesgo/`)

| Carpeta | Contenido |
|---|---|
| `app/Http/Controllers/Api/` | Un controlador por recurso; delgados: validan (FormRequest), autorizan (Policy) y delegan |
| `app/Http/Requests/` | Validación de cada petición, mensajes en español; campos protegidos como `prohibited` |
| `app/Http/Resources/` | Forma del JSON; incluyen `permisos` calculados por la Policy para que la SPA muestre u oculte acciones |
| `app/Policies/` | Reglas de autorización por modelo |
| `app/Services/` | Lógica de negocio: `MatrizService`, `RiesgoService`, `SeguimientoService`, `CalculadoraNivelRiesgo`, `Auditor`, `Reportes/`, `Exportacion/`, `Importacion/` |
| `app/Models/` | Modelos Eloquent; `Concerns/Auditable.php` registra los cambios |
| `app/Enums/` | `EstadoMatriz`, `EstadoTratamiento`, `EscalaTipo`, `EstadoTour` |
| `app/Http/Middleware/` | `EnsureUserIsActive`, `ThrottlePasswordReset` |
| `app/Console/Commands/` | `usuarios:crear-administrador`, `matrices:importar` |
| `config/matrices.php` | Código y edición del formulario institucional (RT-01-NI-ONA-156) |
| `config/importacion.php` | Alias de catálogos para la importación |
| `database/migrations/`, `database/seeders/` | Esquema y catálogos iniciales (roles, niveles, escalas, matriz de calor, factores, periodicidades) |
| `lang/es/` | Mensajes de validación en español |
| `docker/` | Imagen PHP, Nginx, plantilla de configuración de producción |
| `tests/` | Pest: `Feature/Api` (por recurso), `Feature/Auth`, `Feature/Importacion`; `Support/Escenario.php` arma datos de prueba |

### SPA (`SistemaMatrizRiesgo-frontend/app/`)

| Carpeta | Contenido |
|---|---|
| `pages/` | Una página por pantalla (enrutado por archivos) |
| `layouts/default.vue` | Menú lateral por rol, `AvisoInactividad`, `InvitacionTour`; `publico.vue` para login y contraseñas |
| `components/` | `EncabezadoPanel` (barra superior con acceso a Perfil), formularios, paneles de seguimiento e historial, `dashboard/`, `catalogos/`, `perfil/` |
| `composables/` | `useApi` (cliente HTTP único con CSRF), `useAuth`, `useListado`, `useAlternarActivo`, `useRecorridoGuiado` |
| `middleware/` | `auth.global` (sesión), `admin`, `auditoria`, `historial` (rol) |
| `types/api.ts` | Tipos de las respuestas de la API y constantes de roles |
| `utils/` | Etiquetas de auditoría, errores de la API, pasos del recorrido (`pasosTour.ts`) |
| `assets/` | CSS (`main.css`, `tour.css`) e iconos propios (`icons/`, colección `infotep:`) |
| `../scripts/csp-nginx.mjs` | Calcula la CSP con los hashes de los scripts de cada build |

---

## 5. Modelo de dominio

Los nombres del código no coinciden con los de la interfaz. Esta es la
correspondencia:

| En la interfaz | En el código / BD | Notas |
|---|---|---|
| Departamento | `Departamento` / `departamentos` | Se desactiva, no se borra |
| **Matriz** (de un proceso) | `Proceso` / `procesos` | `creado_por_id` registra quién la creó |
| **Edición** de la matriz | `Matriz` / `matrices` | `borrador` → `vigente` → `archivada`; `edicion_anterior_id` encadena ediciones |
| Riesgo (fila del formulario) | `Riesgo` / `riesgos` | Borrado lógico solo en borradores; `riesgo_origen_id` al clonar |
| Seguimiento | `Seguimiento` / `seguimientos` | Histórico: no se edita, se **anula** con motivo |
| Evidencia | `Evidencia` / `evidencias` | Archivo privado con huella SHA-256 |

Reglas que el modelo garantiza aunque un flujo futuro olvide la Policy:

- Una sola edición vigente por proceso; publicar archiva la anterior en la
  misma transacción.
- Una edición vigente o archivada y sus riesgos no se modifican
  (`LogicException` en el modelo).
- Los registros de auditoría no se modifican ni se borran.

Diagrama y tablas: [docs/erd.md](../erd.md). Ciclo de vida:
[docs/matrices-y-ediciones.md](../matrices-y-ediciones.md).

---

## 6. Autenticación y sesión

- **Sanctum en modo SPA, cookies de sesión** (no tokens; la tabla
  `personal_access_tokens` no se usa). Flujo: `GET /sanctum/csrf-cookie` →
  `POST /login` (Fortify) → `GET /api/user`.
- **CSRF:** `useApi` envía la cookie `XSRF-TOKEN` en la cabecera
  `X-XSRF-TOKEN` en toda petición que modifica datos. Es el único cliente HTTP
  de la SPA: no usar `$fetch` directo contra la API.
- **Usuarios inactivos:** `EnsureUserIsActive` se aplica al grupo de la API
  **y** a las rutas de Fortify (`config/fortify.php` → `middleware`), así que
  un usuario desactivado tampoco puede cambiar su contraseña con una sesión
  abierta.
- **Inactividad:** `SESSION_LIFETIME=4` minutos (requisito del DRO). La SPA lo
  recibe en `/api/user` y `AvisoInactividad.vue` avisa un minuto antes; un
  401 de la API devuelve al login con `?motivo=sesion-expirada`.
- **Contraseñas:** en producción, mínimo 8 caracteres con mayúsculas,
  minúsculas, números, símbolos y sin filtraciones conocidas
  (`AppServiceProvider`). El cambio se hace con `PUT /user/password`
  (Fortify), desde **Perfil → Seguridad de la cuenta**. La recuperación por
  correo usa `ThrottlePasswordReset`.
- **Límites de peticiones:** API 300/min por usuario; exportar e importar
  20/min (`operaciones-costosas`); login y recuperación 5/min.

---

## 7. Autorización: roles y alcance

Cinco roles (`database/seeders/RoleSeeder.php`). La autorización combina
**rol** y **alcance por departamento** (asignación en `user_departamento`).

| Capacidad | Administrador | Enc. Departamento | Enc. Seguimiento | Auditor | Dirección |
|---|:-:|:-:|:-:|:-:|:-:|
| Alcance de consulta | Todos | Sus departamentos | Sus departamentos | Todos | Todos |
| Crear matriz (proceso) | ✔ | ✔ en los suyos | — | — | — |
| Editar / desactivar matriz | ✔ | Solo las que **él creó** | — | — | — |
| Crear, editar, publicar, archivar ediciones y riesgos | ✔ | ✔ en los suyos | — | — | — |
| Importar desde Excel | ✔ | — | — | — | — |
| Registrar y anular seguimiento | ✔ | ✔ en los suyos | ✔ en los suyos | — | — |
| Dashboard, reportes, comparar, exportar | ✔ | ✔ | ✔ | ✔ | ✔ |
| Historial de movimientos | Todos | Sus departamentos | Sus departamentos | — | — |
| Auditoría completa | ✔ | — | — | ✔ | — |
| Departamentos, catálogos, usuarios | ✔ | — | — | — | — |
| Perfil (nombre) y recorrido guiado | ✔ | ✔ | ✔ | ✔ | ✔ |

Dónde vive cada regla:

- **Fuente única del alcance** en `App\Models\User`:
  `puedeConsultarTodosLosDepartamentos()`, `puedeGestionarMatricesDe()`,
  `puedeRegistrarSeguimientoEn()`; y el scope `Departamento::visiblesPara($user)`
  para filtrar listados. Las Policies y los servicios de reportes los usan;
  no repetir comparaciones de slugs en otros sitios.
- **Policies** (`app/Policies/`). Algunas conceden todo al Administrador con
  `before()`; `MatrizPolicy` **no**, porque las reglas de estado (no editar
  una edición vigente) valen también para él.
- **Recursos** exponen `permisos` (p. ej. `ProcesoResource.permisos.editar`,
  `DepartamentoResource.permisos.crear_proceso`) calculados con la Policy:
  la SPA decide qué botones mostrar sin duplicar la lógica.
- **SPA:** el menú (`layouts/default.vue`) y los middlewares de ruta ocultan lo
  que el rol no usa. Es comodidad, no seguridad.

---

## 8. Auditoría e historial de movimientos

- **Un solo escritor:** `App\Services\Auditor::registrar()`. Los modelos con el
  trait `Auditable` registran creación, cambios y eliminación con valores
  anteriores y nuevos; los eventos de negocio (publicar, exportar, importar,
  login, login fallido, logout) se registran explícitamente.
- **Nunca se guardan** contraseñas, tokens ni `remember_token`; del login
  fallido solo el email. Los campos del recorrido guiado (`tour_*`) tampoco se
  auditan: son una preferencia personal.
- **Inmutable:** el modelo `Auditoria` lanza `LogicException` al modificar o
  borrar.
- **Área de cada registro** (nov. 2026): `departamento_id`, `proceso_id`,
  `matriz_id`. Cada modelo la aporta con `contextoAuditoria()` (los riesgos y
  seguimientos la toman de su edición, también si están en la papelera). Los
  registros anteriores se completaron con una migración de *backfill*.
- **Dos vistas, permisos distintos:**
  - `GET /api/auditoria` — registro completo (Administrador, Auditor).
  - `GET /api/movimientos` — **historial de movimientos por área**: solo
    registros con área y dentro de los departamentos visibles del usuario
    (`Auditoria::movimientosVisiblesPara`). Administrador y Encargados. No
    expone IP ni navegador.
  - Además, `GET /api/riesgos/{id}/historial` y `GET /api/matrices/{id}/historial`
    muestran los cambios de un riesgo o de una edición a quien puede verlos.

Detalle: [docs/auditoria.md](../auditoria.md).

---

## 9. Módulos funcionales

| Módulo | Pantalla (SPA) | API principal | Documentación |
|---|---|---|---|
| Inicio (dashboard) | `/` | `GET /api/dashboard` | [reportes.md](../reportes.md) |
| Matrices y ediciones | `/matrices`, `/procesos/{id}`, `/matrices/{id}` | `departamentos.procesos`, `procesos.matrices`, `publicar`, `archivar`, `clonar` | [matrices-y-ediciones.md](../matrices-y-ediciones.md) |
| Riesgos | dentro de la edición | `matrices.riesgos` | [riesgos.md](../riesgos.md) |
| Seguimiento y evidencias | panel en la edición vigente | `riesgos.seguimientos`, `anular`, `evidencias/{id}/descargar` | [seguimiento-y-evidencias.md](../seguimiento-y-evidencias.md) |
| Reportes y comparación | `/reportes`, `/comparar` | `reportes/riesgos`, `comparar`, `exportar/pdf`, `exportar/excel` | [reportes.md](../reportes.md) |
| Importación | botón en el proceso (Administrador) | `importacion/vista-previa`, `importacion` | [importacion.md](../importacion.md) |
| Historial de movimientos | `/historial` | `GET /api/movimientos`, `/opciones`, `/{id}` | [auditoria.md](../auditoria.md#historial-de-movimientos-por-área-nov-2026) |
| Auditoría | `/auditoria` | `GET /api/auditoria`, historial por riesgo y edición | [auditoria.md](../auditoria.md) |
| Administración | `/departamentos`, `/catalogos`, `/usuarios` | `departamentos`, catálogos, `users`, `roles` | [modelo-organizativo.md](../modelo-organizativo.md) |
| Perfil | `/perfil` (botón **Perfil**, arriba a la derecha) | `PUT /api/perfil`, `PUT /user/password` | este documento, § 9.1 |
| Ayuda y recorrido guiado | `/ayuda` (al pie del menú) | `PUT /api/perfil/tour` | este documento, § 9.2 |

### 9.1 Perfil

- Muestra información personal (nombre, correo), institucional (rol,
  departamentos, fecha de alta) y **Seguridad de la cuenta** con el cambio de
  contraseña. La antigua ruta `/cambiar-contrasena` redirige a
  `/perfil#seguridad`.
- El usuario solo puede cambiar **su nombre**. Correo, rol, departamentos,
  estado y contraseña están marcados `prohibited` en
  `ActualizarPerfilRequest` (422): los gestiona el Administrador, o Fortify en
  el caso de la contraseña. El cambio de nombre queda en la auditoría.

### 9.2 Recorrido guiado y Ayuda

- **Estado en el servidor:** `users.tour_estado` (`null`, `completado`,
  `omitido`; enum `EstadoTour`) y `tour_actualizado_at`. Llega en
  `GET /api/user` → `tour`, solo para el propio usuario.
- **Primer ingreso:** si `tour.estado` es `null`, `InvitacionTour.vue` ofrece
  el recorrido una vez por visita. "Ahora no" guarda `omitido`; cerrar con la
  X no decide nada.
- **Contenido único:** `utils/pasosTour.ts` define los pasos y los filtra por
  rol con las mismas reglas que el menú; la guía rápida de `/ayuda` se arma
  con esos mismos textos. Al añadir un módulo, agregar su paso allí.
- **Motor:** `composables/useRecorridoGuiado.ts` sobre Driver.js, que se
  importa de forma diferida. Los pasos señalan elementos con atributos
  `data-tour="…"`. Si el elemento no está visible (por ejemplo, el menú en
  móvil), el paso se muestra centrado. Los textos se escapan porque Driver.js
  los inserta como HTML.
- **Por qué no `useTour` de Nuxt UI:** Nuxt UI 4.11 trae un `useTour` sin
  interfaz que no oscurece la pantalla ni resalta el elemento. Por esa
  coincidencia de nombre, el composable propio se llama `useRecorridoGuiado`.

---

## 10. Base de datos y migraciones

- **Producción: MariaDB 11.4. Desarrollo: SQLite.** Las pruebas usan SQLite en
  memoria; para probar contra MariaDB, ver
  [despliegue.md § Pruebas contra MariaDB](../despliegue.md#pruebas-contra-mariadb).
- **Integridad:** claves foráneas `RESTRICT` (nada se borra en cascada desde
  datos de negocio); desactivar (`activo`) en lugar de borrar; borrado lógico
  solo en ediciones en borrador y riesgos. Ver
  [erd.md § Reglas de integridad](../erd.md#reglas-de-integridad-e-historial).
- **Reglas para migraciones nuevas:**
  1. **Incrementales y compatibles** con los datos existentes: columnas nuevas
     `nullable` o con valor por defecto; nunca modificar una migración ya
     aplicada.
  2. **Nunca** `migrate:fresh`, `migrate:reset` ni `db:wipe` sobre una base con
     datos. En producción `DB::prohibitDestructiveCommands()` los bloquea.
  3. **Respaldar antes de migrar.** Producción:
     [despliegue.md § Respaldo](../despliegue.md#respaldo). Desarrollo: copiar
     `database/database.sqlite` a `database.sqlite.respaldo-AAAAMMDD-HHMMSS`.
  4. **SQLite recrea la tabla al añadir una clave foránea.** Probar antes la
     migración sobre una copia de la base y comparar el número de filas.
  5. Las migraciones de datos (como el *backfill* de la auditoría) deben ser
     autocontenidas (`DB::table`, sin modelos), procesar por lotes
     (`chunkById`) y solo completar valores vacíos.

Migraciones de la ampliación de noviembre de 2026:

| Migración | Cambio |
|---|---|
| `2026_11_12_100000_add_creado_por_id_to_procesos_table` | `procesos.creado_por_id` (FK opcional a `users`) |
| `2026_11_12_200000_add_area_to_auditoria_table` | `auditoria.departamento_id`, `proceso_id`, `matriz_id` + índice |
| `2026_11_12_200100_backfill_area_in_auditoria_table` | Completa el área de los registros anteriores (`down` vacío: no borra datos) |
| `2026_11_12_300000_add_tour_to_users_table` | `users.tour_estado`, `users.tour_actualizado_at` |

---

## 11. Entorno de desarrollo

Requisitos: PHP 8.3 con `pdo_sqlite` y las extensiones de la imagen Docker
(`gd`, `intl`, `zip`, `bcmath`); Composer; Node.js 24.

```bash
# API (puerto 8000)
cd SistemaMatrizRiesgo
composer install
cp .env.example .env          # APP_URL=http://localhost:8000, FRONTEND_URL=http://localhost:3000
php artisan key:generate
php artisan migrate           # incremental; si falta database/database.sqlite, ofrece crearla
php artisan db:seed           # roles y catálogos (idempotente; no crea usuarios)
php artisan usuarios:crear-administrador
php artisan serve             # o: composer dev

# SPA (puerto 3000)
cd ../SistemaMatrizRiesgo-frontend
npm ci
echo "NUXT_PUBLIC_API_BASE=http://localhost:8000" > .env
npm run dev
```

- En desarrollo el correo va al log (`MAIL_MAILER=log`): los enlaces de
  recuperación de contraseña aparecen en `storage/logs/laravel.log`.
- Las evidencias se guardan en `storage/app/private/evidencias/` (ignorado
  por Git).
- `php artisan route:list --path=api` lista las rutas de la API.

---

## 12. Calidad y pruebas

Antes de entregar un cambio, las cuatro comprobaciones deben pasar:

```bash
# API
php artisan test                                   # Pest
vendor/bin/phpstan analyse --memory-limit=1G       # nivel 7
vendor/bin/pint --test                             # estilo (sin --test lo corrige)

# SPA
npm run typecheck
npm run generate                                   # build de producción
```

- **Estado al 12/11/2026:** 496 pruebas Pest correctas (en SQLite y en
  MariaDB 11.4), PHPStan sin errores, Pint correcto, typecheck sin errores,
  build correcto.
- `tests/Feature/Api/AmpliacionIntegracionTest.php` recorre de punta a punta,
  con cada rol, la ampliación de noviembre de 2026 junto a los flujos
  anteriores. Es la prueba de regresión a mantener si se toca alguna de esas
  funcionalidades.
- PHPStan necesita `--memory-limit=1G`; con el límite por defecto (128 MB) se
  detiene.
- Con `php artisan test --parallel`, algunas pruebas de importación fallan por
  archivos temporales compartidos. En serie pasan. Es un problema anterior a
  la ampliación, pendiente de revisar.
- Las pruebas de API cubren por recurso: permisos por rol y alcance, campos
  prohibidos (422), reglas de estado y registros de auditoría. Toda
  funcionalidad nueva debe venir con sus pruebas en `tests/Feature/Api/`.
- La colección de Postman se puede ejecutar con Newman contra un entorno
  levantado: [api.md](../api.md#ejecutarla-por-consola-newman).

---

## 13. Despliegue y operación

Todo está en [docs/despliegue.md](../despliegue.md): primera instalación,
actualización a una versión nueva (incluye `php artisan migrate --force`),
respaldo de la base y de `storage/app/private`, prueba local con Docker y
medidas de seguridad (HTTPS, HSTS, CSP con hashes, cabeceras, cookies).

Al actualizar a la versión con la ampliación de noviembre de 2026:

1. Respaldar la base de datos y las evidencias.
2. Aplicar las cuatro migraciones de la § 10. El *backfill* recorre toda la
   tabla `auditoria`: puede tardar en bases grandes.
3. Reconstruir **también la imagen web**, porque cambian la SPA (driver.js,
   iconos propios, páginas nuevas) y la CSP.
4. Los usuarios existentes verán la invitación al recorrido guiado en su
   próximo ingreso.

---

## 14. Convenciones del código

- **Idioma:** dominio, mensajes y comentarios en español; nombres de framework
  en inglés (`store`, `update`, `index`…). Las respuestas de error de la API
  están en español.
- **Controladores delgados:** validación en FormRequest, autorización en
  Policy, lógica en `app/Services`, forma de la respuesta en Resource.
- **Campos que el cliente no puede fijar** (estado, ids de relación, creador,
  rol propio…) se declaran `prohibited` en la FormRequest, con mensaje
  explicativo, y se asignan en el servidor (`forceFill` o atributo explícito,
  nunca `fillable`).
- **Transacciones** para operaciones compuestas (crear proceso + primera
  edición, publicar + archivar la anterior, importar).
- **Nada se borra físicamente** desde la interfaz: desactivar, anular o
  borrado lógico, según la entidad.
- **SPA:** todas las llamadas pasan por `useApi`; las pantallas usan
  `EncabezadoPanel` (que incluye el acceso a Perfil); iconos solo de las
  colecciones empaquetadas (`i-lucide-*`, `infotep:*`), porque el navegador no
  consulta Iconify.
- **Dependencias nuevas:** solo con autorización, con versión publicada hace
  al menos dos semanas, y revisando `composer audit` / `npm audit`.
- **Commits:** los hace el equipo; los registros de credenciales o datos
  sensibles nunca se versionan (`docker/produccion.env` y `.env` están
  ignorados).

---

## 15. Estado: implementado y pendiente

### Implementado

| Área | Estado |
|---|---|
| Estructura organizativa, catálogos, matriz de calor | Completo |
| Matrices, ediciones (ciclo de vida, clonado), riesgos | Completo |
| Seguimiento, anulación y evidencias | Completo |
| Dashboard, reportes, comparación, exportación PDF/Excel | Completo |
| Importación desde Excel (comando y web) | Completo |
| Auditoría | Completo |
| Despliegue con Docker sobre MariaDB | Completo |
| **Nov. 2026:** Encargado de Departamento crea matrices y edita las que creó | Completo |
| **Nov. 2026:** Historial de movimientos por área | Completo |
| **Nov. 2026:** Perfil con Seguridad de la cuenta | Completo |
| **Nov. 2026:** Ayuda y recorrido guiado por rol | Completo |
| **Nov. 2026:** Esta documentación técnica | Completo |
| **Nov. 2026:** Manual de usuario 1.1 (HTML y PDF) | Completo |

### Pendiente o por validar

- Nombres de las escalas de Probabilidad e Impacto: por validar con INFOTEP
  ([modelo-organizativo.md](../modelo-organizativo.md#pendiente-de-validar-con-infotep)).
- Proxies de confianza de Laravel si la institución termina HTTPS en un proxy
  propio ([despliegue.md § Pendiente](../despliegue.md#pendiente)).
- Avisos de `npm audit` en herramientas de desarrollo y construcción (no
  llegan a producción). Revisar al actualizar Nuxt.
- Pruebas de importación en paralelo (§ 12).

---

## 16. Cambios de este documento

| Fecha | Cambio |
|---|---|
| 12/11/2026 | Primera versión: consolida la documentación existente e incorpora la ampliación de noviembre de 2026 |
| 12/11/2026 | Cierre de la integración: prueba de punta a punta, verificación en MariaDB, manual 1.1 |
