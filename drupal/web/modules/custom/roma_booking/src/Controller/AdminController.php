<?php
namespace Drupal\roma_booking\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\roma_booking\Service\HotelService;
use Symfony\Component\DependencyInjection\ContainerInterface;

final class AdminController extends ControllerBase {
  public function __construct(private readonly HotelService $hotel) {}
  public static function create(ContainerInterface $container): static { return new static($container->get('roma_booking.hotel')); }
  private function cell(mixed $value): array { return ['data' => ['#plain_text' => (string) $value]]; }
  private function link(string $text, string $route, array $parameters = []): array { return ['data' => Link::fromTextAndUrl($text, Url::fromRoute($route, $parameters))->toRenderable()]; }
  private function shell(): array {
    $items = [];
    foreach (['roma_booking.dashboard' => ['Panel', 'access roma dashboard'], 'roma_booking.catalog' => ['Habitaciones y tarifas', 'administer roma catalog'], 'roma_booking.content' => ['Contenido', 'administer roma catalog'], 'roma_booking.reservations' => ['Reservas', 'manage roma reservations'], 'roma_booking.messages' => ['Mensajes', 'manage roma reservations'], 'roma_booking.availability' => ['Disponibilidad', 'view roma availability']] as $route => [$label, $permission]) {
      if ($this->currentUser()->hasPermission($permission)) { $items[] = Link::fromTextAndUrl($label, Url::fromRoute($route))->toRenderable(); }
    }
    if (\Drupal::moduleHandler()->moduleExists('roma_shop') && $this->currentUser()->hasPermission('administer commerce_order')) { $items[] = Link::fromTextAndUrl('Pedidos de la tienda', Url::fromRoute('roma_shop.orders'))->toRenderable(); }
    if (\Drupal::moduleHandler()->moduleExists('roma_reception') && $this->currentUser()->hasPermission('manage roma reception')) { $items[] = Link::fromTextAndUrl('Recepción y calendario', Url::fromRoute('roma_reception.calendar'))->toRenderable(); }
    if (\Drupal::moduleHandler()->moduleExists('roma_finance') && $this->currentUser()->hasPermission('manage roma finance')) { $items[] = Link::fromTextAndUrl('Cuentas y saldos', Url::fromRoute('roma_finance.overview'))->toRenderable(); }
    if (\Drupal::moduleHandler()->moduleExists('roma_sunat_beta') && $this->currentUser()->hasPermission('manage roma sunat beta')) { $items[] = Link::fromTextAndUrl('SUNAT beta', Url::fromRoute('roma_sunat_beta.overview'))->toRenderable(); }
    return ['#cache' => ['max-age' => 0], '#attached' => ['library' => ['roma_booking/hotel']], 'navigation' => ['#theme' => 'item_list', '#items' => $items, '#attributes' => ['class' => ['hotel-admin-nav']]], 'notice' => ['#markup' => '<p>Prototipo académico no oficial. Datos de prueba. Los importes estimados no representan pagos ni ingresos reales.</p>']];
  }
  public function dashboard(): array {
    $build = $this->shell(); $s = $this->hotel->stats();
    $rows = [];
    foreach (['active_rooms' => 'Habitaciones activas', 'occupied' => 'Habitaciones ocupadas hoy en la prueba', 'pending' => 'Solicitudes pendientes', 'confirmed' => 'Reservas confirmadas de prueba', 'cancelled' => 'Solicitudes canceladas'] as $key => $label) { $rows[] = [$this->cell($label), $this->cell($s[$key])]; }
    $rows[] = [$this->cell('Importe estimado acumulado de confirmadas (no recaudado)'), $this->cell(HotelService::money($s['estimated_revenue']))];
    $build['stats'] = ['#type' => 'table', '#header' => ['Indicador', 'Valor'], '#rows' => $rows];
    if (\Drupal::moduleHandler()->moduleExists('roma_api')) {
      $db=\Drupal::database(); $rows=[];
      foreach (['queued'=>'Avisos pendientes','delivered'=>'Correos capturados localmente','skipped'=>'Avisos omitidos'] as $state=>$label) { $rows[]=[$this->cell($label),$this->cell($db->select('roma_notification','n')->condition('state',$state)->countQuery()->execute()->fetchField())]; }
      $build['automation']=['#type'=>'table','#caption'=>'Automatizaciones n8n · captura local Mailpit (sin correo externo)','#header'=>['Indicador','Cantidad'],'#rows'=>$rows];
    }
    $build['convention'] = ['#markup' => '<p>Ocupación: ingreso inclusivo y salida exclusiva. Las confirmadas y los mantenimientos activos bloquean sus fechas; las pendientes no bloquean. La limpieza y las salidas pendientes se comprueban para entradas de hoy. La disponibilidad se vuelve a comprobar dentro de una transacción antes de confirmar.</p>'];
    return $build;
  }
  public function catalog(): array {
    $build = $this->shell();
    foreach (['type' => 'Crear tipo y tarifa', 'room' => 'Crear habitación', 'service' => 'Crear servicio'] as $kind => $label) { $build['add_' . $kind] = Link::fromTextAndUrl($label, Url::fromRoute('roma_booking.catalog_edit', ['kind' => $kind, 'id' => 0]))->toRenderable(); $build['add_' . $kind]['#prefix'] = '<p>'; $build['add_' . $kind]['#suffix'] = '</p>'; }
    $rows = [];
    foreach ($this->hotel->types(FALSE) as $t) { $rows[] = [$this->cell($t['name']), $this->cell($t['capacity']), $this->cell(HotelService::money((int) $t['amount'])), $this->cell($t['active'] ? 'Activo' : 'Inactivo'), $this->link('Editar tipo y tarifa', 'roma_booking.catalog_edit', ['kind' => 'type', 'id' => $t['id']])]; }
    $build['types'] = ['#type' => 'table', '#caption' => 'Tipos y tarifas ficticias por noche', '#header' => ['Tipo', 'Capacidad', 'Tarifa PEN', 'Estado', 'Acción'], '#rows' => $rows];
    $rows = [];
    foreach ($this->hotel->rooms(FALSE) as $r) { $rows[] = [$this->cell($r['code']), $this->cell($r['type_name']), $this->cell($r['active'] ? 'Activa' : 'Inactiva'), $this->link('Editar o desactivar', 'roma_booking.catalog_edit', ['kind' => 'room', 'id' => $r['id']])]; }
    $build['rooms'] = ['#type' => 'table', '#caption' => 'Habitaciones individuales del inventario', '#header' => ['Código', 'Tipo', 'Estado', 'Acción'], '#rows' => $rows];
    $rows = [];
    foreach ($this->hotel->services(FALSE) as $s) { $rows[] = [$this->cell($s['name']), $this->cell($s['description']), $this->cell($s['active'] ? 'Activo' : 'Inactivo'), $this->link('Editar servicio', 'roma_booking.catalog_edit', ['kind' => 'service', 'id' => $s['id']])]; }
    $build['services'] = ['#type' => 'table', '#caption' => 'Servicios de prueba', '#header' => ['Servicio', 'Descripción', 'Estado', 'Acción'], '#rows' => $rows];
    return $build;
  }
  public function reservations(): array {
    $build = $this->shell(); $rows = [];
    foreach ($this->hotel->reservations() as $b) {
      $room = $this->hotel->room((int) $b['room_id']); $actions = [];
      if (\Drupal::moduleHandler()->moduleExists('roma_finance') && $this->currentUser()->hasPermission('manage roma finance')) { $actions[] = Link::fromTextAndUrl('Ver cuenta', Url::fromRoute('roma_finance.account', ['reservation'=>$b['id']]))->toRenderable(); }
      if ($b['status'] === 'pending') { $actions[] = Link::fromTextAndUrl('Confirmar', Url::fromRoute('roma_booking.transition', ['id' => $b['id'], 'action' => 'confirm']))->toRenderable(); }
      if ($b['status'] !== 'cancelled') { $actions[] = Link::fromTextAndUrl('Cancelar', Url::fromRoute('roma_booking.transition', ['id' => $b['id'], 'action' => 'cancel']))->toRenderable(); }
      $rows[] = [$this->cell($b['reference']), $this->cell($b['customer_name']), $this->cell($b['email']), $this->cell($b['phone'] ?: '—'), $this->cell($room['code']), $this->cell(HotelService::date($b['arrival']) . ' → ' . HotelService::date($b['departure'])), $this->cell($b['guests']), $this->cell(HotelService::STATUS[$b['status']]), $this->cell(HotelService::money((int) $b['estimated_price'])), $this->cell(\Drupal::hasService('roma_payments.demo') ? ((\Drupal::service('roma_payments.demo')->last($b['reference'])['outcome'] ?? 'Sin simulación')) : 'No habilitado'), ['data' => ['#theme' => 'item_list', '#items' => $actions]]];
    }
    $build['reservations'] = ['#type' => 'table', '#caption' => 'Últimas 200 solicitudes; las acciones requieren confirmación mediante formulario.', '#header' => ['Código', 'Cliente de prueba', 'Correo', 'Teléfono', 'Habitación', 'Fechas', 'Huéspedes', 'Estado', 'Estimado', 'Pago ficticio', 'Acciones'], '#rows' => $rows, '#empty' => 'Todavía no hay solicitudes de prueba.'];
    return $build;
  }
  public function messages(): array {
    $build = $this->shell(); $rows = [];
    foreach ($this->hotel->messages() as $m) { $rows[] = [$this->cell($m['id']), $this->cell($m['name']), $this->cell($m['email']), $this->cell($m['message'])]; }
    $build['messages'] = ['#type' => 'table', '#caption' => 'Últimos 100 mensajes de prueba guardados; no se enviaron al hostal.', '#header' => ['ID', 'Nombre', 'Correo', 'Mensaje'], '#rows' => $rows, '#empty' => 'Sin mensajes.'];
    return $build;
  }
}
