<?php
namespace Drupal\roma_finance\Form;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ReverseForm extends ConfirmFormBase {
  private array $entry;
  public function getFormId(): string{return 'roma_finance_reverse';}
  public function buildForm(array $form,FormStateInterface $form_state,string $entry='0'): array {
    try{$this->entry=\Drupal::service('roma_finance.ledger')->entry((int)$entry);}catch(\DomainException){throw new NotFoundHttpException();}
    $form=parent::buildForm($form,$form_state);$form['#cache']['max-age']=0;
    $form['note']=['#type'=>'textarea','#title'=>'Motivo de la anulación','#maxlength'=>500,'#required'=>TRUE,'#weight'=>0];
    if(!$form_state->get('operation_key'))$form_state->set('operation_key',bin2hex(random_bytes(24)));
    $form['operation_key']=['#type'=>'hidden','#value'=>$form_state->get('operation_key')];return $form;
  }
  public function getQuestion(){return $this->t('¿Anular el movimiento @ref mediante una contrapartida?',['@ref'=>$this->entry['reference']]);}
  public function getDescription(){return $this->t('El registro original se conserva y se añade su efecto inverso. Esta operación no realiza una devolución bancaria ni anula un comprobante SUNAT.');}
  public function getConfirmText(){return $this->t('Registrar contrapartida de prueba');}
  public function getCancelUrl():Url{return Url::fromRoute('roma_finance.account',['reservation'=>$this->entry['reservation_id']]);}
  public function validateForm(array &$form,FormStateInterface $form_state):void{if(mb_strlen(trim((string)$form_state->getValue('note')))<5)$form_state->setErrorByName('note','Indica un motivo de al menos 5 caracteres.');}
  public function submitForm(array &$form,FormStateInterface $form_state):void{
    try{\Drupal::service('roma_finance.ledger')->reverse((int)$this->entry['id'],(string)$form_state->getValue('note'),(string)$form_state->getValue('operation_key'),(int)$this->currentUser()->id());$this->messenger()->addStatus('Contrapartida registrada sin borrar el movimiento original.');$form_state->setRedirect('roma_finance.account',['reservation'=>$this->entry['reservation_id']]);}
    catch(\DomainException $e){$this->messenger()->addError($e->getMessage());$form_state->setRebuild();}
  }
}
