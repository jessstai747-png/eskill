<?php

namespace App\Services\AI\SEO;

use App\Database;
use App\Services\MercadoLivreClient;
use App\Services\ItemService;
use App\Services\CategoryService;
use App\Services\AI\Core\AIProviderManager;
use App\Traits\SEOStrategiesIntegrationTrait;
use PDO;

/**
 * SEO Killer Engine - Diagnóstico completo de conta e otimização SEO para Mercado Livre
 */
class SEOKillerEngine
{
    use SEOStrategiesIntegrationTrait;

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
            'status' => 'critical',
            'problems' => [],
            'opportunities' => [],
            'priority_actions' => [],
            'summary' => '',
        ];

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

        [$problems, $opportunities] = $this->collectAnalysisResults($items);

        return $this->assembleDiagnosis($diagnosis, $problems, $opportunities);
    }

    /**
     * Coleta resultados de todas as análises dimensionais
     * @return array{0: array, 1: array} [problems, opportunities]
     */
    private function collectAnalysisResults(array $items): array
    {
        $problems = [];
        $opportunities = [];

        $analyzers = [
            'analyzeTitles',
            'analyzeDescriptions',
            'analyzeAttributes',
            'analyzeImages',
            'analyzePricing',
            'analyzeVisibility',
        ];

        foreach ($analyzers as $method) {
            $result = $this->{$method}($items);
            $problems = array_merge($problems, $result['problems']);
            $opportunities = array_merge($opportunities, $result['opportunities']);
        }

        return [$problems, $opportunities];
    }

    /**
     * Monta o diagnóstico final com scores e prioridades
     */
    private function assembleDiagnosis(array $diagnosis, array $problems, array $opportunities): array
    {
        usort($problems, fn($a, $b) => $b['impact'] <=> $a['impact']);
        usort($opportunities, fn($a, $b) => $b['potential'] <=> $a['potential']);

        $totalImpact = array_sum(array_column($problems, 'impact'));
        $diagnosis['health_score'] = max(0, 100 + $totalImpact);
        $diagnosis['status'] = $this->resolveHealthStatus($diagnosis['health_score']);
        $diagnosis['problems'] = array_slice($problems, 0, 20);
        $diagnosis['opportunities'] = array_slice($opportunities, 0, 10);
        $diagnosis['priority_actions'] = $this->generatePriorityActions($problems, $opportunities);
        $diagnosis['summary'] = $this->generateDiagnosisSummary($diagnosis);

        return $diagnosis;
    }

    /**
     * Resolve status textual a partir do health score
     */
    private function resolveHealthStatus(int $score): string
    {
        return match (true) {
            $score < 30 => 'critical',
            $score < 60 => 'warning',
            default => 'healthy',
        };
    }

    /**
     * 📊 Analisar Títulos
     */
    private function analyzeTitles(array $items): array
    {
        $stats = $this->collectTitleStats($items);
        $total = count($items);

        return $this->evaluateTitleStats($stats, $total);
    }

    /**
     * Coleta estatísticas de títulos dos itens
     */
    private function collectTitleStats(array $items): array
    {
        $stats = ['shortTitles' => 0, 'longTitles' => 0, 'noNumbers' => 0, 'allCaps' => 0];

        foreach ($items as $item) {
            $title = $item['title'] ?? '';
            $this->classifyTitle($title, $stats);
        }

        return $stats;
    }

    /**
     * Classifica um título individual e incrementa contadores
     */
    private function classifyTitle(string $title, array &$stats): void
    {
        $len = mb_strlen($title);

        if ($len < 40) {
            $stats['shortTitles']++;
        } elseif ($len > 60) {
            $stats['longTitles']++;
        }

        if (!preg_match('/\d/', $title)) {
            $stats['noNumbers']++;
        }

        if ($this->isTitleAllCaps($title, $len)) {
            $stats['allCaps']++;
        }
    }

    /**
     * Verifica se um título está todo em maiúsculas
     */
    private function isTitleAllCaps(string $title, int $len): bool
    {
        return $len > 10 && $title === mb_strtoupper($title);
    }

    /**
     * Avalia estatísticas de títulos e gera problemas/oportunidades
     */
    private function evaluateTitleStats(array $stats, int $total): array
    {
        $problems = [];
        $opportunities = [];

        if ($stats['shortTitles'] > $total * 0.3) {
            $problems[] = [
                'severity' => 'high',
                'category' => 'title',
                'issue' => "Títulos curtos demais ({$stats['shortTitles']} de {$total})",
                'impact' => -15,
                'affected_items' => $stats['shortTitles'],
                'solution' => 'Expandir títulos para 50-60 caracteres com keywords de cauda longa'
            ];
        }

        if ($stats['noNumbers'] > $total * 0.5) {
            $opportunities[] = [
                'category' => 'title',
                'opportunity' => 'Adicionar especificações numéricas nos títulos',
                'potential' => 10,
                'affected_items' => $stats['noNumbers'],
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
        $stats = $this->collectDescriptionStats($items);
        $total = count($items);

        return $this->evaluateDescriptionStats($stats, $total);
    }

    /**
     * Coleta estatísticas de descrições dos itens via API
     */
    private function collectDescriptionStats(array $items): array
    {
        $stats = ['noDescription' => 0, 'shortDescriptions' => 0, 'noStructure' => 0];

        foreach ($items as $item) {
            $this->classifyItemDescription($item, $stats);
        }

        return $stats;
    }

    /**
     * Classificar descrição de um item e incrementar contadores
     */
    private function classifyItemDescription(array $item, array &$stats): void
    {
        $mlItemId = $item['id'] ?? $item['ml_item_id'] ?? null;
        if ($mlItemId === null) {
            return;
        }

        $desc = $this->resolveDescriptionText((string) $mlItemId);
        $this->classifyDescriptionLength($desc, $stats);

        if (!$this->hasStructuredContent($desc)) {
            $stats['noStructure']++;
        }
    }

    /**
     * Classifica comprimento da descrição e atualiza contadores
     */
    private function classifyDescriptionLength(string $desc, array &$stats): void
    {
        $len = mb_strlen($desc);

        if ($len < 100) {
            $stats['noDescription']++;
        } elseif ($len < 500) {
            $stats['shortDescriptions']++;
        }
    }

    /**
     * Verifica se texto contém marcadores de estrutura (bullets, hífens)
     */
    private function hasStructuredContent(string $text): bool
    {
        return str_contains($text, '•') || str_contains($text, '-');
    }

    /**
     * Resolve o texto da descrição de um item, normalizando para string
     */
    private function resolveDescriptionText(string $itemId): string
    {
        $desc = $this->getItemDescription($itemId);

        if (is_array($desc)) {
            return $desc['plain_text'] ?? json_encode($desc);
        }

        return is_string($desc) ? $desc : (string) $desc;
    }

    /**
     * Avalia estatísticas de descrições e gera problemas/oportunidades
     */
    private function evaluateDescriptionStats(array $stats, int $total): array
    {
        $problems = [];
        $opportunities = [];

        if ($stats['noDescription'] > 0) {
            $problems[] = [
                'severity' => 'critical',
                'category' => 'description',
                'issue' => "{$stats['noDescription']} anúncios sem descrição adequada",
                'impact' => -20,
                'affected_items' => $stats['noDescription'],
                'solution' => 'Gerar descrições completas com IA usando template persuasivo'
            ];
        }

        if ($stats['noStructure'] > $total * 0.5) {
            $opportunities[] = [
                'category' => 'description',
                'opportunity' => 'Estruturar descrições com bullet points',
                'potential' => 15,
                'affected_items' => $stats['noStructure'],
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
        $stats = $this->collectAttributeStats($items);
        $total = count($items);

        return $this->evaluateAttributeStats($stats, $total);
    }

    /**
     * Coleta estatísticas de atributos faltantes por item
     */
    private function collectAttributeStats(array $items): array
    {
        $stats = ['incompleteItems' => 0, 'missingRequired' => 0, 'totalMissingOptional' => 0];

        foreach ($items as $item) {
            $categoryId = $item['category_id'] ?? '';
            if (!$categoryId) {
                continue;
            }

            try {
                $itemResult = $this->analyzeItemAttributes($item, $categoryId);
                $stats['missingRequired'] += $itemResult['missingRequired'];
                $stats['totalMissingOptional'] += $itemResult['missingOptional'];
                if ($itemResult['missingRequired'] > 0) {
                    $stats['incompleteItems']++;
                }
            } catch (\Exception $e) {
                continue;
            }
        }

        return $stats;
    }

    /**
     * Analisa atributos de um item específico contra sua categoria
     * @return array{missingRequired: int, missingOptional: int}
     */
    private function analyzeItemAttributes(array $item, string $categoryId): array
    {
        $categoryAttrs = $this->categoryService->getCategoryAttributes($categoryId);
        $itemAttrIds = array_column($item['attributes'] ?? [], 'id');
        $allAttrs = $categoryAttrs['attributes'] ?? [];

        return [
            'missingRequired' => $this->countMissingRequiredAttrs($allAttrs, $itemAttrIds),
            'missingOptional' => $this->countMissingOptionalAttrs($allAttrs, $itemAttrIds),
        ];
    }

    /**
     * Conta atributos OBRIGATÓRIOS faltantes
     */
    private function countMissingRequiredAttrs(array $allAttrs, array $itemAttrIds): int
    {
        $missing = 0;
        foreach ($allAttrs as $attr) {
            $isRequired = !empty($attr['tags']['required']) || !empty($attr['tags']['catalog_required']);
            if ($isRequired && !in_array($attr['id'], $itemAttrIds)) {
                $missing++;
            }
        }
        return $missing;
    }

    /**
     * Conta atributos opcionais visíveis faltantes
     */
    private function countMissingOptionalAttrs(array $allAttrs, array $itemAttrIds): int
    {
        $missing = 0;
        foreach ($allAttrs as $attr) {
            $isOptionalVisible = empty($attr['tags']['required']) && empty($attr['tags']['hidden']);
            if ($isOptionalVisible && !in_array($attr['id'], $itemAttrIds)) {
                $missing++;
            }
        }
        return $missing;
    }

    /**
     * Avalia estatísticas de atributos e gera problemas/oportunidades
     */
    private function evaluateAttributeStats(array $stats, int $total): array
    {
        $problems = [];
        $opportunities = [];

        if ($stats['missingRequired'] > 0) {
            $problems[] = [
                'severity' => 'critical',
                'category' => 'attributes',
                'issue' => "{$stats['incompleteItems']} anúncios com atributos OBRIGATÓRIOS faltando",
                'impact' => -25,
                'affected_items' => $stats['incompleteItems'],
                'solution' => 'Preencher TODOS os atributos obrigatórios imediatamente'
            ];
        }

        if ($stats['totalMissingOptional'] > $total * 5) {
            $opportunities[] = [
                'category' => 'attributes',
                'opportunity' => "~{$stats['totalMissingOptional']} atributos opcionais podem ser preenchidos",
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

    private const LOW_LISTING_TYPES = ['bronze', 'free'];

    /**
     * Analisar Visibilidade
     */
    private function analyzeVisibility(array $items): array
    {
        [$paused, $lowListing] = $this->countVisibilityIssues($items);

        $problems = [];
        $opportunities = [];

        if ($paused > 0) {
            $problems[] = [
                'severity' => 'high',
                'category' => 'visibility',
                'issue' => "{$paused} anúncios pausados",
                'impact' => -15,
                'affected_items' => $paused,
                'solution' => 'Reativar anúncios pausados após otimização'
            ];
        }

        if ($lowListing > 0) {
            $opportunities[] = [
                'category' => 'visibility',
                'opportunity' => "Upgrade de tipo de anúncio em {$lowListing} itens",
                'potential' => 20,
                'strategy' => 'Clássico/Premium têm muito mais exposição que Grátis'
            ];
        }

        return ['problems' => $problems, 'opportunities' => $opportunities];
    }

    /**
     * Conta itens pausados e com listing type baixo
     * @return array{0: int, 1: int} [paused, lowListing]
     */
    private function countVisibilityIssues(array $items): array
    {
        $paused = 0;
        $lowListing = 0;

        foreach ($items as $item) {
            $status = $item['status'] ?? '';
            $listingType = $item['listing_type_id'] ?? '';

            if ($status === 'paused') {
                $paused++;
            }
            if (in_array($listingType, self::LOW_LISTING_TYPES)) {
                $lowListing++;
            }
        }

        return [$paused, $lowListing];
    }

    /**
     * Gerar Ações Prioritárias
     */
    private function generatePriorityActions(array $problems, array $opportunities): array
    {
        $actions = [];
        $critical = array_filter($problems, fn($p) => $p['severity'] === 'critical');

        foreach (array_slice($critical, 0, 3) as $p) {
            $actions[] = [
                'priority' => 1,
                'type' => 'fix_problem',
                'action' => $p['solution'],
                'category' => $p['category'],
                'impact' => 'Alto',
                'affected' => $p['affected_items'] ?? 0
            ];
        }
        foreach (array_slice($opportunities, 0, 2) as $o) {
            $actions[] = [
                'priority' => 2,
                'type' => 'opportunity',
                'action' => $o['strategy'],
                'category' => $o['category'],
                'impact' => 'Médio-Alto',
                'potential' => '+' . $o['potential'] . '%'
            ];
        }

        return $actions;
    }

    /**
     * Gerar Resumo do Diagnóstico
     */
    private function generateDiagnosisSummary(array $diagnosis): string
    {
        $score = $diagnosis['health_score'];
        $n = count($diagnosis['problems']);

        return match (true) {
            $score < 30 => "🔴 CONTA CRÍTICA: Score {$score}/100. {$n} problemas graves. Otimização urgente necessária.",
            $score < 60 => "🟡 CONTA COM PROBLEMAS: Score {$score}/100. {$n} issues. Potencial de melhoria com SEO.",
            default => "🟢 CONTA SAUDÁVEL: Score {$score}/100. Foco em otimizações finas.",
        };
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
}
