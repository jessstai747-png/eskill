<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * CloneSeoOptimizationService
 *
 * Integração SEO completa no fluxo de clonagem.
 * Analisa, otimiza e aplica melhorias antes/depois do clone.
 */
class CloneSeoOptimizationService
{
    private PDO $db;
    private int $accountId;
    private ?MercadoLivreClient $client = null;

    // Níveis de otimização SEO
    public const LEVEL_NONE = 'none';
    public const LEVEL_BASIC = 'basic';
    public const LEVEL_STANDARD = 'standard';
    public const LEVEL_ADVANCED = 'advanced';
    public const LEVEL_AGGRESSIVE = 'aggressive';

    // Palavras proibidas/filtradas no ML
    private array $forbiddenWords = [
        'réplica',
        'replica',
        'imitação',
        'imitacao',
        'fake',
        'falso',
        'primeira linha',
        '1a linha',
        'aaa',
        'grau a',
        'importado china',
        'pronta entrega',
        'envio imediato',
        'frete grátis',
        'frete gratis',
        'promoção',
        'promocao',
        'oferta',
        'liquidação',
        'liquidacao',
        'black friday',
        'natal',
        'ano novo'
    ];

    // Palavras de alto impacto para SEO ML
    private array $highImpactWords = [
        'original',
        'genuíno',
        'genuino',
        'autêntico',
        'autentico',
        'novo',
        'lacrado',
        'garantia',
        'nf',
        'nota fiscal',
        'premium',
        'profissional',
        'alta qualidade'
    ];

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    private function getClient(): MercadoLivreClient
    {
        if ($this->client === null) {
            $this->client = new MercadoLivreClient($this->accountId);
        }
        return $this->client;
    }

    /**
     * Analisa item antes do clone e retorna score/recomendações
     */
    public function analyzeForClone(string $itemId, string $level = self::LEVEL_STANDARD): array
    {
        $client = $this->getClient();

        try {
            $itemData = $client->get("/items/$itemId");
            $description = $client->get("/items/$itemId/description");
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }

        $analysis = [
            'item_id' => $itemId,
            'level' => $level,
            'current_score' => 0,
            'potential_score' => 0,
            'issues' => [],
            'recommendations' => [],
            'optimizations' => []
        ];

        // Análise de título
        $titleAnalysis = $this->analyzeTitle($itemData['title'] ?? '', $itemData['category_id'] ?? '');
        $analysis['title'] = $titleAnalysis;
        $analysis['current_score'] += $titleAnalysis['score'];

        // Análise de descrição
        $descAnalysis = $this->analyzeDescription($description['plain_text'] ?? '');
        $analysis['description'] = $descAnalysis;
        $analysis['current_score'] += $descAnalysis['score'];

        // Análise de atributos
        $attrAnalysis = $this->analyzeAttributes($itemData['attributes'] ?? [], $itemData['category_id'] ?? '');
        $analysis['attributes'] = $attrAnalysis;
        $analysis['current_score'] += $attrAnalysis['score'];

        // Análise de imagens
        $imgAnalysis = $this->analyzeImages($itemData['pictures'] ?? []);
        $analysis['images'] = $imgAnalysis;
        $analysis['current_score'] += $imgAnalysis['score'];

        // Análise de frete
        $shippingAnalysis = $this->analyzeShipping($itemData['shipping'] ?? []);
        $analysis['shipping'] = $shippingAnalysis;
        $analysis['current_score'] += $shippingAnalysis['score'];

        // Score máximo = 100 (20 por categoria)
        $analysis['current_score'] = min(100, $analysis['current_score']);

        // Calcular score potencial com otimizações
        $analysis['potential_score'] = $this->calculatePotentialScore($analysis, $level);

        // Gerar otimizações sugeridas
        $analysis['optimizations'] = $this->generateOptimizations($analysis, $level);

        // Should clone?
        $analysis['should_clone'] = $analysis['current_score'] >= 40 || $level === self::LEVEL_AGGRESSIVE;
        $analysis['clone_risk'] = $this->calculateCloneRisk($analysis);

        // Salvar análise
        $this->saveAnalysis($itemId, $analysis);

        return $analysis;
    }

