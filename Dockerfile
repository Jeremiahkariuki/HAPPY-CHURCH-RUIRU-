FROM php:8.2-apache

# Install required PHP extensions for the project
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Copy project files into the container
COPY . /var/www/html/

# Update permissions for the uploads and logs directories
RUN chmod -R 755 /var/www/html \
    && chown -R www-data:www-data /var/www/html

# Expose port 80
EXPOSE 80
