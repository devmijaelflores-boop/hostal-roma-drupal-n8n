# API de demostración · v1

Base local: `http://localhost:8080/api/roma/v1`. JSON UTF-8. Importes enteros en céntimos PEN. Fechas ISO `YYYY-MM-DD`; ingreso inclusivo, salida exclusiva. Respuestas privadas sin caché. Solo las reservas confirmadas bloquean habitaciones; una solicitud pendiente no confirma ni garantiza habitación.

| Método y ruta | Autorización | Uso |
| --- | --- | --- |
| GET `/catalog` | Pública, limitada | Tipos activos, tarifas, capacidades y servicios de la demo. |
| GET `/availability?arrival=...&departure=...&guests=...` | Pública, limitada | Habitaciones disponibles y precio estimado en MariaDB. |
| POST `/reservations` | Bearer de integración + permiso Drupal | Registrar solicitud pendiente. |
| POST `/reservation-status` | Bearer de integración + `X-Reservation-Key` | Consultar una solicitud por código, sin PII. |

El token pertenece a un usuario de servicio Drupal con permisos limitados. No es la contraseña del administrador y no debe enviarse al navegador. Se inyecta mediante `ROMA_INTEGRATION_TOKEN`. `ROMA_PUBLIC_URL` define el único origen de navegador admitido. La web y el chat usan el mismo origen mediante un puente servidor: no se habilita CORS global ni `*`. Las llamadas servidor a servidor no necesitan CORS.

## Registrar solicitud

JSON de entrada:

```json
{"room_id":1,"arrival":"2027-06-10","departure":"2027-06-12","guests":1,"customer_name":"Cliente ficticio","email":"prueba@example.invalid","phone":"","demo_consent":true}
```

`request_key` es opcional; si se proporciona debe contener de 32 a 64 caracteres hexadecimales y ser único. El servidor siempre calcula el estimado y asigna `pending`; los campos de precio o confirmación del cliente se ignoran. La respuesta 201 contiene `reference`, `token`, `status` y `estimated_price`. Guarda la clave: no se incluye en ninguna URL. No reenvíes datos personales a servicios externos.

La consulta de estado recibe `{"reference":"ROMA-..."}` y la clave privada en `X-Reservation-Key`, además del Bearer. Un código o clave incorrectos responde 404 sin revelar si el código existe.

## Errores y límites

400: JSON/tipos inválidos. 401/403: acceso no autorizado. 404: consulta no autorizada o recurso ausente. 405: método no permitido. 413: cuerpo mayor de 8 KB. 415: contenido distinto de JSON. 422: reglas del negocio o consentimiento. 429: límite de intentos. Las consultas tienen límites de 60/minuto; las escrituras 10/minuto y la consulta privada 20/minuto por identificador de IP derivado. Una IP compartida comparte el límite.

Las rutas de escritura no aceptan cookies de administrador como alternativa a Bearer. Los formularios Drupal continúan usando su protección CSRF y sesiones independientes.

Prueba de contrato: `python3 tests/api.py`. Crea una solicitud ficticia y elimina solamente su propia fixture al finalizar.
