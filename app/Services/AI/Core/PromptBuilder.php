<?php

namespace App\Services\AI\Core;

/**
 * Builds AI prompts for different optimization tasks
 */
class PromptBuilder
{
    /**
     * Build prompt for title optimization
     * 
     * @param string $currentTitle
     * @param array $context Additional context (category, brand, attributes, etc.)
     * @return string
     */
    public function buildTitleOptimizationPrompt(string $currentTitle, array $context = []): string
    {
        $category = $context['category'] ?? 'produto';
        $brand = $context['brand'] ?? '';
        $model = $context['model'] ?? '';
        $attributes = $context['attributes'] ?? [];
        $keywords = $context['keywords'] ?? [];
        
        $attributesText = $this->formatAttributes($attributes);
        $keywordsText = !empty($keywords) ? implode(', ', array_slice($keywords, 0, 5)) : '';
        
        return <<<PROMPT
Você é um especialista em SEO e copywriting para e-commerce no Mercado Livre.

## TAREFA
Otimize o título do produto para maximizar visibilidade (SEO) e taxa de cliques (CTR).

## TÍTULO ATUAL
"{$currentTitle}"

## CONTEXTO DO PRODUTO
- Categoria: {$category}
{$this->addIfPresent('Marca', $brand)}{$this->addIfPresent('Modelo', $model)}{$this->addIfPresent('Atributos principais', $attributesText)}{$this->addIfPresent('Keywords relevantes', $keywordsText)}

## REQUISITOS OBRIGATÓRIOS
1. Máximo 60 caracteres (limite do Mercado Livre)
2. Incluir palavras-chave mais importantes
3. Destacar diferenciais competitivos
4. Ser claro e direto (evitar marketing excessivo)
5. Usar português brasileiro coloquial
6. NÃO usar caracteres especiais além de - | /
7. NÃO usar CAPS LOCK excessivo
8. NÃO incluir informações de preço ou frete

## PRIORIDADES (em ordem)
1. Relevância para busca (SEO)
2. Atratividade para clique (CTR)
3. Informações técnicas importantes
4. Diferenciação da concorrência

## FORMATO DE RESPOSTA
Retorne um JSON com este formato exato:
{
  "optimized_title": "Título otimizado aqui",
  "score": 95,
  "improvements": ["Melhoria 1", "Melhoria 2"],
  "keywords_used": ["keyword1", "keyword2"],
  "char_count": 58,
  "alternatives": [
    {
      "title": "Alternativa 1",
      "focus": "SEO",
      "score": 90
    },
    {
      "title": "Alternativa 2",
      "focus": "CTR",
      "score": 88
    }
  ]
}

Gere o melhor título possível seguindo RIGOROSAMENTE os requisitos.
PROMPT;
    }
    
    /**
     * Build prompt for description optimization
     * 
     * @param array $productData
     * @param array $keywords
     * @return string
     */
    public function buildDescriptionOptimizationPrompt(array $productData, array $keywords = []): string
    {
        $title = $productData['title'] ?? '';
        $category = $productData['category'] ?? 'produto';
        $brand = $productData['brand'] ?? '';
        $attributes = $productData['attributes'] ?? [];
        $features = $productData['features'] ?? [];
        $currentDesc = $productData['current_description'] ?? '';
        
        $attributesText = $this->formatAttributes($attributes);
        $featuresText = !empty($features) ? "• " . implode("\n• ", $features) : '';
        $keywordsText = !empty($keywords) ? implode(', ', array_slice($keywords, 0, 10)) : '';
        
        return <<<PROMPT
Você é um copywriter especialista em e-commerce no Mercado Livre com foco em conversão.

## TAREFA
Crie uma descrição persuasiva e otimizada que converta visitantes em compradores.

## PRODUTO
Título: {$title}
Categoria: {$category}
{$this->addIfPresent('Marca', $brand)}{$this->addIfPresent('Atributos', $attributesText)}{$this->addIfPresent('Características', $featuresText)}

{$this->addIfPresent('Descrição atual (para referência)', $currentDesc)}

{$this->addIfPresent('Keywords SEO', $keywordsText)}

## ESTRUTURA OBRIGATÓRIA

### 1. GANCHO INICIAL (2-3 linhas)
- Destaque o maior benefício
- Crie conexão emocional
- Gere interesse imediato

### 2. BENEFÍCIOS PRINCIPAIS (bullet points)
- Foco em BENEFÍCIOS, não apenas features
- Máximo 5-6 bullets
- Usar emojis estrategicamente (mas sem exagero)

### 3. ESPECIFICAÇÕES TÉCNICAS
- Formato de lista organizada
- Informações precisas e completas
- Facilita comparação

### 4. O QUE ESTÁ INCLUSO
- Lista clara dos itens da embalagem

### 5. GARANTIA E CONFIANÇA
- Informações de garantia
- Diferenciais do vendedor
- Elementos de trust

### 6. ENVIO E ENTREGA
- Informações de frete
- Prazo de envio
- Rastreamento

## DIRETRIZES DE COPYWRITING
✅ FAZER:
- Escrever em português brasileiro coloquial
- Usar linguagem persuasiva mas honesta
- Incluir keywords naturalmente
- Destacar benefícios únicos
- Criar senso de valor
- Usar formatação (emojis, bullet points)
- Responder objeções comuns

❌ NÃO FAZER:
- Fazer promessas impossíveis
- Usar linguagem muito formal ou técnica
- Exagerar com emojis
- Copiar descrições genéricas
- Incluir informações de preço
- Usar HTML ou tags especiais

## LIMITES
- Mínimo: 800 caracteres
- Máximo: 4500 caracteres
- Ideal: 1500-2500 caracteres

## FORMATO DE RESPOSTA
Retorne um JSON com este formato exato:
{
  "description": "Descrição completa otimizada aqui\\n\\nCom quebras de linha e formatação",
  "score": 94,
  "char_count": 1847,
  "keywords_used": ["keyword1", "keyword2"],
  "highlights": ["Destaque 1", "Destaque 2"],
  "structure_compliance": {
    "hook": true,
    "benefits": true,
    "specs": true,
    "includes": true,
    "warranty": true,
    "shipping": true
  }
}

Crie a MELHOR descrição possível que vende o produto!
PROMPT;
    }
    
