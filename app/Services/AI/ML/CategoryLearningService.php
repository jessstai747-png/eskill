<?php

declare(strict_types=1);

namespace App\Services\AI\ML;

use App\Database;
use App\Services\MercadoLivreClient;
use App\Services\AI\Core\AIProviderManager;
use PDO;

/**
 * 🧠 Category Learning Service
 * 
 * Aprende padrões de otimização por categoria do Mercado Livre:
 * - Analisa top performers de cada categoria
 * - Identifica padrões de título, descrição, atributos
 * - Gera templates otimizados por categoria
 * - Cache de aprendizado para performance
 */
class CategoryLearningService
{
    private PDO $db;
    private int $accountId;
    private ?MercadoLivreClient $mlClient;
    private ?AIProviderManager $aiProvider;

    // Campos de aprendizado por categoria
    private const LEARNING_FIELDS = [
        'title_patterns',
        'description_patterns', 
        'attribute_patterns',
        'keyword_patterns',
        'price_ranges',
        'image_patterns',
    ];

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->mlClient = new MercadoLivreClient($accountId);
        $this->aiProvider = new AIProviderManager();
    }

    /**
     * 📚 Aprender padrões de uma categoria
     */
    public function learnCategory(string $categoryId, int $sampleSize = 50): array
    {
        try {
            // Buscar top sellers da categoria
            $topItems = $this->fetchTopItems($categoryId, $sampleSize);
            
            if (empty($topItems)) {
                return [
                    'success' => false,
                    'error' => 'Nenhum item encontrado na categoria',
                ];
            }

            // Analisar padrões
            $patterns = [
                'title_patterns' => $this->analyzeTitlePatterns($topItems),
                'description_patterns' => $this->analyzeDescriptionPatterns($topItems),
                'attribute_patterns' => $this->analyzeAttributePatterns($topItems, $categoryId),
                'keyword_patterns' => $this->extractKeywordPatterns($topItems),
                'price_ranges' => $this->analyzePriceRanges($topItems),
                'image_patterns' => $this->analyzeImagePatterns($topItems),
            ];

            // Salvar aprendizado no banco
            $this->saveLearning($categoryId, $patterns);

            return [
                'success' => true,
                'category_id' => $categoryId,
                'items_analyzed' => count($topItems),
                'patterns' => $patterns,
                'learned_at' => date('Y-m-d H:i:s'),
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * 🔍 Buscar top items de uma categoria
     */
    private function fetchTopItems(string $categoryId, int $limit): array
    {
        try {
            $response = $this->mlClient->get("/sites/MLB/search", [
                'category' => $categoryId,
                'sort' => 'sold_quantity_desc',
                'limit' => $limit,
            ]);

            return $response['results'] ?? [];
        } catch (\Exception $e) {
            log_warning('Erro ao buscar top items da categoria', [
                'service' => 'CategoryLearningService',
                'category_id' => $categoryId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    /**
     * 📝 Analisar padrões de título
     */
    private function analyzeTitlePatterns(array $items): array
    {
        $patterns = [
            'avg_length' => 0,
            'common_words' => [],
            'structure_patterns' => [],
            'capitalization' => [],
            'numeric_usage' => 0,
            'special_chars' => [],
        ];

        $wordFrequency = [];
        $totalLength = 0;
        $numericCount = 0;

        foreach ($items as $item) {
            $title = $item['title'] ?? '';
            $totalLength += mb_strlen($title);

            // Contar uso de números
            if (preg_match('/\d/', $title)) {
                $numericCount++;
            }

            // Extrair palavras
            $words = preg_split('/\s+/', mb_strtolower($title));
            foreach ($words as $word) {
                $word = trim($word, '.,;:!?()[]{}');
                if (mb_strlen($word) >= 3) {
                    $wordFrequency[$word] = ($wordFrequency[$word] ?? 0) + 1;
                }
            }

            // Identificar padrões estruturais
            $structure = $this->identifyTitleStructure($title);
            if ($structure) {
                $patterns['structure_patterns'][$structure] = ($patterns['structure_patterns'][$structure] ?? 0) + 1;
            }
        }

        $itemCount = count($items);
        $patterns['avg_length'] = $itemCount > 0 ? round($totalLength / $itemCount) : 0;
        $patterns['numeric_usage'] = $itemCount > 0 ? round(($numericCount / $itemCount) * 100) : 0;

        // Top 20 palavras mais comuns
        arsort($wordFrequency);
        $patterns['common_words'] = array_slice($wordFrequency, 0, 20, true);

        return $patterns;
    }

    /**
     * 🏗️ Identificar estrutura do título
     */
    private function identifyTitleStructure(string $title): ?string
    {
        // Padrões comuns
        if (preg_match('/^[A-Za-z]+\s+[A-Za-z0-9]+\s+\d+/', $title)) {
            return 'MARCA_MODELO_NUMERO';
        }
        if (preg_match('/^[A-Za-z]+\s+.+\s+\d+\s*(ml|g|kg|cm|mm|m|l|un)/i', $title)) {
            return 'PRODUTO_QUANTIDADE_UNIDADE';
        }
        if (preg_match('/^kit\s+/i', $title)) {
            return 'KIT_DESCRICAO';
        }
        if (preg_match('/\s+x\s*\d+$/i', $title)) {
            return 'PRODUTO_MULTIPLICADOR';
        }
        if (preg_match('/^[A-Z][a-z]+\s+[A-Z][a-z]+/', $title)) {
            return 'MARCA_LINHA';
        }

        return 'GENERICO';
    }

    /**
     * 📄 Analisar padrões de descrição
     */
    private function analyzeDescriptionPatterns(array $items): array
    {
        $patterns = [
            'avg_length' => 0,
            'has_bullets' => 0,
            'has_emojis' => 0,
            'has_specifications' => 0,
            'has_warranty_info' => 0,
            'common_sections' => [],
        ];

        $totalLength = 0;
        $bulletCount = 0;
        $emojiCount = 0;
        $specCount = 0;
        $warrantyCount = 0;

        // Limitar para evitar muitas chamadas de API
        $sampleItems = array_slice($items, 0, 20);

        foreach ($sampleItems as $item) {
            $itemId = $item['id'] ?? '';
            if (!$itemId) {
                continue;
            }

            try {
                $descData = $this->mlClient->get("/items/{$itemId}/description");
                $description = $descData['plain_text'] ?? $descData['text'] ?? '';

                $totalLength += mb_strlen($description);

                // Detectar bullets
                if (preg_match('/[•\-\*]\s/', $description)) {
                    $bulletCount++;
                }

                // Detectar emojis
                if (preg_match('/[\x{1F300}-\x{1F9FF}]/u', $description)) {
                    $emojiCount++;
                }

                // Detectar especificações técnicas
                if (preg_match('/(especifica[çc][õo]es|caracter[íi]sticas|ficha t[ée]cnica)/i', $description)) {
                    $specCount++;
                }

                // Detectar informações de garantia
                if (preg_match('/(garantia|warranty)/i', $description)) {
                    $warrantyCount++;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        $itemCount = count($sampleItems);
        if ($itemCount > 0) {
            $patterns['avg_length'] = round($totalLength / $itemCount);
            $patterns['has_bullets'] = round(($bulletCount / $itemCount) * 100);
            $patterns['has_emojis'] = round(($emojiCount / $itemCount) * 100);
            $patterns['has_specifications'] = round(($specCount / $itemCount) * 100);
            $patterns['has_warranty_info'] = round(($warrantyCount / $itemCount) * 100);
        }

        return $patterns;
    }

    /**
     * 📊 Analisar padrões de atributos
     */
    private function analyzeAttributePatterns(array $items, string $categoryId): array
    {
        $patterns = [
            'required_attributes' => [],
            'common_values' => [],
            'fill_rate' => [],
        ];

        // Buscar atributos da categoria
        try {
            $categoryAttrs = $this->mlClient->get("/categories/{$categoryId}/attributes");
            
            $requiredAttrs = [];
            foreach ($categoryAttrs as $attr) {
                if (($attr['tags']['required'] ?? false) || ($attr['relevance'] ?? 0) > 0.5) {
                    $requiredAttrs[$attr['id']] = [
                        'name' => $attr['name'] ?? $attr['id'],
                        'type' => $attr['value_type'] ?? 'string',
                    ];
                }
            }
            $patterns['required_attributes'] = $requiredAttrs;
        } catch (\Exception $e) {
            // Ignora se não conseguir buscar atributos
        }

        // Analisar valores comuns
        $valueFrequency = [];
        foreach ($items as $item) {
            $attributes = $item['attributes'] ?? [];
            foreach ($attributes as $attr) {
                $attrId = $attr['id'] ?? '';
                $value = $attr['value_name'] ?? '';
                if ($attrId && $value) {
                    if (!isset($valueFrequency[$attrId])) {
                        $valueFrequency[$attrId] = [];
                    }
                    $valueFrequency[$attrId][$value] = ($valueFrequency[$attrId][$value] ?? 0) + 1;
                }
            }
        }

        // Top valores por atributo
        foreach ($valueFrequency as $attrId => $values) {
            arsort($values);
            $patterns['common_values'][$attrId] = array_slice($values, 0, 5, true);
        }

        return $patterns;
    }

    /**
     * 🔑 Extrair padrões de keywords
     */
    private function extractKeywordPatterns(array $items): array
    {
        $allKeywords = [];
        $bigramFrequency = [];
        $trigramFrequency = [];

        foreach ($items as $item) {
            $title = mb_strtolower($item['title'] ?? '');
            $words = preg_split('/\s+/', $title);
            $words = array_filter($words, fn($w) => mb_strlen($w) >= 3);
            $words = array_values($words);

            // Unigrams
            foreach ($words as $word) {
                $word = trim($word, '.,;:!?()[]{}');
                $allKeywords[$word] = ($allKeywords[$word] ?? 0) + 1;
            }

            // Bigrams
            for ($i = 0; $i < count($words) - 1; $i++) {
                $bigram = $words[$i] . ' ' . $words[$i + 1];
                $bigramFrequency[$bigram] = ($bigramFrequency[$bigram] ?? 0) + 1;
            }

            // Trigrams
            for ($i = 0; $i < count($words) - 2; $i++) {
                $trigram = $words[$i] . ' ' . $words[$i + 1] . ' ' . $words[$i + 2];
                $trigramFrequency[$trigram] = ($trigramFrequency[$trigram] ?? 0) + 1;
            }
        }

        arsort($allKeywords);
        arsort($bigramFrequency);
        arsort($trigramFrequency);

        return [
            'top_unigrams' => array_slice($allKeywords, 0, 30, true),
            'top_bigrams' => array_slice($bigramFrequency, 0, 20, true),
            'top_trigrams' => array_slice($trigramFrequency, 0, 10, true),
        ];
    }

    /**
     * 💰 Analisar faixas de preço
     */
    private function analyzePriceRanges(array $items): array
    {
        $prices = [];
        foreach ($items as $item) {
            $price = $item['price'] ?? 0;
            if ($price > 0) {
                $prices[] = $price;
            }
        }

        if (empty($prices)) {
            return ['min' => 0, 'max' => 0, 'avg' => 0, 'median' => 0];
        }

        sort($prices);
        $count = count($prices);
        $median = $count % 2 === 0
            ? ($prices[(int) ($count / 2) - 1] + $prices[(int) ($count / 2)]) / 2
            : $prices[(int) floor($count / 2)];

        return [
            'min' => min($prices),
            'max' => max($prices),
            'avg' => round(array_sum($prices) / $count, 2),
            'median' => round($median, 2),
            'percentile_25' => $prices[(int) floor($count * 0.25)] ?? 0,
            'percentile_75' => $prices[(int) floor($count * 0.75)] ?? 0,
        ];
    }

    /**
     * 🖼️ Analisar padrões de imagem
     */
    private function analyzeImagePatterns(array $items): array
    {
        $imageCounts = [];
        foreach ($items as $item) {
            $count = count($item['pictures'] ?? []);
            $imageCounts[] = $count;
        }

        if (empty($imageCounts)) {
            return ['avg_count' => 0, 'min_count' => 0, 'max_count' => 0];
        }

        return [
            'avg_count' => round(array_sum($imageCounts) / count($imageCounts), 1),
            'min_count' => min($imageCounts),
            'max_count' => max($imageCounts),
            'recommended_count' => max(6, (int) round(array_sum($imageCounts) / count($imageCounts))),
        ];
    }

    /**
     * 💾 Salvar aprendizado no banco
     */
    private function saveLearning(string $categoryId, array $patterns): void
    {
        $sql = "
            INSERT INTO category_learning (
                category_id, patterns_json, items_analyzed, learned_at, updated_at
            ) VALUES (
                :category_id, :patterns_json, :items_analyzed, NOW(), NOW()
            )
            ON DUPLICATE KEY UPDATE
                patterns_json = VALUES(patterns_json),
                items_analyzed = VALUES(items_analyzed),
                updated_at = NOW()
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            'category_id' => $categoryId,
            'patterns_json' => json_encode($patterns),
            'items_analyzed' => $patterns['title_patterns']['common_words'] 
                ? count($patterns['title_patterns']['common_words']) : 0,
        ]);
    }

    /**
     * 📖 Obter aprendizado de uma categoria
     */
    public function getCategoryLearning(string $categoryId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT patterns_json, items_analyzed, learned_at, updated_at
            FROM category_learning
            WHERE category_id = :category_id
        ");
        $stmt->execute(['category_id' => $categoryId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            return null;
        }

        return [
            'category_id' => $categoryId,
            'patterns' => json_decode($row['patterns_json'], true),
            'items_analyzed' => (int) $row['items_analyzed'],
            'learned_at' => $row['learned_at'],
            'updated_at' => $row['updated_at'],
        ];
    }

    /**
     * 🎯 Gerar template otimizado baseado no aprendizado
     */
    public function generateOptimizedTemplate(string $categoryId): array
    {
        $learning = $this->getCategoryLearning($categoryId);

        if (!$learning) {
            // Aprender primeiro se não tiver dados
            $result = $this->learnCategory($categoryId);
            if (!$result['success']) {
                return ['success' => false, 'error' => 'Não foi possível aprender a categoria'];
            }
            $learning = $this->getCategoryLearning($categoryId);
        }

        $patterns = $learning['patterns'] ?? [];
        $titlePatterns = $patterns['title_patterns'] ?? [];
        $keywordPatterns = $patterns['keyword_patterns'] ?? [];

        // Gerar template de título
        $topWords = array_keys($titlePatterns['common_words'] ?? []);
        $avgLength = $titlePatterns['avg_length'] ?? 50;

        // Gerar template de descrição
        $descPatterns = $patterns['description_patterns'] ?? [];

        return [
            'success' => true,
            'category_id' => $categoryId,
            'template' => [
                'title' => [
                    'recommended_length' => min(60, max(40, $avgLength)),
                    'suggested_words' => array_slice($topWords, 0, 10),
                    'use_numbers' => ($titlePatterns['numeric_usage'] ?? 0) > 50,
                    'structure' => array_key_first($titlePatterns['structure_patterns'] ?? ['GENERICO' => 1]),
                ],
                'description' => [
                    'recommended_length' => max(500, $descPatterns['avg_length'] ?? 500),
                    'use_bullets' => ($descPatterns['has_bullets'] ?? 0) > 50,
                    'include_specifications' => ($descPatterns['has_specifications'] ?? 0) > 30,
                    'include_warranty' => ($descPatterns['has_warranty_info'] ?? 0) > 30,
                ],
                'keywords' => [
                    'core' => array_slice(array_keys($keywordPatterns['top_unigrams'] ?? []), 0, 5),
                    'phrases' => array_slice(array_keys($keywordPatterns['top_bigrams'] ?? []), 0, 5),
                    'long_tail' => array_slice(array_keys($keywordPatterns['top_trigrams'] ?? []), 0, 3),
                ],
                'pricing' => $patterns['price_ranges'] ?? [],
                'images' => $patterns['image_patterns'] ?? [],
            ],
        ];
    }

    /**
     * 📋 Listar todas as categorias aprendidas
     */
    public function listLearnedCategories(): array
    {
        $stmt = $this->db->query("
            SELECT category_id, items_analyzed, learned_at, updated_at
            FROM category_learning
            ORDER BY updated_at DESC
        ");

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 🔄 Atualizar aprendizado de categoria (re-learn)
     */
    public function refreshCategoryLearning(string $categoryId): array
    {
        return $this->learnCategory($categoryId, 50);
    }
}
