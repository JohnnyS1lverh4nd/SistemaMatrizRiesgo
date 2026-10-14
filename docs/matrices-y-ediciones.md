# Matrices y ediciones (Fase 3)

Cada fila de `matrices` es una **edición** de la matriz de riesgos de un
proceso, igual que cada pestaña de los Excel ("Edición 3", "Enero-Marzo 2026",
"Abril-Junio 2026").

```
Proceso ──< Matriz (edición) ──< Riesgos (Fase 4)
              └─ edicion_anterior_id: edición de la que se clonó
```

## Ciclo de vida

```
borrador ──publicar──> vigente ──archivar──> archivada
   │
   └─ descartar (eliminación lógica, solo si nunca se publicó)
```

| Estado    | Metadatos y riesgos | Seguimiento (Fase 6) | Eliminar |
|-----------|---------------------|----------------------|----------|
| Borrador  | Editables           | —                    | Descartar (soft delete) |
| Vigente   | **Fijos**           | Sí                   | No       |
| Archivada | Fijos               | No                   | No       |

Reglas confirmadas con el responsable del proyecto (oct. 2026):

- **Una sola edición vigente por proceso.** Publicar un borrador archiva la
  vigente anterior en la misma transacción (`MatrizService::publicar`).
- **Para reevaluar** (cambiar probabilidad, impacto, tratamiento…) se **clona**
  una edición nueva, como hoy se copia la pestaña del Excel. Cualquier edición,
  también una archivada, sirve como punto de partida; el origen no se modifica.
- **Gestionan** el Administrador (todos los departamentos) y el Encargado de
  Departamento (solo los suyos). Encargado de Seguimiento, Auditor y Dirección
  solo consultan, según su alcance.

## Garantías

- La Policy (`MatrizPolicy`) aplica las reglas de estado **también al
  Administrador** (no usa `before()`).
- El modelo `Matriz` rechaza con `LogicException` cualquier cambio en una
  edición vigente o archivada, y cualquier transición de estado distinta de
  borrador → vigente → archivada, aunque un flujo futuro olvide la Policy.
- `estado`, `proceso_id` y `edicion_anterior_id` están prohibidos en las
  peticiones (422): solo cambian mediante publicar, archivar y clonar.
- El nombre de la edición es único por proceso entre las no descartadas.

## API

| Método y ruta                              | Acción                              |
|--------------------------------------------|-------------------------------------|
| `GET /api/procesos/{id}/matrices` (`?estado=`) | Ediciones del proceso, más recientes primero |
| `POST /api/procesos/{id}/matrices`         | Nueva edición en borrador           |
| `GET /api/matrices/{id}`                   | Detalle (incluye `permisos` para la interfaz) |
| `PUT /api/matrices/{id}`                   | Editar nombre / fecha (solo borrador) |
| `DELETE /api/matrices/{id}`                | Descartar borrador                  |
| `POST /api/matrices/{id}/publicar`         | Borrador → vigente                  |
| `POST /api/matrices/{id}/archivar`         | Vigente → archivada                 |
| `POST /api/matrices/{id}/clonar`           | Nuevo borrador a partir de esta     |

## Pendiente (Fase 4)

`MatrizService::clonar` copiará los riesgos del origen, enlazando cada copia con
su riesgo original para poder comparar ediciones (RF-5).
