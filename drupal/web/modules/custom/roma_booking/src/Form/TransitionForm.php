<?php
namespace Drupal\roma_booking\Form;

use Drupal\Core\Form\ConfirmFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class TransitionForm extends ConfirmFormBase {
  private array $reservation;
  private string $action;
  public function getFormId(): string { return 'roma_transition_form'; }
  public function buildForm(array $form, FormStateInterface $form_state, string $id = '0', string $action = 'cancel'): array {
    try { $this->reservation = \Drupal::service('roma_booking.hotel')->reservation((int) $id); }
    catch (\DomainException) { throw new NotFoundHttpException(); }
    $this->action = $action;
    $form = parent::buildForm($form, $form_state);
    $form['#cache']['max-age'] = 0;
    return $form;
  }
  public function getQuestion() { return $this->t('¿@action la solicitud de prueba @reference?', ['@action' => $this->action === 'confirm' ? 'Confirmar' : 'Cancelar', '@reference' => $this->reservation['reference']]); }
  public function getDescription() { return $this->t('Esta acción cambia el estado en la demo. Confirmar comprueba nuevamente capacidad, fechas y superposición. No envía confirmaciones al negocio real ni realiza cobros.'); }
  public function getCancelUrl(): Url { return Url::fromRoute('roma_booking.reservations'); }
  public function getConfirmText() { return $this->t('Aplicar cambio de estado'); }
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    try { \Drupal::service('roma_booking.hotel')->transition((int) $this->reservation['id'], $this->action === 'confirm' ? 'confirmed' : 'cancelled', (int) $this->currentUser()->id()); $this->messenger()->addStatus('Estado actualizado en la base de datos de demostración.'); }
    catch (\DomainException $error) { $this->messenger()->addError($error->getMessage()); }
    $form_state->setRedirect('roma_booking.reservations');
  }
}
