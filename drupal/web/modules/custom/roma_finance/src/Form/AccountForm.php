<?php
namespace Drupal\roma_finance\Form;
use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\roma_booking\Service\HotelService;
use Drupal\roma_finance\Service\LedgerService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class AccountForm extends FormBase {
  private int $reservation;
  public function getFormId(): string { return 'roma_finance_account'; }
  private function cell(string $value): array {return ['data'=>['#plain_text'=>$value]];}
  public function buildForm(array $form,FormStateInterface $form_state,string $reservation='0'): array {
    $this->reservation=(int)$reservation;$ledger=\Drupal::service('roma_finance.ledger');
    try{$account=$ledger->account($this->reservation);}catch(\DomainException){throw new NotFoundHttpException();}
    $b=$account['booking'];$form['#cache']['max-age']=0;$form['#attached']['library'][]='roma_finance/finance';
    $form['notice']=['#markup'=>'<p class="finance-notice"><strong>Simulación de cargos y pagos, sin cobro real.</strong> Esta cuenta no emite facturas ni boletas SUNAT. Registra solo datos de ensayo. El pago académico de Commerce se conserva aparte y no se suma a este libro.</p>'];
    $form['back']=Link::fromTextAndUrl('Volver a cuentas y saldos',Url::fromRoute('roma_finance.overview'))->toRenderable();
    $form['booking']=['#type'=>'table','#header'=>['Dato','Valor'],'#rows'=>[
      [$this->cell('Solicitud'),$this->cell($b['reference'])],[$this->cell('Huésped'),$this->cell($b['customer_name'])],[$this->cell('Estado de reserva'),$this->cell(HotelService::STATUS[$b['status']])],[$this->cell('Estimado original de la estadía'),$this->cell(HotelService::money((int)$b['estimated_price']))],
    ]];
    $form['is_open']=['#type'=>'value','#value'=>$account['open']];
    if(\Drupal::moduleHandler()->moduleExists('roma_sunat_beta')&&$this->currentUser()->hasPermission('manage roma sunat beta')&&$account['open']&&$account['charges']>0&&$account['balance']===0&&$b['status']==='confirmed'){
      $form['beta']=Link::fromTextAndUrl('Preparar documento de prueba SUNAT beta',Url::fromRoute('roma_sunat_beta.prepare',['reservation'=>$this->reservation]))->toRenderable();
    }
    if(!$account['open']){
      $form['explanation']=['#markup'=>'<p>Abrir la cuenta guarda el estimado original como cargo inicial de simulación. No confirma la reserva, no cobra dinero y no cambia las tarifas. Abrirla nuevamente no duplica el cargo.</p>'];
      $form['actions']=['#type'=>'actions','save'=>['#type'=>'submit','#value'=>'Abrir cuenta de demostración']];
    }else{
      $balance=$account['balance'];
      $form['summary']=['#type'=>'table','#attributes'=>['class'=>['finance-summary']],'#header'=>['Concepto','PEN (simulado)'],'#rows'=>[
        [$this->cell('Cargos netos'),$this->cell(HotelService::money($account['charges']))],[$this->cell('Pagado neto'),$this->cell(HotelService::money($account['collected']))],[$this->cell($balance<0?'Saldo a favor del huésped':'Saldo pendiente'),$this->cell(HotelService::money(abs($balance)))],
      ]];
      $rows=[];
      foreach($account['entries'] as $entry){
        $actions=[Link::fromTextAndUrl('Constancia interna',Url::fromRoute('roma_finance.receipt',['entry'=>$entry['id']]))->toRenderable()];
        if($entry['kind']!=='reversal'&&!in_array((int)$entry['id'],$account['reversed'],TRUE)){$actions[]=Link::fromTextAndUrl('Anular con motivo',Url::fromRoute('roma_finance.reverse',['entry'=>$entry['id']]))->toRenderable();}
        $date=(new \DateTimeImmutable('@'.$entry['created']))->setTimezone(new \DateTimeZone('America/Lima'))->format('d/m/Y H:i');
        $rows[]=[$this->cell($entry['reference']),$this->cell($date),$this->cell(LedgerService::KINDS[$entry['kind']].($entry['reverses_id']?' · corrige #'.$entry['reverses_id']:'')),$this->cell(HotelService::money((int)$entry['amount'])),$this->cell(LedgerService::METHODS[$entry['method']]??'—'),$this->cell($entry['note']),$this->cell('Usuario #'.$entry['actor_uid']),['data'=>['#theme'=>'item_list','#items'=>$actions]]];
      }
      $form['entries']=['#type'=>'table','#caption'=>'Historial íntegro: las anulaciones añaden una contrapartida y no borran el original','#header'=>['Movimiento','Fecha Perú','Tipo','Importe','Medio','Motivo','Responsable','Acción'],'#rows'=>$rows];
      $form['kind']=['#type'=>'select','#title'=>'Movimiento','#options'=>array_intersect_key(LedgerService::KINDS,array_flip(['charge','discount','payment','refund'])),'#required'=>TRUE];
      $form['amount']=['#type'=>'textfield','#title'=>'Importe en PEN','#description'=>'Mayor que cero, hasta dos decimales. Ejemplo: 50.00. Sin separador de miles.','#required'=>TRUE,'#attributes'=>['inputmode'=>'decimal']];
      $form['method']=['#type'=>'select','#title'=>'Medio simulado','#options'=>[''=>'No corresponde a cargos o descuentos']+LedgerService::METHODS];
      $form['note']=['#type'=>'textarea','#title'=>'Motivo del movimiento','#required'=>TRUE,'#maxlength'=>500,'#description'=>'Entre 5 y 500 caracteres. No incluyas números de tarjeta ni claves bancarias.'];
      if(!$form_state->get('operation_key'))$form_state->set('operation_key',bin2hex(random_bytes(24)));
      $form['operation_key']=['#type'=>'hidden','#value'=>$form_state->get('operation_key')];
      $form['actions']=['#type'=>'actions','save'=>['#type'=>'submit','#value'=>'Registrar movimiento simulado']];
    }
    $form['consent']=['#type'=>'checkbox','#title'=>'Entiendo que esta operación es una simulación y que no se moverá dinero.','#required'=>TRUE,'#weight'=>90];$form['actions']['#weight']=100;
    return $form;
  }
  public function validateForm(array &$form,FormStateInterface $form_state): void {
    if($form_state->getValue('is_open')){
      try{LedgerService::cents((string)$form_state->getValue('amount'));}catch(\DomainException $e){$form_state->setErrorByName('amount',$e->getMessage());}
      if(mb_strlen(trim((string)$form_state->getValue('note')))<5)$form_state->setErrorByName('note','Explica el movimiento con al menos 5 caracteres.');
      if(in_array($form_state->getValue('kind'),['payment','refund'],TRUE)&&!isset(LedgerService::METHODS[$form_state->getValue('method')]))$form_state->setErrorByName('method','Selecciona un medio simulado para el pago o devolución.');
    }
  }
  public function submitForm(array &$form,FormStateInterface $form_state): void {
    $ledger=\Drupal::service('roma_finance.ledger');$actor=(int)$this->currentUser()->id();
    try{
      if(!$form_state->getValue('is_open'))$ledger->open($this->reservation,$actor);
      else $ledger->record($this->reservation,(string)$form_state->getValue('kind'),LedgerService::cents((string)$form_state->getValue('amount')),(string)$form_state->getValue('note'),(string)$form_state->getValue('method'),(string)$form_state->getValue('operation_key'),$actor);
      $this->messenger()->addStatus('Operación de demostración registrada. No se movió dinero.');$form_state->setRedirect('roma_finance.account',['reservation'=>$this->reservation]);
    }catch(\DomainException $e){$this->messenger()->addError($e->getMessage());$form_state->setRebuild();}
  }
}
