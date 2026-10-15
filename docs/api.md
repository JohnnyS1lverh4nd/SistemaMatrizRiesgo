# API REST — documentación

Entregable del documento de requisitos ("API documentada y funcional").

| Archivo | Contenido |
|---|---|
| `docs/api/SistemaMatrizRiesgo.postman_collection.json` | Colección de Postman: 68 peticiones en 12 carpetas, con descripción, permisos, ejemplos y pruebas |
| `docs/api/ejemplo-importacion.xlsx` | Excel de ejemplo (datos ficticios) para la importación |
| `docs/api/ejemplo-evidencia.pdf` | PDF de ejemplo para adjuntar como evidencia |

## Cómo está hecha la API

- **Mismo dominio** que la SPA: la API en `/api`, la sesión en `/login`,
  `/logout`, `/sanctum/csrf-cookie` (Fortify y Sanctum).
- **Autenticación:** Laravel Sanctum en modo SPA — **cookies de sesión**, no
  tokens. Toda petición que modifica datos lleva la cabecera `X-XSRF-TOKEN`
  con el valor de la cookie `XSRF-TOKEN` (protección CSRF), y `Referer` del
  propio dominio.
- **Autorización:** cada endpoint comprueba el rol y el alcance por
  departamento (Policies). La columna "Quién puede" de la tabla lo resume.
- **Respuestas JSON.** Errores: `401` sin sesión · `403` sin permiso (con el
  motivo) · `404` no existe · `419` sesión o token CSRF caducados ·
  `422` validación (`errors` por campo, en español) · `429` demasiadas
  peticiones.
- **Límites por usuario:** 300 peticiones/minuto; exportar e importar 20/minuto;
  login y recuperación de contraseña 5/minuto.
- **Sesión:** caduca a los 4 minutos de inactividad.

## Usar la colección en Postman

1. *Import* → `docs/api/SistemaMatrizRiesgo.postman_collection.json`.
2. En la colección, pestaña **Variables**: `baseUrl` (p. ej.
   `https://localhost:8443`), `email` y `password` de un **Administrador**.
   La contraseña no se guarda en el repositorio.
3. Con el certificado autofirmado de pruebas: *Settings → General →
   SSL certificate verification* desactivado.
4. Ejecute **1. Autenticación → Cookie CSRF** y después **Iniciar sesión**.
   Postman conserva las cookies; el resto de peticiones ya funciona.
5. Para los ejemplos con archivo (seguimiento, importación), seleccione en el
   cuerpo los archivos de `docs/api/`.

Las carpetas siguen el orden de uso y cada petición guarda los identificadores
que usa la siguiente: con **Run collection** se recorre todo el sistema.
**Crea datos de ejemplo**: ejecútela solo en un entorno de pruebas.

## Ejecutarla por consola (Newman)

```bash
cd docs/api
npx -y newman@6 run SistemaMatrizRiesgo.postman_collection.json \
  --insecure --working-dir . \
  --env-var "baseUrl=https://localhost:8443" \
  --env-var "email=admin@dominio" --env-var "password=********"
```

Resultado el 15/10/2026 contra los contenedores de prueba: **68 peticiones,
74 comprobaciones, 0 fallos**.

## Endpoints

