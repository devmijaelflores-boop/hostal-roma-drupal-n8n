FROM php:8.4-apache-bookworm

RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates git unzip libicu-dev libzip-dev libxml2-dev libpng-dev libjpeg62-turbo-dev libfreetype6-dev libonig-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" gd intl pdo_mysql zip opcache mbstring bcmath soap \
    && a2enmod rewrite headers \
    && rm -rf /var/lib/apt/lists/*

COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer
COPY docker/drupal/apache.conf /etc/apache2/sites-available/000-default.conf
COPY docker/drupal/php.ini /usr/local/etc/php/conf.d/roma.ini
WORKDIR /var/www/html
COPY drupal/composer.json drupal/composer.lock ./
ENV COMPOSER_ALLOW_SUPERUSER=1
RUN composer install --no-interaction --prefer-dist --no-progress --optimize-autoloader \
    && composer check-platform-reqs
COPY drupal/web/sites/default/settings.php web/sites/default/settings.php
COPY drupal/web/modules/custom/ web/modules/custom/
COPY drupal/web/themes/custom/ web/themes/custom/
COPY drupal/config/ config/
COPY docker/drupal/install.sh docker/drupal/configure.php docker/drupal/design.php docker/drupal/integrations.php docker/drupal/commerce.php /opt/roma/
RUN mkdir -p web/sites/default/files /var/www/private/sunat-beta \
    && php -r 'require "vendor/autoload.php"; Drupal\Component\FileSecurity\FileSecurity::writeHtaccess("config/sync", TRUE);' \
    && chown -R www-data:www-data web/sites/default/files /var/www/private \
    && chmod 700 /var/www/private/sunat-beta \
    && chmod 644 web/sites/default/settings.php
