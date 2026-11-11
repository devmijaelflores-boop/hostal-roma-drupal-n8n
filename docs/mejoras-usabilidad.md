# Mejoras de uso y personalización

Publicación: https://hoteles.3-91-159-139.sslip.io

- Disponibilidad y solicitud muestran los tres pasos: fechas, habitación, datos. La navegación distingue estadías adicionales y selección de habitaciones.
- `/admin/roma/fotografias` permite hasta tres imágenes por galería de portada y por tipo de habitación, con permisos de catálogo. JPEG, PNG o WebP, máximo 2 MB por archivo; Drupal limita dimensiones a 1920×1920. Descripción accesible obligatoria. Los archivos guardados se vuelven permanentes y registran uso; las imágenes ajenas a la galería mantienen sus usos existentes.
- Las fotos ilustrativas permanecen como respaldo; cargar una fotografía no acredita que sea del establecimiento. Las galerías de una sola foto no muestran flechas ni avance automático.
- La historia se muestra solo si el hotel proporciona texto; en su ausencia se explica el recorrido de reserva. No se inventa historia empresarial.
- Contexto del chat: fechas y ocupación durante 30 minutos en la sesión Drupal; no guarda transcripción, nombre, correo ni teléfono. Comprende seguimientos «somos dos», «llegamos mañana» y «dos noches», o fechas completas. «Reiniciar» borra el contexto. No se confirma disponibilidad desde la memoria: se consulta MariaDB.
- CSS separado en base (`roma.css`), experiencia, galerías e identidad para mantener los siguientes cambios por componente.

## Comprobaciones

Compilación Docker y caché Drupal completadas en EC2; informe de requisitos sin errores de severidad 2. Contexto del chat verificado desde navegador público con los cuatro mensajes consecutivos. Acceso administrativo a fotografías, subida de JPEG, guardado y aparición en portada comprobados. Imagen temporal de ensayo retirada y portada restaurada. La prueba automática de eliminación mediante navegación no era adecuada porque Drupal utiliza AJAX; no se presenta como una comprobación de ese botón.

Pendiente para una puesta en producción: fotografías autorizadas del hotel, historia proporcionada por el responsable, traducciones del contenido propio y pagos/facturación de producción. CSV y personalización completa de identidad no forman parte de esta actualización.
