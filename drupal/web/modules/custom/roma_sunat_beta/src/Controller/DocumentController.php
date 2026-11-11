<?php
namespace Drupal\roma_sunat_beta\Controller;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\roma_booking\Service\HotelService;
use Drupal\roma_sunat_beta\Service\DocumentService;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class DocumentController extends ControllerBase {
  private function cell(string $value):array{return ['data'=>['#plain_text'=>$value]];}
  private function doc(int $id):array{try{return \Drupal::service('roma_sunat_beta.documents')->document($id);}catch(\DomainException){throw new NotFoundHttpException();}}
  private function notice():array{return ['#markup'=>'<p class="finance-notice"><strong>SUNAT BETA · PRUEBAS SIN VALIDEZ TRIBUTARIA.</strong> XML firmado con certificado académico e identidades genéricas. No representa comprobantes del establecimiento. No se envían nombres, correos, teléfonos ni documentos personales de huéspedes. El servicio beta valida estructuras y no garantiza la consistencia tributaria de datos.</p>'];}
  public function overview():array{
    $rows=[];foreach(\Drupal::service('roma_sunat_beta.documents')->listing() as $d){$rows[]=[$this->cell($d['series'].'-'.$d['number']),$this->cell(DocumentService::TYPES[$d['type']]),$this->cell(HotelService::money($d['amount'])),$this->cell(DocumentService::STATES[$d['state']]),$this->cell($d['code']?:'—'),['data'=>Link::fromTextAndUrl('Ver prueba',Url::fromRoute('roma_sunat_beta.document',['document'=>$d['id']]))->toRenderable()]];}
    return ['#type'=>'container','#attributes'=>['class'=>['beta-panel']],'#attached'=>['library'=>['roma_sunat_beta/beta']],'#cache'=>['max-age'=>0],'notice'=>$this->notice(),'info'=>['#markup'=>'<p>Prepara una prueba desde una cuenta de prueba confirmada y saldada. El envío requiere una segunda confirmación. Se admite un envío por minuto para toda la instalación y hasta tres intentos por documento; no hay envíos automáticos.</p>'],'accounts'=>Link::fromTextAndUrl('Abrir cuentas y saldos',Url::fromRoute('roma_finance.overview'))->toRenderable(),'table'=>['#type'=>'table','#caption'=>'Últimos 100 documentos académicos','#header'=>['Número beta','Tipo','Total PEN','Estado','Código de respuesta','Acción'],'#rows'=>$rows,'#empty'=>'Todavía no hay documentos beta.']];
  }
  public function view(string $document):array{
    $d=$this->doc((int)$document);$rows=[];
    $date=(new \DateTimeImmutable('@'.$d['created']))->setTimezone(new \DateTimeZone('America/Lima'))->format('d/m/Y H:i:s');
    foreach(['Número beta'=>$d['series'].'-'.$d['number'],'Documento de prueba'=>DocumentService::TYPES[$d['type']],'Preparación (hora de Perú)'=>$date,'Estado'=>DocumentService::STATES[$d['state']],'Total de prueba'=>HotelService::money($d['amount']),'Respuesta'=>$d['code'].' · '.$d['description'],'SHA-256 XML'=>$d['xml_hash'],'SHA-256 CDR'=>$d['cdr_hash']?:'Aún no disponible','Responsable de preparación'=>'Usuario #'.$d['actor_uid']] as $label=>$value)$rows[]=[$this->cell($label),$this->cell($value)];
    $base=intdiv($d['amount']*100+59,118);$tax=$d['amount']-$base;
    $build=['#type'=>'container','#attributes'=>['class'=>['beta-panel']],'#cache'=>['max-age'=>0],'#attached'=>['library'=>['roma_sunat_beta/beta']],'notice'=>$this->notice(),'table'=>['#type'=>'table','#header'=>['Dato','Valor'],'#rows'=>$rows]];
    $build['scenario']=['#type'=>'table','#caption'=>'Representación académica: escenario gravado al 18 %, no régimen del hotel','#header'=>['Concepto del ejercicio','Valor'],'#rows'=>[
      [$this->cell('Emisor genérico de pruebas'),$this->cell('EMISOR ACADEMICO DE PRUEBAS · RUC 20000000001')],[$this->cell('Destinatario genérico de pruebas'),$this->cell($d['type']==='01'?'CLIENTE FICTICIO · RUC 20000000001':'CLIENTE FICTICIO · DNI 00000000')],[$this->cell('Base del ejercicio'),$this->cell(HotelService::money($base))],[$this->cell('IGV del ejercicio, 18 %'),$this->cell(HotelService::money($tax))],[$this->cell('Total PEN'),$this->cell(HotelService::money($d['amount']))],
    ]];
    $build['print']=['#type'=>'html_tag','#tag'=>'button','#value'=>'Imprimir representación de prueba','#attributes'=>['type'=>'button','class'=>['finance-print']]];
    $links=[Link::fromTextAndUrl('Descargar XML firmado de prueba',Url::fromRoute('roma_sunat_beta.download',['document'=>$d['id'],'kind'=>'xml']))->toRenderable()];
    if($d['cdr'])$links[]=Link::fromTextAndUrl('Descargar CDR recibido',Url::fromRoute('roma_sunat_beta.download',['document'=>$d['id'],'kind'=>'cdr']))->toRenderable();
    if($d['cdr']&&in_array($d['state'],['uncertain','error'],TRUE))$links[]=Link::fromTextAndUrl('Revisar el CDR guardado sin reenviar',Url::fromRoute('roma_sunat_beta.action',['document'=>$d['id'],'action'=>'review']))->toRenderable();
    if(in_array($d['state'],['prepared','error','uncertain'],TRUE)&&$d['attempts']<3)$links[]=Link::fromTextAndUrl('Revisar y enviar a SUNAT beta',Url::fromRoute('roma_sunat_beta.action',['document'=>$d['id'],'action'=>'send']))->toRenderable();
    if($d['state']==='sending')$links[]=Link::fromTextAndUrl('Revisar intento detenido (mínimo 5 minutos)',Url::fromRoute('roma_sunat_beta.action',['document'=>$d['id'],'action'=>'recover']))->toRenderable();
    $links[]=Link::fromTextAndUrl('Volver a documentos beta',Url::fromRoute('roma_sunat_beta.overview'))->toRenderable();$build['actions']=['#theme'=>'item_list','#items'=>$links];
    if(!\Drupal::service('roma_sunat_beta.gateway')->ready())$build['configuration']=['#markup'=>'<p>El XML puede prepararse localmente. Para enviarlo, el administrador debe configurar SOAP y las credenciales genéricas beta en el entorno del servidor. No introduzcas una clave SOL real.</p>'];
    $rows=[];foreach(\Drupal::service('roma_sunat_beta.documents')->attempts($d['id']) as $attempt){$links=[];if($attempt['finished'])$links[]=Link::fromTextAndUrl('CDR del intento, si existe',Url::fromRoute('roma_sunat_beta.attempt_cdr',['document'=>$d['id'],'attempt'=>$attempt['number']]))->toRenderable();$date=(new \DateTimeImmutable('@'.$attempt['started']))->setTimezone(new \DateTimeZone('America/Lima'))->format('d/m/Y H:i:s');$rows[]=[$this->cell((string)$attempt['number']),$this->cell($date),$this->cell('Usuario #'.$attempt['actor_uid']),$this->cell(DocumentService::STATES[$attempt['state']]??$attempt['state']),$this->cell($attempt['code'].' · '.$attempt['description']),['data'=>['#theme'=>'item_list','#items'=>$links]]];}
    $build['attempts']=['#type'=>'table','#caption'=>'Historial de intentos; una respuesta incierta no equivale a aceptación','#header'=>['Intento','Inicio Perú','Responsable','Estado','Respuesta','Archivo'],'#rows'=>$rows,'#empty'=>'No se ha enviado este documento.'];
    $rows=[];foreach(\Drupal::service('roma_sunat_beta.documents')->reviews($d['id']) as $r)$rows[]=[$this->cell('Usuario #'.$r['actor_uid']),$this->cell((DocumentService::STATES[$r['from_state']]??$r['from_state']).' → '.(DocumentService::STATES[$r['to_state']]??$r['to_state'])),$this->cell($r['from_code'].' → '.$r['to_code']),$this->cell($r['cdr_hash'])];
    $build['reviews']=['#type'=>'table','#caption'=>'Revisiones locales de la misma evidencia, sin nuevos envíos','#header'=>['Responsable','Estado','Código','SHA-256 CDR'],'#rows'=>$rows,'#empty'=>'Sin revisiones locales.'];return $build;
  }
  private function attachment(string $content,string $name,string $type):Response{return new Response($content,200,['Content-Type'=>$type,'Content-Disposition'=>'attachment; filename="'.$name.'"','Cache-Control'=>'private, no-store','X-Content-Type-Options'=>'nosniff']);}
  public function download(string $document,string $kind):Response{
    $d=$this->doc((int)$document);$data=$kind==='xml'?$d['xml']:$d['cdr'];$hash=$kind==='xml'?$d['xml_hash']:$d['cdr_hash'];
    if(!$data)throw new NotFoundHttpException('Aún no hay un archivo para descargar.');if(!hash_equals($hash,hash('sha256',$data)))throw new ConflictHttpException('La integridad del archivo no coincide.');
    return $this->attachment($data,($kind==='cdr'?'R-':'').$d['filename'].($kind==='cdr'?'.zip':'.xml'),$kind==='cdr'?'application/zip':'application/xml; charset=UTF-8');
  }
  public function attemptCdr(string $document,string $attempt):Response{
    $d=$this->doc((int)$document);$cdr=\Drupal::database()->select('roma_beta_attempt','a')->fields('a',['cdr'])->condition('document_id',$d['id'])->condition('number',(int)$attempt)->execute()->fetchField();
    if(is_resource($cdr))$cdr=stream_get_contents($cdr);if(!$cdr)throw new NotFoundHttpException('Este intento no devolvió un CDR.');
    return $this->attachment($cdr,'intento-'.(int)$attempt.'-R-'.$d['filename'].'.zip','application/zip');
  }
}