    /**
     * Analisa título do anúncio
     */
    private function analyzeTitle(string $title, string $categoryId): array
    {
        $result = [
            'original' => $title,
            'length' => mb_strlen($title),
            'score' => 0,
            'issues' => [],
            'suggestions' => []
        ];

        $length = mb_strlen($title);

        // Comprimento ideal: 45-58 caracteres
        if ($length >= 45 && $length <= 58) {
            $result['score'] = 20;
        } elseif ($length >= 40 && $length <= 60) {
            $result['score'] = 15;
        } elseif ($length >= 30) {
            $result['score'] = 10;
        } else {
            $result['score'] = 5;
            $result['issues'][] = 'Título muito curto (mín. 40 caracteres recomendado)';
        }

        if ($length > 60) {
            $result['issues'][] = 'Título excede limite de 60 caracteres';
            $result['score'] = max(0, $result['score'] - 5);
        }

        // Verificar palavras proibidas
        $lowerTitle = mb_strtolower($title);
        foreach ($this->forbiddenWords as $word) {
            if (strpos($lowerTitle, $word) !== false) {
                $result['issues'][] = "Contém palavra proibida: '$word'";
                $result['score'] = max(0, $result['score'] - 3);
            }
        }

        // Verificar palavras de alto impacto
        $hasHighImpact = false;
        foreach ($this->highImpactWords as $word) {
            if (strpos($lowerTitle, $word) !== false) {
                $hasHighImpact = true;
                break;
            }
        }
        if (!$hasHighImpact) {
            $result['suggestions'][] = 'Adicionar palavras de alto impacto (Original, Novo, Garantia)';
        }

        // Verificar estrutura (Marca + Modelo + Característica)
        if (preg_match('/^[A-Z]/u', $title)) {
            $result['score'] += 2;
        } else {
            $result['suggestions'][] = 'Iniciar título com maiúscula';
        }

        return $result;
    }

    /**
     * Analisa descrição do anúncio
     */
    private function analyzeDescription(string $description): array
    {
        $result = [
            'length' => mb_strlen($description),
            'score' => 0,
            'issues' => [],
            'suggestions' => []
        ];

        $length = mb_strlen($description);

        // Mínimo recomendado: 500 caracteres
        if ($length >= 1000) {
            $result['score'] = 20;
        } elseif ($length >= 500) {
            $result['score'] = 15;
        } elseif ($length >= 200) {
            $result['score'] = 10;
            $result['suggestions'][] = 'Expandir descrição para pelo menos 500 caracteres';
        } else {
            $result['score'] = 5;
            $result['issues'][] = 'Descrição muito curta';
        }

        // Verificar estruturação (bullets, parágrafos)
        if (strpos($description, '•') !== false || strpos($description, '-') !== false) {
            $result['score'] += 2;
        } else {
            $result['suggestions'][] = 'Usar bullets (•) para listar características';
        }

        // Verificar seções
        $sections = ['especificações', 'características', 'garantia', 'conteúdo'];
        $foundSections = 0;
        $lowerDesc = mb_strtolower($description);
        foreach ($sections as $section) {
            if (strpos($lowerDesc, $section) !== false) {
                $foundSections++;
            }
        }

        if ($foundSections < 2) {
            $result['suggestions'][] = 'Adicionar seções: Especificações, Características, Garantia';
        }

        return $result;
    }

    /**
     * Analisa atributos do anúncio
     */
    private function analyzeAttributes(array $attributes, string $categoryId): array
    {
        $result = [
            'total' => count($attributes),
            'score' => 0,
            'missing_required' => [],
            'suggestions' => []
        ];

        // Atributos críticos
        $criticalAttrs = ['BRAND', 'MODEL', 'GTIN'];
        $foundCritical = 0;

        $attrIds = array_column($attributes, 'id');

        foreach ($criticalAttrs as $attr) {
            if (in_array($attr, $attrIds)) {
                $foundCritical++;
            } else {
                $result['missing_required'][] = $attr;
            }
        }

        // Score baseado em atributos preenchidos
        if ($foundCritical === 3) {
            $result['score'] = 15;
        } elseif ($foundCritical >= 2) {
            $result['score'] = 10;
        } elseif ($foundCritical >= 1) {
            $result['score'] = 5;
        }

        // Bonus por mais atributos
        if (count($attributes) >= 10) {
            $result['score'] += 5;
        } elseif (count($attributes) >= 5) {
            $result['score'] += 3;
        }

        if (!empty($result['missing_required'])) {
            $result['suggestions'][] = 'Preencher: ' . implode(', ', $result['missing_required']);
        }

        return $result;
    }

