<?php
/** Prueba real opcional y de bajo volumen: no forma parte de verify-all. */
if(getenv('ROMA_ALLOW_BETA_LIVE')!=='1')throw new \RuntimeException('Habilita ROMA_ALLOW_BETA_LIVE=1 solo para una prueba manual beta.');
$type=getenv('ROMA_BETA_TEST_TYPE')?:'01';if(!in_array($type,['01','03'],TRUE))throw new \RuntimeException('Tipo beta no válido.');
$h=\Drupal::service('roma_booking.hotel');$ledger=\Drupal::service('roma_finance.ledger');$service=\Drupal::service('roma_sunat_beta.documents');
$a=(new \DateTimeImmutable($h->today()))->modify('+120 days')->format('Y-m-d');$d=(new \DateTimeImmutable($a))->modify('+1 day')->format('Y-m-d');$rooms=$h->availability($a,$d,1);
if(!$rooms)throw new \RuntimeException('No hay una habitación demo para preparar el ejercicio.');
$r=$h->request(['room_id'=>$rooms[0]['id'],'arrival'=>$a,'departure'=>$d,'guests'=>1,'customer_name'=>'EJERCICIO ACADEMICO SUNAT BETA','email'=>'sunat-beta@example.invalid']);
$h->transition($r['id'],'confirmed',1);$ledger->open($r['id'],1);$ledger->record($r['id'],'payment',$r['estimated_price'],'Saldo simulado para prueba estructural UBL','cash_demo',bin2hex(random_bytes(24)),1);
$doc=$service->prepare($r['id'],$type,bin2hex(random_bytes(24)),1);$doc=$service->send($doc['id'],1);
echo json_encode(['document_id'=>$doc['id'],'type'=>$doc['type'],'number'=>$doc['series'].'-'.$doc['number'],'mode'=>$doc['mode'],'state'=>$doc['state'],'code'=>$doc['code'],'description'=>$doc['description'],'xml_sha256'=>$doc['xml_hash'],'cdr_sha256'=>$doc['cdr_hash'],'has_cdr'=>(bool)$doc['cdr'],'legal_validity'=>FALSE,'data'=>'Identidades genéricas, certificado académico y cuenta de demostración.'],JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT).PHP_EOL;
