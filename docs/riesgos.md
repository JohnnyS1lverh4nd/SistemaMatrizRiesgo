# Riesgos (Fase 4)

Cada riesgo es una fila de una edición de matriz, con las columnas del Excel
institucional (RF-3).

| Columna del Excel     | Campo                         | Notas |
|-----------------------|-------------------------------|-------|
| Actividad             | `actividad`                   | Obligatorio |
| Riesgos               | `descripcion`                 | Obligatorio |
| Fáctor                | `factores` (varios)           | Tabla `riesgo_factor`; "Pnal / Tec" = Personal + Tecnológico |
| Causas                | `causas`                      | |
| Control actual        | `control_actual`              | Texto, como en el Excel |
| Probabilidad          | `probabilidad` (1–4)          | Se evalúa junto con el impacto |
| Impacto               | `impacto` (1–4)               | |
| Nivel de riesgo       | `nivel_calculado` / `nivel_ajustado` / `nivel` | Ver abajo |
| Síntoma               | `sintoma`                     | |
| Tratamiento           | `tratamiento`                 | Texto + `estado_tratamiento` (RF-4) |
| Responsable           | `responsable`                 | Texto libre (unidades o cargos) |
| Fecha ó Periodicidad  | `periodicidad`                | Catálogo |
| Seguimiento/Evidencias| —                             | Entidad propia (Fase 6) |

Decisiones confirmadas con el responsable del proyecto (oct. 2026): control
actual, tratamiento, responsable y periodicidad son campos del riesgo;
responsable es texto libre; quien edita el riesgo puede ajustar el nivel con
justificación.

## Nivel de riesgo

- **Calculado** (`nivel_calculado`): lo fija `CalculadoraNivelRiesgo` con la
  matriz de calor; nunca llega desde la petición. Se **guarda** en el riesgo:
  si después cambia la matriz de calor, las ediciones publicadas conservan su
  nivel. En un borrador se recalcula cada vez que se guarda.
- **Ajustado** (`nivel_ajustado` + `justificacion_ajuste`, RF-3): opcional.
  Exige justificación, que el riesgo esté evaluado y que sea distinto del
  calculado.
- **Efectivo** (`nivel`): el ajustado si existe; si no, el calculado.

## Riesgos sin evaluar

Probabilidad e impacto pueden quedar **ambos** vacíos mientras la edición es
borrador (algunos riesgos de los Excel no tienen evaluación). **No se puede
publicar** una edición con riesgos sin evaluar (422 en `riesgos`).

## Cuándo se pueden modificar

| Edición   | Crear / editar / eliminar riesgos | Estado del tratamiento |
|-----------|-----------------------------------|------------------------|
| Borrador  | Sí                                | Sí                     |
| Vigente   | No                                | Sí (seguimiento, Fase 6) |
| Archivada | No                                | No                     |

`RiesgoPolicy` delega en los permisos de la edición (`MatrizPolicy`), y el
modelo `Riesgo` rechaza con `LogicException` cualquier otro cambio aunque un
flujo futuro olvide la Policy. Eliminar es lógico (`deleted_at`).

## Nueva edición a partir de otra

`MatrizService::clonar` copia los riesgos no eliminados con sus factores,
ajuste y estado de tratamiento. Cada copia guarda `riesgo_origen_id` (el
riesgo original) para comparar ediciones (RF-5). El nivel calculado se
recalcula con la matriz de calor vigente. Los seguimientos no se copian.

## API

| Método y ruta                         | Acción |
|---------------------------------------|--------|
| `GET /api/matrices/{id}/riesgos`      | Riesgos de la edición, en orden |
| `POST /api/matrices/{id}/riesgos`     | Nuevo riesgo (solo borrador) |
| `GET /api/riesgos/{id}`               | Detalle (incluye `permisos`) |
| `PUT /api/riesgos/{id}`               | Edición parcial (solo borrador) |
| `DELETE /api/riesgos/{id}`            | Eliminación lógica (solo borrador) |

`factor_ids` reemplaza la lista completa de factores cuando se envía.
Probabilidad e impacto se envían juntos.
