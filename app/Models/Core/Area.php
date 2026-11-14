<?php

namespace App\Models\Core;

use App\Models\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Area extends ModeloBase
{
    protected $table = 'areas';

    protected $fillable = [
        'nombre',
        'sede_id',
        'activo',
    ];

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }
}
