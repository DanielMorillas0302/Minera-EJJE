<?php

namespace App\Models\Finanzas;

use App\Models\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Presupuesto extends ModeloBase
{
    protected $table = 'presupuestos';

    protected $fillable = [
        'centro_costo_id',
        'anio',
        'mes',
        'monto_presupuestado',
        'notas',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'mes' => 'integer',
            'monto_presupuestado' => 'decimal:2',
        ];
    }

    public function centroCosto(): BelongsTo
    {
        return $this->belongsTo(CentroCosto::class, 'centro_costo_id');
    }
}
