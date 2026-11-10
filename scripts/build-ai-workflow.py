#!/usr/bin/env python3
"""Alternativa opcional: IA clasifica intención; Drupal genera la respuesta factual."""
import json
from pathlib import Path
root=Path(__file__).resolve().parents[1]
flow=json.loads((root/'n8n/workflows/roma-chat.json').read_text())
flow['id']='RomaChatAiOpt01';flow['name']='ROMA · IA opcional (requiere credencial OpenAI)'
entry,drupal,response=flow['nodes'];entry['parameters']['path']='roma-chat-ai';entry['webhookId']='roma-chat-ai'
intent_schema={'type':'object','properties':{'intent':{'type':'string','enum':['prices','services','location','availability','reservation','status','payment','other']}},'required':['intent'],'additionalProperties':False}
request={'model':'gpt-4o-mini','store':False,'instructions':'Clasifica únicamente la intención de la consulta hotelera. No generes tarifas, fechas, confirmaciones, ni texto para el cliente.','text':{'format':{'type':'json_schema','name':'hotel_intent','schema':intent_schema,'strict':True}},'max_output_tokens':100}
body="={{ JSON.stringify({ ..."+json.dumps(request,ensure_ascii=False)+", input: String($json.body.chatInput || '').slice(0,1000) }) }}"
openai={'parameters':{'method':'POST','url':'https://api.openai.com/v1/responses','authentication':'predefinedCredentialType','nodeCredentialType':'openAiApi','sendBody':True,'specifyBody':'json','jsonBody':body,'options':{'response':{'response':{'neverError':True}}}},'id':'optional-openai','name':'Clasificar intención (opcional)','type':'n8n-nodes-base.httpRequest','typeVersion':4.2,'position':[450,300],'credentials':{'openAiApi':{'id':'CONFIGURE_OPENAI','name':'Configurar credencial OpenAI de prueba'}}}
drupal['position']=[700,300];response['position']=[940,300]
drupal['parameters']['jsonBody']="={{ JSON.stringify({chatInput: $('Entrada segura').first().json.body.chatInput, intent: (()=>{try{return JSON.parse($json.output.find(o=>o.type==='message').content.find(c=>c.type==='output_text').text).intent}catch{return 'other'}})()}) }}"
flow['nodes']=[entry,openai,drupal,response]
flow['connections']={'Entrada segura':{'main':[[{'node':'Clasificar intención (opcional)','type':'main','index':0}]]},'Clasificar intención (opcional)':{'main':[[{'node':'Consultar Drupal','type':'main','index':0}]]},'Consultar Drupal':{'main':[[{'node':'Responder','type':'main','index':0}]]}}
out=root/'n8n/workflows/optional';out.mkdir(exist_ok=True)
(out/'chat-openai-optional.json').write_text(json.dumps(flow,indent=2,ensure_ascii=False)+'\n')
print('Workflow opcional generado, desactivado y sin credenciales.')
