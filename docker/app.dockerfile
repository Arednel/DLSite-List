# Python 3.10 runtime stage
FROM python:3.10-slim AS python-runtime

# Main PHP application stage
FROM php:8.3-fpm

WORKDIR /var/www/dlsite_list

RUN apt-get update && apt-get install -y  \
    libfreetype6-dev \
    libjpeg-dev \
    libpng-dev \
    libwebp-dev \
    libzip-dev \
    unzip \
    --no-install-recommends \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo_mysql -j$(nproc) gd zip    

# Use the default production configuration for PHP runtime arguments
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Copy the app files from the app directory.
COPY . /var/www/dlsite_list

# Create directories
RUN mkdir -p \
    /var/www/dlsite_list/storage/app/public \
    /var/www/dlsite_list/storage/app/Works \
    /var/www/dlsite_list/storage/framework/cache \
    /var/www/dlsite_list/storage/framework/cache/data \
    /var/www/dlsite_list/storage/framework/sessions \
    /var/www/dlsite_list/storage/framework/views \
    /var/www/dlsite_list/storage/logs \
    /var/www/dlsite_list/bootstrap/cache

# Get Composer from the official Composer image
COPY --from=composer:lts /usr/bin/composer /usr/bin/composer
# Install Composer dependencies
RUN composer install

# Bring Python 3.10 into the PHP image
COPY --from=python-runtime /usr/local /usr/local

# Create python venv and install Python dependencies
RUN python3 -m venv /var/www/dlsite_list/python/venv \
    && /var/www/dlsite_list/python/venv/bin/pip install pip \
    && /var/www/dlsite_list/python/venv/bin/pip install -r /var/www/dlsite_list/python/requirements.txt

# Set permissions
RUN chown -R www-data:www-data /var/www/dlsite_list/storage /var/www/dlsite_list/bootstrap/cache \
    && chmod -R ug+rwx /var/www/dlsite_list/storage /var/www/dlsite_list/bootstrap/cache

# Copy entrypoint script
COPY docker/docker-app-entrypoint.sh /usr/local/bin/docker-app-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-app-entrypoint.sh