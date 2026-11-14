<?php

namespace Tests\Unit;

use App\Enums\EstadoOrdenCompra;
use App\Enums\EstadoPago;
use App\Enums\NivelNotificacion;
use App\Enums\RolUsuario;
use App\Enums\TipoMovimientoInventario;
use App\Enums\TipoNotificacion;
use App\Enums\TipoSede;
use App\Models\Core\Area;
use App\Models\Core\Cargo;
use App\Models\Core\Sede;
use App\Models\Core\UnidadMedida;
use App\Models\Finanzas\CategoriaGasto;
use App\Models\Finanzas\CentroCosto;
use App\Models\Logistica\Almacen;
use App\Models\Logistica\CategoriaMaterial;
use App\Models\Logistica\Material;
use App\Models\Logistica\MovimientoInventario;
use App\Models\Logistica\OrdenCompra;
use App\Models\Logistica\Proveedor;
use App\Models\Logistica\StockAlmacen;
use App\Models\User;
use App\Services\Core\NotificacionService;
use App\Services\Logistica\InventarioService;
use App\Services\Logistica\OrdenCompraService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class Fase7ServiciosTest extends TestCase
{
    use RefreshDatabase;

    private OrdenCompraService $ordenCompraService;

    private NotificacionService $notificacionService;

    private InventarioService $inventarioService;

    protected function setUp(): void
    {
        parent::setUp();
        $this->inventarioService = app(InventarioService::class);
        $this->notificacionService = app(NotificacionService::class);
        $this->ordenCompraService = app(OrdenCompraService::class);
    }

    private function crearDatosBase(): array
    {
        $sede = Sede::create([
            'nombre' => 'Oficina Trujillo',
            'tipo' => TipoSede::ADMINISTRATIVA,
            'ubicacion' => 'Trujillo',
            'activo' => true,
        ]);

        $area = Area::create([
            'nombre' => 'Logística y Abastecimiento',
            'sede_id' => $sede->id,
            'activo' => true,
        ]);

        $cargo = Cargo::create([
            'nombre' => 'Jefe de Logística',
            'area_id' => $area->id,
            'activo' => true,
        ]);

        $usuario = User::factory()->create([
            'rol' => RolUsuario::LOGISTICA_JEFE,
            'sede_id' => $sede->id,
            'activo' => true,
        ]);

        $almacen = Almacen::create([
            'sede_id' => $sede->id,
            'codigo' => 'ALM-TRU',
            'nombre' => 'Almacén Central Trujillo',
            'activo' => true,
        ]);

        $proveedor = Proveedor::create([
            'ruc' => '20123456789',
            'razon_social' => 'Ferretería Industrial del Norte S.A.C.',
            'contacto' => 'Pedro Castillo',
            'telefono' => '944112233',
            'email' => 'ventas@ferreterianorte.pe',
            'activo' => true,
        ]);

        $unidad = UnidadMedida::create([
            'nombre' => 'Unidad',
            'abreviatura' => 'UND',
            'activo' => true,
        ]);

        $categoriaMat = CategoriaMaterial::create([
            'nombre' => 'Herramientas y Repuestos',
            'activo' => true,
        ]);

        $material1 = Material::create([
            'codigo' => 'MAT-BRO-01',
            'nombre' => 'Broca de Perforación 38mm',
            'categoria_id' => $categoriaMat->id,
            'unidad_medida_id' => $unidad->id,
            'stock_minimo' => 10,
            'precio_referencial' => 150.00,
            'activo' => true,
        ]);

        $material2 = Material::create([
            'codigo' => 'MAT-BAR-01',
            'nombre' => 'Barrena Helicoidal 1.5m',
            'categoria_id' => $categoriaMat->id,
            'unidad_medida_id' => $unidad->id,
            'stock_minimo' => 5,
            'precio_referencial' => 300.00,
            'activo' => true,
        ]);

        $centroCosto = CentroCosto::create([
            'codigo' => 'CC-MINA',
            'nombre' => 'Operaciones Mina',
            'area_id' => $area->id,
            'activo' => true,
        ]);

        $categoriaGasto = CategoriaGasto::create([
            'codigo' => 'CAT-REP',
            'nombre' => 'Repuestos y Consumibles',
            'activo' => true,
        ]);

        return compact('usuario', 'almacen', 'proveedor', 'material1', 'material2', 'centroCosto', 'categoriaGasto');
    }

    public function test_crear_orden_compra_genera_codigo_y_totales(): void
    {
        $base = $this->crearDatosBase();

        $orden = $this->ordenCompraService->crearOrden([
            'proveedor_id' => $base['proveedor']->id,
            'almacen_destino_id' => $base['almacen']->id,
            'fecha' => '2026-11-12',
            'condiciones_pago' => 'Crédito 30 días',
            'detalles' => [
                [
                    'material_id' => $base['material1']->id,
                    'cantidad' => 10,
                    'precio_unitario' => 150.00, // 1,500.00
                ],
                [
                    'material_id' => $base['material2']->id,
                    'cantidad' => 2,
                    'precio_unitario' => 300.00, // 600.00
                ],
            ],
        ], $base['usuario']);

        $this->assertStringStartsWith('OC-'.Carbon::now()->year, $orden->codigo);
        $this->assertEquals(EstadoOrdenCompra::BORRADOR, $orden->estado);
        $this->assertEquals(2100.00, $orden->subtotal);
        $this->assertEquals(378.00, $orden->igv);
        $this->assertEquals(2478.00, $orden->total);
        $this->assertCount(2, $orden->detalles);
    }

    public function test_emitir_orden_compra_actualiza_estado_y_aprueba(): void
    {
        $base = $this->crearDatosBase();

        $orden = $this->ordenCompraService->crearOrden([
            'proveedor_id' => $base['proveedor']->id,
            'almacen_destino_id' => $base['almacen']->id,
            'detalles' => [
                ['material_id' => $base['material1']->id, 'cantidad' => 5, 'precio_unitario' => 100],
            ],
        ], $base['usuario']);

        $ordenEmitida = $this->ordenCompraService->emitirOrden($orden, $base['usuario']);

        $this->assertEquals(EstadoOrdenCompra::EMITIDA, $ordenEmitida->estado);
        $this->assertEquals($base['usuario']->id, $ordenEmitida->aprobado_por_id);
        $this->assertNotNull($ordenEmitida->fecha_aprobacion);
    }

    public function test_recepcionar_items_ingresa_kardex_y_actualiza_saldo(): void
    {
        $base = $this->crearDatosBase();

        $orden = $this->ordenCompraService->crearOrden([
            'proveedor_id' => $base['proveedor']->id,
            'almacen_destino_id' => $base['almacen']->id,
            'emitir_inmediata' => true,
            'detalles' => [
                ['material_id' => $base['material1']->id, 'cantidad' => 10, 'precio_unitario' => 100],
            ],
        ], $base['usuario']);

        $detalleId = $orden->detalles->first()->id;

        // Recepción parcial: 4 de 10
        $res = $this->ordenCompraService->recepcionarItems($orden, [
            ['detalle_id' => $detalleId, 'cantidad_recibir' => 4],
        ], $base['usuario']);

        $ordenActualizada = $res['orden'];
        $this->assertEquals(EstadoOrdenCompra::RECIBIDA_PARCIAL, $ordenActualizada->estado);
        $this->assertEquals(4.0, (float) $ordenActualizada->detalles->first()->cantidad_recibida);

        // Verificar stock de almacén actualizado
        $stock = StockAlmacen::where('almacen_id', $base['almacen']->id)
            ->where('material_id', $base['material1']->id)
            ->first();
        $this->assertEquals(4.0, (float) $stock->cantidad);

        // Verificar kardex
        $kardex = MovimientoInventario::where('documento_tipo', OrdenCompra::class)
            ->where('documento_id', $orden->id)
            ->first();
        $this->assertNotNull($kardex);
        $this->assertEquals(TipoMovimientoInventario::ENTRADA_COMPRA, $kardex->tipo);
        $this->assertEquals(4.0, (float) $kardex->cantidad);
    }

    public function test_recepcionar_total_marca_orden_como_recibida_y_crea_cuenta_por_pagar(): void
    {
        $base = $this->crearDatosBase();

        $orden = $this->ordenCompraService->crearOrden([
            'proveedor_id' => $base['proveedor']->id,
            'almacen_destino_id' => $base['almacen']->id,
            'emitir_inmediata' => true,
            'detalles' => [
                ['material_id' => $base['material1']->id, 'cantidad' => 10, 'precio_unitario' => 100],
            ],
        ], $base['usuario']);

        $detalleId = $orden->detalles->first()->id;

        $res = $this->ordenCompraService->recepcionarItems($orden, [
            ['detalle_id' => $detalleId, 'cantidad_recibir' => 10],
        ], $base['usuario'], [
            'generar_cuenta_pagar' => true,
            'comprobante_tipo' => 'FACTURA',
            'comprobante_numero' => 'F001-000843',
            'fecha_comprobante' => '2026-11-12',
            'fecha_vencimiento' => '2026-12-12',
            'centro_costo_id' => $base['centroCosto']->id,
            'categoria_id' => $base['categoriaGasto']->id,
        ]);

        $this->assertEquals(EstadoOrdenCompra::RECIBIDA, $res['orden']->estado);
        $this->assertNotNull($res['movimiento_financiero']);
        $this->assertEquals('F001-000843', $res['movimiento_financiero']->comprobante_numero);
        $this->assertEquals(EstadoPago::PENDIENTE, $res['movimiento_financiero']->estado_pago);
        $this->assertEquals($orden->id, $res['movimiento_financiero']->orden_compra_id);
    }

    public function test_anular_orden_compra_sin_recepciones_exitoso(): void
    {
        $base = $this->crearDatosBase();

        $orden = $this->ordenCompraService->crearOrden([
            'proveedor_id' => $base['proveedor']->id,
            'almacen_destino_id' => $base['almacen']->id,
            'detalles' => [
                ['material_id' => $base['material1']->id, 'cantidad' => 5, 'precio_unitario' => 100],
            ],
        ], $base['usuario']);

        $ordenAnulada = $this->ordenCompraService->anularOrden($orden, 'Error en cotización', $base['usuario']);

        $this->assertEquals(EstadoOrdenCompra::ANULADA, $ordenAnulada->estado);
        $this->assertStringContainsString('Error en cotización', $ordenAnulada->observaciones);
    }

    public function test_anular_orden_con_recepciones_lanza_excepcion(): void
    {
        $base = $this->crearDatosBase();

        $orden = $this->ordenCompraService->crearOrden([
            'proveedor_id' => $base['proveedor']->id,
            'almacen_destino_id' => $base['almacen']->id,
            'emitir_inmediata' => true,
            'detalles' => [
                ['material_id' => $base['material1']->id, 'cantidad' => 5, 'precio_unitario' => 100],
            ],
        ], $base['usuario']);

        $detalleId = $orden->detalles->first()->id;
        $this->ordenCompraService->recepcionarItems($orden, [
            ['detalle_id' => $detalleId, 'cantidad_recibir' => 2],
        ], $base['usuario']);

        $this->expectException(ValidationException::class);
        $this->ordenCompraService->anularOrden($orden, 'Intento de anulación', $base['usuario']);
    }

    public function test_notificaciones_crear_y_marcar_leida(): void
    {
        $base = $this->crearDatosBase();

        $notif = $this->notificacionService->crearNotificacion(
            TipoNotificacion::STOCK_CRITICO,
            'logistica',
            NivelNotificacion::URGENTE,
            'Alerta de Reposición',
            'Diesel B5 bajo mínimo',
            '/logistica/kardex',
            $base['usuario']->id,
        );

        $this->assertFalse($notif->leida);
        $this->assertEquals(1, $this->notificacionService->contarNoLeidas($base['usuario']));

        $this->notificacionService->marcarComoLeida($notif->id, $base['usuario']);
        $this->assertEquals(0, $this->notificacionService->contarNoLeidas($base['usuario']));
    }

    public function test_usuario_bloqueo_por_cinco_intentos_fallidos(): void
    {
        $usuario = User::factory()->create([
            'email' => 'minero.seguro@morillas.pe',
            'activo' => true,
        ]);

        $this->assertFalse($usuario->estaBloqueado());

        // 4 intentos fallidos
        for ($i = 1; $i <= 4; $i++) {
            $usuario->registrarIntentoFallido();
            $this->assertFalse($usuario->estaBloqueado());
        }

        // 5to intento fallido
        $usuario->registrarIntentoFallido();
        $this->assertTrue($usuario->estaBloqueado());
        $this->assertNotNull($usuario->bloqueado_hasta);

        // Reinicio exitoso
        $usuario->reiniciarIntentosFallidos();
        $this->assertFalse($usuario->estaBloqueado());
        $this->assertNull($usuario->bloqueado_hasta);
    }
}
