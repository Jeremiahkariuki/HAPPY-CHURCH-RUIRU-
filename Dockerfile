FROM php:8.2-apache

# Copy the pre-downloaded extension installer to leverage Docker build caching
COPY install-php-extensions /usr/local/bin/

RUN chmod +x /usr/local/bin/install-php-extensions && \
    install-php-extensions pdo pdo_mysql imap openssl opcache

# ── OPcache: dramatically speeds up PHP on Render ──
RUN { \
    echo 'opcache.enable=1'; \
    echo 'opcache.enable_cli=0'; \
    echo 'opcache.memory_consumption=128'; \
    echo 'opcache.interned_strings_buffer=16'; \
    echo 'opcache.max_accelerated_files=10000'; \
    echo 'opcache.revalidate_freq=0'; \
    echo 'opcache.validate_timestamps=0'; \
    echo 'opcache.fast_shutdown=1'; \
    echo 'opcache.save_comments=1'; \
} > /usr/local/etc/php/conf.d/opcache-recommended.ini

# ── PHP tuning for responsiveness ──
RUN { \
    echo 'memory_limit=256M'; \
    echo 'max_execution_time=60'; \
    echo 'upload_max_filesize=20M'; \
    echo 'post_max_size=22M'; \
    echo 'session.gc_maxlifetime=7200'; \
    echo 'realpath_cache_size=4096K'; \
    echo 'realpath_cache_ttl=600'; \
} > /usr/local/etc/php/conf.d/church-tuning.ini

# Enable Apache modules used by the app and faster static asset delivery
RUN a2enmod rewrite headers expires deflate http2

# Apache MPM event for concurrent connections on Render free tier
RUN a2dismod mpm_prefork && a2enmod mpm_event || true

# Performance + compression config
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
    '  ExpiresByType image/webp "access plus 30 days"' \
    '  ExpiresByType image/svg+xml "access plus 30 days"' \
    '</IfModule>' \
    '<IfModule mod_headers.c>' \
    '  <FilesMatch "\.(css|js|png|jpg|jpeg|gif|svg|webp)$">' \
    '    Header set Cache-Control "public, max-age=2592000, immutable"' \
    '  </FilesMatch>' \
    '  Header always set X-Content-Type-Options "nosniff"' \
    '  Header always set X-Frame-Options "SAMEORIGIN"' \
    '  Header always set Referrer-Policy "same-origin"' \
    '</IfModule>' \
    > /etc/apache2/conf-available/performance.conf \
    && a2enconf performance

# ── Configure Apache to use Render's dynamic ${PORT} environment variable natively ──
# Render sets the $PORT variable at runtime. We default it to 10000 if not set.
ENV PORT=10000
RUN sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
    && sed -i 's/:80/:${PORT}/' /etc/apache2/sites-available/000-default.conf

# Enable AllowOverride for .htaccess in document root
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Copy project files into the container
COPY . /var/www/html/

# Update permissions and create writable dirs for uploads + logs
RUN chmod -R 755 /var/www/html \
    && chown -R www-data:www-data /var/www/html \
    && mkdir -p /var/www/html/uploads/gallery /var/www/html/logs \
    && chmod -R 775 /var/www/html/uploads /var/www/html/logs \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/logs

# Use the standard Apache command (natively uses the configuration updated above)
CMD ["apache2-foreground"]
