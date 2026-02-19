# 🎨 Dashboard de Otimização IA - UX/UI Design

## 📐 Estrutura de Navegação

### Menu Principal (Sidebar)
```
┌─────────────────────────┐
│ 🏠 Dashboard            │
│ 🤖 Otimização IA        │ ← NOVA SEÇÃO
│   ├─ Visão Geral       │
│   ├─ Otimizar Anúncio   │
│   ├─ Otimização em Lote │
│   ├─ Histórico          │
│   └─ Configurações      │
│ 📦 Anúncios             │
│ 📊 Relatórios           │
│ 💰 Financeiro           │
│ ⚙️ Configurações        │
└─────────────────────────┘
```

---

## 🏠 Página 1: Visão Geral (Dashboard Overview)

### Layout
```
┌─────────────────────────────────────────────────────────────────────┐
│  🤖 Otimização IA - Visão Geral                    [⚙️ Configurar] │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌────────────┐  ┌────────────┐  ┌────────────┐  ┌────────────┐  │
│  │    1,247   │  │     892    │  │     71%    │  │     87     │  │
│  │  Anúncios  │  │ Otimizados │  │ Taxa Otim. │  │Score Médio │  │
│  │   Totais   │  │            │  │            │  │            │  │
│  └────────────┘  └────────────┘  └────────────┘  └────────────┘  │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  📊 PERFORMANCE APÓS OTIMIZAÇÃO                                     │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │                                                             │  │
│  │    +142%  Views    |    +89% Visitas    |   +67% Vendas   │  │
│  │                                                             │  │
│  │    [████████████████████] Gráfico de Evolução             │  │
│  │                                                             │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  🎯 ANÚNCIOS PARA OTIMIZAR                      [🔍 Ver Todos →]  │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ 🔴 CRÍTICO (45)     - Score < 50  [Otimizar Todos]          │  │
│  │ 🟠 MÉDIO (180)      - Score 50-70 [Otimizar Todos]          │  │
│  │ 🟡 MELHORAR (135)   - Score 70-85 [Otimizar Todos]          │  │
│  │ 🟢 BOM (887)        - Score > 85  [-]                       │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ⭐ TOP PERFORMERS                              [📊 Ver Análise]  │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │  1. Fone Bluetooth TWS Pro      Score: 98  Views: +287%    │  │
│  │  2. Carregador Turbo USB-C      Score: 96  Views: +245%    │  │
│  │  3. Smartwatch Fit Pro 2        Score: 95  Views: +198%    │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  📈 INSIGHTS E RECOMENDAÇÕES                                        │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │  💡 45 anúncios com score crítico - Otimize agora!         │  │
│  │  💡 180 anúncios podem melhorar com IA                      │  │
│  │  💡 Títulos otimizados têm +89% CTR                         │  │
│  │  💡 Descrições IA aumentam conversão em 67%                 │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Componentes Interativos

#### Cards de Métricas
```html
<div class="stats-grid">
  <div class="stat-card">
    <div class="stat-value">1,247</div>
    <div class="stat-label">Anúncios Totais</div>
    <div class="stat-change">+12 este mês</div>
  </div>
  <!-- More cards... -->
</div>
```

#### Gráfico de Performance
- **Tipo**: Line chart (Chart.js)
- **Eixo X**: Últimos 30 dias
- **Eixo Y**: Views, Visitas, Conversões
- **Interativo**: Hover mostra detalhes

---

## 🤖 Página 2: Otimizar Anúncio Individual

### Layout Principal
```
┌─────────────────────────────────────────────────────────────────────┐
│  🤖 Otimizar Anúncio: MLB123456789              [✖️ Fechar]        │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌──────────────────────┐  ┌──────────────────────┐               │
│  │   SCORE ATUAL: 54    │  │  SCORE PREVISTO: 87  │               │
│  │        🔴            │  │         🟢           │               │
│  └──────────────────────┘  └──────────────────────┘               │
│                                                                     │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ┌──────────────────┬──────────────────┬──────────────────────┐   │
│  │   📝 ANTES       │  🤖 IA SUGESTÃO  │   👁️ PREVIEW        │   │
│  ├──────────────────┼──────────────────┼──────────────────────┤   │
│  │                  │                  │                      │   │
│  │  [Conteúdo       │  [Otimizações    │  [Como ficará        │   │
│  │   original]      │   sugeridas]     │   no ML]             │   │
│  │                  │                  │                      │   │
│  └──────────────────┴──────────────────┴──────────────────────┘   │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Seção de Otimizações (Tabs)

