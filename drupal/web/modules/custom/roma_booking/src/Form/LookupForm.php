<?php
namespace Drupal\roma_booking\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\roma_booking\Service\HotelService;

final class LookupForm extends HotelFormBase {
  public function getFormId(): string { return 'roma_lookup_form'; }
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $this->protect($form);
    $form['intro'] = ['#markup' => '<p>Introduce el código y la clave privada que recibiste al enviar tu solicitud. No mostramos datos personales. No compartas tu clave.</p>'];
    $form['reference'] = ['#type' => 'textfield', '#title' => 'Código de solicitud', '#maxlength' => 32, '#required' => TRUE];
    $form['secret'] = ['#type' => 'password', '#title' => 'Clave privada de consulta', '#maxlength' => 100, '#required' => TRUE];
    $form['actions'] = ['#type' => 'actions', 'submit' => ['#type' => 'submit', '#value' => 'Consultar mi solicitud']];
    if ($row = $form_state->get('result')) {
      $form['result'] = ['#type' => 'container', '#attributes' => ['class' => ['hotel-receipt']], 'state' => ['#plain_text' => 'Estado: ' . HotelService::STATUS[$row['status']] . '. ' . $row['type'] . ' · ' . $row['room'] . '. Del ' . HotelService::date($row['arrival']) . ' al ' . HotelService::date($row['departure']) . '. Estimado: ' . HotelService::money((int) $row['estimated_price']) . '. Solicitud académica, no reserva real.']];
    }
    if ($form_state->get('result') && \Drupal::hasService('roma_payments.demo')) {
      $row = $form_state->get('result');
      $last = \Drupal::service('roma_payments.demo')->last($row['reference']);
      $labels = ['demo_approved'=>'Aprobado en simulación', 'demo_declined'=>'Rechazado en simulación', 'demo_cancelled'=>'Cancelado en simulación'];
      $form['payment'] = ['#type'=>'item', '#title'=>'Pago de demostración', '#plain_text'=>$last ? $labels[$last['outcome']] . '. Sin dinero real. Código: ' . $last['receipt'] : 'Sin simulaciones de pago.'];
      if ($row['status'] === 'confirmed') { $form['payment_link'] = \Drupal\Core\Link::fromTextAndUrl('Probar pago de demostración (sin dinero real)', \Drupal\Core\Url::fromRoute('roma_payments.demo'))->toRenderable(); }
    }
    return $form;
  }
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->guard($form, $form_state, 'roma.lookup', 20)) { return; }
    $row = $this->hotel->lookup((string) $form_state->getValue('reference'), (string) $form_state->getValue('secret'));
    if (!$row) { $form_state->setErrorByName('reference', 'Código o clave incorrectos.'); }
    else { $form_state->set('result', $row); }
  }
  public function submitForm(array &$form, FormStateInterface $form_state): void { $form_state->setRebuild(TRUE); }
}
