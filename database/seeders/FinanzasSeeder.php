<?php

namespace Database\Seeders;

use App\Enums\EstadoPago;
use App\Enums\TipoComprobante;
use App\Enums\TipoMovimientoFinanciero;
use App\Models\Core\Area;
use App\Models\Finanzas\CategoriaGasto;
use App\Models\Finanzas\CentroCosto;
use App\Models\Finanzas\MovimientoFinanciero;
use App\Models\Finanzas\Presupuesto;
use App\Models\Logistica\Envio;
use App\Models\Logistica\Proveedor;
use App\Models\Rrhh\Trabajador;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FinanzasSeeder extends Seeder
{
    public function run(): void
    {
        $centrosMap = $this->seedCentrosCosto();
        $categoriasMap = $this->seedCategoriasGasto();

        if (! (bool) config('app.debug')) {
            return;
        }

        $this->seedPresupuestos($centrosMap);
        $this->seedMovimientosFinancieros($centrosMap, $categoriasMap);
    }

    /**
     * @return array<string, int>
     */
    private function seedCentrosCosto(): array
    {
        $operacionesId = Area::query()->where('nombre', 'Operaciones')->value('id');
        $logisticaId = Area::query()->where('nombre', 'Logística')->value('id');
        $rrhhId = Area::query()->where('nombre', 'RR.HH.')->value('id');
        $finanzasId = Area::query()->where('nombre', 'Finanzas')->value('id');

        $centros = [
            [
                'codigo' => 'CC-01',
                'nombre' => 'Operación Mina Pataz',
                'area_id' => $operacionesId,
                'descripcion' => 'Costos directos de extracción, perforación y sostenimiento en mina subterránea',
            ],
            [
                'codigo' => 'CC-02',
                'nombre' => 'Planta de Beneficio y Procesamiento',
                'area_id' => $operacionesId,
                'descripcion' => 'Costos de molienda, flotación, reactivos y procesamiento metalúrgico',
            ],
            [
                'codigo' => 'CC-03',
                'nombre' => 'Logística y Transporte Pataz',
                'area_id' => $logisticaId,
                'descripcion' => 'Fletes, transporte de carga Trujillo - Pataz y almacenaje de materiales',
            ],
            [
                'codigo' => 'CC-04',
                'nombre' => 'Mantenimiento Mecánico y Eléctrico',
                'area_id' => $operacionesId,
                'descripcion' => 'Reparación de compresores, grupos electrógenos, volquetes y scoop',
            ],
            [
                'codigo' => 'CC-05',
                'nombre' => 'Administración y Campamento',
                'area_id' => $finanzasId ?? $rrhhId,
                'descripcion' => 'Alimentación, hospedaje, comunicaciones y gestión administrativa general',
            ],
        ];

        $map = [];
        foreach ($centros as $c) {
            $modelo = CentroCosto::updateOrCreate(
                ['codigo' => $c['codigo']],
                [
                    'nombre' => $c['nombre'],
                    'area_id' => $c['area_id'],
                    'descripcion' => $c['descripcion'],
                    'activo' => true,
                ]
            );
            $map[$c['codigo']] = $modelo->id;
        }

        return $map;
    }

    /**
     * @return array<string, int>
     */
    private function seedCategoriasGasto(): array
    {
        $categorias = [
            // Egresos
            [
                'nombre' => 'Combustibles y Lubricantes',
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'descripcion' => 'Diésel D-2 para generadores y maquinaria, aceites hidráulicos y grasas',
            ],
            [
                'nombre' => 'Repuestos y Servicios de Mantenimiento',
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'descripcion' => 'Repuestos de perforadoras, filtros, mangueras y servicios técnicos especializados',
            ],
            [
                'nombre' => 'Transporte y Fletes Trujillo-Pataz',
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'descripcion' => 'Fletes de carga pesada, transferencias de material y encomiendas mineras',
            ],
            [
                'nombre' => 'EPP y Seguridad Minera',
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'descripcion' => 'Botas, cascos, mamelucos térmicos, respiradores y arneses de seguridad',
            ],
            [
                'nombre' => 'Alimentación y Campamento',
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'descripcion' => 'Catering para el personal de mina, víveres y mantenimiento del campamento en Pataz',
            ],
            [
                'nombre' => 'Planilla y Servicios Profesionales',
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'descripcion' => 'Honorarios profesionales, asesorías contables/legales y servicios de salud ocupacional',
            ],
            [
                'nombre' => 'Herramientas y Materiales Menores',
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'descripcion' => 'Palas, picos, barretillas, discos de corte y ferretería en general',
            ],

            // Ingresos
            [
                'nombre' => 'Venta de Concentrado y Mineral',
                'tipo' => TipoMovimientoFinanciero::INGRESO->value,
                'descripcion' => 'Liquidaciones comerciales por venta de concentrado de mineral a refinería',
            ],
            [
                'nombre' => 'Servicios y Alquiler de Maquinaria',
                'tipo' => TipoMovimientoFinanciero::INGRESO->value,
                'descripcion' => 'Servicios de carga y apoyo mecánico prestados a contratistas terceros',
            ],
        ];

        $map = [];
        foreach ($categorias as $cat) {
            $modelo = CategoriaGasto::updateOrCreate(
                [
                    'nombre' => $cat['nombre'],
                    'tipo' => $cat['tipo'],
                ],
                [
                    'descripcion' => $cat['descripcion'],
                    'activo' => true,
                ]
            );
            $map[$cat['nombre']] = $modelo->id;
        }

        return $map;
    }

    /**
     * @param  array<string, int>  $centrosMap
     */
    private function seedPresupuestos(array $centrosMap): void
    {
        $anio = Carbon::now()->year;

        $presupuestosBase = [
            'CC-01' => 75000.00, // Operación Mina
            'CC-02' => 55000.00, // Planta
            'CC-03' => 40000.00, // Logística y Fletes
            'CC-04' => 45000.00, // Mantenimiento
            'CC-05' => 28000.00, // Administración
        ];

        foreach ($centrosMap as $codigo => $ccId) {
            $montoBase = $presupuestosBase[$codigo] ?? 30000.00;

            for ($mes = 1; $mes <= 12; $mes++) {
                // Ligera variación mensual para realismo
                $factor = 1.0 + (($mes % 3) * 0.04);
                $monto = round($montoBase * $factor, 2);

                Presupuesto::updateOrCreate(
                    [
                        'centro_costo_id' => $ccId,
                        'anio' => $anio,
                        'mes' => $mes,
                    ],
                    [
                        'monto_presupuestado' => $monto,
                        'notas' => "Presupuesto operativo mensual asignado {$anio}-{$mes}",
                        'activo' => true,
                    ]
                );
            }
        }
    }

    /**
     * @param  array<string, int>  $centrosMap
     * @param  array<string, int>  $categoriasMap
     */
    private function seedMovimientosFinancieros(array $centrosMap, array $categoriasMap): void
    {
        $hoy = Carbon::now();
        $proveedores = Proveedor::query()->orderBy('id')->get()->keyBy('ruc');
        $envios = Envio::query()->orderBy('id')->get();
        $trabajador = Trabajador::query()->first();

        // Proveedores de Fase 3:
        // 20123456789 -> Ferretería Minera del Norte S.A.C.
        // 20234567890 -> Distribuidora de EPP Industrial E.I.R.L.
        // 20345678901 -> Lubricantes y Combustibles Andinos S.A.
        // 20456789012 -> Repuestos Mineros del Perú S.A.C.
        // 20567890134 -> Transportes y Carga La Libertad S.R.L.

        $provCombustibles = $proveedores->get('20345678901')?->id;
        $provRepuestos = $proveedores->get('20456789012')?->id;
        $provTransportes = $proveedores->get('20567890134')?->id;
        $provEpp = $proveedores->get('20234567890')?->id;
        $provFerreteria = $proveedores->get('20123456789')?->id;

        $envio1 = $envios->first()?->id;
        $envio2 = $envios->skip(1)->first()?->id;

        $movimientos = [
            // 1. Ingresos
            [
                'tipo' => TipoMovimientoFinanciero::INGRESO->value,
                'fecha' => $hoy->copy()->subDays(25)->toDateString(),
                'monto' => 145000.00,
                'categoria_id' => $categoriasMap['Venta de Concentrado y Mineral'] ?? 1,
                'centro_costo_id' => $centrosMap['CC-01'],
                'proveedor_id' => null,
                'concepto' => 'Liquidación de venta de lote concentrado Au-Ag a refinería',
                'comprobante_tipo' => TipoComprobante::FACTURA->value,
                'comprobante_numero' => 'F001-00892',
                'estado_pago' => EstadoPago::PAGADO->value,
                'fecha_vencimiento' => null,
                'fecha_pago' => $hoy->copy()->subDays(25)->toDateString(),
            ],
            [
                'tipo' => TipoMovimientoFinanciero::INGRESO->value,
                'fecha' => $hoy->copy()->subDays(10)->toDateString(),
                'monto' => 182000.00,
                'categoria_id' => $categoriasMap['Venta de Concentrado y Mineral'] ?? 1,
                'centro_costo_id' => $centrosMap['CC-02'],
                'proveedor_id' => null,
                'concepto' => 'Liquidación quincenal por mineral procesado en planta',
                'comprobante_tipo' => TipoComprobante::FACTURA->value,
                'comprobante_numero' => 'F001-00898',
                'estado_pago' => EstadoPago::PAGADO->value,
                'fecha_vencimiento' => null,
                'fecha_pago' => $hoy->copy()->subDays(10)->toDateString(),
            ],
            [
                'tipo' => TipoMovimientoFinanciero::INGRESO->value,
                'fecha' => $hoy->copy()->subDays(4)->toDateString(),
                'monto' => 8500.00,
                'categoria_id' => $categoriasMap['Servicios y Alquiler de Maquinaria'] ?? 1,
                'centro_costo_id' => $centrosMap['CC-04'],
                'proveedor_id' => null,
                'concepto' => 'Servicio de apoyo mecánico y soldadura a contratista local',
                'comprobante_tipo' => TipoComprobante::FACTURA->value,
                'comprobante_numero' => 'F001-00902',
                'estado_pago' => EstadoPago::PAGADO->value,
                'fecha_vencimiento' => null,
                'fecha_pago' => $hoy->copy()->subDays(4)->toDateString(),
            ],

            // 2. Egresos Operación Mina (CC-01)
            [
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'fecha' => $hoy->copy()->subDays(22)->toDateString(),
                'monto' => 28500.00,
                'categoria_id' => $categoriasMap['Combustibles y Lubricantes'] ?? 1,
                'centro_costo_id' => $centrosMap['CC-01'],
                'proveedor_id' => $provCombustibles,
                'concepto' => 'Abastecimiento de 3,500 galones de diésel D-2 para interior mina',
                'comprobante_tipo' => TipoComprobante::FACTURA->value,
                'comprobante_numero' => 'F003-014520',
                'estado_pago' => EstadoPago::PAGADO->value,
                'fecha_vencimiento' => $hoy->copy()->subDays(10)->toDateString(),
                'fecha_pago' => $hoy->copy()->subDays(12)->toDateString(),
            ],
            [
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'fecha' => $hoy->copy()->subDays(18)->toDateString(),
                'monto' => 14200.00,
                'categoria_id' => $categoriasMap['EPP y Seguridad Minera'] ?? 1,
                'centro_costo_id' => $centrosMap['CC-01'],
                'proveedor_id' => $provEpp,
                'concepto' => 'Lote de botas mineras con punta de acero y mamelucos térmicos',
                'comprobante_tipo' => TipoComprobante::FACTURA->value,
                'comprobante_numero' => 'F002-005612',
                'estado_pago' => EstadoPago::PAGADO->value,
                'fecha_vencimiento' => $hoy->copy()->subDays(5)->toDateString(),
                'fecha_pago' => $hoy->copy()->subDays(6)->toDateString(),
            ],
            [
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'fecha' => $hoy->copy()->subDays(15)->toDateString(),
                'monto' => 9800.00,
                'categoria_id' => $categoriasMap['Herramientas y Materiales Menores'] ?? 1,
                'centro_costo_id' => $centrosMap['CC-01'],
                'proveedor_id' => $provFerreteria,
                'concepto' => 'Adquisición de barretillas, palas y combos para cuadrilla de mina',
                'comprobante_tipo' => TipoComprobante::FACTURA->value,
                'comprobante_numero' => 'F001-003410',
                'estado_pago' => EstadoPago::PAGADO->value,
                'fecha_vencimiento' => $hoy->copy()->subDays(1)->toDateString(),
                'fecha_pago' => $hoy->copy()->subDays(2)->toDateString(),
            ],

            // 3. Egresos Planta (CC-02)
            [
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'fecha' => $hoy->copy()->subDays(20)->toDateString(),
                'monto' => 22000.00,
                'categoria_id' => $categoriasMap['Combustibles y Lubricantes'] ?? 1,
                'centro_costo_id' => $centrosMap['CC-02'],
                'proveedor_id' => $provCombustibles,
                'concepto' => 'Combustible para generadores de planta concentradora',
                'comprobante_tipo' => TipoComprobante::FACTURA->value,
                'comprobante_numero' => 'F003-014588',
                'estado_pago' => EstadoPago::PAGADO->value,
                'fecha_vencimiento' => $hoy->copy()->subDays(5)->toDateString(),
                'fecha_pago' => $hoy->copy()->subDays(8)->toDateString(),
            ],
            [
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'fecha' => $hoy->copy()->subDays(12)->toDateString(),
                'monto' => 16500.00,
                'categoria_id' => $categoriasMap['Repuestos y Servicios de Mantenimiento'] ?? 1,
                'centro_costo_id' => $centrosMap['CC-02'],
                'proveedor_id' => $provRepuestos,
                'concepto' => 'Mantenimiento preventivo de celdas de flotación y bombas de relave',
                'comprobante_tipo' => TipoComprobante::FACTURA->value,
                'comprobante_numero' => 'F004-008210',
                'estado_pago' => EstadoPago::PAGADO->value,
                'fecha_vencimiento' => $hoy->copy()->subDays(2)->toDateString(),
                'fecha_pago' => $hoy->copy()->subDays(3)->toDateString(),
            ],

            // 4. Egresos Logística y Transporte (CC-03) con vínculo a Envíos
            [
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'fecha' => $hoy->copy()->subDays(16)->toDateString(),
                'monto' => 12500.00,
                'categoria_id' => $categoriasMap['Transporte y Fletes Trujillo-Pataz'] ?? 1,
                'centro_costo_id' => $centrosMap['CC-03'],
                'proveedor_id' => $provTransportes,
                'concepto' => 'Flete de camión plataforma Trujillo - Pataz con materiales',
                'comprobante_tipo' => TipoComprobante::FACTURA->value,
                'comprobante_numero' => 'F005-001290',
                'estado_pago' => EstadoPago::PAGADO->value,
                'fecha_vencimiento' => $hoy->copy()->subDays(4)->toDateString(),
                'fecha_pago' => $hoy->copy()->subDays(4)->toDateString(),
                'envio_id' => $envio1,
            ],
            [
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'fecha' => $hoy->copy()->subDays(8)->toDateString(),
                'monto' => 11800.00,
                'categoria_id' => $categoriasMap['Transporte y Fletes Trujillo-Pataz'] ?? 1,
                'centro_costo_id' => $centrosMap['CC-03'],
                'proveedor_id' => $provTransportes,
                'concepto' => 'Servicio de flete urgente con repuestos críticos a mina',
                'comprobante_tipo' => TipoComprobante::FACTURA->value,
                'comprobante_numero' => 'F005-001315',
                'estado_pago' => EstadoPago::PAGADO->value,
                'fecha_vencimiento' => $hoy->copy()->subDays(1)->toDateString(),
                'fecha_pago' => $hoy->copy()->subDays(1)->toDateString(),
                'envio_id' => $envio2,
            ],

            // 5. Egresos Mantenimiento (CC-04)
            [
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'fecha' => $hoy->copy()->subDays(14)->toDateString(),
                'monto' => 18400.00,
                'categoria_id' => $categoriasMap['Repuestos y Servicios de Mantenimiento'] ?? 1,
                'centro_costo_id' => $centrosMap['CC-04'],
                'proveedor_id' => $provRepuestos,
                'concepto' => 'Kit de sellos hidráulicos, filtros y mangueras para cargador frontal',
                'comprobante_tipo' => TipoComprobante::FACTURA->value,
                'comprobante_numero' => 'F004-008240',
                'estado_pago' => EstadoPago::PAGADO->value,
                'fecha_vencimiento' => $hoy->copy()->subDays(2)->toDateString(),
                'fecha_pago' => $hoy->copy()->subDays(2)->toDateString(),
            ],
            [
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'fecha' => $hoy->copy()->subDays(6)->toDateString(),
                'monto' => 7600.00,
                'categoria_id' => $categoriasMap['Repuestos y Servicios de Mantenimiento'] ?? 1,
                'centro_costo_id' => $centrosMap['CC-04'],
                'proveedor_id' => $provRepuestos,
                'concepto' => 'Reparación de motor de arranque y alternador de grupo electrógeno',
                'comprobante_tipo' => TipoComprobante::FACTURA->value,
                'comprobante_numero' => 'F004-008275',
                'estado_pago' => EstadoPago::PAGADO->value,
                'fecha_vencimiento' => $hoy->copy()->addDays(10)->toDateString(),
                'fecha_pago' => $hoy->copy()->subDays(1)->toDateString(),
            ],

            // 6. Egresos Administración y Campamento (CC-05) con trabajador
            [
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'fecha' => $hoy->copy()->subDays(11)->toDateString(),
                'monto' => 13400.00,
                'categoria_id' => $categoriasMap['Alimentación y Campamento'] ?? 1,
                'centro_costo_id' => $centrosMap['CC-05'],
                'proveedor_id' => null,
                'concepto' => 'Servicio de alimentación y catering quincenal para 65 colaboradores',
                'comprobante_tipo' => TipoComprobante::FACTURA->value,
                'comprobante_numero' => 'F001-002105',
                'estado_pago' => EstadoPago::PAGADO->value,
                'fecha_vencimiento' => $hoy->copy()->subDays(1)->toDateString(),
                'fecha_pago' => $hoy->copy()->subDays(1)->toDateString(),
            ],
            [
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'fecha' => $hoy->copy()->subDays(7)->toDateString(),
                'monto' => 3800.00,
                'categoria_id' => $categoriasMap['Planilla y Servicios Profesionales'] ?? 1,
                'centro_costo_id' => $centrosMap['CC-05'],
                'proveedor_id' => null,
                'concepto' => 'Honorarios médicos y seguimiento de exámenes ocupacionales periódicos',
                'comprobante_tipo' => TipoComprobante::RECIBO->value,
                'comprobante_numero' => 'E001-00045',
                'estado_pago' => EstadoPago::PAGADO->value,
                'fecha_vencimiento' => null,
                'fecha_pago' => $hoy->copy()->subDays(7)->toDateString(),
                'trabajador_id' => $trabajador?->id,
            ],

            // 7. Cuentas por Pagar (Egresos PENDIENTES - Alertas para el sistema)
            // 7.1 Vencida hace 5 días (Alerta Roja)
            [
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'fecha' => $hoy->copy()->subDays(25)->toDateString(),
                'monto' => 24500.00,
                'categoria_id' => $categoriasMap['Combustibles y Lubricantes'] ?? 1,
                'centro_costo_id' => $centrosMap['CC-01'],
                'proveedor_id' => $provCombustibles,
                'concepto' => 'Factura pendiente de pago: Segundo lote de combustible mina',
                'comprobante_tipo' => TipoComprobante::FACTURA->value,
                'comprobante_numero' => 'F003-014605',
                'estado_pago' => EstadoPago::PENDIENTE->value,
                'fecha_vencimiento' => $hoy->copy()->subDays(5)->toDateString(),
                'fecha_pago' => null,
                'observaciones' => 'Vencida. Programar pago con tesorería urgentemente.',
            ],
            // 7.2 Vencida hace 2 días (Alerta Roja)
            [
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'fecha' => $hoy->copy()->subDays(18)->toDateString(),
                'monto' => 8900.00,
                'categoria_id' => $categoriasMap['Repuestos y Servicios de Mantenimiento'] ?? 1,
                'centro_costo_id' => $centrosMap['CC-04'],
                'proveedor_id' => $provRepuestos,
                'concepto' => 'Repuestos mecánicos para compresora Atlas Copco GA-75',
                'comprobante_tipo' => TipoComprobante::FACTURA->value,
                'comprobante_numero' => 'F004-008310',
                'estado_pago' => EstadoPago::PENDIENTE->value,
                'fecha_vencimiento' => $hoy->copy()->subDays(2)->toDateString(),
                'fecha_pago' => null,
                'observaciones' => 'Factura con retraso de liquidación.',
            ],
            // 7.3 Por vencer en 3 días (Advertencia Ámbar)
            [
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'fecha' => $hoy->copy()->subDays(12)->toDateString(),
                'monto' => 15600.00,
                'categoria_id' => $categoriasMap['Transporte y Fletes Trujillo-Pataz'] ?? 1,
                'centro_costo_id' => $centrosMap['CC-03'],
                'proveedor_id' => $provTransportes,
                'concepto' => 'Fletes acumulados semana 43 Trujillo - Pataz',
                'comprobante_tipo' => TipoComprobante::FACTURA->value,
                'comprobante_numero' => 'F005-001340',
                'estado_pago' => EstadoPago::PENDIENTE->value,
                'fecha_vencimiento' => $hoy->copy()->addDays(3)->toDateString(),
                'fecha_pago' => null,
            ],
            // 7.4 Por vencer en 6 días (Advertencia Ámbar)
            [
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'fecha' => $hoy->copy()->subDays(8)->toDateString(),
                'monto' => 11200.00,
                'categoria_id' => $categoriasMap['EPP y Seguridad Minera'] ?? 1,
                'centro_costo_id' => $centrosMap['CC-01'],
                'proveedor_id' => $provEpp,
                'concepto' => 'Dotación de respiradores 3M y filtros contra polvos y gases',
                'comprobante_tipo' => TipoComprobante::FACTURA->value,
                'comprobante_numero' => 'F002-005690',
                'estado_pago' => EstadoPago::PENDIENTE->value,
                'fecha_vencimiento' => $hoy->copy()->addDays(6)->toDateString(),
                'fecha_pago' => null,
            ],
            // 7.5 Por vencer en 15 días (Normal Verde)
            [
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'fecha' => $hoy->copy()->subDays(3)->toDateString(),
                'monto' => 6400.00,
                'categoria_id' => $categoriasMap['Herramientas y Materiales Menores'] ?? 1,
                'centro_costo_id' => $centrosMap['CC-04'],
                'proveedor_id' => $provFerreteria,
                'concepto' => 'Suministro de pernos de anclaje y brocas para taller',
                'comprobante_tipo' => TipoComprobante::FACTURA->value,
                'comprobante_numero' => 'F001-003480',
                'estado_pago' => EstadoPago::PENDIENTE->value,
                'fecha_vencimiento' => $hoy->copy()->addDays(15)->toDateString(),
                'fecha_pago' => null,
            ],
            // 7.6 Recibo por honorarios pendiente
            [
                'tipo' => TipoMovimientoFinanciero::EGRESO->value,
                'fecha' => $hoy->copy()->subDays(2)->toDateString(),
                'monto' => 4500.00,
                'categoria_id' => $categoriasMap['Planilla y Servicios Profesionales'] ?? 1,
                'centro_costo_id' => $centrosMap['CC-05'],
                'proveedor_id' => null,
                'concepto' => 'Asesoría ambiental y monitoreo de efluentes mina',
                'comprobante_tipo' => TipoComprobante::RECIBO->value,
                'comprobante_numero' => 'E001-00052',
                'estado_pago' => EstadoPago::PENDIENTE->value,
                'fecha_vencimiento' => $hoy->copy()->addDays(12)->toDateString(),
                'fecha_pago' => null,
            ],
        ];

        foreach ($movimientos as $mov) {
            MovimientoFinanciero::updateOrCreate(
                [
                    'comprobante_tipo' => $mov['comprobante_tipo'],
                    'comprobante_numero' => $mov['comprobante_numero'],
                ],
                [
                    'tipo' => $mov['tipo'],
                    'fecha' => $mov['fecha'],
                    'monto' => $mov['monto'],
                    'moneda' => 'PEN',
                    'categoria_id' => $mov['categoria_id'],
                    'centro_costo_id' => $mov['centro_costo_id'],
                    'proveedor_id' => $mov['proveedor_id'] ?? null,
                    'concepto' => $mov['concepto'],
                    'estado_pago' => $mov['estado_pago'],
                    'fecha_vencimiento' => $mov['fecha_vencimiento'] ?? null,
                    'fecha_pago' => $mov['fecha_pago'] ?? null,
                    'envio_id' => $mov['envio_id'] ?? null,
                    'orden_compra_id' => $mov['orden_compra_id'] ?? null,
                    'trabajador_id' => $mov['trabajador_id'] ?? null,
                    'observaciones' => $mov['observaciones'] ?? null,
                    'activo' => true,
                ]
            );
        }
    }
}
