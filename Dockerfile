# Web server for the attendance system: PHP 8.2 + Apache.
# Used by compose.yaml; the site is served at http://localhost:8080/
FROM php:8.2-apache

# MySQL driver for PDO, URL rewriting for clean addresses, .htaccess support in the web root,
# and PHP's production settings (errors are logged, not shown to visitors).
RUN docker-php-ext-install pdo_mysql \
    && a2enmod rewrite \
    && printf '<Directory /var/www/html>\n    AllowOverride All\n</Directory>\nServerName localhost\n' \
        > /etc/apache2/conf-available/attendance.conf \
    && a2enconf attendance \
    && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

COPY --chown=www-data:www-data . /var/www/html/
