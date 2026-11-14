<?php

namespace App\Services\Logistica;

use App\Enums\EstadoRequerimiento;
use App\Models\Logistica\Requerimiento;
use App\Models\Logistica\RequerimientoDetalle;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class RequerimientoService
{
    public function __construct(
        private readonly CorrelativoService $correlativos,
    ) {}

    /**
     * Crea un requerimiento en estado BORRADOR (o ENVIADO si se solicita) con sus ítems.
     *
     * @param  array<string, mixed>  $datos
     * @param  list<array{material_id: int, cantidad_solicitada: float|int, observacion?: string|null}>  $items
     *
     * @throws ValidationException
     */
    public function crear(array $datos, array $items, User $solicitante, bool $enviarInmediatamente = false): Requerimiento
    {
        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Debe agregar al menos un material al requerimiento.',
            ]);
        }

        $codigo = $this->correlativos->siguienteCodigo('REQ');

        return DB::transaction(function () use ($datos, $items, $solicitante, $enviarInmediatamente, $codigo): Requerimiento {
            /** @var Requerimiento $requerimiento */
            $requerimiento = Requerimiento::query()->create([
                'codigo' => $codigo,
                'fecha_solicitud' => $datos['fecha_solicitud'] ?? now()->toDateString(),
                'area_id' => $datos['area_id'],
                'sede_id' => $datos['sede_id'],
                'solicitante_id' => $solicitante->id,
                'prioridad' => $datos['prioridad'],
                'estado' => $enviarInmediatamente ? EstadoRequerimiento::ENVIADO : EstadoRequerimiento::BORRADOR,
                'observaciones' => $datos['observaciones'] ?? null,
                'activo' => true,
            ]);

            $this->sincronizarDetalles($requerimiento, $items);

            return $requerimiento->load(['detalles.material.unidadMedida', 'area', 'sede', 'solicitante']);
        });
    }

    /**
     * Actualiza un requerimiento mientras esté en BORRADOR.
     *
     * @param  array<string, mixed>  $datos
     * @param  list<array{material_id: int, cantidad_solicitada: float|int, observacion?: string|null}>  $items
     *
     * @throws ValidationException
     */
    public function actualizarBorrador(Requerimiento $requerimiento, array $datos, array $items): Requerimiento
    {
        if ($requerimiento->estado !== EstadoRequerimiento::BORRADOR) {
            throw ValidationException::withMessages([
                'estado' => 'Solo pueden editarse requerimientos en estado BORRADOR.',
            ]);
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Debe mantener al menos un material en el requerimiento.',
            ]);
        }

        return DB::transaction(function () use ($requerimiento, $datos, $items): Requerimiento {
            $requerimiento->update([
                'fecha_solicitud' => $datos['fecha_solicitud'] ?? $requerimiento->fecha_solicitud,
                'area_id' => $datos['area_id'],
                'sede_id' => $datos['sede_id'],
                'prioridad' => $datos['prioridad'],
                'observaciones' => $datos['observaciones'] ?? null,
            ]);

            $this->sincronizarDetalles($requerimiento, $items);

            return $requerimiento->load(['detalles.material.unidadMedida', 'area', 'sede', 'solicitante']);
        });
    }

    /**
     * Envía un borrador para aprobación.
     *
     * @throws ValidationException
     */
    public function enviar(Requerimiento $requerimiento): Requerimiento
    {
        if ($requerimiento->estado !== EstadoRequerimiento::BORRADOR) {
            throw ValidationException::withMessages([
                'estado' => 'Solo los requerimientos en BORRADOR pueden enviarse a aprobación.',
            ]);
        }

        if ($requerimiento->detalles()->count() === 0) {
            throw ValidationException::withMessages([
                'items' => 'El requerimiento no tiene ítems registrados.',
            ]);
        }

        $requerimiento->update(['estado' => EstadoRequerimiento::ENVIADO]);

        return $requerimiento->refresh();
    }

    /**
     * Aprueba un requerimiento enviado. Regla: ningún usuario puede aprobar su propio requerimiento.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function aprobar(Requerimiento $requerimiento, User $aprobador): Requerimiento
    {
        if ((int) $requerimiento->solicitante_id === (int) $aprobador->id) {
            throw new AuthorizationException('Un usuario no puede aprobar sus propios requerimientos.');
        }

        if ($requerimiento->estado !== EstadoRequerimiento::ENVIADO) {
            throw ValidationException::withMessages([
                'estado' => 'Solo pueden aprobarse requerimientos en estado ENVIADO.',
            ]);
        }

        $requerimiento->update([
            'estado' => EstadoRequerimiento::APROBADO,
            'aprobado_por_id' => $aprobador->id,
            'fecha_aprobacion' => now(),
            'motivo_rechazo' => null,
        ]);

        return $requerimiento->refresh();
    }

    /**
     * Rechaza un requerimiento enviado con motivo obligatorio.
     *
     * @throws AuthorizationException
     * @throws ValidationException
     */
    public function rechazar(Requerimiento $requerimiento, User $aprobador, string $motivo): Requerimiento
    {
        if ((int) $requerimiento->solicitante_id === (int) $aprobador->id) {
            throw new AuthorizationException('Un usuario no puede rechazar sus propios requerimientos.');
        }

        if ($requerimiento->estado !== EstadoRequerimiento::ENVIADO) {
            throw ValidationException::withMessages([
                'estado' => 'Solo pueden rechazarse requerimientos en estado ENVIADO.',
            ]);
        }

        if (trim($motivo) === '') {
            throw ValidationException::withMessages([
                'motivo_rechazo' => 'Debe indicar el motivo del rechazo.',
            ]);
        }

        $requerimiento->update([
            'estado' => EstadoRequerimiento::RECHAZADO,
            'aprobado_por_id' => $aprobador->id,
            'fecha_aprobacion' => now(),
            'motivo_rechazo' => trim($motivo),
        ]);

        return $requerimiento->refresh();
    }

    /**
     * Recalcula el estado de atención según las cantidades atendidas en sus detalles.
     */
    public function recalcularEstadoAtencion(Requerimiento $requerimiento): Requerimiento
    {
        $detalles = $requerimiento->detalles()->get();

        if ($detalles->isEmpty()) {
            return $requerimiento;
        }

        $todosCompletos = true;
        $algunoAtendido = false;

        foreach ($detalles as $detalle) {
            $solicitada = (float) $detalle->cantidad_solicitada;
            $atendida = (float) $detalle->cantidad_atendida;

            if ($atendida > 0.0005) {
                $algunoAtendido = true;
            }

            if ($atendida + 0.0005 < $solicitada) {
                $todosCompletos = false;
            }
        }

        $nuevoEstado = match (true) {
            $todosCompletos => EstadoRequerimiento::ATENDIDO,
            $algunoAtendido => EstadoRequerimiento::ATENDIDO_PARCIAL,
            default => EstadoRequerimiento::APROBADO,
        };

        $requerimiento->update(['estado' => $nuevoEstado]);

        return $requerimiento->refresh();
    }

    /**
     * @param  list<array{material_id: int, cantidad_solicitada: float|int, observacion?: string|null}>  $items
     */
    private function sincronizarDetalles(Requerimiento $requerimiento, array $items): void
    {
        $materialIds = array_map(static fn (array $item): int => (int) $item['material_id'], $items);

        $requerimiento->detalles()
            ->whereNotIn('material_id', $materialIds)
            ->get()
            ->each(static fn (RequerimientoDetalle $det) => $det->delete());

        foreach ($items as $item) {
            $materialId = (int) $item['material_id'];

            /** @var RequerimientoDetalle|null $existente */
            $existente = RequerimientoDetalle::withTrashed()
                ->where('requerimiento_id', $requerimiento->id)
                ->where('material_id', $materialId)
                ->first();

            $payload = [
                'cantidad_solicitada' => round((float) $item['cantidad_solicitada'], 3),
                'observacion' => $item['observacion'] ?? null,
                'activo' => true,
            ];

            if ($existente) {
                if ($existente->trashed()) {
                    $existente->restore();
                }
                $existente->update($payload);
            } else {
                RequerimientoDetalle::query()->create(array_merge($payload, [
                    'requerimiento_id' => $requerimiento->id,
                    'material_id' => $materialId,
                    'cantidad_atendida' => 0,
                ]));
            }
        }
    }
}
