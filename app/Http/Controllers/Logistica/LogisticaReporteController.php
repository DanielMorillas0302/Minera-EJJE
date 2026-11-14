<?php

namespace App\Http\Controllers\Logistica;

use App\Enums\EstadoEnvio;
use App\Enums\EstadoRequerimiento;
use App\Exports\TablaReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Logistica\Almacen;
use App\Models\Logistica\Envio;
use App\Models\Logistica\EnvioDetalle;
use App\Models\Logistica\Material;
use App\Models\Logistica\MovimientoInventario;
use App\Models\Logistica\Requerimiento;
use App\Models\Logistica\StockAlmacen;
use App\Models\User;
use App\Services\Logistica\AlmacenScopeService;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class LogisticaReporteController extends Controller
{
    public function __construct(
        private readonly AlmacenScopeService $scope,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $filtros = $this->extraerFiltros($request, $usuario);

        $stockActual = $this->consultaStock($filtros, $usuario)->limit(25)->get();
        $enviosResumen = $this->consultaEnviosPorEstado($filtros, $usuario);
        $diferencias = $this->consultaDiferenciasRecepcion($filtros, $usuario)->limit(25)->get();
        $reqPendientes = $this->consultaRequerimientosPendientes($usuario)->limit(25)->get();
        $kardexMovimientos = $this->consultaKardex($filtros, $usuario)->limit(25)->get();

        $almacenIds = $this->scope->almacenIdsPermitidos($usuario);

        return Inertia::render('logistica/reportes', [
            'filtros' => [
                'almacen_id' => $filtros['almacen_id'],
                'material_id' => $filtros['material_id'],
                'desde' => $filtros['desde'],
                'hasta' => $filtros['hasta'],
            ],
            'almacenes' => Almacen::query()
                ->with('sede')
                ->activos()
                ->when($almacenIds !== null, static fn ($q) => $q->whereIn('id', $almacenIds === [] ? [0] : $almacenIds))
                ->orderBy('codigo')
                ->get(),
            'materiales' => Material::query()->activos()->orderBy('codigo')->get(),
            'stockActual' => $stockActual,
            'enviosPorEstado' => $enviosResumen,
            'diferenciasRecepcion' => $diferencias,
            'requerimientosPendientes' => $reqPendientes,
            'kardexReciente' => $kardexMovimientos,
        ]);
    }

    public function exportarExcel(Request $request): BinaryFileResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $tipo = (string) $request->query('tipo', 'stock');
        $filtros = $this->extraerFiltros($request, $usuario);

        [$encabezados, $filas, $archivo] = match ($tipo) {
            'kardex' => $this->armarExportKardex($filtros, $usuario),
            'diferencias' => $this->armarExportDiferencias($filtros, $usuario),
            'envios' => $this->armarExportEnvios($filtros, $usuario),
            'requerimientos' => $this->armarExportRequerimientos($usuario),
            default => $this->armarExportStock($filtros, $usuario),
        };

        return Excel::download(new TablaReporteExport($encabezados, $filas), $archivo);
    }

    public function kardexPdf(Request $request): HttpResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $filtros = $this->extraerFiltros($request, $usuario);

        $movimientos = $this->consultaKardex($filtros, $usuario)->limit(250)->get();

        $partesFiltro = [];
        if ($filtros['almacen_id']) {
            $partesFiltro[] = 'Almacén #'.$filtros['almacen_id'];
        }
        if ($filtros['material_id']) {
            $partesFiltro[] = 'Material #'.$filtros['material_id'];
        }
        if ($filtros['desde']) {
            $partesFiltro[] = 'Desde '.$filtros['desde'];
        }
        if ($filtros['hasta']) {
            $partesFiltro[] = 'Hasta '.$filtros['hasta'];
        }

        $pdf = Pdf::loadView('pdf.kardex', [
            'movimientos' => $movimientos,
            'fechaEmision' => now()->format('d/m/Y H:i'),
            'filtrosTexto' => implode(' · ', $partesFiltro),
        ])->setPaper('a4', 'landscape');

        return $pdf->download('kardex-logistica.pdf');
    }

    /**
     * @return array{almacen_id: int|null, material_id: int|null, desde: string|null, hasta: string|null}
     */
    private function extraerFiltros(Request $request, User $usuario): array
    {
        $almacenId = $request->query('almacen_id');
        if ($almacenId !== null && $almacenId !== '') {
            $this->scope->asegurarAccesoAlmacen($usuario, (int) $almacenId);
        }

        $materialId = $request->query('material_id');

        return [
            'almacen_id' => $almacenId ? (int) $almacenId : null,
            'material_id' => $materialId ? (int) $materialId : null,
            'desde' => $request->query('desde') ?: null,
            'hasta' => $request->query('hasta') ?: null,
        ];
    }

    /**
     * @param  array{almacen_id: int|null, material_id: int|null, desde: string|null, hasta: string|null}  $filtros
     */
    private function consultaStock(array $filtros, User $usuario)
    {
        $almacenIds = $this->scope->almacenIdsPermitidos($usuario);

        return StockAlmacen::query()
            ->with(['almacen.sede', 'material.categoria', 'material.unidadMedida'])
            ->when($almacenIds !== null, static fn ($q) => $q->whereIn('almacen_id', $almacenIds === [] ? [0] : $almacenIds))
            ->when($filtros['almacen_id'], static fn ($q) => $q->where('almacen_id', $filtros['almacen_id']))
            ->when($filtros['material_id'], static fn ($q) => $q->where('material_id', $filtros['material_id']))
            ->orderBy('almacen_id')
            ->orderBy('material_id');
    }

    /**
     * @param  array{almacen_id: int|null, material_id: int|null, desde: string|null, hasta: string|null}  $filtros
     */
    private function consultaKardex(array $filtros, User $usuario)
    {
        $almacenIds = $this->scope->almacenIdsPermitidos($usuario);

        return MovimientoInventario::query()
            ->with(['almacen', 'material.unidadMedida', 'usuario'])
            ->when($almacenIds !== null, static fn ($q) => $q->whereIn('almacen_id', $almacenIds === [] ? [0] : $almacenIds))
            ->when($filtros['almacen_id'], static fn ($q) => $q->where('almacen_id', $filtros['almacen_id']))
            ->when($filtros['material_id'], static fn ($q) => $q->where('material_id', $filtros['material_id']))
            ->when($filtros['desde'], static fn ($q) => $q->whereDate('fecha_hora', '>=', $filtros['desde']))
            ->when($filtros['hasta'], static fn ($q) => $q->whereDate('fecha_hora', '<=', $filtros['hasta']))
            ->latest('fecha_hora')
            ->latest('id');
    }

    /**
     * @param  array{almacen_id: int|null, material_id: int|null, desde: string|null, hasta: string|null}  $filtros
     * @return list<array{estado: string, etiqueta: string, total: int}>
     */
    private function consultaEnviosPorEstado(array $filtros, User $usuario): array
    {
        $almacenIds = $this->scope->almacenIdsPermitidos($usuario);

        $conteos = Envio::query()
            ->when($almacenIds !== null, static function ($query) use ($almacenIds): void {
                $ids = $almacenIds === [] ? [0] : $almacenIds;
                $query->where(static function ($q) use ($ids): void {
                    $q->whereIn('almacen_origen_id', $ids)->orWhereIn('almacen_destino_id', $ids);
                });
            })
            ->when($filtros['desde'], static fn ($q) => $q->whereDate('creado_en', '>=', $filtros['desde']))
            ->when($filtros['hasta'], static fn ($q) => $q->whereDate('creado_en', '<=', $filtros['hasta']))
            ->selectRaw('estado, COUNT(*) as total')
            ->groupBy('estado')
            ->pluck('total', 'estado');

        return array_map(static fn (EstadoEnvio $estado): array => [
            'estado' => $estado->value,
            'etiqueta' => $estado->etiqueta(),
            'total' => (int) ($conteos[$estado->value] ?? 0),
        ], EstadoEnvio::cases());
    }

    /**
     * @param  array{almacen_id: int|null, material_id: int|null, desde: string|null, hasta: string|null}  $filtros
     */
    private function consultaDiferenciasRecepcion(array $filtros, User $usuario)
    {
        $almacenIds = $this->scope->almacenIdsPermitidos($usuario);

        return EnvioDetalle::query()
            ->with(['envio.almacenOrigen', 'envio.almacenDestino', 'material.unidadMedida'])
            ->whereNotNull('cantidad_recibida')
            ->whereRaw('ABS(cantidad_enviada - cantidad_recibida) > 0.0005')
            ->whereHas('envio', static function ($q) use ($almacenIds, $filtros): void {
                if ($almacenIds !== null) {
                    $ids = $almacenIds === [] ? [0] : $almacenIds;
                    $q->where(static function ($sub) use ($ids): void {
                        $sub->whereIn('almacen_origen_id', $ids)->orWhereIn('almacen_destino_id', $ids);
                    });
                }
                if ($filtros['desde']) {
                    $q->whereDate('fecha_recepcion', '>=', $filtros['desde']);
                }
                if ($filtros['hasta']) {
                    $q->whereDate('fecha_recepcion', '<=', $filtros['hasta']);
                }
            })
            ->latest('id');
    }

    private function consultaRequerimientosPendientes(User $usuario)
    {
        return Requerimiento::query()
            ->with(['area', 'sede', 'solicitante'])
            ->whereIn('estado', [
                EstadoRequerimiento::ENVIADO,
                EstadoRequerimiento::APROBADO,
                EstadoRequerimiento::ATENDIDO_PARCIAL,
            ])
            ->when($this->scope->esRecepcionPataz($usuario), static fn ($q) => $q->where('solicitante_id', $usuario->id))
            ->latest('fecha_solicitud');
    }

    /**
     * @param  array{almacen_id: int|null, material_id: int|null, desde: string|null, hasta: string|null}  $filtros
     * @return array{0: list<string>, 1: list<list<mixed>>, 2: string}
     */
    private function armarExportStock(array $filtros, User $usuario): array
    {
        $filas = $this->consultaStock($filtros, $usuario)->get()->map(static fn (StockAlmacen $s): array => [
            $s->almacen?->nombre,
            $s->material?->codigo,
            $s->material?->nombre,
            $s->material?->categoria?->nombre,
            $s->material?->unidadMedida?->abreviatura,
            (float) $s->cantidad,
            (float) ($s->material?->stock_minimo ?? 0),
            (float) $s->cantidad <= (float) ($s->material?->stock_minimo ?? 0) ? 'BAJO MÍNIMO' : 'OK',
        ])->values()->all();

        return [
            ['Almacén', 'Código', 'Material', 'Categoría', 'Unidad', 'Stock Actual', 'Stock Mínimo', 'Alerta'],
            $filas,
            'reporte-stock-actual.xlsx',
        ];
    }

    /**
     * @param  array{almacen_id: int|null, material_id: int|null, desde: string|null, hasta: string|null}  $filtros
     * @return array{0: list<string>, 1: list<list<mixed>>, 2: string}
     */
    private function armarExportKardex(array $filtros, User $usuario): array
    {
        $filas = $this->consultaKardex($filtros, $usuario)->get()->map(static fn (MovimientoInventario $m): array => [
            $m->fecha_hora?->format('d/m/Y H:i'),
            $m->almacen?->nombre,
            $m->material?->codigo,
            $m->material?->nombre,
            $m->tipo?->etiqueta() ?? $m->tipo,
            (float) $m->cantidad,
            (float) $m->saldo_resultante,
            $m->usuario?->name,
            $m->observacion,
        ])->values()->all();

        return [
            ['Fecha/Hora', 'Almacén', 'Código', 'Material', 'Tipo Movimiento', 'Cantidad', 'Saldo Resultante', 'Usuario', 'Observación'],
            $filas,
            'reporte-kardex.xlsx',
        ];
    }

    /**
     * @param  array{almacen_id: int|null, material_id: int|null, desde: string|null, hasta: string|null}  $filtros
     * @return array{0: list<string>, 1: list<list<mixed>>, 2: string}
     */
    private function armarExportDiferencias(array $filtros, User $usuario): array
    {
        $filas = $this->consultaDiferenciasRecepcion($filtros, $usuario)->get()->map(static fn (EnvioDetalle $d): array => [
            $d->envio?->codigo,
            $d->envio?->fecha_recepcion?->format('d/m/Y'),
            $d->envio?->almacenOrigen?->nombre,
            $d->envio?->almacenDestino?->nombre,
            $d->material?->codigo,
            $d->material?->nombre,
            (float) $d->cantidad_enviada,
            (float) $d->cantidad_recibida,
            round((float) $d->cantidad_recibida - (float) $d->cantidad_enviada, 3),
            $d->observacion_recepcion,
        ])->values()->all();

        return [
            ['Código Envío', 'Fecha Recepción', 'Origen', 'Destino', 'Código Material', 'Material', 'Enviado', 'Recibido', 'Diferencia', 'Observación'],
            $filas,
            'reporte-diferencias-recepcion.xlsx',
        ];
    }

    /**
     * @param  array{almacen_id: int|null, material_id: int|null, desde: string|null, hasta: string|null}  $filtros
     * @return array{0: list<string>, 1: list<list<mixed>>, 2: string}
     */
    private function armarExportEnvios(array $filtros, User $usuario): array
    {
        $filas = array_map(static fn (array $r): array => [
            $r['etiqueta'],
            $r['total'],
        ], $this->consultaEnviosPorEstado($filtros, $usuario));

        return [
            ['Estado de Envío', 'Cantidad'],
            $filas,
            'reporte-envios-estado.xlsx',
        ];
    }

    /**
     * @return array{0: list<string>, 1: list<list<mixed>>, 2: string}
     */
    private function armarExportRequerimientos(User $usuario): array
    {
        $filas = $this->consultaRequerimientosPendientes($usuario)->get()->map(static fn (Requerimiento $r): array => [
            $r->codigo,
            $r->fecha_solicitud?->format('d/m/Y'),
            $r->area?->nombre,
            $r->sede?->nombre,
            $r->solicitante?->name,
            $r->prioridad?->etiqueta(),
            $r->estado?->etiqueta(),
        ])->values()->all();

        return [
            ['Código', 'Fecha', 'Área', 'Sede', 'Solicitante', 'Prioridad', 'Estado'],
            $filas,
            'reporte-requerimientos-pendientes.xlsx',
        ];
    }
}
