FROM php:8.4-fpm-alpine

# Dependências do sistema
RUN apk add --no-cache \
    bash \
    curl \
    git \
    unzip \
    libpq-dev \
    libzip-dev \
    oniguruma-dev \
    supervisor

# Extensões PHP
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    zip \
    pcntl \
    bcmath \
    opcache

# Redis extension (reinstall build deps; docker-php-ext-install purges them)
RUN apk add --no-cache --virtual .redis-build-deps $PHPIZE_DEPS \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .redis-build-deps

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Configuração PHP
COPY .docker/php/local.ini /usr/local/etc/php/conf.d/local.ini
COPY .docker/php/www.conf /usr/local/etc/php-fpm.d/www.conf

# Supervisor (app + horizon + scheduler)
COPY .docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

WORKDIR /var/www/html

# Copiar dependências primeiro (cache de layer)
COPY composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --prefer-dist

# Copiar aplicação
COPY . .

# Finalizar composer
RUN composer dump-autoload --optimize

# Permissões
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

EXPOSE 9000

CMD ["php-fpm"]
