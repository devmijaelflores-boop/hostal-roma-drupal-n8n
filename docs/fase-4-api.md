# Fase 4 — API segura

Objetivo: exponer catálogo y disponibilidad de demostración, registrar solicitudes autorizadas y consultar estado con una clave privada, sin listados de clientes.

Archivos: módulo `roma_api` con rutas, proveedor de autenticación Bearer asociado a un usuario Drupal, controlador JSON y permisos; script de aprovisionamiento `docker/drupal/integrations.php`; variables de ejemplo en `.env.example`; pruebas `tests/api.py`; contrato en `docs/api.md`.

Se ejecutaron comprobaciones de sintaxis PHP, compilación Python y validación Compose. Después de activar Docker se construyó la imagen, se instaló el módulo y pasaron 15 pruebas HTTP de contrato y seguridad con MariaDB. La evidencia se guarda en `verificacion-api.json`.
Comandos después de iniciar el motor:

```bash
docker compose build drupal
docker compose run --rm install
docker compose up -d --wait drupal
docker compose exec -T drupal vendor/bin/drush cache:rebuild
python3 tests/api.py
```

Guardar con Git el módulo, pruebas, aprovisionamiento, Compose y documentación. No guardar `.env`, secretos de integración ni datos de bases.