    /**
     * Analisa imagens do anúncio
     */
    private function analyzeImages(array $pictures): array
    {
        $result = [
            'total' => count($pictures),
            'score' => 0,
            'issues' => [],
            'suggestions' => []
        ];

        $count = count($pictures);

        // Mínimo ideal: 6 imagens
        if ($count >= 6) {
            $result['score'] = 20;
        } elseif ($count >= 4) {
            $result['score'] = 15;
        } elseif ($count >= 2) {
            $result['score'] = 10;
        } elseif ($count >= 1) {
            $result['score'] = 5;
        } else {
            $result['issues'][] = 'Sem imagens';
        }

        if ($count < 6) {
            $result['suggestions'][] = "Adicionar mais imagens (atual: $count, ideal: 6+)";
        }

        return $result;
    }

    /**
     * Analisa configuração de frete
     */
    private function analyzeShipping(array $shipping): array
    {
        $result = [
            'free_shipping' => $shipping['free_shipping'] ?? false,
            'logistic_type' => $shipping['logistic_type'] ?? 'unknown',
            'score' => 0,
            'suggestions' => []
        ];

        // Frete grátis = melhor ranking
        if ($result['free_shipping']) {
            $result['score'] += 10;
        } else {
            $result['suggestions'][] = 'Considerar frete grátis para melhor ranking';
        }

        // Fulfillment (Full) = melhor ranking
        if ($result['logistic_type'] === 'fulfillment') {
            $result['score'] += 10;
        } elseif ($result['logistic_type'] === 'cross_docking') {
            $result['score'] += 5;
        } else {
            $result['suggestions'][] = 'Considerar Mercado Livre Full para melhor visibilidade';
        }

        return $result;
    }

    /**
     * Calcula score potencial após otimizações
     */
    private function calculatePotentialScore(array $analysis, string $level): int
    {
        $potential = $analysis['current_score'];

        // Adicionar pontos potenciais por otimizações
        foreach (['title', 'description', 'attributes', 'images'] as $key) {
            if (!empty($analysis[$key]['suggestions'])) {
                $potential += count($analysis[$key]['suggestions']) * 3;
            }
        }

        return min(100, $potential);
    }

    /**
     * Gera lista de otimizações baseadas no nível
     */
    private function generateOptimizations(array $analysis, string $level): array
    {
        $optimizations = [];

        if ($level === self::LEVEL_NONE) {
            return $optimizations;
        }

        // Basic: apenas correções críticas
        if (
            $level === self::LEVEL_BASIC || $level === self::LEVEL_STANDARD ||
            $level === self::LEVEL_ADVANCED || $level === self::LEVEL_AGGRESSIVE
        ) {

            if (!empty($analysis['title']['issues'])) {
                $optimizations['title'] = [
                    'action' => 'fix_issues',
                    'issues' => $analysis['title']['issues']
                ];
            }
        }

        // Standard: correções + sugestões básicas
        if (
            $level === self::LEVEL_STANDARD || $level === self::LEVEL_ADVANCED ||
            $level === self::LEVEL_AGGRESSIVE
        ) {

            if ($analysis['title']['length'] < 45) {
                $optimizations['title_expand'] = [
                    'action' => 'expand_title',
                    'current_length' => $analysis['title']['length'],
                    'target_length' => 50
                ];
            }

            if ($analysis['description']['length'] < 500) {
                $optimizations['description_expand'] = [
                    'action' => 'expand_description',
                    'current_length' => $analysis['description']['length']
                ];
            }
        }

        // Advanced: otimizações automáticas
        if ($level === self::LEVEL_ADVANCED || $level === self::LEVEL_AGGRESSIVE) {

            if (!empty($analysis['attributes']['missing_required'])) {
                $optimizations['attributes'] = [
                    'action' => 'fill_attributes',
                    'missing' => $analysis['attributes']['missing_required']
                ];
            }
        }

        // Aggressive: máximo de otimizações
        if ($level === self::LEVEL_AGGRESSIVE) {
            $optimizations['full_optimization'] = [
                'action' => 'apply_all',
                'description' => 'Aplicar todas as otimizações possíveis'
            ];
        }

        return $optimizations;
    }

