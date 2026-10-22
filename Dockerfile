FROM php:8.4-fpm

# Set environment variables
ENV DEBIAN_FRONTEND=noninteractive \
    COMPOSER_ALLOW_SUPERUSER=1 \
    COMPOSER_VENDOR_DIR=/var/www/html/vendor

# Install system dependencies
RUN apt-get update && apt-get install -y --no-install-recommends \
    bash \
    git \
    unzip \
    curl \
    libpng-dev \
    libjpeg62-turbo-dev \
    libwebp-dev \
    zlib1g-dev \
    libfreetype6-dev \
    libzip-dev \
    libonig-dev \
    libicu-dev \
    mariadb-client \
    ca-certificates \
    && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg && \
    docker-php-ext-install -j$(nproc) \
    pdo_mysql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    intl \
    zip \
    opcache

# Configure PHP
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY docker/php.ini /usr/local/etc/php/conf.d/99-custom.ini
COPY docker/www.conf /usr/local/etc/php-fpm.d/www.conf

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Copy only composer files first (for better Docker layer caching)
COPY composer.json composer.lock* ./

# Install dependencies - skip scripts to avoid test command execution
RUN composer install \
    --no-dev \
    --optimize-autoloader \
    --no-interaction \
    --prefer-dist \
    --no-scripts

# Copy entire application
COPY . .

# Run post-install composer scripts safely (without 'test' command)
RUN composer run-script post-autoload-dump && \
    composer run-script post-update-cmd || true

# Set git safe directory
RUN git config --global --add safe.directory /var/www/html

# Create necessary directories and set permissions
RUN mkdir -p storage/logs bootstrap/cache && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 storage bootstrap/cache

# Clear all caches
RUN php artisan config:clear && \
    php artisan cache:clear && \
    php artisan view:clear && \
    php artisan route:clear || true

EXPOSE 9000

# Health check
HEALTHCHECK --interval=5s --timeout=3s --retries=5 \
    CMD php-fpm -t || exit 1

CMD ["php-fpm"]