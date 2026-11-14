# 04 – Modelo de datos

Todos heredan `ModeloBase` salvo indicación. PK = `id` autoincremental. FK `on_delete=PROTECT` por defecto.

## core
- **Usuario** (AbstractUser): `rol` (choices), `sede` FK→Sede (null), `telefono`.
- **Sede**: `nombre`, `tipo` (ADMINISTRATIVA, OPERATIVA), `ubicacion`. Semilla: Trujillo, Pataz.
- **Area**: `nombre`, `sede` FK. (RR.HH., Logística, Finanzas, Gerencia, Operaciones)
- **Cargo**: `nombre`.
- **UnidadMedida**: `nombre`, `abreviatura`.
- **Adjunto**: `archivo`, `descripcion`, GenericForeignKey (`content_type`, `object_id`).
- **ImportacionExcel**: `tipo`, `archivo`, `estado`, `resumen_errores` (JSON), `filas_ok`, `filas_error`.

## rrhh
- **Trabajador**: `dni` (único, 8 dígitos), `nombres`, `apellidos`, `fecha_nacimiento`, `sexo`, `telefono`, `email`, `direccion`, `contacto_emergencia_nombre`, `contacto_emergencia_telefono`, `cargo` FK, `area` FK, `sede_base` FK, `fecha_ingreso`, `fecha_cese` (null), `estado` (ACTIVO, CESADO, SUSPENDIDO), `ubicacion_actual` (TRUJILLO, PATAZ, EN_TRANSITO), `esquema_rotacion` (texto, ej. "21x7", null) `[SUPUESTO]`, `foto` (null).
- **ExamenOcupacional**: `trabajador` FK, `tipo` (INGRESO, PERIODICO, RETIRO, OTRO), `fecha_examen`, `fecha_vencimiento`, `clinica`, `resultado` (APTO, APTO_CON_RESTRICCIONES, NO_APTO), `observaciones`, adjuntos.
  - Propiedad `vigente` = `fecha_vencimiento >= hoy`.
- **SolicitudVacaciones**: `trabajador` FK, `fecha_inicio`, `fecha_fin`, `dias`, `estado` (PENDIENTE, APROBADA, RECHAZADA, CANCELADA, TOMADA), `solicitado_por`, `aprobado_por` (null), `fecha_resolucion`, `comentario`.
- **PeriodoVacacional**: `trabajador` FK, `anio`, `dias_ganados` (default 30), `dias_tomados` (calculado). Unique (`trabajador`,`anio`).
- **Permiso**: `trabajador` FK, `tipo` (SALUD, PERSONAL, FAMILIAR, CAPACITACION, OTRO), `con_goce` bool, `fecha_inicio`, `fecha_fin`, `motivo`, `estado` (igual que vacaciones), `aprobado_por`.
- **MovimientoPersonal**: `trabajador` FK, `tipo` (SALIDA_A_PATAZ, RETORNO_A_TRUJILLO, OTRO), `fecha_hora`, `origen` FK Sede, `destino` FK Sede, `transporte`, `observaciones`, `registrado_por`.
  - Regla: solo se permite SALIDA_A_PATAZ si `aptitud_viaje` es True.

## logistica
- **Categoria**: `nombre`.
- **Material**: `codigo` (único), `nombre`, `descripcion`, `categoria` FK, `unidad` FK, `stock_minimo`, `precio_referencial`.
- **Proveedor**: `ruc` (único, 11), `razon_social`, `contacto`, `telefono`, `email`, `direccion`.
- **Almacen**: `nombre`, `sede` FK. Semilla: Almacén Trujillo, Almacén Pataz.
- **StockAlmacen**: `almacen` FK, `material` FK, `cantidad` Decimal. Unique (`almacen`,`material`).
- **MovimientoInventario** (kardex): `almacen` FK, `material` FK, `tipo` (ENTRADA_COMPRA, SALIDA_ENVIO, ENTRADA_ENVIO, CONSUMO, AJUSTE), `cantidad` (signo según tipo), `saldo_resultante`, `referencia_tipo`, `referencia_id`, `fecha_hora`, `usuario`.
- **Requerimiento**: `codigo` (REQ-AAAA-NNNN), `solicitante` FK Usuario, `area` FK, `sede_destino` FK, `fecha`, `prioridad` (BAJA, MEDIA, ALTA, URGENTE), `estado` (BORRADOR, ENVIADO, APROBADO, RECHAZADO, ATENDIDO_PARCIAL, ATENDIDO), `justificacion`, `aprobado_por`, `fecha_resolucion`.
- **RequerimientoDetalle**: `requerimiento` FK, `material` FK, `cantidad_solicitada`, `cantidad_atendida`.
- **OrdenCompra** `[S]`: `codigo`, `proveedor` FK, `requerimiento` FK (null), `fecha`, `estado` (EMITIDA, RECIBIDA_PARCIAL, RECIBIDA, ANULADA), `total`.
- **OrdenCompraDetalle**: `orden` FK, `material` FK, `cantidad`, `precio_unitario`, `cantidad_recibida`.
- **Envio**: `codigo` (ENV-AAAA-NNNN), `guia_remision`, `requerimiento` FK (null), `almacen_origen` FK, `almacen_destino` FK, `fecha_salida`, `fecha_estimada_llegada`, `fecha_recepcion` (null), `vehiculo_placa`, `conductor`, `estado` (PREPARADO, EN_TRANSITO, RECIBIDO, RECIBIDO_CON_OBSERVACIONES, ANULADO), `observaciones`, `recibido_por` (null).
- **EnvioDetalle**: `envio` FK, `material` FK, `cantidad_enviada`, `cantidad_recibida` (null), `observacion`.

## finanzas
- **CentroCosto**: `codigo`, `nombre`, `area` FK (null).
- **CategoriaGasto**: `nombre`, `tipo` (EGRESO, INGRESO).
- **MovimientoFinanciero**: `tipo` (INGRESO, EGRESO), `fecha`, `monto` Decimal(14,2), `moneda` (PEN), `categoria` FK, `centro_costo` FK, `proveedor` FK (null), `concepto`, `comprobante_tipo` (FACTURA, BOLETA, RECIBO, OTRO), `comprobante_numero`, `estado_pago` (PENDIENTE, PAGADO), `fecha_vencimiento` (null), `fecha_pago` (null), `envio` FK (null), `orden_compra` FK (null), `trabajador` FK (null), adjuntos.
- **Presupuesto**: `centro_costo` FK, `anio`, `mes`, `monto_presupuestado`. Unique (`centro_costo`,`anio`,`mes`).

## Relaciones clave
- Trabajador 1–N ExamenOcupacional / Vacaciones / Permiso / MovimientoPersonal.
- Requerimiento 1–N Detalle; Requerimiento 1–N Envio; Envio 1–N EnvioDetalle.
- Envio/OrdenCompra/Trabajador ← MovimientoFinanciero (vínculos opcionales).

## Reglas de integridad
- `Trabajador.aptitud_viaje` (propiedad): True si existe examen con `resultado=APTO`, vigente, y estado ACTIVO.
- Stock nunca negativo (validar y bloquear con `select_for_update`).
- No solapar vacaciones/permisos aprobados del mismo trabajador.
- `dias` vacaciones ≤ saldo del período.
- Índices: `Trabajador.dni`, `Material.codigo`, `Envio.estado`, `ExamenOcupacional.fecha_vencimiento`, `MovimientoFinanciero.fecha`.
