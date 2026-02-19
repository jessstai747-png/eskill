# 🧪 Guia de Testes - v2.0.0 AI Integration

**Data:** 31 de Dezembro de 2025  
**Versão:** 2.0.0  
**Status:** Integração Completa - Testes Necessários

---

## 📋 Checklist de Testes

### ✅ **1. AI Insights Dashboard** (`ai-insights-dashboard.php`)

#### Funcionalidades a Testar:
- [ ] **Strategic Assessment**
  - Carregar análise estratégica via `POST /api/ai/insights/strategic`
  - Exibir strengths, weaknesses, opportunities, risks, next_steps
  - Botões "Execute Action" funcionando
  
- [ ] **Trends Analysis**
  - Selector de time range (7/30/90 dias)
  - Carregar via `GET /api/ai/insights/trends?days=30`
  - Exibir rising, declining, seasonal trends
  - Forecast de 30 dias
  
- [ ] **Market Sentiment**
  - Canvas gauge renderizando corretamente
  - Cores corretas (bullish=green, bearish=red, neutral=gray)
  - Key factors listados
  
- [ ] **Prioritized Recommendations**
  - Filtros (All, Quick Wins, SEO, Pricing, Marketing)
  - Top 10 recommendations carregadas
  - Badges de impact/effort/category corretos
  - Botão "Apply" funcional
  
- [ ] **A/B Test Suggestions**
  - Cards de variantes A vs B
  - Expected impact e duration
  - Botão "Create Test"

#### Testes de API:
```bash
# Strategic Insights
curl -X POST http://localhost/api/ai/insights/strategic \
  -H "Content-Type: application/json" \
  -d '{"account_id": 1}'

# Trends Analysis
curl -X GET http://localhost/api/ai/insights/trends?days=30

# Market Sentiment
curl -X GET http://localhost/api/ai/insights/sentiment

# Recommendations
curl -X GET http://localhost/api/ai/insights/recommendations?limit=10

# A/B Tests
curl -X POST http://localhost/api/ai/insights/ab-tests \
  -H "Content-Type: application/json" \
  -d '{"account_id": 1}'
```

#### Testes Visuais:
- [ ] Canvas gauge desenhado sem erros
- [ ] Badges coloridos corretamente
- [ ] Animações fadeIn suaves
- [ ] Cards hover funcionando
- [ ] Responsive em mobile

---

### ✅ **2. AI Chatbot Widget** (`ai-chatbot-widget.php`)