#### Tab 1: Título
```
┌─────────────────────────────────────────────────────────────────────┐
│  📝 TÍTULO                                                          │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ATUAL (Score: 45/100)                                              │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ Fone de Ouvido Bluetooth                                     │  │
│  └─────────────────────────────────────────────────────────────┘  │
│  ⚠️ Muito curto | ⚠️ Faltam keywords | ⚠️ Sem diferenciação      │
│                                                                     │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                     │
│  🤖 SUGESTÕES DA IA                                                 │
│                                                                     │
│  ⭐ RECOMENDADO (Score: 92/100)                                     │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ Fone Bluetooth TWS Sem Fio Esportivo Resistente Água IPX7   │  │
│  └─────────────────────────────────────────────────────────────┘  │
│  ✅ 60 caracteres | ✅ 4 keywords | ✅ Diferenciadores           │
│  Estimativa: +145% CTR                    [✓ Aplicar Este]        │
│                                                                     │
│  ───────────────────────────────────────────────────────────────    │
│                                                                     │
│  Alternativa SEO (Score: 88/100)                                   │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ Fone Ouvido Bluetooth 5.0 TWS Sem Fio Sport Prova D'Água    │  │
│  └─────────────────────────────────────────────────────────────┘  │
│  [▶︎ Ver Mais]                             [Aplicar]              │
│                                                                     │
│  Alternativa CTR (Score: 90/100)                                   │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ Fone Bluetooth Premium TWS | Bateria 48h | Frete Grátis     │  │
│  └─────────────────────────────────────────────────────────────┘  │
│  [▶︎ Ver Mais]                             [Aplicar]              │
│                                                                     │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                     │
│  [🔄 Gerar Novas Sugestões]  [✏️ Editar Manualmente]              │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

#### Tab 2: Descrição
```
┌─────────────────────────────────────────────────────────────────────┐
│  📄 DESCRIÇÃO                                                       │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ATUAL (Score: 38/100)                                              │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ Fone de ouvido bluetooth novo na caixa.                      │  │
│  │ Boa qualidade de som.                                        │  │
│  └─────────────────────────────────────────────────────────────┘  │
│  ⚠️ Muito curta | ⚠️ Pouco persuasiva | ⚠️ Sem especificações    │
│                                                                     │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                     │
│  🤖 DESCRIÇÃO OTIMIZADA (Score: 94/100)                             │
│                                                                     │
│  [Versão: Persuasiva ▼] [Modelo: GPT-4o ▼] [🔄 Regenerar]         │
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ ✅ Fone Bluetooth TWS Sem Fio Esportivo - Tecnologia Premium│  │
│  │                                                              │  │
│  │ 🎧 EXPERIÊNCIA SONORA INCOMPARÁVEL                           │  │
│  │ Mergulhe em um áudio cristalino com drivers premium de      │  │
│  │ 13mm que reproduzem cada nota com perfeição. Tecnologia     │  │
│  │ de cancelamento de ruído garante foco total na sua música.  │  │
│  │                                                              │  │
│  │ 💪 LIBERDADE PARA SEUS TREINOS                               │  │
│  │ • Certificação IPX7 - Resistente a suor e chuva             │  │
│  │ • Ajuste ergonômico - Não cai durante exercícios            │  │
│  │ • Controles touch - Sem precisar tirar do bolso             │  │
│  │                                                              │  │
│  │ 🔋 BATERIA PARA O DIA TODO                                   │  │
│  │ • 8h de reprodução contínua                                 │  │
│  │ • Case com 40h adicionais                                   │  │
│  │ • Carregamento rápido USB-C (15min = 2h uso)                │  │
│  │                                                              │  │
│  │ 📐 ESPECIFICAÇÕES TÉCNICAS                                   │  │
│  │ • Bluetooth: 5.3                                            │  │
│  │ • Alcance: 15 metros                                        │  │
│  │ • Driver: 13mm dinâmico                                     │  │
│  │ • Frequência: 20Hz - 20kHz                                  │  │
│  │ • Peso: 4g cada fone                                        │  │
│  │                                                              │  │
│  │ 📦 INCLUSO NA EMBALAGEM                                      │  │
│  │ • 1x Par de fones TWS                                       │  │
│  │ • 1x Case de carregamento                                   │  │
│  │ • 3x Pares de ponteiras (P/M/G)                             │  │
│  │ • 1x Cabo USB-C                                             │  │
│  │ • Manual em Português                                       │  │
│  │                                                              │  │
│  │ 🛡️ GARANTIA E SUPORTE                                        │  │
│  │ • 12 meses de garantia nacional                             │  │
│  │ • Nota fiscal inclusa                                       │  │
│  │ • Suporte técnico WhatsApp                                  │  │
│  │                                                              │  │
│  │ 🚚 ENTREGA RÁPIDA                                            │  │
│  │ • FRETE GRÁTIS para todo Brasil                             │  │
│  │ • Envio em 24h após confirmação                             │  │
│  │ • Rastreamento completo                                     │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ✅ 1,847 caracteres | ✅ 15 keywords | ✅ Estrutura completa      │
│                                                                     │
│  [Ver Outras Versões]  [✏️ Editar]  [✓ Aplicar]                   │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

