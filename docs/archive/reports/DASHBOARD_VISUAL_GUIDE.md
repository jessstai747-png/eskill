# 🎨 Guia Visual do Dashboard de Otimização IA

## 📊 Visão Geral

Este documento apresenta a interface visual do sistema de otimização de anúncios com Inteligência Artificial.

---

## 🏠 Tela 1: Dashboard Overview (Principal)

![Dashboard Overview](/root/.gemini/antigravity/brain/508d5101-e530-494f-96e8-ab3918215ea4/dashboard_overview_1766612542250.png)

### Elementos da Interface:

#### 1️⃣ Header
- **Logo e Título**: "🤖 Otimização IA - Visão Geral"
- **Botão Configurações**: Acesso rápido às configurações
- **Gradiente**: Indigo para Purple (moderno e tech)

#### 2️⃣ Cards de Métricas (Top)
```
┌──────────────┬──────────────┬──────────────┬──────────────┐
│   1,247      │     892      │     71%      │      87      │
│  Anúncios    │  Otimizados  │ Taxa Otim.   │ Score Médio  │
│   Totais     │              │              │              │
└──────────────┴──────────────┴──────────────┴──────────────┘
```
- Cards com gradiente sutil
- Ícones coloridos
- Animação ao hover
- Indicador de variação (↑ +12%)

#### 3️⃣ Gráfico de Performance
- **Tipo**: Line chart interativo
- **Métricas**: Views, Visitas, Vendas
- **Período**: Últimos 30 dias
- **Destaque**: Percentuais de crescimento
  - +142% Views (azul)
  - +89% Visitas (roxo)
  - +67% Vendas (verde)

#### 4️⃣ Seção "Anúncios para Otimizar"
```
🔴 CRÍTICO (45)    - Score < 50   [Otimizar Todos]
🟠 MÉDIO (180)     - Score 50-70  [Otimizar Todos]
🟡 MELHORAR (135)  - Score 70-85  [Otimizar Todos]
🟢 BOM (887)       - Score > 85   [-]
```
- Cards clicáveis com cores semafóricas
- Botões de ação rápida
- Número de anúncios em cada categoria

#### 5️⃣ Top Performers
Lista dos 3 anúncios com melhor desempenho:
- Score visual (98, 96, 95)
- Variação de views
- Link para análise detalhada

#### 6️⃣ Insights e Recomendações
Alertas inteligentes da IA:
- 💡 45 anúncios críticos
- 💡 Estatísticas de impacto
- 💡 Recomendações acionáveis

---

## 🔧 Tela 2: Editor de Otimização

### Wireframe Detalhado

```
┌─────────────────────────────────────────────────────────────────┐
│  🤖 Otimizar: Fone Bluetooth TWS                    [✖️ Fechar] │
├─────────────────────────────────────────────────────────────────┤
│                                                                 │
│  ┌──────────────┐              ┌──────────────┐               │
│  │ SCORE ATUAL  │              │SCORE PREVISTO│               │
│  │     54 🔴    │  ========>   │    87 🟢     │               │
│  └──────────────┘              └──────────────┘               │
│                                                                 │
├─────────────────────┬──────────────────┬──────────────────────┤
│                     │                  │                      │
│   📝 ANTES          │  🤖 SUGESTÃO IA  │   👁️ PREVIEW        │
│                     │                  │                      │
│ [Título Original]   │ [Título Otim.]   │ [Como fica no ML]   │
│                     │                  │                      │
│ Fone de Ouvido      │ Fone Bluetooth   │ ┌─────────────────┐ │
│ Bluetooth           │ TWS Sem Fio      │ │ [ML Product]    │ │
│                     │ Esportivo        │ │  Card Preview   │ │
│ Score: 45 🔴        │ Resistente Água  │ │                 │ │
│                     │ IPX7             │ │ Como aparece    │ │
│ ⚠️ Muito curto      │                  │ │ no Mercado      │ │
│ ⚠️ Sem keywords     │ Score: 92 🟢     │ │ Livre           │ │
│                     │                  │ │                 │ │
│                     │ ✅ 60 chars      │ └─────────────────┘ │
│                     │ ✅ 4 keywords    │                      │
│                     │ ✅ CTR +145%     │                      │
│                     │                  │                      │
│                     │ [✓ Aplicar]      │                      │
│                     │ [🔄 Regenerar]   │                      │
│                     │                  │                      │
└─────────────────────┴──────────────────┴──────────────────────┘
```

