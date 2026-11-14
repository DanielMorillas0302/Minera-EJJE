<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Reporte Presupuesto vs Real - Sistema Integrado de Minería</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 10px; color: #1f2937; margin: 18px; }
        .header { border-bottom: 2px solid #0F4C5C; padding-bottom: 8px; margin-bottom: 12px; }
        .title { font-size: 15px; font-weight: bold; color: #0F4C5C; margin: 0; }
        .subtitle { font-size: 10px; color: #4b5563; margin-top: 3px; }
        
        .kpi-table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        .kpi-cell { width: 25%; padding: 8px; background: #f8fafc; border: 1px solid #e2e8f0; text-align: center; }
        .kpi-title { font-size: 8px; text-transform: uppercase; color: #64748b; font-weight: bold; }
        .kpi-value { font-size: 12px; font-weight: bold; color: #0F4C5C; margin-top: 3px; }

        table.data-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background: #0F4C5C; color: #ffffff; text-align: left; padding: 6px 7px; font-size: 9px; }
        td { border-bottom: 1px solid #e5e7eb; padding: 6px 7px; vertical-align: middle; font-size: 9px; }
        .num { text-align: right; white-space: nowrap; }
        
        .badge { display: inline-block; padding: 2px 5px; border-radius: 3px; font-size: 8px; font-weight: bold; text-align: center; }
        .badge-normal { background: #dcfce7; color: #166534; }
        .badge-advertencia { background: #fef9c3; color: #854d0e; }
        .badge-alerta { background: #ffedd5; color: #9a3412; }
        .badge-sobregirado { background: #fee2e2; color: #991b1b; }
        
        .row-total { background: #f1f5f9; font-weight: bold; border-top: 2px solid #cbd5e1; }
        .footer { margin-top: 14px; font-size: 8px; color: #6b7280; text-align: right; }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">Control Presupuestal: Presupuesto vs Ejecutado</p>
        <p class="subtitle">
            Sistema Integrado de Minería (Trujillo / Pataz) · Período: {{ $comparativo['mes_nombre'] }} {{ $comparativo['anio'] }} · Emitido el {{ $fechaEmision }}
        </p>
    </div>

    <table class="kpi-table">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-title">Presupuesto Mensual</div>
                <div class="kpi-value">S/ {{ number_format((float) $comparativo['totales']['presupuesto'], 2) }}</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Gasto Real Ejecutado</div>
                <div class="kpi-value">S/ {{ number_format((float) $comparativo['totales']['ejecutado'], 2) }}</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Saldo Disponible</div>
                <div class="kpi-value" style="color: {{ $comparativo['totales']['diferencia'] < 0 ? '#b91c1c' : '#15803d' }}">
                    S/ {{ number_format((float) $comparativo['totales']['diferencia'], 2) }}
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">% Ejecución Global</div>
                <div class="kpi-value">{{ number_format((float) $comparativo['totales']['porcentaje'], 1) }}%</div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Centro de Costo</th>
                <th>Área</th>
                <th class="num">Presupuesto</th>
                <th class="num">Ejecutado</th>
                <th class="num">Diferencia</th>
                <th class="num">% Ejec.</th>
                <th style="text-align: center;">Estado</th>
            </tr>
        </thead>
        <tbody>
            @forelse($comparativo['items'] as $item)
                <tr>
                    <td><strong>{{ $item['codigo'] }}</strong></td>
                    <td>{{ $item['nombre'] }}</td>
                    <td>{{ $item['area_nombre'] }}</td>
                    <td class="num">S/ {{ number_format((float) $item['presupuesto'], 2) }}</td>
                    <td class="num">S/ {{ number_format((float) $item['ejecutado'], 2) }}</td>
                    <td class="num" style="color: {{ $item['diferencia'] < 0 ? '#b91c1c' : '#15803d' }}">
                        S/ {{ number_format((float) $item['diferencia'], 2) }}
                    </td>
                    <td class="num">{{ number_format((float) $item['porcentaje'], 1) }}%</td>
                    <td style="text-align: center;">
                        @if($item['estado'] === 'NORMAL')
                            <span class="badge badge-normal">Normal</span>
                        @elseif($item['estado'] === 'ADVERTENCIA')
                            <span class="badge badge-advertencia">Advertencia (80-90%)</span>
                        @elseif($item['estado'] === 'ALERTA')
                            <span class="badge badge-alerta">Alerta (&gt;90%)</span>
                        @else
                            <span class="badge badge-sobregirado">Sobregirado (&gt;100%)</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="8" style="text-align: center; padding: 14px;">No hay centros de costo registrados para este período.</td>
                </tr>
            @endforelse
            <tr class="row-total">
                <td colspan="3">TOTALES</td>
                <td class="num">S/ {{ number_format((float) $comparativo['totales']['presupuesto'], 2) }}</td>
                <td class="num">S/ {{ number_format((float) $comparativo['totales']['ejecutado'], 2) }}</td>
                <td class="num" style="color: {{ $comparativo['totales']['diferencia'] < 0 ? '#b91c1c' : '#15803d' }}">
                    S/ {{ number_format((float) $comparativo['totales']['diferencia'], 2) }}
                </td>
                <td class="num">{{ number_format((float) $comparativo['totales']['porcentaje'], 1) }}%</td>
                <td style="text-align: center;">—</td>
            </tr>
        </tbody>
    </table>

    <div class="footer">
        Sistema Integrado de Minería · Trujillo - Pataz · Reporte financiero generado automáticamente
    </div>
</body>
</html>

