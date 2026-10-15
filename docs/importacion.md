# Importación de matrices desde Excel (Fase 9, RF-7)

Carga inicial de las matrices que hoy viven en Excel con el formato
institucional RT-01-NI-ONA-156.

## Piezas

```text
LectorExcel          Lee el .xlsx: hojas visibles con el formato, encabezado y
                     una fila plana por riesgo (celdas combinadas resueltas,
                     texto enriquecido a texto plano, espacios normalizados).
        │
ImportadorMatrices   analizar(): traduce a datos del dominio y valida, sin
                     escribir nada → PlanImportacion (ediciones, errores, avisos).
                     ejecutar(): crea las ediciones con MatrizService,
                     RiesgoService y SeguimientoService en una sola transacción.
        │
  ┌─────┴──────────────┐
matrices:importar     pantalla web (Nuxt)
```

## Reglas

| Situación en el Excel | Qué hace la importación |
|---|---|
| Varias hojas visibles (Compras) | Una edición por hoja, en orden de fecha de revisión. La última queda **vigente** y las anteriores **archivadas**. Cada edición apunta a la anterior. |
| Hojas ocultas (`Hoja1` con la lista de niveles) u hojas sin el formato | Se ignoran. |
| Riesgo con varias sub-filas (celdas combinadas, SFP y CTIVPR) | **Un riesgo por sub-fila**, con la descripción, probabilidad, impacto y nivel del riesgo combinado. |
| Mismo riesgo en ediciones sucesivas | Se enlaza con el de la edición anterior (misma descripción) para la comparación de ediciones (RF-5). |
| Nivel del Excel distinto del que calcula la matriz de calor | Se importa como **ajuste manual** con la justificación de `config/importacion.php`. Aparece como aviso. |
| Nivel vacío | Se usa el calculado (aviso). |
| Columna Seguimiento/Evidencias con texto | Un **seguimiento** con fecha = fecha de revisión de la edición, autor = quien importa y el prefijo "Importado del Excel: ". |
| Fecha solo con mes ("JUNIO 2026") | Último día del mes. También acepta "30 Abril 2025", "15 de marzo de 2026" y "31/03/2026". |
| Nombre de la edición | "…Edición 3" → "Edición 3"; "ENERO-MARZO 2026" → "Enero-Marzo 2026"; hoja genérica ("Hoja1") → mes de revisión ("Abril 2025"). |
| Proceso del encabezado | No se usa (los encabezados no son fiables: el de Admisión dice "Formación Profesional"). El proceso se elige al importar; si no coincide, aviso. |

### Errores que detienen la importación

Nada se importa mientras haya un error; el informe indica hoja y fila.

- Factor, periodicidad o nivel que no existe en los catálogos (activos).
- Probabilidad o impacto vacíos o fuera de 1–4.
- Falta la actividad o la descripción; texto de más de 10 000 caracteres.
- Fecha de revisión ausente o irreconocible.
- El proceso ya tiene ediciones: la importación solo carga procesos sin
  historial, para no mezclar datos importados con ediciones existentes.

### Catálogos y alias

Los valores se buscan por nombre o slug, sin distinguir mayúsculas, acentos
ni el punto final. Las formas abreviadas se resuelven con alias en
`config/importacion.php` (`Pnal` → Personal, `Tec` → Tecnológico,
`Revisión cada tres meses` → Trimestral). Un valor nuevo se resuelve
**añadiéndolo al catálogo** (Administrador) o como alias; no hace falta tocar
código.

## Quién puede importar

Solo un **Administrador activo** (`MatrizPolicy::importar`): la importación
crea y publica ediciones de una vez. Todo lo creado queda en la auditoría a su
nombre, más un evento `importado` por edición con el archivo y la hoja.

## Comando

```bash
# Vista previa: muestra el plan, no escribe nada
php artisan matrices:importar ruta/archivo.xlsx --proceso=3 --usuario=admin@infotep.gob.do

# Importar
php artisan matrices:importar ruta/archivo.xlsx --proceso=3 --usuario=admin@infotep.gob.do --confirmar
```

## Estado de los archivos entregados (15/10/2026)

| Archivo | Resultado del análisis |
|---|---|
| Admisión | Válido: 1 edición ("Abril 2025"), 1 riesgo |
| Compras | Válido: 3 ediciones (Edición 3, Enero-Marzo 2026, Abril-Junio 2026) |
| CTIVPR / Los Centros | Válido: 1 edición, 4 riesgos (uno con 3 sub-filas) |
| SFP | Válido: 1 edición, 8 riesgos. 2 avisos de nivel (P 3 × I 4 = Moderado en el Excel → ajuste manual). La periodicidad "Por cada módulo" se agregó al catálogo (`PeriodicidadSeeder`, sin plazo). |
| Servicios Empresariales | **Pendiente de datos.** Se generó una plantilla vacía (ver abajo) para que el departamento la complete. |

## Plantilla para Servicios Empresariales

`~/Dev/recursos/PLANTILLA Matriz de Riesgo Servicios Empresariales.xlsx`,
generada a partir de la hoja "ABRIL-JUNIO 2026" de Compras (mismo formato
visual), con:

- Encabezado: Dirección "Regionales y Oficinas Satélites" y Proceso "SP2.2
  Asesoría y Asistencia Técnica a Empresas" (documento de requisitos;
  **a confirmar** con el departamento). Fecha de revisión vacía: la
  importación la exige.
- Filas 6–20 vacías. Sin datos inventados: los riesgos los aporta el
  departamento.
- Validaciones propias (los Excel originales apuntaban a un archivo externo
  en `H:\RIESGOS\…` y su lista de niveles daba `#REF!`): Probabilidad e
  Impacto 1–4, lista de niveles, y listas de factores y periodicidades del
  catálogo (admiten otros valores con aviso, p. ej. "Pnal / Tec").
- Hoja "Instrucciones" (la importación la ignora).

La cabecera de columnas puede ocupar una fila o dos combinadas: el lector
admite tanto los Excel institucionales como los que exporta el sistema.
