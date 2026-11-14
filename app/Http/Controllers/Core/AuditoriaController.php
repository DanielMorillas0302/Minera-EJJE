<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\Adjunto;
use App\Models\Core\Area;
use App\Models\Core\Cargo;
use App\Models\Core\Sede;
use App\Models\Core\UnidadMedida;
use App\Models\Finanzas\CategoriaGasto;
use App\Models\Finanzas\CentroCosto;
use App\Models\Finanzas\MovimientoFinanciero;
use App\Models\Finanzas\Presupuesto;
use App\Models\Logistica\Almacen;
use App\Models\Logistica\CategoriaMaterial;
use App\Models\Logistica\Envio;
use App\Models\Logistica\Material;
use App\Models\Logistica\MovimientoInventario;
use App\Models\Logistica\Proveedor;
use App\Models\Logistica\Requerimiento;
use App\Models\Logistica\StockAlmacen;
use App\Models\Rrhh\ExamenOcupacional;
use App\Models\Rrhh\MovimientoPersonal;
use App\Models\Rrhh\PeriodoVacacional;
use App\Models\Rrhh\Permiso;
use App\Models\Rrhh\SolicitudVacaciones;
use App\Models\Rrhh\Trabajador;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AuditoriaController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const MODELOS = [
        User::class => 'Usuario',
        Sede::class => 'Sede',
        Area::class => 'Área',
        Cargo::class => 'Cargo',
        UnidadMedida::class => 'Unidad de medida',
        Adjunto::class => 'Adjunto',
        Trabajador::class => 'Trabajador',
        ExamenOcupacional::class => 'Examen ocupacional',
        PeriodoVacacional::class => 'Período vacacional',
        SolicitudVacaciones::class => 'Solicitud de vacaciones',
        Permiso::class => 'Permiso',
        MovimientoPersonal::class => 'Movimiento de personal',
        CategoriaMaterial::class => 'Categoría de material',
        Material::class => 'Material',
        Proveedor::class => 'Proveedor',
        Almacen::class => 'Almacén',
        StockAlmacen::class => 'Stock en almacén',
        MovimientoInventario::class => 'Movimiento de inventario',
        Requerimiento::class => 'Requerimiento',
        Envio::class => 'Envío',
        CentroCosto::class => 'Centro de costo',
        CategoriaGasto::class => 'Categoría de gasto',
        Presupuesto::class => 'Presupuesto',
        MovimientoFinanciero::class => 'Movimiento financiero',
    ];

    /**
     * @var array<string, string>
     */
    private const EVENTOS = [
        'created' => 'Creación',
        'updated' => 'Actualización',
        'deleted' => 'Desactivación',
        'restored' => 'Reactivación',
    ];

    public function index(Request $request): Response
    {
        $buscar = trim((string) $request->query('q', ''));
        $evento = (string) $request->query('evento', '');
        $usuarioId = $request->query('usuario_id');

        $consulta = Activity::query()->with('causer:id,name,email');

        if ($buscar !== '') {
            $consulta->where(function ($q) use ($buscar): void {
                $q->where('description', 'like', "%{$buscar}%")
                    ->orWhere('subject_type', 'like', "%{$buscar}%");
            });
        }

        if ($evento !== '') {
            $consulta->where('event', $evento);
        }

        if ($usuarioId !== null && $usuarioId !== '') {
            $consulta->where('causer_id', (int) $usuarioId);
        }

        $registros = $consulta
            ->latest('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Activity $act): array => [
                'id' => $act->id,
                'evento' => $act->event,
                'evento_etiqueta' => self::EVENTOS[$act->event ?? ''] ?? ($act->event ?: 'Acción'),
                'entidad' => self::MODELOS[$act->subject_type ?? ''] ?? class_basename((string) $act->subject_type),
                'entidad_id' => $act->subject_id,
                'descripcion' => $act->description,
                'usuario' => $act->causer ? $act->causer->name : 'Sistema',
                'propiedades' => $act->properties?->toArray() ?? [],
                'fecha' => $act->created_at?->format('d/m/Y H:i:s'),
            ]);

        return Inertia::render('core/auditoria', [
            'registros' => $registros,
            'filtros' => [
                'q' => $buscar,
                'evento' => $evento,
                'usuario_id' => $usuarioId ? (string) $usuarioId : '',
            ],
            'eventos' => array_map(
                static fn (string $k, string $v): array => ['value' => $k, 'label' => $v],
                array_keys(self::EVENTOS),
                array_values(self::EVENTOS)
            ),
            'usuarios' => User::orderBy('name')->get(['id', 'name']),
        ]);
    }
}