    /**
     * Build prompt for analyzing listing quality
     * 
     * @param array $listingData
     * @return string
     */
    public function buildQualityAnalysisPrompt(array $listingData): string
    {
        $title = $listingData['title'] ?? '';
        $description = $listingData['description'] ?? '';
        $attributes = $listingData['attributes'] ?? [];
        $images = $listingData['images'] ?? [];
        
        $attributeCount = count($attributes);
        $imageCount = count($images);
        
        return <<<PROMPT
Você é um analista especialista em qualidade de anúncios do Mercado Livre.

## TAREFA
Analise a qualidade geral do anúncio e dê um score de 0-100 com sugestões de melhoria.

## ANÚNCIO ATUAL

### Título
"{$title}"

### Descrição
{$description}

### Ficha Técnica
- Atributos preenchidos: {$attributeCount}

### Imagens
- Total de imagens: {$imageCount}

## CRITÉRIOS DE AVALIAÇÃO

### Título (25 pontos)
- Comprimento ideal (50-60 chars): 0-5 pts
- Keywords relevantes: 0-8 pts
- Clareza e especificidade: 0-7 pts
- Diferenciação competitiva: 0-5 pts

### Descrição (20 pontos)
- Completude e estrutura: 0-6 pts
- Persuasão e copy: 0-6 pts
- Informações técnicas: 0-4 pts
- SEO (keywords): 0-4 pts

### Ficha Técnica (25 pontos)
- Atributos obrigatórios: 0-10 pts
- Atributos recomendados: 0-10 pts
- Precisão das informações: 0-5 pts

### Imagens (15 pontos)
- Quantidade (mínimo 6): 0-5 pts
- Qualidade esperada: 0-5 pts
- Diversidade (ângulos): 0-5 pts

### Preço e Competitividade (10 pontos)
- Baseado em contexto geral: 0-10 pts

### Shipping (5 pontos)
- Frete grátis ou configurado: 0-5 pts

## FORMATO DE RESPOSTA
Retorne um JSON com este formato exato:
{
  "overall_score": 67,
  "breakdown": {
    "title": {"score": 18, "max": 25, "issues": ["Muito curto", "Faltam keywords"]},
    "description": {"score": 12, "max": 20, "issues": ["Pouco persuasiva", "Sem estrutura"]},
    "attributes": {"score": 15, "max": 25, "issues": ["11 atributos faltando"]},
    "images": {"score": 9, "max": 15, "issues": ["Apenas 4 imagens", "Baixa qualidade"]},
    "price": {"score": 8, "max": 10, "issues": []},
    "shipping": {"score": 5, "max": 5, "issues": []}
  },
  "priority_improvements": [
    {"area": "Atributos", "impact": "high", "suggestion": "Preencher 11 atributos faltantes"},
    {"area": "Título", "impact": "high", "suggestion": "Expandir e incluir keywords"},
    {"area": "Descrição", "impact": "medium", "suggestion": "Criar estrutura persuasiva"}
  ],
  "estimated_impact": {
    "visibility": "+45%",
    "ctr": "+32%",
    "conversion": "+28%"
  }
}

Analise com rigor e objetividade!
PROMPT;
    }
    
    /**
     * Format attributes for inclusion in prompts
     * 
     * @param array $attributes
     * @return string
     */
    private function formatAttributes(array $attributes): string
    {
        if (empty($attributes)) {
            return '';
        }
        
        $formatted = [];
        foreach ($attributes as $attr) {
            $name = $attr['name'] ?? $attr['id'] ?? '';
            $value = $attr['value_name'] ?? $attr['value'] ?? '';
            
            if ($name && $value) {
                $formatted[] = "{$name}: {$value}";
            }
        }
        
        return implode(', ', $formatted);
    }
    
    /**
     * Helper to add field if present
     * 
     * @param string $label
     * @param string $value
     * @return string
     */
    private function addIfPresent(string $label, string $value): string
    {
        return $value ? "- {$label}: {$value}\n" : '';
    }
    
    /**
     * Build system message for chat models
     * 
     * @param string $role
     * @return string
     */
    public function buildSystemMessage(string $role = 'optimizer'): string
    {
        $messages = [
            'optimizer' => 'Você é um especialista em otimização de anúncios para e-commerce, com profundo conhecimento em SEO, copywriting e algoritmos do Mercado Livre. Seu objetivo é maximizar visibilidade, cliques e conversões.',
            
            'analyzer' => 'Você é um analista de dados especializado em e-commerce e marketplace. Você avalia anúncios com critérios objetivos e fornece insights acionáveis baseados em dados.',
            
            'copywriter' => 'Você é um copywriter senior com 10+ anos de experiência em e-commerce. Você cria textos persuasivos que convertem, sempre focando em benefícios e superando objeções.',
        ];
        
        return $messages[$role] ?? $messages['optimizer'];
    }
}
