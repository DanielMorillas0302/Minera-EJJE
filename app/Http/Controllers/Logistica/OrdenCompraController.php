<?php

namespace App\Http\Controllers\Logistica;

use App\Enums\EstadoOrdenCompra;
use App\Enums\EstadoRequerimiento;
use App\Http\Controllers\Controller;
use App\Models\Finanzas\CategoriaGasto;
use App\Models\Finanzas\CentroCosto;
use App\Models\Logistica\Almacen;
use App\Models\Logistica\Material;
use App\Models\Logistica\OrdenCompra;
use App\Models\Logistica\Proveedor;
use App\Models\Logistica\Requerimiento;
use App\Services\Logistica\OrdenCompraService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class OrdenCompraController extends Controller
{
    public function __construct(
        private readonly OrdenCompraService $ordenCompraService
    ) {}

    public function index(Request $request): Response
    {
        $busqueda = $request->input('buscar');
        $estado = $request->input('estado');
        $proveedorId = $request->input('proveedor_id');
        $almacenId = $request->input('almacen_id');

        $ordenes = OrdenCompra::query()
            ->with(['proveedor', 'almacenDestino', 'detalles.material', 'aprobadoPor'])
            ->whereNull('eliminado_en')
            ->when($busqueda, function ($q) use ($busqueda): void {
                $q->where('codigo', 'like', "%{$busqueda}%")
                    ->orWhereHas('proveedor', fn ($pq) => $pq->where('razon_social', 'like', "%{$busqueda}%"));
            })
            ->when($estado, fn ($q) => $q->where('estado', $estado))
            ->when($proveedorId, fn ($q) => $q->where('proveedor_id', $proveedorId))
            ->when($almacenId, fn ($q) => $q->where('almacen_destino_id', $almacenId))
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString();

        $proveedores = Proveedor::query()->where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social', 'ruc']);
        $almacenes = Almacen::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'codigo']);
        $materiales = Material::query()->where('activo', true)->orderBy('nombre')->get(['id', 'nombre', 'codigo', 'precio_referencial']);
        $centrosCosto = CentroCosto::query()->where('activo', true)->orderBy('codigo')->get(['id', 'codigo', 'nombre']);
        $categoriasGasto = CategoriaGasto::query()->where('activo', true)->orderBy('codigo')->get(['id', 'codigo', 'nombre']);

        $requerimientosPendientes = Requerimiento::query()
            ->whereIn('estado', [EstadoRequerimiento::APROBADO->value, EstadoRequerimiento::ATENDIDO_PARCIAL->value])
            ->whereNull('eliminado_en')
            ->with('detalles.material')
            ->get(['id', 'codigo', 'sede_id', 'area_id']);

        return Inertia::render('logistica/ordenes-compra', [
            'ordenes' => $ordenes,
            'filtros' => [
                'buscar' => $busqueda,
                'estado' => $estado,
                'proveedor_id' => $proveedorId,
                'almacen_id' => $almacenId,
            ],
            'estados' => array_map(fn ($e) => ['id' => $e->value, 'nombre' => $e->etiqueta()], EstadoOrdenCompra::cases()),
            'proveedores' => $proveedores,
            'almacenes' => $almacenes,
            'materiales' => $materiales,
            'centrosCosto' => $centrosCosto,
            'categoriasGasto' => $categoriasGasto,
            'requerimientos' => $requerimientosPendientes,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validados = $request->validate([
            'proveedor_id' => ['required', 'exists:proveedores,id'],
            'almacen_destino_id' => ['required', 'exists:almacenes,id'],
            'requerimiento_id' => ['nullable', 'exists:requerimientos,id'],
            'fecha' => ['required', 'date'],
            'fecha_entrega_esperada' => ['nullable', 'date', 'after_or_equal:fecha'],
            'condiciones_pago' => ['nullable', 'string', 'max:100'],
            'observaciones' => ['nullable', 'string'],
            'emitir_inmediata' => ['nullable', 'boolean'],
            'detalles' => ['required', 'array', 'min:1'],
            'detalles.*.material_id' => ['required', 'exists:materiales,id'],
            'detalles.*.cantidad' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.precio_unitario' => ['required', 'numeric', 'min:0.01'],
            'detalles.*.observaciones' => ['nullable', 'string'],
        ], [
            'proveedor_id.required' => 'Selecciona un proveedor válido.',
            'almacen_destino_id.required' => 'Selecciona el almacén de destino.',
            'detalles.required' => 'Debes incluir al menos un material en la orden de compra.',
            'detalles.min' => 'Debes incluir al menos un material en la orden de compra.',
            'detalles.*.cantidad.min' => 'La cantidad debe ser mayor a cero.',
            'detalles.*.precio_unitario.min' => 'El precio unitario debe ser mayor a cero.',
        ]);

        $orden = $this->ordenCompraService->crearOrden($validados, $request->user());

        return back()->with('exito', "Orden de compra {$orden->codigo} creada exitosamente.");
    }

    public function emitir(OrdenCompra $orden, Request $request): RedirectResponse
    {
        $this->ordenCompraService->emitirOrden($orden, $request->user());

        return back()->with('exito', "Orden de compra {$orden->codigo} aprobada y emitida.");
    }

    public function recepcionar(Request $request, OrdenCompra $orden): RedirectResponse
    {
        $validados = $request->validate([
            'recepciones' => ['required', 'array', 'min:1'],
            'recepciones.*.detalle_id' => ['required', 'integer'],
            'recepciones.*.cantidad_recibir' => ['required', 'numeric', 'min:0'],
            'generar_cuenta_pagar' => ['nullable', 'boolean'],
            'comprobante_tipo' => ['nullable', 'string'],
            'comprobante_numero' => ['nullable', 'string', 'max:60'],
            'fecha_comprobante' => ['nullable', 'date'],
            'fecha_vencimiento' => ['nullable', 'date'],
            'monto' => ['nullable', 'numeric', 'min:0.01'],
            'centro_costo_id' => ['nullable', 'exists:centros_costo,id'],
            'categoria_id' => ['nullable', 'exists:categorias_gasto,id'],
        ]);

        $resultado = $this->ordenCompraService->recepcionarItems(
            $orden,
            $validados['recepciones'],
            $request->user(),
            $validados
        );

        $msg = "Recepción de ítems aplicada a la orden {$orden->codigo} e inventario actualizado.";
        if (! empty($resultado['movimiento_financiero'])) {
            $msg .= ' Se generó la cuenta por pagar en Finanzas.';
        }

        return back()->with('exito', $msg);
    }

    public function anular(Request $request, OrdenCompra $orden): RedirectResponse
    {
        $validados = $request->validate([
            'motivo' => ['required', 'string', 'min:5', 'max:255'],
        ], [
            'motivo.required' => 'Debes especificar el motivo de la anulación.',
            'motivo.min' => 'El motivo debe tener al menos 5 caracteres.',
        ]);

        $this->ordenCompraService->anularOrden($orden, $validados['motivo'], $request->user());

        return back()->with('exito', "La orden de compra {$orden->codigo} fue anulada.");
    }

    public function pdf(OrdenCompra $orden): HttpResponse
    {
        $orden->load(['proveedor', 'almacenDestino', 'detalles.material', 'aprobadoPor']);

        $pdf = Pdf::loadView('pdf.orden-compra', [
            'orden' => $orden,
        ])->setPaper('a4', 'portrait');

        return $pdf->download("orden_compra_{$orden->codigo}.pdf");
    }
}
