#!/bin/sh
# Mantiene el editor n8n y Mailpit accesibles solo por un túnel SSH local.
set -eu
if [ "$#" -ne 2 ]; then
  echo 'Uso: sh scripts/aws-tunnel.sh IP_O_HOST RUTA_LLAVE_PEM' >&2
  exit 1
fi
aws_host=$1
aws_key=$2
test -f "$aws_key" || { echo 'No se encontró la llave SSH indicada.' >&2; exit 1; }
chmod 600 "$aws_key"
exec ssh -i "$aws_key" -N -o ExitOnForwardFailure=yes \
  -o ServerAliveInterval=30 -o ServerAliveCountMax=3 \
  -L 127.0.0.1:15678:127.0.0.1:5678 \
  -L 127.0.0.1:18025:127.0.0.1:8025 "ubuntu@$aws_host"
