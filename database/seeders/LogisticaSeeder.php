<?php

namespace Database\Seeders;

use App\Enums\EstadoEnvio;
use App\Enums\EstadoRequerimiento;
use App\Enums\PrioridadRequerimiento;
use App\Enums\RolUsuario;
use App\Enums\TipoMovimientoInventario;
use App\Models\Core\Area;
use App\Models\Core\Sede;
use App\Models\Core\UnidadMedida;
use App\Models\Logistica\Almacen;
use App\Models\Logistica\CategoriaMaterial;
use App\Models\Logistica\CorrelativoDocumento;
use App\Models\Logistica\Envio;
use App\Models\Logistica\EnvioDetalle;
use App\Models\Logistica\Material;
use App\Models\Logistica\Proveedor;
use App\Models\Logistica\Requerimiento;
use App\Models\Logistica\RequerimientoDetalle;
use App\Models\Logistica\StockAlmacen;
use App\Models\User;
use App\Services\Logistica\InventarioService;
use Illuminate\Database\Seeder;

class LogisticaSeeder extends Seeder
{
    public function run(): void
    {
        $adminId = User::query()->where('rol', RolUsuario::ADMIN)->value('id')
            ?? User::query()->orderBy('id')->value('id');

        $categoriasMap = $this->seedCategorias();
        $almacenesMap = $this->seedAlmacenes();

        if (! (bool) config('app.debug')) {
            return;
        }

        $this->seedProveedores();
        $materialesMap = $this->seedMateriales($categoriasMap);
        $this->seedStockInicial($almacenesMap, $materialesMap, $adminId);
        $requerimientosMap = $this->seedRequerimientos($materialesMap, $adminId);
        $this->seedEnvios($almacenesMap, $materialesMap, $requerimientosMap, $adminId);
    }

    /**
     * @return array<string, int>
     */
    private function seedCategorias(): array
    {
        $definiciones = [
            ['nombre' => 'EPP', 'descripcion' => 'Equipos de protección personal para mina y superficie'],
            ['nombre' => 'Explosivos y Accesorios', 'descripcion' => 'Material de voladura y accesorios de encendido'],
            ['nombre' => 'Herramientas', 'descripcion' => 'Herramientas manuales y eléctricas'],
            ['nombre' => 'Combustibles y Lubricantes', 'descripcion' => 'Diésel, aceites hidráulicos y grasas'],
            ['nombre' => 'Repuestos', 'descripcion' => 'Repuestos mecánicos de perforadoras, winches y vehículos'],
            ['nombre' => 'Material Eléctrico', 'descripcion' => 'Cables, reflectores, tableros e interruptores'],
            ['nombre' => 'Suministros Oficina/Campamento', 'descripcion' => 'Útiles, agua, víveres secos e higiene'],
        ];

        $existentes = CategoriaMaterial::withTrashed()->get()->keyBy('nombre');
        $resultado = [];

        foreach ($definiciones as $def) {
            /** @var CategoriaMaterial|null $cat */
            $cat = $existentes->get($def['nombre']);
            if (! $cat) {
                $cat = CategoriaMaterial::query()->create(array_merge($def, ['activo' => true]));
            }
            $resultado[$def['nombre']] = (int) $cat->id;
        }

        return $resultado;
    }

    /**
     * @return array<string, Almacen>
     */
    private function seedAlmacenes(): array
    {
        $sedeTrujillo = Sede::query()->where('nombre', 'like', '%Trujillo%')->value('id')
            ?? Sede::query()->orderBy('id')->value('id');
        $sedePataz = Sede::query()->where('nombre', 'like', '%Pataz%')->value('id')
            ?? $sedeTrujillo;

        $definiciones = [
            [
                'codigo' => 'ALM-TRUJILLO',
                'nombre' => 'Almacén Central Trujillo',
                'sede_id' => $sedeTrujillo,
                'es_principal' => true,
            ],
            [
                'codigo' => 'ALM-PATAZ',
                'nombre' => 'Almacén Mina Pataz',
                'sede_id' => $sedePataz,
                'es_principal' => false,
            ],
        ];

        $existentes = Almacen::withTrashed()->get()->keyBy('codigo');
        $mapa = [];

        foreach ($definiciones as $def) {
            /** @var Almacen|null $alm */
            $alm = $existentes->get($def['codigo']);
            if (! $alm) {
                $alm = Almacen::query()->create(array_merge($def, ['activo' => true]));
            }
            $mapa[$def['codigo']] = $alm;
        }

        return $mapa;
    }

