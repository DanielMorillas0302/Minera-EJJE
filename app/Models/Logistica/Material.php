<?php

namespace App\Models\Logistica;

use App\Models\Core\UnidadMedida;
use App\Models\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Material extends ModeloBase
{
    protected $table = 'materiales';

    protected $fillable = [
        'codigo',
        'nombre',
        'descripcion',
        'categoria_id',
        'unidad_medida_id',
        'stock_minimo',
        'costo_referencial',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'stock_minimo' => 'decimal:3',
            'costo_referencial' => 'decimal:2',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CategoriaMaterial, $this>
     */
    public function categoria(): BelongsTo
    {
        return $this->belongsTo(CategoriaMaterial::class, 'categoria_id');
    }

    /**
     * @return BelongsTo<UnidadMedida, $this>
     */
    public function unidadMedida(): BelongsTo
    {
        return $this->belongsTo(UnidadMedida::class, 'unidad_medida_id');
    }

    /**
     * @return HasMany<StockAlmacen, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(StockAlmacen::class, 'material_id');
    }

    /**
     * @return HasMany<MovimientoInventario, $this>
     */
    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoInventario::class, 'material_id');
    }
}
