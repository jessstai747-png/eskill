# 🌐 Guia de Acesso ao Sistema - SEO Strategies

**Data:** 23 de Janeiro de 2026
**Versão:** 1.0.0
**Status:** Sistema Operacional

---

## 🔐 ACESSO AO SISTEMA

### URL Base
```
https://eskill.com.br
```

### Login Necessário
O sistema requer autenticação. Acesse:
```
https://eskill.com.br/login
```

---

## 📍 URLS DISPONÍVEIS

### 1. **SEO Killer** (Sistema Principal)
```
https://eskill.com.br/dashboard/seo-killer
```
**Funcionalidades:**
- Geração de títulos otimizados
- Geração de descrições
- Análise de atributos
- Preenchimento automático
- Espionagem de concorrentes
- Pesquisa de keywords
- Autopilot
- Bulk optimizer

---

### 2. **Ficha Técnica** (Technical Sheet)
```
https://eskill.com.br/dashboard/seo/ficha-tecnica
OU
https://eskill.com.br/dashboard/tech-sheet
```
**Funcionalidades:**
- Visualização de fichas técnicas
- Edição de produtos
- Análise de completude
- Histórico de mudanças

---

### 3. **SEO Dashboard** (Geral)
```
https://eskill.com.br/dashboard/seo
```
**Funcionalidades:**
- Visão geral de SEO
- Métricas e estatísticas
- Análise de gap
- Geração de conteúdo

---

### 4. **SEO Intelligence**
```
https://eskill.com.br/dashboard/seo-intelligence
```
**Funcionalidades:**
- Análise inteligente
- Insights avançados
- Monitoramento
- Relatórios detalhados

---

### 5. **Dashboard Principal**
```
https://eskill.com.br/dashboard
```
**Funcionalidades:**
- Visão geral do sistema
- Métricas principais
- Acesso rápido a todas as funcionalidades

---

## 🎯 ENDPOINTS DA API (SEO Strategies)

### Análise de Produtos
```
GET  /api/seo/strategies/score/{itemId}
GET  /api/seo/strategies/preview/{itemId}
GET  /api/seo/strategies/history/{itemId}
```

### Otimização
```
POST /api/seo/strategies/optimize/full/{itemId}
POST /api/seo/strategies/optimize/partial/{itemId}
POST /api/seo/strategies/apply/{itemId}
```

### Monitoramento
```
POST /api/seo/monitoring/schedule/{itemId}
GET  /api/seo/monitoring/metrics/{itemId}
```

---

## 🔥 ENDPOINTS DO SEO KILLER

### Core
```
GET  /api/seo-killer/diagnose
POST /api/seo-killer/title
POST /api/seo-killer/description
POST /api/seo-killer/optimize
GET  /api/seo-killer/score/{itemId}
```

### Análise
```
POST /api/seo-killer/description/analyze
GET  /api/seo-killer/hidden-attributes/{categoryId}
GET  /api/seo-killer/report
GET  /api/seo-killer/top-performers
```

### Pesquisa
```
POST /api/seo-killer/keywords
POST /api/seo-killer/spy
```

### Autopilot
```
GET  /api/seo-killer/autopilot/status
GET  /api/seo-killer/autopilot/config
POST /api/seo-killer/autopilot/toggle
POST /api/seo-killer/autopilot/settings
```

### Bulk Operations
```
GET  /api/seo-killer/bulk/select
POST /api/seo-killer/bulk/start
POST /api/seo-killer/bulk/process/{jobId}
GET  /api/seo-killer/bulk/status/{jobId}
GET  /api/seo-killer/bulk/jobs
GET  /api/seo-killer/bulk/monitor
POST /api/seo-killer/bulk/cancel/{jobId}
POST /api/seo-killer/bulk/retry/{jobId}
```

