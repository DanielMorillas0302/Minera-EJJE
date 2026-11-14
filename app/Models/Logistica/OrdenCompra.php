<?php

namespace App\Models\Logistica;

use App\Enums\EstadoOrdenCompra;
use App\Models\Core\Adjunto;
use App\Models\Finanzas\MovimientoFinanciero;
use App\Models\ModeloBase;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class OrdenCompra extends ModeloBase
{
    protected $table = 'ordenes_compra';

    protected $fillable = [
        'codigo',
        'proveedor_id',
        'requerimiento_id',
        'almacen_destino_id',
        'fecha',
        'fecha_entrega_esperada',
        'estado',
        'moneda',
        'subtotal',
        'igv',
        'total',
        'condiciones_pago',
        'observaciones',
        'aprobado_por_id',
        'fecha_aprobacion',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha' => 'date',
            'fecha_entrega_esperada' => 'date',
            'fecha_aprobacion' => 'datetime',
            'estado' => EstadoOrdenCompra::class,
            'subtotal' => 'decimal:2',
            'igv' => 'decimal:2',
            'total' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    public function proveedor(): BelongsTo
    {
        return $this->belongsTo(Proveedor::class, 'proveedor_id');
    }

    public function requerimiento(): BelongsTo
    {
        return $this->belongsTo(Requerimiento::class, 'requerimiento_id');
    }

    public function almacenDestino(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_destino_id');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por_id');
    }

    public function detalles(): HasMany
    {
        return $this->hasMany(OrdenCompraDetalle::class, 'orden_compra_id');
    }

    public function movimientosFinancieros(): HasMany
    {
        return $this->hasMany(MovimientoFinanciero::class, 'orden_compra_id');
    }

    public function adjuntos(): MorphMany
    {
        return $this->morphMany(Adjunto::class, 'entidad');
    }
}
