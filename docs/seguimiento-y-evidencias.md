# Seguimiento y evidencias (Fase 6)

Equivale a la columna "Seguimiento/Evidencias" del Excel, pero como
**histórico**: cada entrada queda registrada con fecha, descripción y usuario,
y no se reescribe (RF-4).

La Fase 5 (controles y tratamiento) se dio por cubierta: control actual,
tratamiento, responsable y periodicidad son campos del riesgo (Fase 4); el
estado del tratamiento cambia con el seguimiento.

## Reglas (confirmadas con el responsable del proyecto, oct. 2026)

- **Quién registra:** el Encargado de Seguimiento y el Encargado de
  Departamento en sus departamentos, y el Administrador. Auditor y Dirección
  consultan el histórico.
- **Dónde:** solo en riesgos de ediciones **vigentes**. En un borrador aún no
  hay nada que seguir; una edición archivada es inmutable.
- **El usuario** es siempre el de la sesión (`user_id` en la petición → 422).
- **Estado del tratamiento** (pendiente / en progreso / cumplido): un
  seguimiento puede cambiarlo; se guardan el estado anterior y el nuevo.
- **Sin edición ni borrado.** Un registro equivocado se **anula con motivo**:
  sigue visible en el histórico. Si era el último cambio de estado del
  tratamiento, el riesgo vuelve al estado anterior.
- **Evidencias:** PDF, JPG/PNG y XLSX/XLS, hasta 10 MB cada una y 5 por
  seguimiento. El tipo se comprueba por el **contenido real** del archivo (un
  script renombrado `.pdf` se rechaza).

## Almacenamiento de evidencias

- Disco `local` → `storage/app/private/evidencias/{riesgo_id}/`, con nombre
  aleatorio; el nombre original solo se guarda como dato. Carpeta ignorada
  por Git.
- `serve` desactivado en `config/filesystems.php`: no existe ninguna URL
  directa a los archivos. La única vía es
  `GET /api/evidencias/{id}/descargar`, que comprueba que el usuario puede ver
  el riesgo y responde siempre como descarga con `X-Content-Type-Options: nosniff`.
- Se guarda la huella `sha256` de cada archivo para poder verificar que no se
  alteró.
- Si el registro falla a mitad, los archivos ya escritos se borran (no quedan
  huérfanos).
- **Incluir `storage/app/private` en las copias de seguridad** junto con la base
  de datos.

## Límites de PHP (obligatorio para archivos de más de 2 MB)

PHP limita el tamaño de subida por defecto (`upload_max_filesize = 2M`,
`post_max_size = 8M`). Para 5 archivos de 10 MB, en el `php.ini` del servidor:

```ini
upload_max_filesize = 10M
post_max_size = 55M
```

Si se usa Nginx, también `client_max_body_size 55m;`.

## API

| Método y ruta                                  | Acción |
|------------------------------------------------|--------|
| `GET /api/riesgos/{id}/seguimientos`           | Histórico del riesgo, más reciente primero |
| `POST /api/riesgos/{id}/seguimientos`          | Registrar (multipart: `fecha`, `descripcion`, `estado_tratamiento?`, `archivos[]?`) |
| `GET /api/seguimientos/{id}`                   | Detalle |
| `POST /api/seguimientos/{id}/anular`           | Anular con `motivo` |
| `GET /api/evidencias/{id}/descargar`           | Descargar una evidencia |
