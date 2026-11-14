<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Kardex de Inventario - Sistema Integrado de Minería</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; margin: 18px; }
        .header { border-bottom: 2px solid #0F4C5C; padding-bottom: 8px; margin-bottom: 12px; }
        .title { font-size: 15px; font-weight: bold; color: #0F4C5C; margin: 0; }
        .subtitle { font-size: 10px; color: #4b5563; margin-top: 3px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #0F4C5C; color: #ffffff; text-align: left; padding: 5px 6px; font-size: 9px; }
        td { border-bottom: 1px solid #e5e7eb; padding: 5px 6px; vertical-align: top; font-size: 9px; }
        .num { text-align: right; white-space: nowrap; }
        .badge { display: inline-block; padding: 2px 5px; border-radius: 3px; background: #e2e8f0; font-size: 8px; font-weight: bold; }
        .footer { margin-top: 14px; font-size: 8px; color: #6b7280; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">Kardex Valorizado / Movimientos de Inventario</p>
        <p class="subtitle">
            Sistema Integrado de Minería (Trujillo / Pataz) · Emitido el {{ $fechaEmision }}
            @if(!empty($filtrosTexto))
                · Filtros: {{ $filtrosTexto }}
            @endif
        </p>
    </div>

    <table>
        <thead>
            <tr>
                <th>Fecha/Hora</th>
                <th>Almacén</th>
                <th>Código</th>
                <th>Material</th>
                <th>Tipo</th>
                <th class="num">Cantidad</th>
                <th class="num">Saldo</th>
                <th>Responsable</th>
                <th>Observación</th>
            </tr>
        </thead>
        <tbody>
            @forelse($movimientos as $mov)
                <tr>
                    <td>{{ $mov->fecha_hora?->format('d/m/Y H:i') }}</td>
                    <td>{{ $mov->almacen?->nombre ?? '—' }}</td>
                    <td>{{ $mov->material?->codigo ?? '—' }}</td>
                    <td>{{ $mov->material?->nombre ?? '—' }}</td>
                    <td><span class="badge">{{ $mov->tipo?->etiqueta() ?? $mov->tipo }}</span></td>
                    <td class="num">{{ number_format((float) $mov->cantidad, 3) }}</td>
                    <td class="num"><strong>{{ number_format((float) $mov->saldo_resultante, 3) }}</strong></td>
                    <td>{{ $mov->usuario?->name ?? '—' }}</td>
                    <td>{{ $mov->observacion ?? '—' }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="9" style="text-align: center; padding: 14px;">No se encontraron movimientos para los criterios seleccionados.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <div class="footer">Total de registros: {{ $movimientos->count() }}</div>
</body>
</html>

