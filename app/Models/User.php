<?php

namespace App\Models;

use App\Enums\RolUsuario;
use App\Models\Core\Sede;
use App\Services\MatrizPermisos;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Models\Role;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasRoles, LogsActivity, Notifiable, SoftDeletes;

    public const DELETED_AT = 'eliminado_en';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'rol',
        'sede_id',
        'telefono',
        'activo',
        'intentos_fallidos',
        'bloqueado_hasta',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'rol' => RolUsuario::class,
            'activo' => 'boolean',
            'intentos_fallidos' => 'integer',
            'bloqueado_hasta' => 'datetime',
            'eliminado_en' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (User $usuario): void {
            if ($usuario->wasRecentlyCreated || $usuario->wasChanged('rol')) {
                $usuario->sincronizarRolSpatie();
            }
        });
    }

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

        $query->update($columnas);
        $this->syncOriginalAttributes(array_keys($columnas));
        $this->fireModelEvent('trashed', false);
    }

    public function sede(): BelongsTo
    {
        return $this->belongsTo(Sede::class, 'sede_id');
    }

    public function sincronizarRolSpatie(): void
    {
        if (! $this->rol) {
            $this->syncRoles([]);

            return;
        }

        Role::findOrCreate($this->rol->value, 'web');
        $this->syncRoles([$this->rol->value]);
    }

    public function tieneRol(RolUsuario|string ...$roles): bool
    {
        if (! $this->activo) {
            return false;
        }

        foreach ($roles as $rol) {
            $valor = $rol instanceof RolUsuario ? $rol->value : $rol;
            if ($this->rol?->value === $valor) {
                return true;
            }
        }

        return false;
    }

    public function puede(string $recurso, string $accion): bool
    {
        return MatrizPermisos::puede($this, $recurso, $accion);
    }

    public function esAdministrador(): bool
    {
        return $this->tieneRol(RolUsuario::ADMIN);
    }

    public function etiquetaRol(): string
    {
        return $this->rol?->etiqueta() ?? 'Sin rol asignado';
    }

    /**
     * @return list<string>
     */
    public function modulosVisibles(): array
    {
        if (! $this->activo) {
            return [];
        }

        return $this->rol?->modulosVisibles() ?? ['inicio'];
    }

    public function estaBloqueado(): bool
    {
        return $this->bloqueado_hasta !== null && $this->bloqueado_hasta->isFuture();
    }

    public function registrarIntentoFallido(): int
    {
        $this->intentos_fallidos++;
        if ($this->intentos_fallidos >= 5) {
            $this->bloqueado_hasta = now()->addMinutes(15);
            $this->intentos_fallidos = 0;
        }
        $this->saveQuietly();

        return $this->intentos_fallidos;
    }

    public function reiniciarIntentosFallidos(): void
    {
        if ($this->intentos_fallidos > 0 || $this->bloqueado_hasta !== null) {
            $this->intentos_fallidos = 0;
            $this->bloqueado_hasta = null;
            $this->saveQuietly();
        }
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['name', 'email', 'rol', 'sede_id', 'telefono', 'activo'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
