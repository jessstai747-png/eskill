<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\BrandAnalyzerService;
use App\Helpers\SessionHelper;

/**
 * Controller de Análise de Marca
 * 
 * Endpoints para análise de marcas no Mercado Livre,
 * especialmente focado na marca AWA (motos e acessórios).
 */
class BrandAnalyzerController extends BaseController
{
    private BrandAnalyzerService $brandAnalyzer;

    public function __construct()
    {
        parent::__construct();
        $accountId = SessionHelper::getActiveAccountId();
        $this->brandAnalyzer = new BrandAnalyzerService($accountId);
    }

    /**
     * Render the Brand Analysis dashboard view
     * GET /dashboard/brand-analysis
     */
    public function index(): void
    {
        if (!isset($_SESSION['user_id'])) {
            header('Location: /login');
            exit;
        }

        $pageTitle = 'Análise de Marca';

        ob_start();
        require __DIR__ . '/../Views/brand_analysis/index.php';
        $content = ob_get_clean();

        require __DIR__ . '/../Views/layouts/modern/app.php';
    }

    /**
     * Análise completa da marca AWA
     * GET /api/brand/awa/analyze
     */
    public function analyzeAwa(): void
    {
        header('Content-Type: application/json');

        try {
            $options = [
                'categories' => $this->parseCategories($this->request->get('categories')),
                'max_results' => $this->request->getInt('max_results', 500),
                'include_details' => $this->request->getBool('include_details', true),
            ];

            $results = $this->brandAnalyzer->analyzeAwaBrand($options);

            echo json_encode([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Análise rápida da marca AWA
     * GET /api/brand/awa/quick
     */
    public function quickAnalysis(): void
    {
        header('Content-Type: application/json');

        try {
            $options = [
                'category' => $this->request->get('category', 'MLB214858'),
                'max_results' => $this->request->getInt('max_results', 100),
            ];

            $results = $this->brandAnalyzer->quickAnalysis($options);

            echo json_encode([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtém lacunas de dados (gaps)
     * GET /api/brand/awa/gaps
     */
    public function getGaps(): void
    {
        header('Content-Type: application/json');

        try {
            $options = [
                'categories' => $this->parseCategories($this->request->get('categories')),
                'max_results' => $this->request->getInt('max_results', 500),
                'include_details' => false, // Não precisa de todos os detalhes
            ];

            $results = $this->brandAnalyzer->analyzeAwaBrand($options);

            echo json_encode([
                'success' => true,
                'data' => [
                    'total_gaps' => count($results['gaps_detected']),
                    'gaps' => $results['gaps_detected'],
                    'gap_types' => $this->categorizeGaps($results['gaps_detected']),
                ],
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtém inconsistências
     * GET /api/brand/awa/inconsistencies
     */
    public function getInconsistencies(): void
    {
        header('Content-Type: application/json');

        try {
            $options = [
                'categories' => $this->parseCategories($this->request->get('categories')),
                'max_results' => $this->request->getInt('max_results', 500),
                'include_details' => false,
            ];

            $results = $this->brandAnalyzer->analyzeAwaBrand($options);

            // Detectar padrões de inconsistência
            $patterns = $this->brandAnalyzer->detectInconsistencyPatterns($results);

            echo json_encode([
                'success' => true,
                'data' => [
                    'total_inconsistencies' => count($results['inconsistencies']),
                    'inconsistencies' => $results['inconsistencies'],
                    'patterns' => $patterns,
                ],
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtém análise de vendedores
     * GET /api/brand/awa/sellers
     */
    public function getSellers(): void
    {
        header('Content-Type: application/json');

        try {
            $options = [
                'categories' => $this->parseCategories($this->request->get('categories')),
                'max_results' => $this->request->getInt('max_results', 500),
                'include_details' => true,
            ];

            $results = $this->brandAnalyzer->analyzeAwaBrand($options);

            // Ordenar vendedores por quantidade de itens
            $sellers = $results['sellers'];
            usort($sellers, fn($a, $b) => ($b['items_count'] ?? 0) - ($a['items_count'] ?? 0));

            echo json_encode([
                'success' => true,
                'data' => [
                    'total_sellers' => count($sellers),
                    'sellers' => $sellers,
                ],
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtém resumo executivo
     * GET /api/brand/awa/summary
     */
    public function getSummary(): void
    {
        header('Content-Type: application/json');

        try {
            $options = [
                'categories' => $this->parseCategories($this->request->get('categories')),
                'max_results' => $this->request->getInt('max_results', 500),
                'include_details' => false,
            ];

            $results = $this->brandAnalyzer->analyzeAwaBrand($options);
            $exportedSummary = $this->brandAnalyzer->exportReport($results, 'summary');

            echo json_encode([
                'success' => true,
                'data' => $exportedSummary['report'],
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obtém histórico de análises
     * GET /api/brand/awa/history
     */
    public function getHistory(): void
    {
        header('Content-Type: application/json');

        try {
            $limit = $this->request->getInt('limit', 30);
            $history = $this->brandAnalyzer->getAnalysisHistory('AWA', $limit);

            echo json_encode([
                'success' => true,
                'data' => [
                    'total' => count($history),
                    'history' => $history,
                ],
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Exporta relatório em CSV
     * GET /api/brand/awa/export/csv
     */
    public function exportCSV(): void
    {
        try {
            $options = [
                'categories' => $this->parseCategories($this->request->get('categories')),
                'max_results' => $this->request->getInt('max_results', 1000),
                'include_details' => true,
            ];

            $results = $this->brandAnalyzer->analyzeAwaBrand($options);
            $csvExport = $this->brandAnalyzer->exportReport($results, 'csv');

            $filename = $csvExport['filename'];
            $data = $csvExport['data'];

            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            $output = fopen('php://output', 'w');

            // BOM para UTF-8 no Excel
            fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

            foreach ($data as $row) {
                fputcsv($output, $row, ';');
            }

            fclose($output);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Exporta relatório em JSON
     * GET /api/brand/awa/export/json
     */
    public function exportJSON(): void
    {
        try {
            $options = [
                'categories' => $this->parseCategories($this->request->get('categories')),
                'max_results' => $this->request->getInt('max_results', 1000),
                'include_details' => true,
            ];

            $results = $this->brandAnalyzer->analyzeAwaBrand($options);

            $filename = 'awa_brand_analysis_' . date('Y-m-d_H-i-s') . '.json';

            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="' . $filename . '"');

            echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Análise de preços da marca AWA
     * GET /api/brand/awa/pricing
     */
    public function getPricing(): void
    {
        header('Content-Type: application/json');

        try {
            $options = [
                'categories' => $this->parseCategories($this->request->get('categories')),
                'max_results' => $this->request->getInt('max_results', 500),
                'include_details' => true,
            ];

            $results = $this->brandAnalyzer->analyzeAwaBrand($options);

            echo json_encode([
                'success' => true,
                'data' => [
                    'analysis' => $results['price_analysis'],
                    'total_items' => $results['total_listings'],
                ],
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Análise de frete da marca AWA
     * GET /api/brand/awa/shipping
     */
    public function getShipping(): void
    {
        header('Content-Type: application/json');

        try {
            $options = [
                'categories' => $this->parseCategories($this->request->get('categories')),
                'max_results' => $this->request->getInt('max_results', 500),
                'include_details' => true,
            ];

            $results = $this->brandAnalyzer->analyzeAwaBrand($options);

            echo json_encode([
                'success' => true,
                'data' => $results['shipping_analysis'],
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Lista itens da marca AWA com filtros
     * GET /api/brand/awa/items
     */
    public function listItems(): void
    {
        header('Content-Type: application/json');

        try {
            $options = [
                'categories' => $this->parseCategories($this->request->get('categories')),
                'max_results' => $this->request->getInt('max_results', 500),
                'include_details' => true,
            ];

            $results = $this->brandAnalyzer->analyzeAwaBrand($options);

            $items = $results['items'];

            // Aplicar filtros
            $filterHasBrand = $this->request->get('has_brand');
            $filterCondition = $this->request->get('condition');
            $filterFreeShipping = $this->request->get('free_shipping');
            $filterMinPrice = $this->request->get('min_price');
            $filterMaxPrice = $this->request->get('max_price');

            if ($filterHasBrand !== null) {
                $hasBrand = $filterHasBrand === 'true' || $filterHasBrand === '1';
                $items = array_filter(
                    $items,
                    fn($item) => ($item['brand_analysis']['has_brand'] ?? false) === $hasBrand
                );
            }

            if ($filterCondition !== null) {
                $items = array_filter(
                    $items,
                    fn($item) => ($item['condition'] ?? '') === $filterCondition
                );
            }

            if ($filterFreeShipping !== null) {
                $freeShipping = $filterFreeShipping === 'true' || $filterFreeShipping === '1';
                $items = array_filter(
                    $items,
                    fn($item) => ($item['shipping']['free_shipping'] ?? false) === $freeShipping
                );
            }

            if ($filterMinPrice !== null) {
                $minPrice = (float) $filterMinPrice;
                $items = array_filter(
                    $items,
                    fn($item) => ($item['price'] ?? 0) >= $minPrice
                );
            }

            if ($filterMaxPrice !== null) {
                $maxPrice = (float) $filterMaxPrice;
                $items = array_filter(
                    $items,
                    fn($item) => ($item['price'] ?? 0) <= $maxPrice
                );
            }

            // Ordenação
            $sortBy = $this->request->get('sort', 'price');
            $sortOrder = $this->request->get('order', 'asc');

            usort($items, function ($a, $b) use ($sortBy, $sortOrder) {
                $valueA = $a[$sortBy] ?? 0;
                $valueB = $b[$sortBy] ?? 0;

                $comparison = $valueA <=> $valueB;
                return $sortOrder === 'desc' ? -$comparison : $comparison;
            });

            // Paginação
            $page = max(1, $this->request->getInt('page', 1));
            $perPage = min(100, max(1, $this->request->getInt('per_page', 50)));
            $offset = ($page - 1) * $perPage;

            $totalItems = count($items);
            $items = array_slice(array_values($items), $offset, $perPage);

            echo json_encode([
                'success' => true,
                'data' => [
                    'items' => $items,
                    'pagination' => [
                        'total' => $totalItems,
                        'page' => $page,
                        'per_page' => $perPage,
                        'total_pages' => ceil($totalItems / $perPage),
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Dashboard de análise da marca AWA
     * GET /api/brand/awa/dashboard
     */
    public function dashboard(): void
    {
        header('Content-Type: application/json');

        try {
            // Análise rápida para dashboard
            $quickAnalysis = $this->brandAnalyzer->quickAnalysis([
                'max_results' => 200,
            ]);

            // Histórico recente
            $history = $this->brandAnalyzer->getAnalysisHistory('AWA', 7);

            // Calcular tendência
            $trend = $this->calculateTrend($history);

            echo json_encode([
                'success' => true,
                'data' => [
                    'current' => [
                        'total_listings' => $quickAnalysis['total'],
                        'with_brand' => $quickAnalysis['with_brand'],
                        'without_brand' => $quickAnalysis['without_brand'],
                        'consistency_score' => $quickAnalysis['consistency_score'],
                    ],
                    'trend' => $trend,
                    'history' => array_slice($history, 0, 7),
                    'last_update' => $quickAnalysis['analysis_date'],
                ],
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Parse de categorias
     */
    private function parseCategories(?string $categories): ?array
    {
        if ($categories === null || $categories === '') {
            return null; // Usar categorias padrão
        }

        return array_map('trim', explode(',', $categories));
    }

    /**
     * Categoriza tipos de gaps
     */
    private function categorizeGaps(array $gaps): array
    {
        $categories = [
            'missing_brand' => 0,
            'brand_in_title_not_attribute' => 0,
            'other' => 0,
        ];

        foreach ($gaps as $gap) {
            $type = $gap['type'] ?? 'other';
            if (isset($categories[$type])) {
                $categories[$type]++;
            } else {
                $categories['other']++;
            }
        }

        return $categories;
    }

    /**
     * Calcula tendência com base no histórico
     */
    private function calculateTrend(array $history): array
    {
        if (count($history) < 2) {
            return [
                'direction' => 'stable',
                'change' => 0,
            ];
        }

        $latest = $history[0];
        $previous = $history[1];

        $latestScore = $latest['consistency_score'] ?? 0;
        $previousScore = $previous['consistency_score'] ?? 0;

        $change = $latestScore - $previousScore;

        $direction = 'stable';
        if ($change > 1) {
            $direction = 'up';
        } elseif ($change < -1) {
            $direction = 'down';
        }

        return [
            'direction' => $direction,
            'change' => round($change, 2),
            'previous_score' => $previousScore,
            'current_score' => $latestScore,
        ];
    }

    /**
     * Compara AWA com marcas concorrentes
     * GET /api/brand/awa/compare
     */
    public function compareCompetitors(): void
    {
        header('Content-Type: application/json');

        try {
            $categoryId = $this->request->get('category', 'MLB214858');
            $competitors = $this->request->get('competitors') !== null
                ? array_map('trim', explode(',', $this->request->get('competitors')))
                : [];

            $results = $this->brandAnalyzer->compareWithCompetitors($categoryId, $competitors);

            echo json_encode([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Análise de tendências
     * GET /api/brand/awa/trends
     */
    public function getTrends(): void
    {
        header('Content-Type: application/json');

        try {
            $days = $this->request->getInt('days', 30);
            $results = $this->brandAnalyzer->analyzeTrends($days);

            echo json_encode([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Gera alertas baseados na análise
     * GET /api/brand/awa/alerts
     */
    public function getAlerts(): void
    {
        header('Content-Type: application/json');

        try {
            $options = [
                'categories' => $this->parseCategories($this->request->get('categories')),
                'max_results' => $this->request->getInt('max_results', 300),
                'include_details' => true,
            ];

            $results = $this->brandAnalyzer->analyzeAwaBrand($options);
            $alerts = $this->brandAnalyzer->generateAlerts($results);

            echo json_encode([
                'success' => true,
                'data' => [
                    'total_alerts' => count($alerts),
                    'critical' => count(array_filter($alerts, fn($a) => $a['type'] === 'critical')),
                    'warning' => count(array_filter($alerts, fn($a) => $a['type'] === 'warning')),
                    'info' => count(array_filter($alerts, fn($a) => $a['type'] === 'info')),
                    'alerts' => $alerts,
                ],
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Produtos mais vendidos da marca AWA
     * GET /api/brand/awa/top-products
     */
    public function getTopProducts(): void
    {
        header('Content-Type: application/json');

        try {
            $categoryId = $this->request->get('category', 'MLB214858');
            $limit = min(50, max(5, $this->request->getInt('limit', 20)));

            $results = $this->brandAnalyzer->getTopSellingProducts($categoryId, $limit);

            echo json_encode([
                'success' => true,
                'data' => [
                    'category' => $categoryId,
                    'total' => count($results),
                    'products' => $results,
                ],
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Análise de oportunidades de mercado
     * GET /api/brand/awa/opportunities
     */
    public function getOpportunities(): void
    {
        header('Content-Type: application/json');

        try {
            $categoryId = $this->request->get('category', 'MLB214858');
            $results = $this->brandAnalyzer->analyzeOpportunities($categoryId);

            echo json_encode([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Estatísticas detalhadas por vendedor
     * GET /api/brand/awa/seller-stats
     */
    public function getSellerStats(): void
    {
        header('Content-Type: application/json');

        try {
            $results = $this->brandAnalyzer->getSellerStatistics();

            echo json_encode([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Detecta padrões de inconsistência
     * GET /api/brand/awa/patterns
     */
    public function getPatterns(): void
    {
        header('Content-Type: application/json');

        try {
            $options = [
                'categories' => $this->parseCategories($this->request->get('categories')),
                'max_results' => $this->request->getInt('max_results', 500),
                'include_details' => true,
            ];

            $results = $this->brandAnalyzer->analyzeAwaBrand($options);
            $patterns = $this->brandAnalyzer->detectInconsistencyPatterns($results);

            echo json_encode([
                'success' => true,
                'data' => [
                    'total_patterns' => count($patterns),
                    'patterns' => $patterns,
                ],
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Relatório completo consolidado
     * GET /api/brand/awa/report
     */
    public function getFullReport(): void
    {
        header('Content-Type: application/json');

        try {
            $options = [
                'categories' => $this->parseCategories($this->request->get('categories')),
                'max_results' => $this->request->getInt('max_results', 500),
                'include_details' => true,
            ];

            // Executar todas as análises
            $analysis = $this->brandAnalyzer->analyzeAwaBrand($options);
            $alerts = $this->brandAnalyzer->generateAlerts($analysis);
            $patterns = $this->brandAnalyzer->detectInconsistencyPatterns($analysis);
            $trends = $this->brandAnalyzer->analyzeTrends(30);
            $opportunities = $this->brandAnalyzer->analyzeOpportunities($this->request->get('category', 'MLB214858'));

            $report = [
                'generated_at' => date('Y-m-d H:i:s'),
                'brand' => 'AWA',
                'overview' => [
                    'total_listings' => $analysis['total_listings'],
                    'consistency_score' => $analysis['brand_consistency_score'],
                    'health_status' => $analysis['summary']['health_status'] ?? null,
                ],
                'brand_analysis' => [
                    'with_brand' => $analysis['listings_with_brand'],
                    'without_brand' => $analysis['listings_without_brand'],
                    'wrong_brand' => $analysis['listings_with_wrong_brand'],
                ],
                'gaps' => [
                    'total' => count($analysis['gaps_detected']),
                    'by_type' => $this->categorizeGaps($analysis['gaps_detected']),
                ],
                'inconsistencies' => [
                    'total' => count($analysis['inconsistencies']),
                    'items' => $analysis['inconsistencies'],
                ],
                'patterns' => $patterns,
                'alerts' => $alerts,
                'pricing' => $analysis['price_analysis'],
                'shipping' => $analysis['shipping_analysis'],
                'sellers' => [
                    'total' => count($analysis['sellers']),
                    'top' => $analysis['summary']['top_sellers'] ?? [],
                ],
                'trends' => $trends,
                'opportunities' => $opportunities,
                'recommendations' => $analysis['summary']['recommendations'] ?? [],
            ];

            echo json_encode([
                'success' => true,
                'data' => $report,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Exportar lista de itens para correção
     * GET /api/brand/awa/export/fix-list
     */
    public function exportFixList(): void
    {
        header('Content-Type: application/json');

        try {
            $options = [
                'categories' => $this->parseCategories($this->request->get('categories')),
                'max_results' => $this->request->getInt('max_results', 500),
                'include_details' => true,
            ];

            $results = $this->brandAnalyzer->analyzeAwaBrand($options);

            // Combinar gaps e inconsistências
            $fixList = [];

            foreach ($results['gaps_detected'] as $gap) {
                $fixList[] = [
                    'item_id' => $gap['item_id'],
                    'title' => $gap['title'] ?? '',
                    'issue_type' => $gap['type'],
                    'current_brand' => null,
                    'suggested_brand' => 'AWA',
                    'action' => 'add_brand',
                    'url' => "https://www.mercadolibre.com.br/p/{$gap['item_id']}",
                ];
            }

            foreach ($results['inconsistencies'] as $inc) {
                $fixList[] = [
                    'item_id' => $inc['item_id'],
                    'title' => $inc['title'] ?? '',
                    'issue_type' => $inc['type'],
                    'current_brand' => $inc['current_value'] ?? null,
                    'suggested_brand' => 'AWA',
                    'action' => 'fix_brand',
                    'url' => "https://www.mercadolibre.com.br/p/{$inc['item_id']}",
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => [
                    'total_items' => count($fixList),
                    'gaps_count' => count($results['gaps_detected']),
                    'inconsistencies_count' => count($results['inconsistencies']),
                    'items' => $fixList,
                ],
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Métricas resumidas para widget/dashboard externo
     * GET /api/brand/awa/metrics
     */
    public function getMetrics(): void
    {
        header('Content-Type: application/json');

        try {
            $quick = $this->brandAnalyzer->quickAnalysis([
                'max_results' => 100,
            ]);

            $metrics = [
                'timestamp' => date('Y-m-d H:i:s'),
                'brand' => 'AWA',
                'total_listings' => $quick['total'] ?? 0,
                'brand_coverage' => round(($quick['with_brand'] ?? 0) / max(1, $quick['total'] ?? 1) * 100, 1),
                'consistency_score' => $quick['consistency_score'] ?? 0,
                'issues' => [
                    'gaps' => $quick['gaps_count'] ?? 0,
                    'inconsistencies' => $quick['inconsistencies_count'] ?? 0,
                ],
                'health' => $quick['health_status'] ?? 'unknown',
            ];

            echo json_encode([
                'success' => true,
                'data' => $metrics,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
