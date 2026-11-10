# Corrección del formulario de reserva — 10/11/2026

El formulario rechazaba un teléfono que contenía letras, pero asignaba el error a la habitación. El aviso aparecía en la parte superior y podía quedar fuera de la vista al enviar desde el final del formulario. No se registraba una solicitud inválida.

Se asocia cada error al campo correspondiente: fechas, huéspedes, nombre o teléfono. Se habilita el módulo oficial de Drupal `inline_form_errors`, se muestra una explicación junto al campo y se desplaza el foco hasta el primer error visible. Los datos ingresados se conservan para corregirlos. El teléfono incluye instrucciones y sigue siendo opcional.

Cuando el envío es válido, el recibo también recibe el foco. Se entrega el código de solicitud y su clave privada, y se muestra el estado pendiente. La confirmación sigue correspondiendo al administrador. Se conservan la validación del servicio, la protección CSRF, el límite de intentos y las comprobaciones de disponibilidad en MariaDB.

## Verificación ejecutada

`tests/booking-feedback.mjs` completó 16 comprobaciones en Brave sin errores JavaScript, con vistas de 1440 y 390 píxeles. Se reprodujo el teléfono con letras, se comprobaron errores junto a los campos, conservación de valores, foco y ausencia de escrituras inválidas. Al corregir los campos y dejar el teléfono vacío, se creó exactamente una solicitud pendiente en MariaDB y apareció el recibo con código y clave. La solicitud ficticia de esa prueba se canceló conservando su historial.

También pasaron la comprobación de CSRF manipulado sin escrituras y las pruebas HTTP de portada, acceso anónimo, login administrativo y n8n. Los cuatro servicios permanecieron saludables.

Evidencia: [resultados](verificacion-reserva-feedback.json) y [captura del error](capturas/reserva-error-telefono.png). No se publican claves privadas de solicitudes.

## Aplicar y repetir

```bash
docker compose build drupal
docker compose up -d --wait drupal
docker compose exec -T drupal vendor/bin/drush en inline_form_errors --yes
docker compose exec -T drupal vendor/bin/drush cache:rebuild
deno run --cached-only --allow-all tests/booking-feedback.mjs
python3 tests/hotel-security.py
python3 tests/http_checks.py
```

El navegador de pruebas requiere Brave en `/opt/brave-bin/brave` y Puppeteer en la caché de Deno, igual que las demás comprobaciones del proyecto. El recorrido envía tres formularios y utiliza los límites de intentos normales de la aplicación.

Para verificarlo manualmente, recargar `/reservar`, completar datos ficticios, escribir letras en el teléfono y enviar. Debe aparecer la explicación junto al teléfono. Dejarlo vacío o introducir un número ficticio válido y reenviar: deben mostrarse el recibo y el estado pendiente. Guardar el código y la clave privada para consultar `/mi-reserva`.
