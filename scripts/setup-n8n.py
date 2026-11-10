#!/usr/bin/env python3
"""Importa credenciales por stdin y publica únicamente los flujos ROMA."""
import json, subprocess
from pathlib import Path
root=Path(__file__).resolve().parents[1]
env=dict(line.split('=',1) for line in (root/'.env').read_text().splitlines() if line and not line.startswith('#') and '=' in line)
def compose(*args,input=None):
    subprocess.run(['docker','compose',*args],cwd=root,input=input,text=True,check=True)
creds=[{'id':'RomaDrupalApi01','name':'ROMA Drupal API','type':'httpHeaderAuth','data':{'name':'Authorization','value':'Bearer '+env['ROMA_INTEGRATION_TOKEN']}}, {'id':'RomaWebhookAuth1','name':'ROMA Webhook privado','type':'httpHeaderAuth','data':{'name':'X-ROMA-Webhook','value':env['ROMA_N8N_WEBHOOK_TOKEN']}}]
# El archivo transitorio vive en el contenedor con modo 600 y se elimina incluso si falla.
try:
    compose('exec','-T','n8n','node','-e',"const fs=require('fs');let s='';process.stdin.on('data',d=>s+=d);process.stdin.on('end',()=>fs.writeFileSync('/tmp/roma-credentials.json',s,{mode:0o600}));",input=json.dumps(creds))
    compose('exec','-T','n8n','n8n','import:credentials','--input=/tmp/roma-credentials.json')
finally:
    compose('exec','-T','n8n','node','-e',"require('fs').rmSync('/tmp/roma-credentials.json',{force:true})")
for path in sorted((root/'n8n/workflows').glob('roma-*.json')):
    workflow=json.loads(path.read_text())
    compose('cp',str(path),'n8n:/tmp/roma-workflow.json')
    compose('exec','-T','n8n','n8n','import:workflow','--input=/tmp/roma-workflow.json')
    compose('exec','-T','n8n','n8n','publish:workflow','--id='+workflow['id'])
compose('restart','n8n')
compose('up','-d','--wait','n8n')
print('Workflows ROMA importados y publicados. Sin correo externo: avisos solo en Mailpit local.')
