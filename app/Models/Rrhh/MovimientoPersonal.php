<?php

namespace App\Models\Rrhh;

use App\Enums\TipoMovimientoPersonal;
use App\Models\Core\Sede;
use App\Models\ModeloBase;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MovimientoPersonal extends ModeloBase
{
    protected $table = 'movimientos_personal';

    protected $fillable = [
        'trabajador_id',
        'tipo',
        'fecha_hora',
        'origen_sede_id',
        'destino_sede_id',
        'transporte',
        'observaciones',
        'registrado_por',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoMovimientoPersonal::class,
            'fecha_hora' => 'datetime',
        ];
    }

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    public function origenSede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'origen_sede_id');
    }

    public function destinoSede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'destino_sede_id');
    }

    public function registradoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'registrado_por');
    }
}
