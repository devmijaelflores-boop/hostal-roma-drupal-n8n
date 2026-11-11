<?php
namespace Drupal\roma_finance\Controller;
use Drupal\Core\Controller\ControllerBase;
use Drupal\Core\Link;
use Drupal\Core\Url;
use Drupal\roma_booking\Service\HotelService;
use Drupal\roma_finance\Service\LedgerService;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class ReceiptController extends ControllerBase {
  public function view(string $entry):array{
    try{$e=\Drupal::service('roma_finance.ledger')->entry((int)$entry);$account=\Drupal::service('roma_finance.ledger')->account((int)$e['reservation_id']);}catch(\DomainException){throw new NotFoundHttpException();}
    $cell=static fn(string $s):array=>['data'=>['#plain_text'=>$s]];
    $date=(new \DateTimeImmutable('@'.$e['created']))->setTimezone(new \DateTimeZone('America/Lima'))->format('d/m/Y H:i');
    $reversed=in_array((int)$e['id'],$account['reversed'],TRUE);
    return ['#cache'=>['max-age'=>0],'#attached'=>['library'=>['roma_finance/finance']],
      'notice'=>['#markup'=>'<p class="finance-notice"><strong>SIMULACIÓN ACADÉMICA · SIN VALIDEZ TRIBUTARIA.</strong> No es factura, boleta ni comprobante SUNAT. No acredita un pago real.</p>'],
      'table'=>['#type'=>'table','#header'=>['Dato','Valor'],'#rows'=>[
        [$cell('Movimiento'),$cell($e['reference'])],[$cell('Solicitud'),$cell($account['booking']['reference'])],[$cell('Tipo'),$cell(LedgerService::KINDS[$e['kind']])],[$cell('Fecha Perú'),$cell($date)],[$cell('Importe PEN (simulado)'),$cell(HotelService::money((int)$e['amount']))],[$cell('Medio'),$cell(LedgerService::METHODS[$e['method']]??'No corresponde')],[$cell('Motivo'),$cell($e['note'])],[$cell('Responsable'),$cell('Usuario #'.$e['actor_uid'])],[$cell('Estado del registro'),$cell($reversed?'Anulado mediante contrapartida':($e['kind']==='reversal'?'Contrapartida de #'.$e['reverses_id']:'Registrado'))],
      ]],
      'print'=>['#type'=>'html_tag','#tag'=>'button','#value'=>'Imprimir constancia interna','#attributes'=>['type'=>'button','class'=>['finance-print']]],
      'back'=>Link::fromTextAndUrl('Volver a la cuenta',Url::fromRoute('roma_finance.account',['reservation'=>$e['reservation_id']]))->toRenderable(),
    ];
  }
}
