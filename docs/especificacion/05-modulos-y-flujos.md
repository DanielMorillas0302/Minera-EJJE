# 05 – Módulos, pantallas y flujos

## Navegación principal (sidebar)
Inicio (dashboard) · RR.HH. · Logística · Finanzas · Gerencia · Reportes · Administración (solo admin).

## RR.HH.
**Pantallas**: Trabajadores (lista/detalle/form) · Exámenes ocupacionales · Vacaciones · Permisos · Movimientos Trujillo↔Pataz · Alertas (exámenes por vencer).
- Detalle de trabajador con pestañas: Datos, Exámenes, Vacaciones, Permisos, Movimientos. Badge **"Apto para Pataz"** (verde) / **"No apto"** (rojo) con el motivo.
- Lista de exámenes con filtro "vencidos / vencen en ≤30 días".

**Flujo: enviar trabajador a Pataz**
1. RR.HH. registra/actualiza al trabajador.
2. Registra examen ocupacional (APTO, vigente).
3. Sistema habilita `aptitud_viaje`.
4. RR.HH. crea `MovimientoPersonal` SALIDA_A_PATAZ → `ubicacion_actual=PATAZ`.
5. Si no es apto → bloqueo con mensaje explicativo.
6. Al volver: RETORNO_A_TRUJILLO → `ubicacion_actual=TRUJILLO`.

**Flujo vacaciones/permisos**: Solicitud (PENDIENTE) → Jefe RR.HH. aprueba/rechaza → si APROBADA descuenta saldo → cambia a TOMADA al pasar `fecha_fin` (comando programado o cálculo al consultar).

## Logística
**Pantallas**: Materiales · Proveedores · Almacenes/Stock · Kardex · Requerimientos · Órdenes de compra · Envíos · Recepciones.

**Flujo abastecimiento**
1. Usuario crea **Requerimiento** (BORRADOR) → lo envía (ENVIADO).
2. Jefe de logística aprueba/rechaza.
3. Si falta stock en Trujillo → (S) crea Orden de Compra; al recibirla, ENTRADA_COMPRA en Almacén Trujillo.
4. Logística crea **Envío** desde Almacén Trujillo a Almacén Pataz con ítems → al pasar a EN_TRANSITO genera `SALIDA_ENVIO` (descuenta stock Trujillo).
5. **Recepción**: se registran cantidades recibidas → genera `ENTRADA_ENVIO` en Pataz. Si hay diferencias → estado RECIBIDO_CON_OBSERVACIONES y alerta.
6. Requerimiento se actualiza a ATENDIDO / ATENDIDO_PARCIAL.
7. Consumo en Pataz: registro `CONSUMO` (individual o por carga Excel).

**Reglas**
- No se puede enviar más que el stock disponible.
- Un envío anulado revierte movimientos de inventario.
- Códigos correlativos por año generados con servicio atómico.
- Plantilla Excel de recepción: columnas `codigo_envio, codigo_material, cantidad_recibida, observacion`.

## Finanzas
**Pantallas**: Movimientos (ingresos/egresos) · Centros de costo · Presupuesto · Pendientes de pago.
- Desde un Envío u OC puede generarse un egreso precargado (vínculo).
- Filtros por fecha, centro de costo, categoría, estado de pago.
- Presupuesto vs ejecutado con barra de progreso (alerta >90%).

## Gerencia (dashboard)
KPIs (tarjetas): Personal activo (Trujillo/Pataz) · Aptos vs no aptos · Exámenes vencen ≤30 días · Envíos en tránsito · Materiales bajo stock mínimo · Gasto del mes vs presupuesto.
Gráficos: gasto mensual (línea), gasto por centro de costo (barras), envíos por estado (dona), personal por área (barras).
Filtros: rango de fechas y sede. Botón "Exportar PDF".

## Reportes (todos exportables a XLSX; los marcados a PDF)
RR.HH.: personal por sede/área · exámenes vencidos/por vencer · vacaciones del período · movimientos de personal.
Logística: stock actual · kardex por material (PDF) · envíos por estado · diferencias de recepción · requerimientos pendientes.
Finanzas: gasto por centro de costo · por categoría · presupuesto vs real (PDF) · cuentas por pagar.

## Importación desde Excel (core)
Tipos: Trabajadores, Materiales, Proveedores, Stock inicial, Recepción de envío.
Proceso: subir archivo → validar (formato, duplicados, FKs) → previsualizar → confirmar → resumen con filas con error descargable. Ofrecer **plantillas descargables** por tipo.