### Tabs de Otimização

#### Tab: Título
- Versão atual com análise de problemas
- 3 versões sugeridas pela IA:
  - ⭐ Recomendada (melhor score)
  - SEO Focada
  - CTR Focada
- Score individual de cada versão
- Estimativa de impacto

#### Tab: Descrição
```
┌─────────────────────────────────────────────────────────────┐
│  [Estilo: Persuasivo ▼] [Modelo: GPT-4o ▼] [🔄 Regenerar] │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ✅ Fone Bluetooth TWS Sem Fio Esportivo                    │
│                                                             │
│  🎧 EXPERIÊNCIA SONORA INCOMPARÁVEL                         │
│  Mergulhe em um áudio cristalino com drivers premium...    │
│                                                             │
│  💪 LIBERDADE PARA SEUS TREINOS                             │
│  • Certificação IPX7 - Resistente a suor e chuva           │
│  • Ajuste ergonômico - Não cai durante exercícios          │
│  • Controles touch - Sem precisar tirar do bolso           │
│                                                             │
│  [... conteúdo completo ...]                               │
│                                                             │
│  ✅ 1,847 caracteres | ✅ 15 keywords | Score: 94/100      │
│                                                             │
│  [Ver Outras Versões] [✏️ Editar] [✓ Aplicar]             │
└─────────────────────────────────────────────────────────────┘
```

**Características**:
- Editor WYSIWYG inline
- Emojis estratégicos
- Estrutura markdown preservada
- Contadores de caracteres/keywords
- Múltiplas versões para escolher

#### Tab: Ficha Técnica
```
┌─────────────────────────────────────────────────────────────┐
│  COMPLETUDE: 62% [████████░░░░░░]                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  🤖 ATRIBUTOS SUGERIDOS PELA IA                             │
│                                                             │
│  ⚠️ OBRIGATÓRIOS FALTANTES                                  │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ Marca:           [TWS Pro ▼]        ✓ Aplicar        │ │
│  │ Modelo:          [X10 Elite ▼]      ✓ Aplicar        │ │
│  │ Bluetooth:       [5.3 ▼]            ✓ Aplicar        │ │
│  │ Cancelamento:    [Sim ▼]            ✓ Aplicar        │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                             │
│  💡 RECOMENDADOS (melhoria de ranqueamento)                 │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ Resist. Água:    [IPX7 ▼]           ✓ Aplicar        │ │
│  │ Autonomia:       [8 horas ▼]        ✓ Aplicar        │ │
│  │ Microfone:       [Sim ▼]            ✓ Aplicar        │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                             │
│  📊 Top sellers preenchem em média: 24 atributos (vs 18)   │
│                                                             │
│  [✓ Aplicar Todos] [Preencher Manualmente]                │
└─────────────────────────────────────────────────────────────┘
```

**Funcionalidades**:
- Auto-detecção de atributos faltantes
- Inferência inteligente de valores
- Nível de confiança da IA
- Comparação com concorrentes
- Aplicação individual ou em lote