### Strategies (85 endpoints)
```
GET  /api/seo-killer/strategies/dashboard
GET  /api/seo-killer/strategies/cache/stats
GET  /api/seo-killer/strategies/analyze/{itemId}
POST /api/seo-killer/strategies/optimize/{itemId}
POST /api/seo-killer/strategies/batch

# Sinônimos (E1)
POST /api/seo-killer/strategies/synonyms/expand
GET  /api/seo-killer/strategies/synonyms/hierarchy/{categoryId}
POST /api/seo-killer/strategies/synonyms/generate

# Score Semântico (E9)
POST /api/seo-killer/strategies/score/calculate
POST /api/seo-killer/strategies/score/rank
POST /api/seo-killer/strategies/score/filter

# Keywords
POST /api/seo-killer/strategies/keywords/fetch
GET  /api/seo-killer/strategies/keywords/trending/{categoryId}

# Contextos (E6)
GET  /api/seo-killer/strategies/contexts/{categoryId}
POST /api/seo-killer/strategies/contexts/detect

# Long Tail (E7)
POST /api/seo-killer/strategies/longtail/generate
GET  /api/seo-killer/strategies/longtail/autocomplete/{keyword}

# FAQ (E11)
POST /api/seo-killer/strategies/faq/generate
POST /api/seo-killer/strategies/faq/ai

# Campos Ocultos (E2)
GET  /api/seo-killer/strategies/hidden-fields/{itemId}
POST /api/seo-killer/strategies/hidden-fields/suggest

# Compatibilidade (E10)
GET  /api/seo-killer/strategies/compatibility/analyze/{itemId}
POST /api/seo-killer/strategies/compatibility/expand

# Engine (E12)
GET  /api/seo-killer/strategies/engine/analyze/{itemId}
POST /api/seo-killer/strategies/engine/analyze
POST /api/seo-killer/strategies/engine/optimize/{itemId}
GET  /api/seo-killer/strategies/engine/dashboard
GET  /api/seo-killer/strategies/engine/report/{itemId}
```

---

## 🧪 COMO TESTAR O SISTEMA

### 1. Via Interface Web
```
1. Acesse: https://eskill.com.br/login
2. Faça login
3. Navegue para: /dashboard/seo-killer
4. Use as funcionalidades através da interface
```

### 2. Via API (curl)
```bash
# Score de um item
curl https://eskill.com.br/api/seo-killer/strategies/score/MLB123456789 \
  -H "Authorization: Bearer YOUR_TOKEN"

# Analisar item
curl https://eskill.com.br/api/seo-killer/strategies/analyze/MLB123456789 \
  -H "Authorization: Bearer YOUR_TOKEN"

# Dashboard
curl https://eskill.com.br/api/seo-killer/strategies/dashboard \
  -H "Authorization: Bearer YOUR_TOKEN"
```

### 3. Via Scripts PHP (local)
```bash
# Teste básico
php test_seo_strategies.php

# Teste com produtos
php test_ml_api_products.php

# Teste de integração
php test_tech_sheet_integration.php
```

---

## 📊 STATUS ATUAL DO SISTEMA

### Backend
- ✅ **100%** - Todos os services implementados
- ✅ **100%** - Todas as rotas criadas
- ✅ **100%** - Banco de dados configurado
- ✅ **80/100** - Prontidão para integração

### Frontend
- ✅ **60%** - Views principais criadas
- 🟡 **40%** - Integração completa pendente

### API
- ✅ **85 rotas** de strategies disponíveis
- ✅ **51 rotas** de ficha técnica
- ✅ **Autenticação** funcionando

---

## 🔑 AUTENTICAÇÃO

### Via API
Todas as requisições API requerem token de autenticação:

```bash
# Header
Authorization: Bearer YOUR_TOKEN_HERE
```

### Via Web
Login através do formulário em:
```
https://eskill.com.br/login
```

---

## 💡 COMO USAR AS ESTRATÉGIAS SEO

### Fluxo Básico

1. **Analisar Produto**
   ```
   GET /api/seo-killer/strategies/analyze/MLB123456789
   ```
   Retorna score geral e breakdown das 12 estratégias

2. **Ver Detalhes**
   ```
   GET /api/seo-killer/strategies/score/MLB123456789
   ```
   Retorna apenas o score

