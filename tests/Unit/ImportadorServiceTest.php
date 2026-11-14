<?php

namespace Tests\Unit;

use App\Enums\EstadoEnvio;
use App\Enums\RolUsuario;
use App\Enums\TipoMovimientoInventario;
use App\Enums\TipoSede;
use App\Models\Core\Area;
use App\Models\Core\Cargo;
use App\Models\Core\Sede;
use App\Models\Core\UnidadMedida;
use App\Models\Logistica\Almacen;
use App\Models\Logistica\CategoriaMaterial;
use App\Models\Logistica\Material;
use App\Models\Logistica\StockAlmacen;
use App\Models\Rrhh\Trabajador;
use App\Models\User;
use App\Services\Importacion\ImportadorService;
use App\Services\Logistica\EnvioService;
use App\Services\Logistica\InventarioService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class ImportadorServiceTest extends TestCase
{
    use RefreshDatabase;

    private function crearArchivoExcel(array $filas): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($filas, null, 'A1');

        $writer = new Xlsx($spreadsheet);
        $tempPath = tempnam(sys_get_temp_dir(), 'test_import_').'.xlsx';
        $writer->save($tempPath);

        return new UploadedFile(
            $tempPath,
            'archivo_prueba.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    private function prepararCatalogosBase(): array
    {
        $sedeTrujillo = Sede::query()->create(['nombre' => 'Trujillo', 'tipo' => TipoSede::ADMINISTRATIVA, 'ubicacion' => 'Trujillo', 'activo' => true]);
        $sedePataz = Sede::query()->create(['nombre' => 'Pataz', 'tipo' => TipoSede::OPERATIVA, 'ubicacion' => 'Pataz', 'activo' => true]);
        $area = Area::query()->create(['nombre' => 'Operaciones Mina', 'sede_id' => $sedePataz->id, 'activo' => true]);
        $cargo = Cargo::query()->create(['nombre' => 'Operario de mina', 'descripcion' => 'Mina', 'activo' => true]);
        $unidad = UnidadMedida::query()->create(['nombre' => 'Unidad', 'abreviatura' => 'UND', 'activo' => true]);
        $categoria = CategoriaMaterial::query()->create(['nombre' => 'Herramientas', 'activo' => true]);

        $admin = User::factory()->create(['rol' => RolUsuario::ADMIN, 'sede_id' => $sedeTrujillo->id, 'activo' => true]);
        $recepPataz = User::factory()->create(['rol' => RolUsuario::RECEPCION_PATAZ, 'sede_id' => $sedePataz->id, 'activo' => true]);

        $almTrujillo = Almacen::query()->create(['codigo' => 'ALM-TRU', 'nombre' => 'Almacén Trujillo', 'sede_id' => $sedeTrujillo->id, 'es_principal' => true, 'activo' => true]);
        $almPataz = Almacen::query()->create(['codigo' => 'ALM-PAT', 'nombre' => 'Almacén Pataz', 'sede_id' => $sedePataz->id, 'es_principal' => false, 'activo' => true]);

        $material = Material::query()->create([
            'codigo' => 'MAT-T01',
            'nombre' => 'Barreno 5 pies',
            'categoria_id' => $categoria->id,
            'unidad_medida_id' => $unidad->id,
            'stock_minimo' => 5,
            'costo_referencial' => 120,
            'activo' => true,
        ]);

        return compact('sedeTrujillo', 'sedePataz', 'area', 'cargo', 'unidad', 'categoria', 'admin', 'recepPataz', 'almTrujillo', 'almPataz', 'material');
    }

    public function test_descarga_plantillas_para_los_5_tipos(): void
    {
        $importador = app(ImportadorService::class);

        foreach (ImportadorService::tiposValidos() as $tipo) {
            $resp = $importador->descargarPlantilla($tipo);
            $this->assertSame(200, $resp->getStatusCode());
            $this->assertStringContainsString('.xlsx', $resp->getFile()->getFilename());
        }
    }

    public function test_valida_columnas_faltantes_y_lanza_excepcion(): void
    {
        $base = $this->prepararCatalogosBase();
        $importador = app(ImportadorService::class);

        $archivoInvalido = $this->crearArchivoExcel([
            ['dni', 'nombres'], // faltan apellidos, cargo, etc.
            ['45891234', 'Juan'],
        ]);

        $this->expectException(ValidationException::class);
        $importador->previsualizar(ImportadorService::TIPO_TRABAJADORES, $archivoInvalido, $base['admin']);
    }

    public function test_previsualizacion_de_trabajadores_detecta_validos_y_errores(): void
    {
        $base = $this->prepararCatalogosBase();
        $importador = app(ImportadorService::class);

        // Crear un trabajador previo para verificar detección de DNI duplicado
        Trabajador::query()->create([
            'dni' => '11223344',
            'nombres' => 'Existente',
            'apellidos' => 'Ya Registrado',
            'fecha_nacimiento' => '1990-01-01',
            'sexo' => 'M',
            'cargo_id' => $base['cargo']->id,
            'area_id' => $base['area']->id,
            'sede_base_id' => $base['sedePataz']->id,
            'fecha_ingreso' => '2026-01-01',
            'activo' => true,
        ]);

        $archivo = $this->crearArchivoExcel([
            ['dni', 'nombres', 'apellidos', 'fecha_nacimiento', 'sexo', 'telefono', 'email', 'direccion', 'cargo', 'area', 'sede_base', 'fecha_ingreso', 'esquema_rotacion'],
            // Fila 2: Válida
            ['77889900', 'Pedro', 'Castillo Alva', '1992-05-10', 'M', '949111222', 'pedro@mineria.pe', 'Trujillo', 'Operario de mina', 'Operaciones Mina', 'Pataz', '2026-02-01', '21x7'],
            // Fila 3: Error DNI corto
            ['123', 'Luis', 'Torres', '1995-03-12', 'M', '', '', '', 'Operario de mina', 'Operaciones Mina', 'Pataz', '2026-02-01', '21x7'],
            // Fila 4: Error Cargo inexistente
            ['88991122', 'Ana', 'Ramos', '1996-08-15', 'F', '', '', '', 'Cargo Inexistente', 'Operaciones Mina', 'Pataz', '2026-02-01', '21x7'],
            // Fila 5: Error DNI duplicado en BD
            ['11223344', 'Duplicado', 'Pérez', '1991-04-20', 'M', '', '', '', 'Operario de mina', 'Operaciones Mina', 'Pataz', '2026-02-01', '21x7'],
        ]);

        $res = $importador->previsualizar(ImportadorService::TIPO_TRABAJADORES, $archivo, $base['admin']);

        $this->assertSame(4, $res['total_filas']);
        $this->assertSame(1, $res['validas_count']);
        $this->assertSame(3, $res['errores_count']);
        $this->assertNotEmpty($res['token']);

        // Confirmar importación
        $resultadoConfirmar = $importador->confirmar($res['token'], $base['admin']);
        $this->assertSame(1, $resultadoConfirmar['exitosos']);
        $this->assertDatabaseHas('trabajadores', ['dni' => '77889900', 'nombres' => 'Pedro']);
    }

    public function test_previsualizacion_y_confirmacion_de_materiales(): void
    {
        $base = $this->prepararCatalogosBase();
        $importador = app(ImportadorService::class);

        $archivo = $this->crearArchivoExcel([
            ['codigo', 'nombre', 'descripcion', 'categoria', 'unidad_medida', 'stock_minimo', 'costo_referencial'],
            // Fila 2: Válida
            ['MAT-IMP01', 'Pala minera de arrastre', 'Pala reforzada', 'Herramientas', 'UND', '15', '85.50'],
            // Fila 3: Código duplicado con Fila 2
            ['MAT-IMP01', 'Pala duplicada', '', 'Herramientas', 'UND', '5', '80'],
            // Fila 4: Categoría inexistente
            ['MAT-IMP02', 'Lámpara solar', '', 'Inexistente Cat', 'UND', '5', '120'],
        ]);

        $res = $importador->previsualizar(ImportadorService::TIPO_MATERIALES, $archivo, $base['admin']);

        $this->assertSame(3, $res['total_filas']);
        $this->assertSame(1, $res['validas_count']);
        $this->assertSame(2, $res['errores_count']);

        $importador->confirmar($res['token'], $base['admin']);
        $this->assertDatabaseHas('materiales', ['codigo' => 'MAT-IMP01', 'stock_minimo' => 15]);
    }

    public function test_previsualizacion_y_confirmacion_de_proveedores(): void
    {
        $base = $this->prepararCatalogosBase();
        $importador = app(ImportadorService::class);

        $archivo = $this->crearArchivoExcel([
            ['ruc', 'razon_social', 'contacto', 'telefono', 'email', 'direccion', 'condicion_pago', 'observaciones'],
            // Fila 2: Válida
            ['20601122334', 'Comercial Pataz S.A.C.', 'Juan Díaz', '949000111', 'ventas@compataz.pe', 'Trujillo', 'CREDITO 15 DIAS', ''],
            // Fila 3: RUC inválido (no 11 dígitos)
            ['123456', 'Proveedor Invalido', '', '', '', '', 'CONTADO', ''],
        ]);

        $res = $importador->previsualizar(ImportadorService::TIPO_PROVEEDORES, $archivo, $base['admin']);

        $this->assertSame(2, $res['total_filas']);
        $this->assertSame(1, $res['validas_count']);
        $this->assertSame(1, $res['errores_count']);

        $importador->confirmar($res['token'], $base['admin']);
        $this->assertDatabaseHas('proveedores', ['ruc' => '20601122334']);
    }

    public function test_previsualizacion_y_confirmacion_de_stock_inicial(): void
    {
        $base = $this->prepararCatalogosBase();
        $importador = app(ImportadorService::class);

        $archivo = $this->crearArchivoExcel([
            ['almacen', 'codigo_material', 'cantidad', 'observacion'],
            // Fila 2: Válida
            ['ALM-TRU', 'MAT-T01', '45.5', 'Inventario inicial apertura'],
            // Fila 3: Material inexistente
            ['ALM-TRU', 'MAT-NO-EXISTE', '10', ''],
            // Fila 4: Cantidad negativa
            ['ALM-PAT', 'MAT-T01', '-5', ''],
        ]);

        $res = $importador->previsualizar(ImportadorService::TIPO_STOCK_INICIAL, $archivo, $base['admin']);

        $this->assertSame(3, $res['total_filas']);
        $this->assertSame(1, $res['validas_count']);
        $this->assertSame(2, $res['errores_count']);

        $importador->confirmar($res['token'], $base['admin']);

        $saldo = (float) StockAlmacen::query()
            ->where('almacen_id', $base['almTrujillo']->id)
            ->where('material_id', $base['material']->id)
            ->value('cantidad');
        $this->assertEquals(45.5, $saldo);
    }

    public function test_previsualizacion_y_confirmacion_de_recepcion_envio(): void
    {
        $base = $this->prepararCatalogosBase();
        $importador = app(ImportadorService::class);
        $envioService = app(EnvioService::class);
        $inventario = app(InventarioService::class);

        // Sembrar stock origen y crear envío en tránsito
        $inventario->aplicarMovimiento(
            almacenId: $base['almTrujillo']->id,
            materialId: $base['material']->id,
            tipo: TipoMovimientoInventario::ENTRADA_COMPRA,
            cantidad: 20,
            usuario: $base['admin'],
        );

        $envio = $envioService->crear(
            datos: [
                'almacen_origen_id' => $base['almTrujillo']->id,
                'almacen_destino_id' => $base['almPataz']->id,
            ],
            items: [
                ['material_id' => $base['material']->id, 'cantidad_enviada' => 10],
            ],
            responsable: $base['admin'],
            despacharInmediatamente: true,
        );

        $archivo = $this->crearArchivoExcel([
            ['codigo_envio', 'codigo_material', 'cantidad_recibida', 'observacion'],
            // Fila 2: Válida con diferencia (8 de 10)
            [$envio->codigo, 'MAT-T01', '8', '2 unidades dañadas en viaje'],
        ]);

        $res = $importador->previsualizar(ImportadorService::TIPO_RECEPCION_ENVIO, $archivo, $base['recepPataz']);

        $this->assertSame(1, $res['validas_count']);
        $this->assertSame(0, $res['errores_count']);

        $importador->confirmar($res['token'], $base['recepPataz']);

        $envio->refresh();
        $this->assertSame(EstadoEnvio::RECIBIDO_CON_OBSERVACIONES, $envio->estado);

        $saldoDestino = (float) StockAlmacen::query()
            ->where('almacen_id', $base['almPataz']->id)
            ->where('material_id', $base['material']->id)
            ->value('cantidad');
        $this->assertEquals(8.0, $saldoDestino);
    }

    public function test_descarga_reporte_de_errores_en_excel(): void
    {
        $base = $this->prepararCatalogosBase();
        $importador = app(ImportadorService::class);

        $archivo = $this->crearArchivoExcel([
            ['ruc', 'razon_social'],
            ['123', 'Proveedor Invalido'],
        ]);

        $res = $importador->previsualizar(ImportadorService::TIPO_PROVEEDORES, $archivo, $base['admin']);
        $this->assertSame(1, $res['errores_count']);

        $reporte = $importador->descargarReporteErrores($res['token']);
        $this->assertSame(200, $reporte->getStatusCode());
        $this->assertStringContainsString('reporte-errores-proveedores', (string) $reporte->headers->get('content-disposition'));
    }
}
