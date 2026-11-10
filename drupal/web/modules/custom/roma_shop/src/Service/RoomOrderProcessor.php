<?php
namespace Drupal\roma_shop\Service;

use Drupal\commerce_order\OrderProcessorInterface;
use Drupal\commerce_order\Entity\OrderInterface;
use Drupal\roma_booking\Service\HotelService;

/** La cantidad es el número de noches; la tarifa procede del inventario. */
final class RoomOrderProcessor implements OrderProcessorInterface {
  public function __construct(private readonly HotelService $hotel) {}
  public function process(OrderInterface $order) {
    if ($order->bundle() !== 'roma_stay') { return; }
    foreach ($order->getItems() as $item) {
      $line = $item->getData('roma', []);
      if (!$line) { continue; }
      try {
        $nights = $this->hotel->dates($line['arrival'], $line['departure'], (int) $line['guests']);
        $room = $this->hotel->room((int) $line['room_id']);
        $item->setQuantity((string) $nights);
        $item->setUnitPrice(ShopService::price((int) $room['amount']), TRUE);
        $item->setTitle($room['type_name'] . ' · ' . $room['code'] . ' · ' . HotelService::date($line['arrival']) . ' al ' . HotelService::date($line['departure']) . ' · ' . $line['guests'] . ' huésped(es)');
      }
      catch (\DomainException) { /* El checkout mostrará el error sin perder el carrito. */ }
    }
  }
}
