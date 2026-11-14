<?php

namespace App\Http\Controllers\Core;

use App\Http\Controllers\Controller;
use App\Models\Core\Area;
use App\Models\Core\Cargo;
use App\Models\Core\Sede;
use App\Models\Core\UnidadMedida;
use App\Models\User;
use Inertia\Inertia;
use Inertia\Response;
use Spatie\Activitylog\Models\Activity;

class AdministracionController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('core/index', [
            'resumen' => [
                'usuarios' => User::where('activo', true)->count(),
                'sedes' => Sede::activos()->count(),
                'areas' => Area::activos()->count(),
                'cargos' => Cargo::activos()->count(),
                'unidades' => UnidadMedida::activos()->count(),
                'auditoria' => Activity::count(),
            ],
        ]);
    }
}
