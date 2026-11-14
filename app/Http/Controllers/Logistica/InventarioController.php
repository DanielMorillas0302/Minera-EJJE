<?php

namespace App\Http\Controllers\Logistica;

use App\Enums\TipoMovimientoInventario;
use App\Http\Controllers\Controller;
use App\Models\Logistica\Almacen;
use App\Models\Logistica\CategoriaMaterial;
use App\Models\Logistica\Material;
use App\Models\Logistica\MovimientoInventario;
use App\Models\Logistica\StockAlmacen;
use App\Models\User;
use App\Services\Logistica\AlmacenScopeService;
use App\Services\Logistica\InventarioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class InventarioController extends Controller
{
    public function __construct(
        private readonly InventarioService $inventario,
        private readonly AlmacenScopeService $scope,
    ) {}

    public function stock(Request $request): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $almacenIds = $this->scope->almacenIdsPermitidos($usuario);

        $almacenId = $request->query('almacen_id');
        if ($almacenId !== null && $almacenId !== '') {
            $this->scope->asegurarAccesoAlmacen($usuario, (int) $almacenId);
        }

        $categoriaId = $request->query('categoria_id');
        $buscar = trim((string) $request->query('buscar', ''));
        $soloBajoMinimo = filter_var($request->query('bajo_minimo', false), FILTER_VALIDATE_BOOLEAN);

        $stocks = StockAlmacen::query()
            ->with(['almacen.sede', 'material.categoria', 'material.unidadMedida'])
            ->when($almacenIds !== null, static function ($query) use ($almacenIds): void {
                $query->whereIn('almacen_id', $almacenIds === [] ? [0] : $almacenIds);
            })
            ->when($almacenId, static fn ($q) => $q->where('almacen_id', (int) $almacenId))
            ->when($categoriaId, static function ($q) use ($categoriaId): void {
                $q->whereHas('material', static fn ($mq) => $mq->where('categoria_id', (int) $categoriaId));
            })
            ->when($buscar !== '', static function ($q) use ($buscar): void {
                $q->whereHas('material', static function ($mq) use ($buscar): void {
                    $mq->where('codigo', 'like', "%{$buscar}%")
                        ->orWhere('nombre', 'like', "%{$buscar}%");
                });
            })
            ->when($soloBajoMinimo, static function ($q): void {
                $q->whereHas('material', static fn ($mq) => $mq->where('stock_minimo', '>', 0))
                    ->whereRaw('stock_almacenes.cantidad <= (SELECT m.stock_minimo FROM materiales m WHERE m.id = stock_almacenes.material_id)');
            })
            ->orderBy('almacen_id')
            ->orderBy('material_id')
            ->paginate(20)
            ->withQueryString();

        $almacenes = Almacen::query()
            ->with('sede')
            ->activos()
            ->when($almacenIds !== null, static function ($query) use ($almacenIds): void {
                $query->whereIn('id', $almacenIds === [] ? [0] : $almacenIds);
            })
            ->orderBy('codigo')
            ->get();

        return Inertia::render('logistica/stock-kardex', [
            'pestanaActiva' => 'stock',
            'stocks' => $stocks,
            'movimientos' => null,
            'almacenes' => $almacenes,
            'categorias' => CategoriaMaterial::query()->activos()->orderBy('nombre')->get(),
            'materiales' => Material::query()->with('unidadMedida')->activos()->orderBy('codigo')->get(),
            'tiposMovimiento' => array_map(
                static fn (TipoMovimientoInventario $tipo): array => ['value' => $tipo->value, 'label' => $tipo->etiqueta()],
                TipoMovimientoInventario::cases()
            ),
            'filtros' => [
                'almacen_id' => $almacenId ? (int) $almacenId : null,
                'categoria_id' => $categoriaId ? (int) $categoriaId : null,
                'buscar' => $buscar,
                'bajo_minimo' => $soloBajoMinimo,
            ],
            'esRecepcionPataz' => $this->scope->esRecepcionPataz($usuario),
        ]);
    }

    public function kardex(Request $request): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $almacenIds = $this->scope->almacenIdsPermitidos($usuario);

        $almacenId = $request->query('almacen_id');
        if ($almacenId !== null && $almacenId !== '') {
            $this->scope->asegurarAccesoAlmacen($usuario, (int) $almacenId);
        }

        $materialId = $request->query('material_id');
        $tipo = $request->query('tipo');
        $desde = $request->query('desde');
        $hasta = $request->query('hasta');

        $movimientos = MovimientoInventario::query()
            ->with(['almacen', 'material.unidadMedida', 'usuario'])
            ->when($almacenIds !== null, static function ($query) use ($almacenIds): void {
                $query->whereIn('almacen_id', $almacenIds === [] ? [0] : $almacenIds);
            })
            ->when($almacenId, static fn ($q) => $q->where('almacen_id', (int) $almacenId))
            ->when($materialId, static fn ($q) => $q->where('material_id', (int) $materialId))
            ->when($tipo, static fn ($q) => $q->where('tipo', $tipo))
            ->when($desde, static fn ($q) => $q->whereDate('fecha_hora', '>=', $desde))
            ->when($hasta, static fn ($q) => $q->whereDate('fecha_hora', '<=', $hasta))
            ->latest('fecha_hora')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        $almacenes = Almacen::query()
            ->with('sede')
            ->activos()
            ->when($almacenIds !== null, static function ($query) use ($almacenIds): void {
                $query->whereIn('id', $almacenIds === [] ? [0] : $almacenIds);
            })
            ->orderBy('codigo')
            ->get();

        return Inertia::render('logistica/stock-kardex', [
            'pestanaActiva' => 'kardex',
            'stocks' => null,
            'movimientos' => $movimientos,
            'almacenes' => $almacenes,
            'categorias' => CategoriaMaterial::query()->activos()->orderBy('nombre')->get(),
            'materiales' => Material::query()->with('unidadMedida')->activos()->orderBy('codigo')->get(),
            'tiposMovimiento' => array_map(
                static fn (TipoMovimientoInventario $t): array => ['value' => $t->value, 'label' => $t->etiqueta()],
                TipoMovimientoInventario::cases()
            ),
            'filtros' => [
                'almacen_id' => $almacenId ? (int) $almacenId : null,
                'material_id' => $materialId ? (int) $materialId : null,
                'tipo' => $tipo ?: null,
                'desde' => $desde ?: null,
                'hasta' => $hasta ?: null,
            ],
            'esRecepcionPataz' => $this->scope->esRecepcionPataz($usuario),
        ]);
    }

    public function registrarMovimiento(Request $request): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        $datos = $request->validate([
            'almacen_id' => ['required', 'exists:almacenes,id'],
            'material_id' => ['required', 'exists:materiales,id'],
            'tipo' => ['required', Rule::in([TipoMovimientoInventario::ENTRADA_COMPRA->value, TipoMovimientoInventario::AJUSTE->value])],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'es_salida' => ['boolean'],
            'observacion' => ['required', 'string', 'max:500'],
        ]);

        $this->scope->asegurarAccesoAlmacen($usuario, (int) $datos['almacen_id']);

        $this->inventario->registrarIngresoOAjuste(
            almacenId: (int) $datos['almacen_id'],
            materialId: (int) $datos['material_id'],
            tipo: TipoMovimientoInventario::from($datos['tipo']),
            cantidad: (float) $datos['cantidad'],
            esSalida: (bool) ($datos['es_salida'] ?? false),
            usuario: $usuario,
            observacion: $datos['observacion'],
        );

        return back()->with('exito', 'Movimiento de inventario registrado en el kardex.');
    }

    public function registrarConsumo(Request $request): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        $datos = $request->validate([
            'almacen_id' => ['required', 'exists:almacenes,id'],
            'material_id' => ['required', 'exists:materiales,id'],
            'cantidad' => ['required', 'numeric', 'gt:0'],
            'observacion' => ['required', 'string', 'max:500'],
        ]);

        $this->scope->asegurarAccesoAlmacen(
            $usuario,
            (int) $datos['almacen_id'],
            'Solo puede registrar consumos en el almacén de su propia sede.'
        );

        $this->inventario->registrarConsumo(
            almacenId: (int) $datos['almacen_id'],
            materialId: (int) $datos['material_id'],
            cantidad: (float) $datos['cantidad'],
            usuario: $usuario,
            observacion: $datos['observacion'],
        );

        return back()->with('exito', 'Consumo registrado correctamente.');
    }
}
