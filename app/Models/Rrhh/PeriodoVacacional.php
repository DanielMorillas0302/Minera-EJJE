<?php

namespace App\Models\Rrhh;

use App\Enums\EstadoSolicitudRrhh;
use App\Models\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PeriodoVacacional extends ModeloBase
{
    protected $table = 'periodos_vacacionales';

    protected $fillable = [
        'trabajador_id',
        'anio',
        'dias_ganados',
        'dias_tomados',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'anio' => 'integer',
            'dias_ganados' => 'integer',
            'dias_tomados' => 'integer',
        ];
    }

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    public function solicitudes(): HasMany
    {
        return $this->hasMany(SolicitudVacaciones::class, 'periodo_vacacional_id');
    }

    /**
     * Calcula los días consumidos a partir de solicitudes aprobadas o tomadas activas.
     */
    public function recalcularDiasTomados(): int
    {
        $tomados = (int) $this->solicitudes()
            ->activos()
            ->whereIn('estado', [
                EstadoSolicitudRrhh::APROBADA->value,
                EstadoSolicitudRrhh::TOMADA->value,
            ])
            ->sum('dias');

        $this->update(['dias_tomados' => $tomados]);

        return $tomados;
    }

    public function saldoDisponible(): int
    {
        return max(0, (int) $this->dias_ganados - (int) $this->dias_tomados);
    }
}
