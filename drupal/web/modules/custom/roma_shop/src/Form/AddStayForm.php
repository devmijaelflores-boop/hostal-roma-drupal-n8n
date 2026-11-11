<?php
namespace Drupal\roma_shop\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\roma_booking\Form\HotelFormBase;
use Drupal\roma_booking\Service\HotelService;

final class AddStayForm extends HotelFormBase {
  public function getFormId(): string { return 'roma_add_stay_form'; }
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $this->protect($form);
    $q = $this->getRequest()->query;
    $options = [];
    foreach ($this->hotel->rooms() as $room) { $options[$room['id']] = $room['type_name'] . ' · ' . $room['code'] . ' · capacidad ' . $room['capacity'] . ' · ' . HotelService::money((int) $room['amount']) . '/noche'; }
    $form['intro'] = ['#markup'=>'<p>Elige las fechas y los huéspedes para una habitación. Puedes agregar varias estadías al carrito. Las tarifas son ficticias y el carrito todavía no registra ni confirma una reserva.</p>'];
    $form['room_id'] = ['#type'=>'select','#title'=>'Habitación','#options'=>$options,'#empty_option'=>'Selecciona una habitación','#required'=>TRUE,'#default_value'=>$q->get('room','')];
    foreach (['arrival'=>'Llegada','departure'=>'Salida'] as $key=>$title) { $form[$key] = ['#type'=>'date','#title'=>$title,'#required'=>TRUE,'#default_value'=>$q->get($key,''),'#attributes'=>['min'=>$this->hotel->today()]]; }
    $form['guests'] = ['#type'=>'number','#title'=>'Huéspedes en esta habitación','#min'=>1,'#max'=>10,'#step'=>1,'#required'=>TRUE,'#default_value'=>$q->get('guests',1)];
    $form['actions'] = ['#type'=>'actions','submit'=>['#type'=>'submit','#value'=>'Agregar al carrito']];
    return $form;
  }
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->guard($form,$form_state,'roma.shop.add',30)) { return; }
    try { $this->hotel->dates((string) $form_state->getValue('arrival'),(string) $form_state->getValue('departure'),(int) $form_state->getValue('guests')); }
    catch (\DomainException $error) { $form_state->setErrorByName('departure',$error->getMessage()); return; }
    try {
      $room = $this->hotel->room((int) $form_state->getValue('room_id'));
      if ((int) $form_state->getValue('guests') > (int) $room['capacity']) { $form_state->setErrorByName('guests','La cantidad de huéspedes supera la capacidad de la habitación.'); }
    }
    catch (\DomainException $error) { $form_state->setErrorByName('room_id',$error->getMessage()); }
  }
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try {
      \Drupal::service('roma_shop.store')->add((int) $form_state->getValue('room_id'),$form_state->getValue('arrival'),$form_state->getValue('departure'),(int) $form_state->getValue('guests'));
      $this->messenger()->addStatus('Estadía agregada. Revisa tu carrito antes de continuar.');
      $form_state->setRedirect('roma_shop.cart');
    }
    catch (\DomainException $error) { $this->messenger()->addError($error->getMessage()); $form_state->setRebuild(TRUE); }
  }
}
