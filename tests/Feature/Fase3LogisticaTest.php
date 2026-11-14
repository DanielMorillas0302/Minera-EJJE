<?php

namespace Tests\Feature;

use App\Enums\EstadoEnvio;
use App\Enums\EstadoRequerimiento;
use App\Enums\PrioridadRequerimiento;
use App\Enums\RolUsuario;
use App\Models\Core\Area;
use App\Models\Core\Sede;
use App\Models\Logistica\Almacen;
use App\Models\Logistica\CategoriaMaterial;
use App\Models\Logistica\Envio;
use App\Models\Logistica\Material;
use App\Models\Logistica\Proveedor;
use App\Models\Logistica\Requerimiento;
use App\Models\User;
use Database\Seeders\CoreSeeder;
use Database\Seeders\LogisticaSeeder;
use Database\Seeders\RrhhSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class Fase3LogisticaTest extends TestCase
{
    use RefreshDatabase;

    private function sembrarTodo(): void
    {
        config(['app.debug' => true]);
        $this->seed([CoreSeeder::class, RrhhSeeder::class, LogisticaSeeder::class]);
    }

    public function test_cargar_semilla_es_idempotente_y_puebla_logistica(): void
    {
        config(['app.debug' => true]);

        $this->artisan('cargar_semilla')->assertExitCode(0);
        $this->artisan('cargar_semilla')->assertExitCode(0);

        $this->assertSame(7, CategoriaMaterial::query()->count());
        $this->assertSame(2, Almacen::query()->count());
        $this->assertSame(5, Proveedor::query()->count());
        $this->assertSame(40, Material::query()->count());
        $this->assertSame(5, Requerimiento::query()->count());
        $this->assertSame(3, Envio::query()->count());
    }

    public function test_recepcion_pataz_solo_accede_a_su_almacen_y_requerimientos_propios(): void
    {
        $this->sembrarTodo();

        $recepPataz = User::query()->where('rol', RolUsuario::RECEPCION_PATAZ)->firstOrFail();
        $asistenteLog = User::query()->where('rol', RolUsuario::LOGISTICA_ASISTENTE)->firstOrFail();
        $almTrujillo = Almacen::query()->where('codigo', 'ALM-TRUJILLO')->firstOrFail();
        $almPataz = Almacen::query()->where('codigo', 'ALM-PATAZ')->firstOrFail();
        $material = Material::query()->where('codigo', 'MAT-001')->firstOrFail();

        // Lectura permitida en catálogos, creación bloqueada (403)
        $this->actingAs($recepPataz)->get('/logistica/materiales')->assertStatus(200);
        $this->actingAs($recepPataz)->get('/logistica/proveedores')->assertStatus(200);
        $this->actingAs($recepPataz)->post('/logistica/materiales', [])->assertStatus(403);
        $this->actingAs($recepPataz)->post('/logistica/proveedores', [])->assertStatus(403);

        // Permitido en resumen, stock Pataz y consumos Pataz
        $this->actingAs($recepPataz)->get('/logistica')->assertStatus(200);
        $this->actingAs($recepPataz)->get("/logistica/stock?almacen_id={$almPataz->id}")->assertStatus(200);
        $this->actingAs($recepPataz)->get("/logistica/stock?almacen_id={$almTrujillo->id}")->assertStatus(403);

        $this->actingAs($recepPataz)
            ->post('/logistica/consumos', [
                'almacen_id' => $almTrujillo->id,
                'material_id' => $material->id,
                'cantidad' => 1,
                'observacion' => 'Intento no autorizado',
            ])
            ->assertStatus(403);

        $this->actingAs($recepPataz)
            ->post('/logistica/consumos', [
                'almacen_id' => $almPataz->id,
                'material_id' => $material->id,
                'cantidad' => 1,
                'observacion' => 'Consumo diario mina',
            ])
            ->assertRedirect();

        // No puede enviar/editar requerimiento creado por otro usuario
        $reqAjeno = Requerimiento::query()
            ->where('solicitante_id', $asistenteLog->id)
            ->firstOrFail();

        $this->actingAs($recepPataz)
            ->post("/logistica/requerimientos/{$reqAjeno->id}/enviar")
            ->assertStatus(403);
    }

    public function test_flujo_completo_requerimiento_envio_recepcion_con_diferencias(): void
    {
        $this->sembrarTodo();

        $recepPataz = User::query()->where('rol', RolUsuario::RECEPCION_PATAZ)->firstOrFail();
        $jefeLog = User::query()->where('rol', RolUsuario::LOGISTICA_JEFE)->firstOrFail();
        $area = Area::query()->firstOrFail();
        $sedePataz = Sede::query()->where('nombre', 'like', '%Pataz%')->firstOrFail();
        $material = Material::query()->where('codigo', 'MAT-002')->firstOrFail();
        $almTrujillo = Almacen::query()->where('codigo', 'ALM-TRUJILLO')->firstOrFail();
        $almPataz = Almacen::query()->where('codigo', 'ALM-PATAZ')->firstOrFail();

        // 1. RECEPCION_PATAZ crea y envía requerimiento
        $this->actingAs($recepPataz)->post('/logistica/requerimientos', [
            'fecha_solicitud' => now()->toDateString(),
            'area_id' => $area->id,
            'sede_id' => $sedePataz->id,
            'prioridad' => PrioridadRequerimiento::URGENTE->value,
            'observaciones' => 'Lámparas guardia noche',
            'enviar' => true,
            'items' => [
                ['material_id' => $material->id, 'cantidad_solicitada' => 5],
            ],
        ])->assertRedirect();

        /** @var Requerimiento $req */
        $req = Requerimiento::query()->latest('id')->firstOrFail();
        $this->assertSame(EstadoRequerimiento::ENVIADO, $req->estado);

        // Self-approval de un borrador enviado por uno mismo falla con 403 si tuviera permiso,
        // aquí jefe de logística aprueba el requerimiento del otro usuario.
        $this->actingAs($jefeLog)->post("/logistica/requerimientos/{$req->id}/aprobar")->assertRedirect();
        $this->assertSame(EstadoRequerimiento::APROBADO, $req->fresh()->estado);

        // 2. Jefe de logística crea y despacha envío
        $reqDetalle = $req->detalles()->firstOrFail();
        $this->actingAs($jefeLog)->post('/logistica/envios', [
            'requerimiento_id' => $req->id,
            'almacen_origen_id' => $almTrujillo->id,
            'almacen_destino_id' => $almPataz->id,
            'fecha_envio' => now()->toDateString(),
            'fecha_estimada_llegada' => now()->addDays(2)->toDateString(),
            'guia_remision' => 'T001-998877',
            'despachar' => true,
            'items' => [
                [
                    'material_id' => $material->id,
                    'cantidad_enviada' => 5,
                    'requerimiento_detalle_id' => $reqDetalle->id,
                ],
            ],
        ])->assertRedirect();

        /** @var Envio $envio */
        $envio = Envio::query()->latest('id')->firstOrFail();
        $this->assertSame(EstadoEnvio::EN_TRANSITO, $envio->estado);
        $envDetalle = $envio->detalles()->firstOrFail();

        // 3. RECEPCION_PATAZ confirma llegada parcial (4 de 5)
        $this->actingAs($recepPataz)->post("/logistica/envios/{$envio->id}/recibir", [
            'fecha_recepcion' => now()->toDateString(),
            'observaciones' => '1 unidad con falla de batería',
            'items' => [
                [
                    'detalle_id' => $envDetalle->id,
                    'cantidad_recibida' => 4,
                    'observacion_recepcion' => 'Devuelta por daño',
                ],
            ],
        ])->assertRedirect();

        $this->assertSame(EstadoEnvio::RECIBIDO_CON_OBSERVACIONES, $envio->fresh()->estado);
        $this->assertSame(EstadoRequerimiento::ATENDIDO_PARCIAL, $req->fresh()->estado);
    }

    public function test_no_puede_aprobar_propio_requerimiento_por_http(): void
    {
        $this->sembrarTodo();

        $jefeLog = User::query()->where('rol', RolUsuario::LOGISTICA_JEFE)->firstOrFail();
        $area = Area::query()->firstOrFail();
        $sede = Sede::query()->firstOrFail();
        $material = Material::query()->where('codigo', 'MAT-001')->firstOrFail();

        $this->actingAs($jefeLog)->post('/logistica/requerimientos', [
            'fecha_solicitud' => now()->toDateString(),
            'area_id' => $area->id,
            'sede_id' => $sede->id,
            'prioridad' => PrioridadRequerimiento::MEDIA->value,
            'enviar' => true,
            'items' => [
                ['material_id' => $material->id, 'cantidad_solicitada' => 2],
            ],
        ])->assertRedirect();

        $req = Requerimiento::query()->latest('id')->firstOrFail();

        $this->actingAs($jefeLog)
            ->post("/logistica/requerimientos/{$req->id}/aprobar")
            ->assertStatus(403);
    }

    public function test_exportaciones_plantilla_excel_y_kardex_pdf_responden_correctamente(): void
    {
        $this->sembrarTodo();
        $jefeLog = User::query()->where('rol', RolUsuario::LOGISTICA_JEFE)->firstOrFail();

        $this->actingAs($jefeLog)
            ->get('/logistica/envios/plantilla-recepcion')
            ->assertStatus(200);

        $this->actingAs($jefeLog)
            ->get('/logistica/reportes/exportar?tipo=stock')
            ->assertStatus(200);

        $this->actingAs($jefeLog)
            ->get('/logistica/reportes/exportar?tipo=diferencias')
            ->assertStatus(200);

        $respPdf = $this->actingAs($jefeLog)->get('/logistica/reportes/kardex-pdf');
        $respPdf->assertStatus(200);
        $this->assertStringContainsString('application/pdf', (string) $respPdf->headers->get('content-type'));
    }

    public function test_crud_materiales_y_proveedores_con_validacion_ruc_11_digitos(): void
    {
        $this->sembrarTodo();
        $jefeLog = User::query()->where('rol', RolUsuario::LOGISTICA_JEFE)->firstOrFail();
        $categoria = CategoriaMaterial::query()->firstOrFail();
        $material = Material::query()->firstOrFail();

        $this->actingAs($jefeLog)
            ->post('/logistica/proveedores', [
                'ruc' => '12345',
                'razon_social' => 'Proveedor Inválido S.A.C.',
                'condicion_pago' => 'CONTADO',
            ])
            ->assertSessionHasErrors('ruc');

        $this->actingAs($jefeLog)
            ->post('/logistica/proveedores', [
                'ruc' => '20609988776',
                'razon_social' => 'Transportes Pataz Express S.A.C.',
                'condicion_pago' => 'CREDITO 30 DIAS',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('proveedores', ['ruc' => '20609988776']);

        $this->actingAs($jefeLog)
            ->post('/logistica/materiales', [
                'codigo' => 'MAT-NEW99',
                'nombre' => 'Polea de izaje 10"',
                'categoria_id' => $categoria->id,
                'unidad_medida_id' => $material->unidad_medida_id,
                'stock_minimo' => 2,
                'costo_referencial' => 350.50,
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('materiales', ['codigo' => 'MAT-NEW99']);
    }
}
