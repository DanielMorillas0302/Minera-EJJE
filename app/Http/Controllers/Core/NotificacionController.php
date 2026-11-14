<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Services\Core\NotificacionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NotificacionController extends Controller
{
    public function __construct(
        private readonly NotificacionService $notificacionService
    ) {}

    public function index(Request $request): Response
    {
        $usuario = $request->user();
        $soloNoLeidas = $request->boolean('solo_no_leidas');

        // Sincronizar alertas operativas antes de mostrar
        $this->notificacionService->sincronizarAlertasAutomaticas();

        $notificaciones = $this->notificacionService->obtenerParaUsuario($usuario, $soloNoLeidas, 50);
        $totalNoLeidas = $this->notificacionService->contarNoLeidas($usuario);

        return Inertia::render('core/notificaciones', [
            'notificaciones' => $notificaciones,
            'totalNoLeidas' => $totalNoLeidas,
            'filtros' => [
                'solo_no_leidas' => $soloNoLeidas,
            ],
        ]);
    }

    public function noLeidas(Request $request): JsonResponse
    {
        $usuario = $request->user();
        $notificaciones = $this->notificacionService->obtenerParaUsuario($usuario, true, 5);
        $totalNoLeidas = $this->notificacionService->contarNoLeidas($usuario);

        return response()->json([
            'total_no_leidas' => $totalNoLeidas,
            'items' => $notificaciones,
        ]);
    }

    public function marcarLeida(Request $request, int $id): RedirectResponse
    {
        $this->notificacionService->marcarComoLeida($id, $request->user());

        return back()->with('exito', 'Notificación marcada como leída.');
    }

    public function marcarTodasLeidas(Request $request): RedirectResponse
    {
        $cantidad = $this->notificacionService->marcarTodasComoLeidas($request->user());

        return back()->with('exito', "Se marcaron {$cantidad} notificaciones como leídas.");
    }
}
