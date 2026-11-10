<?php
namespace Drupal\roma_shop\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\roma_booking\Form\HotelFormBase;
use Drupal\roma_booking\Service\HotelService;
use Drupal\roma_shop\Service\ShopService;

final class CartForm extends HotelFormBase {
  public function getFormId(): string { return 'roma_cart_form'; }
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $this->protect($form);
    $form['#attached']['library'][] = 'roma_shop/shop';
    $form['intro'] = ['#markup'=>'<p>Revisa las estadías de demostración. El total se calcula en soles (PEN) con las tarifas del sistema. Solo el checkout registra las solicitudes; el administrador confirma después cada reserva.</p>'];
    $cart = \Drupal::service('roma_shop.store')->cart();
    if (!$cart || !$cart->hasItems()) {
      $form['empty'] = ['#markup'=>'<div class="hotel-notice"><h2>Tu carrito está vacío</h2><p>Agrega una habitación con sus fechas para comenzar.</p></div>'];
    }
    else {
      \Drupal::service('commerce_order.order_refresh')->refresh($cart); $cart->save();
      $form['items'] = ['#type'=>'container','#attributes'=>['class'=>['shop-cart-items']]];
      foreach ($cart->getItems() as $item) {
        $line = $item->getData('roma', []);
        $form['items']['item_' . $item->id()] = [
          '#type'=>'container','#attributes'=>['class'=>['shop-cart-item']],
          'title'=>['#type'=>'item','#title'=>$item->getTitle()],
          'price'=>['#plain_text'=>(int) $item->getQuantity() . ' noche(s) · ' . ShopService::format($item->getTotalPrice())],
          'remove'=>['#type'=>'submit','#value'=>'Quitar estadía','#name'=>'remove_' . $item->id(),'#roma_item_id'=>(int) $item->id(),'#submit'=>['::remove']],
        ];
      }
      $form['total'] = ['#type'=>'item','#title'=>'Total de demostración','#plain_text'=>ShopService::format($cart->getTotalPrice()),'#attributes'=>['class'=>['shop-total']]];
      $form['actions'] = ['#type'=>'actions','submit'=>['#type'=>'submit','#value'=>'Continuar al checkout']];
    }
    $form['continue'] = Link::fromTextAndUrl('Seguir eligiendo estadías',Url::fromRoute('roma_shop.catalog'))->toRenderable();
    return $form;
  }
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->guard($form,$form_state,'roma.shop.cart',60)) { return; }
    if (!empty($form_state->getTriggeringElement()['#roma_item_id'])) { return; }
    $cart = \Drupal::service('roma_shop.store')->cart();
    try { if (!$cart) { throw new \DomainException('Agrega una estadía antes de continuar.'); } \Drupal::service('roma_shop.store')->validate($cart); }
    catch (\DomainException $error) { $form_state->setErrorByName('_roma_csrf',$error->getMessage()); }
  }
  public function remove(array &$form, FormStateInterface $form_state): void {
    $cart = \Drupal::service('roma_shop.store')->cart();
    foreach ($cart?->getItems() ?? [] as $item) {
      if ((int) $item->id() === $form_state->getTriggeringElement()['#roma_item_id']) { \Drupal::service('commerce_cart.cart_manager')->removeOrderItem($cart,$item); break; }
    }
    $form_state->setRedirect('roma_shop.cart');
  }
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $cart = \Drupal::service('roma_shop.store')->cart();
    $form_state->setRedirect('commerce_checkout.form',['commerce_order'=>$cart->id()]);
  }
}
