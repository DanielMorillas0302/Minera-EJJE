<?php

namespace App\Http\Controllers\Finanzas;

use App\Enums\TipoMovimientoFinanciero;
use App\Http\Controllers\Controller;
use App\Models\Core\Area;
use App\Models\Finanzas\CategoriaGasto;
use App\Models\Finanzas\CentroCosto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CatalogoFinanzasController extends Controller
{
    public function index(): Response
    {
        $centrosCosto = CentroCosto::query()
            ->with('area')
            ->orderBy('codigo')
            ->get()
            ->map(fn (CentroCosto $c): array => [
                'id' => $c->id,
                'codigo' => $c->codigo,
                'nombre' => $c->nombre,
                'area_id' => $c->area_id,
                'area_nombre' => $c->area?->nombre ?? 'Sin área asignada',
                'descripcion' => $c->descripcion,
                'activo' => (bool) $c->activo,
                'movimientos_count' => $c->movimientos()->count(),
            ]);

        $categorias = CategoriaGasto::query()
            ->orderBy('tipo')
            ->orderBy('nombre')
            ->get()
            ->map(fn (CategoriaGasto $c): array => [
                'id' => $c->id,
                'nombre' => $c->nombre,
                'tipo' => $c->tipo->value,
                'tipo_etiqueta' => $c->tipo->etiqueta(),
                'descripcion' => $c->descripcion,
                'activo' => (bool) $c->activo,
                'movimientos_count' => $c->movimientos()->count(),
            ]);

        $areas = Area::query()
            ->where('activo', true)
            ->orderBy('nombre')
            ->get(['id', 'nombre']);

        return Inertia::render('finanzas/catalogos', [
            'centrosCosto' => $centrosCosto,
            'categorias' => $categorias,
            'areas' => $areas,
            'tiposMovimiento' => TipoMovimientoFinanciero::valores(),
        ]);
    }

    public function storeCentroCosto(Request $request): RedirectResponse
    {
        $validados = $request->validate([
            'codigo' => ['required', 'string', 'max:30', 'unique:centros_costo,codigo'],
            'nombre' => ['required', 'string', 'max:120'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ], [
            'codigo.required' => 'El código del centro de costo es obligatorio.',
            'codigo.unique' => 'Ya existe un centro de costo con este código.',
            'nombre.required' => 'El nombre del centro de costo es obligatorio.',
        ]);

        CentroCosto::create([
            ...$validados,
            'activo' => true,
        ]);

        return back()->with('exito', 'Centro de costo registrado exitosamente.');
    }

    public function updateCentroCosto(Request $request, CentroCosto $centroCosto): RedirectResponse
    {
        $validados = $request->validate([
            'codigo' => ['required', 'string', 'max:30', Rule::unique('centros_costo', 'codigo')->ignore($centroCosto->id)],
            'nombre' => ['required', 'string', 'max:120'],
            'area_id' => ['nullable', 'integer', 'exists:areas,id'],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ], [
            'codigo.required' => 'El código del centro de costo es obligatorio.',
            'codigo.unique' => 'Ya existe un centro de costo con este código.',
            'nombre.required' => 'El nombre del centro de costo es obligatorio.',
        ]);

        $centroCosto->update($validados);

        return back()->with('exito', 'Centro de costo actualizado exitosamente.');
    }

    public function toggleCentroCosto(CentroCosto $centroCosto): RedirectResponse
    {
        $centroCosto->activo = ! $centroCosto->activo;
        $centroCosto->save();

        $estado = $centroCosto->activo ? 'activado' : 'desactivado';

        return back()->with('exito', "Centro de costo {$estado} correctamente.");
    }

    public function storeCategoriaGasto(Request $request): RedirectResponse
    {
        $validados = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::in(TipoMovimientoFinanciero::valores())],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ], [
            'nombre.required' => 'El nombre de la categoría es obligatorio.',
            'tipo.required' => 'El tipo de movimiento es obligatorio.',
        ]);

        CategoriaGasto::create([
            ...$validados,
            'activo' => true,
        ]);

        return back()->with('exito', 'Categoría registrada exitosamente.');
    }

    public function updateCategoriaGasto(Request $request, CategoriaGasto $categoriaGasto): RedirectResponse
    {
        $validados = $request->validate([
            'nombre' => ['required', 'string', 'max:120'],
            'tipo' => ['required', Rule::in(TipoMovimientoFinanciero::valores())],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ], [
            'nombre.required' => 'El nombre de la categoría es obligatorio.',
            'tipo.required' => 'El tipo de movimiento es obligatorio.',
        ]);

        $categoriaGasto->update($validados);

        return back()->with('exito', 'Categoría actualizada exitosamente.');
    }

    public function toggleCategoriaGasto(CategoriaGasto $categoriaGasto): RedirectResponse
    {
        $categoriaGasto->activo = ! $categoriaGasto->activo;
        $categoriaGasto->save();

        $estado = $categoriaGasto->activo ? 'activada' : 'desactivada';

        return back()->with('exito', "Categoría {$estado} correctamente.");
    }
}
