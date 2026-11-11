<?php
namespace Drupal\roma_finance\Service;
use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\roma_booking\Service\HotelService;

/** Libro exclusivamente académico. No invoca pasarelas ni emite comprobantes. */
final class LedgerService {
  public const KINDS=['opening'=>'Cargo de estadía','charge'=>'Cargo adicional','discount'=>'Descuento','payment'=>'Adelanto / pago simulado','refund'=>'Devolución simulada','reversal'=>'Contrapartida de anulación'];
  public const METHODS=['cash_demo'=>'Efectivo simulado','transfer_demo'=>'Transferencia simulada','card_demo'=>'Tarjeta simulada'];
  public function __construct(private readonly Connection $db,private readonly HotelService $hotel,private readonly TimeInterface $time) {}
  public static function cents(string $value): int {
    $value=trim(str_replace(',','.',$value));
    if(!preg_match('/^\d{1,7}(?:\.\d{1,2})?$/D',$value)) { throw new \DomainException('Usa un importe positivo con hasta dos decimales, sin separador de miles.'); }
    $parts=explode('.',$value);$amount=(int)$parts[0]*100+(int)str_pad($parts[1]??'',2,'0');
    if($amount<1) { throw new \DomainException('El importe debe ser mayor que cero.'); }return $amount;
  }
  private function write(callable $fn): mixed {
    $tx=$this->db->startTransaction();
    try { $this->db->query('SELECT id FROM {roma_guard} WHERE id=1 FOR UPDATE')->fetchField();$result=$fn();$tx->commitOrRelease();return $result; }
    catch(\Throwable $e) { $tx->rollBack();throw $e; }
  }
  private function normalize(array $row): array {
    foreach(['id','reservation_id','amount','actor_uid','created'] as $field)$row[$field]=(int)$row[$field];
    $row['reverses_id']=$row['reverses_id']===NULL?NULL:(int)$row['reverses_id'];return $row;
  }
  public function entries(int $reservation): array { return array_map($this->normalize(...),$this->db->select('roma_finance_entry','e')->fields('e')->condition('reservation_id',$reservation)->orderBy('id')->execute()->fetchAll(\PDO::FETCH_ASSOC)); }
  public function entry(int $id): array {
    $row=$this->db->select('roma_finance_entry','e')->fields('e')->condition('id',$id)->execute()->fetchAssoc();return $row?$this->normalize($row):throw new \DomainException('El movimiento no existe.');
  }
  private function effect(array $entry): array {
    $amount=(int)$entry['amount'];
    return match($entry['kind']) {
      'opening','charge'=>[$amount,0], 'discount'=>[-$amount,0], 'payment'=>[0,$amount], 'refund'=>[0,-$amount],
      'reversal'=>array_map(static fn(int $n): int=>-$n,$this->effect($this->entry((int)$entry['reverses_id']))),
      default=>throw new \LogicException('Tipo de movimiento no reconocido.'),
    };
  }
  public function account(int $id): array {
    $booking=$this->hotel->reservation($id);
    $open=(bool)$this->db->select('roma_finance_account','a')->condition('reservation_id',$id)->countQuery()->execute()->fetchField();
    $entries=$open?$this->entries($id):[];$charges=0;$collected=0;$reversed=[];
    foreach($entries as $entry) { [$a,$b]=$this->effect($entry);$charges+=$a;$collected+=$b;if($entry['reverses_id'])$reversed[]=(int)$entry['reverses_id']; }
    return ['booking'=>$booking,'entries'=>$entries,'open'=>$open,'charges'=>$charges,'collected'=>$collected,'balance'=>$charges-$collected,'reversed'=>$reversed,'mode'=>'demo'];
  }
  private function append(int $reservation,string $kind,int $amount,string $note,string $method,?int $reverses,string $key,string $fingerprint,int $actor): array {
    $row=['reservation_id'=>$reservation,'reference'=>'MOV-'.strtoupper(bin2hex(random_bytes(6))),'kind'=>$kind,'amount'=>$amount,'note'=>$note,'method'=>$method,'reverses_id'=>$reverses,'request_key'=>$key,'fingerprint'=>$fingerprint,'actor_uid'=>$actor,'created'=>$this->time->getCurrentTime(),'mode'=>'demo'];
    $row['id']=(int)$this->db->insert('roma_finance_entry')->fields($row)->execute();
    \Drupal::moduleHandler()->invokeAll('roma_finance_entry_recorded',[$row]);return $row;
  }
  public function open(int $reservation,int $actor): array {
    return $this->write(function()use($reservation,$actor) {
      $account=$this->account($reservation);
      if($account['open'])return $account;
      $this->db->insert('roma_finance_account')->fields(['reservation_id'=>$reservation,'actor_uid'=>$actor,'created'=>$this->time->getCurrentTime()])->execute();
      $key=hash('sha256','roma-finance-opening:'.$reservation);
      $this->append($reservation,'opening',(int)$account['booking']['estimated_price'],'Importe estimado al abrir la cuenta. No es un cargo bancario.','',NULL,$key,$key,$actor);
      return $this->account($reservation);
    });
  }
  public function record(int $reservation,string $kind,int $amount,string $note,string $method,string $key,int $actor): array {
    return $this->write(function()use($reservation,$kind,$amount,$note,$method,$key,$actor) {
      if(!in_array($kind,['charge','discount','payment','refund'],TRUE)||$amount<1||$amount>999999999||!preg_match('/^[a-f0-9]{48}$/D',$key)) { throw new \DomainException('Movimiento, importe o identificador inválido.'); }
      $note=trim($note);if(mb_strlen($note)<5||mb_strlen($note)>500) { throw new \DomainException('Explica el movimiento con entre 5 y 500 caracteres, sin datos bancarios.'); }
      if(in_array($kind,['payment','refund'],TRUE)) { if(!isset(self::METHODS[$method]))throw new \DomainException('Selecciona un medio de simulación válido.'); }
      else { $method=''; }
      $fingerprint=hash('sha256',json_encode([$reservation,$kind,$amount,$note,$method],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
      $old=$this->db->select('roma_finance_entry','e')->fields('e')->condition('request_key',$key)->execute()->fetchAssoc();
      if($old) { if(!hash_equals($old['fingerprint'],$fingerprint))throw new \DomainException('El identificador ya se usó para otro movimiento.');return $this->normalize($old); }
      $account=$this->account($reservation);if(!$account['open'])throw new \DomainException('Abre la cuenta antes de registrar movimientos.');
      if($kind==='payment' && $account['booking']['status']!=='confirmed')throw new \DomainException('Confirma la reserva antes de registrar un adelanto simulado.');
      if($kind==='payment' && $amount>$account['balance'])throw new \DomainException('El pago supera el saldo pendiente.');
      if($kind==='refund' && $amount>$account['collected'])throw new \DomainException('La devolución supera lo registrado como pagado.');
      if($kind==='discount' && $amount>$account['charges'])throw new \DomainException('El descuento supera los cargos netos.');
      return $this->append($reservation,$kind,$amount,$note,$method,NULL,$key,$fingerprint,$actor);
    });
  }
  public function reverse(int $id,string $note,string $key,int $actor): array {
    return $this->write(function()use($id,$note,$key,$actor) {
      $entry=$this->entry($id);$note=trim($note);
      if($entry['kind']==='reversal'||mb_strlen($note)<5||mb_strlen($note)>500||!preg_match('/^[a-f0-9]{48}$/D',$key))throw new \DomainException('Indica un motivo válido. No se anula una contrapartida; registra la corrección correspondiente.');
      $fingerprint=hash('sha256',json_encode(['reverse',$id,$note],JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR));
      $old=$this->db->select('roma_finance_entry','e')->fields('e')->condition('request_key',$key)->execute()->fetchAssoc();
      if($old) { if(!hash_equals($old['fingerprint'],$fingerprint))throw new \DomainException('Identificador reutilizado para otra operación.');return $this->normalize($old); }
      $account=$this->account((int)$entry['reservation_id']);if(in_array($id,$account['reversed'],TRUE))throw new \DomainException('El movimiento ya tiene una contrapartida.');
      [$charge,$paid]=$this->effect($entry);
      if($account['charges']-$charge<0||$account['collected']-$paid<0)throw new \DomainException('La anulación dejaría cargos o pagos netos negativos. Corrige primero los movimientos relacionados.');
      return $this->append((int)$entry['reservation_id'],'reversal',(int)$entry['amount'],$note,'',$id,$key,$fingerprint,$actor);
    });
  }
  public function overview(string $from,string $to): array {
    $tz=new \DateTimeZone('America/Lima');$start=\DateTimeImmutable::createFromFormat('!Y-m-d',$from,$tz);$end=\DateTimeImmutable::createFromFormat('!Y-m-d',$to,$tz);
    if(!$start||!$end||$start->format('Y-m-d')!==$from||$end->format('Y-m-d')!==$to||$to<$from||$start->diff($end)->days>366)throw new \DomainException('Selecciona un periodo válido de hasta 367 días.');
    $charges=0;$collected=0;$count=0;
    $rows=$this->db->select('roma_finance_entry','e')->fields('e')->condition('created',$start->getTimestamp(),'>=')->condition('created',$end->modify('+1 day')->getTimestamp(),'<')->execute()->fetchAll(\PDO::FETCH_ASSOC);
    foreach($rows as $row) { [$a,$b]=$this->effect($row);$charges+=$a;$collected+=$b;$count++; }
    $accounts=[];$q=$this->db->select('roma_reservation','b')->fields('b',['id'])->orderBy('created','DESC')->orderBy('id','DESC')->range(0,100);
    foreach($q->execute()->fetchCol() as $id) { $accounts[]=$this->account((int)$id); }
    return ['charges'=>$charges,'collected'=>$collected,'count'=>$count,'accounts'=>$accounts,'from'=>$from,'to'=>$to];
  }
}
