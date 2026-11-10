# Golden Roma — Prototipo académico Drupal + n8n

Sistema Web de Reservas y Atención Automatizada — Juliaca, Puno, Perú.

**Prototipo académico no oficial. Habitaciones, disponibilidad y tarifas de demostración**

Proyecto independiente, no autorizado ni afiliado oficialmente al establecimiento. Las imágenes, habitaciones y tarifas son ilustrativas. La dirección de referencia fue identificada por el usuario; no se importan datos de clientes del negocio.

## Estado de la entrega

La aplicación reúne el sistema hotelero y una tienda de estadías con **Drupal Commerce 3.3.14**: catálogo, carrito de varias habitaciones, checkout, pedidos, pasarela local de prueba y confirmación administrativa de reservas. El chatbot oficial `@n8n/chat` consulta Drupal mediante n8n. Tres workflows exportables gestionan consultas, solicitudes y automatizaciones con captura SMTP local.

La opción de la consigna implementada es **Drupal/PHP + chat n8n + Docker Compose**. El pago académico permite aprobar, rechazar y cancelar sin dinero real. Una aprobación no confirma habitaciones: cada solicitud requiere decisión administrativa. OpenAI es opcional y permanece desactivado; no hay integración bancaria externa. Ver [tienda y alcance](docs/tienda-commerce.md), [guía para usarla](docs/guia-tienda.md) y [resultados de la entrega](docs/verificacion-tienda.md).

## Arranque y comprobación

```bash
python3 scripts/init-env.py
sh scripts/start.sh
sh scripts/verify-all.sh
```

El generador conserva `.env` y añade únicamente variables faltantes; no cambia secretos existentes. El script de arranque exige un motor Docker accesible, construye e instala Drupal, importa credenciales y publica solo los tres flujos ROMA. Si el motor está detenido, un administrador del equipo puede iniciarlo con `sudo systemctl start docker`.

| Pantalla local | Función |
| --- | --- |
| `http://localhost:8080` | Página pública y chatbot |
| `/tienda` | Catálogo comercial de estadías de demostración |
| `/carrito` | Varias habitaciones, fechas, huéspedes e importes en PEN |
| `/checkout` | Datos, revisión, pago de prueba y recibo privado |
| `/disponibilidad` | Consulta de habitaciones libres de la demo |
| `/reservar` | Solicitud pendiente |
| `/mi-reserva` | Estado privado por código y clave |
| `/pago-demo` | Simulación explícita, sin cobros |
| `/contacto` | Mensajes de prueba |
| `/admin/roma` | Panel hotelero Drupal |
| `/admin/roma/pedidos` | Pedidos Commerce y reservas relacionadas |
| `http://localhost:5678` | Editor n8n; crear propietario si sigue el asistente inicial |
| `http://localhost:8025` | Correos de prueba capturados en Mailpit |

Usuario Drupal: `admin_demo`; contraseña en `.env`. El usuario de integración no se usa para entrar al panel. No compartas `.env`, claves de consulta o credenciales n8n.

## Documentación

- [Gestión hotelera y reglas de integridad](docs/fase-3-gestion-hotelera.md).
- [Tienda Drupal Commerce y checkout](docs/tienda-commerce.md).
- [Guía rápida de la tienda](docs/guia-tienda.md).
- [API y autorización](docs/api.md).
- [Chat, workflows y automatizaciones n8n](docs/n8n.md).
- [IA opcional](docs/fase-6-ia-opcional.md).
- [Pagos: simulador y límite de la pasarela externa](docs/pagos.md).
- [Arquitectura](docs/arquitectura.md) y [guion de demostración](docs/guion-demostracion.md).
- [Referencias web, GitHub y YouTube](docs/referencias-implementacion.md).
- [Propuesta académica Word/PDF](docs/propuesta/README.md).

## Guardar con Git

Repositorio privado: [devmijaelflores-boop/hostal-roma-drupal-n8n](https://github.com/devmijaelflores-boop/hostal-roma-drupal-n8n). Se guardan código propio, Composer y su lock, configuración Docker, flujos sin secretos, recursos con licencias, pruebas y documentación. `.env`, dependencias, bases de datos y cachés se excluyen.

```bash
git status --short
git check-ignore .env drupal/vendor/autoload.php
git add .gitignore .dockerignore .env.example compose.yaml README.md drupal docker n8n scripts tests docs
git diff --cached --stat
git diff --cached
git commit -m "feat: prototipo hotelero Drupal y automatizaciones n8n"
```

Revisa los cambios antes de confirmar. Para subir nuevos commits, ejecuta `git push origin main`. Conserva las credenciales exclusivamente en el entorno local.

## Versiones elegidas

| Componente | Selección | Motivo |
| --- | --- | --- |
| Drupal | 11.4.12, bloqueado en `composer.lock` | Versión estable publicada el 6/11/2026. La restricción `~11.4.12` admite futuros parches 11.4 al actualizar expresamente Composer. |
| Drupal Commerce | 3.3.14, bloqueado en Composer | Commerce 3.x es compatible con Drupal 11; aporta las entidades de producto, carrito, pedido, checkout y pago. |
| PHP | 8.4, Apache, Debian Bookworm | Rama compatible con Drupal 11.4; imagen con GD, Intl, PDO MySQL, Mbstring, Zip, OPcache y BCMath. |
| MariaDB | 11.4 LTS | Rama mantenida, superior al mínimo requerido por Drupal 11; aislamiento READ COMMITTED y UTF-8 completo. |
| n8n | 2.47.3 | Versión identificada como estable en la documentación oficial consultada. |
| Drush | 13.8.0, bloqueado en Composer | Instalación y administración por línea de comandos. |
| Composer | 2 | Gestor oficial de dependencias para la plantilla Drupal. |

Consulta oficial realizada el 10/11/2026: [Drupal 11.4.12](https://www.drupal.org/project/drupal/releases/11.4.12), [compatibilidad PHP](https://www.drupal.org/docs/getting-started/system-requirements/php-requirements), [base de datos](https://www.drupal.org/docs/getting-started/system-requirements/database-server-requirements), [plantilla Composer](https://www.drupal.org/docs/develop/using-composer/starting-a-site-using-drupal-composer-project-templates), [mantenimiento MariaDB](https://mariadb.org/about/#maintenance-policy), [n8n con Docker](https://docs.n8n.io/hosting/installation/docker/).

Drupal y n8n tienen versiones exactas registradas. Las etiquetas PHP 8.4, MariaDB 11.4 y Composer 2 reciben parches dentro de su rama; una nueva construcción puede cambiar esos parches. Los digests utilizados están registrados en [imágenes verificadas](docs/imagenes-verificadas.txt). Revisar soporte y avisos antes de actualizar.


Mailpit: imagen `axllent/mailpit:v1.33.1`; widget `@n8n/chat` 1.46.2. Sus fuentes están registradas en la documentación.