    private function seedProveedores(): void
    {
        $definiciones = [
            [
                'ruc' => '20481234561',
                'razon_social' => 'Proveedora Minera del Norte S.A.C.',
                'contacto' => 'Carlos Mendoza',
                'telefono' => '044-224110',
                'email' => 'ventas@pmnorte.pe',
                'direccion' => 'Av. América Sur 1420, Trujillo',
                'condicion_pago' => 'CREDITO 30 DIAS',
            ],
            [
                'ruc' => '20539876123',
                'razon_social' => 'Seguridad Industrial La Libertad S.R.L.',
                'contacto' => 'Rosa Paredes',
                'telefono' => '044-298451',
                'email' => 'cotizaciones@seguridadlibertad.pe',
                'direccion' => 'Av. España 845, Trujillo',
                'condicion_pago' => 'CREDITO 15 DIAS',
            ],
            [
                'ruc' => '20601478529',
                'razon_social' => 'Lubricantes y Combustibles Sierra S.A.C.',
                'contacto' => 'Jorge Alcántara',
                'telefono' => '949874123',
                'email' => 'despachos@lubrisierra.pe',
                'direccion' => 'Panamericana Norte Km 558, Moche',
                'condicion_pago' => 'CONTADO',
            ],
            [
                'ruc' => '20398745126',
                'razon_social' => 'ElectroMin Pataz E.I.R.L.',
                'contacto' => 'Luis Vargas',
                'telefono' => '944551188',
                'email' => 'contacto@electromin.pe',
                'direccion' => 'Jr. Bolívar 312, Trujillo',
                'condicion_pago' => 'CREDITO 30 DIAS',
            ],
            [
                'ruc' => '20604569871',
                'razon_social' => 'Ferretería y Maquinaria Andina S.A.C.',
                'contacto' => 'Elena Quispe',
                'telefono' => '044-415263',
                'email' => 'pedidos@ferreandina.pe',
                'direccion' => 'Av. Industrial 502, Trujillo',
                'condicion_pago' => 'CREDITO 45 DIAS',
            ],
        ];

        $existentes = Proveedor::withTrashed()->pluck('ruc')->flip();

        foreach ($definiciones as $def) {
            if (! $existentes->has($def['ruc'])) {
                Proveedor::query()->create(array_merge($def, ['activo' => true]));
            }
        }
    }

