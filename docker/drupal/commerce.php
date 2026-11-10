<?php
// Configuración idempotente: no sobrescribe tarifas ni inventario hotelero.
use Drupal\commerce_price\Entity\Currency;
use Drupal\commerce_store\Entity\Store;
use Drupal\commerce_store\Entity\StoreType;
use Drupal\commerce_order\Entity\OrderItemType;
use Drupal\commerce_order\Entity\OrderType;
use Drupal\commerce_product\Entity\ProductType;
use Drupal\commerce_product\Entity\ProductVariationType;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\commerce_checkout\Entity\CheckoutFlow;
use Drupal\user\Entity\Role;

if (!Currency::load('PEN')) {
  Currency::create(['currencyCode'=>'PEN','name'=>'Sol peruano','numericCode'=>'604','symbol'=>'S/','fractionDigits'=>2])->save();
}
if (!StoreType::load('roma_academic')) {
  StoreType::create(['id'=>'roma_academic','label'=>'Tienda académica'])->save();
}
$stores = \Drupal::entityTypeManager()->getStorage('commerce_store')->loadByProperties(['type'=>'roma_academic']);
if (!$stores) {
  $store = Store::create(['type'=>'roma_academic','uid'=>1,'name'=>'ROMA · Tienda académica','mail'=>'demo@example.invalid','default_currency'=>'PEN','timezone'=>'America/Lima','address'=>['country_code'=>'PE','locality'=>'Juliaca','administrative_area'=>'PUN','address_line1'=>'Dirección ilustrativa del prototipo'],'is_default'=>TRUE]);
  $store->save();
}
if (!OrderType::load('roma_stay')) {
  OrderType::create(['id'=>'roma_stay','label'=>'Pedido de estadías (demo)','workflow'=>'roma_shop_order','refresh_mode'=>'always','refresh_frequency'=>0,'sendReceipt'=>FALSE,'numberPattern'=>'order_default'])->save();
}
if (!OrderItemType::load('roma_night')) {
  OrderItemType::create(['id'=>'roma_night','label'=>'Estadía por noches','purchasableEntityType'=>'commerce_product_variation','orderType'=>'roma_stay'])->save();
}
if (!ProductVariationType::load('roma_night')) {
  ProductVariationType::create(['id'=>'roma_night','label'=>'Noche de habitación','orderItemType'=>'roma_night','generateTitle'=>FALSE])->save();
}
if (!ProductType::load('roma_stay')) {
  ProductType::create(['id'=>'roma_stay','label'=>'Estadía de demostración','variationTypes'=>['roma_night'],'multipleVariations'=>FALSE,'injectVariationFields'=>FALSE])->save();
}
if (!CheckoutFlow::load('roma_stay')) {
  CheckoutFlow::create(['id'=>'roma_stay','label'=>'Checkout de estadías · demostración','plugin'=>'roma_stay','configuration'=>[
    'display_checkout_progress'=>TRUE,'display_sidebar_checkout_complete'=>FALSE,
    'guest_order_assign'=>FALSE,'guest_new_account'=>FALSE,'guest_new_account_notify'=>FALSE,
    'panes'=>[
      'login'=>['step'=>'_disabled'], 'contact_information'=>['step'=>'_disabled'],
      'billing_information'=>['step'=>'_disabled'], 'completion_message'=>['step'=>'_disabled'],
      'roma_guest'=>['step'=>'order_information','weight'=>0,'display_label'=>'Tus datos de prueba'],
      'payment_information'=>['step'=>'_disabled'],
      'review'=>['step'=>'review','weight'=>0,'display_label'=>'Revisa tu pedido'],
      'payment_process'=>['step'=>'_disabled'],
      'roma_payment'=>['step'=>'payment','weight'=>0,'display_label'=>'Pago de demostración'],
      'roma_receipt'=>['step'=>'complete','weight'=>0,'display_label'=>'Pedido registrado'],
      'order_summary'=>['step'=>'_sidebar','weight'=>0],
    ],
  ]])->save();
}
$type = OrderType::load('roma_stay');
$type->setThirdPartySetting('commerce_checkout', 'checkout_flow', 'roma_stay')->save();
if (!PaymentGateway::load('roma_academic')) {
  PaymentGateway::create(['id'=>'roma_academic','label'=>'Pasarela académica local · sin cobros','plugin'=>'roma_academic','status'=>TRUE,'configuration'=>['mode'=>'test','display_label'=>'Pago de prueba local · sin dinero real','instructions'=>['value'=>'Resultado de demostración registrado. No se ha cobrado dinero ni emitido comprobante comercial. Las reservas requieren confirmación administrativa.','format'=>'plain_text']],'conditions'=>[]])->save();
}
foreach (['anonymous','authenticated'] as $id) {
  Role::load($id)->grantPermission('access checkout')->save();
}
// Las páginas de producto no ofrecen un botón que omita fechas/capacidad.
\Drupal::service('entity_display.repository')->getViewDisplay('commerce_product', 'roma_stay', 'default')->removeComponent('variations')->save();
\Drupal::service('roma_shop.store')->syncProducts();
echo "Drupal Commerce configurado: PEN, productos hoteleros y checkout de prueba.\n";
