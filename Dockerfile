# Start with the official PHP image
FROM php:8.4.1

# Set working directory inside the container
WORKDIR /var/www

# Install system dependencies and PHP extensions
RUN apt-get update && apt-get install -y \
    build-essential \
    libpng-dev \
    libjpeg62-turbo-dev \
    libfreetype6-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    curl \
    git \
    nano \
    libzip-dev \
    libpq-dev \
    && rm -rf /var/lib/apt/lists/* \
    && docker-php-ext-configure gd --with-jpeg --with-freetype \
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Copy composer.json and composer.lock files first to leverage Docker cache
COPY composer.json composer.lock ./

# Install application dependencies
# This layer is cached and only re-run if composer.json or composer.lock change
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Copy the rest of the application code
COPY . .

# Grant permissions for the web server to write to the storage and cache directories
RUN chown -R www-data:www-data /var/www \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache

# Switch to the www-data user for security
USER www-data

# Expose the application port
EXPOSE 8000

# Start the Laravel development server
# The host must be 0.0.0.0 to be accessible from the host machine
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
