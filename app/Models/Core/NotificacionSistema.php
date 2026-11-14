<?php

namespace App\Models\Core;

use App\Enums\NivelNotificacion;
use App\Enums\TipoNotificacion;
use App\Models\ModeloBase;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificacionSistema extends ModeloBase
{
    protected $table = 'notificaciones_sistema';

    protected $fillable = [
        'tipo',
        'modulo',
        'nivel',
        'titulo',
        'mensaje',
        'url_accion',
        'leida',
        'usuario_id',
        'rol_destinatario',
        'fecha_leida',
        'activo',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'tipo' => TipoNotificacion::class,
            'nivel' => NivelNotificacion::class,
            'leida' => 'boolean',
            'fecha_leida' => 'datetime',
            'activo' => 'boolean',
        ];
    }

    public function usuario(): BelongsTo
    {
        return $this->belongsTo(User::class, 'usuario_id');
    }

    /**
     * Filtra notificaciones pertinentes para un usuario dado según su ID y rol.
     */
    public function scopeParaUsuario(Builder $query, User $usuario): Builder
    {
        return $query->where(function (Builder $q) use ($usuario): void {
            $q->where('usuario_id', $usuario->id)
                ->orWhere(function (Builder $sub) use ($usuario): void {
                    $sub->whereNull('usuario_id')
                        ->where(function (Builder $rolSub) use ($usuario): void {
                            $rolSub->whereNull('rol_destinatario')
                                ->orWhere('rol_destinatario', $usuario->rol?->value);
                        });
                });
        });
    }

    public function scopeNoLeidas(Builder $query): Builder
    {
        return $query->where('leida', false);
    }
}
