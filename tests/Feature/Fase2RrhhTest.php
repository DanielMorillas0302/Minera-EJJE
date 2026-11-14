<?php

namespace Tests\Feature;

use App\Enums\EstadoSolicitudRrhh;
use App\Enums\EstadoTrabajador;
use App\Enums\ResultadoExamen;
use App\Enums\RolUsuario;
use App\Enums\SexoTrabajador;
use App\Enums\TipoExamenOcupacional;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoPermiso;
use App\Enums\TipoSede;
use App\Enums\UbicacionTrabajador;
use App\Models\Core\Adjunto;
use App\Models\Core\Area;
use App\Models\Core\Cargo;
use App\Models\Core\Sede;
use App\Models\Rrhh\ExamenOcupacional;
use App\Models\Rrhh\PeriodoVacacional;
use App\Models\Rrhh\SolicitudVacaciones;
use App\Models\Rrhh\Trabajador;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class Fase2RrhhTest extends TestCase
{
    use RefreshDatabase;

    public function test_flujo_completo_trabajador_examen_aptitud_y_envio_pataz(): void
    {
        Storage::fake('local');

        $rrhhJefe = User::factory()->conRol(RolUsuario::RRHH_JEFE)->create();
        $trujillo = Sede::create(['nombre' => 'Trujillo', 'tipo' => TipoSede::ADMINISTRATIVA]);
        $pataz = Sede::create(['nombre' => 'Pataz', 'tipo' => TipoSede::OPERATIVA]);
        $area = Area::create(['nombre' => 'Operaciones', 'sede_id' => $pataz->id]);
        $cargo = Cargo::create(['nombre' => 'Operario de mina']);

        // 1. Crear trabajador en Trujillo
        $this->actingAs($rrhhJefe)->post('/rrhh/trabajadores', [
            'dni' => '70112233',
            'nombres' => 'Mateo',
            'apellidos' => 'Cárdenas Quispe',
            'fecha_nacimiento' => '1991-03-14',
            'sexo' => SexoTrabajador::MASCULINO->value,
            'cargo_id' => $cargo->id,
            'area_id' => $area->id,
            'sede_base_id' => $pataz->id,
            'fecha_ingreso' => '2025-01-10',
            'estado' => EstadoTrabajador::ACTIVO->value,
            'ubicacion_actual' => UbicacionTrabajador::TRUJILLO->value,
            'esquema_rotacion' => '21x7',
        ])->assertSessionHas('exito');

        $trabajador = Trabajador::where('dni', '70112233')->firstOrFail();

        // 2. Intento de enviar a Pataz sin examen médico -> bloqueado con error
        $respBloqueo = $this->actingAs($rrhhJefe)->post('/rrhh/movimientos', [
            'trabajador_id' => $trabajador->id,
            'tipo' => TipoMovimientoPersonal::SALIDA_A_PATAZ->value,
            'fecha_hora' => now()->format('Y-m-d H:i:s'),
            'origen_sede_id' => $trujillo->id,
            'destino_sede_id' => $pataz->id,
        ]);
        $respBloqueo->assertSessionHasErrors('trabajador_id');
        $this->assertSame(UbicacionTrabajador::TRUJILLO, $trabajador->fresh()->ubicacion_actual);

        // 3. Registrar examen ocupacional APTO vigente con PDF adjunto
        $pdf = UploadedFile::fake()->create('certificado-emo.pdf', 150, 'application/pdf');
        $this->actingAs($rrhhJefe)->post('/rrhh/examenes', [
            'trabajador_id' => $trabajador->id,
            'tipo' => TipoExamenOcupacional::PERIODICO->value,
            'fecha_examen' => Carbon::today()->toDateString(),
            'fecha_vencimiento' => Carbon::today()->addYear()->toDateString(),
            'clinica' => 'Clínica San Pablo Trujillo',
            'resultado' => ResultadoExamen::APTO->value,
            'observaciones' => 'Apto sin restricciones.',
            'archivo' => $pdf,
        ])->assertSessionHas('exito');

        $examen = ExamenOcupacional::where('trabajador_id', $trabajador->id)->firstOrFail();
        $adjunto = Adjunto::where('entidad_type', ExamenOcupacional::class)
            ->where('entidad_id', $examen->id)
            ->firstOrFail();
        Storage::disk('local')->assertExists($adjunto->archivo);

        // 4. Enviar a Pataz exitosamente -> ubicacion_actual cambia a PATAZ
        $this->actingAs($rrhhJefe)->post('/rrhh/movimientos', [
            'trabajador_id' => $trabajador->id,
            'tipo' => TipoMovimientoPersonal::SALIDA_A_PATAZ->value,
            'fecha_hora' => now()->format('Y-m-d H:i:s'),
            'origen_sede_id' => $trujillo->id,
            'destino_sede_id' => $pataz->id,
            'transporte' => 'Bus Corporativo 04',
        ])->assertSessionHas('exito');

        $this->assertSame(UbicacionTrabajador::PATAZ, $trabajador->fresh()->ubicacion_actual);

        // 5. Retorno a Trujillo -> ubicacion_actual cambia a TRUJILLO
        $this->actingAs($rrhhJefe)->post('/rrhh/movimientos', [
            'trabajador_id' => $trabajador->id,
            'tipo' => TipoMovimientoPersonal::RETORNO_A_TRUJILLO->value,
            'fecha_hora' => now()->addHours(8)->format('Y-m-d H:i:s'),
            'origen_sede_id' => $pataz->id,
            'destino_sede_id' => $trujillo->id,
        ])->assertSessionHas('exito');

        $this->assertSame(UbicacionTrabajador::TRUJILLO, $trabajador->fresh()->ubicacion_actual);

        // 6. Descarga de adjunto médico funciona para RRHH_JEFE
        $this->actingAs($rrhhJefe)
            ->get("/rrhh/examenes/adjuntos/{$adjunto->id}/descargar")
            ->assertOk();
    }

    public function test_control_permisos_por_rol_y_confidencialidad_medica_en_rrhh(): void
    {
        Storage::fake('local');

        $sede = Sede::create(['nombre' => 'Trujillo', 'tipo' => TipoSede::ADMINISTRATIVA]);
        $area = Area::create(['nombre' => 'RR.HH.', 'sede_id' => $sede->id]);
        $cargo = Cargo::create(['nombre' => 'Supervisor de seguridad']);
        $trabajador = Trabajador::create([
            'dni' => '70998877',
            'nombres' => 'Andrés',
            'apellidos' => 'Vargas Ruiz',
            'fecha_nacimiento' => '1988-09-01',
            'sexo' => SexoTrabajador::MASCULINO,
            'cargo_id' => $cargo->id,
            'area_id' => $area->id,
            'sede_base_id' => $sede->id,
            'fecha_ingreso' => '2023-05-01',
            'estado' => EstadoTrabajador::ACTIVO,
            'ubicacion_actual' => UbicacionTrabajador::TRUJILLO,
        ]);

        $examen = ExamenOcupacional::create([
            'trabajador_id' => $trabajador->id,
            'tipo' => TipoExamenOcupacional::PERIODICO,
            'fecha_examen' => Carbon::today()->subDays(10),
            'fecha_vencimiento' => Carbon::today()->addDays(20),
            'clinica' => 'Policlínico Norte',
            'resultado' => ResultadoExamen::APTO,
            'observaciones' => 'Diagnóstico confidencial reservado',
        ]);
        $ruta = UploadedFile::fake()->create('emo.pdf', 50, 'application/pdf')->store('rrhh/examenes', 'local');
        $adjunto = Adjunto::create([
            'archivo' => $ruta,
            'nombre_original' => 'emo.pdf',
            'entidad_type' => ExamenOcupacional::class,
            'entidad_id' => $examen->id,
        ]);

        $finanzas = User::factory()->conRol(RolUsuario::FINANZAS)->create();
        $gerente = User::factory()->conRol(RolUsuario::GERENTE)->create();
        $logJefe = User::factory()->conRol(RolUsuario::LOGISTICA_JEFE)->create();
        $recepcion = User::factory()->conRol(RolUsuario::RECEPCION_PATAZ)->create();

        // FINANZAS: solo lectura básica de trabajadores
        $this->actingAs($finanzas)->get('/rrhh/trabajadores')->assertOk();
        $this->actingAs($finanzas)->get("/rrhh/trabajadores/{$trabajador->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('trabajador.aptitud_visible', false)
                ->where('examenes', [])
            );
        $this->actingAs($finanzas)->get('/rrhh/examenes')->assertStatus(403);
        $this->actingAs($finanzas)->get('/rrhh/vacaciones')->assertStatus(403);
        $this->actingAs($finanzas)->get('/rrhh/movimientos')->assertStatus(403);
        $this->actingAs($finanzas)->post('/rrhh/trabajadores', [])->assertStatus(403);

        // GERENTE: lectura sin resultado médico específico y sin descarga de archivos médicos
        $this->actingAs($gerente)->get('/rrhh/examenes')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('esMedicoAutorizado', false)
                ->where('examenes.data.0.resultado', null)
                ->where('examenes.data.0.observaciones', null)
                ->where('examenes.data.0.adjuntos', [])
            );
        $this->actingAs($gerente)
            ->get("/rrhh/examenes/adjuntos/{$adjunto->id}/descargar")
            ->assertStatus(403);
        $this->actingAs($gerente)->post('/rrhh/examenes', [])->assertStatus(403);

        // LOGISTICA_JEFE: lectura solo de movimientos y reporte de movimientos
        $this->actingAs($logJefe)->get('/rrhh')->assertOk();
        $this->actingAs($logJefe)->get('/rrhh/movimientos')->assertOk();
        $this->actingAs($logJefe)->get('/rrhh/trabajadores')->assertStatus(403);
        $this->actingAs($logJefe)->get('/rrhh/examenes')->assertStatus(403);
        $this->actingAs($logJefe)->post('/rrhh/movimientos', [])->assertStatus(403);

        // RECEPCION_PATAZ: sin acceso al módulo RR.HH.
        $this->actingAs($recepcion)->get('/rrhh')->assertStatus(403);
        $this->actingAs($recepcion)->get('/rrhh/trabajadores')->assertStatus(403);
    }

    public function test_asistente_no_puede_aprobar_ni_propia_ni_ajena_y_export_xlsx_funciona(): void
    {
        $asistente = User::factory()->conRol(RolUsuario::RRHH_ASISTENTE)->create();
        $jefe = User::factory()->conRol(RolUsuario::RRHH_JEFE)->create();
        $sede = Sede::create(['nombre' => 'Trujillo', 'tipo' => TipoSede::ADMINISTRATIVA]);
        $area = Area::create(['nombre' => 'RR.HH.', 'sede_id' => $sede->id]);
        $cargo = Cargo::create(['nombre' => 'Contador']);
        $trabajador = Trabajador::create([
            'dni' => '70554433',
            'nombres' => 'Lucía',
            'apellidos' => 'Mendoza',
            'fecha_nacimiento' => '1993-04-20',
            'sexo' => SexoTrabajador::FEMENINO,
            'cargo_id' => $cargo->id,
            'area_id' => $area->id,
            'sede_base_id' => $sede->id,
            'fecha_ingreso' => '2024-02-01',
            'estado' => EstadoTrabajador::ACTIVO,
            'ubicacion_actual' => UbicacionTrabajador::TRUJILLO,
        ]);

        // Asistente registra solicitud de vacaciones
        $this->actingAs($asistente)->post('/rrhh/vacaciones', [
            'trabajador_id' => $trabajador->id,
            'fecha_inicio' => Carbon::today()->addDays(15)->toDateString(),
            'fecha_fin' => Carbon::today()->addDays(19)->toDateString(),
        ])->assertSessionHas('exito');

        $solicitud = SolicitudVacaciones::firstOrFail();

        // Asistente recibe 403 al intentar aprobar
        $this->actingAs($asistente)
            ->post("/rrhh/vacaciones/{$solicitud->id}/resolver", ['accion' => 'aprobar'])
            ->assertStatus(403);

        // Jefe aprueba solicitud creada por el asistente -> éxito
        $this->actingAs($jefe)
            ->post("/rrhh/vacaciones/{$solicitud->id}/resolver", ['accion' => 'aprobar'])
            ->assertSessionHas('exito');

        $this->assertSame(EstadoSolicitudRrhh::APROBADA, $solicitud->fresh()->estado);

        // Jefe registra permiso y no puede aprobar su propia solicitud
        $this->actingAs($jefe)->post('/rrhh/permisos', [
            'trabajador_id' => $trabajador->id,
            'tipo' => TipoPermiso::SALUD->value,
            'con_goce' => true,
            'fecha_inicio' => Carbon::today()->addDays(30)->toDateString(),
            'fecha_fin' => Carbon::today()->addDays(30)->toDateString(),
            'motivo' => 'Control médico',
        ])->assertSessionHas('exito');

        $permiso = $trabajador->permisos()->firstOrFail();
        $this->actingAs($jefe)
            ->post("/rrhh/permisos/{$permiso->id}/resolver", ['accion' => 'aprobar'])
            ->assertStatus(403);

        // Exportación de reporte RR.HH. a XLSX
        $respuestaExport = $this->actingAs($jefe)->get('/rrhh/reportes/exportar/personal');
        $respuestaExport->assertOk();
        $this->assertStringContainsString(
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            (string) $respuestaExport->headers->get('content-type')
        );
    }

    public function test_semilla_rrhh_carga_30_trabajadores_idempotentes_en_debug(): void
    {
        config(['app.debug' => true]);

        $this->artisan('cargar_semilla')->assertSuccessful();
        $this->artisan('cargar_semilla')->assertSuccessful();

        $this->assertSame(30, Trabajador::count());
        $this->assertGreaterThanOrEqual(25, ExamenOcupacional::count());
        $this->assertSame(30, PeriodoVacacional::count());

        // Verificar que existen exámenes vigentes, por vencer y vencidos para pruebas de alertas
        $this->assertGreaterThan(0, ExamenOcupacional::vigentes()->count());
        $this->assertGreaterThan(0, ExamenOcupacional::porVencer(30)->count());
        $this->assertGreaterThan(0, ExamenOcupacional::vencidos()->count());
    }
}
