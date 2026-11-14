# 08 – Despliegue en Render

## Servicios
1. **PostgreSQL** (Render Postgres).
2. **Web Service** (Python) con gunicorn.
3. (Opcional) **Disk** montado en `/var/data` para `MEDIA_ROOT`, o S3 compatible.

## `build.sh`
```bash
#!/usr/bin/env bash
set -o errexit
pip install -r requirements.txt
python manage.py collectstatic --no-input
python manage.py migrate
```

## `render.yaml`
```yaml
databases:
  - name: minera-db
    plan: free
    databaseName: minera
services:
  - type: web
    name: sistema-minera
    runtime: python
    buildCommand: "./build.sh"
    startCommand: "gunicorn config.wsgi:application"
    envVars:
      - key: DATABASE_URL
        fromDatabase: { name: minera-db, property: connectionString }
      - key: SECRET_KEY
        generateValue: true
      - key: DEBUG
        value: "False"
      - key: ALLOWED_HOSTS
        value: ".onrender.com"
      - key: CSRF_TRUSTED_ORIGINS
        value: "https://*.onrender.com"
      - key: PYTHON_VERSION
        value: "3.12.3"
```

## Settings de producción
`SECURE_SSL_REDIRECT=True`, `SESSION_COOKIE_SECURE=True`, `CSRF_COOKIE_SECURE=True`, `SECURE_PROXY_SSL_HEADER=("HTTP_X_FORWARDED_PROTO","https")`, WhiteNoise en middleware, `DATABASES` desde `dj_database_url`.

## Primer despliegue
1. Subir repo a GitHub. 2. Crear Blueprint en Render apuntando a `render.yaml`. 3. Ejecutar en shell: `python manage.py createsuperuser` y `python manage.py cargar_semilla`.

## Notas
- El plan gratuito de Render duerme el servicio tras inactividad y la BD gratuita tiene límites/expiración: **advertir en el README** y recomendar plan de pago para uso real.
- Documentar backup: `pg_dump` periódico.
- Archivos subidos: si no se usa Disk/S3 se perderán en cada deploy.
