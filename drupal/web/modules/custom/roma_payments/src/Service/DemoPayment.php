<?php
namespace Drupal\roma_payments\Service;
use Drupal\Core\Database\Connection;
use Drupal\roma_booking\Service\HotelService;

/** Ningún método de esta clase accede a una pasarela ni cobra dinero. */
final class DemoPayment {
  public function __construct(private readonly Connection $db, private readonly HotelService $hotel) {}
  public function record(string $reference, string $secret, string $outcome, string $key): array {
    if (!in_array($outcome, ['demo_approved','demo_declined','demo_cancelled'], TRUE) || !preg_match('/^[a-f0-9]{48}$/D',$key)) { throw new \DomainException('Resultado de simulación inválido.'); }
    $transaction = $this->db->startTransaction();
    try {
      $this->db->query('SELECT id FROM {roma_guard} WHERE id = 1 FOR UPDATE')->fetchField();
      $lookup = $this->hotel->lookup($reference,$secret);
      if (!$lookup) { throw new \DomainException('Código o clave incorrectos.'); }
      if ($lookup['status'] !== 'confirmed') { throw new \DomainException('Solo puedes simular el pago de una reserva confirmada de prueba.'); }
      $id = (int) $this->db->select('roma_reservation','b')->fields('b',['id'])->condition('reference',$lookup['reference'])->execute()->fetchField();
      $q = $this->db->select('roma_demo_payment','p')->fields('p')->condition('reservation_id',$id);
      $group=$q->orConditionGroup()->condition('outcome','demo_approved')->condition('request_key',$key);
      $existing = $q->condition($group)->orderBy('id','DESC')->execute()->fetchAssoc();
      if ($existing) { $transaction->commitOrRelease(); return $existing; }
      $row = ['reservation_id'=>$id,'receipt'=>'DEMO-' . strtoupper(bin2hex(random_bytes(6))),'amount'=>(int)$lookup['estimated_price'],'outcome'=>$outcome,'request_key'=>$key,'created'=>time()];
      $row['id'] = $this->db->insert('roma_demo_payment')->fields($row)->execute();
      $transaction->commitOrRelease(); return $row;
    }
    catch (\Throwable $error) { $transaction->rollBack(); throw $error; }
  }
  public function last(string $reference): ?array {
    $q=$this->db->select('roma_demo_payment','p')->fields('p'); $q->join('roma_reservation','b','b.id = p.reservation_id');
    return $q->condition('b.reference',$reference)->orderBy('p.id','DESC')->range(0,1)->execute()->fetchAssoc() ?: NULL;
  }
}
