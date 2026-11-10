# Resultados de verificación — fase 1

Fecha: 10/11/2026. Directorio: `/home/sae/hostal-roma-drupal-n8n`.

## Comprobaciones ejecutadas

| Comprobación | Resultado observado |
| --- | --- |
| Git en `/home/sae` | No era un repositorio. Se creó uno local independiente en la carpeta del proyecto, rama `main`, sin remoto ni commit. |
| `docker --version` | Docker 29.8.2 instalado. |
| `docker compose version` | Compose 5.6.0 instalado. |
| Plantilla oficial Composer | Se descargó `drupal/recommended-project:11.4.12`. |
| Resolución e instalación de dependencias | Composer instaló 89 paquetes, incluido Drupal 11.4.12 y Drush 13.8.0; generó el lock y no reportó avisos de vulnerabilidad conocidos. |
| `composer validate --strict --working-dir=drupal` | Correcto después del ajuste de restricciones a `~11.4.12`. |
| `drupal/vendor/bin/drush --version` | `Drush Commandline Tool 13.8.0.0`. Esto prueba la herramienta CLI, no el bootstrap del sitio. |
| `docker compose config --quiet` | Correcto después de generar `.env`. No requiere motor Docker. |
| `php -l` de settings y configuración inicial | Sin errores de sintaxis. |
| `sh -n` de arranque, instalador y smoke | Sin errores de sintaxis. |
| Generación de `.env` | Correcta; permisos `600`. |
| `git check-ignore .env drupal/vendor/autoload.php` | Ambos excluidos. |
| Inspección del modelo Compose resuelto | Puertos Drupal/n8n en `127.0.0.1`, MariaDB sin puerto publicado; contraseña inicial Drupal solo en el instalador. |

El PHP local es 8.5 y carece de GD y PDO MySQL. La instalación local de dependencias se ejecutó con `--ignore-platform-req=ext-gd` exclusivamente para descargar y revisar el código. Composer resuelve usando la plataforma PHP 8.4 declarada. Esto **no demuestra** compatibilidad de ejecución en el equipo. La imagen Docker instala GD y PDO MySQL y ejecuta `composer check-platform-reqs` sin omitir requisitos.

## Errores analizados

- Composer falló inicialmente por resolución DNS bajo el sandbox; se repitió con autorización de red y terminó correctamente.
- El generador `.env` usaba inicialmente `Path.open(opener=...)`, parámetro no admitido. Se corrigió a la función incorporada `open()`; la siguiente ejecución creó el archivo correctamente con modo `600`.
- Las restricciones exactas en `composer.json` generaban avisos en la validación estricta. Se cambiaron a `~11.4.12`, manteniendo la versión exacta en el lock; se actualizó su hash y la validación estricta pasó.
- `docker info` falló por acceso al socket. Se repitió con ejecución autorizada y el resultado fue socket `/var/run/docker.sock` ausente. No existe un contexto Docker alternativo configurado. El intento de `docker compose build drupal` también falló al conectar con el motor. La advertencia adicional indica que falta el plugin buildx, pero el impedimento principal es el motor inaccesible.

## Ejecución completada después de activar Docker

El usuario inició el motor Docker y se repitió la instalación. Resultado final: **fase 1 funcional y comprobada**.

