# Use PHP with Apache
FROM php:8.2-apache

# Install dependencies
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libpq-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PostgreSQL PDO extension (replaces pdo_mysql)
RUN docker-php-ext-install pdo pdo_pgsql gd

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Set permissions for uploads directory
RUN chmod -R 755 /var/www/html/uploads

# Configure Apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri 's|/var/www/html|${APACHE_DOCUMENT_ROOT}|g' /etc/apache2/sites-available/*.conf
RUN sed -ri 's|AllowOverride None|AllowOverride All|g' /etc/apache2/sites-available/*.conf
