<?php

namespace App\Http\Controllers\Finanzas;

use App\Exports\TablaReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Finanzas\MovimientoFinanciero;
use App\Services\Finanzas\FinanzasService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FinanzasReporteController extends Controller
{
    public function __construct(
        private readonly FinanzasService $finanzasService
    ) {}

    public function index(Request $request): Response
    {
        $pestana = (string) $request->input('tab', 'egresos_centro_costo');
        $ahora = Carbon::now();
        $anio = (int) $request->input('anio', $ahora->year);
        $mes = (int) $request->input('mes', $ahora->month);
        $desde = (string) $request->input('desde', $ahora->copy()->startOfMonth()->toDateString());
        $hasta = (string) $request->input('hasta', $ahora->copy()->endOfMonth()->toDateString());

        $datos = match ($pestana) {
            'egresos_categoria' => $this->finanzasService->reporteEgresosPorCategoria($desde, $hasta),
            'cuentas_por_pagar' => $this->finanzasService->reporteCuentasPorPagar()->map(fn (MovimientoFinanciero $m): array => [
                'id' => $m->id,
                'fecha' => $m->fecha?->format('d/m/Y'),
                'monto' => (float) $m->monto,
                'moneda' => $m->moneda,
                'concepto' => $m->concepto,
                'proveedor' => $m->proveedor?->razon_social ?? 'Sin proveedor',
                'centro_costo' => $m->centroCosto?->codigo ?? '—',
                'categoria' => $m->categoria?->nombre ?? '—',
                'comprobante' => $m->comprobante_numero ? ($m->comprobante_tipo->value.' '.$m->comprobante_numero) : '—',
                'fecha_vencimiento' => $m->fecha_vencimiento?->format('d/m/Y') ?? 'Sin vencimiento',
                'vencida' => $m->fecha_vencimiento ? $m->fecha_vencimiento->isPast() : false,
            ]),
            'presupuesto_real' => $this->finanzasService->comparativoPresupuestoVsReal($anio, $mes),
            default => $this->finanzasService->reporteEgresosPorCentroCosto($desde, $hasta),
        };

        return Inertia::render('finanzas/reportes', [
            'tab' => $pestana,
            'datos' => $datos,
            'filtros' => [
                'tab' => $pestana,
                'anio' => $anio,
                'mes' => $mes,
                'desde' => $desde,
                'hasta' => $hasta,
            ],
            'aniosDisponibles' => range($ahora->year - 2, $ahora->year + 1),
        ]);
    }

    public function exportar(Request $request): BinaryFileResponse
    {
        $tipoReporte = (string) $request->input('tipo_reporte', 'egresos_cc');
        $ahora = Carbon::now();
        $anio = (int) $request->input('anio', $ahora->year);
        $mes = (int) $request->input('mes', $ahora->month);
        $desde = (string) $request->input('desde', '');
        $hasta = (string) $request->input('hasta', '');

        if ($tipoReporte === 'egresos_cat') {
            $datos = $this->finanzasService->reporteEgresosPorCategoria($desde ?: null, $hasta ?: null);
            $encabezados = ['Categoría de Gasto', 'Cantidad Movimientos', 'Total Egresos (PEN)'];
            $filas = $datos->map(fn ($r): array => [
                $r->nombre,
                (int) $r->total_movimientos,
                number_format((float) $r->total_monto, 2, '.', ''),
            ])->all();
            $nombreArchivo = 'reporte_egresos_por_categoria_'.now()->format('Ymd_His').'.xlsx';
        } elseif ($tipoReporte === 'cuentas_pagar') {
            $datos = $this->finanzasService->reporteCuentasPorPagar();
            $encabezados = ['Fecha Emisión', 'Proveedor', 'RUC', 'Comprobante', 'Concepto', 'Centro Costo', 'Monto (PEN)', 'Fecha Vencimiento', 'Estado'];
            $filas = $datos->map(fn (MovimientoFinanciero $m): array => [
                $m->fecha?->format('d/m/Y') ?? '',
                $m->proveedor?->razon_social ?? 'Sin proveedor',
                $m->proveedor?->ruc ?? '',
                $m->comprobante_numero ? ($m->comprobante_tipo->value.' '.$m->comprobante_numero) : '',
                $m->concepto,
                $m->centroCosto?->codigo ?? '',
                number_format((float) $m->monto, 2, '.', ''),
                $m->fecha_vencimiento?->format('d/m/Y') ?? 'Sin vencimiento',
                $m->fecha_vencimiento && $m->fecha_vencimiento->isPast() ? 'VENCIDO' : 'POR VENCER',
            ])->all();
            $nombreArchivo = 'reporte_cuentas_por_pagar_'.now()->format('Ymd_His').'.xlsx';
        } elseif ($tipoReporte === 'presupuesto_real') {
            $comp = $this->finanzasService->comparativoPresupuestoVsReal($anio, $mes);
            $encabezados = ['Código CC', 'Centro de Costo', 'Área', 'Presupuesto (PEN)', 'Ejecutado Real (PEN)', 'Diferencia (PEN)', '% Ejecución', 'Estado'];
            $filas = collect($comp['items'])->map(fn ($item): array => [
                $item['codigo'],
                $item['nombre'],
                $item['area_nombre'],
                number_format((float) $item['presupuesto'], 2, '.', ''),
                number_format((float) $item['ejecutado'], 2, '.', ''),
                number_format((float) $item['diferencia'], 2, '.', ''),
                $item['porcentaje'].'%',
                $item['estado'],
            ])->all();
            // Fila de totales
            $filas[] = [
                'TOTALES',
                '—',
                '—',
                number_format((float) $comp['totales']['presupuesto'], 2, '.', ''),
                number_format((float) $comp['totales']['ejecutado'], 2, '.', ''),
                number_format((float) $comp['totales']['diferencia'], 2, '.', ''),
                $comp['totales']['porcentaje'].'%',
                '—',
            ];
            $nombreArchivo = "reporte_presupuesto_vs_real_{$anio}_{$mes}_".now()->format('Ymd_His').'.xlsx';
        } else {
            // Default: Egresos por Centro de Costo
            $datos = $this->finanzasService->reporteEgresosPorCentroCosto($desde ?: null, $hasta ?: null);
            $encabezados = ['Código', 'Centro de Costo', 'Área', 'Cantidad Movimientos', 'Total Egresos (PEN)'];
            $filas = $datos->map(fn ($r): array => [
                $r->codigo,
                $r->nombre,
                $r->area_nombre ?? 'Sin área',
                (int) $r->total_movimientos,
                number_format((float) $r->total_monto, 2, '.', ''),
            ])->all();
            $nombreArchivo = 'reporte_egresos_por_centro_costo_'.now()->format('Ymd_His').'.xlsx';
        }

        return Excel::download(new TablaReporteExport($encabezados, $filas), $nombreArchivo);
    }

    public function presupuestoPdf(Request $request): HttpResponse
    {
        $ahora = Carbon::now();
        $anio = (int) $request->input('anio', $ahora->year);
        $mes = (int) $request->input('mes', $ahora->month);

        $comparativo = $this->finanzasService->comparativoPresupuestoVsReal($anio, $mes);

        $pdf = Pdf::loadView('pdf.presupuesto', [
            'comparativo' => $comparativo,
            'fechaEmision' => Carbon::now()->format('d/m/Y H:i'),
        ])->setPaper('a4', 'portrait');

        $nombreArchivo = "presupuesto_vs_real_{$anio}_{$mes}.pdf";

        return $pdf->download($nombreArchivo);
    }
}