#### Tab: Imagens
```
┌─────────────────────────────────────────────────────────────┐
│  QUALIDADE GERAL: 68% [██████████░░░░░░]                   │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌────────┬────────┬────────┬────────┬────────┬────────┐  │
│  │ IMG 1  │ IMG 2  │ IMG 3  │ IMG 4  │ IMG 5  │ IMG 6  │  │
│  │  🟢88  │  🟡72  │  🟡70  │  🔴45  │  [+]   │  [+]   │  │
│  │ [📸]   │ [📸]   │ [📸]   │ [📸]   │ Add    │ Add    │  │
│  └────────┴────────┴────────┴────────┴────────┴────────┘  │
│                                                             │
│  🤖 ANÁLISE DETALHADA                                       │
│                                                             │
│  IMAGEM 1 (Score: 88)                                       │
│  ✅ Boa resolução | ✅ Iluminação | ✅ Foco                │
│  💡 Adicionar overlay promocional                           │
│  [🎨 Gerar com Overlay]                                    │
│                                                             │
│  IMAGEM 2 (Score: 72)                                       │
│  ✅ Composição | ⚠️ Fundo distraindo                       │
│  💡 Remover fundo para destaque                             │
│  [🎨 Remover Fundo]                                        │
│                                                             │
│  IMAGEM 4 (Score: 45)                                       │
│  ⚠️ Baixa resolução | ⚠️ Escura | ⚠️ Desfocada             │
│  💡 Substituir ou melhorar qualidade                        │
│  [🎨 Gerar Nova com IA]                                    │
│                                                             │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  🎨 GERAR IMAGENS COM IA                                    │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ [📊 Infográfico Specs]                                │ │
│  │ [📏 Guia de Tamanhos]                                 │ │
│  │ [✨ Grid de Features]                                 │ │
│  │ [🎯 Comparativo]                                      │ │
│  │ [🏃 Lifestyle/Uso]                                    │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                             │
│  [🎨 Gerar Selecionado]                                    │
└─────────────────────────────────────────────────────────────┘
```

**Capacidades**:
- Análise de qualidade por imagem
- Sugestões de melhorias
- Remoção de fundo automática
- Geração de infográficos
- Upload de novas imagens
- Reordenação drag & drop

---

## 📦 Tela 3: Otimização em Lote

```
┌─────────────────────────────────────────────────────────────┐
│  📦 Otimização em Lote                   [⚙️ Configurar]   │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  FILTROS: [Categoria ▼] [Score ▼] [Status ▼] [🔍]         │
│                                                             │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ ☑️ SELECIONAR TODOS (247)        [✓][✗][⚙️]         │ │
│  ├───────────────────────────────────────────────────────┤ │
│  │                                                       │ │
│  │ ☑️ Fone Bluetooth TWS      Score: 45 🔴 [Otimizar]  │ │
│  │    Eletrônicos | 12 vendas                          │ │
│  │                                                       │ │
│  │ ☑️ Carregador USB-C        Score: 52 🔴 [Otimizar]  │ │
│  │    Eletrônicos | 8 vendas                           │ │
│  │                                                       │ │
│  │ ☑️ Smartwatch Fit          Score: 48 🔴 [Otimizar]  │ │
│  │    Eletrônicos | 15 vendas                          │ │
│  │                                                       │ │
│  │ [ ... mais 244 anúncios ... ]                       │ │
│  │                                                       │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                             │
│  AÇÕES EM LOTE:                                             │
│  [🤖 Títulos] [📝 Descrições] [📋 Fichas] [✨ Completa]   │
│                                                             │
│  ⚙️ CONFIGURAÇÕES:                                          │
│  • Modelo IA:        [GPT-4o ▼]                            │
│  • Aplicação:        [Preview antes ▼]                     │
│  • Prioridade:       [Menor score primeiro ▼]              │
│  • Limite diário:    [100/dia]                             │
│                                                             │
│  [🚀 INICIAR OTIMIZAÇÃO (247 selecionados)]                │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Tela de Processamento
```
┌─────────────────────────────────────────────────────────────┐
│  ⚡ Otimizando em Lote...                    [⏸️ Pausar]   │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  PROGRESSO: 47/247 (19%)                                    │
│  [███████░░░░░░░░░░░░░░░░░░░░░░░░░░]                       │
│                                                             │
│  ┌─────────────┬─────────────┬──────────────────┐         │
│  │ ✅ Feitos   │ ⏳ Em Fila  │ ⏱️ ETA          │         │
│  │     47      │     200     │  ~45 min        │         │
│  └─────────────┴─────────────┴──────────────────┘         │
│                                                             │
│  📋 LOG DE ATIVIDADES                                       │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ ✅ MLB123456 - OK (Score: 45→87) +42pts              │ │
│  │ ✅ MLB123457 - OK (Score: 52→89) +37pts              │ │
│  │ ⚠️ MLB123458 - Aviso: Atributos parciais             │ │
│  │ ✅ MLB123459 - OK (Score: 48→85) +37pts              │ │
│  │ ⏳ MLB123460 - Processando...                        │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                             │
│  📊 ESTATÍSTICAS                                            │
│  • Score médio antes:   51.2                                │
│  • Score médio depois:  86.7                                │
│  • Melhoria média:     +35.5 pts                            │
│  • Taxa de sucesso:     96% (45/47)                         │
│                                                             │
│  [📄 Exportar Relatório] [📊 Ver Analytics]                │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 📜 Tela 4: Histórico

