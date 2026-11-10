<?php
namespace Drupal\roma_api\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\roma_booking\Service\HotelService;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\HttpException;

class ApiController extends ControllerBase {
  public function __construct(protected readonly HotelService $hotel) {}
  public static function create(ContainerInterface $container): static { return new static($container->get('roma_booking.hotel')); }
  protected function respond(array $data, int $code = 200): JsonResponse {
    return new JsonResponse($data, $code, ['Cache-Control' => 'no-store, private', 'X-Content-Type-Options' => 'nosniff', 'Referrer-Policy' => 'no-referrer']);
  }
  protected function guard(Request $request, string $event, int $limit = 60): void {
    $allowed = rtrim(getenv('ROMA_PUBLIC_URL') ?: 'http://localhost:8080', '/');
    $origin = $request->headers->get('Origin');
    if ($origin && $origin !== $allowed) { throw new HttpException(403, 'Origen no permitido.'); }
    $flood = \Drupal::service('flood');
    $id = hash('sha256', ($request->getClientIp() ?? 'unknown') . \Drupal::service('private_key')->get());
    if (!$flood->isAllowed($event, $limit, 60, $id)) { throw new HttpException(429, 'Demasiados intentos. Espera un minuto.'); }
    $flood->register($event, 60, $id);
  }
  protected function body(Request $request, bool $chat = FALSE): array {
    if (!str_starts_with($request->headers->get('Content-Type', ''), 'application/json')) { throw new HttpException(415, 'Usa application/json.'); }
    if (strlen($request->getContent()) > 8192) { throw new HttpException(413, 'Solicitud demasiado extensa.'); }
    try { $body = json_decode($request->getContent(), TRUE, 16, JSON_THROW_ON_ERROR); }
    catch (\JsonException) { throw new HttpException(400, 'JSON inválido.'); }
    if (!is_array($body) || array_is_list($body)) { throw new HttpException(400, 'Se requiere un objeto JSON.'); }
    if ($chat) { $body = array_intersect_key($body, array_flip(['chatInput', 'action', 'sessionId'])); }
    foreach ($body as $value) { if (!is_scalar($value) && $value !== NULL) { throw new HttpException(400, 'Solo se admiten valores simples.'); } }
    return $body;
  }
  public function catalog(Request $request): JsonResponse {
    $this->guard($request, 'roma.api.read');
    $types = [];
    foreach ($this->hotel->types() as $t) {
      $serviceIds = $this->hotel->item('type', (int) $t['id'])['services'];
      $services = array_values(array_filter($this->hotel->services(), static fn(array $s): bool => in_array($s['id'], $serviceIds)));
      $types[] = ['id' => (int) $t['id'], 'name' => $t['name'], 'capacity' => (int) $t['capacity'], 'description' => $t['description'], 'nightly_amount_cents' => (int) $t['amount'], 'currency' => 'PEN', 'services' => array_column($services, 'name')];
    }
    return $this->respond(['demo' => TRUE, 'hotel_reference' => 'Hotel Golden Roma · referencia académica', 'location' => 'Nueva Zelandia 196, Juliaca 21101, Puno, Perú (enlace facilitado por el usuario)', 'types' => $types, 'services' => array_column($this->hotel->services(), 'name')]);
  }
  public function availability(Request $request): JsonResponse {
    $this->guard($request, 'roma.api.read');
    try {
      $guests = filter_var($request->query->get('guests'), FILTER_VALIDATE_INT);
      if ($guests === FALSE || $guests === NULL) { throw new \DomainException('Indica huéspedes enteros.'); }
      $rows = $this->hotel->availability((string) $request->query->get('arrival', ''), (string) $request->query->get('departure', ''), $guests);
      $rooms = array_map(static fn(array $r): array => ['room_id' => (int) $r['id'], 'type' => $r['type_name'], 'capacity' => (int) $r['capacity'], 'nights' => $r['nights'], 'estimated_amount_cents' => $r['estimated_price'], 'currency' => 'PEN'], $rows);
      return $this->respond(['demo' => TRUE, 'arrival_inclusive' => TRUE, 'departure_exclusive' => TRUE, 'rooms' => $rooms]);
    }
    catch (\DomainException $error) { return $this->respond(['error' => $error->getMessage()], 422); }
  }
  public function reserve(Request $request): JsonResponse {
    $this->guard($request, 'roma.api.write', 10);
    $body = $this->body($request);
    if (($body['demo_consent'] ?? FALSE) !== TRUE) { return $this->respond(['error' => 'Acepta el uso de datos de demostración.'], 422); }
    $fields = array_intersect_key($body, array_flip(['customer_name', 'email', 'phone', 'room_id', 'arrival', 'departure', 'guests', 'request_key']));
    try { return $this->respond(['demo' => TRUE, 'reservation' => $this->hotel->request($fields)], 201); }
    catch (\DomainException $error) { return $this->respond(['error' => $error->getMessage()], 422); }
  }
  public function status(Request $request): JsonResponse {
    $this->guard($request, 'roma.api.lookup', 20);
    $body = $this->body($request);
    $token = $request->headers->get('X-Reservation-Key', '');
    $row = $this->hotel->lookup((string) ($body['reference'] ?? ''), $token);
    return $row ? $this->respond(['demo' => TRUE, 'reservation' => $row]) : $this->respond(['error' => 'Código o clave incorrectos.'], 404);
  }
}
