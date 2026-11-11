<?php
/** Pruebas locales: el transporte es simulado; nunca llama a SUNAT. */
use Drupal\roma_sunat_beta\Service\{BetaGateway,BetaResponseException,DocumentService};
use Drupal\Component\Datetime\TimeInterface;
final class RomaBetaTestTime implements TimeInterface {
 public int $now;public function __construct(){ $this->now=time()+10000; }
 public function getRequestTime(){return $this->now;}public function getCurrentTime(){return $this->now;}
 public function getRequestMicroTime(){return (float)$this->now;}public function getCurrentMicroTime(){return (float)$this->now;}
}
function roma_test_zip(array $files):string{
 $file=tempnam(sys_get_temp_dir(),'roma-beta-fixture-');$z=new ZipArchive();$z->open($file,ZipArchive::OVERWRITE);
 foreach($files as $name=>$body){if(str_ends_with($name,'/'))$z->addEmptyDir(rtrim($name,'/'));else $z->addFromString($name,$body);}$z->close();$data=file_get_contents($file);unlink($file);return $data;
}
function roma_test_cdr(string $name,string $code='0'):string{
 $parts=explode('-',$name);$ref=$parts[2].'-'.$parts[3];
 $xml='<?xml version="1.0"?><ApplicationResponse xmlns="urn:oasis:names:specification:ubl:schema:xsd:ApplicationResponse-2" xmlns:cac="urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2" xmlns:cbc="urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2"><cac:DocumentResponse><cac:Response><cbc:ReferenceID>'.$ref.'</cbc:ReferenceID><cbc:ResponseCode>'.$code.'</cbc:ResponseCode><cbc:Description>RESPUESTA SIMULADA SOLO PARA PRUEBA LOCAL</cbc:Description></cac:Response></cac:DocumentResponse></ApplicationResponse>';
 return roma_test_zip(['dummy/'=>'','R-'.$name.'.xml'=>$xml]);
}
final class RomaBetaFakeGateway extends BetaGateway {
 public int $calls=0;public string $code='0';public string $behavior='response';public bool $configured=TRUE;public array $last=[];public $probe=NULL;
 public function ready():bool{return $this->configured;}
 public function send(string $filename,string $xml):array{
  $this->calls++;$this->last=[$filename,hash('sha256',$xml)];if($this->probe)($this->probe)();
  if($this->behavior==='timeout')throw new RuntimeException('Timeout de transporte SIMULADO');
  $zip=roma_test_cdr($filename,$this->code);if($this->behavior==='review')throw new BetaResponseException('Respuesta SIMULADA conservada para revisión',$zip);
  return $this->parse($filename,$zip);
 }
}
$db=\Drupal::database();$h=\Drupal::service('roma_booking.hotel');$ledger=\Drupal::service('roma_finance.ledger');$clock=new RomaBetaTestTime();$gateway=new RomaBetaFakeGateway();$service=new DocumentService($db,$ledger,$clock,$gateway);$checks=[];
$check=function(bool $ok,string $label)use(&$checks){if(!$ok)throw new RuntimeException($label);$checks[]=$label;echo 'OK '.$label.PHP_EOL;};
$reject=function(callable $fn,string $label)use($check){try{$fn();}catch(DomainException|RuntimeException $e){$check(TRUE,$label);return;}$check(FALSE,$label);};
$key=fn()=>bin2hex(random_bytes(24));$tx=$db->startTransaction();
try{
 $a=(new DateTimeImmutable($h->today()))->modify('+100 days')->format('Y-m-d');$d=(new DateTimeImmutable($a))->modify('+1 day')->format('Y-m-d');
 $room=$h->saveItem('room',0,['code'=>'QA-UBL-'.bin2hex(random_bytes(3)),'type_id'=>1,'active'=>1]);
 $r=$h->request(['room_id'=>$room,'arrival'=>$a,'departure'=>$d,'guests'=>1,'customer_name'=>'NOMBRE PRIVADO NO EXPORTABLE','email'=>'no-exportar@example.invalid','phone'=>'999111222']);$id=$r['id'];
 $reject(fn()=>$service->prepare($id,'01',$key(),1),'No se prepara un documento sin cuenta saldada');
 $ledger->open($id,1);$h->transition($id,'confirmed',1);$reject(fn()=>$service->prepare($id,'01',$key(),1),'Cuenta con saldo pendiente no genera prueba de contado');
 $ledger->record($id,'payment',$r['estimated_price'],'Pago SIMULADO para ensayo local','cash_demo',$key(),1);
 $reject(fn()=>$service->prepare($id,'07',$key(),1),'Tipo no implementado es rechazado');
 $reject(fn()=>$service->prepare($id,'01','bad',1),'Identificador de preparación inválido es rechazado');
 $k=$key();$doc=$service->prepare($id,'01',$k,1);$replay=$service->prepare($id,'01',$k,1);
 $check($replay['id']===$doc['id']&&$replay['number']===$doc['number'],'Reenvío de preparación reutiliza el documento y correlativo');
 $reject(fn()=>$service->prepare($id,'03',$k,1),'Clave de preparación no se reutiliza para otro tipo');
 $check((new \Greenter\XMLSecLibs\Sunat\SignedXml())->verifyXml($doc['xml']),'XML generado pasa la verificación criptográfica de firma');
 $check(!str_contains($doc['xml'],'NOMBRE PRIVADO')&&!str_contains($doc['xml'],'no-exportar')&&!str_contains($doc['xml'],'999111222'),'XML no contiene nombres, correos ni teléfonos del huésped');
 $check(!str_contains($doc['xml'],'PRIVATE KEY')&&str_contains($doc['xml'],'EMISOR ACADEMICO'),'XML incluye identidad genérica y no una clave privada');
 $dom=new DOMDocument();$dom->loadXML($doc['xml']);$xp=new DOMXPath($dom);$xp->registerNamespace('cbc','urn:oasis:names:specification:ubl:schema:xsd:CommonBasicComponents-2');$xp->registerNamespace('cac','urn:oasis:names:specification:ubl:schema:xsd:CommonAggregateComponents-2');
 $check($xp->evaluate('string(//cac:LegalMonetaryTotal/cbc:PayableAmount)')===number_format($r['estimated_price']/100,2,'.',''),'Total UBL coincide con los céntimos de la cuenta');
 $tamper=str_replace('PEN','USD',$doc['xml']);$valid=FALSE;try{$valid=(new \Greenter\XMLSecLibs\Sunat\SignedXml())->verifyXml($tamper);}catch(Throwable){}$check(!$valid,'Modificar el XML invalida su firma');
 $next=$service->prepare($id,'01',$key(),1);$boleta=$service->prepare($id,'03',$key(),1);
 $check($next['number']===$doc['number']+1&&$boleta['series']==='B901','Correlativos de factura avanzan sin mezclar series de boleta');
 $reject(fn()=>$gateway->build(['type'=>'01','series'=>'B901','number'=>1,'amount'=>8500,'created'=>time()]),'No se firma una combinación incompatible de tipo y serie');
 $check($gateway->endpoint()==='https://e-beta.sunat.gob.pe/ol-ti-itcpfegem-beta/billService','Destino fijo exclusivamente beta');
 $oldUser=getenv('ROMA_SUNAT_BETA_USER');putenv('ROMA_SUNAT_BETA_USER=USUARIO_REAL_NO_PERMITIDO');$check(!(new BetaGateway())->ready(),'No se admiten usuarios SOL reales o arbitrarios');putenv($oldUser===FALSE?'ROMA_SUNAT_BETA_USER':'ROMA_SUNAT_BETA_USER='.$oldUser);
 $gateway->probe=function()use($service,$doc,$reject){$reject(fn()=>$service->send($doc['id'],1),'Un documento en curso rechaza un segundo envío');};
 $out=$service->send($doc['id'],1);$gateway->probe=NULL;$check($out['state']==='accepted'&&$out['code']==='0'&&$out['cdr'],'Estado aceptado requiere CDR de respuesta simulada verificable');
 $before=$gateway->calls;$service->send($doc['id'],1);$check($before===$gateway->calls,'Documento aceptado no se reenvía');
 $reject(fn()=>$service->send($boleta['id'],1),'Control global impide envíos seguidos sin intervalo');
 $clock->now+=61;$gateway->code='2335';$out=$service->send($boleta['id'],1);$check($out['state']==='rejected'&&$out['code']==='2335','CDR de rechazo simulado conserva su código y estado');
 $before=$gateway->calls;$service->send($boleta['id'],1);$check($gateway->calls===$before,'Documento con rechazo definitivo no se reenvía');
 $clock->now+=61;$gateway->code='0';$gateway->behavior='timeout';$out=$service->send($next['id'],1);$check($out['state']==='uncertain'&&!$out['cdr'],'Timeout simulado no se presenta como aceptación');$first=$gateway->last;
 $clock->now+=61;$gateway->behavior='response';$out=$service->send($next['id'],1);$check($out['attempts']===2&&$first===$gateway->last,'Reintento manual conserva exactamente XML y número');
 $check(count($service->attempts($next['id']))===2,'Cada intento queda auditado sin credenciales SOAP');
 $review=$service->prepare($id,'01',$key(),1);$clock->now+=61;$gateway->behavior='review';$out=$service->send($review['id'],1);$check($out['state']==='uncertain'&&$out['cdr'],'Se conserva la respuesta incluso si requiere revisión');
 $before=$gateway->calls;$out=$service->review($review['id'],1);$check($out['state']==='accepted'&&$gateway->calls===$before&&count($service->reviews($review['id']))===1,'Revisión local valida CDR y audita sin un nuevo envío');
 $stale=$service->prepare($id,'01',$key(),1);$ledger->record($id,'charge',100,'Cargo posterior al XML de ensayo','',$key(),1);$ledger->record($id,'payment',100,'Saldo posterior al XML de ensayo','cash_demo',$key(),1);$clock->now+=61;
 $reject(fn()=>$service->send($stale['id'],1),'Cambio de cuenta exige un nuevo documento de prueba');
 $corrupt=$service->prepare($id,'01',$key(),1);$db->update('roma_beta_document')->fields(['xml'=>$corrupt['xml'].' '])->condition('id',$corrupt['id'])->execute();$reject(fn()=>$service->send($corrupt['id'],1),'Integridad SHA-256 evita enviar un XML alterado');
 $retry=$service->prepare($id,'01',$key(),1);$gateway->behavior='timeout';
 for($i=0;$i<3;$i++){$clock->now+=61;$service->send($retry['id'],1);}$reject(fn()=>$service->send($retry['id'],1),'Documento con tres intentos no vuelve a enviarse');
 $recover=$service->prepare($id,'01',$key(),1);$db->update('roma_beta_document')->fields(['state'=>'sending','updated'=>$clock->now])->condition('id',$recover['id'])->execute();$reject(fn()=>$service->recover($recover['id'],1),'No se recupera prematuramente un envío');$clock->now+=301;$out=$service->recover($recover['id'],1);$check($out['state']==='uncertain','Recuperar un proceso detenido mantiene resultado incierto');
 $late=$service->prepare($id,'01',$key(),1);$clock->now+=61;$gateway->behavior='response';
 $gateway->probe=function()use($clock,$service,$late){$clock->now+=301;$service->recover($late['id'],1);};
 $out=$service->send($late['id'],1);$gateway->probe=NULL;$lateAttempts=$service->attempts($late['id']);
 $check($out['state']==='uncertain'&&$lateAttempts[0]['state']==='late_response','Respuesta tardía no sobrescribe un intento recuperado; se conserva en auditoría');
 $unconfigured=$service->prepare($id,'01',$key(),1);$gateway->configured=FALSE;
 $reject(fn()=>$service->send($unconfigured['id'],1),'Configuración incompleta impide enviar');
 $check($service->document($unconfigured['id'])['attempts']===0,'No se consume un intento si faltan credenciales beta');$gateway->configured=TRUE;
 $originalPath=getenv('ROMA_SUNAT_PRIVATE_PATH');$tempPath=sys_get_temp_dir().'/roma-beta-pki-'.bin2hex(random_bytes(6));mkdir($tempPath,0700);
 if(function_exists('posix_geteuid')&&posix_geteuid()===0&&($www=posix_getpwnam('www-data')))chown($tempPath,$www['uid']);
 putenv('ROMA_SUNAT_PRIVATE_PATH='.$tempPath);
 try{$signed=$gateway->build(['type'=>'01','series'=>'F901','number'=>1,'amount'=>11800,'created'=>time()]);$keyFile=$tempPath.'/academic-beta.pem';$check((fileperms($keyFile)&0777)===0600&&fileowner($keyFile)===fileowner($tempPath),'Clave nueva se conserva con permisos 600 y propietario de la carpeta privada');chmod($tempPath,0777);$reject(fn()=>$gateway->build(['type'=>'01','series'=>'F901','number'=>1,'amount'=>11800,'created'=>time()]),'No se usa una carpeta de firma accesible a otros usuarios');}
 finally{if(is_file($tempPath.'/academic-beta.pem'))unlink($tempPath.'/academic-beta.pem');rmdir($tempPath);putenv($originalPath===FALSE?'ROMA_SUNAT_PRIVATE_PATH':'ROMA_SUNAT_PRIVATE_PATH='.$originalPath);}
 $good=roma_test_cdr($doc['filename']);BetaGateway::validateZip($good);$check($gateway->parse($doc['filename'],$good)['state']==='accepted','Se admite la carpeta vacía dummy del CDR beta real');
 $check($gateway->parse($next['filename'],$good)['state']==='uncertain','CDR de otro número no permite aceptar el documento');
 $reject(fn()=>BetaGateway::validateZip('not-a-zip'),'Respuesta que no es ZIP se rechaza');
 $reject(fn()=>BetaGateway::validateZip(roma_test_zip(['../evil.xml'=>'<a/>'])),'ZIP no admite rutas fuera del archivo');
 $reject(fn()=>BetaGateway::validateZip(roma_test_zip(['folder/evil.xml'=>'<a/>'])),'ZIP no admite XML en rutas inesperadas');
 $reject(fn()=>BetaGateway::validateZip(roma_test_zip(['r.xml'=>'<!DOCTYPE x [<!ENTITY e SYSTEM "file:///etc/passwd">]><x>&e;</x>'])),'CDR con entidades externas o DOCTYPE es rechazado');
 $reject(fn()=>BetaGateway::validateZip(roma_test_zip(['a.xml'=>'<a/>','b.xml'=>'<b/>'])),'No se interpreta ambiguamente un ZIP con varios XML');
 $reject(fn()=>BetaGateway::validateZip(roma_test_zip(['a.xml'=>str_repeat('x',2097153)])),'Se limita el tamaño descomprimido antes de leerlo');
 echo 'Total: '.count($checks)." comprobaciones locales, sin conexiones a SUNAT. Datos revertidos.\n";
}finally{$tx->rollBack();}