```
┌─────────────────────────────────────────────────────────────┐
│  📜 Histórico de Otimizações                [📊 Exportar]  │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌─────────┬──────────────────┬─────────┬───────┬──────┐  │
│  │ Data    │ Anúncio          │ Tipo    │ Antes │Depois│  │
│  ├─────────┼──────────────────┼─────────┼───────┼──────┤  │
│  │24/12│14:30│Fone Bluetooth │Completa│  45   │ 87✅│▼│ │
│  │24/12│13:15│Carregador USB │Título  │  52   │ 78✅│▼│ │
│  │24/12│12:45│Smartwatch Fit │Descrição│ 48   │ 82✅│▼│ │
│  │24/12│11:20│Mouse Gamer    │Completa│  41   │ 89✅│▼│ │
│  └─────────┴──────────────────┴─────────┴───────┴──────┘  │
│                                                             │
│  📊 RESUMO (Últimos 30 dias)                                │
│  ┌────────────┬─────────────────┬─────────────────┐       │
│  │Total Otim. │ Melhoria Média  │ Taxa Sucesso   │       │
│  │    847     │   +34 pontos    │      97%       │       │
│  └────────────┴─────────────────┴─────────────────┘       │
│                                                             │
│  [📈 Ver Gráficos] [📄 Relatório Completo]                │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Detalhes Expandidos
Ao clicar no ▼ de qualquer linha:
```
┌─────────────────────────────────────────────────────────────┐
│  📋 Fone Bluetooth TWS - Otimização Completa                │
│  24/12/2025 14:30 | GPT-4o | 12s                            │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ANTES                    │  DEPOIS                         │
│  ─────────────────────────┼─────────────────────────────── │
│  Score: 45 🔴            │  Score: 87 🟢                   │
│                           │                                 │
│  Título (32)              │  Título (92) +60               │
│  Descrição (28)           │  Descrição (94) +66            │
│  Ficha (55)               │  Ficha (92) +37                │
│  Imagens (65)             │  Imagens (78) +13              │
│                           │                                 │
│  ─────────────────────────────────────────────────────────  │
│                                                             │
│  📈 IMPACTO (7 dias após)                                   │
│  • Views:      +156% (234 → 599)                            │
│  • Visitas:    +92% (47 → 90)                               │
│  • Conversões: +71% (3 → 5.13/semana)                       │
│  • Revenue:    +R$ 847,50                                   │
│                                                             │
│  [Ver Detalhes] [🔄 Reverter] [📊 Analytics Completo]     │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## ⚙️ Tela 5: Configurações

