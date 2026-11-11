<?php
namespace Drupal\roma_shop\Service;

use Drupal\Core\Database\Connection;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\commerce_payment\Entity\PaymentInterface;
use Drupal\commerce_price\Calculator;
use Drupal\commerce_price\Price;
use Drupal\commerce_product\Entity\Product;
use Drupal\commerce_product\Entity\ProductVariation;
use Drupal\roma_booking\Service\HotelService;
use Symfony\Component\HttpFoundation\RequestStack;

/** Adapta Commerce al inventario hotelero; todas las escrituras usan MariaDB. */
final class ShopService {
  public function __construct(private readonly Connection $db, private readonly HotelService $hotel, private readonly EntityTypeManagerInterface $entities, private readonly RequestStack $requests) {}
  public static function price(int $cents): Price { return new Price(intdiv($cents, 100) . '.' . sprintf('%02d', $cents % 100), 'PEN'); }
  public static function format(Price $price): string { return HotelService::money((int) Calculator::multiply($price->getNumber(), '100')) . ' PEN'; }
  public function store() { $stores = $this->entities->getStorage('commerce_store')->loadByProperties(['type'=>'roma_academic']); return reset($stores) ?: throw new \DomainException('La tienda aún no está configurada.'); }
  public function cart(): ?OrderInterface { return \Drupal::service('commerce_cart.cart_provider')->getCart('roma_stay', $this->store()); }
  public function syncProducts(): void {
    $store = $this->store();
    foreach ($this->hotel->rooms(FALSE) as $room) {
      $sku = 'ROMA-ROOM-' . $room['id'];
      $variations = $this->entities->getStorage('commerce_product_variation')->loadByProperties(['sku'=>$sku]);
      $variation = reset($variations) ?: ProductVariation::create(['type'=>'roma_night','sku'=>$sku]);
      $variation->setTitle($room['type_name'] . ' · ' . $room['code'] . ' · noche de prueba');
      $variation->setPrice(self::price((int) $room['amount']));
      $variation->set('status', (int) $room['active']);
      $variation->save();
      $product = $variation->getProduct();
      if (!$product) {
        $product = Product::create(['type'=>'roma_stay','title'=>$variation->getTitle(),'stores'=>[$store],'variations'=>[$variation],'status'=>TRUE]);
        $product->save();
      }
    }
  }
  public function add(int $roomId, string $arrival, string $departure, int $guests): void {
    $nights = $this->hotel->dates($arrival, $departure, $guests);
    $rooms = $this->hotel->availability($arrival, $departure, $guests);
    $matches = array_values(array_filter($rooms, static fn($r) => (int) $r['id'] === $roomId));
    if (!$matches) { throw new \DomainException('La habitación no está disponible para esas fechas y huéspedes.'); }
    $room = $matches[0];
    $this->syncProducts();
    $provider = \Drupal::service('commerce_cart.cart_provider');
    $cart = $this->cart() ?: $provider->createCart('roma_stay', $this->store());
    if (count($cart->getItems()) >= 6) { throw new \DomainException('El carrito admite hasta seis estadías.'); }
    foreach ($cart->getItems() as $item) {
      $line = $item->getData('roma', []);
      if (($line['room_id'] ?? 0) === $roomId && $arrival < $line['departure'] && $departure > $line['arrival']) {
        throw new \DomainException('Esa habitación ya está en tu carrito para fechas superpuestas. Puedes agregar otra habitación.');
      }
    }
    $variations = $this->entities->getStorage('commerce_product_variation')->loadByProperties(['sku'=>'ROMA-ROOM-' . $roomId]);
    $variation = reset($variations);
    $manager = \Drupal::service('commerce_cart.cart_manager');
    $item = $manager->createOrderItem($variation, (string) $nights);
    $item->setData('roma', ['room_id'=>$roomId,'arrival'=>$arrival,'departure'=>$departure,'guests'=>$guests]);
    $item->setUnitPrice(self::price((int) $room['amount']), TRUE);
    $item->setTitle($room['type_name'] . ' · ' . $room['code'] . ' · ' . HotelService::date($arrival) . ' al ' . HotelService::date($departure) . ' · ' . $guests . ' huésped(es)');
    $cart->set('checkout_flow', 'roma_stay');
    $manager->addOrderItem($cart, $item, FALSE);
  }
  /** Comprueba también manipulaciones de cantidad, precio o fechas. */
  public function validate(OrderInterface $order): int {
    if ($order->bundle() !== 'roma_stay' || !$order->hasItems() || count($order->getItems()) > 6) { throw new \DomainException('Revisa tu carrito antes de continuar.'); }
    $total = 0; $checked = [];
    foreach ($order->getItems() as $item) {
      $line = $item->getData('roma', []);
      if (count(array_intersect(['room_id','arrival','departure','guests'], array_keys($line))) !== 4) { throw new \DomainException('Una estadía no tiene fechas válidas. Quítala del carrito y agrégala de nuevo.'); }
      $nights = $this->hotel->dates($line['arrival'], $line['departure'], (int) $line['guests']);
      $available = $this->hotel->availability($line['arrival'], $line['departure'], (int) $line['guests']);
      $room = current(array_filter($available, static fn($r) => (int) $r['id'] === (int) $line['room_id']));
      if (!$room) { throw new \DomainException('Una habitación dejó de estar disponible. Revisa las fechas y la ocupación en tu carrito.'); }
      foreach ($checked as $prior) {
        if ($prior['room_id'] === $line['room_id'] && $line['arrival'] < $prior['departure'] && $line['departure'] > $prior['arrival']) { throw new \DomainException('El carrito contiene estadías superpuestas para una misma habitación.'); }
      }
      if (Calculator::compare($item->getQuantity(), (string) $nights) !== 0 || !$item->getUnitPrice()?->equals(self::price((int) $room['amount']))) { throw new \DomainException('La tarifa o cantidad cambió. Vuelve al carrito para revisar el total actualizado.'); }
      $total += $nights * (int) $room['amount'];
      $checked[] = $line;
    }
    if (!$order->getTotalPrice()?->equals(self::price($total))) { throw new \DomainException('El total del pedido necesita actualizarse. Vuelve al carrito.'); }
    return $total;
  }
  /** Pago local y solicitudes se guardan juntos, sin confirmar la disponibilidad. */
  public function pay(OrderInterface $order, PaymentInterface $payment): void {
    if ($payment->getPaymentGateway()->id() !== 'roma_academic' || $payment->getPaymentGateway()->getPlugin()->getMode() !== 'test') { throw new \DomainException('Solo está habilitada la pasarela académica en modo de prueba.'); }
    $transaction = $this->db->startTransaction();
    $receipts = [];
    try {
      $this->db->query('SELECT id FROM {roma_guard} WHERE id = 1 FOR UPDATE')->fetchField();
      $existing = $this->entities->getStorage('commerce_payment')->loadByProperties(['order_id'=>$order->id(),'payment_gateway'=>'roma_academic','state'=>'completed']);
      if ($existing) { $transaction->commitOrRelease(); return; }
      $total = $this->validate($order);
      $guest = $order->getData('roma_guest', []);
      if (empty($guest['consent']) || mb_strlen(trim($guest['name'] ?? '')) < 2 || !filter_var($order->getEmail(), FILTER_VALIDATE_EMAIL)) { throw new \DomainException('Completa los datos y el consentimiento de prueba.'); }
      if (!$payment->getAmount()->equals(self::price($total))) { throw new \DomainException('El importe del pago no coincide con el pedido.'); }
      foreach ($order->getItems() as $item) {
        $line = $item->getData('roma');
        $receipt = $this->hotel->request($line + ['customer_name'=>$guest['name'],'email'=>$order->getEmail(),'phone'=>$guest['phone'] ?? '', 'request_key'=>hash('sha256', 'commerce:' . $order->uuid() . ':' . $item->id())]);
        $this->db->insert('roma_shop_booking')->fields(['order_id'=>$order->id(),'order_item_id'=>$item->id(),'reservation_id'=>$receipt['id']])->execute();
        $receipts[] = $receipt;
      }
      $payment->setRemoteId('DEMO-COMMERCE-' . $order->uuid());
      $payment->setState('completed');
      $payment->save();
      $order->setData('roma_checkout_done', TRUE);
      $order->save();
      $transaction->commitOrRelease();
    }
    catch (\Throwable $error) { $transaction->rollBack(); throw $error; }
    if (($request = $this->requests->getCurrentRequest()) && $request->hasSession()) {
      $session = $request->getSession();
      $all = $session->get('roma_shop_receipts', []);
      $all[$order->id()] = $receipts;
      $session->set('roma_shop_receipts', $all);
    }
  }
  public function receipts(OrderInterface $order): array { $request = $this->requests->getCurrentRequest(); return $request?->hasSession() ? ($request->getSession()->get('roma_shop_receipts', [])[$order->id()] ?? []) : []; }
  public function attempt(OrderInterface $order, string $outcome): void {
    $attempts = $order->getData('roma_attempts', []);
    $attempts[] = ['outcome'=>$outcome,'created'=>time()];
    $order->setData('roma_attempts', array_slice($attempts, -20));
    $order->save();
  }
  /** n8n reconcilia el pedido con las decisiones administrativas de las reservas. */
  public function reconcile(): array {
    $changed = 0;
    $ids = $this->entities->getStorage('commerce_order')->getQuery()->accessCheck(FALSE)->condition('type','roma_stay')->condition('state',['awaiting_confirmation','completed'],'IN')->range(0,200)->execute();
    foreach ($this->entities->getStorage('commerce_order')->loadMultiple($ids) as $order) {
      $q = $this->db->select('roma_shop_booking','l')->condition('l.order_id',$order->id());
      $q->join('roma_reservation','b','b.id = l.reservation_id');
      $states = $q->fields('b',['status'])->execute()->fetchCol();
      if (!$states) { continue; }
      $allConfirmed = count(array_unique($states)) === 1 && $states[0] === 'confirmed';
      $allCancelled = count(array_unique($states)) === 1 && $states[0] === 'cancelled';
      if ($allConfirmed && $order->getState()->getId() === 'awaiting_confirmation') { $order->getState()->applyTransitionById('confirm'); $order->save(); $changed++; }
      elseif ($allCancelled) { $order->getState()->applyTransitionById('cancel'); $order->save(); $changed++; }
      elseif (!$allConfirmed && $order->getState()->getId() === 'completed') { $order->getState()->applyTransitionById('review'); $order->save(); $changed++; }
    }
    return ['orders_updated'=>$changed,'mode'=>'academic'];
  }
}
