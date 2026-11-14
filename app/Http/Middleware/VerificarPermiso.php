<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware granular por recurso y acción según la matriz de 06-roles-y-permisos.md.
 */
class VerificarPermiso
{
    public function handle(Request $request, Closure $next, string $recurso, string $accion = 'L'): Response
    {
        $usuario = $request->user();

        if (! $usuario) {
            return redirect()->route('login');
        }

        if (! $usuario->activo) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'Tu cuenta se encuentra desactivada. Contacta al administrador.',
            ]);
        }

        if (! $usuario->puede($recurso, $accion)) {
            abort(403, 'No tienes permisos suficientes para realizar esta acción.');
        }

        return $next($request);
    }
}
