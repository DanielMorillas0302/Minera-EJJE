# Sistema Integrado de Minería

Sistema web modular de gestión integral (**RR.HH.**, **Logística**, **Finanzas**, **Gerencia**, **Core / Importación Excel** y **Reportes**) para operación minera entre oficinas administrativas en **Trujillo** y operación/campamentos en **Pataz**.

## Stack Tecnológico

- **Backend:** PHP (`^8.2`), Laravel 12 (`laravel/framework`), Inertia.js Laravel (`inertiajs/inertia-laravel`), Ziggy (`tightenco/ziggy`).
- **Frontend:** React 19 + TypeScript, Inertia React (`@inertiajs/react`), Tailwind CSS v4, componentes Radix UI / Lucide Icons, `chart.js` + `react-chartjs-2`, `sonner`, `date-fns`.
- **Roles y Auditoría:** `spatie/laravel-permission`, `spatie/laravel-activitylog`.
- **Reportes e Importación:** `maatwebsite/excel` (Excel/CSV), `barryvdh/laravel-dompdf` (PDF).
- **Base de datos y despliegue:** **MySQL** (alojada en **Hostinger**). Pruebas automáticas configuradas con SQLite en memoria (`phpunit.xml`).

> Los cambios respecto a la especificación inicial (Python/Django/PostgreSQL/Render) están documentados en [`DECISIONES.md`](DECISIONES.md).

## Documentación del Proyecto

- [`AGENTS.md`](AGENTS.md) — Reglas de trabajo y definición de hecho.
- [`DECISIONES.md`](DECISIONES.md) — Registro de decisiones técnicas y supuestos.
- [`docs/manual-usuario.md`](docs/manual-usuario.md) — Manual de Usuario oficial del Sistema Integrado de Gestión Minera.
- [`docs/especificacion/`](docs/especificacion/) — Documentos funcionales y plan por fases (`01-contexto-y-alcance.md` a `10-plan-de-desarrollo.md`).
- [`render.yaml`](render.yaml) — Especificación de despliegue en la nube (Render / Docker).
- [`requirements.txt`](requirements.txt) — Referencia de entorno de ejecución y herramientas auxiliares según `AGENTS.md`.

## Configuración Inicial

1. Verifica que `.env` tenga las credenciales MySQL de tu base de datos en **Hostinger** (o tu instancia MySQL local):

```env
DB_CONNECTION=mysql
DB_HOST=tu_host_mysql
DB_PORT=3306
DB_DATABASE=tu_base_de_datos
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_password
```

2. Ejecuta las migraciones cuando la base de datos esté conectada:

```bash
php artisan migrate
```

3. Inicia el entorno de desarrollo (servidor Laravel + cola + logs + Vite):

```bash
composer dev
```

O por separado:

```bash
php artisan serve
npm run dev
```

## Pruebas y Calidad

```bash
php artisan test
npm run build
npm run lint
```

## Estado Actual

- **Fase 0 – Base del proyecto (completada):**
  - `ModeloBase` (`app/Models/ModeloBase.php`) con auditoría (`spatie/laravel-activitylog`), `creado_por`, `actualizado_por`, `creado_en`, `actualizado_en`, `eliminado_en`, `activo` y borrado lógico obligatorio (prohíbe borrado físico).
  - Macro de migración `$table->camposBase()` y `Schema::defaultStringLength(191)` para compatibilidad MySQL/Hostinger.
  - Usuario personalizado (`App\Models\User`) con `RolUsuario` (8 roles de `06-roles-y-permisos.md`), `sede_id`, `telefono`, `activo` y middleware `rol` (`VerificarRol`).
  - Plantilla base en español (sidebar filtrable por rol, barra superior con usuario/rol/sede/cerrar sesión, breadcrumbs, toasts y páginas de error 403/404/500).
  - Base de datos MySQL en Hostinger (`193.203.175.218:3306`, `u588773342_mineria`) conectada vía `.env`.
