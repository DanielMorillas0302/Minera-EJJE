# Registro de Decisiones Técnicas (`DECISIONES.md`)

Proyecto: **Sistema Integrado de Minería** (Trujillo / Pataz)

---

## 2026-11-11 – Cambio de stack tecnológico a Laravel 12 + Inertia.js + React + Tailwind CSS

- **Contexto:** La especificación original (`AGENTS.md`, `03-arquitectura-y-stack.md`, `07-ui-ux.md`) planteaba Python 3.12 + Django 5 + HTMX + Bootstrap 5.
- **Decisión:** Implementar el proyecto con **Laravel 12 (PHP 8.2/8.3) + Inertia.js v2 + React 19 (TypeScript) + Tailwind CSS** en arquitectura monolítica modular, bajo el nombre oficial **Sistema Integrado de Minería**.
- **Motivo:** Indicación explícita del usuario de construir el sistema en Laravel con React, preservando la simplicidad operativa de un monolito con autenticación de sesión, CSRF y control de roles integrados.
- **Impacto:** Se mapean las herramientas equivalentes manteniendo intactos los requerimientos funcionales, reglas de negocio, matriz de roles y plan por fases:
  - **Roles y permisos:** `spatie/laravel-permission` (en lugar de grupos de Django).
  - **Auditoría e historial:** `spatie/laravel-activitylog` + modelo/trait base con `SoftDeletes`, `creado_en`, `actualizado_en`, `creado_por` y `activo`.
  - **Excel / CSV:** `maatwebsite/excel` (en lugar de `openpyxl`).
  - **PDF:** `barryvdh/laravel-dompdf` (en lugar de WeasyPrint/xhtml2pdf).
  - **UI y gráficos:** Tailwind CSS + componentes React (Radix/shadcn) respetando la paleta (`#0F4C5C`, `#E09F3E`, `#2E7D32`, `#C62828`, `#F5F7F8`) y tipografía `Inter` de `07-ui-ux.md`, más `chart.js` + `react-chartjs-2` y `sonner` para notificaciones toast.
  - **Pruebas y calidad:** `PHPUnit`, `Laravel Pint`, `ESLint` y `Prettier` (en lugar de `pytest` y `ruff`).

---

## 2026-11-11 – Cambio de base de datos y hosting a MySQL + Hostinger

- **Contexto:** La especificación original (`03-arquitectura-y-stack.md`, `08-despliegue-render.md`) indicaba PostgreSQL 16 y despliegue en Render.
- **Decisión:** Utilizar **MySQL** como base de datos principal y **Hostinger** como entorno de alojamiento/producción, manteniendo SQLite en memoria (`:memory:`) únicamente para la ejecución rápida de pruebas automatizadas en `phpunit.xml`.
- **Motivo:** El usuario ya dispone de base de datos MySQL en Hostinger.
- **Impacto:** `.env` y `.env.example` se configuran con `DB_CONNECTION=mysql` (puerto `3306`), `APP_TIMEZONE=America/Lima` y `APP_LOCALE=es`. Se omite `render.yaml` como requisito activo y las migraciones usarán tipos y restricciones estándar compatibles con MySQL (InnoDB / `utf8mb4`).

---

## 2026-11-11 – Organización de la especificación en `docs/especificacion/`

- **Contexto:** La raíz del repositorio contenía los documentos `01-*.md` a `10-*.md`, `AGENTS.md` y `README.md` de especificación, además de copias duplicadas `(1)`.
- **Decisión:** Mover `01`–`10` y un respaldo del `README.md` original (`README-especificacion.md`) a `docs/especificacion/`, eliminar los duplicados idénticos `(1)` y conservar `AGENTS.md`, `DECISIONES.md` y el `README.md` del proyecto en la raíz.
- **Motivo:** Instalar Laravel + React en la raíz del workspace manteniendo limpia la estructura del proyecto sin perder ningún documento de referencia.
- **Impacto:** Toda la documentación funcional y técnica original permanece intacta en `docs/especificacion/`.

---

## 2026-11-11 – Supuestos de reglas de negocio de Fase 2 (RR.HH.)

