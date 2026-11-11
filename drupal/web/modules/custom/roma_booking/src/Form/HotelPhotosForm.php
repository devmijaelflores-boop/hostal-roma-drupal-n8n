<?php
namespace Drupal\roma_booking\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\file\Entity\File;

/** Fotos administradas con validación de imagen y permisos de catálogo. */
final class HotelPhotosForm extends ConfigFormBase {
  public function getFormId(): string { return 'roma_hotel_photos_form'; }
  protected function getEditableConfigNames(): array { return ['roma_booking.photos']; }
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config=$this->config('roma_booking.photos');
    $form['#tree']=TRUE;
    $form['intro']=['#markup'=>'<p>Sube fotografías propias o autorizadas. Selecciona hasta tres por galería, de un máximo de 2 MB cada una. Las fotos de habitación deben corresponder a esa categoría. Si no cargas imágenes, se conservará una imagen ilustrativa del catálogo.</p>'];
    $groups=['hero'=>'Portada'];
    foreach (\Drupal::service('roma_booking.hotel')->types(FALSE) as $type) { $groups['room_'.$type['id']]='Habitación: '.$type['name']; }
    foreach ($groups as $key=>$title) {
      $form[$key]=['#type'=>'details','#title'=>$title,'#open'=>$key==='hero'];
      $form[$key]['files']=['#type'=>'managed_file','#title'=>'Fotografías','#multiple'=>TRUE,'#upload_location'=>'public://hotel-photos/','#default_value'=>$config->get('galleries.'.$key.'.files')??[], '#upload_validators'=>['FileExtension'=>['extensions'=>'jpg jpeg png webp'],'FileIsImage'=>[],'FileImageDimensions'=>['maxDimensions'=>'1920x1920'],'FileSizeLimit'=>['fileLimit'=>2097152]]];
      $form[$key]['alt']=['#type'=>'textfield','#title'=>'Descripción accesible','#maxlength'=>180,'#description'=>'Describe lo que se ve. Por ejemplo: habitación doble con dos camas junto a la ventana.','#default_value'=>$config->get('galleries.'.$key.'.alt')??''];
    }
    $form_state->set('photo_groups',array_keys($groups));
    return parent::buildForm($form,$form_state);
  }
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    parent::validateForm($form,$form_state);
    foreach ($form_state->get('photo_groups') as $key) {
      $value=(array)$form_state->getValue($key);
      if (count($value['files']??[])>3) { $form_state->setErrorByName($key.'][files','Selecciona un máximo de tres imágenes por galería.'); }
      if (!empty($value['files']) && trim($value['alt']??'')==='') { $form_state->setErrorByName($key.'][alt','Describe las fotografías para quienes usan un lector de pantalla.'); }
    }
  }
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $config=$this->config('roma_booking.photos');$old=$config->get('galleries')??[];$galleries=[];
    foreach ($form_state->get('photo_groups') as $key) {
      $value=(array)$form_state->getValue($key);$galleries[$key]=['files'=>array_values(array_map('intval',$value['files']??[])),'alt'=>trim($value['alt']??'')];
    }
    $flatten=static function(array $data): array { $ids=[];foreach($data as $g){$ids=array_merge($ids,$g['files']??[]);}return array_unique($ids); };
    $old_ids=$flatten($old);$new_ids=$flatten($galleries);$usage=\Drupal::service('file.usage');
    foreach (File::loadMultiple(array_diff($new_ids,$old_ids)) as $file) { $file->setPermanent();$file->save();$usage->add($file,'roma_booking','hotel_photos',1); }
    $config->set('galleries',$galleries)->save();
    foreach (File::loadMultiple(array_diff($old_ids,$new_ids)) as $file) { $usage->delete($file,'roma_booking','hotel_photos',1); }
    \Drupal::service('cache_tags.invalidator')->invalidateTags(['roma_catalog']);
    parent::submitForm($form,$form_state);
  }
}
