<?php

namespace App\Services\Rrhh;

use App\Enums\EstadoTrabajador;
use App\Enums\ResultadoExamen;
use App\Models\Rrhh\Trabajador;
use Illuminate\Support\Collection;

class AptitudViajeService
{
    /**
     * Evalúa el estado de aptitud para viajar a Pataz de un trabajador.
     *
     * @return array{apto: bool, codigo: string, motivo: string, ultimo_examen: mixed}
     */
    public function evaluar(Trabajador $trabajador): array
    {
        $trabajador->loadMissing('examenes');

        return $trabajador->detalleAptitudViaje();
    }

    /**
     * Calcula resumen de aptitud y alertas médicas para trabajadores activos usando su último examen activo.
     *
     * @return array{
     *     totales: array{activos: int, aptos: int, no_aptos: int, por_vencer: int, vencidos: int, sin_examen: int},
     *     alertas: Collection<int, array<string, mixed>>
     * }
     */
    public function resumenAlertas(int $diasAlerta = 30): array
    {
        $trabajadores = Trabajador::query()
            ->activos()
            ->where('estado', EstadoTrabajador::ACTIVO->value)
            ->with(['cargo:id,nombre', 'area:id,nombre', 'sedeBase:id,nombre', 'examenes'])
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->get();

        $aptos = 0;
        $noAptos = 0;
        $porVencer = 0;
        $vencidos = 0;
        $sinExamen = 0;
        $alertas = collect();

        foreach ($trabajadores as $trabajador) {
            $detalle = $trabajador->detalleAptitudViaje();
            $examen = $detalle['ultimo_examen'];

            if ($detalle['apto']) {
                $aptos++;
                $diasRestantes = $examen->diasParaVencer();

                if ($diasRestantes <= $diasAlerta) {
                    $porVencer++;
                    $alertas->push($this->formatearAlerta($trabajador, 'POR_VENCER', "Vence en {$diasRestantes} día(s)", $diasRestantes, $detalle));
                }

                continue;
            }

            $noAptos++;

            if ($detalle['codigo'] === 'SIN_EXAMEN') {
                $sinExamen++;
                $alertas->push($this->formatearAlerta($trabajador, 'SIN_EXAMEN', $detalle['motivo'], null, $detalle));
            } elseif ($detalle['codigo'] === 'EXAMEN_VENCIDO') {
                $vencidos++;
                $diasRestantes = $examen?->diasParaVencer();
                $alertas->push($this->formatearAlerta($trabajador, 'VENCIDO', $detalle['motivo'], $diasRestantes, $detalle));
            } elseif ($examen && ! $examen->vigente) {
                $vencidos++;
                $diasRestantes = $examen->diasParaVencer();
                $alertas->push($this->formatearAlerta($trabajador, 'NO_APTO', $detalle['motivo'], $diasRestantes, $detalle));
            } else {
                $diasRestantes = $examen?->diasParaVencer();
                $etiquetaTipo = $examen?->resultado === ResultadoExamen::APTO_CON_RESTRICCIONES
                    ? 'CON_RESTRICCIONES'
                    : 'NO_APTO';
                $alertas->push($this->formatearAlerta($trabajador, $etiquetaTipo, $detalle['motivo'], $diasRestantes, $detalle));
            }
        }

        return [
            'totales' => [
                'activos' => $trabajadores->count(),
                'aptos' => $aptos,
                'no_aptos' => $noAptos,
                'por_vencer' => $porVencer,
                'vencidos' => $vencidos,
                'sin_examen' => $sinExamen,
            ],
            'alertas' => $alertas->sortBy(fn (array $a): int => $a['dias_restantes'] ?? -9999)->values(),
        ];
    }

    /**
     * @param  array{apto: bool, codigo: string, motivo: string, ultimo_examen: mixed}  $detalle
     * @return array<string, mixed>
     */
    private function formatearAlerta(
        Trabajador $trabajador,
        string $nivel,
        string $mensaje,
        ?int $diasRestantes,
        array $detalle
    ): array {
        return [
            'trabajador_id' => $trabajador->id,
            'dni' => $trabajador->dni,
            'nombre_completo' => $trabajador->nombreCompleto(),
            'cargo' => $trabajador->cargo?->nombre,
            'area' => $trabajador->area?->nombre,
            'sede_base' => $trabajador->sedeBase?->nombre,
            'ubicacion_actual' => $trabajador->ubicacion_actual?->value,
            'ubicacion_etiqueta' => $trabajador->ubicacion_actual?->etiqueta(),
            'nivel' => $nivel,
            'mensaje' => $mensaje,
            'dias_restantes' => $diasRestantes,
            'fecha_vencimiento' => $detalle['ultimo_examen']?->fecha_vencimiento?->format('d/m/Y'),
        ];
    }
}
