# 📚 API Reference - Sistema de IA para Otimização de Anúncios

## 📋 Visão Geral

Esta documentação descreve todas as APIs disponíveis no sistema de IA para otimização de anúncios do Mercado Livre.

**Base URL**: `https://eskill.com.br`  
**Autenticação**: Session-based (login obrigatório)  
**Content-Type**: `application/json`

---

## 🔥 SEO Killer API

### Diagnóstico

#### GET /api/seo-killer/diagnose
Diagnóstico completo da conta.

**Response:**
```json
{
  "success": true,
  "data": {
    "total_items": 150,
    "optimized": 45,
    "pending": 105,
    "avg_score": 67.5,
    "categories": [...]
  }
}
```

---

### Otimização de Títulos

#### POST /api/seo-killer/title
Gera título otimizado para SEO.

**Request:**
```json
{
  "item_id": "MLB123456",
  "current_title": "Smartphone Samsung",
  "category_id": "MLB1055",
  "style": "seo" // "aggressive", "balanced", "seo"
}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "original_title": "Smartphone Samsung",
    "optimized_title": "Smartphone Samsung Galaxy A54 5G 128GB 8GB RAM Preto",
    "score_before": 45,
    "score_after": 92,
    "keywords_added": ["5G", "128GB", "8GB RAM"]
  }
}
```

---

### Otimização de Descrições

#### POST /api/seo-killer/description
Gera descrição completa e otimizada.

**Request:**
```json
{
  "item_id": "MLB123456",
  "title": "Smartphone Samsung Galaxy A54",
  "attributes": {...},
  "tone": "professional" // "casual", "professional", "persuasive"
}
```

#### POST /api/seo-killer/description/analyze
Analisa descrição existente.

---

### Atributos

#### POST /api/seo-killer/attributes
Preenche atributos faltantes automaticamente.

**Request:**
```json
{
  "item_id": "MLB123456",
  "category_id": "MLB1055",
  "current_attributes": {...}
}
```

#### GET /api/seo-killer/hidden-attributes/{categoryId}
Lista atributos ocultos da categoria.

---

### Otimização Completa

#### POST /api/seo-killer/optimize
Otimização one-click (título + descrição + atributos).

**Request:**
```json
{
  "item_id": "MLB123456",
  "options": {
    "optimize_title": true,
    "optimize_description": true,
    "fill_attributes": true,
    "auto_apply": false
  }
}
```

---

### Pesquisa de Keywords

#### POST /api/seo-killer/keywords
Pesquisa keywords relacionadas.

**Request:**
```json
{
  "query": "smartphone samsung",
  "category_id": "MLB1055"
}
```

---

### Espionagem de Concorrentes

#### POST /api/seo-killer/spy
Analisa concorrentes do produto.

**Request:**
```json
{
  "item_id": "MLB123456",
  "limit": 10
}
```

---

## 🚀 Otimização em Massa

#### GET /api/seo-killer/bulk/select
Lista itens disponíveis para otimização.

#### POST /api/seo-killer/bulk/start
Inicia job de otimização em massa.

**Request:**
```json
{
  "item_ids": ["MLB123456", "MLB789012"],
  "options": {
    "optimize_title": true,
    "optimize_description": true
  }
}
```

#### POST /api/seo-killer/bulk/process/{jobId}
Processa próximo item do job.

#### GET /api/seo-killer/bulk/status/{jobId}
Status do job de otimização.

#### GET /api/seo-killer/bulk/jobs
Lista todos os jobs.

---

## 🤖 AutoPilot API

#### GET /api/seo-killer/autopilot/config
Obtém configuração do AutoPilot.

#### POST /api/seo-killer/autopilot/config
Salva configuração do AutoPilot.

**Request:**
```json
{
  "enabled": true,
  "min_score_threshold": 70,
  "max_items_per_run": 10,
  "schedule": "0 6 * * *",
  "optimize_title": true,
  "optimize_description": true
}
```

#### POST /api/seo-killer/autopilot/enable
Ativa o AutoPilot.

#### POST /api/seo-killer/autopilot/disable
Desativa o AutoPilot.

#### POST /api/seo-killer/autopilot/run
Execução manual do AutoPilot.

#### GET /api/seo-killer/autopilot/history
Histórico de execuções.

