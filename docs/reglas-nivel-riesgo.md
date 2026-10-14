# Regla del nivel de riesgo (matriz de calor)

El nivel de riesgo (Bajo, Moderado, Alto, Crítico) se obtiene de la **matriz de
calor**: una tabla de 4 × 4 celdas, una por cada combinación de Probabilidad
(1–4) e Impacto (1–4). Cada celda apunta a un nivel (`matriz_calor_celdas`).

El Administrador puede cambiar el nivel de cualquier celda desde la API
(`PUT /api/matriz-calor-celdas/{id}`). Lo que se describe aquí es solo el
**valor inicial** que crea `MatrizCalorCeldaSeeder`.

## Regla inicial: suma Probabilidad + Impacto

| P + I | Nivel    |
|-------|----------|
| 2–4   | Bajo     |
| 5–6   | Moderado |
| 7     | Alto     |
| 8     | Crítico  |

|            | I = 1 | I = 2    | I = 3    | I = 4    |
|------------|-------|----------|----------|----------|
| **P = 1**  | Bajo  | Bajo     | Bajo     | Moderado |
| **P = 2**  | Bajo  | Bajo     | Moderado | Moderado |
| **P = 3**  | Bajo  | Moderado | Moderado | Alto     |
| **P = 4**  | Moderado | Moderado | Alto  | Crítico  |

## Por qué esta regla

INFOTEP no dispone de una tabla oficial (confirmado con el responsable del
proyecto, oct. 2026). En los Excel actuales el nivel **no se calcula**: se
elige a mano de una lista desplegable (hoja oculta `Hoja1`).

Se analizaron las 15 filas con Probabilidad e Impacto numéricos de los cuatro
archivos entregados (Admisión, Compras, Formación Profesional CTIVPR y SFP):

| Regla candidata                                   | Filas que reproduce |
|---------------------------------------------------|---------------------|
| Producto P × I (≤3 Bajo, ≤6 Moderado, ≤9 Alto)    | 3 de 15             |
| **Suma P + I (tabla de arriba)**                  | **13 de 15**        |

Ninguna regla basada en el producto puede reproducir los datos: 1 × 4 y 2 × 2
dan el mismo producto (4), pero en el Excel uno es Moderado y el otro Bajo.

Las 2 filas que no encajan son de SFP: dos riesgos con P = 3, I = 4 clasificados
como **Moderado**, mientras Compras clasifica la misma combinación como
**Alto**. Se consideran ajustes manuales y se tratarán con el "ajuste manual
justificado" del RF-3 (Fase 4).

`tests/Feature/MatrizCalorReglaTest.php` fija la tabla completa y los casos
reales de los Excel.

## Volver a ejecutar los seeders

Los seeders de catálogos usan `firstOrCreate`: solo crean lo que falta. Volver a
ejecutarlos (por ejemplo, en un despliegue) **no** sobrescribe los ajustes que
el Administrador haya hecho en la matriz de calor, los nombres o los colores.
