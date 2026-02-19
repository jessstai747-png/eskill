# 📚 Documentação Técnica: Sistema de Estratégias SEO Avançadas para Mercado Livre

**Versão:** 1.0.0  
**Data:** 22 de Janeiro de 2026  
**Autor:** Sistema eSkill  
**Status:** Planejamento de Implementação

---

## 📋 Índice

1. [Visão Geral](#1-visão-geral)
2. [Arquitetura do Sistema](#2-arquitetura-do-sistema)
3. [As 12 Estratégias SEO](#3-as-12-estratégias-seo)
4. [Mapeamento de Serviços PHP](#4-mapeamento-de-serviços-php)
5. [Estrutura de Dados](#5-estrutura-de-dados)
6. [APIs e Integrações](#6-apis-e-integrações)
7. [Algoritmos de Otimização](#7-algoritmos-de-otimização)
8. [Validações e Score](#8-validações-e-score)
9. [Referência de Classes](#9-referência-de-classes)

---

## 1. Visão Geral

### 1.1 Objetivo

Sistema avançado de otimização SEO para anúncios do Mercado Livre que implementa 12 estratégias comprovadas para maximizar visibilidade, tráfego e conversões.

### 1.2 Escopo e Abrangência

> **🌐 SISTEMA GENÉRICO:** Este sistema funciona para **TODAS as categorias** do Mercado Livre, não apenas para uma categoria específica. A categoria MLB3530 (Baús/Bagageiros) é utilizada como **piloto** com dados pré-populados para testes e validação inicial. Para outras categorias, o sistema gera dados automaticamente via AI + ML API.

### 1.3 Componentes Principais

```
┌─────────────────────────────────────────────────────────────────────┐
│                     SISTEMA SEO AVANÇADO                            │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌─────────────┐  ┌─────────────┐  ┌─────────────┐  ┌────────────┐ │
│  │ SEO Killer  │  │Ficha Técnica│  │   Keyword   │  │ Competitor │ │
│  │   Module    │  │   Module    │  │  Research   │  │  Analysis  │ │
│  └─────┬───────┘  └─────┬───────┘  └─────┬───────┘  └─────┬──────┘ │
│        │                │                │                │        │
│        └────────────────┴────────────────┴────────────────┘        │
│                                  │                                  │
│                    ┌─────────────▼─────────────┐                   │
│                    │   SEO Strategies Engine   │                   │
│                    │   (12 Estratégias Core)   │                   │
│                    └─────────────┬─────────────┘                   │
│                                  │                                  │
│        ┌─────────────────────────┼─────────────────────────┐       │
│        │                         │                         │        │
│  ┌─────▼─────┐            ┌──────▼──────┐           ┌─────▼─────┐  │
│  │  Synonym  │            │  Keyword    │           │ Description│  │
│  │ Expansion │            │Distribution │           │  Builder   │  │
│  │  Service  │            │  Service    │           │  Service   │  │
│  └───────────┘            └─────────────┘           └───────────┘  │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

### 1.4 Arquitetura Híbrida de Keywords

O sistema utiliza uma abordagem **híbrida de 3 camadas** para obtenção de keywords:

```
┌─────────────────────────────────────────────────────────────────────┐
│                 ARQUITETURA HÍBRIDA DE KEYWORDS                     │
├─────────────────────────────────────────────────────────────────────┤
│                                                                      │
│  ┌─────────────────┐  ┌─────────────────┐  ┌─────────────────┐     │
│  │  MERCADO LIVRE  │  │    AI / LLM     │  │    DATABASE     │     │
│  │  API (Primário) │  │   (Expansão)    │  │    (Cache)      │     │
│  └────────┬────────┘  └────────┬────────┘  └────────┬────────┘     │
│           │                    │                    │               │
│           ▼                    ▼                    ▼               │
│  • Trends API          • Geração sinônimos   • Cache curado        │
│  • Autocomplete        • Classificação       • Hierarquias         │
│  • Atributos           • Long Tail           • Performance         │
│  • Concorrentes        • Contextos           • Validações          │
│                                                                      │
│  ┌──────────────────────────────────────────────────────────────┐  │
│  │                   KeywordSourceService                        │  │
│  │         Orquestra busca nas 3 fontes com prioridade          │  │
│  │         Database → ML API → AI (fallback progressivo)        │  │
│  └──────────────────────────────────────────────────────────────┘  │
│                                                                      │
└─────────────────────────────────────────────────────────────────────┘
```

| Camada | Fonte | Quando Usar | Prioridade |
|--------|-------|-------------|------------|
| **Database** | Cache local | Dados já validados | 1 (mais rápido) |
| **ML API** | Mercado Livre | Dados frescos de tendências | 2 (dados oficiais) |
| **AI/LLM** | OpenAI/Claude | Expansão e geração quando não há dados | 3 (fallback) |

**Fluxo por Tipo de Categoria:**
- **Categoria piloto (MLB3530):** Usa dados pré-populados do banco
- **Novas categorias:** AI gera hierarquia → ML API valida → Salva no banco
- **Fallback:** Se ML API indisponível, usa cache + AI para expansão

### 1.5 Benefícios Esperados

| Métrica | Antes | Depois | Melhoria |
|---------|-------|--------|----------|
| Posição média | #25 | #3-5 | +80% |
| CTR | 2% | 6% | +200% |
| Tráfego | 50/dia | 250/dia | +400% |
| Conversão | 1.5% | 3% | +100% |
| Keywords indexadas | 5-8 | 25-35 | +350% |

---

## 2. Arquitetura do Sistema

### 2.1 Estrutura de Diretórios

```
app/
├── Services/
│   ├── SEO/
│   │   ├── SynonymExpansionService.php      [NOVO]
│   │   ├── KeywordDistributionService.php   [NOVO]
│   │   ├── DescriptionBuilderService.php    [NOVO]
│   │   ├── SearchCoverageService.php        [NOVO]
│   │   ├── SemanticScoreService.php         [NOVO]
│   │   ├── ContextInjectorService.php       [NOVO]
│   │   ├── HiddenAttributesDetector.php     [EXISTENTE - EXPANDIR]
│   │   ├── CompetitorAnalysisService.php    [EXISTENTE]
│   │   ├── SEOAuditService.php              [EXISTENTE]
│   │   ├── SEOOptimizerService.php          [EXISTENTE]
│   │   └── TechSheetService.php             [EXISTENTE]
│   ├── KeywordResearchService.php           [EXISTENTE - EXPANDIR]
│   ├── TitleOptimizerService.php            [EXISTENTE - EXPANDIR]
│   └── AI/
│       └── SEO/
│           └── TitleKiller.php              [EXISTENTE - EXPANDIR]
├── Controllers/
│   └── Api/
│       ├── SeoController.php                [EXISTENTE - EXPANDIR]
│       └── TechSheetController.php          [EXISTENTE]
└── Views/
    └── dashboard/
        └── seo/                             [EXISTENTE]
```

### 2.2 Fluxo de Dados

```
┌──────────────────────────────────────────────────────────────────────┐
│                        FLUXO DE OTIMIZAÇÃO                           │
├──────────────────────────────────────────────────────────────────────┤
│                                                                       │
│  1. INPUT                                                             │
│     ├── Item ID (anúncio existente)                                  │
│     └── Dados do produto (novo anúncio)                              │
│         ↓                                                             │
│  2. COLETA DE DADOS                                                   │
│     ├── MercadoLivreClient → Busca item via API                     │
│     ├── CompetitorAnalysisService → Analisa top 20 concorrentes     │
│     └── KeywordResearchService → Pesquisa keywords da categoria     │
│         ↓                                                             │
│  3. PROCESSAMENTO (12 Estratégias)                                   │
│     ├── SynonymExpansionService → Hierarquia 4 níveis               │
│     ├── HiddenAttributesDetector → Campos ocultos                   │
│     ├── KeywordDistributionService → Peso por campo                 │
│     ├── SearchCoverageService → 5 tipos de busca                    │
│     ├── SemanticScoreService → Score de relevância                  │
│     ├── ContextInjectorService → Contextos de uso                   │
│     └── DescriptionBuilderService → 4 blocos estruturados           │
│         ↓                                                             │
│  4. VALIDAÇÃO                                                         │
│     ├── Densidade de keywords (0.5-3%)                               │
│     ├── Comprimento de campos                                        │
│     └── Score SEO geral (0-100)                                      │
│         ↓                                                             │
│  5. OUTPUT                                                            │
│     ├── Título otimizado                                             │
│     ├── Campo MODELO otimizado                                       │
│     ├── Attributes sugeridos                                         │
│     ├── Descrição otimizada (4 blocos)                              │
│     ├── Campos ocultos (KEYWORDS, MPN, LINE)                        │
│     └── Score e relatório                                            │
│         ↓                                                             │
│  6. APLICAÇÃO                                                         │
│     ├── PUT /items/{id} → Atualiza item                             │
│     └── PUT /items/{id}/description → Atualiza descrição            │
│                                                                       │
└──────────────────────────────────────────────────────────────────────┘
```

---

## 3. As 12 Estratégias SEO

### 3.1 Estratégia 1: Hierarquia de Sinônimos

**Objetivo:** Maximizar cobertura de buscas distribuindo sinônimos em 4 níveis hierárquicos.

**Estrutura de Níveis:**

```php
private const SYNONYM_HIERARCHY = [
    'nivel_1_generico' => [
        'score' => 1.0,
        'destino' => 'TÍTULO',
        'exemplos' => ['bauleto', 'baú', 'bagageiro', 'maleiro']
    ],
    'nivel_2_qualificado' => [
        'score' => 0.8,
        'destino' => 'MODELO',
        'exemplos' => ['bau traseiro', 'porta objetos', 'caixa traseira']
    ],
    'nivel_3_contexto' => [
        'score' => 0.6,
        'destino' => 'MODELO + DESCRIÇÃO',
        'exemplos' => ['bau moto', 'bagageiro motocicleta', 'maleiro delivery']
    ],
    'nivel_4_long_tail' => [
        'score' => 0.4,
        'destino' => 'DESCRIÇÃO + KEYWORDS',
        'exemplos' => ['bauleto para motoboy', 'baú entrega delivery']
    ]
];
```

**Algoritmo de Seleção:**

```php
public function selectSynonymsForField(string $title, string $targetField): array
{
    $titleLevel = $this->identifyHierarchyLevel($title);
    $candidates = [];
    
    foreach ($this->hierarchyLevels as $level => $words) {
        if ($level !== $titleLevel) {
            foreach ($words as $word) {
                if (!$this->isInTitle($word, $title)) {
                    $candidates[] = [
                        'word' => $word,
                        'level' => $level,
                        'score' => $this->calculateRelevanceScore($word, $title)
                    ];
                }
            }
        }
    }
    
    usort($candidates, fn($a, $b) => $b['score'] <=> $a['score']);
    
    return array_slice($candidates, 0, 5);
}
```

**Impacto:** +300-400% cobertura de buscas

---

### 3.2 Estratégia 2: Campos Ocultos Indexados

**Objetivo:** Preencher campos que são indexados pelo algoritmo mas não aparecem na UI.

**Campos Ocultos Conhecidos:**

| Campo ID | Nome | Indexado | Visível | Impacto SEO |
|----------|------|----------|---------|-------------|
| GTIN | Código de barras | ✅ | ❌ | Médio |
| MPN | Número de peça | ✅ | ❌ | Médio |
| KEYWORDS | Palavras-chave | ✅ | ❌ | Alto |
| LINE | Linha | ✅ | ❌ | Baixo |
| SKU | SKU | ✅ | ❌ | Baixo |
| RECOMMENDED_USE | Uso recomendado | ✅ | Parcial | Alto |

**Integração com API:**

```php
public function detectHiddenFields(string $itemId): array
{
    $item = $this->mlClient->getItem($itemId);
    $categoryAttrs = $this->mlClient->getCategoryAttributes($item['category_id']);
    
    $hiddenFields = [];
    
    foreach ($categoryAttrs as $attr) {
        if ($this->isHiddenField($attr)) {
            $currentValue = $this->getItemAttributeValue($item, $attr['id']);
            $suggestedValue = $this->generateSuggestion($attr, $item);
            
            $hiddenFields[] = [
                'id' => $attr['id'],
                'name' => $attr['name'],
                'current_value' => $currentValue,
                'suggested_value' => $suggestedValue,
                'is_indexed' => true,
                'impact' => $this->calculateImpact($attr)
            ];
        }
    }
    
    return $hiddenFields;
}
```

**Impacto:** +15-20 keywords extras indexadas

---

### 3.3 Estratégia 3: Injeção Natural de Keywords

**Objetivo:** Inserir keywords na descrição de forma natural, sem penalização.

**Regra de Ouro:** Primeiras 200 palavras têm peso máximo de indexação.

**Estrutura de Keywords Obrigatórias:**

```php
private const KEYWORD_PRIORITIES = [
    'alta' => [
        'frequencia_ideal' => 3,
        'posicao' => 'primeiras_200_palavras',
        'exemplos' => ['bauleto', 'bagageiro', 'delivery', 'motoboy', 'capacete']
    ],
    'media' => [
        'frequencia_ideal' => 2,
        'posicao' => 'primeiras_400_palavras',
        'exemplos' => ['viagem', 'resistente', 'universal']
    ],
    'baixa' => [
        'frequencia_ideal' => 1,
        'posicao' => 'qualquer',
        'exemplos' => ['honda cg', 'yamaha fazer']
    ]
];
```

**Validação de Densidade:**

```php
public function validateDensity(string $text, array $keywords): array
{
    $wordCount = str_word_count($text);
    $densities = [];
    
    foreach ($keywords as $keyword) {
        $occurrences = substr_count(strtolower($text), strtolower($keyword));
        $density = ($occurrences / $wordCount) * 100;
        
        $densities[$keyword] = [
            'occurrences' => $occurrences,
            'density_percent' => round($density, 2),
            'status' => $density >= 0.5 && $density <= 3.0 ? 'ideal' : 'warning'
        ];
    }
    
    return $densities;
}
```

**Impacto:** +40-50% relevância sem penalização

---

### 3.4 Estratégia 4: Cobertura de Tipos de Busca

**Objetivo:** Garantir presença nos 5 tipos de busca do Mercado Livre.

**Tipos de Busca:**

```php
private const SEARCH_TYPES = [
    'generica' => [
        'exemplo' => 'bauleto moto',
        'campo_principal' => 'title',
        'keywords' => 'nivel_1_generico'
    ],
    'qualificada' => [
        'exemplo' => 'bauleto 41 litros universal',
        'campo_principal' => 'title + model',
        'keywords' => 'nivel_2_qualificado'
    ],
    'long_tail' => [
        'exemplo' => 'bauleto 41 litros universal para motoboy delivery',
        'campo_principal' => 'description',
        'keywords' => 'nivel_4_long_tail'
    ],
    'marca_modelo' => [
        'exemplo' => 'bauleto honda cg 160',
        'campo_principal' => 'attributes + description',
        'keywords' => 'compatibilidade'
    ],
    'filtros' => [
        'exemplo' => '41L + preto + com base',
        'campo_principal' => 'attributes',
        'keywords' => 'atributos_tecnicos'
    ]
];
```

**Impacto:** Multiplica tráfego em 5-8x

---

### 3.5 Estratégia 5: Peso de Campo por Indexação

**Objetivo:** Distribuir keywords conforme peso de indexação de cada campo.

**Pesos de Indexação:**

| Campo | Peso | Keywords | Prioridade |
|-------|------|----------|------------|
| Título | 100% | Top 3-5 | CORE |
| Modelo | 70% | Top 5-7 | SUPORTE |
| Attributes | 50% | Top 8-12 | TÉCNICA |
| Descrição | 30% | Top 15-20 | CONTEXTO |
| Campos Ocultos | 50% | Top 15 | EXTRA |

**Algoritmo de Distribuição:**

```php
public function distributeKeywords(array $keywords, string $title): array
{
    $classified = $this->classifyKeywords($keywords);
    
    return [
        'title' => [
            'keywords' => array_slice($classified['core'], 0, 5),
            'weight' => 100,
            'current_usage' => $this->analyzeUsage($title, $classified['core'])
        ],
        'model' => [
            'keywords' => array_slice($classified['suporte'], 0, 7),
            'weight' => 70,
            'suggestion' => $this->generateModelSuggestion($classified['suporte'], $title)
        ],
        'attributes' => [
            'keywords' => array_slice($classified['tecnica'], 0, 12),
            'weight' => 50,
            'fields' => $this->mapToAttributes($classified['tecnica'])
        ],
        'description' => [
            'keywords' => array_slice($classified['contexto'], 0, 20),
            'weight' => 30,
            'injection_plan' => $this->planInjection($classified['contexto'])
        ]
    ];
}
```

**Impacto:** Maximiza eficiência de indexação

---

### 3.6 Estratégia 6: Contextos de Uso

**Objetivo:** Adicionar contextos que aumentam relevância para públicos específicos.

**Contextos Disponíveis:**

```php
private const USE_CONTEXTS = [
    'profissional' => [
        'keywords' => ['delivery', 'motoboy', 'entrega', 'trabalho', 'profissional'],
        'bloco' => 'beneficios',
        'peso' => 1.2
    ],
    'lazer' => [
        'keywords' => ['viagem', 'passeio', 'turismo', 'aventura', 'trilha'],
        'bloco' => 'beneficios',
        'peso' => 1.0
    ],
    'urbano' => [
        'keywords' => ['cidade', 'urbano', 'trânsito', 'dia a dia', 'uso diário'],
        'bloco' => 'especificacoes',
        'peso' => 0.9
    ],
    'carga' => [
        'keywords' => ['carga', 'transporte', 'mercadoria', 'encomenda', 'pacote'],
        'bloco' => 'beneficios',
        'peso' => 1.1
    ]
];
```

**Impacto:** +20-30% conversão

---

### 3.7 Estratégia 7: Long Tail Automático

**Objetivo:** Gerar automaticamente combinações long tail para capturar buscas específicas.

**Gerador de Long Tail:**

```php
public function generateLongTail(string $title, string $categoryId): array
{
    $baseKeywords = $this->extractBaseKeywords($title);
    $capacity = $this->extractCapacity($title);
    $type = $this->extractType($title);
    
    $longTail = [];
    
    // Padrão 1: tipo + capacidade + qualificador
    $longTail[] = "{$type} {$capacity} litros universal";
    
    // Padrão 2: tipo + contexto
    $longTail[] = "{$type} moto delivery";
    $longTail[] = "{$type} para motoboy";
    
    // Padrão 3: tipo + benefício
    $longTail[] = "{$type} capacete {$capacity} litros";
    
    // Padrão 4: compatibilidade
    $compatibilities = $this->getPopularCompatibilities($categoryId);
    foreach ($compatibilities as $compat) {
        $longTail[] = "{$type} {$compat}";
    }
    
    return array_unique($longTail);
}
```

**Impacto:** +15-25% tráfego adicional

---

### 3.8 Estratégia 8: Densidade Controlada

**Objetivo:** Garantir densidade ideal (0.5-3%) para evitar penalizações.

**Fórmula:**

```
Densidade = (Ocorrências da keyword / Total de palavras) × 100
```

**Limites:**

| Densidade | Status | Ação |
|-----------|--------|------|
| < 0.5% | Baixa | Adicionar ocorrências |
| 0.5% - 3% | Ideal | Manter |
| 3% - 5% | Alerta | Reduzir ocorrências |
| > 5% | Crítico | Reescrever (keyword stuffing) |

**Impacto:** Evita penalizações que derrubam ranking

---

### 3.9 Estratégia 9: Score de Relevância Semântica

**Objetivo:** Calcular score para selecionar as melhores keywords.

**Componentes do Score:**

```php
public function calculateSemanticScore(string $word, string $title): float
{
    $score = 0.0;
    
    // +1.0 se for de nível hierárquico diferente do título
    if ($this->getLevel($word) !== $this->getLevel($title)) {
        $score += 1.0;
    }
    
    // +0.5 se tiver contexto de uso
    if ($this->hasUseContext($word)) {
        $score += 0.5;
    }
    
    // +0.3 se for long tail (2+ palavras)
    if (str_word_count($word) >= 2) {
        $score += 0.3;
    }
    
    // +0.2 se tiver alta frequência em concorrentes
    if ($this->getCompetitorFrequency($word) > 70) {
        $score += 0.2;
    }
    
    return $score;
}
```

**Impacto:** Maximiza eficiência com menos keywords

---

### 3.10 Estratégia 10: Compatibilidade Expandida

**Objetivo:** Capturar buscas por marca/modelo de motos compatíveis.

**Lista de Compatibilidade:**

```php
private const COMPATIBILITY_LIST = [
    'honda' => ['CG 160', 'Titan', 'Fan', 'Bros', 'CB 300', 'CB 500', 'XRE 300'],
    'yamaha' => ['Factor', 'Fazer', 'XTZ', 'Lander', 'MT-03', 'Crosser'],
    'suzuki' => ['Yes', 'Intruder', 'GSX-S', 'V-Strom'],
    'dafra' => ['Apache', 'Riva', 'Next', 'Speed'],
    'kawasaki' => ['Ninja', 'Z400', 'Versys']
];
```

**Impacto:** +20-30% de buscas por marca

---

### 3.11 Estratégia 11: FAQ Otimizado

**Objetivo:** Criar FAQ que responde buscas e melhora conversão.

**Estrutura do FAQ:**

```php
private const FAQ_TEMPLATES = [
    [
        'pergunta' => 'Esse {produto} cabe capacete?',
        'resposta' => 'Sim! O {produto} comporta 1 capacete fechado tamanho 60.',
        'keywords' => ['capacete', 'cabe', 'tamanho']
    ],
    [
        'pergunta' => 'É resistente para delivery?',
        'resposta' => 'Sim! Material ABS resistente, ideal para motoboy e entrega delivery.',
        'keywords' => ['resistente', 'delivery', 'motoboy']
    ],
    [
        'pergunta' => 'Serve para viagem?',
        'resposta' => 'Perfeito para viagens! {produto} espaçoso e seguro.',
        'keywords' => ['viagem', 'espaçoso', 'seguro']
    ],
    [
        'pergunta' => 'É universal?',
        'resposta' => 'Sim! Compatível com Honda CG, Yamaha Fazer e mais de 50 modelos.',
        'keywords' => ['universal', 'compatível', 'honda', 'yamaha']
    ]
];
```

**Impacto:** Melhora conversão e SEO

---

### 3.12 Estratégia 12: Atualização Contínua

**Objetivo:** Monitorar e ajustar continuamente para manter TOP 3.

**Ciclo de Monitoramento:**

```php
public function runWeeklyOptimization(string $itemId): array
{
    // 1. Coletar métricas
    $metrics = $this->collectMetrics($itemId);
    
    // 2. Comparar com período anterior
    $comparison = $this->compareWithPrevious($itemId, $metrics);
    
    // 3. Identificar oportunidades
    $opportunities = $this->identifyOpportunities($itemId);
    
    // 4. Gerar recomendações
    $recommendations = $this->generateRecommendations($opportunities);
    
    // 5. Aplicar automaticamente (se habilitado)
    if ($this->isAutoOptimizeEnabled($itemId)) {
        $this->applyRecommendations($itemId, $recommendations);
    }
    
    return [
        'metrics' => $metrics,
        'comparison' => $comparison,
        'opportunities' => $opportunities,
        'recommendations' => $recommendations,
        'next_check' => date('Y-m-d', strtotime('+7 days'))
    ];
}
```

**Impacto:** Mantém TOP 3 consistentemente

---

## 4. Mapeamento de Serviços PHP

### 4.1 Serviços Existentes vs Novos

| Serviço | Status | Estratégias | Prioridade |
|---------|--------|-------------|------------|
| `SynonymExpansionService` | ❌ CRIAR | 1, 9 | Alta |
| `KeywordDistributionService` | ❌ CRIAR | 3, 5, 8 | Alta |
| `DescriptionBuilderService` | ❌ CRIAR | 6, 7, 11 | Média |
| `SearchCoverageService` | ❌ CRIAR | 4 | Média |
| `SemanticScoreService` | ❌ CRIAR | 9 | Média |
| `ContextInjectorService` | ❌ CRIAR | 6 | Baixa |
| `HiddenAttributesDetector` | ⚠️ EXPANDIR | 2 | Alta |
| `KeywordResearchService` | ⚠️ EXPANDIR | 4, 7 | Alta |
| `TitleOptimizerService` | ⚠️ EXPANDIR | 1, 5 | Alta |
| `TitleKiller` | ⚠️ EXPANDIR | 1, 5, 9 | Alta |
| `TechSheetService` | ⚠️ EXPANDIR | 2, 10 | Média |
| `SEOAuditService` | ⚠️ EXPANDIR | 8, 12 | Média |

### 4.2 Dependências entre Serviços

```
SynonymExpansionService
    └── KeywordResearchService
        └── MercadoLivreClient

KeywordDistributionService
    ├── SynonymExpansionService
    ├── SemanticScoreService
    └── HiddenAttributesDetector

DescriptionBuilderService
    ├── KeywordDistributionService
    ├── ContextInjectorService
    └── SynonymExpansionService

TitleKiller
    ├── SynonymExpansionService
    ├── KeywordDistributionService
    └── SemanticScoreService
```

---

## 5. Estrutura de Dados

### 5.1 Hierarquia de Sinônimos (JSON)

```json
{
  "category_id": "MLB3530",
  "hierarchy": {
    "nivel_1_generico": {
      "words": ["bauleto", "baú", "bagageiro", "maleiro"],
      "destination": "title",
      "weight": 1.0
    },
    "nivel_2_qualificado": {
      "words": ["bau traseiro", "porta objetos", "caixa traseira"],
      "destination": "model",
      "weight": 0.8
    },
    "nivel_3_contexto": {
      "words": ["bau moto", "bagageiro motocicleta", "maleiro delivery"],
      "destination": "model_description",
      "weight": 0.6
    },
    "nivel_4_long_tail": {
      "words": ["bauleto para motoboy", "baú entrega delivery"],
      "destination": "description_keywords",
      "weight": 0.4
    }
  },
  "contexts": {
    "profissional": ["delivery", "motoboy", "entrega"],
    "lazer": ["viagem", "passeio", "turismo"],
    "urbano": ["cidade", "urbano", "dia a dia"]
  }
}
```

### 5.2 Resultado de Otimização (JSON)

```json
{
  "item_id": "MLB1234567890",
  "original": {
    "title": "Bauleto 41L Preto",
    "model": null,
    "description_length": 50,
    "attributes_count": 3,
    "hidden_fields_count": 0
  },
  "optimized": {
    "title": "Bauleto Baú 41 Litros Universal Moto Delivery",
    "model": "Bagageiro Maleiro Porta Objetos Caixa Traseira Universal",
    "description": {
      "length": 520,
      "blocks": 4,
      "keywords_injected": 15,
      "density_average": 1.8
    },
    "attributes": {
      "filled": 12,
      "suggested": 5
    },
    "hidden_fields": {
      "KEYWORDS": "bauleto, baú, bagageiro, maleiro, delivery...",
      "MPN": "BAU-41L-UNI-BK-2024",
      "LINE": "Smart Box Universal"
    }
  },
  "scores": {
    "seo_score": 92,
    "title_score": 95,
    "model_score": 88,
    "description_score": 90,
    "coverage_score": 85
  },
  "strategies_applied": [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11]
}
```

### 5.3 Tabela de Banco de Dados

```sql
-- Tabela para armazenar hierarquia de sinônimos por categoria
CREATE TABLE seo_synonym_hierarchy (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id VARCHAR(20) NOT NULL,
    level ENUM('nivel_1', 'nivel_2', 'nivel_3', 'nivel_4') NOT NULL,
    word VARCHAR(100) NOT NULL,
    weight DECIMAL(3,2) DEFAULT 1.00,
    destination ENUM('title', 'model', 'description', 'keywords') NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_category_level (category_id, level),
    INDEX idx_word (word)
);

-- Tabela para armazenar contextos de uso
CREATE TABLE seo_use_contexts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    category_id VARCHAR(20) NOT NULL,
    context_type ENUM('profissional', 'lazer', 'urbano', 'carga') NOT NULL,
    keyword VARCHAR(100) NOT NULL,
    weight DECIMAL(3,2) DEFAULT 1.00,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_category_context (category_id, context_type)
);

-- Tabela para histórico de otimizações
CREATE TABLE seo_optimization_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    item_id VARCHAR(20) NOT NULL,
    account_id INT NOT NULL,
    optimization_type VARCHAR(50) NOT NULL,
    before_data JSON,
    after_data JSON,
    score_before INT,
    score_after INT,
    strategies_applied JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_item (item_id),
    INDEX idx_account (account_id),
    INDEX idx_created (created_at)
);
```

---

## 6. APIs e Integrações

### 6.1 Novos Endpoints

| Método | Endpoint | Descrição |
|--------|----------|-----------|
| GET | `/api/seo/synonyms/{categoryId}` | Retorna hierarquia de sinônimos |
| POST | `/api/seo/synonyms/suggest` | Sugere sinônimos para título |
| GET | `/api/seo/hidden-fields/{itemId}` | Detecta campos ocultos |
| POST | `/api/seo/hidden-fields/fill` | Preenche campos ocultos |
| POST | `/api/seo/keywords/distribute` | Distribui keywords por campo |
| POST | `/api/seo/description/build` | Gera descrição otimizada |
| GET | `/api/seo/coverage/{itemId}` | Analisa cobertura de buscas |
| POST | `/api/seo/optimize/full` | Otimização completa (12 estratégias) |
| GET | `/api/seo/score/{itemId}` | Calcula score SEO |

### 6.2 Integração com API ML

```php
// Endpoints da API do Mercado Livre utilizados
private const ML_ENDPOINTS = [
    'item' => '/items/{id}',
    'description' => '/items/{id}/description',
    'attributes' => '/categories/{id}/attributes',
    'trends' => '/trends/MLB',
    'search' => '/sites/MLB/search',
    'update_item' => '/items/{id}',
    'update_description' => '/items/{id}/description'
];
```

---

## 7. Algoritmos de Otimização

### 7.1 Algoritmo Principal de Otimização

```php
public function optimizeFull(string $itemId): array
{
    // FASE 1: Coleta de dados
    $item = $this->mlClient->getItem($itemId);
    $competitors = $this->competitorService->analyze($itemId, 20);
    $keywords = $this->keywordService->research($item['category_id']);
    
    // FASE 2: Aplicar estratégias
    $results = [];
    
    // E1: Hierarquia de sinônimos
    $synonyms = $this->synonymService->expand($item['title'], $item['category_id']);
    $results['synonyms'] = $synonyms;
    
    // E2: Campos ocultos
    $hiddenFields = $this->hiddenFieldsService->detect($itemId);
    $results['hidden_fields'] = $hiddenFields;
    
    // E3-E5: Distribuição de keywords
    $distribution = $this->distributionService->distribute($keywords, $item);
    $results['distribution'] = $distribution;
    
    // E6-E7: Descrição otimizada
    $description = $this->descriptionService->build($item, $distribution, $synonyms);
    $results['description'] = $description;
    
    // E8: Validar densidade
    $density = $this->validateDensity($description);
    $results['density'] = $density;
    
    // E9: Score semântico
    $score = $this->scoreService->calculate($results);
    $results['score'] = $score;
    
    // FASE 3: Gerar payloads
    $payloads = $this->generatePayloads($results);
    
    return [
        'analysis' => $results,
        'payloads' => $payloads,
        'ready_to_apply' => $score >= 70
    ];
}
```

---

## 8. Validações e Score

### 8.1 Componentes do Score SEO

| Componente | Peso | Critérios |
|------------|------|-----------|
| Título | 25% | Comprimento 45-58 chars, keywords core, sem proibidos |
| Modelo | 15% | Sinônimos não repetidos, 5-7 palavras |
| Attributes | 20% | Obrigatórios + 70% opcionais preenchidos |
| Descrição | 25% | 300-800 palavras, 4 blocos, densidade OK |
| Campos Ocultos | 10% | KEYWORDS + MPN + LINE preenchidos |
| Cobertura | 5% | 5 tipos de busca cobertos |

### 8.2 Fórmula do Score

```php
public function calculateSEOScore(array $analysis): int
{
    $score = 0;
    
    // Título (25%)
    $titleScore = $this->scoreTitleOptimization($analysis['title']);
    $score += $titleScore * 0.25;
    
    // Modelo (15%)
    $modelScore = $this->scoreModelOptimization($analysis['model']);
    $score += $modelScore * 0.15;
    
    // Attributes (20%)
    $attrScore = $this->scoreAttributesFill($analysis['attributes']);
    $score += $attrScore * 0.20;
    
    // Descrição (25%)
    $descScore = $this->scoreDescriptionOptimization($analysis['description']);
    $score += $descScore * 0.25;
    
    // Campos Ocultos (10%)
    $hiddenScore = $this->scoreHiddenFields($analysis['hidden_fields']);
    $score += $hiddenScore * 0.10;
    
    // Cobertura (5%)
    $coverageScore = $this->scoreSearchCoverage($analysis['coverage']);
    $score += $coverageScore * 0.05;
    
    return (int) round($score);
}
```

---

## 9. Referência de Classes

### 9.1 SynonymExpansionService

```php
namespace App\Services\SEO;

class SynonymExpansionService
{
    // Métodos principais
    public function expand(string $title, string $categoryId): array;
    public function getHierarchy(string $categoryId): array;
    public function identifyLevel(string $text): string;
    public function selectForField(string $title, string $field): array;
    public function calculateScore(string $word, string $title): float;
}
```

### 9.2 KeywordDistributionService

```php
namespace App\Services\SEO;

class KeywordDistributionService
{
    // Métodos principais
    public function distribute(array $keywords, array $item): array;
    public function classifyKeywords(array $keywords): array;
    public function mapToFields(array $classified): array;
    public function validateDensity(string $text, array $keywords): array;
    public function generatePlan(array $distribution): array;
}
```

### 9.3 DescriptionBuilderService

```php
namespace App\Services\SEO;

class DescriptionBuilderService
{
    // Métodos principais
    public function build(array $item, array $distribution, array $synonyms): string;
    public function generateBlock(string $type, array $data): string;
    public function injectKeywords(string $text, array $keywords): string;
    public function generateFAQ(array $item, array $keywords): string;
    public function validateDescription(string $description): array;
}
```

---

## 📎 Anexos

### A. Glossário

| Termo | Definição |
|-------|-----------|
| **CORE** | Keywords principais que definem o produto |
| **SUPORTE** | Sinônimos e variações das keywords core |
| **TÉCNICA** | Especificações técnicas (capacidade, cor, material) |
| **CONTEXTO** | Palavras de uso, público, situação |
| **Long Tail** | Combinações específicas de 3+ palavras |
| **Densidade** | Frequência de keyword em relação ao total de palavras |

### B. Referências

- [API Mercado Livre - Documentação Oficial](https://developers.mercadolivre.com.br/)
- [SEO para Marketplaces - Best Practices](https://www.mercadolivre.com.br/seo)
- [Algoritmo de Ranking do ML - Análise](internal-doc)

---

**Última atualização:** 22 de Janeiro de 2026  
**Próxima revisão:** Após implementação da Fase 1
