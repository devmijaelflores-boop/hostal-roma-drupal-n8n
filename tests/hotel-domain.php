<?php
// Integración contra MariaDB real. Crea y elimina solo sus propias fixtures.
$hotel = \Drupal::service('roma_booking.hotel');
$db = \Drupal::database();
$checks = 0; $type = 0; $room = 0;
$assert = static function (bool $condition, string $name) use (&$checks): void { if (!$condition) { throw new \RuntimeException($name); } $checks++; echo "OK $name\n"; };
$reject = static function (callable $operation, string $name) use ($assert): void { try { $operation(); } catch (\DomainException) { $assert(TRUE, $name); return; } throw new \RuntimeException('No se rechazó: ' . $name); };
try {
  $typeData = ['name' => 'QA tipo ' . bin2hex(random_bytes(3)), 'capacity' => 2, 'price' => '90.10', 'image' => 'individual.jpg', 'category' => 'PRUEBA TEMPORAL', 'description' => 'Fixture temporal; no información real.', 'active' => 1];
  $type = $hotel->saveItem('type', 0, $typeData);
  $room = $hotel->saveItem('room', 0, ['code' => 'QA-' . bin2hex(random_bytes(4)), 'type_id' => $type, 'active' => 1]);
  $arrival = (new \DateTimeImmutable($hotel->today()))->modify('+30 days')->format('Y-m-d');
  $departure = (new \DateTimeImmutable($arrival))->modify('+3 days')->format('Y-m-d');
  $data = ['customer_name' => 'Cliente ficticio de validación', 'email' => 'qa@example.invalid', 'phone' => '', 'room_id' => $room, 'arrival' => $arrival, 'departure' => $departure, 'guests' => 2];
  $reject(fn() => $hotel->dates('2027-02-30', '2027-03-05', 1), 'Fecha inexistente rechazada');
  $reject(fn() => $hotel->dates($arrival, $arrival, 1), 'Salida igual a llegada rechazada');
  $reject(fn() => $hotel->dates('2000-01-01', '2000-01-02', 1), 'Fechas pasadas rechazadas');
  $reject(fn() => $hotel->request(array_replace($data, ['guests' => 3])), 'Capacidad excedida rechazada');
  $reject(fn() => $hotel->request(array_replace($data, ['guests' => '1.5'])), 'Ocupación fraccionaria rechazada');
  $reject(fn() => $hotel->request(array_replace($data, ['phone' => '<script>'])), 'Teléfono inválido rechazado');
  $first = $hotel->request($data + ['request_key' => str_repeat('a', 48)]);
  $second = $hotel->request($data);
  $assert($first['status'] === 'pending', 'Solicitud guardada como pendiente');
  $assert($first['estimated_price'] === 27030, 'Precio en céntimos sin errores de coma flotante');
  $assert($hotel->lookup($first['reference'], 'incorrecta') === NULL, 'Consulta con clave incorrecta rechazada');
  $lookup = $hotel->lookup($first['reference'], $first['token']);
  $assert($lookup && !array_intersect(['email', 'customer_name', 'token_hash'], array_keys($lookup)), 'Consulta autorizada sin datos personales');
  $reject(fn() => $hotel->request($data + ['request_key' => str_repeat('a', 48)]), 'Reenvío idempotente rechazado sin duplicar');
  $hotel->transition($first['id'], 'confirmed', 1);
  $assert($hotel->reservation($first['id'])['status'] === 'confirmed', 'Confirmación persistida');
  $reject(fn() => $hotel->transition($second['id'], 'confirmed', 1), 'Confirmadas superpuestas rechazadas');
  $reject(fn() => $hotel->request($data), 'Solicitud sobre fechas confirmadas rechazada');
  $assert(!in_array($room, array_map('intval', array_column($hotel->availability($arrival, $departure, 2), 'id')), TRUE), 'Disponibilidad excluye ocupación confirmada');
  $adjacent = array_replace($data, ['arrival' => $departure, 'departure' => (new \DateTimeImmutable($departure))->modify('+2 days')->format('Y-m-d')]);
  $third = $hotel->request($adjacent); $hotel->transition($third['id'], 'confirmed', 1);
  $assert($hotel->reservation($third['id'])['status'] === 'confirmed', 'Salida exclusiva permite llegada contigua');
  $reject(fn() => $hotel->saveItem('type', $type, array_replace($typeData, ['capacity' => 1])), 'Reducción de capacidad incompatible rechazada');
  $hotel->saveItem('type', $type, array_replace($typeData, ['price' => '100.00']));
  $assert((int) $hotel->reservation($first['id'])['estimated_price'] === 27030, 'Cambio de tarifa conserva estimado existente');
  $hotel->transition($first['id'], 'cancelled', 1);
  $assert(in_array($room, array_map('intval', array_column($hotel->availability($arrival, $departure, 2), 'id')), TRUE), 'Cancelación libera disponibilidad');
  $reject(fn() => $hotel->transition($first['id'], 'confirmed', 1), 'Cancelada no puede reactivarse');
  $hotel->saveItem('room', $room, ['code' => $hotel->room($room)['code'], 'type_id' => $type, 'active' => 0]);
  $reject(fn() => $hotel->request($data), 'Habitación desactivada rechazada');
  echo "TOTAL $checks comprobaciones de dominio correctas\n";
}
finally {
  if ($room) {
    $ids = $db->select('roma_reservation', 'b')->fields('b', ['id'])->condition('room_id', $room)->execute()->fetchCol();
    if ($ids) { $db->delete('roma_audit')->condition('reservation_id', $ids, 'IN')->execute(); }
    if ($ids && $db->schema()->tableExists('roma_notification')) { $db->delete('roma_notification')->condition('reservation_id', $ids, 'IN')->execute(); }
    $db->delete('roma_reservation')->condition('room_id', $room)->execute();
    $db->delete('roma_room')->condition('id', $room)->execute();
  }
  if ($type) { $db->delete('roma_rate')->condition('type_id', $type)->execute(); $db->delete('roma_type_service')->condition('type_id', $type)->execute(); $db->delete('roma_room_type')->condition('id', $type)->execute(); }
  \Drupal::service('cache_tags.invalidator')->invalidateTags(['roma_catalog']);
}
