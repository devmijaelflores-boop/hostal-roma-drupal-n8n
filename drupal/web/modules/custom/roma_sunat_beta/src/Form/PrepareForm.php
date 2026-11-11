<?php
namespace Drupal\roma_sunat_beta\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\roma_booking\Service\HotelService;
use Drupal\roma_sunat_beta\Service\DocumentService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class PrepareForm extends FormBase {
  private int $reservation;
  public function getFormId(): string{return 'roma_beta_prepare';}
  public function buildForm(array $form,FormStateInterface $form_state,string $reservation='0'):array{
    $this->reservation=(int)$reservation;
    try{$a=\Drupal::service('roma_finance.ledger')->account($this->reservation);}catch(\DomainException){throw new NotFoundHttpException();}
    $form['#cache']['max-age']=0;
    $form['notice']=['#markup'=>'<p><strong>EXCLUSIVAMENTE BETA · SIN VALIDEZ TRIBUTARIA.</strong> Este ejercicio usa identidades genéricas y un certificado académico autofirmado. No representa una operación del hotel ni un certificado fiscal autorizado. El escenario gravado al 18 % no determina el régimen tributario del establecimiento.</p><p>Preparar firma y conserva el XML; no lo envía. Cada preparación nueva asigna otro correlativo beta. La cuenta debe estar confirmada, saldada en modo de demostración y tener cargos positivos.</p>'];
    $form['reference']=['#type'=>'item','#title'=>'Solicitud de origen (solo uso interno)','#plain_text'=>$a['booking']['reference']];
    $form['amount']=['#type'=>'item','#title'=>'Cargos netos de la cuenta de demostración','#plain_text'=>HotelService::money($a['charges'])];
    $form['type']=['#type'=>'select','#title'=>'Documento de prueba','#options'=>DocumentService::TYPES,'#required'=>TRUE];
    $form['consent']=['#type'=>'checkbox','#title'=>'Entiendo que esta es una prueba académica y que no genera un comprobante tributario real.','#required'=>TRUE];
    if(!$form_state->get('operation_key'))$form_state->set('operation_key',bin2hex(random_bytes(24)));
    $form['key']=['#type'=>'hidden','#value'=>$form_state->get('operation_key')];
    $form['actions']=['#type'=>'actions','submit'=>['#type'=>'submit','#value'=>'Preparar y firmar XML de prueba']];return $form;
  }
  public function submitForm(array &$form,FormStateInterface $form_state):void{
    try{
      $d=\Drupal::service('roma_sunat_beta.documents')->prepare($this->reservation,(string)$form_state->getValue('type'),(string)$form_state->getValue('key'),(int)$this->currentUser()->id());
      $this->messenger()->addStatus('XML académico firmado y guardado. Todavía no se envió a SUNAT beta.');$form_state->setRedirect('roma_sunat_beta.document',['document'=>$d['id']]);
    }catch(\DomainException $e){$this->messenger()->addError($e->getMessage());$form_state->setRebuild();}
    catch(\Throwable $e){$this->getLogger('roma_sunat_beta')->error('No se pudo preparar la prueba: @type',['@type'=>$e::class]);$this->messenger()->addError('No se pudo preparar la firma. Revisa permisos de la carpeta privada y extensiones PHP; no se guardó un documento incompleto.');$form_state->setRebuild();}
  }
}
