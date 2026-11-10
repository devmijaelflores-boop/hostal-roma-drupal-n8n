#!/usr/bin/env python3
"""Contrato y seguridad de la API contra Drupal/MariaDB reales."""
from datetime import date, timedelta
import json
from pathlib import Path
import subprocess
from urllib.error import HTTPError
from urllib.request import Request, urlopen

root = Path(__file__).resolve().parents[1]
env = dict(line.split('=', 1) for line in (root / '.env').read_text().splitlines() if line and not line.startswith('#') and '=' in line)
base = env.get('ROMA_PUBLIC_URL', 'http://localhost:8080') + '/api/roma/v1/'
results = []
def call(path, body=None, headers=None, raw=None):
    data = raw if raw is not None else json.dumps(body).encode() if body is not None else None
    h = {'Accept':'application/json', **({'Content-Type':'application/json'} if data is not None else {}), **(headers or {})}
    try:
        with urlopen(Request(base+path, data=data, headers=h), timeout=30) as r: return r.status, json.loads(r.read())
    except HTTPError as e:
        raw=e.read()
        try: value=json.loads(raw)
        except ValueError: value={}
        return e.code,value

def check(ok,label):
    assert ok,label
    results.append(label); print('OK',label)

auth={'Authorization':'Bearer '+env['ROMA_INTEGRATION_TOKEN']}
code,data=call('catalog');check(code==200 and data['demo'] and len(data['types'])>=3,'Catálogo público de datos de demostración')
check('email' not in json.dumps(data) and 'phone' not in json.dumps(data),'Catálogo sin datos personales')
check(call('catalog',headers={'Origin':'https://otro.example'})[0]==403,'Origen ajeno rechazado')
check(call('availability?arrival=2027-02-30&departure=2027-03-05&guests=1')[0]==422,'Fecha inexistente rechazada')
start=date.today()+timedelta(days=125);end=start+timedelta(days=2)
query=f'availability?arrival={start}&departure={end}&guests=1'
code,data=call(query);check(code==200 and data['rooms'],'Disponibilidad desde MariaDB')
room=data['rooms'][0]['room_id'];expected=data['rooms'][0]['estimated_amount_cents']
payload={'arrival':str(start),'departure':str(end),'guests':1,'room_id':room,'customer_name':'Cliente ficticio API','email':'api@example.invalid','demo_consent':True,'status':'confirmed','estimated_price':1}
check(call('reservations',payload)[0] in (401,403),'Escritura anónima rechazada')
check(call('reservations',payload,{'Authorization':'Bearer incorrecto'})[0] in (401,403),'Token incorrecto rechazado')
check(call('reservations',headers=auth,raw=b'{invalid')[0]==400,'JSON inválido rechazado')
check(call('reservations',{'room_id':[1]},auth)[0]==400,'Tipos complejos rechazados')
code,data=call('reservations',payload,auth);check(code==201,'Solicitud autenticada creada')
receipt=data['reservation'];check(receipt['status']=='pending' and receipt['estimated_price']==expected,'Estado y precio manipulados ignorados')
try:
    check(call('reservation-status',{'reference':receipt['reference']},auth)[0]==404,'Consulta sin clave rechazada')
    code,data=call('reservation-status',{'reference':receipt['reference']},auth|{'X-Reservation-Key':receipt['token']})
    check(code==200 and data['reservation']['status']=='pending','Consulta autorizada funciona')
    check(not {'email','phone','customer_name','token_hash'} & data['reservation'].keys(),'Consulta privada sin PII')
    check(call('reservations')[0]==405,'Listado público de clientes no existe')
finally:
    identifier=int(receipt['id'])
    subprocess.run(['docker','compose','exec','-T','drupal','vendor/bin/drush','php:eval',r'$db=\Drupal::database();$db->delete("roma_audit")->condition("reservation_id",'+str(identifier)+r')->execute();$db->delete("roma_reservation")->condition("id",'+str(identifier)+r')->execute();$db->delete("roma_notification")->condition("reservation_id",'+str(identifier)+r')->execute();'],cwd=root,check=True,capture_output=True)
(root/'docs/verificacion-api.json').write_text(json.dumps({'results':results},indent=2,ensure_ascii=False)+'\n')
