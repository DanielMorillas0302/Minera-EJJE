# Manual de Usuario – Sistema Integrado de Gestión Minera

**Versión:** 1.0  
**Fecha de Publicación:** Noviembre 2026  
**Ámbito de Aplicación:** Operación Minera Pataz (Campamento y Almacén Mina) y Sede Administrativa Trujillo (Oficinas Centrales y Almacén Central).

---

## 1. Introducción y Arquitectura Operativa

El **Sistema Integrado de Gestión Minera** es una plataforma web desarrollada para unificar y optimizar la administración operativa, logística, de talento humano y financiera de la empresa, reemplazando el uso de hojas de cálculo dispersas.

### 1.1 Sedes del Negocio
* **Sede Central – Trujillo:** Concentra la dirección gerencial, compras corporativas, contrataciones, administración financiera, tesorería y el almacén central de despacho.
* **Operación y Campamento – Pataz:** Mina ubicada en la sierra de La Libertad (alta montaña). Concentra las labores de extracción, mantenimiento mecánico, campamento del personal y almacén de recepción de insumos críticos.

### 1.2 Principios Clave del Sistema
* **Trazabilidad Integral:** Cada registro guarda el usuario que lo creó, fecha de creación y última actualización.
* **Borrado Lógico:** La información operativa e histórica nunca se elimina físicamente de la base de datos; se inhabilita mediante marcado lógico para preservar la auditoría.
* **Control de Acceso Basado en Roles (RBAC):** Las opciones del menú, botones y acciones están estrictamente restringidas según el rol y la sede del usuario.

---

## 2. Acceso al Sistema y Seguridad

### 2.1 Credenciales y Cuentas de Acceso (Entorno Demostrativo)
El sistema cuenta con usuarios preconfigurados para cada área funcional con la contraseña unificada: `Demo1234!`

| Rol / Función | Correo Electrónico | Sede Principal | Áreas Autorizadas |
| :--- | :--- | :--- | :--- |
| **Administrador General** | `admin@mineria.pe` | Trujillo | Acceso irrestricto, configuración, usuarios, auditoría y catálogos |
| **Jefe de RR.HH.** | `rrhh.jefe@mineria.pe` | Trujillo | Padrón de personal, EMO, aptitud de subida, vacaciones y contratos |
| **Asistente de RR.HH.** | `rrhh.asistente@mineria.pe` | Trujillo / Pataz | Registro de personal, asistencia, traslados y descansos |
| **Jefe de Logística** | `logistica.jefe@mineria.pe` | Trujillo | Órdenes de compra, requerimientos, catálogo, inventario general |
| **Asistente de Logística** | `logistica.asistente@mineria.pe` | Trujillo | Requerimientos, despachos intersedes, guías de remisión |
| **Recepción Pataz** | `recepcion.pataz@mineria.pe` | Pataz | Recepción física de envíos en mina, actas de diferencias |
| **Finanzas y Tesorería** | `finanzas@mineria.pe` | Trujillo | Presupuesto mensual, cuentas por pagar, caja chica y bancos |
| **Gerencia General** | `gerente@mineria.pe` | Trujillo | Dashboard ejecutivo, KPIs consolidados, informes PDF y analítica |

### 2.2 Política de Bloqueo por Intentos Fallidos
Por estrictas políticas de ciberseguridad:
1. Tras ingresar **5 contraseñas incorrectas de manera consecutiva**, la cuenta de usuario se bloqueará automáticamente durante **15 minutos**.
2. Cada intento fallido queda registrado en la bitácora de auditoría con la dirección IP, agente de usuario y marca de tiempo.
3. El Administrador General puede desbloquear cuentas anticipadamente desde la pantalla de Auditoría y Usuarios.

---

## 3. Módulo de Recursos Humanos (RR.HH.)

### 3.1 Padrón de Trabajadores
* **Ubicación:** `Menú Principal > RR.HH. > Trabajadores`
* **Alta de Personal:** Clic en **"Nuevo Trabajador"**. Debe completarse:
  - Documento de Identidad (DNI / CE).
  - Nombres y Apellidos completos.
  - Cargo, Área funcional y Sede inicial asignada (Trujillo o Pataz).
  - Régimen Laboral de trabajo en mina (ej. 14x7, 20x10, 5x2).
  - Fecha de ingreso y datos de contacto de emergencia.
* **Filtros y Búsqueda:** Búsqueda rápida por nombre o DNI, y filtrado por estado (Activo / Inactivo) y sede asignada.

