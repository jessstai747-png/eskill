<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PdfService;
use App\Services\OrderService;
use App\Database;
use PDO;

/**
 * Controller para geração de relatórios PDF
 */
class PdfController extends BaseController
{
    private PdfService $pdfService;

    public function __construct()
    {
        parent::__construct();
        $this->pdfService = new PdfService();
    }

    /**
     * Gera relatório de vendas em PDF
     * GET /api/pdf/sales
     */
    public function salesReport(): void
    {
        $period = $this->request->get('period') ?? 'month';
        $dateFrom = $this->request->get('date_from') ?? date('Y-m-01');
        $dateTo = $this->request->get('date_to') ?? date('Y-m-d');

        $data = $this->getSalesData($period, $dateFrom, $dateTo);

        $this->pdfService->generateSalesReport($data, $period);
    }

    /**
     * Gera relatório de análise de mercado em PDF
     * GET /api/pdf/market
     */
    public function marketAnalysis(): void
    {
        $categoryId = $this->request->get('category_id');
        $brand = $this->request->get('brand');

        if (!$categoryId) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Parâmetro category_id é obrigatório']);
            return;
        }

        $data = $this->getMarketData($categoryId, $brand);

        $this->pdfService->generateMarketAnalysis($data);
    }

    /**
     * Gera relatório de pedidos em PDF
     * GET /api/pdf/orders
     */
    public function ordersReport(): void
    {
        $accountId = $this->request->get('account_id');
        $status = $this->request->get('status');
        $dateFrom = $this->request->get('date_from') ?? date('Y-m-d', strtotime('-30 days'));
        $dateTo = $this->request->get('date_to') ?? date('Y-m-d');
        $limit = $this->request->getIntClamped('limit', 1, 1000, 100);
        $allowLocalCache = $this->request->get('allow_local_cache');

        $orderService = new OrderService($accountId ? (int)$accountId : null);

        $filters = [
            'date_from' => $dateFrom . 'T00:00:00.000-03:00',
            'date_to' => $dateTo . 'T23:59:59.000-03:00',
            'limit' => $limit,
        ];

        if ($status) {
            $filters['status'] = $status;
        }

        if ($allowLocalCache !== null) {
            $filters['allow_local_cache'] = $allowLocalCache;
        }

        // Obter pedidos
        if ($accountId) {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT ml_user_id FROM ml_accounts WHERE id = :id");
            $stmt->execute(['id' => $accountId]);
            $account = $stmt->fetch();

            if ($account) {
                $filters['seller_id'] = $account['ml_user_id'];
            }

            $ordersResponse = $orderService->listOrders($filters);
        } else {
            // Obter de todas as contas
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT id FROM ml_accounts WHERE status = 'active'");
            $stmt->execute();
            $accounts = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $allOrders = [];
            foreach ($accounts as $accId) {
                $service = new OrderService((int)$accId);
                $response = $service->listOrders($filters);
                $allOrders = array_merge($allOrders, $response['results'] ?? []);
            }

            usort($allOrders, function (array $a, array $b): int {
                return strtotime((string)($b['date_created'] ?? '')) <=> strtotime((string)($a['date_created'] ?? ''));
            });

            $ordersResponse = [
                'results' => array_slice($allOrders, 0, $limit),
            ];
        }

        $orders = $ordersResponse['results'] ?? [];

        // Calcular resumo
        $summary = $this->calculateOrdersSummary($orders);

        $this->pdfService->generateOrdersReport($orders, $summary);
    }

    /**
     * Gera dashboard executivo em PDF
     * GET /api/pdf/dashboard
     */
    public function executiveDashboard(): void
    {
        $period = $this->request->get('period') ?? 'Últimos 30 dias';
        $accountId = $this->request->get('account_id');

        $data = $this->getExecutiveDashboardData($accountId);
        $data['period'] = $period;

        $this->pdfService->generateExecutiveDashboard($data);
    }

    /**
     * Gera análise de anúncio em PDF
     * GET /api/pdf/listing/{itemId}
     */
    public function listingAnalysis(string $itemId): void
    {
        $accountId = $this->request->get('account_id');

        // Obter dados do anúncio
        $listing = $this->getListingData($itemId, $accountId);

        if (!$listing) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Anúncio não encontrado']);
            return;
        }

        // Calcular score SEO
        $seoScore = $this->calculateSeoScore($listing);

        $this->pdfService->generateListingAnalysis($listing, $seoScore);
    }

    /**
     * Obtém dados de vendas para o relatório
     */
    private function getSalesData(string $period, string $dateFrom, string $dateTo): array
    {
        $db = Database::getInstance();

        // Verificar se tabela de pedidos existe
        try {
            $stmt = $db->query("SELECT 1 FROM ml_orders LIMIT 1");
        } catch (\PDOException $e) {
            // Tabela não existe, retornar dados de exemplo
            return $this->getSampleSalesData($period);
        }

        // Total de vendas
        $stmt = $db->prepare("
            SELECT
                COUNT(*) as total_orders,
                COALESCE(SUM(total_amount), 0) as total_sales
            FROM ml_orders
            WHERE date_created >= :date_from
            AND date_created <= :date_to
            AND status != 'cancelled'
        ");
        $stmt->execute([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalOrders = (int)($totals['total_orders'] ?? 0);
        $totalSales = (float)($totals['total_sales'] ?? 0);
        $averageTicket = $totalOrders > 0 ? $totalSales / $totalOrders : 0;

        // Vendas por período
        $groupBy = match ($period) {
            'day' => "DATE_FORMAT(date_created, '%H:00')",
            'week' => "DATE_FORMAT(date_created, '%d/%m')",
            'month' => "DATE_FORMAT(date_created, '%d/%m')",
            'year' => "DATE_FORMAT(date_created, '%m/%Y')",
            default => "DATE_FORMAT(date_created, '%d/%m')"
        };

        $stmt = $db->prepare("
            SELECT
                {$groupBy} as period,
                COUNT(*) as orders,
                COALESCE(SUM(total_amount), 0) as value
            FROM ml_orders
            WHERE date_created >= :date_from
            AND date_created <= :date_to
            AND status != 'cancelled'
            GROUP BY {$groupBy}
            ORDER BY date_created
        ");
        $stmt->execute([
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
        ]);
        $salesByPeriod = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Top produtos (se tiver dados)
        $topProducts = $this->getTopProducts($dateFrom, $dateTo, 10);

        // Vendas por categoria (se tiver dados)
        $salesByCategory = $this->getSalesByCategory($dateFrom, $dateTo);

        return [
            'total_sales' => $totalSales,
            'total_orders' => $totalOrders,
            'average_ticket' => $averageTicket,
            'conversion_rate' => 0, // Precisaria de dados de visitas
            'date_from' => $dateFrom,
            'date_to' => $dateTo,
            'sales_by_period' => $salesByPeriod,
            'top_products' => $topProducts,
            'sales_by_category' => $salesByCategory,
        ];
    }

    /**
     * Obtém dados reais de vendas via SalesAnalyticsService
     */
    private function getSampleSalesData(string $period): array
    {
        try {
            $salesService = new \App\Services\SalesAnalyticsService($this->getAccountId());
            return $salesService->getSalesData($period);
        } catch (\Exception $e) {
            log_error('Erro ao buscar dados de vendas para PDF', [
                'period' => $period,
                'account_id' => $this->getAccountId(),
                'error' => $e->getMessage(),
            ]);

            // Fallback: estrutura vazia em caso de erro
            return [
                'total_sales' => 0.00,
                'total_orders' => 0,
                'average_ticket' => 0.00,
                'conversion_rate' => 0.0,
                'sales_by_period' => [],
                'top_products' => [],
                'sales_by_category' => [],
                'date_from' => date('Y-m-d', strtotime('-30 days')),
                'date_to' => date('Y-m-d'),
                'error' => 'Não foi possível carregar dados de vendas'
            ];
        }
    }

    /**
     * Obtém top produtos vendidos
     */
    private function getTopProducts(string $dateFrom, string $dateTo, int $limit = 10): array
    {
        $db = Database::getInstance();
        $limitSql = max(1, min(200, (int)$limit));

        try {
            // Tentar obter de tabela de itens vendidos se existir
            $stmt = $db->prepare("
                SELECT
                    oi.item_id,
                    oi.title,
                    SUM(oi.quantity) as quantity,
                    SUM(oi.quantity * oi.unit_price) as revenue
                FROM order_items oi
                JOIN ml_orders o ON (oi.order_id = o.id OR oi.order_id = o.ml_order_id)
                WHERE o.date_created >= :date_from
                AND o.date_created <= :date_to
                GROUP BY oi.item_id, oi.title
                ORDER BY quantity DESC
                LIMIT {$limitSql}
            ");
            $stmt->execute([
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Obtém vendas por categoria
     */
    private function getSalesByCategory(string $dateFrom, string $dateTo): array
    {
        $db = Database::getInstance();

        try {
            $stmt = $db->prepare("
                SELECT
                    COALESCE(category_name, 'Outros') as name,
                    COUNT(*) as orders,
                    COALESCE(SUM(total_amount), 0) as value
                FROM ml_orders
                WHERE date_created >= :date_from
                AND date_created <= :date_to
                AND status != 'cancelled'
                GROUP BY category_name
                ORDER BY value DESC
            ");
            $stmt->execute([
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            return [];
        }
    }

    /**
     * Obtém dados de análise de mercado reais via CompetitorSpy
     */
    private function getMarketData(string $categoryId, ?string $brand): array
    {
        try {
            // Instanciar CompetitorSpy (com account_id da sessão se houver)
            $accountId = $this->getAccountId();
            $spy = new \App\Services\AI\SEO\CompetitorSpy($accountId);

            // Analisar Top Sellers da categoria (real API call)
            $analysis = $spy->analyzeTopSellers($categoryId, 50);

            if (isset($analysis['error'])) {
                // Fallback gracioso se a API falhar ou não houver credenciais
                return $this->getSampleMarketData($categoryId, $brand);
            }

            // Transformar dados do CompetitorSpy para o formato do PDF
            $sellers = $analysis['sellers'] ?? [];

            // Calcular estatísticas dos top sellers
            $allPrices = [];
            foreach ($sellers as $seller) {
                foreach ($seller['items'] as $item) {
                    $allPrices[] = $item['price'];
                }
            }

            $avgPrice = count($allPrices) ? array_sum($allPrices) / count($allPrices) : 0;
            $minPrice = count($allPrices) ? min($allPrices) : 0;
            $maxPrice = count($allPrices) ? max($allPrices) : 0;

            // Formatar competidores
            $competitorsFormatted = array_map(function ($seller) {
                return [
                    'nickname' => $seller['nickname'],
                    'listings' => $seller['item_count'],
                    'average_price' => $seller['avg_price'],
                    'reputation' => 'green' // Default/Unknown se a API não retornar
                ];
            }, $sellers);

            return [
                'category' => [
                    'id' => $categoryId,
                    'name' => 'Categoria ' . $categoryId, // Idealmente buscar nome da categoria
                ],
                'brand' => $brand,
                'total_listings' => count($allPrices), // Listings analisadas
                'average_price' => $avgPrice,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'catalog_count' => 0, // Não disponível via search simples
                'common_count' => count($allPrices),
                'competitors' => $competitorsFormatted,
                'price_ranges' => $this->calculatePriceRanges($allPrices),
            ];
        } catch (\Exception $e) {
            return $this->getSampleMarketData($categoryId, $brand);
        }
    }

    private function calculatePriceRanges(array $prices): array
    {
        $ranges = [
            '0-50' => 0,
            '50-100' => 0,
            '100-200' => 0,
            '200-500' => 0,
            '500+' => 0
        ];

        foreach ($prices as $p) {
            if ($p <= 50) $ranges['0-50']++;
            elseif ($p <= 100) $ranges['50-100']++;
            elseif ($p <= 200) $ranges['100-200']++;
            elseif ($p <= 500) $ranges['200-500']++;
            else $ranges['500+']++;
        }

        return [
            ['label' => 'R$ 0 - R$ 50', 'count' => $ranges['0-50']],
            ['label' => 'R$ 50 - R$ 100', 'count' => $ranges['50-100']],
            ['label' => 'R$ 100 - R$ 200', 'count' => $ranges['100-200']],
            ['label' => 'R$ 200 - R$ 500', 'count' => $ranges['200-500']],
            ['label' => 'R$ 500+', 'count' => $ranges['500+']],
        ];
    }

    private function getSampleMarketData(string $categoryId, ?string $brand): array
    {
        // Return empty structure for production safety
        return [
            'category' => ['id' => $categoryId, 'name' => 'Categoria ' . $categoryId],
            'brand' => $brand,
            'total_listings' => 0,
            'average_price' => 0.0,
            'min_price' => 0.0,
            'max_price' => 0.0,
            'catalog_count' => 0,
            'common_count' => 0,
            'competitors' => [],
            'price_ranges' => []
        ];
    }

    /**
     * Calcula resumo dos pedidos
     */
    private function calculateOrdersSummary(array $orders): array
    {
        $summary = [
            'total_value' => 0,
            'paid' => 0,
            'shipped' => 0,
            'delivered' => 0,
            'cancelled' => 0,
        ];

        foreach ($orders as $order) {
            $summary['total_value'] += (float)($order['total_amount'] ?? 0);

            $status = strtolower($order['status'] ?? '');
            if (isset($summary[$status])) {
                $summary[$status]++;
            } elseif ($status === 'paid' || $status === 'confirmed') {
                $summary['paid']++;
            }
        }

        return $summary;
    }

    /**
     * Obtém dados do dashboard executivo
     */
    private function getExecutiveDashboardData(?int $accountId): array
    {
        $db = Database::getInstance();

        // Dados base
        $data = [
            'revenue' => 0,
            'revenue_growth' => null,
            'orders' => 0,
            'orders_growth' => null,
            'average_ticket' => 0,
            'ticket_growth' => null,
            'active_listings' => 0,
            'conversion_rate' => 0,
            'conversion_growth' => null,
            'active_accounts' => 0,
            'accounts_performance' => [],
            'alerts' => [],
        ];

        try {
            // Contar contas ativas
            $stmt = $db->query("SELECT COUNT(*) FROM ml_accounts WHERE status = 'active'");
            $data['active_accounts'] = (int)$stmt->fetchColumn();

            // Tentar obter dados de vendas
            $stmt = $db->query("
                SELECT
                    COUNT(*) as orders,
                    COALESCE(SUM(total_amount), 0) as revenue
                FROM ml_orders
                WHERE date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                AND status != 'cancelled'
            ");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $data['orders'] = (int)($row['orders'] ?? 0);
            $data['revenue'] = (float)($row['revenue'] ?? 0);
            $data['average_ticket'] = $data['orders'] > 0 ? $data['revenue'] / $data['orders'] : 0;

            // Performance por conta
            $stmt = $db->query("
                SELECT
                    a.nickname as name,
                    COUNT(o.id) as orders,
                    COALESCE(SUM(o.total_amount), 0) as revenue
                FROM ml_accounts a
                LEFT JOIN ml_orders o ON o.ml_account_id = a.id
                    AND o.date_created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                    AND o.status != 'cancelled'
                WHERE a.status = 'active'
                GROUP BY a.id, a.nickname
                ORDER BY revenue DESC
            ");
            $accountsPerf = $stmt->fetchAll(PDO::FETCH_ASSOC);

            foreach ($accountsPerf as &$acc) {
                $acc['average_ticket'] = $acc['orders'] > 0 ? $acc['revenue'] / $acc['orders'] : 0;
            }
            $data['accounts_performance'] = $accountsPerf;
        } catch (\PDOException $e) {
            // Usar dados vazios se falhar
            $data = [
                'revenue' => 0.00,
                'revenue_growth' => 0.0,
                'orders' => 0,
                'orders_growth' => 0.0,
                'average_ticket' => 0.0,
                'ticket_growth' => 0.0,
                'active_listings' => 0,
                'conversion_rate' => 0.0,
                'conversion_growth' => 0.0,
                'active_accounts' => 0,
                'accounts_performance' => [],
                'alerts' => [],
            ];
        }

        return $data;
    }

    /**
     * Obtém dados de um anúncio específico via API do Mercado Livre
     */
    private function getListingData(string $itemId, ?int $accountId): ?array
    {
        try {
            $itemService = new \App\Services\ItemService($accountId);
            $item = $itemService->getItem($itemId);

            if (!$item || isset($item['error'])) {
                return null;
            }

            // Buscar descrição separadamente
            $description = '';
            try {
                $client = new \App\Services\MercadoLivreClient($accountId);
                $descData = $client->get("/items/{$itemId}/description");
                $description = $descData['plain_text'] ?? $descData['text'] ?? '';
            } catch (\Exception $e) {
                // Descrição não é crítica
            }

            return [
                'id' => $item['id'] ?? $itemId,
                'title' => $item['title'] ?? '',
                'price' => (float)($item['price'] ?? 0),
                'condition' => $item['condition'] ?? 'unknown',
                'category_id' => $item['category_id'] ?? '',
                'available_quantity' => (int)($item['available_quantity'] ?? 0),
                'sold_quantity' => (int)($item['sold_quantity'] ?? 0),
                'pictures' => $item['pictures'] ?? [],
                'attributes' => $item['attributes'] ?? [],
                'shipping' => $item['shipping'] ?? [],
                'description' => $description,
            ];
        } catch (\Exception $e) {
            log_error('Erro ao buscar dados do anúncio para PDF', [
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }

    /**
     * Calcula score SEO do anúncio
     */
    private function calculateSeoScore(array $listing): array
    {
        $components = [
            'titulo' => 0,
            'descricao' => 0,
            'imagens' => 0,
            'atributos' => 0,
            'frete' => 0,
        ];

        $recommendations = [];

        // Título (20 pontos)
        $titleLength = mb_strlen($listing['title'] ?? '');
        if ($titleLength >= 45 && $titleLength <= 60) {
            $components['titulo'] = 100;
        } elseif ($titleLength >= 30) {
            $components['titulo'] = 70;
            $recommendations[] = [
                'priority' => 'medium',
                'title' => 'Otimizar tamanho do título',
                'description' => 'O título ideal tem entre 45-60 caracteres. Atual: ' . $titleLength,
            ];
        } else {
            $components['titulo'] = 40;
            $recommendations[] = [
                'priority' => 'high',
                'title' => 'Título muito curto',
                'description' => 'Adicione mais palavras-chave relevantes ao título.',
            ];
        }

        // Descrição (20 pontos)
        $descLength = mb_strlen($listing['description'] ?? '');
        if ($descLength >= 500) {
            $components['descricao'] = 100;
        } elseif ($descLength >= 200) {
            $components['descricao'] = 60;
            $recommendations[] = [
                'priority' => 'medium',
                'title' => 'Expandir descrição',
                'description' => 'Descrições com mais de 500 caracteres performam melhor.',
            ];
        } else {
            $components['descricao'] = 30;
            $recommendations[] = [
                'priority' => 'high',
                'title' => 'Descrição muito curta',
                'description' => 'Adicione mais detalhes sobre o produto na descrição.',
            ];
        }

        // Imagens (20 pontos)
        $imageCount = count($listing['pictures'] ?? []);
        if ($imageCount >= 6) {
            $components['imagens'] = 100;
        } elseif ($imageCount >= 3) {
            $components['imagens'] = 60;
            $recommendations[] = [
                'priority' => 'medium',
                'title' => 'Adicionar mais imagens',
                'description' => 'Recomendamos pelo menos 6 imagens de alta qualidade.',
            ];
        } else {
            $components['imagens'] = 30;
            $recommendations[] = [
                'priority' => 'high',
                'title' => 'Poucas imagens',
                'description' => 'Anúncios com mais imagens têm maior conversão.',
            ];
        }

        // Atributos (20 pontos)
        $attributes = $listing['attributes'] ?? [];
        $hasBrand = false;
        $hasModel = false;
        foreach ($attributes as $attr) {
            if ($attr['id'] === 'BRAND') $hasBrand = true;
            if ($attr['id'] === 'MODEL') $hasModel = true;
        }

        if ($hasBrand && $hasModel) {
            $components['atributos'] = 100;
        } elseif ($hasBrand || $hasModel) {
            $components['atributos'] = 60;
            $recommendations[] = [
                'priority' => 'medium',
                'title' => 'Completar atributos',
                'description' => 'Preencha marca e modelo para melhor posicionamento.',
            ];
        } else {
            $components['atributos'] = 20;
            $recommendations[] = [
                'priority' => 'high',
                'title' => 'Atributos incompletos',
                'description' => 'Preencha todos os atributos obrigatórios e recomendados.',
            ];
        }

        // Frete (20 pontos)
        $freeShipping = $listing['shipping']['free_shipping'] ?? false;
        $isFull = ($listing['shipping']['logistic_type'] ?? '') === 'fulfillment';

        if ($freeShipping && $isFull) {
            $components['frete'] = 100;
        } elseif ($freeShipping) {
            $components['frete'] = 70;
            $recommendations[] = [
                'priority' => 'low',
                'title' => 'Considerar Mercado Livre Full',
                'description' => 'Anúncios Full têm maior visibilidade na busca.',
            ];
        } else {
            $components['frete'] = 30;
            $recommendations[] = [
                'priority' => 'high',
                'title' => 'Oferecer frete grátis',
                'description' => 'Frete grátis aumenta significativamente as conversões.',
            ];
        }

        // Calcular score total
        $totalScore = array_sum($components) / count($components);

        return [
            'total_score' => round($totalScore),
            'components' => $components,
            'recommendations' => $recommendations,
        ];
    }

    /**
     * Gera relatório de análise de marca em PDF
     * GET /api/pdf/brand/awa
     */
    public function brandAnalysisReport(): void
    {
        try {
            // Opções de análise
            $options = [
                'categories' => $this->parseCategories($this->request->get('categories')),
                'max_results' => $this->request->getInt('max_results', 500),
                'include_details' => true,
            ];

            // Obter accountId da sessão se disponível
            $accountId = $this->getAccountId();

            // Executar análise
            $brandAnalyzer = new \App\Services\BrandAnalyzerService($accountId);
            $results = $brandAnalyzer->analyzeAwaBrand($options);

            // Gerar PDF
            $this->pdfService->generateBrandAnalysisReport($results);
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
     * Parse de categorias para análise de marca
     */
    private function parseCategories(?string $categories): ?array
    {
        if ($categories === null || $categories === '') {
            return null;
        }

        return array_map('trim', explode(',', $categories));
    }
}
