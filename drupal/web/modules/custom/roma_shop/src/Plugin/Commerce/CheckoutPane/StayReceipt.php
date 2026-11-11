<?php
namespace Drupal\roma_shop\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;

#[CommerceCheckoutPane(id: 'roma_receipt', label: new TranslatableMarkup('Recibo de prueba'), default_step: 'complete')]
final class StayReceipt extends CheckoutPaneBase {
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $pane_form['receipt'] = ['#type'=>'container','#attributes'=>['class'=>['hotel-receipt']],
      'title'=>['#markup'=>'<h2>Pedido de prueba registrado</h2>'],
      'number'=>['#type'=>'item','#title'=>'Número de pedido','#plain_text'=>(string) $this->order->getOrderNumber()],
      'state'=>['#markup'=>'<p>Pago de prueba aprobado, sin dinero real. Las solicitudes de reserva están <strong>pendientes de confirmación</strong> por el administrador.</p>'],
    ];
    foreach (\Drupal::service('roma_shop.store')->receipts($this->order) as $i=>$receipt) {
      $pane_form['receipt']['booking_' . $i] = ['#type'=>'container',
        'reference'=>['#type'=>'item','#title'=>'Código de solicitud','#plain_text'=>$receipt['reference']],
        'secret'=>['#type'=>'item','#title'=>'Clave privada de consulta — guárdala','#plain_text'=>$receipt['token']],
      ];
    }
    $pane_form['receipt']['lookup'] = Link::fromTextAndUrl('Consultar mis solicitudes con código y clave',Url::fromRoute('roma_booking.lookup'))->toRenderable();
    $pane_form['receipt']['shop'] = Link::fromTextAndUrl('Volver a la tienda',Url::fromRoute('roma_shop.catalog'))->toRenderable();
    return $pane_form;
  }
}
