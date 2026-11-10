<?php
namespace Drupal\roma_shop\Plugin\Commerce\CheckoutPane;

use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\commerce_checkout\Attribute\CommerceCheckoutPane;
use Drupal\commerce_checkout\Plugin\Commerce\CheckoutPane\CheckoutPaneBase;

#[CommerceCheckoutPane(id: 'roma_guest', label: new TranslatableMarkup('Datos del cliente de prueba'), default_step: 'order_information', wrapper_element: 'fieldset')]
final class GuestDetails extends CheckoutPaneBase {
  public function buildPaneForm(array $pane_form, FormStateInterface $form_state, array &$complete_form) {
    $guest = $this->order->getData('roma_guest',[]);
    $pane_form['name'] = ['#type'=>'textfield','#title'=>'Nombre del cliente de prueba','#required'=>TRUE,'#maxlength'=>100,'#default_value'=>$guest['name'] ?? ''];
    $pane_form['email'] = ['#type'=>'email','#title'=>'Correo electrónico de prueba','#required'=>TRUE,'#maxlength'=>254,'#default_value'=>$this->order->getEmail()];
    $pane_form['phone'] = ['#type'=>'tel','#title'=>'Teléfono de prueba (opcional)','#maxlength'=>25,'#default_value'=>$guest['phone'] ?? '','#description'=>'Puedes dejarlo vacío. Usa solo números y separadores como +, espacios, paréntesis o guiones.','#attributes'=>['inputmode'=>'tel']];
    $pane_form['consent'] = ['#type'=>'checkbox','#title'=>'Utilizo datos ficticios y entiendo que el pedido y el pago son de demostración.','#required'=>TRUE,'#default_value'=>$guest['consent'] ?? FALSE];
    return $pane_form;
  }
  public function validatePaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    if (mb_strlen(trim((string) ($values['name'] ?? ''))) < 2) { $form_state->setError($pane_form['name'],'El nombre de prueba debe tener al menos dos caracteres.'); }
    $phone = trim((string) ($values['phone'] ?? ''));
    if ($phone !== '' && !preg_match('/^\+?[0-9 ()-]{6,25}$/D',$phone)) { $form_state->setError($pane_form['phone'],'Revisa el teléfono: no admite letras. También puedes dejarlo vacío.'); }
  }
  public function submitPaneForm(array &$pane_form, FormStateInterface $form_state, array &$complete_form) {
    $values = $form_state->getValue($pane_form['#parents']);
    $this->order->setEmail(trim($values['email']));
    $this->order->setData('customer_email_overridden',TRUE);
    $this->order->setData('roma_guest',['name'=>trim($values['name']),'phone'=>trim($values['phone'] ?? ''),'consent'=>(bool) $values['consent']]);
  }
  public function buildPaneSummary() {
    return ['#plain_text'=>($this->order->getData('roma_guest',[])['name'] ?? '') . ' · ' . $this->order->getEmail() . ' · Datos ficticios'];
  }
}
