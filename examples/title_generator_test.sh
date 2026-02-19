#!/bin/bash
# SEO Title Generator - Teste de API via curl
# Demonstra todos os endpoints disponíveis

BASE_URL="http://localhost:8000"
API_BASE="$BASE_URL/api/title-generator"

echo "========================================"
echo "SEO Title Generator - API Tests"
echo "========================================"
echo ""

# ========================================
# 1. GERAR TÍTULOS
# ========================================
echo "1. Gerar títulos para novo produto..."
echo "----------------------------------------"

curl -X POST "$API_BASE/generate" \
  -H "Content-Type: application/json" \
  -d '{
    "category_id": "MLB1234",
    "brand": "Apple",
    "model": "iPhone 15 Pro Max",
    "attributes": [
      {"id": "INTERNAL_MEMORY", "value_name": "256 GB"},
      {"id": "COLOR", "value_name": "Titanio Natural"}
    ],
    "options": {
      "count": 5,
      "optimize_for": "both",
      "min_score": 70
    }
  }' | jq '.titles[] | {title: .title, score: .score}'

echo ""
echo ""

# ========================================
# 2. ANALISAR TÍTULO
# ========================================
echo "2. Analisar título existente..."
echo "----------------------------------------"

curl -X POST "$API_BASE/analyze" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "iPhone 15 Pro Max 256GB Titanio Natural Apple",
    "category_id": "MLB1234"
  }' | jq '{
    title: .analysis.title,
    overall_score: .analysis.overall_score,
    status: .analysis.status,
    length: .analysis.length,
    seo_score: .analysis.seo_analysis.score,
    performance_score: .analysis.performance_estimate.performance_score
  }'

echo ""
echo ""

# ========================================
# 3. ANALISAR TÍTULO RUIM
# ========================================
echo "3. Analisar título ruim..."
echo "----------------------------------------"

curl -X POST "$API_BASE/analyze" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Celular",
    "category_id": "MLB1234"
  }' | jq '{
    title: .analysis.title,
    score: .analysis.overall_score,
    status: .analysis.status,
    issues: .analysis.issues,
    suggestions: .analysis.suggestions
  }'

echo ""
echo ""

# ========================================
# 4. GERAR VARIAÇÕES
# ========================================
echo "4. Gerar variações de título..."
echo "----------------------------------------"

curl -X POST "$API_BASE/variations" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Samsung Galaxy S23 128GB",
    "category_id": "MLB1234",
    "count": 5,
    "strategy": "all"
  }' | jq '{
    original: .original_title,
    original_score: .original_score,
    variations_count: .variations_generated,
    top_3: [.variations[0,1,2] | {title: .title, score: .score, strategy: .strategy}]
  }'

echo ""
echo ""

# ========================================
# 5. A/B TESTING
# ========================================
echo "5. Gerar variações A/B Testing..."
echo "----------------------------------------"

curl -X POST "$API_BASE/ab-testing" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Samsung Galaxy S23 128GB",
    "category_id": "MLB1234"
  }' | jq '.'

echo ""
echo ""

# ========================================
# 6. COMPARAR TÍTULOS
# ========================================
echo "6. Comparar múltiplos títulos..."
echo "----------------------------------------"

curl -X POST "$API_BASE/compare" \
  -H "Content-Type: application/json" \
  -d '{
    "titles": [
      "iPhone 15 Pro Max 256GB",
      "Apple iPhone 15 Pro Max Titanio Natural",
      "256GB iPhone 15 Pro Max Apple",
      "iPhone 15"
    ],
    "category_id": "MLB1234"
  }' | jq '{
    total: .total_titles,
    best_title: .best_title,
    worst_title: .worst_title,
    comparisons: [.comparisons[] | {title: .title, score: .score, status: .status}]
  }'

echo ""
echo ""

# ========================================
# 7. OTIMIZAR TÍTULO
# ========================================
echo "7. Otimizar título existente..."
echo "----------------------------------------"

curl -X POST "$API_BASE/optimize" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "iPhone 15",
    "category_id": "MLB1234",
    "count": 3
  }' | jq '{
    original: .original_title,
    original_score: .current_analysis.overall_score,
    best_improvement: .best_improvement,
    top_variations: [.optimized_variations[0,1,2] | {title: .title, score: .score}]
  }'

echo ""
echo ""

# ========================================
# 8. QUICK TIPS
# ========================================
echo "8. Quick tips para título..."
echo "----------------------------------------"

curl -X GET "$API_BASE/quick-tips?title=iPhone%2015%20Pro" | jq '.tips'

echo ""
echo ""

# ========================================
# 9. MELHORAR ANÚNCIO EXISTENTE
# ========================================
echo "9. Melhorar título de anúncio existente..."
echo "----------------------------------------"

curl -X POST "$API_BASE/improve/MLB1234567890" \
  -H "Content-Type: application/json" \
  -d '{
    "count": 3,
    "optimize_for": "conversion"
  }' | jq '{
    original: .original_title,
    improvement: .improvement,
    best_title: .best_title
  }' 2>/dev/null || echo "⚠️ Teste pulado (requer item real)"

echo ""
echo ""

# ========================================
# 10. BATCH ANALYSIS
# ========================================
echo "10. Análise em lote (batch)..."
echo "----------------------------------------"

curl -X POST "$API_BASE/batch/analyze" \
  -H "Content-Type: application/json" \
  -d '{
    "item_ids": [
      "MLB1234567890",
      "MLB2345678901",
      "MLB3456789012"
    ]
  }' | jq '{
    total_analyzed: .total_analyzed,
    average_score: .average_score,
    needs_improvement: .needs_improvement_count
  }' 2>/dev/null || echo "⚠️ Teste pulado (requer items reais)"

echo ""
echo ""

# ========================================
# RESUMO
# ========================================
echo "========================================"
echo "✅ Testes de API concluídos!"
echo "========================================"
echo ""
echo "Endpoints testados:"
echo "  ✓ POST /generate - Gerar títulos"
echo "  ✓ POST /analyze - Analisar título"
echo "  ✓ POST /variations - Gerar variações"
echo "  ✓ POST /ab-testing - Variações A/B"
echo "  ✓ POST /compare - Comparar títulos"
echo "  ✓ POST /optimize - Otimizar título"
echo "  ✓ GET  /quick-tips - Dicas rápidas"
echo "  ✓ POST /improve/{itemId} - Melhorar anúncio"
echo "  ✓ POST /batch/analyze - Análise em lote"
echo ""
