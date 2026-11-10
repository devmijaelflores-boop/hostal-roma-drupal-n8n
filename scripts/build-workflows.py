#!/usr/bin/env python3
"""Produce flujos importables sin secretos, compartidos por la instalación y Git."""
import json
from pathlib import Path
root=Path(__file__).resolve().parents[1]
out=root/'n8n/workflows';out.mkdir(parents=True,exist_ok=True)
def http(endpoint, body='={{ JSON.stringify($json.body) }}'):
    return {'parameters':{'method':'POST','url':'http://drupal/api/roma/v1/'+endpoint,'authentication':'genericCredentialType','genericAuthType':'httpHeaderAuth','sendBody':True,'specifyBody':'json','jsonBody':body,'options':{'response':{'response':{'neverError':True}}}},'id':'drupal','name':'Consultar Drupal','type':'n8n-nodes-base.httpRequest','typeVersion':4.2,'position':[480,300],'credentials':{'httpHeaderAuth':{'id':'RomaDrupalApi01','name':'ROMA Drupal API'}}}
def webhook(name,path,endpoint):
    return {'id':name,'name':{'RomaChatDemo01':'ROMA · Chat sin IA','RomaReserve001':'ROMA · Solicitudes autenticadas'}[name], 'nodes':[
      {'parameters':{'httpMethod':'POST','path':path,'authentication':'headerAuth','responseMode':'responseNode','options':{}},'id':'webhook','name':'Entrada segura','type':'n8n-nodes-base.webhook','typeVersion':2,'position':[240,300],'webhookId':path,'credentials':{'httpHeaderAuth':{'id':'RomaWebhookAuth1','name':'ROMA Webhook privado'}}},http(endpoint),
      {'parameters':{'respondWith':'json','responseBody':'={{ $json }}','options':{}},'id':'response','name':'Responder','type':'n8n-nodes-base.respondToWebhook','typeVersion':1.4,'position':[720,300]}],
      'connections':{'Entrada segura':{'main':[[{'node':'Consultar Drupal','type':'main','index':0}]]},'Consultar Drupal':{'main':[[{'node':'Responder','type':'main','index':0}]]}},'settings':{'executionOrder':'v1','saveDataSuccessExecution':'none','saveDataErrorExecution':'none','saveManualExecutions':False,'timezone':'America/Lima'},'active':False}
for name,path,endpoint in [('RomaChatDemo01','roma-chat','assistant'),('RomaReserve001','roma-reservation','reservations')]:
 (out/(path+'.json')).write_text(json.dumps(webhook(name,path,endpoint),indent=2,ensure_ascii=False)+'\n')
schedule={'id':'RomaAutomate01','name':'ROMA · Avisos y recordatorios locales','nodes':[
 {'parameters':{'rule':{'interval':[{'field':'minutes','minutesInterval':1}]}},'id':'schedule','name':'Cada minuto','type':'n8n-nodes-base.scheduleTrigger','typeVersion':1.2,'position':[240,300]},
 http('automation/tick','={{ JSON.stringify({}) }}')], 'connections':{'Cada minuto':{'main':[[{'node':'Consultar Drupal','type':'main','index':0}]]}},'settings':{'executionOrder':'v1','timezone':'America/Lima','saveDataSuccessExecution':'none','saveDataErrorExecution':'none'},'active':False}
manual=webhook('RomaChatDemo01','roma-automation','automation/tick')['nodes'][0]
schedule['nodes'].insert(1,manual)
schedule['nodes'].append({'parameters':{'respondWith':'json','responseBody':'={{ $json }}','options':{}},'id':'response','name':'Responder','type':'n8n-nodes-base.respondToWebhook','typeVersion':1.4,'position':[720,300]})
schedule['connections']['Entrada segura']={'main':[[{'node':'Consultar Drupal','type':'main','index':0}]]}
schedule['nodes'][0]['name']='Cada minuto'
scheduled_http=http('automation/tick','={{ JSON.stringify({}) }}');scheduled_http['id']='scheduled';scheduled_http['name']='Procesar cola programada';scheduled_http['position']=[480,80];schedule['nodes'].append(scheduled_http)
schedule['connections']['Cada minuto']={'main':[[{'node':'Procesar cola programada','type':'main','index':0}]]}
schedule['connections']['Consultar Drupal']={'main':[[{'node':'Responder','type':'main','index':0}]]}
(out/'roma-automation.json').write_text(json.dumps(schedule,indent=2,ensure_ascii=False)+'\n')
print('Tres workflows JSON generados sin secretos.')
