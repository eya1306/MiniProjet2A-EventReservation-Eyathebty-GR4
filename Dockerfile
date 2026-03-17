FROM php:8.2-fpm

# System dependencies
RUN apt-get update && apt-get install -y \
    libpq-dev \
    libzip-dev \
    unzip \
    git \
    && rm -rf /var/lib/apt/lists/*

# PHP extensions
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    zip \
    opcache

# Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Non-root user
RUN groupadd -g 1000 appuser && useradd -u 1000 -g appuser -m appuser

WORKDIR /var/www

# Copy composer files first (cache layer)
COPY --chown=appuser:appuser composer.json composer.lock ./
RUN composer install --no-scripts --no-autoloader --ignore-platform-reqs --no-interaction 2>/dev/null || true

# Copy application source
COPY --chown=appuser:appuser . .

# Finish composer setup
RUN composer dump-autoload --optimize --ignore-platform-reqs 2>/dev/null || true

# Writable directories
RUN mkdir -p var/cache var/log public/uploads/events \
    && chown -R appuser:appuser var public/uploads

USER appuser

EXPOSE 9000
CMD ["php-fpm"]
