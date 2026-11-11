<?php
namespace Drupal\roma_reception\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\roma_booking\Service\HotelService;
use Drupal\roma_reception\Service\ReceptionService;

final class CalendarForm extends FormBase {
  public function getFormId(): string { return 'roma_reception_calendar'; }
  private function link(string $label, string $kind, int $id, string $action): array { return Link::fromTextAndUrl($label,Url::fromRoute('roma_reception.action',compact('kind','id','action')))->toRenderable(); }
  private function cell(string $text): array { return ['data'=>['#plain_text'=>$text]]; }
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $hotel=\Drupal::service('roma_booking.hotel');$ops=\Drupal::service('roma_reception.operations');$today=$hotel->today();
    $start=(string)$this->getRequest()->query->get('start',$today);$days=(int)$this->getRequest()->query->get('days',14);
    try { $data=$ops->calendar($start,$days); }
    catch (\DomainException $error) { $this->messenger()->addError($error->getMessage());$start=$today;$days=14;$data=$ops->calendar($start,$days); }
    $form['#cache']['max-age']=0;$form['#attached']['library'][]='roma_reception/reception';
    $form['intro']=['#markup'=>'<p>Recepción del prototipo académico. Los cambios se guardan en la base de datos; no representan atención oficial del establecimiento. La salida prevista es exclusiva: una estadía termina antes de la noche de salida.</p>'];
    $form['panel']=Link::fromTextAndUrl('Volver al panel hotelero',Url::fromRoute('roma_booking.dashboard'))->toRenderable();
    $form['filter']=['#type'=>'fieldset','#title'=>'Periodo del calendario'];
    $form['filter']['start']=['#type'=>'date','#title'=>'Desde','#default_value'=>$start,'#required'=>TRUE];
    $form['filter']['days']=['#type'=>'select','#title'=>'Mostrar','#options'=>[7=>'7 días',14=>'14 días',31=>'31 días'],'#default_value'=>$days];
    $form['filter']['submit']=['#type'=>'submit','#value'=>'Actualizar calendario'];
    $form['legend']=['#markup'=>'<p class="reception-legend"><span class="rc-confirmed">Confirmada</span> <span class="rc-pending">Pendiente: no bloquea</span> <span class="rc-maintenance">Mantenimiento</span> <span>— Libre en el calendario</span></p><p>La limpieza se controla para entradas de hoy. Una habitación en limpieza puede reservarse para fechas futuras. Las salidas vencidas sin registrar requieren revisión.</p>'];
    $form['maintenance']=Link::fromTextAndUrl('Programar mantenimiento',Url::fromRoute('roma_reception.block'))->toRenderable();
    $header=['Habitación / limpieza'];foreach($data['dates'] as $day) { $header[]=HotelService::date($day); }
    $rows=[];
    foreach($hotel->rooms(FALSE) as $room) {
      $id=(int)$room['id'];$state=$ops->housekeeping($id);
      $label=['#type'=>'container','title'=>['#plain_text'=>$room['code'].' · '.$room['type_name'].($room['active']?'':' · Inactiva').' · '.ReceptionService::CLEAN[$state]]];
      foreach(ReceptionService::CLEAN as $key=>$text) { if($key!==$state) { $label[$key]=$this->link($text,'room',$id,$key);$label[$key]['#prefix']='<div>';$label[$key]['#suffix']='</div>'; } }
      $row=[['data'=>$label]];
      foreach($data['dates'] as $day) {
        $items=[];$class='rc-free';
        foreach($data['reservations'] as $booking) {
          if((int)$booking['room_id']===$id && $booking['arrival']<=$day && $booking['departure']>$day) {
            $items[]=['#plain_text'=>$booking['reference'].' · '.HotelService::STATUS[$booking['status']]];
            if($booking['status']==='confirmed') { $class='rc-confirmed'; } elseif($class==='rc-free') { $class='rc-pending'; }
          }
        }
        foreach($data['blocks'] as $block) { if((int)$block['room_id']===$id && $block['arrival']<=$day && $block['departure']>$day) { $items[]=['#plain_text'=>'Mantenimiento #'.$block['id']];$class='rc-maintenance'; } }
        $row[]=['data'=>$items?['#theme'=>'item_list','#items'=>$items]:['#plain_text'=>'—'],'class'=>[$class,$day===$today?'rc-today':'']];
      }
      $rows[]=$row;
    }
    $form['calendar']=['#type'=>'container','#attributes'=>['class'=>['reception-scroll'],'tabindex'=>'0','role'=>'region','aria-label'=>'Calendario de habitaciones desplazable'], 'table'=>['#type'=>'table','#caption'=>'Inventario por noche · '.$start,'#header'=>$header,'#rows'=>$rows,'#empty'=>'Sin habitaciones.','#attributes'=>['class'=>['reception-calendar']]]];
    $rows=[];
    foreach($ops->today() as $booking) {
      $id=(int)$booking['id'];$stay=$ops->stay($id);$actions=[];
      if($stay['state']==='in_house') { $actions[]=$this->link('Registrar salida','stay',$id,'check_out'); }
      elseif($stay['state']==='not_arrived') {
        if($booking['arrival']<=$today && $booking['departure']>$today) { $actions[]=$this->link('Registrar entrada','stay',$id,'check_in'); }
        if($booking['arrival']<=$today) { $actions[]=$this->link('No se presentó','stay',$id,'no_show'); }
      }
      $room=$hotel->room((int)$booking['room_id']);
      $rows[]=[$this->cell($booking['reference']),$this->cell($booking['customer_name']),$this->cell($room['code']),$this->cell(HotelService::date($booking['arrival']).' → '.HotelService::date($booking['departure'])),$this->cell(ReceptionService::STAYS[$stay['state']]),['data'=>['#theme'=>'item_list','#items'=>$actions]]];
    }
    $form['today']=['#type'=>'table','#caption'=>'Llegadas, salidas y ocupación por resolver · '.HotelService::date($today),'#header'=>['Código','Huésped','Habitación','Fechas previstas','Recepción','Acción'],'#rows'=>$rows,'#empty'=>'No hay entradas ni salidas por atender hoy.'];
    $rows=[];
    foreach($data['blocks'] as $block) { $room=$hotel->room((int)$block['room_id']);$rows[]=[$this->cell($room['code']),$this->cell(HotelService::date($block['arrival']).' → '.HotelService::date($block['departure'])),$this->cell($block['reason']),['data'=>$this->link('Liberar','block',(int)$block['id'],'release')]]; }
    $form['blocks']=['#type'=>'table','#caption'=>'Mantenimientos del periodo','#header'=>['Habitación','Fechas','Motivo','Acción'],'#rows'=>$rows,'#empty'=>'Sin bloqueos en este periodo.'];
    $rows=[];foreach($ops->events() as $event) { $date=(new \DateTimeImmutable('@'.$event['created']))->setTimezone(new \DateTimeZone('America/Lima'))->format('d/m/Y H:i');$rows[]=[$this->cell($date),$this->cell($event['kind'].' #'.$event['entity_id']),$this->cell(ReceptionService::ACTIONS[$event['action']] ?? 'Programar mantenimiento'),$this->cell('Usuario #'.$event['actor_uid'])]; }
    $form['audit']=['#type'=>'table','#caption'=>'Últimas 20 operaciones auditadas','#header'=>['Fecha Perú','Registro','Operación','Responsable'],'#rows'=>$rows,'#empty'=>'Todavía no hay operaciones.'];
    return $form;
  }
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    try { \Drupal::service('roma_reception.operations')->calendar((string)$form_state->getValue('start'),(int)$form_state->getValue('days')); }
    catch(\DomainException $error) { $form_state->setErrorByName('start',$error->getMessage()); }
  }
  public function submitForm(array &$form, FormStateInterface $form_state): void { $form_state->setRedirect('roma_reception.calendar',[],['query'=>['start'=>$form_state->getValue('start'),'days'=>$form_state->getValue('days')]]); }
}
