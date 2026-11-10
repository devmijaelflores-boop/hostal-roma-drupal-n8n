# Fase 5 — Chat y automatizaciones n8n

## Objetivo y archivos

Conectar el widget oficial `@n8n/chat` con n8n y la API Drupal. La ruta principal funciona sin IA: n8n orquesta la consulta, Drupal interpreta las preguntas y consulta MariaDB. No se generan precios o confirmaciones con texto libre. Archivos nuevos: `roma_api`, recursos autoalojados `assets/n8n-chat`, inicialización `js/chat.js`, flujos JSON en `n8n/workflows` y `scripts/setup-n8n.py`.

| Flujo | Disparador | Función |
| --- | --- | --- |
| `roma-chat.json` | Webhook privado `roma-chat` | Reenviar la consulta a la API del asistente y devolver su respuesta. |
| `roma-reservation.json` | Webhook privado `roma-reservation` | Registrar una solicitud mediante la API autenticada. No confirma. |
| `roma-automation.json` | Cada minuto y webhook de prueba privado | Procesar avisos pendientes, recordatorios de mañana y un reporte diario. |

El widget envía sus consultas a `/api/roma/chat` en el mismo origen de Drupal. El servidor verifica sesión CSRF y límites antes de llamar a n8n. El secreto del webhook y el token Bearer no llegan al navegador. El endpoint de sesión no se almacena en caché. Se omite la carga de historial y no se ofrece envío de archivos.

## Configurar, importar y activar

En una instalación nueva, `python3 scripts/init-env.py` genera claves aleatorias. En una existente añade únicamente variables nuevas y conserva los secretos previos. `.env.example` contiene nombres y ejemplos, nunca claves válidas.

```bash
python3 scripts/init-env.py
sh scripts/start.sh
```

El arranque construye Drupal, instala los módulos, inicia n8n y Mailpit y ejecuta `python3 scripts/setup-n8n.py`. Este script introduce dos credenciales de cabecera desde `.env` por stdin; utiliza un archivo temporal con permisos privados dentro del contenedor y lo elimina después de importarlo. No imprime sus valores. Importa y publica individualmente los tres IDs ROMA; no publica otros flujos ni la alternativa OpenAI. Reinicia n8n y espera su salud.

El script de instalación administra los flujos con esos IDs y los reemplaza si se repite. Antes de personalizarlos desde n8n, expórtalos y conserva tus variantes. La cuenta propietaria del editor sigue siendo independiente: créala desde `http://localhost:5678` si aún aparece el asistente inicial. No se creó una cuenta con tu identidad.

Importación manual alternativa: abre el editor, selecciona importar desde archivo, carga el JSON, asocia las credenciales `ROMA Drupal API` y `ROMA Webhook privado`, revisa las URL internas y publica. En n8n 2.x la URL de producción requiere publicación del flujo; la URL de prueba solo sirve cuando el editor escucha pruebas. Las credenciales del flujo se configuran en n8n, no en el JSON versionado.

## Consultas disponibles

- Precios y categorías: «¿Cuánto cuesta una individual?» o «¿Tienen matrimoniales?».
- Servicios y ubicación: datos activos de la demo y dirección de referencia facilitada por el usuario.
- Fechas: «Disponibilidad del 20/12/2027 al 22/12/2027 para 2 personas». Admite ISO o `dd/mm/aaaa`; si faltan fechas pide completarlas. No interpreta fechas relativas ambiguas ni inventa disponibilidad.
- Solicitar: facilita un enlace al formulario que valida fechas y capacidad y guarda la solicitud pendiente.
- Estado: dirige al formulario privado con código y clave; el chat no solicita claves personales.

La API externa también permite registrar solicitudes; el workflow correspondiente exige la cabecera privada y `demo_consent=true`. El widget no tiene credenciales de escritura. Para probar registro por n8n debe utilizarse una llamada servidor a servidor, nunca poner el secreto en JavaScript.

## Avisos locales y recordatorios

Los eventos de solicitud y cambio de estado se guardan dentro de la transacción de reserva en `roma_notification`. El flujo programado procesa hasta 20 avisos por llamada, registra intentos, omite recordatorios que ya no corresponden y evita crear el mismo evento dos veces. Un fallo deja el aviso pendiente para reintentar hasta 10 veces; no se muestran direcciones o mensajes en la respuesta del API de automatización.

Los correos se capturan exclusivamente por SMTP en `mailpit:1025`. Consulta la bandeja en `http://localhost:8025`. No se reenvían a Internet ni se utilizan Gmail, WhatsApp o teléfonos del negocio. El reporte diario contiene contadores; las notificaciones a huéspedes usan correos de prueba de las solicitudes. El simulador no envía credenciales de consulta.

La captura SMTP y el marcado en MariaDB son dos sistemas distintos: una caída entre ambos puede repetir un correo. El identificador `X-ROMA-Event` permite detectarlo. No se promete entrega exactamente una vez. Antes de una operación comercial harían falta canales autorizados, tratamiento de fallos y monitoreo.

## Comprobar

```bash
python3 tests/n8n.py
python3 tests/n8n-reservation.py
docker compose cp tests/automation.php drupal:/tmp/roma-automation.php
docker compose exec -T drupal vendor/bin/drush php:script /tmp/roma-automation.php
```

La prueba del chat exige respuesta real de n8n y modifica temporalmente una tarifa en MariaDB para comprobar que el bot la refleja; la restaura al terminar. La prueba de avisos crea y elimina solo sus propias fixtures. Ambas requieren servicios en ejecución. Un fallo de n8n muestra una explicación explícita de indisponibilidad y enlaces a formularios, sin simular una respuesta conectada.

Verificación del 10/11/2026: los tres flujos se importaron y publicaron. Pasaron 12 comprobaciones del chat y su seguridad, incluido un cambio temporal de tarifa en MariaDB reflejado por el bot y restaurado al finalizar. El widget oficial también respondió en la prueba de navegador. Los avisos de solicitud, confirmación, recordatorio y cancelación se capturaron en Mailpit. n8n registró ejecuciones programadas exitosas. Evidencia: `verificacion-n8n.json`, `verificacion-final-browser.json` y `verificacion-ejecucion.json`.
