FROM php:8.4.1

# install dependencies
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
    && docker-php-ext-install pdo_mysql mbstring zip exif pcntl bcmath

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www

# Copy existing application directory
COPY . .

# Set ownership
RUN chown -R www-data:www-data /var/www

# Set directory permissions to 775
RUN find /var/www -type d -exec chmod 775 {} \;

# Set file permissions to 664
RUN find /var/www -type f -exec chmod 664 {} \;

# Expose port
EXPOSE 8000

# Start Laravel dev server
CMD ["php", "artisan", "serve", "--host=127.0.0.1", "--port=8000"]