#### Tab 3: Ficha Técnica
```
┌─────────────────────────────────────────────────────────────────────┐
│  📋 FICHA TÉCNICA                                                   │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  COMPLETUDE: 62%  [████████░░░░░░]                                 │
│                                                                     │
│  ✅ ATRIBUTOS PREENCHIDOS (18)          [▼ Expandir]               │
│  ⚠️ ATRIBUTOS FALTANTES (11)            [▼ Expandir]               │
│  💡 ATRIBUTOS RECOMENDADOS (8)          [▼ Expandir]               │
│                                                                     │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                     │
│  🤖 SUGESTÕES DA IA                                                 │
│                                                                     │
│  ⚠️ OBRIGATÓRIOS FALTANTES                                          │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ Marca                     : [TWS Pro     ▼] ✓ Aplicar       │  │
│  │ Modelo                    : [X10 Elite  ▼] ✓ Aplicar       │  │
│  │ Conexão                   : [Bluetooth  ▼] ✓ Aplicar       │  │
│  │ Cancelamento de ruído     : [Sim        ▼] ✓ Aplicar       │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  💡 RECOMENDADOS (aumenta visibilidade)                             │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ Versão Bluetooth          : [5.3        ▼] ✓ Aplicar       │  │
│  │ Resistência à água        : [IPX7       ▼] ✓ Aplicar       │  │
│  │ Autonomia bateria         : [8 horas   ▼] ✓ Aplicar       │  │
│  │ Microfone integrado       : [Sim        ▼] ✓ Aplicar       │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  [✓ Aplicar Todos]  [❌ Ignorar Sugestões]                         │
│                                                                     │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                     │
│  📊 ANÁLISE COMPETITIVA                                             │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ Top sellers na categoria têm em média:                       │  │
│  │ • 24 atributos preenchidos (vs 18 seus)                      │  │
│  │ • 98% incluem "Versão Bluetooth"                             │  │
│  │ • 87% incluem "Resistência à água"                           │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

#### Tab 4: Imagens
```
┌─────────────────────────────────────────────────────────────────────┐
│  🖼️ IMAGENS                                                         │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  QUALIDADE GERAL: 68%  [██████████░░░░░░]                          │
│                                                                     │
│  ┌────────┬────────┬────────┬────────┬────────┬────────┐          │
│  │ IMG 1  │ IMG 2  │ IMG 3  │ IMG 4  │ IMG 5  │ IMG 6  │          │
│  │  🟢88  │  🟡72  │  🟡70  │  🔴45  │   -    │   -    │          │
│  │[foto]  │[foto]  │[foto]  │[foto]  │[+Add]  │[+Add]  │          │
│  └────────┴────────┴────────┴────────┴────────┴────────┘          │
│                                                                     │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                     │
│  🤖 ANÁLISE E SUGESTÕES                                             │
│                                                                     │
│  IMAGEM 1 (Score: 88)                                               │
│  ✅ Boa resolução | ✅ Iluminação adequada | ✅ Foco no produto     │
│  💡 Sugestão: Adicionar overlay com texto promocional               │
│  [🎨 Gerar Versão com Overlay]                                     │
│                                                                     │
│  IMAGEM 2 (Score: 72)                                               │
│  ✅ Boa composição | ⚠️ Fundo distraindo                           │
│  💡 Sugestão: Remover fundo para destaque                           │
│  [🎨 Remover Fundo Automaticamente]                                │
│                                                                     │
│  IMAGEM 4 (Score: 45)                                               │
│  ⚠️ Baixa resolução | ⚠️ Imagem escura | ⚠️ Fora de foco           │
│  💡 Sugestão: Substituir por imagem de qualidade                    │
│  [🎨 Gerar Nova Imagem com IA]                                     │
│                                                                     │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                     │
│  🎨 GERAR IMAGENS COM IA                                            │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ Selecione o tipo de imagem:                                  │  │
│  │ [📊 Infográfico de Especificações]                           │  │
│  │ [📏 Guia de Tamanhos]                                        │  │
│  │ [✨ Grid de Características]                                 │  │
│  │ [🎯 Comparativo com Concorrentes]                            │  │
│  │ [🏃 Lifestyle/Em Uso]                                        │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  [🎨 Gerar Imagem]                                                 │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Painel Lateral (Resumo e Ações)
```
┌──────────────────────────┐
│ 📊 RESUMO DA OTIMIZAÇÃO  │
├──────────────────────────┤
│                          │
│ Score Atual:     54 🔴   │
│ Score Previsto:  87 🟢   │
│ Melhoria:      +33pts    │
│                          │
│ ─────────────────────    │
│                          │
│ Mudanças:                │
│ ✓ Título (45→92)         │
│ ✓ Descrição (38→94)      │
│ ✓ Ficha (62%→95%)        │
│ ✓ Imagens (68→85)        │
│                          │
│ ─────────────────────    │
│                          │
│ Estimativa de Impacto:   │
│ Views:      +145%        │
│ CTR:        +89%         │
│ Conversão:  +67%         │
│                          │
├──────────────────────────┤
│                          │
│ [✓ Aplicar Tudo]         │
│ [👁️ Preview Final]       │
│ [💾 Salvar Rascunho]     │
│ [❌ Cancelar]            │
│                          │
└──────────────────────────┘
```

