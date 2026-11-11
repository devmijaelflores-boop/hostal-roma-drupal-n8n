# Procedencia de recursos visuales

Todos los recursos se sirven desde el tema local. No se generaron fotografías con IA y no se presentan como fotografías del negocio real.

## Fotografías referenciales

| Archivo | Fuente de descarga |
| --- | --- |
| `hero.jpg` | [Unsplash · photo-1611892440504-42a792e24d32](https://images.unsplash.com/photo-1611892440504-42a792e24d32) |
| `individual.jpg` | [Unsplash · photo-1631049307264-da0ec9d70304](https://images.unsplash.com/photo-1631049307264-da0ec9d70304) |
| `matrimonial.jpg` | [Unsplash · photo-1618773928121-c32242e63f39](https://images.unsplash.com/photo-1618773928121-c32242e63f39) |
| `doble.jpg` | [Unsplash · photo-1590490360182-c33d57733427](https://images.unsplash.com/photo-1590490360182-c33d57733427) |

Descargadas el 10/11/2026 en JPEG, con anchura 1800 para la portada y 900 para las tarjetas, calidad 85. Se consultó la [licencia Unsplash](https://unsplash.com/license). No se atribuye una identidad de fotógrafo que no se haya verificado. El uso es ilustrativo y no implica que la arquitectura, las camas o el mobiliario representen el inventario del hostal. Cada tarjeta y la portada incluyen una etiqueta de referencia.

## Tipografías

- **Cormorant Garamond** para títulos y **Manrope** para texto e interfaz, descargadas desde Google Fonts y autoalojadas en WOFF2 con subconjuntos latino y latino extendido.
- Licencias SIL Open Font License incluidas en `assets/fonts/cormorant-OFL.txt` y `assets/fonts/manrope-OFL.txt`, obtenidas del [repositorio oficial google/fonts](https://github.com/google/fonts).
- `assets/fonts/sources.json` conserva las URL exactas de los archivos descargados.

## Ilustración e iconos

La ilustración conceptual del altiplano se creó con CSS. No es una fotografía, un mapa o un punto geográfico del negocio. La marca tipográfica y los iconos SVG de interfaz se escribieron para este proyecto; no se presentan como identidad oficial del establecimiento.

## Galerías de imágenes

La portada permite recorrer manualmente tres fotografías existentes. Las ventanas de habitaciones muestran la fotografía de su categoría y una segunda imagen del catálogo ilustrativo; no representan distintos ángulos de una misma habitación real. Las imágenes se amplían en un diálogo y pueden recorrerse mediante flechas o deslizamiento táctil. Las rutas adicionales se conservan como datos y se asignan a la imagen únicamente al navegar; no hay avance automático. Se reutilizan los archivos locales, sin nuevas descargas externas ni dependencias de carrusel.

## Video de portada y movimiento

Se añadió un clip de muestra de 10 segundos basado en [Hotel room with modern minimalist style, Mixkit 4488](https://mixkit.co/free-stock-video/hotel-room-with-modern-minimalist-style-4488/), cuya ficha permite uso comercial y personal bajo [Mixkit Stock Video Free License](https://mixkit.co/license/#videoFree). No representa el establecimiento. Archivo local `assets/video/hotel-interior.mp4`: H.264, 1280×720, 24 fps, 350370 bytes, sin pista de audio; optimizado con faststart. Fuente de descarga: `https://assets.mixkit.co/videos/4488/4488-720.mp4`.

La foto se presenta primero; el video se carga después en escritorio. En pantallas menores de 768 px, conexiones 2G, ahorro de datos y movimiento reducido, solo se descarga al solicitarlo. Incluye botón de reproducción/pausa; se pausa fuera de pantalla y al ocultar la pestaña. Las fotografías propias configuradas desactivan el video de muestra para no mezclarlas con él. Si no puede reproducirse, permanece la imagen.

Las secciones tienen una entrada breve al aparecer en pantalla; el contenido nunca depende de una clase invisible para poder leerse. Las fotografías del carrusel usan un fundido. Se respeta la preferencia de movimiento reducido. No se añadieron librerías de animación ni reproductores externos.

### Actualización de calidad

Se sustituyó el derivado de 720p por una fuente Full HD de Mixkit: `https://assets.mixkit.co/videos/4488/4488-1080.mp4`, obtenida del enlace de descarga 1080p de la misma ficha. Derivado de diez segundos, 1920×1080, 30 fps, H.264 CRF 20, sin audio, 5206242 bytes. Imagen inicial extraída de este derivado con mayor calidad. Las URLs incorporan una versión para evitar reutilizar archivos comprimidos de la caché del navegador. La versión anterior de 350370 bytes priorizaba tamaño y perdía detalle a pantalla completa.
