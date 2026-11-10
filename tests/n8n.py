#!/usr/bin/env python3
"""Integración real del chat y webhooks seguros, sin guardar claves en logs."""
import json, time, subprocess
from pathlib import Path
from urllib.request import Request, urlopen, build_opener, HTTPCookieProcessor
from urllib.error import HTTPError
from http.cookiejar import CookieJar
root=Path(__file__).resolve().parents[1]
env=dict(line.split('=',1) for line in (root/'.env').read_text().splitlines() if line and not line.startswith('#') and '=' in line)
base=env.get('ROMA_PUBLIC_URL','http://localhost:8080');opener=build_opener(HTTPCookieProcessor(CookieJar()))
results=[]
def check(ok,label):assert ok,label;results.append(label);print('OK',label)
def req(url,body=None,headers=None,client=None):
    h={'Content-Type':'application/json',**(headers or {})};data=json.dumps(body).encode() if body is not None else None
    try:
        with (client or opener).open(Request(url,data=data,headers=h),timeout=40) as r:return r.status,json.loads(r.read())
    except HTTPError as e:return e.code,{}
code,session=req(base+'/api/roma/chat/session');check(code==200 and bool(session['csrf']),'Sesión de chat creada')
headers={'X-ROMA-CSRF':session['csrf'],'Origin':base}
check(req(base+'/api/roma/chat',{'chatInput':'precios'})[0]==403,'Chat sin CSRF rechazado')
check(req(base+'/api/roma/chat',{'chatInput':'precios'},headers|{'Origin':'https://ajeno.invalid'})[0]==403,'Chat con origen ajeno rechazado')
for text,expected in [('¿Cuánto cuesta una habitación individual?','S/'),('¿Tienen habitaciones matrimoniales?','Matrimonial'),('¿Qué servicios ofrecen?','Wi-Fi'),('¿Dónde están ubicados?','Nueva Zelandia'),('Quiero solicitar una reserva','/reservar'),('Consultar mi reserva confirmada','/mi-reserva'),('Disponibilidad del 20/12/2027 al 22/12/2027 para 2 personas','Disponibilidad de demostración')]:
    code,data=req(base+'/api/roma/chat',{'action':'sendMessage','chatInput':text,'metadata':{'ignored':'fixture'}},headers)
    check(code==200 and not data.get('unavailable') and expected in data['output'],'Respuesta n8n→Drupal: '+text)
code,data=req(base+'/api/roma/chat',{'chatInput':'¿Cómo uso el carrito de la tienda?','action':'sendMessage'},headers)
check(code==200 and '/tienda' in data.get('output','') and '/carrito' in data['output'],'El chatbot n8n orienta hacia la tienda y el checkout')
check(req('http://localhost:'+env.get('N8N_PORT','5678')+'/webhook/roma-chat',{'chatInput':'precios'})[0] in (401,403),'Webhook n8n rechaza llamadas sin credencial')
# Los precios deben cambiar al editar MariaDB; no basta con una respuesta fija del bot.
def php(code):
    return subprocess.run(['docker','compose','exec','-T','drupal','vendor/bin/drush','php:eval',code],cwd=root,capture_output=True,text=True,check=True).stdout.strip()
original=int(php(r'echo \Drupal::database()->select("roma_rate","p")->fields("p",["amount"])->condition("type_id",1)->execute()->fetchField();'))
try:
    php(r'\Drupal::database()->update("roma_rate")->fields(["amount"=>9317])->condition("type_id",1)->execute();')
    code,data=req(base+'/api/roma/chat',{'chatInput':'precio individual','action':'sendMessage'},headers)
    check(code==200 and '93.17' in data['output'],'El chat refleja una tarifa modificada en MariaDB')
finally:
    php(r'\Drupal::database()->update("roma_rate")->fields(["amount"=>'+str(original)+r'])->condition("type_id",1)->execute();\Drupal::service("cache_tags.invalidator")->invalidateTags(["roma_catalog"]);')
(root/'docs/verificacion-n8n.json').write_text(json.dumps({'results':results},indent=2,ensure_ascii=False)+'\n')