    /**
     * Calcula risco do clone
     */
    private function calculateCloneRisk(array $analysis): string
    {
        $score = $analysis['current_score'];

        if ($score >= 70) {
            return 'low';
        } elseif ($score >= 50) {
            return 'medium';
        } elseif ($score >= 30) {
            return 'high';
        }

        return 'critical';
    }

    /**
     * Aplica otimizações ao payload do item
     */
    public function applyOptimizations(array $itemData, string $level = self::LEVEL_STANDARD): array
    {
        if ($level === self::LEVEL_NONE) {
            return $itemData;
        }

        $optimized = $itemData;

        // Otimizar título
        if (isset($optimized['title'])) {
            $optimized['title'] = $this->optimizeTitle($optimized['title'], $level);
        }

        // Otimizar descrição
        if (isset($optimized['description'])) {
            $optimized['description'] = $this->optimizeDescription($optimized['description'], $level);
        }

        // Preencher atributos faltantes
        if ($level === self::LEVEL_ADVANCED || $level === self::LEVEL_AGGRESSIVE) {
            $optimized['attributes'] = $this->optimizeAttributes($optimized['attributes'] ?? []);
        }

        return $optimized;
    }

    /**
     * Otimiza título
     */
    public function optimizeTitle(string $title, string $level = self::LEVEL_STANDARD): string
    {
        $optimized = $title;

        // Remover palavras proibidas
        foreach ($this->forbiddenWords as $word) {
            $optimized = preg_replace('/\b' . preg_quote($word, '/') . '\b/ui', '', $optimized);
        }

        // Limpar espaços extras
        $optimized = preg_replace('/\s+/', ' ', $optimized);
        $optimized = trim($optimized);

        // Capitalizar primeira letra
        $optimized = mb_strtoupper(mb_substr($optimized, 0, 1)) . mb_substr($optimized, 1);

        // Truncar se necessário
        if (mb_strlen($optimized) > 60) {
            $optimized = mb_substr($optimized, 0, 57) . '...';
        }

        return $optimized;
    }

    /**
     * Otimiza descrição
     */
    public function optimizeDescription(string $description, string $level = self::LEVEL_STANDARD): string
    {
        $optimized = $description;

        // Adicionar estrutura se não tiver
        if (strpos($optimized, '•') === false && strpos($optimized, '✓') === false) {
            // Converter listas simples em bullets
            $optimized = preg_replace('/^- /m', '• ', $optimized);
            $optimized = preg_replace('/^\* /m', '• ', $optimized);
        }

        // Remover palavras proibidas
        foreach ($this->forbiddenWords as $word) {
            $optimized = preg_replace('/\b' . preg_quote($word, '/') . '\b/ui', '', $optimized);
        }

        // Limpar espaços extras
        $optimized = preg_replace('/\n{3,}/', "\n\n", $optimized);
        $optimized = trim($optimized);

        return $optimized;
    }

    /**
     * Otimiza atributos
     */
    private function optimizeAttributes(array $attributes): array
    {
        // Adicionar valores vazios para atributos críticos se não existirem
        $attrIds = array_column($attributes, 'id');

        $criticalDefaults = [
            'BRAND' => null,
            'MODEL' => null,
        ];

        foreach ($criticalDefaults as $id => $value) {
            if (!in_array($id, $attrIds)) {
                $attributes[] = [
                    'id' => $id,
                    'value_name' => $value
                ];
            }
        }

        return $attributes;
    }

