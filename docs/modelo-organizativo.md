# Modelo organizativo y catálogos (Fase 2)

Decisiones tomadas tras analizar las cuatro matrices Excel entregadas
(Admisión, Compras, Formación Profesional CTIVPR / Los Centros y SFP) y
confirmadas con el responsable del proyecto (oct. 2026).

## Estructura

```
Departamento (unidad responsable; usuarios asignados)
  └── Proceso (nombre del proceso del encabezado de la matriz)
        └── Matriz de riesgo, por edición/periodo (Fase 3)
```

- **Departamento**: unidad que gestiona matrices y a la que se asignan los
  encargados (`user_departamento`). Guarda la **Dirección** del encabezado.
- **Proceso**: pertenece a un Departamento. Un mismo nombre de proceso puede
  existir en varias unidades: "Formación Profesional" aparece en Admisión,
  CTIVPR y SFP, por eso el slug es único **por departamento**.
- **Centros**: no se modelan. El archivo "Los Centros" usa el mismo encabezado
  que la Dirección de Formación Profesional; los centros comparten información.
- **Ediciones**: cada pestaña del Excel es una edición o periodo ("Edición 3",
  "ENERO-MARZO 2026", "ABRIL-JUNIO 2026"). Se versionarán en la Fase 3.

## API

| Endpoint                                         | Quién                                      |
|--------------------------------------------------|--------------------------------------------|
| `GET /api/departamentos` (`?activo=1\|0`)         | Todos; los encargados solo ven los suyos   |
| `GET /api/departamentos/{id}`                    | Quien pueda ver ese departamento           |
| `POST /api/departamentos`, `PUT /api/departamentos/{id}` | Administrador                      |
| `GET /api/departamentos/{id}/procesos` (`?activo`) | Quien pueda ver el departamento          |
| `GET /api/procesos/{id}`                         | Quien pueda ver su departamento            |
| `POST /api/departamentos/{id}/procesos`, `PUT /api/procesos/{id}` | Administrador             |

- No hay `DELETE`: se retira con `{ "activo": false }`.
- `slug` es opcional al crear (se genera del nombre); en edición solo cambia si
  se envía. En procesos es único **dentro** del departamento.
- Un proceso no cambia de departamento (`departamento_id` se ignora al editar)
  y no se crean procesos en departamentos inactivos.
- La visibilidad de un proceso se delega en `DepartamentoPolicy::view`, y los
  listados usan `Departamento::visiblesPara()`: una sola regla de alcance.

## Código del formulario

`RT-01-NI-ONA-156` ("Edición 1") es idéntico en todos los archivos: es el
código del **formulario institucional**, no de cada departamento. Está en
`config/matrices.php` (variables `MATRIZ_FORMULARIO_CODIGO` y
`MATRIZ_FORMULARIO_EDICION`) y se usará en el encabezado de las exportaciones.

## Catálogos

| Catálogo          | Editable por el Administrador        | Se puede borrar |
|-------------------|--------------------------------------|-----------------|
| Factores de riesgo| Crear, editar, **desactivar**         | No              |
| Periodicidades    | Crear, editar, **desactivar**         | No              |
| Niveles de riesgo | Solo nombre y color (4 niveles fijos) | No              |
| Escalas P / I     | Solo nombre y descripción (1–4 fijos) | No              |
| Matriz de calor   | Nivel de cada una de las 16 celdas    | No              |

Nada se borra físicamente: un factor o periodicidad retirado sigue existiendo
para los riesgos históricos que lo usan. Los formularios piden `?activo=1`.

### Factores

El catálogo solo tiene factores **simples** (Administrativo, Personal,
Tecnológico, Político-social-ambiental, Interno, Externo). Los valores
combinados de los Excel ("Pnal / Tec", "Personal/Administrativo") se
representarán marcando **varios factores** en el mismo riesgo (relación muchos
a muchos, Fase 4).

### Periodicidades

Se añadió **Trimestral**: "Revisión cada tres meses" es la periodicidad más
frecuente en los Excel (6 de 15 riesgos).

## Pendiente de validar con INFOTEP

- Nombres de las escalas de Probabilidad e Impacto (los Excel solo usan 1–4).
- ~~Las filas sin Probabilidad/Impacto en CTIVPR y SFP~~: no son riesgos sin
  evaluar, sino sub-filas de celdas combinadas (ver `docs/importacion.md`).
