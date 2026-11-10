#!/usr/bin/env python3
"""Crea secretos locales sin imprimirlos ni sobrescribir .env."""
from pathlib import Path
import secrets

root = Path(__file__).resolve().parents[1]
target = root / '.env'
content = (root / '.env.example').read_text()
lines = []
for line in content.splitlines():
    if line.endswith('=CHANGE_ME'):
        line = line.split('=', 1)[0] + '=' + secrets.token_hex(32)
    lines.append(line)
try:
    with open(target, 'x', opener=lambda path, flags: __import__('os').open(path, flags, 0o600)) as output:
        output.write('\n'.join(lines) + '\n')
except FileExistsError:
    existing = target.read_text()
    present = {line.split('=', 1)[0] for line in existing.splitlines() if line and not line.startswith('#') and '=' in line}
    additions = [line for line in lines if '=' in line and not line.startswith('#') and line.split('=',1)[0] not in present]
    if additions:
        with target.open('a') as output:
            output.write('\n' + '\n'.join(additions) + '\n')
        target.chmod(0o600)
        print('Se añadieron variables nuevas; los secretos existentes se conservaron.')
    else:
        print('.env ya contiene todas las variables; no se modificó.')
    raise SystemExit(0)
print('.env creado con permisos 600. No compartir ni agregar a Git.')
