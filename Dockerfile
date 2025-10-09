# Build stage for frontend assets with PHP
FROM php:8.4-alpine AS frontend

# Install Node.js and required dependencies for PHP extensions
RUN apk add --no-cache nodejs npm \
    icu-dev \
    libzip-dev \
    zip

# Install PHP extensions
RUN docker-php-ext-install intl zip

WORKDIR /app

# Install composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Copy all application files
COPY . .

# Remove any cached bootstrap files
RUN rm -f bootstrap/cache/*.php

# Install PHP dependencies
RUN composer install --no-dev --optimize-autoloader

# Install Node.js dependencies
RUN npm ci

# Build assets
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

# Copy the application code
COPY . .

# Remove any cached bootstrap files that might have been copied
RUN rm -f bootstrap/cache/*.php

# Install project dependencies
RUN composer install --optimize-autoloader --no-dev

# Copy built assets from frontend stage
COPY --from=frontend /app/public/build ./public/build

# Set permissions
RUN chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# Expose port
EXPOSE 80

# Start Apache
CMD ["apache2-foreground"]