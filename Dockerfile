# Etapa 1: Compilación de assets con Node.js
FROM node:20-alpine AS frontend-builder
WORKDIR /app
COPY package*.json ./
RUN npm ci
COPY . .
RUN npm run build

# Etapa 2: Aplicación PHP + Nginx en producción
FROM php:8.3-cli-alpine

# Instalar extensiones requeridas por Laravel y SQLite/MySQL/Zip/GD
RUN apk add --no-cache \
    git \
    curl \
    libpng-dev \
    libxml2-dev \
    zip \
    unzip \
    libzip-dev \
    oniguruma-dev \
    mysql-client \
    && docker-php-ext-install pdo pdo_mysql mbstring exif pcntl bcmath gd zip

# Instalar Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www

# Copiar archivos base
COPY composer*.json ./
RUN composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader --no-scripts

# Copiar código fuente
COPY . .

# Copiar assets compilados desde la etapa de Node
COPY --from=frontend-builder /app/public/build ./public/build

# Permisos
RUN chown -R www-data:www-data /var/www/storage /var/www/bootstrap/cache

# Puerto expuesto por Render (por defecto $PORT)
EXPOSE 8080

CMD ["sh", "-c", "php artisan config:cache && php artisan route:cache && php artisan view:cache && php artisan serve --host=0.0.0.0 --port=${PORT:-8080}"]

