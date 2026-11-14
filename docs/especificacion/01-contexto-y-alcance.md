# 01 – Contexto y alcance

## Contexto
Empresa minera con:
- **Trujillo**: todas las oficinas administrativas (RR.HH., logística, financiero, gerencia). Hay internet.
- **Pataz**: zona de extracción (minas/campamentos). **Sin internet ni wifi y sin oficinas.** Recibe materiales y personal desde Trujillo.

Situación actual: **no tienen ningún sistema implementado; todo se gestiona con Excel.**

## Objetivo
Digitalizar y centralizar los procesos en un **sistema web modular con base de datos única**, accesible desde Trujillo.

## Módulos identificados
| # | Módulo | Resumen |
|---|--------|---------|
| 1 | Logística y abastecimiento | Materiales, requerimientos, envíos Trujillo→Pataz, recepción, stock. |
| 2 | RR.HH. (administrativo) | Trabajadores, exámenes ocupacionales, aptitud para viajar a Pataz, entradas/salidas, vacaciones, permisos. |
| 3 | Financiero | Ingresos, egresos, gastos asociados a envíos y personal, presupuesto. `[SUPUESTO]` |
| 4 | Gerencial | Dashboard e indicadores de los demás módulos. `[SUPUESTO]` |
| 5 | Transversal: Importación de Excel | Cargar los Excel actuales (trabajadores, materiales) para migrar datos. |

## Dentro del alcance
- Aplicación web responsive, autenticación, roles, auditoría.
- CRUD completo de cada módulo con flujos de estado.
- Reportes exportables (Excel y PDF) y dashboard gerencial.
- Importación/exportación Excel/CSV.
- Despliegue en Render con PostgreSQL.

## Fuera del alcance (ampliaciones futuras)
- Aplicación offline con sincronización para Pataz. `[SUPUESTO]` Se mitiga con: **formato de registro en Excel descargable/subible** (plantillas) para que Pataz envíe datos cuando haya conectividad, y la recepción la registre personal de Trujillo o quien tenga acceso.
- Planilla/nómina y contabilidad legal (SUNAT).
- Integraciones con ERP externos, app móvil nativa.

## Usuarios
Administrador del sistema, Jefe/Asistente de RR.HH., Jefe/Asistente de Logística, Contador/Finanzas, Gerente, Responsable de recepción en Pataz (acceso eventual).

## Riesgos
| Riesgo | Mitigación |
|--------|-----------|
| Pataz sin internet | Plantillas Excel de envío/recepción + importación; diseño ligero. |
| Alcance muy grande para el plazo | Priorizar por fases (ver `10`); MVP = RR.HH. + Logística. |
| Procesos no totalmente conocidos | Supuestos documentados, modelo flexible, ajustes tras validar con la empresa. |
| Datos sensibles (salud ocupacional) | Roles estrictos, auditoría, HTTPS. |
