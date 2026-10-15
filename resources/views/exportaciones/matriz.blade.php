{{--
    Matriz de riesgos con el formato de la plantilla institucional
    RT-01-NI-ONA-156 (mismos colores y estructura que el Excel exportado).
    Todo el texto usa {{ }} (escapado): el contenido lo escriben usuarios.
--}}
@php($formato = \App\Services\Exportacion\FormatoInstitucional::class)
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Matriz de riesgos · {{ $encabezado['proceso'] }} · {{ $encabezado['edicion'] }}</title>
<style>
    @page { margin: 22px 22px 32px 22px; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 6.5pt; color: #000000; }
    table { width: 100%; border-collapse: collapse; table-layout: fixed; }
    th, td { border: 0.7px solid #000000; padding: 3px; vertical-align: middle; word-wrap: break-word; }
    /* Fila invisible con los anchos: DomPDF los toma de la primera fila. */
    tr.anchos td { border: none; padding: 0; height: 0; }
    thead { display: table-header-group; } /* encabezado repetido en cada página */
    /* Ningún riesgo se corta entre dos páginas (DomPDF tampoco divide filas). */
    tr { page-break-inside: avoid; }

    .fila-titulo td { background: #{{ $formato::FONDO_TITULO }}; height: 44px; }
    .logo { text-align: center; }
    .logo img { height: 38px; }
    .titulo { font-size: 20pt; text-align: center; }
    .borrador { color: #C00000; font-weight: bold; font-size: 9pt; text-align: center; }
    .codigo { font-weight: bold; font-size: 9pt; text-align: center; }
    .fila-datos td { background: #{{ $formato::FONDO_DATOS_ENCABEZADO }}; font-weight: bold; font-size: 7pt; }
    .grupo th { font-size: 8.5pt; text-align: center; }
    .columnas th { background: #{{ $formato::FONDO_COLUMNAS }}; font-size: 6pt; text-align: center; }
    .centro { text-align: center; }
    .nivel { text-align: center; font-weight: bold; }
    .nota { font-size: 5.5pt; font-weight: normal; }
    .seguimiento { color: #{{ $formato::COLOR_SEGUIMIENTO }}; font-weight: bold; }
    .multilinea { white-space: pre-line; }
</style>
</head>
<body>

<table>
    <thead>
        <tr class="anchos">
            @foreach ($anchos as $ancho)
                <td style="width: {{ $ancho }}%;"></td>
            @endforeach
        </tr>
        {{-- Fila 1: logo · título · [BORRADOR] · código del formulario --}}
        <tr class="fila-titulo">
            <td class="logo"><img src="{{ $logo }}" alt="INFOTEP"></td>
            <td colspan="8" class="titulo">{{ $encabezado['titulo'] }}</td>
            <td class="borrador">{{ $encabezado['estado'] === 'borrador' ? 'BORRADOR' : '' }}</td>
            <td colspan="3" class="codigo">{{ $encabezado['codigo'] }}<br>{{ $encabezado['edicion_formulario'] }}</td>
        </tr>
        {{-- Fila 2: Dirección · Proceso · Fecha de revisión (el PDF no tiene hoja: la edición va aquí) --}}
        <tr class="fila-datos">
            <td colspan="2">DIRECCIÓN: {{ $encabezado['direccion'] }}</td>
            <td colspan="7" style="font-size: 8pt;">PROCESO: {{ $encabezado['proceso'] }}</td>
            <td colspan="4">FECHA DE REVISIÓN: {{ $encabezado['fecha_revision'] }}<br>EDICIÓN: {{ $encabezado['edicion'] }}</td>
        </tr>
        {{-- Fila 3: grupos de columnas con su color --}}
        <tr class="grupo">
            @foreach ($grupos as $indice => $grupo)
                <th colspan="{{ count($grupo['columnas']) }}" style="background: #{{ $fondosGrupos[$indice] ?? $formato::FONDO_COLUMNAS }};">{{ $grupo['titulo'] }}</th>
            @endforeach
        </tr>
        <tr class="columnas">
            @foreach ($grupos as $grupo)
                @foreach ($grupo['columnas'] as $columna)
                    <th>{{ $columna }}</th>
                @endforeach
            @endforeach
        </tr>
    </thead>
    <tbody>
        @forelse ($filas as $fila)
            @php($colores = $colorNivel($fila['nivel']))
            <tr>
                <td class="multilinea">{{ $fila['actividad'] }}</td>
                <td class="multilinea">{{ $fila['descripcion'] }}</td>
                <td>{{ $fila['factores'] }}</td>
                <td class="multilinea">{{ $fila['causas'] }}</td>
                <td class="multilinea">{{ $fila['control_actual'] }}</td>
                <td class="centro">{{ $fila['probabilidad'] }}</td>
                <td class="centro">{{ $fila['impacto'] }}</td>
                @if ($colores)
                    <td class="nivel" style="background: #{{ $colores['fondo'] }}; color: #{{ $colores['texto'] }};">
                        {{ $fila['nivel']['nombre'] }}
                        @if ($fila['nivel_ajustado'])
                            <br><span class="nota">Ajustado: {{ $fila['justificacion_ajuste'] }}</span>
                        @endif
                    </td>
                @else
                    <td class="centro">Sin evaluar</td>
                @endif
                <td class="multilinea">{{ $fila['sintoma'] }}</td>
                <td class="multilinea">{{ $fila['tratamiento'] }}</td>
                <td class="multilinea">{{ $fila['responsable'] }}</td>
                <td>{{ $fila['periodicidad'] }}</td>
                <td class="multilinea seguimiento">{{ $fila['seguimiento'] }}</td>
            </tr>
        @empty
            <tr><td colspan="13" class="centro">Esta edición no tiene riesgos registrados.</td></tr>
        @endforelse
    </tbody>
</table>

</body>
</html>
