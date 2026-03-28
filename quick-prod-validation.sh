#!/bin/bash
#
# Script de validação rápida em produção usando curl
# Testa rotas sem browser automation (mais rápido, menos detalhado)
#
# Usage: ./quick-prod-validation.sh [email] [password]
#

set -e

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

PROD_URL="https://eskill.com.br"
COOKIE_FILE="/tmp/eskill-prod-cookies.txt"
SESSION_FILE="/tmp/eskill-prod-session.txt"

echo -e "${BLUE}╔════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   🔍 Validação Rápida - eskill.com.br     ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════╝${NC}"
echo ""

# Cleanup anterior
rm -f "$COOKIE_FILE" "$SESSION_FILE"

# ==============================================
# 1. TESTE DE PÁGINA DE LOGIN
# ==============================================
echo -e "${YELLOW}📋 1. Testando página de login...${NC}"

LOGIN_RESPONSE=$(curl -s -L -c "$COOKIE_FILE" "$PROD_URL/login" -w "\n%{http_code}")
LOGIN_STATUS=$(echo "$LOGIN_RESPONSE" | tail -1)
LOGIN_BODY=$(echo "$LOGIN_RESPONSE" | sed '$d')

echo "   Status HTTP: $LOGIN_STATUS"

if [ "$LOGIN_STATUS" -eq 200 ]; then
    echo -e "   ${GREEN}✓${NC} Página de login acessível"
else
    echo -e "   ${RED}✗${NC} Erro ao acessar página de login"
fi

# Verificar CSRF token
CSRF_TOKEN=$(echo "$LOGIN_BODY" | grep -oP 'name="_token"\s+value="\K[^"]+' | head -1)
CSRF_META=$(echo "$LOGIN_BODY" | grep -oP 'name="csrf-token"\s+content="\K[^"]+' | head -1)

if [ -n "$CSRF_TOKEN" ]; then
    echo -e "   ${GREEN}✓${NC} CSRF token (input hidden): Encontrado"
else
    echo -e "   ${YELLOW}⚠${NC} CSRF token (input hidden): NÃO encontrado"
fi

if [ -n "$CSRF_META" ]; then
    echo -e "   ${GREEN}✓${NC} CSRF token (meta tag): Encontrado"
else
    echo -e "   ${YELLOW}⚠${NC} CSRF token (meta tag): NÃO encontrado"
fi

# Verificar cookie de sessão
if grep -q "PHPSESSID" "$COOKIE_FILE" 2>/dev/null; then
    PHPSESSID=$(grep "PHPSESSID" "$COOKIE_FILE" | awk '{print $7}')
    echo -e "   ${GREEN}✓${NC} Cookie de sessão: PHPSESSID=$PHPSESSID"
else
    echo -e "   ${YELLOW}⚠${NC} Cookie de sessão: NÃO encontrado"
fi

echo ""

# ==============================================
# 2. TESTE DE LOGIN (se credenciais fornecidas)
# ==============================================
if [ -n "$1" ] && [ -n "$2" ]; then
    EMAIL="$1"
    PASSWORD="$2"

    echo -e "${YELLOW}🔐 2. Testando login com credenciais...${NC}"

    # Usar o CSRF token encontrado
    TOKEN="${CSRF_TOKEN:-${CSRF_META}}"

    if [ -z "$TOKEN" ]; then
        echo -e "   ${RED}✗${NC} Não foi possível obter CSRF token para login"
    else
        # Tentar login via POST
        LOGIN_POST=$(curl -s -L -b "$COOKIE_FILE" -c "$COOKIE_FILE" -X POST "$PROD_URL/login" \
            -H "Content-Type: application/x-www-form-urlencoded" \
            -H "X-CSRF-TOKEN: $TOKEN" \
            -d "email=$EMAIL&password=$PASSWORD&_token=$TOKEN" \
            -w "\n%{http_code}\n%{url_effective}")

        LOGIN_POST_STATUS=$(echo "$LOGIN_POST" | tail -2 | head -1)
        LOGIN_POST_URL=$(echo "$LOGIN_POST" | tail -1)

        echo "   Status HTTP: $LOGIN_POST_STATUS"
        echo "   URL final: $LOGIN_POST_URL"

        if [[ "$LOGIN_POST_URL" == *"dashboard"* ]]; then
            echo -e "   ${GREEN}✓${NC} Login bem-sucedido! Redirecionado para dashboard"
            echo "true" > "$SESSION_FILE"
        else
            echo -e "   ${YELLOW}⚠${NC} Login pode ter falhado (não redirecionou para dashboard)"

            # Tentar via API
            echo ""
            echo -e "${YELLOW}   Tentando via API /api/auth/login...${NC}"

            API_LOGIN=$(curl -s -X POST "$PROD_URL/api/auth/login" \
                -H "Content-Type: application/json" \
                -H "Accept: application/json" \
                -d "{\"email\":\"$EMAIL\",\"password\":\"$PASSWORD\"}" \
                -w "\n%{http_code}")

            API_STATUS=$(echo "$API_LOGIN" | tail -1)
            API_BODY=$(echo "$API_LOGIN" | sed '$d')

            echo "   API Status: $API_STATUS"
            echo "   API Response: $API_BODY"

            if [ "$API_STATUS" -eq 200 ]; then
                echo -e "   ${GREEN}✓${NC} API funciona - problema pode ser de CSRF/sessão no fluxo web"
            else
                echo -e "   ${RED}✗${NC} API falhou - problema nas credenciais ou lógica de autenticação"
            fi
        fi
    fi
