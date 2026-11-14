# 06 – Roles y permisos

Implementar con grupos de Django + mixin `PermisoRolMixin` (+ permisos por modelo).

## Roles
| Rol | Descripción |
|-----|-------------|
| ADMIN | Todo, incluye usuarios y catálogos. |
| RRHH_JEFE | Gestiona RR.HH., aprueba vacaciones/permisos. |
| RRHH_ASISTENTE | Registra y edita RR.HH.; no aprueba. |
| LOGISTICA_JEFE | Gestiona logística, aprueba requerimientos. |
| LOGISTICA_ASISTENTE | Registra materiales, envíos, stock; no aprueba. |
| FINANZAS | Gestiona módulo financiero. |
| GERENTE | Solo lectura global + dashboard y reportes. |
| RECEPCION_PATAZ | Crea requerimientos, registra recepciones y consumos; solo ve Logística de su almacén. |

## Matriz (C=crear, L=leer, E=editar, A=aprobar, X=sin acceso)
| Recurso | ADMIN | RRHH_JEFE | RRHH_ASIST | LOG_JEFE | LOG_ASIST | FINANZAS | GERENTE | RECEP_PATAZ |
|---|---|---|---|---|---|---|---|---|
| Trabajadores | CLE | CLE | CLE | X | X | L (básico) | L | X |
| Exámenes ocupacionales (salud) | CLE | CLE | CLE | X | X | X | L resumen | X |
| Vacaciones / Permisos | CLEA | CLEA | CLE | X | X | X | L | X |
| Movimientos de personal | CLE | CLE | CLE | L | X | X | L | X |
| Materiales / Proveedores | CLE | X | X | CLE | CLE | L | L | L |
| Requerimientos | CLEA | X | X | CLEA | CLE | L | L | CLE (propios) |
| Envíos | CLE | X | X | CLE | CLE | L | L | L |
| Recepciones / Consumos | CLE | X | X | CLE | CLE | L | L | CLE (su almacén) |
| Stock / Kardex | L | X | X | L | L | L | L | L (su almacén) |
| Movimientos financieros | CLE | X | X | X | X | CLE | L | X |
| Presupuesto | CLE | X | X | X | X | CLE | L | X |
| Dashboard gerencial | L | L parcial | X | L parcial | X | L parcial | L | X |
| Usuarios / Catálogos | CLE | X | X | X | X | X | X | X |
| Importar Excel | sí | sí (RR.HH.) | sí (RR.HH.) | sí (Logística) | sí (Logística) | X | X | solo recepción |

## Reglas adicionales
- Un usuario no aprueba sus propias solicitudes.
- Datos médicos (resultado y archivos) solo roles RR.HH. y ADMIN.
- Todas las aprobaciones registran usuario y fecha.
- Bloqueo de cuenta tras 5 intentos fallidos (`django-axes`, S).