    /**
     * @param  array<string, int>  $categoriasMap
     * @return array<string, Material>
     */
    private function seedMateriales(array $categoriasMap): array
    {
        $unidades = UnidadMedida::query()->get();
        $unidadPorAbrev = $unidades->keyBy(static fn (UnidadMedida $u): string => strtoupper($u->abreviatura));
        $unidadFallbackId = (int) ($unidades->first()?->id ?? 1);

        $resolverUnidad = static function (string $abrev) use ($unidadPorAbrev, $unidadFallbackId): int {
            return (int) ($unidadPorAbrev->get(strtoupper($abrev))?->id ?? $unidadFallbackId);
        };

        $items = [
            // EPP (7)
            ['MAT-001', 'Casco de seguridad minero con portalámpara', 'EPP', 'UND', 20, 48.00],
            ['MAT-002', 'Lámpara minera LED recargable IP68', 'EPP', 'UND', 15, 210.00],
            ['MAT-003', 'Botas de jebe punta de acero caña alta', 'EPP', 'PAR', 20, 95.00],
            ['MAT-004', 'Respirador media cara doble vía 3M', 'EPP', 'UND', 15, 115.00],
            ['MAT-005', 'Filtros para polvo y gases P100', 'EPP', 'PAR', 30, 42.00],
            ['MAT-006', 'Guantes de cuero badana reforzados', 'EPP', 'PAR', 40, 18.50],
            ['MAT-007', 'Arnés de cuerpo entero con línea de vida', 'EPP', 'UND', 8, 340.00],
            // Explosivos y Accesorios (5)
            ['MAT-008', 'Dinamita semigelatina 65% x 7/8"', 'Explosivos y Accesorios', 'CAJA', 10, 460.00],
            ['MAT-009', 'Carmex armado 2.40 m', 'Explosivos y Accesorios', 'UND', 200, 3.80],
            ['MAT-010', 'Mecha rápida de ignición', 'Explosivos y Accesorios', 'MT', 100, 2.50],
            ['MAT-011', 'Fulminante común N° 8', 'Explosivos y Accesorios', 'CAJA', 6, 180.00],
            ['MAT-012', 'ANFO granulado saco x 25 kg', 'Explosivos y Accesorios', 'KG', 250, 4.90],
            // Herramientas (7)
            ['MAT-013', 'Barreno integral 4 pies x 7/8"', 'Herramientas', 'UND', 12, 390.00],
            ['MAT-014', 'Barreno integral 6 pies x 7/8"', 'Herramientas', 'UND', 10, 475.00],
            ['MAT-015', 'Barretilla de aluminio desatadora 6 pies', 'Herramientas', 'UND', 12, 165.00],
            ['MAT-016', 'Pala minera tipo cuchara mango D', 'Herramientas', 'UND', 15, 58.00],
            ['MAT-017', 'Pico minero punta y pala con mango', 'Herramientas', 'UND', 15, 62.00],
            ['MAT-018', 'Comba octogonal 12 lb', 'Herramientas', 'UND', 8, 125.00],
            ['MAT-019', 'Juego de llaves mixtas milimétricas 8-32mm', 'Herramientas', 'JGO', 4, 240.00],
            // Combustibles y Lubricantes (5)
            ['MAT-020', 'Petróleo Diésel B5 S50', 'Combustibles y Lubricantes', 'GL', 150, 16.80],
            ['MAT-021', 'Aceite neumático para perforadora ISO 100', 'Combustibles y Lubricantes', 'GL', 20, 88.00],
            ['MAT-022', 'Aceite motor 15W-40 servicio pesado', 'Combustibles y Lubricantes', 'GL', 25, 92.00],
            ['MAT-023', 'Aceite hidráulico AW-68', 'Combustibles y Lubricantes', 'GL', 20, 76.00],
            ['MAT-024', 'Grasa litio EP2 extrema presión', 'Combustibles y Lubricantes', 'KG', 25, 34.00],
            // Repuestos (6)
            ['MAT-025', 'Manguera aire comprimido 1" alta presión', 'Repuestos', 'MT', 50, 29.00],
            ['MAT-026', 'Acople rápido tipo Garra 1"', 'Repuestos', 'UND', 20, 32.00],
            ['MAT-027', 'Cable de acero antigiratorio 5/8" para winche', 'Repuestos', 'MT', 60, 24.50],
            ['MAT-028', 'Llanta todo terreno 265/70R17 camioneta', 'Repuestos', 'UND', 4, 780.00],
            ['MAT-029', 'Kit empaquetadura perforadora jackleg', 'Repuestos', 'JGO', 5, 310.00],
            ['MAT-030', 'Rodamiento esférico 6308-2RS', 'Repuestos', 'UND', 10, 68.00],
            // Material Eléctrico (5)
            ['MAT-031', 'Cable vulcanizado NMT 3x10 AWG', 'Material Eléctrico', 'MT', 80, 18.90],
            ['MAT-032', 'Reflector LED industrial 150W IP66', 'Material Eléctrico', 'UND', 8, 295.00],
            ['MAT-033', 'Interruptor termomagnético trifásico 63A', 'Material Eléctrico', 'UND', 6, 145.00],
            ['MAT-034', 'Cinta aislante eléctrica PVC', 'Material Eléctrico', 'UND', 40, 7.50],
            ['MAT-035', 'Enchufe industrial trifásico Mennekes 32A', 'Material Eléctrico', 'UND', 10, 64.00],
            // Suministros Oficina/Campamento (5)
            ['MAT-036', 'Bidón de agua de mesa x 20 L', 'Suministros Oficina/Campamento', 'UND', 30, 18.00],
            ['MAT-037', 'Papel bond A4 75g paquete x 500 hojas', 'Suministros Oficina/Campamento', 'UND', 20, 16.50],
            ['MAT-038', 'Tóner impresora láser multifunción', 'Suministros Oficina/Campamento', 'UND', 3, 230.00],
            ['MAT-039', 'Detergente industrial saco x 15 kg', 'Suministros Oficina/Campamento', 'KG', 30, 11.50],
            ['MAT-040', 'Frazada polar térmica campamento', 'Suministros Oficina/Campamento', 'UND', 15, 55.00],
        ];

        $existentes = Material::withTrashed()->get()->keyBy('codigo');
        $mapa = [];

        foreach ($items as [$codigo, $nombre, $catNombre, $abrev, $stockMin, $costo]) {
            /** @var Material|null $mat */
            $mat = $existentes->get($codigo);
            if (! $mat) {
                $mat = Material::query()->create([
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'categoria_id' => $categoriasMap[$catNombre],
                    'unidad_medida_id' => $resolverUnidad($abrev),
                    'stock_minimo' => $stockMin,
                    'costo_referencial' => $costo,
                    'activo' => true,
                ]);
            }
            $mapa[$codigo] = $mat;
        }

        return $mapa;
    }

