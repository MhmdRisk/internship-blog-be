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

# Copy the entire application source code
COPY . .

# Switch to the www-data user to run composer install and avoid the root warning
USER www-data

# Install application dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Switch back to root to manage file permissions and ownership
USER root

# IMPORTANT: Set robust permissions
# This command ensures www-data owns all files and directories,
# and then sets specific read/write/execute permissions.
RUN chown -R www-data:www-data /var/www \
    && find /var/www -type d -exec chmod 775 {} \; \
    && find /var/www -type f -exec chmod 664 {} \; \
    && chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache

# Switch back to the www-data user for the final command
USER www-data

# Expose the application port
EXPOSE 8000

# Start the Laravel development server
# The host must be 0.0.0.0 to be accessible from the host machine
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
