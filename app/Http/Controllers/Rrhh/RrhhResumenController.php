<?php

namespace App\Http\Controllers\Rrhh;

use App\Enums\EstadoSolicitudRrhh;
use App\Enums\EstadoTrabajador;
use App\Enums\RolUsuario;
use App\Enums\UbicacionTrabajador;
use App\Http\Controllers\Controller;
use App\Models\Rrhh\MovimientoPersonal;
use App\Models\Rrhh\Permiso;
use App\Models\Rrhh\SolicitudVacaciones;
use App\Models\Rrhh\Trabajador;
use App\Services\Rrhh\AptitudViajeService;
use App\Services\Rrhh\VacacionesPermisosService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RrhhResumenController extends Controller
{
    public function index(
        Request $request,
        AptitudViajeService $aptitudService,
        VacacionesPermisosService $vacacionesService
    ): Response {
        $vacacionesService->actualizarEstadosTomados();

        $usuario = $request->user();
        $puedeTrabajadores = $usuario->puede('trabajadores', 'L');
        $puedeExamenes = $usuario->puede('examenes', 'L');
        $puedeVacaciones = $usuario->puede('vacaciones_permisos', 'L');
        $puedeMovimientos = $usuario->puede('movimientos_personal', 'L');
        $esMedicoAutorizado = $usuario->tieneRol(RolUsuario::ADMIN, RolUsuario::RRHH_JEFE, RolUsuario::RRHH_ASISTENTE);

        $conteosPersonal = [
            'activos' => Trabajador::activos()->where('estado', EstadoTrabajador::ACTIVO->value)->count(),
            'trujillo' => Trabajador::activos()->where('estado', EstadoTrabajador::ACTIVO->value)->where('ubicacion_actual', UbicacionTrabajador::TRUJILLO->value)->count(),
            'pataz' => Trabajador::activos()->where('estado', EstadoTrabajador::ACTIVO->value)->where('ubicacion_actual', UbicacionTrabajador::PATAZ->value)->count(),
            'en_transito' => Trabajador::activos()->where('estado', EstadoTrabajador::ACTIVO->value)->where('ubicacion_actual', UbicacionTrabajador::EN_TRANSITO->value)->count(),
        ];

        $resumenAlertas = ($puedeExamenes || $esMedicoAutorizado)
            ? $aptitudService->resumenAlertas(30)
            : ['totales' => null, 'alertas' => collect()];

        $alertasFormateadas = $resumenAlertas['alertas']
            ->take(20)
            ->map(function (array $alerta) use ($esMedicoAutorizado): array {
                if (! $esMedicoAutorizado) {
                    // GERENTE ve resumen de vigencia/alerta sin diagnóstico ni resultado médico específico
                    $alerta['mensaje'] = match ($alerta['nivel']) {
                        'POR_VENCER' => "Examen próximo a vencer ({$alerta['dias_restantes']} día(s))",
                        'VENCIDO' => 'Examen ocupacional vencido',
                        'SIN_EXAMEN' => 'Sin examen ocupacional vigente',
                        default => 'No habilitado para viaje (detalle médico restringido)',
                    };
                }

                return $alerta;
            })
            ->values();

        $pendientesVacaciones = $puedeVacaciones
            ? SolicitudVacaciones::activos()->where('estado', EstadoSolicitudRrhh::PENDIENTE->value)->count()
            : 0;
        $pendientesPermisos = $puedeVacaciones
            ? Permiso::activos()->where('estado', EstadoSolicitudRrhh::PENDIENTE->value)->count()
            : 0;

        $ultimosMovimientos = $puedeMovimientos
            ? MovimientoPersonal::activos()
                ->with(['trabajador:id,dni,nombres,apellidos', 'origenSede:id,nombre', 'destinoSede:id,nombre'])
                ->latest('fecha_hora')
                ->limit(8)
                ->get()
                ->map(fn (MovimientoPersonal $m): array => [
                    'id' => $m->id,
                    'trabajador_id' => $m->trabajador_id,
                    'trabajador_nombre' => $m->trabajador?->nombreCompleto() ?? '—',
                    'trabajador_dni' => $m->trabajador?->dni ?? '—',
                    'tipo' => $m->tipo->value,
                    'tipo_etiqueta' => $m->tipo->etiqueta(),
                    'origen' => $m->origenSede?->nombre ?? '—',
                    'destino' => $m->destinoSede?->nombre ?? '—',
                    'fecha_hora' => $m->fecha_hora?->format('d/m/Y H:i'),
                ])
                ->values()
            : collect();

        return Inertia::render('rrhh/index', [
            'puede' => [
                'trabajadores' => $puedeTrabajadores,
                'examenes' => $puedeExamenes,
                'vacaciones' => $puedeVacaciones,
                'movimientos' => $puedeMovimientos,
                'medico_detalle' => $esMedicoAutorizado,
            ],
            'conteosPersonal' => $conteosPersonal,
            'totalesAptitud' => $resumenAlertas['totales'],
            'alertasExamenes' => $alertasFormateadas,
            'pendientes' => [
                'vacaciones' => $pendientesVacaciones,
                'permisos' => $pendientesPermisos,
            ],
            'ultimosMovimientos' => $ultimosMovimientos,
        ]);
    }
}