3. **Otimizar**
   ```
   POST /api/seo-killer/strategies/optimize/MLB123456789
   ```
   Aplica otimizações automáticas

4. **Monitorar**
   ```
   POST /api/seo/monitoring/schedule/MLB123456789
   ```
   Agenda monitoramento contínuo

---

## 📝 EXEMPLOS DE USO

### Exemplo 1: Expandir Sinônimos
```bash
curl -X POST https://eskill.com.br/api/seo-killer/strategies/synonyms/expand \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{
    "title": "Bauleto 41 Litros",
    "category_id": "MLB3530"
  }'
```

**Resposta:**
```json
{
  "expansions": [
    "Bauleto 41 Litros",
    "Baú 41 Litros",
    "Top Case 41 Litros",
    "Bagageiro 41 Litros",
    "Maleiro 41 Litros"
  ],
  "level": "nivel_2",
  "count": 7
}
```

### Exemplo 2: Calcular Score Semântico
```bash
curl -X POST https://eskill.com.br/api/seo-killer/strategies/score/calculate \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer TOKEN" \
  -d '{
    "word": "delivery",
    "title": "Bauleto Delivery",
    "category_id": "MLB3530"
  }'
```

**Resposta:**
```json
{
  "word": "delivery",
  "score": 87.5,
  "has_context": true,
  "context_type": "profissional",
  "weight": 1.20
}
```

### Exemplo 3: Análise Completa
```bash
curl https://eskill.com.br/api/seo-killer/strategies/engine/analyze/MLB123456789 \
  -H "Authorization: Bearer TOKEN"
```

**Resposta:**
```json
{
  "overall_score": 84.1,
  "quality_level": "Excelente",
  "strategy_scores": {
    "E1_SYNONYMS": 85,
    "E2_HIDDEN_FIELDS": 78,
    "E3_INJECTION": 90,
    "E4_COVERAGE": 82,
    "E5_FIELD_WEIGHT": 88,
    "E6_CONTEXTS": 91,
    "E7_LONG_TAIL": 79,
    "E8_DENSITY": 86,
    "E9_SEMANTIC": 89,
    "E10_COMPATIBILITY": 75,
    "E11_FAQ": 80,
    "E12_MONITORING": 95
  },
  "recommendations": [
    "Adicionar mais contextos de uso",
    "Melhorar densidade de keywords",
    "Expandir compatibilidade"
  ]
}
```

---

## 🎯 INTEGRAÇÃO COM FICHA TÉCNICA

### Como Integrar

1. **Na interface da Ficha Técnica**, adicionar botão:
   ```html
   <button onclick="analisarSEO(itemId)">
     🎯 Analisar SEO
   </button>
   ```

2. **JavaScript:**
   ```javascript
   async function analisarSEO(itemId) {
     const response = await fetch(`/api/seo-killer/strategies/analyze/${itemId}`, {
       headers: {
         'Authorization': `Bearer ${token}`
       }
     });

     const data = await response.json();

     // Exibir score e recomendações
     mostrarResultados(data);
   }
   ```

3. **Exibir Resultados:**
   - Score geral
   - Breakdown por estratégia
   - Recomendações de melhoria
   - Botão "Aplicar Otimizações"

---

## 📚 DOCUMENTAÇÃO ADICIONAL

- [Status Final](docs/SEO_STRATEGIES_STATUS_FINAL.md)
- [Implementação Finalizada](docs/IMPLEMENTACAO_FINALIZADA_23_01_2026.md)
- [Validação Completa](docs/VALIDACAO_SISTEMA_COMPLETA.md)
- [Resumo de Conquistas](CONQUISTAS_HOJE.txt)

---

## ✅ CHECKLIST DE ACESSO

- [ ] Fazer login no sistema
- [ ] Acessar dashboard principal
- [ ] Navegar para SEO Killer
- [ ] Testar análise de um produto
- [ ] Ver score e recomendações
- [ ] Aplicar uma otimização
- [ ] Verificar resultado

---

**Última atualização:** 23 de Janeiro de 2026 - 16:00
**Status:** Sistema em produção e operacional
