<?php

namespace App\Models\Core;

use App\Models\ModeloBase;

class Cargo extends ModeloBase
{
    protected $table = 'cargos';

    protected $fillable = [
        'nombre',
        'activo',
    ];
}