- **Fase 1 – Core (completada):**
  - Modelos y migración de catálogos transversales (`Sede`, `Area`, `Cargo`, `UnidadMedida`, `Adjunto`) y clave foránea `users.sede_id -> sedes.id`, con borrado lógico y restauración.
  - Matriz granular de permisos (`MatrizPermisos`, acciones `C/L/E/A` por rol) y middleware `permiso` (`VerificarPermiso`), compartida por Inertia junto a `sede_nombre`.
  - Módulo `/administracion` (solo `ADMIN`): CRUD de usuarios con rol/sede/restablecimiento de contraseña/desactivación lógica, CRUD unificado de catálogos y visor de auditoría (`activity_log`).
  - Comando idempotente `php artisan cargar_semilla` (`CoreSeeder`: 8 roles Spatie, 2 sedes, 5 áreas, 8 cargos, 7 unidades de medida y 8 usuarios demo cuando `APP_DEBUG=true`).
  - Migraciones aplicadas (`2026_11_11_200500_create_core_tables`) y semilla verificada en Hostinger MySQL; pruebas automatizadas en verde.
- **Fase 2 – RR.HH. (MVP) (completada):**
  - Migración `2026_11_11_210500_create_rrhh_tables` (`trabajadores`, `examenes_ocupacionales`, `periodos_vacacionales`, `solicitudes_vacaciones`, `permisos`, `movimientos_personal`) aplicada en Hostinger MySQL (`[3] Ran`).
  - Modelos en `App\Models\Rrhh` heredando `ModeloBase`, evaluación de aptitud médica (`AptitudViajeService`), control transaccional de traslados Trujillo ↔ Pataz con bloqueo si no apto (`MovimientoPersonalService`) y gestión de vacaciones/permisos sin solapamiento, con control de saldo y prohibición de autoaprobación (`VacacionesPermisosService`).
  - Interfaz `/rrhh` completa por roles (Resumen y alertas ≤ 30 días, Trabajadores con ficha/foto y 5 pestañas, Exámenes con adjuntos confidenciales, Vacaciones/Períodos, Permisos, Movimientos y Reportes exportables a XLSX).
  - Semilla `RrhhSeeder` integrada en `php artisan cargar_semilla`: 30 trabajadores, 29 exámenes (vigentes, por vencer, vencidos, restricciones, no apto), 30 períodos vacacionales y registros de muestra idempotentes en `APP_DEBUG=true`.
- **Fase 3 – Logística (MVP) (completada):**
  - Migración `2026_11_12_090000_create_logistica_tables` (`categorias_material`, `materiales`, `proveedores`, `almacenes`, `stock_almacenes`, `movimientos_inventario`, `correlativos_documentos`, `requerimientos`, `requerimiento_detalles`, `envios`, `envio_detalles`).
  - Modelos en `App\Models\Logistica` heredando `ModeloBase`, servicios transaccionales con bloqueo pesimista `lockForUpdate()` (`CorrelativoService`, `InventarioService`, `RequerimientoService`, `EnvioService`, `AlmacenScopeService`): control estricto de stock no negativo, correlativos anuales atómicos `REQ-AAAA-NNNN`/`ENV-AAAA-NNNN`, prohibición de autoaprobación, reversión de kardex al anular envíos en tránsito y aislamiento de `RECEPCION_PATAZ` a su almacén y requerimientos propios.
  - Interfaz `/logistica` completa (Resumen/alertas stock mínimo y envíos retrasados, Catálogo de Materiales/Categorías/Almacenes, Proveedores RUC 11 dígitos, Stock/Kardex/Consumos, Requerimientos, Envíos y Recepción con diferencias + adjuntos + plantilla Excel, Reportes XLSX y Kardex PDF vía DomPDF).
  - Semilla `LogisticaSeeder` integrada en `php artisan cargar_semilla`: 7 categorías, 2 almacenes (Trujillo/Pataz), 5 proveedores, 40 materiales con stock inicial, 5 requerimientos y 3 envíos idempotentes en `APP_DEBUG=true`.
