<?php

namespace App\Models\Rrhh;

use App\Enums\EstadoTrabajador;
use App\Enums\ResultadoExamen;
use App\Enums\SexoTrabajador;
use App\Enums\UbicacionTrabajador;
use App\Models\Core\Adjunto;
use App\Models\Core\Area;
use App\Models\Core\Cargo;
use App\Models\Core\Sede;
use App\Models\ModeloBase;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Trabajador extends ModeloBase
{
    protected $table = 'trabajadores';

    protected $fillable = [
        'dni',
        'nombres',
        'apellidos',
        'fecha_nacimiento',
        'sexo',
        'telefono',
        'email',
        'direccion',
        'contacto_emergencia_nombre',
        'contacto_emergencia_telefono',
        'cargo_id',
        'area_id',
        'sede_base_id',
        'fecha_ingreso',
        'fecha_cese',
        'estado',
        'ubicacion_actual',
        'esquema_rotacion',
        'foto',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_nacimiento' => 'date',
            'fecha_ingreso' => 'date',
            'fecha_cese' => 'date',
            'sexo' => SexoTrabajador::class,
            'estado' => EstadoTrabajador::class,
            'ubicacion_actual' => UbicacionTrabajador::class,
        ];
    }

    public function cargo(): BelongsTo
    {
        return $this->belongsTo(Cargo::class, 'cargo_id');
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(Area::class, 'area_id');
    }

    public function sedeBase(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_base_id');
    }

    public function examenes(): HasMany
    {
        return $this->hasMany(ExamenOcupacional::class, 'trabajador_id');
    }

    public function periodosVacacionales(): HasMany
    {
        return $this->hasMany(PeriodoVacacional::class, 'trabajador_id');
    }

    public function solicitudesVacaciones(): HasMany
    {
        return $this->hasMany(SolicitudVacaciones::class, 'trabajador_id');
    }

    public function permisos(): HasMany
    {
        return $this->hasMany(Permiso::class, 'trabajador_id');
    }

    public function movimientos(): HasMany
    {
        return $this->hasMany(MovimientoPersonal::class, 'trabajador_id');
    }

    public function adjuntos(): MorphMany
    {
        return $this->morphMany(Adjunto::class, 'entidad');
    }

    public function nombreCompleto(): string
    {
        return trim("{$this->apellidos}, {$this->nombres}");
    }

    public function getAptitudViajeAttribute(): bool
    {
        return $this->detalleAptitudViaje()['apto'];
    }

    /**
     * Evalúa aptitud de viaje a Pataz: trabajador ACTIVO y último examen médico activo con resultado APTO vigente.
     *
     * @return array{apto: bool, codigo: string, motivo: string, ultimo_examen: ?ExamenOcupacional}
     */
    public function detalleAptitudViaje(): array
    {
        if (! $this->activo || $this->trashed()) {
            return [
                'apto' => false,
                'codigo' => 'INACTIVO',
                'motivo' => 'El trabajador está desactivado en el sistema.',
                'ultimo_examen' => null,
            ];
        }

        if ($this->estado !== EstadoTrabajador::ACTIVO) {
            return [
                'apto' => false,
                'codigo' => 'ESTADO_NO_ACTIVO',
                'motivo' => "El trabajador se encuentra en estado {$this->estado->etiqueta()}.",
                'ultimo_examen' => null,
            ];
        }

        $examenesActivos = $this->relationLoaded('examenes')
            ? $this->examenes
                ->filter(fn (ExamenOcupacional $e): bool => $e->activo && ! $e->trashed())
                ->sort(fn (ExamenOcupacional $a, ExamenOcupacional $b): int => [$b->fecha_examen?->timestamp, $b->fecha_vencimiento?->timestamp, $b->id]
                    <=> [$a->fecha_examen?->timestamp, $a->fecha_vencimiento?->timestamp, $a->id])
                ->values()
            : $this->examenes()
                ->activos()
                ->orderByDesc('fecha_examen')
                ->orderByDesc('fecha_vencimiento')
                ->orderByDesc('id')
                ->get();

        /** @var ?ExamenOcupacional $ultimo */
        $ultimo = $examenesActivos->first();

        if (! $ultimo) {
            return [
                'apto' => false,
                'codigo' => 'SIN_EXAMEN',
                'motivo' => 'No cuenta con examen ocupacional registrado.',
                'ultimo_examen' => null,
            ];
        }

        if ($ultimo->resultado !== ResultadoExamen::APTO) {
            return [
                'apto' => false,
                'codigo' => 'RESULTADO_NO_APTO',
                'motivo' => "Último examen ocupacional ({$ultimo->fecha_examen?->format('d/m/Y')}) con resultado {$ultimo->resultado->etiqueta()}.",
                'ultimo_examen' => $ultimo,
            ];
        }

        if (! $ultimo->vigente) {
            return [
                'apto' => false,
                'codigo' => 'EXAMEN_VENCIDO',
                'motivo' => "Examen ocupacional vencido el {$ultimo->fecha_vencimiento?->format('d/m/Y')}.",
                'ultimo_examen' => $ultimo,
            ];
        }

        return [
            'apto' => true,
            'codigo' => 'APTO',
            'motivo' => "Examen {$ultimo->tipo->etiqueta()} APTO vigente hasta el {$ultimo->fecha_vencimiento?->format('d/m/Y')}.",
            'ultimo_examen' => $ultimo,
        ];
    }
}
