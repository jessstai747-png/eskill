<?php

declare(strict_types=1);

namespace App\Services\AI\SEO\Strategies;

use App\Database;
use PDO;
use Exception;

/**
 * SemanticScoreService - Estratégia E9: Score de Relevância Semântica
 *
 * Calcula score de relevância semântica para palavras-chave baseado em:
 * - Relação com o título/produto
 * - Contexto de uso (profissional, lazer, urbano, carga)
 * - Peso hierárquico do sinônimo
 * - Performance histórica (quando disponível)
 *
 * @package App\Services\AI\SEO\Strategies
 */
class SemanticScoreService
{
    private PDO $db;
    private SynonymExpansionService $synonymService;
    private ?int $accountId;

    /**
     * Contextos de uso disponíveis
     */
    private const USE_CONTEXTS = [
        'profissional' => [
            'keywords' => ['delivery', 'motoboy', 'entrega', 'trabalho', 'comercial', 'empresa'],
            'weight' => 1.20,
            'description' => 'Uso profissional e comercial'
        ],
        'lazer' => [
            'keywords' => ['viagem', 'passeio', 'turismo', 'aventura', 'trilha', 'fim de semana'],
            'weight' => 1.00,
            'description' => 'Uso recreativo e lazer'
        ],
        'urbano' => [
            'keywords' => ['cidade', 'dia a dia', 'diário', 'urbano', 'rua', 'trânsito'],
            'weight' => 0.90,
            'description' => 'Uso urbano cotidiano'
        ],
        'carga' => [
            'keywords' => ['capacete', 'transporte', 'carga', 'bagagem', 'volume', 'espaço'],
            'weight' => 1.10,
            'description' => 'Transporte de objetos e cargas'
        ]
    ];

    /**
     * Pesos dos componentes do score
     */
    private const SCORE_WEIGHTS = [
        'relevance' => 0.35,      // Relevância com título
        'hierarchy' => 0.25,      // Nível hierárquico
        'context' => 0.20,        // Contexto de uso
        'performance' => 0.20     // Performance histórica
    ];

    /**
     * Cache de contextos por categoria
     */
    private array $contextCache = [];

