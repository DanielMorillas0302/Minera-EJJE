# AGENTS.md – Reglas para el agente

## Misión
Construir un sistema web modular de gestión (Logística, RR.HH., Financiero, Gerencial) para una empresa minera con oficinas en Trujillo y operación en Pataz, reemplazando el uso de Excel.

## Reglas
1. Stack fijo: **Python 3.12 + Django 5 + PostgreSQL + Render**. No cambiar sin registrar en `DECISIONES.md`.
2. Interfaz en **español**; código (modelos, variables) en español sin tildes ni ñ (ej. `Trabajador`, `fecha_ingreso`), salvo convenciones de Django.
3. Trabaja por **fases** de `10-plan-de-desarrollo.md`. No saltes fases. Al cerrar cada una: migraciones aplicadas, pruebas en verde, `README` del proyecto actualizado.
4. Cada app Django = un módulo (`rrhh`, `logistica`, `finanzas`, `gerencia`, `core`, `reportes`).
5. Todo modelo de negocio hereda de `ModeloBase` (`creado_en`, `actualizado_en`, `creado_por`, `activo`). **Borrado lógico**, nunca físico en datos de negocio.
6. Toda vista exige login y permiso por rol (ver `06-roles-y-permisos.md`).
7. Secretos solo por variables de entorno. Nunca commitear `.env`.
8. Escribe pruebas (pytest-django) para reglas de negocio críticas (ver `09`).
9. Commits pequeños y descriptivos (Conventional Commits).
10. Si hay ambigüedad: aplica el `[SUPUESTO]` del documento y anótalo en `DECISIONES.md` con fecha y motivo.
11. No agregues librerías pesadas innecesarias. Preferir Django nativo + HTMX + Bootstrap 5.

## Definición de "hecho" por tarea
- Funciona en navegador, con validaciones y mensajes en español.
- Con permisos aplicados y pruebas pasando.
- Sin errores en `python manage.py check` ni `ruff`.

## Entregables finales
Código en repositorio Git, `README.md` del proyecto, `DECISIONES.md`, `render.yaml`, `requirements.txt`, datos semilla (`python manage.py cargar_semilla`), manual breve de usuario (`docs/manual-usuario.md`).
