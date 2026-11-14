<?php

namespace Tests\Feature;

use App\Enums\EstadoOrdenCompra;
use App\Enums\RolUsuario;
use App\Enums\TipoNotificacion;
use App\Enums\TipoSede;
use App\Models\Core\Area;
use App\Models\Core\NotificacionSistema;
use App\Models\Core\Sede;
use App\Models\Core\UnidadMedida;
use App\Models\Finanzas\CategoriaGasto;
use App\Models\Finanzas\CentroCosto;
use App\Models\Logistica\Almacen;
use App\Models\Logistica\CategoriaMaterial;
use App\Models\Logistica\Material;
use App\Models\Logistica\OrdenCompra;
use App\Models\Logistica\OrdenCompraDetalle;
use App\Models\Logistica\Proveedor;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Activitylog\Models\Activity;
use Tests\TestCase;

class Fase7MejorasTest extends TestCase
{
    use RefreshDatabase;

    private Sede $sede;

    private Almacen $almacen;

    private Proveedor $proveedor;

    private Material $material;

    private CentroCosto $centroCosto;

    private CategoriaGasto $categoriaGasto;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sede = Sede::create([
            'nombre' => 'Oficina Trujillo',
            'tipo' => TipoSede::ADMINISTRATIVA,
            'ubicacion' => 'Trujillo',
            'activo' => true,
        ]);

        $area = Area::create([
            'nombre' => 'Logística',
            'sede_id' => $this->sede->id,
            'activo' => true,
        ]);

        $this->almacen = Almacen::create([
            'sede_id' => $this->sede->id,
            'codigo' => 'ALM-01',
            'nombre' => 'Almacén Principal',
            'activo' => true,
        ]);

        $this->proveedor = Proveedor::create([
            'ruc' => '20987654321',
            'razon_social' => 'Aceros del Perú S.A.',
            'contacto' => 'Juan Solis',
            'activo' => true,
        ]);

        $unidad = UnidadMedida::create([
            'nombre' => 'Pieza',
            'abreviatura' => 'PZA',
            'activo' => true,
        ]);

        $catMat = CategoriaMaterial::create([
            'nombre' => 'Metales y Aceros',
            'activo' => true,
        ]);

        $this->material = Material::create([
            'codigo' => 'MAT-PER-01',
            'nombre' => 'Perno Minero 3/4x6"',
            'categoria_id' => $catMat->id,
            'unidad_medida_id' => $unidad->id,
            'stock_minimo' => 20,
            'precio_referencial' => 25.00,
            'activo' => true,
        ]);

        $this->centroCosto = CentroCosto::create([
            'codigo' => 'CC-MINA',
            'nombre' => 'Operaciones Mina',
            'area_id' => $area->id,
            'activo' => true,
        ]);

