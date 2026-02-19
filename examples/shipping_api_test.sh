#!/bin/bash

# ====================================
# SHIPPING STRATEGY OPTIMIZER - TEST
# ====================================

BASE_URL="http://localhost/api"

echo "================================"
echo "📦 SHIPPING STRATEGY OPTIMIZER"
echo "================================"
echo ""

# 1. SIMULAR CUSTOS DE ITEM EXISTENTE
echo "1️⃣  Simulando custos para item..."
echo "GET /api/shipping/simulate/MLB123456789?zip_code=01310-100"
curl -s -X GET "${BASE_URL}/shipping/simulate/MLB123456789?zip_code=01310-100" \
     -H "Content-Type: application/json" | jq '.'
echo ""

# 2. SIMULAR COM DADOS CUSTOMIZADOS
echo "2️⃣  Simulação com parâmetros customizados..."
echo "POST /api/shipping/simulate"
curl -s -X POST "${BASE_URL}/shipping/simulate" \
     -H "Content-Type: application/json" \
     -d '{
       "dimensions": {
         "length": 30,
         "width": 20,
         "height": 10
       },
       "weight": 2.5,
       "zip_code": "01310-100",
       "include_full": true
     }' | jq '.'
echo ""

# 3. COMPARAR CUSTOS MÚLTIPLOS CEPs
echo "3️⃣  Comparando custos para múltiplos CEPs..."
echo "POST /api/shipping/compare"
curl -s -X POST "${BASE_URL}/shipping/compare" \
     -H "Content-Type: application/json" \
     -d '{
       "item_id": "MLB123456789",
       "zip_codes": ["01310-100", "20040-020", "30130-100"]
     }' | jq '.'
echo ""

# 4. OTIMIZAR ESTRATÉGIA DE UM ITEM
echo "4️⃣  Otimizando estratégia de envio..."
echo "GET /api/shipping/optimize/MLB123456789?target_margin=0.30"
curl -s -X GET "${BASE_URL}/shipping/optimize/MLB123456789?target_margin=0.30" \
     -H "Content-Type: application/json" | jq '.'
echo ""

# 5. OTIMIZAR LOTE DE ITENS
echo "5️⃣  Otimizando lote de itens..."
echo "POST /api/shipping/optimize/batch"
curl -s -X POST "${BASE_URL}/shipping/optimize/batch" \
     -H "Content-Type: application/json" \
     -d '{
       "item_ids": ["MLB123456789", "MLB987654321", "MLB555666777"],
       "options": {
         "target_margin": 0.30
       }
     }' | jq '.summary'
echo ""

# 6. CALCULAR PESO CUBADO
echo "6️⃣  Calculando peso cubado..."
echo "POST /api/shipping/dimensions/cubic-weight"
curl -s -X POST "${BASE_URL}/shipping/dimensions/cubic-weight" \
     -H "Content-Type: application/json" \
     -d '{
       "length": 30,
       "width": 20,
       "height": 10
     }' | jq '.'
echo ""

# 7. CALCULAR PESO COBRÁVEL
echo "7️⃣  Calculando peso cobrável (real vs cubado)..."
echo "POST /api/shipping/dimensions/chargeable-weight"
curl -s -X POST "${BASE_URL}/shipping/dimensions/chargeable-weight" \
     -H "Content-Type: application/json" \
     -d '{
       "length": 30,
       "width": 20,
       "height": 10,
       "weight": 2.5
     }' | jq '.'
echo ""

# 8. VALIDAR DIMENSÕES PARA MODALIDADE
echo "8️⃣  Validando dimensões para Full..."
echo "POST /api/shipping/dimensions/validate"
curl -s -X POST "${BASE_URL}/shipping/dimensions/validate" \
     -H "Content-Type: application/json" \
     -d '{
       "length": 30,
       "width": 20,
       "height": 10,
       "weight": 2.5,
       "shipping_mode": "full"
     }' | jq '.valid, .issues, .warnings'
echo ""

# 9. VALIDAR TODAS AS MODALIDADES
echo "9️⃣  Validando para todas as modalidades..."
echo "POST /api/shipping/dimensions/validate-all"
curl -s -X POST "${BASE_URL}/shipping/dimensions/validate-all" \
     -H "Content-Type: application/json" \
     -d '{
       "length": 30,
       "width": 20,
       "height": 10,
       "weight": 2.5
     }' | jq '.compatible_modes, .recommended_mode'
echo ""

# 10. SUGERIR EMBALAGEM
echo "🔟 Sugerindo embalagem adequada..."
echo "POST /api/shipping/dimensions/suggest-packaging"
curl -s -X POST "${BASE_URL}/shipping/dimensions/suggest-packaging" \
     -H "Content-Type: application/json" \
     -d '{
       "length": 28,
       "width": 18,
       "height": 8
     }' | jq '.recommended'
echo ""

# 11. OTIMIZAR DIMENSÕES
echo "1️⃣1️⃣  Otimizando dimensões para reduzir custo..."
echo "POST /api/shipping/dimensions/optimize"
curl -s -X POST "${BASE_URL}/shipping/dimensions/optimize" \
     -H "Content-Type: application/json" \
     -d '{
       "length": 40,
       "width": 35,
       "height": 25,
       "weight": 5.0,
       "target_mode": "full"
     }' | jq '.suggestions, .total_potential_savings'
echo ""

# 12. ANÁLISE COMPLETA DE DIMENSÕES
echo "1️⃣2️⃣  Análise completa de dimensões..."
echo "POST /api/shipping/dimensions/analyze"
curl -s -X POST "${BASE_URL}/shipping/dimensions/analyze" \
     -H "Content-Type: application/json" \
     -d '{
       "length": 30,
       "width": 20,
       "height": 10,
       "weight": 2.5
     }' | jq '{
       chargeable_weight,
       validation_all_modes: .validation_all_modes.compatible_modes,
       packaging_recommended: .packaging_suggestions.recommended.name,
       optimization_suggestions: .optimization_opportunities.suggestions | length
     }'
echo ""

echo "================================"
echo "✅ TODOS OS TESTES CONCLUÍDOS"
echo "================================"
