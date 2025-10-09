# Build stage for frontend assets with PHP
FROM php:8.4-alpine AS frontend

# Install Node.js and required dependencies for PHP extensions
RUN apk add --no-cache nodejs npm \
    icu-dev \
    libzip-dev \
    zip

# Install PHP extensions (Alpine uses docker-php-ext-install)
RUN docker-php-ext-install intl zip

WORKDIR /app

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy composer files first
COPY composer.json composer.lock ./

# Install PHP dependencies (needed for artisan commands)
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy all application files (needed for wayfinder)
COPY . .

# Run post-install scripts now that all files are present
RUN composer dump-autoload --optimize

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
    && docker-php-ext-install intl zip pdo_mysql pdo_pgsql \
    && rm -rf /var/lib/apt/lists/*

# Install Redis extension
RUN pecl install redis && docker-php-ext-enable redis

# Enable mod_rewrite
RUN a2enmod rewrite

# Set Apache document root
ENV APACHE_DOCUMENT_ROOT=/var/www/html/public
RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf
RUN sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf

# Set the working directory
WORKDIR /var/www/html

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy composer files first
COPY composer.json composer.lock ./

# Install project dependencies
RUN composer install --optimize-autoloader --no-dev

# Copy the rest of the application code
COPY . .

# Copy built assets from frontend stage
COPY --from=frontend /app/public/build ./public/build

# Generate optimized autoload files
RUN composer dump-autoload --optimize

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]