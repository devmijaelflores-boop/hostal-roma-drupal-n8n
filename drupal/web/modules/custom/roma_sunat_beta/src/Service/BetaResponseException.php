<?php
namespace Drupal\roma_sunat_beta\Service;

/** Conserva una respuesta acotada sin interpretarla como aceptación. */
final class BetaResponseException extends \RuntimeException {
  public function __construct(string $message,public readonly string $responseZip){parent::__construct($message);}
}
