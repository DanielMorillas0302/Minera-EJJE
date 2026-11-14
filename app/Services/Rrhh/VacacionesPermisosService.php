<?php

namespace App\Services\Rrhh;

use App\Enums\EstadoSolicitudRrhh;
use App\Enums\EstadoTrabajador;
use App\Models\Rrhh\PeriodoVacacional;
use App\Models\Rrhh\Permiso;
use App\Models\Rrhh\SolicitudVacaciones;
use App\Models\Rrhh\Trabajador;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VacacionesPermisosService
{
    /**
     * Cambia automáticamente a TOMADA las solicitudes/permisos APROBADOS cuya fecha_fin ya pasó.
     */
    public function actualizarEstadosTomados(): int
    {
        $hoy = Carbon::today()->toDateString();

        $vacacionesActualizadas = SolicitudVacaciones::query()
            ->activos()
            ->where('estado', EstadoSolicitudRrhh::APROBADA->value)
            ->whereDate('fecha_fin', '<', $hoy)
            ->update(['estado' => EstadoSolicitudRrhh::TOMADA->value]);

        $permisosActualizados = Permiso::query()
            ->activos()
            ->where('estado', EstadoSolicitudRrhh::APROBADA->value)
            ->whereDate('fecha_fin', '<', $hoy)
            ->update(['estado' => EstadoSolicitudRrhh::TOMADA->value]);

        return $vacacionesActualizadas + $permisosActualizados;
    }

    public function calcularDiasInclusivos(CarbonInterface|string $inicio, CarbonInterface|string $fin): int
    {
        $fechaInicio = $inicio instanceof CarbonInterface ? Carbon::instance($inicio)->startOfDay() : Carbon::parse($inicio)->startOfDay();
        $fechaFin = $fin instanceof CarbonInterface ? Carbon::instance($fin)->startOfDay() : Carbon::parse($fin)->startOfDay();

        if ($fechaFin->lt($fechaInicio)) {
            throw ValidationException::withMessages([
                'fecha_fin' => 'La fecha de fin debe ser igual o posterior a la fecha de inicio.',
            ]);
        }

        return (int) $fechaInicio->diffInDays($fechaFin) + 1;
    }

    public function obtenerOCrearPeriodo(int $trabajadorId, int $anio, int $diasGanadosPorDefecto = 30): PeriodoVacacional
    {
        $periodo = PeriodoVacacional::withTrashed()
            ->where('trabajador_id', $trabajadorId)
            ->where('anio', $anio)
            ->first();

        if ($periodo) {
            if ($periodo->trashed()) {
                $periodo->restore();
            }

            return $periodo;
        }

        return PeriodoVacacional::create([
            'trabajador_id' => $trabajadorId,
            'anio' => $anio,
            'dias_ganados' => $diasGanadosPorDefecto,
            'dias_tomados' => 0,
        ]);
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crearSolicitudVacaciones(array $datos, ?User $usuario = null): SolicitudVacaciones
    {
        return DB::transaction(function () use ($datos, $usuario): SolicitudVacaciones {
            $trabajador = Trabajador::query()->findOrFail((int) $datos['trabajador_id']);
            $this->validarTrabajadorActivo($trabajador);

            $fechaInicio = Carbon::parse((string) $datos['fecha_inicio'])->startOfDay();
            $fechaFin = Carbon::parse((string) $datos['fecha_fin'])->startOfDay();
            $dias = $this->calcularDiasInclusivos($fechaInicio, $fechaFin);

            $periodo = isset($datos['periodo_vacacional_id']) && $datos['periodo_vacacional_id']
                ? PeriodoVacacional::query()->lockForUpdate()->findOrFail((int) $datos['periodo_vacacional_id'])
                : $this->obtenerOCrearPeriodo($trabajador->id, $fechaInicio->year);

            if ($periodo->trabajador_id !== $trabajador->id) {
                throw ValidationException::withMessages([
                    'periodo_vacacional_id' => 'El período vacacional seleccionado no pertenece al trabajador.',
                ]);
            }

            $periodo->recalcularDiasTomados();
            if ($dias > $periodo->saldoDisponible()) {
                throw ValidationException::withMessages([
                    'fecha_fin' => "Los {$dias} días solicitados superan el saldo disponible ({$periodo->saldoDisponible()} día(s)) del período {$periodo->anio}.",
                ]);
            }

            $this->asegurarSinSolapamiento($trabajador->id, $fechaInicio, $fechaFin);

            return SolicitudVacaciones::create([
                'trabajador_id' => $trabajador->id,
                'periodo_vacacional_id' => $periodo->id,
                'fecha_inicio' => $fechaInicio->toDateString(),
                'fecha_fin' => $fechaFin->toDateString(),
                'dias' => $dias,
                'estado' => EstadoSolicitudRrhh::PENDIENTE,
                'solicitado_por' => $usuario?->id,
                'comentario' => ($datos['comentario'] ?? null) ?: null,
            ]);
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function resolverSolicitudVacaciones(
        SolicitudVacaciones $solicitud,
        bool $aprobar,
        User $aprobador,
        ?string $comentario = null
    ): SolicitudVacaciones {
        $this->validarAprobador($aprobador, $solicitud->solicitado_por);

        return DB::transaction(function () use ($solicitud, $aprobar, $aprobador, $comentario): SolicitudVacaciones {
            /** @var SolicitudVacaciones $bloqueada */
            $bloqueada = SolicitudVacaciones::query()->lockForUpdate()->findOrFail($solicitud->id);

            if ($bloqueada->estado !== EstadoSolicitudRrhh::PENDIENTE) {
                throw ValidationException::withMessages([
                    'estado' => "Solo pueden resolverse solicitudes pendientes (estado actual: {$bloqueada->estado->etiqueta()}).",
                ]);
            }

            /** @var PeriodoVacacional $periodo */
            $periodo = PeriodoVacacional::query()->lockForUpdate()->findOrFail($bloqueada->periodo_vacacional_id);

            if ($aprobar) {
                $this->asegurarSinSolapamiento(
                    $bloqueada->trabajador_id,
                    $bloqueada->fecha_inicio,
                    $bloqueada->fecha_fin,
                    excluirVacacionId: $bloqueada->id
                );

                $periodo->recalcularDiasTomados();
                if ($bloqueada->dias > $periodo->saldoDisponible()) {
                    throw ValidationException::withMessages([
                        'estado' => "Saldo insuficiente en el período {$periodo->anio} (disponible: {$periodo->saldoDisponible()} día(s), solicitado: {$bloqueada->dias}).",
                    ]);
                }

                $estadoFinal = $bloqueada->fecha_fin->startOfDay()->lt(Carbon::today())
                    ? EstadoSolicitudRrhh::TOMADA
                    : EstadoSolicitudRrhh::APROBADA;

                $bloqueada->update([
                    'estado' => $estadoFinal,
                    'aprobado_por' => $aprobador->id,
                    'fecha_resolucion' => now(),
                    'comentario' => $comentario ?: $bloqueada->comentario,
                ]);

                $periodo->recalcularDiasTomados();
            } else {
                $bloqueada->update([
                    'estado' => EstadoSolicitudRrhh::RECHAZADA,
                    'aprobado_por' => $aprobador->id,
                    'fecha_resolucion' => now(),
                    'comentario' => $comentario ?: $bloqueada->comentario,
                ]);
            }

            return $bloqueada->fresh(['trabajador', 'periodo', 'solicitadoPor', 'aprobadoPor']);
        });
    }

    public function cancelarSolicitudVacaciones(SolicitudVacaciones $solicitud): SolicitudVacaciones
    {
        return DB::transaction(function () use ($solicitud): SolicitudVacaciones {
            /** @var SolicitudVacaciones $bloqueada */
            $bloqueada = SolicitudVacaciones::query()->lockForUpdate()->findOrFail($solicitud->id);

            if (! in_array($bloqueada->estado, [EstadoSolicitudRrhh::PENDIENTE, EstadoSolicitudRrhh::APROBADA], true)) {
                throw ValidationException::withMessages([
                    'estado' => "No es posible cancelar una solicitud en estado {$bloqueada->estado->etiqueta()}.",
                ]);
            }

            $bloqueada->update(['estado' => EstadoSolicitudRrhh::CANCELADA]);

            /** @var ?PeriodoVacacional $periodo */
            $periodo = PeriodoVacacional::query()->lockForUpdate()->find($bloqueada->periodo_vacacional_id);
            $periodo?->recalcularDiasTomados();

            return $bloqueada;
        });
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    public function crearPermiso(array $datos, ?User $usuario = null): Permiso
    {
        return DB::transaction(function () use ($datos, $usuario): Permiso {
            $trabajador = Trabajador::query()->findOrFail((int) $datos['trabajador_id']);
            $this->validarTrabajadorActivo($trabajador);

            $fechaInicio = Carbon::parse((string) $datos['fecha_inicio'])->startOfDay();
            $fechaFin = Carbon::parse((string) $datos['fecha_fin'])->startOfDay();
            $this->calcularDiasInclusivos($fechaInicio, $fechaFin);

            $this->asegurarSinSolapamiento($trabajador->id, $fechaInicio, $fechaFin);

            return Permiso::create([
                'trabajador_id' => $trabajador->id,
                'tipo' => $datos['tipo'],
                'con_goce' => (bool) ($datos['con_goce'] ?? true),
                'fecha_inicio' => $fechaInicio->toDateString(),
                'fecha_fin' => $fechaFin->toDateString(),
                'motivo' => trim((string) $datos['motivo']),
                'estado' => EstadoSolicitudRrhh::PENDIENTE,
                'solicitado_por' => $usuario?->id,
            ]);
        });
    }

    /**
     * @throws AuthorizationException
     */
    public function resolverPermiso(
        Permiso $permiso,
        bool $aprobar,
        User $aprobador,
        ?string $comentario = null
    ): Permiso {
        $this->validarAprobador($aprobador, $permiso->solicitado_por);

        return DB::transaction(function () use ($permiso, $aprobar, $aprobador, $comentario): Permiso {
            /** @var Permiso $bloqueado */
            $bloqueado = Permiso::query()->lockForUpdate()->findOrFail($permiso->id);

            if ($bloqueado->estado !== EstadoSolicitudRrhh::PENDIENTE) {
                throw ValidationException::withMessages([
                    'estado' => "Solo pueden resolverse permisos pendientes (estado actual: {$bloqueado->estado->etiqueta()}).",
                ]);
            }

            if ($aprobar) {
                $this->asegurarSinSolapamiento(
                    $bloqueado->trabajador_id,
                    $bloqueado->fecha_inicio,
                    $bloqueado->fecha_fin,
                    excluirPermisoId: $bloqueado->id
                );

                $estadoFinal = $bloqueado->fecha_fin->startOfDay()->lt(Carbon::today())
                    ? EstadoSolicitudRrhh::TOMADA
                    : EstadoSolicitudRrhh::APROBADA;

                $bloqueado->update([
                    'estado' => $estadoFinal,
                    'aprobado_por' => $aprobador->id,
                    'fecha_resolucion' => now(),
                    'comentario' => $comentario ?: $bloqueado->comentario,
                ]);
            } else {
                $bloqueado->update([
                    'estado' => EstadoSolicitudRrhh::RECHAZADA,
                    'aprobado_por' => $aprobador->id,
                    'fecha_resolucion' => now(),
                    'comentario' => $comentario ?: $bloqueado->comentario,
                ]);
            }

            return $bloqueado->fresh(['trabajador', 'solicitadoPor', 'aprobadoPor']);
        });
    }

    public function cancelarPermiso(Permiso $permiso): Permiso
    {
        return DB::transaction(function () use ($permiso): Permiso {
            /** @var Permiso $bloqueado */
            $bloqueado = Permiso::query()->lockForUpdate()->findOrFail($permiso->id);

            if (! in_array($bloqueado->estado, [EstadoSolicitudRrhh::PENDIENTE, EstadoSolicitudRrhh::APROBADA], true)) {
                throw ValidationException::withMessages([
                    'estado' => "No es posible cancelar un permiso en estado {$bloqueado->estado->etiqueta()}.",
                ]);
            }

            $bloqueado->update(['estado' => EstadoSolicitudRrhh::CANCELADA]);

            return $bloqueado;
        });
    }

    public function asegurarSinSolapamiento(
        int $trabajadorId,
        CarbonInterface $inicio,
        CarbonInterface $fin,
        ?int $excluirVacacionId = null,
        ?int $excluirPermisoId = null
    ): void {
        $estadosOcupados = [
            EstadoSolicitudRrhh::APROBADA->value,
            EstadoSolicitudRrhh::TOMADA->value,
        ];

        $solapaVacacion = SolicitudVacaciones::query()
            ->activos()
            ->where('trabajador_id', $trabajadorId)
            ->whereIn('estado', $estadosOcupados)
            ->when($excluirVacacionId, fn ($q) => $q->where('id', '!=', $excluirVacacionId))
            ->whereDate('fecha_inicio', '<=', $fin->toDateString())
            ->whereDate('fecha_fin', '>=', $inicio->toDateString())
            ->exists();

        if ($solapaVacacion) {
            throw ValidationException::withMessages([
                'fecha_inicio' => 'El trabajador ya tiene vacaciones aprobadas o tomadas que se solapan con ese rango de fechas.',
            ]);
        }

        $solapaPermiso = Permiso::query()
            ->activos()
            ->where('trabajador_id', $trabajadorId)
            ->whereIn('estado', $estadosOcupados)
            ->when($excluirPermisoId, fn ($q) => $q->where('id', '!=', $excluirPermisoId))
            ->whereDate('fecha_inicio', '<=', $fin->toDateString())
            ->whereDate('fecha_fin', '>=', $inicio->toDateString())
            ->exists();

        if ($solapaPermiso) {
            throw ValidationException::withMessages([
                'fecha_inicio' => 'El trabajador ya tiene un permiso aprobado o tomado que se solapa con ese rango de fechas.',
            ]);
        }
    }

    private function validarTrabajadorActivo(Trabajador $trabajador): void
    {
        if (! $trabajador->activo || $trabajador->estado !== EstadoTrabajador::ACTIVO) {
            throw ValidationException::withMessages([
                'trabajador_id' => "El trabajador se encuentra en estado {$trabajador->estado->etiqueta()}.",
            ]);
        }
    }

    /**
     * @throws AuthorizationException
     */
    private function validarAprobador(User $aprobador, ?int $solicitadoPorId): void
    {
        if (! $aprobador->puede('vacaciones_permisos', 'A')) {
            throw new AuthorizationException('Tu rol no tiene permiso para aprobar o rechazar solicitudes.');
        }

        if ($solicitadoPorId !== null && $solicitadoPorId === $aprobador->id) {
            throw new AuthorizationException('Un usuario no puede aprobar ni rechazar sus propias solicitudes.');
        }
    }
}