- **Contexto:** La especificación (`04-modelo-de-datos.md`, `05-modulos-y-flujos.md`, `06-roles-y-permisos.md`) incluye `[SUPUESTO]` en `esquema_rotacion` y reglas de aptitud médica, cálculo de días de vacaciones y visibilidad médica por rol.
- **Decisión:**
  1. `esquema_rotacion` se almacena como texto libre corto (ej. `"21x7"`, `"14x7"`, `"L-V"`) y es opcional para personal administrativo de Trujillo.
  2. `aptitud_viaje` (`Apto para Pataz`) exige **exclusivamente** trabajador `activo = true`, `estado = ACTIVO` y al menos un `ExamenOcupacional` activo con `resultado = APTO` y `fecha_vencimiento >= hoy`. Un resultado `APTO_CON_RESTRICCIONES` o `NO_APTO` bloquea el viaje automático `SALIDA_A_PATAZ` indicando el motivo exacto.
  3. Los días de vacaciones (`dias`) se calculan en días calendario inclusive (`fecha_fin - fecha_inicio + 1`), descontando el saldo del `PeriodoVacacional` al aprobarse y pasando automáticamente a `TOMADA` cuando `fecha_fin < hoy` al consultar o ejecutar actualización.
  4. El rol `GERENTE` visualiza únicamente resúmenes de vigencia de exámenes (sin detalle clínico `resultado`/`observaciones` ni descarga de archivos médicos), mientras `FINANZAS` visualiza datos básicos de trabajadores sin acceso a exámenes, vacaciones ni permisos.
- **Motivo:** Garantizar seguridad operacional en mina (Pataz), confidencialidad de historia clínica y cumplimiento estricto de la matriz de roles.
- **Impacto:** Reflejado en `AptitudViajeService`, `MovimientoPersonalService`, `VacacionesPermisosService`, controladores y vistas de `RR.HH.`.



## 2026-11-12 – Supuestos aplicados en Fase 3 (Logística MVP)

1. **Correlativos anuales atómicos (`correlativos_documentos`):** Para generar códigos `REQ-AAAA-NNNN` y `ENV-AAAA-NNNN` sin duplicados ni reutilización ante borrados lógicos, se crea la tabla `correlativos_documentos` (`tipo`, `anio`, `ultimo_numero`) consultada con `lockForUpdate()` dentro de `DB::transaction`.
2. **Ingresos iniciales antes de Fase 7 (Compras):** Como `OrdenCompra` pertenece a Fase 7, los ingresos directos de stock previos o de ajuste se registran mediante el flujo de `InventarioController` con `TipoMovimientoInventario::ENTRADA_COMPRA` o `AJUSTE`, validando saldos no negativos bajo bloqueo pesimista (`lockForUpdate`).
3. **Alcance territorial de `RECEPCION_PATAZ`:** Siguiendo `06-roles-y-permisos.md`, un usuario con rol `RECEPCION_PATAZ` queda restringido a los almacenes cuya `sede_id` coincida con su propia `user.sede_id` (y por defecto a la sede Pataz si no tiene sede explícita) para consultar stock/kardex, registrar consumos y confirmar recepción de envíos destinados a su almacén; en requerimientos solo visualiza y edita los propios.
4. **Reversión de envíos anulados:** Al anular un envío que ya había salido (`EN_TRANSITO`), `EnvioService::anular()` revierte ítem por ítem el stock descontado al almacén de origen dejando traza explícita en `movimientos_inventario` (`TipoMovimientoInventario::AJUSTE` positivo con `documento_tipo = Envio::class` y observación de anulación); los envíos ya `RECIBIDO` o `RECIBIDO_CON_OBSERVACIONES` no se anulan para preservar la integridad del kardex en destino.

## 2026-11-12 – Supuestos aplicados en Fase 4 (Importador y plantillas Excel)

1. **Estructura del Importador Genérico:**
   - Tipos soportados según `05-modulos-y-flujos.md`: `trabajadores`, `materiales`, `proveedores`, `stock_inicial` y `recepcion_envio`.
   - Flujo en dos pasos: 1) Subida y previsualización (parseo, validación de columnas, validación de tipos, detección de duplicados internos y foráneas no encontradas), 2) Confirmación (ejecución atómica en base de datos).
   - Reporte de errores: Si existen filas inválidas, se ofrece su descarga en un archivo Excel (`reporte-errores-<tipo>.xlsx`) con las columnas originales más la columna `error` detallando el motivo de rechazo por fila.
