<?php

namespace App\Services\AI\SEO;

use App\Database;
use App\Services\MercadoLivreClient;
use App\Services\ItemService;
use App\Services\CategoryService;
use App\Services\AI\Core\AIProviderManager;
use PDO;

/**
 * 🔥 SEO KILLER ENGINE - Sistema Matador para Mercado Livre
 * 
 * Diagnóstico completo de conta + estratégias matadoras de SEO:
 * - Semântica avançada
 * - Cauda longa (long-tail)
 * - Preenchimento total de lacunas
 * - Atributos ocultos
 * - Descrições persuasivas
 * 
 * @author AI Development Team
 * @version 1.0.0
 */
class SEOKillerEngine
{
    private ?\PDO $db;
    private ?int $accountId;
    private ?MercadoLivreClient $mlClient = null;
    private ?ItemService $itemService = null;
    private ?CategoryService $categoryService = null;
    private ?AIProviderManager $aiProvider = null;

    // Weights for diagnosis
    private const DIAGNOSIS_WEIGHTS = [
        'title_quality' => 20,
        'description_quality' => 15,
        'attributes_completeness' => 20,
        'image_quality' => 15,
        'price_competitiveness' => 15,
        'visibility_factors' => 15,
    ];

    public function __construct(int $accountId)
    {
        try {
            $this->db = Database::getInstance();
        } catch (\Exception $e) {
            log_warning('SEOKillerEngine: DB indisponível, operando em modo API-only', [
                'service' => 'SEOKillerEngine',
                'error' => $e->getMessage(),
            ]);
            $this->db = null;
        }
        $this->accountId = $accountId;
        $this->mlClient = new MercadoLivreClient($accountId);
        $this->itemService = new ItemService($accountId);
        $this->categoryService = new CategoryService($accountId);
        $this->aiProvider = new AIProviderManager();
    }

    /**
     * 🔍 DIAGNÓSTICO COMPLETO DA CONTA
     * Identifica TODOS os motivos de baixa performance
     */
    public function diagnoseAccount(): array
    {
        $diagnosis = [
            'account_id' => $this->accountId,
            'diagnosis_date' => date('Y-m-d H:i:s'),
            'health_score' => 0,
            'status' => 'critical', // critical, warning, healthy
            'problems' => [],
            'opportunities' => [],
            'priority_actions' => [],
            'summary' => '',
        ];

        // 1. Buscar todos os anúncios
        $items = $this->getAllItems();

        if (empty($items)) {
            $diagnosis['problems'][] = [
                'severity' => 'critical',
                'category' => 'inventory',
                'issue' => 'Conta sem anúncios ativos',
                'impact' => -100,
                'solution' => 'Criar anúncios com estratégia SEO desde o início'
            ];
            return $diagnosis;
        }

        $diagnosis['total_items'] = count($items);

        // 2. Analisar cada dimensão
        $problems = [];
        $opportunities = [];

        // A. Análise de Títulos
        $titleAnalysis = $this->analyzeTitles($items);
        $problems = array_merge($problems, $titleAnalysis['problems']);
        $opportunities = array_merge($opportunities, $titleAnalysis['opportunities']);

        // B. Análise de Descrições
        $descAnalysis = $this->analyzeDescriptions($items);
        $problems = array_merge($problems, $descAnalysis['problems']);
        $opportunities = array_merge($opportunities, $descAnalysis['opportunities']);

        // C. Análise de Atributos (Visíveis + Ocultos)
        $attrAnalysis = $this->analyzeAttributes($items);
        $problems = array_merge($problems, $attrAnalysis['problems']);
        $opportunities = array_merge($opportunities, $attrAnalysis['opportunities']);

        // D. Análise de Imagens
        $imageAnalysis = $this->analyzeImages($items);
        $problems = array_merge($problems, $imageAnalysis['problems']);
        $opportunities = array_merge($opportunities, $imageAnalysis['opportunities']);

        // E. Análise de Preços
        $priceAnalysis = $this->analyzePricing($items);
        $problems = array_merge($problems, $priceAnalysis['problems']);
        $opportunities = array_merge($opportunities, $priceAnalysis['opportunities']);

        // F. Análise de Visibilidade
        $visibilityAnalysis = $this->analyzeVisibility($items);
        $problems = array_merge($problems, $visibilityAnalysis['problems']);
        $opportunities = array_merge($opportunities, $visibilityAnalysis['opportunities']);

        // Sort by severity/impact
        usort($problems, fn($a, $b) => $b['impact'] <=> $a['impact']);
        usort($opportunities, fn($a, $b) => $b['potential'] <=> $a['potential']);

        // Calculate health score
        $totalImpact = array_sum(array_column($problems, 'impact'));
        $diagnosis['health_score'] = max(0, 100 + $totalImpact);

        // Determine status
        if ($diagnosis['health_score'] < 30) {
            $diagnosis['status'] = 'critical';
        } elseif ($diagnosis['health_score'] < 60) {
            $diagnosis['status'] = 'warning';
        } else {
            $diagnosis['status'] = 'healthy';
        }

        $diagnosis['problems'] = array_slice($problems, 0, 20);
        $diagnosis['opportunities'] = array_slice($opportunities, 0, 10);
        $diagnosis['priority_actions'] = $this->generatePriorityActions($problems, $opportunities);
        $diagnosis['summary'] = $this->generateDiagnosisSummary($diagnosis);

        return $diagnosis;
    }