    /**
     * @param  array<string, Almacen>  $almacenes
     * @param  array<string, Material>  $materiales
     */
    private function seedStockInicial(array $almacenes, array $materiales, int $adminId): void
    {
        /** @var User $admin */
        $admin = User::query()->findOrFail($adminId);
        /** @var InventarioService $inventario */
        $inventario = app(InventarioService::class);

        $almTrujillo = $almacenes['ALM-TRUJILLO'];
        $almPataz = $almacenes['ALM-PATAZ'];

        $stocksExistentes = StockAlmacen::query()
            ->get()
            ->mapWithKeys(static fn (StockAlmacen $s): array => ["{$s->almacen_id}:{$s->material_id}" => true]);

        $codigosCriticos = ['MAT-005', 'MAT-021', 'MAT-028'];

        foreach ($materiales as $codigo => $material) {
            $base = max(10.0, (float) $material->stock_minimo * 3.0);
            $qtyTrujillo = in_array($codigo, $codigosCriticos, true)
                ? max(1.0, (float) $material->stock_minimo * 0.5)
                : $base;
            $qtyPataz = in_array($codigo, $codigosCriticos, true)
                ? max(1.0, (float) $material->stock_minimo * 0.4)
                : max(5.0, round($base * 0.45, 0));

            if (! $stocksExistentes->has("{$almTrujillo->id}:{$material->id}")) {
                $inventario->aplicarMovimiento(
                    almacenId: $almTrujillo->id,
                    materialId: $material->id,
                    tipo: TipoMovimientoInventario::ENTRADA_COMPRA,
                    cantidad: $qtyTrujillo,
                    usuario: $admin,
                    esSalida: false,
                    observacion: 'Inventario inicial sembrado en Trujillo.',
                );
            }

            if (! $stocksExistentes->has("{$almPataz->id}:{$material->id}")) {
                $inventario->aplicarMovimiento(
                    almacenId: $almPataz->id,
                    materialId: $material->id,
                    tipo: TipoMovimientoInventario::ENTRADA_COMPRA,
                    cantidad: $qtyPataz,
                    usuario: $admin,
                    esSalida: false,
                    observacion: 'Inventario inicial sembrado en Pataz.',
                );
            }
        }
    }

