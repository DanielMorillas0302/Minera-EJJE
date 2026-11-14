<?php

use App\Enums\RolUsuario;
use App\Http\Controllers\Core\AdministracionController;
use App\Http\Controllers\Core\AuditoriaController;
use App\Http\Controllers\Core\CatalogoController;
use App\Http\Controllers\Core\ImportacionController;
use App\Http\Controllers\Core\NotificacionController;
use App\Http\Controllers\Core\UsuarioController;
use App\Http\Controllers\Finanzas\CatalogoFinanzasController;
use App\Http\Controllers\Finanzas\CuentasPorPagarController;
use App\Http\Controllers\Finanzas\FinanzasReporteController;
use App\Http\Controllers\Finanzas\FinanzasResumenController;
use App\Http\Controllers\Finanzas\MovimientoFinancieroController;
use App\Http\Controllers\Finanzas\PresupuestoController;
use App\Http\Controllers\Gerencia\GerenciaDashboardController;
use App\Http\Controllers\Logistica\CatalogoLogisticaController;
use App\Http\Controllers\Logistica\EnvioController;
use App\Http\Controllers\Logistica\InventarioController;
use App\Http\Controllers\Logistica\LogisticaReporteController;
use App\Http\Controllers\Logistica\LogisticaResumenController;
use App\Http\Controllers\Logistica\OrdenCompraController;
use App\Http\Controllers\Logistica\RequerimientoController;
use App\Http\Controllers\ModuloBaseController;
use App\Http\Controllers\Reportes\ReportesHubController;
use App\Http\Controllers\Rrhh\ExamenOcupacionalController;
use App\Http\Controllers\Rrhh\MovimientoPersonalController;
use App\Http\Controllers\Rrhh\RrhhReporteController;
use App\Http\Controllers\Rrhh\RrhhResumenController;
use App\Http\Controllers\Rrhh\TrabajadorController;
use App\Http\Controllers\Rrhh\VacacionPermisoController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('welcome');
})->name('home');

