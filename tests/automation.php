<?php
// Prueba avisos, recordatorio de mañana y no duplicación con captura SMTP local.
$h=\Drupal::service('roma_booking.hotel');$a=\Drupal::service('roma_api.automation');$db=\Drupal::database();$type=0;$room=0;$id=0;
try {
 $type=$h->saveItem('type',0,['name'=>'QA avisos','capacity'=>1,'price'=>'91.00','image'=>'individual.jpg','active'=>1]);
 $room=$h->saveItem('room',0,['code'=>'QA-MAIL-'.bin2hex(random_bytes(3)),'type_id'=>$type,'active'=>1]);
 $arrival=(new \DateTimeImmutable($h->today()))->modify('+1 day')->format('Y-m-d');$departure=(new \DateTimeImmutable($arrival))->modify('+1 day')->format('Y-m-d');
 $r=$h->request(['customer_name'=>'Cliente ficticio avisos','email'=>'mail-test@example.invalid','room_id'=>$room,'arrival'=>$arrival,'departure'=>$departure,'guests'=>1]);$id=$r['id'];$h->transition($id,'confirmed',1);
 $a->tick();
 $count=(int)$db->select('roma_notification','n')->condition('reservation_id',$id)->condition('state','delivered')->countQuery()->execute()->fetchField();
 if($count!==3)throw new \RuntimeException('Se esperaban avisos pending, confirmed y reminder capturados. Observado: '.$count);
 $a->tick();$again=(int)$db->select('roma_notification','n')->condition('reservation_id',$id)->countQuery()->execute()->fetchField();
 if($again!==3)throw new \RuntimeException('Los avisos se duplicaron.');
 $h->transition($id,'cancelled',1);$a->tick();
 $count=(int)$db->select('roma_notification','n')->condition('reservation_id',$id)->condition('state','delivered')->countQuery()->execute()->fetchField();
 if($count!==4)throw new \RuntimeException('Falta aviso de cancelación.');
 echo "OK captura local de solicitud, confirmación, recordatorio y cancelación; sin duplicación al repetir.\n";
}finally{
 if($id){$db->delete('roma_audit')->condition('reservation_id',$id)->execute();$db->delete('roma_notification')->condition('reservation_id',$id)->execute();$db->delete('roma_reservation')->condition('id',$id)->execute();}
 if($room)$db->delete('roma_room')->condition('id',$room)->execute();
 if($type){$db->delete('roma_type_service')->condition('type_id',$type)->execute();$db->delete('roma_rate')->condition('type_id',$type)->execute();$db->delete('roma_room_type')->condition('id',$type)->execute();}
}
