<?php

namespace App\Models\Logistica;

use App\Enums\EstadoEnvio;
use App\Models\Core\Adjunto;
use App\Models\ModeloBase;
use App\Models\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Envio extends ModeloBase
{
    protected $table = 'envios';

    protected $fillable = [
        'codigo',
        'requerimiento_id',
        'almacen_origen_id',
        'almacen_destino_id',
        'fecha_envio',
        'fecha_estimada_llegada',
        'fecha_recepcion',
        'responsable_envio_id',
        'recibido_por_id',
        'estado',
        'guia_remision',
        'observaciones',
        'motivo_anulacion',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'fecha_envio' => 'date',
            'fecha_estimada_llegada' => 'date',
            'fecha_recepcion' => 'date',
            'estado' => EstadoEnvio::class,
            'activo' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Requerimiento, $this>
     */
    public function requerimiento(): BelongsTo
    {
        return $this->belongsTo(Requerimiento::class, 'requerimiento_id');
    }

    /**
     * @return BelongsTo<Almacen, $this>
     */
    public function almacenOrigen(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_origen_id');
    }

    /**
     * @return BelongsTo<Almacen, $this>
     */
    public function almacenDestino(): BelongsTo
    {
        return $this->belongsTo(Almacen::class, 'almacen_destino_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function responsableEnvio(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsable_envio_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recibidoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recibido_por_id');
    }

    /**
     * @return HasMany<EnvioDetalle, $this>
     */
    public function detalles(): HasMany
    {
        return $this->hasMany(EnvioDetalle::class, 'envio_id');
    }

    /**
     * @return MorphMany<Adjunto, $this>
     */
    public function adjuntos(): MorphMany
    {
        return $this->morphMany(Adjunto::class, 'entidad');
    }

    public function estaRetrasado(): bool
    {
        if ($this->estado !== EstadoEnvio::EN_TRANSITO || ! $this->fecha_estimada_llegada) {
            return false;
        }

        return $this->fecha_estimada_llegada->isPast() && ! $this->fecha_estimada_llegada->isToday();
    }
}