### 3.2 Exámenes Médicos Ocupacionales (EMO)
* **Ubicación:** `Menú Principal > RR.HH. > Exámenes Médicos`
* **Semáforo Médico de Vencimiento:**
  - 🟢 **Vigente:** EMO con vigencia superior a 30 días.
  - 🟡 **Por Vencer:** EMO con fecha de expiración menor a 30 días (aviso preventivo).
  - 🔴 **Vencido:** EMO caducado; el sistema restringe automáticamente traslados a Pataz.
* **Aptitud de Subida a Faena:** La subida a faena minera exige obligatoriamente resultado **Apto** o **Apto con Restricciones**. Si el resultado es **No Apto** o está vencido, no se permitirá generar programación de viaje a Pataz.

### 3.3 Traslados Intersedes y Programación de Viajes
* **Ubicación:** `Menú Principal > RR.HH. > Traslados`
* **Procedimiento:**
  1. Seleccionar al trabajador.
  2. Indicar la sede de origen y sede de destino (ej. Trujillo -> Pataz).
  3. Ingresar la fecha programada del traslado y el motivo operativo (inicio de guardia, bajada de descanso o reubicación).
  4. El sistema valida automáticamente el estado del EMO del trabajador antes de confirmar el traslado.

### 3.4 Vacaciones y Licencias
* **Ubicación:** `Menú Principal > RR.HH. > Vacaciones`
* Permite programar períodos vacacionales o licencias médicas con control de solapamiento de fechas para evitar duplicidad de coberturas.

---

## 4. Módulo de Logística y Abastecimiento

### 4.1 Catálogo de Materiales
* **Ubicación:** `Menú Principal > Logística > Materiales`
* Cada material cuenta con:
  - Código único de inventario (ej. `MAT-001`).
  - Categoría (Equipos de Protección Personal, Repuestos Mecánicos, Reactivos Químicos, Combustibles, Herramientas).
  - Unidad de Medida (Unidades, Kilogramos, Galones, Metros, Cajas).
  - Stock Mínimo de Seguridad (dispara alertas visuales en caso de nivel crítico).

### 4.2 Requerimientos de Compra / Materiales (RQ)
* **Ubicación:** `Menú Principal > Logística > Requerimientos`
* Flujo de Aprobación:
  1. **Borrador:** Registro inicial por el solicitante en mina o sede.
  2. **Pendiente de Aprobación:** Notifica al Jefe de Logística o Gerencia.
  3. **Aprobado:** Habilita la emisión de la Orden de Compra o despacho de almacén.
  4. **Atendido:** Despachado y completado.

### 4.3 Órdenes de Compra (OC)
* **Ubicación:** `Menú Principal > Logística > Órdenes de Compra`
* **Emisión de OC:**
  - Selección de proveedor registrado (con RUC y razón social validados).
  - Selección de almacén de entrega previsto (Almacén Central Trujillo o Almacén Mina Pataz).
  - Inclusión de ítems con cantidades y precios unitarios pactados.
  - Cálculo automático de subtotal, IGV (18%) y total en Soles o Dólares.
* **Descarga de PDF Oficial:** Botón de impresión/exportación que genera la carátula formal de Orden de Compra con logotipos, firmas de aprobación y condiciones comerciales.
* **Generación Automática de Cuenta por Pagar:** Posibilidad de crear simultáneamente el registro en Tesorería para programar el desembolso financiero.

### 4.4 Envíos Intersedes (Trujillo -> Pataz)
* **Ubicación:** `Menú Principal > Logística > Envíos`
* **Preparación y Despacho:**
  1. El Almacén Central en Trujillo consolida los materiales a despachar en el camión de ruta a Pataz.
  2. Se genera el envío con código único (ej. `ENV-2026-0001`), transportista, placa de vehículo y número de bultos.
  3. Al cambiar el estado a **En Tránsito**, el inventario en Trujillo descuenta las existencias físicas.
* **Recepción y Control de Discrepancias en Mina:**
  1. El personal de **Recepción Pataz** abre el envío al arribar a campamento.
  2. Digita las cantidades físicas realmente recibidas.
  3. Si existen faltantes o mermas de viaje (ej. salieron 50 unidades y llegaron 48), el sistema exige registrar el **Acta de Diferencia**, alertando inmediatamente a Logística y Gerencia para iniciar la investigación con la empresa de transporte.

### 4.5 Inventario y Kardex
* **Ubicación:** `Menú Principal > Logística > Kardex / Inventario`
* Visualización multialmacén con filtros por almacén (Trujillo vs Pataz), tipo de movimiento (Entrada, Salida, Traslado Intersedes) y saldo actualizado en tiempo real.

---

## 5. Módulo Financiero y Tesorería

