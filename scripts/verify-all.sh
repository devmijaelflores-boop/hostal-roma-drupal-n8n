#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
docker info >/dev/null
sh tests/smoke.sh
python3 tests/http_checks.py
python3 tests/api.py
python3 tests/hotel-security.py
python3 tests/hotel-concurrency.py
for test_script in hotel-domain payments automation commerce; do
  docker compose cp "tests/$test_script.php" "drupal:/tmp/roma-$test_script.php"
  docker compose exec -T drupal vendor/bin/drush php:script "/tmp/roma-$test_script.php"
done
python3 tests/n8n.py
python3 tests/n8n-reservation.py
deno run --cached-only --allow-all tests/full-browser.mjs
deno run --cached-only --allow-all tests/commerce-browser.mjs
docker compose exec -T drupal vendor/bin/drush core:requirements --severity=2