2. **Control granular de permisos por tipo de importación:**
   - Trabajadores: requiere permiso `importar_rrhh,C` (`ADMIN`, `RRHH_JEFE`, `RRHH_ASISTENTE`).
   - Materiales, Proveedores, Stock inicial: requiere permiso `importar_logistica,C` (`ADMIN`, `LOGISTICA_JEFE`, `LOGISTICA_ASISTENTE`).
   - Recepción de envío: requiere permiso `importar_recepcion,C` (`ADMIN`, `LOGISTICA_JEFE`, `LOGISTICA_ASISTENTE`, `RECEPCION_PATAZ`). Para `RECEPCION_PATAZ`, se restringe a envíos cuyo destino coincida con la sede del usuario.
3. **Formatos flexibles de fecha y catálogo:**
   - Las fechas aceptan tanto formato ISO (`YYYY-MM-DD`) como estándar peruano (`DD/MM/YYYY`) o números de serie de Excel.
   - Las claves foráneas (cargos, áreas, sedes, categorías, unidades) se resuelven por coincidencia exacta o insensible a mayúsculas sobre su nombre o código/abreviatura.

## 2026-11-12 – Supuestos aplicados en Fase 5 (Finanzas)

1. **Gestión de Moneda y Montos:**
   - Moneda estándar del sistema: PEN (`S/`), almacenada con dos decimales (`decimal(14,2)`) para precisión en importes mineros e impuestos.
2. **Ciclo de Cuentas por Pagar:**
   - Los egresos pueden registrarse como `PAGADO` (con `fecha_pago`) o `PENDIENTE` (con `fecha_vencimiento`). Al saldarse mediante la acción de pago, se registra la fecha de pago efectiva y el estado pasa a `PAGADO`.
3. **Presupuesto vs Ejecutado:**
   - La comparación presupuestaria se calcula mensualmente por `CentroCosto` contra la suma de egresos reales (`MovimientoFinanciero` tipo `EGRESO`) registrados en dicho mes y año.
   - Alertas visuales de ejecución: verde si es inferior al 80%, advertencia/ámbar si se sitúa entre el 90% y 100%, y sobregiro (rojo) si supera el 100% del monto presupuestado.
4. **Trazabilidad y Vínculos Operativos:**
   - Cada egreso puede vincularse opcionalmente a un `Envio` (ej. gastos de flete/transporte) y/o a un `Trabajador` (ej. viáticos o reembolsos), además de admitir archivos adjuntos de comprobante (factura/boleta/recibo en PDF o imagen) a través de la relación polimórfica `Adjunto`.

## 2026-11-12 – Supuestos aplicados en Fase 6 (Gerencia y Reportes)

1. **Consolidación de Métricas Multimódulo:**
   - El servicio `GerenciaService` consolida datos en tiempo real de los módulos ya construidos: RR.HH. (personal activo por sede y aptitud médica), Logística (stock crítico, envíos en tránsito y retrasos) y Finanzas (ejecución presupuestal mensual, ingresos, egresos y cuentas por pagar).
2. **Filtros Dinámicos:**
   - El Dashboard Gerencial permite filtrar por Sede (`todas`, `Trujillo`, `Pataz`) y por Período (`anio`, `mes` o rango de fechas), recalculando reactivamente los KPIs y los gráficos estadísticos.
3. **Visualización Gráfica:**
   - Se integran gráficos interactivos con `chart.js` y `react-chartjs-2` registrados con `ChartJS.register(...)`: línea de evolución de gasto mensual, barras comparativas por centro de costo, dona de envíos por estado y barras de personal por área/sede.
4. **Reporte Ejecutivo Gerencial (PDF):**
   - Se genera un documento formal en PDF descargable (`/gerencia/reporte-pdf`) con DomPDF, que compendia los indicadores clave, balance financiero, estado de la fuerza laboral y situación de la cadena de suministro.
5. **Hub Central de Reportes (`/reportes`):**
   - Actúa como catálogo unificado para acceder y exportar en Excel (.xlsx) y PDF todos los reportes del sistema según los permisos del usuario activo.

---

## 2026-11-12 – Decisiones y supuestos aplicados en Fase 7 (Órdenes de Compra y Mejoras Should/Could)