        $this->categoriaGasto = CategoriaGasto::create([
            'codigo' => 'CAT-SUM',
            'nombre' => 'Suministros de Operación',
            'activo' => true,
        ]);
    }

    public function test_permisos_acceso_ordenes_compra(): void
    {
        $rolesAutorizados = [
            RolUsuario::ADMIN,
            RolUsuario::LOGISTICA_JEFE,
            RolUsuario::LOGISTICA_ASISTENTE,
            RolUsuario::FINANZAS,
            RolUsuario::GERENTE,
        ];

        foreach ($rolesAutorizados as $rol) {
            $user = User::factory()->create([
                'rol' => $rol,
                'sede_id' => $this->sede->id,
                'activo' => true,
            ]);

            $this->actingAs($user)->get('/logistica/ordenes-compra')->assertOk();
        }

        $recepcion = User::factory()->create([
            'rol' => RolUsuario::RECEPCION_PATAZ,
            'sede_id' => $this->sede->id,
            'activo' => true,
        ]);

        $this->actingAs($recepcion)->get('/logistica/ordenes-compra')->assertForbidden();
    }

    public function test_flujo_completo_crear_emitir_recepcionar_orden_compra_via_http(): void
    {
        $jefe = User::factory()->create([
            'rol' => RolUsuario::LOGISTICA_JEFE,
            'sede_id' => $this->sede->id,
            'activo' => true,
        ]);

        // 1. Crear Orden en Borrador
        $respStore = $this->actingAs($jefe)->post('/logistica/ordenes-compra', [
            'proveedor_id' => $this->proveedor->id,
            'almacen_destino_id' => $this->almacen->id,
            'fecha' => '2026-11-12',
            'detalles' => [
                [
                    'material_id' => $this->material->id,
                    'cantidad' => 10,
                    'precio_unitario' => 25.00,
                ],
            ],
        ]);
        $respStore->assertRedirect();
        $respStore->assertSessionHas('exito');

        $orden = OrdenCompra::first();
        $this->assertNotNull($orden);
        $this->assertEquals(EstadoOrdenCompra::BORRADOR, $orden->estado);

        // 2. Emitir Orden
        $respEmitir = $this->actingAs($jefe)->post("/logistica/ordenes-compra/{$orden->id}/emitir");
        $respEmitir->assertRedirect();
        $this->assertEquals(EstadoOrdenCompra::EMITIDA, $orden->fresh()->estado);

        // 3. Recepcionar Orden completa con Factura en Finanzas
        $detalle = $orden->detalles->first();
        $respRec = $this->actingAs($jefe)->post("/logistica/ordenes-compra/{$orden->id}/recepcionar", [
            'recepciones' => [
                [
                    'detalle_id' => $detalle->id,
                    'cantidad_recibir' => 10,
                ],
            ],
            'generar_cuenta_pagar' => 1,
            'comprobante_tipo' => 'FACTURA',
            'comprobante_numero' => 'F002-000100',
            'fecha_comprobante' => '2026-11-12',
            'fecha_vencimiento' => '2026-12-12',
            'centro_costo_id' => $this->centroCosto->id,
            'categoria_id' => $this->categoriaGasto->id,
        ]);

        $respRec->assertRedirect();
        $this->assertEquals(EstadoOrdenCompra::RECIBIDA, $orden->fresh()->estado);
        $this->assertEquals(10.0, (float) $detalle->fresh()->cantidad_recibida);
        $this->assertDatabaseHas('movimientos_financieros', [
            'orden_compra_id' => $orden->id,
            'comprobante_numero' => 'F002-000100',
        ]);
    }

    public function test_descarga_pdf_orden_compra(): void
    {
        $jefe = User::factory()->create([
            'rol' => RolUsuario::LOGISTICA_JEFE,
            'sede_id' => $this->sede->id,
            'activo' => true,
        ]);

        $orden = OrdenCompra::create([
            'codigo' => 'OC-2026-0001',
            'proveedor_id' => $this->proveedor->id,
            'almacen_destino_id' => $this->almacen->id,
            'fecha' => '2026-11-12',
            'estado' => EstadoOrdenCompra::EMITIDA,
            'subtotal' => 250,
            'igv' => 45,
            'total' => 295,
        ]);

        OrdenCompraDetalle::create([
            'orden_compra_id' => $orden->id,
            'material_id' => $this->material->id,
            'cantidad' => 10,
            'precio_unitario' => 25,
            'subtotal' => 250,
        ]);

        $resp = $this->actingAs($jefe)->get("/logistica/ordenes-compra/{$orden->id}/pdf");
        $resp->assertOk();
        $this->assertStringContainsString('application/pdf', $resp->headers->get('content-type', ''));
    }

    public function test_notificaciones_hub_y_marcar_leida_via_http(): void
    {
        $usuario = User::factory()->create([
            'rol' => RolUsuario::LOGISTICA_JEFE,
            'sede_id' => $this->sede->id,
            'activo' => true,
        ]);

        $notif = NotificacionSistema::create([
            'tipo' => TipoNotificacion::STOCK_CRITICO,
            'modulo' => 'logistica',
            'titulo' => 'Pernos Mineros bajo stock',
            'mensaje' => 'Existencias en 0',
            'usuario_id' => $usuario->id,
            'leida' => false,
        ]);

        // Ver hub
        $this->actingAs($usuario)->get('/notificaciones')->assertOk();

        // Ver JSON de no leídas
        $respJson = $this->actingAs($usuario)->getJson('/notificaciones/no-leidas');
        $respJson->assertOk();
        $respJson->assertJsonPath('total_no_leidas', 1);

        // Marcar leída
        $respLeer = $this->actingAs($usuario)->post("/notificaciones/{$notif->id}/leer");
        $respLeer->assertRedirect();
        $this->assertTrue($notif->fresh()->leida);

        // Marcar todas leídas
        $this->actingAs($usuario)->post('/notificaciones/leer-todas')->assertRedirect();
    }

    public function test_bloqueo_por_cinco_intentos_fallidos_en_login(): void
    {
        $usuario = User::factory()->create([
            'email' => 'operador.mina@morillas.pe',
            'password' => bcrypt('password_correcto'),
            'activo' => true,
        ]);

        // 4 intentos con contraseña incorrecta
        for ($i = 1; $i <= 4; $i++) {
            $resp = $this->post('/login', [
                'email' => 'operador.mina@morillas.pe',
                'password' => 'password_invalido',
            ]);
            $resp->assertSessionHasErrors('email');
            $this->assertGuest();
        }

        // 5to intento erróneo activa el bloqueo de cuenta
        $resp5 = $this->post('/login', [
            'email' => 'operador.mina@morillas.pe',
            'password' => 'password_invalido',
        ]);
        $resp5->assertSessionHasErrors('email');
        $this->assertGuest();

        $usuario->refresh();
        $this->assertTrue($usuario->estaBloqueado());

        // Verificar que quedó registrado en activity_log
        $this->assertTrue(Activity::where('description', 'Cuenta bloqueada temporalmente tras 5 intentos fallidos')->exists());
    }
}
