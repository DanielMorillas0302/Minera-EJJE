# 02 – Requerimientos

Prioridad: **M** = Must (MVP), **S** = Should, **C** = Could.

## Transversales (CORE)
| ID | Requerimiento | P |
|----|---------------|---|
| RF-CORE-01 | Login/logout con usuario y contraseña; recuperación de contraseña por administrador. | M |
| RF-CORE-02 | Gestión de usuarios y roles (ver `06`). | M |
| RF-CORE-03 | Auditoría: registrar quién creó/modificó/aprobó cada registro y cuándo (`django-simple-history` o tabla propia). | M |
| RF-CORE-04 | Catálogos administrables: sedes, áreas, cargos, unidades de medida, categorías. | M |
| RF-CORE-05 | Búsqueda, filtros, paginación y orden en todos los listados. | M |
| RF-CORE-06 | Exportar listados a Excel (.xlsx) y CSV. | M |
| RF-CORE-07 | Importar desde Excel con previsualización, validación y reporte de errores por fila. | S |
| RF-CORE-08 | Notificaciones internas (alertas de exámenes por vencer, stock mínimo, envíos retrasados). | S |
| RF-CORE-09 | Adjuntar archivos (PDF/imagen) a registros (ej. resultado de examen, factura, guía). | S |

## RR.HH.
| ID | Requerimiento | P |
|----|---------------|---|
| RF-RH-01 | CRUD de trabajadores (DNI, nombres, cargo, área, sede de trabajo, fecha de ingreso, contacto, contacto de emergencia, estado). | M |
| RF-RH-02 | Registro de exámenes ocupacionales (tipo: ingreso, periódico, retiro; fecha, clínica, resultado: apto/apto con restricciones/no apto, fecha de vencimiento, archivo). | M |
| RF-RH-03 | Cálculo automático de **aptitud para viaje a Pataz**: apto si tiene examen vigente con resultado apto. | M |
| RF-RH-04 | Solicitud y aprobación de **vacaciones** (fechas, días, saldo, estado). | M |
| RF-RH-05 | Solicitud y aprobación de **permisos / días libres** (motivo, tipo, con/sin goce). | M |
| RF-RH-06 | Registro de **movimientos de personal**: salida hacia Pataz, retorno a Trujillo (fecha, transporte, observaciones). | M |
| RF-RH-07 | Control de **rotación/ciclo** (ej. 21x7): días en Pataz, próximo retorno. `[SUPUESTO]` | S |
| RF-RH-08 | Reportes: personal por sede/área, exámenes vencidos/por vencer (30 días), vacaciones del mes, ausencias. | M |
| RF-RH-09 | Histórico de movimientos por trabajador (línea de tiempo). | S |

## Logística y abastecimiento
| ID | Requerimiento | P |
|----|---------------|---|
| RF-LG-01 | CRUD de materiales/insumos (código, nombre, categoría, unidad, stock mínimo, precio referencial). | M |
| RF-LG-02 | CRUD de proveedores (RUC, razón social, contacto). | M |
| RF-LG-03 | **Requerimientos** (pedidos) de materiales desde Pataz/áreas: cabecera + detalle, flujo Borrador→Enviado→Aprobado/Rechazado. | M |
| RF-LG-04 | **Órdenes de compra** a proveedores a partir de requerimientos aprobados. | S |
| RF-LG-05 | Inventario por almacén (Trujillo, Pataz) con **kardex** (entradas, salidas, transferencias). | M |
| RF-LG-06 | **Envíos Trujillo→Pataz**: guía, fecha, transporte/vehículo, conductor, detalle de ítems, estados (Preparado, En tránsito, Recibido, Recibido con observaciones). | M |
| RF-LG-07 | **Recepción en Pataz**: confirmar cantidades recibidas vs enviadas, diferencias y observaciones. Debe poder registrarse luego (sin internet) o por plantilla Excel. | M |
| RF-LG-08 | Alertas de stock bajo mínimo y envíos con retraso. | S |
| RF-LG-09 | Reportes: stock, consumo por período, envíos por estado, diferencias de recepción. | M |
| RF-LG-10 | Plantilla Excel descargable para registrar recepción/consumo offline y subirla después. | S |

## Financiero
| ID | Requerimiento | P |
|----|---------------|---|
| RF-FN-01 | Catálogo de centros de costo (ej. Logística, RR.HH., Operación Pataz) y categorías de gasto. | M |
| RF-FN-02 | Registro de **egresos** (fecha, monto, categoría, centro de costo, proveedor, comprobante, vínculo opcional a envío/OC/trabajador). | M |
| RF-FN-03 | Registro de **ingresos** (fecha, monto, concepto, fuente). | S |
| RF-FN-04 | **Presupuesto** mensual por centro de costo y comparación contra ejecutado. | S |
| RF-FN-05 | Cuentas por pagar básicas: comprobantes pendientes/pagados y fecha de vencimiento. | C |
| RF-FN-06 | Reportes: gasto por centro de costo, por categoría, por mes; presupuesto vs real. | M |

## Gerencial
| ID | Requerimiento | P |
|----|---------------|---|
| RF-GR-01 | Dashboard con KPIs: personal activo por sede, personal apto/no apto, exámenes por vencer, envíos en tránsito, stock crítico, gasto del mes vs presupuesto. | M |
| RF-GR-02 | Gráficos (barras, líneas, dona) con filtros por rango de fechas y sede. | M |
| RF-GR-03 | Reporte ejecutivo imprimible/PDF. | S |

## No funcionales
| ID | Requerimiento |
|----|---------------|
| RNF-01 | Web responsive (desktop primero, usable en tablet/móvil). |
| RNF-02 | HTTPS, contraseñas con hash, protección CSRF/XSS/SQLi (defaults de Django), `DEBUG=False` en producción. |
| RNF-03 | Tiempo de respuesta < 2 s en listados paginados con 10 000 registros. |
| RNF-04 | Interfaz en español (es-PE), zona horaria `America/Lima`, moneda PEN (S/). |
| RNF-05 | Mantenible: apps separadas por módulo, pruebas automáticas, `ruff` + `black`. |
| RNF-06 | Copias de seguridad: documentar backup de PostgreSQL en Render. |
| RNF-07 | Datos sensibles de salud accesibles solo a RR.HH. y administrador. |