1. **Flujo y Correlativos de Órdenes de Compra (`OC-AAAA-NNNN`):**
   - Se utiliza el servicio atómico `CorrelativoService` con tipo `'OC'` para garantizar códigos anuales atómicos secuenciales (`OC-2026-0001`, `OC-2026-0002`).
   - Estados de la orden: `BORRADOR` -> `EMITIDA` -> `RECIBIDA_PARCIAL` -> `RECIBIDA` / `ANULADA`.
   - Aprobación y emisión restringida a `ADMIN` y `LOGISTICA_JEFE`; creación y edición asistida por `LOGISTICA_ASISTENTE`. `GERENTE` y `FINANZAS` disponen de acceso de lectura.
   - La recepción (parcial o total) de la orden en el almacén de destino registra automáticamente movimientos de inventario (`ENTRADA_COMPRA`) mediante `InventarioService` bajo bloqueo pesimista `lockForUpdate()`, actualizando `cantidad_recibida` en los detalles.
   - Al recepcionar la orden, se genera opcionalmente la cuenta por pagar en `movimientos_financieros` (`tipo = EGRESO`, `estado_pago = PENDIENTE`, `orden_compra_id = $orden->id`) con la factura del proveedor y su fecha de vencimiento.
   - Se incluye plantilla de exportación PDF membretada de la orden de compra (`resources/views/pdf/orden-compra.blade.php`).

2. **Centro de Notificaciones Internas y Alertas Operativas (`RF-CORE-08`):**
   - Servicio centralizado `NotificacionService` con persistencia en `notificaciones_sistema`:
     - RR.HH.: Exámenes médicos por vencer en <= 30 días o vencidos.
     - Logística: Materiales con existencias <= stock mínimo de seguridad, envíos con retraso en la ruta Trujillo-Pataz, requerimientos urgentes pendientes.
     - Finanzas: Cuentas por pagar vencidas, centros de costo con sobregiro presupuestal (> 90% o > 100%).
     - Seguridad: Intentos de acceso fallidos reiterados.
   - Controlador y rutas en `/notificaciones` con recuento no leído, marcación individual o masiva de lectura y componente de alertas.

3. **Bloqueo de Cuenta por Intentos Fallidos (`RNF-02` / `06-roles-y-permisos.md`):**
   - Se añaden columnas `intentos_fallidos` y `bloqueado_hasta` a la tabla `users`.
   - Al acumular 5 intentos consecutivos con contraseña incorrecta para un usuario, la cuenta queda bloqueada por 15 minutos.
   - El bloqueo se audita en `activity_log` con el responsable e IP, y genera una alerta de seguridad para los administradores.
   - El inicio de sesión exitoso reinicia el contador de intentos y limpia la marca de bloqueo.

---

## 2026-11-12 – Cierre de Fase 8: Calidad, Manual de Usuario y Despliegue

1. **Integración completa de datos semilla (`php artisan cargar_semilla`):**
   - Se integró `Fase7Seeder` en `app/Console/Commands/CargarSemillaCommand.php`, consolidando la carga secuencial e idempotente de:
     - `CoreSeeder`: roles Spatie, sedes (Trujillo, Pataz), áreas, cargos, unidades de medida y usuarios demo por rol.
     - `RrhhSeeder`: trabajadores, exámenes ocupacionales, períodos vacacionales, solicitudes y movimientos intersedes.
     - `LogisticaSeeder`: categorías, almacenes, proveedores, catálogo de materiales con stock inicial, requerimientos y envíos intersedes.
     - `FinanzasSeeder`: centros de costo, categorías de gasto/ingreso, presupuestos anuales y movimientos financieros con cuentas por pagar.
     - `Fase7Seeder`: órdenes de compra (`OC-2026-0001`, `OC-2026-0002`) y notificaciones operativas del sistema.
   - Verificado y probado con éxito en la base de datos MySQL remota en Hostinger (`u588773342_mineria`).

2. **Manual de Usuario (`docs/manual-usuario.md`):**
   - Elaboración del documento oficial de uso en español para operadores de la mina en Pataz y personal administrativo en Trujillo.
   - Describe en detalle los flujos de negocio, reglas de seguridad, política de bloqueo por intentos fallidos, procedimientos de RR.HH. (aptitud de viaje, traslados, EMOs), Logística (catálogo, requerimientos, compras, despachos y actas de discrepancia), Finanzas (control presupuestal, liquidación de facturas), Gerencia (dashboard, gráficos, informe ejecutivo PDF), Notificaciones y Auditoría.

