<?php

namespace Tests\Unit;

use App\Enums\EstadoPago;
use App\Enums\TipoComprobante;
use App\Enums\TipoMovimientoFinanciero;
use App\Enums\TipoSede;
use App\Models\Core\Area;
use App\Models\Core\Sede;
use App\Models\Finanzas\CategoriaGasto;
use App\Models\Finanzas\CentroCosto;
use App\Models\Finanzas\MovimientoFinanciero;
use App\Models\Finanzas\Presupuesto;
use App\Models\Logistica\Proveedor;
use App\Services\Finanzas\FinanzasService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FinanzasServiciosTest extends TestCase
{
    use RefreshDatabase;

    private FinanzasService $servicio;

    protected function setUp(): void
    {
        parent::setUp();
        $this->servicio = new FinanzasService;
    }

    private function crearEscenarioFinanciero(): array
    {
        $sede = Sede::create([
            'nombre' => 'Pataz Mina',
            'tipo' => TipoSede::OPERATIVA,
            'ubicacion' => 'Pataz',
            'activo' => true,
        ]);

        $area = Area::create([
            'nombre' => 'Operaciones',
            'sede_id' => $sede->id,
            'activo' => true,
        ]);

        $ccMina = CentroCosto::create([
            'codigo' => 'CC-MINA',
            'nombre' => 'Operación Mina',
            'area_id' => $area->id,
            'activo' => true,
        ]);

        $ccPlanta = CentroCosto::create([
            'codigo' => 'CC-PLANTA',
            'nombre' => 'Planta de Procesamiento',
            'area_id' => $area->id,
            'activo' => true,
        ]);

        $catCombustible = CategoriaGasto::create([
            'nombre' => 'Combustibles',
            'tipo' => TipoMovimientoFinanciero::EGRESO,
            'activo' => true,
        ]);

        $catMineral = CategoriaGasto::create([
            'nombre' => 'Venta Mineral',
            'tipo' => TipoMovimientoFinanciero::INGRESO,
            'activo' => true,
        ]);

        $proveedor = Proveedor::create([
            'ruc' => '20123456789',
            'razon_social' => 'Petróleo del Perú S.A.',
            'activo' => true,
        ]);

        return [
            'sede' => $sede,
            'area' => $area,
            'ccMina' => $ccMina,
            'ccPlanta' => $ccPlanta,
            'catCombustible' => $catCombustible,
            'catMineral' => $catMineral,
            'proveedor' => $proveedor,
        ];
    }

    public function test_kpis_financieros_calculan_ingresos_egresos_saldo_y_porcentaje_ejecucion(): void
    {
        $esc = $this->crearEscenarioFinanciero();
        $anio = 2026;
        $mes = 5;

        // Presupuesto de CC Mina = 50,000
        Presupuesto::create([
            'centro_costo_id' => $esc['ccMina']->id,
            'anio' => $anio,
            'mes' => $mes,
            'monto_presupuestado' => 50000.00,
            'activo' => true,
        ]);

        // Ingreso del mes = 80,000
        MovimientoFinanciero::create([
            'tipo' => TipoMovimientoFinanciero::INGRESO,
            'fecha' => "{$anio}-05-10",
            'monto' => 80000.00,
            'categoria_id' => $esc['catMineral']->id,
            'centro_costo_id' => $esc['ccMina']->id,
            'concepto' => 'Cobranza lote de mineral',
            'comprobante_tipo' => TipoComprobante::FACTURA,
            'estado_pago' => EstadoPago::PAGADO,
            'fecha_pago' => "{$anio}-05-10",
            'activo' => true,
        ]);

        // Egreso del mes = 35,000 (70% del presupuesto)
        MovimientoFinanciero::create([
            'tipo' => TipoMovimientoFinanciero::EGRESO,
            'fecha' => "{$anio}-05-15",
            'monto' => 35000.00,
            'categoria_id' => $esc['catCombustible']->id,
            'centro_costo_id' => $esc['ccMina']->id,
            'proveedor_id' => $esc['proveedor']->id,
            'concepto' => 'Diésel D-2 para mina',
            'comprobante_tipo' => TipoComprobante::FACTURA,
            'estado_pago' => EstadoPago::PAGADO,
            'fecha_pago' => "{$anio}-05-15",
            'activo' => true,
        ]);

        $kpis = $this->servicio->resumenKpis($anio, $mes);

        $this->assertEquals(80000.00, $kpis['total_ingresos_mes']);
        $this->assertEquals(35000.00, $kpis['total_egresos_mes']);
        $this->assertEquals(45000.00, $kpis['saldo_neto_mes']);
        $this->assertEquals(50000.00, $kpis['presupuesto_total_mes']);
        $this->assertEquals(70.0, $kpis['porcentaje_ejecucion']);
    }

    public function test_kpis_financieros_detectan_cuentas_pendientes_y_vencidas(): void
    {
        $esc = $this->crearEscenarioFinanciero();
        $hoy = Carbon::now();

        // 1. Factura Pendiente Vencida (hace 3 días)
        MovimientoFinanciero::create([
            'tipo' => TipoMovimientoFinanciero::EGRESO,
            'fecha' => $hoy->copy()->subDays(20)->toDateString(),
            'monto' => 12000.00,
            'categoria_id' => $esc['catCombustible']->id,
            'centro_costo_id' => $esc['ccMina']->id,
            'proveedor_id' => $esc['proveedor']->id,
            'concepto' => 'Factura vencida combustible',
            'comprobante_tipo' => TipoComprobante::FACTURA,
            'estado_pago' => EstadoPago::PENDIENTE,
            'fecha_vencimiento' => $hoy->copy()->subDays(3)->toDateString(),
            'activo' => true,
        ]);

        // 2. Factura Pendiente Por Vencer (en 10 días)
        MovimientoFinanciero::create([
            'tipo' => TipoMovimientoFinanciero::EGRESO,
            'fecha' => $hoy->copy()->subDays(5)->toDateString(),
            'monto' => 8000.00,
            'categoria_id' => $esc['catCombustible']->id,
            'centro_costo_id' => $esc['ccMina']->id,
            'proveedor_id' => $esc['proveedor']->id,
            'concepto' => 'Factura vigente por vencer',
            'comprobante_tipo' => TipoComprobante::FACTURA,
            'estado_pago' => EstadoPago::PENDIENTE,
            'fecha_vencimiento' => $hoy->copy()->addDays(10)->toDateString(),
            'activo' => true,
        ]);

        $kpis = $this->servicio->resumenKpis($hoy->year, $hoy->month);

        $this->assertEquals(20000.00, $kpis['cuentas_pendientes_total']);
        $this->assertEquals(2, $kpis['cuentas_pendientes_count']);
        $this->assertEquals(12000.00, $kpis['cuentas_vencidas_total']);
        $this->assertEquals(1, $kpis['cuentas_vencidas_count']);
    }

    public function test_comparativo_presupuesto_vs_real_aplica_alertas_segun_porcentaje(): void
    {
        $esc = $this->crearEscenarioFinanciero();
        $anio = 2026;
        $mes = 8;

        // CC Mina: Presupuesto 10,000, Gasto 7,000 (70%) => NORMAL
        Presupuesto::create([
            'centro_costo_id' => $esc['ccMina']->id,
            'anio' => $anio,
            'mes' => $mes,
            'monto_presupuestado' => 10000.00,
            'activo' => true,
        ]);
        MovimientoFinanciero::create([
            'tipo' => TipoMovimientoFinanciero::EGRESO,
            'fecha' => "{$anio}-08-10",
            'monto' => 7000.00,
            'categoria_id' => $esc['catCombustible']->id,
            'centro_costo_id' => $esc['ccMina']->id,
            'concepto' => 'Gasto normal',
            'comprobante_tipo' => TipoComprobante::FACTURA,
            'estado_pago' => EstadoPago::PAGADO,
            'activo' => true,
        ]);

        // CC Planta: Presupuesto 10,000, Gasto 11,500 (115%) => SOBREGIRADO
        Presupuesto::create([
            'centro_costo_id' => $esc['ccPlanta']->id,
            'anio' => $anio,
            'mes' => $mes,
            'monto_presupuestado' => 10000.00,
            'activo' => true,
        ]);
        MovimientoFinanciero::create([
            'tipo' => TipoMovimientoFinanciero::EGRESO,
            'fecha' => "{$anio}-08-12",
            'monto' => 11500.00,
            'categoria_id' => $esc['catCombustible']->id,
            'centro_costo_id' => $esc['ccPlanta']->id,
            'concepto' => 'Gasto sobregirado',
            'comprobante_tipo' => TipoComprobante::FACTURA,
            'estado_pago' => EstadoPago::PAGADO,
            'activo' => true,
        ]);

        $comparativo = $this->servicio->comparativoPresupuestoVsReal($anio, $mes);

        $itemMina = collect($comparativo['items'])->firstWhere('centro_costo_id', $esc['ccMina']->id);
        $this->assertEquals(70.0, $itemMina['porcentaje']);
        $this->assertEquals('NORMAL', $itemMina['estado']);

        $itemPlanta = collect($comparativo['items'])->firstWhere('centro_costo_id', $esc['ccPlanta']->id);
        $this->assertEquals(115.0, $itemPlanta['porcentaje']);
        $this->assertEquals('SOBREGIRADO', $itemPlanta['estado']);

        $this->assertEquals(20000.00, $comparativo['totales']['presupuesto']);
        $this->assertEquals(18500.00, $comparativo['totales']['ejecutado']);
        $this->assertEquals(1500.00, $comparativo['totales']['diferencia']);
    }

    public function test_guardar_presupuesto_es_idempotente(): void
    {
        $esc = $this->crearEscenarioFinanciero();

        $p1 = $this->servicio->guardarPresupuesto($esc['ccMina']->id, 2026, 6, 25000.00, 'Primera versión');
        $this->assertEquals(25000.00, (float) $p1->monto_presupuestado);

        // Actualización sobre la misma clave única
        $p2 = $this->servicio->guardarPresupuesto($esc['ccMina']->id, 2026, 6, 32000.00, 'Aumento aprobado');
        $this->assertEquals($p1->id, $p2->id);
        $this->assertEquals(32000.00, (float) $p2->fresh()->monto_presupuestado);

        $this->assertDatabaseCount('presupuestos', 1);
    }

    public function test_guardar_movimiento_con_archivo_adjunto(): void
    {
        Storage::fake('local');
        $esc = $this->crearEscenarioFinanciero();

        $archivo = UploadedFile::fake()->create('factura_001.pdf', 120, 'application/pdf');

        $datos = [
            'tipo' => TipoMovimientoFinanciero::EGRESO->value,
            'fecha' => '2026-06-15',
            'monto' => 1500.00,
            'moneda' => 'PEN',
            'categoria_id' => $esc['catCombustible']->id,
            'centro_costo_id' => $esc['ccMina']->id,
            'proveedor_id' => $esc['proveedor']->id,
            'concepto' => 'Compra de lubricantes',
            'comprobante_tipo' => TipoComprobante::FACTURA->value,
            'comprobante_numero' => 'F001-9988',
            'estado_pago' => EstadoPago::PAGADO->value,
        ];

        $mov = $this->servicio->guardarMovimiento($datos, $archivo, 'Factura electrónica sellada');

        $this->assertNotNull($mov->id);
        $this->assertEquals(1, $mov->adjuntos()->count());
        $adjunto = $mov->adjuntos()->first();
        $this->assertEquals('factura_001.pdf', $adjunto->nombre_original);
        Storage::disk('local')->assertExists($adjunto->archivo);
    }

    public function test_marcar_movimiento_como_pagado_actualiza_estado_y_fecha(): void
    {
        $esc = $this->crearEscenarioFinanciero();

        $mov = MovimientoFinanciero::create([
            'tipo' => TipoMovimientoFinanciero::EGRESO,
            'fecha' => '2026-06-01',
            'monto' => 4500.00,
            'categoria_id' => $esc['catCombustible']->id,
            'centro_costo_id' => $esc['ccMina']->id,
            'proveedor_id' => $esc['proveedor']->id,
            'concepto' => 'Factura pendiente de crédito',
            'comprobante_tipo' => TipoComprobante::FACTURA,
            'estado_pago' => EstadoPago::PENDIENTE,
            'fecha_vencimiento' => '2026-06-20',
            'activo' => true,
        ]);

        $this->assertEquals(EstadoPago::PENDIENTE, $mov->estado_pago);
        $this->assertNull($mov->fecha_pago);

        $this->servicio->marcarPagado($mov, '2026-06-18', 'OP-BCP-554433');

        $movActualizado = $mov->fresh();
        $this->assertEquals(EstadoPago::PAGADO, $movActualizado->estado_pago);
        $this->assertEquals('2026-06-18', $movActualizado->fecha_pago->format('Y-m-d'));
        $this->assertEquals('OP-BCP-554433', $movActualizado->comprobante_numero);
    }

    public function test_reportes_financieros_agrupan_y_totalizan_correctamente(): void
    {
        $esc = $this->crearEscenarioFinanciero();

        // 2 Egresos en CC Mina (Total = 15,000)
        MovimientoFinanciero::create([
            'tipo' => TipoMovimientoFinanciero::EGRESO,
            'fecha' => '2026-07-05',
            'monto' => 10000.00,
            'categoria_id' => $esc['catCombustible']->id,
            'centro_costo_id' => $esc['ccMina']->id,
            'concepto' => 'Diésel lote 1',
            'comprobante_tipo' => TipoComprobante::FACTURA,
            'estado_pago' => EstadoPago::PAGADO,
            'activo' => true,
        ]);
        MovimientoFinanciero::create([
            'tipo' => TipoMovimientoFinanciero::EGRESO,
            'fecha' => '2026-07-12',
            'monto' => 5000.00,
            'categoria_id' => $esc['catCombustible']->id,
            'centro_costo_id' => $esc['ccMina']->id,
            'concepto' => 'Diésel lote 2',
            'comprobante_tipo' => TipoComprobante::FACTURA,
            'estado_pago' => EstadoPago::PAGADO,
            'activo' => true,
        ]);

        // 1 Egreso en CC Planta (Total = 8,000)
        MovimientoFinanciero::create([
            'tipo' => TipoMovimientoFinanciero::EGRESO,
            'fecha' => '2026-07-18',
            'monto' => 8000.00,
            'categoria_id' => $esc['catCombustible']->id,
            'centro_costo_id' => $esc['ccPlanta']->id,
            'concepto' => 'Aceites planta',
            'comprobante_tipo' => TipoComprobante::FACTURA,
            'estado_pago' => EstadoPago::PAGADO,
            'activo' => true,
        ]);

        $repCC = $this->servicio->reporteEgresosPorCentroCosto('2026-07-01', '2026-07-31');
        $this->assertCount(2, $repCC);
        $this->assertEquals(15000.00, (float) $repCC->firstWhere('centro_costo_id', $esc['ccMina']->id)->total_monto);
        $this->assertEquals(8000.00, (float) $repCC->firstWhere('centro_costo_id', $esc['ccPlanta']->id)->total_monto);

        $repCat = $this->servicio->reporteEgresosPorCategoria('2026-07-01', '2026-07-31');
        $this->assertCount(1, $repCat);
        $this->assertEquals(23000.00, (float) $repCat->first()->total_monto);
    }
}
