# Verificación de la tienda académica — 10/11/2026

## Resultado

La opción implementada es Drupal/PHP con Drupal Commerce, chat integrado con n8n y Docker Compose. La tienda permite elegir estadías, reunirlas en un carrito, completar un checkout, ensayar resultados de pago y consultar las solicitudes generadas. La confirmación hotelera sigue protegida por los permisos de Drupal.

| Parte de la consigna | Implementación | Evidencia |
| --- | --- | --- |
| Tienda online | Catálogo, carrito, varias estadías, checkout, pedidos y pasarela local de prueba | `verificacion-commerce-browser.json` |
| CMS y PHP | Drupal 11.4.12, Commerce 3.3.14 y módulos propios | `composer.lock`, `roma_shop`, `roma_booking` |
| Chat con n8n | Widget oficial, consultas a Drupal, orientación hacia tienda y carrito | `verificacion-n8n.json` |
| Automatizaciones | Avisos capturados en Mailpit y conciliación entre reservas y pedidos por n8n | Prueba de confirmación/cancelación en navegador |
| Docker Compose | Drupal, MariaDB, n8n y Mailpit, con datos persistentes | Recreación completa sin eliminar volúmenes |
| Control de acceso | Sesiones separadas, recibo privado, CSRF, permisos y límites de intentos | Pruebas API, de navegador y de seguridad |
| Documentación | Guía de uso, instalación, arquitectura, API y guion para el profesor | Archivos en `docs/` |

## Comprobaciones ejecutadas

- **18 comprobaciones de integración Commerce:** total en PEN, manipulación de cantidades/precios/importes, consentimiento, pago test, solicitudes pendientes, reenvío sin duplicados, cambios administrativos y reversión completa si falla una línea.
- **37 comprobaciones de navegador:** catálogo, carrito, eliminación, fechas peruanas, alias protegido, checkout, datos inválidos, aprobación/rechazo/cancelación, aislamiento de sesiones, recibo privado, permisos administrativos y sincronización n8n. Se verificaron páginas reales con HTTP 200 a 1440, 768 y 390 píxeles, antes de cancelar el pedido. Cero errores JavaScript.
- **Regresión completa:** pasaron las 15 comprobaciones API, 21 hoteleras, seis pagos individuales, 13 de chat, dos de reserva n8n, 28 del navegador anterior, prueba CSRF y confirmaciones concurrentes.
- **Persistencia:** `down` y `up` sin `-v` conservaron productos, pedidos, pagos, enlaces de reservas, datos Drupal, archivos y usuarios SQLite n8n.
- **Plataforma:** requisitos Composer correctos, BCMath disponible, informe Drupal sin incidencias de severidad de error y cuatro servicios saludables.

Las fixtures se cancelaron conservando su historial. Las evidencias no incluyen claves privadas de consulta. Las capturas del catálogo y carrito se revisaron visualmente. La propuesta académica Word/PDF existente se conservó.

## Incidencias resueltas

El PHP del equipo carecía de GD; la resolución Composer se realizó dentro de Docker. Commerce también requería BCMath, incorporado a la imagen. Durante la primera activación, la imagen anterior regeneró una caché sin los campos de Commerce. Se reconstruyó la caché con la imagen nueva y el arranque ahora detiene Drupal durante la instalación de módulos. No se reinstaló ni se vació MariaDB.

La revisión visual detectó que el refresco de Commerce reemplazaba el título de las líneas y ocultaba sus fechas. El procesador propio ahora conserva habitación, fechas y huéspedes en el resumen. La ruta `/cart` redirige al carrito con controles anónimos explícitos.

Dos primeras comprobaciones de integración usaban nombres de métodos ajenos a esta versión de Commerce; se corrigieron contra sus interfaces instaladas. La prueba también se ajustó para recargar la versión del pedido actualizada por el subsistema de pagos. La pasada final terminó sin esos errores.

## Alcance del pago

El pago es **local y académico**, guardado en Commerce en modo `test`. No se realizó una llamada a Mercado Pago ni a otro proveedor bancario. No hay cargos, tarjetas ni comprobantes comerciales. La integración bancaria externa y OpenAI siguen fuera de la ejecución verificada; requerirían credenciales y pruebas propias.

## Repetir y presentar

```bash
sh scripts/start.sh
sh scripts/verify-all.sh
python3 tests/persistence.py
```

Para la presentación, sigue [la guía de la tienda](guia-tienda.md) y [el guion del profesor](guion-demostracion.md). Las fuentes técnicas y versiones se explican en [tienda Commerce](tienda-commerce.md).
