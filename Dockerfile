FROM php:8.2-apache

# Install required PHP extensions (including IMAP for reply inbox)
RUN apt-get update && apt-get install -y libc-client-dev libkrb5-dev && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-configure imap --with-kerberos --with-imap-ssl \
    && docker-php-ext-install pdo pdo_mysql imap

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

# Create a dynamic entrypoint to bind Apache to Render's $PORT variable at runtime
RUN echo '#!/bin/bash\n\
target_port=${PORT:-10000}\n\
sed -i "s/Listen .*/Listen $target_port/" /etc/apache2/ports.conf\n\
sed -i "s/<VirtualHost .*/<VirtualHost *:$target_port>/" /etc/apache2/sites-available/000-default.conf\n\
exec apache2-foreground' > /usr/local/bin/start.sh \
    && chmod +x /usr/local/bin/start.sh

# Use dynamic entrypoint instead of default foreground
CMD ["/usr/local/bin/start.sh"]
