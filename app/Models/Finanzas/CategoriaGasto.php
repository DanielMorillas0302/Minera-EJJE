<?php

namespace App\Models\Finanzas;

use App\Enums\TipoMovimientoFinanciero;
use App\Models\ModeloBase;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaGasto extends ModeloBase
{
    protected $table = 'categorias_gasto';

    protected $fillable = [
        'nombre',
        'tipo',
        'descripcion',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimientoFinanciero::class,
        ];
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoFinanciero::class, 'categoria_id');
    }
}
