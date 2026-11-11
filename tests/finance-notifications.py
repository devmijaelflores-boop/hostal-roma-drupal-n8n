#!/usr/bin/env python3
"""Comprueba avisos del libro usando el workflow n8n y SMTP Mailpit reales."""
import json,subprocess,secrets,time
from pathlib import Path
from urllib.request import Request,urlopen
root=Path(__file__).resolve().parents[1]
env=dict(s.split('=',1) for s in (root/'.env').read_text().splitlines() if s and not s.startswith('#') and '=' in s)
def php(code):
 r=subprocess.run(['docker','compose','exec','-T','drupal','vendor/bin/drush','php:eval',code],cwd=root,text=True,capture_output=True,check=True)
 return json.loads(r.stdout)
def http(url,body=None,headers=None):
 data=None if body is None else json.dumps(body).encode()
 with urlopen(Request(url,data=data,headers={'Content-Type':'application/json',**(headers or {})}),timeout=40) as r:return json.load(r)
fixture=php(r"$h=\Drupal::service('roma_booking.hotel');$a=(new \DateTimeImmutable($h->today()))->modify('+155 days')->format('Y-m-d');$d=(new \DateTimeImmutable($a))->modify('+1 day')->format('Y-m-d');$rooms=$h->availability($a,$d,1);$r=$h->request(['room_id'=>$rooms[0]['id'],'arrival'=>$a,'departure'=>$d,'guests'=>1,'customer_name'=>'Ensayo aviso financiero','email'=>'finance-mail@example.invalid']);$h->transition($r['id'],'confirmed',1);$l=\Drupal::service('roma_finance.ledger');$l->open($r['id'],1);$k=bin2hex(random_bytes(24));$l->record($r['id'],'payment',1000,'Adelanto para aviso de ensayo','cash_demo',$k,1);$l->record($r['id'],'payment',1000,'Adelanto para aviso de ensayo','cash_demo',$k,1);echo json_encode(['id'=>$r['id'],'references'=>array_column($l->entries($r['id']),'reference')]);")
identifier=int(fixture['id'])
def tick():return http('http://localhost:'+env.get('N8N_PORT','5678')+'/webhook/roma-automation',{}, {'X-ROMA-Webhook':env['ROMA_N8N_WEBHOOK_TOKEN']})
def matched():
 data=http('http://localhost:'+env.get('MAILPIT_PORT','8025')+'/api/v1/messages')
 return [row for row in data.get('messages',[]) if any(ref in row.get('Subject','') for ref in fixture['references'])]
try:
 for attempt in range(6):
  tick()
  rows=php(f"echo json_encode(\\Drupal::database()->select('roma_notification','n')->fields('n',['state'])->condition('reservation_id',{identifier})->condition('kind','finance')->execute()->fetchCol());")
  if rows==['delivered','delivered']:break
  time.sleep(1)
 assert rows==['delivered','delivered'],rows
 print('OK Workflow n8n entrega los dos avisos financieros a Mailpit')
 messages=matched();assert len(messages)==2
 for message in messages:
  assert all(recipient['Address']=='admin@example.invalid' for recipient in message['To'])
  detail=http('http://localhost:'+env.get('MAILPIT_PORT','8025')+'/api/v1/message/'+message['ID'])
  assert 'No se realizó un cobro real' in detail['Text'] and 'No es un comprobante SUNAT' in detail['Text']
 print('OK Avisos al administrador identifican simulación y ausencia de comprobante SUNAT')
 tick();assert len(matched())==2
 print('OK Reejecutar el workflow no duplica avisos ya entregados normalmente')
finally:
 php(f"$db=\\Drupal::database();$db->delete('roma_finance_entry')->condition('reservation_id',{identifier})->execute();$db->delete('roma_finance_account')->condition('reservation_id',{identifier})->execute();foreach(['roma_audit','roma_notification'] as $t){{$db->delete($t)->condition('reservation_id',{identifier})->execute();}}$db->delete('roma_reservation')->condition('id',{identifier})->execute();echo 'true';")