    /**
     * @param  array<string, Material>  $materiales
     * @return array<string, Requerimiento>
     */
    private function seedRequerimientos(array $materiales, int $adminId): array
    {
        $anio = (int) now()->format('Y');
        $solicitantePatazId = User::query()->where('rol', RolUsuario::RECEPCION_PATAZ)->value('id') ?? $adminId;
        $jefeLogId = User::query()->where('rol', RolUsuario::LOGISTICA_JEFE)->value('id') ?? $adminId;
        $asistenteLogId = User::query()->where('rol', RolUsuario::LOGISTICA_ASISTENTE)->value('id') ?? $adminId;

        $areaId = Area::query()->orderBy('id')->value('id');
        $sedePatazId = Sede::query()->where('nombre', 'like', '%Pataz%')->value('id')
            ?? Sede::query()->orderBy('id')->value('id');

        $definiciones = [
            [
                'codigo' => "REQ-{$anio}-0001",
                'estado' => EstadoRequerimiento::ATENDIDO,
                'prioridad' => PrioridadRequerimiento::ALTA,
                'solicitante_id' => $solicitantePatazId,
                'aprobado_por_id' => $jefeLogId,
                'observaciones' => 'Reposición mensual EPP guardia A',
                'items' => [
                    ['MAT-001', 15, 15],
                    ['MAT-003', 10, 10],
                ],
            ],
            [
                'codigo' => "REQ-{$anio}-0002",
                'estado' => EstadoRequerimiento::ATENDIDO_PARCIAL,
                'prioridad' => PrioridadRequerimiento::URGENTE,
                'solicitante_id' => $solicitantePatazId,
                'aprobado_por_id' => $jefeLogId,
                'observaciones' => 'Aceites y barrenos frente Nivel 2140',
                'items' => [
                    ['MAT-013', 10, 8],
                    ['MAT-021', 20, 20],
                ],
            ],
            [
                'codigo' => "REQ-{$anio}-0003",
                'estado' => EstadoRequerimiento::APROBADO,
                'prioridad' => PrioridadRequerimiento::MEDIA,
                'solicitante_id' => $asistenteLogId,
                'aprobado_por_id' => $jefeLogId,
                'observaciones' => 'Material eléctrico galería principal',
                'items' => [
                    ['MAT-031', 40, 0],
                    ['MAT-032', 4, 0],
                ],
            ],
            [
                'codigo' => "REQ-{$anio}-0004",
                'estado' => EstadoRequerimiento::ENVIADO,
                'prioridad' => PrioridadRequerimiento::ALTA,
                'solicitante_id' => $solicitantePatazId,
                'aprobado_por_id' => null,
                'observaciones' => 'Filtros y guantes para zona de perforación',
                'items' => [
                    ['MAT-005', 25, 0],
                    ['MAT-006', 30, 0],
                ],
            ],
            [
                'codigo' => "REQ-{$anio}-0005",
                'estado' => EstadoRequerimiento::BORRADOR,
                'prioridad' => PrioridadRequerimiento::BAJA,
                'solicitante_id' => $solicitantePatazId,
                'aprobado_por_id' => null,
                'observaciones' => 'Utiles campamento quincena',
                'items' => [
                    ['MAT-036', 20, 0],
                    ['MAT-037', 10, 0],
                ],
            ],
        ];

        $existentes = Requerimiento::withTrashed()->get()->keyBy('codigo');
        $mapa = [];

        foreach ($definiciones as $i => $def) {
            /** @var Requerimiento|null $req */
            $req = $existentes->get($def['codigo']);
            if (! $req) {
                $req = Requerimiento::query()->create([
                    'codigo' => $def['codigo'],
                    'fecha_solicitud' => now()->subDays(10 - $i * 2)->toDateString(),
                    'area_id' => $areaId,
                    'sede_id' => $sedePatazId,
                    'solicitante_id' => $def['solicitante_id'],
                    'prioridad' => $def['prioridad'],
                    'estado' => $def['estado'],
                    'aprobado_por_id' => $def['aprobado_por_id'],
                    'fecha_aprobacion' => $def['aprobado_por_id'] ? now()->subDays(9 - $i * 2) : null,
                    'observaciones' => $def['observaciones'],
                    'activo' => true,
                ]);

                foreach ($def['items'] as [$matCod, $sol, $ate]) {
                    RequerimientoDetalle::query()->create([
                        'requerimiento_id' => $req->id,
                        'material_id' => $materiales[$matCod]->id,
                        'cantidad_solicitada' => $sol,
                        'cantidad_atendida' => $ate,
                        'activo' => true,
                    ]);
                }
            }
            $mapa[$def['codigo']] = $req->load('detalles');
        }

        $this->sincronizarCorrelativo('REQ', $anio, 5);

        return $mapa;
    }

