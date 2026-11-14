<?php

namespace App\Models\Logistica;

use App\Enums\EstadoRequerimiento;
use App\Enums\PrioridadRequerimiento;
use App\Models\Core\Area;
use App\Models\Core\Sede;
use App\Models\ModeloBase;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Requerimiento extends ModeloBase
{
    protected $table = 'requerimientos';

    protected $fillable = [
        'codigo',
        'fecha_solicitud',
        'area_id',
        'sede_id',
        'solicitante_id',
        'prioridad',
        'estado',
        'aprobado_por_id',
        'fecha_aprobacion',
        'motivo_rechazo',
        'observaciones',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_solicitud' => 'date',
            'fecha_aprobacion' => 'datetime',
            'prioridad' => PrioridadRequerimiento::class,
            'estado' => EstadoRequerimiento::class,
            'activo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Area, $this>
     */
    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    /**
     * @return BelongsTo<Sede, $this>
     */
    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function solicitante(): BelongsTo
    {
        return $this->belongsTo(User::class, 'solicitante_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function aprobadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'aprobado_por_id');
    }

    /**
     * @return HasMany<RequerimientoDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(RequerimientoDetalle::class, 'requerimiento_id');
    }

    /**
     * @return HasMany<Envio, $this>
     */
    public function envios(): HasMany
    {
        return $this->hasMany(Envio::class, 'requerimiento_id');
    }
}
