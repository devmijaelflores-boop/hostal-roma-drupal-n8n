# Fase 2 — Diseño web ROMA · Altiplano

## Objetivo y alcance

Reemplazar la portada provisional por una experiencia visual elegante y responsive, implementada como tema nativo Drupal con Twig, CSS y JavaScript. No se añadió inventario, reservas, API ni automatizaciones de negocio: corresponden a las fases siguientes.

La dirección visual combina marfil, verde profundo, tonos de arena, fotografía referencial y tipografía editorial. Los componentes propios incluyen identidad tipográfica, encabezado, hero, planificador visual, catálogo, filtros, detalles en ventanas modales, servicios, ilustración del altiplano, ubicación general, pie y asistente en modo demostración. El login utiliza los formularios originales de Drupal; el tema administrativo sigue siendo Claro.

## Referencias consultadas

| Fuente | Referencia | Aplicación en este proyecto |
| --- | --- | --- |
| Web | [The Hoxton · habitaciones](https://thehoxton.com/london/holborn/rooms/) | Jerarquía de habitaciones, fotografía y lectura clara de categorías. Se revisó la página; no se reutilizó su contenido ni sus imágenes. |
| GitHub | [Nocturne Hotel](https://github.com/lowrencekhanna69/nocturne-hotel) | Referencia de presentación boutique y énfasis en responsive, accesibilidad y rendimiento descritos en su repositorio. |
| GitHub | [OceanBlue Hotel Template](https://github.com/sanusiaz/hotel-website-flow) | Referencia de organización de secciones y navegación de una web hotelera. Se revisó su README. No se copiaron su código ni sus integraciones de pagos. |
| YouTube | [Create A Responsive Hotel Website Design Using HTML - CSS - JAVASCRIPT](https://www.youtube.com/watch?v=9OVD-HA-zAI) | Recurso educativo localizado mediante búsqueda. No fue posible reproducir ni obtener el video; no se afirma haber seguido sus pasos. |
| YouTube | [Responsive Hotel Booking Website Using HTML, CSS & JavaScript](https://www.youtube.com/watch?v=zqLs3Ei4cAc) | Segundo recurso localizado; mismo límite de acceso al video. |

La implementación del repositorio es propia y conserva Drupal. Las referencias no validan funcionalidades de reservas ni datos del establecimiento.

## Archivos creados y modificados

- `drupal/web/themes/custom/roma_elegante/`: definición del tema, bibliotecas, preprocess, plantillas Twig, estilos, JavaScript, configuración inicial, esquema y recursos locales.
- `docker/drupal/design.php`: instala y activa el tema de forma idempotente, preservando el contenido existente.
- `docker/drupal/install.sh` y `Dockerfile`: incorporan la activación y copian el tema a la imagen.
- `scripts/start.sh`: reconstruye cachés después de recrear el contenedor web.
- `tests/design-browser.mjs`: comprobaciones de navegador y capturas.
- `docs/verificacion-fase-2.json` y `docs/capturas/`: resultados y evidencia visual.
- `docs/recursos-visuales.md`: procedencia y licencias.

## Interacciones disponibles

El planificador valida que la salida sea posterior a la llegada y que la llegada no sea anterior a hoy en Lima. Muestra noches, huéspedes y fechas `dd/mm/aaaa`. No envía datos al servidor ni consulta disponibilidad. Los campos nativos de fecha pueden mostrar el orden visual definido por el idioma del navegador; el resumen siempre se presenta en formato peruano.

Los filtros muestran una o dos categorías según capacidad ilustrativa. Las tarjetas abren una ventana con detalles y tarifa ficticia. El menú se adapta al móvil y se cierra al navegar. El asistente informa explícitamente que todavía no está conectado con n8n; no simula respuestas de un sistema conectado.

Contacto presenta información del proyecto, sin inventar teléfono o correo del negocio. El usuario identificó el establecimiento de referencia mediante un enlace de Google Maps: Hostal ROMA, Nueva Zelandia 196, Juliaca 21101, Perú. La sección de ubicación muestra esa dirección y un botón de indicaciones hacia el enlace compartido, sin seguimiento UTM. Google Maps no pudo abrirse mediante la herramienta de consulta; la identificación procede de la confirmación del usuario, no de una verificación independiente. La ilustración andina sigue siendo conceptual: no representa un mapa ni una localización exacta.

## Catálogo configurable

Las categorías y tarifas visibles se leen de la configuración Drupal `roma_elegante.settings`, almacenada en MariaDB después de instalar el tema. El YAML de `config/install` aporta valores iniciales; modificar ese archivo no cambia una instalación existente. No constituye inventario ni un modelo de reservas.

Ejemplo de modificación explícita de una tarifa ficticia en la instalación actual:

```bash
docker compose exec -T drupal vendor/bin/drush config:set roma_elegante.settings demo_rooms.0.price 90 --yes
docker compose exec -T drupal vendor/bin/drush cache:rebuild
```

El ejemplo no se ejecutó: la entrega conserva las tarifas ficticias 85, 130 y 150 PEN. La gestión hotelera mediante permisos y formularios se realizará en fase 3.

## Comandos para instalar o actualizar

Desde la raíz del proyecto, con `.env` existente:

```bash
docker compose build drupal
docker compose run --rm install
docker compose up -d --wait drupal
docker compose exec -T drupal vendor/bin/drush cache:rebuild
sh tests/smoke.sh
python3 tests/http_checks.py
```

El instalador conserva la base existente. También puede utilizarse `sh scripts/start.sh` para el proceso completo. La reconstrucción de caché posterior a recrear el servidor evita conservar el registro de plantillas generado por un contenedor con código anterior.

## Verificación ejecutada

Se utilizó Brave headless con Puppeteer. Pasaron 35 comprobaciones: portada HTTP 200, una cabecera principal, tres categorías iniciales, carga de imágenes y ausencia de desbordamientos a 1440, 1024, 768, 390 y 360 píxeles; menú móvil; filtros; modal y Escape; resumen de tres noches con fechas peruanas; rechazo de salida igual a llegada; aviso y cierre del asistente; y formulario Drupal en escritorio y móvil. No se detectaron errores JavaScript ni recursos HTTP fallidos durante esa prueba.

Los resultados exactos quedan en `verificacion-fase-2.json`. El navegador se ejecuta con:

```bash
deno run --cached-only --allow-all tests/design-browser.mjs
```

Ese comando usa el Puppeteer ya disponible en la caché local y Brave en `/opt/brave-bin/brave`. En otro equipo se debe instalar Puppeteer Core 25.10.0 en la caché de Deno y definir `ROMA_BROWSER` con la ruta del navegador Chromium compatible. La instalación Docker no depende de estas herramientas de prueba.

La revisión detectó primero un registro de plantillas obsoleto después del cambio de imagen; se corrigió reconstruyendo caché en el contenedor actualizado. También se ajustó la prueba para cargar expresamente las imágenes diferidas antes de revisarlas: no eran errores de descarga.

La prueba HTTP de login administrativo y las comprobaciones de SQL, requisitos y comunicación Drupal/n8n se repitieron después de instalar el tema. La persistencia ya se comprobó en fase 1; no se modificaron sus volúmenes.

## Revisión manual y Git

Abre `http://localhost:8080` y utiliza `Ctrl+Shift+R` si conservas la portada anterior. Comprueba el menú móvil, los filtros, las ventanas de detalles, el planificador y el asistente. Las capturas muestran la vista pública, sin credenciales.

Guardar con Git el tema completo, recursos y licencias, scripts, pruebas, documentación y capturas. No guardar `.env`, cachés de navegador, dependencias o datos de volúmenes.

```bash
git add drupal/web/themes/custom/roma_elegante docker/drupal scripts/start.sh tests/design-browser.mjs docs README.md
git diff --cached --stat
git diff --cached
git commit -m "feat: tema Drupal ROMA con diseño editorial responsive"
```

El repositorio aún no tiene commit inicial; para incluir también la infraestructura usa primero la lista completa de archivos del README. No se publicó en GitHub porque no se ha indicado un remoto.
