<?php

namespace App\Models\Logistica;

use App\Models\ModeloBase;

class Proveedor extends ModeloBase
{
    protected $table = 'proveedores';

    protected $fillable = [
        'ruc',
        'razon_social',
        'contacto',
        'telefono',
        'email',
        'direccion',
        'condicion_pago',
        'observaciones',
        'activo',
    ];
}