Route::middleware(['auth', 'rol'])->group(function () {
    Route::get('dashboard', [ModuloBaseController::class, 'inicio'])->name('dashboard');

    Route::prefix('gerencia')
        ->name('gerencia.')
        ->middleware('rol:'.implode(',', [
            RolUsuario::ADMIN->value,
            RolUsuario::GERENTE->value,
            RolUsuario::RRHH_JEFE->value,
            RolUsuario::LOGISTICA_JEFE->value,
            RolUsuario::FINANZAS->value,
        ]))
        ->group(function () {
            Route::get('/', [GerenciaDashboardController::class, 'index'])->name('index');
            Route::get('reporte-pdf', [GerenciaDashboardController::class, 'reportePdf'])->name('reporte_pdf');
        });

    Route::prefix('reportes')
        ->name('reportes.')
        ->middleware('rol:'.implode(',', [
            RolUsuario::ADMIN->value,
            RolUsuario::RRHH_JEFE->value,
            RolUsuario::RRHH_ASISTENTE->value,
            RolUsuario::LOGISTICA_JEFE->value,
            RolUsuario::LOGISTICA_ASISTENTE->value,
            RolUsuario::FINANZAS->value,
            RolUsuario::GERENTE->value,
        ]))
        ->group(function () {
            Route::get('/', [ReportesHubController::class, 'index'])->name('index');
        });

    Route::prefix('finanzas')
        ->name('finanzas.')
        ->middleware('rol:'.implode(',', [
            RolUsuario::ADMIN->value,
            RolUsuario::FINANZAS->value,
            RolUsuario::GERENTE->value,
        ]))
        ->group(function () {
            Route::get('/', [FinanzasResumenController::class, 'index'])->name('index');

            // Movimientos financieros
            Route::get('movimientos', [MovimientoFinancieroController::class, 'index'])
                ->middleware('permiso:movimientos_financieros,L')
                ->name('movimientos.index');
            Route::post('movimientos', [MovimientoFinancieroController::class, 'store'])
                ->middleware('permiso:movimientos_financieros,C')
                ->name('movimientos.store');
            Route::put('movimientos/{movimiento}', [MovimientoFinancieroController::class, 'update'])
                ->middleware('permiso:movimientos_financieros,E')
                ->name('movimientos.update');
            Route::delete('movimientos/{movimiento}', [MovimientoFinancieroController::class, 'destroy'])
                ->middleware('permiso:movimientos_financieros,E')
                ->name('movimientos.destroy');
            Route::post('movimientos/{movimiento}/pagar', [MovimientoFinancieroController::class, 'pagar'])
                ->middleware('permiso:movimientos_financieros,E')
                ->name('movimientos.pagar');
            Route::post('movimientos/{movimiento}/adjuntos', [MovimientoFinancieroController::class, 'subirAdjunto'])
                ->middleware('permiso:movimientos_financieros,E')
                ->name('movimientos.adjuntos.store');
            Route::get('movimientos/adjuntos/{adjunto}/descargar', [MovimientoFinancieroController::class, 'descargarAdjunto'])
                ->middleware('permiso:movimientos_financieros,L')
                ->name('movimientos.adjuntos.descargar');
            Route::delete('movimientos/adjuntos/{adjunto}', [MovimientoFinancieroController::class, 'eliminarAdjunto'])
                ->middleware('permiso:movimientos_financieros,E')
                ->name('movimientos.adjuntos.destroy');

            // Cuentas por pagar
            Route::get('cuentas-por-pagar', [CuentasPorPagarController::class, 'index'])
                ->middleware('permiso:movimientos_financieros,L')
                ->name('cuentas_por_pagar.index');

            // Presupuestos
            Route::get('presupuestos', [PresupuestoController::class, 'index'])
                ->middleware('permiso:presupuesto,L')
                ->name('presupuestos.index');
            Route::post('presupuestos', [PresupuestoController::class, 'store'])
                ->middleware('permiso:presupuesto,C')
                ->name('presupuestos.store');
            Route::post('presupuestos/lote', [PresupuestoController::class, 'guardarLote'])
                ->middleware('permiso:presupuesto,C')
                ->name('presupuestos.lote');

            // Catálogos (Centros de costo y Categorías de gasto)
            Route::get('catalogos', [CatalogoFinanzasController::class, 'index'])
                ->middleware('permiso:movimientos_financieros,L')
                ->name('catalogos.index');
            Route::post('centros-costo', [CatalogoFinanzasController::class, 'storeCentroCosto'])
                ->middleware('permiso:movimientos_financieros,C')
                ->name('centros_costo.store');
            Route::put('centros-costo/{centroCosto}', [CatalogoFinanzasController::class, 'updateCentroCosto'])
                ->middleware('permiso:movimientos_financieros,E')
                ->name('centros_costo.update');
            Route::post('centros-costo/{centroCosto}/toggle', [CatalogoFinanzasController::class, 'toggleCentroCosto'])
                ->middleware('permiso:movimientos_financieros,E')
                ->name('centros_costo.toggle');

            Route::post('categorias-gasto', [CatalogoFinanzasController::class, 'storeCategoriaGasto'])
                ->middleware('permiso:movimientos_financieros,C')
                ->name('categorias_gasto.store');
            Route::put('categorias-gasto/{categoriaGasto}', [CatalogoFinanzasController::class, 'updateCategoriaGasto'])
                ->middleware('permiso:movimientos_financieros,E')
                ->name('categorias_gasto.update');
            Route::post('categorias-gasto/{categoriaGasto}/toggle', [CatalogoFinanzasController::class, 'toggleCategoriaGasto'])
                ->middleware('permiso:movimientos_financieros,E')
                ->name('categorias_gasto.toggle');

            // Reportes
            Route::get('reportes', [FinanzasReporteController::class, 'index'])
                ->middleware('permiso:movimientos_financieros,L')
                ->name('reportes.index');
            Route::get('reportes/exportar', [FinanzasReporteController::class, 'exportar'])
                ->middleware('permiso:movimientos_financieros,L')
                ->name('reportes.exportar');
            Route::get('reportes/presupuesto-pdf', [FinanzasReporteController::class, 'presupuestoPdf'])
                ->middleware('permiso:presupuesto,L')
                ->name('reportes.presupuesto_pdf');
        });

    Route::prefix('logistica')
        ->name('logistica.')
        ->middleware('rol:'.implode(',', [
            RolUsuario::ADMIN->value,
            RolUsuario::LOGISTICA_JEFE->value,
            RolUsuario::LOGISTICA_ASISTENTE->value,
            RolUsuario::FINANZAS->value,
            RolUsuario::GERENTE->value,
            RolUsuario::RECEPCION_PATAZ->value,
        ]))
        ->group(function () {
            Route::get('/', [LogisticaResumenController::class, 'index'])->name('index');

            // Materiales, categorías y almacenes
            Route::get('materiales', [CatalogoLogisticaController::class, 'materiales'])
                ->middleware('permiso:materiales_proveedores,L')
                ->name('materiales.index');
            Route::post('materiales', [CatalogoLogisticaController::class, 'storeMaterial'])
                ->middleware('permiso:materiales_proveedores,C')
                ->name('materiales.store');
            Route::put('materiales/{material}', [CatalogoLogisticaController::class, 'updateMaterial'])
                ->middleware('permiso:materiales_proveedores,E')
                ->name('materiales.update');
            Route::post('materiales/{material}/toggle', [CatalogoLogisticaController::class, 'toggleMaterial'])
                ->middleware('permiso:materiales_proveedores,E')
                ->name('materiales.toggle');

            Route::post('categorias', [CatalogoLogisticaController::class, 'storeCategoria'])
                ->middleware('permiso:materiales_proveedores,C')
                ->name('categorias.store');
            Route::put('categorias/{categoria}', [CatalogoLogisticaController::class, 'updateCategoria'])
                ->middleware('permiso:materiales_proveedores,E')
                ->name('categorias.update');
            Route::post('categorias/{categoria}/toggle', [CatalogoLogisticaController::class, 'toggleCategoria'])
                ->middleware('permiso:materiales_proveedores,E')
                ->name('categorias.toggle');

            Route::post('almacenes', [CatalogoLogisticaController::class, 'storeAlmacen'])
                ->middleware('permiso:materiales_proveedores,C')
                ->name('almacenes.store');

            // Proveedores
            Route::get('proveedores', [CatalogoLogisticaController::class, 'proveedores'])
                ->middleware('permiso:materiales_proveedores,L')
                ->name('proveedores.index');
            Route::post('proveedores', [CatalogoLogisticaController::class, 'storeProveedor'])
                ->middleware('permiso:materiales_proveedores,C')
                ->name('proveedores.store');
            Route::put('proveedores/{proveedor}', [CatalogoLogisticaController::class, 'updateProveedor'])
                ->middleware('permiso:materiales_proveedores,E')
                ->name('proveedores.update');
            Route::post('proveedores/{proveedor}/toggle', [CatalogoLogisticaController::class, 'toggleProveedor'])
                ->middleware('permiso:materiales_proveedores,E')
                ->name('proveedores.toggle');

            // Stock, Kardex y Consumos
            Route::get('stock', [InventarioController::class, 'stock'])
                ->middleware('permiso:stock_kardex,L')
                ->name('stock.index');
            Route::get('kardex', [InventarioController::class, 'kardex'])
                ->middleware('permiso:stock_kardex,L')
                ->name('kardex.index');
            Route::post('stock/movimiento', [InventarioController::class, 'registrarMovimiento'])
                ->middleware('permiso:envios,C')
                ->name('stock.movimiento');
            Route::post('consumos', [InventarioController::class, 'registrarConsumo'])
                ->middleware('permiso:recepciones_consumos,C')
                ->name('consumos.store');

            // Requerimientos
            Route::get('requerimientos', [RequerimientoController::class, 'index'])
                ->middleware('permiso:requerimientos,L')
                ->name('requerimientos.index');
            Route::post('requerimientos', [RequerimientoController::class, 'store'])
                ->middleware('permiso:requerimientos,C')
                ->name('requerimientos.store');
            Route::put('requerimientos/{requerimiento}', [RequerimientoController::class, 'update'])
                ->middleware('permiso:requerimientos,E')
                ->name('requerimientos.update');
            Route::post('requerimientos/{requerimiento}/enviar', [RequerimientoController::class, 'enviar'])
                ->middleware('permiso:requerimientos,E')
                ->name('requerimientos.enviar');
            Route::post('requerimientos/{requerimiento}/aprobar', [RequerimientoController::class, 'aprobar'])
                ->middleware('permiso:requerimientos,A')
                ->name('requerimientos.aprobar');
            Route::post('requerimientos/{requerimiento}/rechazar', [RequerimientoController::class, 'rechazar'])
                ->middleware('permiso:requerimientos,A')
                ->name('requerimientos.rechazar');

            // Órdenes de Compra
            Route::get('ordenes-compra', [OrdenCompraController::class, 'index'])
                ->middleware('permiso:ordenes_compra,L')
                ->name('ordenes_compra.index');
            Route::post('ordenes-compra', [OrdenCompraController::class, 'store'])
                ->middleware('permiso:ordenes_compra,C')
                ->name('ordenes_compra.store');
            Route::post('ordenes-compra/{orden}/emitir', [OrdenCompraController::class, 'emitir'])
                ->middleware('permiso:ordenes_compra,A')
                ->name('ordenes_compra.emitir');
            Route::post('ordenes-compra/{orden}/recepcionar', [OrdenCompraController::class, 'recepcionar'])
                ->middleware('permiso:ordenes_compra,E')
                ->name('ordenes_compra.recepcionar');
            Route::post('ordenes-compra/{orden}/anular', [OrdenCompraController::class, 'anular'])
                ->middleware('permiso:ordenes_compra,E')
                ->name('ordenes_compra.anular');
            Route::get('ordenes-compra/{orden}/pdf', [OrdenCompraController::class, 'pdf'])
                ->middleware('permiso:ordenes_compra,L')
                ->name('ordenes_compra.pdf');

            // Envíos y Recepciones
            Route::get('envios', [EnvioController::class, 'index'])
                ->middleware('permiso:envios,L')
                ->name('envios.index');
            Route::get('envios/plantilla-recepcion', [EnvioController::class, 'plantillaRecepcion'])
                ->middleware('permiso:recepciones_consumos,L')
                ->name('envios.plantilla');
            Route::post('envios', [EnvioController::class, 'store'])
                ->middleware('permiso:envios,C')
                ->name('envios.store');
            Route::get('envios/{envio}', [EnvioController::class, 'show'])
                ->middleware('permiso:envios,L')
                ->name('envios.show');
            Route::post('envios/{envio}/despachar', [EnvioController::class, 'despachar'])
                ->middleware('permiso:envios,E')
                ->name('envios.despachar');
            Route::post('envios/{envio}/recibir', [EnvioController::class, 'recibir'])
                ->middleware('permiso:recepciones_consumos,C')
                ->name('envios.recibir');
            Route::post('envios/{envio}/anular', [EnvioController::class, 'anular'])
                ->middleware('permiso:envios,E')
                ->name('envios.anular');
            Route::post('envios/{envio}/adjuntos', [EnvioController::class, 'subirAdjunto'])
                ->middleware('permiso:recepciones_consumos,C')
                ->name('envios.adjuntos.store');
            Route::get('envios/{envio}/adjuntos/{adjunto}', [EnvioController::class, 'descargarAdjunto'])
                ->middleware('permiso:envios,L')
                ->name('envios.adjuntos.download');

            // Reportes Logística
            Route::get('reportes', [LogisticaReporteController::class, 'index'])
                ->middleware('permiso:stock_kardex,L')
                ->name('reportes.index');
            Route::get('reportes/exportar', [LogisticaReporteController::class, 'exportarExcel'])
                ->middleware('permiso:stock_kardex,L')
                ->name('reportes.exportar');
            Route::get('reportes/kardex-pdf', [LogisticaReporteController::class, 'kardexPdf'])
                ->middleware('permiso:stock_kardex,L')
                ->name('reportes.kardex_pdf');
        });

    Route::prefix('rrhh')
        ->name('rrhh.')
        ->middleware('rol:ADMIN,RRHH_JEFE,RRHH_ASISTENTE,LOGISTICA_JEFE,FINANZAS,GERENTE')
        ->group(function () {
            Route::get('/', [RrhhResumenController::class, 'index'])->name('index');

            Route::get('trabajadores', [TrabajadorController::class, 'index'])
                ->middleware('permiso:trabajadores,L')
                ->name('trabajadores.index');
            Route::post('trabajadores', [TrabajadorController::class, 'store'])
                ->middleware('permiso:trabajadores,C')
                ->name('trabajadores.store');
            Route::get('trabajadores/{trabajador}', [TrabajadorController::class, 'show'])
                ->whereNumber('trabajador')
                ->middleware('permiso:trabajadores,L')
                ->name('trabajadores.show');
            Route::get('trabajadores/{trabajador}/foto', [TrabajadorController::class, 'foto'])
                ->whereNumber('trabajador')
                ->middleware('permiso:trabajadores,L')
                ->name('trabajadores.foto');
            Route::put('trabajadores/{trabajador}', [TrabajadorController::class, 'update'])
                ->whereNumber('trabajador')
                ->middleware('permiso:trabajadores,E')
                ->name('trabajadores.update');
            Route::delete('trabajadores/{trabajador}', [TrabajadorController::class, 'destroy'])
                ->whereNumber('trabajador')
                ->middleware('permiso:trabajadores,E')
                ->name('trabajadores.destroy');
            Route::post('trabajadores/{trabajador}/restaurar', [TrabajadorController::class, 'restore'])
                ->whereNumber('trabajador')
                ->middleware('permiso:trabajadores,E')
                ->name('trabajadores.restore');

            Route::get('examenes', [ExamenOcupacionalController::class, 'index'])
                ->middleware('permiso:examenes,L')
                ->name('examenes.index');
            Route::post('examenes', [ExamenOcupacionalController::class, 'store'])
                ->middleware('permiso:examenes,C')
                ->name('examenes.store');
            Route::put('examenes/{examen}', [ExamenOcupacionalController::class, 'update'])
                ->whereNumber('examen')
                ->middleware('permiso:examenes,E')
                ->name('examenes.update');
            Route::delete('examenes/{examen}', [ExamenOcupacionalController::class, 'destroy'])
                ->whereNumber('examen')
                ->middleware('permiso:examenes,E')
                ->name('examenes.destroy');
            Route::post('examenes/{examen}/restaurar', [ExamenOcupacionalController::class, 'restore'])
                ->whereNumber('examen')
                ->middleware('permiso:examenes,E')
                ->name('examenes.restore');
            Route::post('examenes/{examen}/adjuntos', [ExamenOcupacionalController::class, 'subirAdjunto'])
                ->whereNumber('examen')
                ->middleware('permiso:examenes,C')
                ->name('examenes.adjuntos.store');
            Route::get('examenes/adjuntos/{adjunto}/descargar', [ExamenOcupacionalController::class, 'descargarAdjunto'])
                ->whereNumber('adjunto')
                ->middleware('rol:ADMIN,RRHH_JEFE,RRHH_ASISTENTE')
                ->name('examenes.adjuntos.download');
            Route::delete('examenes/adjuntos/{adjunto}', [ExamenOcupacionalController::class, 'eliminarAdjunto'])
                ->whereNumber('adjunto')
                ->middleware('permiso:examenes,E')
                ->name('examenes.adjuntos.destroy');

            Route::get('vacaciones', [VacacionPermisoController::class, 'vacaciones'])
                ->middleware('permiso:vacaciones_permisos,L')
                ->name('vacaciones.index');
            Route::post('vacaciones', [VacacionPermisoController::class, 'guardarVacaciones'])
                ->middleware('permiso:vacaciones_permisos,C')
                ->name('vacaciones.store');
            Route::post('vacaciones/periodos', [VacacionPermisoController::class, 'guardarPeriodo'])
                ->middleware('permiso:vacaciones_permisos,A')
                ->name('vacaciones.periodos.store');
            Route::post('vacaciones/{solicitud}/resolver', [VacacionPermisoController::class, 'resolverVacaciones'])
                ->whereNumber('solicitud')
                ->middleware('permiso:vacaciones_permisos,A')
                ->name('vacaciones.resolver');
            Route::post('vacaciones/{solicitud}/cancelar', [VacacionPermisoController::class, 'cancelarVacaciones'])
                ->whereNumber('solicitud')
                ->middleware('permiso:vacaciones_permisos,E')
                ->name('vacaciones.cancelar');

            Route::get('permisos', [VacacionPermisoController::class, 'permisos'])
                ->middleware('permiso:vacaciones_permisos,L')
                ->name('permisos.index');
            Route::post('permisos', [VacacionPermisoController::class, 'guardarPermiso'])
                ->middleware('permiso:vacaciones_permisos,C')
                ->name('permisos.store');
            Route::post('permisos/{permiso}/resolver', [VacacionPermisoController::class, 'resolverPermiso'])
                ->whereNumber('permiso')
                ->middleware('permiso:vacaciones_permisos,A')
                ->name('permisos.resolver');
            Route::post('permisos/{permiso}/cancelar', [VacacionPermisoController::class, 'cancelarPermiso'])
                ->whereNumber('permiso')
                ->middleware('permiso:vacaciones_permisos,E')
                ->name('permisos.cancelar');

            Route::get('movimientos', [MovimientoPersonalController::class, 'index'])
                ->middleware('permiso:movimientos_personal,L')
                ->name('movimientos.index');
            Route::post('movimientos', [MovimientoPersonalController::class, 'store'])
                ->middleware('permiso:movimientos_personal,C')
                ->name('movimientos.store');

            Route::get('reportes', [RrhhReporteController::class, 'index'])->name('reportes.index');
            Route::get('reportes/exportar/{tipo}', [RrhhReporteController::class, 'exportar'])->name('reportes.exportar');
        });

    Route::prefix('importaciones')
        ->name('importaciones.')
        ->middleware('rol:'.implode(',', [
            RolUsuario::ADMIN->value,
            RolUsuario::RRHH_JEFE->value,
            RolUsuario::RRHH_ASISTENTE->value,
            RolUsuario::LOGISTICA_JEFE->value,
            RolUsuario::LOGISTICA_ASISTENTE->value,
            RolUsuario::RECEPCION_PATAZ->value,
        ]))
        ->group(function () {
            Route::get('/', [ImportacionController::class, 'index'])->name('index');
            Route::get('plantilla/{tipo}', [ImportacionController::class, 'descargarPlantilla'])->name('plantilla');
            Route::post('previsualizar', [ImportacionController::class, 'previsualizar'])->name('previsualizar');
            Route::post('confirmar', [ImportacionController::class, 'confirmar'])->name('confirmar');
            Route::match(['get', 'post'], 'reporte-errores', [ImportacionController::class, 'reporteErrores'])->name('reporte_errores');
        });

    Route::prefix('administracion')->name('administracion.')->group(function () {
        Route::get('/', [AdministracionController::class, 'index'])
            ->middleware('permiso:usuarios_catalogos,L')
            ->name('index');

        Route::get('usuarios', [UsuarioController::class, 'index'])
            ->middleware('permiso:usuarios_catalogos,L')
            ->name('usuarios.index');
        Route::post('usuarios', [UsuarioController::class, 'store'])
            ->middleware('permiso:usuarios_catalogos,C')
            ->name('usuarios.store');
        Route::put('usuarios/{usuario}', [UsuarioController::class, 'update'])
            ->middleware('permiso:usuarios_catalogos,E')
            ->name('usuarios.update');
        Route::put('usuarios/{usuario}/password', [UsuarioController::class, 'resetPassword'])
            ->middleware('permiso:usuarios_catalogos,E')
            ->name('usuarios.password');
        Route::delete('usuarios/{usuario}', [UsuarioController::class, 'destroy'])
            ->middleware('permiso:usuarios_catalogos,E')
            ->name('usuarios.destroy');
        Route::post('usuarios/{id}/restaurar', [UsuarioController::class, 'restore'])
            ->middleware('permiso:usuarios_catalogos,E')
            ->name('usuarios.restore');

        Route::prefix('catalogos/{tipo}')
            ->whereIn('tipo', ['sedes', 'areas', 'cargos', 'unidades-medida'])
            ->group(function () {
                Route::get('/', [CatalogoController::class, 'index'])
                    ->middleware('permiso:usuarios_catalogos,L')
                    ->name('catalogos.index');
                Route::post('/', [CatalogoController::class, 'store'])
                    ->middleware('permiso:usuarios_catalogos,C')
                    ->name('catalogos.store');
                Route::put('/{id}', [CatalogoController::class, 'update'])
                    ->middleware('permiso:usuarios_catalogos,E')
                    ->name('catalogos.update');
                Route::delete('/{id}', [CatalogoController::class, 'destroy'])
                    ->middleware('permiso:usuarios_catalogos,E')
                    ->name('catalogos.destroy');
                Route::post('/{id}/restaurar', [CatalogoController::class, 'restore'])
                    ->middleware('permiso:usuarios_catalogos,E')
                    ->name('catalogos.restore');
            });

        Route::get('auditoria', [AuditoriaController::class, 'index'])
            ->middleware('permiso:usuarios_catalogos,L')
            ->name('auditoria.index');
    });

    Route::prefix('notificaciones')->name('notificaciones.')->group(function () {
        Route::get('/', [NotificacionController::class, 'index'])->name('index');
        Route::get('no-leidas', [NotificacionController::class, 'noLeidas'])->name('no_leidas');
        Route::post('{id}/leer', [NotificacionController::class, 'marcarLeida'])->name('leer');
        Route::post('leer-todas', [NotificacionController::class, 'marcarTodasLeidas'])->name('leer_todas');
    });
});

require __DIR__.'/settings.php';
require __DIR__.'/auth.php';
