# Verificación final de la continuación — 10/11/2026

## Resultado

La demo local está funcionando. Docker se inició, la imagen se construyó con los requisitos Composer completos y se instalaron `roma_api` y `roma_payments` sobre la base existente. Se importaron las credenciales internas sin imprimirlas y se publicaron los tres workflows n8n. Drupal, MariaDB, n8n y Mailpit quedaron activos y saludables.

| Grupo | Evidencia observada |
| --- | --- |
| API | 15 comprobaciones: catálogo, disponibilidad, origen, fechas, autenticación, JSON, manipulación de precio/estado, consulta privada y ausencia de listados públicos. |
| Reservas | 21 pruebas de dominio con MariaDB, incluidas superposición, fechas contiguas, capacidad, idempotencia y cambios de tarifas. |
| Seguridad | CSRF manipulado rechazado sin crear reservas. Dos confirmaciones simultáneas: exactamente una aceptada. |
| Chat n8n | 12 comprobaciones de respuestas y seguridad; una tarifa modificada temporalmente en MariaDB se reflejó en el bot y se restauró. |
| Reserva por n8n | Webhook privado creó una pendiente con precio calculado por Drupal; reenvío no duplicó la solicitud. |
| Avisos | Captura local de solicitud, confirmación, recordatorio y cancelación; repetición sin duplicar eventos. |
| Programación | Se observaron ejecuciones n8n con `mode=trigger` y `status=success`. |
| Pagos ficticios | Seis pruebas, sin tarjetas ni dinero real. |
| Navegador | 28 comprobaciones del widget, reserva, panel, consulta privada, pago simulado, cancelación y formularios a 1440, 768, 390 y 360 píxeles; cero errores JavaScript. |
| Persistencia | Recreación completa sin eliminar volúmenes; identidad del sitio, páginas, archivo de prueba y usuarios SQLite conservados. |
| Requisitos Drupal | Informe de severidad de error sin incidencias. |

Resultados legibles por herramientas: `verificacion-api.json`, `verificacion-n8n.json`, `verificacion-final-browser.json` y `verificacion-ejecucion.json`. Capturas: `capturas/final-1440.png`, `capturas/final-390.png` y `capturas/panel-final.png`. La página pública y el panel se revisaron visualmente.

## Incidencia de la prueba de navegador

La primera pasada se detuvo al llegar al simulador de pagos porque el test no seleccionaba el resultado requerido. El navegador impidió enviar el formulario, correctamente. Se corrigió la prueba para elegir explícitamente la aprobación de demostración y se repitió el recorrido completo con éxito. La fixture confirmada de esa pasada se canceló sin borrar su historial.

## Límites

Los correos se capturan en Mailpit, no se envían a personas reales. El pago es un simulador y no equivale a una pasarela externa ni a boleta/factura. La alternativa OpenAI está desactivada y no se probó contra un proveedor por ausencia de credenciales. No hay remoto GitHub identificado y no se publicó el proyecto. Se conservaron la propuesta Word/PDF y sus datos académicos.

## Repetir

```bash
sh scripts/verify-all.sh
python3 tests/persistence.py
```

Las pruebas generan datos ficticios y el navegador deja una solicitud cancelada con una aprobación simulada para revisión. No utilizar el conjunto sobre una operación comercial.
