<?php
/** Pruebas de integración de Commerce con MariaDB; usa solo fixtures ficticias. */
use Drupal\commerce_order\Entity\Order;
use Drupal\commerce_order\Entity\OrderItem;
use Drupal\commerce_payment\Entity\Payment;
use Drupal\commerce_payment\Entity\PaymentGateway;
use Drupal\roma_shop\Service\ShopService;

$shop = \Drupal::service('roma_shop.store'); $hotel = \Drupal::service('roma_booking.hotel'); $db = \Drupal::database();
$checks = []; $orders = []; $extraBookings = [];
$check = static function(bool $ok, string $name) use (&$checks): void { if (!$ok) { throw new \RuntimeException($name); } $checks[] = $name; echo "OK $name\n"; };
$reject = static function(callable $action, string $name) use ($check): void { try { $action(); } catch (\DomainException) { $check(TRUE,$name); return; } throw new \RuntimeException('No se rechazó: ' . $name); };
$arrival = (new \DateTimeImmutable($hotel->today()))->modify('+120 days')->format('Y-m-d');
$departure = (new \DateTimeImmutable($arrival))->modify('+2 days')->format('Y-m-d');
$rooms = array_slice($hotel->availability($arrival,$departure,1),0,2);
$check(count($rooms) === 2,'Hay dos habitaciones de demostración para las fixtures');
$create = static function() use ($rooms,$arrival,$departure,$shop,&$orders): Order {
  $order = Order::create(['type'=>'roma_stay','store_id'=>$shop->store()->id(),'uid'=>0,'cart'=>TRUE,'checkout_flow'=>'roma_stay','mail'=>'commerce-qa@example.invalid']);
  $order->setData('roma_guest',['name'=>'Cliente ficticio prueba Commerce','phone'=>'','consent'=>TRUE]);
  $order->setData('roma_test_fixture',TRUE); $order->save(); $orders[] = $order;
  foreach ($rooms as $room) {
    $variations = \Drupal::entityTypeManager()->getStorage('commerce_product_variation')->loadByProperties(['sku'=>'ROMA-ROOM-' . $room['id']]);
    $variation = reset($variations);
    $item = OrderItem::create(['type'=>'roma_night','order_id'=>$order->id(),'purchased_entity'=>$variation,'title'=>'Estadía ficticia QA · ' . $room['code'],'quantity'=>'2','unit_price'=>ShopService::price((int) $room['amount'])]);
    $item->setUnitPrice(ShopService::price((int) $room['amount']),TRUE);
    $item->setData('roma',['room_id'=>(int) $room['id'],'arrival'=>$arrival,'departure'=>$departure,'guests'=>1]);
    $item->save(); $order->addItem($item);
  }
  $order->save(); return $order;
};
$gateway = PaymentGateway::load('roma_academic');
$payment = static fn(Order $order) => Payment::create(['state'=>'new','payment_gateway'=>$gateway,'order_id'=>$order->id(),'amount'=>$order->getTotalPrice()]);
$paymentCount = static fn($order) => (int) $db->select('commerce_payment','p')->condition('order_id',$order->id())->countQuery()->execute()->fetchField();
$links = static fn($order) => $db->select('roma_shop_booking','l')->fields('l')->condition('order_id',$order->id())->execute()->fetchAll(\PDO::FETCH_ASSOC);
try {
  $check($gateway->getPlugin()->getMode() === 'test' && array_keys($gateway->getPlugin()->getPluginDefinition()['modes']) === ['test'],'La pasarela solo ofrece modo test');
  $order = $create();
  $expected = 2 * ((int) $rooms[0]['amount'] + (int) $rooms[1]['amount']);
  $check($shop->validate($order) === $expected && $order->getTotalPrice()->getCurrencyCode() === 'PEN','Total de varias estadías calculado en PEN desde MariaDB');
  $item = $order->getItems()[0];
  $item->setQuantity('99'); $reject(fn()=>$shop->validate($order),'Cantidad manipulada rechazada'); $item->setQuantity('2');
  $item->setUnitPrice(new \Drupal\commerce_price\Price('0.01','PEN'),TRUE); $reject(fn()=>$shop->validate($order),'Precio de línea manipulado rechazado'); $item->setUnitPrice(ShopService::price((int) $rooms[0]['amount']),TRUE);
  $tampered = $payment($order); $tampered->setAmount(new \Drupal\commerce_price\Price('0.01','PEN'));
  $reject(fn()=>$shop->pay($order,$tampered),'Importe de pago manipulado rechazado');
  $check(!$links($order) && $paymentCount($order) === 0,'El rechazo de importe no crea pagos ni reservas');
  $order->setData('roma_guest',['name'=>'Cliente ficticio','consent'=>FALSE]);
  $reject(fn()=>$shop->pay($order,$payment($order)),'Checkout sin consentimiento rechazado');
  $order->setData('roma_guest',['name'=>'Cliente ficticio prueba Commerce','phone'=>'','consent'=>TRUE]);
  $gateway->getPlugin()->createPayment($payment($order));
  $rows = $links($order);
  $check(count($rows) === 2 && $paymentCount($order) === 1,'Un pago de prueba registra exactamente dos solicitudes');
  foreach ($rows as $row) { $check($hotel->reservation((int) $row['reservation_id'])['status'] === 'pending','El pago no confirma automáticamente la reserva'); }
  $payments = \Drupal::entityTypeManager()->getStorage('commerce_payment')->loadByProperties(['order_id'=>$order->id()]); $saved = reset($payments);
  $check($saved->getState()->getId() === 'completed' && $saved->getPaymentGatewayMode() === 'test' && $saved->getAmount()->equals(ShopService::price($expected)),'Pago Commerce persistido con importe correcto y modo test');
  $gateway->getPlugin()->createPayment($payment($order));
  $check(count($links($order)) === 2 && $paymentCount($order) === 1,'El reenvío no duplica pagos ni solicitudes');
  // El subsistema de pagos actualiza la versión del pedido al guardar.
  $order = \Drupal::entityTypeManager()->getStorage('commerce_order')->loadUnchanged($order->id());
  $order->getState()->applyTransitionById('place'); $order->save();
  $shop->reconcile(); $fresh = \Drupal::entityTypeManager()->getStorage('commerce_order')->loadUnchanged($order->id());
  $check($fresh->getState()->getId() === 'awaiting_confirmation','Pedido pendiente mientras las reservas esperan al administrador');
  foreach ($rows as $row) { $hotel->transition((int) $row['reservation_id'],'confirmed',1); }
  $shop->reconcile(); $fresh = \Drupal::entityTypeManager()->getStorage('commerce_order')->loadUnchanged($order->id());
  $check($fresh->getState()->getId() === 'completed','La reconciliación refleja todas las confirmaciones');
  foreach ($rows as $row) { $hotel->transition((int) $row['reservation_id'],'cancelled',1); }
  $shop->reconcile(); $fresh = \Drupal::entityTypeManager()->getStorage('commerce_order')->loadUnchanged($order->id());
  $check($fresh->getState()->getId() === 'canceled','La reconciliación refleja todas las cancelaciones');

  $atomic = $create(); $second = $atomic->getItems()[1];
  $dummy = $hotel->request($second->getData('roma') + ['customer_name'=>'Cliente ficticio atomicidad','email'=>'atomic@example.invalid','request_key'=>hash('sha256','commerce:' . $atomic->uuid() . ':' . $second->id())]);
  $extraBookings[] = $dummy['id'];
  $before = (int) $db->select('roma_reservation','b')->countQuery()->execute()->fetchField();
  $reject(fn()=>$shop->pay($atomic,$payment($atomic)),'Fallo de una línea rechaza el checkout completo');
  $after = (int) $db->select('roma_reservation','b')->countQuery()->execute()->fetchField();
  $check($before === $after && !$links($atomic) && $paymentCount($atomic) === 0,'La transacción revierte también la primera solicitud y el pago');
  file_put_contents('/tmp/roma-commerce-domain.json',json_encode(['date'=>date(DATE_ATOM),'checks'=>$checks,'fixtures'=>'Pedidos y solicitudes ficticios cancelados; historial conservado.'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}
finally {
  foreach ($orders as $fixture) {
    foreach ($links($fixture) as $row) { if ($hotel->reservation((int) $row['reservation_id'])['status'] !== 'cancelled') { $hotel->transition((int) $row['reservation_id'],'cancelled',1); } }
    $fresh = \Drupal::entityTypeManager()->getStorage('commerce_order')->loadUnchanged($fixture->id());
    if ($fresh && $fresh->getState()->getId() !== 'canceled') { $fresh->unlock(); $fresh->getState()->applyTransitionById('cancel'); $fresh->save(); }
  }
  foreach ($extraBookings as $id) { $hotel->transition((int) $id,'cancelled',1); }
}
