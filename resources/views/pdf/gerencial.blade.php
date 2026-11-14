<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Informe Ejecutivo Gerencial - Sistema Integrado de Minería</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 9px; color: #1e293b; margin: 16px; line-height: 1.35; }
        .header { border-bottom: 2px solid #0F4C5C; padding-bottom: 8px; margin-bottom: 12px; }
        .title { font-size: 15px; font-weight: bold; color: #0F4C5C; margin: 0; text-transform: uppercase; }
        .subtitle { font-size: 9px; color: #475569; margin-top: 3px; }
        
        .section-title { font-size: 11px; font-weight: bold; color: #0F4C5C; margin-top: 14px; margin-bottom: 6px; border-bottom: 1px solid #cbd5e1; padding-bottom: 3px; }
        
        .kpi-table { width: 100%; border-collapse: collapse; margin-bottom: 10px; }
        .kpi-cell { padding: 6px; background: #f8fafc; border: 1px solid #e2e8f0; text-align: center; }
        .kpi-title { font-size: 7.5px; text-transform: uppercase; color: #64748b; font-weight: bold; }
        .kpi-value { font-size: 11px; font-weight: bold; color: #0F4C5C; margin-top: 2px; }

        table.data-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        th { background: #0F4C5C; color: #ffffff; text-align: left; padding: 5px 6px; font-size: 8px; }
        td { border-bottom: 1px solid #e2e8f0; padding: 4.5px 6px; vertical-align: middle; font-size: 8px; }
        .num { text-align: right; white-space: nowrap; }

        .badge { display: inline-block; padding: 2px 5px; border-radius: 3px; font-size: 7.5px; font-weight: bold; text-align: center; }
        .badge-normal { background: #dcfce7; color: #166534; }
        .badge-advertencia { background: #fef9c3; color: #854d0e; }
        .badge-alerta { background: #ffedd5; color: #9a3412; }
        .badge-sobregirado { background: #fee2e2; color: #991b1b; }
        
        .row-total { background: #f1f5f9; font-weight: bold; border-top: 1.5px solid #cbd5e1; }
        .footer { margin-top: 22px; font-size: 7.5px; color: #64748b; text-align: right; }
        
        .signatures { width: 100%; margin-top: 26px; border-collapse: collapse; }
        .sig-box { width: 50%; text-align: center; padding: 12px; }
        .sig-line { border-top: 1px solid #64748b; width: 70%; margin: 0 auto 4px auto; }
    </style>
</head>
<body>
    <div class="header">
        <p class="title">Informe Ejecutivo Gerencial</p>
        <p class="subtitle">
            Sistema Integrado de Minería · Operación Pataz / Sede Administrativa Trujillo
            <br>
            <strong>Período evaluado:</strong> {{ $datos['mes_nombre'] }} {{ $datos['anio'] }} · <strong>Alcance:</strong> {{ $datos['sede'] }} · <strong>Emisión:</strong> {{ $datos['fechaEmision'] }}
        </p>
    </div>

    <!-- 1. RESUMEN FINANCIERO -->
    <div class="section-title">1. Balance Financiero y Ejecución Presupuestal</div>
    <table class="kpi-table">
        <tr>
            <td class="kpi-cell" style="width: 20%;">
                <div class="kpi-title">Ingresos del Mes</div>
                <div class="kpi-value" style="color: #166534;">S/ {{ number_format((float) $datos['kpis']['finanzas']['total_ingresos_mes'], 2) }}</div>
            </td>
            <td class="kpi-cell" style="width: 20%;">
                <div class="kpi-title">Egresos Ejecutados</div>
                <div class="kpi-value" style="color: #991b1b;">S/ {{ number_format((float) $datos['kpis']['finanzas']['total_egresos_mes'], 2) }}</div>
            </td>
            <td class="kpi-cell" style="width: 20%;">
                <div class="kpi-title">Saldo Operativo Neto</div>
                <div class="kpi-value">S/ {{ number_format((float) $datos['kpis']['finanzas']['saldo_neto_mes'], 2) }}</div>
            </td>
            <td class="kpi-cell" style="width: 20%;">
                <div class="kpi-title">Presupuesto Aprobado</div>
                <div class="kpi-value">S/ {{ number_format((float) $datos['kpis']['finanzas']['presupuesto_total_mes'], 2) }}</div>
            </td>
            <td class="kpi-cell" style="width: 20%;">
                <div class="kpi-title">% Ejecución Global</div>
                <div class="kpi-value">{{ number_format((float) $datos['kpis']['finanzas']['porcentaje_ejecucion'], 1) }}%</div>
            </td>
        </tr>
    </table>

    <table class="data-table">
        <thead>
            <tr>
                <th>Código</th>
                <th>Centro de Costo</th>
                <th>Área</th>
                <th class="num">Presupuestado</th>
                <th class="num">Ejecutado Real</th>
                <th class="num">Variación</th>
                <th class="num">% Avance</th>
                <th style="text-align: center;">Control</th>
            </tr>
        </thead>
        <tbody>
            @foreach($datos['comparativo']['items'] as $item)
                <tr>
                    <td><strong>{{ $item['codigo'] }}</strong></td>
                    <td>{{ $item['nombre'] }}</td>
                    <td>{{ $item['area_nombre'] }}</td>
                    <td class="num">S/ {{ number_format((float) $item['presupuesto'], 2) }}</td>
                    <td class="num">S/ {{ number_format((float) $item['ejecutado'], 2) }}</td>
                    <td class="num" style="color: {{ $item['diferencia'] < 0 ? '#991b1b' : '#166534' }};">
                        S/ {{ number_format((float) $item['diferencia'], 2) }}
                    </td>
                    <td class="num">{{ number_format((float) $item['porcentaje'], 1) }}%</td>
                    <td style="text-align: center;">
                        @if($item['estado'] === 'NORMAL')
                            <span class="badge badge-normal">Normal</span>
                        @elseif($item['estado'] === 'ADVERTENCIA')
                            <span class="badge badge-advertencia">Advertencia</span>
                        @elseif($item['estado'] === 'ALERTA')
                            <span class="badge badge-alerta">Alerta</span>
                        @else
                            <span class="badge badge-sobregirado">Sobregiro</span>
                        @endif
                    </td>
                </tr>
            @endforeach
            <tr class="row-total">
                <td colspan="3">TOTALES</td>
                <td class="num">S/ {{ number_format((float) $datos['comparativo']['totales']['presupuesto'], 2) }}</td>
                <td class="num">S/ {{ number_format((float) $datos['comparativo']['totales']['ejecutado'], 2) }}</td>
                <td class="num" style="color: {{ $datos['comparativo']['totales']['diferencia'] < 0 ? '#991b1b' : '#166534' }};">
                    S/ {{ number_format((float) $datos['comparativo']['totales']['diferencia'], 2) }}
                </td>
                <td class="num">{{ number_format((float) $datos['comparativo']['totales']['porcentaje'], 1) }}%</td>
                <td style="text-align: center;">—</td>
            </tr>
        </tbody>
    </table>

    <!-- 2. FUERZA LABORAL Y SALUD OCUPACIONAL -->
    <div class="section-title">2. Gestión de Fuerza Laboral y Salud Ocupacional (RR.HH.)</div>
    <table class="kpi-table">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-title">Personal Activo Total</div>
                <div class="kpi-value">{{ $datos['kpis']['rrhh']['personal_activo_total'] }} colaboradores</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Despliegue Operativo</div>
                <div class="kpi-value">
                    Pataz: {{ $datos['kpis']['rrhh']['personal_pataz'] }} · Trujillo: {{ $datos['kpis']['rrhh']['personal_trujillo'] }}
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Aptitud Médica (Viaje Pataz)</div>
                <div class="kpi-value" style="color: #166534;">
                    {{ $datos['kpis']['rrhh']['personal_apto'] }} Aptos / {{ $datos['kpis']['rrhh']['personal_no_apto'] }} Con restricción
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Alertas Médicas (&le; 30d)</div>
                <div class="kpi-value" style="color: {{ $datos['kpis']['rrhh']['examenes_por_vencer_30d'] > 0 ? '#b45309' : '#1e293b' }};">
                    {{ $datos['kpis']['rrhh']['examenes_por_vencer_30d'] }} por vencer ({{ $datos['kpis']['rrhh']['examenes_vencidos'] }} vencidos)
                </div>
            </td>
        </tr>
    </table>

    <!-- 3. CADENA DE SUMINISTRO Y LOGÍSTICA -->
    <div class="section-title">3. Cadena de Suministro y Logística Operativa</div>
    <table class="kpi-table">
        <tr>
            <td class="kpi-cell">
                <div class="kpi-title">Envíos en Tránsito</div>
                <div class="kpi-value">{{ $datos['kpis']['logistica']['envios_en_transito'] }} camiones en ruta</div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Envíos con Retraso</div>
                <div class="kpi-value" style="color: {{ $datos['kpis']['logistica']['envios_retrasados'] > 0 ? '#991b1b' : '#166534' }};">
                    {{ $datos['kpis']['logistica']['envios_retrasados'] }} unidades retrasadas
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Materiales Bajo Stock Mínimo</div>
                <div class="kpi-value" style="color: {{ $datos['kpis']['logistica']['materiales_stock_critico'] > 0 ? '#b45309' : '#166534' }};">
                    {{ $datos['kpis']['logistica']['materiales_stock_critico'] }} ítems críticos
                </div>
            </td>
            <td class="kpi-cell">
                <div class="kpi-title">Requerimientos Pendientes</div>
                <div class="kpi-value">{{ $datos['kpis']['logistica']['requerimientos_pendientes'] }} solicitudes activas</div>
            </td>
        </tr>
    </table>

    @if($datos['stockCritico']->isNotEmpty())
        <div style="font-size: 8px; font-weight: bold; color: #475569; margin-top: 6px; margin-bottom: 3px;">
            Materiales Críticos con Mayor Riesgo de Desabastecimiento:
        </div>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Material</th>
                    <th>Almacén</th>
                    <th class="num">Stock Actual</th>
                    <th class="num">Stock Mínimo</th>
                    <th class="num">Déficit</th>
                </tr>
            </thead>
            <tbody>
                @foreach($datos['stockCritico'] as $stk)
                    <tr>
                        <td><strong>{{ $stk['material']['codigo'] }}</strong></td>
                        <td>{{ $stk['material']['nombre'] }}</td>
                        <td>{{ $stk['almacen']['nombre'] }}</td>
                        <td class="num" style="color: #991b1b; font-weight: bold;">
                            {{ number_format((float) $stk['cantidad'], 2) }}
                        </td>
                        <td class="num">{{ number_format((float) $stk['material']['stock_minimo'], 2) }}</td>
                        <td class="num" style="color: #991b1b;">
                            {{ number_format(max(0, (float) $stk['material']['stock_minimo'] - (float) $stk['cantidad']), 2) }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <!-- FIRMAS -->
    <table class="signatures">
        <tr>
            <td class="sig-box">
                <div class="sig-line"></div>
                <strong>Gerencia General</strong>
                <br>
                Sistema Integrado de Minería
            </td>
            <td class="sig-box">
                <div class="sig-line"></div>
                <strong>Dirección de Operaciones Mina</strong>
                <br>
                Operación Minera Pataz
            </td>
        </tr>
    </table>

    <div class="footer">
        Documento oficial reservado para la Dirección Ejecutiva · Emitido el {{ $datos['fechaEmision'] }}
    </div>
</body>
</html>

