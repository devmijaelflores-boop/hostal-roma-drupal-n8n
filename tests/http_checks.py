#!/usr/bin/env python3
"""Comprueba acceso HTTP real y login Drupal sin imprimir credenciales."""
from html.parser import HTMLParser
from http.cookiejar import CookieJar
from pathlib import Path
import urllib.error
import urllib.parse
import urllib.request


class LoginForm(HTMLParser):
    def __init__(self):
        super().__init__()
        self.fields = {}

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        if tag == 'input' and attrs.get('type') == 'hidden' and attrs.get('name'):
            self.fields[attrs['name']] = attrs.get('value', '')


root = Path(__file__).resolve().parents[1]
env = dict(line.split('=', 1) for line in (root / '.env').read_text().splitlines()
           if line and not line.startswith('#') and '=' in line)
drupal = 'http://localhost:' + env.get('DRUPAL_PORT', '8080')
n8n = 'http://localhost:' + env.get('N8N_PORT', '5678')
opener = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(CookieJar()))


def get(url):
    return opener.open(url, timeout=30)


with get(drupal) as response:
    assert response.status == 200
    assert 'Prototipo académico no oficial. Habitaciones, disponibilidad y tarifas de demostración' in response.read().decode()
print('HTTP portada y aviso académico: OK')

try:
    with get(drupal + '/admin/reports/status') as response:
        assert '/user/login' in response.url, 'Administración accesible sin login'
except urllib.error.HTTPError as error:
    assert error.code == 403, f'Acceso anónimo inesperado: {error.code}'
print('Administración protegida ante acceso anónimo: OK')

with get(drupal + '/user/login') as response:
    form = LoginForm()
    form.feed(response.read().decode())
assert form.fields.get('form_id') == 'user_login_form'
form.fields.update(name=env['DRUPAL_ADMIN_USER'], pass_=env['DRUPAL_ADMIN_PASSWORD'])
form.fields['pass'] = form.fields.pop('pass_')
payload = urllib.parse.urlencode(form.fields).encode()
with opener.open(drupal + '/user/login', data=payload, timeout=30) as response:
    assert response.status == 200
with get(drupal + '/admin/reports/status') as response:
    assert response.status == 200 and '/user/login' not in response.url
print('Login administrador y acceso al informe de estado: OK')

with get(n8n + '/healthz/readiness') as response:
    assert response.status == 200
with get(n8n) as response:
    assert response.status == 200 and 'n8n' in response.read().decode().lower()
print('n8n listo e interfaz HTTP accesible: OK')
