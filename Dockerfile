# Multi-stage Dockerfile for Laravel with React/Inertia
FROM node:22-alpine AS node-builder

WORKDIR /app

# Copy package files
COPY package*.json ./
RUN npm install

# Copy source code and build assets
COPY . .

# Create missing wayfinder directories and files for Docker build
RUN mkdir -p resources/js/routes resources/js/actions resources/js/wayfinder

# Create a basic routes index file if it doesn't exist
RUN echo 'export const route = (name: string, params?: any) => `/${name}`; export const dashboard = () => "/dashboard"; export const home = () => "/";' > resources/js/routes/index.ts

# Build assets without PHP dependencies (disable wayfinder generation)
RUN DISABLE_WAYFINDER=true npm run build

# PHP base stage
FROM php:8.4-fpm-alpine AS php-base

# Install system dependencies
RUN apk add --no-cache \
    curl \
    zip \
    unzip \
    git \
    postgresql-dev \
    libpng-dev \
    libjpeg-turbo-dev \
    freetype-dev \
    oniguruma-dev \
    libxml2-dev \
    nginx \
    supervisor

# Install PHP extensions
RUN docker-php-ext-configure gd --with-freetype --with-jpeg
RUN docker-php-ext-install \
    pdo \
    pdo_pgsql \
    pgsql \
    mbstring \
    exif \
    pcntl \
    bcmath \
    gd \
    xml

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# Create application user
RUN addgroup -g 1000 -S laravel && \
    adduser -u 1000 -S laravel -G laravel

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer*.json ./

# Development stage
FROM php-base AS development

# Install development dependencies
RUN composer install --no-scripts --no-autoloader

# Copy application code
COPY . .

# Copy built assets from node builder
COPY --from=node-builder /app/public/build ./public/build

# Set permissions and generate autoloader
RUN composer dump-autoload --optimize && \
    chown -R laravel:laravel /var/www/html && \
    chmod -R 755 /var/www/html/storage && \
    chmod -R 755 /var/www/html/bootstrap/cache

# Copy nginx and supervisor configs
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]

# Production stage
FROM php-base AS production

# Install production dependencies only
RUN composer install --no-dev --optimize-autoloader --no-scripts

# Copy application code
COPY . .

# Copy built assets from node builder
COPY --from=node-builder /app/public/build ./public/build

# Optimize Laravel for production
RUN php artisan config:cache && \
    php artisan route:cache && \
    php artisan view:cache

# Set permissions
RUN chown -R laravel:laravel /var/www/html && \
    chmod -R 755 /var/www/html/storage && \
    chmod -R 755 /var/www/html/bootstrap/cache

# Copy nginx and supervisor configs
COPY docker/nginx/default.conf /etc/nginx/http.d/default.conf
COPY docker/supervisor/supervisord.conf /etc/supervisor/conf.d/supervisord.conf

EXPOSE 80

CMD ["/usr/bin/supervisord", "-c", "/etc/supervisor/conf.d/supervisord.conf"]