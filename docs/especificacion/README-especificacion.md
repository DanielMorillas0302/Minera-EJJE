# Sistema Web Integral de Gestión – Empresa Minera (Trujillo / Pataz)

Paquete de documentos para que un agente de código construya el sistema completo.

## Orden de lectura (obligatorio)
1. `AGENTS.md` – reglas de trabajo del agente (léelo primero).
2. `01-contexto-y-alcance.md` – qué problema se resuelve y qué entra / no entra.
3. `02-requerimientos.md` – requerimientos funcionales y no funcionales con IDs.
4. `03-arquitectura-y-stack.md` – tecnologías y estructura del proyecto.
5. `04-modelo-de-datos.md` – entidades, campos y relaciones.
6. `05-modulos-y-flujos.md` – pantallas, flujos y reglas de negocio por módulo.
7. `06-roles-y-permisos.md` – matriz de permisos.
8. `07-ui-ux.md` – lineamientos de interfaz.
9. `08-despliegue-render.md` – despliegue en Render + PostgreSQL.
10. `09-datos-semilla-y-pruebas.md` – datos de ejemplo y pruebas.
11. `10-plan-de-desarrollo.md` – fases, tareas y criterios de aceptación.

## Prompt sugerido para el agente
> Lee todos los archivos .md de esta carpeta en el orden del README. Ejecuta el plan de `10-plan-de-desarrollo.md` fase por fase. Al terminar cada fase, corre las pruebas, verifica los criterios de aceptación y resume lo hecho antes de seguir. No inventes requerimientos fuera de estos documentos; si algo es ambiguo, usa el supuesto indicado y regístralo en `DECISIONES.md`.

## Estado de la información
- La transcripción de la reunión está **incompleta** (0:01–2:10 de 6:26). Los procesos internos de cada módulo (sobre todo Financiero) son **supuestos razonables** marcados con `[SUPUESTO]`.
- Indicaciones del profesor: **Python**, **PostgreSQL**, **aplicación web**, despliegue en **Render**.
