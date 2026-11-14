<?php

namespace App\Models\Logistica;

use App\Models\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OrdenCompraDetalle extends ModeloBase
{
    protected $table = 'orden_compra_detalles';

    protected $fillable = [
        'orden_compra_id',
        'material_id',
        'cantidad',
        'precio_unitario',
        'subtotal',
        'cantidad_recibida',
        'observaciones',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad' => 'decimal:2',
            'precio_unitario' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'cantidad_recibida' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function ordenCompra(): BelongsTo
    {
        return $this->belongsTo(OrdenCompra::class, 'orden_compra_id');
    }

    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
