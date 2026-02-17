<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\LLMService;
use App\Services\AI\Core\RetryService;
use App\Services\CacheService;
use App\Services\LogService;

class CompetitorAnalysisService
{
    private LLMService $ai;
    private RetryService $retryService;
    private CacheService $cache;
    private LogService $logger;
    private ?MercadoLivreClient $mlClient = null;
    private ?int $accountId = null;

    public function __construct(?int $accountId = null)
    {
        $this->ai = new LLMService();
        $this->retryService = new RetryService();
        $this->cache = new CacheService();
        $this->logger = new LogService();
        $this->accountId = $accountId;
        $this->mlClient = new MercadoLivreClient($accountId);
    }

    /**
     * Analisa concorrência em uma categoria/marca com IA
     */
    public function analyzeCompetition(string $categoryId, string $brand, ?int $accountId = null): array
    {
        if ($accountId !== null && $accountId !== $this->accountId) {
            $this->accountId = $accountId;
            $this->mlClient = new MercadoLivreClient($accountId);
        }

        $cacheKey = "competition_analysis_{$categoryId}_{$brand}_" . md5($this->accountId ?? '');
        $cached = $this->cache->get($cacheKey, 'competition');
        if ($cached) {
            return $cached;
        }

        $snapshot = $this->fetchMarketSnapshot($categoryId, $brand);

        // Se não conseguimos dados reais, evita retornar mocks
        if (empty($snapshot['sellers'])) {
            $empty = [
                'total_sellers' => 0,
                'sellers' => [],
                'market_avg_price' => 0,
                'market_min_price' => 0,
                'market_max_price' => 0,
                'competition_level' => 'unknown',
                'market_share_top3' => 0,
            ];
            $this->cache->set($cacheKey, $empty, 'competition', 3600);
            return $empty;
        }

        $competitionData = [
            'total_sellers' => count($snapshot['sellers']),
            'sellers' => array_values($snapshot['sellers']),
            'market_avg_price' => $snapshot['stats']['avg_price'],
            'market_min_price' => $snapshot['stats']['min_price'],
            'market_max_price' => $snapshot['stats']['max_price'],
            'competition_level' => $snapshot['stats']['competition_level'],
            'market_share_top3' => $snapshot['stats']['market_share_top3'],
        ];

        $this->cache->set($cacheKey, $competitionData, 'competition', 10800); // 3 hours

        return $competitionData;
    }

    /**
     * Coleta concorrentes reais via API do Mercado Livre
     */
    private function fetchMarketSnapshot(string $categoryId, string $brand, int $limit = 100): array
    {
        if (!$this->mlClient) {
            return ['sellers' => [], 'stats' => []];
        }

        $results = [];
        $offset = 0;
        $pageSize = 50;

        while (count($results) < $limit) {
            $response = $this->mlClient->searchItems([
                'category' => $categoryId,
                'q' => $brand,
                'limit' => $pageSize,
                'offset' => $offset,
                'sort' => 'relevance'
            ]);

            $batch = $response['results'] ?? [];
            if (empty($batch)) {
                break;
            }

            $results = array_merge($results, $batch);
            if (count($batch) < $pageSize) {
                break;
            }
            $offset += $pageSize;
        }

        $sellers = [];
        $prices = [];
        $totalResults = $response['paging']['total'] ?? count($results);

        foreach ($results as $item) {
            $price = (float)($item['price'] ?? 0);
            if ($price > 0) {
                $prices[] = $price;
            }

            $sellerId = (string)($item['seller']['id'] ?? $item['seller_id'] ?? '');
            if ($sellerId === '') {
                continue;
            }

            if (!isset($sellers[$sellerId])) {
                $sellers[$sellerId] = [
                    'seller_id' => $sellerId,
                    'nickname' => $item['seller']['nickname'] ?? "Vendedor {$sellerId}",
                    'items' => 0,
                    'total_sales' => 0,
                    'avg_price' => 0,
                    'min_price' => $price ?: 0,
                    'max_price' => $price ?: 0,
                ];
            }

            $sellers[$sellerId]['items']++;
            $sellers[$sellerId]['total_sales'] += (int)($item['sold_quantity'] ?? 0);
            if ($price > 0) {
                $sellers[$sellerId]['avg_price'] = $sellers[$sellerId]['avg_price'] > 0
                    ? ($sellers[$sellerId]['avg_price'] + $price) / 2
                    : $price;
                $sellers[$sellerId]['min_price'] = min($sellers[$sellerId]['min_price'], $price);
                $sellers[$sellerId]['max_price'] = max($sellers[$sellerId]['max_price'], $price);
            }
        }

        // Ordenar sellers por vendas
        usort($sellers, fn($a, $b) => $b['total_sales'] <=> $a['total_sales']);

        $stats = [
            'min_price' => !empty($prices) ? min($prices) : 0,
            'max_price' => !empty($prices) ? max($prices) : 0,
            'avg_price' => !empty($prices) ? round(array_sum($prices) / count($prices), 2) : 0,
            'competition_index' => $this->calculateCompetitionIndex(count($sellers), $totalResults),
            'competition_level' => $this->mapCompetitionLevel($this->calculateCompetitionIndex(count($sellers), $totalResults)),
            'market_share_top3' => $this->calculateTop3MarketShare($sellers),
        ];

        return ['sellers' => $sellers, 'stats' => $stats];
    }

