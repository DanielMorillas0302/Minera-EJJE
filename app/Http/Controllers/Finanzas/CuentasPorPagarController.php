<?php

namespace App\Http\Controllers\Finanzas;

use App\Enums\EstadoPago;
use App\Enums\TipoMovimientoFinanciero;
use App\Http\Controllers\Controller;
use App\Models\Finanzas\CentroCosto;
use App\Models\Finanzas\MovimientoFinanciero;
use App\Models\Logistica\Proveedor;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CuentasPorPagarController extends Controller
{
    public function index(Request $request): Response
    {
        $hoy = Carbon::now()->startOfDay();
        $en7Dias = (clone $hoy)->addDays(7)->endOfDay();

        $proveedorId = trim((string) $request->input('proveedor_id', ''));
        $centroCostoId = trim((string) $request->input('centro_costo_id', ''));
        $estadoVencimiento = trim((string) $request->input('estado_vencimiento', ''));

        $consulta = MovimientoFinanciero::query()
            ->with(['proveedor', 'centroCosto', 'categoria', 'adjuntos'])
            ->where('tipo', TipoMovimientoFinanciero::EGRESO->value)
            ->where('estado_pago', EstadoPago::PENDIENTE->value)
            ->whereNull('eliminado_en');

        if ($proveedorId !== '') {
            $consulta->where('proveedor_id', (int) $proveedorId);
        }

        if ($centroCostoId !== '') {
            $consulta->where('centro_costo_id', (int) $centroCostoId);
        }

        if ($estadoVencimiento === 'vencidas') {
            $consulta->whereNotNull('fecha_vencimiento')
                ->whereDate('fecha_vencimiento', '<', $hoy->toDateString());
        } elseif ($estadoVencimiento === 'por_vencer') {
            $consulta->whereNotNull('fecha_vencimiento')
                ->whereBetween('fecha_vencimiento', [$hoy->toDateString(), $en7Dias->toDateString()]);
        }

        $cuentas = $consulta
            ->orderByRaw('CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END')
            ->orderBy('fecha_vencimiento')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(function (MovimientoFinanciero $m) use ($hoy): array {
                $diasParaVencer = null;
                $estadoSemaforo = 'normal'; // normal, por_vencer, vencido

                if ($m->fecha_vencimiento) {
                    $fechaVenc = Carbon::parse($m->fecha_vencimiento)->startOfDay();
                    $diasParaVencer = (int) $hoy->diffInDays($fechaVenc, false);

                    if ($diasParaVencer < 0) {
                        $estadoSemaforo = 'vencido';
                    } elseif ($diasParaVencer <= 7) {
                        $estadoSemaforo = 'por_vencer';
                    }
                }

                return [
                    'id' => $m->id,
                    'fecha' => $m->fecha?->format('d/m/Y'),
                    'monto' => (float) $m->monto,
                    'moneda' => $m->moneda,
                    'concepto' => $m->concepto,
                    'proveedor_id' => $m->proveedor_id,
                    'proveedor_ruc' => $m->proveedor?->ruc,
                    'proveedor_nombre' => $m->proveedor?->razon_social ?? 'Sin proveedor',
                    'centro_costo_codigo' => $m->centroCosto?->codigo ?? '—',
                    'centro_costo_nombre' => $m->centroCosto?->nombre ?? '—',
                    'categoria_nombre' => $m->categoria?->nombre ?? '—',
                    'comprobante_tipo' => $m->comprobante_tipo->value,
                    'comprobante_tipo_etiqueta' => $m->comprobante_tipo->etiqueta(),
                    'comprobante_numero' => $m->comprobante_numero ?? 'S/N',
                    'fecha_vencimiento' => $m->fecha_vencimiento?->format('d/m/Y') ?? 'Sin vencimiento',
                    'fecha_vencimiento_raw' => $m->fecha_vencimiento?->format('Y-m-d'),
                    'dias_para_vencer' => $diasParaVencer,
                    'estado_semaforo' => $estadoSemaforo,
                    'adjuntos_count' => $m->adjuntos->count(),
                ];
            });

        // Totales globales para KPIs
        $baseTotalQuery = MovimientoFinanciero::query()
            ->where('tipo', TipoMovimientoFinanciero::EGRESO->value)
            ->where('estado_pago', EstadoPago::PENDIENTE->value)
            ->whereNull('eliminado_en');

        $totalPendiente = (float) (clone $baseTotalQuery)->sum('monto');
        $cantidadPendiente = (clone $baseTotalQuery)->count();

        $totalVencido = (float) (clone $baseTotalQuery)
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<', $hoy->toDateString())
            ->sum('monto');
        $cantidadVencido = (clone $baseTotalQuery)
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<', $hoy->toDateString())
            ->count();

        $totalPorVencer7Dias = (float) (clone $baseTotalQuery)
            ->whereNotNull('fecha_vencimiento')
            ->whereBetween('fecha_vencimiento', [$hoy->toDateString(), $en7Dias->toDateString()])
            ->sum('monto');
        $cantidadPorVencer7Dias = (clone $baseTotalQuery)
            ->whereNotNull('fecha_vencimiento')
            ->whereBetween('fecha_vencimiento', [$hoy->toDateString(), $en7Dias->toDateString()])
            ->count();

        $proveedores = Proveedor::query()
            ->where('activo', true)
            ->orderBy('razon_social')
            ->get(['id', 'ruc', 'razon_social']);

        $centrosCosto = CentroCosto::query()
            ->where('activo', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        return Inertia::render('finanzas/cuentas-por-pagar', [
            'cuentas' => $cuentas,
            'proveedores' => $proveedores,
            'centrosCosto' => $centrosCosto,
            'totales' => [
                'total_pendiente' => $totalPendiente,
                'cantidad_pendiente' => $cantidadPendiente,
                'total_vencido' => $totalVencido,
                'cantidad_vencido' => $cantidadVencido,
                'total_por_vencer' => $totalPorVencer7Dias,
                'cantidad_por_vencer' => $cantidadPorVencer7Dias,
            ],
            'filtros' => [
                'proveedor_id' => $proveedorId,
                'centro_costo_id' => $centroCostoId,
                'estado_vencimiento' => $estadoVencimiento,
            ],
        ]);
    }
}
