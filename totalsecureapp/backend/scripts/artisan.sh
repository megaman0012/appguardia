#!/usr/bin/env bash
# =============================================================================
# artisan.sh — Ejecuta artisan DENTRO del contenedor, con el usuario correcto.
#
# ⚠️ **Usar esto y no `docker exec ts_backend php artisan ...` a secas.**
#
# `docker exec` entra como **root**, pero los workers de php-fpm corren como
# **uid 1000**. Cualquier comando de artisan que escriba en `storage/` deja esos
# archivos con dueño root, y a partir de ahí el worker **no puede tocarlos**:
# `touch()` sobre un archivo ajeno da «Utime failed: Operation not permitted»
# aunque los permisos de escritura estén bien, porque cambiar la fecha de un
# archivo solo lo puede hacer su dueño.
#
# Eso ya tumbó el panel entero con un 500 (2026-09-17): `view:clear` corrido como
# root dejó 64 vistas compiladas suyas, y la siguiente petición que necesitó
# recompilar una de ellas reventó. El síntoma es engañoso: el error no habla de
# permisos ni de usuarios, y aparece en páginas que no se habían tocado.
#
# Si ya pasó:
#   find storage bootstrap/cache -user root -exec chown 1000:1000 {} +
#
# Uso:  ./scripts/artisan.sh migrate --force
#       ./scripts/artisan.sh route:list
# =============================================================================

set -euo pipefail

DIR="$(cd -- "$(dirname -- "${BASH_SOURCE[0]}")/.." && pwd)"

exec /usr/bin/docker compose --project-directory "$DIR" \
    exec -T -u 1000 backend php artisan "$@"
