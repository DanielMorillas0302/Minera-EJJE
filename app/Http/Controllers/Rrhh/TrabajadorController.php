<?php

namespace App\Http\Controllers\Rrhh;

use App\Enums\EstadoTrabajador;
use App\Enums\ResultadoExamen;
use App\Enums\RolUsuario;
use App\Enums\SexoTrabajador;
use App\Enums\UbicacionTrabajador;
use App\Http\Controllers\Controller;
use App\Models\Core\Area;
use App\Models\Core\Cargo;
use App\Models\Core\Sede;
use App\Models\Rrhh\ExamenOcupacional;
use App\Models\Rrhh\MovimientoPersonal;
use App\Models\Rrhh\Permiso;
use App\Models\Rrhh\SolicitudVacaciones;
use App\Models\Rrhh\Trabajador;
use App\Services\Rrhh\VacacionesPermisosService;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TrabajadorController extends Controller
{
    public function index(Request $request): Response
    {
        $usuario = $request->user();
        $puedeVerAptitud = $usuario->tieneRol(
            RolUsuario::ADMIN,
            RolUsuario::RRHH_JEFE,
            RolUsuario::RRHH_ASISTENTE,
            RolUsuario::GERENTE
        );
        $esMedicoAutorizado = $usuario->tieneRol(
            RolUsuario::ADMIN,
            RolUsuario::RRHH_JEFE,
            RolUsuario::RRHH_ASISTENTE
        );

        $buscar = trim((string) $request->query('q', ''));
        $sedeId = (string) $request->query('sede_id', '');
        $areaId = (string) $request->query('area_id', '');
        $estado = (string) $request->query('estado', '');
        $ubicacion = (string) $request->query('ubicacion', '');
        $aptitud = (string) $request->query('aptitud', '');
        $ver = (string) $request->query('ver', 'activos');

        $consulta = Trabajador::query()
            ->with(['cargo:id,nombre', 'area:id,nombre', 'sedeBase:id,nombre'])
            ->when($puedeVerAptitud, fn ($q) => $q->with('examenes'));

        if ($ver === 'todos') {
            $consulta->withTrashed();
        } elseif ($ver === 'inactivos') {
            $consulta->withTrashed()->where('activo', false);
        } else {
            $consulta->where('activo', true);
        }

        if ($buscar !== '') {
            $consulta->where(function ($q) use ($buscar): void {
                $q->where('dni', 'like', "%{$buscar}%")
                    ->orWhere('nombres', 'like', "%{$buscar}%")
                    ->orWhere('apellidos', 'like', "%{$buscar}%")
                    ->orWhere('email', 'like', "%{$buscar}%");
            });
        }

        if ($sedeId !== '') {
            $consulta->where('sede_base_id', (int) $sedeId);
        }

        if ($areaId !== '') {
            $consulta->where('area_id', (int) $areaId);
        }

        if ($estado !== '') {
            $consulta->where('estado', $estado);
        }

        if ($ubicacion !== '') {
            $consulta->where('ubicacion_actual', $ubicacion);
        }

        if ($puedeVerAptitud && in_array($aptitud, ['apto', 'no_apto'], true)) {
            $hoy = Carbon::today()->toDateString();
            if ($aptitud === 'apto') {
                $consulta->where('activo', true)
                    ->where('estado', EstadoTrabajador::ACTIVO->value)
                    ->whereHas('examenes', fn ($q) => $q->where('activo', true)
                        ->where('resultado', ResultadoExamen::APTO->value)
                        ->whereDate('fecha_vencimiento', '>=', $hoy));
            } else {
                $consulta->where(function ($q) use ($hoy): void {
                    $q->where('activo', false)
                        ->orWhere('estado', '!=', EstadoTrabajador::ACTIVO->value)
                        ->orWhereDoesntHave('examenes', fn ($ex) => $ex->where('activo', true)
                            ->where('resultado', ResultadoExamen::APTO->value)
                            ->whereDate('fecha_vencimiento', '>=', $hoy));
                });
            }
        }

        $trabajadores = $consulta
            ->orderBy('apellidos')
            ->orderBy('nombres')
            ->paginate(20)
            ->withQueryString()
            ->through(function (Trabajador $t) use ($puedeVerAptitud, $esMedicoAutorizado): array {
                $detalle = $puedeVerAptitud ? $t->detalleAptitudViaje() : null;

                $motivoPublico = null;
                if ($detalle) {
                    $motivoPublico = $esMedicoAutorizado
                        ? $detalle['motivo']
                        : ($detalle['apto'] ? 'Habilitado para viajar a Pataz' : 'No habilitado para viaje');
                }

                return [
                    'id' => $t->id,
                    'dni' => $t->dni,
                    'nombres' => $t->nombres,
                    'apellidos' => $t->apellidos,
                    'nombre_completo' => $t->nombreCompleto(),
                    'fecha_nacimiento' => $t->fecha_nacimiento?->format('Y-m-d'),
                    'sexo' => $t->sexo?->value,
                    'sexo_etiqueta' => $t->sexo?->etiqueta(),
                    'telefono' => $t->telefono,
                    'email' => $t->email,
                    'direccion' => $t->direccion,
                    'contacto_emergencia_nombre' => $t->contacto_emergencia_nombre,
                    'contacto_emergencia_telefono' => $t->contacto_emergencia_telefono,
                    'cargo_id' => $t->cargo_id,
                    'cargo_nombre' => $t->cargo?->nombre,
                    'area_id' => $t->area_id,
                    'area_nombre' => $t->area?->nombre,
                    'sede_base_id' => $t->sede_base_id,
                    'sede_nombre' => $t->sedeBase?->nombre,
                    'fecha_ingreso' => $t->fecha_ingreso?->format('Y-m-d'),
                    'fecha_ingreso_fmt' => $t->fecha_ingreso?->format('d/m/Y'),
                    'fecha_cese' => $t->fecha_cese?->format('Y-m-d'),
                    'estado' => $t->estado?->value,
                    'estado_etiqueta' => $t->estado?->etiqueta(),
                    'ubicacion_actual' => $t->ubicacion_actual?->value,
                    'ubicacion_etiqueta' => $t->ubicacion_actual?->etiqueta(),
                    'esquema_rotacion' => $t->esquema_rotacion,
                    'tiene_foto' => (bool) $t->foto,
                    'activo' => (bool) $t->activo,
                    'aptitud_visible' => $puedeVerAptitud,
                    'apto_viaje' => $detalle ? $detalle['apto'] : null,
                    'aptitud_motivo' => $motivoPublico,
                ];
            });

        return Inertia::render('rrhh/trabajadores', [
            'trabajadores' => $trabajadores,
            'filtros' => [
                'q' => $buscar,
                'sede_id' => $sedeId,
                'area_id' => $areaId,
                'estado' => $estado,
                'ubicacion' => $ubicacion,
                'aptitud' => $aptitud,
                'ver' => $ver,
            ],
            'catalogos' => [
                'sedes' => Sede::activos()->orderBy('nombre')->get(['id', 'nombre', 'tipo']),
                'areas' => Area::activos()->with('sede:id,nombre')->orderBy('nombre')->get(['id', 'nombre', 'sede_id']),
                'cargos' => Cargo::activos()->orderBy('nombre')->get(['id', 'nombre']),
                'sexos' => array_map(fn (SexoTrabajador $s): array => ['value' => $s->value, 'label' => $s->etiqueta()], SexoTrabajador::cases()),
                'estados' => array_map(fn (EstadoTrabajador $e): array => ['value' => $e->value, 'label' => $e->etiqueta()], EstadoTrabajador::cases()),
                'ubicaciones' => array_map(fn (UbicacionTrabajador $u): array => ['value' => $u->value, 'label' => $u->etiqueta()], UbicacionTrabajador::cases()),
            ],
            'puedeGestionar' => $usuario->puede('trabajadores', 'C'),
            'puedeVerAptitud' => $puedeVerAptitud,
        ]);
    }

    public function show(
        Request $request,
        int $trabajador,
        VacacionesPermisosService $vacacionesService
    ): Response {
        $vacacionesService->actualizarEstadosTomados();

        $registro = Trabajador::withTrashed()
            ->with(['cargo:id,nombre', 'area:id,nombre', 'sedeBase:id,nombre'])
            ->findOrFail($trabajador);

        $usuario = $request->user();
        $puedeExamenes = $usuario->puede('examenes', 'L');
        $esMedicoAutorizado = $usuario->tieneRol(RolUsuario::ADMIN, RolUsuario::RRHH_JEFE, RolUsuario::RRHH_ASISTENTE);
        $puedeVacaciones = $usuario->puede('vacaciones_permisos', 'L');
        $puedeMovimientos = $usuario->puede('movimientos_personal', 'L');
        $puedeVerAptitud = $esMedicoAutorizado || $usuario->hasRole(RolUsuario::GERENTE->value) || $usuario->tieneRol(RolUsuario::GERENTE);

        $aptitud = $puedeVerAptitud ? $registro->detalleAptitudViaje() : null;

        $examenes = $puedeExamenes
            ? $registro->examenes()
                ->activos()
                ->with('adjuntos')
                ->orderByDesc('fecha_examen')
                ->get()
                ->map(fn (ExamenOcupacional $ex): array => [
                    'id' => $ex->id,
                    'tipo' => $ex->tipo->value,
                    'tipo_etiqueta' => $ex->tipo->etiqueta(),
                    'fecha_examen' => $ex->fecha_examen?->format('Y-m-d'),
                    'fecha_examen_fmt' => $ex->fecha_examen?->format('d/m/Y'),
                    'fecha_vencimiento' => $ex->fecha_vencimiento?->format('Y-m-d'),
                    'fecha_vencimiento_fmt' => $ex->fecha_vencimiento?->format('d/m/Y'),
                    'vigente' => $ex->vigente,
                    'dias_para_vencer' => $ex->diasParaVencer(),
                    'clinica' => $esMedicoAutorizado ? $ex->clinica : 'Restringido',
                    'resultado' => $esMedicoAutorizado ? $ex->resultado->value : null,
                    'resultado_etiqueta' => $esMedicoAutorizado ? $ex->resultado->etiqueta() : ($ex->vigente ? 'Resumen reservado (vigente)' : 'Resumen reservado (vencido)'),
                    'observaciones' => $esMedicoAutorizado ? $ex->observaciones : null,
                    'adjuntos' => $esMedicoAutorizado ? $ex->adjuntos->where('activo', true)->values()->map(fn ($adj): array => [
                        'id' => $adj->id,
                        'nombre' => $adj->nombre_original ?: basename($adj->archivo),
                        'tamano' => $adj->tamano,
                    ])->all() : [],
                ])
                ->values()
            : collect();

        $periodos = $puedeVacaciones
            ? $registro->periodosVacacionales()->activos()->orderByDesc('anio')->get()->map(fn ($p): array => [
                'id' => $p->id,
                'anio' => $p->anio,
                'dias_ganados' => $p->dias_ganados,
                'dias_tomados' => $p->dias_tomados,
                'saldo' => $p->saldoDisponible(),
            ])->values()
            : collect();

        $vacaciones = $puedeVacaciones
            ? $registro->solicitudesVacaciones()
                ->activos()
                ->with(['periodo:id,anio', 'solicitadoPor:id,name', 'aprobadoPor:id,name'])
                ->orderByDesc('fecha_inicio')
                ->get()
                ->map(fn (SolicitudVacaciones $v): array => [
                    'id' => $v->id,
                    'anio' => $v->periodo?->anio,
                    'fecha_inicio' => $v->fecha_inicio?->format('d/m/Y'),
                    'fecha_fin' => $v->fecha_fin?->format('d/m/Y'),
                    'dias' => $v->dias,
                    'estado' => $v->estado->value,
                    'estado_etiqueta' => $v->estado->etiqueta(),
                    'solicitado_por' => $v->solicitadoPor?->name ?? '—',
                    'aprobado_por' => $v->aprobadoPor?->name ?? '—',
                    'comentario' => $v->comentario,
                ])
                ->values()
            : collect();

        $permisos = $puedeVacaciones
            ? $registro->permisos()
                ->activos()
                ->with(['solicitadoPor:id,name', 'aprobadoPor:id,name'])
                ->orderByDesc('fecha_inicio')
                ->get()
                ->map(fn (Permiso $p): array => [
                    'id' => $p->id,
                    'tipo_etiqueta' => $p->tipo->etiqueta(),
                    'con_goce' => (bool) $p->con_goce,
                    'fecha_inicio' => $p->fecha_inicio?->format('d/m/Y'),
                    'fecha_fin' => $p->fecha_fin?->format('d/m/Y'),
                    'motivo' => $p->motivo,
                    'estado' => $p->estado->value,
                    'estado_etiqueta' => $p->estado->etiqueta(),
                    'aprobado_por' => $p->aprobadoPor?->name ?? '—',
                ])
                ->values()
            : collect();

        $movimientos = $puedeMovimientos
            ? $registro->movimientos()
                ->activos()
                ->with(['origenSede:id,nombre', 'destinoSede:id,nombre', 'registradoPor:id,name'])
                ->orderByDesc('fecha_hora')
                ->get()
                ->map(fn (MovimientoPersonal $m): array => [
                    'id' => $m->id,
                    'tipo' => $m->tipo->value,
                    'tipo_etiqueta' => $m->tipo->etiqueta(),
                    'fecha_hora' => $m->fecha_hora?->format('d/m/Y H:i'),
                    'origen' => $m->origenSede?->nombre ?? '—',
                    'destino' => $m->destinoSede?->nombre ?? '—',
                    'transporte' => $m->transporte,
                    'observaciones' => $m->observaciones,
                    'registrado_por' => $m->registradoPor?->name ?? '—',
                ])
                ->values()
            : collect();

        return Inertia::render('rrhh/trabajador-detalle', [
            'trabajador' => [
                'id' => $registro->id,
                'dni' => $registro->dni,
                'nombres' => $registro->nombres,
                'apellidos' => $registro->apellidos,
                'nombre_completo' => $registro->nombreCompleto(),
                'fecha_nacimiento' => $registro->fecha_nacimiento?->format('d/m/Y'),
                'sexo_etiqueta' => $registro->sexo?->etiqueta(),
                'telefono' => $registro->telefono,
                'email' => $registro->email,
                'direccion' => $registro->direccion,
                'contacto_emergencia_nombre' => $registro->contacto_emergencia_nombre,
                'contacto_emergencia_telefono' => $registro->contacto_emergencia_telefono,
                'cargo_nombre' => $registro->cargo?->nombre,
                'area_nombre' => $registro->area?->nombre,
                'sede_base_id' => $registro->sede_base_id,
                'sede_nombre' => $registro->sedeBase?->nombre,
                'fecha_ingreso' => $registro->fecha_ingreso?->format('d/m/Y'),
                'fecha_cese' => $registro->fecha_cese?->format('d/m/Y'),
                'estado' => $registro->estado?->value,
                'estado_etiqueta' => $registro->estado?->etiqueta(),
                'ubicacion_actual' => $registro->ubicacion_actual?->value,
                'ubicacion_etiqueta' => $registro->ubicacion_actual?->etiqueta(),
                'esquema_rotacion' => $registro->esquema_rotacion,
                'foto_url' => $registro->foto ? "/rrhh/trabajadores/{$registro->id}/foto" : null,
                'activo' => (bool) $registro->activo,
                'aptitud_visible' => $puedeVerAptitud,
                'apto_viaje' => $aptitud ? $aptitud['apto'] : null,
                'aptitud_motivo' => $aptitud
                    ? ($esMedicoAutorizado ? $aptitud['motivo'] : ($aptitud['apto'] ? 'Examen médico vigente APTO' : 'No cumple requisitos médicos vigentes'))
                    : null,
            ],
            'examenes' => $examenes,
            'periodos' => $periodos,
            'vacaciones' => $vacaciones,
            'permisos' => $permisos,
            'movimientos' => $movimientos,
            'sedes' => Sede::activos()->orderBy('nombre')->get(['id', 'nombre', 'tipo']),
            'permisosModulo' => [
                'examenes' => $puedeExamenes,
                'medico_detalle' => $esMedicoAutorizado,
                'vacaciones' => $puedeVacaciones,
                'movimientos' => $puedeMovimientos,
                'crear_movimiento' => $usuario->puede('movimientos_personal', 'C'),
                'crear_examen' => $esMedicoAutorizado && $usuario->puede('examenes', 'C'),
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        if ($request->hasFile('foto')) {
            $datos['foto'] = $request->file('foto')->store('rrhh/fotos', 'local');
        }

        Trabajador::create($datos);

        return back()->with('exito', 'Trabajador registrado correctamente.');
    }

    public function update(Request $request, int $trabajador): RedirectResponse
    {
        $registro = Trabajador::withTrashed()->findOrFail($trabajador);
        $datos = $this->validar($request, $registro->id);

        if ($request->hasFile('foto')) {
            if ($registro->foto && Storage::disk('local')->exists($registro->foto)) {
                Storage::disk('local')->delete($registro->foto);
            }
            $datos['foto'] = $request->file('foto')->store('rrhh/fotos', 'local');
        } else {
            unset($datos['foto']);
        }

        $registro->update($datos);

        return back()->with('exito', 'Datos del trabajador actualizados.');
    }

    public function destroy(Trabajador $trabajador): RedirectResponse
    {
        $trabajador->delete();

        return back()->with('exito', 'Trabajador desactivado correctamente.');
    }

    public function restore(int $trabajador): RedirectResponse
    {
        $registro = Trabajador::withTrashed()->findOrFail($trabajador);
        $registro->restore();

        return back()->with('exito', 'Trabajador reactivado correctamente.');
    }

    public function foto(Trabajador $trabajador): StreamedResponse
    {
        abort_unless($trabajador->foto && Storage::disk('local')->exists($trabajador->foto), 404);

        return Storage::disk('local')->response($trabajador->foto);
    }

    /**
     * @return array<string, mixed>
     */
    private function validar(Request $request, ?int $ignorarId = null): array
    {
        $datos = $request->validate(
            [
                'dni' => ['required', 'digits:8', Rule::unique('trabajadores', 'dni')->ignore($ignorarId)],
                'nombres' => ['required', 'string', 'max:100'],
                'apellidos' => ['required', 'string', 'max:100'],
                'fecha_nacimiento' => ['required', 'date', 'before:today'],
                'sexo' => ['required', Rule::in(SexoTrabajador::valores())],
                'telefono' => ['nullable', 'string', 'max:20'],
                'email' => ['nullable', 'email', 'max:191'],
                'direccion' => ['nullable', 'string', 'max:200'],
                'contacto_emergencia_nombre' => ['nullable', 'string', 'max:120'],
                'contacto_emergencia_telefono' => ['nullable', 'string', 'max:20'],
                'cargo_id' => ['required', 'integer', 'exists:cargos,id'],
                'area_id' => ['required', 'integer', 'exists:areas,id'],
                'sede_base_id' => ['required', 'integer', 'exists:sedes,id'],
                'fecha_ingreso' => ['required', 'date'],
                'fecha_cese' => ['nullable', 'date', 'after_or_equal:fecha_ingreso'],
                'estado' => ['required', Rule::in(EstadoTrabajador::valores())],
                'ubicacion_actual' => ['required', Rule::in(UbicacionTrabajador::valores())],
                'esquema_rotacion' => ['nullable', 'string', 'max:30'],
                'foto' => ['nullable', 'image', 'max:3072'],
            ],
            [
                'dni.required' => 'El DNI es obligatorio.',
                'dni.digits' => 'El DNI debe tener exactamente 8 dígitos numéricos.',
                'dni.unique' => 'Ya existe un trabajador registrado con ese DNI.',
                'nombres.required' => 'Los nombres son obligatorios.',
                'apellidos.required' => 'Los apellidos son obligatorios.',
                'fecha_nacimiento.required' => 'La fecha de nacimiento es obligatoria.',
                'fecha_nacimiento.before' => 'La fecha de nacimiento debe ser anterior a hoy.',
                'sexo.required' => 'Selecciona el sexo del trabajador.',
                'cargo_id.required' => 'Selecciona un cargo.',
                'area_id.required' => 'Selecciona un área.',
                'sede_base_id.required' => 'Selecciona la sede base.',
                'fecha_ingreso.required' => 'La fecha de ingreso es obligatoria.',
                'fecha_cese.after_or_equal' => 'La fecha de cese no puede ser anterior a la fecha de ingreso.',
                'foto.image' => 'La foto debe ser una imagen válida (JPG/PNG).',
                'foto.max' => 'La foto no debe superar los 3 MB.',
            ]
        );

        foreach (['telefono', 'email', 'direccion', 'contacto_emergencia_nombre', 'contacto_emergencia_telefono', 'fecha_cese', 'esquema_rotacion'] as $campoOpcional) {
            if (array_key_exists($campoOpcional, $datos) && $datos[$campoOpcional] === '') {
                $datos[$campoOpcional] = null;
            }
        }

        return $datos;
    }
}
