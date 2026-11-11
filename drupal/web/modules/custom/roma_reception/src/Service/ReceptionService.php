<?php
namespace Drupal\roma_reception\Service;

use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\roma_booking\Service\HotelService;

final class ReceptionService {
  public const STAYS = ['not_arrived'=>'Por llegar','in_house'=>'Hospedado','checked_out'=>'Salida registrada','no_show'=>'No se presentó'];
  public const CLEAN = ['clean'=>'Lista','dirty'=>'Pendiente de limpieza','cleaning'=>'En limpieza'];
  public const ACTIONS = ['check_in'=>'Registrar entrada','check_out'=>'Registrar salida','no_show'=>'Marcar no presentado','clean'=>'Marcar lista','dirty'=>'Marcar pendiente de limpieza','cleaning'=>'Iniciar limpieza','release'=>'Liberar mantenimiento'];
  public function __construct(private readonly Connection $db, private readonly HotelService $hotel, private readonly TimeInterface $time) {}
  private function write(callable $callback): mixed {
    $tx=$this->db->startTransaction();
    try { $this->db->query('SELECT id FROM {roma_guard} WHERE id=1 FOR UPDATE')->fetchField(); $value=$callback(); $tx->commitOrRelease(); return $value; }
    catch (\Throwable $error) { $tx->rollBack(); throw $error; }
  }
  private function event(string $kind, int $id, string $action, int $actor): void {
    $this->db->insert('roma_reception_event')->fields(['kind'=>$kind,'entity_id'=>$id,'action'=>$action,'actor_uid'=>$actor,'created'=>$this->time->getCurrentTime()])->execute();
  }
  public function stay(int $id): array {
    return $this->db->select('roma_stay','s')->fields('s')->condition('reservation_id',$id)->execute()->fetchAssoc() ?: ['reservation_id'=>$id,'state'=>'not_arrived','check_in'=>0,'check_out'=>0];
  }
  public function housekeeping(int $room): string {
    return $this->db->select('roma_housekeeping','h')->fields('h',['state'])->condition('room_id',$room)->execute()->fetchField() ?: 'clean';
  }
  private function occupied(int $room): bool {
    $q=$this->db->select('roma_stay','s');$q->join('roma_reservation','b','b.id=s.reservation_id');
    return (bool)$q->condition('b.room_id',$room)->condition('s.state','in_house')->countQuery()->execute()->fetchField();
  }
  public function assertAvailable(int $room, string $arrival, string $departure): void {
    if ($this->db->select('roma_room_block','m')->condition('room_id',$room)->condition('active',1)->condition('arrival',$departure,'<')->condition('departure',$arrival,'>')->countQuery()->execute()->fetchField()) {
      throw new \DomainException('La habitación está bloqueada por mantenimiento en esas fechas.');
    }
    if ($arrival <= $this->hotel->today() && $this->housekeeping($room) !== 'clean') {
      throw new \DomainException('La habitación necesita terminar la limpieza antes de recibir una entrada hoy.');
    }
    // Una salida vencida sin checkout requiere resolver la ocupación de hoy.
    if ($arrival <= $this->hotel->today() && $this->occupied($room)) {
      $q=$this->db->select('roma_stay','s');$q->join('roma_reservation','b','b.id=s.reservation_id');
      if ($q->condition('b.room_id',$room)->condition('s.state','in_house')->condition('b.departure',$this->hotel->today(),'<=')->countQuery()->execute()->fetchField()) {
        throw new \DomainException('Existe una salida pendiente en esta habitación. Recepción debe resolverla.');
      }
    }
  }
  public function action(string $kind, int $id, string $action, int $actor): void {
    $this->write(function () use($kind,$id,$action,$actor) {
      $now=$this->time->getCurrentTime();$today=$this->hotel->today();
      if ($kind==='stay' && in_array($action,['check_in','check_out','no_show'],TRUE)) {
        $booking=$this->hotel->reservation($id);$stay=$this->stay($id);$room=(int)$booking['room_id'];
        $target=['check_in'=>'in_house','check_out'=>'checked_out','no_show'=>'no_show'][$action];
        if ($stay['state']===$target) { return; }
        if ($booking['status']!=='confirmed') { throw new \DomainException('La reserva debe estar confirmada para registrar una operación de recepción.'); }
        if ($action==='check_in') {
          if ($stay['state']!=='not_arrived' || $booking['arrival']>$today || $booking['departure']<=$today) { throw new \DomainException('La entrada requiere una reserva vigente para hoy y sin entrada anterior.'); }
          if ($this->occupied($room)) { throw new \DomainException('La habitación tiene una entrada sin salida registrada.'); }
          $data=$this->hotel->room($room);$type=$this->hotel->item('type',(int)$data['type_id']);
          if (!$data['active'] || !$type['active']) { throw new \DomainException('La habitación o su tipo están desactivados.'); }
          $this->assertAvailable($room,$today,$booking['departure']);$stay['check_in']=$now;
        }
        elseif ($action==='check_out') {
          if ($stay['state']!=='in_house') { throw new \DomainException('Primero debes registrar la entrada.'); }
          $stay['check_out']=$now;$this->db->merge('roma_housekeeping')->key('room_id',$room)->fields(['state'=>'dirty','updated'=>$now])->execute();
        }
        else {
          if ($stay['state']!=='not_arrived' || $booking['arrival']>$today) { throw new \DomainException('Solo puedes marcar no presentado después de la llegada prevista y sin entrada registrada.'); }
          $this->hotel->transition($id,'cancelled',$actor);
        }
        $this->db->merge('roma_stay')->key('reservation_id',$id)->fields(['state'=>$target,'check_in'=>$stay['check_in'],'check_out'=>$stay['check_out'],'updated'=>$now])->execute();
      }
      elseif ($kind==='room' && isset(self::CLEAN[$action])) {
        $this->hotel->room($id);
        if ($this->occupied($id)) { throw new \DomainException('Registra la salida antes de cambiar el estado de limpieza.'); }
        if ($this->housekeeping($id)===$action) { return; }
        $this->db->merge('roma_housekeeping')->key('room_id',$id)->fields(['state'=>$action,'updated'=>$now])->execute();
      }
      elseif ($kind==='block' && $action==='release') {
        $block=$this->db->select('roma_room_block','b')->fields('b')->condition('id',$id)->execute()->fetchAssoc();
        if (!$block) { throw new \DomainException('El bloqueo no existe.'); }
        if (!$block['active']) { return; }
        $this->db->update('roma_room_block')->fields(['active'=>0])->condition('id',$id)->execute();
      }
      else { throw new \DomainException('Operación de recepción inválida.'); }
      $this->event($kind,$id,$action,$actor);
    });
  }
  public function block(int $room, string $arrival, string $departure, string $reason, int $actor): int {
    return $this->write(function () use($room,$arrival,$departure,$reason,$actor) {
      $this->hotel->room($room);$this->hotel->dates($arrival,$departure,1);$reason=trim($reason);
      if (mb_strlen($reason)<5 || mb_strlen($reason)>200) { throw new \DomainException('Explica el mantenimiento con entre 5 y 200 caracteres.'); }
      $conflicts=$this->db->select('roma_reservation','b')->condition('room_id',$room)->condition('status','confirmed')->condition('arrival',$departure,'<')->condition('departure',$arrival,'>');
      \Drupal::moduleHandler()->invokeAll('roma_room_conflicts_query',[$conflicts]);
      if ($conflicts->countQuery()->execute()->fetchField()) { throw new \DomainException('El mantenimiento se superpone con una reserva confirmada. Reasigna o resuelve la reserva primero.'); }
      if ($arrival===$this->hotel->today() && $this->occupied($room)) { throw new \DomainException('No bloquees una habitación con huéspedes sin salida.'); }
      if ($this->db->select('roma_room_block','m')->condition('room_id',$room)->condition('active',1)->condition('arrival',$departure,'<')->condition('departure',$arrival,'>')->countQuery()->execute()->fetchField()) { throw new \DomainException('Ya hay un mantenimiento activo en ese intervalo.'); }
      $id=(int)$this->db->insert('roma_room_block')->fields(['room_id'=>$room,'arrival'=>$arrival,'departure'=>$departure,'reason'=>$reason,'active'=>1,'actor_uid'=>$actor,'created'=>$this->time->getCurrentTime()])->execute();
      $this->event('block',$id,'create',$actor);return $id;
    });
  }
  public function calendar(string $start, int $days): array {
    $date=\DateTimeImmutable::createFromFormat('!Y-m-d',$start);
    if (!$date || $date->format('Y-m-d')!==$start || !in_array($days,[7,14,31],TRUE)) { throw new \DomainException('Selecciona una fecha válida y un periodo de 7, 14 o 31 días.'); }
    $end=$date->modify('+'.$days.' days')->format('Y-m-d');$dates=[];
    for ($i=0;$i<$days;$i++) { $dates[]=$date->modify('+'.$i.' days')->format('Y-m-d'); }
    $q=$this->db->select('roma_reservation','b')->fields('b',['id','reference','room_id','arrival','departure','status','customer_name'])->condition('status',['pending','confirmed'],'IN')->condition('arrival',$end,'<')->condition('departure',$start,'>');
    $reservations=$q->orderBy('arrival')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach($reservations as &$booking) {
      $stay=$this->stay((int)$booking['id']);
      if($stay['state']==='checked_out') {
        $actual=(new \DateTimeImmutable('@'.$stay['check_out']))->setTimezone(new \DateTimeZone('America/Lima'))->format('Y-m-d');
        $booking['departure']=min($booking['departure'],$actual);
      }
    }
    unset($booking);
    $blocks=$this->db->select('roma_room_block','m')->fields('m')->condition('active',1)->condition('arrival',$end,'<')->condition('departure',$start,'>')->orderBy('arrival')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    return ['dates'=>$dates,'reservations'=>$reservations,'blocks'=>$blocks];
  }
  public function today(): array {
    $today=$this->hotel->today();$q=$this->db->select('roma_reservation','b')->fields('b');$q->leftJoin('roma_stay','s','s.reservation_id=b.id');
    $q->condition('b.status','confirmed')->condition($q->orConditionGroup()->condition('b.arrival',$today)->condition('b.departure',$today)->condition('s.state','in_house')->condition($q->andConditionGroup()->condition('b.arrival',$today,'<')->isNull('s.state')));
    return $q->orderBy('b.arrival')->execute()->fetchAll(\PDO::FETCH_ASSOC);
  }
  public function events(): array { return $this->db->select('roma_reception_event','e')->fields('e')->orderBy('id','DESC')->range(0,20)->execute()->fetchAll(\PDO::FETCH_ASSOC); }
}
