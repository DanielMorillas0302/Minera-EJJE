<?php

namespace App\Models\Core;

use App\Enums\TipoSede;
use App\Models\ModeloBase;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Sede extends ModeloBase
{
    protected $table = 'sedes';

    protected $fillable = [
        'nombre',
        'tipo',
        'ubicacion',
        'activo',
    ];

    protected $casts = [
        'tipo' => TipoSede::class,
        'activo' => 'boolean',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'eliminado_en' => 'datetime',
    ];

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class, 'sede_id');
    }

    public function usuarios(): HasMany
    {
        return $this->hasMany(User::class, 'sede_id');
    }
}
