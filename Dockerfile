FROM php:8.2-apache

# Install PDO and MySQL extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache rewrite module
RUN a2enmod rewrite

# Copy all project files into Apache root
COPY . /var/www/html/

# Expose HTTP port
EXPOSE 80