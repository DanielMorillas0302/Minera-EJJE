<?php

namespace Tests\Feature;

use App\Enums\RolUsuario;
use App\Models\User;
use App\Services\Importacion\ImportadorService;
use Database\Seeders\CoreSeeder;
use Database\Seeders\LogisticaSeeder;
use Database\Seeders\RrhhSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Tests\TestCase;

class Fase4ImportadorTest extends TestCase
{
    use RefreshDatabase;

    private function sembrarTodo(): void
    {
        config(['app.debug' => true]);
        $this->seed([CoreSeeder::class, RrhhSeeder::class, LogisticaSeeder::class]);
    }

    private function crearArchivoExcel(array $filas): UploadedFile
    {
        $spreadsheet = new Spreadsheet;
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->fromArray($filas, null, 'A1');

        $writer = new Xlsx($spreadsheet);
        $tempPath = tempnam(sys_get_temp_dir(), 'test_feature_import_').'.xlsx';
        $writer->save($tempPath);

        return new UploadedFile(
            $tempPath,
            'importacion_test.xlsx',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            null,
            true
        );
    }

    public function test_control_de_acceso_y_permisos_por_rol_en_importaciones(): void
    {
        $this->sembrarTodo();

        $admin = User::query()->where('rol', RolUsuario::ADMIN)->firstOrFail();
        $rrhhAsist = User::query()->where('rol', RolUsuario::RRHH_ASISTENTE)->firstOrFail();
        $logAsist = User::query()->where('rol', RolUsuario::LOGISTICA_ASISTENTE)->firstOrFail();
        $recepPataz = User::query()->where('rol', RolUsuario::RECEPCION_PATAZ)->firstOrFail();
        $finanzas = User::query()->where('rol', RolUsuario::FINANZAS)->firstOrFail();
        $gerente = User::query()->where('rol', RolUsuario::GERENTE)->firstOrFail();

        // 1. Roles sin acceso total al importador reciben 403
        $this->actingAs($finanzas)->get('/importaciones')->assertStatus(403);
        $this->actingAs($gerente)->get('/importaciones')->assertStatus(403);

        // 2. Roles con acceso parcial pueden cargar la vista
        $this->actingAs($admin)->get('/importaciones')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('core/importaciones')
                ->has('tiposDisponibles', 5)
            );

        $this->actingAs($rrhhAsist)->get('/importaciones')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('core/importaciones')
                ->has('tiposDisponibles', 1)
            );

        $this->actingAs($logAsist)->get('/importaciones')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('core/importaciones')
                ->has('tiposDisponibles', 4)
            );

        $this->actingAs($recepPataz)->get('/importaciones')->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('core/importaciones')
                ->has('tiposDisponibles', 1)
            );

        // 3. Recepción Pataz bloqueado si intenta subir trabajadores o materiales
        $archivo = $this->crearArchivoExcel([['dni', 'nombres'], ['123', 'X']]);
        $this->actingAs($recepPataz)->post('/importaciones/previsualizar', [
            'tipo' => ImportadorService::TIPO_TRABAJADORES,
            'archivo' => $archivo,
        ])->assertStatus(403);

        $this->actingAs($recepPataz)->post('/importaciones/previsualizar', [
            'tipo' => ImportadorService::TIPO_MATERIALES,
            'archivo' => $archivo,
        ])->assertStatus(403);

        // 4. RR.HH. bloqueado en materiales
        $this->actingAs($rrhhAsist)->post('/importaciones/previsualizar', [
            'tipo' => ImportadorService::TIPO_MATERIALES,
            'archivo' => $archivo,
        ])->assertStatus(403);
    }

    public function test_flujo_completo_previsualizacion_confirmacion_y_reporte_errores(): void
    {
        $this->sembrarTodo();
        $admin = User::query()->where('rol', RolUsuario::ADMIN)->firstOrFail();

        // 1. Descarga de plantilla oficial
        $respPlantilla = $this->actingAs($admin)->get('/importaciones/plantilla/materiales');
        $respPlantilla->assertOk();
        $this->assertStringContainsString('plantilla-materiales.xlsx', (string) $respPlantilla->headers->get('content-disposition'));

        // 2. Subida de archivo con 1 fila válida y 1 con error
        $archivo = $this->crearArchivoExcel([
            ['codigo', 'nombre', 'descripcion', 'categoria', 'unidad_medida', 'stock_minimo', 'costo_referencial'],
            // Fila 2 válida
            ['MAT-FEAT01', 'Broca diamantada 2"', 'Broca de perforación', 'Herramientas', 'UND', '10', '250'],
            // Fila 3 inválida (categoría inexistente)
            ['MAT-FEAT02', 'Líquido refrigerante', '', 'Categoría No Existe', 'GL', '5', '80'],
        ]);

        $respPreview = $this->actingAs($admin)->post('/importaciones/previsualizar', [
            'tipo' => ImportadorService::TIPO_MATERIALES,
            'archivo' => $archivo,
        ]);

        $respPreview->assertOk()
            ->assertJsonPath('total_filas', 2)
            ->assertJsonPath('validas_count', 1)
            ->assertJsonPath('errores_count', 1);

        $token = $respPreview->json('token');
        $this->assertNotEmpty($token);

        // 3. Descarga del reporte de errores
        $respErrores = $this->actingAs($admin)->get("/importaciones/reporte-errores?token={$token}");
        $respErrores->assertOk();
        $this->assertStringContainsString('reporte-errores-materiales', (string) $respErrores->headers->get('content-disposition'));

        // 4. Confirmación de las filas válidas
        $respConfirmar = $this->actingAs($admin)->post('/importaciones/confirmar', [
            'token' => $token,
        ]);

        $respConfirmar->assertOk()
            ->assertJsonPath('exitosos', 1);

        $this->assertDatabaseHas('materiales', ['codigo' => 'MAT-FEAT01']);
    }

    public function test_importacion_de_proveedores_valida_ruc_11_digitos_via_http(): void
    {
        $this->sembrarTodo();
        $logJefe = User::query()->where('rol', RolUsuario::LOGISTICA_JEFE)->firstOrFail();

        $archivo = $this->crearArchivoExcel([
            ['ruc', 'razon_social', 'contacto', 'condicion_pago'],
            ['20608899112', 'Servicios Mineros Trujillo S.A.C.', 'Carlos Ramos', 'CONTADO'],
            ['12345', 'Proveedor Con Ruc Corto', '', 'CONTADO'],
        ]);

        $respPreview = $this->actingAs($logJefe)->post('/importaciones/previsualizar', [
            'tipo' => ImportadorService::TIPO_PROVEEDORES,
            'archivo' => $archivo,
        ]);

        $respPreview->assertOk()
            ->assertJsonPath('validas_count', 1)
            ->assertJsonPath('errores_count', 1);

        $token = $respPreview->json('token');

        $this->actingAs($logJefe)->post('/importaciones/confirmar', ['token' => $token])
            ->assertOk()
            ->assertJsonPath('exitosos', 1);

        $this->assertDatabaseHas('proveedores', ['ruc' => '20608899112']);
    }
}