---

## 📦 Página 3: Otimização em Lote

### Layout
```
┌─────────────────────────────────────────────────────────────────────┐
│  📦 Otimização em Lote                          [⚙️ Configurar]    │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  FILTROS: [Categoria ▼] [Score ▼] [Status ▼] [🔍 Buscar]          │
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ ☑️ SELECIONAR TODOS (247 anúncios)         [✓][✗][⚙️]      │  │
│  ├─────────────────────────────────────────────────────────────┤  │
│  │                                                             │  │
│  │ ☑️ Fone Bluetooth TWS          Score: 45  [Otimizar]       │  │
│  │    MLB123456 | Eletrônicos | 12 vendas                     │  │
│  │                                                             │  │
│  │ ☑️ Carregador USB-C Rápido     Score: 52  [Otimizar]       │  │
│  │    MLB123457 | Eletrônicos | 8 vendas                      │  │
│  │                                                             │  │
│  │ ☑️ Smartwatch Fit Pro          Score: 48  [Otimizar]       │  │
│  │    MLB123458 | Eletrônicos | 15 vendas                     │  │
│  │                                                             │  │
│  │ [ ... mais 244 anúncios ... ]                              │  │
│  │                                                             │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                     │
│  AÇÕES EM LOTE:                                                     │
│  [🤖 Otimizar Títulos] [📝 Otimizar Descrições] [📋 Fichas]       │
│  [🖼️ Otimizar Imagens] [✨ Otimização Completa]                   │
│                                                                     │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                     │
│  ⚙️ CONFIGURAÇÕES BATCH:                                            │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ Modelo de IA:        [GPT-4o ▼]                             │  │
│  │ Modo de aplicação:   [Preview antes ▼]                      │  │
│  │ Prioridade:          [Por score (menor primeiro) ▼]         │  │
│  │ Limite diário:       [100 otimizações]                      │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  [🚀 INICIAR OTIMIZAÇÃO EM LOTE (247 anúncios)]                    │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Tela de Processamento
```
┌─────────────────────────────────────────────────────────────────────┐
│  ⚡ Otimizando em Lote...                         [⏸️ Pausar]      │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  PROGRESSO GERAL: 47/247 (19%)                                      │
│  [███████░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░░]                          │
│                                                                     │
│  ┌──────────────────┬──────────────────┬──────────────────────┐   │
│  │ ✅ Processados   │ ⏳ Em Fila       │ ⏱️ Tempo Estimado   │   │
│  │      47          │      200         │    ~45 minutos      │   │
│  └──────────────────┴──────────────────┴──────────────────────┘   │
│                                                                     │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                     │
│  📋 LOG DE ATIVIDADES                              [📄 Export]     │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ ✅ MLB123456 - Otimizado com sucesso (Score: 45→87)         │  │
│  │ ✅ MLB123457 - Otimizado com sucesso (Score: 52→89)         │  │
│  │ ⚠️ MLB123458 - Aviso: Alguns atributos não inferidos        │  │
│  │ ✅ MLB123459 - Otimizado com sucesso (Score: 48→85)         │  │
│  │ ⏳ MLB123460 - Processando...                               │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                     │
│  📊 ESTATÍSTICAS                                                    │
│  • Score médio antes:   51.2                                        │
│  • Score médio depois:  86.7                                        │
│  • Melhoria média:     +35.5 pontos                                 │
│  • Taxa de sucesso:     96% (45/47)                                 │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 📜 Página 4: Histórico de Otimizações

