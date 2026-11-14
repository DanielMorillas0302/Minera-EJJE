<?php

namespace App\Http\Controllers\Logistica;

use App\Enums\EstadoRequerimiento;
use App\Enums\PrioridadRequerimiento;
use App\Http\Controllers\Controller;
use App\Models\Core\Area;
use App\Models\Core\Sede;
use App\Models\Logistica\Material;
use App\Models\Logistica\Requerimiento;
use App\Models\User;
use App\Services\Logistica\AlmacenScopeService;
use App\Services\Logistica\RequerimientoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class RequerimientoController extends Controller
{
    public function __construct(
        private readonly RequerimientoService $requerimientos,
        private readonly AlmacenScopeService $scope,
    ) {}

    public function index(Request $request): Response
    {
        /** @var User $usuario */
        $usuario = $request->user();
        $estado = $request->query('estado');
        $prioridad = $request->query('prioridad');
        $sedeId = $request->query('sede_id');
        $buscar = trim((string) $request->query('buscar', ''));

        $lista = Requerimiento::query()
            ->with([
                'area',
                'sede',
                'solicitante',
                'aprobadoPor',
                'detalles.material.unidadMedida',
            ])
            ->when($this->scope->esRecepcionPataz($usuario), static function ($query) use ($usuario): void {
                $query->where('solicitante_id', $usuario->id);
            })
            ->when($estado, static fn ($q) => $q->where('estado', $estado))
            ->when($prioridad, static fn ($q) => $q->where('prioridad', $prioridad))
            ->when($sedeId, static fn ($q) => $q->where('sede_id', (int) $sedeId))
            ->when($buscar !== '', static function ($q) use ($buscar): void {
                $q->where(static function ($sub) use ($buscar): void {
                    $sub->where('codigo', 'like', "%{$buscar}%")
                        ->orWhere('observaciones', 'like', "%{$buscar}%");
                });
            })
            ->latest('fecha_solicitud')
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('logistica/requerimientos', [
            'requerimientos' => $lista,
            'areas' => Area::query()->activos()->orderBy('nombre')->get(),
            'sedes' => Sede::query()->activos()->orderBy('nombre')->get(),
            'materiales' => Material::query()->with('unidadMedida')->activos()->orderBy('codigo')->get(),
            'estados' => array_map(
                static fn (EstadoRequerimiento $e): array => ['value' => $e->value, 'label' => $e->etiqueta()],
                EstadoRequerimiento::cases()
            ),
            'prioridades' => array_map(
                static fn (PrioridadRequerimiento $p): array => ['value' => $p->value, 'label' => $p->etiqueta()],
                PrioridadRequerimiento::cases()
            ),
            'filtros' => [
                'estado' => $estado ?: null,
                'prioridad' => $prioridad ?: null,
                'sede_id' => $sedeId ? (int) $sedeId : null,
                'buscar' => $buscar,
            ],
            'esRecepcionPataz' => $this->scope->esRecepcionPataz($usuario),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        $datos = $request->validate([
            'fecha_solicitud' => ['required', 'date'],
            'area_id' => ['required', 'exists:areas,id'],
            'sede_id' => ['required', 'exists:sedes,id'],
            'prioridad' => ['required', Rule::in(PrioridadRequerimiento::valores())],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'enviar' => ['boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'distinct', 'exists:materiales,id'],
            'items.*.cantidad_solicitada' => ['required', 'numeric', 'gt:0'],
            'items.*.observacion' => ['nullable', 'string', 'max:255'],
        ], [
            'items.min' => 'Debe seleccionar al menos un material.',
            'items.*.material_id.distinct' => 'No repita el mismo material dentro del requerimiento.',
        ]);

        $req = $this->requerimientos->crear(
            datos: $datos,
            items: $datos['items'],
            solicitante: $usuario,
            enviarInmediatamente: (bool) ($datos['enviar'] ?? false),
        );

        return back()->with('exito', "Requerimiento {$req->codigo} registrado correctamente.");
    }

    public function update(Request $request, Requerimiento $requerimiento): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        abort_if(
            $this->scope->esRecepcionPataz($usuario) && (int) $requerimiento->solicitante_id !== (int) $usuario->id,
            403,
            'Solo puede modificar sus propios requerimientos.'
        );

        $datos = $request->validate([
            'fecha_solicitud' => ['required', 'date'],
            'area_id' => ['required', 'exists:areas,id'],
            'sede_id' => ['required', 'exists:sedes,id'],
            'prioridad' => ['required', Rule::in(PrioridadRequerimiento::valores())],
            'observaciones' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.material_id' => ['required', 'distinct', 'exists:materiales,id'],
            'items.*.cantidad_solicitada' => ['required', 'numeric', 'gt:0'],
            'items.*.observacion' => ['nullable', 'string', 'max:255'],
        ]);

        $this->requerimientos->actualizarBorrador($requerimiento, $datos, $datos['items']);

        return back()->with('exito', "Requerimiento {$requerimiento->codigo} actualizado.");
    }

    public function enviar(Request $request, Requerimiento $requerimiento): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        abort_if(
            $this->scope->esRecepcionPataz($usuario) && (int) $requerimiento->solicitante_id !== (int) $usuario->id,
            403,
            'Solo puede enviar sus propios requerimientos.'
        );

        $this->requerimientos->enviar($requerimiento);

        return back()->with('exito', "Requerimiento {$requerimiento->codigo} enviado a aprobación.");
    }

    public function aprobar(Request $request, Requerimiento $requerimiento): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        $this->requerimientos->aprobar($requerimiento, $usuario);

        return back()->with('exito', "Requerimiento {$requerimiento->codigo} aprobado.");
    }

    public function rechazar(Request $request, Requerimiento $requerimiento): RedirectResponse
    {
        /** @var User $usuario */
        $usuario = $request->user();

        $datos = $request->validate([
            'motivo_rechazo' => ['required', 'string', 'max:1000'],
        ], [
            'motivo_rechazo.required' => 'Debe indicar el motivo del rechazo.',
        ]);

        $this->requerimientos->rechazar($requerimiento, $usuario, $datos['motivo_rechazo']);

        return back()->with('exito', "Requerimiento {$requerimiento->codigo} rechazado.");
    }
}
