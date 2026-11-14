<?php

namespace App\Http\Controllers\Rrhh;

use App\Enums\EstadoSolicitudRrhh;
use App\Enums\EstadoTrabajador;
use App\Enums\TipoPermiso;
use App\Http\Controllers\Controller;
use App\Models\Rrhh\PeriodoVacacional;
use App\Models\Rrhh\Permiso;
use App\Models\Rrhh\SolicitudVacaciones;
use App\Models\Rrhh\Trabajador;
use App\Services\Rrhh\VacacionesPermisosService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class VacacionPermisoController extends Controller
{
    public function vacaciones(Request $request, VacacionesPermisosService $servicio): Response
    {
        $servicio->actualizarEstadosTomados();

        $usuario = $request->user();
        $buscar = trim((string) $request->query('q', ''));
        $estado = (string) $request->query('estado', '');
        $trabajadorId = (string) $request->query('trabajador_id', '');
        $anio = (string) $request->query('anio', '');

        $consulta = SolicitudVacaciones::query()
            ->activos()
            ->with([
                'trabajador:id,dni,nombres,apellidos,cargo_id',
                'trabajador.cargo:id,nombre',
                'periodo:id,anio,dias_ganados,dias_tomados',
                'solicitadoPor:id,name',
                'aprobadoPor:id,name',
            ]);

        if ($buscar !== '') {
            $consulta->whereHas('trabajador', function ($q) use ($buscar): void {
                $q->where('dni', 'like', "%{$buscar}%")
                    ->orWhere('nombres', 'like', "%{$buscar}%")
                    ->orWhere('apellidos', 'like', "%{$buscar}%");
            });
        }

        if ($estado !== '') {
            $consulta->where('estado', $estado);
        }

        if ($trabajadorId !== '') {
            $consulta->where('trabajador_id', (int) $trabajadorId);
        }

        if ($anio !== '') {
            $consulta->whereHas('periodo', fn ($q) => $q->where('anio', (int) $anio));
        }

        $solicitudes = $consulta
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (SolicitudVacaciones $s): array => [
                'id' => $s->id,
                'trabajador_id' => $s->trabajador_id,
                'trabajador_dni' => $s->trabajador?->dni ?? '—',
                'trabajador_nombre' => $s->trabajador?->nombreCompleto() ?? '—',
                'cargo_nombre' => $s->trabajador?->cargo?->nombre ?? '—',
                'anio' => $s->periodo?->anio,
                'saldo_periodo' => $s->periodo?->saldoDisponible() ?? 0,
                'fecha_inicio' => $s->fecha_inicio?->format('Y-m-d'),
                'fecha_inicio_fmt' => $s->fecha_inicio?->format('d/m/Y'),
                'fecha_fin' => $s->fecha_fin?->format('Y-m-d'),
                'fecha_fin_fmt' => $s->fecha_fin?->format('d/m/Y'),
                'dias' => $s->dias,
                'estado' => $s->estado->value,
                'estado_etiqueta' => $s->estado->etiqueta(),
                'solicitado_por_id' => $s->solicitado_por,
                'solicitado_por' => $s->solicitadoPor?->name ?? 'Sistema',
                'aprobado_por' => $s->aprobadoPor?->name,
                'fecha_resolucion' => $s->fecha_resolucion?->format('d/m/Y H:i'),
                'comentario' => $s->comentario,
                'puede_aprobar' => $usuario->puede('vacaciones_permisos', 'A')
                    && $s->estado === EstadoSolicitudRrhh::PENDIENTE
                    && $s->solicitado_por !== $usuario->id,
                'es_propia' => $s->solicitado_por === $usuario->id,
            ]);

        $periodos = PeriodoVacacional::query()
            ->activos()
            ->with('trabajador:id,dni,nombres,apellidos')
            ->when($trabajadorId !== '', fn ($q) => $q->where('trabajador_id', (int) $trabajadorId))
            ->when($anio !== '', fn ($q) => $q->where('anio', (int) $anio))
            ->orderByDesc('anio')
            ->orderBy('trabajador_id')
            ->limit(60)
            ->get()
            ->map(fn (PeriodoVacacional $p): array => [
                'id' => $p->id,
                'trabajador_id' => $p->trabajador_id,
                'trabajador_nombre' => $p->trabajador?->nombreCompleto() ?? '—',
                'trabajador_dni' => $p->trabajador?->dni ?? '—',
                'anio' => $p->anio,
                'dias_ganados' => $p->dias_ganados,
                'dias_tomados' => $p->dias_tomados,
                'saldo' => $p->saldoDisponible(),
            ])
            ->values();

        return Inertia::render('rrhh/vacaciones', [
            'solicitudes' => $solicitudes,
            'periodos' => $periodos,
            'filtros' => [
                'q' => $buscar,
                'estado' => $estado,
                'trabajador_id' => $trabajadorId,
                'anio' => $anio,
            ],
            'trabajadores' => Trabajador::activos()
                ->where('estado', EstadoTrabajador::ACTIVO->value)
                ->with(['periodosVacacionales' => fn ($q) => $q->activos()->orderByDesc('anio')])
                ->orderBy('apellidos')
                ->get(['id', 'dni', 'nombres', 'apellidos'])
                ->map(fn (Trabajador $t): array => [
                    'id' => $t->id,
                    'label' => "{$t->apellidos}, {$t->nombres} ({$t->dni})",
                    'periodos' => $t->periodosVacacionales->map(fn (PeriodoVacacional $p): array => [
                        'id' => $p->id,
                        'anio' => $p->anio,
                        'saldo' => $p->saldoDisponible(),
                    ])->values()->all(),
                ]),
            'estados' => array_map(fn (EstadoSolicitudRrhh $e): array => ['value' => $e->value, 'label' => $e->etiqueta()], EstadoSolicitudRrhh::cases()),
            'permisos' => [
                'crear' => $usuario->puede('vacaciones_permisos', 'C'),
                'aprobar' => $usuario->puede('vacaciones_permisos', 'A'),
                'editar' => $usuario->puede('vacaciones_permisos', 'E'),
            ],
        ]);
    }

    public function guardarVacaciones(Request $request, VacacionesPermisosService $servicio): RedirectResponse
    {
        $datos = $request->validate(
            [
                'trabajador_id' => ['required', 'integer', 'exists:trabajadores,id'],
                'periodo_vacacional_id' => ['nullable', 'integer', 'exists:periodos_vacacionales,id'],
                'fecha_inicio' => ['required', 'date'],
                'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
                'comentario' => ['nullable', 'string', 'max:1000'],
            ],
            [
                'trabajador_id.required' => 'Selecciona un trabajador.',
                'fecha_inicio.required' => 'Ingresa la fecha de inicio.',
                'fecha_fin.required' => 'Ingresa la fecha de fin.',
                'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior al inicio.',
            ]
        );

        $servicio->crearSolicitudVacaciones($datos, $request->user());

        return back()->with('exito', 'Solicitud de vacaciones registrada correctamente.');
    }

    public function resolverVacaciones(
        Request $request,
        SolicitudVacaciones $solicitud,
        VacacionesPermisosService $servicio
    ): RedirectResponse {
        $datos = $request->validate([
            'accion' => ['required', Rule::in(['aprobar', 'rechazar'])],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ]);

        $aprobar = $datos['accion'] === 'aprobar';
        $servicio->resolverSolicitudVacaciones($solicitud, $aprobar, $request->user(), $datos['comentario'] ?? null);

        return back()->with('exito', $aprobar ? 'Solicitud de vacaciones aprobada.' : 'Solicitud de vacaciones rechazada.');
    }

    public function cancelarVacaciones(
        SolicitudVacaciones $solicitud,
        VacacionesPermisosService $servicio
    ): RedirectResponse {
        $servicio->cancelarSolicitudVacaciones($solicitud);

        return back()->with('exito', 'Solicitud de vacaciones cancelada.');
    }

    public function guardarPeriodo(Request $request, VacacionesPermisosService $servicio): RedirectResponse
    {
        $datos = $request->validate(
            [
                'trabajador_id' => ['required', 'integer', 'exists:trabajadores,id'],
                'anio' => ['required', 'integer', 'min:2020', 'max:2100'],
                'dias_ganados' => ['required', 'integer', 'min:1', 'max:90'],
            ],
            [
                'trabajador_id.required' => 'Selecciona un trabajador.',
                'anio.required' => 'Indica el año del período.',
                'dias_ganados.min' => 'Los días ganados deben ser al menos 1.',
            ]
        );

        $periodo = $servicio->obtenerOCrearPeriodo((int) $datos['trabajador_id'], (int) $datos['anio'], (int) $datos['dias_ganados']);
        $tomados = $periodo->recalcularDiasTomados();

        if ((int) $datos['dias_ganados'] < $tomados) {
            return back()->withErrors([
                'dias_ganados' => "No se pueden asignar menos de {$tomados} días porque ya fueron tomados/aprobados.",
            ]);
        }

        $periodo->update(['dias_ganados' => (int) $datos['dias_ganados']]);

        return back()->with('exito', "Período vacacional {$periodo->anio} actualizado.");
    }

    public function permisos(Request $request, VacacionesPermisosService $servicio): Response
    {
        $servicio->actualizarEstadosTomados();

        $usuario = $request->user();
        $buscar = trim((string) $request->query('q', ''));
        $estado = (string) $request->query('estado', '');
        $tipo = (string) $request->query('tipo', '');
        $trabajadorId = (string) $request->query('trabajador_id', '');

        $consulta = Permiso::query()
            ->activos()
            ->with([
                'trabajador:id,dni,nombres,apellidos,cargo_id',
                'trabajador.cargo:id,nombre',
                'solicitadoPor:id,name',
                'aprobadoPor:id,name',
            ]);

        if ($buscar !== '') {
            $consulta->where(function ($q) use ($buscar): void {
                $q->where('motivo', 'like', "%{$buscar}%")
                    ->orWhereHas('trabajador', function ($tw) use ($buscar): void {
                        $tw->where('dni', 'like', "%{$buscar}%")
                            ->orWhere('nombres', 'like', "%{$buscar}%")
                            ->orWhere('apellidos', 'like', "%{$buscar}%");
                    });
            });
        }

        if ($estado !== '') {
            $consulta->where('estado', $estado);
        }

        if ($tipo !== '') {
            $consulta->where('tipo', $tipo);
        }

        if ($trabajadorId !== '') {
            $consulta->where('trabajador_id', (int) $trabajadorId);
        }

        $permisosLista = $consulta
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Permiso $p): array => [
                'id' => $p->id,
                'trabajador_id' => $p->trabajador_id,
                'trabajador_dni' => $p->trabajador?->dni ?? '—',
                'trabajador_nombre' => $p->trabajador?->nombreCompleto() ?? '—',
                'cargo_nombre' => $p->trabajador?->cargo?->nombre ?? '—',
                'tipo' => $p->tipo->value,
                'tipo_etiqueta' => $p->tipo->etiqueta(),
                'con_goce' => (bool) $p->con_goce,
                'fecha_inicio' => $p->fecha_inicio?->format('Y-m-d'),
                'fecha_inicio_fmt' => $p->fecha_inicio?->format('d/m/Y'),
                'fecha_fin' => $p->fecha_fin?->format('Y-m-d'),
                'fecha_fin_fmt' => $p->fecha_fin?->format('d/m/Y'),
                'dias' => $p->fecha_inicio && $p->fecha_fin ? ($p->fecha_inicio->diffInDays($p->fecha_fin) + 1) : 1,
                'motivo' => $p->motivo,
                'estado' => $p->estado->value,
                'estado_etiqueta' => $p->estado->etiqueta(),
                'solicitado_por' => $p->solicitadoPor?->name ?? 'Sistema',
                'aprobado_por' => $p->aprobadoPor?->name,
                'fecha_resolucion' => $p->fecha_resolucion?->format('d/m/Y H:i'),
                'comentario' => $p->comentario,
                'puede_aprobar' => $usuario->puede('vacaciones_permisos', 'A')
                    && $p->estado === EstadoSolicitudRrhh::PENDIENTE
                    && $p->solicitado_por !== $usuario->id,
                'es_propia' => $p->solicitado_por === $usuario->id,
            ]);

        return Inertia::render('rrhh/permisos', [
            'permisosLista' => $permisosLista,
            'filtros' => [
                'q' => $buscar,
                'estado' => $estado,
                'tipo' => $tipo,
                'trabajador_id' => $trabajadorId,
            ],
            'trabajadores' => Trabajador::activos()
                ->where('estado', EstadoTrabajador::ACTIVO->value)
                ->orderBy('apellidos')
                ->get(['id', 'dni', 'nombres', 'apellidos'])
                ->map(fn (Trabajador $t): array => ['id' => $t->id, 'label' => "{$t->apellidos}, {$t->nombres} ({$t->dni})"]),
            'tipos' => array_map(fn (TipoPermiso $t): array => ['value' => $t->value, 'label' => $t->etiqueta()], TipoPermiso::cases()),
            'estados' => array_map(fn (EstadoSolicitudRrhh $e): array => ['value' => $e->value, 'label' => $e->etiqueta()], EstadoSolicitudRrhh::cases()),
            'permisos' => [
                'crear' => $usuario->puede('vacaciones_permisos', 'C'),
                'aprobar' => $usuario->puede('vacaciones_permisos', 'A'),
                'editar' => $usuario->puede('vacaciones_permisos', 'E'),
            ],
        ]);
    }

    public function guardarPermiso(Request $request, VacacionesPermisosService $servicio): RedirectResponse
    {
        $datos = $request->validate(
            [
                'trabajador_id' => ['required', 'integer', 'exists:trabajadores,id'],
                'tipo' => ['required', Rule::in(TipoPermiso::valores())],
                'con_goce' => ['boolean'],
                'fecha_inicio' => ['required', 'date'],
                'fecha_fin' => ['required', 'date', 'after_or_equal:fecha_inicio'],
                'motivo' => ['required', 'string', 'max:1500'],
            ],
            [
                'trabajador_id.required' => 'Selecciona un trabajador.',
                'tipo.required' => 'Selecciona el tipo de permiso.',
                'fecha_inicio.required' => 'Ingresa la fecha de inicio.',
                'fecha_fin.required' => 'Ingresa la fecha de fin.',
                'fecha_fin.after_or_equal' => 'La fecha de fin debe ser igual o posterior al inicio.',
                'motivo.required' => 'Describe el motivo del permiso.',
            ]
        );

        $servicio->crearPermiso($datos, $request->user());

        return back()->with('exito', 'Solicitud de permiso registrada correctamente.');
    }

    public function resolverPermiso(
        Request $request,
        Permiso $permiso,
        VacacionesPermisosService $servicio
    ): RedirectResponse {
        $datos = $request->validate([
            'accion' => ['required', Rule::in(['aprobar', 'rechazar'])],
            'comentario' => ['nullable', 'string', 'max:1000'],
        ]);

        $aprobar = $datos['accion'] === 'aprobar';
        $servicio->resolverPermiso($permiso, $aprobar, $request->user(), $datos['comentario'] ?? null);

        return back()->with('exito', $aprobar ? 'Permiso aprobado correctamente.' : 'Permiso rechazado.');
    }

    public function cancelarPermiso(
        Permiso $permiso,
        VacacionesPermisosService $servicio
    ): RedirectResponse {
        $servicio->cancelarPermiso($permiso);

        return back()->with('exito', 'Permiso cancelado.');
    }
}
