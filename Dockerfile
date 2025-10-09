# Build stage for frontend assets with PHP
FROM php:8.4-alpine AS frontend

# Install Node.js
RUN apk add --no-cache nodejs npm

WORKDIR /app

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy all application files (needed for wayfinder)
COPY . .

# Install PHP dependencies (needed for artisan commands)
RUN composer install --no-dev --optimize-autoloader

# Install Node.js dependencies
RUN npm ci

# Build assets (wayfinder can now run php artisan commands)
RUN npm run build

# Production stage
FROM php:8.4-apache

# Install dependencies
RUN apt-get update && \
    apt-get install -y \
    libzip-dev \
    zip \
    libpq-dev \
    libicu-dev \
    && docker-php-ext-install intl zip \
    && rm -rf /var/lib/apt/lists/*

# Enable mod_rewrite
RUN a2enmod rewrite

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql pdo_pgsql zip
RUN pecl install redis && docker-php-ext-enable redis

# Set Apache document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Copy the application code
COPY . /var/www/html

# Set the working directory
WORKDIR /var/www/html

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install project dependencies
RUN composer install --optimize-autoloader --no-dev

# Copy built assets from frontend stage
COPY --from=frontend /app/public/build /var/www/html/public/build

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port
EXPOSE 80