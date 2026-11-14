<?php

namespace App\Models\Core;

use App\Models\ModeloBase;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class Adjunto extends ModeloBase
{
    protected $table = 'adjuntos';

    protected $fillable = [
        'archivo',
        'nombre_original',
        'mime_type',
        'tamano',
        'descripcion',
        'entidad_type',
        'entidad_id',
        'activo',
    ];

    public function entidad(): MorphTo
    {
        return $this->morphTo();
    }
}
