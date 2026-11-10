# Fase 3 — Gestión hotelera funcional

Se implementó el módulo `roma_booking` mediante Schema API, Database API, Form API, rutas y permisos de Drupal. MariaDB almacena tipos, habitaciones, tarifas en céntimos, servicios y sus asociaciones, estados, reservas, historial de cambios y mensajes. Los usuarios y roles pertenecen al sistema de acceso de Drupal. La semilla crea seis habitaciones ficticias y no importa clientes ni reservas del negocio real.

## Funciones disponibles

- `/disponibilidad`: consulta fechas y ocupación contra las reservas confirmadas de la demo.
- `/reservar`: guarda una solicitud pendiente y entrega código y clave privada de consulta.
- `/mi-reserva`: exige código y clave; devuelve estado, fechas y estimado, sin datos personales.
- `/contacto`: guarda mensajes de prueba para el administrador; no envía correos al negocio.
- `/admin/roma`: indicadores y acceso al panel.
- `/admin/roma/catalogo`: tipos, habitaciones, tarifas y servicios; permite crear, editar y desactivar.
- `/admin/roma/reservas`: revisión de las últimas 200 solicitudes y formularios POST para confirmar o cancelar.
- `/admin/roma/mensajes`: consulta protegida de mensajes de prueba.

La portada obtiene categorías, tarifas y servicios de las tablas hoteleras; la configuración visual antigua ya no es su fuente de catálogo. Cada habitación tiene un código propio. Las tarifas nuevas no alteran el precio estimado almacenado en solicitudes anteriores.

## Integridad y acceso

Ingreso inclusivo y salida exclusiva: un ingreso en la fecha de salida de otra reserva está permitido. Solo las confirmadas bloquean. Las pendientes no son bloqueos ni garantías de habitación. Antes de registrar y confirmar se comprueban fechas, capacidad, actividad y ocupación. Una transacción InnoDB bloquea una fila de coordinación antes de cualquier modificación de inventario o reserva; la comprobación y la actualización ocurren dentro de ese bloqueo. Se prioriza la consistencia sobre el paralelismo para esta demo académica.

No se permite reactivar una cancelada, reducir capacidad por debajo de una confirmada vigente ni confirmar una habitación desactivada. Se rechazan estadías de menos de una noche o más de 180 noches. Los importes se calculan en el servidor usando enteros en céntimos. Un identificador único impide repetir un mismo envío de reserva.

Los formularios de administración usan CSRF de Drupal. Los formularios anónimos crean una sesión con marcador sin datos personales y añaden una validación CSRF específica, honeypot y límites de intentos por identificador derivado de IP. Los registros duraderos de reserva guardan el hash de la clave de consulta; el secreto se muestra en el recibo del envío y nunca se añade a una URL o a los logs de prueba. El estado temporal del formulario puede conservar el recibo durante su sesión de presentación.

El rol `roma_manager` tiene permisos hoteleros; un usuario autenticado sin ese rol no obtiene acceso administrativo. No se expone ningún listado público de clientes. La API externa, n8n y los pagos aún no están implementados.

## Pruebas ejecutadas

- 21 pruebas de dominio contra MariaDB real: fechas inexistentes o pasadas, capacidad, ocupación fraccionaria, teléfono inválido, estados, cálculo exacto, privacidad, idempotencia, superposición, fechas contiguas, cambios de tarifa, cancelación y desactivación. Las fixtures temporales se eliminan al terminar.
- Dos procesos Drupal intentaron confirmar solicitudes superpuestas simultáneamente: exactamente uno confirmó y el otro fue rechazado. Se verificó una sola confirmada en la base.
- Un token CSRF anónimo manipulado fue rechazado sin crear reservas.
- Un usuario autenticado sin permisos fue rechazado en cuatro rutas de administración.
- Prueba de navegador de extremo a extremo: disponibilidad, solicitud pendiente, recibo, consulta privada, acceso al panel, confirmación, consulta de estado confirmado, cancelación y contacto. Se comprobó la adaptación móvil de los cuatro formularios públicos. Resultado en `verificacion-fase-3-browser.json`.

Las pruebas dejaron dos solicitudes ficticias canceladas y un mensaje de prueba para revisión. No corresponden a clientes reales. Durante el desarrollo se corrigieron la denominación del argumento `form_state` requerida por el resolvedor de formularios y la persistencia de sesiones anónimas, necesaria para los tokens. La prueba de navegador se corrigió para separar las sesiones del visitante y administrador.

## Comandos

```bash
docker compose build drupal
docker compose run --rm install
docker compose up -d --wait drupal
docker compose exec -T drupal vendor/bin/drush cache:rebuild
docker compose cp tests/hotel-domain.php drupal:/tmp/roma-hotel-domain.php
docker compose exec -T drupal vendor/bin/drush php:script /tmp/roma-hotel-domain.php
python3 tests/hotel-concurrency.py
python3 tests/hotel-security.py
deno run --cached-only --allow-all tests/hotel-browser.mjs
```

Para revisar manualmente, solicita una reserva usando datos ficticios, guarda código y clave, inicia sesión administrativa, confirma desde el panel y consulta nuevamente el estado con esas claves. Cancela después para liberar el inventario de prueba. No hay pagos ni reservas comerciales.

Guardar con Git el módulo, los cambios del tema y de instalación, las pruebas y la documentación. No guardar `.env`, sesiones, cachés o volúmenes. El repositorio todavía no tiene remoto de GitHub identificado.

## Aclaración de búsquedas por capacidad

Se añadió una explicación cuando los huéspedes solicitados superan la capacidad máxima de las habitaciones activas. La pantalla indica que la búsqueda corresponde a una sola habitación y ofrece ajustar la ocupación conservando fechas; para un grupo se requieren solicitudes independientes por habitación. No se cambió el inventario ni se simula capacidad adicional.

Verificación del 10/11/2026: la búsqueda del 11 al 15 de noviembre para tres huéspedes explica el límite actual de dos personas, no ofrece reservas incompatibles y conserva ambas fechas en el enlace de ajuste. Al consultar para dos huéspedes aparecen habitaciones y enlaces de solicitud. Se comprobó el enlace en navegador a 1440 y 390 píxeles, sin desbordamientos. Evidencia visual: `capturas/disponibilidad-capacidad.png`.
