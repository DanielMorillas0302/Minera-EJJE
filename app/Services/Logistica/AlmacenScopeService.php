<?php

namespace App\Services\Logistica;

use App\Enums\RolUsuario;
use App\Models\Core\Sede;
use App\Models\Logistica\Almacen;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class AlmacenScopeService
{
    public function esRecepcionPataz(User $usuario): bool
    {
        return $usuario->rol === RolUsuario::RECEPCION_PATAZ;
    }

    /**
     * Devuelve los IDs de almacén visibles para el usuario, o null si tiene alcance global.
     *
     * @return list<int>|null
     */
    public function almacenIdsPermitidos(User $usuario): ?array
    {
        if (! $this->esRecepcionPataz($usuario)) {
            return null;
        }

        $sedeId = $usuario->sede_id;

        if (! $sedeId) {
            $sedeId = Sede::query()
                ->where('nombre', 'like', '%Pataz%')
                ->value('id');
        }

        if (! $sedeId) {
            return [];
        }

        return Almacen::query()
            ->where('sede_id', $sedeId)
            ->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->values()
            ->all();
    }

    public function puedeAccederAlmacen(User $usuario, int $almacenId): bool
    {
        $permitidos = $this->almacenIdsPermitidos($usuario);

        if ($permitidos === null) {
            return true;
        }

        return in_array($almacenId, $permitidos, true);
    }

    /**
     * @throws AuthorizationException
     */
    public function asegurarAccesoAlmacen(
        User $usuario,
        int $almacenId,
        string $mensaje = 'No tiene permiso para operar sobre un almacén fuera de su sede.',
    ): void {
        if (! $this->puedeAccederAlmacen($usuario, $almacenId)) {
            throw new AuthorizationException($mensaje);
        }
    }
}
