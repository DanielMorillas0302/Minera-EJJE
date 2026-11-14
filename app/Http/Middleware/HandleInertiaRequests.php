<?php

namespace App\Http\Middleware;

use App\Services\MatrizPermisos;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $usuario = $request->user();
        $usuario?->loadMissing('sede:id,nombre');

        return [
            ...parent::share($request),
            'name' => config('app.name'),
            'auth' => [
                'user' => $usuario ? [
                    'id' => $usuario->id,
                    'name' => $usuario->name,
                    'email' => $usuario->email,
                    'rol' => $usuario->rol?->value,
                    'rol_etiqueta' => $usuario->etiquetaRol(),
                    'sede_id' => $usuario->sede_id,
                    'sede_nombre' => $usuario->sede?->nombre,
                    'telefono' => $usuario->telefono,
                    'activo' => (bool) $usuario->activo,
                    'modulos_visibles' => $usuario->modulosVisibles(),
                    'permisos' => MatrizPermisos::permisosParaRol($usuario->rol),
                    'email_verified_at' => $usuario->email_verified_at,
                ] : null,
            ],
            'flash' => [
                'exito' => fn (): mixed => $request->session()->get('exito'),
                'error' => fn (): mixed => $request->session()->get('error'),
            ],
        ];
    }
}
