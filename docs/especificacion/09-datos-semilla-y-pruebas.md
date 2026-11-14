# 09 – Datos semilla y pruebas

## Comando `python manage.py cargar_semilla`
Idempotente (usa `get_or_create`). Crea:
- Sedes: Trujillo (ADMINISTRATIVA), Pataz (OPERATIVA).
- Áreas: RR.HH., Logística, Finanzas, Gerencia, Operaciones (Pataz).
- Cargos: Gerente General, Jefe de RR.HH., Asistente de RR.HH., Jefe de Logística, Almacenero, Contador, Operario de mina, Supervisor de seguridad.
- Unidades: unidad, kg, litro, metro, caja, par, galón.
- Categorías de material: EPP, Herramientas, Combustibles, Explosivos y accesorios (solo catálogo), Repuestos, Alimentos, Oficina.
- Almacenes: Almacén Trujillo, Almacén Pataz.
- Centros de costo y categorías de gasto básicos.
- Usuarios demo (contraseña `Demo1234!`, **solo en DEBUG**): uno por rol.
- ~30 trabajadores, ~40 materiales, 5 proveedores, 5 requerimientos, 3 envíos, 20 movimientos financieros, con datos ficticios coherentes (exámenes vigentes y vencidos para probar alertas).

## Pruebas obligatorias (pytest)
| Área | Casos |
|------|-------|
| RR.HH. | aptitud True con examen apto vigente; False si vencido / no apto / cesado; bloqueo de SALIDA_A_PATAZ si no apto; saldo de vacaciones; no solapamiento de permisos. |
| Logística | stock no negativo; envío descuenta origen; recepción suma destino; diferencias → estado con observaciones; anulación revierte kardex; correlativos únicos. |
| Finanzas | totales por centro de costo; presupuesto vs ejecutado; egreso vinculado a envío. |
| Permisos | cada rol accede solo a lo de la matriz (403 en el resto); usuario no aprueba su propia solicitud. |
| Importador | archivo válido, con duplicados, con columnas faltantes. |
| Smoke | todas las URLs principales responden 200 con usuario ADMIN. |

Cobertura mínima objetivo: 70 % en `servicios.py` de cada app.

## Pruebas manuales (checklist de aceptación)
- [ ] Crear trabajador, registrar examen, enviarlo a Pataz y retornarlo.
- [ ] Crear requerimiento → aprobar → envío → recepción con diferencia.
- [ ] Registrar egreso ligado al envío y verlo en reporte.
- [ ] Dashboard muestra KPIs coherentes con los datos.
- [ ] Exportar a Excel y PDF funciona.
- [ ] Importar Excel de trabajadores con errores muestra reporte.
