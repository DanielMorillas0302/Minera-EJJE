<?php

namespace App\Services\Logistica;

use App\Enums\TipoMovimientoInventario;
use App\Models\Logistica\Almacen;
use App\Models\Logistica\Material;
use App\Models\Logistica\MovimientoInventario;
use App\Models\Logistica\StockAlmacen;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventarioService
{
    /**
     * Aplica un movimiento de inventario sobre un almacén y material garantizando stock no negativo.
     *
     * @throws ValidationException
     */
    public function aplicarMovimiento(
        int $almacenId,
        int $materialId,
        TipoMovimientoInventario $tipo,
        float $cantidad,
        User $usuario,
        bool $esSalida = false,
        ?string $documentoTipo = null,
        ?int $documentoId = null,
        ?string $observacion = null,
        ?Carbon $fechaHora = null,
    ): MovimientoInventario {
        if ($cantidad <= 0) {
            throw ValidationException::withMessages([
                'cantidad' => 'La cantidad del movimiento debe ser mayor a cero.',
            ]);
        }

        return DB::transaction(function () use (
            $almacenId,
            $materialId,
            $tipo,
            $cantidad,
            $usuario,
            $esSalida,
            $documentoTipo,
            $documentoId,
            $observacion,
            $fechaHora
        ): MovimientoInventario {
            /** @var Material $material */
            $material = Material::query()->whereKey($materialId)->lockForUpdate()->firstOrFail();

            /** @var Almacen $almacen */
            $almacen = Almacen::query()->whereKey($almacenId)->firstOrFail();

            $stock = StockAlmacen::query()
                ->where('almacen_id', $almacenId)
                ->where('material_id', $materialId)
                ->lockForUpdate()
                ->first();

            if (! $stock) {
                $stock = StockAlmacen::query()->create([
                    'almacen_id' => $almacenId,
                    'material_id' => $materialId,
                    'cantidad' => 0,
                    'activo' => true,
                ]);
                $stock->refresh();
            }

            $saldoActual = round((float) $stock->cantidad, 3);
            $cantidadRedondeada = round($cantidad, 3);
            $debeRestar = $esSalida || $tipo->esSalida();

            $nuevoSaldo = $debeRestar
                ? round($saldoActual - $cantidadRedondeada, 3)
                : round($saldoActual + $cantidadRedondeada, 3);

            if ($nuevoSaldo < 0) {
                throw ValidationException::withMessages([
                    'stock' => sprintf(
                        'Stock insuficiente de %s - %s en %s. Disponible: %s, requerido: %s.',
                        $material->codigo,
                        $material->nombre,
                        $almacen->nombre,
                        number_format($saldoActual, 3, '.', ''),
                        number_format($cantidadRedondeada, 3, '.', ''),
                    ),
                ]);
            }

            $stock->cantidad = $nuevoSaldo;
            $stock->save();

            return MovimientoInventario::query()->create([
                'almacen_id' => $almacenId,
                'material_id' => $materialId,
                'tipo' => $tipo,
                'cantidad' => $cantidadRedondeada,
                'saldo_resultante' => $nuevoSaldo,
                'fecha_hora' => $fechaHora ?? now(),
                'usuario_id' => $usuario->id,
                'documento_tipo' => $documentoTipo,
                'documento_id' => $documentoId,
                'observacion' => $observacion,
                'activo' => true,
            ]);
        });
    }

    /**
     * Registra un consumo interno en el almacén indicado.
     *
     * @throws ValidationException
     */
    public function registrarConsumo(
        int $almacenId,
        int $materialId,
        float $cantidad,
        User $usuario,
        string $observacion,
    ): MovimientoInventario {
        return $this->aplicarMovimiento(
            almacenId: $almacenId,
            materialId: $materialId,
            tipo: TipoMovimientoInventario::CONSUMO,
            cantidad: $cantidad,
            usuario: $usuario,
            esSalida: true,
            observacion: $observacion,
        );
    }

    /**
     * Registra un ingreso de compra directa o un ajuste manual de inventario.
     *
     * @throws ValidationException
     */
    public function registrarIngresoOAjuste(
        int $almacenId,
        int $materialId,
        TipoMovimientoInventario $tipo,
        float $cantidad,
        bool $esSalida,
        User $usuario,
        ?string $observacion = null,
    ): MovimientoInventario {
        if (! in_array($tipo, [TipoMovimientoInventario::ENTRADA_COMPRA, TipoMovimientoInventario::AJUSTE], true)) {
            throw ValidationException::withMessages([
                'tipo' => 'Solo se permiten movimientos de ENTRADA_COMPRA o AJUSTE por este flujo.',
            ]);
        }

        if ($tipo === TipoMovimientoInventario::ENTRADA_COMPRA && $esSalida) {
            throw ValidationException::withMessages([
                'tipo' => 'Una entrada por compra no puede registrarse como salida.',
            ]);
        }

        return $this->aplicarMovimiento(
            almacenId: $almacenId,
            materialId: $materialId,
            tipo: $tipo,
            cantidad: $cantidad,
            usuario: $usuario,
            esSalida: $esSalida,
            observacion: $observacion,
        );
    }

    /**
     * Lista registros de stock que se encuentran en o por debajo de su stock mínimo configurado.
     *
     * @param  list<int>|null  $almacenIdsPermitidos
     * @return Collection<int, StockAlmacen>
     */
    public function alertasStockMinimo(?array $almacenIdsPermitidos = null): Collection
    {
        return StockAlmacen::query()
            ->with(['almacen.sede', 'material.unidadMedida', 'material.categoria'])
            ->whereHas('material', static function ($query): void {
                $query->where('stock_minimo', '>', 0)->where('activo', true);
            })
            ->whereHas('almacen', static function ($query): void {
                $query->where('activo', true);
            })
            ->when($almacenIdsPermitidos !== null, static function ($query) use ($almacenIdsPermitidos): void {
                $query->whereIn('almacen_id', $almacenIdsPermitidos === [] ? [0] : $almacenIdsPermitidos);
            })
            ->whereRaw(
                'stock_almacenes.cantidad <= (SELECT m.stock_minimo FROM materiales m WHERE m.id = stock_almacenes.material_id)'
            )
            ->orderBy('cantidad')
            ->get();
    }
}
