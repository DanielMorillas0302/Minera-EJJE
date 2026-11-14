<?php

namespace App\Http\Controllers\Logistica;

use App\Enums\EstadoEnvio;
use App\Enums\EstadoRequerimiento;
use App\Exports\TablaReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Core\Adjunto;
use App\Models\Logistica\Almacen;
use App\Models\Logistica\Envio;
use App\Models\Logistica\Material;
use App\Models\Logistica\MovimientoInventario;
use App\Models\Logistica\Requerimiento;
use App\Models\User;
use App\Services\Logistica\AlmacenScopeService;
use App\Services\Logistica\EnvioService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EnvioController extends Controller
{
    public function __construct(
        private readonly EnvioService $envios,
        private readonly AlmacenScopeService $scope,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $almacenIds = $this->scope->almacenIdsPermitidos($usuario);

        $estado = $request->query('estado');
        $almacenOrigenId = $request->query('almacen_origen_id');
        $almacenDestinoId = $request->query('almacen_destino_id');
        $soloRetrasados = filter_var($request->query('retrasados', false), FILTER_VALIDATE_BOOLEAN);
        $buscar = trim((string) $request->query('buscar', ''));

        $lista = Envio::query()
            ->with(['almacenOrigen', 'almacenDestino', 'requerimiento', 'responsableEnvio', 'recibidoPor'])
            ->withCount('detalles')
            ->when($almacenIds !== null, static function ($query) use ($almacenIds): void {
                $ids = $almacenIds === [] ? [0] : $almacenIds;
                $query->where(static function ($q) use ($ids): void {
                    $q->whereIn('almacen_origen_id', $ids)
                        ->orWhereIn('almacen_destino_id', $ids);
                });
            })
            ->when($estado, static fn ($q) => $q->where('estado', $estado))
            ->when($almacenOrigenId, static fn ($q) => $q->where('almacen_origen_id', (int) $almacenOrigenId))
            ->when($almacenDestinoId, static fn ($q) => $q->where('almacen_destino_id', (int) $almacenDestinoId))
            ->when($soloRetrasados, static function ($q): void {
                $q->where('estado', EstadoEnvio::EN_TRANSITO)
                    ->whereDate('fecha_estimada_llegada', '<', now()->toDateString());
            })
            ->when($buscar !== '', static function ($q) use ($buscar): void {
                $q->where(static function ($sub) use ($buscar): void {
                    $sub->where('codigo', 'like', "%{$buscar}%")
                        ->orWhere('guia_remision', 'like', "%{$buscar}%");
                });
            })
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(static function (Envio $envio): array {
                return [
                    'id' => $envio->id,
                    'codigo' => $envio->codigo,
                    'estado' => $envio->estado->value,
                    'estado_etiqueta' => $envio->estado->etiqueta(),
                    'almacen_origen' => $envio->almacenOrigen,
                    'almacen_destino' => $envio->almacenDestino,
                    'requerimiento' => $envio->requerimiento,
                    'fecha_envio' => $envio->fecha_envio?->format('Y-m-d'),
                    'fecha_estimada_llegada' => $envio->fecha_estimada_llegada?->format('Y-m-d'),
                    'fecha_recepcion' => $envio->fecha_recepcion?->format('Y-m-d'),
                    'guia_remision' => $envio->guia_remision,
                    'detalles_count' => $envio->detalles_count,
                    'responsable_envio' => $envio->responsableEnvio,
                    'recibido_por' => $envio->recibidoPor,
                    'retrasado' => $envio->estaRetrasado(),
                ];
            });

        $requerimientosDisponibles = Requerimiento::query()
            ->with(['detalles.material.unidadMedida', 'sede', 'area'])
            ->whereIn('estado', [EstadoRequerimiento::APROBADO, EstadoRequerimiento::ATENDIDO_PARCIAL])
            ->orderBy('codigo')
            ->get();

        return Inertia::render('logistica/envios', [
            'envios' => $lista,
            'almacenes' => Almacen::query()->with('sede')->activos()->orderBy('codigo')->get(),
            'materiales' => Material::query()->with('unidadMedida')->activos()->orderBy('codigo')->get(),
            'requerimientosDisponibles' => $requerimientosDisponibles,
            'estados' => array_map(
                static fn (EstadoEnvio $e): array => ['value' => $e->value, 'label' => $e->etiqueta()],
                EstadoEnvio::cases()
            ),
            'filtros' => [
                'estado' => $estado ?: null,
                'almacen_origen_id' => $almacenOrigenId ? (int) $almacenOrigenId : null,
                'almacen_destino_id' => $almacenDestinoId ? (int) $almacenDestinoId : null,
                'retrasados' => $soloRetrasados,
                'buscar' => $buscar,
            ],
            'esRecepcionPataz' => $this->scope->esRecepcionPataz($usuario),
        ]);
    }

    public function show(Request $request, Envio $envio): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();

        if ($this->scope->esRecepcionPataz($usuario)) {
            $permitido = $this->scope->puedeAccederAlmacen($usuario, $envio->almacen_origen_id)
                || $this->scope->puedeAccederAlmacen($usuario, $envio->almacen_destino_id);
            abort_unless($permitido, 403, 'No tiene permiso para visualizar envíos de otros almacenes.');
        }

        $envio->load([
            'almacenOrigen.sede',
            'almacenDestino.sede',
            'requerimiento.area',
            'responsableEnvio',
            'recibidoPor',
            'detalles.material.unidadMedida',
            'detalles.requerimientoDetalle',
            'adjuntos',
        ]);

        $movimientos = MovimientoInventario::query()
            ->with(['almacen', 'material', 'usuario'])
            ->where('documento_tipo', Envio::class)
            ->where('documento_id', $envio->id)
            ->orderBy('id')
            ->get();

        $puedeRecibirEsteAlmacen = $this->scope->puedeAccederAlmacen($usuario, $envio->almacen_destino_id);

        return Inertia::render('logistica/envio-detalle', [
            'envio' => $envio,
            'retrasado' => $envio->estaRetrasado(),
            'movimientos' => $movimientos,
            'puedeRecibirEnDestino' => $puedeRecibirEsteAlmacen,
            'esRecepcionPataz' => $this->scope->esRecepcionPataz($usuario),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        $datos = $request->validate([
            'requerimiento_id' => ['nullable', 'exists:requerimientos,id'],
            'almacen_origen_id' => ['required', 'exists:almacenes,id'],
            'almacen_destino_id' => ['required', 'exists:almacenes,id', 'different:almacen_origen_id'],
            'fecha_envio' => ['nullable', 'date'],
            'fecha_estimada_llegada' => ['nullable', 'date'],
            'guia_remision' => ['nullable', 'string', 'max:60'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'despachar' => ['boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'distinct', 'exists:materiales,id'],
            'items.*.cantidad_enviada' => ['required', 'numeric', 'gt:0'],
            'items.*.requerimiento_detalle_id' => ['nullable', 'exists:requerimiento_detalles,id'],
        ], [
            'almacen_destino_id.different' => 'El almacén de destino debe ser distinto al de origen.',
            'items.min' => 'Debe agregar al menos un material al envío.',
        ]);

        $envio = $this->envios->crear(
            datos: $datos,
            items: $datos['items'],
            responsable: $usuario,
            despacharInmediatamente: (bool) ($datos['despachar'] ?? false),
        );

        return redirect()
            ->route('logistica.envios.show', $envio)
            ->with('exito', "Envío {$envio->codigo} registrado correctamente.");
    }

    public function despachar(Request $request, Envio $envio): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        $datos = $request->validate([
            'fecha_envio' => ['nullable', 'date'],
            'fecha_estimada_llegada' => ['nullable', 'date'],
            'guia_remision' => ['nullable', 'string', 'max:60'],
        ]);

        $this->envios->despachar(
            envio: $envio,
            usuario: $usuario,
            fechaEnvio: $datos['fecha_envio'] ?? null,
            fechaEstimada: $datos['fecha_estimada_llegada'] ?? null,
            guiaRemision: $datos['guia_remision'] ?? null,
        );

        return back()->with('exito', "Envío {$envio->codigo} despachado (EN TRÁNSITO). Stock descontado en origen.");
    }

    public function recibir(Request $request, Envio $envio): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        $this->scope->asegurarAccesoAlmacen(
            $usuario,
            $envio->almacen_destino_id,
            'Solo puede confirmar recepción de envíos destinados a su propio almacén.'
        );

        $datos = $request->validate([
            'fecha_recepcion' => ['required', 'date'],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.detalle_id' => ['required', 'exists:envio_detalles,id'],
            'items.*.cantidad_recibida' => ['required', 'numeric', 'min:0'],
            'items.*.observacion_recepcion' => ['nullable', 'string', 'max:255'],
            'guia_adjunto' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
        ]);

        $recepciones = [];
        foreach ($datos['items'] as $item) {
            $recepciones[(int) $item['detalle_id']] = [
                'cantidad_recibida' => $item['cantidad_recibida'],
                'observacion_recepcion' => $item['observacion_recepcion'] ?? null,
            ];
        }

        $actualizado = $this->envios->recibir(
            envio: $envio,
            usuario: $usuario,
            recepcionesPorDetalleId: $recepciones,
            fechaRecepcion: $datos['fecha_recepcion'],
            observaciones: $datos['observaciones'] ?? null,
        );

        if ($request->hasFile('guia_adjunto')) {
            $this->guardarGuiaAdjunto($request->file('guia_adjunto'), $actualizado);
        }

        $mensaje = $actualizado->estado === EstadoEnvio::RECIBIDO_CON_OBSERVACIONES
            ? "Recepción registrada con diferencias ({$actualizado->codigo})."
            : "Envío {$actualizado->codigo} recibido conforme en destino.";

        return back()->with('exito', $mensaje);
    }

    public function anular(Request $request, Envio $envio): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        $datos = $request->validate([
            'motivo_anulacion' => ['required', 'string', 'max:500'],
        ], [
            'motivo_anulacion.required' => 'Debe ingresar el motivo de la anulación.',
        ]);

        $this->envios->anular($envio, $usuario, $datos['motivo_anulacion']);

        return back()->with('exito', "Envío {$envio->codigo} anulado correctamente.");
    }

    public function subirAdjunto(Request $request, Envio $envio): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        if ($this->scope->esRecepcionPataz($usuario)) {
            $this->scope->asegurarAccesoAlmacen($usuario, $envio->almacen_destino_id);
        }

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:5120'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        $this->guardarGuiaAdjunto(
            $request->file('archivo'),
            $envio,
            $request->input('descripcion', 'Guía de remisión / sustento de recepción')
        );

        return back()->with('exito', 'Documento adjunto guardado correctamente.');
    }

    public function descargarAdjunto(Request $request, Envio $envio, Adjunto $adjunto): StreamedResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        if ($this->scope->esRecepcionPataz($usuario)) {
            $permitido = $this->scope->puedeAccederAlmacen($usuario, $envio->almacen_origen_id)
                || $this->scope->puedeAccederAlmacen($usuario, $envio->almacen_destino_id);
            abort_unless($permitido, 403);
        }

        abort_unless(
            $adjunto->entidad_type === Envio::class && (int) $adjunto->entidad_id === (int) $envio->id,
            404
        );

        return Storage::disk('local')->download($adjunto->archivo, $adjunto->nombre_original);
    }

    public function plantillaRecepcion(): BinaryFileResponse
    {
        $encabezados = ['codigo_envio', 'codigo_material', 'cantidad_recibida', 'observacion'];
        $ejemplo = [
            ['ENV-2026-0001', 'MAT-001', '10.000', 'Recepción conforme en Pataz'],
        ];

        return Excel::download(
            new TablaReporteExport($encabezados, $ejemplo),
            'plantilla-recepcion-pataz.xlsx'
        );
    }

    private function guardarGuiaAdjunto(UploadedFile $archivo, Envio $envio, ?string $descripcion = null): void
    {
        $ruta = Storage::disk('local')->putFile('adjuntos/envios', $archivo);

        Adjunto::query()->create([
            'archivo' => $ruta,
            'nombre_original' => $archivo->getClientOriginalName(),
            'mime_type' => $archivo->getClientMimeType(),
            'tamano' => $archivo->getSize(),
            'descripcion' => $descripcion ?? 'Guía de remisión',
            'entidad_type' => Envio::class,
            'entidad_id' => $envio->id,
            'activo' => true,
        ]);
    }
}
