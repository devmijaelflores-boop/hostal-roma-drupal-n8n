<?php
$hotel=\Drupal::service('roma_booking.hotel');$payments=\Drupal::service('roma_payments.demo');$db=\Drupal::database();$type=0;$room=0;$id=0;$checks=0;
$check=static function(bool $ok,string $label)use(&$checks){if(!$ok)throw new \RuntimeException($label);echo 'OK '.$label."\n";$checks++;};
try {
 $type=$hotel->saveItem('type',0,['name'=>'QA pagos','capacity'=>1,'price'=>'87.13','image'=>'individual.jpg','active'=>1]);
 $room=$hotel->saveItem('room',0,['code'=>'QA-PAY-'.bin2hex(random_bytes(3)),'type_id'=>$type,'active'=>1]);
 $arrival=(new \DateTimeImmutable($hotel->today()))->modify('+150 days')->format('Y-m-d');$departure=(new \DateTimeImmutable($arrival))->modify('+2 days')->format('Y-m-d');
 $r=$hotel->request(['customer_name'=>'Cliente ficticio pagos','email'=>'payments@example.invalid','room_id'=>$room,'arrival'=>$arrival,'departure'=>$departure,'guests'=>1]);$id=$r['id'];
 try{$payments->record($r['reference'],$r['token'],'demo_approved',bin2hex(random_bytes(24)));$check(FALSE,'Pendiente rechazada');}catch(\DomainException){$check(TRUE,'No se simula pago de pendiente');}
 $hotel->transition($id,'confirmed',1);
 try{$payments->record($r['reference'],'incorrecta','demo_approved',bin2hex(random_bytes(24)));$check(FALSE,'Clave rechazada');}catch(\DomainException){$check(TRUE,'Pago exige clave privada correcta');}
 $declined=$payments->record($r['reference'],$r['token'],'demo_declined',bin2hex(random_bytes(24)));$check($declined['outcome']==='demo_declined','Rechazo simulado registrado');
 $approved=$payments->record($r['reference'],$r['token'],'demo_approved',bin2hex(random_bytes(24)));$check($approved['amount']===17426,'Importe de pago obtenido del servidor');
 $duplicate=$payments->record($r['reference'],$r['token'],'demo_approved',bin2hex(random_bytes(24)));$check($duplicate['receipt']===$approved['receipt'],'Doble aprobación no duplica registro');
 $hotel->transition($id,'cancelled',1);
 try{$payments->record($r['reference'],$r['token'],'demo_approved',bin2hex(random_bytes(24)));$check(FALSE,'Cancelada rechazada');}catch(\DomainException){$check(TRUE,'Cancelada no admite pago');}
 echo "TOTAL $checks pruebas de pago ficticio\n";
}finally{
 if($id){$db->delete('roma_demo_payment')->condition('reservation_id',$id)->execute();$db->delete('roma_audit')->condition('reservation_id',$id)->execute();$db->delete('roma_notification')->condition('reservation_id',$id)->execute();$db->delete('roma_reservation')->condition('id',$id)->execute();}
 if($room)$db->delete('roma_room')->condition('id',$room)->execute();
 if($type){$db->delete('roma_type_service')->condition('type_id',$type)->execute();$db->delete('roma_rate')->condition('type_id',$type)->execute();$db->delete('roma_room_type')->condition('id',$type)->execute();}
}
