<?php

namespace App\Http\Controllers\Reportes;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportesHubController extends Controller
{
    public function index(Request $request): Response
    {
        $usuario = $request->user();

        $modulosReportes = [
            [
                'modulo' => 'Gerencia General',
                'clave' => 'gerencia',
                'descripcion' => 'Informes ejecutivos consolidados e indicadores estratégicos para la toma de decisiones.',
                'items' => [
                    [
                        'id' => 'informe_ejecutivo',
                        'titulo' => 'Informe Ejecutivo Gerencial',
                        'descripcion' => 'Compendio gerencial mensual de balance financiero, fuerza laboral y cadena de suministro.',
                        'formato' => 'PDF',
                        'icono' => 'FileText',
                        'url_vista' => '/gerencia',
                        'url_descarga' => '/gerencia/reporte-pdf',
                        'recurso' => 'dashboard_gerencial',
                    ],
                ],
            ],
            [
                'modulo' => 'Finanzas y Costos',
                'clave' => 'finanzas',
                'descripcion' => 'Control presupuestario, flujo de ingresos y egresos, cuentas por pagar y centros de costo.',
                'items' => [
                    [
                        'id' => 'presupuesto_real',
                        'titulo' => 'Presupuesto vs Gasto Real',
                        'descripcion' => 'Desviación presupuestal mensual por centro de costo con alertas de sobregiro.',
                        'formato' => 'PDF y XLSX',
                        'icono' => 'Scale',
                        'url_vista' => '/finanzas/reportes?tab=presupuesto_real',
                        'url_descarga' => '/finanzas/reportes/presupuesto-pdf',
                        'recurso' => 'presupuesto',
                    ],
                    [
                        'id' => 'egresos_cc',
                        'titulo' => 'Egresos por Centro de Costo',
                        'descripcion' => 'Consolidado analítico de costos operativos imputados a mina, planta y administración.',
                        'formato' => 'XLSX',
                        'icono' => 'FolderTree',
                        'url_vista' => '/finanzas/reportes?tab=egresos_centro_costo',
                        'url_descarga' => '/finanzas/reportes/exportar?tipo_reporte=egresos_cc',
                        'recurso' => 'movimientos_financieros',
                    ],
                    [
                        'id' => 'egresos_cat',
                        'titulo' => 'Egresos por Categoría de Gasto',
                        'descripcion' => 'Gastos operacionales agrupados por rubro (combustibles, repuestos, fletes, etc.).',
                        'formato' => 'XLSX',
                        'icono' => 'Tag',
                        'url_vista' => '/finanzas/reportes?tab=egresos_categoria',
                        'url_descarga' => '/finanzas/reportes/exportar?tipo_reporte=egresos_cat',
                        'recurso' => 'movimientos_financieros',
                    ],
                    [
                        'id' => 'cuentas_pagar',
                        'titulo' => 'Cuentas por Pagar y Vencimientos',
                        'descripcion' => 'Cartera de pasivos pendientes ordenada por semáforo de vencimiento.',
                        'formato' => 'XLSX',
                        'icono' => 'Clock',
                        'url_vista' => '/finanzas/cuentas-por-pagar',
                        'url_descarga' => '/finanzas/reportes/exportar?tipo_reporte=cuentas_pagar',
                        'recurso' => 'movimientos_financieros',
                    ],
                ],
            ],
            [
                'modulo' => 'Recursos Humanos',
                'clave' => 'rrhh',
                'descripcion' => 'Distribución de personal, aptitud médica para Pataz, vacaciones y traslados.',
                'items' => [
                    [
                        'id' => 'personal_sede_area',
                        'titulo' => 'Personal por Sede y Área',
                        'descripcion' => 'Padrón de trabajadores activos clasificados por sede base y área de trabajo.',
                        'formato' => 'XLSX',
                        'icono' => 'Users',
                        'url_vista' => '/rrhh/reportes?tab=personal_sede_area',
                        'url_descarga' => '/rrhh/reportes/exportar?tipo_reporte=personal_sede_area',
                        'recurso' => 'trabajadores',
                    ],
                    [
                        'id' => 'examenes_medicos',
                        'titulo' => 'Exámenes Médicos Ocupacionales',
                        'descripcion' => 'Certificados vigentes, por vencer y estado de aptitud médica para ascenso a mina.',
                        'formato' => 'XLSX',
                        'icono' => 'ShieldCheck',
                        'url_vista' => '/rrhh/reportes?tab=examenes_por_vencer',
                        'url_descarga' => '/rrhh/reportes/exportar?tipo_reporte=examenes_por_vencer',
                        'recurso' => 'examenes',
                    ],
                    [
                        'id' => 'vacaciones',
                        'titulo' => 'Vacaciones y Saldos del Período',
                        'descripcion' => 'Días gozados, programados y pendientes de descanso físico por colaborador.',
                        'formato' => 'XLSX',
                        'icono' => 'Calendar',
                        'url_vista' => '/rrhh/reportes?tab=vacaciones_periodo',
                        'url_descarga' => '/rrhh/reportes/exportar?tipo_reporte=vacaciones_periodo',
                        'recurso' => 'vacaciones_permisos',
                    ],
                    [
                        'id' => 'movimientos_personal',
                        'titulo' => 'Movimientos Trujillo ↔ Pataz',
                        'descripcion' => 'Historial de subidas a campamento y retornos con control de transporte.',
                        'formato' => 'XLSX',
                        'icono' => 'Truck',
                        'url_vista' => '/rrhh/reportes?tab=movimientos_personal',
                        'url_descarga' => '/rrhh/reportes/exportar?tipo_reporte=movimientos_personal',
                        'recurso' => 'movimientos_personal',
                    ],
                ],
            ],
            [
                'modulo' => 'Logística y Almacenes',
                'clave' => 'logistica',
                'descripcion' => 'Existencias físicas, kardex valorizado, despachos intersedes y requerimientos.',
                'items' => [
                    [
                        'id' => 'stock_actual',
                        'titulo' => 'Stock Actual en Almacenes',
                        'descripcion' => 'Saldos de inventario en Trujillo y Pataz con alertas de stock mínimo.',
                        'formato' => 'XLSX',
                        'icono' => 'Boxes',
                        'url_vista' => '/logistica/reportes?tab=stock_actual',
                        'url_descarga' => '/logistica/reportes/exportar?tipo_reporte=stock_actual',
                        'recurso' => 'stock_kardex',
                    ],
                    [
                        'id' => 'kardex_inventario',
                        'titulo' => 'Kardex de Inventario',
                        'descripcion' => 'Movimientos valorizados de entrada, salida, consumos y transferencias.',
                        'formato' => 'PDF',
                        'icono' => 'FileBarChart2',
                        'url_vista' => '/logistica/kardex',
                        'url_descarga' => '/logistica/reportes/kardex-pdf',
                        'recurso' => 'stock_kardex',
                    ],
                    [
                        'id' => 'envios_trazabilidad',
                        'titulo' => 'Envíos Trujillo → Pataz',
                        'descripcion' => 'Trazabilidad de guías de remisión, transportistas y estados de recepción.',
                        'formato' => 'XLSX',
                        'icono' => 'Truck',
                        'url_vista' => '/logistica/reportes?tab=envios_estado',
                        'url_descarga' => '/logistica/reportes/exportar?tipo_reporte=envios_estado',
                        'recurso' => 'envios',
                    ],
                    [
                        'id' => 'diferencias_recepcion',
                        'titulo' => 'Diferencias en Recepción Pataz',
                        'descripcion' => 'Discrepancias cuantitativas y observaciones registradas al recibir envíos en mina.',
                        'formato' => 'XLSX',
                        'icono' => 'AlertTriangle',
                        'url_vista' => '/logistica/reportes?tab=diferencias_recepcion',
                        'url_descarga' => '/logistica/reportes/exportar?tipo_reporte=diferencias_recepcion',
                        'recurso' => 'recepciones_consumos',
                    ],
                ],
            ],
        ];

        // Filtrar según permisos del usuario
        $reportesFiltrados = [];
        foreach ($modulosReportes as $seccion) {
            $itemsVisibles = array_filter($seccion['items'], function ($item) use ($usuario): bool {
                if ($usuario->esAdministrador()) {
                    return true;
                }

                return $usuario->puede($item['recurso'], 'L');
            });

            if (! empty($itemsVisibles)) {
                $seccion['items'] = array_values($itemsVisibles);
                $reportesFiltrados[] = $seccion;
            }
        }

        $totalReportes = array_reduce($reportesFiltrados, fn ($carry, $sec) => $carry + count($sec['items']), 0);

        return Inertia::render('reportes/index', [
            'modulos' => $reportesFiltrados,
            'totalReportes' => $totalReportes,
            'rolUsuario' => $usuario->rol?->value ?? '',
            'rolEtiqueta' => $usuario->rol?->etiqueta() ?? 'Usuario',
        ]);
    }
}
