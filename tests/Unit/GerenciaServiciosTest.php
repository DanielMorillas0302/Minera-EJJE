<?php

namespace Tests\Unit;

use App\Enums\EstadoEnvio;
use App\Enums\EstadoPago;
use App\Enums\EstadoRequerimiento;
use App\Enums\EstadoTrabajador;
use App\Enums\PrioridadRequerimiento;
use App\Enums\ResultadoExamen;
use App\Enums\SexoTrabajador;
use App\Enums\TipoComprobante;
use App\Enums\TipoExamenOcupacional;
use App\Enums\TipoMovimientoFinanciero;
use App\Enums\TipoSede;
use App\Enums\UbicacionTrabajador;
use App\Models\Core\Area;
use App\Models\Core\Cargo;
use App\Models\Core\Sede;
use App\Models\Core\UnidadMedida;
use App\Models\Finanzas\CategoriaGasto;
use App\Models\Finanzas\CentroCosto;
use App\Models\Finanzas\MovimientoFinanciero;
use App\Models\Finanzas\Presupuesto;
use App\Models\Logistica\Almacen;
use App\Models\Logistica\CategoriaMaterial;
use App\Models\Logistica\Envio;
use App\Models\Logistica\Material;
use App\Models\Logistica\Requerimiento;
use App\Models\Logistica\StockAlmacen;
use App\Models\Rrhh\ExamenOcupacional;
use App\Models\Rrhh\Trabajador;
use App\Models\User;
use App\Services\Gerencia\GerenciaService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GerenciaServiciosTest extends TestCase
{
    use RefreshDatabase;

    private GerenciaService $gerenciaService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->gerenciaService = app(GerenciaService::class);
    }

    private function crearDatosPrueba(): array
    {
        $sedeTrujillo = Sede::create([
            'nombre' => 'Oficina Trujillo',
            'tipo' => TipoSede::ADMINISTRATIVA,
            'ubicacion' => 'Trujillo',
            'activo' => true,
        ]);

        $sedePataz = Sede::create([
            'nombre' => 'Campamento Pataz',
            'tipo' => TipoSede::OPERATIVA,
            'ubicacion' => 'Pataz',
            'activo' => true,
        ]);

        $areaMina = Area::create([
            'nombre' => 'Mina',
            'sede_id' => $sedePataz->id,
            'activo' => true,
        ]);

        $areaAdmin = Area::create([
            'nombre' => 'Administración',
            'sede_id' => $sedeTrujillo->id,
            'activo' => true,
        ]);

        $cargoMinero = Cargo::create([
            'nombre' => 'Perforista',
            'area_id' => $areaMina->id,
            'activo' => true,
        ]);

        $cargoAdmin = Cargo::create([
            'nombre' => 'Contador',
            'area_id' => $areaAdmin->id,
            'activo' => true,
        ]);

        $usuario = User::factory()->create();

        // Trabajador 1: Pataz, con EMO vigente y apto
        $t1 = Trabajador::create([
            'dni' => '10000001',
            'nombres' => 'Juan',
            'apellidos' => 'Perez',
            'sexo' => SexoTrabajador::MASCULINO,
            'fecha_nacimiento' => '1990-01-01',
            'sede_base_id' => $sedePataz->id,
            'area_id' => $areaMina->id,
            'cargo_id' => $cargoMinero->id,
            'fecha_ingreso' => '2022-01-01',
            'estado' => EstadoTrabajador::ACTIVO,
            'ubicacion_actual' => UbicacionTrabajador::PATAZ,
        ]);

        ExamenOcupacional::create([
            'trabajador_id' => $t1->id,
            'tipo' => TipoExamenOcupacional::PERIODICO,
            'fecha_examen' => Carbon::now()->subMonths(6)->toDateString(),
            'fecha_vencimiento' => Carbon::now()->addMonths(6)->toDateString(),
            'resultado' => ResultadoExamen::APTO,
            'clinica' => 'Clinica Salud',
        ]);

        // Trabajador 2: Trujillo, examen por vencer en 15 días y con restricción
        $t2 = Trabajador::create([
            'dni' => '10000002',
            'nombres' => 'Carlos',
            'apellidos' => 'Gomez',
            'sexo' => SexoTrabajador::MASCULINO,
            'fecha_nacimiento' => '1985-05-10',
            'sede_base_id' => $sedeTrujillo->id,
            'area_id' => $areaAdmin->id,
            'cargo_id' => $cargoAdmin->id,
            'fecha_ingreso' => '2023-03-01',
            'estado' => EstadoTrabajador::ACTIVO,
            'ubicacion_actual' => UbicacionTrabajador::TRUJILLO,
        ]);

        ExamenOcupacional::create([
            'trabajador_id' => $t2->id,
            'tipo' => TipoExamenOcupacional::PERIODICO,
            'fecha_examen' => Carbon::now()->subYear()->toDateString(),
            'fecha_vencimiento' => Carbon::now()->addDays(15)->toDateString(),
            'resultado' => ResultadoExamen::APTO_CON_RESTRICCIONES,
            'observaciones' => 'No levantar más de 15kg',
            'clinica' => 'Clinica Salud',
        ]);

        // Almacenes
        $almacenTrujillo = Almacen::create([
            'sede_id' => $sedeTrujillo->id,
            'codigo' => 'ALM-TRU',
            'nombre' => 'Almacén Central Trujillo',
            'activo' => true,
        ]);

        $almacenPataz = Almacen::create([
            'sede_id' => $sedePataz->id,
            'codigo' => 'ALM-PAT',
            'nombre' => 'Almacén Mina Pataz',
            'activo' => true,
        ]);

        $unidad = UnidadMedida::create([
            'nombre' => 'Galón',
            'abreviatura' => 'GL',
            'activo' => true,
        ]);

        $categoriaMaterial = CategoriaMaterial::create([
            'nombre' => 'Combustibles y Lubricantes',
            'activo' => true,
        ]);

        $material = Material::create([
            'codigo' => 'MAT-001',
            'nombre' => 'Diesel B5',
            'categoria_id' => $categoriaMaterial->id,
            'unidad_medida_id' => $unidad->id,
            'stock_minimo' => 100,
            'activo' => true,
        ]);

        // Stock bajo mínimo en Pataz
        StockAlmacen::create([
            'almacen_id' => $almacenPataz->id,
            'material_id' => $material->id,
            'cantidad' => 30,
        ]);

        // Envíos
        Envio::create([
            'codigo' => 'ENV-001',
            'almacen_origen_id' => $almacenTrujillo->id,
            'almacen_destino_id' => $almacenPataz->id,
            'fecha_envio' => Carbon::now()->subDays(2)->toDateString(),
            'fecha_estimada_llegada' => Carbon::now()->subDay()->toDateString(), // retrasado
            'responsable_envio_id' => $usuario->id,
            'estado' => EstadoEnvio::EN_TRANSITO,
        ]);

        // Requerimiento pendiente
        Requerimiento::create([
            'codigo' => 'REQ-001',
            'sede_id' => $sedePataz->id,
            'area_id' => $areaMina->id,
            'solicitante_id' => $usuario->id,
            'fecha_solicitud' => Carbon::now()->toDateString(),
            'prioridad' => PrioridadRequerimiento::ALTA,
            'estado' => EstadoRequerimiento::APROBADO,
        ]);

        // Finanzas
        $centroCosto = CentroCosto::create([
            'codigo' => 'CC-MINA',
            'nombre' => 'Operaciones Mina',
            'area_id' => $areaMina->id,
            'activo' => true,
        ]);

        $categoria = CategoriaGasto::create([
            'codigo' => 'CAT-COM',
            'nombre' => 'Combustibles',
            'activo' => true,
        ]);

        $anio = Carbon::now()->year;
        $mes = Carbon::now()->month;

        Presupuesto::create([
            'anio' => $anio,
            'mes' => $mes,
            'centro_costo_id' => $centroCosto->id,
            'monto_presupuestado' => 50000.00,
            'activo' => true,
        ]);

        MovimientoFinanciero::create([
            'tipo' => TipoMovimientoFinanciero::INGRESO,
            'fecha' => Carbon::now()->toDateString(),
            'centro_costo_id' => $centroCosto->id,
            'categoria_id' => $categoria->id,
            'concepto' => 'Cobro por venta de mineral',
            'comprobante_tipo' => TipoComprobante::FACTURA,
            'comprobante_numero' => 'F001-001',
            'monto' => 80000.00,
            'estado_pago' => EstadoPago::PAGADO,
        ]);

        MovimientoFinanciero::create([
            'tipo' => TipoMovimientoFinanciero::EGRESO,
            'fecha' => Carbon::now()->toDateString(),
            'centro_costo_id' => $centroCosto->id,
            'categoria_id' => $categoria->id,
            'concepto' => 'Compra de combustible mina',
            'comprobante_tipo' => TipoComprobante::FACTURA,
            'comprobante_numero' => 'F001-002',
            'monto' => 30000.00,
            'estado_pago' => EstadoPago::PAGADO,
        ]);

        return [
            'sedeTrujillo' => $sedeTrujillo,
            'sedePataz' => $sedePataz,
            'anio' => $anio,
            'mes' => $mes,
        ];
    }

    public function test_kpis_generales_consolida_todas_las_areas(): void
    {
        $datos = $this->crearDatosPrueba();

        $kpis = $this->gerenciaService->kpisGenerales(null, $datos['anio'], $datos['mes']);

        // Verificaciones de RR.HH.
        $this->assertEquals(2, $kpis['rrhh']['personal_activo_total']);
        $this->assertEquals(1, $kpis['rrhh']['personal_trujillo']);
        $this->assertEquals(1, $kpis['rrhh']['personal_pataz']);
        $this->assertEquals(1, $kpis['rrhh']['personal_apto']);
        $this->assertEquals(1, $kpis['rrhh']['personal_no_apto']);
        $this->assertEquals(1, $kpis['rrhh']['examenes_por_vencer_30d']);

        // Verificaciones de Logística
        $this->assertEquals(1, $kpis['logistica']['envios_en_transito']);
        $this->assertEquals(1, $kpis['logistica']['envios_retrasados']);
        $this->assertEquals(1, $kpis['logistica']['materiales_stock_critico']);
        $this->assertEquals(1, $kpis['logistica']['requerimientos_pendientes']);

        // Verificaciones de Finanzas
        $this->assertEquals(80000.00, $kpis['finanzas']['total_ingresos_mes']);
        $this->assertEquals(30000.00, $kpis['finanzas']['total_egresos_mes']);
        $this->assertEquals(50000.00, $kpis['finanzas']['saldo_neto_mes']);
        $this->assertEquals(60.0, $kpis['finanzas']['porcentaje_ejecucion']);
    }

    public function test_kpis_generales_filtra_por_sede(): void
    {
        $datos = $this->crearDatosPrueba();

        $kpisPataz = $this->gerenciaService->kpisGenerales($datos['sedePataz']->id, $datos['anio'], $datos['mes']);

        $this->assertEquals(1, $kpisPataz['rrhh']['personal_activo_total']);
        $this->assertEquals(1, $kpisPataz['rrhh']['personal_pataz']);
        $this->assertEquals(0, $kpisPataz['rrhh']['personal_trujillo']);
    }

    public function test_datos_graficos_genera_series_completas(): void
    {
        $datos = $this->crearDatosPrueba();

        $graficos = $this->gerenciaService->datosGraficos($datos['anio'], $datos['mes'], null);

        $this->assertArrayHasKey('gasto_mensual', $graficos);
        $this->assertArrayHasKey('presupuesto_vs_real_cc', $graficos);
        $this->assertArrayHasKey('envios_por_estado', $graficos);
        $this->assertArrayHasKey('personal_por_area', $graficos);
        $this->assertArrayHasKey('aptitud_medica', $graficos);

        // 12 meses en gráfico de línea
        $this->assertCount(12, $graficos['gasto_mensual']['labels']);
        $this->assertCount(12, $graficos['gasto_mensual']['ingresos']);
        $this->assertCount(12, $graficos['gasto_mensual']['egresos']);

        // Centro de costo
        $this->assertContains('CC-MINA', $graficos['presupuesto_vs_real_cc']['labels']);

        // Envíos por estado
        $this->assertContains('En tránsito', $graficos['envios_por_estado']['labels']);
    }

    public function test_reporte_ejecutivo_consolida_payload_para_pdf(): void
    {
        $datos = $this->crearDatosPrueba();

        $reporte = $this->gerenciaService->reporteEjecutivo($datos['anio'], $datos['mes'], null);

        $this->assertEquals($datos['anio'], $reporte['anio']);
        $this->assertEquals($datos['mes'], $reporte['mes']);
        $this->assertNotEmpty($reporte['fechaEmision']);
        $this->assertArrayHasKey('kpis', $reporte);
        $this->assertArrayHasKey('comparativo', $reporte);
        $this->assertArrayHasKey('envios', $reporte);
        $this->assertArrayHasKey('stockCritico', $reporte);
        $this->assertCount(1, $reporte['envios']);
        $this->assertCount(1, $reporte['stockCritico']);
    }
}
