#!/bin/bash
#
# Script para rodar testes E2E de validação em produção
# Usage: ./run-prod-validation.sh [email] [password]
#

set -e

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

echo -e "${GREEN}🚀 Iniciando testes E2E em PRODUÇÃO${NC}"
echo "============================================="

# Verificar se credenciais foram passadas
if [ -z "$1" ] || [ -z "$2" ]; then
    echo -e "${YELLOW}⚠️  Credenciais não fornecidas como argumentos${NC}"
    echo ""
    echo "Opções:"
    echo "  1. Passar credenciais: ./run-prod-validation.sh email@example.com senha123"
    echo "  2. Usar variáveis de ambiente: PROD_EMAIL=... PROD_PASSWORD=... ./run-prod-validation.sh"
    echo ""

    # Verificar se há variáveis de ambiente
    if [ -z "$PROD_EMAIL" ] || [ -z "$PROD_PASSWORD" ]; then
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
    export PROD_EMAIL="$1"
    export PROD_PASSWORD="$2"
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
npx playwright test tests/e2e/production-validation.spec.ts --reporter=list

# Verificar resultado
if [ $? -eq 0 ]; then
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
