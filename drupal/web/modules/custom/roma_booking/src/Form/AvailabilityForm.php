<?php
namespace Drupal\roma_booking\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\roma_booking\Service\HotelService;

final class AvailabilityForm extends HotelFormBase {
  public function getFormId(): string { return 'roma_availability_form'; }
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $this->decorate($form); $form['#method'] = 'get'; $form['#token'] = FALSE;
    $form['intro'] = ['#markup' => '<p>Disponibilidad calculada desde las habitaciones y reservas confirmadas de la base de datos de demostración. Las solicitudes pendientes no bloquean habitaciones. No es disponibilidad del establecimiento real.</p>'];
    $active_rooms = $this->hotel->rooms();
    $max_capacity = $active_rooms ? max(array_map('intval', array_column($active_rooms, 'capacity'))) : 0;
    $q = $this->getRequest()->query;
    foreach (['arrival' => 'Llegada', 'departure' => 'Salida'] as $key => $label) {
      $form[$key] = ['#type' => 'date', '#title' => $label, '#required' => TRUE, '#default_value' => $q->get($key, ''), '#attributes' => ['min' => $this->hotel->today()]];
    }
    $form['guests'] = ['#type' => 'number', '#title' => 'Huéspedes en una habitación', '#description' => $max_capacity ? $this->t('La capacidad máxima del catálogo activo es de @count persona(s) por habitación. Para grupos mayores, registra una solicitud por cada habitación.', ['@count' => $max_capacity]) : $this->t('Actualmente no hay habitaciones activas en el catálogo de demostración.'), '#required' => TRUE, '#min' => 1, '#max' => 10, '#step' => 1, '#default_value' => $q->get('guests', 2)];
    $form['actions'] = ['#type' => 'actions', '#weight' => 10, 'submit' => ['#type' => 'submit', '#value' => 'Consultar disponibilidad de prueba']];
    if ($q->get('arrival') && $q->get('departure')) {
      try {
        $guests = filter_var($q->get('guests', 2), FILTER_VALIDATE_INT);
        if ($guests === FALSE) { throw new \DomainException('Introduce un número entero de huéspedes.'); }
        $rooms = $this->hotel->availability((string) $q->get('arrival'), (string) $q->get('departure'), $guests);
        $rows = [];
        foreach ($rooms as $r) {
          $rows[] = [['data' => ['#plain_text' => $r['type_name'] . ' · ' . $r['code']]], ['data' => ['#plain_text' => $r['capacity'] . ' huéspedes']], ['data' => ['#plain_text' => HotelService::money((int) $r['estimated_price'])]], ['data' => Link::fromTextAndUrl('Solicitar reserva de prueba', Url::fromRoute('roma_booking.request', [], ['query' => ['room' => $r['id'], 'arrival' => $q->get('arrival'), 'departure' => $q->get('departure'), 'guests' => $guests]]))->toRenderable()]];
        }
        if ($rows) {
          $form['results'] = ['#type' => 'table', '#weight' => 20, '#caption' => 'Resultados de demostración · ' . HotelService::date($q->get('arrival')) . ' al ' . HotelService::date($q->get('departure')), '#header' => ['Habitación', 'Capacidad', 'Estimado de la estadía', 'Solicitud'], '#rows' => $rows];
        }
        else {
          $form['results'] = ['#type' => 'container', '#weight' => 20, '#attributes' => ['class' => ['hotel-notice'], 'role' => 'status']];
          if ($max_capacity && $guests > $max_capacity) {
            $form['results']['title'] = ['#markup' => '<h2>Tu grupo necesita más de una habitación</h2>'];
            $form['results']['explanation'] = ['#plain_text' => $this->t('Buscaste para @guests personas en una sola habitación, pero la capacidad máxima del catálogo activo es de @capacity. Puedes consultar habitaciones para menos personas y registrar solicitudes separadas para tu grupo.', ['@guests' => $guests, '@capacity' => $max_capacity])];
            $label = $this->t('Consultar para @count persona(s)', ['@count' => $max_capacity]);
            $form['results']['adjust'] = Link::fromTextAndUrl($label, Url::fromRoute('roma_booking.availability', [], ['query' => ['arrival' => $q->get('arrival'), 'departure' => $q->get('departure'), 'guests' => $max_capacity]]))->toRenderable();
            $form['results']['adjust']['#attributes']['class'][] = 'hotel-adjust-search';
            $form['results']['limit'] = ['#type' => 'container', 'text' => ['#plain_text' => 'Cada solicitud se valida por separado. La disponibilidad y la confirmación se comprueban para cada habitación de la demo.']];
          }
          else {
            $form['results']['title'] = ['#markup' => '<h2>Sin resultados para esta búsqueda</h2>'];
            $form['results']['explanation'] = ['#plain_text' => $max_capacity ? 'No hay habitaciones de la demo que admitan esa ocupación y estén libres durante todas las fechas elegidas. Prueba otras fechas o una ocupación menor.' : 'Actualmente no hay habitaciones activas en el catálogo de demostración. Un administrador puede activarlas desde el panel.'];
          }
        }
      }
      catch (\DomainException $error) { $form['result_error'] = ['#type' => 'container', '#attributes' => ['class' => ['hotel-notice']], 'text' => ['#plain_text' => $error->getMessage()]]; }
    }
    return $form;
  }
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    try { $this->hotel->dates((string) $form_state->getValue('arrival'), (string) $form_state->getValue('departure'), (int) $form_state->getValue('guests')); }
    catch (\DomainException $error) { $form_state->setErrorByName('arrival', $error->getMessage()); }
  }
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $form_state->setRedirect('roma_booking.availability', [], ['query' => array_intersect_key($form_state->getValues(), array_flip(['arrival', 'departure', 'guests']))]);
  }
}
