<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use LogicException;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * Modelo base abstracto para todas las entidades de negocio.
 * Garantiza auditoría (creado_en, actualizado_en, creado_por, activitylog) y borrado lógico.
 */
abstract class ModeloBase extends Model
{
    use LogsActivity, SoftDeletes;

    public const CREATED_AT = 'creado_en';

    public const UPDATED_AT = 'actualizado_en';

    public const DELETED_AT = 'eliminado_en';

    protected $casts = [
        'activo' => 'boolean',
        'creado_en' => 'datetime',
        'actualizado_en' => 'datetime',
        'eliminado_en' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (ModeloBase $modelo): void {
            if (is_null($modelo->activo)) {
                $modelo->activo = true;
            }

            if (Auth::check()) {
                $modelo->creado_por ??= Auth::id();
                $modelo->actualizado_por ??= Auth::id();
            }
        });

        static::updating(function (ModeloBase $modelo): void {
            if (Auth::check()) {
                $modelo->actualizado_por = Auth::id();
            }
        });

        static::restoring(function (ModeloBase $modelo): void {
            $modelo->activo = true;
            if (Auth::check()) {
                $modelo->actualizado_por = Auth::id();
            }
        });
    }

    /**
     * Aplica borrado lógico sincronizando eliminado_en, activo = false y actualizado_por.
     */
    protected function runSoftDelete(): void
    {
        $query = $this->setKeysForSaveQuery($this->newModelQuery());
        $ahora = $this->freshTimestamp();

        $columnas = [
            $this->getDeletedAtColumn() => $this->fromDateTime($ahora),
            'activo' => false,
        ];

        $this->{$this->getDeletedAtColumn()} = $ahora;
        $this->activo = false;

        if ($this->usesTimestamps() && ! is_null($this->getUpdatedAtColumn())) {
            $this->{$this->getUpdatedAtColumn()} = $ahora;
            $columnas[$this->getUpdatedAtColumn()] = $this->fromDateTime($ahora);
        }

        if (Auth::check()) {
            $this->actualizado_por = Auth::id();
            $columnas['actualizado_por'] = Auth::id();
        }

        $query->update($columnas);
        $this->syncOriginalAttributes(array_keys($columnas));
        $this->fireModelEvent('trashed', false);
    }

    /**
     * Prohíbe el borrado físico en modelos de negocio según regla del proyecto.
     */
    public function forceDelete(): ?bool
    {
        throw new LogicException('El borrado físico no está permitido en datos de negocio.');
    }

    public function scopeActivos(Builder $query): Builder
    {
        return $query->where($this->getTable().'.activo', true);
    }

    public function creadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'creado_por');
    }

    public function actualizadoPor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actualizado_por');
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