- **Fase 4 – Importador y plantillas Excel (completada):**
  - Servicio genérico `App\Services\Importacion\ImportadorService` con previsualización, validación por fila, reporte de errores descargable en Excel y confirmación transaccional.
  - Soporte completo para los 5 tipos especificados en `05-modulos-y-flujos.md`: `trabajadores`, `materiales`, `proveedores`, `stock_inicial` y `recepcion_envio`.
  - Plantillas oficiales descargables en `.xlsx` para cada tipo con fila de ejemplo y encabezados oficiales.
  - Control granular de permisos de importación (`importar_rrhh`, `importar_logistica`, `importar_recepcion`), permitiendo a `RECEPCION_PATAZ` operar exclusivamente la recepción de su almacén y restringiendo a usuarios no autorizados (`FINANZAS`/`GERENTE` con 403).
  - Interfaz en `/importaciones` (`resources/js/pages/core/importaciones.tsx`) con previsualización instantánea de filas válidas, contador visual de discrepancias, tabla de errores y enlace directo a la descarga del reporte de rechazos.
  - Pruebas unitarias (`ImportadorServiceTest`) y funcionales (`Fase4ImportadorTest`) con 100% de aserciones pasando.
- **Fase 5 – Finanzas (MVP) (completada):**
  - Migración `2026_11_12_100000_create_finanzas_tables` (`centros_costo`, `categorias_gasto`, `presupuestos`, `movimientos_financieros`) aplicada exitosamente en Hostinger MySQL (`[5] Ran`).
  - Modelos en `App\Models\Finanzas` (`CentroCosto`, `CategoriaGasto`, `Presupuesto`, `MovimientoFinanciero`) heredando de `ModeloBase` con borrado lógico y auditoría automática en `AuditoriaController`.
  - Servicio de negocio `App\Services\Finanzas\FinanzasService`: cálculo de KPIs mensuales (ingresos, egresos, saldo neto, presupuesto global, % de ejecución), matriz mensual y anual de control presupuestal por centro de costo con alertas configuradas (`<80%` NORMAL, `80-90%` ADVERTENCIA, `>90%` ALERTA, `>100%` SOBREGIRADO), cuentas por pagar con semáforo de vencimiento, soporte para comprobantes adjuntos vía `Adjunto` polimórfico y reportes exportables.
  - Interfaz web `/finanzas` completa en React + Inertia:
    - `/finanzas`: Dashboard con KPIs del mes, barra de avance presupuestal y accesos directos.
    - `/finanzas/movimientos`: CRUD integral de ingresos/egresos con filtros avanzados, modal con vínculos a proveedores, envíos y trabajadores, subida y descarga de comprobantes digitales (PDF/JPG/PNG) y marcación de pagos.
    - `/finanzas/cuentas-por-pagar`: Cartera de pasivos pendientes clasificada por semáforo de vencimiento con acción directa de liquidación.
    - `/finanzas/presupuestos`: Matriz comparativa mensual y matriz anual de 12 meses por centro de costo, con modal individual y ajuste masivo por lote.
    - `/finanzas/catalogos`: Gestión de centros de costo y categorías de gasto con toggle de activación/desactivación.
    - `/finanzas/reportes`: Reportes analíticos con filtros de fechas exportables a Excel `.xlsx` y reporte Presupuesto vs Real descargable en PDF vía `barryvdh/laravel-dompdf`.
  - Matriz de permisos rigurosa: acceso exclusivo a `ADMIN` y `FINANZAS` (C/L/E) y `GERENTE` (L). Roles no financieros reciben 403 Forbidden.
  - Semilla `FinanzasSeeder` integrada en `php artisan cargar_semilla`: 5 centros de costo operativos, 9 categorías de gasto/ingreso, 60 presupuestos mensuales del año y 20 movimientos financieros coherentes con comprobantes, vencimientos y vínculos operativos (verificada doblemente en Hostinger MySQL).
  - Pruebas automatizadas en verde: `FinanzasServiciosTest` y `Fase5FinanzasTest` (14 pruebas nuevas, 86 pruebas y 609 aserciones totales en el proyecto).
