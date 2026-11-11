#!/usr/bin/env python3
"""Carreras reales de pagos simulados; solo crea y retira registros propios."""
from concurrent.futures import ThreadPoolExecutor
import json, secrets, subprocess
from pathlib import Path
root=Path(__file__).resolve().parents[1]
def php(code):
 result=subprocess.run(['docker','compose','exec','-T','drupal','vendor/bin/drush','php:eval',code],cwd=root,text=True,capture_output=True,check=True)
 return json.loads(result.stdout)
fixture=php(r"$h=\Drupal::service('roma_booking.hotel');$a=(new \DateTimeImmutable($h->today()))->modify('+160 days')->format('Y-m-d');$d=(new \DateTimeImmutable($a))->modify('+1 day')->format('Y-m-d');$rooms=$h->availability($a,$d,1);$r=$h->request(['room_id'=>$rooms[0]['id'],'arrival'=>$a,'departure'=>$d,'guests'=>1,'customer_name'=>'Ensayo concurrencia cuenta','email'=>'finance-race@example.invalid']);$h->transition($r['id'],'confirmed',1);\Drupal::service('roma_finance.ledger')->open($r['id'],1);echo json_encode(['id'=>$r['id'],'amount'=>$r['estimated_price']]);")
identifier=int(fixture['id']);amount=int(fixture['amount'])
def payment(key):
 return php(f"try{{$e=\\Drupal::service('roma_finance.ledger')->record({identifier},'payment',{amount},'Adelanto simultáneo ficticio','cash_demo','{key}',1);echo json_encode(['accepted'=>$e['id']]);}}catch(\\DomainException $e){{echo json_encode(['rejected'=>$e->getMessage()]);}}")
def state():
 return php(f"$a=\\Drupal::service('roma_finance.ledger')->account({identifier});echo json_encode(['paid'=>$a['collected'],'balance'=>$a['balance'],'count'=>count($a['entries'])]);")
try:
 with ThreadPoolExecutor(max_workers=2) as pool:results=list(pool.map(payment,[secrets.token_hex(24),secrets.token_hex(24)]))
 assert sum('accepted' in row for row in results)==1 and sum('rejected' in row for row in results)==1,results
 assert state()=={'paid':amount,'balance':0,'count':2}
 print('OK Dos adelantos simultáneos no sobrepagan el saldo')
 entry=next(row['accepted'] for row in results if 'accepted' in row)
 def reverse(key):
  return php(f"try{{$e=\\Drupal::service('roma_finance.ledger')->reverse({int(entry)},'Anulación simultánea de ensayo','{key}',1);echo json_encode(['accepted'=>$e['id']]);}}catch(\\DomainException $e){{echo json_encode(['rejected'=>$e->getMessage()]);}}")
 with ThreadPoolExecutor(max_workers=2) as pool:results=list(pool.map(reverse,[secrets.token_hex(24),secrets.token_hex(24)]))
 assert sum('accepted' in row for row in results)==1 and sum('rejected' in row for row in results)==1
 assert state()=={'paid':0,'balance':amount,'count':3}
 print('OK Dos anulaciones simultáneas solo crean una contrapartida')
 same=secrets.token_hex(24)
 with ThreadPoolExecutor(max_workers=2) as pool:results=list(pool.map(payment,[same,same]))
 assert results[0]['accepted']==results[1]['accepted']
 assert state()=={'paid':amount,'balance':0,'count':4}
 print('OK Reintento simultáneo con la misma clave reutiliza un movimiento')
finally:
 php(f"$db=\\Drupal::database();$db->delete('roma_finance_entry')->condition('reservation_id',{identifier})->execute();$db->delete('roma_finance_account')->condition('reservation_id',{identifier})->execute();foreach(['roma_audit','roma_notification'] as $t){{$db->delete($t)->condition('reservation_id',{identifier})->execute();}}$db->delete('roma_reservation')->condition('id',{identifier})->execute();echo 'true';")
