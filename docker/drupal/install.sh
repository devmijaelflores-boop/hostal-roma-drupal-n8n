#!/bin/sh
set -eu
cd /var/www/html
if vendor/bin/drush status --fields=bootstrap --format=list 2>/dev/null | grep -q Successful; then
  vendor/bin/drush php:script /opt/roma/configure.php
  vendor/bin/drush php:script /opt/roma/design.php
  vendor/bin/drush en inline_form_errors roma_booking roma_api roma_payments roma_shop roma_reception roma_finance roma_sunat_beta --yes
  vendor/bin/drush cache:rebuild
  vendor/bin/drush php:script /opt/roma/integrations.php
  vendor/bin/drush php:script /opt/roma/commerce.php
  vendor/bin/drush cache:rebuild
  chown -R www-data:www-data web/sites/default/files /var/www/private/sunat-beta
  chmod 700 /var/www/private/sunat-beta
  echo 'Drupal ya está instalado; no se sobrescribe la base de datos.'
  exit 0
fi
# No se reinstala una base parcialmente creada. Se exige revisión manual.
php -r '
$pdo = new PDO("mysql:host=".getenv("DB_HOST").";dbname=".getenv("DB_NAME"), getenv("DB_USER"), getenv("DB_PASSWORD"));
if ($pdo->query("SHOW TABLES")->fetchColumn() !== false) {
  fwrite(STDERR, "Base no vacía sin bootstrap correcto. Revisar antes de instalar.\n"); exit(1);
}'
test "$DRUPAL_ADMIN_PASSWORD" != CHANGE_ME
vendor/bin/drush site:install standard --yes --locale=es \
  --site-name='Hostal ROMA — Prototipo académico' \
  --account-name="$DRUPAL_ADMIN_USER" \
  --account-pass="$DRUPAL_ADMIN_PASSWORD" \
  --account-mail="$DRUPAL_ADMIN_EMAIL" \
  --site-mail="$DRUPAL_ADMIN_EMAIL"
vendor/bin/drush php:script /opt/roma/configure.php
vendor/bin/drush php:script /opt/roma/design.php
  vendor/bin/drush en inline_form_errors roma_booking roma_api roma_payments roma_shop roma_reception roma_finance roma_sunat_beta --yes
  vendor/bin/drush cache:rebuild
  vendor/bin/drush php:script /opt/roma/integrations.php
  vendor/bin/drush php:script /opt/roma/commerce.php
chown -R www-data:www-data web/sites/default/files /var/www/private/sunat-beta
chmod 700 /var/www/private/sunat-beta
vendor/bin/drush cache:rebuild
