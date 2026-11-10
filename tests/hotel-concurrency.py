#!/usr/bin/env python3
"""Dos procesos Drupal intentan confirmar reservas superpuestas en MariaDB."""
import concurrent.futures
import json
from pathlib import Path
import subprocess

root = Path(__file__).resolve().parents[1]

def php(code):
    result = subprocess.run(['docker', 'compose', 'exec', '-T', 'drupal', 'vendor/bin/drush', 'php:eval', code], cwd=root, capture_output=True, text=True, check=True)
    return result.stdout.strip()

fixture = json.loads(php(r'''
$h=\Drupal::service('roma_booking.hotel');
$t=$h->saveItem('type',0,['name'=>'QA concurrencia '.bin2hex(random_bytes(3)),'capacity'=>2,'price'=>'90.00','image'=>'individual.jpg','active'=>1]);
$r=$h->saveItem('room',0,['code'=>'QA-RACE-'.bin2hex(random_bytes(3)),'type_id'=>$t,'active'=>1]);
$a=(new \DateTimeImmutable($h->today()))->modify('+90 days')->format('Y-m-d');
$d=(new \DateTimeImmutable($a))->modify('+2 days')->format('Y-m-d');
$data=['room_id'=>$r,'arrival'=>$a,'departure'=>$d,'guests'=>2,'customer_name'=>'Cliente ficticio concurrencia','email'=>'concurrency@example.invalid'];
$x=$h->request($data);$y=$h->request($data);echo json_encode(['type'=>$t,'room'=>$r,'ids'=>[$x['id'],$y['id']]]);
'''))
try:
    def confirm(identifier):
        return php(r"try { \Drupal::service('roma_booking.hotel')->transition(" + str(identifier) + r",'confirmed',1);echo 'CONFIRMED'; } catch (\DomainException $e) {echo 'BLOCKED';}")
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        results = list(pool.map(confirm, fixture['ids']))
    assert sorted(results) == ['BLOCKED', 'CONFIRMED'], results
    count = php(r"echo \Drupal::database()->select('roma_reservation','b')->condition('room_id'," + str(fixture['room']) + r")->condition('status','confirmed')->countQuery()->execute()->fetchField();")
    assert count == '1', count
    print('Confirmaciones simultáneas: exactamente una confirmada y otra rechazada. OK')
finally:
    ids = ','.join(str(int(x)) for x in fixture['ids'])
    php(r"$db=\Drupal::database();$ids=["+ids+r"]; $db->delete('roma_audit')->condition('reservation_id',$ids,'IN')->execute();$db->delete('roma_reservation')->condition('id',$ids,'IN')->execute();if($db->schema()->tableExists('roma_notification')){$db->delete('roma_notification')->condition('reservation_id',$ids,'IN')->execute();}$db->delete('roma_room')->condition('id',"+str(fixture['room'])+r")->execute();$db->delete('roma_rate')->condition('type_id',"+str(fixture['type'])+r")->execute();$db->delete('roma_type_service')->condition('type_id',"+str(fixture['type'])+r")->execute();$db->delete('roma_room_type')->condition('id',"+str(fixture['type'])+r")->execute();\Drupal::service('cache_tags.invalidator')->invalidateTags(['roma_catalog']);")
