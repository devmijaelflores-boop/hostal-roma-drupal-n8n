# Verificación de la mejora de recepción

Primera fase de ampliación tras el despliegue en AWS Academy: calendario, check-in, check-out, limpieza y mantenimiento. Conserva Drupal, MariaDB, Commerce y n8n; no incorpora otro CMS.

## Reglas verificadas

- Calendario protegido de 7, 14 o 31 noches, con separación entre solicitudes pendientes, reservas confirmadas y mantenimiento.
- Entrada solo de reservas confirmadas y vigentes, con habitación activa, lista y sin otro huésped sin salida.
- Salida solo tras entrada. Guarda hora, conserva historia y deja limpieza pendiente. Repetir una salida no vuelve a ensuciar una habitación ya preparada.
- Una salida anticipada libera inventario después de la limpieza requerida para hoy, sin modificar las fechas e importes comerciales originales ni efectuar devoluciones.
- No se cancelan estadías con entrada/salida registrada. No presentado cancela la reserva sin cobrar ni reembolsar.
- Mantenimiento sin superposición con confirmadas vigentes u otros bloqueos. Intervalos con salida exclusiva.
- Solicitar y confirmar revalidan mantenimiento y preparación de hoy. API, chatbot y Commerce usan la misma disponibilidad.
- Escrituras serializadas mediante el mismo bloqueo InnoDB del sistema de reservas; auditoría con usuario y hora. Los roles públicos no reciben permiso de recepción.

## Ejecución en EC2

Se aprobaron **29 comprobaciones de reglas de recepción**, ejecutadas dentro de una transacción revertida al terminar. Se volvieron a aprobar 15 de API, 13 de chat n8n y 18 de Commerce: **75 comprobaciones del servidor**, además de las verificaciones existentes de CSRF y confirmaciones simultáneas. Véase [evidencia del servidor](verificacion-recepcion-servidor.json).

La prueba de navegador sobre HTTPS comprobó acceso anónimo rechazado, acceso administrativo, periodo del calendario, llegada de hoy, ausencia de mutación en GET, rechazo del token manipulado, entrada, salida, limpieza, creación/liberación de mantenimiento reflejada en API y desplazamiento móvil. Los resultados están en [evidencia de navegador](verificacion-recepcion-browser.json). Los registros de ensayo se retiraron al terminar y se conservó la información previa.

## Repetir las pruebas

```bash
docker compose cp tests/reception.php drupal:/tmp/roma-reception.php
docker compose exec -T drupal vendor/bin/drush php:script /tmp/roma-reception.php
deno run --cached-only --allow-all tests/reception-browser.mjs
```

El navegador requiere Deno, Puppeteer disponible en caché y el ejecutable Brave del entorno de desarrollo, igual que las pruebas previas. Por defecto usa la instalación local; `ROMA_BROWSER_BASE` permite cambiar la URL. Las operaciones auxiliares de Docker deben apuntar al mismo servidor que esa URL. La verificación de AWS utilizó SSH para esas operaciones, sin publicar secretos.

## Instalación existente

Construye la imagen actualizada, detén Drupal durante la habilitación y conserva sus volúmenes:

```bash
docker compose build drupal
docker compose stop drupal
docker compose run --rm --no-deps --entrypoint vendor/bin/drush install en roma_reception --yes
docker compose up -d --wait drupal
docker compose exec -T drupal vendor/bin/drush cache:rebuild
```

La instalación del módulo crea tablas nuevas; no reemplaza las habitaciones ni las reservas existentes. El instalador general también lo habilita en instalaciones nuevas. El usuario raíz de Drupal puede acceder; el rol responsable hotelero recibe el permiso específico.

## Fases siguientes

Esta entrega corresponde a recepción y disponibilidad operativa. Los pagos continúan simulados y Mailpit captura las notificaciones. La siguiente fase debe separar cargos, adelantos, saldos y devoluciones antes de conectar una pasarela. La integración SUNAT debe comenzar en beta, con XML firmado, correlativos, respuestas CDR y estados auditables; la emisión de producción requiere la configuración y autorización del contribuyente. No se emiten comprobantes tributarios en esta fase.
