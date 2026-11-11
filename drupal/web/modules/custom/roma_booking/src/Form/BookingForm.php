<?php
namespace Drupal\roma_booking\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\roma_booking\Service\HotelService;

final class BookingForm extends HotelFormBase {
  public function getFormId(): string { return 'roma_booking_form'; }
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $this->protect($form);
    if ($receipt = $form_state->get('receipt')) {
      $form['receipt'] = ['#type' => 'container', '#attributes' => ['class' => ['hotel-receipt']], 'title' => ['#markup' => '<h2>Solicitud de prueba registrada</h2>'], 'reference' => ['#type' => 'item', '#title' => 'Código de solicitud', '#plain_text' => $receipt['reference']], 'token' => ['#type' => 'item', '#title' => 'Clave privada de consulta — guárdala ahora', '#plain_text' => $receipt['token']], 'state' => ['#plain_text' => 'Estado: pendiente. Estimado: ' . HotelService::money($receipt['estimated_price']) . '. No es una reserva real ni una confirmación del establecimiento. No se ha efectuado ningún cobro.'], 'lookup' => Link::fromTextAndUrl('Consultar el estado de mi solicitud', Url::fromRoute('roma_booking.lookup'))->toRenderable()];
      return $form;
    }
    $form['steps']=['#markup'=>'<p class="booking-steps">1. Fechas → 2. Habitación → <strong>3. Datos y solicitud</strong></p>','#weight'=>-20];
    $q = $this->getRequest()->query;
    $form['intro'] = ['#markup' => '<p>Elige tu habitación y completa los datos del huésped. Tu solicitud quedará <strong>pendiente de confirmación</strong>. Este prototipo no registra reservas oficiales del establecimiento ni realiza cobros.</p>'];
    $options = [];
    foreach ($this->hotel->rooms() as $room) { $options[$room['id']] = $room['type_name'] . ' · ' . $room['code'] . ' · ' . HotelService::money((int) $room['amount']) . '/noche'; }
    $form['room_id'] = ['#type' => 'select', '#title' => 'Habitación', '#options' => $options, '#empty_option' => '- Selecciona -', '#required' => TRUE, '#default_value' => $q->get('room', '')];
    foreach (['arrival' => 'Llegada', 'departure' => 'Salida'] as $key => $label) { $form[$key] = ['#type' => 'date', '#title' => $label, '#required' => TRUE, '#default_value' => $q->get($key, ''), '#attributes' => ['min' => $this->hotel->today()]]; }
    $form['guests'] = ['#type' => 'number', '#title' => 'Huéspedes', '#min' => 1, '#max' => 10, '#step' => 1, '#required' => TRUE, '#default_value' => $q->get('guests', 1)];
    $form['customer_name'] = ['#type' => 'textfield', '#title' => 'Nombre completo', '#maxlength' => 100, '#required' => TRUE];
    $form['email'] = ['#type' => 'email', '#title' => 'Correo electrónico', '#maxlength' => 254, '#required' => TRUE];
    $form['phone'] = [
      '#type' => 'tel',
      '#title' => 'Teléfono (opcional)',
      '#maxlength' => 25,
      '#description' => 'Puedes dejarlo vacío. Si lo completas, usa números y, opcionalmente, +, espacios, paréntesis o guiones. Ejemplo ficticio: 999 123 456.',
      '#attributes' => ['inputmode' => 'tel', 'autocomplete' => 'tel'],
    ];
    $form['demo_consent'] = ['#type' => 'checkbox', '#title' => 'Confirmo que utilizo datos de prueba y entiendo que no es una reserva real.', '#required' => TRUE];
    if (!$form_state->get('request_key')) { $form_state->set('request_key', bin2hex(random_bytes(24))); }
    $form['request_key'] = ['#type' => 'hidden', '#default_value' => $form_state->get('request_key')];
    $form['actions'] = ['#type' => 'actions', 'submit' => ['#type' => 'submit', '#value' => 'Solicitar reserva']];
    return $form;
  }
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->guard($form, $form_state, 'roma.booking', 10)) { return; }
    try {
      $this->hotel->dates((string) $form_state->getValue('arrival'), (string) $form_state->getValue('departure'), (int) $form_state->getValue('guests'));
    }
    catch (\DomainException $error) {
      $arrival = (string) $form_state->getValue('arrival');
      $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $arrival);
      $field = !$date || $date->format('Y-m-d') !== $arrival || $arrival < $this->hotel->today() ? 'arrival' : 'departure';
      if ((int) $form_state->getValue('guests') < 1 || (int) $form_state->getValue('guests') > 10) { $field = 'guests'; }
      $form_state->setErrorByName($field, $error->getMessage());
    }
    try {
      $room = $this->hotel->room((int) $form_state->getValue('room_id'));
      if ((int) $form_state->getValue('guests') > (int) $room['capacity']) {
        $form_state->setErrorByName('guests', 'La cantidad de huéspedes supera la capacidad de la habitación.');
      }
    }
    catch (\DomainException $error) { $form_state->setErrorByName('room_id', $error->getMessage()); }
    if (mb_strlen(trim((string) $form_state->getValue('customer_name'))) < 2) {
      $form_state->setErrorByName('customer_name', 'El nombre debe tener al menos dos caracteres.');
    }
    $phone = trim((string) $form_state->getValue('phone'));
    $form_state->setValue('phone', $phone);
    if ($phone !== '' && !preg_match('/^\+?[0-9 ()-]{6,25}$/D', $phone)) {
      $form_state->setErrorByName('phone', 'El teléfono no admite letras. Usa de 6 a 25 caracteres: números, + al inicio, espacios, paréntesis o guiones; también puedes dejarlo vacío.');
    }
  }
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try { $receipt = $this->hotel->request($form_state->getValues()); $form_state->set('receipt', $receipt); }
    catch (\DomainException $error) { $this->messenger()->addError($error->getMessage()); }
    $form_state->setRebuild(TRUE);
  }
}
