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
    $q = $this->getRequest()->query;
    foreach (['arrival' => 'Llegada', 'departure' => 'Salida'] as $key => $label) {
      $form[$key] = ['#type' => 'date', '#title' => $label, '#required' => TRUE, '#default_value' => $q->get($key, ''), '#attributes' => ['min' => $this->hotel->today()]];
    }
    $form['guests'] = ['#type' => 'number', '#title' => 'Número de huéspedes', '#required' => TRUE, '#min' => 1, '#max' => 10, '#step' => 1, '#default_value' => $q->get('guests', 2)];
    $form['actions'] = ['#type' => 'actions', 'submit' => ['#type' => 'submit', '#value' => 'Consultar disponibilidad de prueba']];
    if ($q->get('arrival') && $q->get('departure')) {
      try {
        $guests = filter_var($q->get('guests', 2), FILTER_VALIDATE_INT);
        if ($guests === FALSE) { throw new \DomainException('Introduce un número entero de huéspedes.'); }
        $rooms = $this->hotel->availability((string) $q->get('arrival'), (string) $q->get('departure'), $guests);
        $rows = [];
        foreach ($rooms as $r) {
          $rows[] = [['data' => ['#plain_text' => $r['type_name'] . ' · ' . $r['code']]], ['data' => ['#plain_text' => $r['capacity'] . ' huéspedes']], ['data' => ['#plain_text' => HotelService::money((int) $r['estimated_price'])]], ['data' => Link::fromTextAndUrl('Solicitar reserva de prueba', Url::fromRoute('roma_booking.request', [], ['query' => ['room' => $r['id'], 'arrival' => $q->get('arrival'), 'departure' => $q->get('departure'), 'guests' => $guests]]))->toRenderable()]];
        }
        $form['results'] = ['#type' => 'table', '#caption' => 'Resultados de demostración · ' . HotelService::date($q->get('arrival')) . ' al ' . HotelService::date($q->get('departure')), '#header' => ['Habitación', 'Capacidad', 'Estimado de la estadía', 'Solicitud'], '#rows' => $rows, '#empty' => 'No hay habitaciones disponibles en la demo para esa ocupación y esas fechas.'];
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
