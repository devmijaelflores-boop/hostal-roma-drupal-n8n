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

## Alternativa Gemini

`n8n/workflows/optional/chat-gemini-optional.json` usa la credencial `Gemini Hotel` (tipo `googlePalmApi`), guardada exclusivamente en n8n. Clasifica la intención con Gemini 3.5 Flash Lite; consulta los importes y disponibilidad en Drupal. No crea reservas ni genera confirmaciones. No ofrece memoria de conversación ni redacta libremente respuestas: la IA interpreta la consulta y Drupal responde desde el catálogo.

Las consultas se limitan a 1000 caracteres y se omiten correos y secuencias numéricas largas antes de enviarlas a Google. Esto no constituye anonimización completa: usar solo datos de prueba. No se exportan registros de huéspedes. Los errores, salidas inválidas o cuota agotada vuelven a interpretación determinista. Si n8n deja de responder, el puente intenta consultar Drupal directamente. No se guardan ejecuciones con datos del chat.

El flujo usa la ruta privada `roma-chat-ai`, por lo que no debe publicarse simultáneamente con la alternativa OpenAI. Se activa mediante `ROMA_CHAT_MODE=ai`; volver a `deterministic` desactiva el uso de IA desde la web. La clave no forma parte del JSON exportable ni del repositorio. El modelo y la cuota disponible se deben comprobar para cada cuenta.

### Verificación en AWS — 11/11/2026

Gemini quedó activo en el chat público (`ROMA_CHAT_MODE=ai`). Pruebas reales aprobadas: consulta de presupuesto sin palabras clave de tarifa, disponibilidad del 20/12/2027 al 22/12/2027 para dos personas y envío desde el widget en navegador. La respuesta pública reportó `ai=true`, `provider=Gemini` y el precio individual S/ 85.00 del catálogo de MariaDB.

El modelo 2.5 Flash Lite aparecía en el listado de Google, pero rechazó generación para cuentas nuevas con HTTP 404. El flujo conservó la respuesta de Drupal (`ai=false`, `fallback=true`). Se sustituyó por 3.5 Flash Lite, comprobado mediante generación y ejecución completa en n8n. Las expresiones iniciales incompatibles con el evaluador de n8n se reemplazaron por nodos JavaScript y expresiones simples. No se habilitó facturación ni se modificó la cuota de la cuenta. La API puede limitar el servicio; no se garantiza uso gratuito ilimitado.

Fuentes: [Gemini 3.5 Flash Lite](https://ai.google.dev/gemini-api/docs/models/gemini-3.5-flash-lite), [salida estructurada](https://ai.google.dev/gemini-api/docs/structured-output) y [credenciales n8n](https://docs.n8n.io/integrations/builtin/credentials/googleai/).
