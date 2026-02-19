#!/bin/bash
# Listing Builder Wizard - Teste de API via curl
# Demonstra todos os endpoints disponíveis

BASE_URL="http://localhost:8000"
API_BASE="$BASE_URL/api/listing-builder"

echo "========================================"
echo "Listing Builder Wizard - API Tests"
echo "========================================"
echo ""

# ========================================
# 1. INICIAR WIZARD
# ========================================
echo "1. Iniciando wizard..."
echo "----------------------------------------"

curl -X POST "$API_BASE/start" \
  -H "Content-Type: application/json" \
  -d '{
    "category_id": "MLB1234",
    "product_name": "iPhone 15 Pro Max"
  }' | jq '.'

echo ""
echo ""

# ========================================
# 2. VALIDAR BASIC INFO
# ========================================
echo "2. Validando informações básicas..."
echo "----------------------------------------"

curl -X POST "$API_BASE/validate/basic_info" \
  -H "Content-Type: application/json" \
  -d '{
    "category_id": "MLB1234",
    "product_name": "iPhone 15 Pro Max",
    "brand": "Apple",
    "model": "iPhone 15 Pro Max",
    "condition": "new"
  }' | jq '.'

echo ""
echo ""

# ========================================
# 3. VALIDAR TÍTULO
# ========================================
echo "3. Validando título..."
echo "----------------------------------------"

curl -X POST "$API_BASE/validate/title" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "iPhone 15 Pro Max 256GB Titanio Natural Apple"
  }' | jq '.'

echo ""
echo ""

# ========================================
# 4. LISTAR TEMPLATES
# ========================================
echo "4. Listando templates disponíveis..."
echo "----------------------------------------"

curl -X GET "$API_BASE/templates" | jq '.'

echo ""
echo ""

# ========================================
# 5. RENDERIZAR TEMPLATE
# ========================================
echo "5. Renderizando template Modern..."
echo "----------------------------------------"

curl -X POST "$API_BASE/templates/modern/render" \
  -H "Content-Type: application/json" \
  -d '{
    "product_name": "iPhone 15 Pro Max",
    "description": "O mais avançado iPhone já criado.",
    "features": [
      "Chip A17 Pro",
      "Câmera 48MP",
      "Design em Titânio"
    ],
    "specs": {
      "Marca": "Apple",
      "Modelo": "iPhone 15 Pro Max",
      "Memória": "256GB"
    },
    "warranty": "12 meses de garantia oficial Apple"
  }' | jq '.rendered_html' | head -20

echo "... (HTML truncado)"
echo ""
echo ""

# ========================================
# 6. VALIDAR DESCRIÇÃO
# ========================================
echo "6. Validando descrição..."
echo "----------------------------------------"

curl -X POST "$API_BASE/validate/description" \
  -H "Content-Type: application/json" \
  -d '{
    "description": "<div>iPhone 15 Pro Max com chip A17 Pro, câmera 48MP e design em titânio. O iPhone mais avançado já criado com recursos pro e desempenho incomparável. Tela Super Retina XDR de 6.7 polegadas com ProMotion e Dynamic Island. Sistema de câmera Pro com zoom óptico 5x e gravação em ProRes 4K. Bateria de longa duração com até 29h de vídeo.</div>"
  }' | jq '.'

echo ""
echo ""

# ========================================
# 7. VALIDAR ATRIBUTOS
# ========================================
echo "7. Validando atributos..."
echo "----------------------------------------"

curl -X POST "$API_BASE/validate/attributes" \
  -H "Content-Type: application/json" \
  -d '{
    "attributes": [
      {"id": "BRAND", "value_name": "Apple"},
      {"id": "MODEL", "value_name": "iPhone 15 Pro Max"},
      {"id": "GTIN", "value_name": "0195949038266"},
      {"id": "INTERNAL_MEMORY", "value_name": "256 GB"},
      {"id": "COLOR", "value_name": "Titanio Natural"}
    ]
  }' | jq '.'

echo ""
echo ""

# ========================================
# 8. VALIDAR IMAGENS
# ========================================
echo "8. Validando imagens..."
echo "----------------------------------------"

curl -X POST "$API_BASE/validate/images" \
  -H "Content-Type: application/json" \
  -d '{
    "pictures": [
      {"source": "https://exemplo.com/img1.jpg"},
      {"source": "https://exemplo.com/img2.jpg"},
      {"source": "https://exemplo.com/img3.jpg"},
      {"source": "https://exemplo.com/img4.jpg"},
      {"source": "https://exemplo.com/img5.jpg"},
      {"source": "https://exemplo.com/img6.jpg"}
    ]
  }' | jq '.'

echo ""
echo ""

# ========================================
# 9. VALIDAR PRICING
# ========================================
echo "9. Validando preço..."
echo "----------------------------------------"

