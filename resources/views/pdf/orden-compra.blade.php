<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Orden de Compra {{ $orden->codigo }}</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            margin: 20px;
            line-height: 1.4;
        }
        .header {
            border-bottom: 2px solid #0F4C5C;
            padding-bottom: 12px;
            margin-bottom: 18px;
        }
        .title {
            color: #0F4C5C;
            font-size: 18px;
            font-weight: bold;
            margin: 0;
            text-transform: uppercase;
        }
        .subtitle {
            color: #64748b;
            font-size: 10px;
            margin-top: 3px;
        }
        .badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            background-color: #0F4C5C;
            color: white;
            float: right;
        }
        .info-grid {
            width: 100%;
            margin-bottom: 16px;
            border-collapse: collapse;
        }
        .info-grid td {
            vertical-align: top;
            padding: 4px 6px;
        }
        .info-card {
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 4px;
            padding: 10px;
        }
        .info-card h4 {
            margin: 0 0 6px 0;
            color: #0F4C5C;
            font-size: 11px;
            border-bottom: 1px solid #cbd5e1;
            padding-bottom: 3px;
        }
        table.items {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
            margin-bottom: 16px;
        }
        table.items th {
            background-color: #0F4C5C;
            color: white;
            text-align: left;
            padding: 6px 8px;
            font-size: 10px;
            text-transform: uppercase;
        }
        table.items td {
            padding: 6px 8px;
            border-bottom: 1px solid #e2e8f0;
        }
        table.items tr:nth-child(even) {
            background-color: #f8fafc;
        }
        .totals-table {
            width: 40%;
            float: right;
            border-collapse: collapse;
            margin-bottom: 25px;
        }
        .totals-table td {
            padding: 4px 8px;
            text-align: right;
        }
        .totals-table tr.total-row {
            font-size: 12px;
            font-weight: bold;
            border-top: 2px solid #0F4C5C;
            color: #0F4C5C;
        }
        .firmas {
            width: 100%;
            margin-top: 70px;
            border-collapse: collapse;
        }
        .firmas td {
            width: 33.3%;
            text-align: center;
            vertical-align: bottom;
            padding: 0 15px;
        }
        .linea-firma {
            border-top: 1px solid #475569;
            margin-top: 50px;
            padding-top: 5px;
            font-size: 10px;
            font-weight: bold;
            color: #334155;
        }
    </style>
</head>
<body>
    <div class="header">
        <span class="badge">{{ $orden->estado->etiqueta() }}</span>
        <h1 class="title">SISTEMA INTEGRADO DE MINERÍA</h1>
        <div class="subtitle">Operaciones Mineras Trujillo - Pataz | Orden de Compra: <strong>{{ $orden->codigo }}</strong></div>
    </div>

    <table class="info-grid">
        <tr>
            <td style="width: 50%;">
                <div class="info-card">
                    <h4>DATOS DEL PROVEEDOR</h4>
                    <p style="margin: 2px 0;"><strong>Razón Social:</strong> {{ $orden->proveedor->razon_social }}</p>
                    <p style="margin: 2px 0;"><strong>RUC:</strong> {{ $orden->proveedor->ruc }}</p>
                    <p style="margin: 2px 0;"><strong>Contacto:</strong> {{ $orden->proveedor->contacto ?? 'No especificado' }}</p>
                    <p style="margin: 2px 0;"><strong>Teléfono / Email:</strong> {{ $orden->proveedor->telefono ?? '-' }} / {{ $orden->proveedor->email ?? '-' }}</p>
                </div>
            </td>
            <td style="width: 50%;">
                <div class="info-card">
                    <h4>CONDICIONES DE LA COMPRA</h4>
                    <p style="margin: 2px 0;"><strong>Fecha de Emisión:</strong> {{ $orden->fecha->format('d/m/Y') }}</p>
                    <p style="margin: 2px 0;"><strong>Entrega Estimada:</strong> {{ $orden->fecha_entrega_esperada ? $orden->fecha_entrega_esperada->format('d/m/Y') : 'Inmediata' }}</p>
                    <p style="margin: 2px 0;"><strong>Almacén Destino:</strong> {{ $orden->almacenDestino->nombre }}</p>
                    <p style="margin: 2px 0;"><strong>Condición de Pago:</strong> {{ $orden->condiciones_pago ?? 'Contado / Crédito 30 días' }}</p>
                </div>
            </td>
        </tr>
    </table>

    <table class="items">
        <thead>
            <tr>
                <th style="width: 5%;">#</th>
                <th style="width: 15%;">Código</th>
                <th style="width: 45%;">Descripción del Material</th>
                <th style="width: 10%; text-align: right;">Cant.</th>
                <th style="width: 12%; text-align: right;">P. Unit.</th>
                <th style="width: 13%; text-align: right;">Subtotal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($orden->detalles as $idx => $det)
            <tr>
                <td>{{ $idx + 1 }}</td>
                <td><strong>{{ $det->material->codigo }}</strong></td>
                <td>{{ $det->material->nombre }}</td>
                <td style="text-align: right;">{{ number_format($det->cantidad, 2) }}</td>
                <td style="text-align: right;">S/ {{ number_format($det->precio_unitario, 2) }}</td>
                <td style="text-align: right;">S/ {{ number_format($det->subtotal, 2) }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <table class="totals-table">
        <tr>
            <td>Subtotal:</td>
            <td><strong>S/ {{ number_format($orden->subtotal, 2) }}</strong></td>
        </tr>
        <tr>
            <td>I.G.V. (18%):</td>
            <td><strong>S/ {{ number_format($orden->igv, 2) }}</strong></td>
        </tr>
        <tr class="total-row">
            <td>TOTAL (PEN):</td>
            <td><strong>S/ {{ number_format($orden->total, 2) }}</strong></td>
        </tr>
    </table>

    <div style="clear: both;"></div>

    @if($orden->observaciones)
    <div style="margin-top: 10px; font-size: 10px; color: #475569; background-color: #f1f5f9; padding: 6px 10px; border-radius: 4px;">
        <strong>Observaciones:</strong> {{ $orden->observaciones }}
    </div>
    @endif

    <table class="firmas">
        <tr>
            <td>
                <div class="linea-firma">
                    RESPONSABLE DE LOGÍSTICA<br>
                    <span>Emisión y Control</span>
                </div>
            </td>
            <td>
                <div class="linea-firma">
                    GERENCIA / ADMINISTRACIÓN<br>
                    <span>Aprobación de Compra</span>
                </div>
            </td>
            <td>
                <div class="linea-firma">
                    PROVEEDOR ADJUDICADO<br>
                    <span>Conformidad de Entrega</span>
                </div>
            </td>
        </tr>
    </table>
</body>
</html>

