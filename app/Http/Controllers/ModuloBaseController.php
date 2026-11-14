<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ModuloBaseController extends Controller
{
    /**
     * Catálogo de módulos principales para navegación y vistas base de Fase 0.
     *
     * @var array<string, array{titulo: string, fase: string, descripcion: string, ruta: string}>
     */
    private const MODULOS = [
        'rrhh' => [
            'titulo' => 'Recursos Humanos',
            'fase' => 'Fase 2 – RR.HH. (MVP)',
            'descripcion' => 'Gestión de trabajadores, exámenes ocupacionales, aptitud de viaje a Pataz, vacaciones, permisos y movimientos de personal.',
            'ruta' => '/rrhh',
        ],
        'logistica' => [
            'titulo' => 'Logística y Abastecimiento',
            'fase' => 'Fase 3 – Logística (MVP)',
            'descripcion' => 'Materiales, proveedores, requerimientos, almacenes Trujillo/Pataz, kardex, envíos y recepciones.',
            'ruta' => '/logistica',
        ],
        'finanzas' => [
            'titulo' => 'Finanzas',
            'fase' => 'Fase 5 – Finanzas',
            'descripcion' => 'Centros de costo, categorías de gasto, movimientos financieros vinculados y presupuestos mensuales.',
            'ruta' => '/finanzas',
        ],
        'gerencia' => [
            'titulo' => 'Gerencia',
            'fase' => 'Fase 6 – Gerencia y reportes',
            'descripcion' => 'Tablero ejecutivo con KPIs consolidados, gráficos por sede/fecha y reporte PDF.',
            'ruta' => '/gerencia',
        ],
        'reportes' => [
            'titulo' => 'Reportes e Importación',
            'fase' => 'Fase 4 y Fase 6',
            'descripcion' => 'Exportación Excel/PDF y carga masiva mediante plantillas descargables.',
            'ruta' => '/reportes',
        ],
        'administracion' => [
            'titulo' => 'Administración del Sistema',
            'fase' => 'Fase 1 – Core',
            'descripcion' => 'Gestión de usuarios, roles, catálogos (sedes, áreas, cargos, unidades) y auditoría.',
            'ruta' => '/administracion',
        ],
    ];

    public function inicio(Request $request): Response
    {
        return Inertia::render('dashboard', [
            'resumenModulos' => self::MODULOS,
        ]);
    }

    public function mostrar(Request $request, string $modulo): Response
    {
        abort_unless(isset(self::MODULOS[$modulo]), 404);

        return Inertia::render('modulo-en-construccion', [
            'modulo' => $modulo,
            ...self::MODULOS[$modulo],
        ]);
    }
}
