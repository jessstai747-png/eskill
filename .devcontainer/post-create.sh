#!/usr/bin/env bash

set -euo pipefail

git config --global --add safe.directory "$(pwd)"

mkdir -p storage/logs storage/cache storage/sessions storage/tmp/sessions
chmod -R ug+rwX storage || true

# Garante que o script do MCP Mercado Livre seja executável
chmod +x bin/mcp-ml-start.sh 2>/dev/null || true

echo "[devcontainer] Workspace pronto para agent com auto-approve."
echo "[devcontainer] Se quiser ambiente completo: rode 'composer install' e, se necessário, 'npm install'."