```
┌─────────────────────────────────────────────────────────────┐
│  ⚙️ Configurações da IA                                     │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  🤖 MODELOS DE IA                                           │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ Principal:     [GPT-4o ▼]                             │ │
│  │ Alternativo:   [Claude 3.5 ▼]                         │ │
│  │ Fallback:      [Gemini Pro ▼]                         │ │
│  │                                                        │ │
│  │ ☑️ Usar múltiplos e escolher melhor resultado        │ │
│  │ ☑️ Cache de respostas (economia)                     │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                             │
│  📝 PERSONALIZAÇÃO                                          │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ Tom de voz:      [Profissional ▼]                     │ │
│  │ Estilo descrição:[Detalhado ▼]                        │ │
│  │ Foco principal:  [SEO + Conversão ▼]                  │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                             │
│  ⚡ AUTOMAÇÃO                                                │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ ☑️ Auto-otimizar score < 50                          │ │
│  │ ☑️ Sugerir otimização score 50-70                    │ │
│  │ ☑️ Relatório semanal                                 │ │
│  │ ☐ Aplicar sem aprovação (cuidado!)                   │ │
│  │                                                        │ │
│  │ Horário:       [02:00 - 06:00 ▼]                      │ │
│  │ Limite diário: [100 otimizações]                      │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                             │
│  💰 USO E CUSTOS                                            │
│  ┌───────────────────────────────────────────────────────┐ │
│  │ Este mês:                                              │ │
│  │ • Otimizações:  847                                    │ │
│  │ • Custo:        R$ 127,05                              │ │
│  │ • Limite:       R$ 500,00                              │ │
│  │ • Uso:          25% [█████░░░░░░░░░░░]                │ │
│  │                                                        │ │
│  │ [📊 Detalhes]                                         │ │
│  └───────────────────────────────────────────────────────┘ │
│                                                             │
│  [💾 Salvar Configurações]                                 │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

---

## 🎨 Design System

### Paleta de Cores

#### Scores
- 🔴 Crítico (< 50):    `#EF4444`
- 🟠 Atenção (50-70):   `#F59E0B`
- 🟡 Bom (70-85):       `#FCD34D`
- 🟢 Excelente (> 85):  `#10B981`
- 🔵 Premium (> 95):    `#06B6D4`

#### Interface
- **Primary**:    `#6366F1` (Indigo)
- **Secondary**:  `#8B5CF6` (Purple)
- **Accent**:     `#EC4899` (Pink)
- **Background**: `#F9FAFB`
- **Surface**:    `#FFFFFF`
- **Text**:       `#1F2937`

### Tipografia
- **Headings**: Inter Bold
- **Body**: Inter Regular
- **Code/Data**: JetBrains Mono

### Espaçamento
- XS: 4px
- SM: 8px
- MD: 16px
- LG: 24px
- XL: 32px
- 2XL: 48px

### Shadows
```css
--shadow-sm: 0 1px 2px rgba(0,0,0,0.05)
--shadow-md: 0 4px 6px rgba(0,0,0,0.1)
--shadow-lg: 0 10px 15px rgba(0,0,0,0.1)
--shadow-xl: 0 20px 25px rgba(0,0,0,0.15)
```

### Bordas
- **Radius SM**: 4px
- **Radius MD**: 8px
- **Radius LG**: 12px
- **Radius XL**: 16px
- **Radius Full**: 9999px

---

## 📱 Componentes Reutilizáveis

### Score Badge
```html
<span class="score-badge score-87">
  87
</span>
```
```css
.score-badge {
  padding: 4px 12px;
  border-radius: 12px;
  font-weight: 600;
  font-size: 14px;
}
.score-87 {
  background: #D1FAE5;
  color: #065F46;
}
```

### Progress Bar
```html
<div class="progress-bar">
  <div class="progress-fill" style="width: 67%">
    <span>67%</span>
  </div>
</div>
```

### Stat Card
```html
<div class="stat-card">
  <div class="stat-icon">🎯</div>
  <div class="stat-value">847</div>
  <div class="stat-label">Otimizados</div>
  <div class="stat-change positive">↑ 12%</div>
</div>
```

### Button Variants
```html
<button class="btn btn-primary">Otimizar</button>
<button class="btn btn-secondary">Cancelar</button>
<button class="btn btn-success">Aplicar</button>
<button class="btn btn-ghost">Ver Mais</button>
```

---

## 🎯 Estados Interativos

### Loading States
- **Skeleton Screens**: Para carregamento inicial
- **Spinners**: Para ações rápidas
- **Progress Bars**: Para processos longos
- **Shimmer Effect**: Para placeholders

