# Verificación de SUNAT beta

**Fecha: 11/11/2026. Estado de despliegue: implementado y probado en un entorno local aislado; pendiente de publicación en AWS.** La IP anterior no respondió a SSH ni HTTPS. No se cambiaron servicios de EC2 sin conexión ni se reutilizó su base de datos activa: la verificación local partió de una copia de demostración en otra base MariaDB.

## Comunicación con el servicio beta

Se prepararon dos documentos con emisor y destinatarios genéricos, cuenta de demostración saldada y certificado académico autofirmado. Se utilizó únicamente `https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService`, con comprobación TLS del servidor activa.

| Documento | Resultado final | Código CDR | Evidencia |
| --- | --- | --- | --- |
| Factura F901-1 | Aceptado en beta | 0 | XML, CDR y PDF en [ejemplos](ejemplos-sunat-beta/README.md) |
| Boleta B901-1 | Aceptado en beta | 0 | XML, CDR y PDF en el mismo directorio |

Los [resultados del servicio](verificacion-sunat-beta-real.json) incluyen las huellas SHA-256 del XML y CDR. Los PDF son representaciones académicas imprimibles, no documentos tributarios válidos. No se usaron nombres ni documentos de personas del hotel.

SUNAT advierte que [beta sirve para probar estructuras XML y no verifica la consistencia de los datos](https://cpe.sunat.gob.pe/noticias/servicio-beta-para-realizar-pruebas-ubl-21). La aceptación no verifica un régimen tributario, una venta, un pago ni la autorización fiscal del certificado. Tampoco valida el procedimiento de emisión de producción, plazos, resúmenes o contingencias.

## Pruebas sin conexiones a SUNAT

- **44 comprobaciones de reglas:** firma y alteración de XML, confidencialidad del huésped, correlativos, claves de idempotencia, integridad, estados, reintento manual, límites, recuperación, respuestas tardías, revisión de CDR, permisos de archivos y rechazo de ZIP/XML peligrosos. Transporte simulado, separado de la comunicación beta real. [Resultados](verificacion-sunat-beta-reglas.json).
- **17 comprobaciones de navegador:** permisos anónimo/autenticado, cuenta saldada, CSRF, preparación explícita, descarga privada, ausencia de clave y datos del huésped, consentimiento de envío, impresión, boleta y vista móvil. Estas pruebas no envían documentos. [Resultados](verificacion-sunat-beta-browser.json).
- **69 regresiones locales:** 40 de cuentas y 29 de recepción. Sus fixtures se revierten. [Resultados](verificacion-sunat-beta-regresion.json).
- Composer verificó las extensiones y dependencias; el informe Drupal no presentó errores de severidad 2 en el entorno local.

Las pruebas de navegador retiran sus propios documentos y usuarios. Los correlativos beta de esos ensayos no se reutilizan: pueden dejar huecos en esta base exclusivamente académica. Los ejemplos aceptados permanecen para revisar en la interfaz.

## Entorno realmente usado

La instancia de AWS estaba inaccesible. Se preparó una base separada con MariaDB nativo **13.0.2**, puerto **33077**, enlazado solo a loopback, y PHP **8.5.10** con SOAP, PDO MySQL, Intl y GD habilitados para este proceso. GD se cargó desde un paquete oficial Arch de PHP 8.5.12, compatible con el módulo comprobado; no se cambiaron extensiones del sistema ni la configuración global PHP. Composer validó esta plataforma. El servidor HTTP local escucha en `127.0.0.1:18088`.

La configuración de producción académica de Docker **conserva PHP 8.4 y MariaDB 11.4**; añade SOAP y un volumen de firma privado. Aún falta construir y verificar esta revisión en EC2 cuando se recupere su IP y acceso. No se afirma que el nuevo módulo ya esté disponible en el enlace público de AWS.

## Incidencias analizadas y resueltas

1. Twig requiere una estrategia de escape identificada por nombre. Se corrigió la configuración del constructor XML y se verificó la firma del documento generado.
2. El CDR beta contenía una carpeta vacía `dummy/` y un XML. La validación inicial rechazó conservadoramente esa respuesta. El primer intento quedó incierto sin CDR conservado; se guardó la respuesta del segundo intento y se ajustó únicamente la admisión de directorios vacíos seguros. La revisión posterior utilizó el mismo CDR, sin una nueva solicitud, registrando el cambio de estado y su responsable.
3. Se conservó TLS verificado: se sustituyeron expresamente las opciones inseguras por defecto del cliente SOAP del SDK.
4. Se protegieron tamaño comprimido y descomprimido, nombres de archivos, rutas, número de XML y entidades externas antes de que el SDK interprete el CDR.
5. Se comprobó que la cuenta del huésped no se exporte al XML: cliente, emisor y dirección son genéricos. No se incluyen contraseñas ni claves privadas en los ejemplos.
6. Las pruebas de interfaz seleccionan explícitamente el tipo antes de comprobar CSRF; así se prueba un POST al servidor y no solo la validación nativa del navegador.

Se hicieron tres solicitudes beta de bajo volumen: dos intentos sobre la factura durante el diagnóstico y uno sobre la boleta. Las pruebas restantes usan transporte simulado o preparación/lectura local. La suite general no ejecuta la prueba beta real.

## Archivos y comandos

El módulo `roma_sunat_beta` añade contadores, documentos, intentos y auditoría de revisiones. Los XML y respuestas se guardan en MariaDB y se descargan mediante rutas protegidas. El certificado privado se conserva fuera de la raíz pública, en un volumen independiente; no se incluye en Git ni en la imagen.

La [guía de uso e instalación](sunat-beta.md) contiene los comandos para habilitar el módulo, configurar las credenciales genéricas y reproducir las pruebas. El permiso es `manage roma sunat beta`; el rol responsable hotelero lo recibe, los visitantes no.

## Límites de esta entrega

No se implementa producción, certificado fiscal autorizado, clave SOL real, OSE/PSE, notas de crédito/débito, resúmenes diarios ni determinación del régimen tributario. El escenario gravado al 18 % es solo un ejercicio de XML. El envío individual beta de una boleta no demuestra el procedimiento legal para emitirla en producción.

Una respuesta tardía queda en su intento sin modificar un estado más reciente. Si solo se conserva en un intento, debe descargarse para revisión técnica; no se convierte automáticamente en aceptación. Un timeout no prueba que SUNAT haya rechazado el XML, por eso se mantiene el estado incierto y se evita repetir automáticamente.
