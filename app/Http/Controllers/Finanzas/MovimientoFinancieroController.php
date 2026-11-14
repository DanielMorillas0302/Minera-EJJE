<?php

namespace App\Http\Controllers\Finanzas;

use App\Enums\EstadoPago;
use App\Enums\TipoComprobante;
use App\Enums\TipoMovimientoFinanciero;
use App\Http\Controllers\Controller;
use App\Models\Core\Adjunto;
use App\Models\Finanzas\CategoriaGasto;
use App\Models\Finanzas\CentroCosto;
use App\Models\Finanzas\MovimientoFinanciero;
use App\Models\Logistica\Envio;
use App\Models\Logistica\Proveedor;
use App\Models\Rrhh\Trabajador;
use App\Services\Finanzas\FinanzasService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MovimientoFinancieroController extends Controller
{
    public function __construct(
        private readonly FinanzasService $finanzasService
    ) {}

    public function index(Request $request): Response
    {
        $buscar = trim((string) $request->input('buscar', ''));
        $tipo = trim((string) $request->input('tipo', ''));
        $centroCostoId = trim((string) $request->input('centro_costo_id', ''));
        $categoriaId = trim((string) $request->input('categoria_id', ''));
        $estadoPago = trim((string) $request->input('estado_pago', ''));
        $desde = trim((string) $request->input('desde', ''));
        $hasta = trim((string) $request->input('hasta', ''));

        $consulta = MovimientoFinanciero::query()
            ->with(['categoria', 'centroCosto', 'proveedor', 'envio', 'trabajador', 'adjuntos'])
            ->whereNull('eliminado_en');

        if ($buscar !== '') {
            $consulta->where(function ($q) use ($buscar): void {
                $q->where('concepto', 'like', "%{$buscar}%")
                    ->orWhere('comprobante_numero', 'like', "%{$buscar}%")
                    ->orWhereHas('proveedor', function ($qp) use ($buscar): void {
                        $qp->where('razon_social', 'like', "%{$buscar}%")
                            ->orWhere('ruc', 'like', "%{$buscar}%");
                    })
                    ->orWhereHas('trabajador', function ($qt) use ($buscar): void {
                        $qt->where('nombres', 'like', "%{$buscar}%")
                            ->orWhere('apellidos', 'like', "%{$buscar}%")
                            ->orWhere('dni', 'like', "%{$buscar}%");
                    });
            });
        }

        if ($tipo !== '') {
            $consulta->where('tipo', $tipo);
        }

        if ($centroCostoId !== '') {
            $consulta->where('centro_costo_id', (int) $centroCostoId);
        }

        if ($categoriaId !== '') {
            $consulta->where('categoria_id', (int) $categoriaId);
        }

        if ($estadoPago !== '') {
            $consulta->where('estado_pago', $estadoPago);
        }

        if ($desde !== '') {
            $consulta->whereDate('fecha', '>=', $desde);
        }

        if ($hasta !== '') {
            $consulta->whereDate('fecha', '<=', $hasta);
        }

        $movimientos = $consulta
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (MovimientoFinanciero $m): array => [
                'id' => $m->id,
                'tipo' => $m->tipo->value,
                'tipo_etiqueta' => $m->tipo->etiqueta(),
                'fecha' => $m->fecha?->format('Y-m-d'),
                'fecha_fmt' => $m->fecha?->format('d/m/Y'),
                'monto' => (float) $m->monto,
                'moneda' => $m->moneda,
                'categoria_id' => $m->categoria_id,
                'categoria_nombre' => $m->categoria?->nombre ?? '—',
                'centro_costo_id' => $m->centro_costo_id,
                'centro_costo_codigo' => $m->centroCosto?->codigo ?? '—',
                'centro_costo_nombre' => $m->centroCosto?->nombre ?? '—',
                'proveedor_id' => $m->proveedor_id,
                'proveedor_nombre' => $m->proveedor?->razon_social ?? '—',
                'concepto' => $m->concepto,
                'comprobante_tipo' => $m->comprobante_tipo->value,
                'comprobante_tipo_etiqueta' => $m->comprobante_tipo->etiqueta(),
                'comprobante_numero' => $m->comprobante_numero,
                'estado_pago' => $m->estado_pago->value,
                'estado_pago_etiqueta' => $m->estado_pago->etiqueta(),
                'fecha_vencimiento' => $m->fecha_vencimiento?->format('Y-m-d'),
                'fecha_vencimiento_fmt' => $m->fecha_vencimiento?->format('d/m/Y'),
                'fecha_pago' => $m->fecha_pago?->format('Y-m-d'),
                'fecha_pago_fmt' => $m->fecha_pago?->format('d/m/Y'),
                'envio_id' => $m->envio_id,
                'envio_codigo' => $m->envio?->codigo,
                'orden_compra_id' => $m->orden_compra_id,
                'trabajador_id' => $m->trabajador_id,
                'trabajador_nombre' => $m->trabajador ? ($m->trabajador->nombres.' '.$m->trabajador->apellidos) : null,
                'observaciones' => $m->observaciones,
                'adjuntos_count' => $m->adjuntos->count(),
                'adjuntos' => $m->adjuntos->map(fn (Adjunto $adj): array => [
                    'id' => $adj->id,
                    'nombre_original' => $adj->nombre_original,
                    'tamano' => $adj->tamano,
                    'mime_type' => $adj->mime_type,
                    'descripcion' => $adj->descripcion,
                ]),
            ]);

        $centrosCosto = CentroCosto::query()
            ->where('activo', true)
            ->orderBy('codigo')
            ->get(['id', 'codigo', 'nombre']);

        $categorias = CategoriaGasto::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo']);

        $proveedores = Proveedor::query()
            ->where('activo', true)
            ->orderBy('razon_social')
            ->get(['id', 'ruc', 'razon_social']);

        $enviosRecientes = Envio::query()
            ->orderByDesc('id')
            ->limit(20)
            ->get(['id', 'codigo', 'guia_remision', 'estado']);

        $trabajadores = Trabajador::query()
            ->where('activo', true)
            ->orderBy('apellidos')
            ->get(['id', 'dni', 'nombres', 'apellidos']);

        return Inertia::render('finanzas/movimientos', [
            'movimientos' => $movimientos,
            'centrosCosto' => $centrosCosto,
            'categorias' => $categorias,
            'proveedores' => $proveedores,
            'envios' => $enviosRecientes,
            'trabajadores' => $trabajadores,
            'tiposComprobante' => TipoComprobante::valores(),
            'estadosPago' => EstadoPago::valores(),
            'tiposMovimiento' => TipoMovimientoFinanciero::valores(),
            'filtros' => [
                'buscar' => $buscar,
                'tipo' => $tipo,
                'centro_costo_id' => $centroCostoId,
                'categoria_id' => $categoriaId,
                'estado_pago' => $estadoPago,
                'desde' => $desde,
                'hasta' => $hasta,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validados = $this->validar($request);

        $archivo = $request->file('archivo');
        $descripcion = $request->input('descripcion_adjunto');

        $this->finanzasService->guardarMovimiento($validados, $archivo, $descripcion);

        return redirect()->route('finanzas.movimientos.index')
            ->with('exito', 'Movimiento financiero registrado correctamente.');
    }

    public function update(Request $request, MovimientoFinanciero $movimiento): RedirectResponse
    {
        $validados = $this->validar($request);

        $archivo = $request->file('archivo');
        $descripcion = $request->input('descripcion_adjunto');

        $this->finanzasService->actualizarMovimiento($movimiento, $validados, $archivo, $descripcion);

        return redirect()->route('finanzas.movimientos.index')
            ->with('exito', 'Movimiento financiero actualizado correctamente.');
    }

    public function destroy(MovimientoFinanciero $movimiento): RedirectResponse
    {
        $movimiento->delete();

        return redirect()->route('finanzas.movimientos.index')
            ->with('exito', 'Movimiento financiero eliminado lógicamente.');
    }

    public function pagar(Request $request, MovimientoFinanciero $movimiento): RedirectResponse
    {
        $request->validate([
            'fecha_pago' => ['nullable', 'date'],
            'comprobante_numero' => ['nullable', 'string', 'max:60'],
        ]);

        $this->finanzasService->marcarPagado(
            $movimiento,
            $request->input('fecha_pago'),
            $request->input('comprobante_numero')
        );

        return back()->with('exito', 'El movimiento ha sido marcado como PAGADO.');
    }

    public function subirAdjunto(Request $request, MovimientoFinanciero $movimiento): RedirectResponse
    {
        $request->validate(
            [
                'archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
                'descripcion' => ['nullable', 'string', 'max:255'],
            ],
            [
                'archivo.required' => 'Selecciona un archivo PDF o imagen.',
                'archivo.mimes' => 'Solo se permiten archivos PDF, JPG o PNG.',
                'archivo.max' => 'El comprobante no debe superar los 5 MB.',
            ]
        );

        $this->finanzasService->guardarAdjunto(
            $request->file('archivo'),
            $movimiento,
            $request->input('descripcion')
        );

        return back()->with('exito', 'Comprobante adjuntado con éxito.');
    }

    public function descargarAdjunto(Adjunto $adjunto): StreamedResponse
    {
        abort_unless($adjunto->entidad_type === MovimientoFinanciero::class && $adjunto->activo, 404);
        abort_unless(Storage::disk('local')->exists($adjunto->archivo), 404);

        return Storage::disk('local')->download(
            $adjunto->archivo,
            $adjunto->nombre_original ?: basename($adjunto->archivo)
        );
    }

    public function eliminarAdjunto(Adjunto $adjunto): RedirectResponse
    {
        abort_unless($adjunto->entidad_type === MovimientoFinanciero::class, 404);
        $adjunto->delete();

        return back()->with('exito', 'Comprobante adjunto eliminado.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request): array
    {
        return $request->validate(
            [
                'tipo' => ['required', Rule::in(TipoMovimientoFinanciero::valores())],
                'fecha' => ['required', 'date'],
                'monto' => ['required', 'numeric', 'min:0.01'],
                'moneda' => ['nullable', 'string', 'max:5'],
                'categoria_id' => ['required', 'integer', 'exists:categorias_gasto,id'],
                'centro_costo_id' => ['required', 'integer', 'exists:centros_costo,id'],
                'proveedor_id' => ['nullable', 'integer', 'exists:proveedores,id'],
                'concepto' => ['required', 'string', 'max:255'],
                'comprobante_tipo' => ['required', Rule::in(TipoComprobante::valores())],
                'comprobante_numero' => ['nullable', 'string', 'max:60'],
                'estado_pago' => ['required', Rule::in(EstadoPago::valores())],
                'fecha_vencimiento' => ['nullable', 'date'],
                'fecha_pago' => ['nullable', 'date'],
                'envio_id' => ['nullable', 'integer', 'exists:envios,id'],
                'orden_compra_id' => ['nullable', 'integer'],
                'trabajador_id' => ['nullable', 'integer', 'exists:trabajadores,id'],
                'observaciones' => ['nullable', 'string', 'max:2000'],
                'archivo' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
                'descripcion_adjunto' => ['nullable', 'string', 'max:255'],
            ],
            [
                'tipo.required' => 'El tipo de movimiento (Ingreso/Egreso) es obligatorio.',
                'fecha.required' => 'La fecha del movimiento es obligatoria.',
                'monto.required' => 'El monto es obligatorio.',
                'monto.min' => 'El monto debe ser mayor a 0.',
                'categoria_id.required' => 'La categoría es obligatoria.',
                'centro_costo_id.required' => 'El centro de costo es obligatorio.',
                'concepto.required' => 'El concepto o descripción del movimiento es obligatorio.',
                'comprobante_tipo.required' => 'El tipo de comprobante es obligatorio.',
                'estado_pago.required' => 'El estado de pago es obligatorio.',
                'archivo.mimes' => 'El comprobante debe ser un archivo PDF, JPG o PNG.',
                'archivo.max' => 'El archivo no puede exceder 5 MB.',
            ]
        );
    }
}
