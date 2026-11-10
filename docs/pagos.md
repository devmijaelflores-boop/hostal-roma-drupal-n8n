# Pagos académicos y pasarela externa

La entrega también incluye la pasarela académica del checkout de **Drupal Commerce**. Consulta [tienda y pagos de prueba](tienda-commerce.md). Ese flujo permite registrar varias solicitudes en un pedido y conserva las reservas pendientes hasta su confirmación administrativa. La ruta `/pago-demo` descrita a continuación se mantiene para solicitudes individuales confirmadas.

La ruta `/pago-demo` permite simular un pago aprobado, rechazado o cancelado sobre una reserva confirmada. Exige código, clave privada, sesión CSRF y consentimiento explícito. No solicita tarjetas, credenciales bancarias, Yape o Plin, no contacta a una pasarela y no cobra dinero. Los registros usan estados `demo_approved`, `demo_declined` y `demo_cancelled`; el código `DEMO-...` no es boleta ni factura.

La simulación se guarda en MariaDB, valida que la reserva siga confirmada dentro del mismo bloqueo de escritura, toma el importe del servidor y evita duplicar una aprobación mediante reenvíos. La consulta privada muestra el último resultado. Cancelar una reserva no genera un reembolso: en este entorno nunca existió un cargo.

## Mercado Pago

La integración con una pasarela externa no puede declararse funcional sin una cuenta del estudiante y credenciales de prueba válidas. No se creó una cuenta, no se adivinaron tokens y no se utilizaron credenciales de otro proyecto. La [documentación oficial para pruebas de Checkout Pro en Perú](https://www.mercadopago.com.pe/developers/en/docs/checkout-pro-preferences/integration-test/test-purchases?scope=prod) se consultó como referencia.

Antes de añadir esa integración será necesario acordar la cuenta, moneda PEN, canal HTTPS para notificaciones, validación de firmas, verificación servidor a servidor de estado/importe/moneda y pruebas de duplicados, rechazo, cancelación y reembolso. No debe marcarse una reserva como pagada a partir de parámetros de regreso del navegador. Los datos personales y las credenciales de la pasarela permanecerán fuera del JSON público y Git.

Alcance de esta entrega: tienda de estadías con carrito, checkout y pagos académicos persistentes; la pasarela externa queda pendiente de conector, credenciales y pruebas. No se realizan cobros reales.

## Verificación local

Pasaron seis pruebas de integración de pagos ficticios: pendiente rechazada, clave privada incorrecta rechazada, rechazo simulado, importe calculado en el servidor, aprobación sin duplicación y rechazo tras cancelación. En navegador se completó una aprobación de demostración usando una reserva confirmada. No se realizó ninguna llamada a una pasarela externa.
