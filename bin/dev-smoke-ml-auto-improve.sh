#!/bin/bash
# Smoke test do pipeline ML-only:
# 1) Sobe MySQL de teste (docker compose)
# 2) Roda migrations com .env.testing
# 3) Executa ml-auto-improve em DRY-RUN
#
# Uso:
#   bash bin/dev-smoke-ml-auto-improve.sh
#   MAX_RISK=low LIMIT_ACCOUNTS=1 MAX_ITEMS=5 bash bin/dev-smoke-ml-auto-improve.sh

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$PROJECT_DIR"

MAX_RISK="${MAX_RISK:-low}"
LIMIT_ACCOUNTS="${LIMIT_ACCOUNTS:-1}"
MAX_ITEMS="${MAX_ITEMS:-5}"

if [ ! -f .env.testing ]; then
    echo "ERRO: .env.testing não encontrado." >&2
    echo "Crie .env.testing com DB_HOST=127.0.0.1, DB_PORT=3307, DB_DATABASE=app_test, DB_USERNAME=testuser, DB_PASSWORD=testpass" >&2
    exit 1
fi

echo "[smoke] Subindo DB de teste..."
bash bin/dev-test-db-up.sh

echo "[smoke] Preflight (antes das migrations)..."
php bin/ml-auto-improve.php --preflight

echo "[smoke] Rodando migrations..."
php bin/migrate.php --status || true
php bin/migrate.php

echo "[smoke] Preflight (depois das migrations)..."
php bin/ml-auto-improve.php --preflight

echo "[smoke] Executando ml-auto-improve (dry-run)..."
php bin/ml-auto-improve.php \
  --dry-run \
  --limit-accounts="$LIMIT_ACCOUNTS" \
  --max-items-per-account="$MAX_ITEMS" \
  --max-risk="$MAX_RISK"

echo "[smoke] OK."
