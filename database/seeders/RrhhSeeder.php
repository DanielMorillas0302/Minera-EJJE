<?php

namespace Database\Seeders;

use App\Enums\EstadoSolicitudRrhh;
use App\Enums\EstadoTrabajador;
use App\Enums\ResultadoExamen;
use App\Enums\SexoTrabajador;
use App\Enums\TipoExamenOcupacional;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\TipoPermiso;
use App\Enums\UbicacionTrabajador;
use App\Models\Core\Area;
use App\Models\Core\Cargo;
use App\Models\Core\Sede;
use App\Models\Rrhh\ExamenOcupacional;
use App\Models\Rrhh\MovimientoPersonal;
use App\Models\Rrhh\PeriodoVacacional;
use App\Models\Rrhh\Permiso;
use App\Models\Rrhh\SolicitudVacaciones;
use App\Models\Rrhh\Trabajador;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class RrhhSeeder extends Seeder
{
    /**
     * Carga idempotente (~30 trabajadores con exámenes vigentes/por vencer/vencidos,
     * períodos vacacionales, solicitudes, permisos y movimientos) solo en modo DEBUG.
     */
    public function run(): void
    {
        if (! config('app.debug')) {
            return;
        }

        $trujillo = Sede::where('nombre', 'Trujillo')->first();
        $pataz = Sede::where('nombre', 'Pataz')->first();

        if (! $trujillo || ! $pataz) {
            return;
        }

        $areas = Area::pluck('id', 'nombre');
        $cargos = Cargo::pluck('id', 'nombre');
        $jefeRrhh = User::where('email', 'rrhh.jefe@mineria.pe')->first();
        $asistenteRrhh = User::where('email', 'rrhh.asistente@mineria.pe')->first();

        $nomina = $this->definirNominaDemo();
        $hoy = Carbon::today();
        $anioActual = $hoy->year;

        $trabajadoresPorDni = Trabajador::query()->get()->keyBy('dni');
        $trabajadoresConExamen = ExamenOcupacional::query()->pluck('trabajador_id')->flip();
        $trabajadoresConPeriodo = PeriodoVacacional::query()->where('anio', $anioActual)->pluck('trabajador_id')->flip();

        $creados = [];

        foreach ($nomina as $idx => $item) {
            $sedeBaseId = $item['sede'] === 'Pataz' ? $pataz->id : $trujillo->id;
            $areaId = $areas->get($item['area']) ?? $areas->first();
            $cargoId = $cargos->get($item['cargo']) ?? $cargos->first();

            $trabajador = $trabajadoresPorDni->get($item['dni']);
            $necesitaExamen = ! $trabajador || (! $trabajadoresConExamen->has($trabajador->id) && $item['examen'] !== 'SIN_EXAMEN');
            $necesitaPeriodo = ! $trabajador || ! $trabajadoresConPeriodo->has($trabajador->id);

            if (! $trabajador || $necesitaExamen || $necesitaPeriodo) {
                $trabajador = DB::transaction(function () use (
                    $trabajador,
                    $item,
                    $idx,
                    $sedeBaseId,
                    $areaId,
                    $cargoId,
                    $hoy,
                    $anioActual,
                    $necesitaExamen,
                    $necesitaPeriodo
                ): Trabajador {
                    $modelo = $trabajador ?? Trabajador::firstOrCreate(
                        ['dni' => $item['dni']],
                        [
                            'nombres' => $item['nombres'],
                            'apellidos' => $item['apellidos'],
                            'fecha_nacimiento' => Carbon::create(1980 + ($idx % 18), ($idx % 12) + 1, ($idx % 25) + 1)->toDateString(),
                            'sexo' => $item['sexo'],
                            'telefono' => '949'.str_pad((string) (100000 + $idx * 137), 6, '0', STR_PAD_LEFT),
                            'email' => strtolower(str_replace(' ', '.', $item['nombres']))."{$idx}@mineria.pe",
                            'direccion' => $item['sede'] === 'Pataz' ? 'Campamento Anexo Vijus - Pataz' : 'Urb. Primavera Mz. '.chr(65 + ($idx % 8)).' - Trujillo',
                            'contacto_emergencia_nombre' => 'Familiar de '.$item['apellidos'],
                            'contacto_emergencia_telefono' => '948'.str_pad((string) (200000 + $idx * 211), 6, '0', STR_PAD_LEFT),
                            'cargo_id' => $cargoId,
                            'area_id' => $areaId,
                            'sede_base_id' => $sedeBaseId,
                            'fecha_ingreso' => $hoy->copy()->subMonths(6 + ($idx % 36))->toDateString(),
                            'fecha_cese' => $item['estado'] === EstadoTrabajador::CESADO ? $hoy->copy()->subDays(20)->toDateString() : null,
                            'estado' => $item['estado'],
                            'ubicacion_actual' => $item['ubicacion'],
                            'esquema_rotacion' => $item['rotacion'],
                        ]
                    );

                    if ($necesitaExamen && $item['examen'] !== 'SIN_EXAMEN') {
                        [$resultado, $diasVenc] = match ($item['examen']) {
                            'POR_VENCER_10' => [ResultadoExamen::APTO, 10],
                            'POR_VENCER_25' => [ResultadoExamen::APTO, 25],
                            'VENCIDO' => [ResultadoExamen::APTO, -15],
                            'RESTRICCIONES' => [ResultadoExamen::APTO_CON_RESTRICCIONES, 120],
                            'NO_APTO' => [ResultadoExamen::NO_APTO, 90],
                            default => [ResultadoExamen::APTO, 180 + ($idx * 3)],
                        };

                        ExamenOcupacional::create([
                            'trabajador_id' => $modelo->id,
                            'tipo' => $idx % 3 === 0 ? TipoExamenOcupacional::INGRESO : TipoExamenOcupacional::PERIODICO,
                            'fecha_examen' => $hoy->copy()->addDays($diasVenc)->subYear()->toDateString(),
                            'fecha_vencimiento' => $hoy->copy()->addDays($diasVenc)->toDateString(),
                            'clinica' => $idx % 2 === 0 ? 'Clínica San Pablo Trujillo' : 'Policlínico Ocupacional del Norte',
                            'resultado' => $resultado,
                            'observaciones' => $resultado === ResultadoExamen::APTO
                                ? 'Evaluación médica ocupacional aprobada sin observaciones.'
                                : 'Requiere seguimiento médico ocupacional antes de ascenso a mina.',
                        ]);
                    }

                    if ($necesitaPeriodo) {
                        PeriodoVacacional::firstOrCreate(
                            ['trabajador_id' => $modelo->id, 'anio' => $anioActual],
                            ['dias_ganados' => 30, 'dias_tomados' => 0]
                        );
                    }

                    return $modelo;
                });
            }

            $creados[] = ['modelo' => $trabajador, 'examen' => $item['examen']];
        }

        // Movimientos iniciales de referencia (idempotente)
        if (MovimientoPersonal::count() === 0 && count($creados) >= 6) {
            foreach (array_slice($creados, 0, 5) as $item) {
                /** @var Trabajador $t */
                $t = $item['modelo'];
                if ($t->ubicacion_actual === UbicacionTrabajador::PATAZ) {
                    MovimientoPersonal::create([
                        'trabajador_id' => $t->id,
                        'tipo' => TipoMovimientoPersonal::SALIDA_A_PATAZ,
                        'fecha_hora' => $hoy->copy()->subDays(5)->setTime(6, 30),
                        'origen_sede_id' => $trujillo->id,
                        'destino_sede_id' => $pataz->id,
                        'transporte' => 'Bus Corporativo Trujillo-Pataz #01',
                        'observaciones' => 'Ingreso regular de guardia.',
                        'registrado_por' => $jefeRrhh?->id,
                    ]);
                }
            }
        }

        // Vacaciones y permisos de muestra (idempotente)
        if (SolicitudVacaciones::count() === 0 && count($creados) >= 4) {
            $t1 = $creados[0]['modelo'];
            $p1 = PeriodoVacacional::where('trabajador_id', $t1->id)->where('anio', $anioActual)->first();
            if ($p1) {
                SolicitudVacaciones::create([
                    'trabajador_id' => $t1->id,
                    'periodo_vacacional_id' => $p1->id,
                    'fecha_inicio' => $hoy->copy()->addDays(15)->toDateString(),
                    'fecha_fin' => $hoy->copy()->addDays(21)->toDateString(),
                    'dias' => 7,
                    'estado' => EstadoSolicitudRrhh::APROBADA,
                    'solicitado_por' => $asistenteRrhh?->id,
                    'aprobado_por' => $jefeRrhh?->id,
                    'fecha_resolucion' => now(),
                    'comentario' => 'Vacaciones programadas al cierre de guardia.',
                ]);
                $p1->recalcularDiasTomados();
            }

            $t2 = $creados[1]['modelo'];
            $p2 = PeriodoVacacional::where('trabajador_id', $t2->id)->where('anio', $anioActual)->first();
            if ($p2) {
                SolicitudVacaciones::create([
                    'trabajador_id' => $t2->id,
                    'periodo_vacacional_id' => $p2->id,
                    'fecha_inicio' => $hoy->copy()->addDays(25)->toDateString(),
                    'fecha_fin' => $hoy->copy()->addDays(29)->toDateString(),
                    'dias' => 5,
                    'estado' => EstadoSolicitudRrhh::PENDIENTE,
                    'solicitado_por' => $asistenteRrhh?->id,
                    'comentario' => 'Solicitud pendiente de visto bueno.',
                ]);
            }
        }

        if (Permiso::count() === 0 && count($creados) >= 3) {
            $t3 = $creados[2]['modelo'];
            Permiso::create([
                'trabajador_id' => $t3->id,
                'tipo' => TipoPermiso::CAPACITACION,
                'con_goce' => true,
                'fecha_inicio' => $hoy->copy()->addDays(4)->toDateString(),
                'fecha_fin' => $hoy->copy()->addDays(5)->toDateString(),
                'motivo' => 'Curso de actualización en seguridad minera IPERC.',
                'estado' => EstadoSolicitudRrhh::PENDIENTE,
                'solicitado_por' => $asistenteRrhh?->id,
            ]);
        }
    }

    /**
     * 30 trabajadores ficticios coherentes con sedes, áreas, cargos y casos de examen médico.
     *
     * @return list<array<string, mixed>>
     */
    private function definirNominaDemo(): array
    {
        return [
            ['dni' => '41200101', 'nombres' => 'Juan Carlos', 'apellidos' => 'Mendoza Paredes', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Pataz', 'area' => 'Operaciones', 'cargo' => 'Operario de mina', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::PATAZ, 'rotacion' => '21x7', 'examen' => 'VIGENTE'],
            ['dni' => '41200102', 'nombres' => 'Segundo Luis', 'apellidos' => 'Vásquez Quispe', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Pataz', 'area' => 'Operaciones', 'cargo' => 'Supervisor de seguridad', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::PATAZ, 'rotacion' => '14x7', 'examen' => 'VIGENTE'],
            ['dni' => '41200103', 'nombres' => 'María Elena', 'apellidos' => 'Rojas Cabrera', 'sexo' => SexoTrabajador::FEMENINO, 'sede' => 'Trujillo', 'area' => 'RR.HH.', 'cargo' => 'Jefe de RR.HH.', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::TRUJILLO, 'rotacion' => 'L-V', 'examen' => 'VIGENTE'],
            ['dni' => '41200104', 'nombres' => 'Pedro Antonio', 'apellidos' => 'Huamán Flores', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Pataz', 'area' => 'Operaciones', 'cargo' => 'Operario de mina', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::TRUJILLO, 'rotacion' => '21x7', 'examen' => 'POR_VENCER_10'],
            ['dni' => '41200105', 'nombres' => 'César Augusto', 'apellidos' => 'Salazar Vigo', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Pataz', 'area' => 'Operaciones', 'cargo' => 'Operario de mina', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::TRUJILLO, 'rotacion' => '21x7', 'examen' => 'VENCIDO'],
            ['dni' => '41200106', 'nombres' => 'Rosa Maribel', 'apellidos' => 'Chávez León', 'sexo' => SexoTrabajador::FEMENINO, 'sede' => 'Trujillo', 'area' => 'Finanzas', 'cargo' => 'Contador', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::TRUJILLO, 'rotacion' => 'L-V', 'examen' => 'VIGENTE'],
            ['dni' => '41200107', 'nombres' => 'Miguel Ángel', 'apellidos' => 'Cerna Loyola', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Trujillo', 'area' => 'Logística', 'cargo' => 'Jefe de Logística', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::TRUJILLO, 'rotacion' => 'L-V', 'examen' => 'VIGENTE'],
            ['dni' => '41200108', 'nombres' => 'José Manuel', 'apellidos' => 'Rodríguez Díaz', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Pataz', 'area' => 'Operaciones', 'cargo' => 'Almacenero', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::PATAZ, 'rotacion' => '14x7', 'examen' => 'POR_VENCER_25'],
            ['dni' => '41200109', 'nombres' => 'Víctor Raúl', 'apellidos' => 'Pérez Gálvez', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Pataz', 'area' => 'Operaciones', 'cargo' => 'Operario de mina', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::TRUJILLO, 'rotacion' => '21x7', 'examen' => 'RESTRICCIONES'],
            ['dni' => '41200110', 'nombres' => 'Daniel Eduardo', 'apellidos' => 'Zavaleta Cruz', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Pataz', 'area' => 'Operaciones', 'cargo' => 'Operario de mina', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::TRUJILLO, 'rotacion' => '21x7', 'examen' => 'NO_APTO'],
            ['dni' => '41200111', 'nombres' => 'Ana Lucía', 'apellidos' => 'Castillo Vera', 'sexo' => SexoTrabajador::FEMENINO, 'sede' => 'Trujillo', 'area' => 'RR.HH.', 'cargo' => 'Asistente de RR.HH.', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::TRUJILLO, 'rotacion' => 'L-V', 'examen' => 'VIGENTE'],
            ['dni' => '41200112', 'nombres' => 'Jorge Luis', 'apellidos' => 'Alvarado Soto', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Trujillo', 'area' => 'Gerencia', 'cargo' => 'Gerente General', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::TRUJILLO, 'rotacion' => 'L-V', 'examen' => 'VIGENTE'],
            ['dni' => '41200113', 'nombres' => 'Wilmer Iván', 'apellidos' => 'Campos Neyra', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Pataz', 'area' => 'Operaciones', 'cargo' => 'Operario de mina', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::PATAZ, 'rotacion' => '21x7', 'examen' => 'VIGENTE'],
            ['dni' => '41200114', 'nombres' => 'Elmer Darío', 'apellidos' => 'Sánchez Rubio', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Pataz', 'area' => 'Operaciones', 'cargo' => 'Supervisor de seguridad', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::PATAZ, 'rotacion' => '14x7', 'examen' => 'VIGENTE'],
            ['dni' => '41200115', 'nombres' => 'Héctor Alonso', 'apellidos' => 'Valderrama Cruz', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Pataz', 'area' => 'Operaciones', 'cargo' => 'Operario de mina', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::TRUJILLO, 'rotacion' => '21x7', 'examen' => 'SIN_EXAMEN'],
            ['dni' => '41200116', 'nombres' => 'Marco Antonio', 'apellidos' => 'Ruiz Blas', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Trujillo', 'area' => 'Logística', 'cargo' => 'Almacenero', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::TRUJILLO, 'rotacion' => 'L-S', 'examen' => 'VIGENTE'],
            ['dni' => '41200117', 'nombres' => 'Nélida Pilar', 'apellidos' => 'Gutiérrez Reyes', 'sexo' => SexoTrabajador::FEMENINO, 'sede' => 'Trujillo', 'area' => 'Finanzas', 'cargo' => 'Contador', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::TRUJILLO, 'rotacion' => 'L-V', 'examen' => 'POR_VENCER_25'],
            ['dni' => '41200118', 'nombres' => 'Santos Gabriel', 'apellidos' => 'Polo Méndez', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Pataz', 'area' => 'Operaciones', 'cargo' => 'Operario de mina', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::PATAZ, 'rotacion' => '21x7', 'examen' => 'VIGENTE'],
            ['dni' => '41200119', 'nombres' => 'Edwin Omar', 'apellidos' => 'Tello Marín', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Pataz', 'area' => 'Operaciones', 'cargo' => 'Operario de mina', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::PATAZ, 'rotacion' => '21x7', 'examen' => 'VIGENTE'],
            ['dni' => '41200120', 'nombres' => 'Fredy Ronald', 'apellidos' => 'Acosta Silva', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Pataz', 'area' => 'Operaciones', 'cargo' => 'Operario de mina', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::TRUJILLO, 'rotacion' => '21x7', 'examen' => 'VENCIDO'],
            ['dni' => '41200121', 'nombres' => 'Wilder Martín', 'apellidos' => 'Vargas López', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Pataz', 'area' => 'Operaciones', 'cargo' => 'Supervisor de seguridad', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::TRUJILLO, 'rotacion' => '14x7', 'examen' => 'POR_VENCER_10'],
            ['dni' => '41200122', 'nombres' => 'Óscar Enrique', 'apellidos' => 'Luján Paredes', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Pataz', 'area' => 'Operaciones', 'cargo' => 'Operario de mina', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::PATAZ, 'rotacion' => '21x7', 'examen' => 'VIGENTE'],
            ['dni' => '41200123', 'nombres' => 'Lidia Beatriz', 'apellidos' => 'Ávila Moreno', 'sexo' => SexoTrabajador::FEMENINO, 'sede' => 'Trujillo', 'area' => 'RR.HH.', 'cargo' => 'Asistente de RR.HH.', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::TRUJILLO, 'rotacion' => 'L-V', 'examen' => 'VIGENTE'],
            ['dni' => '41200124', 'nombres' => 'Raúl Fernando', 'apellidos' => 'Pinedo Morales', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Pataz', 'area' => 'Operaciones', 'cargo' => 'Operario de mina', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::EN_TRANSITO, 'rotacion' => '21x7', 'examen' => 'VIGENTE'],
            ['dni' => '41200125', 'nombres' => 'Katia Vanessa', 'apellidos' => 'Ramos Aguirre', 'sexo' => SexoTrabajador::FEMENINO, 'sede' => 'Trujillo', 'area' => 'Logística', 'cargo' => 'Almacenero', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::TRUJILLO, 'rotacion' => 'L-S', 'examen' => 'VIGENTE'],
            ['dni' => '41200126', 'nombres' => 'Néstor Arturo', 'apellidos' => 'Gamboa Siccha', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Pataz', 'area' => 'Operaciones', 'cargo' => 'Operario de mina', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::PATAZ, 'rotacion' => '21x7', 'examen' => 'VIGENTE'],
            ['dni' => '41200127', 'nombres' => 'Alexánder Joel', 'apellidos' => 'Carranza Ortiz', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Pataz', 'area' => 'Operaciones', 'cargo' => 'Operario de mina', 'estado' => EstadoTrabajador::SUSPENDIDO, 'ubicacion' => UbicacionTrabajador::TRUJILLO, 'rotacion' => '21x7', 'examen' => 'VIGENTE'],
            ['dni' => '41200128', 'nombres' => 'Dante Guillermo', 'apellidos' => 'Becerra Núñez', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Pataz', 'area' => 'Operaciones', 'cargo' => 'Operario de mina', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::PATAZ, 'rotacion' => '21x7', 'examen' => 'VIGENTE'],
            ['dni' => '41200129', 'nombres' => 'Cristian Paul', 'apellidos' => 'Herrera Aguilar', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Pataz', 'area' => 'Operaciones', 'cargo' => 'Supervisor de seguridad', 'estado' => EstadoTrabajador::ACTIVO, 'ubicacion' => UbicacionTrabajador::TRUJILLO, 'rotacion' => '14x7', 'examen' => 'VIGENTE'],
            ['dni' => '41200130', 'nombres' => 'Eusebio Martín', 'apellidos' => 'Quezada Mantilla', 'sexo' => SexoTrabajador::MASCULINO, 'sede' => 'Pataz', 'area' => 'Operaciones', 'cargo' => 'Operario de mina', 'estado' => EstadoTrabajador::CESADO, 'ubicacion' => UbicacionTrabajador::TRUJILLO, 'rotacion' => '21x7', 'examen' => 'VENCIDO'],
        ];
    }
}
