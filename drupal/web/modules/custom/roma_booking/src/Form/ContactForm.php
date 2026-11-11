<?php
namespace Drupal\roma_booking\Form;

use Drupal\Core\Form\FormStateInterface;

final class ContactForm extends HotelFormBase {
  public function getFormId(): string { return 'roma_contact_form'; }
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $this->protect($form);
    if ($form_state->get('sent')) { $form['receipt'] = ['#plain_text' => 'Mensaje de prueba guardado. Puede revisarlo el administrador en el panel. No se ha enviado correo al establecimiento real.']; return $form; }
    $form['intro'] = ['#markup' => '<p>Este formulario guarda mensajes de demostración para el profesor o administrador del proyecto. No contacta con el hostal real. Utiliza datos ficticios.</p>'];
    $form['name'] = ['#type' => 'textfield', '#title' => 'Nombre completo', '#required' => TRUE, '#maxlength' => 100];
    $form['email'] = ['#type' => 'email', '#title' => 'Correo electrónico', '#required' => TRUE, '#maxlength' => 254];
    $form['message'] = ['#type' => 'textarea', '#title' => 'Mensaje', '#required' => TRUE, '#maxlength' => 2000, '#description' => 'Entre 10 y 2000 caracteres.'];
    $form['demo_consent'] = ['#type' => 'checkbox', '#title' => 'Uso datos ficticios y entiendo que el mensaje no se envía al negocio real.', '#required' => TRUE];
    $form['actions'] = ['#type' => 'actions', 'submit' => ['#type' => 'submit', '#value' => 'Enviar mensaje']];
    return $form;
  }
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    if (!$this->guard($form, $form_state, 'roma.contact', 5)) { return; }
    if (mb_strlen(trim((string) $form_state->getValue('name'))) < 2 || mb_strlen(trim((string) $form_state->getValue('message'))) < 10) { $form_state->setErrorByName('message', 'Introduce un nombre válido y un mensaje de al menos 10 caracteres.'); }
  }
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try { $this->hotel->contact($form_state->getValues()); $form_state->set('sent', TRUE); }
    catch (\DomainException $error) { $this->messenger()->addError($error->getMessage()); }
    $form_state->setRebuild(TRUE);
  }
}
