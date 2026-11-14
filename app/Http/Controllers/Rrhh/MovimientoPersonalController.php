<?php

namespace App\Http\Controllers\Rrhh;

use App\Enums\EstadoTrabajador;
use App\Enums\RolUsuario;
use App\Enums\TipoMovimientoPersonal;
use App\Http\Controllers\Controller;
use App\Models\Core\Sede;
use App\Models\Rrhh\MovimientoPersonal;
use App\Models\Rrhh\Trabajador;
use App\Services\Rrhh\MovimientoPersonalService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class MovimientoPersonalController extends Controller
{
    public function index(Request $request): Response
    {
        $usuario = $request->user();
        $esMedicoAutorizado = $usuario->tieneRol(RolUsuario::ADMIN, RolUsuario::RRHH_JEFE, RolUsuario::RRHH_ASISTENTE);

        $buscar = trim((string) $request->query('q', ''));
        $tipo = (string) $request->query('tipo', '');
        $trabajadorId = (string) $request->query('trabajador_id', '');
        $desde = (string) $request->query('desde', '');
        $hasta = (string) $request->query('hasta', '');

        $consulta = MovimientoPersonal::query()
            ->activos()
            ->with([
                'trabajador:id,dni,nombres,apellidos,cargo_id,ubicacion_actual',
                'trabajador.cargo:id,nombre',
                'origenSede:id,nombre',
                'destinoSede:id,nombre',
                'registradoPor:id,name',
            ]);

        if ($buscar !== '') {
            $consulta->where(function ($q) use ($buscar): void {
                $q->where('transporte', 'like', "%{$buscar}%")
                    ->orWhereHas('trabajador', function ($tw) use ($buscar): void {
                        $tw->where('dni', 'like', "%{$buscar}%")
                            ->orWhere('nombres', 'like', "%{$buscar}%")
                            ->orWhere('apellidos', 'like', "%{$buscar}%");
                    });
            });
        }

        if ($tipo !== '') {
            $consulta->where('tipo', $tipo);
        }

        if ($trabajadorId !== '') {
            $consulta->where('trabajador_id', (int) $trabajadorId);
        }

        if ($desde !== '') {
            $consulta->whereDate('fecha_hora', '>=', $desde);
        }

        if ($hasta !== '') {
            $consulta->whereDate('fecha_hora', '<=', $hasta);
        }

        $movimientos = $consulta
            ->orderByDesc('fecha_hora')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (MovimientoPersonal $m): array => [
                'id' => $m->id,
                'trabajador_id' => $m->trabajador_id,
                'trabajador_dni' => $m->trabajador?->dni ?? '—',
                'trabajador_nombre' => $m->trabajador?->nombreCompleto() ?? '—',
                'cargo_nombre' => $m->trabajador?->cargo?->nombre ?? '—',
                'tipo' => $m->tipo->value,
                'tipo_etiqueta' => $m->tipo->etiqueta(),
                'fecha_hora' => $m->fecha_hora?->format('d/m/Y H:i'),
                'origen' => $m->origenSede?->nombre ?? '—',
                'destino' => $m->destinoSede?->nombre ?? '—',
                'transporte' => $m->transporte,
                'observaciones' => $m->observaciones,
                'registrado_por' => $m->registradoPor?->name ?? 'Sistema',
            ]);

        $puedeCrear = $usuario->puede('movimientos_personal', 'C');

        $trabajadores = $puedeCrear
            ? Trabajador::activos()
                ->where('estado', EstadoTrabajador::ACTIVO->value)
                ->with('examenes')
                ->orderBy('apellidos')
                ->get()
                ->map(function (Trabajador $t) use ($esMedicoAutorizado): array {
                    $detalle = $t->detalleAptitudViaje();

                    return [
                        'id' => $t->id,
                        'label' => "{$t->apellidos}, {$t->nombres} ({$t->dni})",
                        'ubicacion_actual' => $t->ubicacion_actual?->value,
                        'ubicacion_etiqueta' => $t->ubicacion_actual?->etiqueta(),
                        'apto_viaje' => $detalle['apto'],
                        'aptitud_motivo' => $esMedicoAutorizado
                            ? $detalle['motivo']
                            : ($detalle['apto'] ? 'Habilitado para viaje' : 'No habilitado para viajar a Pataz'),
                    ];
                })
                ->values()
            : collect();

        return Inertia::render('rrhh/movimientos', [
            'movimientos' => $movimientos,
            'filtros' => [
                'q' => $buscar,
                'tipo' => $tipo,
                'trabajador_id' => $trabajadorId,
                'desde' => $desde,
                'hasta' => $hasta,
            ],
            'trabajadores' => $trabajadores,
            'sedes' => Sede::activos()->orderBy('nombre')->get(['id', 'nombre', 'tipo']),
            'tipos' => array_map(fn (TipoMovimientoPersonal $t): array => ['value' => $t->value, 'label' => $t->etiqueta()], TipoMovimientoPersonal::cases()),
            'puedeCrear' => $puedeCrear,
        ]);
    }

    public function store(Request $request, MovimientoPersonalService $servicio): RedirectResponse
    {
        $datos = $request->validate(
            [
                'trabajador_id' => ['required', 'integer', 'exists:trabajadores,id'],
                'tipo' => ['required', Rule::in(TipoMovimientoPersonal::valores())],
                'fecha_hora' => ['required', 'date'],
                'origen_sede_id' => ['required', 'integer', 'exists:sedes,id'],
                'destino_sede_id' => ['required', 'integer', 'exists:sedes,id'],
                'transporte' => ['nullable', 'string', 'max:120'],
                'observaciones' => ['nullable', 'string', 'max:1000'],
            ],
            [
                'trabajador_id.required' => 'Selecciona un trabajador.',
                'tipo.required' => 'Selecciona el tipo de movimiento.',
                'fecha_hora.required' => 'Ingresa la fecha y hora del traslado.',
                'origen_sede_id.required' => 'Selecciona la sede de origen.',
                'destino_sede_id.required' => 'Selecciona la sede de destino.',
            ]
        );

        $movimiento = $servicio->registrar($datos, $request->user());

        return back()->with('exito', "{$movimiento->tipo->etiqueta()} registrada correctamente.");
    }
}
