# 10 – Plan de desarrollo por fases

Cada fase termina con: migraciones, pruebas en verde, commit/tag (`fase-N`) y resumen.

## Fase 0 – Base del proyecto
- Crear proyecto Django con estructura de `03`, settings por entorno, `.env.example`, `requirements.txt`, `ruff/black/pre-commit`.
- Conectar PostgreSQL; `ModeloBase`; usuario personalizado con rol.
- Plantilla base (sidebar, navbar, mensajes), login/logout.
- **Aceptación**: `manage.py check` OK, login funcional, layout visible.

## Fase 1 – Core
- Modelos: Sede, Area, Cargo, UnidadMedida, Adjunto; CRUD de catálogos.
- Gestión de usuarios y roles; `PermisoRolMixin`; auditoría con historial.
- Comando `cargar_semilla` (parte core).
- **Aceptación**: admin crea usuarios con rol; menú cambia según rol.

## Fase 2 – RR.HH. (MVP)
- Trabajador, ExamenOcupacional, aptitud de viaje, MovimientoPersonal.
- Vacaciones (período + solicitud + aprobación), Permisos.
- Alertas de exámenes por vencer; reportes RR.HH.
- **Aceptación**: flujo "enviar a Pataz" completo con bloqueo si no apto; pruebas RR.HH. verdes.

## Fase 3 – Logística (MVP)
- Material, Proveedor, Almacén, Stock, Kardex.
- Requerimientos con flujo de aprobación.
- Envíos y recepciones con movimientos de inventario transaccionales.
- Alertas de stock mínimo; reportes logística.
- **Aceptación**: flujo requerimiento→envío→recepción con diferencias; kardex consistente.

## Fase 4 – Importador y plantillas Excel
- Importador genérico con previsualización y reporte de errores.
- Plantillas: trabajadores, materiales, proveedores, stock inicial, recepción de envío.
- **Aceptación**: importar archivos de ejemplo OK y con errores.

## Fase 5 – Finanzas
- CentroCosto, CategoriaGasto, MovimientoFinanciero, Presupuesto.
- Vínculo con envíos/trabajadores; pendientes de pago; reportes.
- **Aceptación**: presupuesto vs ejecutado correcto.

## Fase 6 – Gerencia y reportes
- Dashboard con KPIs y gráficos; filtros por fecha/sede.
- Exportadores XLSX/PDF reutilizables en `apps/reportes`.
- **Aceptación**: KPIs coinciden con datos; PDF ejecutivo se genera.

## Fase 7 – Órdenes de compra y mejoras (Should/Could)
- Orden de compra con recepción; notificaciones internas; cuentas por pagar; bloqueo por intentos fallidos.

## Fase 8 – Calidad y despliegue
- Completar pruebas y cobertura; revisión de permisos (matriz `06`).
- `render.yaml`, `build.sh`, despliegue y verificación en producción.
- `README.md` final, `DECISIONES.md`, `docs/manual-usuario.md` (capturas opcionales).
- **Aceptación**: checklist manual de `09` completo en producción.

## Orden de prioridad si falta tiempo
Fase 0 → 1 → 2 → 3 → 6 (dashboard básico) → 8 → 5 → 4 → 7.

## Plantilla de `DECISIONES.md`
```
## AAAA-MM-DD – Título
Contexto: ...
Decisión: ...
Motivo: ...
Impacto: ...
```