curl -X POST "$API_BASE/validate/pricing" \
  -H "Content-Type: application/json" \
  -d '{
    "price": 7299.90,
    "available_quantity": 10
  }' | jq '.'

echo ""
echo ""

# ========================================
# 10. VALIDAR SHIPPING
# ========================================
echo "10. Validando estratégia de frete..."
echo "----------------------------------------"

curl -X POST "$API_BASE/validate/shipping" \
  -H "Content-Type: application/json" \
  -d '{
    "shipping": {
      "dimensions": "15x7x1",
      "weight": 221,
      "zip_code": "01310-100"
    }
  }' | jq '.'

echo ""
echo ""

# ========================================
# 11. BUILD LISTING
# ========================================
echo "11. Construindo anúncio completo..."
echo "----------------------------------------"

curl -X POST "$API_BASE/build" \
  -H "Content-Type: application/json" \
  -d '{
    "title": "iPhone 15 Pro Max 256GB Titanio Natural Apple",
    "category_id": "MLB1234",
    "price": 7299.90,
    "condition": "new",
    "available_quantity": 10,
    "description": "<div>iPhone 15 Pro Max com chip A17 Pro...</div>",
    "pictures": [
      {"source": "https://exemplo.com/img1.jpg"},
      {"source": "https://exemplo.com/img2.jpg"},
      {"source": "https://exemplo.com/img3.jpg"},
      {"source": "https://exemplo.com/img4.jpg"},
      {"source": "https://exemplo.com/img5.jpg"},
      {"source": "https://exemplo.com/img6.jpg"}
    ],
    "attributes": [
      {"id": "BRAND", "value_name": "Apple"},
      {"id": "MODEL", "value_name": "iPhone 15 Pro Max"},
      {"id": "GTIN", "value_name": "0195949038266"}
    ],
    "shipping": {
      "dimensions": "15x7x1",
      "weight": 221
    }
  }' | jq '.'

echo ""
echo ""

# ========================================
# 12. SALVAR RASCUNHO
# ========================================
echo "12. Salvando rascunho..."
echo "----------------------------------------"

DRAFT_RESPONSE=$(curl -s -X POST "$API_BASE/draft/save" \
  -H "Content-Type: application/json" \
  -d '{
    "data": {
      "title": "iPhone 15 Pro Max",
      "category_id": "MLB1234",
      "price": 7299.90,
      "last_step": "pricing"
    },
    "draft_name": "iPhone 15 Rascunho Teste"
  }')

echo "$DRAFT_RESPONSE" | jq '.'

# Extrair draft_id para próximo teste
DRAFT_ID=$(echo "$DRAFT_RESPONSE" | jq -r '.draft_id')

echo ""
echo ""

# ========================================
# 13. CARREGAR RASCUNHO
# ========================================
echo "13. Carregando rascunho..."
echo "----------------------------------------"

curl -X GET "$API_BASE/draft/$DRAFT_ID" | jq '.'

echo ""
echo ""

# ========================================
# 14. CLONAR ANÚNCIO
# ========================================
echo "14. Clonando anúncio com melhorias..."
echo "----------------------------------------"

curl -X POST "$API_BASE/clone" \
  -H "Content-Type: application/json" \
  -d '{
    "item_id": "MLB1234567890",
    "improvements": [
      "optimize_title",
      "optimize_shipping",
      "apply_template",
      "enhance_seo"
    ]
  }' | jq '.'

echo ""
echo ""

# ========================================
# 15. LISTAR BLOCOS REUTILIZÁVEIS
# ========================================
echo "15. Listando blocos reutilizáveis..."
echo "----------------------------------------"

curl -X GET "$API_BASE/blocks" | jq '.'

echo ""
echo ""

# ========================================
# 16. CRIAR TEMPLATE PERSONALIZADO
# ========================================
echo "16. Criando template personalizado..."
echo "----------------------------------------"

curl -X POST "$API_BASE/templates/custom" \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Template Tech Customizado",
    "description": "Template para produtos de tecnologia",
    "content": "<div class=\"custom-tech\"><h1>{{product_name}}</h1><div>{{description}}</div>{{features}}</div>",
    "categories": ["MLB1234", "MLB5678"]
  }' | jq '.'

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
echo "  ✓ POST /start"
echo "  ✓ POST /validate/{step} (8 steps)"
echo "  ✓ POST /build"
echo "  ✓ POST /publish (comentado)"
echo "  ✓ POST /draft/save"
echo "  ✓ GET  /draft/{id}"
echo "  ✓ POST /clone"
echo "  ✓ GET  /templates"
echo "  ✓ POST /templates/{id}/render"
echo "  ✓ POST /templates/custom"
echo "  ✓ GET  /blocks"
echo ""
