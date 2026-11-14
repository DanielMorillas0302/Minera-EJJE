<?php

namespace App\Http\Controllers\Finanzas;

use App\Http\Controllers\Controller;
use App\Models\Finanzas\CentroCosto;
use App\Models\Finanzas\Presupuesto;
use App\Services\Finanzas\FinanzasService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PresupuestoController extends Controller
{
    public function __construct(
        private readonly FinanzasService $finanzasService
    ) {}

    public function index(Request $request): Response
    {
        $ahora = Carbon::now();
        $anio = (int) $request->input('anio', $ahora->year);
        $mes = (int) $request->input('mes', $ahora->month);

        $comparativo = $this->finanzasService->comparativoPresupuestoVsReal($anio, $mes);

        $centrosCosto = CentroCosto::query()
            ->with('area')
            ->where('activo', true)
            ->orderBy('codigo')
            ->get();

        // Matriz anual (12 meses) de presupuestos para el año seleccionado
        $presupuestosAnio = Presupuesto::query()
            ->where('anio', $anio)
            ->where('activo', true)
            ->get();

        $matrizAnual = $centrosCosto->map(function (CentroCosto $cc) use ($presupuestosAnio): array {
            $meses = [];
            $totalAnual = 0.0;

            for ($m = 1; $m <= 12; $m++) {
                $p = $presupuestosAnio->first(fn ($row) => $row->centro_costo_id === $cc->id && $row->mes === $m);
                $monto = (float) ($p?->monto_presupuestado ?? 0);
                $meses[$m] = $monto;
                $totalAnual += $monto;
            }

            return [
                'centro_costo_id' => $cc->id,
                'codigo' => $cc->codigo,
                'nombre' => $cc->nombre,
                'area_nombre' => $cc->area?->nombre ?? 'Sin área',
                'meses' => $meses,
                'total_anual' => round($totalAnual, 2),
            ];
        });

        return Inertia::render('finanzas/presupuestos', [
            'comparativo' => $comparativo,
            'matrizAnual' => $matrizAnual,
            'centrosCosto' => $centrosCosto->map(fn (CentroCosto $c): array => [
                'id' => $c->id,
                'codigo' => $c->codigo,
                'nombre' => $c->nombre,
            ]),
            'filtros' => [
                'anio' => $anio,
                'mes' => $mes,
            ],
            'aniosDisponibles' => range($ahora->year - 2, $ahora->year + 1),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validados = $request->validate([
            'centro_costo_id' => ['required', 'integer', 'exists:centros_costo,id'],
            'anio' => ['required', 'integer', 'min:2020', 'max:2040'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'monto_presupuestado' => ['required', 'numeric', 'min:0'],
            'notas' => ['nullable', 'string', 'max:500'],
        ], [
            'centro_costo_id.required' => 'Selecciona el centro de costo.',
            'monto_presupuestado.required' => 'Ingresa el monto presupuestado.',
            'monto_presupuestado.min' => 'El monto no puede ser negativo.',
        ]);

        $this->finanzasService->guardarPresupuesto(
            (int) $validados['centro_costo_id'],
            (int) $validados['anio'],
            (int) $validados['mes'],
            (float) $validados['monto_presupuestado'],
            $validados['notas'] ?? null
        );

        return back()->with('exito', 'Presupuesto mensual guardado con éxito.');
    }

    public function guardarLote(Request $request): RedirectResponse
    {
        $validados = $request->validate([
            'anio' => ['required', 'integer', 'min:2020', 'max:2040'],
            'mes' => ['required', 'integer', 'min:1', 'max:12'],
            'presupuestos' => ['required', 'array'],
            'presupuestos.*.centro_costo_id' => ['required', 'integer', 'exists:centros_costo,id'],
            'presupuestos.*.monto' => ['required', 'numeric', 'min:0'],
        ]);

        $anio = (int) $validados['anio'];
        $mes = (int) $validados['mes'];

        foreach ($validados['presupuestos'] as $item) {
            $this->finanzasService->guardarPresupuesto(
                (int) $item['centro_costo_id'],
                $anio,
                $mes,
                (float) $item['monto']
            );
        }

        return back()->with('exito', 'Presupuestos mensuales actualizados correctamente.');
    }
}
