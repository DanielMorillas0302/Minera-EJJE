<?php

namespace App\Models\Logistica;

use App\Models\ModeloBase;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoriaMaterial extends ModeloBase
{
    protected $table = 'categorias_material';

    protected $fillable = [
        'nombre',
        'descripcion',
        'activo',
    ];

    /**
     * @return HasMany<Material, $this>
     */
    public function materiales(): HasMany
    {
        return $this->hasMany(Material::class, 'categoria_id');
    }
}