    /**
     * Análise em lote de múltiplos itens
     */
    public function analyzeBatch(array $itemIds, string $level = self::LEVEL_STANDARD): array
    {
        $results = [
            'total' => count($itemIds),
            'analyzed' => 0,
            'avg_score' => 0,
            'clone_ready' => 0,
            'needs_optimization' => 0,
            'not_recommended' => 0,
            'items' => []
        ];

        $totalScore = 0;

        foreach ($itemIds as $itemId) {
            try {
                $analysis = $this->analyzeForClone($itemId, $level);
                $results['items'][$itemId] = $analysis;
                $results['analyzed']++;

                if (isset($analysis['current_score'])) {
                    $totalScore += $analysis['current_score'];

                    if ($analysis['current_score'] >= 70) {
                        $results['clone_ready']++;
                    } elseif ($analysis['current_score'] >= 40) {
                        $results['needs_optimization']++;
                    } else {
                        $results['not_recommended']++;
                    }
                }

                usleep(100000); // 100ms rate limit

            } catch (\Exception $e) {
                $results['items'][$itemId] = [
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        $results['avg_score'] = $results['analyzed'] > 0
            ? round($totalScore / $results['analyzed'], 2)
            : 0;

        return $results;
    }

    /**
     * Obtém configurações de SEO do usuário
     */
    public function getSettings(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM clone_seo_settings
                WHERE account_id = :account_id
            ");
            $stmt->execute(['account_id' => $this->accountId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($row) {
                return [
                    'default_level' => $row['default_level'] ?? self::LEVEL_STANDARD,
                    'auto_optimize' => (bool) ($row['auto_optimize'] ?? false),
                    'forbidden_words' => json_decode($row['forbidden_words'] ?? '[]', true),
                    'high_impact_words' => json_decode($row['high_impact_words'] ?? '[]', true),
                    'min_score_to_clone' => (int) ($row['min_score_to_clone'] ?? 40)
                ];
            }
        } catch (\Exception $e) {
            // Tabela pode não existir
        }

        return [
            'default_level' => self::LEVEL_STANDARD,
            'auto_optimize' => false,
            'forbidden_words' => $this->forbiddenWords,
            'high_impact_words' => $this->highImpactWords,
            'min_score_to_clone' => 40
        ];
    }

    /**
     * Atualiza configurações de SEO
     */
    public function updateSettings(array $settings): bool
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO clone_seo_settings (
                    account_id, default_level, auto_optimize,
                    forbidden_words, high_impact_words, min_score_to_clone, updated_at
                ) VALUES (
                    :account_id, :level, :auto, :forbidden, :impact, :min_score, NOW()
                )
                ON DUPLICATE KEY UPDATE
                    default_level = VALUES(default_level),
                    auto_optimize = VALUES(auto_optimize),
                    forbidden_words = VALUES(forbidden_words),
                    high_impact_words = VALUES(high_impact_words),
                    min_score_to_clone = VALUES(min_score_to_clone),
                    updated_at = NOW()
            ");

            return $stmt->execute([
                'account_id' => $this->accountId,
                'level' => $settings['default_level'] ?? self::LEVEL_STANDARD,
                'auto' => ($settings['auto_optimize'] ?? false) ? 1 : 0,
                'forbidden' => json_encode($settings['forbidden_words'] ?? $this->forbiddenWords),
                'impact' => json_encode($settings['high_impact_words'] ?? $this->highImpactWords),
                'min_score' => (int) ($settings['min_score_to_clone'] ?? 40)
            ]);
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Salva análise no banco
     */
    private function saveAnalysis(string $itemId, array $analysis): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO clone_seo_analyses (
                    account_id, item_id, level, current_score, potential_score,
                    analysis_data, created_at
                ) VALUES (
                    :account_id, :item_id, :level, :current, :potential, :data, NOW()
                )
            ");

            $stmt->execute([
                'account_id' => $this->accountId,
                'item_id' => $itemId,
                'level' => $analysis['level'],
                'current' => $analysis['current_score'],
                'potential' => $analysis['potential_score'],
                'data' => json_encode($analysis)
            ]);
        } catch (\Exception $e) {
            // Tabela pode não existir
        }
    }

    /**
     * Obtém histórico de análises
     */
    public function getAnalysisHistory(int $limit = 50): array
    {
        try {
            $limitSql = max(1, min(200, (int)$limit));
            $stmt = $this->db->prepare("
                SELECT * FROM clone_seo_analyses
                WHERE account_id = :account_id
                ORDER BY created_at DESC
                LIMIT {$limitSql}
            ");
            $stmt->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
            $stmt->execute();

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Estatísticas de SEO
     */
    public function getStats(): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT
                    COUNT(*) as total_analyses,
                    AVG(current_score) as avg_current_score,
                    AVG(potential_score) as avg_potential_score,
                    SUM(CASE WHEN current_score >= 70 THEN 1 ELSE 0 END) as high_score,
                    SUM(CASE WHEN current_score >= 40 AND current_score < 70 THEN 1 ELSE 0 END) as medium_score,
                    SUM(CASE WHEN current_score < 40 THEN 1 ELSE 0 END) as low_score
                FROM clone_seo_analyses
                WHERE account_id = :account_id
                AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
            ");
            $stmt->execute(['account_id' => $this->accountId]);

            return $stmt->fetch(PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return [];
        }
    }
}