### Empty States
```
┌─────────────────────────────────────┐
│         🤖                          │
│                                     │
│   Nenhuma otimização ainda         │
│                                     │
│   Comece otimizando seus anúncios  │
│   com IA para ver resultados aqui  │
│                                     │
│   [🚀 Otimizar Primeiro Anúncio]   │
│                                     │
└─────────────────────────────────────┘
```

### Error States
```
┌─────────────────────────────────────┐
│         ⚠️                          │
│                                     │
│   Ops! Algo deu errado              │
│                                     │
│   Não foi possível processar sua   │
│   solicitação. Tente novamente.    │
│                                     │
│   [🔄 Tentar Novamente]             │
│   [📞 Contatar Suporte]             │
│                                     │
└─────────────────────────────────────┘
```

---

## 🔔 Notificações e Feedbacks

### Toast Notifications
```
┌────────────────────────────────────────┐
│ ✅ Otimização aplicada com sucesso!   │
│    Score melhorou de 45 para 87       │
│    [Ver Anúncio] [x]                  │
└────────────────────────────────────────┘
```

### Alerts
```
┌────────────────────────────────────────┐
│ ⚠️ ATENÇÃO                             │
│                                        │
│ Esta ação irá substituir o conteúdo   │
│ atual do anúncio. Deseja continuar?   │
│                                        │
│ [Cancelar] [Confirmar]                │
└────────────────────────────────────────┘
```

---

## ⌨️ Atalhos de Teclado

| Atalho | Ação |
|--------|------|
| `Ctrl/Cmd + K` | Quick Search |
| `Ctrl/Cmd + N` | Novo Anúncio |
| `Ctrl/Cmd + O` | Otimizar Selecionado |
| `Ctrl/Cmd + Enter` | Aplicar Otimização |
| `Ctrl/Cmd + R` | Regenerar Sugestões |
| `Ctrl/Cmd + /` | Ver Atalhos |
| `Esc` | Fechar Modal |
| `Tab` | Próximo Campo |
| `Shift + Tab` | Campo Anterior |

---

## 📱 Responsividade

### Mobile (< 768px)
- Stack vertical
- Sidebar colapsável
- Bottom navigation
- Swipe gestures
- Touch-friendly (44px min)

### Tablet (768px - 1024px)
- 2 colunas
- Sidebar expansível
- Mixed navigation

### Desktop (> 1024px)
- 3 colunas
- Sidebar sempre visível
- Hover states
- Keyboard shortcuts

---

## ✨ Animações

### Micro-interactions
- **Hover em cards**: Lift + shadow
- **Click em botão**: Scale down
- **Loading**: Pulse effect
- **Success**: Checkmark animation
- **Score update**: Count-up animation

### Page Transitions
- **Fade in**: 200ms
- **Slide in**: 300ms
- **Modal**: Scale + fade 250ms

---

## 🚀 Performance

### Otimizações
- Lazy loading de componentes pesados
- Virtual scrolling para listas longas
- Debounce em searches (300ms)
- Throttle em scroll events
- Image lazy loading
- Code splitting por rota

### Métricas Alvo
- **FCP** (First Contentful Paint): < 1.5s
- **LCP** (Largest Contentful Paint): < 2.5s
- **FID** (First Input Delay): < 100ms
- **CLS** (Cumulative Layout Shift): < 0.1

---

## ✅ Checklist de Acessibilidade

- [ ] Contrast ratio mínimo 4.5:1
- [ ] Navegação completa por teclado
- [ ] ARIA labels em todos os controles
- [ ] Focus indicators visíveis
- [ ] Screen reader friendly
- [ ] Alt text em todas as imagens
- [ ] Heading hierarchy correta
- [ ] Form labels associados
- [ ] Error messages descritivas

---

*Documento criado em: 24/12/2025*  
*Versão: 1.0*  
*Para dúvidas: consulte AI_OPTIMIZATION_ROADMAP.md*