    /**
     * @param  array<string, Almacen>  $almacenes
     * @param  array<string, Material>  $materiales
     * @param  array<string, Requerimiento>  $requerimientos
     */
    private function seedEnvios(array $almacenes, array $materiales, array $requerimientos, int $adminId): void
    {
        $anio = (int) now()->format('Y');
        $almTrujillo = $almacenes['ALM-TRUJILLO'];
        $almPataz = $almacenes['ALM-PATAZ'];
        $jefeLogId = User::query()->where('rol', RolUsuario::LOGISTICA_JEFE)->value('id') ?? $adminId;
        $recepPatazId = User::query()->where('rol', RolUsuario::RECEPCION_PATAZ)->value('id') ?? $adminId;

        $definiciones = [
            [
                'codigo' => "ENV-{$anio}-0001",
                'requerimiento' => $requerimientos["REQ-{$anio}-0001"],
                'estado' => EstadoEnvio::RECIBIDO,
                'fecha_envio' => now()->subDays(8)->toDateString(),
                'fecha_estimada' => now()->subDays(6)->toDateString(),
                'fecha_recepcion' => now()->subDays(6)->toDateString(),
                'recibido_por_id' => $recepPatazId,
                'guia_remision' => 'T001-004512',
                'observaciones' => 'Despacho completo EPP sin novedades',
                'detalles' => [
                    ['MAT-001', 15, 15, null],
                    ['MAT-003', 10, 10, null],
                ],
            ],
            [
                'codigo' => "ENV-{$anio}-0002",
                'requerimiento' => $requerimientos["REQ-{$anio}-0002"],
                'estado' => EstadoEnvio::RECIBIDO_CON_OBSERVACIONES,
                'fecha_envio' => now()->subDays(5)->toDateString(),
                'fecha_estimada' => now()->subDays(3)->toDateString(),
                'fecha_recepcion' => now()->subDays(3)->toDateString(),
                'recibido_por_id' => $recepPatazId,
                'guia_remision' => 'T001-004529',
                'observaciones' => 'Llegó con diferencia en barrenos por daño en embalaje',
                'detalles' => [
                    ['MAT-013', 10, 8, '2 barrenos con rosca dañada rechazados'],
                    ['MAT-021', 20, 20, null],
                ],
            ],
            [
                'codigo' => "ENV-{$anio}-0003",
                'requerimiento' => $requerimientos["REQ-{$anio}-0003"],
                'estado' => EstadoEnvio::EN_TRANSITO,
                'fecha_envio' => now()->subDays(3)->toDateString(),
                'fecha_estimada' => now()->subDays(1)->toDateString(),
                'fecha_recepcion' => null,
                'recibido_por_id' => null,
                'guia_remision' => 'T001-004560',
                'observaciones' => 'Unidad en ruta Huamachuco - Pataz (con retraso por lluvia)',
                'detalles' => [
                    ['MAT-031', 40, null, null],
                    ['MAT-032', 4, null, null],
                ],
            ],
        ];

        $existentes = Envio::withTrashed()->pluck('codigo')->flip();

        foreach ($definiciones as $def) {
            if ($existentes->has($def['codigo'])) {
                continue;
            }

            /** @var Requerimiento $req */
            $req = $def['requerimiento'];
            $reqDetPorMat = $req->detalles->keyBy('material_id');

            $envio = Envio::query()->create([
                'codigo' => $def['codigo'],
                'requerimiento_id' => $req->id,
                'almacen_origen_id' => $almTrujillo->id,
                'almacen_destino_id' => $almPataz->id,
                'fecha_envio' => $def['fecha_envio'],
                'fecha_estimada_llegada' => $def['fecha_estimada'],
                'fecha_recepcion' => $def['fecha_recepcion'],
                'responsable_envio_id' => $jefeLogId,
                'recibido_por_id' => $def['recibido_por_id'],
                'estado' => $def['estado'],
                'guia_remision' => $def['guia_remision'],
                'observaciones' => $def['observaciones'],
                'activo' => true,
            ]);

            foreach ($def['detalles'] as [$matCod, $enviada, $recibida, $obs]) {
                $mat = $materiales[$matCod];
                EnvioDetalle::query()->create([
                    'envio_id' => $envio->id,
                    'material_id' => $mat->id,
                    'requerimiento_detalle_id' => $reqDetPorMat->get($mat->id)?->id,
                    'cantidad_enviada' => $enviada,
                    'cantidad_recibida' => $recibida,
                    'observacion_recepcion' => $obs,
                    'activo' => true,
                ]);
            }
        }

        $this->sincronizarCorrelativo('ENV', $anio, 3);
    }

    private function sincronizarCorrelativo(string $tipo, int $anio, int $minimo): void
    {
        $registro = CorrelativoDocumento::query()->firstOrCreate(
            ['tipo' => $tipo, 'anio' => $anio],
            ['ultimo_numero' => $minimo],
        );

        if ($registro->ultimo_numero < $minimo) {
            $registro->update(['ultimo_numero' => $minimo]);
        }
    }
}
