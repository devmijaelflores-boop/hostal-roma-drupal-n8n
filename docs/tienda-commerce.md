# Tienda de estadías con Drupal Commerce

La entrega utiliza Drupal como la opción PHP de la consigna compartida. Drupal Commerce añade productos, variaciones, carrito, pedidos, checkout y pagos de prueba a la aplicación hotelera existente. Esta implementación corresponde a la opción Drupal; las otras plataformas enumeradas son alternativas del listado.

## Componentes y versiones

Composer resolvió `drupal/commerce` **3.3.14**, fijado con sus dependencias en `drupal/composer.lock`. Commerce 3.x admite Drupal 11 según el [proyecto oficial](https://www.drupal.org/project/commerce). Se conserva Drupal 11.4.12 y PHP 8.4; se incorpora la extensión BCMath requerida por Commerce. La instalación dentro de Docker comprueba los requisitos de Composer.

El módulo propio `roma_shop` adapta los productos de Commerce a las habitaciones existentes. Usa el carrito y el control de acceso de Commerce, un flujo de checkout con paneles propios y una pasarela académica que solo ofrece modo `test`. La arquitectura sigue el [sistema de checkout](https://docs.drupalcommerce.org/v2/developer-guide/checkout/) y la [API de pasarelas de Commerce](https://docs.drupalcommerce.org/v2/developer-guide/payments/creating-payment-gateway/).

## Recorrido disponible

| Pantalla | Función |
| --- | --- |
| `/tienda` | Catálogo del inventario activo con imágenes ilustrativas, capacidad y tarifa ficticia. |
| `/tienda/agregar` | Selección de habitación, llegada, salida y huéspedes mediante formulario protegido. |
| `/carrito` | Varias estadías, fechas y huéspedes por línea, noches, importes en PEN y eliminación mediante POST. |
| `/checkout/{pedido}` | Datos ficticios, revisión, resultado del pago de prueba y recibo privado. |
| `/admin/roma/pedidos` | Pedidos y sus solicitudes relacionadas; exige permiso administrativo de Commerce. |
| `/admin/commerce/orders` | Administración nativa de pedidos de Drupal Commerce. |

El carrito permite hasta seis estadías. Una línea representa una habitación y un intervalo de fechas; su cantidad corresponde al número de noches. El procesador de pedidos recalcula cantidad, tarifa y título desde los datos hoteleros. No acepta una cantidad arbitraria para cambiar la duración de una estadía.

El precio, las fechas y la disponibilidad se verifican al agregar y finalizar. No se permite agregar la misma habitación con fechas superpuestas dentro del mismo carrito. Las reservas confirmadas se vuelven a consultar antes de escribir. Continúa aplicándose el intervalo `[ingreso, salida)`.

## Persistencia y autorización

Cada habitación tiene una variación Commerce con SKU estable. `roma_shop_booking` enlaza cada línea del pedido con una solicitud hotelera; no publica datos de clientes. El total se calcula con precios del servidor, en PEN.

El pago local y todas las solicitudes del pedido se escriben dentro de una transacción, usando el mismo bloqueo InnoDB del inventario. Los identificadores de envío son deterministas por pedido y línea. Un fallo revierte todas las solicitudes y el pago. Un reenvío no genera otra aprobación ni solicitudes duplicadas.

El checkout anónimo añade token CSRF por pedido y paso, señuelo contra envíos automatizados, validación y límite de intentos. La ruta alternativa `/cart` redirige al carrito propio con fechas y protección CSRF explícita. Commerce comprueba la pertenencia del carrito y del recibo a la sesión o al usuario. Conocer el número del pedido no concede acceso desde otra sesión. El recibo conserva las claves privadas en la sesión del comprador; no se incluyen en el chatbot ni en las evidencias versionadas.

El resultado de prueba aprobado **no confirma habitaciones**. Cada solicitud permanece pendiente hasta la decisión administrativa. El pedido usa los estados carrito, solicitudes pendientes, reservas confirmadas y reservas canceladas.

## n8n y avisos

Los pedidos generan los mismos eventos transaccionales de reserva que el resto del sistema. El workflow `roma-automation.json` llama al endpoint protegido, procesa avisos en Mailpit y reconcilia el pedido con los estados de sus reservas. Cuando todas se confirman, el pedido refleja la confirmación; cuando todas se cancelan, refleja la cancelación. Los estados mixtos quedan pendientes de revisión.

El bot responde preguntas sobre tienda, carrito y checkout. Las consultas de tarifas y disponibilidad siguen resolviéndose en Drupal/MariaDB a través de n8n. Los tres flujos exportables siguen en `n8n/workflows/`.

## Pago académico y proveedor externo

El comprador puede elegir aprobar, rechazar o cancelar una operación de prueba. La aprobación registra una entidad de pago Commerce en modo `test`; rechazo y cancelación muestran un aviso, conservan el carrito y no crean reservas ni pagos aprobados. Los intentos quedan registrados en los datos privados del pedido. No se recopilan tarjetas ni se contacta a un proveedor bancario.

**La pasarela es local y académica. No es Mercado Pago, no realiza cobros ni emite comprobantes comerciales.** La consigna compartida exige tienda, CMS, chat n8n y Docker; no identifica un proveedor de pagos. Una integración externa requiere desarrollo del conector, cuenta y credenciales de prueba del estudiante, notificaciones HTTPS y pruebas con el proveedor. No se declara realizada esa integración.

## Arranque y pruebas

```bash
python3 scripts/init-env.py
sh scripts/start.sh
sh scripts/verify-all.sh
```

Al actualizar, el arranque detiene el contenedor Drupal anterior durante la instalación de módulos. Así evita que una imagen sin Commerce regenere cachés mientras cambia la base. La instalación conserva inventario y tarifas existentes. Para repetir solo las comprobaciones nuevas:

```bash
docker compose cp tests/commerce.php drupal:/tmp/roma-commerce.php
docker compose exec -T drupal vendor/bin/drush php:script /tmp/roma-commerce.php
deno run --cached-only --allow-all tests/commerce-browser.mjs
```

Las pruebas usan datos ficticios y cancelan sus solicitudes conservando el historial. La prueba de navegador utiliza las credenciales locales sin imprimirlas. Sus capturas muestran catálogo y carrito, sin claves privadas. Ver `verificacion-commerce-domain.json` y `verificacion-commerce-browser.json` para los resultados de ejecución.
