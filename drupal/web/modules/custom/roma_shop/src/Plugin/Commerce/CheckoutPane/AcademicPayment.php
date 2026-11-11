<?php
namespace Drupal\roma_shop\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentGateway;

#[CommerceCheckoutPane(id: 'roma_payment', label: new TranslatableMarkup('Pago académico local'), default_step: 'payment', wrapper_element: 'fieldset')]
final class AcademicPayment extends CheckoutPaneBase {
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form['notice'] = ['#markup'=>'<p>Pasarela de prueba local. Elige un resultado para demostrar el proceso. No se solicitan tarjetas, cuentas bancarias ni se realiza un cobro.</p>'];
    $pane_form['outcome'] = ['#type'=>'radios','#title'=>'Resultado del pago de prueba','#options'=>['approved'=>'Aprobar pago de prueba','declined'=>'Rechazar pago de prueba','cancelled'=>'Cancelar operación de prueba'],'#required'=>TRUE];
    $pane_form['back'] = Link::fromTextAndUrl('Volver al carrito',Url::fromRoute('roma_shop.cart'))->toRenderable();
    return $pane_form;
  }
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    if (in_array($values['outcome'] ?? '',['declined','cancelled'],TRUE) && !$form_state->hasAnyErrors()) {
      \Drupal::service('roma_shop.store')->attempt($this->order,$values['outcome']);
      $form_state->setError($pane_form['outcome'],$values['outcome'] === 'declined' ? 'Pago de prueba rechazado. No se registraron reservas ni cobros. Puedes elegir aprobar para continuar o volver al carrito.' : 'Operación de prueba cancelada. Tu carrito se conserva y no se registraron reservas ni cobros.');
    }
  }
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    try {
      $gateway = PaymentGateway::load('roma_academic');
      $this->order->set('payment_gateway',$gateway);
      $payment = Payment::create(['state'=>'new','amount'=>$this->order->getTotalPrice(),'payment_gateway'=>$gateway,'order_id'=>$this->order->id()]);
      $gateway->getPlugin()->createPayment($payment);
    }
    catch (\DomainException $error) {
      $this->messenger()->addError($error->getMessage());
      $this->checkoutFlow->redirectToStep('review');
    }
  }
  public function buildPaneSummary() { return ['#plain_text'=>'Pasarela académica local. Sin dinero real.']; }
}
