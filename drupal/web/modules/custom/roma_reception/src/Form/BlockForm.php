<?php
namespace Drupal\roma_reception\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;

final class BlockForm extends FormBase {
  public function getFormId(): string { return 'roma_reception_block'; }
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $hotel=\Drupal::service('roma_booking.hotel');$options=[];foreach($hotel->rooms(FALSE) as $room) { $options[$room['id']]=$room['code'].' · '.$room['type_name']; }
    $form['intro']=['#markup'=>'<p>El bloqueo impedirá solicitudes y confirmaciones en el intervalo seleccionado. No puede superponerse con reservas confirmadas ni mantenimientos existentes. La fecha final es exclusiva.</p>'];
    $form['room']=['#type'=>'select','#title'=>'Habitación','#options'=>$options,'#required'=>TRUE];
    foreach(['arrival'=>'Desde','departure'=>'Hasta (exclusivo)'] as $key=>$title) { $form[$key]=['#type'=>'date','#title'=>$title,'#required'=>TRUE,'#attributes'=>['min'=>$hotel->today()]]; }
    $form['reason']=['#type'=>'textfield','#title'=>'Motivo','#required'=>TRUE,'#maxlength'=>200,'#description'=>'Entre 5 y 200 caracteres. No incluyas datos personales.'];
    $form['actions']=['#type'=>'actions','save'=>['#type'=>'submit','#value'=>'Programar mantenimiento']];return $form;
  }
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    try { \Drupal::service('roma_booking.hotel')->dates((string)$form_state->getValue('arrival'),(string)$form_state->getValue('departure'),1); }
    catch(\DomainException $error) { $form_state->setErrorByName('arrival',$error->getMessage()); }
    if(mb_strlen(trim((string)$form_state->getValue('reason')))<5) { $form_state->setErrorByName('reason','Explica el motivo con al menos 5 caracteres.'); }
  }
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try { \Drupal::service('roma_reception.operations')->block((int)$form_state->getValue('room'),(string)$form_state->getValue('arrival'),(string)$form_state->getValue('departure'),(string)$form_state->getValue('reason'),(int)$this->currentUser()->id());$this->messenger()->addStatus('Mantenimiento programado.');$form_state->setRedirect('roma_reception.calendar'); }
    catch(\DomainException $error) { $this->messenger()->addError($error->getMessage());$form_state->setRebuild(); }
  }
}
