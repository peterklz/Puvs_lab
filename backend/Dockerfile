FROM php:8.1-apache

# Install PostgreSQL PDO extension
RUN apt-get update && apt-get install -y \
    libpq-dev libzip-dev git \
    && docker-php-ext-install pdo pdo_pgsql zip \
    && rm -rf /var/lib/apt/lists/*

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy source code
COPY . /var/www/html/

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Install PHP dependencies
WORKDIR /var/www/html
RUN composer install --no-dev --optimize-autoloader

# Set permissions
RUN chown -R www-data:www-data /var/www/html

# Configure Apache
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/slim.conf \
    && a2enconf slim

EXPOSE 80