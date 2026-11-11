<?php
namespace Drupal\roma_booking\Form;
use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
final class HotelContentForm extends ConfigFormBase {
  public function getFormId(): string { return 'roma_hotel_content_form'; }
  protected function getEditableConfigNames(): array { return ['roma_booking.content']; }
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config=$this->config('roma_booking.content');
    $defaults=['story_title'=>'Nuestra historia','story_text'=>'','hotel_name'=>'ROMA','brand_subtitle'=>'GOLDEN · JULIACA','city'=>'Juliaca','region'=>'Puno','country'=>'Perú','presentation'=>'Entre nuevos caminos y días por descubrir, siempre hay espacio para una pausa. Esta propuesta reúne ambientes acogedores y una experiencia de reservas de prueba.', 'location_text'=>'Juliaca, en Puno, Perú, es la ciudad de referencia de este proyecto académico independiente.', 'address'=>'Nueva Zelandia 196, Juliaca 21101, Puno, Perú'];
    foreach (['story_title'=>'Título de la historia','story_text'=>'Historia del hotel','hotel_name'=>'Nombre del hotel','brand_subtitle'=>'Subtítulo de la marca','city'=>'Ciudad','region'=>'Región','country'=>'País','presentation'=>'Presentación del proyecto','location_text'=>'Descripción de ubicación','address'=>'Dirección de referencia'] as $key=>$label) { $form[$key]=['#type'=>in_array($key,['presentation','location_text','story_text'],TRUE)?'textarea':'textfield','#title'=>$label,'#required'=>$key!=='story_text','#default_value'=>$config->get($key) ?? $defaults[$key], '#maxlength'=>$key==='address'?200:1500]; }
    $form['story_text']['#description']='Cuenta hechos reales y verificables del establecimiento. Si lo dejas vacío, la web mostrará los pasos de reserva en lugar de una historia de ejemplo.';
    $form['whatsapp']=['#type'=>'tel','#title'=>'WhatsApp de atención','#description'=>'Número internacional, por ejemplo +51902420412. Vacío para ocultar el botón.','#default_value'=>$config->get('whatsapp') ?? '', '#maxlength'=>16];
    $form['notice']=['#markup'=>'<p>Texto sin HTML, escapado al mostrarlo. Mantén el carácter académico. Las reservas siguen en modo de prueba.</p>'];
    return parent::buildForm($form,$form_state);
  }
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form,$form_state);
    $number=trim((string)$form_state->getValue('whatsapp'));
    if ($number!=='' && !preg_match('/^\+?[1-9][0-9]{7,14}$/D',$number)) { $form_state->setErrorByName('whatsapp','Escribe un número internacional válido, sin espacios.'); }
  }
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config=$this->config('roma_booking.content');
    foreach (['story_title','story_text','hotel_name','brand_subtitle','city','region','country','presentation','location_text','address'] as $key) { $config->set($key,trim((string)$form_state->getValue($key))); }
    $config->set('whatsapp',trim((string)$form_state->getValue('whatsapp')));
    $config->save(); \Drupal::configFactory()->getEditable('system.site')->set('name',trim((string)$form_state->getValue('hotel_name')) . '')->save(); \Drupal::service('cache_tags.invalidator')->invalidateTags(['roma_catalog']);
    parent::submitForm($form,$form_state);
  }
}
