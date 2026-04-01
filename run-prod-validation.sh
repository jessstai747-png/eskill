#!/bin/bash
#
# Script para rodar testes E2E de validação em produção
# Usage: ./run-prod-validation.sh [email] [password]
#

set -euo pipefail

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}🚀 Iniciando testes E2E em PRODUÇÃO${NC}"
echo "============================================="

PROD_URL="https://eskill.com.br"
TMP_BASE="${TMPDIR:-/tmp}"
PROBE_HEADERS="${TMP_BASE}/eskill-prod-run-probe-headers.txt"
ARG_EMAIL="${1:-}"
ARG_PASSWORD="${2:-}"

# Pré-checagem de bloqueio por allowlist no sandbox
rm -f "$PROBE_HEADERS"
PROBE_STATUS=$(curl -sS -I -D "$PROBE_HEADERS" -o /dev/null "$PROD_URL/login" -w "%{http_code}" 2>/dev/null || true)
if grep -qi "^x-proxy-error:\s*blocked-by-allowlist" "$PROBE_HEADERS" 2>/dev/null; then
    echo -e "${YELLOW}⚠ Bloqueio de rede detectado (blocked-by-allowlist).${NC}"
    echo "   O ambiente atual não consegue acessar https://eskill.com.br."
    echo "   Execute este script fora do sandbox (SSH/host direto) para validação real de produção."
    rm -f "$PROBE_HEADERS"
    exit 0
fi

if [ "$PROBE_STATUS" = "403" ]; then
    PROBE_BODY="${TMP_BASE}/eskill-prod-run-probe-body.txt"
    curl -sS -L "$PROD_URL/login" -o "$PROBE_BODY" 2>/dev/null || true
    if grep -qi "blocked-by-allowlist" "$PROBE_BODY" 2>/dev/null; then
        echo -e "${YELLOW}⚠ Bloqueio de rede detectado (blocked-by-allowlist).${NC}"
        echo "   O ambiente atual não consegue acessar https://eskill.com.br."
        echo "   Execute este script fora do sandbox (SSH/host direto) para validação real de produção."
        rm -f "$PROBE_HEADERS" "$PROBE_BODY"
        exit 0
    fi
    rm -f "$PROBE_BODY"
fi

rm -f "$PROBE_HEADERS"

# Verificar se credenciais foram passadas
if [ -z "$ARG_EMAIL" ] || [ -z "$ARG_PASSWORD" ]; then
    echo -e "${YELLOW}⚠️  Credenciais não fornecidas como argumentos${NC}"
    echo ""
    echo "Opções:"
    echo "  1. Passar credenciais: ./run-prod-validation.sh email@example.com senha123"
    echo "  2. Usar variáveis de ambiente: PROD_EMAIL=... PROD_PASSWORD=... ./run-prod-validation.sh"
    echo ""

    # Verificar se há variáveis de ambiente
    if [ -z "${PROD_EMAIL:-}" ] || [ -z "${PROD_PASSWORD:-}" ]; then
        echo -e "${RED}❌ Nenhuma credencial disponível${NC}"
        echo ""
        echo "Os testes que requerem autenticação serão PULADOS."
        echo "Alguns testes (como inspeção da página de login) ainda serão executados."
        echo ""
        read -p "Continuar mesmo assim? (y/N) " -n 1 -r
        echo
        if [[ ! $REPLY =~ ^[Yy]$ ]]; then
            exit 1
        fi
    fi
else
    export PROD_EMAIL="$ARG_EMAIL"
    export PROD_PASSWORD="$ARG_PASSWORD"
    echo -e "${GREEN}✅ Credenciais fornecidas via argumentos${NC}"
fi

# Verificar se node_modules existe
if [ ! -d "node_modules" ]; then
    echo -e "${YELLOW}📦 node_modules não encontrado. Instalando dependências...${NC}"
    npm install
fi

# Criar diretório de screenshots
mkdir -p storage/playwright-screenshots

# Rodar testes
echo ""
echo -e "${GREEN}🎭 Executando Playwright E2E Tests...${NC}"
echo ""

# Rodar apenas o arquivo de validação em produção
if npx playwright test tests/e2e/production-validation.spec.ts --reporter=list; then
    echo ""
    echo -e "${GREEN}✅ Testes concluídos com sucesso!${NC}"
    echo ""
    echo "📸 Screenshots salvos em: storage/playwright-screenshots/"
    echo "📊 Relatório HTML: npx playwright show-report"
else
    echo ""
    echo -e "${RED}❌ Alguns testes falharam${NC}"
    echo ""
    echo "📸 Screenshots: storage/playwright-screenshots/"
    echo "📊 Ver relatório: npx playwright show-report"
    exit 1
fi
