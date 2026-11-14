<?php

namespace App\Http\Controllers\Gerencia;

use App\Http\Controllers\Controller;
use App\Models\Core\Sede;
use App\Services\Gerencia\GerenciaService;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

class GerenciaDashboardController extends Controller
{
    public function __construct(
        private readonly GerenciaService $gerenciaService
    ) {}

    public function index(Request $request): Response
    {
        $ahora = Carbon::now();
        $sedeId = $request->filled('sede_id') ? (int) $request->input('sede_id') : null;
        $anio = (int) $request->input('anio', $ahora->year);
        $mes = (int) $request->input('mes', $ahora->month);

        $kpis = $this->gerenciaService->kpisGenerales($sedeId, $anio, $mes);
        $graficos = $this->gerenciaService->datosGraficos($anio, $mes, $sedeId);

        $sedes = Sede::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre', 'tipo']);

        return Inertia::render('gerencia/index', [
            'kpis' => $kpis,
            'graficos' => $graficos,
            'sedes' => $sedes,
            'filtros' => [
                'sede_id' => $sedeId,
                'anio' => $anio,
                'mes' => $mes,
            ],
            'aniosDisponibles' => range($ahora->year - 2, $ahora->year + 1),
        ]);
    }

    public function reportePdf(Request $request): HttpResponse
    {
        $ahora = Carbon::now();
        $sedeId = $request->filled('sede_id') ? (int) $request->input('sede_id') : null;
        $anio = (int) $request->input('anio', $ahora->year);
        $mes = (int) $request->input('mes', $ahora->month);

        $datos = $this->gerenciaService->reporteEjecutivo($anio, $mes, $sedeId);

        $pdf = Pdf::loadView('pdf.gerencial', [
            'datos' => $datos,
        ])->setPaper('a4', 'portrait');

        $nombreArchivo = "informe_ejecutivo_gerencial_{$anio}_{$mes}.pdf";

        return $pdf->download($nombreArchivo);
    }
}
