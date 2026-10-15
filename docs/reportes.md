# Dashboard y reportes (Fase 8)

Decisiones confirmadas con el responsable del proyecto (oct. 2026):

- El **dashboard cuenta solo ediciones vigentes** (situación actual). Los
  borradores nunca cuentan.
- **Todos los roles** ven el dashboard y los reportes, **según su alcance**:
  los encargados, sus departamentos; Dirección, Auditor y Administrador, todos.
  Filtrar por un departamento o proceso fuera del alcance responde 403.
- **Atrasos** por días de la periodicidad (ver abajo).
- **Exportación** a Excel y PDF generada en el backend.

Dashboard, reporte y exportación usan la misma consulta
(`App\Services\Reportes\ConsultaRiesgos`): cuentan exactamente lo mismo.

## Nivel que se cuenta

El **efectivo**: el ajustado manualmente si existe; si no, el calculado.

## Atraso de seguimiento

Un riesgo está atrasado si:

1. su edición está **vigente**,
2. su tratamiento **no está cumplido**,
3. su periodicidad tiene plazo (`frecuencia_dias`; "Cuando ocurra" no tiene), y
4. no hay seguimiento **no anulado** en ese plazo, contado desde el último
   seguimiento o, si no hay ninguno, desde la publicación de la edición.

Plazos iniciales (editables en Catálogos → Periodicidades): Diario 1, Mensual
30, Trimestral 90, Cuatrimestral 120, Anual 365 días.

## Filtros (RF-6)

`departamento_id`, `proceso_id`, `factor_id`, `nivel_id` (efectivo),
`responsable` (texto parcial), `estado_tratamiento`, `prioritarios` (Alto o
Crítico con tratamiento no cumplido), `atrasados`, y periodo `desde` /
`hasta`. Con periodo se incluyen las ediciones vigentes y **archivadas** cuya
fecha de revisión cae en el rango.

## Comparación de ediciones (RF-5)

`GET /api/matrices/{base}/comparar/{nueva}` (mismo proceso):

- **Agregados:** riesgos de la nueva sin antecesor en la base.
- **Cerrados:** riesgos de la base que no continúan en la nueva.
- **Cambios de nivel:** pares cuyo nivel efectivo sube o baja.

El vínculo es `riesgo_origen_id` (se fija al crear una edición a partir de
otra). Se sigue la cadena completa, así que también se pueden comparar
ediciones no consecutivas (Enero-Marzo con Octubre-Diciembre).

## Exportación con formato institucional

`App\Services\Exportacion\DatosMatriz` arma el contenido de una edición igual
que los Excel originales: encabezado (MATRIZ DE RIESGOS, código
RT-01-NI-ONA-156 + edición del formulario, Dirección, Proceso, fecha de
revisión, edición), los tres grupos de columnas (Identificación, Análisis,
Seguimiento y Control) y una fila por riesgo. PDF y Excel dibujan exactamente
esos datos.

- **PDF** (`GET /api/matrices/{id}/exportar/pdf`): DomPDF, carta horizontal,
  encabezado de columnas repetido en cada página y "Página X de Y". Los
  borradores llevan la marca "BORRADOR". Configuración restrictiva: sin
  recursos remotos ni PHP incrustado; todo el texto se escapa.
- **Excel** (`GET /api/matrices/{id}/exportar/excel`): PhpSpreadsheet, `.xlsx`
  con la misma distribución (título y código, Dirección/Proceso/fecha, grupos,
  columnas y un riesgo por fila desde la fila 6). El nivel lleva el color de la
  matriz de calor; encabezados inmovilizados y repetidos al imprimir (carta
  horizontal, ajustado al ancho). Los borradores llevan "BORRADOR" en la fila 3.
  Todo el texto se escribe como cadena explícita: un valor que empiece por `=`
  nunca se ejecuta como fórmula (*formula injection*). Requiere las extensiones
  PHP `gd` e `intl` (también en producción).
- La columna Seguimiento/Evidencias lista los seguimientos válidos (nunca los
  anulados) con su fecha.
- Cada exportación queda en la auditoría (`evento = exportado`).
- Quien puede ver la edición puede exportarla.

## API

| Método y ruta | Contenido |
|---------------|-----------|
| `GET /api/dashboard` (`departamento_id?`) | Totales por nivel, mapa de calor 4×4, estados del tratamiento, prioritarios pendientes, atrasados y consolidado por departamento |
| `GET /api/reportes/riesgos` (filtros) | Riesgos con su contexto y estado de seguimiento; más graves primero; 50 por página |
| `GET /api/matrices/{id}/comparar/{otra}` | Comparación de dos ediciones |
| `GET /api/matrices/{id}/exportar/pdf` | PDF con formato institucional |
| `GET /api/matrices/{id}/exportar/excel` | Excel (.xlsx) con formato institucional |
