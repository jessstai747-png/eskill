# 🤖 Guia Completo: AI-Powered Insights v2.0.0

**Versão:** 2.0.0  
**Data:** 31 de Dezembro de 2025  
**Status:** 🟢 Production Ready  
**Powered by:** OpenAI GPT-4 Turbo & Machine Learning

---

## 📋 Índice

1. [Visão Geral](#visão-geral)
2. [Arquitetura](#arquitetura)
3. [Configuração](#configuração)
4. [Serviços Disponíveis](#serviços-disponíveis)
5. [API Reference](#api-reference)
6. [Exemplos de Uso](#exemplos-de-uso)
7. [Custos e Otimização](#custos-e-otimização)
8. [Troubleshooting](#troubleshooting)
9. [Melhores Práticas](#melhores-práticas)

---

## 🎯 Visão Geral

O módulo **AI-Powered Insights v2.0.0** adiciona inteligência artificial avançada ao sistema usando GPT-4 Turbo da OpenAI e algoritmos de Machine Learning para:

### Principais Recursos:

1. **📊 Strategic Insights** - Análises estratégicas com GPT-4
   - Avaliação completa da conta
   - Identificação de oportunidades de crescimento
   - Recomendações priorizadas por impacto
   - Análise de sentimento de mercado
   - Sugestões de testes A/B

2. **🖼️ Image Analysis** - Computer Vision para análise de imagens
   - Análise técnica (resolução, formato, qualidade)
   - Comparação com best practices do ML
   - Detecção de problemas (watermarks, baixa resolução)
   - Sugestão de ordem ótima de imagens
   - Identificação de duplicatas

3. **💰 Dynamic Pricing** - Otimização de preços com ML
   - 4 estratégias de precificação (penetração, competitiva, premium, margem)
   - Análise de elasticidade de preço
   - Otimização de margem de lucro
   - Regras de precificação dinâmica
   - Forecasting de receita

4. **💬 AI Chatbot** - Assistente conversacional
   - Chat contextual com histórico
   - Explicação de métricas em linguagem natural
   - Ajuda com funcionalidades
   - Sugestões proativas de ações

---

## 🏗️ Arquitetura

```
┌─────────────────────────────────────────────────────┐
│                  AIController                       │
│              (20+ API Endpoints)                    │
└──────────────┬──────────────────────────────────────┘
               │
       ┌───────┴────────┬──────────┬──────────┐
       │                │          │          │
┌──────▼──────┐ ┌──────▼─────┐ ┌─▼────────┐ ┌▼─────────┐
│AIInsights   │ │AIImage     │ │AIPricing │ │AIChatbot │
│Service      │ │Analyzer    │ │Optimizer │ │Service   │
└──────┬──────┘ └──────┬─────┘ └─┬────────┘ └┬─────────┘
       │                │          │           │
       └────────────────┴──────────┴───────────┘
                         │
                  ┌──────▼───────┐
                  │  OpenAI API  │
                  │  GPT-4 Turbo │
                  └──────────────┘
```

### Componentes:

- **AIController** - 20+ endpoints REST organizados por serviço
- **4 Services** - Lógica de negócio especializada
- **OpenAI Integration** - Chamadas diretas à API GPT-4 Turbo
- **Database** - 3 novas tabelas para histórico e configurações

---

## ⚙️ Configuração

### 1. Variáveis de Ambiente

Adicione ao seu arquivo `.env`:

```env
# OpenAI API Configuration
OPENAI_API_KEY=sk-proj-xxxxxxxxxxxxxxxxxxxxxxxxxxxxx
OPENAI_MODEL=gpt-4-turbo-preview
OPENAI_MAX_TOKENS=2000
OPENAI_TEMPERATURE=0.7
OPENAI_TIMEOUT=30

# Optional: Rate Limiting
OPENAI_RATE_LIMIT_PER_MINUTE=60
```

### 2. Database Migrations

Execute as migrations para criar as tabelas necessárias:

```bash
php bin/migrate-ai-tables.php
```

Ou manualmente:

```bash
mysql -u root -p eskill < database/migrations/2025_01_01_000001_create_ai_insights_history.sql
mysql -u root -p eskill < database/migrations/2025_01_01_000002_create_chatbot_conversations.sql
mysql -u root -p eskill < database/migrations/2025_01_01_000003_create_pricing_rules.sql
```

### 3. Validação da Configuração

Teste se a API está funcionando:

```bash
curl -X POST https://seu-dominio.com/api/ai/chat \
  -H "Content-Type: application/json" \
  -d '{"message": "Hello, can you help me?"}'
```

---

## 🛠️ Serviços Disponíveis

### 1. AIInsightsService

**Objetivo:** Gerar insights estratégicos usando GPT-4.

**Métodos Principais:**
- `generateStrategicInsights()` - Análise completa da conta
- `suggestABTests()` - Sugestões de testes A/B
- `analyzeTrends()` - Detecção de padrões e tendências
- `explainMetric()` - Explicações em linguagem natural
- `getPrioritizedRecommendations()` - Ações priorizadas
- `analyzeMarketSentiment()` - Sentimento do mercado

**Casos de Uso:**
- Dashboard executivo com insights automáticos
- Planejamento estratégico mensal
- Identificação de oportunidades de crescimento
- Análise de risco e mitigação

---

### 2. AIImageAnalyzer

**Objetivo:** Analisar qualidade de imagens com computer vision.

**Métodos Principais:**
- `analyzeProductImages()` - Análise completa de set de imagens
- `analyzeImage()` - Análise individual detalhada
- `compareWithBestPractices()` - Compliance com padrões ML
- `suggestOptimalOrder()` - Ordem ótima de exibição
- `detectSimilarImages()` - Identificação de duplicatas

**Métricas Analisadas:**
- Resolução (mínimo 1200px, ideal 2000px)
- Formato (JPEG, PNG, WebP)
- Aspect ratio (ideal 1:1 para ML)
- Qualidade (iluminação, fundo, centralização)
- Compliance (watermarks, texto sobreposto)

**Casos de Uso:**
- Auditoria de qualidade de catálogo
- Otimização antes de publicar novos produtos
- Identificação de imagens para substituir
- Melhoria de CTR nas buscas

---

### 3. AIPricingOptimizer

**Objetivo:** Otimização dinâmica de preços com ML.

**Métodos Principais:**
- `suggestOptimalPrice()` - Sugestão de preço ideal
- `analyzePriceElasticity()` - Elasticidade de demanda
- `optimizeMargin()` - Maximização de margem
- `createDynamicPricingRules()` - Regras automatizadas
- `analyzeCompetitivePricing()` - Posicionamento competitivo
- `forecastRevenue()` - Previsão de receita

**Estratégias Disponíveis:**
1. **Penetration** - 5% abaixo do menor concorrente (volume)
2. **Competitive** - Média de mercado (equilibrado)
3. **Premium** - 5% acima do maior concorrente (margem)
4. **Margin-Based** - 30% de margem fixa (rentabilidade)

**Casos de Uso:**
- Repricing automático baseado em concorrência
- Análise de impacto antes de mudar preços
- Identificação de produtos com elasticidade alta
- Maximização de receita total

---

### 4. AIChatbotService

**Objetivo:** Assistente conversacional contextual.

**Métodos Principais:**
- `chat()` - Conversa com histórico
- `explainMetric()` - Explicação rápida de métrica
- `helpWithFeature()` - Ajuda com funcionalidade
- `suggestNextActions()` - Sugestões proativas

**Contextos Suportados:**
- Dashboard (visão geral de conta)
- SEO Killer (otimizações SEO)
- Bulk Optimizer (operações em lote)
- Reports (análise de relatórios)

**Casos de Uso:**
- Onboarding de novos usuários
- Suporte in-app contextual
- Explicação de dados complexos
- Guia de melhores práticas

---

## 📚 API Reference

### Insights Endpoints

#### POST `/api/ai/insights/strategic`

Gera análise estratégica completa da conta.

**Request:**
```json
{
  "include_opportunities": true,
  "include_risks": true,
  "include_next_steps": true
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "overall_assessment": "Sua conta está performando acima da média...",
    "strengths": [
      "Taxa de conversão 15% acima do mercado",
      "Reputação de vendedor excelente (99% positivo)"
    ],
    "weaknesses": [
      "Score SEO médio abaixo de 70",
      "33% dos produtos sem descrição otimizada"
    ],
    "opportunities": [
      {
        "title": "Otimizar Títulos de Top Sellers",
        "description": "Seus 10 produtos mais vendidos têm score SEO <60",
        "impact": "high",
        "effort": "low"
      }
    ],
    "risks": [
      "Concorrência aumentou preços em 8% no último mês"
    ],
    "next_steps": [
      {
        "action": "Rodar Bulk Optimizer nos top 20 produtos",
        "priority": "high",
        "effort": "low",
        "expected_impact": "Aumento de 10-15% em visualizações"
      }
    ],
    "confidence": 85
  },
  "timestamp": "2025-01-01 10:30:00"
}
```

---

#### POST `/api/ai/insights/ab-tests`

Sugere testes A/B baseados em performance atual.

**Request:**
```json
{
  "focus_area": "title"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "suggested_tests": [
      {
        "name": "Teste de Título com Keywords de Long-Tail",
        "description": "Testar títulos com keywords mais específicas vs genéricas",
        "variant_a": {
          "type": "current",
          "example": "Notebook Dell Inspiron 15"
        },
        "variant_b": {
          "type": "optimized",
          "example": "Notebook Dell Inspiron 15 i5 8GB SSD 256GB Tela Full HD"
        },
        "expected_impact": "high",
        "recommended_duration_days": 14,
        "success_metrics": ["ctr", "views", "conversions"]
      }
    ]
  }
}
```

---

#### GET `/api/ai/insights/trends?days=30`

Analisa tendências em dados históricos.

**Response:**
```json
{
  "success": true,
  "data": {
    "rising_trends": [
      {
        "metric": "avg_seo_score",
        "change_percentage": 12.5,
        "interpretation": "SEO melhorando consistentemente"
      }
    ],
    "declining_trends": [
      {
        "metric": "conversion_rate",
        "change_percentage": -3.2,
        "interpretation": "Quedas podem indicar problema de preço ou concorrência"
      }
    ],
    "seasonal_patterns": [
      {
        "pattern": "Pico de vendas toda segunda-feira",
        "recommendation": "Agende promoções para segundas"
      }
    ],
    "anomalies": [
      {
        "date": "2025-12-25",
        "metric": "views",
        "value": 450,
        "expected": 1200,
        "reason": "Feriado - tráfego baixo"
      }
    ],
    "forecast_30_days": {
      "avg_seo_score": 78.5,
      "confidence": 72
    }
  }
}
```

---

### Image Analysis Endpoints

#### POST `/api/ai/images/analyze`

Analisa todas as imagens de um produto.

**Request:**
```json
{
  "item_id": "MLB3456789012"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "item_id": "MLB3456789012",
    "overall_score": 76,
    "images": [
      {
        "url": "https://http2.mlstatic.com/...",
        "position": 0,
        "score": 85,
        "resolution": 1800,
        "format": "JPEG",
        "issues": [],
        "status": "good"
      },
      {
        "url": "https://http2.mlstatic.com/...",
        "position": 1,
        "score": 55,
        "resolution": 800,
        "format": "PNG",
        "issues": [
          {
            "type": "low_resolution",
            "severity": "warning",
            "message": "Resolução abaixo de 1200px",
            "fix": "Substituir por imagem HD"
          }
        ],
        "status": "warning"
      }
    ],
    "recommendations": [
      "Adicione mais 2 imagens (mínimo 6)",
      "Substitua imagens com resolução <1200px",
      "Use fundo branco na primeira imagem"
    ],
    "compliance": {
      "min_images": false,
      "min_resolution": false,
      "no_watermarks": true,
      "square_format": true
    }
  }
}
```

---

### Pricing Endpoints

#### POST `/api/ai/pricing/suggest`

Sugere preço ótimo para um produto.

**Request:**
```json
{
  "item_id": "MLB3456789012",
  "goal": "balanced"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "item_id": "MLB3456789012",
    "current_price": 149.90,
    "suggested_price": 139.90,
    "strategy": "competitive",
    "reasoning": "Preço competitivo alinhado com média de mercado",
    "market_position": "above_average",
    "competitors": {
      "min": 129.90,
      "max": 159.90,
      "avg": 139.50,
      "your_price": 149.90
    },
    "expected_results": {
      "volume_change": "+15%",
      "revenue_change": "+8%",
      "margin_percentage": 28
    },
    "confidence": 85,
    "alternatives": [
      {
        "strategy": "penetration",
        "price": 123.41,
        "goal": "Ganhar market share"
      },
      {
        "strategy": "premium",
        "price": 167.90,
        "goal": "Maximizar margem"
      }
    ]
  }
}
```

---

#### POST `/api/ai/pricing/elasticity`

Analisa elasticidade de preço.

**Request:**
```json
{
  "item_id": "MLB3456789012"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "elasticity_coefficient": -1.2,
    "interpretation": "elastic",
    "explanation": "Produto é sensível a preço. Redução de 10% aumenta vendas em ~12%",
    "scenarios": [
      {
        "price_change": "-20%",
        "expected_volume_change": "+24%",
        "net_revenue_effect": "+3%"
      },
      {
        "price_change": "+10%",
        "expected_volume_change": "-12%",
        "net_revenue_effect": "-3%"
      }
    ],
    "recommendation": "Considere competir agressivamente no preço. Produto responde bem a descontos."
  }
}
```

---

### Chatbot Endpoints

#### POST `/api/ai/chat`

Conversa com o assistente de IA.

**Request:**
```json
{
  "message": "Como posso melhorar minhas vendas?",
  "context": {
    "page": "dashboard",
    "account_metrics": {
      "avg_seo_score": 65,
      "conversion_rate": 2.3
    }
  }
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "message": "Baseado nos seus dados, identifico 3 oportunidades principais:\n\n1. **Otimizar SEO (Impacto Alto):** Seu score médio é 65, abaixo da meta de 80+. Produtos com score >80 convertem 40% melhor.\n\n2. **Melhorar Imagens:** 45% dos seus produtos têm menos de 6 imagens. Adicionar imagens aumenta CTR em até 25%.\n\n3. **Revisar Preços:** 12 produtos estão acima da média do mercado sem justificativa de valor.\n\nQuer que eu te ajude com alguma dessas áreas?",
    "suggested_actions": [
      "Rodar Bulk Optimizer nos produtos com score <70",
      "Adicionar mais imagens aos top sellers",
      "Analisar pricing competitivo"
    ],
    "conversation_id": "conv_abc123",
    "timestamp": "2025-01-01 10:30:00"
  }
}
```

---

## 💡 Exemplos de Uso

### Exemplo 1: Dashboard Executivo com Insights

```javascript
// Fetch strategic insights
async function loadDashboardInsights() {
  const response = await fetch('/api/ai/insights/strategic', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({
      include_opportunities: true,
      include_risks: true,
      include_next_steps: true
    })
  });
  
  const {data} = await response.json();
  
  // Render insights
  document.getElementById('overall-assessment').textContent = data.overall_assessment;
  
  // Render opportunities
  data.opportunities.forEach(opp => {
    if (opp.impact === 'high') {
      renderOpportunityCard(opp);
    }
  });
  
  // Render next steps
  const highPrioritySteps = data.next_steps.filter(s => s.priority === 'high');
  renderActionList(highPrioritySteps);
}
```

---

### Exemplo 2: Análise de Imagens antes de Publicar

```javascript
async function analyzeBeforePublish(itemId) {
  const response = await fetch('/api/ai/images/analyze', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({item_id: itemId})
  });
  
  const {data} = await response.json();
  
  // Check if ready to publish
  if (data.overall_score >= 80 && data.compliance.min_images) {
    showSuccessMessage('✅ Imagens aprovadas! Pode publicar.');
  } else {
    // Show issues
    const criticalIssues = data.images
      .flatMap(img => img.issues)
      .filter(issue => issue.severity === 'critical');
    
    showWarningMessage(`⚠️ ${criticalIssues.length} problemas críticos encontrados`);
    renderIssueList(criticalIssues);
  }
}
```

---

### Exemplo 3: Otimização de Preço com Elasticidade

```javascript
async function optimizePricing(itemId) {
  // First, analyze elasticity
  const elasticityResponse = await fetch('/api/ai/pricing/elasticity', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({item_id: itemId})
  });
  
  const elasticity = await elasticityResponse.json();
  
  // Determine goal based on elasticity
  let goal = 'balanced';
  if (elasticity.data.interpretation === 'highly_elastic') {
    goal = 'volume';  // Price sensitive - compete on price
  } else if (elasticity.data.interpretation === 'inelastic') {
    goal = 'profit';  // Not price sensitive - maximize margin
  }
  
  // Get price suggestion
  const priceResponse = await fetch('/api/ai/pricing/suggest', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({item_id: itemId, goal})
  });
  
  const pricing = await priceResponse.json();
  
  // Show recommendation
  showPriceRecommendation({
    current: pricing.data.current_price,
    suggested: pricing.data.suggested_price,
    strategy: pricing.data.strategy,
    expected_impact: pricing.data.expected_results
  });
}
```

---

### Exemplo 4: Chatbot Contextual

```javascript
class AIChatWidget {
  constructor() {
    this.conversationId = null;
  }
  
  async sendMessage(message) {
    const context = {
      page: window.location.pathname,
      feature: this.getCurrentFeature(),
      account_metrics: this.getAccountMetrics()
    };
    
    const response = await fetch('/api/ai/chat', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({message, context})
    });
    
    const {data} = await response.json();
    this.conversationId = data.conversation_id;
    
    // Render response
    this.renderMessage('bot', data.message);
    
    // Show suggested actions as quick replies
    if (data.suggested_actions) {
      this.renderQuickReplies(data.suggested_actions);
    }
  }
  
  getCurrentFeature() {
    // Detect which feature user is using
    if (window.location.pathname.includes('seo-killer')) return 'seo-killer';
    if (window.location.pathname.includes('bulk-optimizer')) return 'bulk-optimizer';
    return 'dashboard';
  }
}
```

---

## 💰 Custos e Otimização

### Estimativa de Custos OpenAI

**Modelo:** GPT-4 Turbo (gpt-4-turbo-preview)  
**Preço:** $0.01 / 1K tokens (input) + $0.03 / 1K tokens (output)

**Consumo Médio por Operação:**

| Operação | Tokens Input | Tokens Output | Custo/Call |
|----------|-------------|---------------|------------|
| Strategic Insights | ~800 | ~500 | ~$0.023 |
| A/B Test Suggestions | ~600 | ~400 | ~$0.018 |
| Explain Metric | ~200 | ~150 | ~$0.007 |
| Chat Message | ~300 | ~200 | ~$0.009 |
| Market Sentiment | ~500 | ~300 | ~$0.014 |

**Consumo Estimado Mensal (1000 usuários ativos):**

- Strategic Insights: 1x/semana/usuário = 4000 calls × $0.023 = **$92/mês**
- Chat Messages: 10x/semana/usuário = 40000 calls × $0.009 = **$360/mês**
- Other Operations: ~20000 calls × $0.015 avg = **$300/mês**

**Total Estimado:** ~$750/mês para 1000 usuários ativos

---

### Estratégias de Otimização de Custos

#### 1. Caching Inteligente

```php
// Cache insights for 24h
$cacheKey = "insights_strategic_{$accountId}_" . date('Y-m-d');
$cached = $cache->get($cacheKey);

if ($cached) {
    return $cached;
}

$insights = $this->callGPT4($prompt);
$cache->set($cacheKey, $insights, 86400); // 24h
```

#### 2. Rate Limiting por Usuário

```php
// Limit to 50 AI calls per day per user
$dailyLimit = 50;
$count = $redis->incr("ai_calls:{$accountId}:" . date('Y-m-d'));
$redis->expire("ai_calls:{$accountId}:" . date('Y-m-d'), 86400);

if ($count > $dailyLimit) {
    throw new \Exception('Daily AI limit reached');
}
```

#### 3. Batch Processing

```php
// Process multiple items in single GPT-4 call
$items = array_slice($allItems, 0, 10); // Max 10 per call
$prompt = "Analyze these products:\n" . json_encode($items);
```

#### 4. Token Optimization

```php
// Use shorter prompts when possible
$systemPrompt = "You are an expert ML SEO analyst. Be concise.";

// Limit output tokens
$options = [
    'max_tokens' => 500,  // Instead of 2000
    'temperature' => 0.7
];
```

#### 5. Fallback to Cached Models

```php
// Use cached ML model for simple predictions
if ($operationType === 'simple_price_suggestion') {
    return $this->cachedPricingModel->predict($data);
}

// Only call GPT-4 for complex analysis
if ($operationType === 'strategic_analysis') {
    return $this->callGPT4($prompt);
}
```

---

## 🐛 Troubleshooting

### Erro: "OpenAI API key not configured"

**Causa:** `OPENAI_API_KEY` não definido no `.env`

**Solução:**
```bash
echo "OPENAI_API_KEY=sk-proj-xxxxx" >> .env
```

---

### Erro: "Rate limit exceeded"

**Causa:** Muitas requisições à API OpenAI

**Solução:**
1. Implementar caching mais agressivo
2. Aumentar intervalo entre chamadas
3. Considerar upgrade do plano OpenAI

```php
// Add exponential backoff
$retries = 0;
while ($retries < 3) {
    try {
        return $this->callGPT4($prompt);
    } catch (RateLimitException $e) {
        $retries++;
        sleep(pow(2, $retries)); // 2s, 4s, 8s
    }
}
```

---

### Erro: "Invalid JSON response from GPT-4"

**Causa:** GPT-4 retornou texto não formatado como JSON

**Solução:**
```php
// Add JSON validation and retry
$response = $this->callGPT4($prompt);

if (!$this->isValidJson($response)) {
    // Retry with explicit instruction
    $prompt .= "\n\nIMPORTANT: Respond ONLY with valid JSON, no markdown.";
    $response = $this->callGPT4($prompt);
}

// Fallback: Extract JSON from markdown
if (preg_match('/```json\n(.*?)\n```/s', $response, $matches)) {
    $response = $matches[1];
}
```

---

### Erro: "Timeout connecting to OpenAI"

**Causa:** Rede lenta ou OpenAI API indisponível

**Solução:**
```php
// Increase timeout
$options = [
    'timeout' => 60,  // 60 seconds
    'connect_timeout' => 10
];

// Add fallback
try {
    return $this->callGPT4($prompt);
} catch (TimeoutException $e) {
    // Return cached data if available
    return $this->getCachedFallback($cacheKey);
}
```

---

### Baixa Qualidade de Respostas

**Causa:** Prompts mal estruturados

**Solução:**
1. **Seja específico:**
```php
// ❌ Ruim
$prompt = "Analyze this product";

// ✅ Bom
$prompt = "Analyze this product's SEO score. Focus on: title optimization, keyword usage, and missing attributes. Return JSON with scores 0-100.";
```

2. **Forneça exemplos:**
```php
$prompt = "Suggest A/B tests. Example format:
{
  \"name\": \"Test Title Length\",
  \"variant_a\": \"Short title\",
  \"variant_b\": \"Long descriptive title\"
}";
```

3. **Use system prompts:**
```php
$messages = [
    ['role' => 'system', 'content' => 'You are an expert ML SEO consultant. Always provide actionable, data-driven recommendations.'],
    ['role' => 'user', 'content' => $userPrompt]
];
```

---

## 🎯 Melhores Práticas

### 1. Prompt Engineering

**✅ DO:**
- Seja específico e direto
- Forneça contexto suficiente
- Peça formato específico (JSON, lista, etc)
- Inclua exemplos quando necessário
- Use system prompts para definir tom/papel

**❌ DON'T:**
- Prompts vagos ("analyze this")
- Excesso de informação desnecessária
- Pedir respostas longas sem estrutura
- Misturar múltiplas perguntas

---

### 2. Error Handling

```php
try {
    $insights = $this->insightsService->generateStrategicInsights();
} catch (OpenAIException $e) {
    // Log error
    $this->logger->error('OpenAI API error', [
        'error' => $e->getMessage(),
        'account_id' => $this->accountId
    ]);
    
    // Return cached data or default
    return $this->getCachedInsights() ?? $this->getDefaultInsights();
} catch (\Exception $e) {
    // Generic fallback
    return ['error' => 'Unable to generate insights'];
}
```

---

### 3. Performance

- **Cache agressivamente** - Insights não mudam a cada minuto
- **Batch operations** - Processar múltiplos itens de uma vez
- **Async processing** - Use filas para operações pesadas
- **Lazy loading** - Carregue insights sob demanda

```javascript
// Lazy load insights on tab switch
document.getElementById('insights-tab').addEventListener('click', async () => {
  if (!insightsLoaded) {
    await loadInsights();
    insightsLoaded = true;
  }
});
```

---

### 4. User Experience

- **Loading states** - Sempre mostre feedback visual
- **Progressive disclosure** - Mostre resultados parciais
- **Explain results** - Sempre contextualize respostas da IA
- **Allow editing** - Deixe usuário refinar sugestões

```javascript
// Progressive disclosure
async function generateInsights() {
  showLoadingSpinner('Analyzing account data...');
  
  // Step 1
  updateLoadingText('Collecting performance metrics...');
  const metrics = await fetchMetrics();
  
  // Step 2
  updateLoadingText('Analyzing trends...');
  const trends = await analyzeTrends();
  
  // Step 3
  updateLoadingText('Generating recommendations...');
  const insights = await generateWithGPT4(metrics, trends);
  
  hideLoadingSpinner();
  renderInsights(insights);
}
```

---

### 5. Security

- **Never expose API keys** - Use backend proxy
- **Rate limiting** - Proteja contra abuso
- **Input validation** - Sanitize dados antes de enviar ao GPT-4
- **Output validation** - Valide respostas antes de exibir

```php
// Sanitize user input
$message = strip_tags($input['message']);
$message = substr($message, 0, 500); // Max 500 chars

// Validate output
$response = $this->callGPT4($prompt);
if ($this->containsMaliciousContent($response)) {
    throw new SecurityException('Invalid response content');
}
```

---

## 📊 Monitoramento

### Métricas Importantes:

1. **API Usage:**
   - Total calls/day
   - Tokens consumed
   - Cost per day
   - Error rate

2. **Performance:**
   - Average response time
   - P95 response time
   - Timeout rate
   - Cache hit rate

3. **Quality:**
   - User satisfaction (thumbs up/down)
   - Recommendation acceptance rate
   - Chat conversation length
   - Re-generation rate

### Dashboard SQL:

```sql
-- Daily AI usage
SELECT 
    DATE(created_at) as date,
    COUNT(*) as total_calls,
    SUM(tokens_used) as total_tokens,
    AVG(processing_time_ms) as avg_response_time,
    SUM(CASE WHEN status = 'error' THEN 1 ELSE 0 END) as errors
FROM ai_insights_history
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;
```

---

## 🚀 Roadmap Futuro

### v2.1.0 - Q1 2026
- GPT-4 Vision para análise real de imagens
- Fine-tuned models por categoria
- Multilingual support (PT, ES, EN)
- Voice assistant integration

### v2.2.0 - Q2 2026
- Auto-apply AI recommendations
- Predictive inventory management
- Automated campaign optimization
- Custom AI agents per user

### v2.3.0 - Q3 2026
- AI-generated full listings
- Sentiment analysis of reviews
- Competitor intelligence automation
- Real-time market alerts

---

## 📞 Suporte

**Documentação:** [docs/AI_POWERED_INSIGHTS.md](AI_POWERED_INSIGHTS.md)  
**Issues:** GitHub Issues  
**Email:** dev@eskill.com.br  

---

**Última Atualização:** 31/12/2025  
**Versão:** 2.0.0  
**Status:** 🟢 Production Ready
