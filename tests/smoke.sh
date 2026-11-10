#!/bin/sh
set -eu
cd "$(dirname "$0")/.."
docker compose config --quiet
docker compose exec -T drupal composer check-platform-reqs
docker compose exec -T drupal vendor/bin/drush status --fields=drupal-version,bootstrap,db-status --format=table
docker compose exec -T drupal vendor/bin/drush php:eval 'if (!\Drupal::database()->query("SELECT 1")->fetchField()) { throw new \RuntimeException("BD inaccesible"); } echo "Consulta SQL real: OK\n";'
docker compose exec -T drupal php -r '$s=file_get_contents("http://localhost/"); if (!str_contains($s,"Prototipo académico no oficial. Habitaciones, disponibilidad y tarifas de demostración")) {fwrite(STDERR,"Aviso ausente\n");exit(1);} echo "Portada y aviso: OK\n";'
docker compose exec -T drupal php -r 'if (@file_get_contents("http://n8n:5678/healthz/readiness") === false) exit(1); echo "Drupal → n8n: OK\n";'
docker compose exec -T n8n node -e 'fetch("http://drupal/user/login").then(r=>{if(!r.ok) process.exit(1); console.log("n8n → Drupal: OK")}).catch(()=>process.exit(1))'
docker compose exec -T n8n n8n --version
docker compose ps