    /**
     * 📊 Analisar Títulos
     */
    private function analyzeTitles(array $items): array
    {
        $problems = [];
        $opportunities = [];

        $shortTitles = 0;
        $longTitles = 0;
        $noKeywords = 0;
        $noNumbers = 0;
        $allCaps = 0;

        foreach ($items as $item) {
            $title = $item['title'] ?? '';
            $len = mb_strlen($title);

            if ($len < 40) {
                $shortTitles++;
            } elseif ($len > 60) {
                $longTitles++;
            }

            if (!preg_match('/\d/', $title)) {
                $noNumbers++;
            }

            if ($title === mb_strtoupper($title) && $len > 10) {
                $allCaps++;
            }
        }

        $total = count($items);

        if ($shortTitles > $total * 0.3) {
            $problems[] = [
                'severity' => 'high',
                'category' => 'title',
                'issue' => "Títulos curtos demais ({$shortTitles} de {$total})",
                'impact' => -15,
                'affected_items' => $shortTitles,
                'solution' => 'Expandir títulos para 50-60 caracteres com keywords de cauda longa'
            ];
        }

        if ($noNumbers > $total * 0.5) {
            $opportunities[] = [
                'category' => 'title',
                'opportunity' => 'Adicionar especificações numéricas nos títulos',
                'potential' => 10,
                'affected_items' => $noNumbers,
                'strategy' => 'Incluir tamanho, quantidade, capacidade, voltagem, etc.'
            ];
        }

        return ['problems' => $problems, 'opportunities' => $opportunities];
    }

    /**
     * 📝 Analisar Descrições
     */
    private function analyzeDescriptions(array $items): array
    {
        $problems = [];
        $opportunities = [];

        $noDescription = 0;
        $shortDescriptions = 0;
        $noStructure = 0;
        $noEmojis = 0;

        foreach ($items as $item) {
            // Get ML item ID (may be in 'id' or 'ml_item_id' field)
            $mlItemId = $item['id'] ?? $item['ml_item_id'] ?? null;

            if (!$mlItemId) {
                continue; // Skip items without ML ID
            }

            $desc = $this->getItemDescription($mlItemId);

            // Ensure $desc is a string
            if (is_array($desc)) {
                $desc = $desc['plain_text'] ?? json_encode($desc);
            }
            if (!is_string($desc)) {
                $desc = (string) $desc;
            }

            $len = mb_strlen($desc);

            if ($len < 100) {
                $noDescription++;
            } elseif ($len < 500) {
                $shortDescriptions++;
            }

            if (strpos($desc, '•') === false && strpos($desc, '-') === false) {
                $noStructure++;
            }
        }

        $total = count($items);

        if ($noDescription > 0) {
            $problems[] = [
                'severity' => 'critical',
                'category' => 'description',
                'issue' => "{$noDescription} anúncios sem descrição adequada",
                'impact' => -20,
                'affected_items' => $noDescription,
                'solution' => 'Gerar descrições completas com IA usando template persuasivo'
            ];
        }

        if ($noStructure > $total * 0.5) {
            $opportunities[] = [
                'category' => 'description',
                'opportunity' => 'Estruturar descrições com bullet points',
                'potential' => 15,
                'affected_items' => $noStructure,
                'strategy' => 'Usar emojis + bullets + seções claras'
            ];
        }

        return ['problems' => $problems, 'opportunities' => $opportunities];
    }

