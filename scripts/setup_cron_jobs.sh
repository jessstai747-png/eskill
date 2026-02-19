#!/bin/bash

# ====================================
# CONFIGURADOR DE CRON JOBS
# Fonte única: final_crontab (raiz)
# ====================================

set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"

echo "[INFO] Sincronizando cron jobs a partir de: $ROOT_DIR/final_crontab"
"$ROOT_DIR/update_crontab.sh"

echo "[INFO] ✅ Cron Jobs configurados com sucesso"
echo "[INFO] Verifique com: crontab -l"
