<?php

namespace App\Services\Rrhh;

use App\Enums\EstadoTrabajador;
use App\Enums\TipoMovimientoPersonal;
use App\Enums\UbicacionTrabajador;
use App\Models\Core\Sede;
use App\Models\Rrhh\MovimientoPersonal;
use App\Models\Rrhh\Trabajador;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MovimientoPersonalService
{
    /**
     * Registra un movimiento Trujillo <-> Pataz validando aptitud médica obligatoria en SALIDA_A_PATAZ.
     *
     * @param  array<string, mixed>  $datos
     */
    public function registrar(array $datos, ?User $usuario = null): MovimientoPersonal
    {
        return DB::transaction(function () use ($datos, $usuario): MovimientoPersonal {
            /** @var Trabajador $trabajador */
            $trabajador = Trabajador::query()
                ->with('examenes')
                ->lockForUpdate()
                ->findOrFail((int) $datos['trabajador_id']);

            if (! $trabajador->activo || $trabajador->estado !== EstadoTrabajador::ACTIVO) {
                throw ValidationException::withMessages([
                    'trabajador_id' => "No se pueden registrar movimientos para un trabajador en estado {$trabajador->estado->etiqueta()}.",
                ]);
            }

            $origen = Sede::query()->findOrFail((int) $datos['origen_sede_id']);
            $destino = Sede::query()->findOrFail((int) $datos['destino_sede_id']);

            if ($origen->id === $destino->id) {
                throw ValidationException::withMessages([
                    'destino_sede_id' => 'La sede de destino debe ser distinta a la sede de origen.',
                ]);
            }

            $tipo = $datos['tipo'] instanceof TipoMovimientoPersonal
                ? $datos['tipo']
                : TipoMovimientoPersonal::from((string) $datos['tipo']);

            if ($tipo === TipoMovimientoPersonal::SALIDA_A_PATAZ) {
                $aptitud = $trabajador->detalleAptitudViaje();
                if (! $aptitud['apto']) {
                    throw ValidationException::withMessages([
                        'trabajador_id' => 'Salida a Pataz bloqueada: '.$aptitud['motivo'],
                    ]);
                }
            }

            $movimiento = MovimientoPersonal::create([
                'trabajador_id' => $trabajador->id,
                'tipo' => $tipo,
                'fecha_hora' => $datos['fecha_hora'],
                'origen_sede_id' => $origen->id,
                'destino_sede_id' => $destino->id,
                'transporte' => ($datos['transporte'] ?? null) ?: null,
                'observaciones' => ($datos['observaciones'] ?? null) ?: null,
                'registrado_por' => $usuario?->id,
            ]);

            $nuevaUbicacion = $this->resolverUbicacionResultante($tipo, $destino, $datos['ubicacion_resultante'] ?? null);
            $trabajador->update(['ubicacion_actual' => $nuevaUbicacion]);

            return $movimiento;
        });
    }

    private function resolverUbicacionResultante(
        TipoMovimientoPersonal $tipo,
        Sede $destino,
        ?string $ubicacionExplicita
    ): UbicacionTrabajador {
        if ($tipo === TipoMovimientoPersonal::SALIDA_A_PATAZ) {
            return UbicacionTrabajador::PATAZ;
        }

        if ($tipo === TipoMovimientoPersonal::RETORNO_A_TRUJILLO) {
            return UbicacionTrabajador::TRUJILLO;
        }

        if ($ubicacionExplicita && in_array($ubicacionExplicita, UbicacionTrabajador::valores(), true)) {
            return UbicacionTrabajador::from($ubicacionExplicita);
        }

        if (stripos($destino->nombre, 'pataz') !== false) {
            return UbicacionTrabajador::PATAZ;
        }

        if (stripos($destino->nombre, 'trujillo') !== false) {
            return UbicacionTrabajador::TRUJILLO;
        }

        return UbicacionTrabajador::EN_TRANSITO;
    }
}
