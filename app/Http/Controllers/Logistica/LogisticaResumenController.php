<?php

namespace App\Http\Controllers\Logistica;

use App\Enums\EstadoEnvio;
use App\Enums\EstadoRequerimiento;
use App\Http\Controllers\Controller;
use App\Models\Logistica\Envio;
use App\Models\Logistica\Material;
use App\Models\Logistica\Proveedor;
use App\Models\Logistica\Requerimiento;
use App\Models\User;
use App\Services\Logistica\AlmacenScopeService;
use App\Services\Logistica\InventarioService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class LogisticaResumenController extends Controller
{
    public function __construct(
        private readonly InventarioService $inventario,
        private readonly AlmacenScopeService $scope,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $almacenIds = $this->scope->almacenIdsPermitidos($usuario);

        $alertasStock = $this->inventario->alertasStockMinimo($almacenIds)->take(15)->values();

        $enviosEnTransitoQuery = Envio::query()
            ->with(['almacenOrigen', 'almacenDestino', 'requerimiento'])
            ->where('estado', EstadoEnvio::EN_TRANSITO)
            ->when($almacenIds !== null, static function ($query) use ($almacenIds): void {
                $ids = $almacenIds === [] ? [0] : $almacenIds;
                $query->where(static function ($q) use ($ids): void {
                    $q->whereIn('almacen_origen_id', $ids)
                        ->orWhereIn('almacen_destino_id', $ids);
                });
            })
            ->orderBy('fecha_estimada_llegada');

        $enviosEnTransito = (clone $enviosEnTransitoQuery)->limit(10)->get()->map(static function (Envio $envio): array {
            return [
                'id' => $envio->id,
                'codigo' => $envio->codigo,
                'origen' => $envio->almacenOrigen?->nombre,
                'destino' => $envio->almacenDestino?->nombre,
                'fecha_envio' => $envio->fecha_envio?->format('d/m/Y'),
                'fecha_estimada_llegada' => $envio->fecha_estimada_llegada?->format('d/m/Y'),
                'retrasado' => $envio->estaRetrasado(),
                'requerimiento_codigo' => $envio->requerimiento?->codigo,
            ];
        })->values();

        $enviosRetrasadosCount = (clone $enviosEnTransitoQuery)
            ->whereDate('fecha_estimada_llegada', '<', now()->toDateString())
            ->count();

        $requerimientosPendientesQuery = Requerimiento::query()
            ->with(['area', 'sede', 'solicitante'])
            ->whereIn('estado', [
                EstadoRequerimiento::ENVIADO,
                EstadoRequerimiento::APROBADO,
                EstadoRequerimiento::ATENDIDO_PARCIAL,
            ])
            ->when($this->scope->esRecepcionPataz($usuario), static function ($query) use ($usuario): void {
                $query->where('solicitante_id', $usuario->id);
            })
            ->latest('fecha_solicitud');

        $requerimientosPendientesCount = (clone $requerimientosPendientesQuery)->count();
        $requerimientosRecientes = (clone $requerimientosPendientesQuery)->limit(8)->get();

        return Inertia::render('logistica/index', [
            'kpis' => [
                'alertas_stock_bajo' => $alertasStock->count(),
                'envios_en_transito' => (clone $enviosEnTransitoQuery)->count(),
                'envios_retrasados' => $enviosRetrasadosCount,
                'requerimientos_pendientes' => $requerimientosPendientesCount,
                'materiales_activos' => Material::query()->activos()->count(),
                'proveedores_activos' => Proveedor::query()->activos()->count(),
            ],
            'alertasStock' => $alertasStock,
            'enviosEnTransito' => $enviosEnTransito,
            'requerimientosPendientes' => $requerimientosRecientes,
            'esRecepcionPataz' => $this->scope->esRecepcionPataz($usuario),
        ]);
    }
}
