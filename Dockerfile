FROM php:8.2-apache

# Copy the pre-downloaded extension installer to leverage Docker build caching
COPY install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions pdo pdo_mysql imap openssl

# Enable Apache modules used by the app and faster static asset delivery
RUN a2enmod rewrite headers expires deflate

RUN printf '%s\n' \
    '<IfModule mod_deflate.c>' \
    '  AddOutputFilterByType DEFLATE text/html text/plain text/css text/javascript application/javascript application/json image/svg+xml' \
    '</IfModule>' \
    '<IfModule mod_expires.c>' \
    '  ExpiresActive On' \
    '  ExpiresByType text/css "access plus 30 days"' \
    '  ExpiresByType application/javascript "access plus 30 days"' \
    '  ExpiresByType image/png "access plus 30 days"' \
    '  ExpiresByType image/jpeg "access plus 30 days"' \
    '  ExpiresByType image/svg+xml "access plus 30 days"' \
    '</IfModule>' \
    '<IfModule mod_headers.c>' \
    '  <FilesMatch "\.(css|js|png|jpg|jpeg|gif|svg|webp)$">' \
    '    Header set Cache-Control "public, max-age=2592000"' \
    '  </FilesMatch>' \
    '</IfModule>' \
    > /etc/apache2/conf-available/performance.conf \
    && a2enconf performance

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
