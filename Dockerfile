# ---------------------------------------------------------------------------
# Aptitude Test Management System — application image
#
# Apache + PHP with only the mysqli extension, which is all this project needs.
# ---------------------------------------------------------------------------
FROM php:8.4-apache

# --- PHP extensions --------------------------------------------------------
RUN docker-php-ext-install -j"$(nproc)" mysqli \
    && docker-php-ext-enable mysqli

# --- PHP runtime configuration ---------------------------------------------
COPY docker/php.ini /usr/local/etc/php/conf.d/app.ini

# --- Apache hardening ------------------------------------------------------
# Disable directory listing and deny access to non-public files.
RUN a2enmod rewrite headers \
    && printf '%s\n' \
        '<Directory /var/www/html>' \
        '    Options -Indexes +FollowSymLinks' \
        '    AllowOverride None' \
        '    Require all granted' \
        '</Directory>' \
        > /etc/apache2/conf-available/app.conf \
    && a2enconf app

# --- Application code ------------------------------------------------------
WORKDIR /var/www/html
COPY --chown=www-data:www-data . .

# Runtime entrypoint (keeps the default Apache CMD).
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

EXPOSE 80

ENTRYPOINT ["entrypoint.sh"]
CMD ["apache2-foreground"]
