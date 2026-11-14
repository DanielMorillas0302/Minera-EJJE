<?php

namespace App\Services\Logistica;

use App\Enums\EstadoOrdenCompra;
use App\Enums\EstadoPago;
use App\Enums\NivelNotificacion;
use App\Enums\RolUsuario;
use App\Enums\TipoComprobante;
use App\Enums\TipoMovimientoFinanciero;
use App\Enums\TipoMovimientoInventario;
use App\Enums\TipoNotificacion;
use App\Models\Finanzas\CategoriaGasto;
use App\Models\Finanzas\CentroCosto;
use App\Models\Finanzas\MovimientoFinanciero;
use App\Models\Logistica\OrdenCompra;
use App\Models\Logistica\OrdenCompraDetalle;
use App\Models\User;
use App\Services\Core\NotificacionService;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class OrdenCompraService
{
    public function __construct(
        private readonly CorrelativoService $correlativoService,
        private readonly InventarioService $inventarioService,
        private readonly ?NotificacionService $notificacionService = null,
    ) {}

    /**
     * Crea una orden de compra en estado BORRADOR o EMITIDA.
     *
     * @param  array<string, mixed>  $datos
     *
     * @throws ValidationException
     */
    public function crearOrden(array $datos, User $usuario): OrdenCompra
    {
        $items = $datos['detalles'] ?? [];
        if (empty($items)) {
            throw ValidationException::withMessages([
                'detalles' => 'La orden de compra debe incluir al menos un material.',
            ]);
        }

        return DB::transaction(function () use ($datos, $items, $usuario): OrdenCompra {
            $codigo = $this->correlativoService->siguienteCodigo('OC');

            $subtotal = 0.0;
            foreach ($items as $item) {
                $subtotal += ((float) $item['cantidad']) * ((float) $item['precio_unitario']);
            }
            $igv = round($subtotal * 0.18, 2);
            $total = round($subtotal + $igv, 2);

            $estadoInicial = ! empty($datos['emitir_inmediata'])
                ? EstadoOrdenCompra::EMITIDA
                : EstadoOrdenCompra::BORRADOR;

            $orden = OrdenCompra::create([
                'codigo' => $codigo,
                'proveedor_id' => $datos['proveedor_id'],
                'requerimiento_id' => $datos['requerimiento_id'] ?? null,
                'almacen_destino_id' => $datos['almacen_destino_id'],
                'fecha' => $datos['fecha'] ?? Carbon::now()->toDateString(),
                'fecha_entrega_esperada' => $datos['fecha_entrega_esperada'] ?? null,
                'estado' => $estadoInicial,
                'moneda' => $datos['moneda'] ?? 'PEN',
                'subtotal' => $subtotal,
                'igv' => $igv,
                'total' => $total,
                'condiciones_pago' => $datos['condiciones_pago'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
                'aprobado_por_id' => $estadoInicial === EstadoOrdenCompra::EMITIDA ? $usuario->id : null,
                'fecha_aprobacion' => $estadoInicial === EstadoOrdenCompra::EMITIDA ? Carbon::now() : null,
                'activo' => true,
            ]);

            foreach ($items as $item) {
                $cant = (float) $item['cantidad'];
                $pu = (float) $item['precio_unitario'];
                $sub = round($cant * $pu, 2);

                OrdenCompraDetalle::create([
                    'orden_compra_id' => $orden->id,
                    'material_id' => $item['material_id'],
                    'cantidad' => $cant,
                    'precio_unitario' => $pu,
                    'subtotal' => $sub,
                    'cantidad_recibida' => 0,
                    'observaciones' => $item['observaciones'] ?? null,
                    'activo' => true,
                ]);
            }

            if ($estadoInicial === EstadoOrdenCompra::EMITIDA && $this->notificacionService) {
                $this->notificacionService->crearNotificacion(
                    TipoNotificacion::ORDEN_COMPRA_EMITIDA,
                    'logistica',
                    NivelNotificacion::INFO,
                    "Orden de Compra emitida: {$orden->codigo}",
                    "Se emitió la orden de compra {$orden->codigo} para el proveedor {$orden->proveedor?->razon_social} por un total de S/ {$total}.",
                    "/logistica/ordenes-compra/{$orden->id}",
                    null,
                    RolUsuario::FINANZAS->value,
                );
            }

            return $orden->load(['proveedor', 'almacenDestino', 'detalles.material']);
        });
    }

    /**
     * Emite y aprueba una orden de compra en borrador.
     *
     * @throws ValidationException
     */
    public function emitirOrden(OrdenCompra $orden, User $usuario): OrdenCompra
    {
        if ($orden->estado !== EstadoOrdenCompra::BORRADOR) {
            throw ValidationException::withMessages([
                'estado' => 'Solo se pueden emitir órdenes de compra que se encuentren en estado BORRADOR.',
            ]);
        }

        $orden->update([
            'estado' => EstadoOrdenCompra::EMITIDA,
            'aprobado_por_id' => $usuario->id,
            'fecha_aprobacion' => Carbon::now(),
        ]);

        if ($this->notificacionService) {
            $this->notificacionService->crearNotificacion(
                TipoNotificacion::ORDEN_COMPRA_EMITIDA,
                'logistica',
                NivelNotificacion::INFO,
                "Orden de Compra emitida: {$orden->codigo}",
                "Se aprobó la orden de compra {$orden->codigo} con destino a {$orden->almacenDestino?->nombre}.",
                "/logistica/ordenes-compra/{$orden->id}",
                null,
                RolUsuario::FINANZAS->value,
            );
        }

        return $orden->load(['proveedor', 'almacenDestino', 'detalles.material']);
    }

    /**
     * Recepciona ítems de una orden de compra e ingresa stock a almacén con kardex.
     *
     * @param  array<int, array{detalle_id: int, cantidad_recibir: float}>  $recepciones
     * @param  array<string, mixed>|null  $datosFactura
     * @return array{orden: OrdenCompra, movimiento_financiero: ?MovimientoFinanciero}
     *
     * @throws ValidationException
     */
    public function recepcionarItems(OrdenCompra $orden, array $recepciones, User $usuario, ?array $datosFactura = null): array
    {
        if (! in_array($orden->estado, [EstadoOrdenCompra::EMITIDA, EstadoOrdenCompra::RECIBIDA_PARCIAL], true)) {
            throw ValidationException::withMessages([
                'estado' => 'Solo se pueden recepcionar órdenes de compra en estado EMITIDA o RECIBIDA_PARCIAL.',
            ]);
        }

        return DB::transaction(function () use ($orden, $recepciones, $usuario, $datosFactura): array {
            $detalles = $orden->detalles()->lockForUpdate()->get()->keyBy('id');
            $movimientoFinanciero = null;
            $hayRecepcionValida = false;

            foreach ($recepciones as $rec) {
                $detalleId = (int) $rec['detalle_id'];
                $cantidadRecibir = (float) ($rec['cantidad_recibir'] ?? 0);

                if ($cantidadRecibir <= 0) {
                    continue;
                }

                /** @var OrdenCompraDetalle|null $detalle */
                $detalle = $detalles->get($detalleId);
                if (! $detalle) {
                    continue;
                }

                $pendiente = (float) ($detalle->cantidad - $detalle->cantidad_recibida);
                if ($cantidadRecibir > $pendiente) {
                    throw ValidationException::withMessages([
                        'recepciones' => "La cantidad a recibir ({$cantidadRecibir}) excede el saldo pendiente ({$pendiente}) para el ítem {$detalle->material?->nombre}.",
                    ]);
                }

                // 1. Ingreso al kardex e inventario
                $this->inventarioService->aplicarMovimiento(
                    $orden->almacen_destino_id,
                    $detalle->material_id,
                    TipoMovimientoInventario::ENTRADA_COMPRA,
                    $cantidadRecibir,
                    $usuario,
                    false,
                    OrdenCompra::class,
                    $orden->id,
                    "Recepción de compra {$orden->codigo}",
                );

                // 2. Acumula cantidad recibida
                $detalle->update([
                    'cantidad_recibida' => (float) ($detalle->cantidad_recibida + $cantidadRecibir),
                ]);

                $hayRecepcionValida = true;
            }

            if (! $hayRecepcionValida) {
                throw ValidationException::withMessages([
                    'recepciones' => 'Debes ingresar una cantidad válida mayor a cero para al menos un ítem.',
                ]);
            }

            // Recalcula estado global de la orden
            $detallesActualizados = $orden->detalles()->get();
            $todosCompletos = true;
            $algunoRecibido = false;

            foreach ($detallesActualizados as $d) {
                if ($d->cantidad_recibida < $d->cantidad) {
                    $todosCompletos = false;
                }
                if ($d->cantidad_recibida > 0) {
                    $algunoRecibido = true;
                }
            }

            $nuevoEstado = $todosCompletos
                ? EstadoOrdenCompra::RECIBIDA
                : ($algunoRecibido ? EstadoOrdenCompra::RECIBIDA_PARCIAL : $orden->estado);

            $orden->update(['estado' => $nuevoEstado]);

            // 3. Generación opcional de cuenta por pagar vinculada
            if (! empty($datosFactura['generar_cuenta_pagar'])) {
                $centroCostoId = $datosFactura['centro_costo_id'] ?? CentroCosto::query()->where('activo', true)->value('id');
                $categoriaId = $datosFactura['categoria_id'] ?? CategoriaGasto::query()->where('activo', true)->value('id');

                if ($centroCostoId && $categoriaId) {
                    $movimientoFinanciero = MovimientoFinanciero::create([
                        'tipo' => TipoMovimientoFinanciero::EGRESO,
                        'fecha' => $datosFactura['fecha_comprobante'] ?? Carbon::now()->toDateString(),
                        'monto' => (float) ($datosFactura['monto'] ?? $orden->total),
                        'moneda' => $orden->moneda,
                        'categoria_id' => $categoriaId,
                        'centro_costo_id' => $centroCostoId,
                        'proveedor_id' => $orden->proveedor_id,
                        'concepto' => "Factura de compra - {$orden->codigo}",
                        'comprobante_tipo' => TipoComprobante::tryFrom($datosFactura['comprobante_tipo'] ?? 'FACTURA') ?? TipoComprobante::FACTURA,
                        'comprobante_numero' => $datosFactura['comprobante_numero'] ?? null,
                        'estado_pago' => EstadoPago::PENDIENTE,
                        'fecha_vencimiento' => $datosFactura['fecha_vencimiento'] ?? Carbon::now()->addDays(30)->toDateString(),
                        'orden_compra_id' => $orden->id,
                        'observaciones' => "Generado automáticamente tras recepción de OC {$orden->codigo}.",
                        'activo' => true,
                    ]);
                }
            }

            return [
                'orden' => $orden->fresh(['proveedor', 'almacenDestino', 'detalles.material', 'movimientosFinancieros']),
                'movimiento_financiero' => $movimientoFinanciero,
            ];
        });
    }

    /**
     * Anula una orden de compra si no tiene recepciones aplicadas.
     *
     * @throws ValidationException
     */
    public function anularOrden(OrdenCompra $orden, string $motivo, User $usuario): OrdenCompra
    {
        if ($orden->estado === EstadoOrdenCompra::ANULADA) {
            throw ValidationException::withMessages([
                'estado' => 'La orden de compra ya se encuentra anulada.',
            ]);
        }

        $tieneRecepciones = $orden->detalles()->where('cantidad_recibida', '>', 0)->exists();
        if ($tieneRecepciones) {
            throw ValidationException::withMessages([
                'estado' => 'No es posible anular una orden de compra que ya cuenta con recepciones de almacén registradas.',
            ]);
        }

        $orden->update([
            'estado' => EstadoOrdenCompra::ANULADA,
            'observaciones' => trim(($orden->observaciones ?? '')."\n[ANULADA por {$usuario->name}]: {$motivo}"),
        ]);

        return $orden->load(['proveedor', 'almacenDestino', 'detalles.material']);
    }
}