### 5.1 Centros de Costo y Presupuesto Mensual
* **Ubicación:** `Menú Principal > Finanzas > Presupuestos`
* Permite fijar los techos presupuestales mensuales para cada centro de costo operativo:
  - Operaciones Mina
  - Mantenimiento y Planta
  - Campamento y Servicios Generales
  - Administración y Gerencia
* **Semáforo de Sobregiro:** Barra de consumo que alerta visualmente en color amarillo (al superar el 80%) y rojo (al superar el 100% presupuestado).

### 5.2 Movimientos Financieros (Caja y Bancos)
* **Ubicación:** `Menú Principal > Finanzas > Movimientos`
* Registro de flujos de efectivo, transferencias bancarias, depósitos de clientes y pagos de gastos menores.

### 5.3 Cuentas por Pagar a Proveedores
* **Ubicación:** `Menú Principal > Finanzas > Cuentas por Pagar`
* Control de compromisos comerciales:
  - Número de comprobante / factura fiscal.
  - Fecha de emisión y fecha límite de pago.
  - **Semáforo de Vencimiento:** Alerta de facturas pendientes con más de 15 días de vencidas.
  - Registro de pagos parciales (amortizaciones) o cancelación total con actualización automática del saldo adeudado.

---

## 6. Módulo Gerencial y Hub de Reportes

### 6.1 Dashboard Ejecutivo
* **Ubicación:** `Menú Principal > Gerencia > Dashboard`
* Tarjetas de Métricas Directivas:
  - Total de trabajadores en operación activa y personal en campamento Pataz.
  - Valor total del inventario valorizado distribuido entre Trujillo y Pataz.
  - Presupuesto mensual consumido global y porcentaje de ejecución.
  - Monto acumulado de cuentas por pagar vigentes vs vencidas.
* Gráficos Analíticos:
  - Evolución mensual de compras y gastos.
  - Distribución de costos operativos por centro de costo.

### 6.2 Hub Central de Reportes y Exportación a Excel
* **Ubicación:** `Menú Principal > Reportes > Catálogo de Reportes`
* Permite descargar en formato `.xlsx` con cabeceras estilizadas los siguientes listados:
  - Padrón consolidado de trabajadores y estado de EMOs.
  - Kardex valorizado de almacenes.
  - Registro cronológico de envíos intersedes y discrepancias.
  - Ejecución presupuestal y detalle de cuentas por pagar.

### 6.3 Generación del Informe Ejecutivo PDF
* **Ubicación:** `Menú Principal > Gerencia > Descargar Informe Ejecutivo`
* Genera un documento PDF formal con portada institucional, resumen directivo de indicadores clave, alertas operativas del período y pie de página con firmas autorizadas.

---

## 7. Centro de Notificaciones y Auditoría

### 7.1 Bandeja de Notificaciones del Sistema
* **Ubicación:** `Icono de Campana en la barra superior` o `Menú Principal > Notificaciones`
* Genera alertas automáticas para los usuarios pertinentes:
  - Exámenes médicos ocupacionales próximos a vencer.
  - Materiales cuyo stock actual está por debajo del mínimo de seguridad.
  - Nuevas órdenes de compra pendientes de revisión o aprobación.
  - Recepción de envíos en Pataz con diferencias en bultos.

### 7.2 Bitácora de Auditoría (Audit Log)
* **Ubicación:** `Menú Principal > Administración > Bitácora de Auditoría`
* Registro inmutable de cada acción crítica:
  - Intentos de inicio de sesión (exitosos y fallidos).
  - Creación, modificación o anulación lógica de registros de negocio.
  - Dirección IP, fecha, hora y usuario ejecutor.

---

## 8. Carga Masiva y Mantenimiento

### 8.1 Importaciones Masivas vía Excel
* Desde los submódulos correspondientes (Personal, Catálogo de Materiales), los usuarios con rol Administrador pueden subir archivos `.xlsx` respetando el formato de columnas predefinido para cargar lotes de datos rápidamente.

### 8.2 Comando de Datos Semilla
Para reiniciar o abastecer una base de datos recién instalada con datos de prueba realistas, ejecutar en la consola del servidor:
```bash
php artisan cargar_semilla
```
Este comando es idempotente (no duplica registros existentes) y habilita catálogos core, sedes, almacenes, proveedores, personal, presupuestos y órdenes de compra demo.

---

## 9. Soporte y Contacto Técnico
Ante dudas operativas, incidentes de acceso o solicitudes de nuevos perfiles de usuario, dirigirse al Administrador del Sistema:
* **Mesa de Ayuda Interna:** `admin@mineria.pe`
* **Horario de Atención:** Lunes a Viernes de 08:00 a 18:00 hrs (Sede Trujillo) / Soporte de Emergencia en Turno Guardia (Pataz).