- **Fase 6 – Gerencia y Reportes (MVP) (completada):**
  - Servicio de negocio `App\Services\Gerencia\GerenciaService`:
    - Métodos para consolidación ejecutiva de KPIs multimódulo (`kpisGenerales(?sedeId, ?anio, ?mes)`): personal activo por sede (Trujillo, Pataz, tránsito), índice de aptitud médica para Pataz, alertas médicas por vencer ($\le 30$ días) y vencidas, envíos en tránsito y retrasados, materiales en stock crítico bajo mínimo, requerimientos pendientes, ingresos, egresos, saldo neto y porcentaje de ejecución presupuestal.
    - Series estadísticas dinámicas (`datosGraficos(anio, mes, ?sedeId)`): evolución mensual de 12 meses de Ingresos vs Egresos compatible con MySQL y SQLite, comparativa de Presupuesto vs Real por Centro de Costo, dona de estados de Envíos Trujillo-Pataz, barras de dotación por Área y dona de Aptitud médica.
    - Generación de estructura ejecutiva consolidada (`reporteEjecutivo(anio, mes, ?sedeId)`).
  - Controlador gerencial `GerenciaDashboardController`:
    - `GET /gerencia`: Dashboard interactivo con filtros dinámicos por Sede (Consolidado o sedes específicas), Año y Mes.
    - `GET /gerencia/reporte-pdf`: Generación y descarga directa en PDF (`resources/views/pdf/gerencial.blade.php`) del Informe Ejecutivo Gerencial membretado, con fecha/hora de emisión, balance financiero, estado de fuerza laboral, cadena logística, detalle de stock crítico y firmas institucionales.
  - Centro Unificado de Reportes `ReportesHubController`:
    - `GET /reportes`: Catálogo centralizado categorizado por módulos (Gerencia, Finanzas, RR.HH., Logística) filtrado dinámicamente según la matriz de permisos de lectura por recurso (`dashboard_gerencial`, `presupuesto`, `movimientos_financieros`, `trabajadores`, `examenes`, `vacaciones_permisos`, `movimientos_personal`, `stock_kardex`, `envios`, `recepciones_consumos`).
    - Vista interactiva en React (`resources/js/pages/reportes/index.tsx`) con buscador de texto en tiempo real, filtro rápido por formato (`Todos`, `Solo Excel XLSX`, `Solo PDF`), badges distintivos, botón "Ver en pantalla" y botón directo "Descargar".
  - Componentes visuales frontend (`resources/js/layouts/gerencia/gerencia-layout.tsx` y `resources/js/pages/gerencia/index.tsx`):
    - Integración de `Chart.js` y `react-chartjs-2` con paleta institucional (`#0F4C5C`, `#E36414`, `#FB8B24`, `#10B981`, `#E11D48`).
    - Cinco gráficos estadísticos interactivos (Línea, Barras comparativas, Dona de envíos, Barras horizontales por área y Dona de aptitud médica).
    - Tarjetas de KPIs con desglose por sede e indicadores visuales de semáforo.
  - Matriz de permisos rigurosa:
    - `/gerencia` restringido a `ADMIN`, `GERENTE`, `RRHH_JEFE`, `LOGISTICA_JEFE`, `FINANZAS`. Los demás roles reciben 403 Forbidden.
    - `/reportes` accesible por roles con acceso a reportes, adaptando el catálogo al nivel de autorización de cada usuario.
  - Pruebas y verificación de calidad:
    - Pruebas unitarias (`GerenciaServiciosTest`) y funcionales (`Fase6GerenciaTest`) pasando al 100%.
    - Suite completa del proyecto en verde: **97 tests pasando con 763 aserciones**.
    - Formato `pint` conforme, `npx tsc --noEmit` y `npm run lint` sin advertencias, `npm run build` exitoso.
