<?php

namespace Tests\Unit;

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
use App\Models\Core\Area;
use App\Models\Core\Cargo;
use App\Models\Core\Sede;
use App\Models\Rrhh\ExamenOcupacional;
use App\Models\Rrhh\Trabajador;
use App\Models\User;
use App\Services\Rrhh\MovimientoPersonalService;
use App\Services\Rrhh\VacacionesPermisosService;
use Carbon\Carbon;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class RrhhServiciosTest extends TestCase
{
    use RefreshDatabase;

    private Sede $trujillo;

    private Sede $pataz;

    private Trabajador $trabajador;

    protected function setUp(): void
    {
        parent::setUp();

        $this->trujillo = Sede::create(['nombre' => 'Trujillo', 'tipo' => TipoSede::ADMINISTRATIVA]);
        $this->pataz = Sede::create(['nombre' => 'Pataz', 'tipo' => TipoSede::OPERATIVA]);
        $area = Area::create(['nombre' => 'Operaciones', 'sede_id' => $this->pataz->id]);
        $cargo = Cargo::create(['nombre' => 'Operario de mina']);

        $this->trabajador = Trabajador::create([
            'dni' => '45001122',
            'nombres' => 'Luis',
            'apellidos' => 'Paredes Soto',
            'fecha_nacimiento' => '1992-05-10',
            'sexo' => SexoTrabajador::MASCULINO,
            'cargo_id' => $cargo->id,
            'area_id' => $area->id,
            'sede_base_id' => $this->pataz->id,
            'fecha_ingreso' => '2024-01-15',
            'estado' => EstadoTrabajador::ACTIVO,
            'ubicacion_actual' => UbicacionTrabajador::TRUJILLO,
            'esquema_rotacion' => '21x7',
        ]);
    }

    public function test_evaluacion_aptitud_viaje_cubre_todos_los_casos_criticos(): void
    {
        // Sin examen -> false
        $this->assertFalse($this->trabajador->aptitud_viaje);
        $this->assertSame('SIN_EXAMEN', $this->trabajador->detalleAptitudViaje()['codigo']);

        // Examen vencido -> false
        $vencido = ExamenOcupacional::create([
            'trabajador_id' => $this->trabajador->id,
            'tipo' => TipoExamenOcupacional::PERIODICO,
            'fecha_examen' => Carbon::today()->subYear()->subMonth(),
            'fecha_vencimiento' => Carbon::today()->subDay(),
            'clinica' => 'Clínica Norte',
            'resultado' => ResultadoExamen::APTO,
        ]);
        $this->assertFalse($vencido->vigente);
        $this->assertFalse($this->trabajador->fresh('examenes')->aptitud_viaje);

        // Examen APTO vigente -> true
        $vigente = ExamenOcupacional::create([
            'trabajador_id' => $this->trabajador->id,
            'tipo' => TipoExamenOcupacional::PERIODICO,
            'fecha_examen' => Carbon::today()->subDays(5),
            'fecha_vencimiento' => Carbon::today()->addDays(180),
            'clinica' => 'Clínica Norte',
            'resultado' => ResultadoExamen::APTO,
        ]);
        $this->assertTrue($vigente->vigente);
        $this->assertTrue($this->trabajador->fresh('examenes')->aptitud_viaje);

        // Examen más reciente con restricciones invalida aptitud inmediata
        ExamenOcupacional::create([
            'trabajador_id' => $this->trabajador->id,
            'tipo' => TipoExamenOcupacional::OTRO,
            'fecha_examen' => Carbon::today(),
            'fecha_vencimiento' => Carbon::today()->addDays(90),
            'clinica' => 'Clínica Norte',
            'resultado' => ResultadoExamen::APTO_CON_RESTRICCIONES,
        ]);
        $this->assertFalse($this->trabajador->fresh('examenes')->aptitud_viaje);

        // Trabajador cesado nunca es apto aunque tenga examen apto
        ExamenOcupacional::create([
            'trabajador_id' => $this->trabajador->id,
            'tipo' => TipoExamenOcupacional::INGRESO,
            'fecha_examen' => Carbon::today()->addDay(),
            'fecha_vencimiento' => Carbon::today()->addYear(),
            'clinica' => 'Clínica Norte',
            'resultado' => ResultadoExamen::APTO,
        ]);
        $this->trabajador->update(['estado' => EstadoTrabajador::CESADO, 'fecha_cese' => Carbon::today()]);
        $this->assertFalse($this->trabajador->fresh('examenes')->aptitud_viaje);
    }

    public function test_movimiento_personal_bloquea_salida_si_no_apto_y_actualiza_ubicacion(): void
    {
        $servicio = app(MovimientoPersonalService::class);
        $jefe = User::factory()->conRol(RolUsuario::RRHH_JEFE)->create();

        // Bloqueo por no tener examen apto
        $this->expectException(ValidationException::class);
        try {
            $servicio->registrar([
                'trabajador_id' => $this->trabajador->id,
                'tipo' => TipoMovimientoPersonal::SALIDA_A_PATAZ->value,
                'fecha_hora' => now(),
                'origen_sede_id' => $this->trujillo->id,
                'destino_sede_id' => $this->pataz->id,
            ], $jefe);
        } finally {
            $this->assertSame(UbicacionTrabajador::TRUJILLO, $this->trabajador->fresh()->ubicacion_actual);
        }
    }

    public function test_movimiento_personal_salida_y_retorno_exitoso(): void
    {
        $servicio = app(MovimientoPersonalService::class);
        $jefe = User::factory()->conRol(RolUsuario::RRHH_JEFE)->create();

        ExamenOcupacional::create([
            'trabajador_id' => $this->trabajador->id,
            'tipo' => TipoExamenOcupacional::PERIODICO,
            'fecha_examen' => Carbon::today()->subDays(10),
            'fecha_vencimiento' => Carbon::today()->addDays(200),
            'clinica' => 'Clínica San Pablo',
            'resultado' => ResultadoExamen::APTO,
        ]);

        $servicio->registrar([
            'trabajador_id' => $this->trabajador->id,
            'tipo' => TipoMovimientoPersonal::SALIDA_A_PATAZ->value,
            'fecha_hora' => now(),
            'origen_sede_id' => $this->trujillo->id,
            'destino_sede_id' => $this->pataz->id,
            'transporte' => 'Bus 01',
        ], $jefe);

        $this->assertSame(UbicacionTrabajador::PATAZ, $this->trabajador->fresh()->ubicacion_actual);

        $servicio->registrar([
            'trabajador_id' => $this->trabajador->id,
            'tipo' => TipoMovimientoPersonal::RETORNO_A_TRUJILLO->value,
            'fecha_hora' => now()->addDays(21),
            'origen_sede_id' => $this->pataz->id,
            'destino_sede_id' => $this->trujillo->id,
            'transporte' => 'Bus 02',
        ], $jefe);

        $this->assertSame(UbicacionTrabajador::TRUJILLO, $this->trabajador->fresh()->ubicacion_actual);
    }

    public function test_vacaciones_saldo_solapamiento_auto_aprobacion_y_estado_tomada(): void
    {
        $servicio = app(VacacionesPermisosService::class);
        $asistente = User::factory()->conRol(RolUsuario::RRHH_ASISTENTE)->create();
        $jefe = User::factory()->conRol(RolUsuario::RRHH_JEFE)->create();

        $anio = Carbon::today()->year + 1;
        $inicio = Carbon::create($anio, 2, 1);
        $fin = Carbon::create($anio, 2, 10); // 10 días inclusive

        $solicitud = $servicio->crearSolicitudVacaciones([
            'trabajador_id' => $this->trabajador->id,
            'fecha_inicio' => $inicio->toDateString(),
            'fecha_fin' => $fin->toDateString(),
        ], $asistente);

        $this->assertSame(10, $solicitud->dias);
        $this->assertSame(EstadoSolicitudRrhh::PENDIENTE, $solicitud->estado);

        // Solicitante no puede aprobar su propia solicitud
        try {
            $servicio->resolverSolicitudVacaciones($solicitud, true, $asistente);
            $this->fail('El asistente no debía poder aprobar su solicitud.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        // Un jefe tampoco puede aprobar una solicitud creada por sí mismo
        $solPropia = $servicio->crearSolicitudVacaciones([
            'trabajador_id' => $this->trabajador->id,
            'fecha_inicio' => Carbon::create($anio, 6, 1)->toDateString(),
            'fecha_fin' => Carbon::create($anio, 6, 5)->toDateString(),
        ], $jefe);

        try {
            $servicio->resolverSolicitudVacaciones($solPropia, true, $jefe);
            $this->fail('Un usuario no puede aprobar su propia solicitud.');
        } catch (AuthorizationException) {
            $this->assertTrue(true);
        }

        // Jefe aprueba solicitud del asistente -> descuenta 10 días del saldo (30 -> 20)
        $aprobada = $servicio->resolverSolicitudVacaciones($solicitud, true, $jefe);
        $this->assertSame(EstadoSolicitudRrhh::APROBADA, $aprobada->estado);
        $this->assertSame(10, $aprobada->periodo->fresh()->dias_tomados);
        $this->assertSame(20, $aprobada->periodo->fresh()->saldoDisponible());

        // Permiso solapado con vacaciones aprobadas debe fallar
        try {
            $servicio->crearPermiso([
                'trabajador_id' => $this->trabajador->id,
                'tipo' => TipoPermiso::PERSONAL->value,
                'con_goce' => true,
                'fecha_inicio' => Carbon::create($anio, 2, 8)->toDateString(),
                'fecha_fin' => Carbon::create($anio, 2, 12)->toDateString(),
                'motivo' => 'Asunto familiar',
            ], $asistente);
            $this->fail('Debía bloquearse el solapamiento con vacaciones aprobadas.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Solicitud que excede saldo restante (20 días disponibles, pide 25 días) debe fallar
        try {
            $servicio->crearSolicitudVacaciones([
                'trabajador_id' => $this->trabajador->id,
                'periodo_vacacional_id' => $aprobada->periodo_vacacional_id,
                'fecha_inicio' => Carbon::create($anio, 4, 1)->toDateString(),
                'fecha_fin' => Carbon::create($anio, 4, 25)->toDateString(),
            ], $asistente);
            $this->fail('Debía bloquearse solicitud que supera el saldo disponible.');
        } catch (ValidationException) {
            $this->assertTrue(true);
        }

        // Cancelar devuelve saldo
        $servicio->cancelarSolicitudVacaciones($aprobada);
        $this->assertSame(0, $aprobada->periodo->fresh()->dias_tomados);

        // Solicitud cuya fecha_fin ya pasó transiciona automáticamente a TOMADA al aprobar o sincronizar
        $pasada = $servicio->crearSolicitudVacaciones([
            'trabajador_id' => $this->trabajador->id,
            'fecha_inicio' => Carbon::today()->subDays(10)->toDateString(),
            'fecha_fin' => Carbon::today()->subDays(8)->toDateString(),
        ], $asistente);
        $resPasada = $servicio->resolverSolicitudVacaciones($pasada, true, $jefe);
        $this->assertSame(EstadoSolicitudRrhh::TOMADA, $resPasada->estado);
    }
}
