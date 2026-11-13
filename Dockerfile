# Use PHP with Apache
FROM php:8.2-apache

# Install system dependencies.
# libpq-dev is the only one our own extension needs; the official PHP image
# already links curl, mbstring and pdo, so their -dev packages are not required.
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    zip \
    unzip \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions.
# pdo, mbstring and curl are compiled into the official php:8.2-apache image,
# so recompiling them here only adds failure points. Only the PostgreSQL PDO
# driver needs building. The assertion catches a missing built-in early.
RUN docker-php-ext-install -j"$(nproc)" pdo_pgsql

# Verify the extensions the app relies on. Uses extension_loaded() rather than
# parsing `php -m`, whose module names are not all lowercase ("PDO" is upper).
RUN php -r 'foreach (["pdo", "pdo_pgsql", "mbstring", "curl", "fileinfo"] as $e) { if (!extension_loaded($e)) { fwrite(STDERR, "missing PHP extension: " . $e . PHP_EOL); exit(1); } } echo "required PHP extensions present" . PHP_EOL;'

# Configure PHP settings
RUN echo "upload_max_filesize = 50M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "max_execution_time = 300" >> /usr/local/etc/php/conf.d/uploads.ini

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Set working directory
WORKDIR /var/www/html

# Copy application files
COPY . /var/www/html/

# Set permissions for uploads directory
RUN chmod -R 755 /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads

# Configure Apache
ENV APACHE_DOCUMENT_ROOT=/var/www/html
RUN sed -ri 's|/var/www/html|${APACHE_DOCUMENT_ROOT}|g' /etc/apache2/sites-available/*.conf
RUN sed -ri 's|AllowOverride None|AllowOverride All|g' /etc/apache2/sites-available/*.conf

# Expose port 80
EXPOSE 80

# Start Apache in foreground
CMD ["apache2-foreground"]
