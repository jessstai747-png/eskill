<?php
declare(strict_types=1);

namespace App\Services\SEO;

use App\Services\CacheService;

/**
 * Serviço de Otimização SEO com IA Real
 * Implementação FUNCIONAL - sem mocks, sem placeholders
 *
 * @author Sistema SEO Profissional
 * @version 1.0.0
 */
class SEOOptimizerService
{
    private AIClient $ai;
    private ?CacheService $cache;

    // Prompts otimizados para SEO
    private const SYSTEM_PROMPT = "Você é um especialista em SEO para e-commerce e marketplaces, especialmente Mercado Livre.
Você conhece profundamente:
- Algoritmos de busca do Mercado Livre
- Otimização de títulos para conversão
- Palavras-chave de alta conversão
- Estrutura ideal de descrições
- Atributos que melhoram ranking
- Práticas de SEO para produtos

Sempre forneça respostas práticas, específicas e acionáveis.
Foque em resultados mensuráveis e aumento de visibilidade.";

    public function __construct()
    {
        $this->ai = new AIClient();

        try {
            $this->cache = new CacheService();
        } catch (\Exception $e) {
            $this->cache = null;
        }
    }

    /**
     * Análise completa de SEO de um produto
     */
    public function analyze(array $product): array
    {
        $title = $product['title'] ?? '';
        $description = $product['description'] ?? '';
        $category = $product['category'] ?? '';
        $attributes = $product['attributes'] ?? [];
        $price = $product['price'] ?? 0;

        $prompt = "Analise este produto para SEO de marketplace:

TÍTULO: {$title}
CATEGORIA: {$category}
PREÇO: R$ {$price}
DESCRIÇÃO: " . mb_substr($description, 0, 500) . "
ATRIBUTOS: " . json_encode($attributes, JSON_UNESCAPED_UNICODE) . "

Faça uma análise completa e retorne um JSON com:
{
    \"score\": (número de 0 a 100),
    \"title_analysis\": {
        \"score\": (0-100),
        \"length\": (número de caracteres),
        \"has_keywords\": (boolean),
        \"issues\": [\"lista de problemas\"],
        \"suggestions\": [\"lista de sugestões\"]
    },
    \"description_analysis\": {
        \"score\": (0-100),
        \"length\": (número de caracteres),
        \"has_benefits\": (boolean),
        \"has_features\": (boolean),
        \"issues\": [\"lista de problemas\"],
        \"suggestions\": [\"lista de sugestões\"]
    },
    \"keywords\": {
        \"found\": [\"palavras encontradas\"],
        \"missing\": [\"palavras importantes que faltam\"],
        \"recommended\": [\"top 5 keywords recomendadas\"]
    },
    \"optimization_priority\": [
        {\"action\": \"ação específica\", \"impact\": \"alto/médio/baixo\", \"effort\": \"alto/médio/baixo\"}
    ],
    \"competitor_insights\": \"breve análise do que concorrentes fazem melhor\",
    \"estimated_improvement\": \"estimativa de melhoria de visibilidade em %\"
}";

        $response = $this->ai->chatJSON($prompt, [
            'system' => self::SYSTEM_PROMPT,
            'cache_ttl' => 7200 // 2 horas
        ]);

        if (!$response['success']) {
            return [
                'success' => false,
                'error' => $response['error'],
                'score' => 0
            ];
        }

        $data = $response['data'];
        $data['success'] = true;
        $data['cached'] = $response['cached'] ?? false;
        $data['analyzed_at'] = date('Y-m-d H:i:s');

        return $data;
    }

    /**
     * Otimiza título para SEO
     */
    public function optimizeTitle(string $title, array $context = []): array
    {
        $category = $context['category'] ?? '';
        $brand = $context['brand'] ?? '';
        $keywords = $context['keywords'] ?? [];
        $attributes = $context['attributes'] ?? [];

        $keywordsStr = !empty($keywords) ? implode(', ', $keywords) : 'não informadas';
        $attributesStr = !empty($attributes) ? json_encode($attributes, JSON_UNESCAPED_UNICODE) : 'não informados';

        $prompt = "Otimize este título de produto para máxima visibilidade no Mercado Livre:

TÍTULO ATUAL: {$title}
CATEGORIA: {$category}
MARCA: {$brand}
KEYWORDS DESEJADAS: {$keywordsStr}
ATRIBUTOS DO PRODUTO: {$attributesStr}

Regras para otimização:
1. Máximo 60 caracteres (ideal: 50-60)
2. Marca no início (se relevante)
3. Palavras-chave principais primeiro
4. Atributos diferenciadores incluídos
5. Sem caracteres especiais desnecessários
6. Sem repetição de palavras
7. Fácil de ler e entender

Retorne JSON:
{
    \"original_title\": \"título original\",
    \"optimized_title\": \"título otimizado\",
    \"alternative_titles\": [\"3 outras opções de título\"],
    \"changes_made\": [\"lista do que foi alterado\"],
    \"keywords_included\": [\"keywords presentes no novo título\"],
    \"character_count\": número,
    \"improvement_score\": (0-100),
    \"reasoning\": \"explicação breve das melhorias\"
}";

        $response = $this->ai->chatJSON($prompt, [
            'system' => self::SYSTEM_PROMPT,
            'temperature' => 0.8, // Mais criativo para títulos
            'cache_ttl' => 3600
        ]);

        if (!$response['success']) {
            return [
                'success' => false,
                'error' => $response['error'],
                'optimized_title' => $title // Retorna original em caso de erro
            ];
        }

        $data = $response['data'];
        $data['success'] = true;
        $data['cached'] = $response['cached'] ?? false;

        return $data;
    }

    /**
     * Gera descrição otimizada
     */
    public function generateDescription(array $product): array
    {
        $title = $product['title'] ?? '';
        $category = $product['category'] ?? '';
        $brand = $product['brand'] ?? '';
        $features = $product['features'] ?? [];
        $specifications = $product['specifications'] ?? [];
        $currentDescription = $product['description'] ?? '';

        $featuresStr = !empty($features) ? implode("\n- ", $features) : 'não informadas';
        $specsStr = !empty($specifications) ? json_encode($specifications, JSON_UNESCAPED_UNICODE) : 'não informadas';

        $prompt = "Crie uma descrição de produto otimizada para SEO e conversão:

PRODUTO: {$title}
CATEGORIA: {$category}
MARCA: {$brand}
CARACTERÍSTICAS:
- {$featuresStr}
ESPECIFICAÇÕES: {$specsStr}
DESCRIÇÃO ATUAL: " . mb_substr($currentDescription, 0, 300) . "

Crie uma descrição que:
1. Tenha 300-500 palavras
2. Use palavras-chave naturalmente
3. Liste benefícios antes de features
4. Tenha parágrafos curtos (2-3 linhas)
5. Inclua bullet points para features
6. Tenha call-to-action no final
7. Seja persuasiva mas honesta
8. Responda dúvidas comuns do comprador

Retorne JSON:
{
    \"description\": \"descrição completa otimizada\",
    \"short_description\": \"versão resumida em 100 palavras\",
    \"bullet_points\": [\"5-7 bullet points principais\"],
    \"keywords_used\": [\"palavras-chave incluídas\"],
    \"word_count\": número,
    \"seo_score\": (0-100),
    \"unique_selling_points\": [\"diferenciais destacados\"]
}";

        $response = $this->ai->chatJSON($prompt, [
            'system' => self::SYSTEM_PROMPT,
            'max_tokens' => 2000,
            'cache_ttl' => 7200
        ]);

        if (!$response['success']) {
            return [
                'success' => false,
                'error' => $response['error']
            ];
        }

        $data = $response['data'];
        $data['success'] = true;
        $data['cached'] = $response['cached'] ?? false;

        return $data;
    }

    /**
     * Pesquisa de keywords
     */
    public function researchKeywords(string $product, array $context = []): array
    {
        $category = $context['category'] ?? '';
        $competitors = $context['competitors'] ?? [];

        $competitorsStr = !empty($competitors) ? implode(', ', $competitors) : 'não informados';

        $prompt = "Faça uma pesquisa de palavras-chave para o produto:

PRODUTO: {$product}
CATEGORIA: {$category}
CONCORRENTES CONHECIDOS: {$competitorsStr}

Analise e retorne JSON:
{
    \"main_keyword\": \"palavra-chave principal\",
    \"secondary_keywords\": [\"5-10 keywords secundárias importantes\"],
    \"long_tail_keywords\": [\"5-10 keywords de cauda longa\"],
    \"question_keywords\": [\"5 perguntas que compradores fazem\"],
    \"competitor_keywords\": [\"keywords que concorrentes usam\"],
    \"trending_keywords\": [\"keywords em alta no momento\"],
    \"negative_keywords\": [\"palavras a evitar\"],
    \"keyword_difficulty\": {
        \"alta_concorrencia\": [\"keywords difíceis\"],
        \"media_concorrencia\": [\"keywords médias\"],
        \"baixa_concorrencia\": [\"keywords fáceis - oportunidades\"]
    },
    \"search_intent\": {
        \"informacional\": [\"keywords de pesquisa\"],
        \"transacional\": [\"keywords de compra\"],
        \"navegacional\": [\"keywords de marca\"]
    },
    \"recommended_strategy\": \"estratégia recomendada de uso das keywords\"
}";

        $response = $this->ai->chatJSON($prompt, [
            'system' => self::SYSTEM_PROMPT,
            'cache_ttl' => 86400 // 24 horas
        ]);

        if (!$response['success']) {
            return [
                'success' => false,
                'error' => $response['error']
            ];
        }

        $data = $response['data'];
        $data['success'] = true;
        $data['cached'] = $response['cached'] ?? false;
        $data['researched_at'] = date('Y-m-d H:i:s');

        return $data;
    }

    /**
     * Análise de concorrentes
     */
    public function analyzeCompetitors(array $product, array $competitors = []): array
    {
        $productTitle = $product['title'] ?? '';
        $category = $product['category'] ?? '';
        $price = $product['price'] ?? 0;

        $competitorsJson = !empty($competitors) ?
            json_encode($competitors, JSON_UNESCAPED_UNICODE) :
            'Analise concorrentes típicos desta categoria';

        $prompt = "Analise a concorrência para posicionamento SEO:

MEU PRODUTO: {$productTitle}
CATEGORIA: {$category}
MEU PREÇO: R$ {$price}
CONCORRENTES: {$competitorsJson}

Faça análise competitiva e retorne JSON:
{
    \"market_position\": \"análise da posição no mercado\",
    \"competitive_advantages\": [\"vantagens do meu produto\"],
    \"competitive_gaps\": [\"onde estou perdendo para concorrentes\"],
    \"price_position\": \"análise de posicionamento de preço\",
    \"seo_gaps\": [\"oportunidades de SEO que concorrentes não exploram\"],
    \"content_gaps\": [\"conteúdo que concorrentes têm e eu não\"],
    \"keyword_opportunities\": [\"keywords que concorrentes rankeiam e eu poderia\"],
    \"differentiation_suggestions\": [\"como me diferenciar\"],
    \"quick_wins\": [\"ações rápidas para ganhar vantagem\"],
    \"long_term_strategy\": \"estratégia de longo prazo\",
    \"estimated_market_share\": \"estimativa de participação possível\"
}";

        $response = $this->ai->chatJSON($prompt, [
            'system' => self::SYSTEM_PROMPT,
            'cache_ttl' => 43200 // 12 horas
        ]);

        if (!$response['success']) {
            return [
                'success' => false,
                'error' => $response['error']
            ];
        }

        $data = $response['data'];
        $data['success'] = true;
        $data['cached'] = $response['cached'] ?? false;

        return $data;
    }

    /**
     * Otimização completa de produto
     */
    public function optimizeProduct(array $product): array
    {
        $results = [
            'success' => true,
            'original' => $product,
            'optimizations' => []
        ];

        // 1. Análise SEO
        $analysis = $this->analyze($product);
        $results['analysis'] = $analysis;
        $results['score'] = $analysis['score'] ?? 0;

        // 2. Otimizar título
        $titleResult = $this->optimizeTitle($product['title'] ?? '', [
            'category' => $product['category'] ?? '',
            'brand' => $product['brand'] ?? '',
            'attributes' => $product['attributes'] ?? []
        ]);

        if ($titleResult['success']) {
            $results['optimizations']['title'] = $titleResult;
            $results['optimized_title'] = $titleResult['optimized_title'] ?? $product['title'];
        }

        // 3. Gerar descrição (se solicitado ou se descrição atual é fraca)
        $currentDesc = $product['description'] ?? '';
        if (empty($currentDesc) || strlen($currentDesc) < 200 || ($analysis['description_analysis']['score'] ?? 100) < 60) {
            $descResult = $this->generateDescription($product);

            if ($descResult['success']) {
                $results['optimizations']['description'] = $descResult;
                $results['optimized_description'] = $descResult['description'] ?? $currentDesc;
            }
        }

        // 4. Keywords
        $keywordsResult = $this->researchKeywords(
            $product['title'] ?? '',
            ['category' => $product['category'] ?? '']
        );

        if ($keywordsResult['success']) {
            $results['keywords'] = $keywordsResult;
        }

        // 5. Calcular score final
        $scores = [
            $analysis['score'] ?? 0,
            $titleResult['improvement_score'] ?? 0,
            $descResult['seo_score'] ?? 0
        ];
        $results['final_score'] = round(array_sum($scores) / count(array_filter($scores)));

        // 6. Gerar resumo de ações
        $results['action_summary'] = $this->generateActionSummary($results);
        $results['optimized_at'] = date('Y-m-d H:i:s');

        return $results;
    }

    /**
     * Gera resumo de ações
     */
    private function generateActionSummary(array $results): array
    {
        $actions = [];

        if (isset($results['analysis']['optimization_priority'])) {
            foreach ($results['analysis']['optimization_priority'] as $priority) {
                $actions[] = [
                    'action' => $priority['action'] ?? 'Ação não especificada',
                    'priority' => $priority['impact'] ?? 'médio',
                    'type' => 'from_analysis'
                ];
            }
        }

        if (isset($results['optimizations']['title']['changes_made'])) {
            foreach ($results['optimizations']['title']['changes_made'] as $change) {
                $actions[] = [
                    'action' => "Título: {$change}",
                    'priority' => 'alto',
                    'type' => 'title_change'
                ];
            }
        }

        return $actions;
    }

    /**
     * Verifica se o serviço está disponível
     */
    public function isAvailable(): bool
    {
        return $this->ai->isAvailable();
    }

    /**
     * Retorna nome do provider em uso
     */
    public function getProvider(): string
    {
        return $this->ai->getProviderName();
    }
}
