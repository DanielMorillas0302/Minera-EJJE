<?php

namespace App\Http\Controllers\Logistica;

use App\Http\Controllers\Controller;
use App\Models\Core\Sede;
use App\Models\Core\UnidadMedida;
use App\Models\Logistica\Almacen;
use App\Models\Logistica\CategoriaMaterial;
use App\Models\Logistica\Material;
use App\Models\Logistica\Proveedor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CatalogoLogisticaController extends Controller
{
    public function materiales(Request $request): Response
    {
        $buscar = trim((string) $request->query('buscar', ''));
        $categoriaId = $request->query('categoria_id');
        $estado = $request->query('estado', 'activos');

        $materiales = Material::query()
            ->when($estado === 'inactivos', static fn ($q) => $q->where('activo', false))
            ->when($estado === 'activos', static fn ($q) => $q->where('activo', true))
            ->when($buscar !== '', static function ($query) use ($buscar): void {
                $query->where(static function ($q) use ($buscar): void {
                    $q->where('codigo', 'like', "%{$buscar}%")
                        ->orWhere('nombre', 'like', "%{$buscar}%");
                });
            })
            ->when($categoriaId, static fn ($q) => $q->where('categoria_id', $categoriaId))
            ->with(['categoria', 'unidadMedida'])
            ->withSum('stocks as stock_total', 'cantidad')
            ->orderBy('codigo')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('logistica/materiales', [
            'materiales' => $materiales,
            'categorias' => CategoriaMaterial::query()->orderBy('nombre')->get(),
            'unidadesMedida' => UnidadMedida::query()->activos()->orderBy('nombre')->get(),
            'almacenes' => Almacen::query()->with('sede')->orderBy('codigo')->get(),
            'sedes' => Sede::query()->activos()->orderBy('nombre')->get(),
            'filtros' => [
                'buscar' => $buscar,
                'categoria_id' => $categoriaId ? (int) $categoriaId : null,
                'estado' => $estado,
            ],
        ]);
    }

    public function storeMaterial(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:30', Rule::unique('materiales', 'codigo')],
            'nombre' => ['required', 'string', 'max:160'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'categoria_id' => ['required', 'exists:categorias_material,id'],
            'unidad_medida_id' => ['required', 'exists:unidades_medida,id'],
            'stock_minimo' => ['required', 'numeric', 'min:0'],
            'costo_referencial' => ['required', 'numeric', 'min:0'],
        ], [
            'codigo.unique' => 'Ya existe un material con ese código.',
        ]);

        Material::query()->create(array_merge($datos, [
            'codigo' => strtoupper(trim($datos['codigo'])),
            'activo' => true,
        ]));

        return back()->with('exito', 'Material registrado correctamente.');
    }

    public function updateMaterial(Request $request, Material $material): RedirectResponse
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:30', Rule::unique('materiales', 'codigo')->ignore($material->id)],
            'nombre' => ['required', 'string', 'max:160'],
            'descripcion' => ['nullable', 'string', 'max:255'],
            'categoria_id' => ['required', 'exists:categorias_material,id'],
            'unidad_medida_id' => ['required', 'exists:unidades_medida,id'],
            'stock_minimo' => ['required', 'numeric', 'min:0'],
            'costo_referencial' => ['required', 'numeric', 'min:0'],
        ]);

        $material->update(array_merge($datos, [
            'codigo' => strtoupper(trim($datos['codigo'])),
        ]));

        return back()->with('exito', 'Material actualizado correctamente.');
    }

    public function toggleMaterial(Material $material): RedirectResponse
    {
        $nuevoEstado = ! $material->activo;
        $material->update(['activo' => $nuevoEstado]);

        return back()->with('exito', $nuevoEstado ? 'Material activado.' : 'Material desactivado.');
    }

    public function storeCategoria(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('categorias_material', 'nombre')],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        CategoriaMaterial::query()->create(array_merge($datos, ['activo' => true]));

        return back()->with('exito', 'Categoría creada correctamente.');
    }

    public function updateCategoria(Request $request, CategoriaMaterial $categoria): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100', Rule::unique('categorias_material', 'nombre')->ignore($categoria->id)],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ]);

        $categoria->update($datos);

        return back()->with('exito', 'Categoría actualizada correctamente.');
    }

    public function toggleCategoria(CategoriaMaterial $categoria): RedirectResponse
    {
        $nuevoEstado = ! $categoria->activo;
        $categoria->update(['activo' => $nuevoEstado]);

        return back()->with('exito', $nuevoEstado ? 'Categoría activada.' : 'Categoría desactivada.');
    }

    public function storeAlmacen(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'codigo' => ['required', 'string', 'max:20', Rule::unique('almacenes', 'codigo')],
            'nombre' => ['required', 'string', 'max:120'],
            'sede_id' => ['required', 'exists:sedes,id'],
            'es_principal' => ['boolean'],
        ]);

        Almacen::query()->create(array_merge($datos, [
            'codigo' => strtoupper(trim($datos['codigo'])),
            'es_principal' => (bool) ($datos['es_principal'] ?? false),
            'activo' => true,
        ]));

        return back()->with('exito', 'Almacén registrado correctamente.');
    }

    public function proveedores(Request $request): Response
    {
        $buscar = trim((string) $request->query('buscar', ''));
        $estado = $request->query('estado', 'activos');

        $proveedores = Proveedor::query()
            ->when($estado === 'inactivos', static fn ($q) => $q->where('activo', false))
            ->when($estado === 'activos', static fn ($q) => $q->where('activo', true))
            ->when($buscar !== '', static function ($query) use ($buscar): void {
                $query->where(static function ($q) use ($buscar): void {
                    $q->where('ruc', 'like', "%{$buscar}%")
                        ->orWhere('razon_social', 'like', "%{$buscar}%")
                        ->orWhere('contacto', 'like', "%{$buscar}%");
                });
            })
            ->orderBy('razon_social')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('logistica/proveedores', [
            'proveedores' => $proveedores,
            'filtros' => [
                'buscar' => $buscar,
                'estado' => $estado,
            ],
        ]);
    }

    public function storeProveedor(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'ruc' => ['required', 'string', 'regex:/^\d{11}$/', Rule::unique('proveedores', 'ruc')],
            'razon_social' => ['required', 'string', 'max:180'],
            'contacto' => ['nullable', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'condicion_pago' => ['required', 'string', 'max:60'],
            'observaciones' => ['nullable', 'string'],
        ], [
            'ruc.regex' => 'El RUC debe tener exactamente 11 dígitos numéricos.',
            'ruc.unique' => 'Ya existe un proveedor registrado con ese RUC.',
        ]);

        Proveedor::query()->create(array_merge($datos, ['activo' => true]));

        return back()->with('exito', 'Proveedor registrado correctamente.');
    }

    public function updateProveedor(Request $request, Proveedor $proveedor): RedirectResponse
    {
        $datos = $request->validate([
            'ruc' => ['required', 'string', 'regex:/^\d{11}$/', Rule::unique('proveedores', 'ruc')->ignore($proveedor->id)],
            'razon_social' => ['required', 'string', 'max:180'],
            'contacto' => ['nullable', 'string', 'max:120'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:120'],
            'direccion' => ['nullable', 'string', 'max:200'],
            'condicion_pago' => ['required', 'string', 'max:60'],
            'observaciones' => ['nullable', 'string'],
        ], [
            'ruc.regex' => 'El RUC debe tener exactamente 11 dígitos numéricos.',
        ]);

        $proveedor->update($datos);

        return back()->with('exito', 'Proveedor actualizado correctamente.');
    }

    public function toggleProveedor(Proveedor $proveedor): RedirectResponse
    {
        $nuevoEstado = ! $proveedor->activo;
        $proveedor->update(['activo' => $nuevoEstado]);

        return back()->with('exito', $nuevoEstado ? 'Proveedor activado.' : 'Proveedor desactivado.');
    }
}