- **Fase 7 – Órdenes de compra y mejoras (completada):**
  - Migración `2026_11_12_110000_create_fase7_tables` aplicada exitosamente en Hostinger MySQL (`ordenes_compra`, `orden_compra_detalles`, `notificaciones_sistema`, y columnas de bloqueo `intentos_fallidos` / `bloqueado_hasta` en `users`).
  - Modelos en `App\Models\Logistica` (`OrdenCompra`, `OrdenCompraDetalle`) y `App\Models\Core` (`NotificacionSistema`) heredando de `ModeloBase` con borrado lógico y trazabilidad.
  - Servicio transaccional `App\Services\Logistica\OrdenCompraService`:
    - Generación de correlativos anuales atómicos secuenciales (`OC-2026-0001`, `OC-2026-0002`).
    - Flujo completo de estados: `BORRADOR` -> `EMITIDA` -> `RECIBIDA_PARCIAL` -> `RECIBIDA` / `ANULADA`.
    - Recepción de ítems en almacén con bloqueo pesimista `lockForUpdate()`, actualización automática del kardex (`ENTRADA_COMPRA`) a través de `InventarioService` y generación opcional de cuentas por pagar en `movimientos_financieros` (`tipo = EGRESO`, `estado_pago = PENDIENTE`, `orden_compra_id = $orden->id`).
    - Anulación segura protegida (bloquea la anulación si la orden ya tiene recepciones de stock registradas).
    - Descarga oficial de la Orden de Compra membretada en PDF vía DomPDF (`/logistica/ordenes-compra/{orden}/pdf`).
  - Centro de Notificaciones y Alertas del Sistema (`App\Services\Core\NotificacionService` y `/notificaciones`):
    - Detección reactiva y persistencia de alertas operativas: exámenes médicos ocupacionales próximos a vencer o vencidos, materiales bajo stock mínimo de seguridad, envíos con retraso en la ruta Trujillo-Pataz, requerimientos urgentes pendientes y emisiones de órdenes de compra.
    - Soporte para consulta de no leídas, filtros por módulo/severidad (`INFO`, `ADVERTENCIA`, `URGENTE`) y marcación individual o masiva de lectura.
    - Enlace a Notificaciones integrado en el sidebar para todos los usuarios autenticados.
  - Bloqueo de Cuenta por Intentos Fallidos (`RNF-02` / `06-roles-y-permisos.md`):
    - Detección en `LoginRequest`: tras 5 intentos fallidos consecutivos de inicio de sesión con contraseña incorrecta, la cuenta se bloquea automáticamente por 15 minutos.
    - Registro de auditoría inmediato en `activity_log` con descripción y dirección IP del atacante/usuario.
    - Reinicio automático del contador tras autenticación exitosa.
  - Pruebas y calidad:
    - Pruebas unitarias (`Fase7ServiciosTest`) y funcionales (`Fase7MejorasTest`) con 100% de aserciones pasando.
    - Suite completa del proyecto en verde: **110 tests pasando con 839 aserciones**.
    - Formato `pint` conforme, `npx tsc --noEmit` y `npm run lint` sin advertencias, `npm run build` exitoso.
- **Fase 8 – Calidad y despliegue (completada - PROYECTO CONCLUIDO AL 100%):**
  - Consolidación del comando maestro idempotente `php artisan cargar_semilla`:
    - Integra `CoreSeeder`, `RrhhSeeder`, `LogisticaSeeder`, `FinanzasSeeder` y `Fase7Seeder`.
    - Probado y verificado en la base de datos MySQL remota de producción en Hostinger (`u588773342_mineria`).
  - Manual de Usuario exhaustivo en [`docs/manual-usuario.md`](docs/manual-usuario.md):
    - Cobertura integral de flujos operativos en Trujillo y Pataz, control de acceso por roles, credenciales demo, bloqueo por fuerza bruta, RR.HH. (padrón, EMOs, aptitud de viaje, traslados, vacaciones), Logística (materiales, requerimientos, compras, despachos con actas de discrepancia, kardex multialmacén), Finanzas (presupuesto vs ejecutado con semáforo, liquidación de facturas), Gerencia (dashboard, gráficos, informe ejecutivo PDF), Centro de Notificaciones, Auditoría y Carga Masiva vía Excel.
  - Especificación de despliegue y compatibilidad:
    - [`render.yaml`](render.yaml) y [`Dockerfile`](Dockerfile) multi-etapa optimizados para despliegue en Render y contenedores Docker en la nube.
    - [`requirements.txt`](requirements.txt) para trazabilidad de dependencias de auditoría y pruebas del proyecto según `AGENTS.md`.
  - Verificación final de calidad:
    - Suite automatizada: **110 tests pasando con 839 aserciones**.
    - Análisis estático y linter: `npx tsc --noEmit` (0 errores) y `npm run lint` (0 advertencias).
    - Compilación de producción: `npm run build` exitoso.
    - Formateo de código: Laravel Pint verificado.
