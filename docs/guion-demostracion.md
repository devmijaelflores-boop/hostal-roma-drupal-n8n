# Guion de demostración para el profesor

Duración sugerida: 10–12 minutos. Usar solo nombres y correos ficticios. No presentar el proyecto como autorizado por el establecimiento. La plataforma elegida de la lista del docente es Drupal/PHP, con Drupal Commerce, n8n y Docker Compose.

1. Abre la portada y señala el aviso de prototipo no oficial. Explica que las fotografías y tarifas son ilustrativas.
2. Abre `/tienda`, elige dos habitaciones y agrega fechas futuras y huéspedes. Muestra `/carrito`: fechas peruanas, huéspedes, noches, tarifa y total en PEN. Quita una línea y agrégala otra vez.
3. Continúa al checkout de Commerce con datos ficticios. Revisa el resumen y ensaya rechazo o cancelación del pago de prueba: el carrito permanece y no se crean reservas. Después selecciona aprobar.
4. Muestra el recibo del pedido. Guarda en privado el código y la clave de cada solicitud. La aprobación es académica y las reservas siguen pendientes de decisión administrativa.
5. Inicia sesión administrativa y abre `/admin/roma` y `/admin/roma/pedidos`. Muestra inventario, tarifas, estadísticas y solicitudes. Confirma cada reserva y consulta su estado con código y clave. El flujo n8n actualiza el estado del pedido en su próxima ejecución.
6. Demuestra las reglas: una habitación no admite huéspedes por encima de su capacidad; no se confirma una reserva superpuesta; una llegada igual a la salida de otra sí está permitida. Explica que el pago del checkout no determina disponibilidad ni confirma reservas.
7. Pregunta al chatbot por una tarifa y por disponibilidad con fechas. Cambia una tarifa en el panel y vuelve a consultar para evidenciar la fuente de verdad.
8. Abre n8n en `localhost:5678` y muestra los tres flujos. El bot no necesita IA; los webhooks internos tienen autenticación.
9. Abre Mailpit en `localhost:8025` para enseñar avisos capturados y el reporte de la demo. No son correos enviados al negocio real.
10. Cancela las solicitudes, vuelve a consultar disponibilidad y muestra la actualización del pedido por n8n. Explica los límites: pago local de prueba, IA opcional sin activar y conector bancario externo pendiente. Los tres requisitos compartidos se presentan con su evidencia: CMS Drupal, chat n8n y servicios Docker Compose.

Comandos de pruebas integradas: `sh scripts/verify-all.sh`. No lo ejecutes sobre una operación real: crea datos ficticios y pruebas de disponibilidad. El conjunto de navegador deja una reserva ficticia cancelada con registro de pago simulado para revisión.
