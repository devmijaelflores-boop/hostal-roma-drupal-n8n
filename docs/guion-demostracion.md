# Guion de demostración para el profesor

Duración sugerida: 8–10 minutos. Usar solo nombres y correos ficticios. No presentar el proyecto como autorizado por el establecimiento.

1. Abre la portada y señala el aviso de prototipo no oficial. Explica que las fotografías y tarifas son ilustrativas.
2. Muestra el catálogo y filtra las categorías. Consulta fechas futuras y huéspedes: el resultado se calcula sobre MariaDB, ingreso inclusivo y salida exclusiva.
3. Envía una solicitud con datos ficticios. Muestra el estado pendiente y guarda en privado el código y la clave. Una solicitud no es confirmación.
4. Inicia sesión administrativa y abre `/admin/roma`. Revisa inventario, tarifas, estadísticas, contenido y solicitudes.
5. Confirma la solicitud. Consulta su estado con código y clave. Intenta confirmar otra superpuesta para demostrar el rechazo; una llegada igual a la salida de otra reserva sí está permitida.
6. En una solicitud confirmada, abre `/pago-demo`. Simula un resultado; recalca que no existe dinero real ni comprobante comercial.
7. Pregunta al chatbot por una tarifa y por disponibilidad con fechas. Cambia una tarifa en el panel y vuelve a consultar para evidenciar la fuente de verdad.
8. Abre n8n en `localhost:5678` y muestra los tres flujos. El bot no necesita IA; los webhooks internos tienen autenticación.
9. Abre Mailpit en `localhost:8025` para enseñar avisos capturados y el reporte de la demo. No son correos enviados al negocio real.
10. Cancela la solicitud, vuelve a consultar disponibilidad y explica las limitaciones: IA opcional sin activar, pasarela externa pendiente de credenciales y despliegue comercial sujeto a aprobación.

Comandos de pruebas integradas: `sh scripts/verify-all.sh`. No lo ejecutes sobre una operación real: crea datos ficticios y pruebas de disponibilidad. El conjunto de navegador deja una reserva ficticia cancelada con registro de pago simulado para revisión.
