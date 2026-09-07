#!/bin/bash
# Corre el scheduler de Laravel dentro del contenedor, una vez por minuto.
#
# SIN esto no hay deteccion de faltas: `turnos:revisar-cobertura` corre cada 5
# minutos y es lo que descubre que un puesto quedo vacio. El otro comando,
# `turnos:cerrar-dia`, corre a las 23:55; enterarse a esa hora de que el puesto
# de las 06:00 quedo vacio no sirve para cubrirlo.
#
# Instalar en el crontab del usuario dueño del repo (no de root, que no esta en
# el grupo docker ni es dueño de storage/):
#
#   * * * * * /ruta/al/backend/scripts/schedule-run.sh >> /tmp/ts-schedule.log 2>&1
#
# Rutas absolutas a proposito: cron corre con un PATH minimo y `docker` a secas
# no se resuelve.
set -euo pipefail

DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

# -T porque cron no tiene TTY; -u 1000 para que los archivos que escriba Laravel
# (logs, cache) queden del dueño del repo y no de root.
exec /usr/bin/docker compose --project-directory "$DIR" \
    exec -T -u 1000 backend php artisan schedule:run
