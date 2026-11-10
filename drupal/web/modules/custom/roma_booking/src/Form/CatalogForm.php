<?php
namespace Drupal\roma_booking\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class CatalogForm extends HotelFormBase {
  public function getFormId(): string { return 'roma_catalog_form'; }
  public function buildForm(array $form, FormStateInterface $form_state, string $kind = 'room', string $id = '0'): array {
    $this->decorate($form); $form_state->set('kind', $kind); $form_state->set('item_id', (int) $id);
    try { $item = (int) $id ? $this->hotel->item($kind, (int) $id) : []; }
    catch (\DomainException) { throw new NotFoundHttpException(); }
    $form['intro'] = ['#markup' => '<p>Catálogo ficticio del proyecto académico. Desactivar conserva los registros y las solicitudes existentes. Las nuevas tarifas no cambian los estimados ya registrados.</p>'];
    if ($kind === 'room') {
      $types = []; foreach ($this->hotel->types(FALSE) as $type) { $types[$type['id']] = $type['name']; }
      $form['code'] = ['#type' => 'textfield', '#title' => 'Código de habitación', '#required' => TRUE, '#maxlength' => 30, '#default_value' => $item['code'] ?? ''];
      $form['type_id'] = ['#type' => 'select', '#title' => 'Tipo de habitación', '#options' => $types, '#required' => TRUE, '#default_value' => $item['type_id'] ?? ''];
    }
    else {
      $form['name'] = ['#type' => 'textfield', '#title' => 'Nombre', '#required' => TRUE, '#maxlength' => $kind === 'type' ? 80 : 100, '#default_value' => $item['name'] ?? ''];
      $form['description'] = ['#type' => 'textarea', '#title' => 'Descripción de demostración', '#maxlength' => 1000, '#default_value' => $item['description'] ?? ''];
      if ($kind === 'type') {
        $form['capacity'] = ['#type' => 'number', '#title' => 'Capacidad máxima', '#min' => 1, '#max' => 10, '#step' => 1, '#required' => TRUE, '#default_value' => $item['capacity'] ?? 1];
        $form['price'] = ['#type' => 'textfield', '#title' => 'Tarifa ficticia por noche (PEN)', '#description' => 'Importe de 1.00 a 99999.99, con dos decimales como máximo.', '#required' => TRUE, '#default_value' => isset($item['amount']) ? number_format($item['amount'] / 100, 2, '.', '') : '85.00'];
        $form['category'] = ['#type' => 'textfield', '#title' => 'Subtítulo de la tarjeta', '#maxlength' => 100, '#default_value' => $item['category'] ?? 'CATÁLOGO DE DEMOSTRACIÓN'];
        $form['image'] = ['#type' => 'select', '#title' => 'Fotografía referencial', '#options' => ['individual.jpg' => 'Interior claro', 'matrimonial.jpg' => 'Interior matrimonial', 'doble.jpg' => 'Interior amplio'], '#default_value' => $item['image'] ?? 'individual.jpg'];
        $services = []; foreach ($this->hotel->services(FALSE) as $service) { $services[$service['id']] = $service['name']; }
        $form['services'] = ['#type' => 'checkboxes', '#title' => 'Servicios asociados', '#options' => $services, '#default_value' => $item['services'] ?? []];
      }
    }
    $form['active'] = ['#type' => 'checkbox', '#title' => 'Activo en el catálogo de demostración', '#default_value' => $item['active'] ?? 1];
    $form['actions'] = ['#type' => 'actions', 'submit' => ['#type' => 'submit', '#value' => 'Guardar cambios'], 'cancel' => ['#type' => 'link', '#title' => 'Volver al catálogo', '#url' => Url::fromRoute('roma_booking.catalog')]];
    return $form;
  }
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try { $this->hotel->saveItem($form_state->get('kind'), $form_state->get('item_id'), $form_state->getValues()); \Drupal::service('cache_tags.invalidator')->invalidateTags(['roma_catalog']); $this->messenger()->addStatus('Catálogo actualizado.'); $form_state->setRedirect('roma_booking.catalog'); }
    catch (\DomainException $error) { $this->messenger()->addError($error->getMessage()); $form_state->setRebuild(TRUE); }
  }
}