    private function calculateCompetitionIndex(int $sellerCount, int $totalResults): int
    {
        $normalizedSellers = min(100, $sellerCount * 4); // 25 sellers ≈ 100
        $normalizedResults = $totalResults > 0 ? min(100, ($totalResults / 200) * 100) : 0; // 200+ resultados = 100

        return (int)round(max($normalizedSellers, $normalizedResults));
    }

    private function mapCompetitionLevel(int $index): string
    {
        if ($index >= 80) return 'very_high';
        if ($index >= 60) return 'high';
        if ($index >= 40) return 'medium';
        if ($index >= 20) return 'low';
        return 'very_low';
    }

    /**
     * Calculate top 3 market share
     */
    private function calculateTop3MarketShare(array $sellers): float
    {
        $totalSales = array_sum(array_column($sellers, 'total_sales'));
        if ($totalSales === 0) return 0;

        $top3Sales = 0;
        $top3 = array_slice($sellers, 0, 3);
        foreach ($top3 as $seller) {
            $top3Sales += $seller['total_sales'];
        }

        return round(($top3Sales / $totalSales) * 100, 2);
    }

    /**
     * Detecta oportunidades de mercado com IA
     */
    public function detectOpportunities(string $categoryId, string $brand, ?int $accountId = null): array
    {
        $cacheKey = "market_opportunities_{$categoryId}_{$brand}_" . md5($accountId ?? '');
        $cached = $this->cache->get($cacheKey, 'opportunities');
        if ($cached) {
            return $cached;
        }

        try {
            // Use AI to detect market opportunities
            $competition = $this->analyzeCompetition($categoryId, $brand, $accountId);

            $prompt = "Com base na análise de concorrência para a marca {$brand} na categoria {$categoryId}:
- Número total de vendedores: {$competition['total_sellers']}
- Nível de concorrência: {$competition['competition_level']}
- Preço médio de mercado: R$ {$competition['market_avg_price']}
- Participação de mercado dos top 3: {$competition['market_share_top3']}%

Identifique oportunidades de mercado, nichos não explorados, gaps de produto e estratégias competitivas. Retorne um JSON com as oportunidades identificadas e recomendações estratégicas.";

            $result = $this->retryService->execute(
                fn() => $this->ai->generate($prompt, "Você é um especialista em estratégia de mercado e identificação de oportunidades para marketplaces. Identifique gaps e oportunidades reais de negócio.", 'advanced'),
                'detect_opportunities',
                ['timeout', 'rate limit', 'service unavailable']
            );

            if ($result['success']) {
                $aiResponse = $result['content'];

                // Try to extract JSON from response
                $jsonStart = strpos($aiResponse, '{');
                $jsonEnd = strrpos($aiResponse, '}');

                if ($jsonStart !== false && $jsonEnd !== false) {
                    $jsonStr = substr($aiResponse, $jsonStart, $jsonEnd - $jsonStart + 1);
                    $parsed = json_decode($jsonStr, true);

                    if ($parsed && is_array($parsed)) {
                        $opportunitiesData = [
                            'opportunities' => $parsed['opportunities'] ?? [],
                            'recommendations' => $parsed['recommendations'] ?? [],
                            'market_insights' => $parsed['market_insights'] ?? [],
                            'competition' => $competition,
                        ];

                        // Cache the result
                        $this->cache->set($cacheKey, $opportunitiesData, 'opportunities', 14400); // 4 hours

                        return $opportunitiesData;
                    }
                }
            }
        } catch (\Exception $e) {
            $this->logger->error('Opportunity detection failed', [
                'error' => $e->getMessage(),
                'category_id' => $categoryId,
                'brand' => $brand
            ]);
        }

        // Fallback: return basic opportunity analysis
        $competition = $this->analyzeCompetition($categoryId, $brand, $accountId);

        $opportunities = [];

        // Oportunidade: Poucos vendedores (baixa concorrência)
        if ($competition['total_sellers'] < 10) {
            $opportunities[] = [
                'type' => 'low_competition',
                'message' => "Baixa concorrência detectada: apenas {$competition['total_sellers']} vendedores",
                'severity' => 'success',
                'priority' => 'high',
            ];
        }

        // Oportunidade: Preço médio alto (margem potencial)
        $marketAvg = $competition['market_avg_price'];
        if ($marketAvg > 800) {
            $opportunities[] = [
                'type' => 'high_price_market',
                'message' => "Mercado com preço médio alto: R$ " . number_format($marketAvg, 2, ',', '.'),
                'severity' => 'info',
                'priority' => 'medium',
            ];
        }

        // Oportunidade: Vendedor dominante (possível nicho)
        if (count($competition['sellers']) > 0) {
            $topSeller = $competition['sellers'][0] ?? null;
            if ($topSeller) {
                $topSellerShare = $competition['market_share_top3'];

                if ($topSellerShare > 60) {
                    $opportunities[] = [
                        'type' => 'dominant_seller',
                        'message' => "Vendedor dominante com {$topSellerShare}% das vendas - oportunidade de diferenciação",
                        'severity' => 'warning',
                        'priority' => 'medium',
                    ];
                }
            }
        }

        $opportunitiesData = [
            'opportunities' => $opportunities,
            'recommendations' => $this->generateBasicRecommendations($competition),
            'market_insights' => $this->generateBasicInsights($competition),
            'competition' => $competition,
        ];

        // Cache the result
        $this->cache->set($cacheKey, $opportunitiesData, 'opportunities', 14400); // 4 hours

        return $opportunitiesData;
    }

