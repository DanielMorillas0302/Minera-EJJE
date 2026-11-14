<?php

namespace App\Models\Logistica;

use App\Models\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RequerimientoDetalle extends ModeloBase
{
    protected $table = 'requerimiento_detalles';

    protected $fillable = [
        'requerimiento_id',
        'material_id',
        'cantidad_solicitada',
        'cantidad_atendida',
        'observacion',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'cantidad_solicitada' => 'decimal:3',
            'cantidad_atendida' => 'decimal:3',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Requerimiento, $this>
     */
    public function requerimiento(): BelongsTo
    {
        return $this->belongsTo(Requerimiento::class, 'requerimiento_id');
    }

    /**
     * @return BelongsTo<Material, $this>
     */
    public function material(): BelongsTo
    {
        return $this->belongsTo(Material::class, 'material_id');
    }
}