| Carpeta | Método | Ruta | Para qué | Quién puede |
|---|---|---|---|---|
| Autenticación | `GET` | `/sanctum/csrf-cookie` | Entrega la cookie XSRF-TOKEN. | Público |
| Autenticación | `POST` | `/login` | Inicia sesión con correo y contraseña (cookie de sesión). | Público |
| Autenticación | `GET` | `/api/user` | Datos del usuario con sesión, su rol, departamentos y `meta.sesion_minutos` (inactividad permitida). | Cualquier usuario autenticado |
| Usuarios y roles | `GET` | `/api/roles` | Lista de roles para asignar a los usuarios. | Administrador |
| Usuarios y roles | `GET` | `/api/users` | Todos los usuarios (activos e inactivos). | Administrador |
| Usuarios y roles | `POST` | `/api/users` | Crea un usuario con rol y departamentos. | Administrador |
| Usuarios y roles | `GET` | `/api/users/{user_id}` | Un usuario con su rol y departamentos. | Administrador |
| Usuarios y roles | `PUT` | `/api/users/{user_id}` | Cambios parciales: solo se validan los campos enviados. | Administrador |
| Departamentos y procesos | `GET` | `/api/departamentos` | Departamentos visibles para el usuario. | Todos los roles (según su alcance) |
| Departamentos y procesos | `POST` | `/api/departamentos` | El `slug` se genera del nombre si no se envía. | Administrador |
| Departamentos y procesos | `GET` | `/api/departamentos/{departamento_id}` | Un departamento. | Todos los roles (según su alcance) |
| Departamentos y procesos | `PUT` | `/api/departamentos/{departamento_id}` | Sin borrado: se desactiva con `activo: false`. | Administrador |
| Departamentos y procesos | `GET` | `/api/departamentos/{departamento_id}/procesos` | Procesos de un departamento. | Todos los roles (según su alcance) |
| Departamentos y procesos | `POST` | `/api/departamentos/{departamento_id}/procesos` | Proceso nuevo en un departamento activo. | Administrador |
| Departamentos y procesos | `POST` | `/api/departamentos/{departamento_id}/procesos` | Segundo proceso, sin ediciones, para el ejemplo de importación. | Administrador |
| Departamentos y procesos | `GET` | `/api/procesos/{proceso_id}` | Un proceso; `permisos` indica qué puede hacer el usuario (crear ediciones, importar). | Todos los roles (según su alcance) |
| Departamentos y procesos | `PUT` | `/api/procesos/{proceso_id}` | Sin borrado: se desactiva con `activo: false` (deja de contar en los reportes). | Administrador |
| Catálogos | `GET` | `/api/factores-riesgo` | Factores de riesgo. | Todos los roles (según su alcance) |
| Catálogos | `POST` | `/api/factores-riesgo` | Factor nuevo. | Administrador |
| Catálogos | `GET` | `/api/factores-riesgo/{factor_nuevo_id}` | Un factor. | Todos los roles (según su alcance) |
| Catálogos | `PUT` | `/api/factores-riesgo/{factor_nuevo_id}` | Los catálogos no se borran: se desactivan. | Administrador |
| Catálogos | `GET` | `/api/periodicidades` | Periodicidades; `frecuencia_dias` marca cuándo un seguimiento está atrasado. | Todos los roles (según su alcance) |
| Catálogos | `POST` | `/api/periodicidades` | Periodicidad nueva (`frecuencia_dias` opcional: sin plazo). | Administrador |
| Catálogos | `GET` | `/api/periodicidades/{periodicidad_nueva_id}` | Una periodicidad. | Todos los roles (según su alcance) |
| Catálogos | `PUT` | `/api/periodicidades/{periodicidad_nueva_id}` | Se desactiva en lugar de borrarse. | Administrador |
| Catálogos | `GET` | `/api/niveles-riesgo` | Bajo, Moderado, Alto y Crítico. | Todos los roles (según su alcance) |
| Catálogos | `GET` | `/api/niveles-riesgo/{nivel_id}` | Un nivel. | Todos los roles (según su alcance) |
| Catálogos | `PUT` | `/api/niveles-riesgo/{nivel_id}` | Cambia nombre o color (#RRGGBB). | Administrador |
| Catálogos | `GET` | `/api/escalas-nivel` | Nombres de los valores 1–4 de Probabilidad e Impacto. | Todos los roles (según su alcance) |
| Catálogos | `GET` | `/api/escalas-nivel/{escala_id}` | Un valor de escala. | Todos los roles (según su alcance) |
| Catálogos | `PUT` | `/api/escalas-nivel/{escala_id}` | Cambia el nombre de un valor. | Administrador |
| Catálogos | `GET` | `/api/matriz-calor-celdas` | Las 16 celdas Probabilidad × Impacto con su nivel. | Todos los roles (según su alcance) |
| Catálogos | `GET` | `/api/matriz-calor-celdas/{celda_id}` | Una celda. | Todos los roles (según su alcance) |
| Catálogos | `PUT` | `/api/matriz-calor-celdas/{celda_id}` | Asigna otro nivel a una combinación. | Administrador |
| Matrices y riesgos | `GET` | `/api/procesos/{proceso_id}/matrices` | Ediciones de la matriz de un proceso, con `permisos` por edición. | Todos los roles (según su alcance) |
| Matrices y riesgos | `POST` | `/api/procesos/{proceso_id}/matrices` | Edición nueva en borrador. | Administrador y Encargado de Departamento (sus departamentos) |
| Matrices y riesgos | `GET` | `/api/matrices/{matriz_id}` | Una edición. | Todos los roles (según su alcance) |
| Matrices y riesgos | `PUT` | `/api/matrices/{matriz_id}` | Solo en borrador. | Administrador y Encargado de Departamento (sus departamentos) |
| Matrices y riesgos | `POST` | `/api/matrices/{matriz_id}/riesgos` | Fila de la matriz con los 13 campos. | Administrador y Encargado de Departamento (sus departamentos) |
| Matrices y riesgos | `POST` | `/api/matrices/{matriz_id}/riesgos` | Segundo riesgo, para el ejemplo de eliminación. | Administrador y Encargado de Departamento (sus departamentos) |
| Matrices y riesgos | `GET` | `/api/matrices/{matriz_id}/riesgos` | Riesgos en orden, con nivel calculado, ajustado y efectivo. | Todos los roles (según su alcance) |
| Matrices y riesgos | `GET` | `/api/riesgos/{riesgo_id}` | Un riesgo. | Todos los roles (según su alcance) |
| Matrices y riesgos | `PUT` | `/api/riesgos/{riesgo_id}` | Cambios parciales en un borrador; el nivel se recalcula. | Administrador y Encargado de Departamento (sus departamentos) |
| Matrices y riesgos | `DELETE` | `/api/riesgos/{riesgo_descartar_id}` | Eliminación lógica, solo en borradores. | Administrador y Encargado de Departamento (sus departamentos) |
| Matrices y riesgos | `POST` | `/api/matrices/{matriz_id}/publicar` | Borrador → vigente (la vigente anterior se archiva). | Administrador y Encargado de Departamento (sus departamentos) |
| Matrices y riesgos | `POST` | `/api/matrices/{matriz_id}/clonar` | Borrador nuevo con copia de los riesgos, enlazados con sus originales (comparación). | Administrador y Encargado de Departamento (sus departamentos) |
| Matrices y riesgos | `GET` | `/api/matrices/{matriz_id}/comparar/{matriz_clon_id}` | Riesgos agregados, cerrados y cambios de nivel entre dos ediciones del mismo proceso (RF-5). | Todos los roles (según su alcance) |
| Matrices y riesgos | `DELETE` | `/api/matrices/{matriz_clon_id}` | Solo borradores nunca publicados (eliminación lógica). | Administrador y Encargado de Departamento (sus departamentos) |
| Matrices y riesgos | `POST` | `/api/matrices/{matriz_id}/archivar` | Vigente → archivada (el proceso queda sin vigente). | Administrador y Encargado de Departamento (sus departamentos) |
| Seguimiento y evidencias | `POST` | `/api/riesgos/{riesgo_id}/seguimientos` | Avance con fecha (no futura) y descripción; puede cambiar el estado del tratamiento (pendiente / en_progreso / cumplido) y adjuntar hasta 5 archivos (`archivos[]`: PDF, imágenes o Excel, 10 MB). | Administrador y Encargados (sus departamentos) |
| Seguimiento y evidencias | `GET` | `/api/riesgos/{riesgo_id}/seguimientos` | Histórico del riesgo, más reciente primero (incluye anulados, marcados). | Todos los roles (según su alcance) |
| Seguimiento y evidencias | `GET` | `/api/seguimientos/{seguimiento_id}` | Un seguimiento con sus evidencias. | Todos los roles (según su alcance) |
| Seguimiento y evidencias | `GET` | `/api/evidencias/{evidencia_id}/descargar` | Archivo adjunto (disco privado; solo con permiso sobre el riesgo). | Todos los roles (según su alcance) |
| Seguimiento y evidencias | `POST` | `/api/seguimientos/{seguimiento_id}/anular` | El historial no se edita: un error se anula con motivo y queda visible como anulado. | Administrador y Encargados (sus departamentos) |
| Importación y exportación | `POST` | `/api/procesos/{proceso_importacion_id}/importacion/vista-previa` | Analiza un Excel institucional (RT-01-NI-ONA-156) sin guardar nada: ediciones, errores (con hoja y fila) y avisos. | Administrador |
| Importación y exportación | `POST` | `/api/procesos/{proceso_importacion_id}/importacion` | Importa el mismo archivo (se vuelve a analizar). | Administrador |
| Importación y exportación | `GET` | `/api/matrices/{matriz_id}/exportar/pdf` | PDF con el formato institucional y el logo. | Todos los roles (según su alcance) |
| Importación y exportación | `GET` | `/api/matrices/{matriz_id}/exportar/excel` | Excel con la hoja de la plantilla institucional y el logo; se puede volver a importar. | Todos los roles (según su alcance) |
| Reportes y auditoría | `GET` | `/api/dashboard` | Totales por nivel, mapa de calor, estados del tratamiento, prioritarios, atrasados y consolidado por departamento (solo ediciones vigentes, procesos activos). | Todos los roles (según su alcance) |
| Reportes y auditoría | `GET` | `/api/reportes/riesgos` | Riesgos con filtros, más graves primero, 50 por página. | Todos los roles (según su alcance) |
| Reportes y auditoría | `GET` | `/api/auditoria` | Registro de cambios (quién, qué, cuándo), paginado. | Administrador y Auditor |
| Reportes y auditoría | `GET` | `/api/matrices/{matriz_id}/historial` | Cambios de la edición y de sus riesgos. | Todos los roles (según su alcance) |
| Reportes y auditoría | `GET` | `/api/riesgos/{riesgo_id}/historial` | Cambios de un riesgo (incluye factores y seguimientos). | Todos los roles (según su alcance) |
| Mi contraseña | `PUT` | `/user/password` | Exige la contraseña actual. | Cualquier usuario autenticado |
| Cerrar sesión | `POST` | `/logout` | Termina la sesión. | Cualquier usuario autenticado |
| Cerrar sesión | `GET` | `/api/user` | Sin sesión, la API responde 401. | Público |
| Recuperar contraseña (sin sesión) | `POST` | `/forgot-password` | Solo sin sesión (con sesión, Fortify redirige). | Público |
| Recuperar contraseña (sin sesión) | `POST` | `/reset-password` | Con el `token` y el `email` del enlace recibido por correo. | Público |

Rutas de Fortify que existen en Laravel pero **no usa la aplicación** ni
publica Nginx en producción: `POST /user/confirm-password` y
`GET /user/confirmed-password-status`.

## Mantener la colección

La colección y la tabla de endpoints salen de una sola definición,
`docs/api/generar-coleccion.py` (lista `R`). Al añadir o cambiar un endpoint:

1. Edite la lista `R` (ruta, método, quién puede, ejemplo, prueba).
2. `python3 docs/api/generar-coleccion.py .` → regenera la colección y escribe
   la tabla en `docs/api/.tabla.md`; cópiela en la sección "Endpoints".
3. Vuelva a ejecutar Newman.

`php artisan route:list --path=api` muestra todas las rutas registradas.
