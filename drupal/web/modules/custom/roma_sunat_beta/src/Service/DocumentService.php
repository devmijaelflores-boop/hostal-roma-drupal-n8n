<?php
namespace Drupal\roma_sunat_beta\Service;
use Drupal\Core\Database\Connection;
use Drupal\Component\Datetime\TimeInterface;
use Drupal\roma_finance\Service\LedgerService;

final class DocumentService {
  public const STATES=['prepared'=>'XML firmado preparado','sending'=>'Envío en curso','accepted'=>'Aceptado en beta: prueba estructural','rejected'=>'Rechazado en beta','error'=>'Error del servicio beta','uncertain'=>'Respuesta no verificable: requiere revisión','late_response'=>'Respuesta tardía conservada'];
  public const TYPES=['01'=>'Factura de prueba UBL 2.1','03'=>'Boleta de prueba UBL 2.1'];
  public function __construct(private readonly Connection $db,private readonly LedgerService $ledger,private readonly TimeInterface $time,private readonly BetaGateway $gateway) {}
  private function write(callable $fn): mixed {
    $tx=$this->db->startTransaction();
    try{$this->db->query('SELECT id FROM {roma_guard} WHERE id=1 FOR UPDATE')->fetchField();$out=$fn();$tx->commitOrRelease();return $out;}catch(\Throwable $e){$tx->rollBack();throw $e;}
  }
  private function normalize(array $row): array {
    foreach(['id','reservation_id','number','amount','actor_uid','created','updated','attempts'] as $key)$row[$key]=(int)$row[$key];
    foreach(['xml','cdr'] as $key)if(isset($row[$key])&&is_resource($row[$key]))$row[$key]=stream_get_contents($row[$key]);return $row;
  }
  public function document(int $id): array {
    $row=$this->db->select('roma_beta_document','d')->fields('d')->condition('id',$id)->execute()->fetchAssoc();return $row?$this->normalize($row):throw new \DomainException('El documento beta no existe.');
  }
  public function listing(): array {
    return array_map($this->normalize(...),$this->db->select('roma_beta_document','d')->fields('d',['id','reservation_id','type','series','number','filename','mode','state','amount','code','description','actor_uid','created','updated','attempts'])->orderBy('id','DESC')->range(0,100)->execute()->fetchAll(\PDO::FETCH_ASSOC));
  }
  public function attempts(int $id): array {return $this->db->select('roma_beta_attempt','a')->fields('a',['number','actor_uid','started','finished','state','code','description'])->condition('document_id',$id)->orderBy('number')->execute()->fetchAll(\PDO::FETCH_ASSOC);}
  private function source(int $reservation): array {
    $account=$this->ledger->account($reservation);
    if(!$account['open']||$account['charges']<1||$account['balance']!==0||$account['booking']['status']!=='confirmed')throw new \DomainException('Para este ejercicio de contado, la reserva debe estar confirmada y la cuenta de demostración abierta, con cargos positivos y saldo cero.');
    foreach($account['entries'] as $entry)if($entry['mode']!=='demo')throw new \DomainException('No se envían movimientos reales a beta.');
    $entries=array_map(static fn(array $e):array=>array_intersect_key($e,array_flip(['id','kind','amount','reverses_id','mode'])),$account['entries']);
    return ['amount'=>$account['charges'],'hash'=>hash('sha256',json_encode([$reservation,$account['charges'],$account['collected'],$entries],JSON_THROW_ON_ERROR))];
  }
  public function prepare(int $reservation,string $type,string $key,int $actor): array {
    return $this->write(function()use($reservation,$type,$key,$actor){
      if(!isset(self::TYPES[$type])||!preg_match('/^[a-f0-9]{48}$/D',$key))throw new \DomainException('Tipo o identificador de preparación inválido.');
      $old=$this->db->select('roma_beta_document','d')->fields('d',['id','reservation_id','type'])->condition('request_key',$key)->execute()->fetchAssoc();
      if($old){if((int)$old['reservation_id']!==$reservation||$old['type']!==$type)throw new \DomainException('Identificador reutilizado para otra prueba.');return $this->document((int)$old['id']);}
      $source=$this->source($reservation);$number=(int)$this->db->select('roma_beta_counter','c')->fields('c',['next_number'])->condition('type',$type)->execute()->fetchField();
      if($number<1||$number>99999999)throw new \DomainException('El correlativo beta no está disponible.');
      $now=$this->time->getCurrentTime();$series=$type==='01'?'F901':'B901';
      $snapshot=['version'=>1,'mode'=>'beta','type'=>$type,'series'=>$series,'number'=>$number,'amount'=>$source['amount'],'created'=>$now,'scenario'=>'Ejercicio gravado al 18 %, identidades genéricas. No representa una operación comercial ni régimen fiscal del hotel.'];
      $xml=$this->gateway->build($snapshot);
      $this->db->update('roma_beta_counter')->fields(['next_number'=>$number+1])->condition('type',$type)->execute();
      $id=(int)$this->db->insert('roma_beta_document')->fields(['reservation_id'=>$reservation,'type'=>$type,'series'=>$series,'number'=>$number,'filename'=>BetaGateway::ISSUER.'-'.$type.'-'.$series.'-'.$number,'mode'=>'beta','state'=>'prepared','amount'=>$source['amount'],'snapshot'=>json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'source_hash'=>$source['hash'],'xml'=>$xml,'xml_hash'=>hash('sha256',$xml),'cdr'=>'','cdr_hash'=>'','code'=>'','description'=>'Documento académico preparado localmente; todavía no enviado.','request_key'=>$key,'actor_uid'=>$actor,'created'=>$now,'updated'=>$now,'attempts'=>0])->execute();
      return $this->document($id);
    });
  }
  private function clean(string $text,int $max=1000): string {
    $text=strip_tags($text);$password=getenv('ROMA_SUNAT_BETA_PASSWORD');if($password)$text=str_replace($password,'[OCULTO]',$text);return mb_substr(preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F]/','',$text),0,$max);
  }
  public function send(int $id,int $actor): array {
    $claim=$this->write(function()use($id,$actor){
      $doc=$this->document($id);if(in_array($doc['state'],['accepted','rejected'],TRUE))return ['terminal'=>$doc];
      if(!$this->gateway->ready())throw new \DomainException('El envío beta no está configurado con SOAP y las credenciales genéricas de prueba.');
      if($doc['mode']!=='beta'||!hash_equals($doc['xml_hash'],hash('sha256',$doc['xml'])))throw new \DomainException('La integridad o el modo del XML no son válidos.');
      if($doc['state']==='sending')throw new \DomainException('Ya hay un envío en curso. No lo repitas; revisa su resultado o recupera el intento si quedó detenido.');
      if(!in_array($doc['state'],['prepared','error','uncertain'],TRUE)||$doc['attempts']>=3)throw new \DomainException('Este documento no admite otro intento. Máximo tres intentos manuales.');
      $source=$this->source($doc['reservation_id']);if(!hash_equals($source['hash'],$doc['source_hash']))throw new \DomainException('La cuenta cambió desde que se preparó el XML. Prepara una nueva prueba; el documento anterior se conserva.');
      $now=$this->time->getCurrentTime();$last=(int)$this->db->select('roma_beta_control','c')->fields('c',['last_send'])->condition('id',1)->execute()->fetchField();
      if($last&&$now-$last<60)throw new \DomainException('Espera un minuto entre envíos beta. No se permiten pruebas de carga ni envíos masivos.');
      $attempt=$doc['attempts']+1;$this->db->update('roma_beta_control')->fields(['last_send'=>$now])->condition('id',1)->execute();
      $this->db->update('roma_beta_document')->fields(['state'=>'sending','attempts'=>$attempt,'updated'=>$now])->condition('id',$id)->execute();
      $this->db->insert('roma_beta_attempt')->fields(['document_id'=>$id,'number'=>$attempt,'actor_uid'=>$actor,'started'=>$now,'finished'=>0,'state'=>'sending','code'=>'','description'=>'Solicitud beta iniciada.','cdr'=>''])->execute();
      return ['document'=>$doc,'attempt'=>$attempt];
    });
    if(isset($claim['terminal']))return $claim['terminal'];
    $doc=$claim['document'];
    try{
      $result=$this->gateway->send($doc['filename'],$doc['xml']);
      if(!in_array($result['state']??'', ['accepted','rejected','error','uncertain'],TRUE))throw new \RuntimeException('Respuesta beta inesperada.');
      if(in_array($result['state'],['accepted','rejected'],TRUE)){
        if(empty($result['cdr'])||($result['state']==='accepted'&&$result['code']!=='0'))throw new \RuntimeException('No hay una constancia verificable para ese estado.');
        BetaGateway::validateZip($result['cdr']);
      }
    }catch(\Throwable $e){$result=['state'=>'uncertain','code'=>$e instanceof BetaResponseException?'CDR_FORMAT':'TRANSPORT','description'=>'No se obtuvo una respuesta verificable. '.$e->getMessage(),'cdr'=>$e instanceof BetaResponseException?$e->responseZip:''];}
    return $this->write(function()use($id,$claim,$result){
      $current=$this->document($id);$now=$this->time->getCurrentTime();
      $state=$result['state'];$code=$this->clean((string)($result['code']??''),80);$description=$this->clean((string)($result['description']??''));$cdr=(string)($result['cdr']??'');
      if(strlen($cdr)>2097152){$cdr='';$state='uncertain';$code='CDR_SIZE';$description='Respuesta CDR fuera del límite permitido.';}
      if($current['state']!=='sending'||$current['attempts']!==$claim['attempt']){
        $this->db->update('roma_beta_attempt')->fields(['finished'=>$now,'state'=>'late_response','code'=>$code,'description'=>$this->clean('Respuesta tardía conservada sin cambiar el intento actual. '.$description),'cdr'=>$cdr])->condition('document_id',$id)->condition('number',$claim['attempt'])->execute();return $current;
      }
      $this->db->update('roma_beta_attempt')->fields(['finished'=>$now,'state'=>$state,'code'=>$code,'description'=>$description,'cdr'=>$cdr])->condition('document_id',$id)->condition('number',$claim['attempt'])->execute();
      $this->db->update('roma_beta_document')->fields(['state'=>$state,'code'=>$code,'description'=>$description,'cdr'=>$cdr,'cdr_hash'=>$cdr?hash('sha256',$cdr):'','updated'=>$now])->condition('id',$id)->execute();
      return $this->document($id);
    });
  }
  public function recover(int $id,int $actor): array {
    return $this->write(function()use($id,$actor){
      $doc=$this->document($id);$now=$this->time->getCurrentTime();if($doc['state']!=='sending'||$now-$doc['updated']<300)throw new \DomainException('Solo se recupera un envío sin respuesta que lleve al menos cinco minutos detenido.');
      $text='Recuperación manual por usuario #'.$actor.'. Resultado desconocido; revisar antes de reenviar.';
      $this->db->update('roma_beta_attempt')->fields(['state'=>'uncertain','finished'=>$now,'code'=>'MANUAL_REVIEW','description'=>$text])->condition('document_id',$id)->condition('number',$doc['attempts'])->execute();
      $this->db->update('roma_beta_document')->fields(['state'=>'uncertain','code'=>'MANUAL_REVIEW','description'=>$text,'updated'=>$now])->condition('id',$id)->execute();return $this->document($id);
    });
  }
  public function reviews(int $id): array {return $this->db->select('roma_beta_review','r')->fields('r')->condition('document_id',$id)->orderBy('id')->execute()->fetchAll(\PDO::FETCH_ASSOC);}
  public function review(int $id,int $actor): array {
    return $this->write(function()use($id,$actor){
      $doc=$this->document($id);if(in_array($doc['state'],['accepted','rejected'],TRUE))return $doc;
      if(!in_array($doc['state'],['uncertain','error'],TRUE)||!$doc['cdr']||!hash_equals($doc['cdr_hash'],hash('sha256',$doc['cdr'])))throw new \DomainException('No hay una respuesta conservada íntegra para revisar.');
      try{$result=$this->gateway->parse($doc['filename'],$doc['cdr']);}catch(\RuntimeException $e){throw new \DomainException('El CDR conservado aún no puede validarse: '.$e->getMessage());}
      $now=$this->time->getCurrentTime();$code=$this->clean($result['code'],80);$description=$this->clean('Revisión local del CDR por usuario #'.$actor.'. '.$result['description']);
      $this->db->insert('roma_beta_review')->fields(['document_id'=>$id,'attempt'=>$doc['attempts'],'actor_uid'=>$actor,'created'=>$now,'from_state'=>$doc['state'],'to_state'=>$result['state'],'from_code'=>$doc['code'],'to_code'=>$code,'cdr_hash'=>$doc['cdr_hash']])->execute();
      $this->db->update('roma_beta_document')->fields(['state'=>$result['state'],'code'=>$code,'description'=>$description,'updated'=>$now])->condition('id',$id)->execute();
      return $this->document($id);
    });
  }
}
