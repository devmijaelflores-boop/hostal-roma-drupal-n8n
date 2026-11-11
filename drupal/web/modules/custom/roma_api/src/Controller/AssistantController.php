<?php
namespace Drupal\roma_api\Controller;

use Drupal\roma_booking\Service\HotelService;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/** Asistente determinista: sin IA, sin memoria personal y sin confirmaciones. */
final class AssistantController extends ApiController {
  public function answer(Request $request): JsonResponse {
    $this->guard($request, 'roma.api.assistant', 90);
    $body = $this->body($request);
    $input = trim((string) ($body['chatInput'] ?? ''));
    if (!$input || mb_strlen($input) > 1000) { return $this->respond(['error' => 'Escribe una consulta de hasta 1000 caracteres.'], 422); }
    $text = strtr(mb_strtolower($input), ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n']);
    $intents = ['prices'=>'precio', 'services'=>'servicios', 'location'=>'ubicacion', 'availability'=>'disponibilidad', 'reservation'=>'reserva', 'status'=>'estado', 'payment'=>'pago'];
    $intent = (string) ($body['intent'] ?? '');
    // La IA solo aporta una etiqueta; nunca fechas, precios ni texto al cliente.
    if (isset($intents[$intent])) { $text = $intents[$intent] . ' ' . $text; }
    $output = '';
    if (preg_match('/disponib|fecha|\d{4}-\d{2}-\d{2}|\d{2}\/\d{2}\/\d{4}/', $text)) {
      preg_match_all('/\b(?:\d{4}-\d{2}-\d{2}|\d{2}\/\d{2}\/\d{4})\b/', $text, $matches);
      if (count($matches[0]) >= 2) {
        $convert = static fn(string $d): string => str_contains($d, '/') ? implode('-', array_reverse(explode('/', $d))) : $d;
        preg_match('/(\d+)\s*(?:huesped|persona|adulto)/', $text, $occupancy);
        $guests = isset($occupancy[1]) ? (int) $occupancy[1] : 1;
        try {
          $arrival = $convert($matches[0][0]); $departure = $convert($matches[0][1]);
          $rooms = $this->hotel->availability($arrival, $departure, $guests);
          $output = 'Disponibilidad de demostración del ' . HotelService::date($arrival) . ' al ' . HotelService::date($departure) . ' para ' . $guests . ' persona(s): ';
          if (!$rooms) { $output .= 'no hay habitaciones para esos criterios.'; }
          else {
            $groups = [];
            foreach ($rooms as $room) { $key = $room['type_name']; $groups[$key] ??= ['count'=>0,'price'=>$room['estimated_price']]; $groups[$key]['count']++; }
            foreach ($groups as $name => $group) { $output .= "\n• " . $name . ': ' . $group['count'] . ' habitación(es), estimado de estadía ' . HotelService::money($group['price']) . '.'; }
            $output .= "\n[Solicitar reserva](/reservar?" . http_build_query(['arrival'=>$arrival,'departure'=>$departure,'guests'=>$guests]) . ')';
          }
        }
        catch (\DomainException $error) { $output = $error->getMessage(); }
      }
      else { $output = 'Indica llegada, salida y huéspedes. Ejemplo: «Disponibilidad del 20/12/2027 al 22/12/2027 para 2 personas». Consultaré la base de la demo; no es disponibilidad del negocio real.'; }
    }
    elseif (preg_match('/servicio|wifi|wi-fi|cochera|estacion|agua|bano/', $text)) {
      $output = 'Servicios del catálogo de demostración: ' . implode(', ', array_column($this->hotel->services(), 'name')) . '.';
    }
    elseif (preg_match('/ubic|direccion|donde|llegar/', $text)) {
      $output = 'El establecimiento de referencia indicado por el usuario está en Nueva Zelandia 196, Juliaca 21101, Puno, Perú. [Ver ubicación](/#ubicacion). El proyecto es independiente y no oficial.';
    }
    elseif (preg_match('/estado|mi reserva|confirmad|codigo/', $text)) {
      $output = 'Por privacidad no solicito claves en el chat. [Consulta tu solicitud en el formulario seguro](/mi-reserva) usando su código y clave privada. El estado se obtiene de MariaDB; solo el panel autorizado confirma o cancela.';
    }
    elseif (preg_match('/carrito|checkout|tienda|comprar/', $text)) {
      $output = '[Visita la tienda de estadías](/tienda), elige habitación, fechas y huéspedes y agrega tus estadías al [carrito](/carrito). El checkout registra solicitudes pendientes y permite aprobar, rechazar o cancelar un pago de demostración. No se cobra dinero real. La confirmación de las habitaciones corresponde al administrador.';
    }
    elseif (preg_match('/reserv|solicitud|alojar/', $text)) {
      $output = '[Solicita una reserva](/reservar). El formulario comprobará fechas, capacidad y disponibilidad. Recibirás un código y una clave privada. La solicitud quedará pendiente; no significa una confirmación ni genera un cobro.';
    }
    elseif (preg_match('/pago|pagar|tarjeta|yape|plin/', $text)) {
      $output = 'Los pagos del proyecto son exclusivamente de demostración. [Consulta tu solicitud](/mi-reserva) para ver las opciones habilitadas. Nunca compartas tarjetas, claves bancarias ni datos reales en el chat.';
    }
    elseif (preg_match('/precio|tarifa|cuesta|habitacion|individual|matrimonial|doble|promo|barat|economic/', $text)) {
      $lines = [];
      foreach ($this->hotel->types() as $type) {
        $normalized = strtr(mb_strtolower($type['name']), ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u']);
        $specific = preg_match('/individual|matrimonial|doble/', $text);
        if (!$specific || str_contains($text, $normalized)) { $lines[] = '- **' . $type['name'] . '**' . ': ' . HotelService::money((int) $type['amount']) . '/noche, capacidad ' . $type['capacity'] . '.'; }
      }
      if ($lines) {
        $types=$this->hotel->types();
        usort($types,static fn(array $a,array $b): int => (int)$a['amount'] <=> (int)$b['amount']);
        $output="¡Claro! 😊 Te ayudo a encontrar una opción para tu viaje.\n\n";
        if (!preg_match('/individual|matrimonial|doble/', $text) && $types) {
          $lowest=$types[0];
          $output.='La opción más económica del catálogo es **'.$lowest['name'].'**, desde **'.HotelService::money((int)$lowest['amount']).' por noche**, para hasta '.$lowest['capacity'].' persona(s).'."\n\n";
        }
        $output.=implode("\n\n",$lines);
        if (str_contains($text,'promo') || str_contains($text,'oferta') || str_contains($text,'descuento')) {
          $output.="\n\nSobre promociones, todavía no tengo ofertas verificadas para anunciarte. Puedes [consultar con recepción](/contacto).";
        }
        $output.="\n\n¿Cuántas personas viajarían y cuáles serían tus fechas de llegada y salida? Así puedes consultar una opción que se ajuste a tu estadía. 🧳";
      } else { $output='Por ahora no encuentro esa categoría en el catálogo. ¿Te muestro las habitaciones disponibles? 😊'; }
    }
    else { $output = '¡Hola, qué gusto saludarte! 😊 Soy el asistente de ROMA. ¿Estás planeando un viaje? Puedo ayudarte a elegir habitación, revisar precios o consultar fechas. Cuéntame qué necesitas y empezamos. 🧳'; }
    return $this->respond(['output'=>$output, 'demo'=>TRUE, 'source'=>'Drupal/MariaDB', 'ai'=>FALSE]);
  }
}
