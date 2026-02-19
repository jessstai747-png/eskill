#!/bin/bash

# =============================================================================
# Script de Teste - API SEO Strategies
# Testa os endpoints principais da API
# =============================================================================

echo "========================================"
echo "   TESTE - API SEO STRATEGIES"
echo "========================================"
echo ""

# URL base (ajustar conforme necessário)
BASE_URL="http://localhost"
API_URL="${BASE_URL}/api/seo-killer/strategies"

# Categoria piloto
CATEGORY_ID="MLB3530"

# Item de exemplo
ITEM_ID="MLB123456789"

echo "📡 URL Base: ${BASE_URL}"
echo "📁 Categoria: ${CATEGORY_ID}"
echo ""

# =============================================================================
# TESTE 1: Dashboard Principal
# =============================================================================
echo "🔍 TESTE 1: Dashboard Principal"
echo "----------------------------------------"
echo "GET ${API_URL}/dashboard"

response=$(curl -s -o /dev/null -w "%{http_code}" "${API_URL}/dashboard")

if [ "$response" = "200" ]; then
    echo "✅ Status: 200 OK"
else
    echo "⚠️  Status: $response"
fi
echo ""

# =============================================================================
# TESTE 2: Cache Stats
# =============================================================================
echo "📊 TESTE 2: Estatísticas de Cache"
echo "----------------------------------------"
echo "GET ${API_URL}/cache/stats"

response=$(curl -s -o /dev/null -w "%{http_code}" "${API_URL}/cache/stats")

if [ "$response" = "200" ]; then
    echo "✅ Status: 200 OK"
else
    echo "⚠️  Status: $response"
fi
echo ""

# =============================================================================
# TESTE 3: Hierarquia de Sinônimos
# =============================================================================
echo "🔤 TESTE 3: Hierarquia de Sinônimos"
echo "----------------------------------------"
echo "GET ${API_URL}/synonyms/hierarchy/${CATEGORY_ID}"

response=$(curl -s -o /dev/null -w "%{http_code}" "${API_URL}/synonyms/hierarchy/${CATEGORY_ID}")

if [ "$response" = "200" ]; then
    echo "✅ Status: 200 OK"
else
    echo "⚠️  Status: $response"
fi
echo ""

# =============================================================================
# TESTE 4: Contextos de Uso
# =============================================================================
echo "🎯 TESTE 4: Contextos de Uso"
echo "----------------------------------------"
echo "GET ${API_URL}/contexts/${CATEGORY_ID}"

response=$(curl -s -o /dev/null -w "%{http_code}" "${API_URL}/contexts/${CATEGORY_ID}")

if [ "$response" = "200" ]; then
    echo "✅ Status: 200 OK"
else
    echo "⚠️  Status: $response"
fi
echo ""

# =============================================================================
# TESTE 5: Dashboard por Categoria
# =============================================================================
echo "📈 TESTE 5: Dashboard por Categoria"
echo "----------------------------------------"
echo "GET ${API_URL}/engine/dashboard/${CATEGORY_ID}"

response=$(curl -s -o /dev/null -w "%{http_code}" "${API_URL}/engine/dashboard/${CATEGORY_ID}")

if [ "$response" = "200" ]; then
    echo "✅ Status: 200 OK"
else
    echo "⚠️  Status: $response"
fi
echo ""

# =============================================================================
# TESTE 6: Testar POST - Expansão de Sinônimos
# =============================================================================
echo "🔄 TESTE 6: Expansão de Sinônimos (POST)"
echo "----------------------------------------"
echo "POST ${API_URL}/synonyms/expand"

response=$(curl -s -o /dev/null -w "%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -d "{\"title\":\"Bauleto 41 Litros\",\"category_id\":\"${CATEGORY_ID}\"}" \
    "${API_URL}/synonyms/expand")

if [ "$response" = "200" ]; then
    echo "✅ Status: 200 OK"
else
    echo "⚠️  Status: $response"
fi
echo ""

# =============================================================================
# TESTE 7: Calcular Score Semântico
# =============================================================================
echo "🎯 TESTE 7: Score Semântico (POST)"
echo "----------------------------------------"
echo "POST ${API_URL}/score/calculate"

response=$(curl -s -o /dev/null -w "%{http_code}" \
    -X POST \
    -H "Content-Type: application/json" \
    -d "{\"word\":\"bauleto\",\"title\":\"Bauleto 41 Litros\",\"category_id\":\"${CATEGORY_ID}\"}" \
    "${API_URL}/score/calculate")

if [ "$response" = "200" ]; then
    echo "✅ Status: 200 OK"
else
    echo "⚠️  Status: $response"
fi
echo ""

# =============================================================================
# RESUMO
# =============================================================================
echo "========================================"
echo "   RESUMO DOS TESTES"
echo "========================================"
echo ""
echo "✅ 7 endpoints testados"
echo ""
echo "📝 NOTA:"
echo "   - Endpoints que retornam status 401/403 requerem autenticação"
echo "   - Endpoints que retornam 404 podem não estar registrados"
echo "   - Endpoints que retornam 500 têm erros de implementação"
echo ""
echo "🔗 Para testar com dados reais, acesse:"
echo "   ${BASE_URL}/dashboard/seo-killer/strategies"
echo ""
echo "========================================"
echo "   TESTE CONCLUÍDO!"
echo "========================================"
