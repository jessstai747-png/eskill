#!/usr/bin/env bash
# update-secret.sh — Atualiza PROXY_SECRET no Worker via Cloudflare API
# Não requer redeploy do código — só atualiza a variável de ambiente
#
# Uso interativo:
#   ./update-secret.sh
#
# Uso com env vars:
#   CLOUDFLARE_ACCOUNT_ID=xxx CLOUDFLARE_API_TOKEN=yyy ./update-secret.sh

set -euo pipefail

ENV_FILE="/home/eskill/htdocs/eskill.com.br/.env"
WORKER_NAME="ml-api-proxy"

echo "========================================="
echo "  Update: PROXY_SECRET → $WORKER_NAME"
echo "========================================="
echo ""

CF_ACCOUNT_ID="${CLOUDFLARE_ACCOUNT_ID:-}"
CF_API_TOKEN="${CLOUDFLARE_API_TOKEN:-}"

if [[ -z "$CF_ACCOUNT_ID" ]]; then
  read -rp "Cloudflare Account ID (em dash.cloudflare.com → lado direito): " CF_ACCOUNT_ID
fi
if [[ -z "$CF_API_TOKEN" ]]; then
  read -rsp "Cloudflare API Token (permissão Workers Scripts:Edit): " CF_API_TOKEN
  echo ""
fi

if [[ -f "$ENV_FILE" ]]; then
  PROXY_SECRET=$(grep "^ML_CF_PROXY_SECRET=" "$ENV_FILE" | cut -d'=' -f2-)
fi
PROXY_SECRET="${PROXY_SECRET:-$(openssl rand -hex 32)}"
echo "Secret (first 8): ${PROXY_SECRET:0:8}..."
echo ""

echo "Enviando para Cloudflare API..."
RESPONSE=$(curl -s -X PUT \
  "https://api.cloudflare.com/client/v4/accounts/${CF_ACCOUNT_ID}/workers/scripts/${WORKER_NAME}/secrets" \
  -H "Authorization: Bearer ${CF_API_TOKEN}" \
  -H "Content-Type: application/json" \
  -d "{\"name\":\"PROXY_SECRET\",\"text\":\"${PROXY_SECRET}\",\"type\":\"secret_text\"}")

SUCCESS=$(echo "$RESPONSE" | python3 -c "import sys,json; print(json.load(sys.stdin).get('success',False))" 2>/dev/null || echo "False")

if [[ "$SUCCESS" == "True" ]]; then
  echo "✅ PROXY_SECRET atualizado com sucesso!"
  echo ""
  echo "Aguardando propagação (3s)..."
  sleep 3

  TEST=$(curl -s -w "\nHTTP:%{http_code}" \
    -H "X-Proxy-Secret: ${PROXY_SECRET}" \
    -H "X-ML-Path: /sites/MLB" \
    "https://ml-api-proxy.facilytycontato.workers.dev")

  HTTP_CODE=$(echo "$TEST" | grep "HTTP:" | cut -d: -f2)
  echo "Teste Worker: HTTP $HTTP_CODE"
  [[ "$HTTP_CODE" == "200" ]] && echo "✅ Worker OK!" || echo "⚠ HTTP $HTTP_CODE — aguarde alguns segundos"
else
  echo "❌ Erro na API:"
  echo "$RESPONSE"
  echo ""
  echo "── Alternativa manual (Cloudflare Dashboard) ──"
  echo "1. https://dash.cloudflare.com"
  echo "2. Workers & Pages → ml-api-proxy → Settings → Variables"
  echo "3. Add variable: PROXY_SECRET = ${PROXY_SECRET}  (Encrypted: ✓)"
fi
