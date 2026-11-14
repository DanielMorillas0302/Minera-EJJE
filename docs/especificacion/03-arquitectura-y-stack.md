# 03 – Arquitectura y stack

## Stack
| Capa | Tecnología |
|------|-----------|
| Lenguaje | Python 3.12 |
| Framework | Django 5.x (monolito modular, templates server-side) |
| Base de datos | PostgreSQL 16 (Render) |
| Frontend | Django Templates + **HTMX** + Bootstrap 5 + Chart.js |
| Excel/PDF | `openpyxl` (xlsx), `WeasyPrint` o `xhtml2pdf` (PDF) |
| Auth | `django.contrib.auth` con modelo de usuario personalizado |
| Auditoría | `django-simple-history` |
| Filtros | `django-filter` |
| Formularios | `django-crispy-forms` + `crispy-bootstrap5` |
| Archivos | Render Disk o almacenamiento S3 compatible (`django-storages`) |
| Servidor | `gunicorn` + `whitenoise` para estáticos |
| Config | `django-environ`, `dj-database-url` |
| Pruebas | `pytest`, `pytest-django`, `factory-boy` |
| Calidad | `ruff`, `black`, `pre-commit` |

> Se descarta n8n (automatización) como base: el profesor indicó Python. Se descarta NoSQL: los datos son relacionales.

## Estructura del repositorio
```
sistema-minera/
├── manage.py
├── requirements.txt
├── render.yaml
├── build.sh
├── .env.example
├── AGENTS.md  DECISIONES.md  README.md
├── config/               # settings (base.py, dev.py, prod.py), urls, wsgi
├── apps/
│   ├── core/             # usuarios, roles, catálogos, auditoría, importador, base
│   ├── rrhh/
│   ├── logistica/
│   ├── finanzas/
│   ├── gerencia/         # dashboard (sin modelos propios, consulta a otros)
│   └── reportes/         # exportadores xlsx/pdf reutilizables
├── templates/            # base.html, partials/, por app
├── static/
├── docs/                 # manual-usuario.md
└── tests/
```

## Convenciones
- Modelos: `ModeloBase` abstracto (`creado_en`, `actualizado_en`, `creado_por`, `activo`).
- Vistas: Class-Based Views (`ListView`, `CreateView`, …) + mixin `PermisoRolMixin`.
- URLs con namespace por app: `rrhh:trabajador_lista`.
- Servicios de negocio en `servicios.py` por app (no meter lógica en vistas).
- Estados con `TextChoices`.
- Transacciones atómicas en operaciones de stock y envíos.
- Ajustes: `LANGUAGE_CODE="es-pe"`, `TIME_ZONE="America/Lima"`.

## Diagrama lógico
```
Navegador (Trujillo) ──HTTPS──> Render Web Service (Django + gunicorn)
                                      │
                                      └──> Render PostgreSQL
Pataz (sin internet): plantillas Excel ──(cuando haya conectividad)──> Importador
```

## Variables de entorno
`SECRET_KEY`, `DEBUG`, `ALLOWED_HOSTS`, `DATABASE_URL`, `CSRF_TRUSTED_ORIGINS`, `MEDIA_ROOT`, `TIME_ZONE`.
