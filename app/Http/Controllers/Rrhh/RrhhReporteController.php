<?php

namespace App\Http\Controllers\Rrhh;

use App\Enums\EstadoTrabajador;
use App\Enums\RolUsuario;
use App\Exports\RrhhReporteExport;
use App\Http\Controllers\Controller;
use App\Models\Core\Area;
use App\Models\Core\Sede;
use App\Models\Rrhh\ExamenOcupacional;
use App\Models\Rrhh\MovimientoPersonal;
use App\Models\Rrhh\PeriodoVacacional;
use App\Models\Rrhh\Trabajador;
use App\Models\User;
use App\Services\Rrhh\VacacionesPermisosService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class RrhhReporteController extends Controller
{
    public function index(Request $request, VacacionesPermisosService $vacacionesService): Response
    {
        $vacacionesService->actualizarEstadosTomados();

        $usuario = $request->user();
        $disponibles = $this->reportesPermitidos($usuario);
        abort_if($disponibles === [], 403);

        $tipoSolicitado = (string) $request->query('tipo', $disponibles[0]);
        $tipo = in_array($tipoSolicitado, $disponibles, true) ? $tipoSolicitado : $disponibles[0];

        $filtros = [
            'tipo' => $tipo,
            'sede_id' => (string) $request->query('sede_id', ''),
            'area_id' => (string) $request->query('area_id', ''),
            'estado_examen' => (string) $request->query('estado_examen', 'todos'),
            'anio' => (string) $request->query('anio', Carbon::now()->year),
            'desde' => (string) $request->query('desde', ''),
            'hasta' => (string) $request->query('hasta', ''),
        ];

        $datos = $this->construirReporte($tipo, $filtros, $usuario);

        return Inertia::render('rrhh/reportes', [
            'tipo' => $tipo,
            'reportesPermitidos' => $disponibles,
            'filtros' => $filtros,
            'encabezados' => $datos['encabezados'],
            'filas' => $datos['filas'],
            'sedes' => Sede::activos()->orderBy('nombre')->get(['id', 'nombre']),
            'areas' => Area::activos()->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function exportar(Request $request, string $tipo): BinaryFileResponse
    {
        $usuario = $request->user();
        $disponibles = $this->reportesPermitidos($usuario);
        abort_unless(in_array($tipo, $disponibles, true), 403);

        $filtros = [
            'sede_id' => (string) $request->query('sede_id', ''),
            'area_id' => (string) $request->query('area_id', ''),
            'estado_examen' => (string) $request->query('estado_examen', 'todos'),
            'anio' => (string) $request->query('anio', Carbon::now()->year),
            'desde' => (string) $request->query('desde', ''),
            'hasta' => (string) $request->query('hasta', ''),
        ];

        $datos = $this->construirReporte($tipo, $filtros, $usuario);

        return Excel::download(
            new RrhhReporteExport($datos['encabezados'], $datos['filas']),
            "reporte-rrhh-{$tipo}-".now()->format('Ymd-Hi').'.xlsx'
        );
    }

    /**
     * @return list<string>
     */
    private function reportesPermitidos(User $usuario): array
    {
        $lista = [];
        if ($usuario->puede('trabajadores', 'L')) {
            $lista[] = 'personal';
        }
        if ($usuario->puede('examenes', 'L')) {
            $lista[] = 'examenes';
        }
        if ($usuario->puede('vacaciones_permisos', 'L')) {
            $lista[] = 'vacaciones';
        }
        if ($usuario->puede('movimientos_personal', 'L')) {
            $lista[] = 'movimientos';
        }

        return $lista;
    }

    /**
     * @param  array<string, string>  $filtros
     * @return array{encabezados: list<string>, filas: list<list<string|int|float|null>>}
     */
    private function construirReporte(string $tipo, array $filtros, User $usuario): array
    {
        $esMedicoAutorizado = $usuario->tieneRol(RolUsuario::ADMIN, RolUsuario::RRHH_JEFE, RolUsuario::RRHH_ASISTENTE);

        return match ($tipo) {
            'personal' => $this->reportePersonal($filtros, $esMedicoAutorizado),
            'examenes' => $this->reporteExamenes($filtros, $esMedicoAutorizado),
            'vacaciones' => $this->reporteVacaciones($filtros),
            'movimientos' => $this->reporteMovimientos($filtros),
            default => ['encabezados' => [], 'filas' => []],
        };
    }

    /**
     * @param  array<string, string>  $filtros
     * @return array{encabezados: list<string>, filas: list<list<string|int|float|null>>}
     */
    private function reportePersonal(array $filtros, bool $esMedicoAutorizado): array
    {
        $consulta = Trabajador::query()
            ->activos()
            ->with(['cargo:id,nombre', 'area:id,nombre', 'sedeBase:id,nombre', 'examenes'])
            ->when($filtros['sede_id'] !== '', fn ($q) => $q->where('sede_base_id', (int) $filtros['sede_id']))
            ->when($filtros['area_id'] !== '', fn ($q) => $q->where('area_id', (int) $filtros['area_id']))
            ->orderBy('apellidos')
            ->orderBy('nombres');

        $encabezados = ['DNI', 'Apellidos y Nombres', 'Cargo', 'Área', 'Sede base', 'Ubicación actual', 'Estado', 'Rotación', 'Fecha ingreso'];
        if ($esMedicoAutorizado) {
            $encabezados[] = 'Apto Pataz';
        }

        $filas = $consulta->get()->map(function (Trabajador $t) use ($esMedicoAutorizado): array {
            $fila = [
                $t->dni,
                $t->nombreCompleto(),
                $t->cargo?->nombre ?? '—',
                $t->area?->nombre ?? '—',
                $t->sedeBase?->nombre ?? '—',
                $t->ubicacion_actual?->etiqueta() ?? '—',
                $t->estado?->etiqueta() ?? '—',
                $t->esquema_rotacion ?: '—',
                $t->fecha_ingreso?->format('d/m/Y') ?? '—',
            ];
            if ($esMedicoAutorizado) {
                $fila[] = $t->aptitud_viaje ? 'Sí (Apto)' : 'No';
            }

            return $fila;
        })->values()->all();

        return ['encabezados' => $encabezados, 'filas' => $filas];
    }

    /**
     * @param  array<string, string>  $filtros
     * @return array{encabezados: list<string>, filas: list<list<string|int|float|null>>}
     */
    private function reporteExamenes(array $filtros, bool $esMedicoAutorizado): array
    {
        $consulta = ExamenOcupacional::query()
            ->activos()
            ->whereHas('trabajador', fn ($q) => $q->where('activo', true)->where('estado', EstadoTrabajador::ACTIVO->value))
            ->with(['trabajador:id,dni,nombres,apellidos,sede_base_id', 'trabajador.sedeBase:id,nombre'])
            ->orderBy('fecha_vencimiento');

        if ($filtros['sede_id'] !== '') {
            $consulta->whereHas('trabajador', fn ($q) => $q->where('sede_base_id', (int) $filtros['sede_id']));
        }

        if ($filtros['estado_examen'] === 'por_vencer') {
            $consulta->porVencer(30);
        } elseif ($filtros['estado_examen'] === 'vencidos') {
            $consulta->vencidos();
        } elseif ($filtros['estado_examen'] === 'vigentes') {
            $consulta->vigentes();
        }

        $encabezados = ['DNI', 'Trabajador', 'Sede base', 'Tipo examen', 'Fecha examen', 'Vencimiento', 'Estado vigencia'];
        if ($esMedicoAutorizado) {
            $encabezados[] = 'Clínica';
            $encabezados[] = 'Resultado médico';
        }

        $filas = $consulta->get()->map(function (ExamenOcupacional $ex) use ($esMedicoAutorizado): array {
            $dias = $ex->diasParaVencer();
            $estadoVig = $dias < 0
                ? 'Vencido'
                : ($dias <= 30 ? "Vence en {$dias} días" : 'Vigente');

            $fila = [
                $ex->trabajador?->dni ?? '—',
                $ex->trabajador?->nombreCompleto() ?? '—',
                $ex->trabajador?->sedeBase?->nombre ?? '—',
                $ex->tipo->etiqueta(),
                $ex->fecha_examen?->format('d/m/Y') ?? '—',
                $ex->fecha_vencimiento?->format('d/m/Y') ?? '—',
                $estadoVig,
            ];

            if ($esMedicoAutorizado) {
                $fila[] = $ex->clinica;
                $fila[] = $ex->resultado->etiqueta();
            }

            return $fila;
        })->values()->all();

        return ['encabezados' => $encabezados, 'filas' => $filas];
    }

    /**
     * @param  array<string, string>  $filtros
     * @return array{encabezados: list<string>, filas: list<list<string|int|float|null>>}
     */
    private function reporteVacaciones(array $filtros): array
    {
        $anio = (int) ($filtros['anio'] ?: Carbon::now()->year);

        $periodos = PeriodoVacacional::query()
            ->activos()
            ->where('anio', $anio)
            ->with(['trabajador:id,dni,nombres,apellidos,area_id,sede_base_id', 'trabajador.area:id,nombre', 'trabajador.sedeBase:id,nombre'])
            ->when($filtros['sede_id'] !== '', fn ($q) => $q->whereHas('trabajador', fn ($tw) => $tw->where('sede_base_id', (int) $filtros['sede_id'])))
            ->when($filtros['area_id'] !== '', fn ($q) => $q->whereHas('trabajador', fn ($tw) => $tw->where('area_id', (int) $filtros['area_id'])))
            ->orderBy('trabajador_id')
            ->get();

        $encabezados = ['DNI', 'Trabajador', 'Área', 'Sede base', 'Año', 'Días ganados', 'Días tomados', 'Saldo disponible'];
        $filas = $periodos->map(fn (PeriodoVacacional $p): array => [
            $p->trabajador?->dni ?? '—',
            $p->trabajador?->nombreCompleto() ?? '—',
            $p->trabajador?->area?->nombre ?? '—',
            $p->trabajador?->sedeBase?->nombre ?? '—',
            $p->anio,
            $p->dias_ganados,
            $p->dias_tomados,
            $p->saldoDisponible(),
        ])->values()->all();

        return ['encabezados' => $encabezados, 'filas' => $filas];
    }

    /**
     * @param  array<string, string>  $filtros
     * @return array{encabezados: list<string>, filas: list<list<string|int|float|null>>}
     */
    private function reporteMovimientos(array $filtros): array
    {
        $consulta = MovimientoPersonal::query()
            ->activos()
            ->with(['trabajador:id,dni,nombres,apellidos,area_id', 'trabajador.area:id,nombre', 'origenSede:id,nombre', 'destinoSede:id,nombre', 'registradoPor:id,name'])
            ->when($filtros['desde'] !== '', fn ($q) => $q->whereDate('fecha_hora', '>=', $filtros['desde']))
            ->when($filtros['hasta'] !== '', fn ($q) => $q->whereDate('fecha_hora', '<=', $filtros['hasta']))
            ->orderByDesc('fecha_hora');

        $encabezados = ['Fecha y hora', 'DNI', 'Trabajador', 'Tipo traslado', 'Origen', 'Destino', 'Transporte', 'Registrado por'];
        $filas = $consulta->get()->map(fn (MovimientoPersonal $m): array => [
            $m->fecha_hora?->format('d/m/Y H:i') ?? '—',
            $m->trabajador?->dni ?? '—',
            $m->trabajador?->nombreCompleto() ?? '—',
            $m->tipo->etiqueta(),
            $m->origenSede?->nombre ?? '—',
            $m->destinoSede?->nombre ?? '—',
            $m->transporte ?: '—',
            $m->registradoPor?->name ?? 'Sistema',
        ])->values()->all();

        return ['encabezados' => $encabezados, 'filas' => $filas];
    }
}
