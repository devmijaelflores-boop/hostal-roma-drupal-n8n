<?php
namespace Drupal\roma_shop\Plugin\Commerce\CheckoutFlow;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutFlow;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutFlow\CheckoutFlowWithPanesBase;
use Symfony\Component\HttpKernel\Exception\HttpException;

#[CommerceCheckoutFlow(id: 'roma_stay', label: new TranslatableMarkup('Checkout de estadías académicas'))]
final class StayCheckout extends CheckoutFlowWithPanesBase {
  public function getSteps() {
    return [
      'order_information'=>['label'=>'Datos de prueba','next_label'=>'Continuar','previous_label'=>'Volver','has_sidebar'=>TRUE],
      'review'=>['label'=>'Revisar pedido','next_label'=>'Revisar pedido','previous_label'=>'Volver a la revisión','has_sidebar'=>TRUE],
      'payment'=>['label'=>'Pago de demostración','next_label'=>'Elegir pago de prueba','previous_label'=>'Volver al pago','has_sidebar'=>TRUE],
      'complete'=>['label'=>'Pedido registrado','next_label'=>'Registrar pedido de prueba','has_sidebar'=>FALSE],
    ];
  }
  public function buildForm(array $form, FormStateInterface $form_state, $step_id = NULL) {
    $session = \Drupal::request()->getSession(); $session->start(); $session->set('roma_public_forms',TRUE);
    $form = parent::buildForm($form,$form_state,$step_id);
    $form['#cache']['max-age'] = 0;
    $form['#attached']['library'][] = 'roma_booking/hotel';
    $form['#attached']['library'][] = 'roma_shop/shop';
    $form['#attributes']['data-hotel-feedback'] = 'true';
    $form['intro'] = ['#weight'=>-100,'#markup'=>'<p class="shop-checkout-notice">Compra académica de demostración en PEN. Las solicitudes quedarán pendientes de confirmación y no se cobrará dinero real.</p>'];
    if ($step_id !== 'complete') {
      $form['_roma_csrf'] = ['#type'=>'hidden','#default_value'=>\Drupal::csrfToken()->get('roma_checkout:' . $this->order->uuid() . ':' . $step_id)];
      $form['website'] = ['#type'=>'textfield','#title'=>'Sitio web (dejar vacío)','#attributes'=>['autocomplete'=>'off','tabindex'=>'-1'],'#wrapper_attributes'=>['class'=>['visually-hidden']]];
    }
    return $form;
  }
  public function validateForm(array &$form, FormStateInterface $form_state) {
    $key = 'roma_checkout:' . $this->order->uuid() . ':' . $form['#step_id'];
    if (!\Drupal::csrfToken()->validate((string) $form_state->getValue('_roma_csrf'),$key) || $form_state->getValue('website')) {
      $form_state->setErrorByName('_roma_csrf','La sesión del checkout no es válida. Recarga la página e inténtalo de nuevo.'); return;
    }
    $flood = \Drupal::service('flood');
    $id = hash('sha256',(\Drupal::request()->getClientIp() ?? '') . \Drupal::service('private_key')->get());
    if (!$flood->isAllowed('roma.shop.checkout',30,900,$id)) { $form_state->setErrorByName('_roma_csrf','Se alcanzó el límite de intentos del checkout. Inténtalo en 15 minutos.'); return; }
    $flood->register('roma.shop.checkout',900,$id);
    parent::validateForm($form,$form_state);
    if ($this->order->getState()->getId() === 'draft' && !$form_state->hasAnyErrors()) {
      try { \Drupal::service('roma_shop.store')->validate($this->order); }
      catch (\DomainException $error) { $form_state->setErrorByName('_roma_csrf',$error->getMessage()); }
    }
  }
  protected function onStepChange($step_id) {
    if ($step_id === 'complete' && $this->order->getState()->getId() === 'draft' && !$this->order->getData('roma_checkout_done')) {
      throw new HttpException(409,'Completa el pago de demostración antes de registrar el pedido.');
    }
    parent::onStepChange($step_id);
  }
}
