<?php

namespace App\Http\Controllers\Finanzas;

use App\Enums\EstadoPago;
use App\Enums\TipoMovimientoFinanciero;
use App\Http\Controllers\Controller;
use App\Models\Finanzas\MovimientoFinanciero;
use App\Services\Finanzas\FinanzasService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class FinanzasResumenController extends Controller
{
    public function __construct(
        private readonly FinanzasService $finanzasService
    ) {}

    public function index(Request $request): Response
    {
        $ahora = Carbon::now();
        $anio = (int) $request->input('anio', $ahora->year);
        $mes = (int) $request->input('mes', $ahora->month);

        $kpis = $this->finanzasService->resumenKpis($anio, $mes);
        $comparativo = $this->finanzasService->comparativoPresupuestoVsReal($anio, $mes);

        $ultimosMovimientos = MovimientoFinanciero::query()
            ->with(['categoria', 'centroCosto', 'proveedor'])
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (MovimientoFinanciero $m): array => [
                'id' => $m->id,
                'tipo' => $m->tipo->value,
                'tipo_etiqueta' => $m->tipo->etiqueta(),
                'fecha' => $m->fecha?->format('Y-m-d'),
                'fecha_fmt' => $m->fecha?->format('d/m/Y'),
                'monto' => (float) $m->monto,
                'moneda' => $m->moneda,
                'concepto' => $m->concepto,
                'categoria' => $m->categoria?->nombre ?? '—',
                'centro_costo' => $m->centroCosto?->nombre ?? '—',
                'proveedor' => $m->proveedor?->razon_social ?? '—',
                'estado_pago' => $m->estado_pago->value,
                'estado_pago_etiqueta' => $m->estado_pago->etiqueta(),
            ]);

        $cuentasPendientes = MovimientoFinanciero::query()
            ->with(['proveedor', 'centroCosto'])
            ->where('tipo', TipoMovimientoFinanciero::EGRESO->value)
            ->where('estado_pago', EstadoPago::PENDIENTE->value)
            ->orderByRaw('CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END')
            ->orderBy('fecha_vencimiento')
            ->limit(6)
            ->get()
            ->map(fn (MovimientoFinanciero $m): array => [
                'id' => $m->id,
                'concepto' => $m->concepto,
                'monto' => (float) $m->monto,
                'moneda' => $m->moneda,
                'proveedor' => $m->proveedor?->razon_social ?? '—',
                'centro_costo' => $m->centroCosto?->codigo ?? '—',
                'comprobante' => $m->comprobante_numero ? ($m->comprobante_tipo->value.' '.$m->comprobante_numero) : 'Sin comprobante',
                'fecha_vencimiento' => $m->fecha_vencimiento?->format('d/m/Y') ?? 'Sin vencimiento',
                'es_vencida' => $m->fecha_vencimiento ? $m->fecha_vencimiento->isPast() : false,
            ]);

        return Inertia::render('finanzas/index', [
            'kpis' => $kpis,
            'comparativo' => $comparativo,
            'ultimosMovimientos' => $ultimosMovimientos,
            'cuentasPendientes' => $cuentasPendientes,
            'filtros' => [
                'anio' => $anio,
                'mes' => $mes,
            ],
        ]);
    }
}
