#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
test -f .env || { echo 'Ejecuta primero: python3 scripts/init-env.py' >&2; exit 1; }
docker info >/dev/null
docker compose config --quiet
docker compose build --pull drupal
docker compose up -d --wait mariadb
# Evita que la imagen anterior regenere cachés mientras se instalan módulos nuevos.
docker compose stop drupal
docker compose run --rm install
docker compose up -d --wait drupal n8n mailpit
docker compose exec -T drupal vendor/bin/drush cache:rebuild
python3 scripts/setup-n8n.py
sh tests/smoke.sh
python3 tests/api.py
python3 tests/http_checks.py