### Layout
```
┌─────────────────────────────────────────────────────────────────────┐
│  📜 Histórico de Otimizações                    [📊 Exportar]      │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  FILTROS: [Período ▼] [Tipo ▼] [Status ▼] [🔍 Buscar]             │
│                                                                     │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ Data       │ Anúncio           │ Tipo    │ Antes │ Depois  │  │
│  ├─────────────────────────────────────────────────────────────┤  │
│  │ 24/12 14:30│ Fone Bluetooth   │ Completa│ 45    │ 87 ✅   │▼│
│  │ 24/12 13:15│ Carregador USB-C │ Título  │ 52    │ 78 ✅   │▼│
│  │ 24/12 12:45│ Smartwatch Fit   │ Descrição│ 48   │ 82 ✅   │▼│
│  │ 24/12 11:20│ Mouse Gamer RGB  │ Completa│ 41    │ 89 ✅   │▼│
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                     │
│  📊 RESUMO DO PERÍODO (Últimos 30 dias)                             │
│  ┌──────────────────┬──────────────────┬──────────────────────┐   │
│  │ Total Otimizados │ Melhoria Média   │ Taxa de Sucesso     │   │
│  │       847        │    +34 pontos    │       97%           │   │
│  └──────────────────┴──────────────────┴──────────────────────┘   │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

### Detalhes de uma Otimização (Expandido)
```
┌─────────────────────────────────────────────────────────────────────┐
│  📋 Fone Bluetooth TWS - Otimização Completa                        │
│  Data: 24/12/2025 14:30 | Modelo: GPT-4o | Tempo: 12s              │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  ANTES                          │  DEPOIS                           │
│  ───────────────────────────────┼───────────────────────────────── │
│  Score Total: 45 🔴             │  Score Total: 87 🟢               │
│                                 │                                   │
│  Título (Score: 32)             │  Título (Score: 92) +60          │
│  • Muito curto                  │  • Comprimento ideal              │
│  • Poucas keywords              │  • 4 keywords otimizadas          │
│                                 │                                   │
│  Descrição (Score: 28)          │  Descrição (Score: 94) +66       │
│  • Muito básica                 │  • Estrutura completa             │
│  • Não persuasiva               │  • Copywriting otimizado          │
│                                 │                                   │
│  Ficha (Score: 55)              │  Ficha (Score: 92) +37           │
│  • 11 atributos faltando        │  • Completude 95%                 │
│                                 │                                   │
│  Imagens (Score: 65)            │  Imagens (Score: 78) +13         │
│  • 2 imagens baixa qualidade    │  • Todas otimizadas               │
│                                 │                                   │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                     │
│  📈 IMPACTO OBSERVADO (7 dias após)                                 │
│  • Views:       +156% (234 → 599)                                   │
│  • Visitas:     +92% (47 → 90)                                      │
│  • Conversões:  +71% (3 → 5,13 vendas)                              │
│  • Revenue:     +R$ 847,50                                          │
│                                                                     │
│  [Ver Detalhes Completos] [🔄 Reverter] [📊 Analytics]             │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## ⚙️ Página 5: Configurações

