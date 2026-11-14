<?php

namespace App\Services\Logistica;

use App\Enums\EstadoEnvio;
use App\Enums\EstadoRequerimiento;
use App\Enums\TipoMovimientoInventario;
use App\Models\Logistica\Envio;
use App\Models\Logistica\EnvioDetalle;
use App\Models\Logistica\Requerimiento;
use App\Models\Logistica\RequerimientoDetalle;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EnvioService
{
    public function __construct(
        private readonly CorrelativoService $correlativos,
        private readonly InventarioService $inventario,
        private readonly RequerimientoService $requerimientos,
    ) {}

    /**
     * Crea un envío en estado PREPARADO.
     *
     * @param  array<string, mixed>  $datos
     * @param  list<array{material_id: int, cantidad_enviada: float|int, requerimiento_detalle_id?: int|null}>  $items
     *
     * @throws ValidationException
     */
    public function crear(array $datos, array $items, User $responsable, bool $despacharInmediatamente = false): Envio
    {
        if ((int) $datos['almacen_origen_id'] === (int) $datos['almacen_destino_id']) {
            throw ValidationException::withMessages([
                'almacen_destino_id' => 'El almacén de destino debe ser distinto al almacén de origen.',
            ]);
        }

        if ($items === []) {
            throw ValidationException::withMessages([
                'items' => 'Debe incluir al menos un material en el envío.',
            ]);
        }

        $requerimiento = null;
        if (! empty($datos['requerimiento_id'])) {
            /** @var Requerimiento $requerimiento */
            $requerimiento = Requerimiento::query()->findOrFail($datos['requerimiento_id']);

            if (! in_array($requerimiento->estado, [EstadoRequerimiento::APROBADO, EstadoRequerimiento::ATENDIDO_PARCIAL], true)) {
                throw ValidationException::withMessages([
                    'requerimiento_id' => 'Solo se pueden generar envíos desde requerimientos APROBADOS o ATENDIDO_PARCIAL.',
                ]);
            }
        }

        $codigo = $this->correlativos->siguienteCodigo('ENV');

        $envio = DB::transaction(function () use ($datos, $items, $responsable, $requerimiento, $codigo): Envio {
            /** @var Envio $nuevoEnvio */
            $nuevoEnvio = Envio::query()->create([
                'codigo' => $codigo,
                'requerimiento_id' => $requerimiento?->id,
                'almacen_origen_id' => (int) $datos['almacen_origen_id'],
                'almacen_destino_id' => (int) $datos['almacen_destino_id'],
                'fecha_envio' => $datos['fecha_envio'] ?? null,
                'fecha_estimada_llegada' => $datos['fecha_estimada_llegada'] ?? null,
                'responsable_envio_id' => $responsable->id,
                'estado' => EstadoEnvio::PREPARADO,
                'guia_remision' => $datos['guia_remision'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
                'activo' => true,
            ]);

            $detallesReqPorMaterial = $requerimiento
                ? $requerimiento->detalles()->get()->keyBy('material_id')
                : collect();

            foreach ($items as $item) {
                $materialId = (int) $item['material_id'];
                $reqDetalleId = $item['requerimiento_detalle_id'] ?? $detallesReqPorMaterial->get($materialId)?->id;

                EnvioDetalle::query()->create([
                    'envio_id' => $nuevoEnvio->id,
                    'material_id' => $materialId,
                    'requerimiento_detalle_id' => $reqDetalleId,
                    'cantidad_enviada' => round((float) $item['cantidad_enviada'], 3),
                    'activo' => true,
                ]);
            }

            return $nuevoEnvio;
        });

        if ($despacharInmediatamente) {
            return $this->despachar(
                envio: $envio,
                usuario: $responsable,
                fechaEnvio: $datos['fecha_envio'] ?? null,
                fechaEstimada: $datos['fecha_estimada_llegada'] ?? null,
                guiaRemision: $datos['guia_remision'] ?? null,
            );
        }

        return $envio->load(['detalles.material.unidadMedida', 'almacenOrigen', 'almacenDestino', 'requerimiento']);
    }

    /**
     * Despacha un envío PREPARADO -> EN_TRANSITO, descontando el stock en origen mediante SALIDA_ENVIO.
     *
     * @throws ValidationException
     */
    public function despachar(
        Envio $envio,
        User $usuario,
        ?string $fechaEnvio = null,
        ?string $fechaEstimada = null,
        ?string $guiaRemision = null,
    ): Envio {
        return DB::transaction(function () use ($envio, $usuario, $fechaEnvio, $fechaEstimada, $guiaRemision): Envio {
            /** @var Envio $bloqueado */
            $bloqueado = Envio::query()->whereKey($envio->id)->lockForUpdate()->firstOrFail();

            if ($bloqueado->estado !== EstadoEnvio::PREPARADO) {
                throw ValidationException::withMessages([
                    'estado' => 'Solo pueden despacharse envíos en estado PREPARADO.',
                ]);
            }

            $detalles = $bloqueado->detalles()->with('material')->get();
            if ($detalles->isEmpty()) {
                throw ValidationException::withMessages([
                    'items' => 'El envío no tiene ítems para despachar.',
                ]);
            }

            $fecha = $fechaEnvio ?? $bloqueado->fecha_envio?->toDateString() ?? now()->toDateString();

            foreach ($detalles as $detalle) {
                $this->inventario->aplicarMovimiento(
                    almacenId: $bloqueado->almacen_origen_id,
                    materialId: $detalle->material_id,
                    tipo: TipoMovimientoInventario::SALIDA_ENVIO,
                    cantidad: (float) $detalle->cantidad_enviada,
                    usuario: $usuario,
                    esSalida: true,
                    documentoTipo: Envio::class,
                    documentoId: $bloqueado->id,
                    observacion: "Salida por despacho de envío {$bloqueado->codigo}.",
                    fechaHora: Carbon::parse($fecha)->setTimeFromTimeString(now()->format('H:i:s')),
                );
            }

            $bloqueado->update([
                'estado' => EstadoEnvio::EN_TRANSITO,
                'fecha_envio' => $fecha,
                'fecha_estimada_llegada' => $fechaEstimada ?? $bloqueado->fecha_estimada_llegada,
                'guia_remision' => $guiaRemision ?? $bloqueado->guia_remision,
            ]);

            return $bloqueado->fresh(['detalles.material.unidadMedida', 'almacenOrigen', 'almacenDestino', 'requerimiento']);
        });
    }

    /**
     * Registra la recepción de un envío EN_TRANSITO -> RECIBIDO o RECIBIDO_CON_OBSERVACIONES.
     *
     * @param  array<int, array{cantidad_recibida: float|int, observacion_recepcion?: string|null}>  $recepcionesPorDetalleId
     *
     * @throws ValidationException
     */
    public function recibir(
        Envio $envio,
        User $usuario,
        array $recepcionesPorDetalleId,
        ?string $fechaRecepcion = null,
        ?string $observaciones = null,
    ): Envio {
        return DB::transaction(function () use ($envio, $usuario, $recepcionesPorDetalleId, $fechaRecepcion, $observaciones): Envio {
            /** @var Envio $bloqueado */
            $bloqueado = Envio::query()->whereKey($envio->id)->lockForUpdate()->firstOrFail();

            if ($bloqueado->estado !== EstadoEnvio::EN_TRANSITO) {
                throw ValidationException::withMessages([
                    'estado' => 'Solo pueden recibirse envíos que se encuentren EN_TRANSITO.',
                ]);
            }

            $detalles = $bloqueado->detalles()->get();
            $fecha = $fechaRecepcion ?? now()->toDateString();
            $fechaHoraMov = Carbon::parse($fecha)->setTimeFromTimeString(now()->format('H:i:s'));
            $huboDiferencias = false;

            foreach ($detalles as $detalle) {
                if (! array_key_exists($detalle->id, $recepcionesPorDetalleId)) {
                    throw ValidationException::withMessages([
                        'detalles' => "Falta informar la cantidad recibida para el ítem #{$detalle->id}.",
                    ]);
                }

                $recibido = round((float) $recepcionesPorDetalleId[$detalle->id]['cantidad_recibida'], 3);
                $obsItem = isset($recepcionesPorDetalleId[$detalle->id]['observacion_recepcion'])
                    ? trim((string) $recepcionesPorDetalleId[$detalle->id]['observacion_recepcion'])
                    : null;

                if ($recibido < 0) {
                    throw ValidationException::withMessages([
                        'detalles' => 'La cantidad recibida no puede ser negativa.',
                    ]);
                }

                $enviado = round((float) $detalle->cantidad_enviada, 3);
                if (abs($enviado - $recibido) > 0.0005) {
                    $huboDiferencias = true;
                }

                $detalle->update([
                    'cantidad_recibida' => $recibido,
                    'observacion_recepcion' => $obsItem !== '' ? $obsItem : null,
                ]);

                if ($recibido > 0) {
                    $notaDiferencia = abs($enviado - $recibido) > 0.0005
                        ? " (diferencia: enviado {$enviado}, recibido {$recibido})"
                        : '';

                    $this->inventario->aplicarMovimiento(
                        almacenId: $bloqueado->almacen_destino_id,
                        materialId: $detalle->material_id,
                        tipo: TipoMovimientoInventario::ENTRADA_ENVIO,
                        cantidad: $recibido,
                        usuario: $usuario,
                        esSalida: false,
                        documentoTipo: Envio::class,
                        documentoId: $bloqueado->id,
                        observacion: "Recepción de envío {$bloqueado->codigo}{$notaDiferencia}.",
                        fechaHora: $fechaHoraMov,
                    );
                }

                if ($detalle->requerimiento_detalle_id && $recibido > 0) {
                    /** @var RequerimientoDetalle|null $reqDet */
                    $reqDet = RequerimientoDetalle::query()
                        ->whereKey($detalle->requerimiento_detalle_id)
                        ->lockForUpdate()
                        ->first();

                    if ($reqDet) {
                        $reqDet->cantidad_atendida = round((float) $reqDet->cantidad_atendida + $recibido, 3);
                        $reqDet->save();
                    }
                }
            }

            $estadoFinal = $huboDiferencias
                ? EstadoEnvio::RECIBIDO_CON_OBSERVACIONES
                : EstadoEnvio::RECIBIDO;

            $bloqueado->update([
                'estado' => $estadoFinal,
                'fecha_recepcion' => $fecha,
                'recibido_por_id' => $usuario->id,
                'observaciones' => $observaciones ?? $bloqueado->observaciones,
            ]);

            if ($bloqueado->requerimiento) {
                $this->requerimientos->recalcularEstadoAtencion($bloqueado->requerimiento);
            }

            return $bloqueado->fresh(['detalles.material.unidadMedida', 'almacenOrigen', 'almacenDestino', 'requerimiento', 'recibidoPor']);
        });
    }

    /**
     * Anula un envío PREPARADO o EN_TRANSITO, revirtiendo el movimiento en origen si ya fue despachado.
     *
     * @throws ValidationException
     */
    public function anular(Envio $envio, User $usuario, string $motivo): Envio
    {
        if (trim($motivo) === '') {
            throw ValidationException::withMessages([
                'motivo_anulacion' => 'Debe indicar el motivo de anulación del envío.',
            ]);
        }

        return DB::transaction(function () use ($envio, $usuario, $motivo): Envio {
            /** @var Envio $bloqueado */
            $bloqueado = Envio::query()->whereKey($envio->id)->lockForUpdate()->firstOrFail();

            if (! in_array($bloqueado->estado, [EstadoEnvio::PREPARADO, EstadoEnvio::EN_TRANSITO], true)) {
                throw ValidationException::withMessages([
                    'estado' => 'No se pueden anular envíos que ya fueron recibidos o anulados.',
                ]);
            }

            if ($bloqueado->estado === EstadoEnvio::EN_TRANSITO) {
                foreach ($bloqueado->detalles()->get() as $detalle) {
                    $this->inventario->aplicarMovimiento(
                        almacenId: $bloqueado->almacen_origen_id,
                        materialId: $detalle->material_id,
                        tipo: TipoMovimientoInventario::AJUSTE,
                        cantidad: (float) $detalle->cantidad_enviada,
                        usuario: $usuario,
                        esSalida: false,
                        documentoTipo: Envio::class,
                        documentoId: $bloqueado->id,
                        observacion: "Reversión por anulación de {$bloqueado->codigo}: ".trim($motivo),
                    );
                }
            }

            $bloqueado->update([
                'estado' => EstadoEnvio::ANULADO,
                'motivo_anulacion' => trim($motivo),
            ]);

            return $bloqueado->fresh(['detalles.material.unidadMedida', 'almacenOrigen', 'almacenDestino']);
        });
    }
}
