<?php
namespace Drupal\roma_sunat_beta\Service;

/** Verifica el tamaño y el contenido del ZIP antes de que el SDK lo procese. */
final class BetaSoapClient extends \Greenter\Ws\Services\SoapClient {
  public function call($function,$arguments) {
    if($function!=='sendBill')throw new \LogicException('Solo está habilitada la prueba sendBill.');
    $response=parent::call($function,$arguments);
    if(!empty($response->applicationResponse)){
      $zip=(string)$response->applicationResponse;
      try{BetaGateway::validateZip($zip);}catch(\RuntimeException $e){throw new BetaResponseException($e->getMessage(),strlen($zip)<=2097152?$zip:'');}
    }
    return $response;
  }
}
