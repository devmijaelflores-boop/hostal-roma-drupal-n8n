<?php
namespace Drupal\roma_shop\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\roma_booking\Service\HotelService;
use Symfony\Component\HttpFoundation\RedirectResponse;

final class ShopController extends ControllerBase {
  public function cartRedirect(): RedirectResponse { return $this->redirect('roma_shop.cart'); }
  public function catalog(): array {
    $rooms = [];
    $theme = base_path() . \Drupal::service('extension.list.theme')->getPath('roma_elegante');
    foreach (\Drupal::service('roma_booking.hotel')->rooms() as $room) {
      $rooms[] = [
        'name'=>$room['type_name'],'code'=>$room['code'],'description'=>$room['description'],'capacity'=>$room['capacity'],
        'price'=>HotelService::money((int) $room['amount']), 'image'=>$theme . '/assets/images/' . $room['image'],
        'url'=>Url::fromRoute('roma_shop.add',[],['query'=>['room'=>$room['id']]])->toString(),
      ];
    }
    return ['#theme'=>'roma_shop_catalog','#rooms'=>$rooms,'#cart_url'=>Url::fromRoute('roma_shop.cart')->toString(),'#cache'=>['max-age'=>0],'#attached'=>['library'=>['roma_shop/shop']]];
  }
  public function orders(): array {
    $storage = $this->entityTypeManager()->getStorage('commerce_order');
    $ids = $storage->getQuery()->accessCheck(TRUE)->condition('type','roma_stay')->sort('order_id','DESC')->range(0,100)->execute();
    $rows = [];
    foreach ($storage->loadMultiple($ids) as $order) {
      $q = \Drupal::database()->select('roma_shop_booking','l')->condition('l.order_id',$order->id());
      $q->join('roma_reservation','b','b.id = l.reservation_id');
      $references = $q->fields('b',['reference','status'])->execute()->fetchAll(\PDO::FETCH_ASSOC);
      $bookings = [];
      foreach ($references as $booking) { $bookings[] = $booking['reference'] . ' · ' . HotelService::STATUS[$booking['status']]; }
      $rows[] = [
        ['data'=>Link::fromTextAndUrl($order->getOrderNumber() ?: 'Carrito ' . $order->id(),$order->toUrl('edit-form'))->toRenderable()],
        ['data'=>['#plain_text'=>(string) $order->getState()->getLabel()]],
        ['data'=>['#plain_text'=>($order->getTotalPrice()?->getNumber() ?? '0.00') . ' PEN']],
        ['data'=>['#plain_text'=>$order->getData('roma_checkout_done') ? 'Aprobado de prueba · sin dinero real' : 'Sin pago aprobado']],
        ['data'=>['#theme'=>'item_list','#items'=>$bookings ?: ['Sin solicitudes registradas']]],
      ];
    }
    return [
      '#cache'=>['max-age'=>0],'#attached'=>['library'=>['roma_booking/hotel']],
      'intro'=>['#markup'=>'<p>Pedidos de prueba. La aprobación del pago local no confirma habitaciones. Confirma o cancela cada solicitud desde el panel hotelero; n8n actualiza el estado del pedido.</p>'],
      'reservations'=>Link::fromTextAndUrl('Gestionar solicitudes de reserva',Url::fromRoute('roma_booking.reservations'))->toRenderable(),
      'orders'=>['#type'=>'table','#caption'=>'Últimos 100 pedidos y carritos académicos','#header'=>['Pedido','Estado','Total ficticio','Pago de prueba','Solicitudes relacionadas'],'#rows'=>$rows,'#empty'=>'Todavía no hay pedidos.'],
    ];
  }
}
