#!/usr/bin/env python3
"""Reserva por webhook privado n8n y comprobación de idempotencia."""
from pathlib import Path
from datetime import date,timedelta
from urllib.request import Request,urlopen
from urllib.error import HTTPError
import json,secrets,subprocess
root=Path(__file__).resolve().parents[1]
env=dict(line.split('=',1) for line in (root/'.env').read_text().splitlines() if line and not line.startswith('#') and '=' in line)
start=date.today()+timedelta(days=135);end=start+timedelta(days=1)
with urlopen(env['ROMA_PUBLIC_URL']+f'/api/roma/v1/availability?arrival={start}&departure={end}&guests=1') as response:data=json.load(response)
room=data['rooms'][0]['room_id'];expected=data['rooms'][0]['estimated_amount_cents']
payload={'arrival':str(start),'departure':str(end),'guests':1,'room_id':room,'customer_name':'Cliente ficticio webhook','email':'workflow@example.invalid','demo_consent':True,'request_key':secrets.token_hex(24)}
url='http://localhost:'+env.get('N8N_PORT','5678')+'/webhook/roma-reservation'
headers={'Content-Type':'application/json','X-ROMA-Webhook':env['ROMA_N8N_WEBHOOK_TOKEN']}
with urlopen(Request(url,data=json.dumps(payload).encode(),headers=headers),timeout=30) as response:data=json.load(response)
receipt=data['reservation']
try:
 assert receipt['status']=='pending' and receipt['estimated_price']==expected
 print('OK Webhook n8n registra una solicitud pendiente en Drupal')
 with urlopen(Request(url,data=json.dumps(payload).encode(),headers=headers),timeout=30) as response:duplicate=json.load(response)
 assert 'error' in duplicate and 'procesado' in duplicate['error']
 print('OK Reenvío por n8n no duplica solicitud')
finally:
 identifier=int(receipt['id'])
 code=r'$db=\Drupal::database();foreach(["roma_notification"=>"reservation_id","roma_audit"=>"reservation_id","roma_reservation"=>"id"] as $table=>$column){$db->delete($table)->condition($column,'+str(identifier)+r')->execute();}'
 subprocess.run(['docker','compose','exec','-T','drupal','vendor/bin/drush','php:eval',code],cwd=root,check=True,capture_output=True)