| Comprobación real | Resultado |
| --- | --- |
| Construcción de imagen Drupal | Correcta con PHP 8.4.27; requisitos Composer sin omisiones. |
| Instalación Drupal en MariaDB | Correcta, Drupal 11.4.12 y MariaDB 11.4.13; idioma `es`, zona `America/Lima`. |
| Traducciones | Más de 8.900 traducciones importadas durante la instalación. |
| Estado Compose | Drupal, MariaDB y n8n `healthy`. |
| Consulta SQL real desde Drupal | `SELECT 1` correcto; bootstrap `Successful`, base `Connected`. |
| Portada HTTP | 200 y aviso académico exacto presente. |
| Login administrador por formulario HTTP | Correcto; acceso autenticado al informe de estado con HTTP 200. |
| Panel sin autenticación | Acceso rechazado. |
| Informe de requisitos Drupal | `drush core:requirements --severity=2` sin errores. |
| Drupal → n8n | Ruta interna de disponibilidad del servidor HTTP accesible. |
| n8n → Drupal | Formulario de login HTTP accesible mediante DNS Compose. |
| Salud e interfaz n8n | `/healthz/readiness` responde `{"status":"ok"}` y la interfaz devuelve HTTP 200. |
| SQLite n8n | Base legible y con registro inicial de usuario; se comprobó su persistencia. |
| Recreación completa sin eliminar volúmenes | Conservó identidad del sitio, páginas, archivo de prueba y registros de usuarios n8n. |
| Repetición de pruebas después de recrear | Pruebas de servicios y HTTP correctas. |
| Reejecución del instalador | Detectó sitio instalado; no reinstaló la base; quedó una sola página inicial. |

### Correcciones derivadas de la ejecución

La primera portada devolvió HTTP 500. El registro Drupal identificó un tipo de contenido ausente: el perfil Standard de esta versión no creaba `page`. El instalador se corrigió para crear explícitamente el tipo Página básica, almacenamiento, campo de contenido y sus formularios y visualización. Se reparó el nodo inicial existente mediante API Drupal, sin duplicarlo ni reinstalar la base. La portada pasó después de la corrección.

Se añadió la protección `.htaccess` de Drupal en `config/sync` durante la construcción para evitar que el proceso web intentara generarla en un directorio de solo lectura. Se cambió la comprobación de salud Drupal para verificar la portada, que es la ruta principal del proyecto.

Se sustituyó `WEBHOOK_URL` por `N8N_WEBHOOK_URL` después del aviso de deprecación de n8n. Se deshabilitó la instalación de paquetes comunitarios no verificados. La configuración vigente de webhooks está descrita en el [repositorio oficial de n8n](https://github.com/n8n-io/n8n-hosting/blob/main/charts/n8n/README.md).

La instalación mostró un aviso de cliente `mysql` ausente. La base ya estaba creada por MariaDB y se verificó vacía mediante PDO antes de instalar; la instalación y las consultas SQL terminaron correctamente. Esta imagen no incluye herramientas CLI de copia de seguridad de MySQL.

Docker construyó mediante el constructor clásico porque no está instalado buildx; la construcción terminó correctamente. Apache emite un aviso de nombre global de servidor sin afectar el acceso local. n8n mantiene avisos de deprecación del ejecutor interno y de valores predeterminados futuros; el ejecutor JavaScript se registró correctamente. El ejecutor Python no está instalado y no se utiliza en este proyecto.

### Cómo repetir las pruebas

```bash
sh tests/smoke.sh
python3 tests/http_checks.py
python3 tests/persistence.py
```

La última prueba apaga y recrea servicios temporalmente, conservando los volúmenes. El script de arranque ejecuta las dos primeras. Las pruebas HTTP utilizan las credenciales de `.env` sin imprimirlas.

### Pendiente y límites

La cuenta propietaria de n8n debe crearse en `http://localhost:5678`; no se registró una cuenta en nombre del usuario. Su editor autenticado y la creación de workflows no se han probado todavía. No hay workflows ni integración de negocio en esta fase. No se hicieron capturas ni una revisión visual en navegador: la portada, formulario de login y panel se probaron mediante solicitudes HTTP reales.

Las versiones de imagen y sus digests están registradas en [imágenes verificadas](imagenes-verificadas.txt). Los servicios se dejaron encendidos en localhost.

## Documentación y revisión

La guía se basa en los archivos creados, la plantilla oficial y los comandos ejecutados. No incluye capturas; no se realizó una sesión de navegador visual. Se distingue cada resultado observado de los resultados esperados. No se incluye documentación de funciones hoteleras todavía inexistentes.

No se publicó nada en GitHub: falta identificar el repositorio remoto. Los comandos de preparación del commit y vinculación aparecen en el README. No se generaron workflows ni se inició otra fase.
