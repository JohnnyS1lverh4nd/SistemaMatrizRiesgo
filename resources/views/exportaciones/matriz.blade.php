{{--
    Matriz de riesgos con el formato institucional RT-01-NI-ONA-156.
    Todo el texto usa {{ }} (escapado): el contenido lo escriben usuarios.
--}}
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<title>Matriz de riesgos · {{ $encabezado['proceso'] }} · {{ $encabezado['edicion'] }}</title>
<style>
    @page { margin: 28px 24px 34px 24px; }
    body { font-family: "DejaVu Sans", sans-serif; font-size: 6.5pt; color: #111827; }
    table { width: 100%; border-collapse: collapse; }
    /* Anchos fijos por columna (fila "anchos"): DomPDF ignora los anchos de celda en tablas automáticas. */
    table.riesgos { table-layout: fixed; }
    tr.anchos td { border: none; padding: 0; height: 0; }
    td { word-wrap: break-word; }
    th, td { border: 0.6px solid #6b7280; padding: 3px; vertical-align: top; }
    .encabezado td { font-size: 8pt; }
    .titulo { font-size: 12pt; font-weight: bold; text-align: center; }
    .codigo { text-align: center; font-weight: bold; }
    .grupo th { background: #e5e7eb; font-size: 7.5pt; text-align: center; }
    .columnas th { background: #f3f4f6; font-size: 6pt; text-align: center; vertical-align: middle; }
    thead { display: table-header-group; } /* encabezado repetido en cada página */
    tr { page-break-inside: avoid; }
    .centro { text-align: center; }
    .muestra { display: inline-block; width: 7px; height: 7px; margin-right: 3px; border: 0.5px solid #374151; }
    .nota { font-size: 5.5pt; color: #4b5563; }
    .borrador { color: #b91c1c; font-weight: bold; font-size: 9pt; text-align: center; }
    .multilinea { white-space: pre-line; }
</style>
</head>
<body>

<table class="encabezado">
    <tr>
        <td colspan="9" class="titulo">{{ $encabezado['titulo'] }}</td>
        <td colspan="4" class="codigo">{{ $encabezado['codigo'] }} {{ $encabezado['edicion_formulario'] }}</td>
    </tr>
    <tr>
        <td colspan="4"><strong>DIRECCIÓN:</strong> {{ $encabezado['direccion'] }}</td>
        <td colspan="5"><strong>PROCESO:</strong> {{ $encabezado['proceso'] }}</td>
        <td colspan="4"><strong>FECHA DE REVISIÓN:</strong> {{ $encabezado['fecha_revision'] }}<br><strong>EDICIÓN:</strong> {{ $encabezado['edicion'] }}</td>
    </tr>
</table>

@if ($encabezado['estado'] === 'borrador')
    <p class="borrador">BORRADOR — documento sin publicar</p>
@endif

<table class="riesgos" style="margin-top: 4px;">
    <thead>
        {{-- Primera fila invisible: con table-layout fixed DomPDF toma de aquí los anchos. --}}
        <tr class="anchos">
            <td style="width: 8%;"></td>
            <td style="width: 8%;"></td>
            <td style="width: 7.5%;"></td>
            <td style="width: 9.5%;"></td>
            <td style="width: 8.5%;"></td>
            <td style="width: 6%;"></td>
            <td style="width: 4.5%;"></td>
            <td style="width: 6.5%;"></td>
            <td style="width: 8%;"></td>
            <td style="width: 9.5%;"></td>
            <td style="width: 8%;"></td>
            <td style="width: 7.5%;"></td>
            <td style="width: 8.5%;"></td>
        </tr>
        <tr class="grupo">
            @foreach ($grupos as $grupo)
                <th colspan="{{ count($grupo['columnas']) }}">{{ $grupo['titulo'] }}</th>
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
            <tr>
                <td class="multilinea">{{ $fila['actividad'] }}</td>
                <td class="multilinea">{{ $fila['descripcion'] }}</td>
                <td>{{ $fila['factores'] }}</td>
                <td class="multilinea">{{ $fila['causas'] }}</td>
                <td class="multilinea">{{ $fila['control_actual'] }}</td>
                <td class="centro">{{ $fila['probabilidad'] }}</td>
                <td class="centro">{{ $fila['impacto'] }}</td>
                <td>
                    @if ($fila['nivel'])
                        {{-- El color va siempre con el nombre del nivel. --}}
                        <span class="muestra" style="background: {{ $fila['nivel']['color'] }};"></span>{{ $fila['nivel']['nombre'] }}
                        @if ($fila['nivel_ajustado'])
                            <br><span class="nota">Ajustado: {{ $fila['justificacion_ajuste'] }}</span>
                        @endif
                    @else
                        Sin evaluar
                    @endif
                </td>
                <td class="multilinea">{{ $fila['sintoma'] }}</td>
                <td class="multilinea">{{ $fila['tratamiento'] }}</td>
                <td class="multilinea">{{ $fila['responsable'] }}</td>
                <td>{{ $fila['periodicidad'] }}</td>
                <td class="multilinea">{{ $fila['seguimiento'] }}</td>
            </tr>
        @empty
            <tr><td colspan="13" class="centro">Esta edición no tiene riesgos registrados.</td></tr>
        @endforelse
    </tbody>
</table>

</body>
</html>
