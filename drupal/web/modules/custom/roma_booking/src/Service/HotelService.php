<?php

namespace Drupal\roma_booking\Service;

use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;

/** Fuente de verdad de la demo. Importes enteros en céntimos PEN. */
final class HotelService {
  public const STATUS = ['pending' => 'Pendiente', 'confirmed' => 'Confirmada', 'cancelled' => 'Cancelada'];
  public function __construct(private readonly Connection $db, private readonly TimeInterface $time) {}

  public static function money(int $cents): string { return 'S/ ' . number_format($cents / 100, 2, '.', ','); }
  public static function date(string $date): string { return implode('/', array_reverse(explode('-', $date))); }
  public function today(): string { return (new \DateTimeImmutable('@' . $this->time->getCurrentTime()))->setTimezone(new \DateTimeZone('America/Lima'))->format('Y-m-d'); }

  public function dates(string $arrival, string $departure, int $guests): int {
    foreach ([$arrival, $departure] as $value) {
      $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('America/Lima'));
      if (!$date || $date->format('Y-m-d') !== $value) { throw new \DomainException('Introduce fechas válidas.'); }
    }
    if ($arrival < $this->today()) { throw new \DomainException('La llegada no puede estar en el pasado.'); }
    $nights = (int) (new \DateTimeImmutable($arrival))->diff(new \DateTimeImmutable($departure))->format('%r%a');
    if ($nights < 1 || $nights > 180) { throw new \DomainException('La salida debe ser posterior a la llegada y la estadía no puede superar 180 noches.'); }
    if ($guests < 1 || $guests > 10) { throw new \DomainException('El número de huéspedes debe estar entre 1 y 10.'); }
    return $nights;
  }

  public function types(bool $active = TRUE): array {
    $q = $this->db->select('roma_room_type', 't')->fields('t');
    $q->join('roma_rate', 'p', 'p.type_id = t.id');
    $q->addField('p', 'amount');
    if ($active) { $q->condition('t.active', 1); }
    return $q->orderBy('t.id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function rooms(bool $active = TRUE): array {
    $q = $this->db->select('roma_room', 'r')->fields('r');
    $q->join('roma_room_type', 't', 't.id = r.type_id');
    $q->join('roma_rate', 'p', 'p.type_id = t.id');
    $q->fields('t', ['capacity', 'description', 'image', 'category'])->addField('t', 'name', 'type_name');
    $q->addField('p', 'amount');
    if ($active) { $q->condition('r.active', 1)->condition('t.active', 1); }
    return $q->orderBy('r.code')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function room(int $id): array {
    foreach ($this->rooms(FALSE) as $room) { if ((int) $room['id'] === $id) { return $room; } }
    throw new \DomainException('La habitación solicitada no existe.');
  }

  private function availableRoom(array $room, string $arrival, string $departure, int $guests, int $except = 0): void {
    $type = $this->item('type', (int) $room['type_id']);
    if (!$room['active'] || !$type['active']) { throw new \DomainException('La habitación está desactivada. Elige otra.'); }
    if ($guests > (int) $room['capacity']) { throw new \DomainException('La cantidad de huéspedes supera la capacidad de la habitación.'); }
    $q = $this->db->select('roma_reservation', 'b')->condition('room_id', $room['id'])->condition('status', 'confirmed')->condition('arrival', $departure, '<')->condition('departure', $arrival, '>');
    if ($except) { $q->condition('id', $except, '<>'); }
    if ($q->countQuery()->execute()->fetchField()) { throw new \DomainException('La habitación ya tiene una reserva confirmada para esas fechas.'); }
  }

  public function availability(string $arrival, string $departure, int $guests): array {
    $nights = $this->dates($arrival, $departure, $guests);
    $result = [];
    foreach ($this->rooms() as $room) {
      try { $this->availableRoom($room, $arrival, $departure, $guests); }
      catch (\DomainException) { continue; }
      $room['nights'] = $nights;
      $room['estimated_price'] = $nights * (int) $room['amount'];
      $result[] = $room;
    }
    return $result;
  }

  /** Serializa escrituras sobre un registro InnoDB; también protege concurrencia. */
  private function write(callable $callback): mixed {
    $transaction = $this->db->startTransaction();
    try {
      $this->db->query('SELECT id FROM {roma_guard} WHERE id = 1 FOR UPDATE')->fetchField();
      $result = $callback();
      $transaction->commitOrRelease();
      return $result;
    }
    catch (\Throwable $error) { $transaction->rollBack(); throw $error; }
  }

  public function request(array $data): array {
    return $this->write(function () use ($data) {
      $arrival = (string) ($data['arrival'] ?? ''); $departure = (string) ($data['departure'] ?? '');
      $guests = filter_var($data['guests'] ?? NULL, FILTER_VALIDATE_INT);
      $roomId = filter_var($data['room_id'] ?? NULL, FILTER_VALIDATE_INT);
      if ($guests === FALSE || $roomId === FALSE || $guests === NULL || $roomId === NULL) { throw new \DomainException('Habitación y huéspedes deben ser enteros válidos.'); }
      $nights = $this->dates($arrival, $departure, $guests);
      $name = trim((string) ($data['customer_name'] ?? '')); $email = trim((string) ($data['email'] ?? '')); $phone = trim((string) ($data['phone'] ?? ''));
      if (mb_strlen($name) < 2 || mb_strlen($name) > 100 || mb_strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL)) { throw new \DomainException('Introduce un nombre de prueba y un correo válidos.'); }
      if ($phone !== '' && !preg_match('/^\+?[0-9 ()-]{6,25}$/D', $phone)) { throw new \DomainException('El teléfono debe contener entre 6 y 25 caracteres válidos.'); }
      $requestKey = (string) ($data['request_key'] ?? bin2hex(random_bytes(24)));
      if (!preg_match('/^[a-f0-9]{32,64}$/D', $requestKey)) { throw new \DomainException('Identificador de envío inválido.'); }
      if ($this->db->select('roma_reservation', 'b')->condition('request_key', $requestKey)->countQuery()->execute()->fetchField()) { throw new \DomainException('Este envío ya fue procesado. Consulta tu solicitud con el código y la clave que recibiste.'); }
      $room = $this->room($roomId);
      $this->availableRoom($room, $arrival, $departure, $guests);
      $token = bin2hex(random_bytes(24));
      $reference = 'ROMA-' . strtoupper(bin2hex(random_bytes(6)));
      $now = $this->time->getCurrentTime();
      $id = $this->db->insert('roma_reservation')->fields([
        'reference' => $reference, 'token_hash' => hash('sha256', $token), 'request_key' => $requestKey,
        'customer_name' => $name, 'email' => $email, 'phone' => $phone, 'room_id' => $roomId,
        'arrival' => $arrival, 'departure' => $departure, 'guests' => $guests, 'status' => 'pending',
        'estimated_price' => $nights * (int) $room['amount'], 'created' => $now, 'updated' => $now,
      ])->execute();
      $this->db->insert('roma_audit')->fields(['reservation_id' => $id, 'from_status' => '', 'to_status' => 'pending', 'actor_uid' => 0, 'created' => $now])->execute();
      \Drupal::moduleHandler()->invokeAll('roma_reservation_changed', [(int) $id, 'pending']);
      return ['id' => (int) $id, 'reference' => $reference, 'token' => $token, 'status' => 'pending', 'estimated_price' => $nights * (int) $room['amount']];
    });
  }

  public function reservation(int $id): array {
    $row = $this->db->select('roma_reservation', 'b')->fields('b')->condition('id', $id)->execute()->fetchAssoc();
    if (!$row) { throw new \DomainException('La solicitud no existe.'); }
    return $row;
  }

  public function lookup(string $reference, string $token): ?array {
    if (strlen($reference) > 32 || strlen($token) > 100) { return NULL; }
    $row = $this->db->select('roma_reservation', 'b')->fields('b')->condition('reference', strtoupper(trim($reference)))->execute()->fetchAssoc();
    if (!$row || !hash_equals($row['token_hash'], hash('sha256', trim($token)))) { return NULL; }
    $room = $this->room((int) $row['room_id']);
    // La consulta autorizada no devuelve datos personales ni hashes.
    return array_intersect_key($row, array_flip(['reference', 'status', 'arrival', 'departure', 'guests', 'estimated_price', 'created'])) + ['room' => $room['code'], 'type' => $room['type_name']];
  }

  public function transition(int $id, string $to, int $actor): void {
    if (!in_array($to, ['confirmed', 'cancelled'], TRUE)) { throw new \DomainException('Estado de destino inválido.'); }
    $this->write(function () use ($id, $to, $actor) {
      $row = $this->reservation($id);
      if ($row['status'] === $to) { return; }
      if ($row['status'] === 'cancelled') { throw new \DomainException('Una solicitud cancelada no puede confirmarse. Registra una nueva.'); }
      if ($to === 'confirmed') {
        $this->dates($row['arrival'], $row['departure'], (int) $row['guests']);
        $this->availableRoom($this->room((int) $row['room_id']), $row['arrival'], $row['departure'], (int) $row['guests'], $id);
      }
      $now = $this->time->getCurrentTime();
      $this->db->update('roma_reservation')->fields(['status' => $to, 'updated' => $now])->condition('id', $id)->execute();
      $this->db->insert('roma_audit')->fields(['reservation_id' => $id, 'from_status' => $row['status'], 'to_status' => $to, 'actor_uid' => $actor, 'created' => $now])->execute();
      \Drupal::moduleHandler()->invokeAll('roma_reservation_changed', [$id, $to]);
    });
  }

  public function item(string $kind, int $id): array {
    $table = ['type' => 'roma_room_type', 'room' => 'roma_room', 'service' => 'roma_service'][$kind] ?? throw new \DomainException('Catálogo inválido.');
    $row = $this->db->select($table, 't')->fields('t')->condition('id', $id)->execute()->fetchAssoc();
    if (!$row) { throw new \DomainException('El registro no existe.'); }
    if ($kind === 'type') {
      $row['amount'] = (int) $this->db->select('roma_rate', 'p')->fields('p', ['amount'])->condition('type_id', $id)->execute()->fetchField();
      $row['services'] = $this->db->select('roma_type_service', 's')->fields('s', ['service_id'])->condition('type_id', $id)->execute()->fetchCol();
    }
    return $row;
  }

  public function services(bool $active = TRUE): array {
    $q = $this->db->select('roma_service', 's')->fields('s');
    if ($active) { $q->condition('active', 1); }
    return $q->orderBy('id')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }

  public function saveItem(string $kind, int $id, array $data): int {
    return $this->write(function () use ($kind, $id, $data) {
      if ($id) { $this->item($kind, $id); }
      $active = !empty($data['active']) ? 1 : 0;
      if ($kind === 'type') {
        $name = trim((string) ($data['name'] ?? '')); $capacity = filter_var($data['capacity'] ?? 0, FILTER_VALIDATE_INT);
        $price = str_replace(',', '.', trim((string) ($data['price'] ?? '')));
        if (!$name || mb_strlen($name) > 80 || $capacity < 1 || $capacity > 10 || !preg_match('/^\d{1,5}(?:\.\d{1,2})?$/D', $price)) { throw new \DomainException('Revisa nombre, capacidad (1–10) y tarifa (máximo 99,999.99 PEN).'); }
        $parts = explode('.', $price); $amount = (int) $parts[0] * 100 + (int) str_pad($parts[1] ?? '', 2, '0');
        if ($amount < 100) { throw new \DomainException('La tarifa mínima de demostración es S/ 1.00.'); }
        $q = $this->db->select('roma_reservation', 'b'); $q->join('roma_room', 'r', 'r.id = b.room_id');
        if ($id && $q->condition('r.type_id', $id)->condition('b.status', 'confirmed')->condition('b.departure', $this->today(), '>')->condition('b.guests', $capacity, '>')->countQuery()->execute()->fetchField()) { throw new \DomainException('No reduzcas la capacidad por debajo de los huéspedes de reservas confirmadas vigentes.'); }
        $image = (string) ($data['image'] ?? 'individual.jpg');
        if (!in_array($image, ['individual.jpg', 'matrimonial.jpg', 'doble.jpg'], TRUE)) { throw new \DomainException('Imagen de referencia inválida.'); }
        $fields = ['name' => $name, 'capacity' => $capacity, 'description' => mb_substr((string) ($data['description'] ?? ''), 0, 1000), 'image' => $image, 'category' => mb_substr((string) ($data['category'] ?? 'CATÁLOGO DE DEMOSTRACIÓN'), 0, 100), 'active' => $active];
        if ($id) { $this->db->update('roma_room_type')->fields($fields)->condition('id', $id)->execute(); }
        else { $id = (int) $this->db->insert('roma_room_type')->fields($fields)->execute(); }
        $this->db->merge('roma_rate')->key('type_id', $id)->fields(['amount' => $amount])->execute();
        $valid = array_map('intval', array_column($this->services(FALSE), 'id'));
        $this->db->delete('roma_type_service')->condition('type_id', $id)->execute();
        foreach (array_unique(array_filter(array_map('intval', $data['services'] ?? []))) as $service) {
          if (!in_array($service, $valid, TRUE)) { throw new \DomainException('Servicio inválido.'); }
          $this->db->insert('roma_type_service')->fields(['type_id' => $id, 'service_id' => $service])->execute();
        }
        return $id;
      }
      if ($kind === 'room') {
        $code = trim((string) ($data['code'] ?? '')); $type = $this->item('type', (int) ($data['type_id'] ?? 0));
        if (!preg_match('/^[\pL\pN _-]{1,30}$/uD', $code)) { throw new \DomainException('Usa un código de hasta 30 letras, números, espacios o guiones.'); }
        if ($this->db->select('roma_room', 'r')->condition('code', $code)->condition('id', $id, '<>')->countQuery()->execute()->fetchField()) { throw new \DomainException('Ese código de habitación ya existe.'); }
        if ($id && $this->db->select('roma_reservation', 'b')->condition('room_id', $id)->condition('status', 'confirmed')->condition('departure', $this->today(), '>')->condition('guests', $type['capacity'], '>')->countQuery()->execute()->fetchField()) { throw new \DomainException('El nuevo tipo no admite la ocupación de una reserva confirmada vigente.'); }
        $fields = ['code' => $code, 'type_id' => $type['id'], 'active' => $active]; $table = 'roma_room';
      }
      elseif ($kind === 'service') {
        $name = trim((string) ($data['name'] ?? ''));
        if (!$name || mb_strlen($name) > 100) { throw new \DomainException('Introduce un nombre de servicio de hasta 100 caracteres.'); }
        $fields = ['name' => $name, 'description' => mb_substr((string) ($data['description'] ?? ''), 0, 1000), 'active' => $active]; $table = 'roma_service';
      }
      else { throw new \DomainException('Catálogo inválido.'); }
      if ($id) { $this->db->update($table)->fields($fields)->condition('id', $id)->execute(); }
      else { $id = (int) $this->db->insert($table)->fields($fields)->execute(); }
      return $id;
    });
  }

  public function reservations(): array {
    return $this->db->select('roma_reservation', 'b')->fields('b')->orderBy('created', 'DESC')->orderBy('id', 'DESC')->range(0, 200)->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }
  public function stats(): array {
    $result = ['pending' => 0, 'confirmed' => 0, 'cancelled' => 0, 'occupied' => 0, 'active_rooms' => count($this->rooms()), 'estimated_revenue' => 0];
    $q = $this->db->select('roma_reservation', 'b')->fields('b', ['status']); $q->addExpression('COUNT(*)', 'total'); $q->groupBy('status');
    foreach ($q->execute() as $row) { $result[$row->status] = (int) $row->total; }
    $q = $this->db->select('roma_reservation', 'b')->condition('status', 'confirmed')->condition('arrival', $this->today(), '<=')->condition('departure', $this->today(), '>');
    $q->addExpression('COUNT(DISTINCT room_id)', 'total'); $result['occupied'] = (int) $q->execute()->fetchField();
    $q = $this->db->select('roma_reservation', 'b')->condition('status', 'confirmed'); $q->addExpression('COALESCE(SUM(estimated_price),0)', 'total'); $result['estimated_revenue'] = (int) $q->execute()->fetchField();
    return $result;
  }
  public function contact(array $data): void {
    $name = trim((string) ($data['name'] ?? '')); $email = trim((string) ($data['email'] ?? '')); $message = trim((string) ($data['message'] ?? ''));
    if (mb_strlen($name) < 2 || mb_strlen($name) > 100 || mb_strlen($email) > 254 || !filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($message) < 10 || mb_strlen($message) > 2000) { throw new \DomainException('Revisa el nombre, correo y mensaje (10–2000 caracteres).'); }
    $this->db->insert('roma_contact')->fields(['name' => $name, 'email' => $email, 'message' => $message, 'created' => $this->time->getCurrentTime()])->execute();
  }
  public function messages(): array { return $this->db->select('roma_contact', 'c')->fields('c')->orderBy('id', 'DESC')->range(0, 100)->execute()->fetchAll(\PDO::FETCH_ASSOC); }
}
