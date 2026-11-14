<?php

namespace App\Services\Finanzas;

use App\Enums\EstadoPago;
use App\Enums\TipoMovimientoFinanciero;
use App\Models\Core\Adjunto;
use App\Models\Finanzas\CentroCosto;
use App\Models\Finanzas\MovimientoFinanciero;
use App\Models\Finanzas\Presupuesto;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FinanzasService
{
    /**
     * Resumen de KPIs para el mes y año dados (por defecto mes/año actual).
     *
     * @return array<string, mixed>
     */
    public function resumenKpis(?int $anio = null, ?int $mes = null): array
    {
        $ahora = Carbon::now();
        $anio ??= (int) $ahora->year;
        $mes ??= (int) $ahora->month;

        $inicioMes = Carbon::createFromDate($anio, $mes, 1)->startOfMonth()->toDateString();
        $finMes = Carbon::createFromDate($anio, $mes, 1)->endOfMonth()->toDateString();
        $hoy = $ahora->toDateString();

        // Totales del mes
        $totalIngresosMes = (float) MovimientoFinanciero::query()
            ->where('tipo', TipoMovimientoFinanciero::INGRESO->value)
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->sum('monto');

        $totalEgresosMes = (float) MovimientoFinanciero::query()
            ->where('tipo', TipoMovimientoFinanciero::EGRESO->value)
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->sum('monto');

        // Presupuesto asignado para el mes
        $presupuestoTotalMes = (float) Presupuesto::query()
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->where('activo', true)
            ->sum('monto_presupuestado');

        $porcentajeEjecucion = $presupuestoTotalMes > 0
            ? round(($totalEgresosMes / $presupuestoTotalMes) * 100, 1)
            : ($totalEgresosMes > 0 ? 100.0 : 0.0);

        // Cuentas por pagar (Egresos pendientes)
        $cuentasPendientesQuery = MovimientoFinanciero::query()
            ->where('tipo', TipoMovimientoFinanciero::EGRESO->value)
            ->where('estado_pago', EstadoPago::PENDIENTE->value);

        $totalCuentasPendientes = (float) (clone $cuentasPendientesQuery)->sum('monto');
        $cantidadCuentasPendientes = (clone $cuentasPendientesQuery)->count();

        // Cuentas vencidas (pendientes con fecha_vencimiento < hoy)
        $cuentasVencidasQuery = (clone $cuentasPendientesQuery)
            ->whereNotNull('fecha_vencimiento')
            ->whereDate('fecha_vencimiento', '<', $hoy);

        $totalCuentasVencidas = (float) (clone $cuentasVencidasQuery)->sum('monto');
        $cantidadCuentasVencidas = (clone $cuentasVencidasQuery)->count();

        return [
            'anio' => $anio,
            'mes' => $mes,
            'mes_nombre' => ucfirst(Carbon::createFromDate($anio, $mes, 1)->translatedFormat('F')),
            'total_ingresos_mes' => $totalIngresosMes,
            'total_egresos_mes' => $totalEgresosMes,
            'saldo_neto_mes' => round($totalIngresosMes - $totalEgresosMes, 2),
            'presupuesto_total_mes' => $presupuestoTotalMes,
            'porcentaje_ejecucion' => $porcentajeEjecucion,
            'cuentas_pendientes_total' => $totalCuentasPendientes,
            'cuentas_pendientes_count' => $cantidadCuentasPendientes,
            'cuentas_vencidas_total' => $totalCuentasVencidas,
            'cuentas_vencidas_count' => $cantidadCuentasVencidas,
        ];
    }

    /**
     * Matriz de comparación Presupuesto vs Real por centro de costo.
     *
     * @return array{
     *     anio: int,
     *     mes: int,
     *     mes_nombre: string,
     *     items: list<array<string, mixed>>,
     *     totales: array<string, float|int>
     * }
     */
    public function comparativoPresupuestoVsReal(int $anio, int $mes): array
    {
        $inicioMes = Carbon::createFromDate($anio, $mes, 1)->startOfMonth()->toDateString();
        $finMes = Carbon::createFromDate($anio, $mes, 1)->endOfMonth()->toDateString();

        $centrosCosto = CentroCosto::query()
            ->with('area')
            ->where('activo', true)
            ->orderBy('codigo')
            ->get();

        $presupuestos = Presupuesto::query()
            ->where('anio', $anio)
            ->where('mes', $mes)
            ->where('activo', true)
            ->get()
            ->keyBy('centro_costo_id');

        $egresosPorCentro = MovimientoFinanciero::query()
            ->select('centro_costo_id', DB::raw('SUM(monto) as total_ejecutado'))
            ->where('tipo', TipoMovimientoFinanciero::EGRESO->value)
            ->whereBetween('fecha', [$inicioMes, $finMes])
            ->groupBy('centro_costo_id')
            ->pluck('total_ejecutado', 'centro_costo_id');

        $items = [];
        $totalPresupuestado = 0.0;
        $totalEjecutado = 0.0;

        foreach ($centrosCosto as $cc) {
            $montoPresupuesto = (float) ($presupuestos->get($cc->id)?->monto_presupuestado ?? 0);
            $montoEjecutado = (float) ($egresosPorCentro->get($cc->id) ?? 0);
            $diferencia = round($montoPresupuesto - $montoEjecutado, 2);

            $porcentaje = $montoPresupuesto > 0
                ? round(($montoEjecutado / $montoPresupuesto) * 100, 1)
                : ($montoEjecutado > 0 ? 100.0 : 0.0);

            // Niveles de alerta especificados:
            // < 80% NORMAL, 80-90% ADVERTENCIA, >90% ALERTA, >100% SOBREGIRADO
            if ($porcentaje > 100) {
                $estado = 'SOBREGIRADO';
                $color = 'destructive';
            } elseif ($porcentaje > 90) {
                $estado = 'ALERTA';
                $color = 'amber';
            } elseif ($porcentaje >= 80) {
                $estado = 'ADVERTENCIA';
                $color = 'yellow';
            } else {
                $estado = 'NORMAL';
                $color = 'emerald';
            }

            $items[] = [
                'centro_costo_id' => $cc->id,
                'codigo' => $cc->codigo,
                'nombre' => $cc->nombre,
                'area_nombre' => $cc->area?->nombre ?? 'Sin área',
                'presupuesto' => $montoPresupuesto,
                'ejecutado' => $montoEjecutado,
                'diferencia' => $diferencia,
                'porcentaje' => $porcentaje,
                'estado' => $estado,
                'color' => $color,
            ];

            $totalPresupuestado += $montoPresupuesto;
            $totalEjecutado += $montoEjecutado;
        }

        $totalDiferencia = round($totalPresupuestado - $totalEjecutado, 2);
        $porcentajeGlobal = $totalPresupuestado > 0
            ? round(($totalEjecutado / $totalPresupuestado) * 100, 1)
            : ($totalEjecutado > 0 ? 100.0 : 0.0);

        return [
            'anio' => $anio,
            'mes' => $mes,
            'mes_nombre' => ucfirst(Carbon::createFromDate($anio, $mes, 1)->translatedFormat('F')),
            'items' => $items,
            'totales' => [
                'presupuesto' => round($totalPresupuestado, 2),
                'ejecutado' => round($totalEjecutado, 2),
                'diferencia' => $totalDiferencia,
                'porcentaje' => $porcentajeGlobal,
            ],
        ];
    }

    /**
     * Crea o actualiza el presupuesto mensual para un centro de costo.
     */
    public function guardarPresupuesto(
        int $centroCostoId,
        int $anio,
        int $mes,
        float $monto,
        ?string $notas = null
    ): Presupuesto {
        return Presupuesto::updateOrCreate(
            [
                'centro_costo_id' => $centroCostoId,
                'anio' => $anio,
                'mes' => $mes,
            ],
            [
                'monto_presupuestado' => $monto,
                'notas' => $notas,
                'activo' => true,
            ]
        );
    }

    /**
     * Registra un nuevo movimiento financiero con soporte para comprobante adjunto.
     *
     * @param  array<string, mixed>  $datos
     */
    public function guardarMovimiento(
        array $datos,
        ?UploadedFile $archivo = null,
        ?string $descripcionAdjunto = null
    ): MovimientoFinanciero {
        return DB::transaction(function () use ($datos, $archivo, $descripcionAdjunto): MovimientoFinanciero {
            $estadoPago = $datos['estado_pago'] ?? EstadoPago::PAGADO->value;
            $fecha = $datos['fecha'] ?? now()->toDateString();
            $fechaPago = $datos['fecha_pago'] ?? null;

            if ($estadoPago === EstadoPago::PAGADO->value && empty($fechaPago)) {
                $fechaPago = $fecha;
            }

            $datos['estado_pago'] = $estadoPago;
            $datos['fecha_pago'] = $fechaPago;
            $datos['moneda'] = $datos['moneda'] ?? 'PEN';

            $movimiento = MovimientoFinanciero::create($datos);

            if ($archivo) {
                $this->guardarAdjunto($archivo, $movimiento, $descripcionAdjunto);
            }

            return $movimiento;
        });
    }

    /**
     * Actualiza un movimiento financiero existente.
     *
     * @param  array<string, mixed>  $datos
     */
    public function actualizarMovimiento(
        MovimientoFinanciero $movimiento,
        array $datos,
        ?UploadedFile $archivo = null,
        ?string $descripcionAdjunto = null
    ): MovimientoFinanciero {
        return DB::transaction(function () use ($movimiento, $datos, $archivo, $descripcionAdjunto): MovimientoFinanciero {
            $estadoPago = $datos['estado_pago'] ?? $movimiento->estado_pago->value;
            $fecha = $datos['fecha'] ?? $movimiento->fecha->toDateString();
            $fechaPago = $datos['fecha_pago'] ?? $movimiento->fecha_pago?->toDateString();

            if ($estadoPago === EstadoPago::PAGADO->value && empty($fechaPago)) {
                $fechaPago = $fecha;
            } elseif ($estadoPago === EstadoPago::PENDIENTE->value) {
                $fechaPago = null;
            }

            $datos['estado_pago'] = $estadoPago;
            $datos['fecha_pago'] = $fechaPago;

            $movimiento->update($datos);

            if ($archivo) {
                $this->guardarAdjunto($archivo, $movimiento, $descripcionAdjunto);
            }

            return $movimiento->fresh();
        });
    }

    /**
     * Marca un movimiento financiero pendiente como PAGADO.
     */
    public function marcarPagado(
        MovimientoFinanciero $movimiento,
        ?string $fechaPago = null,
        ?string $comprobanteNumero = null
    ): MovimientoFinanciero {
        $movimiento->estado_pago = EstadoPago::PAGADO;
        $movimiento->fecha_pago = $fechaPago ?? now()->toDateString();

        if ($comprobanteNumero) {
            $movimiento->comprobante_numero = $comprobanteNumero;
        }

        $movimiento->save();

        return $movimiento;
    }

    /**
     * Reporte: Egresos agrupados por Centro de Costo en un rango de fechas.
     *
     * @return Collection<int, object>
     */
    public function reporteEgresosPorCentroCosto(?string $desde = null, ?string $hasta = null): Collection
    {
        $consulta = MovimientoFinanciero::query()
            ->join('centros_costo', 'movimientos_financieros.centro_costo_id', '=', 'centros_costo.id')
            ->leftJoin('areas', 'centros_costo.area_id', '=', 'areas.id')
            ->where('movimientos_financieros.tipo', TipoMovimientoFinanciero::EGRESO->value)
            ->whereNull('movimientos_financieros.eliminado_en');

        if ($desde) {
            $consulta->whereDate('movimientos_financieros.fecha', '>=', $desde);
        }
        if ($hasta) {
            $consulta->whereDate('movimientos_financieros.fecha', '<=', $hasta);
        }

        return $consulta
            ->select(
                'centros_costo.id as centro_costo_id',
                'centros_costo.codigo',
                'centros_costo.nombre',
                'areas.nombre as area_nombre',
                DB::raw('COUNT(movimientos_financieros.id) as total_movimientos'),
                DB::raw('SUM(movimientos_financieros.monto) as total_monto')
            )
            ->groupBy('centros_costo.id', 'centros_costo.codigo', 'centros_costo.nombre', 'areas.nombre')
            ->orderByDesc('total_monto')
            ->get();
    }

    /**
     * Reporte: Egresos agrupados por Categoría de Gasto en un rango de fechas.
     *
     * @return Collection<int, object>
     */
    public function reporteEgresosPorCategoria(?string $desde = null, ?string $hasta = null): Collection
    {
        $consulta = MovimientoFinanciero::query()
            ->join('categorias_gasto', 'movimientos_financieros.categoria_id', '=', 'categorias_gasto.id')
            ->where('movimientos_financieros.tipo', TipoMovimientoFinanciero::EGRESO->value)
            ->whereNull('movimientos_financieros.eliminado_en');

        if ($desde) {
            $consulta->whereDate('movimientos_financieros.fecha', '>=', $desde);
        }
        if ($hasta) {
            $consulta->whereDate('movimientos_financieros.fecha', '<=', $hasta);
        }

        return $consulta
            ->select(
                'categorias_gasto.id as categoria_id',
                'categorias_gasto.nombre',
                DB::raw('COUNT(movimientos_financieros.id) as total_movimientos'),
                DB::raw('SUM(movimientos_financieros.monto) as total_monto')
            )
            ->groupBy('categorias_gasto.id', 'categorias_gasto.nombre')
            ->orderByDesc('total_monto')
            ->get();
    }

    /**
     * Reporte: Cuentas por pagar pendientes ordenadas por fecha de vencimiento.
     *
     * @return Collection<int, MovimientoFinanciero>
     */
    public function reporteCuentasPorPagar(): Collection
    {
        return MovimientoFinanciero::query()
            ->with(['proveedor', 'centroCosto', 'categoria'])
            ->where('tipo', TipoMovimientoFinanciero::EGRESO->value)
            ->where('estado_pago', EstadoPago::PENDIENTE->value)
            ->orderByRaw('CASE WHEN fecha_vencimiento IS NULL THEN 1 ELSE 0 END')
            ->orderBy('fecha_vencimiento')
            ->orderBy('id')
            ->get();
    }

    /**
     * Guarda un archivo adjunto polimórfico en storage.
     */
    public function guardarAdjunto(UploadedFile $archivo, MovimientoFinanciero $movimiento, ?string $descripcion = null): Adjunto
    {
        $ruta = $archivo->store('finanzas/comprobantes', 'local');

        return Adjunto::create([
            'archivo' => $ruta,
            'nombre_original' => $archivo->getClientOriginalName(),
            'mime_type' => $archivo->getClientMimeType(),
            'tamano' => $archivo->getSize(),
            'descripcion' => ($descripcion ?: 'Comprobante financiero'),
            'entidad_type' => MovimientoFinanciero::class,
            'entidad_id' => $movimiento->id,
            'activo' => true,
        ]);
    }
}
