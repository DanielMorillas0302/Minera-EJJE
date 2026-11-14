<?php

namespace App\Models\Finanzas;

use App\Models\Core\Area;
use App\Models\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CentroCosto extends ModeloBase
{
    protected $table = 'centros_costo';

    protected $fillable = [
        'codigo',
        'nombre',
        'area_id',
        'descripcion',
        'activo',
    ];

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function presupuestos(): HasMany
    {
        return $this->hasMany(Presupuesto::class, 'centro_costo_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoFinanciero::class, 'centro_costo_id');
    }
}
