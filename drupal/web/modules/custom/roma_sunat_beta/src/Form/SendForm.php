<?php
namespace Drupal\roma_sunat_beta\Form;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\roma_sunat_beta\Service\DocumentService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SendForm extends ConfirmFormBase {
  private array $document;private string $action;
  public function getFormId():string{return 'roma_beta_send';}
  public function buildForm(array $form,FormStateInterface $form_state,string $document='0',string $action='send'):array{
    try{$this->document=\Drupal::service('roma_sunat_beta.documents')->document((int)$document);}catch(\DomainException){throw new NotFoundHttpException();}
    $this->action=$action;$form=parent::buildForm($form,$form_state);$form['#cache']['max-age']=0;
    $form['consent']=['#type'=>'checkbox','#title'=>'Confirmo que el documento contiene únicamente datos genéricos de prueba y que no tiene validez tributaria.','#required'=>TRUE];return $form;
  }
  public function getQuestion(){return $this->t('@action @number en modo beta?',['@action'=>match($this->action){'send'=>'¿Enviar','review'=>'¿Revisar el CDR conservado de',default=>'¿Revisar el envío detenido de'},'@number'=>$this->document['series'].'-'.$this->document['number']]);}
  public function getDescription(){return $this->t('El destino es exclusivamente el servicio beta de SUNAT. No se permite producción, envío masivo ni reintento automático. Una respuesta aceptada en beta solo corresponde a pruebas de estructura, no acredita una venta ni validez tributaria. Si hubo una interrupción, revisa el estado antes de volver a enviar.');}
  public function getConfirmText(){return $this->t(match($this->action){'send'=>'Enviar solo a SUNAT beta','review'=>'Revisar CDR sin volver a enviar',default=>'Recuperar estado como incierto'});}
  public function getCancelUrl():Url{return Url::fromRoute('roma_sunat_beta.document',['document'=>$this->document['id']]);}
  public function submitForm(array &$form,FormStateInterface $form_state):void{
    $service=\Drupal::service('roma_sunat_beta.documents');
    try{$d=match($this->action){'send'=>$service->send($this->document['id'],(int)$this->currentUser()->id()),'review'=>$service->review($this->document['id'],(int)$this->currentUser()->id()),default=>$service->recover($this->document['id'],(int)$this->currentUser()->id())};$this->messenger()->addStatus(DocumentService::STATES[$d['state']].'. '.$d['description']);}
    catch(\DomainException $e){$this->messenger()->addError($e->getMessage());}
    $form_state->setRedirect('roma_sunat_beta.document',['document'=>$this->document['id']]);
  }
}
