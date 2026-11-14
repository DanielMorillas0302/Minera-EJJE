<?php

namespace App\Http\Controllers\Core;

use App\Enums\TipoSede;
use App\Http\Controllers\Controller;
use App\Models\Core\Area;
use App\Models\Core\Cargo;
use App\Models\Core\Sede;
use App\Models\Core\UnidadMedida;
use App\Models\ModeloBase;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CatalogoController extends Controller
{
    /**
     * Metadatos de catálogos administrables para unificar CRUD y evitar duplicación.
     *
     * @var array<string, array{modelo: class-string<ModeloBase>, titulo: string, singular: string}>
     */
    private const CATALOGOS = [
        'sedes' => [
            'modelo' => Sede::class,
            'titulo' => 'Sedes',
            'singular' => 'Sede',
        ],
        'areas' => [
            'modelo' => Area::class,
            'titulo' => 'Áreas',
            'singular' => 'Área',
        ],
        'cargos' => [
            'modelo' => Cargo::class,
            'titulo' => 'Cargos',
            'singular' => 'Cargo',
        ],
        'unidades-medida' => [
            'modelo' => UnidadMedida::class,
            'titulo' => 'Unidades de medida',
            'singular' => 'Unidad de medida',
        ],
    ];

    public function index(Request $request, string $tipo): Response
    {
        $meta = $this->resolverCatalogo($tipo);
        $clase = $meta['modelo'];

        $buscar = trim((string) $request->query('q', ''));
        $estado = (string) $request->query('estado', 'activos');
        $filtroExtra = (string) $request->query('filtro', '');

        $consulta = $clase::query();

        if ($tipo === 'areas') {
            $consulta->with('sede:id,nombre');
        }

        if ($estado === 'todos') {
            $consulta->withTrashed();
        } elseif ($estado === 'inactivos') {
            $consulta->withTrashed()->where('activo', false);
        } else {
            $consulta->where('activo', true);
        }

        if ($buscar !== '') {
            $consulta->where(function ($q) use ($buscar, $tipo): void {
                $q->where('nombre', 'like', "%{$buscar}%");
                if ($tipo === 'sedes') {
                    $q->orWhere('ubicacion', 'like', "%{$buscar}%");
                } elseif ($tipo === 'unidades-medida') {
                    $q->orWhere('abreviatura', 'like', "%{$buscar}%");
                }
            });
        }

        if ($filtroExtra !== '') {
            if ($tipo === 'sedes') {
                $consulta->where('tipo', $filtroExtra);
            } elseif ($tipo === 'areas') {
                $consulta->where('sede_id', (int) $filtroExtra);
            }
        }

        $registros = $consulta
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (ModeloBase $item): array => $this->formatearRegistro($tipo, $item));

        return Inertia::render('core/catalogos', [
            'tipo' => $tipo,
            'titulo' => $meta['titulo'],
            'singular' => $meta['singular'],
            'registros' => $registros,
            'filtros' => [
                'q' => $buscar,
                'estado' => $estado,
                'filtro' => $filtroExtra,
            ],
            'sedesDisponibles' => $tipo === 'areas'
                ? Sede::activos()->orderBy('nombre')->get(['id', 'nombre'])
                : [],
            'tiposSede' => $tipo === 'sedes'
                ? array_map(static fn (TipoSede $t): array => ['value' => $t->value, 'label' => $t->etiqueta()], TipoSede::cases())
                : [],
        ]);
    }

    public function store(Request $request, string $tipo): RedirectResponse
    {
        $meta = $this->resolverCatalogo($tipo);
        $datos = $this->validarDatos($request, $tipo);

        $meta['modelo']::create($datos);

        return back()->with('exito', "{$meta['singular']} creada correctamente.");
    }

    public function update(Request $request, string $tipo, int $id): RedirectResponse
    {
        $meta = $this->resolverCatalogo($tipo);
        /** @var ModeloBase $modelo */
        $modelo = $meta['modelo']::withTrashed()->findOrFail($id);

        $datos = $this->validarDatos($request, $tipo, $modelo->id);
        $modelo->update($datos);

        return back()->with('exito', "{$meta['singular']} actualizada correctamente.");
    }

    public function destroy(string $tipo, int $id): RedirectResponse
    {
        $meta = $this->resolverCatalogo($tipo);
        /** @var ModeloBase $modelo */
        $modelo = $meta['modelo']::findOrFail($id);

        if ($modelo instanceof Sede) {
            if ($modelo->areas()->where('activo', true)->exists()) {
                return back()->with('error', 'No se puede desactivar la sede porque tiene áreas activas asociadas.');
            }
            if ($modelo->usuarios()->where('activo', true)->exists()) {
                return back()->with('error', 'No se puede desactivar la sede porque tiene usuarios activos asignados.');
            }
        }

        $modelo->delete();

        return back()->with('exito', "{$meta['singular']} desactivada correctamente.");
    }

    public function restore(string $tipo, int $id): RedirectResponse
    {
        $meta = $this->resolverCatalogo($tipo);
        /** @var ModeloBase $modelo */
        $modelo = $meta['modelo']::withTrashed()->findOrFail($id);

        $modelo->restore();

        return back()->with('exito', "{$meta['singular']} reactivada correctamente.");
    }

    /**
     * @return array{modelo: class-string<ModeloBase>, titulo: string, singular: string}
     */
    private function resolverCatalogo(string $tipo): array
    {
        abort_unless(isset(self::CATALOGOS[$tipo]), 404);

        return self::CATALOGOS[$tipo];
    }

    /**
     * @return array<string, mixed>
     */
    private function validarDatos(Request $request, string $tipo, ?int $ignorarId = null): array
    {
        return match ($tipo) {
            'sedes' => $request->validate(
                [
                    'nombre' => ['required', 'string', 'max:100', Rule::unique('sedes', 'nombre')->ignore($ignorarId)],
                    'tipo' => ['required', 'string', Rule::in(TipoSede::valores())],
                    'ubicacion' => ['nullable', 'string', 'max:150'],
                ],
                [
                    'nombre.required' => 'El nombre de la sede es obligatorio.',
                    'nombre.unique' => 'Ya existe una sede con ese nombre.',
                    'tipo.required' => 'Selecciona el tipo de sede.',
                    'tipo.in' => 'El tipo de sede no es válido.',
                ]
            ),
            'areas' => $request->validate(
                [
                    'nombre' => [
                        'required',
                        'string',
                        'max:100',
                        Rule::unique('areas', 'nombre')
                            ->where(fn ($q) => $q->where('sede_id', $request->input('sede_id')))
                            ->ignore($ignorarId),
                    ],
                    'sede_id' => ['required', 'integer', 'exists:sedes,id'],
                ],
                [
                    'nombre.required' => 'El nombre del área es obligatorio.',
                    'nombre.unique' => 'Ya existe esa área en la sede seleccionada.',
                    'sede_id.required' => 'Selecciona una sede.',
                    'sede_id.exists' => 'La sede seleccionada no existe.',
                ]
            ),
            'cargos' => $request->validate(
                [
                    'nombre' => ['required', 'string', 'max:100', Rule::unique('cargos', 'nombre')->ignore($ignorarId)],
                ],
                [
                    'nombre.required' => 'El nombre del cargo es obligatorio.',
                    'nombre.unique' => 'Ya existe un cargo con ese nombre.',
                ]
            ),
            'unidades-medida' => $request->validate(
                [
                    'nombre' => ['required', 'string', 'max:80', Rule::unique('unidades_medida', 'nombre')->ignore($ignorarId)],
                    'abreviatura' => ['required', 'string', 'max:20', Rule::unique('unidades_medida', 'abreviatura')->ignore($ignorarId)],
                ],
                [
                    'nombre.required' => 'El nombre de la unidad es obligatorio.',
                    'nombre.unique' => 'Ya existe una unidad con ese nombre.',
                    'abreviatura.required' => 'La abreviatura es obligatoria.',
                    'abreviatura.unique' => 'Ya existe una unidad con esa abreviatura.',
                ]
            ),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function formatearRegistro(string $tipo, ModeloBase $item): array
    {
        $base = [
            'id' => $item->id,
            'nombre' => $item->getAttribute('nombre'),
            'activo' => (bool) $item->getAttribute('activo'),
            'creado_en' => $item->getAttribute('creado_en')?->format('d/m/Y H:i'),
        ];

        return match ($tipo) {
            'sedes' => [
                ...$base,
                'tipo' => $item instanceof Sede ? $item->tipo->value : '',
                'tipo_etiqueta' => $item instanceof Sede ? $item->tipo->etiqueta() : '',
                'ubicacion' => $item->getAttribute('ubicacion'),
            ],
            'areas' => [
                ...$base,
                'sede_id' => $item->getAttribute('sede_id'),
                'sede_nombre' => $item instanceof Area ? $item->sede?->nombre : null,
            ],
            'unidades-medida' => [
                ...$base,
                'abreviatura' => $item->getAttribute('abreviatura'),
            ],
            default => $base,
        };
    }
}