### Layout
```
┌─────────────────────────────────────────────────────────────────────┐
│  ⚙️ Configurações da IA                                             │
├─────────────────────────────────────────────────────────────────────┤
│                                                                     │
│  🤖 MODELOS DE IA                                                   │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ Modelo Principal:     [GPT-4o ▼]                            │  │
│  │ Modelo Alternativo:   [Claude 3.5 Sonnet ▼]                 │  │
│  │ Fallback:             [Gemini Pro ▼]                        │  │
│  │                                                              │  │
│  │ ☑️ Usar múltiplos modelos e escolher melhor resultado       │  │
│  │ ☑️ Cache de respostas (economia de custos)                  │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                     │
│  📝 PERSONALIZAÇÃO                                                  │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ Tom de voz:           [Profissional ▼]                      │  │
│  │                       Opções: Casual, Profissional,          │  │
│  │                       Técnico, Persuasivo                    │  │
│  │                                                              │  │
│  │ Estilo de descrição:  [Detalhado ▼]                         │  │
│  │                       Opções: Conciso, Detalhado, Premium    │  │
│  │                                                              │  │
│  │ Foco principal:       [SEO + Conversão ▼]                   │  │
│  │                       Opções: SEO, Conversão, Balanced       │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                     │
│  ⚡ AUTOMAÇÃO                                                        │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ ☑️ Auto-otimizar anúncios com score < 50                    │  │
│  │ ☑️ Sugerir otimização para scores 50-70                     │  │
│  │ ☑️ Enviar relatório semanal de performance                  │  │
│  │ ☐ Aplicar otimizações automaticamente (sem aprovação)       │  │
│  │                                                              │  │
│  │ Horário de processamento: [02:00 - 06:00 ▼]                 │  │
│  │ Limite diário:            [100 otimizações]                 │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  ─────────────────────────────────────────────────────────────────  │
│                                                                     │
│  💰 USO E CUSTOS                                                    │
│  ┌─────────────────────────────────────────────────────────────┐  │
│  │ Este mês:                                                    │  │
│  │ • Otimizações realizadas:  847                               │  │
│  │ • Custo estimado:          R$ 127,05                         │  │
│  │ • Limite mensal:           R$ 500,00                         │  │
│  │ • Uso:                     25% [█████░░░░░░░░░░░░░░░]       │  │
│  │                                                              │  │
│  │ [📊 Ver Detalhes de Uso]                                    │  │
│  └─────────────────────────────────────────────────────────────┘  │
│                                                                     │
│  [💾 Salvar Configurações]                                         │
│                                                                     │
└─────────────────────────────────────────────────────────────────────┘
```

