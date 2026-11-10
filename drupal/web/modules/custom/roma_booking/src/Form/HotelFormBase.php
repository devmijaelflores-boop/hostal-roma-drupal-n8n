<?php
namespace Drupal\roma_booking\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\roma_booking\Service\HotelService;
use Symfony\Component\DependencyInjection\ContainerInterface;

abstract class HotelFormBase extends FormBase {
  public function __construct(protected readonly HotelService $hotel) {}
  public static function create(ContainerInterface $container): static { return new static($container->get('roma_booking.hotel')); }
  protected function decorate(array &$form): void {
    $form['#cache']['max-age'] = 0;
    $form['#attached']['library'][] = 'roma_booking/hotel';
  }
  protected function protect(array &$form): void {
    $this->decorate($form);
    $session = $this->getRequest()->getSession();
    $session->start();
    // Drupal descarta sesiones anónimas vacías; conserva un marcador sin PII.
    $session->set('roma_public_forms', TRUE);
    $form['_roma_csrf'] = ['#type' => 'hidden', '#default_value' => \Drupal::csrfToken()->get($this->getFormId())];
    $form['website'] = ['#type' => 'textfield', '#title' => $this->t('Sitio web (dejar vacío)'), '#maxlength' => 100, '#attributes' => ['autocomplete' => 'off', 'tabindex' => '-1'], '#wrapper_attributes' => ['class' => ['visually-hidden']]];
  }
  protected function guard(array &$form, FormStateInterface $form_state, string $event, int $limit = 10): bool {
    if (!\Drupal::csrfToken()->validate((string) $form_state->getValue('_roma_csrf'), $this->getFormId())) {
      $form_state->setErrorByName('_roma_csrf', $this->t('La sesión del formulario no es válida. Recarga la página e inténtalo de nuevo.')); return FALSE;
    }
    if ($form_state->getValue('website')) { $form_state->setErrorByName('website', $this->t('No se pudo procesar el formulario.')); return FALSE; }
    $id = hash('sha256', ($this->getRequest()->getClientIp() ?? 'unknown') . \Drupal::service('private_key')->get());
    $flood = \Drupal::service('flood');
    if (!$flood->isAllowed($event, $limit, 900, $id)) { $form_state->setErrorByName('_roma_csrf', $this->t('Se alcanzó el límite de intentos. Vuelve a intentarlo en 15 minutos.')); return FALSE; }
    $flood->register($event, 900, $id);
    return TRUE;
  }
}
