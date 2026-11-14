<?php

namespace App\Services\Importacion;

use App\Enums\EstadoEnvio;
use App\Enums\EstadoTrabajador;
use App\Enums\SexoTrabajador;
use App\Enums\TipoMovimientoInventario;
use App\Enums\UbicacionTrabajador;
use App\Exports\TablaReporteExport;
use App\Models\Core\Area;
use App\Models\Core\Cargo;
use App\Models\Core\Sede;
use App\Models\Core\UnidadMedida;
use App\Models\Logistica\Almacen;
use App\Models\Logistica\CategoriaMaterial;
use App\Models\Logistica\Envio;
use App\Models\Logistica\Material;
use App\Models\Logistica\Proveedor;
use App\Models\Rrhh\Trabajador;
use App\Models\User;
use App\Services\Logistica\AlmacenScopeService;
use App\Services\Logistica\EnvioService;
use App\Services\Logistica\InventarioService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Maatwebsite\Excel\Facades\Excel;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ImportadorService
{
    public const TIPO_TRABAJADORES = 'trabajadores';

    public const TIPO_MATERIALES = 'materiales';

    public const TIPO_PROVEEDORES = 'proveedores';

    public const TIPO_STOCK_INICIAL = 'stock_inicial';

    public const TIPO_RECEPCION_ENVIO = 'recepcion_envio';

    public function __construct(
        private readonly InventarioService $inventario,
        private readonly EnvioService $envios,
        private readonly AlmacenScopeService $scope,
    ) {}

    /**
     * @return list<string>
     */
    public static function tiposValidos(): array
    {
        return [
            self::TIPO_TRABAJADORES,
            self::TIPO_MATERIALES,
            self::TIPO_PROVEEDORES,
            self::TIPO_STOCK_INICIAL,
            self::TIPO_RECEPCION_ENVIO,
        ];
    }

    /**
     * @return array<string, array{titulo: string, descripcion: string, permiso: string, archivo: string}>
     */
    public static function metadataTipos(): array
    {
        return [
            self::TIPO_TRABAJADORES => [
                'titulo' => 'Trabajadores',
                'descripcion' => 'Importación de personal (DNI, nombres, cargo, área, sede base, fecha de ingreso).',
                'permiso' => 'importar_rrhh',
                'archivo' => 'plantilla-trabajadores.xlsx',
            ],
            self::TIPO_MATERIALES => [
                'titulo' => 'Materiales e Insumos',
                'descripcion' => 'Catálogo de materiales con categoría, unidad de medida, stock mínimo y costo.',
                'permiso' => 'importar_logistica',
                'archivo' => 'plantilla-materiales.xlsx',
            ],
            self::TIPO_PROVEEDORES => [
                'titulo' => 'Proveedores',
                'descripcion' => 'Directorio de proveedores con RUC de 11 dígitos, razón social y condiciones.',
                'permiso' => 'importar_logistica',
                'archivo' => 'plantilla-proveedores.xlsx',
            ],
            self::TIPO_STOCK_INICIAL => [
                'titulo' => 'Stock Inicial en Almacén',
                'descripcion' => 'Carga de saldos iniciales por almacén y material generando movimiento de apertura.',
                'permiso' => 'importar_logistica',
                'archivo' => 'plantilla-stock-inicial.xlsx',
            ],
            self::TIPO_RECEPCION_ENVIO => [
                'titulo' => 'Recepción de Envíos en Destino',
                'descripcion' => 'Confirmación de llegada de envíos en tránsito (Trujillo ↔ Pataz) con cantidades recibidas.',
                'permiso' => 'importar_recepcion',
                'archivo' => 'plantilla-recepcion-envio.xlsx',
            ],
        ];
    }

    /**
     * @return list<string>
     */
    public function tiposPermitidos(User $usuario): array
    {
        return array_values(array_filter(self::tiposValidos(), function (string $tipo) use ($usuario): bool {
            return $this->tienePermisoTipo($tipo, $usuario);
        }));
    }

    public function tienePermisoTipo(string $tipo, User $usuario): bool
    {
        $meta = self::metadataTipos()[$tipo] ?? null;
        if (! $meta) {
            return false;
        }

        return $usuario->puede($meta['permiso'], 'C');
    }

    /**
     * @throws AuthorizationException
     */
    public function asegurarPermisoTipo(string $tipo, User $usuario): void
    {
        if (! in_array($tipo, self::tiposValidos(), true)) {
            throw new ValidationException(validator([], []), ['tipo' => "El tipo de importación '{$tipo}' no es válido."]);
        }

        if (! $this->tienePermisoTipo($tipo, $usuario)) {
            throw new AuthorizationException("No tiene permisos para importar {$tipo}.");
        }
    }

    public function descargarPlantilla(string $tipo): BinaryFileResponse
    {
        $datos = match ($tipo) {
            self::TIPO_TRABAJADORES => [
                'encabezados' => ['dni', 'nombres', 'apellidos', 'fecha_nacimiento', 'sexo', 'telefono', 'email', 'direccion', 'cargo', 'area', 'sede_base', 'fecha_ingreso', 'esquema_rotacion', 'contacto_emergencia_nombre', 'contacto_emergencia_telefono'],
                'ejemplo' => [['45891234', 'Juan Carlos', 'Pérez Quispe', '1990-05-15', 'M', '949123456', 'juan.perez@mineria.pe', 'Av. América Sur 123', 'Operario de mina', 'Operaciones', 'Pataz', '2026-01-10', '21x7', 'María Quispe', '949888777']],
                'archivo' => 'plantilla-trabajadores.xlsx',
            ],
            self::TIPO_MATERIALES => [
                'encabezados' => ['codigo', 'nombre', 'descripcion', 'categoria', 'unidad_medida', 'stock_minimo', 'costo_referencial'],
                'ejemplo' => [['MAT-100', 'Barreno cónico 5 pies', 'Barreno para perforación jackleg', 'Herramientas', 'UND', '10.000', '420.00']],
                'archivo' => 'plantilla-materiales.xlsx',
            ],
            self::TIPO_PROVEEDORES => [
                'encabezados' => ['ruc', 'razon_social', 'contacto', 'telefono', 'email', 'direccion', 'condicion_pago', 'observaciones'],
                'ejemplo' => [['20601234567', 'Insumos del Norte S.A.C.', 'Carlos Gómez', '044-245112', 'ventas@insumosnorte.pe', 'Av. Mansiche 450, Trujillo', 'CREDITO 30 DIAS', 'Proveedor homologado de EPP']],
                'archivo' => 'plantilla-proveedores.xlsx',
            ],
            self::TIPO_STOCK_INICIAL => [
                'encabezados' => ['almacen', 'codigo_material', 'cantidad', 'observacion'],
                'ejemplo' => [['ALM-TRUJILLO', 'MAT-001', '50.000', 'Carga de inventario inicial apertura de año']],
                'archivo' => 'plantilla-stock-inicial.xlsx',
            ],
            self::TIPO_RECEPCION_ENVIO => [
                'encabezados' => ['codigo_envio', 'codigo_material', 'cantidad_recibida', 'observacion'],
                'ejemplo' => [['ENV-2026-0001', 'MAT-001', '15.000', 'Recepción conforme en almacén de destino']],
                'archivo' => 'plantilla-recepcion-envio.xlsx',
            ],
            default => throw new \InvalidArgumentException("Tipo no soportado: {$tipo}"),
        };

        return Excel::download(new TablaReporteExport($datos['encabezados'], $datos['ejemplo']), $datos['archivo']);
    }

    /**
     * @return array{
     *     token: string,
     *     tipo: string,
     *     nombre_archivo: string,
     *     total_filas: int,
     *     validas_count: int,
     *     errores_count: int,
     *     columnas: list<string>,
     *     filas_validas_preview: list<array<string, mixed>>,
     *     filas_con_error: list<array{fila: int, datos: array<string, mixed>, errores: list<string>}>
     * }
     *
     * @throws ValidationException
     */
    public function previsualizar(string $tipo, UploadedFile $archivo, User $usuario): array
    {
        $this->asegurarPermisoTipo($tipo, $usuario);

        $extension = strtolower((string) $archivo->getClientOriginalExtension());
        if (! in_array($extension, ['xlsx', 'xls', 'csv'], true)) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo debe estar en formato Excel (.xlsx, .xls) o CSV.',
            ]);
        }

        try {
            $spreadsheet = IOFactory::load($archivo->getRealPath());
            $worksheet = $spreadsheet->getActiveSheet();
            $filasRaw = $worksheet->toArray(null, true, false, false);
        } catch (\Throwable $e) {
            throw ValidationException::withMessages([
                'archivo' => 'No se pudo leer el archivo Excel: '.$e->getMessage(),
            ]);
        }

        if (empty($filasRaw) || count($filasRaw) < 2) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo no contiene filas de datos (debe tener encabezados y al menos una fila con datos).',
            ]);
        }

        $encabezadosRaw = array_map(static fn (mixed $h): string => strtolower(trim((string) $h)), $filasRaw[0]);
        $encabezadosRequeridos = $this->encabezadosRequeridosPorTipo($tipo);

        $columnasFaltantes = array_diff($encabezadosRequeridos, $encabezadosRaw);
        if (! empty($columnasFaltantes)) {
            throw ValidationException::withMessages([
                'archivo' => 'El archivo no contiene las columnas requeridas: '.implode(', ', $columnasFaltantes),
            ]);
        }

        $mapaIndices = [];
        foreach ($encabezadosRaw as $idx => $nombreColumna) {
            if ($nombreColumna !== '') {
                $mapaIndices[$nombreColumna] = $idx;
            }
        }

        $filasValidas = [];
        $filasConError = [];

        // Validar filas según el tipo específico
        match ($tipo) {
            self::TIPO_TRABAJADORES => $this->validarTrabajadores($filasRaw, $mapaIndices, $filasValidas, $filasConError),
            self::TIPO_MATERIALES => $this->validarMateriales($filasRaw, $mapaIndices, $filasValidas, $filasConError),
            self::TIPO_PROVEEDORES => $this->validarProveedores($filasRaw, $mapaIndices, $filasValidas, $filasConError),
            self::TIPO_STOCK_INICIAL => $this->validarStockInicial($filasRaw, $mapaIndices, $usuario, $filasValidas, $filasConError),
            self::TIPO_RECEPCION_ENVIO => $this->validarRecepcionEnvio($filasRaw, $mapaIndices, $usuario, $filasValidas, $filasConError),
        };

        $token = (string) Str::uuid();

        $resultadoLote = [
            'token' => $token,
            'tipo' => $tipo,
            'nombre_archivo' => $archivo->getClientOriginalName(),
            'total_filas' => count($filasRaw) - 1,
            'validas_count' => count($filasValidas),
            'errores_count' => count($filasConError),
            'columnas' => $encabezadosRequeridos,
            'filas_validas_preview' => array_slice($filasValidas, 0, 50),
            'filas_con_error' => $filasConError,
        ];

        // Guardar lote completo en cache por 30 minutos para posterior confirmación o reporte
        Cache::put("import_lote_{$token}", [
            'tipo' => $tipo,
            'usuario_id' => $usuario->id,
            'filas_validas' => $filasValidas,
            'filas_con_error' => $filasConError,
            'encabezados' => $encabezadosRaw,
        ], now()->addMinutes(30));

        return $resultadoLote;
    }

    /**
     * @return array{exitosos: int, mensaje: string}
     *
     * @throws ValidationException
     */
    public function confirmar(string $token, User $usuario): array
    {
        $datosLote = Cache::get("import_lote_{$token}");

        if (! $datosLote) {
            throw ValidationException::withMessages([
                'token' => 'La sesión de importación ha expirado o no es válida. Por favor, suba el archivo nuevamente.',
            ]);
        }

        $tipo = $datosLote['tipo'];
        $this->asegurarPermisoTipo($tipo, $usuario);

        $filasValidas = $datosLote['filas_validas'] ?? [];

        if (empty($filasValidas)) {
            throw ValidationException::withMessages([
                'token' => 'No hay filas válidas para importar en este lote.',
            ]);
        }

        $exitosos = DB::transaction(function () use ($tipo, $filasValidas, $usuario): int {
            return match ($tipo) {
                self::TIPO_TRABAJADORES => $this->ejecutarImportacionTrabajadores($filasValidas),
                self::TIPO_MATERIALES => $this->ejecutarImportacionMateriales($filasValidas),
                self::TIPO_PROVEEDORES => $this->ejecutarImportacionProveedores($filasValidas),
                self::TIPO_STOCK_INICIAL => $this->ejecutarImportacionStockInicial($filasValidas, $usuario),
                self::TIPO_RECEPCION_ENVIO => $this->ejecutarImportacionRecepcionEnvio($filasValidas, $usuario),
            };
        });

        Cache::forget("import_lote_{$token}");

        return [
            'exitosos' => $exitosos,
            'mensaje' => "Se importaron exitosamente {$exitosos} registros de {$tipo}.",
        ];
    }

    public function descargarReporteErrores(string $token): BinaryFileResponse
    {
        $datosLote = Cache::get("import_lote_{$token}");

        if (! $datosLote || empty($datosLote['filas_con_error'])) {
            throw new \InvalidArgumentException('No se encontraron errores registrados para esta sesión de importación.');
        }

        $encabezados = array_merge($datosLote['encabezados'], ['error_motivo']);
        $filasSalida = [];

        foreach ($datosLote['filas_con_error'] as $item) {
            $filaDatos = [];
            foreach ($datosLote['encabezados'] as $col) {
                $filaDatos[] = $item['datos'][$col] ?? '';
            }
            $filaDatos[] = implode(' | ', $item['errores']);
            $filasSalida[] = $filaDatos;
        }

        $nombreArchivo = 'reporte-errores-'.$datosLote['tipo'].'-'.now()->format('Ymd_His').'.xlsx';

        return Excel::download(new TablaReporteExport($encabezados, $filasSalida), $nombreArchivo);
    }

    /**
     * @return list<string>
     */
    private function encabezadosRequeridosPorTipo(string $tipo): array
    {
        return match ($tipo) {
            self::TIPO_TRABAJADORES => ['dni', 'nombres', 'apellidos', 'fecha_nacimiento', 'sexo', 'cargo', 'area', 'sede_base', 'fecha_ingreso'],
            self::TIPO_MATERIALES => ['codigo', 'nombre', 'categoria', 'unidad_medida'],
            self::TIPO_PROVEEDORES => ['ruc', 'razon_social'],
            self::TIPO_STOCK_INICIAL => ['almacen', 'codigo_material', 'cantidad'],
            self::TIPO_RECEPCION_ENVIO => ['codigo_envio', 'codigo_material', 'cantidad_recibida'],
            default => throw new \InvalidArgumentException("Tipo no soportado: {$tipo}"),
        };
    }

    /**
     * @param  array<int, list<mixed>>  $filasRaw
     * @param  array<string, int>  $mapa
     * @param  list<array<string, mixed>>  $filasValidas
     * @param  list<array{fila: int, datos: array<string, mixed>, errores: list<string>}>  $filasConError
     */
    private function validarTrabajadores(array $filasRaw, array $mapa, array &$filasValidas, array &$filasConError): void
    {
        $cargosMap = Cargo::query()->activos()->get()->keyBy(static fn (Cargo $c): string => mb_strtolower(trim($c->nombre)));
        $areasMap = Area::query()->activos()->get()->keyBy(static fn (Area $a): string => mb_strtolower(trim($a->nombre)));
        $sedesMap = Sede::query()->activos()->get()->keyBy(static fn (Sede $s): string => mb_strtolower(trim($s->nombre)));
        $dnisEnDb = Trabajador::withTrashed()->pluck('dni')->flip();
        $dnisEnArchivo = [];

        for ($i = 1; $i < count($filasRaw); $i++) {
            $numFila = $i + 1;
            $row = $filasRaw[$i];
            $datosFila = $this->extraerFila($row, $mapa);

            if ($this->filaEstaVacia($datosFila)) {
                continue;
            }

            $errores = [];

            $dni = trim((string) ($datosFila['dni'] ?? ''));
            if (! preg_match('/^\d{8}$/', $dni)) {
                $errores[] = 'El DNI debe tener exactamente 8 dígitos numéricos.';
            } elseif ($dnisEnDb->has($dni)) {
                $errores[] = "Ya existe un trabajador registrado con el DNI {$dni}.";
            } elseif (isset($dnisEnArchivo[$dni])) {
                $errores[] = "El DNI {$dni} está duplicado en la fila {$dnisEnArchivo[$dni]} de este archivo.";
            } else {
                $dnisEnArchivo[$dni] = $numFila;
            }

            $nombres = trim((string) ($datosFila['nombres'] ?? ''));
            $apellidos = trim((string) ($datosFila['apellidos'] ?? ''));
            if ($nombres === '') {
                $errores[] = 'El nombre es obligatorio.';
            }
            if ($apellidos === '') {
                $errores[] = 'Los apellidos son obligatorios.';
            }

            $fechaNac = $this->normalizarFecha($datosFila['fecha_nacimiento'] ?? null);
            if (! $fechaNac) {
                $errores[] = 'Fecha de nacimiento inválida o vacía.';
            }

            $fechaIngreso = $this->normalizarFecha($datosFila['fecha_ingreso'] ?? null);
            if (! $fechaIngreso) {
                $errores[] = 'Fecha de ingreso inválida o vacía.';
            }

            $sexoRaw = strtoupper(trim((string) ($datosFila['sexo'] ?? '')));
            $sexo = match ($sexoRaw) {
                'M', 'MASCULINO', 'H' => SexoTrabajador::MASCULINO->value,
                'F', 'FEMENINO', 'MUL' => SexoTrabajador::FEMENINO->value,
                default => null,
            };
            if (! $sexo) {
                $errores[] = 'El sexo debe ser M o F.';
            }

            $cargoNombre = mb_strtolower(trim((string) ($datosFila['cargo'] ?? '')));
            $cargoId = $cargosMap->get($cargoNombre)?->id;
            if (! $cargoId && is_numeric($datosFila['cargo'] ?? null)) {
                $cargoId = Cargo::query()->whereKey((int) $datosFila['cargo'])->value('id');
            }
            if (! $cargoId) {
                $errores[] = 'El cargo no existe en el catálogo activo del sistema.';
            }

            $areaNombre = mb_strtolower(trim((string) ($datosFila['area'] ?? '')));
            $areaId = $areasMap->get($areaNombre)?->id;
            if (! $areaId && is_numeric($datosFila['area'] ?? null)) {
                $areaId = Area::query()->whereKey((int) $datosFila['area'])->value('id');
            }
            if (! $areaId) {
                $errores[] = 'El área no existe en el catálogo activo.';
            }

            $sedeNombre = mb_strtolower(trim((string) ($datosFila['sede_base'] ?? '')));
            $sedeId = $sedesMap->get($sedeNombre)?->id;
            if (! $sedeId && is_numeric($datosFila['sede_base'] ?? null)) {
                $sedeId = Sede::query()->whereKey((int) $datosFila['sede_base'])->value('id');
            }
            if (! $sedeId) {
                $errores[] = 'La sede base no existe en el catálogo activo.';
            }

            if (! empty($errores)) {
                $filasConError[] = ['fila' => $numFila, 'datos' => $datosFila, 'errores' => $errores];
            } else {
                $filasValidas[] = [
                    'dni' => $dni,
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'fecha_nacimiento' => $fechaNac,
                    'fecha_ingreso' => $fechaIngreso,
                    'sexo' => $sexo,
                    'telefono' => trim((string) ($datosFila['telefono'] ?? '')) ?: null,
                    'email' => trim((string) ($datosFila['email'] ?? '')) ?: null,
                    'direccion' => trim((string) ($datosFila['direccion'] ?? '')) ?: null,
                    'contacto_emergencia_nombre' => trim((string) ($datosFila['contacto_emergencia_nombre'] ?? '')) ?: null,
                    'contacto_emergencia_telefono' => trim((string) ($datosFila['contacto_emergencia_telefono'] ?? '')) ?: null,
                    'cargo_id' => $cargoId,
                    'area_id' => $areaId,
                    'sede_base_id' => $sedeId,
                    'esquema_rotacion' => trim((string) ($datosFila['esquema_rotacion'] ?? '21x7')) ?: '21x7',
                ];
            }
        }
    }

    /**
     * @param  array<int, list<mixed>>  $filasRaw
     * @param  array<string, int>  $mapa
     * @param  list<array<string, mixed>>  $filasValidas
     * @param  list<array{fila: int, datos: array<string, mixed>, errores: list<string>}>  $filasConError
     */
    private function validarMateriales(array $filasRaw, array $mapa, array &$filasValidas, array &$filasConError): void
    {
        $categorias = CategoriaMaterial::query()->activos()->get()->keyBy(static fn (CategoriaMaterial $c): string => mb_strtolower(trim($c->nombre)));
        $unidades = UnidadMedida::query()->activos()->get();
        $unidadesPorAbrev = $unidades->keyBy(static fn (UnidadMedida $u): string => mb_strtolower(trim($u->abreviatura)));
        $unidadesPorNombre = $unidades->keyBy(static fn (UnidadMedida $u): string => mb_strtolower(trim($u->nombre)));
        $codigosEnDb = Material::withTrashed()->pluck('codigo')->flip();
        $codigosEnArchivo = [];

        for ($i = 1; $i < count($filasRaw); $i++) {
            $numFila = $i + 1;
            $datosFila = $this->extraerFila($filasRaw[$i], $mapa);

            if ($this->filaEstaVacia($datosFila)) {
                continue;
            }

            $errores = [];

            $codigo = strtoupper(trim((string) ($datosFila['codigo'] ?? '')));
            if ($codigo === '') {
                $errores[] = 'El código del material es obligatorio.';
            } elseif ($codigosEnDb->has($codigo)) {
                $errores[] = "Ya existe un material con el código {$codigo}.";
            } elseif (isset($codigosEnArchivo[$codigo])) {
                $errores[] = "El código {$codigo} está duplicado en la fila {$codigosEnArchivo[$codigo]}.";
            } else {
                $codigosEnArchivo[$codigo] = $numFila;
            }

            $nombre = trim((string) ($datosFila['nombre'] ?? ''));
            if ($nombre === '') {
                $errores[] = 'El nombre del material es obligatorio.';
            }

            $catNombre = mb_strtolower(trim((string) ($datosFila['categoria'] ?? '')));
            $catId = $categorias->get($catNombre)?->id;
            if (! $catId && is_numeric($datosFila['categoria'] ?? null)) {
                $catId = CategoriaMaterial::query()->whereKey((int) $datosFila['categoria'])->value('id');
            }
            if (! $catId) {
                $errores[] = 'La categoría de material no existe en el catálogo activo.';
            }

            $uniTexto = mb_strtolower(trim((string) ($datosFila['unidad_medida'] ?? '')));
            $uniId = $unidadesPorAbrev->get($uniTexto)?->id ?? $unidadesPorNombre->get($uniTexto)?->id;
            if (! $uniId && is_numeric($datosFila['unidad_medida'] ?? null)) {
                $uniId = UnidadMedida::query()->whereKey((int) $datosFila['unidad_medida'])->value('id');
            }
            if (! $uniId) {
                $errores[] = 'La unidad de medida no existe en el catálogo activo.';
            }

            $stockMin = isset($datosFila['stock_minimo']) && is_numeric($datosFila['stock_minimo'])
                ? (float) $datosFila['stock_minimo']
                : 0.0;
            if ($stockMin < 0) {
                $errores[] = 'El stock mínimo no puede ser negativo.';
            }

            $costo = isset($datosFila['costo_referencial']) && is_numeric($datosFila['costo_referencial'])
                ? (float) $datosFila['costo_referencial']
                : 0.0;
            if ($costo < 0) {
                $errores[] = 'El costo referencial no puede ser negativo.';
            }

            if (! empty($errores)) {
                $filasConError[] = ['fila' => $numFila, 'datos' => $datosFila, 'errores' => $errores];
            } else {
                $filasValidas[] = [
                    'codigo' => $codigo,
                    'nombre' => $nombre,
                    'descripcion' => trim((string) ($datosFila['descripcion'] ?? '')) ?: null,
                    'categoria_id' => $catId,
                    'unidad_medida_id' => $uniId,
                    'stock_minimo' => $stockMin,
                    'costo_referencial' => $costo,
                ];
            }
        }
    }

    /**
     * @param  array<int, list<mixed>>  $filasRaw
     * @param  array<string, int>  $mapa
     * @param  list<array<string, mixed>>  $filasValidas
     * @param  list<array{fila: int, datos: array<string, mixed>, errores: list<string>}>  $filasConError
     */
    private function validarProveedores(array $filasRaw, array $mapa, array &$filasValidas, array &$filasConError): void
    {
        $rucsEnDb = Proveedor::withTrashed()->pluck('ruc')->flip();
        $rucsEnArchivo = [];

        for ($i = 1; $i < count($filasRaw); $i++) {
            $numFila = $i + 1;
            $datosFila = $this->extraerFila($filasRaw[$i], $mapa);

            if ($this->filaEstaVacia($datosFila)) {
                continue;
            }

            $errores = [];

            $ruc = trim((string) ($datosFila['ruc'] ?? ''));
            if (! preg_match('/^\d{11}$/', $ruc)) {
                $errores[] = 'El RUC debe tener exactamente 11 dígitos numéricos.';
            } elseif ($rucsEnDb->has($ruc)) {
                $errores[] = "Ya existe un proveedor registrado con el RUC {$ruc}.";
            } elseif (isset($rucsEnArchivo[$ruc])) {
                $errores[] = "El RUC {$ruc} está duplicado en la fila {$rucsEnArchivo[$ruc]}.";
            } else {
                $rucsEnArchivo[$ruc] = $numFila;
            }

            $razon = trim((string) ($datosFila['razon_social'] ?? ''));
            if ($razon === '') {
                $errores[] = 'La razón social es obligatoria.';
            }

            $email = trim((string) ($datosFila['email'] ?? ''));
            if ($email !== '' && ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errores[] = 'El correo electrónico no tiene un formato válido.';
            }

            if (! empty($errores)) {
                $filasConError[] = ['fila' => $numFila, 'datos' => $datosFila, 'errores' => $errores];
            } else {
                $filasValidas[] = [
                    'ruc' => $ruc,
                    'razon_social' => $razon,
                    'contacto' => trim((string) ($datosFila['contacto'] ?? '')) ?: null,
                    'telefono' => trim((string) ($datosFila['telefono'] ?? '')) ?: null,
                    'email' => $email ?: null,
                    'direccion' => trim((string) ($datosFila['direccion'] ?? '')) ?: null,
                    'condicion_pago' => trim((string) ($datosFila['condicion_pago'] ?? 'CONTADO')) ?: 'CONTADO',
                    'observaciones' => trim((string) ($datosFila['observaciones'] ?? '')) ?: null,
                ];
            }
        }
    }

    /**
     * @param  array<int, list<mixed>>  $filasRaw
     * @param  array<string, int>  $mapa
     * @param  list<array<string, mixed>>  $filasValidas
     * @param  list<array{fila: int, datos: array<string, mixed>, errores: list<string>}>  $filasConError
     */
    private function validarStockInicial(array $filasRaw, array $mapa, User $usuario, array &$filasValidas, array &$filasConError): void
    {
        $almacenes = Almacen::query()->activos()->get();
        $almacenesPorCodigo = $almacenes->keyBy(static fn (Almacen $a): string => strtoupper(trim($a->codigo)));
        $almacenesPorNombre = $almacenes->keyBy(static fn (Almacen $a): string => mb_strtolower(trim($a->nombre)));

        $materialesPorCodigo = Material::query()->activos()->get()->keyBy(static fn (Material $m): string => strtoupper(trim($m->codigo)));
        $paresEnArchivo = [];

        for ($i = 1; $i < count($filasRaw); $i++) {
            $numFila = $i + 1;
            $datosFila = $this->extraerFila($filasRaw[$i], $mapa);

            if ($this->filaEstaVacia($datosFila)) {
                continue;
            }

            $errores = [];

            $almTexto = trim((string) ($datosFila['almacen'] ?? ''));
            $almacen = $almacenesPorCodigo->get(strtoupper($almTexto)) ?? $almacenesPorNombre->get(mb_strtolower($almTexto));
            if (! $almacen && is_numeric($almTexto)) {
                $almacen = $almacenes->firstWhere('id', (int) $almTexto);
            }

            if (! $almacen) {
                $errores[] = "El almacén '{$almTexto}' no existe o está inactivo.";
            } elseif (! $this->scope->puedeAccederAlmacen($usuario, $almacen->id)) {
                $errores[] = "No tiene permiso para ingresar stock en el almacén {$almacen->nombre}.";
            }

            $matCodigo = strtoupper(trim((string) ($datosFila['codigo_material'] ?? '')));
            $material = $materialesPorCodigo->get($matCodigo);
            if (! $material) {
                $errores[] = "El código de material '{$matCodigo}' no existe en el catálogo activo.";
            }

            $cantRaw = $datosFila['cantidad'] ?? null;
            if (! is_numeric($cantRaw) || (float) $cantRaw <= 0) {
                $errores[] = 'La cantidad debe ser un número mayor a cero.';
            }

            $parClave = ($almacen?->id ?? '0').':'.$matCodigo;
            if (isset($paresEnArchivo[$parClave])) {
                $errores[] = "La combinación almacén-material ya fue registrada en la fila {$paresEnArchivo[$parClave]}.";
            } else {
                $paresEnArchivo[$parClave] = $numFila;
            }

            if (! empty($errores)) {
                $filasConError[] = ['fila' => $numFila, 'datos' => $datosFila, 'errores' => $errores];
            } else {
                $filasValidas[] = [
                    'almacen_id' => $almacen->id,
                    'almacen_nombre' => $almacen->nombre,
                    'material_id' => $material->id,
                    'codigo_material' => $material->codigo,
                    'material_nombre' => $material->nombre,
                    'cantidad' => round((float) $cantRaw, 3),
                    'observacion' => trim((string) ($datosFila['observacion'] ?? 'Carga inicial desde Excel')) ?: 'Carga inicial desde Excel',
                ];
            }
        }
    }

    /**
     * @param  array<int, list<mixed>>  $filasRaw
     * @param  array<string, int>  $mapa
     * @param  list<array<string, mixed>>  $filasValidas
     * @param  list<array{fila: int, datos: array<string, mixed>, errores: list<string>}>  $filasConError
     */
    private function validarRecepcionEnvio(array $filasRaw, array $mapa, User $usuario, array &$filasValidas, array &$filasConError): void
    {
        $enviosMap = Envio::query()
            ->with(['detalles.material', 'almacenDestino'])
            ->where('estado', EstadoEnvio::EN_TRANSITO)
            ->get()
            ->keyBy(static fn (Envio $e): string => strtoupper(trim($e->codigo)));

        $enviosVerificados = [];

        for ($i = 1; $i < count($filasRaw); $i++) {
            $numFila = $i + 1;
            $datosFila = $this->extraerFila($filasRaw[$i], $mapa);

            if ($this->filaEstaVacia($datosFila)) {
                continue;
            }

            $errores = [];

            $codEnvio = strtoupper(trim((string) ($datosFila['codigo_envio'] ?? '')));
            /** @var Envio|null $envio */
            $envio = $enviosMap->get($codEnvio);

            if (! $envio) {
                $envioExiste = Envio::query()->where('codigo', $codEnvio)->first();
                if ($envioExiste) {
                    $errores[] = "El envío {$codEnvio} no está en estado EN_TRANSITO (actual: {$envioExiste->estado->etiqueta()}).";
                } else {
                    $errores[] = "El envío {$codEnvio} no existe en el sistema.";
                }
            } else {
                if (! $this->scope->puedeAccederAlmacen($usuario, $envio->almacen_destino_id)) {
                    $errores[] = "No tiene permiso para confirmar recepción en {$envio->almacenDestino?->nombre}.";
                }
            }

            $matCod = strtoupper(trim((string) ($datosFila['codigo_material'] ?? '')));
            $detalleEnvio = null;

            if ($envio) {
                $detalleEnvio = $envio->detalles->first(static function ($d) use ($matCod): bool {
                    return strtoupper(trim((string) $d->material?->codigo)) === $matCod;
                });

                if (! $detalleEnvio) {
                    $errores[] = "El material {$matCod} no forma parte de los ítems del envío {$codEnvio}.";
                }
            }

            $cantRaw = $datosFila['cantidad_recibida'] ?? null;
            if (! is_numeric($cantRaw) || (float) $cantRaw < 0) {
                $errores[] = 'La cantidad recibida debe ser un valor numérico mayor o igual a cero.';
            }

            if (! empty($errores)) {
                $filasConError[] = ['fila' => $numFila, 'datos' => $datosFila, 'errores' => $errores];
            } else {
                $filasValidas[] = [
                    'envio_id' => $envio->id,
                    'codigo_envio' => $envio->codigo,
                    'envio_detalle_id' => $detalleEnvio->id,
                    'material_id' => $detalleEnvio->material_id,
                    'codigo_material' => $matCod,
                    'cantidad_enviada' => (float) $detalleEnvio->cantidad_enviada,
                    'cantidad_recibida' => round((float) $cantRaw, 3),
                    'observacion' => trim((string) ($datosFila['observacion'] ?? '')) ?: null,
                ];
            }
        }
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function ejecutarImportacionTrabajadores(array $filas): int
    {
        $count = 0;
        foreach ($filas as $f) {
            $sede = Sede::query()->find($f['sede_base_id']);
            $ubicacion = $sede && str_contains(mb_strtolower($sede->nombre), 'pataz')
                ? UbicacionTrabajador::PATAZ
                : UbicacionTrabajador::TRUJILLO;

            Trabajador::query()->create(array_merge($f, [
                'estado' => EstadoTrabajador::ACTIVO,
                'ubicacion_actual' => $ubicacion,
                'activo' => true,
            ]));
            $count++;
        }

        return $count;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function ejecutarImportacionMateriales(array $filas): int
    {
        $count = 0;
        foreach ($filas as $f) {
            Material::query()->create(array_merge($f, ['activo' => true]));
            $count++;
        }

        return $count;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function ejecutarImportacionProveedores(array $filas): int
    {
        $count = 0;
        foreach ($filas as $f) {
            Proveedor::query()->create(array_merge($f, ['activo' => true]));
            $count++;
        }

        return $count;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function ejecutarImportacionStockInicial(array $filas, User $usuario): int
    {
        $count = 0;
        foreach ($filas as $f) {
            $this->inventario->aplicarMovimiento(
                almacenId: (int) $f['almacen_id'],
                materialId: (int) $f['material_id'],
                tipo: TipoMovimientoInventario::ENTRADA_COMPRA,
                cantidad: (float) $f['cantidad'],
                usuario: $usuario,
                esSalida: false,
                observacion: $f['observacion'] ?? 'Carga inicial desde Excel',
            );
            $count++;
        }

        return $count;
    }

    /**
     * @param  list<array<string, mixed>>  $filas
     */
    private function ejecutarImportacionRecepcionEnvio(array $filas, User $usuario): int
    {
        $porEnvio = [];
        foreach ($filas as $f) {
            $porEnvio[$f['envio_id']][] = $f;
        }

        $enviosProcesados = 0;

        foreach ($porEnvio as $envioId => $items) {
            /** @var Envio $envio */
            $envio = Envio::query()->with('detalles')->findOrFail($envioId);
            $recepciones = [];

            foreach ($envio->detalles as $det) {
                $encontrado = collect($items)->firstWhere('envio_detalle_id', $det->id);

                if ($encontrado) {
                    $recepciones[$det->id] = [
                        'cantidad_recibida' => $encontrado['cantidad_recibida'],
                        'observacion_recepcion' => $encontrado['observacion'] ?? null,
                    ];
                } else {
                    $recepciones[$det->id] = [
                        'cantidad_recibida' => (float) $det->cantidad_enviada,
                        'observacion_recepcion' => 'Conforme por defecto en importación',
                    ];
                }
            }

            $this->envios->recibir(
                envio: $envio,
                usuario: $usuario,
                recepcionesPorDetalleId: $recepciones,
                fechaRecepcion: now()->toDateString(),
                observaciones: 'Recepción registrada vía importador Excel.',
            );

            $enviosProcesados++;
        }

        return count($filas);
    }

    /**
     * @param  list<mixed>  $row
     * @param  array<string, int>  $mapa
     * @return array<string, mixed>
     */
    private function extraerFila(array $row, array $mapa): array
    {
        $res = [];
        foreach ($mapa as $col => $idx) {
            $res[$col] = $row[$idx] ?? null;
        }

        return $res;
    }

    /**
     * @param  array<string, mixed>  $datos
     */
    private function filaEstaVacia(array $datos): bool
    {
        foreach ($datos as $val) {
            if ($val !== null && trim((string) $val) !== '') {
                return false;
            }
        }

        return true;
    }

    private function normalizarFecha(mixed $valor): ?string
    {
        if (empty($valor)) {
            return null;
        }

        if ($valor instanceof \DateTimeInterface) {
            return $valor->format('Y-m-d');
        }

        if (is_numeric($valor) && (float) $valor > 20000 && (float) $valor < 80000) {
            try {
                return ExcelDate::excelToDateTimeObject((float) $valor)->format('Y-m-d');
            } catch (\Throwable) {
                // fall through
            }
        }

        $str = trim((string) $valor);

        // Formato DD/MM/YYYY o DD-MM-YYYY
        if (preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{4})$/', $str, $m)) {
            return sprintf('%04d-%02d-%02d', (int) $m[3], (int) $m[2], (int) $m[1]);
        }

        // Formato YYYY-MM-DD
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $str)) {
            return $str;
        }

        try {
            return Carbon::parse($str)->format('Y-m-d');
        } catch (\Throwable) {
            return null;
        }
    }
}