---

## 🎨 Design System

### Cores
```css
/* Scores */
--score-critical: #EF4444  /* < 50 */
--score-warning:  #F59E0B  /* 50-70 */
--score-good:     #10B981  /* 70-85 */
--score-excellent:#06B6D4  /* > 85 */

/* Status */
--status-processing: #3B82F6
--status-success:    #10B981
--status-error:      #EF4444
--status-pending:    #F59E0B

/* UI */
--primary:   #6366F1  /* Indigo */
--secondary: #8B5CF6  /* Purple */
--accent:    #EC4899  /* Pink */
--background:#F9FAFB
--surface:   #FFFFFF
--text:      #1F2937
```

### Componentes Reutilizáveis

#### Score Badge
```html
<span class="score-badge score-87">
  87
</span>
```

#### Progress Bar
```html
<div class="progress-bar">
  <div class="progress-fill" style="width: 67%">67%</div>
</div>
```

#### Stat Card
```html
<div class="stat-card">
  <div class="stat-icon">🎯</div>
  <div class="stat-value">847</div>
  <div class="stat-label">Otimizados</div>
  <div class="stat-change positive">+12%</div>
</div>
```

---

## 📱 Responsividade

### Mobile Layout
- Stack vertical dos painéis
- Tabs em accordion
- Ações em bottom sheet
- Swipe entre versões
- Preview fullscreen

### Tablet Layout
- 2 colunas (preview + editor)
- Sidebar colapsável
- Touch-friendly controls

### Desktop
- 3 colunas (original + IA + preview)
- Keyboard shortcuts
- Drag & drop
- Multi-select

---

## ⌨️ Atalhos de Teclado

```
Ctrl/Cmd + K     : Quick Search
Ctrl/Cmd + N     : Novo Anúncio
Ctrl/Cmd + O     : Otimizar Selecionado
Ctrl/Cmd + Enter : Aplicar Otimização
Ctrl/Cmd + R     : Regenerar Sugestões
Ctrl/Cmd + /     : Ver Atalhos
Esc              : Fechar Modal
```

---

## 🚀 Performance

### Otimizações
- Lazy loading de imagens
- Virtual scrolling para listas grandes
- Debounce em pesquisas
- Cache de resultados IA
- Progressive Web App (PWA)
- Service Workers para offline

---

## ✅ Checklist de UX

- [ ] Loading states em todas as ações
- [ ] Empty states informativos
- [ ] Error handling amigável
- [ ] Confirmações para ações destrutivas
- [ ] Tooltips em campos complexos
- [ ] Breadcrumbs para navegação
- [ ] Feedback visual imediato
- [ ] Undo/Redo quando aplicável
- [ ] Salvamento automático de rascunhos
- [ ] Indicadores de progresso

---

*Documento criado em: 24/12/2025*  
*Versão: 1.0*
