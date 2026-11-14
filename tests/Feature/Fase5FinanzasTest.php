<?php

namespace Tests\Feature;

use App\Enums\EstadoPago;
use App\Enums\RolUsuario;
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
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class Fase5FinanzasTest extends TestCase
{
    use RefreshDatabase;

    private function prepararUsuarios(): array
    {
        $sede = Sede::create([
            'nombre' => 'Trujillo Base',
            'tipo' => TipoSede::ADMINISTRATIVA,
            'ubicacion' => 'Trujillo',
            'activo' => true,
        ]);

        $admin = User::factory()->create([
            'rol' => RolUsuario::ADMIN,
            'sede_id' => $sede->id,
            'activo' => true,
        ]);

        $finanzas = User::factory()->create([
            'rol' => RolUsuario::FINANZAS,
            'sede_id' => $sede->id,
            'activo' => true,
        ]);

        $gerente = User::factory()->create([
            'rol' => RolUsuario::GERENTE,
            'sede_id' => $sede->id,
            'activo' => true,
        ]);

        $rrhh = User::factory()->create([
            'rol' => RolUsuario::RRHH_JEFE,
            'sede_id' => $sede->id,
            'activo' => true,
        ]);

        $logistica = User::factory()->create([
            'rol' => RolUsuario::LOGISTICA_JEFE,
            'sede_id' => $sede->id,
            'activo' => true,
        ]);

        $recepcionPataz = User::factory()->create([
            'rol' => RolUsuario::RECEPCION_PATAZ,
            'sede_id' => $sede->id,
            'activo' => true,
        ]);

        $area = Area::create([
            'nombre' => 'Finanzas',
            'sede_id' => $sede->id,
            'activo' => true,
        ]);

        $cc = CentroCosto::create([
            'codigo' => 'CC-TEST',
            'nombre' => 'Centro de Prueba',
            'area_id' => $area->id,
            'activo' => true,
        ]);

        $cat = CategoriaGasto::create([
            'nombre' => 'Servicios Varios',
            'tipo' => TipoMovimientoFinanciero::EGRESO,
            'activo' => true,
        ]);

        $prov = Proveedor::create([
            'ruc' => '20999888777',
            'razon_social' => 'Servicios Mineros S.A.C.',
            'activo' => true,
        ]);

        return compact('admin', 'finanzas', 'gerente', 'rrhh', 'logistica', 'recepcionPataz', 'cc', 'cat', 'prov');
    }

    public function test_matriz_permisos_restringe_acceso_a_finanzas(): void
    {
        $u = $this->prepararUsuarios();

        // 1. ADMIN y FINANZAS tienen acceso 200
        $this->actingAs($u['admin'])->get('/finanzas')->assertOk();
        $this->actingAs($u['finanzas'])->get('/finanzas')->assertOk();

        // 2. GERENTE tiene acceso de lectura (200)
        $this->actingAs($u['gerente'])->get('/finanzas')->assertOk();
        $this->actingAs($u['gerente'])->get('/finanzas/movimientos')->assertOk();

        // 3. Otros roles reciben 403 Forbidden
        $this->actingAs($u['rrhh'])->get('/finanzas')->assertForbidden();
        $this->actingAs($u['logistica'])->get('/finanzas')->assertForbidden();
        $this->actingAs($u['recepcionPataz'])->get('/finanzas')->assertForbidden();
    }

    public function test_gerente_no_puede_crear_ni_editar_movimientos_o_presupuestos(): void
    {
        $u = $this->prepararUsuarios();

        // Intentar registrar movimiento siendo GERENTE => 403
        $respMov = $this->actingAs($u['gerente'])->post('/finanzas/movimientos', [
            'tipo' => 'EGRESO',
            'fecha' => '2026-06-01',
            'monto' => 1000,
            'categoria_id' => $u['cat']->id,
            'centro_costo_id' => $u['cc']->id,
            'concepto' => 'Intento de registro por gerente',
            'comprobante_tipo' => 'FACTURA',
            'estado_pago' => 'PAGADO',
        ]);
        $respMov->assertForbidden();

        // Intentar guardar presupuesto siendo GERENTE => 403
        $respPres = $this->actingAs($u['gerente'])->post('/finanzas/presupuestos', [
            'centro_costo_id' => $u['cc']->id,
            'anio' => 2026,
            'mes' => 10,
            'monto_presupuestado' => 50000,
        ]);
        $respPres->assertForbidden();
    }

    public function test_usuario_finanzas_puede_registrar_actualizar_y_eliminar_movimiento(): void
    {
        $u = $this->prepararUsuarios();

        // 1. Crear
        $respCrear = $this->actingAs($u['finanzas'])->post('/finanzas/movimientos', [
            'tipo' => 'EGRESO',
            'fecha' => '2026-06-10',
            'monto' => 2500.50,
            'moneda' => 'PEN',
            'categoria_id' => $u['cat']->id,
            'centro_costo_id' => $u['cc']->id,
            'proveedor_id' => $u['prov']->id,
            'concepto' => 'Alquiler de compresor de aire',
            'comprobante_tipo' => 'FACTURA',
            'comprobante_numero' => 'F001-000889',
            'estado_pago' => 'PENDIENTE',
            'fecha_vencimiento' => '2026-06-30',
        ]);
        $respCrear->assertRedirect('/finanzas/movimientos');
        $this->assertDatabaseHas('movimientos_financieros', [
            'concepto' => 'Alquiler de compresor de aire',
            'monto' => 2500.50,
            'estado_pago' => 'PENDIENTE',
        ]);

        $mov = MovimientoFinanciero::first();

        // 2. Actualizar
        $respUpdate = $this->actingAs($u['finanzas'])->put("/finanzas/movimientos/{$mov->id}", [
            'tipo' => 'EGRESO',
            'fecha' => '2026-06-10',
            'monto' => 2700.00,
            'moneda' => 'PEN',
            'categoria_id' => $u['cat']->id,
            'centro_costo_id' => $u['cc']->id,
            'proveedor_id' => $u['prov']->id,
            'concepto' => 'Alquiler de compresor de aire - Ajustado',
            'comprobante_tipo' => 'FACTURA',
            'comprobante_numero' => 'F001-000889',
            'estado_pago' => 'PENDIENTE',
            'fecha_vencimiento' => '2026-07-05',
        ]);
        $respUpdate->assertRedirect('/finanzas/movimientos');
        $this->assertEquals(2700.00, (float) $mov->fresh()->monto);

        // 3. Pagar
        $respPagar = $this->actingAs($u['finanzas'])->post("/finanzas/movimientos/{$mov->id}/pagar", [
            'fecha_pago' => '2026-06-25',
            'comprobante_numero' => 'OP-778899',
        ]);
        $respPagar->assertRedirect();
        $this->assertEquals(EstadoPago::PAGADO, $mov->fresh()->estado_pago);
        $this->assertEquals('2026-06-25', $mov->fresh()->fecha_pago->format('Y-m-d'));

        // 4. Eliminar (soft delete)
        $respDelete = $this->actingAs($u['finanzas'])->delete("/finanzas/movimientos/{$mov->id}");
        $respDelete->assertRedirect('/finanzas/movimientos');
        $this->assertSoftDeleted('movimientos_financieros', ['id' => $mov->id]);
    }

    public function test_subir_y_descargar_comprobante_adjunto(): void
    {
        Storage::fake('local');
        $u = $this->prepararUsuarios();

        $mov = MovimientoFinanciero::create([
            'tipo' => TipoMovimientoFinanciero::EGRESO,
            'fecha' => '2026-06-01',
            'monto' => 1200.00,
            'categoria_id' => $u['cat']->id,
            'centro_costo_id' => $u['cc']->id,
            'concepto' => 'Servicio de laboratorio',
            'comprobante_tipo' => TipoComprobante::FACTURA,
            'estado_pago' => EstadoPago::PAGADO,
            'activo' => true,
        ]);

        $archivo = UploadedFile::fake()->create('comprobante_lab.pdf', 300, 'application/pdf');

        $respSubida = $this->actingAs($u['finanzas'])->post("/finanzas/movimientos/{$mov->id}/adjuntos", [
            'archivo' => $archivo,
            'descripcion' => 'Factura con sello de visto bueno',
        ]);
        $respSubida->assertRedirect();

        $this->assertEquals(1, $mov->adjuntos()->count());
        $adjunto = $mov->adjuntos()->first();

        // Descargar adjunto
        $respDescarga = $this->actingAs($u['finanzas'])
            ->get("/finanzas/movimientos/adjuntos/{$adjunto->id}/descargar");
        $respDescarga->assertOk();
    }

    public function test_guardar_presupuesto_individual_y_en_lote(): void
    {
        $u = $this->prepararUsuarios();

        // 1. Guardar individual
        $respInd = $this->actingAs($u['finanzas'])->post('/finanzas/presupuestos', [
            'centro_costo_id' => $u['cc']->id,
            'anio' => 2026,
            'mes' => 7,
            'monto_presupuestado' => 45000.00,
            'notas' => 'Presupuesto mensual julio',
        ]);
        $respInd->assertRedirect();
        $this->assertDatabaseHas('presupuestos', [
            'centro_costo_id' => $u['cc']->id,
            'anio' => 2026,
            'mes' => 7,
            'monto_presupuestado' => 45000.00,
        ]);

        // 2. Guardar en lote
        $respLote = $this->actingAs($u['finanzas'])->post('/finanzas/presupuestos/lote', [
            'anio' => 2026,
            'mes' => 8,
            'presupuestos' => [
                [
                    'centro_costo_id' => $u['cc']->id,
                    'monto' => 48000.00,
                ],
            ],
        ]);
        $respLote->assertRedirect();
        $this->assertDatabaseHas('presupuestos', [
            'centro_costo_id' => $u['cc']->id,
            'anio' => 2026,
            'mes' => 8,
            'monto_presupuestado' => 48000.00,
        ]);
    }

    public function test_catalogos_de_centros_de_costo_y_categorias(): void
    {
        $u = $this->prepararUsuarios();

        // 1. Crear nuevo centro de costo
        $respCC = $this->actingAs($u['finanzas'])->post('/finanzas/centros-costo', [
            'codigo' => 'CC-EXP',
            'nombre' => 'Exploraciones y Sondajes',
            'area_id' => $u['cc']->area_id,
            'descripcion' => 'Costos de perforación diamantina',
        ]);
        $respCC->assertRedirect();
        $this->assertDatabaseHas('centros_costo', ['codigo' => 'CC-EXP']);

        $ccNuevo = CentroCosto::where('codigo', 'CC-EXP')->first();

        // Toggle activo
        $this->actingAs($u['finanzas'])->post("/finanzas/centros-costo/{$ccNuevo->id}/toggle");
        $this->assertFalse((bool) $ccNuevo->fresh()->activo);

        // 2. Crear nueva categoría de gasto
        $respCat = $this->actingAs($u['finanzas'])->post('/finanzas/categorias-gasto', [
            'nombre' => 'Explosivos y Detonadores',
            'tipo' => 'EGRESO',
            'descripcion' => 'Dinamita, cordón detonante y emulsiones',
        ]);
        $respCat->assertRedirect();
        $this->assertDatabaseHas('categorias_gasto', ['nombre' => 'Explosivos y Detonadores']);
    }

    public function test_exportar_reportes_a_excel_y_pdf(): void
    {
        $u = $this->prepararUsuarios();

        // 1. Exportar Egresos por Centro de Costo (XLSX)
        $respXlsx = $this->actingAs($u['finanzas'])->get('/finanzas/reportes/exportar?tipo_reporte=egresos_cc');
        $respXlsx->assertOk();
        $this->assertStringContainsString('reporte_egresos_por_centro_costo', (string) $respXlsx->headers->get('content-disposition'));

        // 2. Exportar Presupuesto vs Real (XLSX)
        $respXlsxPres = $this->actingAs($u['finanzas'])->get('/finanzas/reportes/exportar?tipo_reporte=presupuesto_real&anio=2026&mes=6');
        $respXlsxPres->assertOk();
        $this->assertStringContainsString('reporte_presupuesto_vs_real', (string) $respXlsxPres->headers->get('content-disposition'));

        // 3. Exportar Presupuesto vs Real (PDF)
        $respPdf = $this->actingAs($u['finanzas'])->get('/finanzas/reportes/presupuesto-pdf?anio=2026&mes=6');
        $respPdf->assertOk();
        $this->assertEquals('application/pdf', $respPdf->headers->get('content-type'));
    }
}
