<?php

namespace Tests\Unit;

use App\Enums\EstadoEnvio;
use App\Enums\EstadoRequerimiento;
use App\Enums\PrioridadRequerimiento;
use App\Enums\RolUsuario;
use App\Enums\TipoMovimientoInventario;
use App\Enums\TipoSede;
use App\Models\Core\Area;
use App\Models\Core\Sede;
use App\Models\Core\UnidadMedida;
use App\Models\Logistica\Almacen;
use App\Models\Logistica\CategoriaMaterial;
use App\Models\Logistica\Material;
use App\Models\Logistica\MovimientoInventario;
use App\Models\Logistica\StockAlmacen;
use App\Models\User;
use App\Services\Logistica\CorrelativoService;
use App\Services\Logistica\EnvioService;
use App\Services\Logistica\InventarioService;
use App\Services\Logistica\RequerimientoService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class LogisticaServiciosTest extends TestCase
{
    use RefreshDatabase;

    private function prepararEscenarioBase(): array
    {
        $sedeTrujillo = Sede::query()->create(['nombre' => 'Trujillo', 'tipo' => TipoSede::ADMINISTRATIVA, 'ubicacion' => 'Trujillo', 'activo' => true]);
        $sedePataz = Sede::query()->create(['nombre' => 'Pataz', 'tipo' => TipoSede::OPERATIVA, 'ubicacion' => 'Pataz', 'activo' => true]);
        $area = Area::query()->create(['nombre' => 'Operaciones Mina', 'sede_id' => $sedePataz->id, 'activo' => true]);
        $unidad = UnidadMedida::query()->create(['nombre' => 'Unidad', 'abreviatura' => 'UND', 'activo' => true]);

        $jefeLog = User::factory()->create(['rol' => RolUsuario::LOGISTICA_JEFE, 'sede_id' => $sedeTrujillo->id, 'activo' => true]);
        $recepPataz = User::factory()->create(['rol' => RolUsuario::RECEPCION_PATAZ, 'sede_id' => $sedePataz->id, 'activo' => true]);

        $categoria = CategoriaMaterial::query()->create(['nombre' => 'EPP', 'activo' => true]);
        $material = Material::query()->create([
            'codigo' => 'MAT-T01',
            'nombre' => 'Casco minero',
            'categoria_id' => $categoria->id,
            'unidad_medida_id' => $unidad->id,
            'stock_minimo' => 5,
            'costo_referencial' => 45,
            'activo' => true,
        ]);

        $almTrujillo = Almacen::query()->create([
            'codigo' => 'ALM-TRU',
            'nombre' => 'Almacén Trujillo',
            'sede_id' => $sedeTrujillo->id,
            'es_principal' => true,
            'activo' => true,
        ]);

        $almPataz = Almacen::query()->create([
            'codigo' => 'ALM-PAT',
            'nombre' => 'Almacén Pataz',
            'sede_id' => $sedePataz->id,
            'es_principal' => false,
            'activo' => true,
        ]);

        app(InventarioService::class)->aplicarMovimiento(
            almacenId: $almTrujillo->id,
            materialId: $material->id,
            tipo: TipoMovimientoInventario::ENTRADA_COMPRA,
            cantidad: 20,
            usuario: $jefeLog,
            observacion: 'Stock inicial prueba',
        );

        return compact('sedeTrujillo', 'sedePataz', 'area', 'unidad', 'jefeLog', 'recepPataz', 'material', 'almTrujillo', 'almPataz');
    }

    public function test_despachar_envio_bloquea_stock_negativo_y_no_altera_saldo(): void
    {
        $esc = $this->prepararEscenarioBase();
        $envioService = app(EnvioService::class);

        $envio = $envioService->crear(
            datos: [
                'almacen_origen_id' => $esc['almTrujillo']->id,
                'almacen_destino_id' => $esc['almPataz']->id,
            ],
            items: [
                ['material_id' => $esc['material']->id, 'cantidad_enviada' => 50],
            ],
            responsable: $esc['jefeLog'],
        );

        try {
            $envioService->despachar($envio, $esc['jefeLog']);
            $this->fail('Debió lanzar ValidationException por stock insuficiente.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey('stock', $e->errors());
        }

        $envio->refresh();
        $this->assertSame(EstadoEnvio::PREPARADO, $envio->estado);

        $saldo = (float) StockAlmacen::query()
            ->where('almacen_id', $esc['almTrujillo']->id)
            ->where('material_id', $esc['material']->id)
            ->value('cantidad');
        $this->assertEquals(20.0, $saldo);
    }

    public function test_recepcion_con_diferencias_actualiza_kardex_y_requerimiento_parcial(): void
    {
        $esc = $this->prepararEscenarioBase();
        $reqService = app(RequerimientoService::class);
        $envioService = app(EnvioService::class);

        $req = $reqService->crear(
            datos: [
                'fecha_solicitud' => now()->toDateString(),
                'area_id' => $esc['area']->id,
                'sede_id' => $esc['sedePataz']->id,
                'prioridad' => PrioridadRequerimiento::ALTA->value,
            ],
            items: [
                ['material_id' => $esc['material']->id, 'cantidad_solicitada' => 10],
            ],
            solicitante: $esc['recepPataz'],
            enviarInmediatamente: true,
        );

        $reqService->aprobar($req, $esc['jefeLog']);

        $envio = $envioService->crear(
            datos: [
                'requerimiento_id' => $req->id,
                'almacen_origen_id' => $esc['almTrujillo']->id,
                'almacen_destino_id' => $esc['almPataz']->id,
            ],
            items: [
                ['material_id' => $esc['material']->id, 'cantidad_enviada' => 10],
            ],
            responsable: $esc['jefeLog'],
            despacharInmediatamente: true,
        );

        $this->assertSame(EstadoEnvio::EN_TRANSITO, $envio->estado);
        $this->assertEquals(10.0, (float) StockAlmacen::query()
            ->where('almacen_id', $esc['almTrujillo']->id)
            ->where('material_id', $esc['material']->id)
            ->value('cantidad'));

        $detalle = $envio->detalles()->firstOrFail();

        $recibido = $envioService->recibir(
            envio: $envio,
            usuario: $esc['recepPataz'],
            recepcionesPorDetalleId: [
                $detalle->id => [
                    'cantidad_recibida' => 7,
                    'observacion_recepcion' => '3 cascos dañados en transporte',
                ],
            ],
        );

        $this->assertSame(EstadoEnvio::RECIBIDO_CON_OBSERVACIONES, $recibido->estado);
        $this->assertSame(EstadoRequerimiento::ATENDIDO_PARCIAL, $req->fresh()->estado);
        $this->assertEquals(7.0, (float) $req->detalles()->firstOrFail()->cantidad_atendida);

        $saldoDestino = (float) StockAlmacen::query()
            ->where('almacen_id', $esc['almPataz']->id)
            ->where('material_id', $esc['material']->id)
            ->value('cantidad');
        $this->assertEquals(7.0, $saldoDestino);
    }

    public function test_anulacion_envio_en_transito_revierte_stock_y_deja_kardex(): void
    {
        $esc = $this->prepararEscenarioBase();
        $envioService = app(EnvioService::class);

        $envio = $envioService->crear(
            datos: [
                'almacen_origen_id' => $esc['almTrujillo']->id,
                'almacen_destino_id' => $esc['almPataz']->id,
            ],
            items: [
                ['material_id' => $esc['material']->id, 'cantidad_enviada' => 8],
            ],
            responsable: $esc['jefeLog'],
            despacharInmediatamente: true,
        );

        $this->assertEquals(12.0, (float) StockAlmacen::query()
            ->where('almacen_id', $esc['almTrujillo']->id)
            ->where('material_id', $esc['material']->id)
            ->value('cantidad'));

        $anulado = $envioService->anular($envio, $esc['jefeLog'], 'Unidad sufrió desperfecto mecánico');

        $this->assertSame(EstadoEnvio::ANULADO, $anulado->estado);
        $this->assertEquals(20.0, (float) StockAlmacen::query()
            ->where('almacen_id', $esc['almTrujillo']->id)
            ->where('material_id', $esc['material']->id)
            ->value('cantidad'));

        $movReversion = MovimientoInventario::query()
            ->where('documento_id', $envio->id)
            ->where('tipo', TipoMovimientoInventario::AJUSTE)
            ->first();
        $this->assertNotNull($movReversion);
        $this->assertEquals(8.0, (float) $movReversion->cantidad);
    }

    public function test_correlativos_anuales_son_unicos_y_secuenciales(): void
    {
        $correlativos = app(CorrelativoService::class);
        $anio = 2026;

        $c1 = $correlativos->siguienteCodigo('REQ', $anio);
        $c2 = $correlativos->siguienteCodigo('REQ', $anio);
        $e1 = $correlativos->siguienteCodigo('ENV', $anio);

        $this->assertSame('REQ-2026-0001', $c1);
        $this->assertSame('REQ-2026-0002', $c2);
        $this->assertSame('ENV-2026-0001', $e1);
    }

    public function test_usuario_no_puede_aprobar_su_propio_requerimiento(): void
    {
        $esc = $this->prepararEscenarioBase();
        $reqService = app(RequerimientoService::class);

        $req = $reqService->crear(
            datos: [
                'fecha_solicitud' => now()->toDateString(),
                'area_id' => $esc['area']->id,
                'sede_id' => $esc['sedePataz']->id,
                'prioridad' => PrioridadRequerimiento::MEDIA->value,
            ],
            items: [
                ['material_id' => $esc['material']->id, 'cantidad_solicitada' => 4],
            ],
            solicitante: $esc['jefeLog'],
            enviarInmediatamente: true,
        );

        $this->expectException(AuthorizationException::class);
        $reqService->aprobar($req, $esc['jefeLog']);
    }
}
