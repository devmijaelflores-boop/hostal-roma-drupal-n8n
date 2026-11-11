<?php
namespace Drupal\roma_reception\Form;
use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\roma_reception\Service\ReceptionService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ActionForm extends ConfirmFormBase {
  private string $kind;private int $id;private string $action;
  public function getFormId(): string { return 'roma_reception_action'; }
  public function buildForm(array $form, FormStateInterface $form_state, string $kind='stay', string $id='0', string $action='check_in'): array {
    $this->kind=$kind;$this->id=(int)$id;$this->action=$action;
    try {
      if($kind==='stay') { \Drupal::service('roma_booking.hotel')->reservation((int)$id); }
      elseif($kind==='room') { \Drupal::service('roma_booking.hotel')->room((int)$id); }
      elseif(!\Drupal::database()->select('roma_room_block','b')->condition('id',(int)$id)->countQuery()->execute()->fetchField()) { throw new \DomainException(); }
    } catch(\DomainException) { throw new NotFoundHttpException(); }
    $form=parent::buildForm($form,$form_state);$form['#cache']['max-age']=0;return $form;
  }
  public function getQuestion() { return $this->t('¿@action en el registro #@id?', ['@action'=>ReceptionService::ACTIONS[$this->action],'@id'=>$this->id]); }
  public function getDescription() { return $this->t('Se comprobará la operación y quedará registrada con tu usuario. Una salida deja la habitación pendiente de limpieza. Esta acción no cobra dinero ni emite comprobantes tributarios.'); }
  public function getConfirmText() { return $this->t('Confirmar operación'); }
  public function getCancelUrl(): Url { return Url::fromRoute('roma_reception.calendar'); }
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try { \Drupal::service('roma_reception.operations')->action($this->kind,$this->id,$this->action,(int)$this->currentUser()->id());$this->messenger()->addStatus('Operación registrada en recepción.'); }
    catch(\DomainException $error) { $this->messenger()->addError($error->getMessage()); }
    $form_state->setRedirect('roma_reception.calendar');
  }
}
