<?php
namespace Drupal\roma_finance\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\roma_booking\Service\HotelService;

final class OverviewForm extends FormBase {
  public function getFormId(): string { return 'roma_finance_overview'; }
  private function cell(string $value): array { return ['data'=>['#plain_text'=>$value]]; }
  public function buildForm(array $form,FormStateInterface $form_state): array {
    $h=\Drupal::service('roma_booking.hotel');$ledger=\Drupal::service('roma_finance.ledger');$today=$h->today();
    $from=(string)$this->getRequest()->query->get('from',substr($today,0,7).'-01');$to=(string)$this->getRequest()->query->get('to',$today);
    try { $report=$ledger->overview($from,$to); }catch(\DomainException $e){$this->messenger()->addError($e->getMessage());$from=$today;$to=$today;$report=$ledger->overview($from,$to);}
    $form['#cache']['max-age']=0;$form['#attached']['library'][]='roma_finance/finance';
    $form['notice']=['#markup'=>'<p class="finance-notice"><strong>Libro de demostración: sin dinero real.</strong> Los registros no cobran tarjetas ni acreditan ingresos del hotel. Los pagos de prueba de Commerce no se importan como cobros de esta cuenta.</p>'];
    $form['back']=Link::fromTextAndUrl('Volver al panel hotelero',Url::fromRoute('roma_booking.dashboard'))->toRenderable();
    $form['filter']=['#type'=>'fieldset','#title'=>'Movimientos por periodo (hora de Perú)'];
    foreach(['from'=>['Desde',$from],'to'=>['Hasta (incluido)',$to]] as $key=>[$title,$value]){$form['filter'][$key]=['#type'=>'date','#title'=>$title,'#default_value'=>$value,'#required'=>TRUE];}
    $form['filter']['submit']=['#type'=>'submit','#value'=>'Consultar periodo'];
    $form['totals']=['#type'=>'table','#caption'=>'Movimientos del periodo, no recaudación real','#header'=>['Indicador','Valor'],'#rows'=>[
      [$this->cell('Variación neta de cargos'),$this->cell(HotelService::money($report['charges']))],
      [$this->cell('Pagos menos devoluciones y anulaciones simuladas'),$this->cell(HotelService::money($report['collected']))],
      [$this->cell('Movimientos registrados'),$this->cell((string)$report['count'])],
    ]];
    $rows=[];
    foreach($report['accounts'] as $account){$b=$account['booking'];$room=$h->room((int)$b['room_id']);$rows[]=[
      $this->cell($b['reference']),$this->cell($room['code']),$this->cell(HotelService::STATUS[$b['status']]),$this->cell(HotelService::money((int)$b['estimated_price'])),
      $this->cell($account['open']?HotelService::money($account['charges']):'Sin abrir'),$this->cell($account['open']?HotelService::money($account['collected']):'—'),$this->cell($account['open']?HotelService::money($account['balance']):'—'),
      ['data'=>Link::fromTextAndUrl('Ver cuenta',Url::fromRoute('roma_finance.account',['reservation'=>$b['id']]))->toRenderable()],
    ];}
    $form['accounts']=['#type'=>'table','#caption'=>'Últimas 100 solicitudes: saldos actuales independientes del periodo del reporte','#header'=>['Solicitud','Habitación','Reserva','Estimado original','Cargos netos','Pagado neto (simulado)','Saldo (simulado)','Acción'],'#rows'=>$rows,'#empty'=>'Todavía no hay solicitudes.'];
    return $form;
  }
  public function validateForm(array &$form,FormStateInterface $form_state): void {try{\Drupal::service('roma_finance.ledger')->overview((string)$form_state->getValue('from'),(string)$form_state->getValue('to'));}catch(\DomainException $e){$form_state->setErrorByName('from',$e->getMessage());}}
  public function submitForm(array &$form,FormStateInterface $form_state): void {$form_state->setRedirect('roma_finance.overview',[],['query'=>['from'=>$form_state->getValue('from'),'to'=>$form_state->getValue('to')]]);}
}
