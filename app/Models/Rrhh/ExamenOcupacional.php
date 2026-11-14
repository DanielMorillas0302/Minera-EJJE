<?php

namespace App\Models\Rrhh;

use App\Enums\ResultadoExamen;
use App\Enums\TipoExamenOcupacional;
use App\Models\Core\Adjunto;
use App\Models\ModeloBase;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class ExamenOcupacional extends ModeloBase
{
    protected $table = 'examenes_ocupacionales';

    protected $fillable = [
        'trabajador_id',
        'tipo',
        'fecha_examen',
        'fecha_vencimiento',
        'clinica',
        'resultado',
        'observaciones',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoExamenOcupacional::class,
            'resultado' => ResultadoExamen::class,
            'fecha_examen' => 'date',
            'fecha_vencimiento' => 'date',
        ];
    }

    public function trabajador(): BelongsTo
    {
        return $this->belongsTo(Trabajador::class, 'trabajador_id');
    }

    public function adjuntos(): MorphMany
    {
        return $this->morphMany(Adjunto::class, 'entidad');
    }

    public function getVigenteAttribute(): bool
    {
        if (! $this->fecha_vencimiento) {
            return false;
        }

        return $this->fecha_vencimiento->startOfDay()->greaterThanOrEqualTo(Carbon::today());
    }

    public function diasParaVencer(): int
    {
        return (int) Carbon::today()->diffInDays($this->fecha_vencimiento->startOfDay(), false);
    }

    public function scopeVigentes(Builder $query): Builder
    {
        return $query->whereDate('fecha_vencimiento', '>=', Carbon::today()->toDateString());
    }

    public function scopePorVencer(Builder $query, int $dias = 30): Builder
    {
        $hoy = Carbon::today();
        $limite = Carbon::today()->addDays($dias);

        return $query->whereDate('fecha_vencimiento', '>=', $hoy->toDateString())
            ->whereDate('fecha_vencimiento', '<=', $limite->toDateString());
    }

    public function scopeVencidos(Builder $query): Builder
    {
        return $query->whereDate('fecha_vencimiento', '<', Carbon::today()->toDateString());
    }
}