#### GET /api/seo-killer/autopilot/scores
Evolução de scores ao longo do tempo.

---

## 📈 Performance Tracker

#### GET /api/seo-killer/performance/dashboard
Dashboard de performance.

#### GET /api/seo-killer/performance/item/{itemId}
Performance de item específico.

#### GET /api/seo-killer/performance/compare/{itemId}
Comparação antes/depois da otimização.

#### GET /api/seo-killer/performance/top
Top performers (melhores resultados).

---

## 🖼️ Análise de Imagens

#### GET /api/seo-killer/images/analyze/{itemId}
Analisa imagens do item.

---

## 🧪 Testes A/B

#### POST /api/seo-killer/ab-test
Cria novo teste A/B.

**Request:**
```json
{
  "item_id": "MLB123456",
  "variant_a": {"title": "Título A"},
  "variant_b": {"title": "Título B"},
  "duration_days": 7
}
```

#### GET /api/seo-killer/ab-test
Lista testes A/B ativos.

#### POST /api/seo-killer/ab-test/stop/{id}
Encerra teste A/B.

---

## 🤖 AI Optimization API

### Otimização de Títulos

#### POST /api/ai/optimize/title
Otimiza título usando IA.

#### POST /api/ai/analyze/title
Analisa título existente.

### Otimização Completa

#### POST /api/ai/optimize/complete
Otimização completa do item.

#### POST /api/ai/optimize/batch
Otimização em lote.

#### POST /api/ai/optimize/description
Otimiza descrição.

#### POST /api/ai/optimize/tech-sheet
Otimiza ficha técnica.

### Pesquisa Avançada

#### GET /api/ai/keywords/research
Pesquisa de keywords com IA.

#### GET /api/ai/competitors/analyze
Análise de concorrentes.

#### GET /api/ai/providers/status
Status dos providers de IA.

### Batch Processing

#### POST /api/ai/batch/start
Inicia processamento em lote.

#### GET /api/ai/batch/{batchId}/status
Status do lote.

#### GET /api/ai/batch/{batchId}/results
Resultados do lote.

#### GET /api/ai/queue/stats
Estatísticas da fila.

### Testes A/B

#### POST /api/ai/ab-test/create
Cria teste A/B.

#### GET /api/ai/ab-test/{testId}/results
Resultados do teste.

#### POST /api/ai/ab-test/{testId}/end
Encerra teste.

### Audit & Rollback

#### GET /api/ai/audit/{itemId}/history
Histórico de otimizações.

#### POST /api/ai/audit/{logId}/rollback
Reverte otimização.

### Preview

#### POST /api/ai/preview/generate
Gera preview da otimização.

#### POST /api/ai/preview/{previewId}/apply
Aplica preview.

### Analytics

#### GET /api/ai/analytics/dashboard
Dashboard de analytics.

#### GET /api/ai/analytics/summary
Resumo executivo.

#### GET /api/ai/analytics/costs
Análise de custos.

---

## 🔒 Autenticação

Todas as APIs requerem sessão autenticada. O usuário deve estar logado via:

```
POST /login
{
  "email": "user@example.com",
  "password": "password"
}
```

---

## ⚠️ Códigos de Erro

| Código | Descrição |
|--------|-----------|
| 200 | Sucesso |
| 400 | Requisição inválida |
| 401 | Não autorizado |
| 403 | Sem permissão |
| 404 | Não encontrado |
| 429 | Rate limit excedido |
| 500 | Erro interno |

---

## 📊 Rate Limits

- **Standard**: 100 requests/minuto
- **Bulk Operations**: 10 requests/minuto
- **AI Operations**: 30 requests/minuto

---

## 📝 Exemplos

### Otimização rápida de título (cURL)
```bash
curl -X POST https://eskill.com.br/api/seo-killer/title \
  -H "Content-Type: application/json" \
  -H "Cookie: session=..." \
  -d '{"item_id":"MLB123456"}'
```

### Otimização completa (JavaScript)
```javascript
const response = await fetch('/api/seo-killer/optimize', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({
    item_id: 'MLB123456',
    options: {
      optimize_title: true,
      optimize_description: true,
      fill_attributes: true
    }
  })
});

const result = await response.json();
console.log(result);
```

---

*Última atualização: 25/12/2025*
