<?php
namespace Drupal\roma_payments\Form;
use Drupal\roma_booking\Form\HotelFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\roma_booking\Service\HotelService;

final class DemoPaymentForm extends HotelFormBase {
  public function getFormId(): string { return 'roma_demo_payment_form'; }
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $this->protect($form);
    if ($row = $form_state->get('receipt')) {
      $labels=['demo_approved'=>'Aprobado en simulación','demo_declined'=>'Rechazado en simulación','demo_cancelled'=>'Cancelado en simulación'];
      $form['receipt']=['#type'=>'container','#attributes'=>['class'=>['hotel-receipt']], 'heading'=>['#markup'=>'<h2>Resultado de pago de demostración</h2>'], 'detail'=>['#plain_text'=>$labels[$row['outcome']] . '. Código: ' . $row['receipt'] . '. Importe ficticio: ' . HotelService::money((int)$row['amount']) . '. No se ha realizado ningún cobro. Este registro no es un comprobante de pago, boleta ni factura.']];
      return $form;
    }
    $form['intro']=['#markup'=>'<p><strong>Simulador académico sin dinero real.</strong> No solicita tarjeta, datos bancarios, Yape ni Plin. Permite probar resultados aprobados, rechazados y cancelados sobre reservas confirmadas de demostración. Una pasarela externa requerirá credenciales de prueba y una integración adicional.</p>'];
    $form['reference']=['#type'=>'textfield','#title'=>'Código de solicitud','#maxlength'=>32,'#required'=>TRUE];
    $form['secret']=['#type'=>'password','#title'=>'Clave privada de consulta','#maxlength'=>100,'#required'=>TRUE];
    $form['outcome']=['#type'=>'select','#title'=>'Resultado que deseas simular','#options'=>['demo_approved'=>'Aprobar pago ficticio','demo_declined'=>'Rechazar pago ficticio','demo_cancelled'=>'Cancelar pago ficticio'],'#required'=>TRUE];
    $form['demo_consent']=['#type'=>'checkbox','#title'=>'Entiendo que no estoy pagando dinero y que no recibiré un comprobante comercial.','#required'=>TRUE];
    $form_state->set('request_key',$form_state->get('request_key') ?: bin2hex(random_bytes(24)));
    $form['actions']=['#type'=>'actions','submit'=>['#type'=>'submit','#value'=>'Ejecutar simulación de pago']];
    return $form;
  }
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->guard($form,$form_state,'roma.payment',10)) { return; }
    $row=$this->hotel->lookup((string)$form_state->getValue('reference'),(string)$form_state->getValue('secret'));
    if (!$row) { $form_state->setErrorByName('reference','Código o clave incorrectos.'); }
    elseif ($row['status']!=='confirmed') { $form_state->setErrorByName('reference','La solicitud debe estar confirmada por un administrador para simular su pago.'); }
  }
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try { $form_state->set('receipt',\Drupal::service('roma_payments.demo')->record((string)$form_state->getValue('reference'),(string)$form_state->getValue('secret'),(string)$form_state->getValue('outcome'),$form_state->get('request_key'))); }
    catch (\DomainException $error) { $this->messenger()->addError($error->getMessage()); }
    $form_state->setRebuild(TRUE);
  }
}
