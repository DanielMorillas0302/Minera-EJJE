<?php

namespace App\Models\Logistica;

use App\Models\Core\Sede;
use App\Models\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Almacen extends ModeloBase
{
    protected $table = 'almacenes';

    protected $fillable = [
        'codigo',
        'nombre',
        'sede_id',
        'es_principal',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'es_principal' => 'boolean',
            'activo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    /**
     * @return HasMany<StockAlmacen, $this>
     */
    public function stocks(): HasMany
    {
        return $this->hasMany(StockAlmacen::class, 'almacen_id');
    }
}
