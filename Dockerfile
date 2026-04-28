FROM php:8.2-apache

# Enable PostgreSQL support
RUN docker-php-ext-install pdo pdo_pgsql

# Copy project files into server
COPY . /var/www/html/

# Set working directory
WORKDIR /var/www/html/

# Enable Apache rewrite (optional but useful)
RUN a2enmod rewrite

# Expose port
EXPOSE 80