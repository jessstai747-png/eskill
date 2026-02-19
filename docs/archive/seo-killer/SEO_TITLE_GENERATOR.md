# 🏷️ SEO Title Generator - Gerador Inteligente de Títulos

Sistema completo de geração, análise e otimização de títulos para anúncios do Mercado Livre com base em SEO, keywords de alta conversão e análise competitiva.

## 📑 Índice

1. [Visão Geral](#visão-geral)
2. [Componentes do Sistema](#componentes-do-sistema)
3. [API Endpoints](#api-endpoints)
4. [Guia de Uso](#guia-de-uso)
5. [Exemplos Práticos](#exemplos-práticos)
6. [Critérios de Avaliação](#critérios-de-avaliação)
7. [Boas Práticas](#boas-práticas)
8. [Referências ML](#referências-ml)

---

## 🎯 Visão Geral

O **SEO Title Generator** analisa categorias, pesquisa keywords, estuda concorrentes e gera títulos otimizados que maximizam:
- 🔍 **Visibilidade** - Ranking nas buscas do ML
- 👆 **CTR (Click-Through Rate)** - Taxa de cliques
- 💰 **Conversão** - Probabilidade de venda
- ✅ **Compliance** - Regras do Mercado Livre

### ✨ Características Principais

- **Geração Inteligente**: Cria múltiplos títulos otimizados automaticamente
- **Análise Completa**: 6 métricas de avaliação (comprimento, keywords, clareza, estrutura, termos proibidos, competitividade)
- **Variações Criativas**: Gera 10+ variações de qualquer título
- **A/B Testing**: Cria 3 variações específicas para testes
- **Comparação**: Compara múltiplos títulos lado a lado
- **Batch Analysis**: Analisa centenas de anúncios em lote
- **Quick Tips**: Sugestões rápidas de melhoria

### 🎓 Regras do Mercado Livre

| Regra | Limite | Ótimo |
|-------|--------|-------|
| **Comprimento** | Máx. 60 caracteres | 45-58 |
| **Termos Proibidos** | Não permitido | Zero |
| **Keywords** | No início | Primeiros 20 chars |
| **Estrutura** | Marca → Modelo → Specs | Lógica clara |

---

## 🧩 Componentes do Sistema

### 1. TitleGeneratorService

**Responsabilidade**: Gera títulos otimizados a partir de dados do produto.

**Funcionalidades**:
- Pesquisa keywords relevantes na categoria
- Analisa concorrentes de sucesso (top sellers)
- Extrai componentes essenciais (marca, modelo, specs)
- Gera múltiplas variações com diferentes estratégias
- Avalia e ranqueia por score SEO
- Filtra por score mínimo

**Métodos Principais**:

```php
// Gerar títulos a partir de dados
$generator = new TitleGeneratorService($accountId);
$result = $generator->generateTitles([
    'category_id' => 'MLB1234',
    'brand' => 'Apple',
    'model' => 'iPhone 15 Pro Max',
    'attributes' => [...],
], [
    'count' => 5,
    'optimize_for' => 'both', // both|conversion|ranking
    'min_score' => 70
]);

// Melhorar título de anúncio existente
$result = $generator->generateFromItem('MLB123456789', [
    'count' => 5,
    'optimize_for' => 'conversion'
]);

// Gerar variações de título
$result = $generator->generateVariationsFromTitle(
    'Samsung Galaxy S23 128GB',
    'MLB1234',
    ['count' => 5]
);
```

**Output Exemplo**:
```json
{
  "success": true,
  "generated_count": 5,
  "titles": [
    {
      "title": "iPhone 15 Pro Max 256GB Titanio Natural Apple",
      "score": 92,
      "length": 48,
      "optimal": true,
      "details": {
        "length": "✓ Comprimento ótimo: 48 caracteres",
        "keywords": "Keywords relevantes: 95%",
        "clarity": "Clareza: 90%"
      }
    }
  ],
  "best_title": {...},
  "insights": {
    "top_keywords": ["iphone 15", "pro max", "256gb"],
    "avg_competitor_length": 52,
    "common_patterns": [...]
  }
}
```

---

### 2. TitleAnalyzerService

**Responsabilidade**: Análise detalhada de títulos com múltiplas métricas.

**Métricas de Avaliação**:

| Métrica | Peso | Descrição |
|---------|------|-----------|
| **Comprimento** | 15% | 45-58 caracteres = ótimo |
| **Keywords** | 25% | Relevância e posicionamento |
| **Clareza** | 20% | Legibilidade e especificidade |
| **Estrutura** | 15% | Capitalização, pontuação, ordem |
| **Termos Proibidos** | 10% | Zero termos proibidos |
| **Competitividade** | 15% | Diferenciadores e specs |

**Métodos**:

```php
$analyzer = new TitleAnalyzerService($accountId);

// Análise completa
$analysis = $analyzer->analyzeTitle(
    'iPhone 15 Pro Max 256GB Titanio Natural Apple',
    'MLB1234'
);
```

**Output Completo**:
```json
{
  "title": "iPhone 15 Pro Max 256GB Titanio Natural Apple",
  "length": 48,
  "word_count": 7,
  "overall_score": 92,
  "status": "excellent",
  
  "length_analysis": {
    "score": 100,
    "status": "excellent",
    "message": "Comprimento ótimo para SEO (48 caracteres)",
    "chars_available": 12
  },
  
  "keyword_analysis": {
    "score": 95,
    "found_keywords": ["iphone 15", "pro max", "256gb"],
    "keywords_in_first_15_chars": 2,
    "high_impact_words": ["Pro", "Max"],
    "technical_specs": ["256GB"],
    "has_brand": true,
    "has_model": true
  },
  
  "clarity_analysis": {
    "score": 90,
    "optimal_word_count": true,
    "readability": "easy"
  },
  
  "structure_analysis": {
    "score": 95,
    "starts_capitalized": true,
    "logical_structure": true
  },
  
  "forbidden_words_analysis": {
    "score": 100,
    "status": "safe",
    "found_forbidden": [],
    "message": "Nenhum termo proibido detectado"
  },
  
  "competitive_analysis": {
    "score": 85,
    "differentiators": ["Pro", "Max"],
    "uniqueness_level": "high"
  },
  
  "seo_analysis": {
    "score": 90,
    "factors": [
      "Marca no início (+15)",
      "Comprimento ótimo (+20)",
      "Especificações técnicas (+15)"
    ],
    "optimization_level": "excellent"
  },
  
  "performance_estimate": {
    "performance_score": 88,
    "click_through_rate_estimate": "high",
    "conversion_probability": "high",
    "ranking_potential": "excellent",
    "estimated_views": "500-1000+ por semana",
    "estimated_clicks": "50-100+ por semana"
  },
  
  "issues": [],
  "suggestions": [
    "Excelente! Título otimizado para SEO",
    "Comprimento ideal: 48 caracteres"
  ]
}
```

---

### 3. TitleVariationsService

**Responsabilidade**: Gera variações criativas de títulos existentes.

**Estratégias de Variação**:

1. **Reordenação**: Muda ordem dos componentes
2. **Sinônimos**: Substitui palavras por sinônimos
3. **Modificadores**: Adiciona/remove qualificadores (Pro, Max, Premium)
4. **Compressão**: Abrevia palavras longas
5. **Expansão**: Expande abreviações
6. **A/B Testing**: 3 variações específicas (SEO, Conversão, Specs)

**Métodos**:

```php
$variations = new TitleVariationsService();

// Gerar variações gerais
$result = $variations->generateVariations(
    'Samsung Galaxy S23 128GB',
    [
        'count' => 10,
        'category_id' => 'MLB1234',
        'strategy' => 'all', // all|conservative|aggressive
        'min_score' => 60
    ]
);

// Gerar variações para A/B testing
$abResult = $variations->generateABTestingVariations(
    'Samsung Galaxy S23 128GB',
    ['category_id' => 'MLB1234']
);
```

**Output A/B Testing**:
```json
{
  "success": true,
  "original_title": "Samsung Galaxy S23 128GB",
  "ab_variations": [
    {
      "type": "A",
      "title": "128GB Samsung Galaxy S23 Smartphone",
      "focus": "SEO / Ranking",
      "description": "Keywords no início para melhor posicionamento",
      "score": 85,
      "estimated_ctr": "high"
    },
    {
      "type": "B",
      "title": "Samsung Galaxy S23 128GB 5G Camera 50MP",
      "focus": "Conversão / Confiança",
      "description": "Marca em destaque com specs completas",
      "score": 88,
      "estimated_ctr": "high"
    },
    {
      "type": "C",
      "title": "Galaxy S23 128GB 5G 8GB RAM 50MP Samsung",
      "focus": "Especificações / Clareza",
      "description": "Máximo de informações técnicas",
      "score": 82,
      "estimated_ctr": "good"
    }
  ],
  "recommendation": {
    "recommended_type": "B",
    "reason": "Melhor score (88) e Conversão / Confiança"
  }
}
```

---

## 📡 API Endpoints

### Base URL
```
/api/title-generator
```

---

### 1. Gerar Títulos

**Endpoint**: `POST /api/title-generator/generate`

**Request Body**:
```json
{
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
}
```

**Response**:
```json
{
  "success": true,
  "generated_count": 5,
  "titles": [
    {
      "title": "iPhone 15 Pro Max 256GB Titanio Natural Apple",
      "score": 92,
      "length": 48,
      "optimal": true
    }
  ],
  "best_title": {...},
  "insights": {...}
}
```

---

### 2. Melhorar Título de Anúncio

**Endpoint**: `POST /api/title-generator/improve/{itemId}`

**Request Body**:
```json
{
  "count": 5,
  "optimize_for": "conversion"
}
```

**Response**:
```json
{
  "success": true,
  "original_title": "iPhone 15 Pro",
  "generated_count": 5,
  "titles": [...],
  "improvement": {
    "original_score": 68,
    "best_score": 92,
    "score_gain": 24
  }
}
```

---

### 3. Analisar Título

**Endpoint**: `POST /api/title-generator/analyze`

**Request Body**:
```json
{
  "title": "iPhone 15 Pro Max 256GB Titanio Natural Apple",
  "category_id": "MLB1234"
}
```

**Response**: Análise completa (veja Output do TitleAnalyzerService acima)

---

### 4. Gerar Variações

**Endpoint**: `POST /api/title-generator/variations`

**Request Body**:
```json
{
  "title": "Samsung Galaxy S23 128GB",
  "category_id": "MLB1234",
  "count": 10,
  "strategy": "all"
}
```

**Response**:
```json
{
  "success": true,
  "original_title": "Samsung Galaxy S23 128GB",
  "original_score": 75,
  "variations_generated": 24,
  "variations_suitable": 18,
  "variations": [
    {
      "title": "Samsung Galaxy S23 128GB 5G Camera 50MP",
      "score": 88,
      "strategy": "expansion",
      "improvements": {
        "score_change": 13
      }
    }
  ]
}
```

---

### 5. Variações A/B Testing

**Endpoint**: `POST /api/title-generator/ab-testing`

**Request Body**:
```json
{
  "title": "Samsung Galaxy S23 128GB",
  "category_id": "MLB1234"
}
```

**Response**: (veja Output A/B Testing acima)

---

### 6. Comparar Títulos

**Endpoint**: `POST /api/title-generator/compare`

**Request Body**:
```json
{
  "titles": [
    "iPhone 15 Pro Max 256GB",
    "Apple iPhone 15 Pro Max Titanio",
    "256GB iPhone 15 Pro Max Apple"
  ],
  "category_id": "MLB1234"
}
```

**Response**:
```json
{
  "success": true,
  "total_titles": 3,
  "comparisons": [
    {
      "title": "iPhone 15 Pro Max 256GB",
      "score": 85,
      "length": 26,
      "status": "excellent",
      "seo_score": 82
    },
    {
      "title": "Apple iPhone 15 Pro Max Titanio",
      "score": 88,
      "length": 34,
      "status": "excellent",
      "seo_score": 90
    },
    {
      "title": "256GB iPhone 15 Pro Max Apple",
      "score": 80,
      "length": 30,
      "status": "good",
      "seo_score": 85
    }
  ],
  "best_title": {
    "title": "Apple iPhone 15 Pro Max Titanio",
    "score": 88
  }
}
```

---

### 7. Otimizar Título

**Endpoint**: `POST /api/title-generator/optimize`

**Request Body**:
```json
{
  "title": "iPhone 15",
  "category_id": "MLB1234",
  "count": 5
}
```

**Response**:
```json
{
  "success": true,
  "original_title": "iPhone 15",
  "current_analysis": {
    "overall_score": 52,
    "issues": ["Título muito curto", "Falta especificações"],
    "suggestions": ["Adicione modelo (Pro/Max)", "Inclua memória"]
  },
  "optimized_variations": [
    {
      "title": "iPhone 15 Pro Max 256GB Titanio Natural Apple",
      "score": 92,
      "improvements": {
        "score_change": 40
      }
    }
  ],
  "best_improvement": {
    "title": "iPhone 15 Pro Max 256GB Titanio Natural Apple",
    "score_gain": 40,
    "new_score": 92
  }
}
```

---

### 8. Análise em Lote

**Endpoint**: `POST /api/title-generator/batch/analyze`

**Request Body**:
```json
{
  "item_ids": [
    "MLB1234567890",
    "MLB2345678901",
    "MLB3456789012"
  ]
}
```

**Response**:
```json
{
  "success": true,
  "total_analyzed": 3,
  "average_score": 76,
  "needs_improvement_count": 1,
  "needs_improvement_items": ["MLB3456789012"],
  "results": [
    {
      "item_id": "MLB1234567890",
      "original_title": "iPhone 15 Pro Max 256GB",
      "current_score": 85,
      "best_alternative": {
        "title": "iPhone 15 Pro Max 256GB Titanio Natural Apple",
        "score": 92
      },
      "potential_improvement": 7
    }
  ]
}
```

---

### 9. Quick Tips

**Endpoint**: `GET /api/title-generator/quick-tips?title={title}`

**Response**:
```json
{
  "success": true,
  "tips": {
    "score": 68,
    "status": "fair",
    "quick_fixes": [
      "Adicione mais 15 caracteres para comprimento ótimo",
      "Inclua especificações técnicas (ex: 256GB, 48MP)",
      "Adicione diferenciadores (Pro, Max, Premium, etc.)"
    ],
    "critical_issues": [],
    "length_info": {
      "current": 30,
      "optimal": "45-58",
      "available_chars": 30
    }
  }
}
```

---

## 📚 Guia de Uso

### Caso 1: Criar Título para Novo Produto

```bash
# 1. Gerar títulos
curl -X POST http://localhost:8000/api/title-generator/generate \
  -H "Content-Type: application/json" \
  -d '{
    "category_id": "MLB1234",
    "brand": "Samsung",
    "model": "Galaxy S23",
    "attributes": [
      {"id": "INTERNAL_MEMORY", "value_name": "128 GB"},
      {"id": "COLOR", "value_name": "Preto"}
    ],
    "options": {
      "count": 5,
      "optimize_for": "both"
    }
  }'

# 2. Escolher melhor título do resultado
# best_title: "Samsung Galaxy S23 128GB Preto 5G Camera 50MP"
```

---

### Caso 2: Melhorar Título Existente

```bash
# 1. Analisar título atual
curl -X POST http://localhost:8000/api/title-generator/analyze \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Celular Samsung S23",
    "category_id": "MLB1234"
  }'

# Score: 58 (fair) - precisa melhorias

# 2. Otimizar
curl -X POST http://localhost:8000/api/title-generator/optimize \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Celular Samsung S23",
    "category_id": "MLB1234",
    "count": 5
  }'

# best_improvement: "Samsung Galaxy S23 128GB 5G Camera 50MP" (score: 88)
```

---

### Caso 3: A/B Testing

```bash
# Gerar 3 variações para teste
curl -X POST http://localhost:8000/api/title-generator/ab-testing \
  -H "Content-Type: application/json" \
  -d '{
    "title": "Samsung Galaxy S23 128GB",
    "category_id": "MLB1234"
  }'

# Resultado:
# A: "128GB Samsung Galaxy S23 5G" (SEO focus)
# B: "Samsung Galaxy S23 128GB Camera 50MP" (Conversion focus)
# C: "Galaxy S23 128GB 5G 8GB RAM Samsung" (Specs focus)

# Recomendação: B (melhor score + conversão)
```

---

### Caso 4: Comparar Múltiplos Títulos

```bash
curl -X POST http://localhost:8000/api/title-generator/compare \
  -H "Content-Type: application/json" \
  -d '{
    "titles": [
      "Samsung Galaxy S23",
      "Samsung Galaxy S23 128GB",
      "Galaxy S23 128GB 5G Samsung"
    ],
    "category_id": "MLB1234"
  }'

# best_title: "Samsung Galaxy S23 128GB" (score: 82)
```

---

### Caso 5: Análise em Lote de Loja

```bash
# Analisar todos anúncios
curl -X POST http://localhost:8000/api/title-generator/batch/analyze \
  -H "Content-Type: application/json" \
  -d '{
    "item_ids": [
      "MLB1234567890",
      "MLB2345678901",
      "MLB3456789012",
      "MLB4567890123"
    ]
  }'

# Resultado:
# - average_score: 72
# - needs_improvement: 2 anúncios (< 70)
# - potential_improvement: +18 pontos em média
```

---

## 📊 Critérios de Avaliação

### Score Ranges

| Score | Status | Descrição |
|-------|--------|-----------|
| 85-100 | 🌟 Excelente | Otimizado profissionalmente |
| 70-84 | ✅ Bom | Aceitável, pequenas melhorias |
| 60-69 | ⚠️ Regular | Necessita otimização |
| 50-59 | ❌ Ruim | Muitas melhorias necessárias |
| 0-49 | 🚫 Crítico | Recriar título |

### Termos Proibidos

❌ **NUNCA USE**:
- `original`, `genuíno`, `autêntico`, `oficial` (sem autorização)
- `melhor`, `top`, `número 1`, `#1`
- `mais barato`, `menor preço`, `promoção`
- `frete grátis`, `entrega grátis` (informar no anúncio, não título)
- `novo`, `lacrado` (apenas se realmente for)

### Estrutura Ideal

```
[MARCA] [MODELO] [PRINCIPAL_SPEC] [SPEC_ADICIONAL] [COR/VERSÃO]
```

**Exemplos Ótimos**:
- ✓ `iPhone 15 Pro Max 256GB Titanio Natural Apple` (48 chars, score: 92)
- ✓ `Samsung Galaxy S23 128GB 5G Camera 50MP Preto` (51 chars, score: 88)
- ✓ `Notebook Dell Inspiron i5 8GB 256GB SSD 15.6"` (53 chars, score: 90)

**Exemplos Ruins**:
- ✗ `Celular` (6 chars, score: 15 - muito vago)
- ✗ `Original iPhone Novo Melhor Preço Garantido` (47 chars, score: 0 - termos proibidos)
- ✗ `Super Mega Produto Top Profissional Premium Best` (52 chars, score: 25 - spam)

---

## 💡 Boas Práticas

### 1. Sempre Use Keywords Relevantes

✅ **FAÇA**: `iPhone 15 Pro Max 256GB Titanio Natural Apple`  
❌ **NÃO FAÇA**: `Smartphone Apple Lindo Premium Top`

### 2. Especificações no Título

✅ **FAÇA**: `Notebook Dell i5 8GB 256GB SSD 15.6"`  
❌ **NÃO FAÇA**: `Notebook Dell Ótimo para Trabalho`

### 3. Comprimento Otimizado

✅ **FAÇA**: 45-58 caracteres  
❌ **NÃO FAÇA**: < 30 ou > 60 caracteres

### 4. Marca no Início (geralmente)

✅ **FAÇA**: `Samsung Galaxy S23 128GB 5G`  
⚠️ **ALTERNATIVA**: `128GB Samsung Galaxy S23 5G` (A/B test para SEO)

### 5. Teste Variações

- Crie 3-5 variações
- Use A/B testing do ML
- Monitore CTR e conversão
- Ajuste baseado em dados

### 6. Análise Regular

- Analise títulos mensalmente
- Compare com concorrentes
- Atualize conforme tendências
- Use batch analysis para loja completa

### 7. Evite Excesso

✅ **FAÇA**: `Nike Air Max 90 Branco Masculino 42`  
❌ **NÃO FAÇA**: `Nike Air Max 90 Branco Masculino 42 Lindo Top Premium Novo`

---

## 📖 Referências ML

### Documentação Oficial

1. **Quality Guidelines - Títulos**
   - https://developers.mercadolivre.com.br/pt_br/quality-guidelines
   - Regras de títulos e termos proibidos

2. **SEO Best Practices**
   - https://developers.mercadolivre.com.br/pt_br/boas-praticas-seo
   - Otimização para busca interna

3. **Items API**
   - https://developers.mercadolivre.com.br/pt_br/itens-e-buscas
   - Estrutura de anúncios

### Checklist de Título Perfeito

- [ ] 45-58 caracteres
- [ ] Marca reconhecível no início
- [ ] Modelo específico
- [ ] 2-3 especificações técnicas
- [ ] Zero termos proibidos
- [ ] Primeira letra maiúscula
- [ ] Sem pontuação excessiva
- [ ] Keywords relevantes
- [ ] Diferenciadores (Pro/Max/Plus)
- [ ] Testado com analyzer (score > 85)

---

## 🎯 Performance Esperada

Com títulos otimizados (score > 85):

| Métrica | Melhoria Esperada |
|---------|-------------------|
| **CTR** | +25-40% |
| **Conversão** | +15-30% |
| **Ranking** | +10-20 posições |
| **Visualizações** | +30-50% |

---

## 📞 Suporte

**Documentação**: `/docs/SEO_TITLE_GENERATOR.md`  
**Exemplos**: `/examples/title_generator_example.php`  
**Testes**: `/examples/title_generator_test.sh`

---

**Desenvolvido com ❤️ para maximizar vendas no Mercado Livre**
