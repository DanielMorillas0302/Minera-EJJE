<?php

namespace App\Models\Core;

use App\Models\ModeloBase;

class UnidadMedida extends ModeloBase
{
    protected $table = 'unidades_medida';

    protected $fillable = [
        'nombre',
        'abreviatura',
        'activo',
    ];
}
