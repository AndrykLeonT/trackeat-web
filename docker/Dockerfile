# Imagen de TrackEat para la Práctica 5: la usan la app, Reverb y la cola de cada nodo.
# El frontend se compila antes en Windows (pnpm build) y llega ya hecho en backend/public.
FROM php:8.3-cli-alpine

RUN apk add --no-cache postgresql-dev libzip-dev \
    && docker-php-ext-install pdo_pgsql bcmath opcache pcntl zip \
    && printf 'opcache.enable_cli=1\nopcache.validate_timestamps=0\nmemory_limit=256M\n' > /usr/local/etc/php/conf.d/trackeat.ini

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app

# Primero solo las dependencias, para que Docker reutilice esta capa si no cambian.
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY backend/ ./
RUN composer dump-autoload --optimize \
    && mkdir -p storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs bootstrap/cache

EXPOSE 8000
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000", "--no-reload"]