3. **Especificación de Despliegue y Compatibilidad:**
   - Creación de `render.yaml` y `Dockerfile` multi-etapa (Node 20 para Vite build + PHP 8.3 CLI con extensiones pdo_mysql, gd, zip, bcmath) para permitir el despliegue tanto en Render/Docker como en servidores tradicionales con MySQL en Hostinger.
   - Inclusión de `requirements.txt` documentando la compatibilidad de herramientas de auditoría y pruebas del proyecto según lo estipulado en `AGENTS.md`.

4. **Verificación Integral y Cierre de Entregables:**
   - La suite de pruebas automatizadas ejecuta 110 pruebas con 839 aserciones en verde.
   - Cumplimiento de estándares de código: Laravel Pint limpio, TypeScript (`tsc --noEmit`) sin errores, ESLint sin advertencias y compilación de producción con Vite (`npm run build`) exitosa.
   - Concluido el 100% de las fases del plan de desarrollo (`docs/especificacion/10-plan-de-desarrollo.md`).

---

## 2026-11-13 – Rediseño Visual Corporativo: Sistema de Diseño Dark Mode Empresarial (Zinc)

- **Contexto:** La interfaz previa empleaba un esquema mixto con fondos claros y paleta verde-azulada. El usuario solicitó unificar el sistema bajo un estándar visual idéntico a las maquetas de referencia tipo Dashboard Minero Corporativo (Dark Mode Empresarial - Estilo Zinc).
- **Decisión:** Implementar y aplicar de forma integral un sistema de diseño estricto basado en la paleta Zinc de Tailwind CSS:
  1. **Tokens de color:**
     - Fondo base (App, sidebar, layout): `bg-[#09090b]`.
     - Fondo de tarjetas (Cards), modales y contenedores de datos: `bg-[#0f0f13]`.
     - Fondo de inputs, selects y hover states: `bg-zinc-900` (`#18181b`).
     - Bordes estándar: `border-zinc-800` (`#27272a`) de 1px exacto (sin sombras `shadow-lg`). Hover en `border-zinc-700`.
     - Textos: Títulos `text-zinc-100`, cuerpo `text-zinc-300`/`text-zinc-400`, etiquetas `text-[11px]` en mayúsculas, datos numéricos con clase `tabular-nums`.
     - Colores de acento: `text-blue-500` (módulos activos y selecciones), `text-amber-500` (alertas críticas de mina/SSOMA y branding institucional).
  2. **Jerarquía tipográfica y dimensiones compactas:**
     - H1: `text-2xl font-semibold tracking-tight text-zinc-100`.
     - H3: `text-[14px] font-medium text-zinc-200`.
     - P / Tablas: `text-[13px] leading-relaxed`.
     - Sidebar de ancho fijo exacto: `w-64` (256px) con `border-r border-zinc-800`.
     - Topbar de altura compacta: `h-14` (56px) con `border-b border-zinc-800`.
  3. **Componentes globales reutilizables construidos:**
     - `PageHeader`: Cabecera estandarizada con H1, descripción y botones de acción rápida.
     - `KpiCard`: Tarjeta de métricas con indicador de alerta ámbar, icono, badge de estado y acción directa.
     - `ModuleCard`: Tarjetas de acceso a módulos operacionales con badges y descripción.
     - `StatusBadge`: Badges unificados con fondos translúcidos y bordes contrastados para estados de negocio (Apto, Vencido, En Tránsito, Aprobado, Pendiente, Activo, etc.).
     - `FilterBar`: Barra de filtros de búsqueda, selectores compactos y botones de filtrado rápido.
     - `DataTableCard`: Contenedor de tablas con encabezado oscuro, filas con efecto hover sutil y paginación integrada.
     - `ModalConfirmacion`: Modales de confirmación con diseño Zinc Dark.
  4. **Alcance de aplicación:**
     - Migración total de todas las vistas del ERP: Dashboard Resumen Ejecutivo, RR.HH. (8 páginas), Logística (9 páginas), Finanzas (6 páginas), Gerencia (1 página con gráficos Chart.js adaptados con rejillas `#27272a` y texto `#71717a`), Analítica de Datos (1 página), Notificaciones (1 página), Administración y Catálogos (5 páginas), Carga Masiva (1 página), Páginas de Error (1 página), Modales de Construcción y Autenticación (Login, Recuperación, Bienvenida).
- **Impacto:** Experiencia visual moderna, profesional y coherente con ergonomía para largas jornadas de trabajo en campamento minero y oficinas, con 100% de pruebas en verde y 0 errores de compilación TypeScript.
