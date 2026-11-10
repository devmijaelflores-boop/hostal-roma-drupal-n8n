#!/usr/bin/env python3
"""Prueba CSRF anónimo real y ausencia de escrituras ante un token manipulado."""
from datetime import date, timedelta
from html.parser import HTMLParser
from http.cookiejar import CookieJar
from pathlib import Path
import subprocess
from urllib.parse import urlencode
from urllib.request import build_opener, HTTPCookieProcessor

root = Path(__file__).resolve().parents[1]
class Hidden(HTMLParser):
    def __init__(self): super().__init__(); self.fields = {}
    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'input' and attrs.get('type') == 'hidden': self.fields[attrs['name']] = attrs.get('value','')
def count():
    result = subprocess.run(['docker','compose','exec','-T','drupal','vendor/bin/drush','php:eval',r'echo \Drupal::database()->select("roma_reservation","b")->countQuery()->execute()->fetchField();'],cwd=root,capture_output=True,text=True,check=True)
    return int(result.stdout.strip())
start = count()
arrival=(date.today()+timedelta(days=45)).isoformat();departure=(date.today()+timedelta(days=46)).isoformat()
url='http://localhost:8080/reservar?'+urlencode({'room':1,'arrival':arrival,'departure':departure,'guests':1})
opener=build_opener(HTTPCookieProcessor(CookieJar()))
with opener.open(url) as response:
    parser=Hidden();parser.feed(response.read().decode())
fields=parser.fields|{'_roma_csrf':'MANIPULADO','room_id':'1','arrival':arrival,'departure':departure,'guests':'1','customer_name':'Cliente ficticio CSRF','email':'csrf@example.invalid','phone':'','demo_consent':'1','website':''}
with opener.open(url,data=urlencode(fields).encode()) as response:
    text=response.read().decode()
assert 'La sesión del formulario no es válida' in text
assert count()==start
print('CSRF manipulado rechazado sin crear una reserva. OK')
