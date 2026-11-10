# Fase 6 — IA opcional, desactivada por defecto

La demo completa no requiere IA ni claves externas. El flujo principal `roma-chat.json` es determinista: Drupal construye respuestas a partir de MariaDB. El flujo adicional `n8n/workflows/optional/chat-openai-optional.json` sirve como alternativa: OpenAI clasifica la intención en una etiqueta JSON y Drupal vuelve a consultar la fuente de verdad. Ningún texto, precio, fecha o confirmación generado por el modelo llega directamente al cliente.

Se eligió el contrato Responses API con Structured Outputs tras consultar la [documentación oficial](https://developers.openai.com/api/docs/guides/structured-outputs?api-mode=responses). El modelo del ejemplo es `gpt-4o-mini`, compatible con el esquema; su disponibilidad y el gasto dependen de la cuenta utilizada. No se realizó ninguna llamada de pago durante el desarrollo.

## Activación voluntaria

1. Importa `optional/chat-openai-optional.json` en n8n y conserva su ruta `roma-chat-ai`.
2. Asocia las credenciales internas creadas por `scripts/setup-n8n.py`.
3. Crea una credencial OpenAI en n8n y selecciónala en el nodo de clasificación; no pegues la clave en JSON ni en Git.
4. Prueba el flujo desde n8n con consultas ficticias y publica únicamente después de revisar los resultados.
5. Establece `ROMA_CHAT_MODE=ai` en `.env` y recrea Drupal.
6. Para volver al modo principal, usa `ROMA_CHAT_MODE=deterministic` y recrea Drupal.

La API valida las etiquetas admitidas. Una salida de clasificación ausente se reduce a `other`; Drupal conserva el mensaje original para la respuesta determinista. Los precios se calculan en MariaDB, las escrituras requieren los permisos de integración y el chatbot nunca confirma solicitudes. No envíes nombres, correos, claves de consulta ni datos de tarjetas a un proveedor de IA. La interfaz advierte que se usen datos ficticios; activar esta alternativa puede enviar el texto escrito al proveedor.

Estado: JSON preparado y validado sintácticamente; no probado contra OpenAI por ausencia de credencial. No se incluye entre los workflows que la instalación publica automáticamente.