    /**
     * 🔧 Analisar Atributos (CRÍTICO - Visíveis + Ocultos)
     */
    private function analyzeAttributes(array $items): array
    {
        $problems = [];
        $opportunities = [];

        $incompleteItems = 0;
        $missingRequired = 0;
        $totalMissingAttrs = 0;

        foreach ($items as $item) {
            $categoryId = $item['category_id'] ?? '';
            if (!$categoryId) continue;

            // Buscar atributos da categoria
            try {
                $categoryAttrs = $this->categoryService->getCategoryAttributes($categoryId);
                $itemAttrs = $item['attributes'] ?? [];
                $itemAttrIds = array_column($itemAttrs, 'id');

                $requiredAttrs = array_filter(
                    $categoryAttrs['attributes'] ?? [],
                    fn($a) => ($a['tags']['required'] ?? false) || ($a['tags']['catalog_required'] ?? false)
                );

                $missingCount = 0;
                foreach ($requiredAttrs as $attr) {
                    if (!in_array($attr['id'], $itemAttrIds)) {
                        $missingCount++;
                        $missingRequired++;
                    }
                }

                // Check optional but important attributes
                $optionalAttrs = array_filter(
                    $categoryAttrs['attributes'] ?? [],
                    fn($a) => !($a['tags']['required'] ?? false) && !($a['tags']['hidden'] ?? false)
                );

                foreach ($optionalAttrs as $attr) {
                    if (!in_array($attr['id'], $itemAttrIds)) {
                        $totalMissingAttrs++;
                    }
                }

                if ($missingCount > 0) {
                    $incompleteItems++;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        $total = count($items);

        if ($missingRequired > 0) {
            $problems[] = [
                'severity' => 'critical',
                'category' => 'attributes',
                'issue' => "{$incompleteItems} anúncios com atributos OBRIGATÓRIOS faltando",
                'impact' => -25,
                'affected_items' => $incompleteItems,
                'solution' => 'Preencher TODOS os atributos obrigatórios imediatamente'
            ];
        }

        if ($totalMissingAttrs > $total * 5) {
            $opportunities[] = [
                'category' => 'attributes',
                'opportunity' => "~{$totalMissingAttrs} atributos opcionais podem ser preenchidos",
                'potential' => 20,
                'strategy' => 'Preencher 100% dos atributos aumenta visibilidade em filtros'
            ];
        }

        return ['problems' => $problems, 'opportunities' => $opportunities];
    }

    /**
     * 🖼️ Analisar Imagens
     */
    private function analyzeImages(array $items): array
    {
        $problems = [];
        $opportunities = [];

        $fewImages = 0;
        $noImages = 0;

        foreach ($items as $item) {
            $imageCount = count($item['pictures'] ?? []);

            if ($imageCount === 0) {
                $noImages++;
            } elseif ($imageCount < 4) {
                $fewImages++;
            }
        }

        $total = count($items);

        if ($noImages > 0) {
            $problems[] = [
                'severity' => 'critical',
                'category' => 'images',
                'issue' => "{$noImages} anúncios sem imagens",
                'impact' => -30,
                'affected_items' => $noImages,
                'solution' => 'Adicionar mínimo 4-6 imagens de qualidade'
            ];
        }

        if ($fewImages > $total * 0.3) {
            $opportunities[] = [
                'category' => 'images',
                'opportunity' => 'Adicionar mais imagens em ' . $fewImages . ' anúncios',
                'potential' => 15,
                'strategy' => 'ML favorece anúncios com 6+ imagens de ângulos diferentes'
            ];
        }

        return ['problems' => $problems, 'opportunities' => $opportunities];
    }

    /**
     * 💰 Analisar Preços
     */
    private function analyzePricing(array $items): array
    {
        $problems = [];
        $opportunities = [];

        // Simplified - would need competitor data for full analysis
        $noFreeShipping = 0;

        foreach ($items as $item) {
            if (!($item['shipping']['free_shipping'] ?? false)) {
                $noFreeShipping++;
            }
        }

        $total = count($items);

        if ($noFreeShipping > $total * 0.5) {
            $problems[] = [
                'severity' => 'medium',
                'category' => 'shipping',
                'issue' => "{$noFreeShipping} anúncios sem frete grátis",
                'impact' => -10,
                'affected_items' => $noFreeShipping,
                'solution' => 'Ativar frete grátis - aumenta CTR em até 30%'
            ];
        }

        return ['problems' => $problems, 'opportunities' => $opportunities];
    }

    /**
     * 👁️ Analisar Visibilidade
     */
    private function analyzeVisibility(array $items): array
    {
        $problems = [];
        $opportunities = [];

        $pausedItems = 0;
        $lowListingType = 0;

        foreach ($items as $item) {
            if (($item['status'] ?? '') === 'paused') {
                $pausedItems++;
            }

            $listingType = $item['listing_type_id'] ?? '';
            if (in_array($listingType, ['bronze', 'free'])) {
                $lowListingType++;
            }
        }

        if ($pausedItems > 0) {
            $problems[] = [
                'severity' => 'high',
                'category' => 'visibility',
                'issue' => "{$pausedItems} anúncios pausados",
                'impact' => -15,
                'affected_items' => $pausedItems,
                'solution' => 'Reativar anúncios pausados após otimização'
            ];
        }

        if ($lowListingType > 0) {
            $opportunities[] = [
                'category' => 'visibility',
                'opportunity' => 'Upgrade de tipo de anúncio em ' . $lowListingType . ' itens',
                'potential' => 20,
                'strategy' => 'Clássico/Premium têm muito mais exposição que Grátis'
            ];
        }

        return ['problems' => $problems, 'opportunities' => $opportunities];
    }

    /**
     * 🎯 Gerar Ações Prioritárias
     */
    private function generatePriorityActions(array $problems, array $opportunities): array
    {
        $actions = [];

        // Top 5 critical problems
        $criticalProblems = array_filter($problems, fn($p) => $p['severity'] === 'critical');
        foreach (array_slice($criticalProblems, 0, 3) as $problem) {
            $actions[] = [
                'priority' => 1,
                'type' => 'fix_problem',
                'action' => $problem['solution'],
                'category' => $problem['category'],
                'impact' => 'Alto',
                'affected' => $problem['affected_items'] ?? 0,
            ];
        }

        // Top opportunities
        foreach (array_slice($opportunities, 0, 2) as $opp) {
            $actions[] = [
                'priority' => 2,
                'type' => 'opportunity',
                'action' => $opp['strategy'],
                'category' => $opp['category'],
                'impact' => 'Médio-Alto',
                'potential' => '+' . $opp['potential'] . '%',
            ];
        }

        return $actions;
    }

    /**
     * 📋 Gerar Resumo do Diagnóstico
     */
    private function generateDiagnosisSummary(array $diagnosis): string
    {
        $score = $diagnosis['health_score'];
        $totalProblems = count($diagnosis['problems']);

        if ($score < 30) {
            return "🔴 CONTA CRÍTICA: Score {$score}/100. {$totalProblems} problemas graves identificados. " .
                "A conta precisa de otimização urgente em títulos, descrições e atributos.";
        } elseif ($score < 60) {
            return "🟡 CONTA COM PROBLEMAS: Score {$score}/100. {$totalProblems} issues encontradas. " .
                "Há potencial significativo de melhoria com SEO estratégico.";
        } else {
            return "🟢 CONTA SAUDÁVEL: Score {$score}/100. " .
                "Foco em otimizações finas e oportunidades de crescimento.";
        }
    }

    /**
     * Helper: Get all items with pagination
     * Busca todos os anúncios da conta (ativos e pausados) com safety limit de 1000
     */
    private function getAllItems(): array
    {
        $allItems = [];
        $offset = 0;
        $limit = 50;
        $maxItems = 1000; // Safety limit

        try {
            while ($offset < $maxItems) {
                // Buscar itens ativos e pausados (não apenas ativos)
                $result = $this->itemService->listItems([
                    'limit' => $limit,
                    'offset' => $offset,
                    'allow_local_cache' => true, // Permitir fallback para cache local
                ]);

                $items = $result['items'] ?? [];

                // Se não retornou itens, chegamos ao fim
                if (empty($items)) {
                    break;
                }

                $allItems = array_merge($allItems, $items);
                $offset += $limit;

                // Se retornou menos que o limit, não há mais páginas
                if (count($items) < $limit) {
                    break;
                }
            }

            log_info('SEOKillerEngine: itens carregados', [
                'service' => 'SEOKillerEngine',
                'count' => count($allItems),
            ]);
            return $allItems;
        } catch (\Exception $e) {
            log_error('SEOKillerEngine: erro ao buscar todos os itens', [
                'service' => 'SEOKillerEngine',
                'error' => $e->getMessage(),
                'partial_count' => count($allItems),
            ]);
            // Retorna o que conseguiu buscar até o erro
            return $allItems;
        }
    }

    /**
     * Helper: Get item description
     */
    private function getItemDescription(string $itemId): string
    {
        try {
            $desc = $this->mlClient->get("/items/{$itemId}/description");
            return $desc['plain_text'] ?? $desc['text'] ?? '';
        } catch (\Exception $e) {
            return '';
        }
    }

    // ========================================================================
    // 🎯 SEO STRATEGIES ENGINE INTEGRATION
    // ========================================================================

    /**
     * Run advanced SEO analysis using all 12 strategies
     */
    public function runStrategiesAnalysis(string $itemId): array
    {
        $engine = new Strategies\SEOStrategiesEngine($this->accountId);
        return $engine->analyzeItem($itemId);
    }

    /**
     * Get detailed SEO strategies score for an item
     */
    public function getStrategiesScore(string $itemId): array
    {
        $engine = new Strategies\SEOStrategiesEngine($this->accountId);
        $analysis = $engine->analyzeItem($itemId);

        return [
            'item_id' => $itemId,
            'overall_score' => $analysis['overall_score'] ?? 0,
            'strategies' => $analysis['strategies'] ?? [],
            'recommendations' => $analysis['recommendations'] ?? [],
        ];
    }

    /**
     * Optimize item using all 12 SEO strategies
     */
    public function optimizeWithStrategies(string $itemId): array
    {
        $engine = new Strategies\SEOStrategiesEngine($this->accountId);

        // Run full optimization
        $titleOpt = $engine->optimizeTitle($itemId);
        $descOpt = $engine->optimizeDescription($itemId);
        $keywords = $engine->generateKeywords($itemId);

        return [
            'item_id' => $itemId,
            'title_optimization' => $titleOpt,
            'description_optimization' => $descOpt,
            'generated_keywords' => $keywords,
            'strategies_applied' => 12,
        ];
    }

    /**
     * Batch analyze items with SEO strategies
     */
    public function batchStrategiesAnalysis(array $itemIds, int $limit = 10): array
    {
        $results = [];
        $engine = new Strategies\SEOStrategiesEngine($this->accountId);
        $cache = new Strategies\SEOAnalysisCacheService($this->accountId);

        // Limit to prevent timeout
        $itemIds = array_slice($itemIds, 0, $limit);

        foreach ($itemIds as $itemId) {
            try {
                // Use cache when available
                $cached = $cache->get($itemId);
                if ($cached !== null) {
                    $results[$itemId] = [
                        'success' => true,
                        'score' => $cached['overall_score'],
                        'from_cache' => true,
                    ];
                    continue;
                }

                $analysis = $engine->analyzeItem($itemId);
                $results[$itemId] = [
                    'success' => true,
                    'score' => $analysis['overall_score'] ?? 0,
                    'from_cache' => false,
                ];

                // Save to cache
                $cache->set($itemId, $analysis);
            } catch (\Exception $e) {
                $results[$itemId] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'total' => count($itemIds),
            'processed' => count($results),
            'results' => $results,
        ];
    }

    /**
     * Get SEO strategies dashboard data
     */
    public function getStrategiesDashboard(): array
    {
        $cache = new Strategies\SEOAnalysisCacheService($this->accountId);

        return [
            'cache_stats' => $cache->getStats(),
            'score_distribution' => $cache->getScoreDistribution(),
            'low_score_items' => $cache->getLowScoreItems(10, 50),
            'stale_items' => $cache->getStaleItems(20),
        ];
    }
}
