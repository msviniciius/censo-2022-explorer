FROM composer@sha256:9715c7f69044da2a212a5fbde29ee7da24e364d426560ae6367b060236f847d7 AS composer-bin

FROM php:8.4-fpm-bookworm@sha256:43e1ac38217031dbbecae60e84ccf8593722031559178d199bf56adb0145d5d0 AS php-base
RUN apt-get update \
    && apt-get install -y --no-install-recommends libicu-dev unzip \
    && docker-php-ext-install intl \
    && rm -rf /var/lib/apt/lists/*
COPY --from=composer-bin /usr/bin/composer /usr/local/bin/composer
WORKDIR /var/www/html
ENV COMPOSER_ALLOW_SUPERUSER=1

FROM php-base AS backend-deps
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-dev --no-scripts --prefer-dist --no-interaction --no-progress

FROM backend-deps AS app
COPY backend/ ./
RUN composer dump-autoload --no-dev --optimize \
    && mkdir -p storage/app storage/framework/cache/data storage/framework/sessions storage/framework/views storage/logs \
    && chown -R www-data:www-data storage bootstrap/cache
COPY docker/app-entrypoint.sh /usr/local/bin/app-entrypoint
COPY docker/check-census.php /usr/local/bin/check-census.php
RUN chmod +x /usr/local/bin/app-entrypoint
ENTRYPOINT ["app-entrypoint"]
CMD ["php-fpm"]

FROM php-base AS backend-test
COPY backend/composer.json backend/composer.lock ./
RUN composer install --no-scripts --prefer-dist --no-interaction --no-progress
COPY backend/ ./
RUN composer dump-autoload --optimize
CMD ["php", "vendor/bin/pest"]

FROM node:24-bookworm-slim@sha256:0e0ff40c39bc087845bfb27465a0df4ea419520094bc35842ff83dd8cbe6f9b6 AS frontend-deps
WORKDIR /app
COPY frontend/package.json frontend/package-lock.json ./
RUN npm ci

FROM frontend-deps AS frontend-build
COPY frontend/ ./
RUN npm run build

FROM frontend-build AS frontend-test
CMD ["npm", "test"]

FROM nginx:stable-alpine@sha256:985220252f3863977e468f611ef118ebd01421289dd86ee1ae99cb068c3bce2b AS web
COPY docker/nginx.conf /etc/nginx/conf.d/default.conf
COPY --from=frontend-build /app/dist/ /usr/share/nginx/html/
