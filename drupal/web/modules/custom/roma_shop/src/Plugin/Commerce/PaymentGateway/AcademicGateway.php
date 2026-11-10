<?php
namespace Drupal\roma_shop\Plugin\Commerce\PaymentGateway;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_payment\Attribute\CommercePaymentGateway;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_payment\Plugin\Commerce\PaymentGateway\Manual;

#[CommercePaymentGateway(
  id: 'roma_academic',
  label: new TranslatableMarkup('Pasarela académica local · sin cobros'),
  display_label: new TranslatableMarkup('Pago de prueba local'),
  modes: ['test'=>new TranslatableMarkup('Prueba local: sin dinero real')],
  payment_type: 'payment_manual',
  requires_billing_information: FALSE,
)]
final class AcademicGateway extends Manual {
  public function createPayment(PaymentInterface $payment, $received = FALSE) {
    $this->assertPaymentState($payment,['new']);
    \Drupal::service('roma_shop.store')->pay($payment->getOrder(),$payment);
  }
}
