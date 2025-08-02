# Start with the official PHP image
FROM php:8.4.1

# Set working directory inside the container
WORKDIR /var/www

# Install system dependencies and PHP extensions
# This is a good place to remove the lists to keep the image small
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

# Set ownership of all files to www-data so the user can run commands
RUN chown -R www-data:www-data /var/www

# Switch to the www-data user to run composer install and avoid the root warning
USER www-data

# Install application dependencies
RUN composer install --no-interaction --prefer-dist --optimize-autoloader

# Switch back to root to set permissions on critical directories
# This is necessary because the www-data user might not have permissions to do this.
USER root

# Grant write permissions for the web server on storage and cache directories
RUN chmod -R 775 /var/www/storage \
    && chmod -R 775 /var/www/bootstrap/cache

# Switch back to the www-data user for the final command
USER www-data

# Expose the application port
EXPOSE 8000

# Start the Laravel development server
# The host must be 0.0.0.0 to be accessible from the host machine
CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