else
    echo -e "${YELLOW}🔐 2. Login não testado (credenciais não fornecidas)${NC}"
    echo "   Execute: $0 seu@email.com suasenha"
fi

echo ""

# ==============================================
# 3. SMOKE TEST DAS ROTAS DO DASHBOARD
# ==============================================
ROUTES=(
    "/dashboard:Dashboard Principal"
    "/dashboard/accounts:Contas"
    "/dashboard/analytics:Analytics"
    "/dashboard/account-health:Account Health"
    "/dashboard/items:Items"
    "/dashboard/orders:Orders"
    "/dashboard/questions:Questions"
    "/dashboard/messages:Messages"
    "/dashboard/claims:Claims"
    "/dashboard/seo-killer:SEO Killer"
    "/dashboard/financials:Financials"
    "/dashboard/pricing:Pricing"
)

if [ -f "$SESSION_FILE" ] && [ "$(cat $SESSION_FILE)" = "true" ]; then
    echo -e "${YELLOW}📊 3. Smoke test das rotas do dashboard...${NC}"
    echo ""

    SUCCESS_COUNT=0
    FAIL_COUNT=0

    for route_info in "${ROUTES[@]}"; do
        ROUTE=$(echo "$route_info" | cut -d: -f1)
        NAME=$(echo "$route_info" | cut -d: -f2)

        RESPONSE=$(curl -s -L -b "$COOKIE_FILE" "$PROD_URL$ROUTE" -w "\n%{http_code}")
        STATUS=$(echo "$RESPONSE" | tail -1)

        if [ "$STATUS" -ge 200 ] && [ "$STATUS" -lt 400 ]; then
            echo -e "   ${GREEN}✓${NC} [$STATUS] $NAME"
            ((SUCCESS_COUNT++))
        else
            echo -e "   ${RED}✗${NC} [$STATUS] $NAME"
            ((FAIL_COUNT++))
        fi
    done

    echo ""
    echo -e "   ${BLUE}Resumo:${NC} $SUCCESS_COUNT sucessos, $FAIL_COUNT falhas"
else
    echo -e "${YELLOW}📊 3. Smoke test não executado (sem sessão autenticada)${NC}"
fi

echo ""

# ==============================================
# 4. RESUMO FINAL
# ==============================================
echo -e "${BLUE}╔════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║              📋 Resumo Final               ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════╝${NC}"
echo ""
echo "📍 URL testada: $PROD_URL"
echo "📊 Página de login: Status $LOGIN_STATUS"
echo "🔑 CSRF Token: $([ -n "$CSRF_TOKEN" ] || [ -n "$CSRF_META" ] && echo 'OK' || echo 'FALTANDO')"
echo "🍪 Cookie de sessão: $(grep -q PHPSESSID "$COOKIE_FILE" 2>/dev/null && echo 'OK' || echo 'FALTANDO')"

if [ -f "$SESSION_FILE" ] && [ "$(cat $SESSION_FILE)" = "true" ]; then
    echo "🔐 Login: SUCESSO"
    echo "✅ Rotas testadas: ${#ROUTES[@]}"
    echo "   ✓ Sucessos: $SUCCESS_COUNT"
    echo "   ✗ Falhas: $FAIL_COUNT"
else
    echo "🔐 Login: Não testado ou falhou"
fi

echo ""
echo -e "${YELLOW}💡 Para teste completo com browser automation:${NC}"
echo "   ./run-prod-validation.sh $1 $2"
echo ""

# Cleanup
rm -f "$COOKIE_FILE" "$SESSION_FILE"
