API REST del Sistema de Gestión de Matrices de Riesgo (INFOTEP · DRO).

**Antes de empezar** (ver `docs/api.md`):

1. Edite las variables de la colección: `baseUrl`, `email` y `password` (un Administrador).
2. Con el certificado autofirmado de pruebas, desactive *Settings → SSL certificate verification* en Postman.
3. Ejecute **1. Autenticación → Iniciar sesión**. La sesión queda en las cookies de Postman.

**Autenticación:** Laravel Sanctum en modo SPA (cookies de sesión, no tokens).
El script de la colección pide la cookie `XSRF-TOKEN` antes de cada petición
que modifica datos y la envía en la cabecera `X-XSRF-TOKEN`.

**Recorrido:** las carpetas siguen el orden de uso. Cada petición guarda los
identificadores que usa la siguiente (`departamento_id`, `matriz_id`, …) y
comprueba su resultado: se puede ejecutar completa con *Run collection* o
con Newman. Crea datos de ejemplo: ejecútela en un entorno de pruebas, no en
producción.

**Respuestas:** JSON. Errores de validación → 422 con `errors` por campo;
sin sesión → 401; sin permiso → 403; demasiadas peticiones → 429.
