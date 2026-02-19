#!/bin/bash

# Quality Check API - Exemplos de testes com cURL
# Execute: bash examples/quality_check_api_test.sh

BASE_URL="http://localhost:8000"
ITEM_ID="MLB3698937524"

echo "🧪 QUALITY CHECK API - TESTES"
echo "=============================================="
echo ""

# Test 1: Health Check
echo "1️⃣  Testing Health Check API..."
echo "GET ${BASE_URL}/api/quality/health/${ITEM_ID}"
echo ""
curl -s "${BASE_URL}/api/quality/health/${ITEM_ID}" | jq '.health.status, .health.score, .summary' 2>/dev/null || \
    curl -s "${BASE_URL}/api/quality/health/${ITEM_ID}"
echo ""
echo ""

# Test 2: Quality Score
echo "2️⃣  Testing Quality Score API..."
echo "GET ${BASE_URL}/api/quality/score/${ITEM_ID}"
echo ""
curl -s "${BASE_URL}/api/quality/score/${ITEM_ID}" | jq '.quality_score.total, .quality_score.rating' 2>/dev/null || \
    curl -s "${BASE_URL}/api/quality/score/${ITEM_ID}"
echo ""
echo ""

# Test 3: Validation
echo "3️⃣  Testing Validation API..."
echo "POST ${BASE_URL}/api/quality/validate"
echo ""
curl -s -X POST "${BASE_URL}/api/quality/validate" \
    -H "Content-Type: application/json" \
    -d '{
        "title": "Produto Teste Original Novo Lacrado",
        "category_id": "MLB1051",
        "price": 199.90,
        "currency_id": "BRL",
        "available_quantity": 10,
        "buying_mode": "buy_it_now",
        "condition": "new",
        "listing_type_id": "gold_special",
        "pictures": [
            {"source": "https://http2.mlstatic.com/D_NQ_NP_123456-MLB1234567890_012024-O.jpg"}
        ],
        "attributes": [
            {"id": "BRAND", "value_name": "Samsung"}
        ]
    }' | jq '.can_publish, .summary' 2>/dev/null || \
    curl -s -X POST "${BASE_URL}/api/quality/validate" \
        -H "Content-Type: application/json" \
        -d '{"title":"Produto Teste", "category_id":"MLB1051", "price":199.90}'
echo ""
echo ""

# Test 4: Complete Report
echo "4️⃣  Testing Complete Report API..."
echo "GET ${BASE_URL}/api/quality/report/${ITEM_ID}"
echo ""
curl -s "${BASE_URL}/api/quality/report/${ITEM_ID}" | jq '.summary, .action_plan[0]' 2>/dev/null || \
    curl -s "${BASE_URL}/api/quality/report/${ITEM_ID}"
echo ""
echo ""

# Test 5: Health Recommendations
echo "5️⃣  Testing Health Recommendations API..."
echo "GET ${BASE_URL}/api/quality/health/${ITEM_ID}/recommendations"
echo ""
curl -s "${BASE_URL}/api/quality/health/${ITEM_ID}/recommendations" | jq '.recommendations[0:3]' 2>/dev/null || \
    curl -s "${BASE_URL}/api/quality/health/${ITEM_ID}/recommendations"
echo ""
echo ""

# Test 6: Auto-Fix
echo "6️⃣  Testing Auto-Fix API..."
echo "POST ${BASE_URL}/api/quality/autofix"
echo ""
curl -s -X POST "${BASE_URL}/api/quality/autofix" \
    -H "Content-Type: application/json" \
    -d '{
        "title": "Produto  Teste   ",
        "price": 199.90,
        "available_quantity": 5
    }' | jq '.result.changes' 2>/dev/null || \
    curl -s -X POST "${BASE_URL}/api/quality/autofix" \
        -H "Content-Type: application/json" \
        -d '{"title":"Produto  Teste   ", "price":199.90}'
echo ""
echo ""

# Test 7: Batch Health Check
echo "7️⃣  Testing Batch Health Check API..."
echo "POST ${BASE_URL}/api/quality/health/batch"
echo ""
curl -s -X POST "${BASE_URL}/api/quality/health/batch" \
    -H "Content-Type: application/json" \
    -d '{
        "item_ids": ["MLB123", "MLB456"]
    }' | jq '.total_items' 2>/dev/null || \
    curl -s -X POST "${BASE_URL}/api/quality/health/batch" \
        -H "Content-Type: application/json" \
        -d '{"item_ids": ["MLB123", "MLB456"]}'
echo ""
echo ""

echo "=============================================="
echo "✅ Testes concluídos!"
echo ""
echo "💡 Dica: Instale 'jq' para formatação JSON:"
echo "   sudo apt-get install jq"
echo ""
