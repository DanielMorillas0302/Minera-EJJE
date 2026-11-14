<?php

namespace App\Services\Gerencia;

use App\Enums\EstadoEnvio;
use App\Enums\EstadoRequerimiento;
use App\Enums\EstadoTrabajador;
use App\Enums\TipoMovimientoFinanciero;
use App\Enums\UbicacionTrabajador;
use App\Models\Core\Sede;
use App\Models\Finanzas\MovimientoFinanciero;
use App\Models\Logistica\Almacen;
use App\Models\Logistica\Envio;
use App\Models\Logistica\Requerimiento;
use App\Models\Rrhh\ExamenOcupacional;
use App\Models\Rrhh\Trabajador;
use App\Services\Finanzas\FinanzasService;
use App\Services\Logistica\InventarioService;
use App\Services\Rrhh\AptitudViajeService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class GerenciaService
{
    public function __construct(
        private readonly FinanzasService $finanzasService,
        private readonly InventarioService $inventarioService,
        private readonly AptitudViajeService $aptitudService,
    ) {}

    /**
     * Consolida los KPIs estratégicos de RR.HH., Logística y Finanzas.
     *
     * @return array<string, mixed>
     */
    public function kpisGenerales(?int $sedeId = null, ?int $anio = null, ?int $mes = null): array
    {
        $ahora = Carbon::now();
        $anio ??= (int) $ahora->year;
        $mes ??= (int) $ahora->month;
        $hoy = $ahora->toDateString();
        $en30Dias = $ahora->copy()->addDays(30)->toDateString();

        // 1. Métricas de RR.HH.
        $trabajadoresQuery = Trabajador::query()
            ->where('estado', EstadoTrabajador::ACTIVO->value)
            ->whereNull('eliminado_en')
            ->when($sedeId, fn ($q) => $q->where('sede_base_id', $sedeId));

        $personalActivoTotal = (clone $trabajadoresQuery)->count();

        $personalTrujillo = (clone $trabajadoresQuery)
            ->where('ubicacion_actual', UbicacionTrabajador::TRUJILLO->value)
            ->count();

        $personalPataz = (clone $trabajadoresQuery)
            ->where('ubicacion_actual', UbicacionTrabajador::PATAZ->value)
            ->count();

        $personalTransito = (clone $trabajadoresQuery)
            ->where('ubicacion_actual', UbicacionTrabajador::EN_TRANSITO->value)
            ->count();

        // Evaluación de aptitud de trabajadores activos
        $trabajadoresConExamen = (clone $trabajadoresQuery)->with('examenes')->get();
        $personalApto = 0;
        $personalNoApto = 0;

        foreach ($trabajadoresConExamen as $t) {
            $eval = $t->detalleAptitudViaje();
            if ($eval['apto']) {
                $personalApto++;
            } else {
                $personalNoApto++;
            }
        }

        // Exámenes médicos
        $examenesQuery = ExamenOcupacional::query()
            ->whereNull('eliminado_en')
            ->whereHas('trabajador', function ($q) use ($sedeId): void {
                $q->where('estado', EstadoTrabajador::ACTIVO->value)
                    ->when($sedeId, fn ($sq) => $sq->where('sede_base_id', $sedeId));
            });

        $examenesPorVencer = (clone $examenesQuery)
            ->whereBetween('fecha_vencimiento', [$hoy, $en30Dias])
            ->count();

        $examenesVencidos = (clone $examenesQuery)
            ->whereDate('fecha_vencimiento', '<', $hoy)
            ->count();

        // 2. Métricas de Logística
        $almacenesSedeIds = null;
        if ($sedeId) {
            $almacenesSedeIds = Almacen::query()->where('sede_id', $sedeId)->pluck('id')->all();
        }

        $enviosQuery = Envio::query()
            ->whereNull('eliminado_en')
            ->when($almacenesSedeIds, function ($q) use ($almacenesSedeIds): void {
                $q->where(function ($sub) use ($almacenesSedeIds): void {
                    $sub->whereIn('almacen_origen_id', $almacenesSedeIds)
                        ->orWhereIn('almacen_destino_id', $almacenesSedeIds);
                });
            });

        $enviosEnTransito = (clone $enviosQuery)
            ->where('estado', EstadoEnvio::EN_TRANSITO->value)
            ->count();

        $enviosRetrasados = (clone $enviosQuery)
            ->where('estado', EstadoEnvio::EN_TRANSITO->value)
            ->whereDate('fecha_estimada_llegada', '<', $hoy)
            ->count();

        // Materiales en stock crítico (cantidad <= stock_minimo)
        $stockCriticoAlertas = $this->inventarioService->alertasStockMinimo($almacenesSedeIds);
        $materialesBajoStockMinimo = $stockCriticoAlertas->count();

        $requerimientosPendientes = Requerimiento::query()
            ->whereNull('eliminado_en')
            ->whereIn('estado', [
                EstadoRequerimiento::ENVIADO->value,
                EstadoRequerimiento::APROBADO->value,
                EstadoRequerimiento::ATENDIDO_PARCIAL->value,
            ])
            ->when($sedeId, fn ($q) => $q->where('sede_id', $sedeId))
            ->count();

        // 3. Métricas de Finanzas
        $finanzasKpis = $this->finanzasService->resumenKpis($anio, $mes);

        return [
            'periodo' => [
                'anio' => $anio,
                'mes' => $mes,
                'mes_nombre' => ucfirst(Carbon::createFromDate($anio, $mes, 1)->translatedFormat('F')),
            ],
            'sede_id' => $sedeId,
            'rrhh' => [
                'personal_activo_total' => $personalActivoTotal,
                'personal_trujillo' => $personalTrujillo,
                'personal_pataz' => $personalPataz,
                'personal_transito' => $personalTransito,
                'personal_apto' => $personalApto,
                'personal_no_apto' => $personalNoApto,
                'examenes_por_vencer_30d' => $examenesPorVencer,
                'examenes_vencidos' => $examenesVencidos,
            ],
            'logistica' => [
                'envios_en_transito' => $enviosEnTransito,
                'envios_retrasados' => $enviosRetrasados,
                'materiales_stock_critico' => $materialesBajoStockMinimo,
                'requerimientos_pendientes' => $requerimientosPendientes,
            ],
            'finanzas' => [
                'total_ingresos_mes' => $finanzasKpis['total_ingresos_mes'],
                'total_egresos_mes' => $finanzasKpis['total_egresos_mes'],
                'saldo_neto_mes' => $finanzasKpis['saldo_neto_mes'],
                'presupuesto_total_mes' => $finanzasKpis['presupuesto_total_mes'],
                'porcentaje_ejecucion' => $finanzasKpis['porcentaje_ejecucion'],
                'cuentas_pendientes_total' => $finanzasKpis['cuentas_pendientes_total'],
                'cuentas_pendientes_count' => $finanzasKpis['cuentas_pendientes_count'],
                'cuentas_vencidas_total' => $finanzasKpis['cuentas_vencidas_total'],
                'cuentas_vencidas_count' => $finanzasKpis['cuentas_vencidas_count'],
            ],
        ];
    }

    /**
     * Datos para gráficos estadísticos (Chart.js / React-Chartjs-2).
     *
     * @return array<string, mixed>
     */
    public function datosGraficos(int $anio, int $mes, ?int $sedeId = null): array
    {
        // 1. Gráfico de Línea: Evolución mensual de Ingresos vs Egresos del año
        $mesesNombres = ['Ene', 'Feb', 'Mar', 'Abr', 'May', 'Jun', 'Jul', 'Ago', 'Set', 'Oct', 'Nov', 'Dic'];
        $ingresosPorMes = array_fill(1, 12, 0.0);
        $egresosPorMes = array_fill(1, 12, 0.0);

        $inicioAnio = Carbon::createFromDate($anio, 1, 1)->startOfYear()->toDateString();
        $finAnio = Carbon::createFromDate($anio, 12, 31)->endOfYear()->toDateString();

        $driver = DB::connection()->getDriverName();
        $expresionMes = $driver === 'sqlite'
            ? "CAST(strftime('%m', fecha) AS INTEGER)"
            : 'MONTH(fecha)';

        $movimientosAnio = MovimientoFinanciero::query()
            ->selectRaw("{$expresionMes} as mes_num, tipo, SUM(monto) as total")
            ->whereBetween('fecha', [$inicioAnio, $finAnio])
            ->whereNull('eliminado_en')
            ->groupBy(DB::raw($expresionMes), 'tipo')
            ->get();

        foreach ($movimientosAnio as $row) {
            $m = (int) $row->mes_num;
            if ($row->tipo === TipoMovimientoFinanciero::INGRESO) {
                $ingresosPorMes[$m] = (float) $row->total;
            } elseif ($row->tipo === TipoMovimientoFinanciero::EGRESO) {
                $egresosPorMes[$m] = (float) $row->total;
            }
        }

        $graficoGastoMensual = [
            'labels' => $mesesNombres,
            'ingresos' => array_values($ingresosPorMes),
            'egresos' => array_values($egresosPorMes),
        ];

        // 2. Gráfico de Barras: Gasto real ejecutado por Centro de Costo en el mes seleccionado
        $comparativo = $this->finanzasService->comparativoPresupuestoVsReal($anio, $mes);
        $labelsCC = [];
        $presupuestosCC = [];
        $ejecutadosCC = [];

        foreach ($comparativo['items'] as $item) {
            $labelsCC[] = $item['codigo'];
            $presupuestosCC[] = $item['presupuesto'];
            $ejecutadosCC[] = $item['ejecutado'];
        }

        $graficoCentroCosto = [
            'labels' => $labelsCC,
            'presupuesto' => $presupuestosCC,
            'ejecutado' => $ejecutadosCC,
        ];

        // 3. Gráfico de Dona: Envíos por Estado
        $enviosPorEstado = Envio::query()
            ->select('estado', DB::raw('COUNT(*) as total'))
            ->whereNull('eliminado_en')
            ->groupBy('estado')
            ->pluck('total', 'estado')
            ->all();

        $estadosMap = [
            EstadoEnvio::PREPARADO->value => 'Preparado',
            EstadoEnvio::EN_TRANSITO->value => 'En tránsito',
            EstadoEnvio::RECIBIDO->value => 'Recibido conforme',
            EstadoEnvio::RECIBIDO_CON_OBSERVACIONES->value => 'Con observaciones',
            EstadoEnvio::ANULADO->value => 'Anulado',
        ];

        $labelsEnvios = [];
        $valoresEnvios = [];

        foreach ($estadosMap as $key => $label) {
            $labelsEnvios[] = $label;
            $valoresEnvios[] = $enviosPorEstado[$key] ?? 0;
        }

        $graficoEnvios = [
            'labels' => $labelsEnvios,
            'valores' => $valoresEnvios,
        ];

        // 4. Gráfico de Barras: Distribución de Personal Activo por Área
        $personalPorArea = Trabajador::query()
            ->join('areas', 'trabajadores.area_id', '=', 'areas.id')
            ->where('trabajadores.estado', EstadoTrabajador::ACTIVO->value)
            ->whereNull('trabajadores.eliminado_en')
            ->when($sedeId, fn ($q) => $q->where('trabajadores.sede_base_id', $sedeId))
            ->groupBy('areas.nombre')
            ->select('areas.nombre', DB::raw('COUNT(trabajadores.id) as total'))
            ->orderByDesc('total')
            ->pluck('total', 'nombre')
            ->all();

        $graficoPersonal = [
            'labels' => array_keys($personalPorArea),
            'valores' => array_values($personalPorArea),
        ];

        // 5. Gráfico de Dona: Índice de Aptitud Médica para Pataz
        $kpis = $this->kpisGenerales($sedeId, $anio, $mes);
        $graficoAptitud = [
            'labels' => ['Apto para viaje a Pataz', 'No apto / Con restricción'],
            'valores' => [
                $kpis['rrhh']['personal_apto'],
                $kpis['rrhh']['personal_no_apto'],
            ],
        ];

        return [
            'gasto_mensual' => $graficoGastoMensual,
            'presupuesto_vs_real_cc' => $graficoCentroCosto,
            'envios_por_estado' => $graficoEnvios,
            'personal_por_area' => $graficoPersonal,
            'aptitud_medica' => $graficoAptitud,
        ];
    }

    /**
     * Estructura completa de datos consolidada para el Informe Ejecutivo Gerencial (PDF).
     *
     * @return array<string, mixed>
     */
    public function reporteEjecutivo(int $anio, int $mes, ?int $sedeId = null): array
    {
        $kpis = $this->kpisGenerales($sedeId, $anio, $mes);
        $comparativo = $this->finanzasService->comparativoPresupuestoVsReal($anio, $mes);
        $sede = $sedeId ? Sede::find($sedeId) : null;

        $enviosRecientes = Envio::query()
            ->with(['almacenOrigen', 'almacenDestino'])
            ->whereNull('eliminado_en')
            ->orderByDesc('id')
            ->limit(5)
            ->get();

        $stockCritico = $this->inventarioService->alertasStockMinimo($sedeId ? Almacen::where('sede_id', $sedeId)->pluck('id')->all() : null)
            ->take(8)
            ->values();

        return [
            'anio' => $anio,
            'mes' => $mes,
            'mes_nombre' => ucfirst(Carbon::createFromDate($anio, $mes, 1)->translatedFormat('F')),
            'sede' => $sede?->nombre ?? 'Todas las Sedes (Consolidado Trujillo - Pataz)',
            'fechaEmision' => Carbon::now()->format('d/m/Y H:i'),
            'kpis' => $kpis,
            'comparativo' => $comparativo,
            'envios' => $enviosRecientes,
            'stockCritico' => $stockCritico,
        ];
    }
}
