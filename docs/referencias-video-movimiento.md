# Referencias para video y movimiento

Consulta: 11/11/2026. Se revisaron páginas oficiales y documentación técnica. La extracción de páginas no permite certificar todas sus animaciones ni su comportamiento en cada dispositivo.

| Referencia | Evidencia revisada | Aplicación propuesta |
| --- | --- | --- |
| [Belmond Miraflores Park](https://www.belmond.com/es/hotels/south-america/peru/miraflores-park-lima) | Página oficial con alojamiento, galería y reservas; HTML incluye etiquetas de reproducir y pausar video. | Medio visual protagonista con control de reproducción, sin ocultar las acciones de reserva. No se afirma haber auditado todo su reproductor. |
| [EDITION](https://www.editionhotels.com/) | Página oficial con identidad de marca, imágenes y acción Reserve. El acceso HTTP directo fue rechazado; la página se consultó mediante extracción web. | Jerarquía simple entre marca, contenido visual y reserva. No se atribuyen efectos de scroll específicos que no se hayan comprobado. |
| [Aman](https://www.aman.com/) | Página oficial con destinos y acceso Reserve. | Mantener navegación y reserva identificables; no usar la cantidad de animación como medida de calidad. |
| [web.dev: Video performance](https://web.dev/learn/performance/video-performance) | Compresión, formatos, carga y reproducción automática. | Clip comprimido, sin audio; foto inicial y carga diferida. |
| [W3C: Pause, Stop, Hide](https://www.w3.org/WAI/WCAG22/Understanding/pause-stop-hide) | Movimiento automático de más de cinco segundos junto a contenido requiere mecanismo de pausa, salvo excepciones esenciales. | Botón persistente de pausa; no basta detener el movimiento solo mientras se mantiene el foco. |
| [W3C: Carousel animations](https://www.w3.org/WAI/tutorials/carousels/animations/) | Control explícito de avance automático. | Carrusel controlable y alternativa con movimiento reducido. |

## Criterio aplicado al proyecto

Un solo medio en movimiento en portada: video o carrusel, evitando reproducción simultánea. El texto de marca y la reserva permanecen estables. Clip local de diez segundos, sin audio, 350370 bytes; no se descarga automáticamente en móvil, ahorro de datos o movimiento reducido. Entradas discretas de secciones; el contenido permanece accesible si JavaScript falla.

Las duraciones elegidas (550 ms de entrada, 650 ms de fundido, diez segundos de clip) son decisiones de diseño del proyecto, no valores prescritos por las referencias. La siguiente mejora editorial debe ser material propio o autorizado del hotel y una foto inicial correspondiente al mismo video para evitar un cambio de ambiente al comenzar. El clip de muestra actual y las fotos pertenecen a interiores diferentes.

## Aplicación de las referencias

La imagen inicial de portada ahora se extrae del mismo clip (`assets/video/hotel-poster.jpg`) para evitar un salto entre interiores al comenzar la reproducción. Se añadió una presentación editorial con fotografía local, texto breve y acceso al catálogo; la guía de reserva usa tres bloques numerados. No se copió material de los hoteles de referencia. Conserva la reserva estable, el control del video y la alternativa sin movimiento. Se verificaron cinco anchos de pantalla, cambio a inglés y ausencia de errores JavaScript.