    /**
     * Generate basic recommendations
     */
    private function generateBasicRecommendations(array $competition): array
    {
        $recommendations = [];

        if ($competition['competition_level'] === 'low') {
            $recommendations[] = [
                'type' => 'market_entry',
                'message' => 'Boa oportunidade para entrada no mercado devido à baixa concorrência',
                'action' => 'Considerar lançamento de produto diferenciado'
            ];
        }

        if ($competition['market_avg_price'] > 1000) {
            $recommendations[] = [
                'type' => 'premium_positioning',
                'message' => 'Mercado permite posicionamento premium',
                'action' => 'Desenvolver produto com diferenciais de valor'
            ];
        }

        return $recommendations;
    }

    /**
     * Generate basic market insights
     */
    private function generateBasicInsights(array $competition): array
    {
        return [
            'market_health' => $this->assessMarketHealth($competition),
            'entry_barrier' => $this->assessEntryBarrier($competition),
            'growth_potential' => $this->assessGrowthPotential($competition),
            'risk_factors' => $this->identifyRiskFactors($competition)
        ];
    }

    /**
     * Assess market health
     */
    private function assessMarketHealth(array $competition): string
    {
        if ($competition['competition_level'] === 'low' && $competition['market_avg_price'] > 500) {
            return 'healthy_growth';
        } elseif ($competition['competition_level'] === 'very_high') {
            return 'saturated';
        } else {
            return 'stable';
        }
    }

    /**
     * Assess entry barrier
     */
    private function assessEntryBarrier(array $competition): string
    {
        if ($competition['competition_level'] === 'very_high') {
            return 'high';
        } elseif ($competition['competition_level'] === 'low') {
            return 'low';
        } else {
            return 'medium';
        }
    }

    /**
     * Assess growth potential
     */
    private function assessGrowthPotential(array $competition): string
    {
        if ($competition['competition_level'] === 'low' && $competition['market_avg_price'] > 300) {
            return 'high';
        } elseif ($competition['competition_level'] === 'very_high') {
            return 'low';
        } else {
            return 'medium';
        }
    }

    /**
     * Identify risk factors
     */
    private function identifyRiskFactors(array $competition): array
    {
        $risks = [];

        if ($competition['competition_level'] === 'very_high') {
            $risks[] = 'alta_concorrencia';
        }

        if ($competition['market_share_top3'] > 70) {
            $risks[] = 'dominancia_de_mercado';
        }

        return $risks;
    }
}
