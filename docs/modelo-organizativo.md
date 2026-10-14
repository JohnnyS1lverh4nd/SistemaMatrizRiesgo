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
- Las filas sin Probabilidad/Impacto en CTIVPR y SFP (se tratarán en la
  importación, Fase 9).
