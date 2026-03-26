FROM php:8.2-apache

# Install required PHP extensions for the project
RUN docker-php-ext-install pdo pdo_mysql

# Enable Apache mod_rewrite
RUN a2enmod rewrite

# Change Apache port to 10000 for Render compatibility
RUN sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/:80/:10000/' /etc/apache2/sites-available/000-default.conf

# Copy project files into the container
COPY . /var/www/html/

# Update permissions
RUN chmod -R 755 /var/www/html \
    && chown -R www-data:www-data /var/www/html

# Expose port 10000
EXPOSE 10000