#### Funcionalidades a Testar:
- [ ] **Toggle Button**
  - Botão floating bottom-right
  - Gradient background (#667eea → #764ba2)
  - Notification badge com contagem
  - Hover scale(1.1)
  
- [ ] **Chat Window**
  - Abrir/fechar animação slideUp
  - 380×600 dimensões
  - Header com avatar e título
  
- [ ] **Quick Actions**
  - 3 botões: SEO, Preços, Ações
  - Enviar mensagem ao clicar
  
- [ ] **Messages**
  - Bot messages: left, white bg, avatar
  - User messages: right, gradient bg
  - Timestamps corretos
  - Markdown formatting (bold, italic, lists)
  
- [ ] **Typing Indicator**
  - 3 dots animação
  - Mostrar durante API call
  - Esconder ao receber resposta
  
- [ ] **Suggested Actions**
  - Extrair de resposta do bot
  - Exibir botões de ação
  - Auto-hide após 10s
  
- [ ] **Input Area**
  - Enter para enviar
  - Botão send circular
  - Clear após envio
  
- [ ] **History Management**
  - Persistir conversationId
  - Clear history funcional
  - DELETE /api/ai/chat/history

#### Testes de API:
```bash
# Send Message
curl -X POST http://localhost/api/ai/chat \
  -H "Content-Type: application/json" \
  -d '{"message": "Olá, preciso de ajuda com SEO", "context": {"page": "seo-killer"}}'

# Explain Metric
curl -X POST http://localhost/api/ai/chat/explain-metric \
  -H "Content-Type: application/json" \
  -d '{"metric": "score_seo", "value": 75}'

# Feature Help
curl -X POST http://localhost/api/ai/chat/help-feature \
  -H "Content-Type: application/json" \
  -d '{"feature": "title_optimizer"}'

# Proactive Suggestions
curl -X GET http://localhost/api/ai/chat/suggest-actions

# Clear History
curl -X DELETE http://localhost/api/ai/chat/history \
  -H "Content-Type: application/json" \
  -d '{"conversation_id": "abc123"}'
```

#### Testes Visuais:
- [ ] Floating button z-index correto (9999)
- [ ] Chat window não sobrepõe navbar
- [ ] Scrollbar customizada (6px purple)
- [ ] Message bubbles arredondadas
- [ ] Typing dots animation suave
- [ ] Mobile responsive (calc(100vw - 40px))

---

### ✅ **3. AI Pricing Optimizer** (`ai-pricing-optimizer.php`)

#### Funcionalidades a Testar:
- [ ] **Product Selector**
  - Dropdown carrega produtos ativos
  - Botão "Analisar Preço"
  
- [ ] **Summary Cards**
  - Current Price
  - Suggested Price (verde)
  - Estimated Gain (% dinâmica, colorida)
  
- [ ] **Pricing Strategies**
  - 4 cards: Penetração, Competitivo, Premium, Margem
  - Recommended strategy com border
  - Hover effect
  
- [ ] **Elasticity Analysis**
  - Coefficient exibido
  - 5 scenarios (-20%, -10%, 0%, +10%, +20%)
  - Revenue effect colorido (green/red)
  - Recommendation alert
  
- [ ] **Competitive Analysis**
  - Canvas position gauge (200×200)
  - Percentile correto
  - Stats table (min, avg, max, percentile)
  - Market position badge
  
- [ ] **Revenue Forecast**
  - Input de price points (comma-separated)
  - Tabela com 4 colunas
  - Best scenario highlighted (green)
  - Trophy icon
  
- [ ] **Dynamic Rules**
  - Modal de criação de regra
  - Condition dropdown (6 opções)
  - Action dropdown (3 opções)
  - Value input
  - Preview em tempo real
  - Save funcional
  
- [ ] **Apply Price**
  - Card destacada (border-primary)
  - Botão verde grande
  - Confirmação antes de aplicar

#### Testes de API:
```bash
# Pricing Suggestion
curl -X POST http://localhost/api/ai/pricing/suggest \
  -H "Content-Type: application/json" \
  -d '{"item_id": "MLB123", "goal": "balanced"}'

# Elasticity Analysis
curl -X POST http://localhost/api/ai/pricing/elasticity \
  -H "Content-Type: application/json" \
  -d '{"item_id": "MLB123"}'

# Competitive Analysis
curl -X GET http://localhost/api/ai/pricing/competitive/MLB123

# Revenue Forecast
curl -X POST http://localhost/api/ai/pricing/forecast \
  -H "Content-Type: application/json" \
  -d '{"item_id": "MLB123", "price_points": [99.90, 119.90, 139.90]}'

# Dynamic Rules
curl -X POST http://localhost/api/ai/pricing/dynamic-rules \
  -H "Content-Type: application/json" \
  -d '{"item_id": "MLB123", "rules": [{"condition": "competitor_price_below", "action": "decrease_percentage", "value": 5}]}'
```

#### Testes Visuais:
- [ ] Strategy cards hover (translateY -4px)
- [ ] Canvas gauge arco desenhado
- [ ] Scenario cards border/background por result
- [ ] Competitive stats table alinhada
- [ ] Modal centered corretamente

---

### ✅ **4. AI Image Analyzer** (`ai-image-analyzer.php`)

#### Funcionalidades a Testar:
- [ ] **Product Selector**
  - Dropdown carrega produtos
  - Botão "Analisar Imagens"
  
- [ ] **Overall Score Card**
  - Canvas gauge 150×150
  - Score geral /100
  - 4 summary counts (total, good, warning, critical)
  
- [ ] **Images Grid**
  - Cards individuais por imagem
  - Score badge colorido
  - Status border (good=green, warning=yellow, critical=red)
  - Issues badges
  - Filtro: All, Good, Warning, Critical
  
- [ ] **Detected Issues**
  - Lista de problemas
  - Severity icons (critical=danger, warning)
  - Affected images count
  
- [ ] **Recommendations**
  - Numbered list (1, 2, 3...)
  - Title e description
  - Impact badge
  
- [ ] **Best Practices Comparison**
  - Check/X icons
  - Name e description
  - Status badge (Cumprido/Não cumprido)
  
- [ ] **Optimal Order**
  - Grid 6 items (col-md-2)
  - Position badges (1, 2, 3...)
  - Thumbnails 100px height
  - Score abaixo de cada imagem
  - Botão "Apply"
  
- [ ] **Duplicate Detection**
  - Card only if duplicates found
  - Groups com similarity %
  - Remove buttons
  
- [ ] **Image Detail Modal**
  - Preview grande
  - Technical details (resolution, format, size, score)
  - Issues listed
  - Suggestions listed
  - Remove/Replace buttons

#### Testes de API:
```bash
# Analyze Images
curl -X GET http://localhost/api/ai/images/analyze/MLB123

# Reorder Images
curl -X POST http://localhost/api/ai/images/reorder/MLB123 \
  -H "Content-Type: application/json" \
  -d '{"order": [0, 2, 1, 3, 4, 5]}'

# Remove Image
curl -X DELETE http://localhost/api/ai/images/remove \
  -H "Content-Type: application/json" \
  -d '{"item_id": "MLB123", "image_url": "https://..."}'
```

#### Testes Visuais:
- [ ] Image cards hover (translateY -4px, shadow)
- [ ] Canvas gauge 3 cores (green, yellow, red)
- [ ] Filter buttons toggle correctly
- [ ] Optimal order badges positioned (top-left)
- [ ] Modal large (modal-lg)
- [ ] Duplicate groups dashed border (yellow)

---

### ✅ **5. Dashboard Integration**

#### Funcionalidades a Testar:
- [ ] **Navigation Tabs**
  - 7 tabs totais (Dashboard, Competitor Spy, Performance Tracker, A/B Testing, AI Insights, AI Pricing, AI Images)
  - Bootstrap Tab switching
  - Active state correto
  
- [ ] **Quick Access Tools**
  - 7 tool cards no dashboard
  - 2 AI cards com badge "NOVO"
  - Border-primary nos AI cards
  - onClick rotas corretas:
    - `openAIInsights()` → AI Insights tab
    - `openAIPricing()` → AI Pricing tab
    - `openImageAnalyzer()` → modal (SEO Killer tradicional)
  
- [ ] **Chatbot Widget Global**
  - Widget carregado em todas as páginas
  - Floating bottom-right
  - Não interfere com UI
  - Context detection funcional
  
- [ ] **Assets Loading**
  - Toastify CSS/JS
  - Chart.js
  - seo-killer.css
  - seo-killer.js
  - seo-killer-utils.js

#### Testes de Navegação:
1. Dashboard → AI Insights (via tab)
2. Dashboard → AI Pricing (via tool card)
3. Dashboard → AI Images (via tab)
4. Chatbot acessível de qualquer tab
5. Voltar para Dashboard mantém estado

---

## 🎨 Testes Visuais (Todos os Componentes)

### Canvas Rendering:
- [ ] Chrome: Gauges renderizam corretamente
- [ ] Firefox: Gauges renderizam corretamente
- [ ] Safari: Gauges renderizam corretamente
- [ ] Edge: Gauges renderizam corretamente

### Responsive Design:
- [ ] Desktop (1920×1080): Layout perfeito
- [ ] Laptop (1366×768): Layout responsivo
- [ ] Tablet (768×1024): Cards empilhados
- [ ] Mobile (375×667): Chatbot width correto

### Animations:
- [ ] fadeIn (0.5s, opacity 0→1, translateY 10px→0)
- [ ] slideUp (0.3s, chatbot window)
- [ ] messageSlide (0.3s, messages)
- [ ] typing dots (1.4s infinite, translateY)
- [ ] hover transforms (translateY -2px/-4px)

### Colors & Gradients:
- [ ] Primary gradient: #667eea → #764ba2
- [ ] Success: #28a745
- [ ] Warning: #ffc107
- [ ] Danger: #dc3545
- [ ] Info: #17a2b8

---

## 🐛 Testes de Erro

### API Error Handling:
- [ ] **Network Error:** Toastify com mensagem de erro
- [ ] **404 Not Found:** Alert apropriado
- [ ] **500 Server Error:** Retry automático (se configurado)
- [ ] **Timeout:** Loading state removido, erro exibido

### Edge Cases:
- [ ] **Empty Data:** Mensagens "Nenhum dado disponível"
- [ ] **Invalid Input:** Validação antes de submit
- [ ] **Large Datasets:** Performance não degrada com 100+ items
- [ ] **Concurrent Requests:** Não duplica chamadas

### User Experience:
- [ ] **Loading States:** Sempre visíveis durante API calls
- [ ] **Disabled Buttons:** Durante processamento
- [ ] **Tooltips:** Explicações em hover
- [ ] **Confirmations:** Antes de ações destrutivas

---

## 🚀 Testes de Performance

### Métricas Alvo:
- [ ] **First Load:** <2s
- [ ] **Tab Switch:** <300ms
- [ ] **Modal Open:** <300ms
- [ ] **API Response:** <2s (p95)
- [ ] **Canvas Rendering:** <100ms

### Otimizações:
- [ ] **Debounce:** Campos de busca (500ms)
- [ ] **Lazy Load:** Tabs carregam apenas ao ativar
- [ ] **Cache:** LocalStorage para resultados recentes
- [ ] **Bundle:** JavaScript minificado em produção

---

## 📊 Relatório de Testes

### Template:
```
Data: ___/___/_____
Testador: _________________
Navegador: _________________
Versão: 2.0.0

Componente: AI Insights Dashboard
- [ ] Todas as funcionalidades OK
- [ ] Problemas encontrados: _________________
- [ ] Screenshots anexados: [ ]

Componente: AI Chatbot Widget
- [ ] Todas as funcionalidades OK
- [ ] Problemas encontrados: _________________
- [ ] Screenshots anexados: [ ]

Componente: AI Pricing Optimizer
- [ ] Todas as funcionalidades OK
- [ ] Problemas encontrados: _________________
- [ ] Screenshots anexados: [ ]

Componente: AI Image Analyzer
- [ ] Todas as funcionalidades OK
- [ ] Problemas encontrados: _________________
- [ ] Screenshots anexados: [ ]

Dashboard Integration
- [ ] Todas as funcionalidades OK
- [ ] Problemas encontrados: _________________
- [ ] Screenshots anexados: [ ]

Observações Gerais:
_________________________________________________
_________________________________________________
_________________________________________________

Status Final: [ ] APROVADO  [ ] REPROVADO
```

---

## 🎯 Próximos Passos

Após testes completos e aprovação:

1. **Deploy para Staging**
   - Verificar ambiente staging
   - Testar com dados reais (não produção)
   - Validar integrações ML API

2. **User Acceptance Testing (UAT)**
   - Selecionar beta testers
   - Coletar feedback
   - Iterar baseado em sugestões

3. **Deploy para Produção**
   - Horário de baixo tráfego
   - Feature flag (rollout gradual 10% → 50% → 100%)
   - Monitorar logs e métricas 48h

4. **Documentação Final**
   - Update USER_GUIDE.md
   - Screenshots de todos os componentes
   - Video tutorial (5-10min)

---

**Status:** ✅ Guia de Testes Criado  
**Próximo:** Executar testes e validar integrações
