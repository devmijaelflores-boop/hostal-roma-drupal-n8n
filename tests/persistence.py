#!/usr/bin/env python3
"""Recrea contenedores sin eliminar volúmenes y compara datos persistidos."""
from pathlib import Path
import subprocess

root = Path(__file__).resolve().parents[1]


def compose(*args, capture=False):
    result = subprocess.run(['docker', 'compose', *args], cwd=root, check=True,
                            text=True, stdout=subprocess.PIPE if capture else None)
    return result.stdout.strip() if capture else None


def snapshot():
    drupal = compose('exec', '-T', 'drupal', 'vendor/bin/drush', 'php:eval',
                     r'echo json_encode([\Drupal::config("system.site")->get("uuid"),'
                     r'\Drupal::entityQuery("node")->accessCheck(FALSE)->condition("type", "page")->execute()]);',
                     capture=True)
    n8n = compose('exec', '-T', 'n8n', 'node', '-e',
                  'const {DatabaseSync}=require("node:sqlite");'
                  'const db=new DatabaseSync("/home/node/.n8n/database.sqlite",{readOnly:true});'
                  'const users=db.prepare("SELECT id FROM user ORDER BY id").all();'
                  'if(!users.length)process.exit(1);'
                  'console.log(require("crypto").createHash("sha256").update(JSON.stringify(users)).digest("hex"));'
                  'db.close();', capture=True)
    return drupal, n8n


before = snapshot()
compose('exec', '-T', 'drupal', 'php', '-r',
        'if(file_put_contents("web/sites/default/files/.roma-persistence-check","roma-test")===false)exit(1);')
compose('down')
compose('up', '-d', '--wait')
assert snapshot() == before, 'Los datos de Drupal o SQLite cambiaron al recrear contenedores'
compose('exec', '-T', 'drupal', 'php', '-r',
        '$p="web/sites/default/files/.roma-persistence-check";'
        'if(file_get_contents($p)!=="roma-test")exit(1);unlink($p);')
print('Persistencia de MariaDB, archivos Drupal y SQLite n8n tras down/up: OK')