    public function __construct(?int $accountId = null)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->synonymService = new SynonymExpansionService($accountId);
    }

    /**
     * Calcula score de relevância semântica para uma palavra
     *
     * @param string $word Palavra para avaliar
     * @param string $title Título do produto
     * @param string $categoryId ID da categoria
     * @return float Score de 0.0 a 1.0
     */
    public function calculateScore(string $word, string $title, string $categoryId): float
    {
        $components = $this->getScoreComponents($word, $title, $categoryId);

        $finalScore = 0.0;
        foreach (self::SCORE_WEIGHTS as $component => $weight) {
            $finalScore += ($components[$component] ?? 0.0) * $weight;
        }

        return round(min(1.0, max(0.0, $finalScore)), 3);
    }

    /**
     * Calcula scores para lista de palavras
     *
     * @param array $words Lista de palavras
     * @param string $title Título do produto
     * @param string $categoryId ID da categoria
     * @return array Palavras com seus scores
     */
    public function scoreWords(array $words, string $title, string $categoryId): array
    {
        $results = [];

        foreach ($words as $word) {
            $wordText = is_array($word) ? $word['word'] : $word;
            $score = $this->calculateScore($wordText, $title, $categoryId);
            $components = $this->getScoreComponents($wordText, $title, $categoryId);

            $results[] = [
                'word' => $wordText,
                'score' => $score,
                'components' => $components,
                'contexts' => $this->detectContexts($wordText),
                'level' => $this->synonymService->identifyLevel($wordText)
            ];
        }

        return $results;
    }

    /**
     * Rankeia palavras por score (maior para menor)
     *
     * @param array $words Lista de palavras
     * @param string $title Título do produto
     * @param string $categoryId ID da categoria
     * @return array Palavras rankeadas
     */
    public function rankByScore(array $words, string $title, string $categoryId): array
    {
        $scored = $this->scoreWords($words, $title, $categoryId);

        usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        // Adicionar posição no ranking
        foreach ($scored as $index => &$item) {
            $item['rank'] = $index + 1;
        }

        return $scored;
    }

    /**
     * Verifica se palavra tem contexto de uso
     *
     * @param string $word Palavra para verificar
     * @return bool True se tem contexto identificável
     */
    public function hasUseContext(string $word): bool
    {
        $contexts = $this->detectContexts($word);
        return !empty($contexts);
    }

    /**
     * Retorna contextos disponíveis para uma categoria
     *
     * @param string $categoryId ID da categoria
     * @return array Lista de contextos
     */
    public function getContexts(string $categoryId): array
    {
        // Verificar cache
        if (isset($this->contextCache[$categoryId])) {
            return $this->contextCache[$categoryId];
        }

        // Buscar contextos customizados do banco
        $customContexts = $this->loadContextsFromDB($categoryId);

        if (!empty($customContexts)) {
            $this->contextCache[$categoryId] = $customContexts;
            return $customContexts;
        }

        // Retornar contextos padrão
        $this->contextCache[$categoryId] = self::USE_CONTEXTS;
        return self::USE_CONTEXTS;
    }

    /**
     * Adiciona contexto customizado para categoria
     *
     * @param string $categoryId ID da categoria
     * @param string $contextType Tipo do contexto
     * @param string $keyword Palavra-chave do contexto
     * @param float $weight Peso do contexto
     * @return bool Sucesso da operação
     */
    public function addContext(
        string $categoryId,
        string $contextType,
        string $keyword,
        float $weight = 1.0
    ): bool {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO seo_use_contexts
                (category_id, context_type, keyword, weight, is_active, created_at)
                VALUES (:category_id, :context_type, :keyword, :weight, 1, NOW())
                ON DUPLICATE KEY UPDATE
                    weight = :weight2,
                    is_active = 1
            ");

            $stmt->execute([
                'category_id' => $categoryId,
                'context_type' => $contextType,
                'keyword' => mb_strtolower(trim($keyword)),
                'weight' => min(2.0, max(0.1, $weight)),
                'weight2' => min(2.0, max(0.1, $weight))
            ]);

            // Limpar cache
            unset($this->contextCache[$categoryId]);

            return true;
        } catch (Exception $e) {
            log_warning('Erro ao adicionar contexto semântico', [
                'service' => 'SemanticScoreService',
                'category_id' => $categoryId,
                'context_type' => $contextType,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Remove contexto de categoria
     *
     * @param string $categoryId ID da categoria
     * @param string $contextType Tipo do contexto
     * @param string $keyword Palavra-chave
     * @return bool Sucesso da operação
     */
    public function removeContext(string $categoryId, string $contextType, string $keyword): bool
    {
        try {
            $stmt = $this->db->prepare("
                UPDATE seo_use_contexts
                SET is_active = 0
                WHERE category_id = :category_id
                AND context_type = :context_type
                AND keyword = :keyword
            ");

            $stmt->execute([
                'category_id' => $categoryId,
                'context_type' => $contextType,
                'keyword' => mb_strtolower(trim($keyword))
            ]);

            unset($this->contextCache[$categoryId]);

            return $stmt->rowCount() > 0;
        } catch (Exception $e) {
            log_warning('Erro ao remover contexto semântico', [
                'service' => 'SemanticScoreService',
                'category_id' => $categoryId,
                'context_type' => $contextType,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Calcula score médio de uma lista de palavras
     *
     * @param array $words Lista de palavras
     * @param string $title Título do produto
     * @param string $categoryId ID da categoria
     * @return float Score médio
     */
    public function calculateAverageScore(array $words, string $title, string $categoryId): float
    {
        if (empty($words)) {
            return 0.0;
        }

        $scored = $this->scoreWords($words, $title, $categoryId);
        $totalScore = array_sum(array_column($scored, 'score'));

        return round($totalScore / count($scored), 3);
    }

    /**
     * Filtra palavras por score mínimo
     *
     * @param array $words Lista de palavras
     * @param string $title Título do produto
     * @param string $categoryId ID da categoria
     * @param float $minScore Score mínimo (0.0-1.0)
     * @return array Palavras que atendem ao score mínimo
     */
    public function filterByMinScore(
        array $words,
        string $title,
        string $categoryId,
        float $minScore = 0.5
    ): array {
        $scored = $this->scoreWords($words, $title, $categoryId);

        return array_values(array_filter($scored, fn(array $item): bool => $item['score'] >= $minScore));
    }

    /**
     * Agrupa palavras por faixa de score
     *
     * @param array $words Lista de palavras
     * @param string $title Título do produto
     * @param string $categoryId ID da categoria
     * @return array Palavras agrupadas por faixa
     */
    public function groupByScoreRange(array $words, string $title, string $categoryId): array
    {
        $scored = $this->scoreWords($words, $title, $categoryId);

        $groups = [
            'excellent' => [],  // 0.8 - 1.0
            'good' => [],       // 0.6 - 0.79
            'average' => [],    // 0.4 - 0.59
            'low' => []         // 0.0 - 0.39
        ];

        foreach ($scored as $item) {
            if ($item['score'] >= 0.8) {
                $groups['excellent'][] = $item;
            } elseif ($item['score'] >= 0.6) {
                $groups['good'][] = $item;
            } elseif ($item['score'] >= 0.4) {
                $groups['average'][] = $item;
            } else {
                $groups['low'][] = $item;
            }
        }

        return $groups;
    }

    /**
     * Retorna estatísticas de score para análise
     *
     * @param array $words Lista de palavras
     * @param string $title Título do produto
     * @param string $categoryId ID da categoria
     * @return array Estatísticas
     */
    public function getScoreStatistics(array $words, string $title, string $categoryId): array
    {
        $scored = $this->scoreWords($words, $title, $categoryId);
        $scores = array_column($scored, 'score');

        if (empty($scores)) {
            return [
                'count' => 0,
                'min' => 0,
                'max' => 0,
                'average' => 0,
                'median' => 0
            ];
        }

        sort($scores);
        $count = count($scores);
        $middle = (int) floor($count / 2);

        return [
            'count' => $count,
            'min' => min($scores),
            'max' => max($scores),
            'average' => round(array_sum($scores) / $count, 3),
            'median' => $count % 2 === 0
                ? ($scores[$middle - 1] + $scores[$middle]) / 2
                : $scores[$middle],
            'distribution' => [
                'excellent' => count(array_filter($scores, fn(float|int $s): bool => $s >= 0.8)),
                'good' => count(array_filter($scores, fn(float|int $s): bool => $s >= 0.6 && $s < 0.8)),
                'average' => count(array_filter($scores, fn(float|int $s): bool => $s >= 0.4 && $s < 0.6)),
                'low' => count(array_filter($scores, fn(float|int $s): bool => $s < 0.4))
            ]
        ];
    }

    // ==================== MÉTODOS PRIVADOS ====================

    /**
     * Calcula componentes individuais do score
     */
    private function getScoreComponents(string $word, string $title, string $categoryId): array
    {
        return [
            'relevance' => $this->calculateRelevanceScore($word, $title),
            'hierarchy' => $this->calculateHierarchyScore($word, $categoryId),
            'context' => $this->calculateContextScore($word, $categoryId),
            'performance' => $this->calculatePerformanceScore($word, $categoryId)
        ];
    }

    /**
     * Calcula score de relevância com o título
     */
    private function calculateRelevanceScore(string $word, string $title): float
    {
        $wordLower = mb_strtolower($word);
        $titleLower = mb_strtolower($title);

        // Verificar se palavra está contida no título
        if (str_contains($titleLower, $wordLower)) {
            return 0.9; // Alta relevância direta
        }

        // Verificar palavras individuais
        $wordParts = explode(' ', $wordLower);
        $titleParts = explode(' ', $titleLower);
        $matches = 0;

        foreach ($wordParts as $part) {
            if (in_array($part, $titleParts)) {
                $matches++;
            }
        }

        if (count($wordParts) > 0) {
            $matchRatio = $matches / count($wordParts);
            return min(1.0, $matchRatio * 0.8);
        }

        // Verificar similaridade com levenshtein
        foreach ($titleParts as $titlePart) {
            foreach ($wordParts as $wordPart) {
                $distance = levenshtein($titlePart, $wordPart);
                $maxLen = max(mb_strlen($titlePart), mb_strlen($wordPart));
                if ($maxLen > 0 && $distance / $maxLen < 0.3) {
                    return 0.5; // Similaridade moderada
                }
            }
        }

        return 0.2; // Relevância baixa
    }

    /**
     * Calcula score baseado no nível hierárquico
     */
    private function calculateHierarchyScore(string $word, string $categoryId): float
    {
        $level = $this->synonymService->identifyLevel($word);
        $hierarchy = $this->synonymService->getHierarchy($categoryId);

        $levelConfig = $hierarchy[$level] ?? null;
        if ($levelConfig) {
            return $levelConfig['weight'];
        }

        // Fallback baseado no nível
        $levelWeights = [
            'nivel_1' => 1.0,
            'nivel_2' => 0.7,
            'nivel_3' => 0.5,
            'nivel_4' => 0.3
        ];

        return $levelWeights[$level] ?? 0.5;
    }

    /**
     * Calcula score baseado no contexto de uso
     */
    private function calculateContextScore(string $word, string $categoryId): float
    {
        $contexts = $this->detectContexts($word);

        if (empty($contexts)) {
            return 0.5; // Score neutro se não houver contexto
        }

        // Buscar pesos dos contextos para a categoria
        $categoryContexts = $this->getContexts($categoryId);

        $totalWeight = 0.0;
        foreach ($contexts as $context) {
            $contextConfig = $categoryContexts[$context] ?? self::USE_CONTEXTS[$context] ?? null;
            if ($contextConfig) {
                $totalWeight += $contextConfig['weight'];
            }
        }

        // Normalizar para escala 0-1
        return min(1.0, $totalWeight / (count($contexts) * 1.2));
    }

    /**
     * Calcula score baseado em performance histórica
     */
    private function calculatePerformanceScore(string $word, string $categoryId): float
    {
        try {
            $stmt = $this->db->prepare("
                SELECT AVG(conversion_rate) as avg_conversion,
                       AVG(click_rate) as avg_ctr
                FROM seo_keyword_performance
                WHERE keyword LIKE :keyword
                AND category_id = :category_id
                AND recorded_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");

            $stmt->execute([
                'keyword' => '%' . mb_strtolower($word) . '%',
                'category_id' => $categoryId
            ]);

            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row && ($row['avg_conversion'] !== null || $row['avg_ctr'] !== null)) {
                $convScore = min(1.0, ($row['avg_conversion'] ?? 0) / 5); // 5% = 1.0
                $ctrScore = min(1.0, ($row['avg_ctr'] ?? 0) / 10); // 10% = 1.0
                return ($convScore + $ctrScore) / 2;
            }
        } catch (Exception $e) {
            // Tabela pode não existir ainda
        }

        return 0.5; // Score neutro sem dados históricos
    }

    /**
     * Detecta contextos de uso em uma palavra
     */
    private function detectContexts(string $word): array
    {
        $wordLower = mb_strtolower($word);
        $detected = [];

        foreach (self::USE_CONTEXTS as $contextType => $config) {
            foreach ($config['keywords'] as $keyword) {
                if (str_contains($wordLower, $keyword)) {
                    $detected[] = $contextType;
                    break;
                }
            }
        }

        return array_unique($detected);
    }

    /**
     * Carrega contextos customizados do banco
     */
    private function loadContextsFromDB(string $categoryId): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT context_type, keyword, weight
                FROM seo_use_contexts
                WHERE category_id = :category_id AND is_active = 1
                ORDER BY context_type, weight DESC
            ");

            $stmt->execute(['category_id' => $categoryId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            if (empty($rows)) {
                return [];
            }

            $contexts = [];
            foreach ($rows as $row) {
                $type = $row['context_type'];
                if (!isset($contexts[$type])) {
                    $contexts[$type] = [
                        'keywords' => [],
                        'weight' => (float) $row['weight'],
                        'description' => ucfirst($type)
                    ];
                }
                $contexts[$type]['keywords'][] = $row['keyword'];
            }

            return $contexts;
        } catch (Exception $e) {
            return [];
        }
    }
}
