FROM php:8.2-apache

# Enable mod_rewrite
RUN a2enmod rewrite

# Allow .htaccess to work
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Set correct document root (IMPORTANT)
ENV APACHE_DOCUMENT_ROOT=/var/www/html

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf

# Copy your project
COPY . /var/www/html/

# Fix permissions (VERY IMPORTANT)
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html