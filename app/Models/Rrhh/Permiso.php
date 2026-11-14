<?php

namespace App\Models\Rrhh;

use App\Enums\EstadoSolicitudRrhh;
use App\Enums\TipoPermiso;
use App\Models\ModeloBase;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permiso extends ModeloBase
{
    protected $table = 'permisos';

    protected $fillable = [
        'trabajador_id',
        'tipo',
        'con_goce',
        'fecha_inicio',
        'fecha_fin',
        'motivo',
        'estado',
        'solicitado_por',
        'aprobado_por',
        'fecha_resolucion',
        'comentario',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoPermiso::class,
            'con_goce' => 'boolean',
            'fecha_inicio' => 'date',
            'fecha_fin' => 'date',
            'estado' => EstadoSolicitudRrhh::class,
            'fecha_resolucion' => 'datetime',
        ];
    }

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    public function solicitadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitado_por');
    }

    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por');
    }
}
