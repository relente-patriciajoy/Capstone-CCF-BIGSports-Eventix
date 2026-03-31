FROM php:8.2-apache

# Install PHP extensions needed for Eventix
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Install GD for QR code image generation
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    default-mysql-client \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install gd \
    && rm -rf /var/lib/apt/lists/*

# Copy project files
COPY . /var/www/html/

# Set permissions
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && mkdir -p /var/www/html/eventsys/codes/qr_codes \
    && mkdir -p /var/www/html/eventsys/codes/backups \
    && chmod -R 777 /var/www/html/eventsys/codes/qr_codes \
    && chmod -R 777 /var/www/html/eventsys/codes/backups

# Set Apache document root to the PHP folder
ENV APACHE_DOCUMENT_ROOT=/var/www/html/eventsys/codes/php

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' \
    /etc/apache2/sites-available/000-default.conf \
    /etc/apache2/conf-available/docker-php.conf

# Enable mod_rewrite for .htaccess
RUN a2enmod rewrite

# Update Apache config to allow .htaccess overrides
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' \
    /etc/apache2/apache2.conf

EXPOSE 80