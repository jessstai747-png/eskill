<?php

namespace App\Services;

use App\Database;
use App\Helpers\SessionHelper;
use GuzzleHttp\Client as GuzzleClient;
use PDO;

/**
 * Financial Service
 * 
 * Serviço para cálculos financeiros, DRE (P&L) e análises de lucratividade.
 * Centraliza toda a lógica de relatórios financeiros do sistema.
 * Integra com API do Mercado Livre para dados em tempo real.
 */
class FinancialService
{
    private PDO $db;
    private ?int $accountId;
    private ?MercadoLivreClient $client = null;
    private ?object $mpClient = null;

    // Alíquota padrão de impostos (Simples Nacional - média)
    private const DEFAULT_TAX_RATE = 0.0;

    // Cache TTL em segundos
    private const CACHE_TTL_SHORT = 300;   // 5 minutos
    private const CACHE_TTL_MEDIUM = 1800; // 30 minutos
    private const CACHE_TTL_LONG = 3600;   // 1 hora

    public function __construct(?int $accountId = null)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    /**
     * Obtém instância do cliente ML (lazy loading)
     */
    private function getClient(): MercadoLivreClient
    {
        if ($this->client === null) {
            $this->client = new MercadoLivreClient($this->accountId);
        }
        return $this->client;
    }

    /**
     * Obtém cliente HTTP para Mercado Pago API (lazy loading)
     * Usa o mesmo access_token da conta ML autenticada.
     *
     * @return object Cliente com métodos get/post/put/delete retornando arrays
     */
    private function getMercadoPagoClient(): object
    {
        if ($this->mpClient !== null) {
            return $this->mpClient;
        }

        $mlClient = $this->getClient();
        $accessToken = $mlClient->getAccessToken();

        $guzzle = new GuzzleClient([
            'base_uri' => 'https://api.mercadopago.com',
            'timeout'  => 30,
            'headers'  => [
                'Authorization' => 'Bearer ' . ($accessToken ?? ''),
                'Content-Type'  => 'application/json',
                'Accept'        => 'application/json',
            ],
        ]);

        $this->mpClient = new class($guzzle) {
            private GuzzleClient $http;

            public function __construct(GuzzleClient $http)
            {
                $this->http = $http;
            }

            private function request(string $method, string $url, array $data = []): array
            {
                $options = !empty($data) ? ['json' => $data] : [];
                $response = $this->http->request($method, $url, $options);
                return json_decode($response->getBody()->getContents(), true) ?: [];
            }

            public function get(string $url, array $params = []): array
            {
                $options = !empty($params) ? ['query' => $params] : [];
                $response = $this->http->request('GET', $url, $options);
                return json_decode($response->getBody()->getContents(), true) ?: [];
            }

            public function post(string $url, array $data = []): array
            {
                return $this->request('POST', $url, $data);
            }

            public function put(string $url, array $data = []): array
            {
                return $this->request('PUT', $url, $data);
            }

            public function delete(string $url): array
            {
                return $this->request('DELETE', $url);
            }
        };

        return $this->mpClient;
    }

    /**
     * Obtém o seller ID da conta
     */
    private function getSellerId(): ?string
    {
        $client = $this->getClient();
        return $client->getSellerId();
    }

    /**
     * Calcula o DRE (Demonstrativo de Resultado) para o período
     * 
     * @param string $startDate Data inicial (Y-m-d)
     * @param string $endDate Data final (Y-m-d H:i:s)
     * @return array Dados do P&L
     */
    public function getPnL(string $startDate, string $endDate): array
    {
        $whereConditions = ['date_created BETWEEN :start AND :end', "status IN ('paid', 'delivered')"];
        $params = [':start' => $startDate, ':end' => $endDate];

        // Filtrar por conta se especificado
        if ($this->accountId) {
            $whereConditions[] = 'ml_account_id = :account_id';
            $params[':account_id'] = $this->accountId;
        }

        // Filtrar por usuário
        $userId = SessionHelper::getUserId();
        if ($userId) {
            $whereConditions[] = 'user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $whereSql = implode(' AND ', $whereConditions);

        // Consultar dados agregados dos pedidos
        $sql = "SELECT 
                    COUNT(*) as total_orders,
                    COALESCE(SUM(total_amount), 0) as gross_revenue,
                    COALESCE(SUM(subtotal), 0) as subtotal,
                    COALESCE(SUM(ml_commission), 0) as commissions,
                    COALESCE(SUM(payment_fee), 0) as payment_fees,
                    COALESCE(SUM(fixed_fee), 0) as fixed_fees,
                    COALESCE(SUM(shipping_cost), 0) as shipping_cost,
                    COALESCE(SUM(discount_amount), 0) as discounts,
                    COALESCE(SUM(taxes), 0) as taxes,
                    COALESCE(SUM(product_cost), 0) as cogs,
                    COALESCE(SUM(net_profit), 0) as net_profit,
                    COALESCE(AVG(gross_margin), 0) as avg_margin
                FROM ml_orders
                WHERE {$whereSql}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calcular valores derivados
        $grossRevenue = (float)($data['gross_revenue'] ?? 0);
        $taxes = (float)($data['taxes'] ?? 0);
        $netRevenue = $grossRevenue - $taxes;

        // Se não tiver campos detalhados, calcular estimativas
        $commissions = (float)($data['commissions'] ?? 0);
        $paymentFees = (float)($data['payment_fees'] ?? 0);
        $fixedFees = (float)($data['fixed_fees'] ?? 0);
        $shippingCost = (float)($data['shipping_cost'] ?? 0);
        $discounts = (float)($data['discounts'] ?? 0);
        $cogs = (float)($data['cogs'] ?? 0);
        $netProfit = (float)($data['net_profit'] ?? 0);

        // Se net_profit estiver zerado, calcular
        if ($netProfit === 0.0 && $grossRevenue > 0) {
            $totalCosts = $commissions + $paymentFees + $fixedFees + $shippingCost + $cogs;
            $netProfit = $netRevenue - $totalCosts - $discounts;
        }

        // Calcular margem se não existir
        $avgMargin = (float)($data['avg_margin'] ?? 0);
        if ($avgMargin === 0.0 && $grossRevenue > 0) {
            $avgMargin = ($netProfit / $grossRevenue) * 100;
        }

        return [
            'total_orders' => (int)($data['total_orders'] ?? 0),
            'gross_revenue' => round($grossRevenue, 2),
            'taxes' => round($taxes, 2),
            'net_revenue' => round($netRevenue, 2),
            'cogs' => round($cogs, 2),
            'commissions' => round($commissions, 2),
            'payment_fees' => round($paymentFees, 2),
            'fixed_fees' => round($fixedFees, 2),
            'shipping_cost' => round($shippingCost, 2),
            'discounts' => round($discounts, 2),
            'net_profit' => round($netProfit, 2),
            'avg_margin' => round($avgMargin, 2),
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ];
    }

    /**
     * Retorna receita e lucro diários para gráficos
     * 
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @return array Array de dados diários
     */
    public function getDailyRevenue(string $startDate, string $endDate): array
    {
        $whereConditions = [
            'date_created BETWEEN :start AND :end',
            "status IN ('paid', 'delivered')",
        ];
        $params = [':start' => $startDate, ':end' => $endDate];

        if ($this->accountId) {
            $whereConditions[] = 'ml_account_id = :account_id';
            $params[':account_id'] = $this->accountId;
        }

        $userId = SessionHelper::getUserId();
        if ($userId) {
            $whereConditions[] = 'user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $whereSql = implode(' AND ', $whereConditions);

        $sql = "SELECT 
                    DATE(date_created) as date,
                    SUM(total_amount) as revenue,
                    SUM(net_profit) as profit,
                    COUNT(*) as orders
                FROM ml_orders
                WHERE {$whereSql}
                GROUP BY DATE(date_created)
                ORDER BY date ASC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return array_map(fn($row) => [
            'date' => $row['date'],
            'revenue' => round((float)$row['revenue'], 2),
            'profit' => round((float)$row['profit'], 2),
            'orders' => (int)$row['orders'],
        ], $rows);
    }

    /**
     * Retorna o fluxo de caixa do período
     * 
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @return array Dados de fluxo de caixa
     */
    public function getCashFlow(string $startDate, string $endDate): array
    {
        // Entradas: receitas dos pedidos
        $inflows = $this->getInflows($startDate, $endDate);

        // Saídas: custos e taxas
        $outflows = $this->getOutflows($startDate, $endDate);

        // Saldo
        $balance = $inflows['total'] - $outflows['total'];

        return [
            'inflows' => $inflows,
            'outflows' => $outflows,
            'balance' => round($balance, 2),
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
            ],
        ];
    }

    /**
     * Calcula entradas (receitas)
     */
    private function getInflows(string $startDate, string $endDate): array
    {
        $whereConditions = [
            'date_created BETWEEN :start AND :end',
            "status IN ('paid', 'delivered')",
        ];
        $params = [':start' => $startDate, ':end' => $endDate];

        if ($this->accountId) {
            $whereConditions[] = 'ml_account_id = :account_id';
            $params[':account_id'] = $this->accountId;
        }

        $userId = SessionHelper::getUserId();
        if ($userId) {
            $whereConditions[] = 'user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $whereSql = implode(' AND ', $whereConditions);

        $sql = "SELECT 
                    SUM(total_amount) as sales,
                    COUNT(*) as transactions
                FROM ml_orders
                WHERE {$whereSql}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $sales = (float)($data['sales'] ?? 0);

        return [
            'sales' => round($sales, 2),
            'transactions' => (int)($data['transactions'] ?? 0),
            'total' => round($sales, 2),
        ];
    }

    /**
     * Calcula saídas (custos)
     */
    private function getOutflows(string $startDate, string $endDate): array
    {
        $whereConditions = [
            'date_created BETWEEN :start AND :end',
            "status IN ('paid', 'delivered')",
        ];
        $params = [':start' => $startDate, ':end' => $endDate];

        if ($this->accountId) {
            $whereConditions[] = 'ml_account_id = :account_id';
            $params[':account_id'] = $this->accountId;
        }

        $userId = SessionHelper::getUserId();
        if ($userId) {
            $whereConditions[] = 'user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $whereSql = implode(' AND ', $whereConditions);

        $sql = "SELECT 
                    SUM(ml_commission) as commissions,
                    SUM(payment_fee) as payment_fees,
                    SUM(fixed_fee) as fixed_fees,
                    SUM(shipping_cost) as shipping,
                    SUM(product_cost) as cogs,
                    SUM(taxes) as taxes
                FROM ml_orders
                WHERE {$whereSql}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $commissions = (float)($data['commissions'] ?? 0);
        $paymentFees = (float)($data['payment_fees'] ?? 0);
        $fixedFees = (float)($data['fixed_fees'] ?? 0);
        $shipping = (float)($data['shipping'] ?? 0);
        $cogs = (float)($data['cogs'] ?? 0);
        $taxes = (float)($data['taxes'] ?? 0);

        $total = $commissions + $paymentFees + $fixedFees + $shipping + $cogs + $taxes;

        return [
            'commissions' => round($commissions, 2),
            'payment_fees' => round($paymentFees, 2),
            'fixed_fees' => round($fixedFees, 2),
            'shipping' => round($shipping, 2),
            'cogs' => round($cogs, 2),
            'taxes' => round($taxes, 2),
            'total' => round($total, 2),
        ];
    }

    /**
     * Retorna análise de lucratividade por produto/anúncio
     * 
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @param int $limit Limite de resultados
     * @return array Produtos mais/menos lucrativos
     */
    public function getProfitabilityByProduct(string $startDate, string $endDate, int $limit = 20): array
    {
        $whereConditions = [
            'o.date_created BETWEEN :start AND :end',
            "o.status IN ('paid', 'delivered')",
        ];
        $params = [':start' => $startDate, ':end' => $endDate];

        if ($this->accountId) {
            $whereConditions[] = 'o.ml_account_id = :account_id';
            $params[':account_id'] = $this->accountId;
        }

        $userId = SessionHelper::getUserId();
        if ($userId) {
            $whereConditions[] = 'o.user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $whereSql = implode(' AND ', $whereConditions);

        // Extrair item_id do JSON order_data
        $limitSql = max(1, min(500, (int)$limit));

        $sql = "SELECT 
                    JSON_UNQUOTE(JSON_EXTRACT(o.order_data, '$.order_items[0].item.id')) as item_id,
                    JSON_UNQUOTE(JSON_EXTRACT(o.order_data, '$.order_items[0].item.title')) as title,
                    SUM(o.total_amount) as revenue,
                    SUM(o.net_profit) as profit,
                    COUNT(*) as sales,
                    AVG(o.gross_margin) as avg_margin
                FROM ml_orders o
                WHERE {$whereSql}
                GROUP BY item_id, title
                HAVING item_id IS NOT NULL
                ORDER BY profit DESC
            LIMIT {$limitSql}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $topProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Buscar os menos lucrativos
        $sqlWorst = str_replace('ORDER BY profit DESC', 'ORDER BY profit ASC', $sql);
        $stmt = $this->db->prepare($sqlWorst);
        $stmt->execute($params);
        $worstProducts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'top_profitable' => array_map(fn($row) => [
                'item_id' => $row['item_id'],
                'title' => $row['title'] ?? 'Sem título',
                'revenue' => round((float)$row['revenue'], 2),
                'profit' => round((float)$row['profit'], 2),
                'sales' => (int)$row['sales'],
                'avg_margin' => round((float)$row['avg_margin'], 2),
            ], $topProducts),
            'least_profitable' => array_map(fn($row) => [
                'item_id' => $row['item_id'],
                'title' => $row['title'] ?? 'Sem título',
                'revenue' => round((float)$row['revenue'], 2),
                'profit' => round((float)$row['profit'], 2),
                'sales' => (int)$row['sales'],
                'avg_margin' => round((float)$row['avg_margin'], 2),
            ], $worstProducts),
        ];
    }

    /**
     * Retorna métricas financeiras do período
     * 
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @return array Métricas calculadas
     */
    public function getMetrics(string $startDate, string $endDate): array
    {
        $pnl = $this->getPnL($startDate, $endDate);

        // Ticket médio
        $avgTicket = $pnl['total_orders'] > 0
            ? $pnl['gross_revenue'] / $pnl['total_orders']
            : 0;

        // Taxa de conversão de custos
        $costRate = $pnl['gross_revenue'] > 0
            ? (($pnl['commissions'] + $pnl['payment_fees'] + $pnl['fixed_fees']) / $pnl['gross_revenue']) * 100
            : 0;

        // ROI
        $totalCosts = $pnl['cogs'] + $pnl['commissions'] + $pnl['payment_fees'] + $pnl['fixed_fees'] + $pnl['shipping_cost'];
        $roi = $totalCosts > 0
            ? (($pnl['net_profit'] / $totalCosts) * 100)
            : 0;

        return [
            'total_orders' => $pnl['total_orders'],
            'gross_revenue' => $pnl['gross_revenue'],
            'net_profit' => $pnl['net_profit'],
            'avg_ticket' => round($avgTicket, 2),
            'avg_margin' => $pnl['avg_margin'],
            'cost_rate' => round($costRate, 2),
            'roi' => round($roi, 2),
        ];
    }

    /**
     * Compara períodos (mês atual vs anterior, por exemplo)
     * 
     * @param string $currentStart Início do período atual
     * @param string $currentEnd Fim do período atual
     * @param string $previousStart Início do período anterior
     * @param string $previousEnd Fim do período anterior
     * @return array Comparação com variações
     */
    public function comparePeriods(
        string $currentStart,
        string $currentEnd,
        string $previousStart,
        string $previousEnd
    ): array {
        $current = $this->getPnL($currentStart, $currentEnd);
        $previous = $this->getPnL($previousStart, $previousEnd);

        $calculateVariation = function ($current, $previous): float {
            if ($previous == 0) {
                return $current > 0 ? 100 : 0;
            }
            return round((($current - $previous) / $previous) * 100, 2);
        };

        return [
            'current' => $current,
            'previous' => $previous,
            'variations' => [
                'gross_revenue' => $calculateVariation($current['gross_revenue'], $previous['gross_revenue']),
                'net_profit' => $calculateVariation($current['net_profit'], $previous['net_profit']),
                'total_orders' => $calculateVariation($current['total_orders'], $previous['total_orders']),
                'avg_margin' => round($current['avg_margin'] - $previous['avg_margin'], 2),
            ],
        ];
    }

    /**
     * Retorna resumo financeiro para cards do dashboard
     */
    public function getDashboardSummary(): array
    {
        // Período atual (mês)
        $currentMonthStart = date('Y-m-01');
        $currentMonthEnd = date('Y-m-t 23:59:59');

        // Mês anterior
        $previousMonthStart = date('Y-m-01', strtotime('-1 month'));
        $previousMonthEnd = date('Y-m-t 23:59:59', strtotime('-1 month'));

        $comparison = $this->comparePeriods(
            $currentMonthStart,
            $currentMonthEnd,
            $previousMonthStart,
            $previousMonthEnd
        );

        // Hoje
        $todayStart = date('Y-m-d 00:00:00');
        $todayEnd = date('Y-m-d 23:59:59');
        $today = $this->getPnL($todayStart, $todayEnd);

        return [
            'today' => $today,
            'current_month' => $comparison['current'],
            'previous_month' => $comparison['previous'],
            'variations' => $comparison['variations'],
        ];
    }

    // ========================================================================
    // MÉTODOS DE API - DADOS EM TEMPO REAL DO MERCADO LIVRE
    // ========================================================================

    /**
     * Obtém saldo da conta no Mercado Pago
     * Endpoint: GET /users/{user_id}/mercadopago_account/balance
     * 
     * @return array Saldo disponível e total
     */
    public function getAccountBalance(): array
    {
        $sellerId = $this->getSellerId();
        if (!$sellerId) {
            return ['error' => 'Seller ID não encontrado', 'available_balance' => 0, 'total_amount' => 0];
        }

        $client = $this->getClient();
        $response = $client->get("/users/{$sellerId}/mercadopago_account/balance", [], self::CACHE_TTL_SHORT);

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao obter saldo',
                'available_balance' => 0,
                'total_amount' => 0,
                'unavailable_balance' => 0,
            ];
        }

        return [
            'available_balance' => (float)($response['available_balance'] ?? 0),
            'total_amount' => (float)($response['total_amount'] ?? 0),
            'unavailable_balance' => (float)($response['unavailable_balance'] ?? 0),
            'currency_id' => $response['currency_id'] ?? 'BRL',
            'updated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Obtém pedidos com detalhes financeiros da API
     * Endpoint: GET /orders/search
     * 
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @param int $limit Limite de resultados
     * @param int $offset Offset para paginação
     * @return array Lista de pedidos com dados financeiros
     */
    public function getOrdersFromApi(string $startDate, string $endDate, int $limit = 50, int $offset = 0): array
    {
        $sellerId = $this->getSellerId();
        if (!$sellerId) {
            return ['error' => 'Seller ID não encontrado', 'results' => []];
        }

        $client = $this->getClient();

        $params = [
            'seller' => $sellerId,
            'order.date_created.from' => $startDate . 'T00:00:00.000-03:00',
            'order.date_created.to' => $endDate . 'T23:59:59.999-03:00',
            'sort' => 'date_desc',
            'limit' => min(50, $limit),
            'offset' => $offset,
        ];

        $response = $client->get('/orders/search', $params);

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar pedidos',
                'results' => [],
                'paging' => ['total' => 0, 'offset' => $offset, 'limit' => $limit],
            ];
        }

        $orders = [];
        foreach ($response['results'] ?? [] as $order) {
            $orders[] = $this->extractOrderFinancials($order);
        }

        return [
            'results' => $orders,
            'paging' => $response['paging'] ?? ['total' => count($orders), 'offset' => $offset, 'limit' => $limit],
        ];
    }

    /**
     * Extrai dados financeiros de um pedido
     */
    private function extractOrderFinancials(array $order): array
    {
        $payments = $order['payments'] ?? [];
        $totalPaid = 0;
        $paymentFees = 0;
        $paymentMethod = null;

        foreach ($payments as $payment) {
            if (($payment['status'] ?? '') === 'approved') {
                $totalPaid += (float)($payment['total_paid_amount'] ?? $payment['transaction_amount'] ?? 0);
                $paymentFees += (float)($payment['fee_details'][0]['amount'] ?? 0);
                $paymentMethod = $payment['payment_type'] ?? $paymentMethod;
            }
        }

        // Calcular comissões e taxas do pedido
        $orderItems = $order['order_items'] ?? [];
        $subtotal = 0;
        $mlFee = 0;

        foreach ($orderItems as $item) {
            $subtotal += (float)($item['unit_price'] ?? 0) * (int)($item['quantity'] ?? 1);
            $mlFee += (float)($item['sale_fee'] ?? 0);
        }

        // Frete
        $shippingCost = (float)($order['shipping']['cost'] ?? 0);

        return [
            'order_id' => $order['id'] ?? null,
            'status' => $order['status'] ?? 'unknown',
            'date_created' => $order['date_created'] ?? null,
            'date_closed' => $order['date_closed'] ?? null,
            'total_amount' => (float)($order['total_amount'] ?? 0),
            'paid_amount' => $totalPaid,
            'subtotal' => $subtotal,
            'ml_fee' => $mlFee,
            'payment_fee' => $paymentFees,
            'shipping_cost' => $shippingCost,
            'payment_method' => $paymentMethod,
            'buyer_id' => $order['buyer']['id'] ?? null,
            'buyer_nickname' => $order['buyer']['nickname'] ?? null,
            'items' => array_map(fn($i) => [
                'item_id' => $i['item']['id'] ?? null,
                'title' => $i['item']['title'] ?? null,
                'quantity' => (int)($i['quantity'] ?? 1),
                'unit_price' => (float)($i['unit_price'] ?? 0),
                'sale_fee' => (float)($i['sale_fee'] ?? 0),
            ], $orderItems),
        ];
    }

    /**
     * Obtém detalhes de um pedido específico com dados financeiros
     * Endpoint: GET /orders/{order_id}
     * 
     * @param string $orderId ID do pedido
     * @return array Detalhes financeiros do pedido
     */
    public function getOrderDetails(string $orderId): array
    {
        $client = $this->getClient();
        $response = $client->get("/orders/{$orderId}");

        if (isset($response['error'])) {
            return ['error' => $response['message'] ?? 'Pedido não encontrado'];
        }

        return $this->extractOrderFinancials($response);
    }

    /**
     * Obtém informações de cobrança/billing do vendedor
     * Endpoint: GET /users/{user_id}/billing/info
     * 
     * @return array Informações de cobrança
     */
    public function getBillingInfo(): array
    {
        $sellerId = $this->getSellerId();
        if (!$sellerId) {
            return ['error' => 'Seller ID não encontrado'];
        }

        $client = $this->getClient();
        $response = $client->get("/users/{$sellerId}/billing/info", [], self::CACHE_TTL_MEDIUM);

        if (isset($response['error'])) {
            // Se endpoint não disponível, retornar estrutura vazia
            return [
                'billing_allowed' => false,
                'message' => $response['message'] ?? 'Informações de billing não disponíveis',
            ];
        }

        return $response;
    }

    /**
     * Obtém relatório de liquidações (settlements) da API
     * Endpoint: GET /billing/integration/settlement_report
     * 
     * @param string $startDate Data inicial (Y-m-d)
     * @param string $endDate Data final (Y-m-d)
     * @return array Relatório de liquidações
     */
    public function getSettlementReport(string $startDate, string $endDate): array
    {
        $sellerId = $this->getSellerId();
        if (!$sellerId) {
            return ['error' => 'Seller ID não encontrado', 'results' => []];
        }

        $client = $this->getClient();

        // Tentar endpoint de settlement report
        $params = [
            'user_id' => $sellerId,
            'date_from' => $startDate,
            'date_to' => $endDate,
        ];

        $response = $client->get('/billing/integration/settlement_report', $params);

        if (isset($response['error'])) {
            // Fallback: usar dados locais de settlements
            return $this->getLocalSettlements($startDate, $endDate);
        }

        return [
            'source' => 'api',
            'results' => $response['results'] ?? $response,
            'period' => ['start' => $startDate, 'end' => $endDate],
        ];
    }

    /**
     * Obtém settlements locais do banco de dados
     */
    private function getLocalSettlements(string $startDate, string $endDate): array
    {
        $where = ['date_released BETWEEN :start AND :end'];
        $params = [':start' => $startDate, ':end' => $endDate . ' 23:59:59'];

        if ($this->accountId) {
            $where[] = 'account_id = :account_id';
            $params[':account_id'] = $this->accountId;
        }

        $whereSql = implode(' AND ', $where);

        $sql = "SELECT * FROM financial_settlements WHERE {$whereSql} ORDER BY date_released DESC LIMIT 500";

        try {
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'source' => 'local',
                'results' => $results,
                'total' => count($results),
                'period' => ['start' => $startDate, 'end' => $endDate],
            ];
        } catch (\Exception $e) {
            return [
                'source' => 'local',
                'results' => [],
                'error' => 'Tabela de settlements não disponível',
            ];
        }
    }

    /**
     * Sincroniza pedidos da API com banco local e calcula métricas financeiras
     * 
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @param bool $forceSync Forçar sync mesmo se já sincronizado recentemente
     * @return array Resultado da sincronização
     */
    public function syncOrdersWithFinancials(string $startDate, string $endDate, bool $forceSync = false): array
    {
        $sellerId = $this->getSellerId();
        if (!$sellerId) {
            return ['error' => 'Seller ID não encontrado', 'synced' => 0];
        }

        $userId = SessionHelper::getUserId();
        $synced = 0;
        $errors = [];
        $offset = 0;
        $limit = 50;
        $hasMore = true;

        while ($hasMore) {
            $response = $this->getOrdersFromApi($startDate, $endDate, $limit, $offset);

            if (isset($response['error'])) {
                $errors[] = $response['error'];
                break;
            }

            $orders = $response['results'] ?? [];

            if (empty($orders)) {
                break;
            }

            foreach ($orders as $order) {
                try {
                    $this->saveOrderWithFinancials($order, $userId);
                    $synced++;
                } catch (\Exception $e) {
                    $errors[] = "Order {$order['order_id']}: " . $e->getMessage();
                }
            }

            $paging = $response['paging'] ?? [];
            $total = $paging['total'] ?? 0;
            $offset += $limit;
            $hasMore = $offset < $total && count($orders) === $limit;

            // Limite de segurança
            if ($offset > 5000) {
                break;
            }
        }

        return [
            'success' => empty($errors),
            'synced' => $synced,
            'errors' => $errors,
            'period' => ['start' => $startDate, 'end' => $endDate],
        ];
    }

    /**
     * Salva pedido com dados financeiros no banco
     */
    private function saveOrderWithFinancials(array $order, ?int $userId): void
    {
        if (empty($order['order_id'])) {
            return;
        }

        // Verificar se userId não está na sessão (CRON), buscar da conta
        if (!$userId && $this->accountId) {
            $stmt = $this->db->prepare("SELECT user_id FROM ml_accounts WHERE id = :id LIMIT 1");
            $stmt->execute(['id' => $this->accountId]);
            $userId = $stmt->fetchColumn() ?: null;
        }

        $stmt = $this->db->prepare("
            INSERT INTO ml_orders (
                ml_order_id, ml_account_id, user_id, order_data, status, 
                total_amount, subtotal, ml_commission, payment_fee, shipping_cost,
                date_created, synced_at
            ) VALUES (
                :ml_order_id, :ml_account_id, :user_id, :order_data, :status,
                :total_amount, :subtotal, :ml_commission, :payment_fee, :shipping_cost,
                :date_created, NOW()
            )
            ON DUPLICATE KEY UPDATE
                order_data = VALUES(order_data),
                status = VALUES(status),
                total_amount = VALUES(total_amount),
                subtotal = VALUES(subtotal),
                ml_commission = VALUES(ml_commission),
                payment_fee = VALUES(payment_fee),
                shipping_cost = VALUES(shipping_cost),
                synced_at = NOW()
        ");

        $orderJson = json_encode($order);

        $stmt->execute([
            ':ml_order_id' => $order['order_id'],
            ':ml_account_id' => $this->accountId,
            ':user_id' => $userId,
            ':order_data' => $orderJson,
            ':status' => $order['status'] ?? 'unknown',
            ':total_amount' => $order['total_amount'] ?? 0,
            ':subtotal' => $order['subtotal'] ?? 0,
            ':ml_commission' => $order['ml_fee'] ?? 0,
            ':payment_fee' => $order['payment_fee'] ?? 0,
            ':shipping_cost' => $order['shipping_cost'] ?? 0,
            ':date_created' => $order['date_created'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Obtém resumo financeiro em tempo real (API + local)
     * Combina dados da API com dados locais para visão completa
     * 
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @return array Resumo financeiro completo
     */
    public function getRealTimeFinancialSummary(string $startDate, string $endDate): array
    {
        // Dados do banco local (já sincronizados)
        $localPnl = $this->getPnL($startDate, $endDate);

        // Saldo atual da conta
        $balance = $this->getAccountBalance();

        // Tentar obter dados recentes da API para período curto (últimos 7 dias)
        $recentOrders = [];
        $today = date('Y-m-d');
        $weekAgo = date('Y-m-d', strtotime('-7 days'));

        if ($startDate >= $weekAgo) {
            $apiOrders = $this->getOrdersFromApi($startDate, min($endDate, $today), 50);
            $recentOrders = $apiOrders['results'] ?? [];
        }

        // Calcular métricas dos pedidos recentes da API
        $apiMetrics = $this->calculateMetricsFromOrders($recentOrders);

        return [
            'local_data' => $localPnl,
            'account_balance' => $balance,
            'api_recent_orders' => [
                'count' => count($recentOrders),
                'metrics' => $apiMetrics,
            ],
            'combined' => [
                'gross_revenue' => $localPnl['gross_revenue'] + $apiMetrics['revenue'],
                'total_orders' => $localPnl['total_orders'] + $apiMetrics['orders'],
                'available_balance' => $balance['available_balance'] ?? 0,
            ],
            'data_freshness' => [
                'local_data' => 'historical',
                'api_data' => 'real-time',
                'generated_at' => date('Y-m-d H:i:s'),
            ],
        ];
    }

    /**
     * Calcula métricas a partir de lista de pedidos
     */
    private function calculateMetricsFromOrders(array $orders): array
    {
        $revenue = 0;
        $fees = 0;
        $shipping = 0;
        $count = count($orders);

        foreach ($orders as $order) {
            $revenue += (float)($order['total_amount'] ?? 0);
            $fees += (float)($order['ml_fee'] ?? 0) + (float)($order['payment_fee'] ?? 0);
            $shipping += (float)($order['shipping_cost'] ?? 0);
        }

        $profit = $revenue - $fees - $shipping;
        $margin = $revenue > 0 ? ($profit / $revenue) * 100 : 0;

        return [
            'orders' => $count,
            'revenue' => round($revenue, 2),
            'fees' => round($fees, 2),
            'shipping' => round($shipping, 2),
            'profit' => round($profit, 2),
            'margin' => round($margin, 2),
        ];
    }

    /**
     * Obtém análise de taxas e comissões do Mercado Livre
     * 
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @return array Breakdown de taxas
     */
    public function getFeesBreakdown(string $startDate, string $endDate): array
    {
        $whereConditions = [
            'date_created BETWEEN :start AND :end',
            "status IN ('paid', 'delivered')",
        ];
        $params = [':start' => $startDate, ':end' => $endDate . ' 23:59:59'];

        if ($this->accountId) {
            $whereConditions[] = 'ml_account_id = :account_id';
            $params[':account_id'] = $this->accountId;
        }

        $userId = SessionHelper::getUserId();
        if ($userId) {
            $whereConditions[] = 'user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $whereSql = implode(' AND ', $whereConditions);

        $sql = "SELECT 
                    COUNT(*) as total_orders,
                    COALESCE(SUM(total_amount), 0) as gross_revenue,
                    COALESCE(SUM(ml_commission), 0) as ml_commission,
                    COALESCE(SUM(payment_fee), 0) as payment_fees,
                    COALESCE(SUM(fixed_fee), 0) as fixed_fees,
                    COALESCE(SUM(shipping_cost), 0) as shipping_cost
                FROM ml_orders
                WHERE {$whereSql}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $grossRevenue = (float)($data['gross_revenue'] ?? 0);
        $mlCommission = (float)($data['ml_commission'] ?? 0);
        $paymentFees = (float)($data['payment_fees'] ?? 0);
        $fixedFees = (float)($data['fixed_fees'] ?? 0);
        $shippingCost = (float)($data['shipping_cost'] ?? 0);

        $totalFees = $mlCommission + $paymentFees + $fixedFees;
        $feeRate = $grossRevenue > 0 ? ($totalFees / $grossRevenue) * 100 : 0;

        return [
            'gross_revenue' => round($grossRevenue, 2),
            'fees' => [
                'ml_commission' => round($mlCommission, 2),
                'payment_fees' => round($paymentFees, 2),
                'fixed_fees' => round($fixedFees, 2),
                'total' => round($totalFees, 2),
            ],
            'shipping_cost' => round($shippingCost, 2),
            'fee_rate' => round($feeRate, 2),
            'breakdown_by_type' => [
                [
                    'type' => 'Comissão ML',
                    'amount' => round($mlCommission, 2),
                    'percentage' => $grossRevenue > 0 ? round(($mlCommission / $grossRevenue) * 100, 2) : 0,
                ],
                [
                    'type' => 'Taxa de Pagamento',
                    'amount' => round($paymentFees, 2),
                    'percentage' => $grossRevenue > 0 ? round(($paymentFees / $grossRevenue) * 100, 2) : 0,
                ],
                [
                    'type' => 'Taxas Fixas',
                    'amount' => round($fixedFees, 2),
                    'percentage' => $grossRevenue > 0 ? round(($fixedFees / $grossRevenue) * 100, 2) : 0,
                ],
            ],
            'total_orders' => (int)($data['total_orders'] ?? 0),
            'period' => ['start' => $startDate, 'end' => $endDate],
        ];
    }

    /**
     * Obtém projeção financeira baseada em histórico
     * 
     * @param int $daysAhead Dias para projetar
     * @return array Projeção financeira
     */
    public function getFinancialProjection(int $daysAhead = 30): array
    {
        // Usar últimos 30 dias como base
        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime('-30 days'));

        $historical = $this->getPnL($startDate, $endDate . ' 23:59:59');
        $dailyAvg = $historical['total_orders'] > 0
            ? $historical['gross_revenue'] / 30
            : 0;

        $projectedRevenue = $dailyAvg * $daysAhead;
        $projectedProfit = $historical['gross_revenue'] > 0
            ? ($historical['net_profit'] / $historical['gross_revenue']) * $projectedRevenue
            : 0;

        return [
            'projection_period_days' => $daysAhead,
            'based_on_days' => 30,
            'historical' => [
                'daily_avg_revenue' => round($dailyAvg, 2),
                'daily_avg_orders' => round($historical['total_orders'] / 30, 1),
                'avg_margin' => $historical['avg_margin'],
            ],
            'projected' => [
                'revenue' => round($projectedRevenue, 2),
                'profit' => round($projectedProfit, 2),
                'orders' => round(($historical['total_orders'] / 30) * $daysAhead),
            ],
            'confidence' => $historical['total_orders'] >= 30 ? 'high' : ($historical['total_orders'] >= 10 ? 'medium' : 'low'),
        ];
    }

    /**
     * Obtém dados de receita por categoria de produto
     * 
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @return array Receita por categoria
     */
    public function getRevenueByCategory(string $startDate, string $endDate): array
    {
        $whereConditions = [
            'date_created BETWEEN :start AND :end',
            "status IN ('paid', 'delivered')",
        ];
        $params = [':start' => $startDate, ':end' => $endDate . ' 23:59:59'];

        if ($this->accountId) {
            $whereConditions[] = 'ml_account_id = :account_id';
            $params[':account_id'] = $this->accountId;
        }

        $userId = SessionHelper::getUserId();
        if ($userId) {
            $whereConditions[] = 'user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $whereSql = implode(' AND ', $whereConditions);

        // Extrair categoria do JSON de order_data
        $sql = "SELECT 
                    JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.order_items[0].item.category_id')) as category_id,
                    SUM(total_amount) as revenue,
                    SUM(net_profit) as profit,
                    COUNT(*) as orders
                FROM ml_orders
                WHERE {$whereSql}
                GROUP BY category_id
                HAVING category_id IS NOT NULL
                ORDER BY revenue DESC
                LIMIT 20";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Enriquecer com nomes de categorias
        $categories = [];
        $client = $this->getClient();

        foreach ($rows as $row) {
            $categoryId = $row['category_id'];
            $categoryName = $categoryId;

            // Tentar obter nome da categoria
            if ($categoryId) {
                try {
                    $catInfo = $client->get("/categories/{$categoryId}", [], self::CACHE_TTL_LONG, true);
                    $categoryName = $catInfo['name'] ?? $categoryId;
                } catch (\Exception $e) {
                    // Manter ID como nome
                }
            }

            $categories[] = [
                'category_id' => $categoryId,
                'category_name' => $categoryName,
                'revenue' => round((float)$row['revenue'], 2),
                'profit' => round((float)$row['profit'], 2),
                'orders' => (int)$row['orders'],
            ];
        }

        return [
            'categories' => $categories,
            'period' => ['start' => $startDate, 'end' => $endDate],
        ];
    }

    /**
     * Obtém movimentações financeiras (releases) da conta
     * Endpoint: GET /users/{user_id}/mercadopago_account/movements
     * 
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @param int $limit Limite de resultados
     * @return array Lista de movimentações
     */
    public function getAccountMovements(string $startDate, string $endDate, int $limit = 50): array
    {
        $sellerId = $this->getSellerId();
        if (!$sellerId) {
            return ['error' => 'Seller ID não encontrado', 'results' => []];
        }

        $client = $this->getClient();

        $params = [
            'begin_date' => $startDate . 'T00:00:00.000-03:00',
            'end_date' => $endDate . 'T23:59:59.999-03:00',
            'limit' => min(50, $limit),
        ];

        $response = $client->get("/users/{$sellerId}/mercadopago_account/movements", $params);

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar movimentações',
                'results' => [],
            ];
        }

        $movements = [];
        foreach ($response['results'] ?? $response as $mov) {
            $movements[] = [
                'id' => $mov['id'] ?? null,
                'type' => $mov['type'] ?? 'unknown',
                'amount' => (float)($mov['amount'] ?? 0),
                'balance' => (float)($mov['balance'] ?? 0),
                'date_created' => $mov['date_created'] ?? null,
                'reference_id' => $mov['reference_id'] ?? null,
                'description' => $mov['description'] ?? null,
            ];
        }

        return [
            'results' => $movements,
            'total' => count($movements),
            'period' => ['start' => $startDate, 'end' => $endDate],
        ];
    }

    /**
     * Obtém informações de pagamento de um pedido específico
     * Endpoint: GET /payments/{payment_id}
     * 
     * @param string $paymentId ID do pagamento
     * @return array Detalhes do pagamento
     */
    public function getPaymentDetails(string $paymentId): array
    {
        $client = $this->getClient();
        $response = $client->get("/payments/{$paymentId}");

        if (isset($response['error'])) {
            return ['error' => $response['message'] ?? 'Pagamento não encontrado'];
        }

        return [
            'id' => $response['id'] ?? null,
            'status' => $response['status'] ?? 'unknown',
            'status_detail' => $response['status_detail'] ?? null,
            'transaction_amount' => (float)($response['transaction_amount'] ?? 0),
            'total_paid_amount' => (float)($response['total_paid_amount'] ?? 0),
            'net_received_amount' => (float)($response['net_received_amount'] ?? 0),
            'currency_id' => $response['currency_id'] ?? 'BRL',
            'payment_type' => $response['payment_type_id'] ?? null,
            'payment_method' => $response['payment_method_id'] ?? null,
            'installments' => (int)($response['installments'] ?? 1),
            'fee_details' => $response['fee_details'] ?? [],
            'date_created' => $response['date_created'] ?? null,
            'date_approved' => $response['date_approved'] ?? null,
        ];
    }

    /**
     * Obtém detalhes de conciliação/faturamento do Mercado Livre
     * Endpoint: GET /billing/integration/periods/key/{period}/group/ML/details
     * 
     * @param string $periodKey Período no formato YYYY-MM-01 (primeiro dia do mês)
     * @param string $documentType Tipo de documento: BILL ou CREDIT_NOTE
     * @param int $limit Limite de resultados (max 1000)
     * @param int $fromId ID inicial para paginação
     * @return array Detalhes de faturamento
     */
    public function getBillingDetails(
        string $periodKey,
        string $documentType = 'BILL',
        int $limit = 150,
        int $fromId = 0
    ): array {
        $client = $this->getClient();

        $params = [
            'document_type' => $documentType,
            'limit' => min(1000, $limit),
        ];

        if ($fromId > 0) {
            $params['from_id'] = $fromId;
        }

        $response = $client->get(
            "/billing/integration/periods/key/{$periodKey}/group/ML/details",
            $params
        );

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar detalhes de faturamento',
                'results' => [],
            ];
        }

        $results = [];
        foreach ($response['results'] ?? [] as $item) {
            $chargeInfo = $item['charge_info'] ?? [];
            $salesInfo = $item['sales_info'][0] ?? [];
            $itemsInfo = $item['items_info'][0] ?? [];
            $shippingInfo = $item['shipping_info'] ?? [];
            $discountInfo = $item['discount_info'] ?? [];

            $results[] = [
                'detail_id' => $chargeInfo['detail_id'] ?? null,
                'legal_document_number' => $chargeInfo['legal_document_number'] ?? null,
                'legal_document_status' => $chargeInfo['legal_document_status'] ?? null,
                'transaction_detail' => $chargeInfo['transaction_detail'] ?? null,
                'detail_amount' => (float)($chargeInfo['detail_amount'] ?? 0),
                'detail_type' => $chargeInfo['detail_type'] ?? null,
                'detail_sub_type' => $chargeInfo['detail_sub_type'] ?? null,
                'creation_date_time' => $chargeInfo['creation_date_time'] ?? null,
                'debited_from_operation' => $chargeInfo['debited_from_operation'] ?? null,
                'order_id' => $salesInfo['order_id'] ?? null,
                'operation_id' => $salesInfo['operation_id'] ?? null,
                'sale_date_time' => $salesInfo['sale_date_time'] ?? null,
                'sales_channel' => $salesInfo['sales_channel'] ?? null,
                'payer_nickname' => $salesInfo['payer_nickname'] ?? null,
                'state_name' => $salesInfo['state_name'] ?? null,
                'transaction_amount' => (float)($salesInfo['transaction_amount'] ?? 0),
                'sale_fee' => $salesInfo['sale_fee'] ?? null,
                'item_id' => $itemsInfo['item_id'] ?? null,
                'item_title' => $itemsInfo['item_title'] ?? null,
                'item_type' => $itemsInfo['item_type'] ?? null,
                'item_category' => $itemsInfo['item_category'] ?? null,
                'item_price' => (float)($itemsInfo['item_price'] ?? 0),
                'item_amount' => (int)($itemsInfo['item_amount'] ?? 0),
                'shipping_id' => $shippingInfo['shipping_id'] ?? null,
                'receiver_shipping_cost' => (float)($shippingInfo['receiver_shipping_cost'] ?? 0),
                'discount_amount' => (float)($discountInfo['discount_amount'] ?? 0),
                'discount_reason' => $discountInfo['discount_reason'] ?? null,
                'charge_amount_without_discount' => (float)($discountInfo['charge_amount_without_discount'] ?? 0),
            ];
        }

        return [
            'results' => $results,
            'total' => $response['total'] ?? count($results),
            'last_id' => $response['last_id'] ?? null,
            'offset' => $response['offset'] ?? 0,
            'limit' => $response['limit'] ?? $limit,
            'period' => $periodKey,
            'document_type' => $documentType,
        ];
    }

    /**
     * Obtém detalhes de faturamento do Mercado Pago
     * Endpoint: GET /billing/integration/periods/key/{period}/group/MP/details
     * 
     * @param string $periodKey Período no formato YYYY-MM-01
     * @param string $documentType Tipo de documento: BILL ou CREDIT_NOTE
     * @param int $limit Limite de resultados
     * @param int $fromId ID inicial para paginação
     * @return array Detalhes de faturamento MP
     */
    public function getMercadoPagoBillingDetails(
        string $periodKey,
        string $documentType = 'BILL',
        int $limit = 150,
        int $fromId = 0
    ): array {
        $client = $this->getClient();

        $params = [
            'document_type' => $documentType,
            'limit' => min(1000, $limit),
        ];

        if ($fromId > 0) {
            $params['from_id'] = $fromId;
        }

        $response = $client->get(
            "/billing/integration/periods/key/{$periodKey}/group/MP/details",
            $params
        );

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar detalhes de faturamento MP',
                'results' => [],
            ];
        }

        $results = [];
        foreach ($response['results'] ?? [] as $item) {
            $chargeInfo = $item['charge_info'] ?? [];
            $operationInfo = $item['operation_info'] ?? [];
            $perceptionInfo = $item['perception_info'] ?? [];

            $results[] = [
                'detail_id' => $chargeInfo['detail_id'] ?? null,
                'movement_id' => $chargeInfo['movement_id'] ?? null,
                'legal_document_number' => $chargeInfo['legal_document_number'] ?? null,
                'legal_document_status' => $chargeInfo['legal_document_status'] ?? null,
                'transaction_detail' => $chargeInfo['transaction_detail'] ?? null,
                'detail_amount' => (float)($chargeInfo['detail_amount'] ?? 0),
                'detail_type' => $chargeInfo['detail_type'] ?? null,
                'detail_sub_type' => $chargeInfo['detail_sub_type'] ?? null,
                'creation_date_time' => $chargeInfo['creation_date_time'] ?? null,
                'debited_from_operation' => $chargeInfo['debited_from_operation'] ?? null,
                'status' => $chargeInfo['status'] ?? null,
                'status_description' => $chargeInfo['status_description'] ?? null,
                'operation_type' => $operationInfo['operation_type'] ?? null,
                'operation_type_description' => $operationInfo['operation_type_description'] ?? null,
                'reference_id' => $operationInfo['reference_id'] ?? null,
                'sales_channel' => $operationInfo['sales_channel'] ?? null,
                'external_reference' => $operationInfo['external_reference'] ?? null,
                'payer_nickname' => $operationInfo['payer_nickname'] ?? null,
                'transaction_amount' => (float)($operationInfo['transaction_amount'] ?? 0),
                'store_id' => $operationInfo['store_id'] ?? null,
                'store_name' => $operationInfo['store_name'] ?? null,
                'perception_aliquot' => $perceptionInfo['aliquot'] ?? null,
                'perception_taxable_amount' => (float)($perceptionInfo['taxable_amount'] ?? 0),
            ];
        }

        return [
            'results' => $results,
            'total' => $response['total'] ?? count($results),
            'last_id' => $response['last_id'] ?? null,
            'period' => $periodKey,
            'document_type' => $documentType,
        ];
    }

    /**
     * Obtém relatório de faturamento por order/pack específico
     * Endpoint: GET /billing/integration/group/ML/order/details
     * 
     * @param array $orderIds Array de IDs de orders
     * @param string|null $packId ID do pack (opcional)
     * @return array Detalhes de faturamento por order
     */
    public function getBillingByOrder(array $orderIds, ?string $packId = null): array
    {
        $client = $this->getClient();

        $params = [];
        if (!empty($orderIds)) {
            $params['order_ids'] = implode(',', $orderIds);
        }
        if ($packId) {
            $params['pack_id'] = $packId;
        }

        $response = $client->get('/billing/integration/group/ML/order/details', $params);

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar faturamento por order',
                'results' => [],
            ];
        }

        $results = [];
        foreach ($response['results'] ?? [] as $orderData) {
            $orderId = $orderData['order_id'] ?? null;
            $paymentInfo = $orderData['payment_info'][0] ?? [];
            $saleFee = $orderData['sale_fee'] ?? [];
            $details = $orderData['details'] ?? [];

            $charges = [];
            foreach ($details as $detail) {
                $chargeInfo = $detail['charge_info'] ?? [];
                $salesInfo = $detail['sales_info'][0] ?? [];
                $itemsInfo = $detail['items_info'][0] ?? [];
                $discountInfo = $detail['discount_info'] ?? [];

                $charges[] = [
                    'detail_id' => $chargeInfo['detail_id'] ?? null,
                    'transaction_detail' => $chargeInfo['transaction_detail'] ?? null,
                    'detail_amount' => (float)($chargeInfo['detail_amount'] ?? 0),
                    'detail_type' => $chargeInfo['detail_type'] ?? null,
                    'detail_sub_type' => $chargeInfo['detail_sub_type'] ?? null,
                    'debited_from_operation' => $chargeInfo['debited_from_operation'] ?? null,
                    'item_id' => $itemsInfo['item_id'] ?? null,
                    'item_title' => $itemsInfo['item_title'] ?? null,
                    'discount_amount' => (float)($discountInfo['discount_amount'] ?? 0),
                    'discount_reason' => $discountInfo['discount_reason'] ?? null,
                ];
            }

            // Extrair informações de impostos (tax_details)
            $taxDetails = [];
            foreach ($paymentInfo['tax_details'] ?? [] as $tax) {
                $taxDetails[] = [
                    'type' => $tax['mov_detail'] ?? null,
                    'entity' => $tax['mov_financial_entity'] ?? null,
                    'amount' => (float)($tax['original_amount'] ?? 0),
                    'refunded' => (float)($tax['refunded_amount'] ?? 0),
                    'status' => $tax['tax_status'] ?? null,
                ];
            }

            $results[] = [
                'order_id' => $orderId,
                'payment' => [
                    'payment_id' => $paymentInfo['payment_id'] ?? null,
                    'status' => $paymentInfo['status'] ?? null,
                    'payment_method_id' => $paymentInfo['payment_method_id'] ?? null,
                    'payment_type_id' => $paymentInfo['payment_type_id'] ?? null,
                    'date_approved' => $paymentInfo['date_approved'] ?? null,
                    'date_created' => $paymentInfo['date_created'] ?? null,
                    'money_release_date' => $paymentInfo['money_release_date'] ?? null,
                    'money_release_days' => (int)($paymentInfo['money_release_days'] ?? 0),
                    'money_release_status' => $paymentInfo['money_release_status'] ?? null,
                ],
                'sale_fee' => [
                    'gross' => (float)($saleFee['gross'] ?? 0),
                    'net' => (float)($saleFee['net'] ?? 0),
                    'rebate' => (float)($saleFee['rebate'] ?? 0),
                    'discount' => (float)($saleFee['discount'] ?? 0),
                    'discount_reason' => $saleFee['discount_reason'] ?? null,
                ],
                'taxes' => $taxDetails,
                'charges' => $charges,
            ];
        }

        return [
            'results' => $results,
            'total' => count($results),
        ];
    }

    /**
     * Obtém detalhes de fulfillment (envios Full)
     * Endpoint: GET /billing/integration/periods/key/{period}/group/ML/full/details
     * 
     * @param string $periodKey Período no formato YYYY-MM-01
     * @param string $documentType Tipo de documento: BILL ou CREDIT_NOTE
     * @param int $limit Limite de resultados
     * @return array Detalhes de fulfillment
     */
    public function getFulfillmentBillingDetails(
        string $periodKey,
        string $documentType = 'BILL',
        int $limit = 150
    ): array {
        $client = $this->getClient();

        $params = [
            'document_type' => $documentType,
            'limit' => min(1000, $limit),
        ];

        $response = $client->get(
            "/billing/integration/periods/key/{$periodKey}/group/ML/full/details",
            $params
        );

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar detalhes de fulfillment',
                'results' => [],
            ];
        }

        $results = [];
        foreach ($response['results'] ?? [] as $item) {
            $chargeInfo = $item['charge_info'] ?? [];
            $fulfillmentInfo = $item['fulfillment_info'] ?? [];

            $results[] = [
                'detail_id' => $chargeInfo['detail_id'] ?? null,
                'legal_document_number' => $chargeInfo['legal_document_number'] ?? null,
                'transaction_detail' => $chargeInfo['transaction_detail'] ?? null,
                'detail_amount' => (float)($chargeInfo['detail_amount'] ?? 0),
                'detail_type' => $chargeInfo['detail_type'] ?? null,
                'detail_sub_type' => $chargeInfo['detail_sub_type'] ?? null,
                'concept_type' => $chargeInfo['concept_type'] ?? null,
                'creation_date_time' => $chargeInfo['creation_date_time'] ?? null,
                'fulfillment_type' => $fulfillmentInfo['type'] ?? null,
                'amount_per_unit' => (float)($fulfillmentInfo['amount_per_unit'] ?? 0),
                'fulfillment_amount' => (float)($fulfillmentInfo['amount'] ?? 0),
                'sku' => $fulfillmentInfo['sku'] ?? null,
                'ean' => $fulfillmentInfo['ean'] ?? null,
                'item_id' => $fulfillmentInfo['item_id'] ?? null,
                'item_title' => $fulfillmentInfo['item_title'] ?? null,
                'variation' => $fulfillmentInfo['variation'] ?? null,
                'quantity' => (int)($fulfillmentInfo['quantity'] ?? 0),
                'volume_type' => $fulfillmentInfo['volume_type'] ?? null,
                'inventory_id' => $fulfillmentInfo['inventory_id'] ?? null,
                'space' => $fulfillmentInfo['space'] ?? null,
                'inbound_id' => $fulfillmentInfo['inbound_id'] ?? null,
            ];
        }

        return [
            'results' => $results,
            'total' => $response['total'] ?? count($results),
            'last_id' => $response['last_id'] ?? null,
            'period' => $periodKey,
            'document_type' => $documentType,
        ];
    }

    /**
     * Obtém detalhes de Mercado Envíos Flex
     * Endpoint: GET /billing/integration/periods/key/{period}/group/ML/flex/details
     * 
     * @param string $periodKey Período no formato YYYY-MM-01
     * @param string $documentType Tipo de documento: BILL ou CREDIT_NOTE
     * @param int $limit Limite de resultados
     * @return array Detalhes de envios Flex
     */
    public function getFlexShippingBillingDetails(
        string $periodKey,
        string $documentType = 'BILL',
        int $limit = 150
    ): array {
        $client = $this->getClient();

        $params = [
            'document_type' => $documentType,
            'limit' => min(1000, $limit),
        ];

        $response = $client->get(
            "/billing/integration/periods/key/{$periodKey}/group/ML/flex/details",
            $params
        );

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar detalhes de Flex',
                'results' => [],
            ];
        }

        $results = [];
        foreach ($response['results'] ?? [] as $item) {
            $chargeInfo = $item['charge_info'] ?? [];
            $shippingInfo = $item['shipping_info'] ?? [];
            $orderInfo = $shippingInfo['order'] ?? [];

            $results[] = [
                'detail_id' => $chargeInfo['detail_id'] ?? null,
                'detail_associated_id' => $chargeInfo['detail_associated_id'] ?? null,
                'legal_document_number' => $chargeInfo['legal_document_number'] ?? null,
                'transaction_detail' => $chargeInfo['transaction_detail'] ?? null,
                'detail_amount' => (float)($chargeInfo['detail_amount'] ?? 0),
                'detail_type' => $chargeInfo['detail_type'] ?? null,
                'detail_sub_type' => $chargeInfo['detail_sub_type'] ?? null,
                'concept_type' => $chargeInfo['concept_type'] ?? null,
                'creation_date_time' => $chargeInfo['creation_date_time'] ?? null,
                'shipping_id' => $shippingInfo['shipping_id'] ?? null,
                'receiver_nickname' => $shippingInfo['receiver_nickname'] ?? null,
                'pack_id' => $shippingInfo['pack_id'] ?? null,
                'receiver_shipping_cost' => (float)($shippingInfo['receiver_shipping_cost'] ?? 0),
                'order_id' => $orderInfo['order_id'] ?? null,
                'order_date_created' => $orderInfo['date_created'] ?? null,
                'order_total_amount' => (float)($orderInfo['total_amount'] ?? 0),
                'payment_id' => $orderInfo['payment_id'] ?? null,
                'buyer_nickname' => $orderInfo['buyer_nickname'] ?? null,
            ];
        }

        return [
            'results' => $results,
            'total' => $response['total'] ?? count($results),
            'last_id' => $response['last_id'] ?? null,
            'period' => $periodKey,
            'document_type' => $documentType,
        ];
    }

    /**
     * Obtém resumo consolidado de faturamento do período
     * Combina dados de ML, MP, Full e Flex
     * 
     * @param string $periodKey Período no formato YYYY-MM-01
     * @return array Resumo consolidado
     */
    public function getBillingPeriodSummary(string $periodKey): array
    {
        // Buscar dados de todas as fontes
        $mlBilling = $this->getBillingDetails($periodKey, 'BILL', 1000);
        $mpBilling = $this->getMercadoPagoBillingDetails($periodKey, 'BILL', 1000);
        $fullBilling = $this->getFulfillmentBillingDetails($periodKey, 'BILL', 1000);
        $flexBilling = $this->getFlexShippingBillingDetails($periodKey, 'BILL', 1000);

        // Calcular totais de ML
        $mlTotal = 0;
        $mlChargeTypes = [];
        foreach ($mlBilling['results'] ?? [] as $item) {
            $amount = $item['detail_amount'] ?? 0;
            $mlTotal += $amount;
            $subType = $item['detail_sub_type'] ?? 'OTHER';
            $mlChargeTypes[$subType] = ($mlChargeTypes[$subType] ?? 0) + $amount;
        }

        // Calcular totais de MP
        $mpTotal = 0;
        foreach ($mpBilling['results'] ?? [] as $item) {
            $mpTotal += $item['detail_amount'] ?? 0;
        }

        // Calcular totais de Fulfillment
        $fullTotal = 0;
        $fullTypes = [];
        foreach ($fullBilling['results'] ?? [] as $item) {
            $amount = $item['detail_amount'] ?? 0;
            $fullTotal += $amount;
            $type = $item['fulfillment_type'] ?? 'OTHER';
            $fullTypes[$type] = ($fullTypes[$type] ?? 0) + $amount;
        }

        // Calcular totais de Flex
        $flexTotal = 0;
        foreach ($flexBilling['results'] ?? [] as $item) {
            $flexTotal += $item['detail_amount'] ?? 0;
        }

        $grandTotal = $mlTotal + $mpTotal + $fullTotal + $flexTotal;

        return [
            'period' => $periodKey,
            'summary' => [
                'mercado_libre' => [
                    'total' => round($mlTotal, 2),
                    'count' => count($mlBilling['results'] ?? []),
                    'by_type' => $mlChargeTypes,
                ],
                'mercado_pago' => [
                    'total' => round($mpTotal, 2),
                    'count' => count($mpBilling['results'] ?? []),
                ],
                'fulfillment' => [
                    'total' => round($fullTotal, 2),
                    'count' => count($fullBilling['results'] ?? []),
                    'by_type' => $fullTypes,
                ],
                'flex_shipping' => [
                    'total' => round($flexTotal, 2),
                    'count' => count($flexBilling['results'] ?? []),
                ],
                'grand_total' => round($grandTotal, 2),
            ],
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Obtém dados de informação fiscal do comprador para faturamento
     * Endpoint: GET /orders/billing-info/{site_id}/{billing_info_id}
     * 
     * @param string $orderId ID da order para obter billing_info_id
     * @return array Dados fiscais do comprador
     */
    public function getBuyerBillingInfo(string $orderId): array
    {
        $client = $this->getClient();

        // Primeiro, obter a order para pegar o billing_info_id
        $orderResponse = $client->get("/orders/{$orderId}");

        if (isset($orderResponse['error'])) {
            return [
                'error' => $orderResponse['message'] ?? 'Order não encontrada',
                'data' => null,
            ];
        }

        $billingInfoId = $orderResponse['buyer']['billing_info']['doc_number'] ?? null;
        $siteId = 'MLB'; // Brasil

        // Tentar obter do billing_info da order
        $billingInfo = $orderResponse['buyer']['billing_info'] ?? [];

        if (empty($billingInfo)) {
            return [
                'error' => 'Dados fiscais não disponíveis para esta order',
                'data' => null,
            ];
        }

        // Retornar dados disponíveis
        return [
            'order_id' => $orderId,
            'buyer_id' => $orderResponse['buyer']['id'] ?? null,
            'buyer_nickname' => $orderResponse['buyer']['nickname'] ?? null,
            'billing_info' => [
                'doc_type' => $billingInfo['doc_type'] ?? null,
                'doc_number' => $billingInfo['doc_number'] ?? null,
                'first_name' => $billingInfo['first_name'] ?? null,
                'last_name' => $billingInfo['last_name'] ?? null,
                'additional_info' => $billingInfo['additional_info'] ?? [],
            ],
            'shipping_address' => $orderResponse['shipping']['receiver_address'] ?? [],
        ];
    }

    /**
     * Obtém detalhes de taxas específicas de uma venda (sale_fee)
     * Usado para obter breakdown detalhado das taxas do ML
     * 
     * @param string $orderId ID da order
     * @return array Detalhes das taxas
     */
    public function getOrderSaleFeeDetails(string $orderId): array
    {
        $client = $this->getClient();

        // Obter order para extrair sale_fee e marketplace_fee
        $orderResponse = $client->get("/orders/{$orderId}");

        if (isset($orderResponse['error'])) {
            return [
                'error' => $orderResponse['message'] ?? 'Order não encontrada',
                'data' => null,
            ];
        }

        $totalAmount = (float)($orderResponse['total_amount'] ?? 0);
        $payments = $orderResponse['payments'] ?? [];

        $paymentDetails = [];
        $totalFees = 0;
        $totalReceived = 0;

        foreach ($payments as $payment) {
            $paymentId = $payment['id'] ?? null;
            $transactionAmount = (float)($payment['transaction_amount'] ?? 0);

            // Buscar detalhes do pagamento
            $feeDetails = [];
            if ($paymentId) {
                $paymentData = $this->getPaymentDetails($paymentId);
                $feeDetails = $paymentData['fee_details'] ?? [];
                $totalReceived += (float)($paymentData['net_received_amount'] ?? 0);
            }

            $fees = 0;
            $feeBreakdown = [];
            foreach ($feeDetails as $fee) {
                $feeAmount = (float)($fee['amount'] ?? 0);
                $fees += $feeAmount;
                $feeBreakdown[] = [
                    'type' => $fee['type'] ?? 'unknown',
                    'fee_payer' => $fee['fee_payer'] ?? null,
                    'amount' => $feeAmount,
                ];
            }
            $totalFees += $fees;

            $paymentDetails[] = [
                'payment_id' => $paymentId,
                'status' => $payment['status'] ?? null,
                'transaction_amount' => $transactionAmount,
                'marketplace_fee' => (float)($payment['marketplace_fee'] ?? 0),
                'fee_details' => $feeBreakdown,
                'total_fees' => $fees,
            ];
        }

        // Obter order items para sale_fee individual
        $orderItems = $orderResponse['order_items'] ?? [];
        $itemFees = [];
        foreach ($orderItems as $orderItem) {
            $itemFees[] = [
                'item_id' => $orderItem['item']['id'] ?? null,
                'title' => $orderItem['item']['title'] ?? null,
                'quantity' => (int)($orderItem['quantity'] ?? 1),
                'unit_price' => (float)($orderItem['unit_price'] ?? 0),
                'sale_fee' => (float)($orderItem['sale_fee'] ?? 0),
                'listing_type_id' => $orderItem['listing_type_id'] ?? null,
            ];
        }

        return [
            'order_id' => $orderId,
            'total_amount' => $totalAmount,
            'total_fees' => round($totalFees, 2),
            'net_received' => round($totalReceived, 2),
            'effective_fee_rate' => $totalAmount > 0 ? round(($totalFees / $totalAmount) * 100, 2) : 0,
            'payments' => $paymentDetails,
            'items' => $itemFees,
        ];
    }

    /**
     * Gera relatório de conciliação para um período
     * Compara dados do ML com dados locais para identificar divergências
     * 
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @return array Relatório de conciliação
     */
    public function generateReconciliationReport(string $startDate, string $endDate): array
    {
        // Dados da API
        $periodKey = date('Y-m-01', strtotime($startDate));
        $apiBilling = $this->getBillingDetails($periodKey, 'BILL', 1000);

        // Agrupar dados da API por order_id
        $apiByOrder = [];
        foreach ($apiBilling['results'] ?? [] as $item) {
            $orderId = $item['order_id'] ?? null;
            if ($orderId) {
                if (!isset($apiByOrder[$orderId])) {
                    $apiByOrder[$orderId] = [
                        'charges' => 0,
                        'transaction_amount' => $item['transaction_amount'] ?? 0,
                        'details' => [],
                    ];
                }
                $apiByOrder[$orderId]['charges'] += $item['detail_amount'] ?? 0;
                $apiByOrder[$orderId]['details'][] = $item;
            }
        }

        // Dados locais
        $whereConditions = [
            'date_created BETWEEN :start AND :end',
        ];
        $params = [':start' => $startDate, ':end' => $endDate . ' 23:59:59'];

        if ($this->accountId) {
            $whereConditions[] = 'ml_account_id = :account_id';
            $params[':account_id'] = $this->accountId;
        }

        $whereSql = implode(' AND ', $whereConditions);

        $sql = "SELECT ml_order_id, total_amount, ml_commission, payment_fee, shipping_cost, status
                FROM ml_orders WHERE {$whereSql}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $localOrders = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Comparar e identificar divergências
        $divergences = [];
        $matched = 0;
        $localTotal = 0;
        $apiTotal = 0;

        foreach ($localOrders as $localOrder) {
            $orderId = $localOrder['ml_order_id'];
            $localFees = (float)$localOrder['ml_commission'] + (float)$localOrder['payment_fee'];
            $localTotal += $localFees;

            if (isset($apiByOrder[$orderId])) {
                $apiFees = $apiByOrder[$orderId]['charges'];
                $apiTotal += $apiFees;

                $diff = abs($localFees - $apiFees);
                if ($diff > 0.01) { // Diferença maior que 1 centavo
                    $divergences[] = [
                        'order_id' => $orderId,
                        'local_fees' => round($localFees, 2),
                        'api_fees' => round($apiFees, 2),
                        'difference' => round($diff, 2),
                        'status' => $localOrder['status'],
                    ];
                } else {
                    $matched++;
                }
                unset($apiByOrder[$orderId]);
            } else {
                $divergences[] = [
                    'order_id' => $orderId,
                    'local_fees' => round($localFees, 2),
                    'api_fees' => 0,
                    'difference' => round($localFees, 2),
                    'status' => $localOrder['status'],
                    'issue' => 'Não encontrada na API',
                ];
            }
        }

        // Orders na API mas não no local
        foreach ($apiByOrder as $orderId => $apiData) {
            $divergences[] = [
                'order_id' => $orderId,
                'local_fees' => 0,
                'api_fees' => round($apiData['charges'], 2),
                'difference' => round($apiData['charges'], 2),
                'issue' => 'Não encontrada no banco local',
            ];
            $apiTotal += $apiData['charges'];
        }

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => [
                'total_orders_local' => count($localOrders),
                'total_orders_api' => count($apiBilling['results'] ?? []),
                'matched' => $matched,
                'divergences_count' => count($divergences),
                'local_total_fees' => round($localTotal, 2),
                'api_total_fees' => round($apiTotal, 2),
                'total_difference' => round(abs($localTotal - $apiTotal), 2),
            ],
            'divergences' => array_slice($divergences, 0, 100), // Limitar a 100
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Obtém resumo de percepciones (impostos retidos) do período
     * Endpoint: GET /billing/integration/periods/key/{period}/perceptions/summary
     * Aplica apenas para Argentina
     * 
     * @param string $periodKey Período no formato YYYY-MM-01
     * @param string $group Grupo: ML ou MP
     * @return array Resumo de percepciones
     */
    public function getPerceptionsSummary(string $periodKey, string $group = 'ML'): array
    {
        $client = $this->getClient();

        $params = ['group' => $group];

        $response = $client->get(
            "/billing/integration/periods/key/{$periodKey}/perceptions/summary",
            $params
        );

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar percepciones',
                'results' => [],
            ];
        }

        $results = [];
        foreach ($response['summary'] ?? [] as $item) {
            $results[] = [
                'document_id' => $item['document_id'] ?? null,
                'society' => $item['society'] ?? null,
                'legal_document_number' => $item['legal_document_number'] ?? null,
                'user_fiscal_condition' => $item['user_fiscal_condition'] ?? null,
                'amount' => (float)($item['amount'] ?? 0),
                'regimen_tax_type' => $item['regimen_tax_type'] ?? null,
                'regimen_tax_type_description' => $item['regimen_tax_type_description'] ?? null,
                'taxable_amount' => (float)($item['taxable_amount'] ?? 0),
                'aliquot' => (float)($item['aliquot'] ?? 0),
                'coefficient' => (float)($item['coefficient'] ?? 1),
                'perception_charge_number' => $item['perception_charge_number'] ?? null,
                'tax_type' => $item['tax_type'] ?? null,
                'tax_type_description' => $item['tax_type_description'] ?? null,
                'bill_date' => $item['bill_date'] ?? null,
                'status' => $item['status'] ?? null,
                'status_description' => $item['status_description'] ?? null,
                'tax_ids' => $item['tax_ids'] ?? [],
            ];
        }

        return [
            'results' => $results,
            'total' => count($results),
            'period' => $periodKey,
            'group' => $group,
        ];
    }

    /**
     * Obtém detalhes de uma percepção específica (impostos retidos)
     * Endpoint: GET /billing/integration/group/{group}/perceptions/details
     * 
     * @param string $group Grupo: ML ou MP
     * @param int $documentId ID do documento
     * @param string $taxType Tipo de imposto (CIVA, CIVAMP, etc)
     * @param int|null $taxId ID do imposto (necessário para MP)
     * @param int $limit Limite de resultados
     * @return array Detalhes das percepciones
     */
    public function getPerceptionsDetails(
        string $group,
        int $documentId,
        string $taxType,
        ?int $taxId = null,
        int $limit = 150
    ): array {
        $client = $this->getClient();

        $params = [
            'document_id' => $documentId,
            'tax_type' => $taxType,
            'limit' => min(1000, $limit),
        ];

        if ($taxId !== null && $group === 'MP') {
            $params['tax_id'] = $taxId;
        }

        $response = $client->get(
            "/billing/integration/group/{$group}/perceptions/details",
            $params
        );

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar detalhes de percepciones',
                'results' => [],
            ];
        }

        $results = [];
        foreach ($response['results'] ?? [] as $item) {
            $results[] = [
                'detail_id' => $item['detail_id'] ?? null,
                'date_created' => $item['date_created'] ?? null,
                'taxable_amount' => (float)($item['taxable_amount'] ?? 0),
                'aliquot' => (float)($item['aliquot'] ?? 0),
                'tax_amount' => (float)($item['tax_amount'] ?? 0),
                'transaction_detail' => $item['transaction_detail'] ?? null,
                'transaction_detail_description' => $item['transaction_detail_description'] ?? null,
                'charge_bonified_id' => $item['charge_bonified_id'] ?? null,
                'amount' => (float)($item['amount'] ?? 0),
                'gross_amount' => (float)($item['gross_amount'] ?? 0),
                'detail_type' => $item['detail_type'] ?? null,
                'detail_type_description' => $item['detail_type_description'] ?? null,
                // Campos adicionais para Régimen Especial
                'publish_number' => $item['publish_number'] ?? null,
                'publish_title' => $item['publish_title'] ?? null,
                'sale_date' => $item['sale_date'] ?? null,
                'sale_number' => $item['sale_number'] ?? null,
                'buyer_name' => $item['buyer_name'] ?? null,
                'buyer_state_name' => $item['buyer_state_name'] ?? null,
                // Campo adicional para Régimen Tucumán
                'coefficient' => $item['coefficient'] ?? null,
                // Campos adicionais para MP
                'movement_id' => $item['movement_id'] ?? null,
                'reference_id' => $item['reference_id'] ?? null,
            ];
        }

        return [
            'results' => $results,
            'total' => $response['total'] ?? count($results),
            'offset' => $response['offset'] ?? 0,
            'group' => $group,
            'document_id' => $documentId,
            'tax_type' => $taxType,
        ];
    }

    /**
     * Obtém relatório de pagamentos realizados no período
     * Endpoint: GET /billing/integration/periods/key/{period}/group/ML/payment/details
     * 
     * @param string $periodKey Período no formato YYYY-MM-01
     * @param int $limit Limite de resultados
     * @param int $offset Offset para paginação
     * @return array Detalhes dos pagamentos
     */
    public function getPaymentReport(string $periodKey, int $limit = 150, int $offset = 0): array
    {
        $client = $this->getClient();

        $params = [
            'limit' => min(1000, $limit),
            'offset' => $offset,
        ];

        $response = $client->get(
            "/billing/integration/periods/key/{$periodKey}/group/ML/payment/details",
            $params
        );

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar relatório de pagamentos',
                'results' => [],
            ];
        }

        $results = [];
        foreach ($response['results'] ?? [$response] as $item) {
            $paymentInfo = $item['payment_info'] ?? $item;
            $results[] = [
                'payment_id' => $paymentInfo['payment_id'] ?? null,
                'credit_note_number' => $paymentInfo['credit_note_number'] ?? null,
                'payment_date' => $paymentInfo['payment_date'] ?? null,
                'payment_type' => $paymentInfo['payment_type'] ?? null,
                'payment_type_description' => $paymentInfo['payment_type_description'] ?? null,
                'payment_method' => $paymentInfo['payment_method'] ?? null,
                'payment_method_description' => $paymentInfo['payment_method_description'] ?? null,
                'payment_status' => $paymentInfo['payment_status'] ?? null,
                'payment_status_description' => $paymentInfo['payment_status_description'] ?? null,
                'payment_amount' => (float)($paymentInfo['payment_amount'] ?? 0),
                'amount_in_this_period' => (float)($paymentInfo['amount_in_this_period'] ?? 0),
                'amount_in_other_period' => (float)($paymentInfo['amount_in_other_period'] ?? 0),
                'remaining_amount' => (float)($paymentInfo['remaining_amount'] ?? 0),
                'return_amount' => (float)($paymentInfo['return_amount'] ?? 0),
            ];
        }

        return [
            'results' => $results,
            'total' => count($results),
            'period' => $periodKey,
        ];
    }

    /**
     * Obtém detalhes de cargos associados a um pagamento
     * Endpoint: GET /billing/integration/payment/{payment_id}/charges
     * 
     * @param string $paymentId ID do pagamento
     * @param int $limit Limite de resultados
     * @return array Detalhes dos cargos
     */
    public function getPaymentChargesDetail(string $paymentId, int $limit = 150): array
    {
        $client = $this->getClient();

        $params = ['limit' => min(1000, $limit)];

        $response = $client->get(
            "/billing/integration/payment/{$paymentId}/charges",
            $params
        );

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar detalhes do pagamento',
                'results' => [],
            ];
        }

        $results = [];
        foreach ($response['payment_details'] ?? [] as $item) {
            $paymentInfo = $item['payment_info'] ?? [];
            $chargeInfo = $item['charge_info'] ?? [];

            $results[] = [
                'payment_id' => $paymentInfo['payment_id'] ?? null,
                'payment_date' => $paymentInfo['payment_date'] ?? null,
                'association_amount' => (float)($paymentInfo['association_amount'] ?? 0),
                'payment_amount' => (float)($paymentInfo['payment_amount'] ?? 0),
                'charge_detail_id' => $chargeInfo['detail_id'] ?? null,
                'charge_description' => $chargeInfo['detail_description'] ?? null,
                'charge_date' => $chargeInfo['detail_date'] ?? null,
            ];
        }

        return [
            'results' => $results,
            'total' => count($results),
            'payment_id' => $paymentId,
        ];
    }

    /**
     * Obtém detalhes do envio de uma ordem (custos reais de frete)
     * Endpoint: GET /shipments/{shipment_id}
     * 
     * @param string $shipmentId ID do envio
     * @return array Detalhes do envio com custos
     */
    public function getShipmentCosts(string $shipmentId): array
    {
        $client = $this->getClient();

        $response = $client->get("/shipments/{$shipmentId}");

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Envio não encontrado',
                'data' => null,
            ];
        }

        $senderCost = $response['cost_components']['special_discount'] ?? 0;
        $sellerCost = (float)($response['seller_cost'] ?? 0);
        $baseCost = (float)($response['base_cost'] ?? 0);
        $listCost = (float)($response['list_cost'] ?? 0);

        return [
            'shipment_id' => $response['id'] ?? $shipmentId,
            'status' => $response['status'] ?? null,
            'substatus' => $response['substatus'] ?? null,
            'mode' => $response['mode'] ?? null,
            'logistic_type' => $response['logistic_type'] ?? null,
            'shipping_option' => [
                'name' => $response['shipping_option']['name'] ?? null,
                'shipping_method_id' => $response['shipping_option']['shipping_method_id'] ?? null,
                'delivery_type' => $response['shipping_option']['delivery_type'] ?? null,
            ],
            'costs' => [
                'base_cost' => $baseCost,
                'list_cost' => $listCost,
                'seller_cost' => $sellerCost,
                'receiver_cost' => (float)($response['receiver_cost'] ?? 0),
                'free_shipping' => (bool)($response['free_shipping'] ?? false),
            ],
            'dimensions' => [
                'height' => $response['dimensions']['height'] ?? null,
                'width' => $response['dimensions']['width'] ?? null,
                'length' => $response['dimensions']['length'] ?? null,
                'weight' => $response['dimensions']['weight'] ?? null,
            ],
            'date_created' => $response['date_created'] ?? null,
            'date_first_printed' => $response['date_first_printed'] ?? null,
            'tracking_number' => $response['tracking_number'] ?? null,
            'carrier' => $response['service_id'] ?? null,
        ];
    }

    /**
     * Obtém lista de envios do vendedor com custos
     * Endpoint: GET /orders/{order_id}/shipments
     * 
     * @param string $orderId ID da ordem
     * @return array Lista de envios da ordem
     */
    public function getOrderShipments(string $orderId): array
    {
        $client = $this->getClient();

        $response = $client->get("/orders/{$orderId}/shipments");

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Envios não encontrados',
                'results' => [],
            ];
        }

        $shipments = [];
        $shipmentsData = is_array($response) && isset($response[0]) ? $response : [$response];

        foreach ($shipmentsData as $shipment) {
            if (empty($shipment['id'])) {
                continue;
            }

            $shipments[] = [
                'shipment_id' => $shipment['id'] ?? null,
                'status' => $shipment['status'] ?? null,
                'mode' => $shipment['mode'] ?? null,
                'logistic_type' => $shipment['logistic_type'] ?? null,
                'seller_cost' => (float)($shipment['seller_cost'] ?? 0),
                'receiver_cost' => (float)($shipment['receiver_cost'] ?? 0),
                'free_shipping' => (bool)($shipment['free_shipping'] ?? false),
                'date_created' => $shipment['date_created'] ?? null,
            ];
        }

        return [
            'order_id' => $orderId,
            'results' => $shipments,
            'total' => count($shipments),
        ];
    }

    /**
     * Obtém detalhes completos de custos por vender (comissões)
     * Endpoint: GET /items/{item_id}/sale_terms
     * 
     * @param string $itemId ID do item
     * @return array Termos de venda e comissões
     */
    public function getItemSaleTerms(string $itemId): array
    {
        $client = $this->getClient();

        // Obter dados do item
        $itemResponse = $client->get("/items/{$itemId}");

        if (isset($itemResponse['error'])) {
            return [
                'error' => $itemResponse['message'] ?? 'Item não encontrado',
                'data' => null,
            ];
        }

        $listingTypeId = $itemResponse['listing_type_id'] ?? 'gold_special';
        $categoryId = $itemResponse['category_id'] ?? null;
        $price = (float)($itemResponse['price'] ?? 0);

        // Buscar informações de custos da categoria
        $categoryFees = [];
        if ($categoryId) {
            $catResponse = $client->get("/categories/{$categoryId}");
            if (!isset($catResponse['error'])) {
                $categoryFees = [
                    'category_id' => $categoryId,
                    'category_name' => $catResponse['name'] ?? null,
                ];
            }
        }

        // Obter listing type fee
        $listingFee = $this->getListingTypeFee($listingTypeId);

        return [
            'item_id' => $itemId,
            'title' => $itemResponse['title'] ?? null,
            'price' => $price,
            'listing_type_id' => $listingTypeId,
            'category' => $categoryFees,
            'estimated_fees' => [
                'listing_fee_percentage' => $listingFee,
                'estimated_ml_commission' => round($price * ($listingFee / 100), 2),
            ],
            'shipping' => [
                'free_shipping' => (bool)($itemResponse['shipping']['free_shipping'] ?? false),
                'logistic_type' => $itemResponse['shipping']['logistic_type'] ?? null,
            ],
        ];
    }

    /**
     * Obtém percentual de comissão por tipo de listagem
     */
    private function getListingTypeFee(string $listingTypeId): float
    {
        // Valores aproximados para Brasil (MLB) - podem variar por categoria
        $fees = [
            'gold_pro' => 16.0,
            'gold_special' => 13.0,
            'gold_premium' => 16.0,
            'gold' => 11.0,
            'silver' => 9.0,
            'bronze' => 5.0,
            'free' => 0.0,
        ];

        return $fees[$listingTypeId] ?? 13.0;
    }

    /**
     * Gera relatório financeiro consolidado em tempo real
     * Combina múltiplas fontes de dados da API
     * 
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @return array Relatório financeiro consolidado
     */
    public function generateRealTimeFinancialReport(string $startDate, string $endDate): array
    {
        $periodKey = date('Y-m-01', strtotime($startDate));

        // Buscar dados de múltiplas fontes em paralelo (conceitualmente)
        $balance = $this->getAccountBalance();
        $orders = $this->getOrdersFromApi($startDate, $endDate, 100);
        $mlBilling = $this->getBillingDetails($periodKey, 'BILL', 500);
        $mpBilling = $this->getMercadoPagoBillingDetails($periodKey, 'BILL', 500);
        $payments = $this->getPaymentReport($periodKey, 100);

        // Calcular métricas dos pedidos
        $orderMetrics = $this->calculateMetricsFromOrders($orders['results'] ?? []);

        // Totalizar billing
        $totalMLCharges = 0;
        $totalMPCharges = 0;
        $chargesByType = [];

        foreach ($mlBilling['results'] ?? [] as $item) {
            $amount = (float)($item['detail_amount'] ?? 0);
            $totalMLCharges += $amount;
            $subType = $item['detail_sub_type'] ?? 'OTHER';
            $chargesByType[$subType] = ($chargesByType[$subType] ?? 0) + $amount;
        }

        foreach ($mpBilling['results'] ?? [] as $item) {
            $totalMPCharges += (float)($item['detail_amount'] ?? 0);
        }

        // Totalizar pagamentos recebidos
        $totalPayments = 0;
        foreach ($payments['results'] ?? [] as $payment) {
            $totalPayments += (float)($payment['payment_amount'] ?? 0);
        }

        $grossRevenue = $orderMetrics['revenue'];
        $totalFees = $totalMLCharges + $totalMPCharges;
        $netRevenue = $grossRevenue - $totalFees;
        $margin = $grossRevenue > 0 ? ($netRevenue / $grossRevenue) * 100 : 0;

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'account' => [
                'available_balance' => $balance['available_balance'] ?? 0,
                'total_balance' => $balance['total_balance'] ?? 0,
                'reserved' => $balance['reserved'] ?? 0,
            ],
            'sales' => [
                'total_orders' => $orderMetrics['orders'],
                'gross_revenue' => round($grossRevenue, 2),
                'average_ticket' => $orderMetrics['orders'] > 0
                    ? round($grossRevenue / $orderMetrics['orders'], 2)
                    : 0,
            ],
            'fees' => [
                'mercado_libre' => round($totalMLCharges, 2),
                'mercado_pago' => round($totalMPCharges, 2),
                'total' => round($totalFees, 2),
                'by_type' => $chargesByType,
            ],
            'profitability' => [
                'net_revenue' => round($netRevenue, 2),
                'margin_percentage' => round($margin, 2),
                'fee_rate' => $grossRevenue > 0
                    ? round(($totalFees / $grossRevenue) * 100, 2)
                    : 0,
            ],
            'payments' => [
                'total_received' => round($totalPayments, 2),
                'count' => count($payments['results'] ?? []),
            ],
            'data_sources' => [
                'orders_count' => count($orders['results'] ?? []),
                'ml_billing_count' => count($mlBilling['results'] ?? []),
                'mp_billing_count' => count($mpBilling['results'] ?? []),
            ],
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Obtém métricas de desempenho de vendas por produto
     * 
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @param int $limit Limite de produtos
     * @return array Top produtos com métricas financeiras
     */
    public function getTopProductsFinancialMetrics(
        string $startDate,
        string $endDate,
        int $limit = 20
    ): array {
        $whereConditions = [
            'date_created BETWEEN :start AND :end',
            "status IN ('paid', 'delivered')",
        ];
        $params = [':start' => $startDate, ':end' => $endDate . ' 23:59:59'];

        if ($this->accountId) {
            $whereConditions[] = 'ml_account_id = :account_id';
            $params[':account_id'] = $this->accountId;
        }

        $userId = SessionHelper::getUserId();
        if ($userId) {
            $whereConditions[] = 'user_id = :user_id';
            $params[':user_id'] = $userId;
        }

        $whereSql = implode(' AND ', $whereConditions);

        $limitSql = max(1, min(200, (int)$limit));

        $sql = "SELECT 
                    JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.order_items[0].item.id')) as item_id,
                    JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.order_items[0].item.title')) as title,
                    COUNT(*) as total_sales,
                    SUM(total_amount) as total_revenue,
                    SUM(ml_commission) as total_ml_fee,
                    SUM(payment_fee) as total_payment_fee,
                    SUM(shipping_cost) as total_shipping,
                    SUM(net_profit) as total_profit,
                    AVG(total_amount) as avg_ticket
                FROM ml_orders
                WHERE {$whereSql}
                GROUP BY item_id, title
                HAVING item_id IS NOT NULL
                ORDER BY total_revenue DESC
            LIMIT {$limitSql}";

        $stmt = $this->db->prepare($sql);

        // PDO pode falhar ao bindar LIMIT/OFFSET com prepares nativos
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $products = [];
        foreach ($rows as $row) {
            $revenue = (float)($row['total_revenue'] ?? 0);
            $profit = (float)($row['total_profit'] ?? 0);
            $totalFees = (float)($row['total_ml_fee'] ?? 0)
                + (float)($row['total_payment_fee'] ?? 0);

            $products[] = [
                'item_id' => $row['item_id'],
                'title' => $row['title'] ?? 'Sem título',
                'metrics' => [
                    'total_sales' => (int)($row['total_sales'] ?? 0),
                    'total_revenue' => round($revenue, 2),
                    'avg_ticket' => round((float)($row['avg_ticket'] ?? 0), 2),
                    'total_fees' => round($totalFees, 2),
                    'total_shipping' => round((float)($row['total_shipping'] ?? 0), 2),
                    'total_profit' => round($profit, 2),
                    'profit_margin' => $revenue > 0 ? round(($profit / $revenue) * 100, 2) : 0,
                    'fee_rate' => $revenue > 0 ? round(($totalFees / $revenue) * 100, 2) : 0,
                ],
            ];
        }

        return [
            'products' => $products,
            'period' => ['start' => $startDate, 'end' => $endDate],
            'total_products' => count($products),
        ];
    }

    /**
     * Calcula ROI (Retorno sobre Investimento) por produto baseado em custo
     * 
     * @param string $itemId ID do item
     * @param float $productCost Custo do produto
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @return array Análise de ROI
     */
    public function calculateProductROI(
        string $itemId,
        float $productCost,
        string $startDate,
        string $endDate
    ): array {
        $whereConditions = [
            'date_created BETWEEN :start AND :end',
            "status IN ('paid', 'delivered')",
            "JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.order_items[0].item.id')) = :item_id",
        ];
        $params = [
            ':start' => $startDate,
            ':end' => $endDate . ' 23:59:59',
            ':item_id' => $itemId,
        ];

        if ($this->accountId) {
            $whereConditions[] = 'ml_account_id = :account_id';
            $params[':account_id'] = $this->accountId;
        }

        $whereSql = implode(' AND ', $whereConditions);

        $sql = "SELECT 
                    COUNT(*) as total_sales,
                    SUM(JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.order_items[0].quantity'))) as total_units,
                    SUM(total_amount) as total_revenue,
                    SUM(ml_commission + payment_fee + fixed_fee) as total_fees,
                    SUM(shipping_cost) as total_shipping
                FROM ml_orders
                WHERE {$whereSql}";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        $totalSales = (int)($data['total_sales'] ?? 0);
        $totalUnits = (int)($data['total_units'] ?? $totalSales);
        $totalRevenue = (float)($data['total_revenue'] ?? 0);
        $totalFees = (float)($data['total_fees'] ?? 0);
        $totalShipping = (float)($data['total_shipping'] ?? 0);

        $totalCost = $productCost * $totalUnits;
        $totalExpenses = $totalFees + $totalShipping + $totalCost;
        $netProfit = $totalRevenue - $totalExpenses;
        $roi = $totalCost > 0 ? (($netProfit / $totalCost) * 100) : 0;

        return [
            'item_id' => $itemId,
            'product_cost' => $productCost,
            'period' => ['start' => $startDate, 'end' => $endDate],
            'sales' => [
                'total_orders' => $totalSales,
                'total_units' => $totalUnits,
                'avg_units_per_order' => $totalSales > 0 ? round($totalUnits / $totalSales, 2) : 0,
            ],
            'financials' => [
                'total_revenue' => round($totalRevenue, 2),
                'total_product_cost' => round($totalCost, 2),
                'total_fees' => round($totalFees, 2),
                'total_shipping' => round($totalShipping, 2),
                'total_expenses' => round($totalExpenses, 2),
                'net_profit' => round($netProfit, 2),
            ],
            'metrics' => [
                'roi_percentage' => round($roi, 2),
                'profit_margin' => $totalRevenue > 0 ? round(($netProfit / $totalRevenue) * 100, 2) : 0,
                'profit_per_unit' => $totalUnits > 0 ? round($netProfit / $totalUnits, 2) : 0,
                'breakeven_units' => $netProfit < 0 && $productCost > 0
                    ? ceil(abs($netProfit) / $productCost)
                    : 0,
            ],
        ];
    }

    /**
     * Obtém descontos aplicados a uma ordem
     * Endpoint: GET /orders/{order_id}/discounts
     * Inclui cupons, campanhas e cashbacks
     * 
     * @param string $orderId ID da ordem
     * @return array Detalhes dos descontos
     */
    public function getOrderDiscounts(string $orderId): array
    {
        $client = $this->getClient();

        $response = $client->get("/orders/{$orderId}/discounts");

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Descontos não encontrados',
                'results' => [],
            ];
        }

        $discounts = [];
        $totalDiscount = 0;
        $sellerDiscount = 0;

        foreach ($response['details'] ?? [] as $detail) {
            $type = $detail['type'] ?? 'unknown';
            $items = [];

            foreach ($detail['items'] ?? [] as $item) {
                $total = (float)($item['amounts']['total'] ?? 0);
                $seller = (float)($item['amounts']['seller'] ?? 0);

                $items[] = [
                    'item_id' => $item['id'] ?? null,
                    'quantity' => $item['quantity'] ?? 1,
                    'total_discount' => $total,
                    'seller_discount' => $seller,
                ];

                $totalDiscount += $total;
                $sellerDiscount += $seller;
            }

            $discount = [
                'type' => $type,
                'items' => $items,
            ];

            // Adicionar dados específicos por tipo
            if ($type === 'coupon' && isset($detail['coupon'])) {
                $discount['coupon_id'] = $detail['coupon']['id'] ?? null;
            }

            if (isset($detail['supplier'])) {
                $discount['supplier'] = [
                    'campaign' => $detail['supplier']['meli_campaign'] ?? null,
                    'offer_id' => $detail['supplier']['offer_id'] ?? null,
                    'funding_mode' => $detail['supplier']['funding_mode'] ?? null,
                    'campaign_id' => $detail['supplier']['campaign_id'] ?? null,
                ];
            }

            if ($type === 'cashback' && isset($detail['cashback'])) {
                $discount['cashback_id'] = $detail['cashback']['id'] ?? null;
                if (isset($detail['counter_currency'])) {
                    $discount['counter_currency'] = [
                        'currency_id' => $detail['counter_currency']['currency_id'] ?? null,
                        'value' => $detail['counter_currency']['value'] ?? null,
                    ];
                }
            }

            $discounts[] = $discount;
        }

        return [
            'order_id' => $orderId,
            'discounts' => $discounts,
            'summary' => [
                'total_discount' => round($totalDiscount, 2),
                'seller_discount' => round($sellerDiscount, 2),
                'meli_discount' => round($totalDiscount - $sellerDiscount, 2),
            ],
            'total_discounts' => count($discounts),
        ];
    }

    /**
     * Busca reclamações (claims) do vendedor
     * Endpoint: GET /post-purchase/v1/claims/search
     * 
     * @param string $status Status: opened, closed
     * @param string|null $stage Etapa: claim, dispute, recontact
     * @param int $limit Limite de resultados
     * @return array Lista de reclamações
     */
    public function getClaims(
        string $status = 'opened',
        ?string $stage = null,
        int $limit = 30
    ): array {
        $client = $this->getClient();

        $params = [
            'status' => $status,
            'limit' => min(100, $limit),
        ];

        if ($stage) {
            $params['stage'] = $stage;
        }

        $response = $client->get('/post-purchase/v1/claims/search', $params);

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar reclamações',
                'results' => [],
            ];
        }

        $claims = [];
        foreach ($response['data'] ?? [] as $claim) {
            $players = [];
            foreach ($claim['players'] ?? [] as $player) {
                $players[] = [
                    'role' => $player['role'] ?? null,
                    'type' => $player['type'] ?? null,
                    'user_id' => $player['user_id'] ?? null,
                    'has_actions' => !empty($player['available_actions']),
                ];
            }

            $claims[] = [
                'claim_id' => $claim['id'] ?? null,
                'resource_id' => $claim['resource_id'] ?? null,
                'status' => $claim['status'] ?? null,
                'type' => $claim['type'] ?? null,
                'stage' => $claim['stage'] ?? null,
                'resource' => $claim['resource'] ?? null,
                'reason_id' => $claim['reason_id'] ?? null,
                'fulfilled' => $claim['fulfilled'] ?? null,
                'quantity_type' => $claim['quantity_type'] ?? null,
                'players' => $players,
                'site_id' => $claim['site_id'] ?? null,
                'date_created' => $claim['date_created'] ?? null,
                'last_updated' => $claim['last_updated'] ?? null,
                'resolution' => $claim['resolution'] ?? null,
            ];
        }

        return [
            'results' => $claims,
            'paging' => $response['paging'] ?? ['total' => count($claims)],
            'status_filter' => $status,
        ];
    }

    /**
     * Obtém detalhes de uma reclamação específica
     * Endpoint: GET /post-purchase/v1/claims/{claim_id}
     * 
     * @param string $claimId ID da reclamação
     * @return array Detalhes da reclamação
     */
    public function getClaimDetails(string $claimId): array
    {
        $client = $this->getClient();

        $response = $client->get("/post-purchase/v1/claims/{$claimId}");

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Reclamação não encontrada',
                'data' => null,
            ];
        }

        $players = [];
        foreach ($response['players'] ?? [] as $player) {
            $actions = [];
            foreach ($player['available_actions'] ?? [] as $action) {
                $actions[] = [
                    'action' => $action['action'] ?? null,
                    'mandatory' => $action['mandatory'] ?? false,
                    'due_date' => $action['due_date'] ?? null,
                ];
            }

            $players[] = [
                'role' => $player['role'] ?? null,
                'type' => $player['type'] ?? null,
                'user_id' => $player['user_id'] ?? null,
                'available_actions' => $actions,
            ];
        }

        return [
            'claim_id' => $response['id'] ?? $claimId,
            'resource_id' => $response['resource_id'] ?? null,
            'status' => $response['status'] ?? null,
            'type' => $response['type'] ?? null,
            'stage' => $response['stage'] ?? null,
            'claim_version' => $response['claim_version'] ?? null,
            'claimed_quantity' => $response['claimed_quantity'] ?? null,
            'parent_id' => $response['parent_id'] ?? null,
            'resource' => $response['resource'] ?? null,
            'reason_id' => $response['reason_id'] ?? null,
            'fulfilled' => $response['fulfilled'] ?? null,
            'quantity_type' => $response['quantity_type'] ?? null,
            'players' => $players,
            'resolution' => $response['resolution'] ? [
                'reason' => $response['resolution']['reason'] ?? null,
                'date_created' => $response['resolution']['date_created'] ?? null,
                'benefited' => $response['resolution']['benefited'] ?? [],
                'closed_by' => $response['resolution']['closed_by'] ?? null,
                'applied_coverage' => $response['resolution']['applied_coverage'] ?? false,
            ] : null,
            'site_id' => $response['site_id'] ?? null,
            'date_created' => $response['date_created'] ?? null,
            'last_updated' => $response['last_updated'] ?? null,
            'related_entities' => $response['related_entities'] ?? [],
        ];
    }

    /**
     * Verifica se reclamação afeta a reputação do vendedor
     * Endpoint: GET /post-purchase/v1/claims/{claim_id}/affects-reputation
     * 
     * @param string $claimId ID da reclamação
     * @return array Status de impacto na reputação
     */
    public function getClaimReputationImpact(string $claimId): array
    {
        $client = $this->getClient();

        $response = $client->get("/post-purchase/v1/claims/{$claimId}/affects-reputation");

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao verificar impacto',
                'data' => null,
            ];
        }

        return [
            'claim_id' => $claimId,
            'affects_reputation' => $response['affects_reputation'] ?? 'not_applies',
            'has_incentive' => $response['has_incentive'] ?? false,
            'due_date' => $response['due_date'] ?? null,
            'interpretation' => $this->interpretReputationImpact(
                $response['affects_reputation'] ?? 'not_applies'
            ),
        ];
    }

    /**
     * Interpreta o impacto na reputação
     */
    private function interpretReputationImpact(string $status): string
    {
        return match ($status) {
            'affected' => 'Esta reclamação AFETA sua reputação',
            'not_affected' => 'Esta reclamação NÃO afeta sua reputação',
            'not_applies' => 'Não aplicável (pagamento não vinculado a ordem)',
            default => 'Status desconhecido',
        };
    }

    /**
     * Obtém detalhes de uma devolução associada a uma reclamação
     * Endpoint: GET /post-purchase/v2/claims/{claim_id}/returns
     * 
     * @param string $claimId ID da reclamação
     * @return array Detalhes da devolução
     */
    public function getReturnDetails(string $claimId): array
    {
        $client = $this->getClient();

        $response = $client->get("/post-purchase/v2/claims/{$claimId}/returns");

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Devolução não encontrada',
                'data' => null,
            ];
        }

        $shipments = [];
        foreach ($response['shipments'] ?? [] as $shipment) {
            $destination = $shipment['destination'] ?? [];
            $address = $destination['shipping_address'] ?? [];

            $shipments[] = [
                'shipment_id' => $shipment['shipment_id'] ?? null,
                'status' => $shipment['status'] ?? null,
                'tracking_number' => $shipment['tracking_number'] ?? null,
                'type' => $shipment['type'] ?? null,
                'destination' => [
                    'name' => $destination['name'] ?? null,
                    'city' => $address['city']['name'] ?? null,
                    'state' => $address['state']['name'] ?? null,
                    'zip_code' => $address['zip_code'] ?? null,
                ],
            ];
        }

        $orders = [];
        foreach ($response['orders'] ?? [] as $order) {
            $orders[] = [
                'order_id' => $order['order_id'] ?? null,
                'item_id' => $order['item_id'] ?? null,
                'variation_id' => $order['variation_id'] ?? null,
                'context_type' => $order['context_type'] ?? null,
                'total_quantity' => $order['total_quantity'] ?? null,
                'return_quantity' => $order['return_quantity'] ?? null,
            ];
        }

        return [
            'return_id' => $response['id'] ?? null,
            'claim_id' => $claimId,
            'status' => $response['status'] ?? null,
            'subtype' => $response['subtype'] ?? null,
            'refund_at' => $response['refund_at'] ?? null,
            'status_money' => $response['status_money'] ?? null,
            'resource_type' => $response['resource_type'] ?? null,
            'resource_id' => $response['resource_id'] ?? null,
            'shipments' => $shipments,
            'orders' => $orders,
            'intermediate_check' => $response['intermediate_check'] ?? false,
            'date_created' => $response['date_created'] ?? null,
            'date_closed' => $response['date_closed'] ?? null,
            'last_updated' => $response['last_updated'] ?? null,
            'related_entities' => $response['related_entities'] ?? [],
        ];
    }

    /**
     * Obtém custo de envio de devolução
     * Endpoint: GET /post-purchase/v1/claims/{claim_id}/charges/return-cost
     * 
     * @param string $claimId ID da reclamação
     * @param bool $calculateUsd Se deve calcular em USD
     * @return array Custo da devolução
     */
    public function getReturnShippingCost(string $claimId, bool $calculateUsd = false): array
    {
        $client = $this->getClient();

        $params = [];
        if ($calculateUsd) {
            $params['calculate_amount_usd'] = 'true';
        }

        $response = $client->get(
            "/post-purchase/v1/claims/{$claimId}/charges/return-cost",
            $params
        );

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar custo de devolução',
                'data' => null,
            ];
        }

        return [
            'claim_id' => $claimId,
            'currency_id' => $response['currency_id'] ?? 'BRL',
            'amount' => (float)($response['amount'] ?? 0),
            'amount_usd' => isset($response['amount_usd']) ? (float)$response['amount_usd'] : null,
        ];
    }

    /**
     * Gera relatório consolidado de reclamações e devoluções com impacto financeiro
     * 
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @return array Relatório de reclamações
     */
    public function getClaimsFinancialReport(string $startDate, string $endDate): array
    {
        // Buscar claims abertas e fechadas
        $openedClaims = $this->getClaims('opened', null, 100);
        $closedClaims = $this->getClaims('closed', null, 100);

        $allClaims = array_merge(
            $openedClaims['results'] ?? [],
            $closedClaims['results'] ?? []
        );

        // Filtrar por período
        $filteredClaims = array_filter($allClaims, function ($claim) use ($startDate, $endDate) {
            $claimDate = $claim['date_created'] ?? null;
            if (!$claimDate) {
                return false;
            }
            $date = strtotime($claimDate);
            return $date >= strtotime($startDate) && $date <= strtotime($endDate . ' 23:59:59');
        });

        // Estatísticas
        $stats = [
            'total' => count($filteredClaims),
            'opened' => 0,
            'closed' => 0,
            'by_type' => [],
            'by_stage' => [],
            'by_reason' => [],
            'affecting_reputation' => 0,
        ];

        $claimsWithDetails = [];
        foreach ($filteredClaims as $claim) {
            $status = $claim['status'] ?? 'unknown';
            $type = $claim['type'] ?? 'unknown';
            $stage = $claim['stage'] ?? 'unknown';
            $reason = $claim['reason_id'] ?? 'unknown';

            $stats[$status] = ($stats[$status] ?? 0) + 1;
            $stats['by_type'][$type] = ($stats['by_type'][$type] ?? 0) + 1;
            $stats['by_stage'][$stage] = ($stats['by_stage'][$stage] ?? 0) + 1;
            $stats['by_reason'][$reason] = ($stats['by_reason'][$reason] ?? 0) + 1;

            // Verificar impacto na reputação (limitado para não sobrecarregar API)
            if (count($claimsWithDetails) < 10 && $status === 'opened') {
                $claimId = $claim['claim_id'] ?? null;
                if ($claimId) {
                    $impact = $this->getClaimReputationImpact($claimId);
                    if (($impact['affects_reputation'] ?? '') === 'affected') {
                        $stats['affecting_reputation']++;
                    }
                    $claim['reputation_impact'] = $impact;
                }
            }

            $claimsWithDetails[] = $claim;
        }

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'statistics' => $stats,
            'claims' => array_slice($claimsWithDetails, 0, 50),
            'summary' => [
                'resolution_rate' => $stats['total'] > 0
                    ? round(($stats['closed'] / $stats['total']) * 100, 2)
                    : 0,
                'most_common_type' => !empty($stats['by_type'])
                    ? array_keys($stats['by_type'], max($stats['by_type']))[0]
                    : null,
                'reputation_risk_count' => $stats['affecting_reputation'],
            ],
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Obtém histórico de ações de uma reclamação
     * Endpoint: GET /post-purchase/v1/claims/{claim_id}/actions-history
     * 
     * @param string $claimId ID da reclamação
     * @return array Histórico de ações
     */
    public function getClaimActionsHistory(string $claimId): array
    {
        $client = $this->getClient();

        $response = $client->get("/post-purchase/v1/claims/{$claimId}/actions-history");

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Histórico não encontrado',
                'results' => [],
            ];
        }

        $actions = [];
        foreach ($response as $action) {
            $actions[] = [
                'action_name' => $action['action_name'] ?? null,
                'player_role' => $action['player_role'] ?? null,
                'action_reason_id' => $action['action_reason_id'] ?? null,
                'claim_stage' => $action['claim_stage'] ?? null,
                'claim_status' => $action['claim_status'] ?? null,
                'date_created' => $action['date_created'] ?? null,
            ];
        }

        return [
            'claim_id' => $claimId,
            'actions' => $actions,
            'total' => count($actions),
        ];
    }

    /**
     * Obtém conversão de moeda
     * Endpoint: GET /currency_conversions/search
     * 
     * @param string $from Moeda origem
     * @param string $to Moeda destino
     * @return array Taxa de conversão
     */
    public function getCurrencyConversion(string $from, string $to): array
    {
        $client = $this->getClient();

        $response = $client->get('/currency_conversions/search', [
            'from' => $from,
            'to' => $to,
        ]);

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar conversão',
                'ratio' => null,
            ];
        }

        return [
            'from' => $from,
            'to' => $to,
            'ratio' => (float)($response['ratio'] ?? 0),
            'inverse_ratio' => ($response['ratio'] ?? 0) > 0
                ? round(1 / (float)$response['ratio'], 6)
                : 0,
        ];
    }

    /**
     * Calcula o monto total de uma ordem incluindo frete
     * Combina dados de /orders e /shipments
     * 
     * @param string $orderId ID da ordem
     * @return array Monto total calculado
     */
    public function calculateOrderTotalWithShipping(string $orderId): array
    {
        $client = $this->getClient();

        // Buscar ordem
        $order = $client->get("/orders/{$orderId}");

        if (isset($order['error'])) {
            return [
                'error' => $order['message'] ?? 'Ordem não encontrada',
                'data' => null,
            ];
        }

        $totalAmount = (float)($order['total_amount'] ?? 0);
        $currencyId = $order['currency_id'] ?? 'BRL';
        $taxAmount = (float)($order['taxes']['amount'] ?? 0);
        $taxCurrency = $order['taxes']['currency_id'] ?? $currencyId;

        // Buscar envio se existir
        $shippingCost = 0;
        $shipmentId = $order['shipping']['id'] ?? null;

        if ($shipmentId) {
            $shipment = $client->get("/shipments/{$shipmentId}");
            if (!isset($shipment['error'])) {
                $shippingCost = (float)($shipment['cost_components']['special_discount']
                    ?? $shipment['seller_cost']
                    ?? 0);
            }
        }

        // Converter impostos se necessário
        if ($taxCurrency !== $currencyId && $taxAmount > 0) {
            $conversion = $this->getCurrencyConversion($taxCurrency, $currencyId);
            if ($conversion['ratio']) {
                $taxAmount = $taxAmount * $conversion['ratio'];
            }
        }

        $grandTotal = $totalAmount + $taxAmount + $shippingCost;

        return [
            'order_id' => $orderId,
            'breakdown' => [
                'items_total' => round($totalAmount, 2),
                'tax_amount' => round($taxAmount, 2),
                'shipping_cost' => round($shippingCost, 2),
            ],
            'grand_total' => round($grandTotal, 2),
            'currency_id' => $currencyId,
        ];
    }

    /**
     * Obtém dados completos de uma ordem com todos os campos financeiros
     * 
     * @param string $orderId ID da ordem
     * @return array Dados completos da ordem
     */
    public function getCompleteOrderFinancialData(string $orderId): array
    {
        $client = $this->getClient();

        $order = $client->get("/orders/{$orderId}");

        if (isset($order['error'])) {
            return [
                'error' => $order['message'] ?? 'Ordem não encontrada',
                'data' => null,
            ];
        }

        // Extrair itens
        $items = [];
        $totalGrossPrice = 0;

        foreach ($order['order_items'] ?? [] as $item) {
            $unitPrice = (float)($item['unit_price'] ?? 0);
            $fullUnitPrice = (float)($item['full_unit_price'] ?? $unitPrice);
            $quantity = (int)($item['quantity'] ?? 1);
            $grossPrice = (float)($item['gross_price'] ?? ($fullUnitPrice * $quantity));
            $saleFee = (float)($item['sale_fee'] ?? 0);

            $discountFull = 0;
            foreach ($item['discounts'] ?? [] as $discount) {
                $discountFull += (float)($discount['amounts']['full'] ?? 0);
            }

            $items[] = [
                'item_id' => $item['item']['id'] ?? null,
                'title' => $item['item']['title'] ?? null,
                'variation_id' => $item['item']['variation_id'] ?? null,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'full_unit_price' => $fullUnitPrice,
                'gross_price' => $grossPrice,
                'sale_fee' => $saleFee,
                'discount_per_unit' => $discountFull,
                'total_discount' => $discountFull * $quantity,
                'listing_type_id' => $item['listing_type_id'] ?? null,
                'currency_id' => $item['currency_id'] ?? $order['currency_id'] ?? 'BRL',
            ];

            $totalGrossPrice += $grossPrice;
        }

        // Pagamentos
        $payments = [];
        $totalPaid = 0;
        $totalMarketplaceFee = 0;

        foreach ($order['payments'] ?? [] as $payment) {
            $amount = (float)($payment['transaction_amount'] ?? 0);
            $fee = (float)($payment['marketplace_fee'] ?? 0);

            $payments[] = [
                'payment_id' => $payment['id'] ?? null,
                'status' => $payment['status'] ?? null,
                'status_detail' => $payment['status_detail'] ?? null,
                'payment_type' => $payment['payment_type'] ?? null,
                'payment_method_id' => $payment['payment_method_id'] ?? null,
                'transaction_amount' => $amount,
                'total_paid_amount' => (float)($payment['total_paid_amount'] ?? $amount),
                'marketplace_fee' => $fee,
                'installments' => $payment['installments'] ?? 1,
                'date_approved' => $payment['date_approved'] ?? null,
            ];

            if (($payment['status'] ?? '') === 'approved') {
                $totalPaid += $amount;
                $totalMarketplaceFee += $fee;
            }
        }

        $totalAmount = (float)($order['total_amount'] ?? 0);
        $paidAmount = (float)($order['paid_amount'] ?? $totalPaid);
        $totalDiscounts = $totalGrossPrice - $totalAmount;

        return [
            'order_id' => $order['id'] ?? $orderId,
            'status' => $order['status'] ?? null,
            'status_detail' => $order['status_detail'] ?? null,
            'date_created' => $order['date_created'] ?? null,
            'date_closed' => $order['date_closed'] ?? null,
            'buyer' => [
                'id' => $order['buyer']['id'] ?? null,
            ],
            'seller' => [
                'id' => $order['seller']['id'] ?? null,
            ],
            'items' => $items,
            'payments' => $payments,
            'financials' => [
                'currency_id' => $order['currency_id'] ?? 'BRL',
                'total_amount' => $totalAmount,
                'paid_amount' => $paidAmount,
                'gross_amount' => round($totalGrossPrice, 2),
                'total_discounts' => round($totalDiscounts, 2),
                'total_marketplace_fee' => round($totalMarketplaceFee, 2),
                'coupon_amount' => (float)($order['coupon']['amount'] ?? 0),
                'shipping_cost' => (float)($order['shipping_cost'] ?? 0),
                'taxes' => [
                    'amount' => (float)($order['taxes']['amount'] ?? 0),
                    'currency_id' => $order['taxes']['currency_id'] ?? null,
                ],
            ],
            'shipping' => [
                'id' => $order['shipping']['id'] ?? null,
            ],
            'context' => [
                'channel' => $order['context']['channel'] ?? null,
                'site' => $order['context']['site'] ?? null,
                'flows' => $order['context']['flows'] ?? [],
            ],
            'tags' => $order['tags'] ?? [],
            'pack_id' => $order['pack_id'] ?? null,
        ];
    }

    /**
     * Obtém dados de produtos em uma ordem (atributos especiais como IMEI)
     * Endpoint: GET /orders/{order_id}/product
     * 
     * @param string $orderId ID da ordem
     * @return array Dados do produto
     */
    public function getOrderProductData(string $orderId): array
    {
        $client = $this->getClient();

        $response = $client->get("/orders/{$orderId}/product");

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Dados do produto não encontrados',
                'attributes' => [],
            ];
        }

        $attributes = [];
        foreach ($response['attributes'] ?? [] as $attr) {
            $attributes[] = [
                'id' => $attr['id'] ?? null,
                'name' => $attr['name'] ?? null,
                'value' => $attr['value'] ?? null,
            ];
        }

        return [
            'order_id' => $orderId,
            'attributes' => $attributes,
            'total_attributes' => count($attributes),
        ];
    }

    /**
     * Obtém reputação completa do vendedor
     * Endpoint: GET /users/{user_id}
     * Inclui métricas de qualidade, claims, cancelamentos e handling time
     * 
     * @return array Dados de reputação do vendedor
     */
    public function getSellerReputation(): array
    {
        $client = $this->getClient();

        $sellerId = $client->getSellerId();
        $response = $client->get("/users/{$sellerId}");

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar reputação',
                'data' => null,
            ];
        }

        $reputation = $response['seller_reputation'] ?? [];
        $transactions = $reputation['transactions'] ?? [];
        $metrics = $reputation['metrics'] ?? [];

        return [
            'seller_id' => $sellerId,
            'nickname' => $response['nickname'] ?? null,
            'level' => [
                'id' => $reputation['level_id'] ?? null,
                'power_seller_status' => $reputation['power_seller_status'] ?? null,
                'real_level' => $reputation['real_level'] ?? null,
                'protection_end_date' => $reputation['protection_end_date'] ?? null,
            ],
            'transactions' => [
                'total' => $transactions['total'] ?? 0,
                'completed' => $transactions['completed'] ?? 0,
                'canceled' => $transactions['canceled'] ?? 0,
                'period' => $transactions['period'] ?? null,
                'ratings' => [
                    'positive' => ($transactions['ratings']['positive'] ?? 0) * 100,
                    'neutral' => ($transactions['ratings']['neutral'] ?? 0) * 100,
                    'negative' => ($transactions['ratings']['negative'] ?? 0) * 100,
                ],
            ],
            'metrics' => [
                'sales' => [
                    'period' => $metrics['sales']['period'] ?? null,
                    'completed' => $metrics['sales']['completed'] ?? 0,
                ],
                'claims' => [
                    'period' => $metrics['claims']['period'] ?? null,
                    'rate' => ($metrics['claims']['rate'] ?? 0) * 100,
                    'value' => $metrics['claims']['value'] ?? 0,
                    'excluded' => $metrics['claims']['excluded'] ?? null,
                ],
                'delayed_handling_time' => [
                    'period' => $metrics['delayed_handling_time']['period'] ?? null,
                    'rate' => ($metrics['delayed_handling_time']['rate'] ?? 0) * 100,
                    'value' => $metrics['delayed_handling_time']['value'] ?? 0,
                    'excluded' => $metrics['delayed_handling_time']['excluded'] ?? null,
                ],
                'cancellations' => [
                    'period' => $metrics['cancellations']['period'] ?? null,
                    'rate' => ($metrics['cancellations']['rate'] ?? 0) * 100,
                    'value' => $metrics['cancellations']['value'] ?? 0,
                    'excluded' => $metrics['cancellations']['excluded'] ?? null,
                ],
            ],
            'interpretation' => $this->interpretReputationLevel($reputation['level_id'] ?? null),
        ];
    }

    /**
     * Interpreta o nível de reputação
     */
    private function interpretReputationLevel(?string $levelId): array
    {
        $levels = [
            '5_green' => ['color' => 'verde', 'status' => 'Excelente', 'description' => 'Reputação máxima'],
            '4_light_green' => ['color' => 'verde claro', 'status' => 'Muito Bom', 'description' => 'Acima da média'],
            '3_yellow' => ['color' => 'amarelo', 'status' => 'Bom', 'description' => 'Na média'],
            '2_orange' => ['color' => 'laranja', 'status' => 'Regular', 'description' => 'Abaixo da média'],
            '1_red' => ['color' => 'vermelho', 'status' => 'Ruim', 'description' => 'Precisa melhorar'],
        ];

        return $levels[$levelId] ?? ['color' => 'desconhecido', 'status' => 'N/A', 'description' => 'Nível não identificado'];
    }

    /**
     * Obtém total de visitas de todos os anúncios do vendedor
     * Endpoint: GET /users/{user_id}/items_visits
     * 
     * @param string $startDate Data inicial (ISO format)
     * @param string $endDate Data final (ISO format)
     * @return array Total de visitas
     */
    public function getSellerTotalVisits(string $startDate, string $endDate): array
    {
        $client = $this->getClient();

        $sellerId = $client->getSellerId();
        $response = $client->get("/users/{$sellerId}/items_visits", [
            'date_from' => $startDate,
            'date_to' => $endDate,
        ]);

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar visitas',
                'data' => null,
            ];
        }

        $visitsDetail = [];
        foreach ($response['visits_detail'] ?? [] as $detail) {
            $visitsDetail[] = [
                'company' => $detail['company'] ?? 'mercadolibre',
                'quantity' => $detail['quantity'] ?? 0,
            ];
        }

        return [
            'seller_id' => $sellerId,
            'period' => [
                'from' => $response['date_from'] ?? $startDate,
                'to' => $response['date_to'] ?? $endDate,
            ],
            'total_visits' => $response['total_visits'] ?? 0,
            'visits_detail' => $visitsDetail,
        ];
    }

    /**
     * Obtém visitas por janela de tempo (detalhamento diário)
     * Endpoint: GET /users/{user_id}/items_visits/time_window
     * 
     * @param int $lastDays Últimos N dias
     * @return array Visitas por dia
     */
    public function getSellerVisitsByTimeWindow(int $lastDays = 30): array
    {
        $client = $this->getClient();

        $sellerId = $client->getSellerId();
        $response = $client->get("/users/{$sellerId}/items_visits/time_window", [
            'last' => $lastDays,
            'unit' => 'day',
        ]);

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar visitas por período',
                'data' => null,
            ];
        }

        $dailyVisits = [];
        foreach ($response['results'] ?? [] as $result) {
            $dailyVisits[] = [
                'date' => $result['date'] ?? null,
                'total' => $result['total'] ?? 0,
            ];
        }

        return [
            'seller_id' => $sellerId,
            'period' => [
                'from' => $response['date_from'] ?? null,
                'to' => $response['date_to'] ?? null,
            ],
            'total_visits' => $response['total_visits'] ?? 0,
            'last_days' => $lastDays,
            'daily_visits' => $dailyVisits,
            'average_daily' => count($dailyVisits) > 0
                ? round(($response['total_visits'] ?? 0) / count($dailyVisits), 2)
                : 0,
        ];
    }

    /**
     * Obtém visitas de um item específico
     * Endpoint: GET /visits/items?ids={item_id}
     * 
     * @param string $itemId ID do item
     * @return array Total de visitas do item
     */
    public function getItemVisitsTotal(string $itemId): array
    {
        $client = $this->getClient();

        $response = $client->get('/visits/items', [
            'ids' => $itemId,
        ]);

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar visitas do item',
                'data' => null,
            ];
        }

        return [
            'item_id' => $itemId,
            'total_visits' => $response[$itemId] ?? 0,
        ];
    }

    /**
     * Obtém visitas de um item por período
     * Endpoint: GET /items/visits?ids={item_id}&date_from=&date_to=
     * 
     * @param string $itemId ID do item
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @return array Visitas do item no período
     */
    public function getItemVisitsByPeriod(string $itemId, string $startDate, string $endDate): array
    {
        $client = $this->getClient();

        $response = $client->get('/items/visits', [
            'ids' => $itemId,
            'date_from' => $startDate,
            'date_to' => $endDate,
        ]);

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar visitas do item',
                'data' => null,
            ];
        }

        // A resposta é um array de objetos
        $itemData = is_array($response) && isset($response[0]) ? $response[0] : $response;

        return [
            'item_id' => $itemData['item_id'] ?? $itemId,
            'period' => [
                'from' => $itemData['date_from'] ?? $startDate,
                'to' => $itemData['date_to'] ?? $endDate,
            ],
            'total_visits' => $itemData['total_visits'] ?? 0,
            'visits_detail' => $itemData['visits_detail'] ?? [],
        ];
    }

    /**
     * Obtém visitas de um item por janela de tempo (detalhado)
     * Endpoint: GET /items/{item_id}/visits/time_window
     * 
     * @param string $itemId ID do item
     * @param int $lastDays Últimos N dias
     * @return array Visitas diárias do item
     */
    public function getItemVisitsByTimeWindow(string $itemId, int $lastDays = 30): array
    {
        $client = $this->getClient();

        $response = $client->get("/items/{$itemId}/visits/time_window", [
            'last' => $lastDays,
            'unit' => 'day',
        ]);

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar visitas do item',
                'data' => null,
            ];
        }

        $dailyVisits = [];
        foreach ($response['results'] ?? [] as $result) {
            $dailyVisits[] = [
                'date' => $result['date'] ?? null,
                'total' => $result['total'] ?? 0,
            ];
        }

        return [
            'item_id' => $itemId,
            'period' => [
                'from' => $response['date_from'] ?? null,
                'to' => $response['date_to'] ?? null,
            ],
            'total_visits' => $response['total_visits'] ?? 0,
            'last_days' => $lastDays,
            'daily_visits' => $dailyVisits,
        ];
    }

    /**
     * Obtém total de perguntas por período
     * Endpoint: GET /users/{user_id}/contacts/questions
     * 
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @return array Total de perguntas
     */
    public function getSellerQuestionsMetrics(string $startDate, string $endDate): array
    {
        $client = $this->getClient();

        $sellerId = $client->getSellerId();
        $response = $client->get("/users/{$sellerId}/contacts/questions", [
            'date_from' => $startDate,
            'date_to' => $endDate,
        ]);

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar métricas de perguntas',
                'data' => null,
            ];
        }

        return [
            'seller_id' => $sellerId,
            'period' => [
                'from' => $response['date_from'] ?? $startDate,
                'to' => $response['date_to'] ?? $endDate,
            ],
            'total_questions' => $response['total'] ?? 0,
        ];
    }

    /**
     * Obtém métricas de "ver telefone" do vendedor
     * Endpoint: GET /users/{user_id}/contacts/phone_views
     * 
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @return array Total de cliques em ver telefone
     */
    public function getSellerPhoneViewsMetrics(string $startDate, string $endDate): array
    {
        $client = $this->getClient();

        $sellerId = $client->getSellerId();
        $response = $client->get("/users/{$sellerId}/contacts/phone_views", [
            'date_from' => $startDate,
            'date_to' => $endDate,
        ]);

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Erro ao buscar métricas de telefone',
                'data' => null,
            ];
        }

        return [
            'seller_id' => $sellerId,
            'period' => [
                'from' => $response['date_from'] ?? $startDate,
                'to' => $response['date_to'] ?? $endDate,
            ],
            'total_phone_views' => $response['total'] ?? 0,
        ];
    }

    /**
     * Calcula taxa de conversão (vendas/visitas)
     * Combina dados de visitas com dados de vendas
     * 
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @return array Taxa de conversão e métricas
     */
    public function calculateConversionRate(string $startDate, string $endDate): array
    {
        // Buscar visitas
        $visits = $this->getSellerTotalVisits($startDate, $endDate);
        $totalVisits = $visits['total_visits'] ?? 0;

        // Buscar vendas do período
        $client = $this->getClient();
        $sellerId = $client->getSellerId();

        $ordersResponse = $client->get('/orders/search', [
            'seller' => $sellerId,
            'order.date_created.from' => $startDate . 'T00:00:00.000-00:00',
            'order.date_created.to' => $endDate . 'T23:59:59.999-00:00',
            'order.status' => 'paid',
        ]);

        $totalSales = $ordersResponse['paging']['total'] ?? 0;

        // Calcular conversão
        $conversionRate = $totalVisits > 0 ? ($totalSales / $totalVisits) * 100 : 0;

        return [
            'period' => ['from' => $startDate, 'to' => $endDate],
            'total_visits' => $totalVisits,
            'total_sales' => $totalSales,
            'conversion_rate' => round($conversionRate, 4),
            'conversion_rate_formatted' => round($conversionRate, 2) . '%',
            'visits_per_sale' => $totalSales > 0 ? round($totalVisits / $totalSales) : 0,
            'benchmark' => $this->getConversionBenchmark($conversionRate),
        ];
    }

    /**
     * Retorna benchmark de conversão
     */
    private function getConversionBenchmark(float $rate): array
    {
        if ($rate >= 5) {
            return ['status' => 'excellent', 'message' => 'Excelente! Acima de 5% é muito bom'];
        }
        if ($rate >= 3) {
            return ['status' => 'good', 'message' => 'Bom! Entre 3-5% é a média do mercado'];
        }
        if ($rate >= 1) {
            return ['status' => 'average', 'message' => 'Na média. Há espaço para melhoria'];
        }
        return ['status' => 'low', 'message' => 'Abaixo da média. Considere otimizar anúncios'];
    }

    /**
     * Gera relatório consolidado de performance do vendedor
     * Combina reputação, visitas, vendas e métricas
     * 
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @return array Relatório completo de performance
     */
    public function generateSellerPerformanceReport(string $startDate, string $endDate): array
    {
        // Coletar todas as métricas
        $reputation = $this->getSellerReputation();
        $visits = $this->getSellerVisitsByTimeWindow(30);
        $conversion = $this->calculateConversionRate($startDate, $endDate);

        // Buscar dados financeiros
        $client = $this->getClient();
        $sellerId = $client->getSellerId();

        // Vendas do período
        $ordersResponse = $client->get('/orders/search', [
            'seller' => $sellerId,
            'order.date_created.from' => $startDate . 'T00:00:00.000-00:00',
            'order.date_created.to' => $endDate . 'T23:59:59.999-00:00',
        ]);

        $orders = $ordersResponse['results'] ?? [];
        $totalRevenue = 0;
        $paidOrders = 0;
        $cancelledOrders = 0;

        foreach ($orders as $order) {
            $status = $order['status'] ?? '';
            if ($status === 'paid') {
                $paidOrders++;
                $totalRevenue += (float)($order['total_amount'] ?? 0);
            } elseif ($status === 'cancelled') {
                $cancelledOrders++;
            }
        }

        $totalOrders = count($orders);
        $fulfillmentRate = $totalOrders > 0 ? ($paidOrders / $totalOrders) * 100 : 0;

        return [
            'period' => ['from' => $startDate, 'to' => $endDate],
            'generated_at' => date('Y-m-d H:i:s'),
            'reputation' => [
                'level' => $reputation['level'] ?? null,
                'power_seller' => $reputation['level']['power_seller_status'] ?? null,
                'interpretation' => $reputation['interpretation'] ?? null,
            ],
            'metrics' => [
                'claims_rate' => $reputation['metrics']['claims']['rate'] ?? 0,
                'cancellations_rate' => $reputation['metrics']['cancellations']['rate'] ?? 0,
                'delayed_handling_rate' => $reputation['metrics']['delayed_handling_time']['rate'] ?? 0,
            ],
            'traffic' => [
                'total_visits' => $visits['total_visits'] ?? 0,
                'average_daily_visits' => $visits['average_daily'] ?? 0,
                'visits_trend' => $this->calculateVisitsTrend($visits['daily_visits'] ?? []),
            ],
            'sales' => [
                'total_orders' => $totalOrders,
                'paid_orders' => $paidOrders,
                'cancelled_orders' => $cancelledOrders,
                'total_revenue' => round($totalRevenue, 2),
                'fulfillment_rate' => round($fulfillmentRate, 2),
            ],
            'conversion' => [
                'rate' => $conversion['conversion_rate'] ?? 0,
                'formatted' => $conversion['conversion_rate_formatted'] ?? '0%',
                'benchmark' => $conversion['benchmark'] ?? null,
            ],
            'scores' => $this->calculatePerformanceScores(
                $reputation,
                $conversion,
                $fulfillmentRate
            ),
        ];
    }

    /**
     * Calcula tendência de visitas
     */
    private function calculateVisitsTrend(array $dailyVisits): array
    {
        if (count($dailyVisits) < 2) {
            return ['trend' => 'stable', 'change' => 0];
        }

        $halfCount = (int)(count($dailyVisits) / 2);
        $firstHalf = array_slice($dailyVisits, 0, $halfCount);
        $secondHalf = array_slice($dailyVisits, $halfCount);

        $firstAvg = array_sum(array_column($firstHalf, 'total')) / count($firstHalf);
        $secondAvg = array_sum(array_column($secondHalf, 'total')) / count($secondHalf);

        $change = $firstAvg > 0 ? (($secondAvg - $firstAvg) / $firstAvg) * 100 : 0;

        $trend = 'stable';
        if ($change > 10) {
            $trend = 'increasing';
        } elseif ($change < -10) {
            $trend = 'decreasing';
        }

        return [
            'trend' => $trend,
            'change' => round($change, 2),
        ];
    }

    /**
     * Calcula scores de performance
     */
    private function calculatePerformanceScores(
        array $reputation,
        array $conversion,
        float $fulfillmentRate
    ): array {
        // Score de reputação (0-100)
        $reputationScore = $this->calculateReputationScore($reputation);

        // Score de conversão (0-100)
        $conversionRate = $conversion['conversion_rate'] ?? 0;
        $conversionScore = min(100, ($conversionRate / 5) * 100);

        // Score de fulfillment (0-100)
        $fulfillmentScore = $fulfillmentRate;

        // Score geral (média ponderada)
        $overallScore = ($reputationScore * 0.4) + ($conversionScore * 0.3) + ($fulfillmentScore * 0.3);

        return [
            'reputation_score' => round($reputationScore),
            'conversion_score' => round($conversionScore),
            'fulfillment_score' => round($fulfillmentScore),
            'overall_score' => round($overallScore),
            'grade' => $this->getPerformanceGrade($overallScore),
        ];
    }

    /**
     * Calcula score de reputação
     */
    private function calculateReputationScore(array $reputation): float
    {
        $levelScores = [
            '5_green' => 100,
            '4_light_green' => 80,
            '3_yellow' => 60,
            '2_orange' => 40,
            '1_red' => 20,
        ];

        $level = $reputation['level']['id'] ?? null;
        $baseScore = $levelScores[$level] ?? 50;

        // Penalidades
        $claimsRate = $reputation['metrics']['claims']['rate'] ?? 0;
        $cancellationsRate = $reputation['metrics']['cancellations']['rate'] ?? 0;
        $delayedRate = $reputation['metrics']['delayed_handling_time']['rate'] ?? 0;

        $penalty = ($claimsRate * 2) + ($cancellationsRate * 1.5) + ($delayedRate * 1);

        return max(0, $baseScore - $penalty);
    }

    /**
     * Retorna nota de performance
     */
    private function getPerformanceGrade(float $score): string
    {
        if ($score >= 90) {
            return 'A+';
        }
        if ($score >= 80) {
            return 'A';
        }
        if ($score >= 70) {
            return 'B';
        }
        if ($score >= 60) {
            return 'C';
        }
        if ($score >= 50) {
            return 'D';
        }
        return 'F';
    }

    /**
     * Obtém feedback de vendas
     * Endpoint: GET /orders/{order_id}/feedback
     * 
     * @param string $orderId ID da ordem
     * @return array Feedback da venda
     */
    public function getOrderFeedback(string $orderId): array
    {
        $client = $this->getClient();

        $response = $client->get("/orders/{$orderId}/feedback");

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Feedback não encontrado',
                'data' => null,
            ];
        }

        $sale = $response['sale'] ?? null;
        $purchase = $response['purchase'] ?? null;

        return [
            'order_id' => $orderId,
            'sale' => $sale ? [
                'fulfilled' => $sale['fulfilled'] ?? null,
                'rating' => $sale['rating'] ?? null,
                'date_created' => $sale['date_created'] ?? null,
                'message' => $sale['message'] ?? null,
            ] : null,
            'purchase' => $purchase ? [
                'fulfilled' => $purchase['fulfilled'] ?? null,
                'rating' => $purchase['rating'] ?? null,
                'date_created' => $purchase['date_created'] ?? null,
                'message' => $purchase['message'] ?? null,
            ] : null,
        ];
    }

    /**
     * Obtém opiniões de um produto
     * Endpoint: GET /reviews/item/{item_id}
     * 
     * @param string $itemId ID do item
     * @param int $limit Limite de resultados
     * @return array Opiniões do produto
     */
    public function getProductReviews(string $itemId, int $limit = 50): array
    {
        $client = $this->getClient();

        $response = $client->get("/reviews/item/{$itemId}", [
            'limit' => $limit,
        ]);

        if (isset($response['error'])) {
            return [
                'error' => $response['message'] ?? 'Opiniões não encontradas',
                'data' => null,
            ];
        }

        $reviews = [];
        foreach ($response['reviews'] ?? [] as $review) {
            $reviews[] = [
                'id' => $review['id'] ?? null,
                'rate' => $review['rate'] ?? null,
                'title' => $review['title'] ?? null,
                'content' => $review['content'] ?? null,
                'date_created' => $review['date_created'] ?? null,
                'likes' => $review['likes'] ?? 0,
                'dislikes' => $review['dislikes'] ?? 0,
                'relevance' => $review['relevance'] ?? null,
            ];
        }

        $rating = $response['rating_average'] ?? 0;
        $ratingLevels = $response['rating_levels'] ?? [];

        return [
            'item_id' => $itemId,
            'summary' => [
                'total_reviews' => $response['paging']['total'] ?? count($reviews),
                'rating_average' => round($rating, 2),
                'rating_levels' => [
                    '5_stars' => $ratingLevels['five_star'] ?? 0,
                    '4_stars' => $ratingLevels['four_star'] ?? 0,
                    '3_stars' => $ratingLevels['three_star'] ?? 0,
                    '2_stars' => $ratingLevels['two_star'] ?? 0,
                    '1_star' => $ratingLevels['one_star'] ?? 0,
                ],
            ],
            'reviews' => $reviews,
        ];
    }

    /**
     * Calcula LTV (Lifetime Value) estimado por cliente
     * Baseado no histórico de vendas
     * 
     * @param int $months Meses para análise
     * @return array Métricas de LTV
     */
    public function calculateCustomerLTV(int $months = 12): array
    {
        $client = $this->getClient();
        $sellerId = $client->getSellerId();

        $endDate = date('Y-m-d');
        $startDate = date('Y-m-d', strtotime("-{$months} months"));

        // Buscar todas as vendas do período
        $ordersResponse = $client->get('/orders/search', [
            'seller' => $sellerId,
            'order.date_created.from' => $startDate . 'T00:00:00.000-00:00',
            'order.date_created.to' => $endDate . 'T23:59:59.999-00:00',
            'order.status' => 'paid',
            'limit' => 50,
        ]);

        $orders = $ordersResponse['results'] ?? [];
        $totalOrders = $ordersResponse['paging']['total'] ?? count($orders);

        // Agrupar por comprador
        $buyerPurchases = [];
        $totalRevenue = 0;

        foreach ($orders as $order) {
            $buyerId = $order['buyer']['id'] ?? 'unknown';
            $amount = (float)($order['total_amount'] ?? 0);

            if (!isset($buyerPurchases[$buyerId])) {
                $buyerPurchases[$buyerId] = ['count' => 0, 'total' => 0];
            }

            $buyerPurchases[$buyerId]['count']++;
            $buyerPurchases[$buyerId]['total'] += $amount;
            $totalRevenue += $amount;
        }

        $uniqueCustomers = count($buyerPurchases);
        $repeatCustomers = count(array_filter($buyerPurchases, fn($b) => $b['count'] > 1));

        // Calcular métricas
        $avgOrderValue = $totalOrders > 0 ? $totalRevenue / $totalOrders : 0;
        $avgPurchaseFrequency = $uniqueCustomers > 0 ? $totalOrders / $uniqueCustomers : 0;
        $customerValue = $avgOrderValue * $avgPurchaseFrequency;

        // LTV estimado (12 meses)
        $estimatedLTV = $customerValue * ($months / 12);

        return [
            'period_months' => $months,
            'period' => ['from' => $startDate, 'to' => $endDate],
            'metrics' => [
                'total_revenue' => round($totalRevenue, 2),
                'total_orders' => $totalOrders,
                'unique_customers' => $uniqueCustomers,
                'repeat_customers' => $repeatCustomers,
                'repeat_rate' => $uniqueCustomers > 0
                    ? round(($repeatCustomers / $uniqueCustomers) * 100, 2)
                    : 0,
            ],
            'averages' => [
                'order_value' => round($avgOrderValue, 2),
                'purchase_frequency' => round($avgPurchaseFrequency, 2),
                'customer_value' => round($customerValue, 2),
            ],
            'ltv' => [
                'estimated_12_months' => round($estimatedLTV, 2),
                'currency' => 'BRL',
            ],
            'insights' => $this->getLTVInsights($avgOrderValue, $avgPurchaseFrequency, $repeatCustomers / max(1, $uniqueCustomers)),
        ];
    }

    /**
     * Gera insights de LTV
     */
    private function getLTVInsights(float $aov, float $frequency, float $repeatRate): array
    {
        $insights = [];

        if ($aov < 50) {
            $insights[] = 'Ticket médio baixo. Considere upselling ou bundles';
        } elseif ($aov > 200) {
            $insights[] = 'Ticket médio alto. Foque em fidelização';
        }

        if ($frequency < 1.2) {
            $insights[] = 'Frequência de compra baixa. Implemente programas de recompra';
        }

        if ($repeatRate < 0.1) {
            $insights[] = 'Taxa de recompra muito baixa. Foque em pós-venda e follow-up';
        } elseif ($repeatRate > 0.3) {
            $insights[] = 'Ótima taxa de recompra! Mantenha a qualidade';
        }

        return $insights;
    }

    // ============================================================================
    // CHARGEBACKS & REFUNDS - Mercado Pago API
    // ============================================================================

    /**
     * Obtém detalhes de uma contestação (chargeback)
     * API: GET /v1/chargebacks/{id}
     *
     * @param string $chargebackId ID da contestação
     * @return array Dados da contestação
     */
    public function getChargebackDetails(string $chargebackId): array
    {
        $client = $this->getClient();

        $data = $client->get("/v1/chargebacks/{$chargebackId}");

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao buscar contestação'];
        }

        return [
            'id' => $data['id'] ?? $chargebackId,
            'payments' => $data['payments'] ?? [],
            'currency' => $data['currency'] ?? 'BRL',
            'amount' => (float)($data['amount'] ?? 0),
            'coverage_applied' => $data['coverage_applied'] ?? false,
            'coverage_elegible' => $data['coverage_elegible'] ?? false,
            'documentation_required' => $data['documentation_required'] ?? false,
            'documentation_status' => $data['documentation_status'] ?? null,
            'documentation' => $data['documentation'] ?? [],
            'date_documentation_deadline' => $data['date_documentation_deadline'] ?? null,
            'date_created' => $data['date_created'] ?? null,
            'date_last_updated' => $data['date_last_updated'] ?? null,
            'live_mode' => $data['live_mode'] ?? false,
            'status_interpretation' => $this->interpretChargebackStatus($data),
        ];
    }

    /**
     * Interpreta o status da contestação
     */
    private function interpretChargebackStatus(array $data): array
    {
        $interpretation = [
            'status' => 'unknown',
            'action_required' => false,
            'message' => '',
        ];

        if ($data['documentation_required'] ?? false) {
            $interpretation['status'] = 'documentation_required';
            $interpretation['action_required'] = true;

            $deadline = $data['date_documentation_deadline'] ?? null;
            if ($deadline) {
                $daysRemaining = ceil((strtotime($deadline) - time()) / 86400);
                $interpretation['message'] = "Documentação necessária. Prazo: {$daysRemaining} dias";
            } else {
                $interpretation['message'] = 'Documentação necessária para contestar';
            }
        } elseif (($data['documentation_status'] ?? '') === 'valid') {
            $interpretation['status'] = 'documentation_submitted';
            $interpretation['message'] = 'Documentação enviada e validada, aguardando resolução';
        } elseif ($data['coverage_applied'] ?? false) {
            $interpretation['status'] = 'covered';
            $interpretation['message'] = 'Proteção ao vendedor aplicada';
        }

        return $interpretation;
    }

    /**
     * Busca pagamentos do Mercado Pago
     * API: GET /v1/payments/search
     *
     * @param array $filters Filtros de busca
     * @return array Lista de pagamentos
     */
    public function searchMPPayments(array $filters = []): array
    {
        $client = $this->getClient();

        $params = [
            'sort' => $filters['sort'] ?? 'date_created',
            'criteria' => $filters['criteria'] ?? 'desc',
            'limit' => $filters['limit'] ?? 30,
            'offset' => $filters['offset'] ?? 0,
        ];

        if (!empty($filters['external_reference'])) {
            $params['external_reference'] = $filters['external_reference'];
        }

        if (!empty($filters['status'])) {
            $params['status'] = $filters['status'];
        }

        if (!empty($filters['begin_date'])) {
            $params['range'] = 'date_created';
            $params['begin_date'] = $filters['begin_date'];
            $params['end_date'] = $filters['end_date'] ?? 'NOW';
        }

        $query = http_build_query($params);
        $data = $client->get("/v1/payments/search?{$query}");

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao buscar pagamentos'];
        }

        return [
            'paging' => $data['paging'] ?? ['total' => 0, 'limit' => 30, 'offset' => 0],
            'results' => array_map(function ($payment) {
                return [
                    'id' => $payment['id'],
                    'date_created' => $payment['date_created'] ?? null,
                    'date_approved' => $payment['date_approved'] ?? null,
                    'payment_method_id' => $payment['payment_method_id'] ?? null,
                    'payment_type_id' => $payment['payment_type_id'] ?? null,
                    'status' => $payment['status'] ?? null,
                    'status_detail' => $payment['status_detail'] ?? null,
                    'currency_id' => $payment['currency_id'] ?? 'BRL',
                    'description' => $payment['description'] ?? null,
                    'external_reference' => $payment['external_reference'] ?? null,
                    'transaction_amount' => (float)($payment['transaction_amount'] ?? 0),
                    'transaction_amount_refunded' => (float)($payment['transaction_amount_refunded'] ?? 0),
                    'net_received_amount' => (float)($payment['transaction_details']['net_received_amount'] ?? 0),
                    'installments' => (int)($payment['installments'] ?? 1),
                    'payer' => [
                        'id' => $payment['payer']['id'] ?? null,
                        'email' => $payment['payer']['email'] ?? null,
                        'type' => $payment['payer']['type'] ?? null,
                    ],
                ];
            }, $data['results'] ?? []),
        ];
    }

    /**
     * Obtém detalhes de um pagamento específico
     * API: GET /v1/payments/{id}
     *
     * @param string $paymentId ID do pagamento
     * @return array Detalhes do pagamento
     */
    public function getMPPaymentDetails(string $paymentId): array
    {
        $client = $this->getClient();

        $data = $client->get("/v1/payments/{$paymentId}");

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao buscar pagamento'];
        }

        return [
            'id' => $data['id'],
            'date_created' => $data['date_created'] ?? null,
            'date_approved' => $data['date_approved'] ?? null,
            'date_last_updated' => $data['date_last_updated'] ?? null,
            'money_release_date' => $data['money_release_date'] ?? null,
            'payment_method_id' => $data['payment_method_id'] ?? null,
            'payment_type_id' => $data['payment_type_id'] ?? null,
            'status' => $data['status'] ?? null,
            'status_detail' => $data['status_detail'] ?? null,
            'currency_id' => $data['currency_id'] ?? 'BRL',
            'description' => $data['description'] ?? null,
            'external_reference' => $data['external_reference'] ?? null,
            'transaction_amount' => (float)($data['transaction_amount'] ?? 0),
            'transaction_amount_refunded' => (float)($data['transaction_amount_refunded'] ?? 0),
            'coupon_amount' => (float)($data['coupon_amount'] ?? 0),
            'transaction_details' => [
                'net_received_amount' => (float)($data['transaction_details']['net_received_amount'] ?? 0),
                'total_paid_amount' => (float)($data['transaction_details']['total_paid_amount'] ?? 0),
                'overpaid_amount' => (float)($data['transaction_details']['overpaid_amount'] ?? 0),
                'installment_amount' => (float)($data['transaction_details']['installment_amount'] ?? 0),
            ],
            'installments' => (int)($data['installments'] ?? 1),
            'payer' => $data['payer'] ?? [],
            'fee_details' => $data['fee_details'] ?? [],
            'charges_details' => $data['charges_details'] ?? [],
            'refunds' => $data['refunds'] ?? [],
            'card' => $data['card'] ?? [],
        ];
    }

    /**
     * Lista reembolsos de um pagamento
     * API: GET /v1/payments/{id}/refunds
     *
     * @param string $paymentId ID do pagamento
     * @return array Lista de reembolsos
     */
    public function getPaymentRefunds(string $paymentId): array
    {
        $client = $this->getClient();

        $data = $client->get("/v1/payments/{$paymentId}/refunds");

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao buscar reembolsos'];
        }

        return array_map(function ($refund) use ($paymentId) {
            return [
                'id' => $refund['id'],
                'payment_id' => $paymentId,
                'amount' => (float)($refund['amount'] ?? 0),
                'status' => $refund['status'] ?? null,
                'refund_mode' => $refund['refund_mode'] ?? 'standard',
                'date_created' => $refund['date_created'] ?? null,
                'reason' => $refund['reason'] ?? null,
                'source' => $refund['source'] ?? [],
            ];
        }, is_array($data) ? $data : []);
    }

    /**
     * Cria um reembolso para um pagamento
     * API: POST /v1/payments/{id}/refunds
     *
     * @param string $paymentId ID do pagamento
     * @param float|null $amount Valor do reembolso (null = total)
     * @return array Resultado do reembolso
     */
    public function createRefund(string $paymentId, ?float $amount = null): array
    {
        $client = $this->getClient();

        $body = [];
        if ($amount !== null) {
            $body['amount'] = $amount;
        }

        $data = $client->post("/v1/payments/{$paymentId}/refunds", $body);

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao criar reembolso'];
        }

        return [
            'success' => true,
            'id' => $data['id'],
            'payment_id' => $data['payment_id'] ?? $paymentId,
            'amount' => (float)($data['amount'] ?? 0),
            'status' => $data['status'] ?? null,
            'refund_mode' => $data['refund_mode'] ?? 'standard',
            'date_created' => $data['date_created'] ?? null,
        ];
    }

    /**
     * Gera relatório de chargebacks e reembolsos
     *
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @return array Relatório consolidado
     */
    public function getChargebacksRefundsReport(string $startDate, string $endDate): array
    {
        // Buscar pagamentos com reembolsos no período
        $payments = $this->searchMPPayments([
            'begin_date' => $startDate . 'T00:00:00.000Z',
            'end_date' => $endDate . 'T23:59:59.999Z',
            'limit' => 100,
        ]);

        if (isset($payments['error'])) {
            return ['error' => $payments['error']];
        }

        $totalRefunds = 0;
        $totalRefundAmount = 0;
        $refundedPayments = [];

        foreach ($payments['results'] as $payment) {
            if ($payment['transaction_amount_refunded'] > 0) {
                $totalRefunds++;
                $totalRefundAmount += $payment['transaction_amount_refunded'];
                $refundedPayments[] = [
                    'payment_id' => $payment['id'],
                    'original_amount' => $payment['transaction_amount'],
                    'refunded_amount' => $payment['transaction_amount_refunded'],
                    'date' => $payment['date_created'],
                    'status' => $payment['status'],
                ];
            }
        }

        $totalPayments = count($payments['results']);
        $refundRate = $totalPayments > 0 ? ($totalRefunds / $totalPayments) * 100 : 0;

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'summary' => [
                'total_payments' => $totalPayments,
                'payments_with_refunds' => $totalRefunds,
                'total_refund_amount' => $totalRefundAmount,
                'refund_rate' => round($refundRate, 2),
            ],
            'refunded_payments' => $refundedPayments,
            'insights' => $this->getRefundInsights($refundRate, $totalRefundAmount),
        ];
    }

    /**
     * Gera insights sobre reembolsos
     */
    private function getRefundInsights(float $refundRate, float $totalAmount): array
    {
        $insights = [];

        if ($refundRate > 5) {
            $insights[] = [
                'type' => 'warning',
                'message' => 'Taxa de reembolso acima de 5%. Revisar qualidade dos produtos ou descrições.',
            ];
        } elseif ($refundRate > 2) {
            $insights[] = [
                'type' => 'attention',
                'message' => 'Taxa de reembolso entre 2-5%. Monitorar motivos dos pedidos.',
            ];
        } else {
            $insights[] = [
                'type' => 'success',
                'message' => 'Taxa de reembolso saudável (< 2%).',
            ];
        }

        if ($totalAmount > 1000) {
            $insights[] = [
                'type' => 'info',
                'message' => sprintf('Total de R$ %.2f em reembolsos no período.', $totalAmount),
            ];
        }

        return $insights;
    }

    // ============================================================================
    // FINANCIAL HEALTH SCORE
    // ============================================================================

    /**
     * Calcula score de saúde financeira do vendedor
     *
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @return array Score e métricas
     */
    public function calculateFinancialHealthScore(string $startDate, string $endDate): array
    {
        // Coletar métricas
        $reputation = $this->getSellerReputation();
        $conversion = $this->calculateConversionRate($startDate, $endDate);
        $refunds = $this->getChargebacksRefundsReport($startDate, $endDate);

        $scores = [
            'reputation' => 0,
            'conversion' => 0,
            'refunds' => 0,
            'claims' => 0,
        ];

        // Score de reputação (0-25 pontos)
        if (!isset($reputation['error'])) {
            $repLevel = $reputation['level_id'] ?? '';
            $scores['reputation'] = match ($repLevel) {
                '5_green' => 25,
                '4_light_green' => 20,
                '3_yellow' => 15,
                '2_orange' => 10,
                '1_red' => 5,
                default => 0,
            };
        }

        // Score de conversão (0-25 pontos)
        if (!isset($conversion['error'])) {
            $convRate = $conversion['conversion_rate'] ?? 0;
            $scores['conversion'] = min(25, round($convRate * 5));
        }

        // Score de reembolsos (0-25 pontos)
        if (!isset($refunds['error'])) {
            $refundRate = $refunds['summary']['refund_rate'] ?? 0;
            // Quanto menor a taxa de reembolso, maior o score
            $scores['refunds'] = max(0, 25 - round($refundRate * 5));
        }

        // Score de claims (0-25 pontos) - baseado na reputação
        if (!isset($reputation['error'])) {
            $claimsRate = $reputation['metrics']['claims']['rate'] ?? 0;
            $scores['claims'] = max(0, 25 - round($claimsRate * 5));
        }

        $totalScore = array_sum($scores);

        return [
            'total_score' => $totalScore,
            'max_score' => 100,
            'grade' => $this->getHealthGrade($totalScore),
            'breakdown' => $scores,
            'period' => ['start' => $startDate, 'end' => $endDate],
            'recommendations' => $this->getHealthRecommendations($scores),
        ];
    }

    /**
     * Determina a nota baseada no score
     */
    private function getHealthGrade(int $score): string
    {
        return match (true) {
            $score >= 90 => 'A+',
            $score >= 80 => 'A',
            $score >= 70 => 'B+',
            $score >= 60 => 'B',
            $score >= 50 => 'C+',
            $score >= 40 => 'C',
            $score >= 30 => 'D',
            default => 'F',
        };
    }

    /**
     * Gera recomendações baseadas nos scores
     */
    private function getHealthRecommendations(array $scores): array
    {
        $recommendations = [];

        if ($scores['reputation'] < 20) {
            $recommendations[] = [
                'area' => 'reputation',
                'priority' => 'high',
                'message' => 'Foque em melhorar a reputação: responda rápido e resolva problemas',
            ];
        }

        if ($scores['conversion'] < 15) {
            $recommendations[] = [
                'area' => 'conversion',
                'priority' => 'medium',
                'message' => 'Melhore a conversão: otimize títulos, fotos e descrições',
            ];
        }

        if ($scores['refunds'] < 20) {
            $recommendations[] = [
                'area' => 'refunds',
                'priority' => 'high',
                'message' => 'Reduza reembolsos: melhore embalagens e descrições precisas',
            ];
        }

        if ($scores['claims'] < 20) {
            $recommendations[] = [
                'area' => 'claims',
                'priority' => 'high',
                'message' => 'Reduza reclamações: melhore atendimento e logística',
            ];
        }

        return $recommendations;
    }

    // ============================================================================
    // INVOICES & FISCAL DATA
    // ============================================================================

    /**
     * Obtém dados fiscais de uma ordem para emissão de NF
     *
     * @param string $orderId ID da ordem
     * @return array Dados para NF
     */
    public function getOrderFiscalData(string $orderId): array
    {
        $client = $this->getClient();

        // Buscar dados completos da ordem
        $order = $client->get("/orders/{$orderId}");

        if (isset($order['error'])) {
            return ['error' => $order['message'] ?? 'Erro ao buscar ordem'];
        }

        // Buscar dados do comprador
        $buyerId = $order['buyer']['id'] ?? null;
        $buyer = $buyerId ? $client->get("/users/{$buyerId}") : [];

        // Buscar dados do envio
        $shipmentId = $order['shipping']['id'] ?? null;
        $shipment = $shipmentId ? $client->get("/shipments/{$shipmentId}") : [];

        $items = [];
        foreach ($order['order_items'] ?? [] as $item) {
            $items[] = [
                'sku' => $item['item']['seller_sku'] ?? '',
                'title' => $item['item']['title'] ?? '',
                'quantity' => (int)($item['quantity'] ?? 1),
                'unit_price' => (float)($item['unit_price'] ?? 0),
                'total_price' => (float)($item['unit_price'] ?? 0) * (int)($item['quantity'] ?? 1),
                'category_id' => $item['item']['category_id'] ?? '',
            ];
        }

        return [
            'order_id' => $orderId,
            'order_date' => $order['date_created'] ?? null,
            'marketplace' => 'Mercado Livre',
            'buyer' => [
                'id' => $buyerId,
                'nickname' => $buyer['nickname'] ?? $order['buyer']['nickname'] ?? '',
                'first_name' => $buyer['first_name'] ?? $order['buyer']['first_name'] ?? '',
                'last_name' => $buyer['last_name'] ?? $order['buyer']['last_name'] ?? '',
                'email' => $buyer['email'] ?? '',
                'phone' => $buyer['phone']['number'] ?? '',
                'document' => [
                    'type' => $buyer['identification']['type'] ?? 'CPF',
                    'number' => $buyer['identification']['number'] ?? '',
                ],
            ],
            'shipping_address' => [
                'street' => $shipment['receiver_address']['street_name'] ?? '',
                'number' => $shipment['receiver_address']['street_number'] ?? '',
                'complement' => $shipment['receiver_address']['comment'] ?? '',
                'neighborhood' => $shipment['receiver_address']['neighborhood']['name'] ?? '',
                'city' => $shipment['receiver_address']['city']['name'] ?? '',
                'state' => $shipment['receiver_address']['state']['id'] ?? '',
                'zip_code' => $shipment['receiver_address']['zip_code'] ?? '',
                'country' => 'BR',
            ],
            'items' => $items,
            'totals' => [
                'subtotal' => (float)($order['total_amount'] ?? 0),
                'shipping' => (float)($order['shipping']['cost'] ?? 0),
                'discount' => (float)($order['coupon']['amount'] ?? 0),
                'total' => (float)($order['paid_amount'] ?? $order['total_amount'] ?? 0),
            ],
            'payment_method' => $order['payments'][0]['payment_type'] ?? 'unknown',
        ];
    }

    /**
     * Obtém merchant orders do Mercado Pago
     * API: GET /merchant_orders/search
     *
     * @param array $filters Filtros
     * @return array Lista de merchant orders
     */
    public function searchMerchantOrders(array $filters = []): array
    {
        $client = $this->getClient();
        $sellerId = $this->getSellerId();

        $params = [
            'collector_id' => $sellerId,
            'limit' => $filters['limit'] ?? 20,
            'offset' => $filters['offset'] ?? 0,
        ];

        if (!empty($filters['external_reference'])) {
            $params['external_reference'] = $filters['external_reference'];
        }

        $query = http_build_query($params);
        $data = $client->get("/merchant_orders/search?{$query}");

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao buscar merchant orders'];
        }

        return [
            'total' => $data['total'] ?? 0,
            'elements' => array_map(function ($order) {
                return [
                    'id' => $order['id'],
                    'status' => $order['status'] ?? null,
                    'external_reference' => $order['external_reference'] ?? null,
                    'preference_id' => $order['preference_id'] ?? null,
                    'total_amount' => (float)($order['total_amount'] ?? 0),
                    'paid_amount' => (float)($order['paid_amount'] ?? 0),
                    'refunded_amount' => (float)($order['refunded_amount'] ?? 0),
                    'shipping_cost' => (float)($order['shipping_cost'] ?? 0),
                    'date_created' => $order['date_created'] ?? null,
                    'last_updated' => $order['last_updated'] ?? null,
                    'items' => $order['items'] ?? [],
                    'payments' => array_map(function ($p) {
                        return [
                            'id' => $p['id'],
                            'status' => $p['status'] ?? null,
                            'transaction_amount' => (float)($p['transaction_amount'] ?? 0),
                        ];
                    }, $order['payments'] ?? []),
                ];
            }, $data['elements'] ?? []),
        ];
    }

    /**
     * Calcula análise ABC de produtos (Pareto 80/20)
     *
     * @param string $startDate Data inicial
     * @param string $endDate Data final
     * @return array Análise ABC
     */
    public function calculateABCAnalysis(string $startDate, string $endDate): array
    {
        // Buscar vendas por produto no período
        $stmt = $this->db->prepare("
            SELECT 
                oi.item_id,
                COALESCE(NULLIF(oi.title, ''), oi.item_id) as item_title,
                SUM(quantity) as total_qty,
                SUM(unit_price * quantity) as total_revenue,
                COUNT(DISTINCT o.ml_order_id) as order_count
            FROM order_items oi
            JOIN ml_orders o ON (oi.order_id = o.id OR oi.order_id = o.ml_order_id)
            WHERE o.ml_account_id = :account_id
            AND o.date_created BETWEEN :start_date AND :end_date
            AND o.status = 'paid'
            GROUP BY oi.item_id, item_title
            ORDER BY total_revenue DESC
        ");

        $stmt->execute([
            'account_id' => $this->accountId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (empty($products)) {
            return ['error' => 'Sem dados suficientes para análise ABC'];
        }

        $totalRevenue = array_sum(array_column($products, 'total_revenue'));
        $cumulativeRevenue = 0;
        $classA = [];
        $classB = [];
        $classC = [];

        foreach ($products as $product) {
            $cumulativeRevenue += $product['total_revenue'];
            $cumulativePercentage = ($cumulativeRevenue / $totalRevenue) * 100;

            $product['revenue_percentage'] = round(($product['total_revenue'] / $totalRevenue) * 100, 2);
            $product['cumulative_percentage'] = round($cumulativePercentage, 2);

            if ($cumulativePercentage <= 80) {
                $product['class'] = 'A';
                $classA[] = $product;
            } elseif ($cumulativePercentage <= 95) {
                $product['class'] = 'B';
                $classB[] = $product;
            } else {
                $product['class'] = 'C';
                $classC[] = $product;
            }
        }

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'total_revenue' => $totalRevenue,
            'total_products' => count($products),
            'summary' => [
                'class_a' => [
                    'count' => count($classA),
                    'percentage' => round((count($classA) / count($products)) * 100, 2),
                    'revenue_share' => 80,
                    'description' => 'Produtos vitais - alta receita, prioridade máxima',
                ],
                'class_b' => [
                    'count' => count($classB),
                    'percentage' => round((count($classB) / count($products)) * 100, 2),
                    'revenue_share' => 15,
                    'description' => 'Produtos importantes - receita moderada',
                ],
                'class_c' => [
                    'count' => count($classC),
                    'percentage' => round((count($classC) / count($products)) * 100, 2),
                    'revenue_share' => 5,
                    'description' => 'Produtos de baixa relevância - avaliar descontinuação',
                ],
            ],
            'products' => [
                'class_a' => array_slice($classA, 0, 20),
                'class_b' => array_slice($classB, 0, 10),
                'class_c' => array_slice($classC, 0, 10),
            ],
        ];
    }

    // ============================================================================
    // MERCADO PAGO REPORTS - RELEASES (LIBERAÇÕES)
    // API: /v1/account/release_report
    // ============================================================================

    /**
     * Cria relatório de liberações (dinheiro disponível)
     * API: POST /v1/account/release_report
     *
     * @param string $beginDate Data inicial (formato: Y-m-d\TH:i:s\Z)
     * @param string $endDate Data final
     * @return array Dados do relatório criado
     */
    public function createReleasesReport(string $beginDate, string $endDate): array
    {
        $client = $this->getClient();

        $payload = [
            'begin_date' => $beginDate,
            'end_date' => $endDate,
        ];

        $data = $client->post('/v1/account/release_report', $payload);

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao criar relatório de liberações'];
        }

        return [
            'id' => $data['id'] ?? null,
            'account_id' => $data['account_id'] ?? null,
            'report_id' => $data['report_id'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'begin_date' => $data['begin_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'created_from' => $data['created_from'] ?? 'manual',
            'currency_id' => $data['currency_id'] ?? 'BRL',
            'format' => $data['format'] ?? 'CSV',
            'generation_date' => $data['generation_date'] ?? null,
            'last_modified' => $data['last_modified'] ?? null,
            'is_test' => $data['is_test'] ?? false,
            'retries' => $data['retries'] ?? 0,
            'sub_type' => $data['sub_type'] ?? 'release',
        ];
    }

    /**
     * Consulta lista de relatórios de liberações
     * API: GET /v1/account/release_report/list
     *
     * @param int $limit Limite de resultados
     * @param int $offset Offset para paginação
     * @return array Lista de relatórios
     */
    public function listReleasesReports(int $limit = 50, int $offset = 0): array
    {
        $client = $this->getClient();

        $query = http_build_query([
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $data = $client->get("/v1/account/release_report/list?{$query}");

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao listar relatórios'];
        }

        return [
            'total' => count($data),
            'reports' => array_map(function ($report) {
                return [
                    'id' => $report['id'] ?? null,
                    'report_id' => $report['report_id'] ?? null,
                    'file_name' => $report['file_name'] ?? null,
                    'status' => $report['status'] ?? null,
                    'begin_date' => $report['begin_date'] ?? null,
                    'end_date' => $report['end_date'] ?? null,
                    'created_from' => $report['created_from'] ?? null,
                    'generation_date' => $report['generation_date'] ?? null,
                    'download_url' => $report['download_url'] ?? null,
                ];
            }, $data ?? []),
        ];
    }

    /**
     * Consulta status de tarefa de criação de relatório
     * API: GET /v1/account/release_report/{report_id}
     *
     * @param int $reportId ID da tarefa de criação
     * @return array Status do relatório
     */
    public function getReleasesReportStatus(int $reportId): array
    {
        $client = $this->getClient();

        $data = $client->get("/v1/account/release_report/{$reportId}");

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao consultar relatório'];
        }

        return [
            'id' => $data['id'] ?? null,
            'report_id' => $data['report_id'] ?? null,
            'status' => $data['status'] ?? null,
            'file_name' => $data['file_name'] ?? null,
            'begin_date' => $data['begin_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'download_url' => $data['download_url'] ?? null,
            'generation_date' => $data['generation_date'] ?? null,
            'retries' => $data['retries'] ?? 0,
        ];
    }

    /**
     * Baixa relatório de liberações
     * API: GET /v1/account/release_report/{file_name}
     *
     * @param string $fileName Nome do arquivo do relatório
     * @return array|string Conteúdo do relatório ou erro
     */
    public function downloadReleasesReport(string $fileName): array|string
    {
        $client = $this->getClient();

        // O download retorna CSV raw, não JSON
        $data = $client->get("/v1/account/release_report/{$fileName}");

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao baixar relatório'];
        }

        return $data;
    }

    /**
     * Obtém configurações do relatório de liberações
     * API: GET /v1/account/release_report/config
     *
     * @return array Configurações
     */
    public function getReleasesReportConfig(): array
    {
        $client = $this->getClient();

        $data = $client->get('/v1/account/release_report/config');

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao obter configurações'];
        }

        return [
            'file_name_prefix' => $data['file_name_prefix'] ?? null,
            'display_timezone' => $data['display_timezone'] ?? 'GMT-03',
            'scheduled' => $data['scheduled'] ?? false,
            'frequency' => $data['frequency'] ?? null,
            'sftp_info' => $data['sftp_info'] ?? null,
            'columns' => $data['columns'] ?? [],
            'include_withdraw' => $data['include_withdraw'] ?? true,
            'include_shipping' => $data['include_shipping'] ?? true,
        ];
    }

    /**
     * Cria/Atualiza configurações do relatório de liberações
     * API: POST/PUT /v1/account/release_report/config
     *
     * @param array $config Configurações
     * @param bool $update Se é atualização (PUT) ou criação (POST)
     * @return array Resultado
     */
    public function saveReleasesReportConfig(array $config, bool $update = false): array
    {
        $client = $this->getClient();

        $payload = [
            'display_timezone' => $config['display_timezone'] ?? 'GMT-03',
            'include_withdraw' => $config['include_withdraw'] ?? true,
            'include_shipping' => $config['include_shipping'] ?? true,
        ];

        if (!empty($config['file_name_prefix'])) {
            $payload['file_name_prefix'] = $config['file_name_prefix'];
        }

        if (!empty($config['columns'])) {
            $payload['columns'] = $config['columns'];
        }

        if ($update) {
            $data = $client->put('/v1/account/release_report/config', $payload);
        } else {
            $data = $client->post('/v1/account/release_report/config', $payload);
        }

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao salvar configurações'];
        }

        return [
            'success' => true,
            'config' => $data,
        ];
    }

    /**
     * Ativa geração automática de relatório de liberações
     * API: POST /v1/account/release_report/schedule
     *
     * @return array Resultado
     */
    public function enableReleasesAutoGeneration(): array
    {
        $client = $this->getClient();

        $data = $client->post('/v1/account/release_report/schedule', []);

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao ativar geração automática'];
        }

        return [
            'success' => true,
            'scheduled' => true,
            'message' => 'Geração automática ativada',
        ];
    }

    /**
     * Desativa geração automática de relatório de liberações
     * API: DELETE /v1/account/release_report/schedule
     *
     * @return array Resultado
     */
    public function disableReleasesAutoGeneration(): array
    {
        $client = $this->getClient();

        $data = $client->delete('/v1/account/release_report/schedule');

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao desativar geração automática'];
        }

        return [
            'success' => true,
            'scheduled' => false,
            'message' => 'Geração automática desativada',
        ];
    }

    // ============================================================================
    // MERCADO PAGO REPORTS - SETTLEMENTS (DINHEIRO EM CONTA)
    // API: /v1/account/settlement_report
    // ============================================================================

    /**
     * Cria relatório de dinheiro em conta (settlements)
     * API: POST /v1/account/settlement_report
     *
     * @param string $beginDate Data inicial (formato: Y-m-d\TH:i:s\Z)
     * @param string $endDate Data final
     * @return array Dados do relatório criado
     */
    public function createSettlementsReport(string $beginDate, string $endDate): array
    {
        $client = $this->getClient();

        $payload = [
            'begin_date' => $beginDate,
            'end_date' => $endDate,
        ];

        $data = $client->post('/v1/account/settlement_report', $payload);

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao criar relatório de settlements'];
        }

        return [
            'id' => $data['id'] ?? null,
            'account_id' => $data['account_id'] ?? null,
            'report_id' => $data['report_id'] ?? null,
            'status' => $data['status'] ?? 'pending',
            'begin_date' => $data['begin_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'created_from' => $data['created_from'] ?? 'manual',
            'currency_id' => $data['currency_id'] ?? 'BRL',
            'format' => $data['format'] ?? 'CSV',
            'generation_date' => $data['generation_date'] ?? null,
            'last_modified' => $data['last_modified'] ?? null,
            'is_reserve' => $data['is_reserve'] ?? false,
            'is_test' => $data['is_test'] ?? false,
            'retries' => $data['retries'] ?? 0,
            'report_type' => $data['report_type'] ?? 'settlement',
        ];
    }

    /**
     * Consulta lista de relatórios de settlements
     * API: GET /v1/account/settlement_report/list
     *
     * @param int $limit Limite de resultados
     * @param int $offset Offset para paginação
     * @return array Lista de relatórios
     */
    public function listSettlementsReports(int $limit = 50, int $offset = 0): array
    {
        $client = $this->getClient();

        $query = http_build_query([
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $data = $client->get("/v1/account/settlement_report/list?{$query}");

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao listar relatórios'];
        }

        return [
            'total' => count($data),
            'reports' => array_map(function ($report) {
                return [
                    'id' => $report['id'] ?? null,
                    'report_id' => $report['report_id'] ?? null,
                    'file_name' => $report['file_name'] ?? null,
                    'status' => $report['status'] ?? null,
                    'begin_date' => $report['begin_date'] ?? null,
                    'end_date' => $report['end_date'] ?? null,
                    'created_from' => $report['created_from'] ?? null,
                    'generation_date' => $report['generation_date'] ?? null,
                    'download_url' => $report['download_url'] ?? null,
                    'is_reserve' => $report['is_reserve'] ?? false,
                ];
            }, $data ?? []),
        ];
    }

    /**
     * Consulta status de tarefa de criação de settlement report
     * API: GET /v1/account/settlement_report/{report_id}
     *
     * @param int $reportId ID da tarefa de criação
     * @return array Status do relatório
     */
    public function getSettlementsReportStatus(int $reportId): array
    {
        $client = $this->getClient();

        $data = $client->get("/v1/account/settlement_report/{$reportId}");

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao consultar relatório'];
        }

        return [
            'id' => $data['id'] ?? null,
            'report_id' => $data['report_id'] ?? null,
            'status' => $data['status'] ?? null,
            'file_name' => $data['file_name'] ?? null,
            'begin_date' => $data['begin_date'] ?? null,
            'end_date' => $data['end_date'] ?? null,
            'download_url' => $data['download_url'] ?? null,
            'generation_date' => $data['generation_date'] ?? null,
            'is_reserve' => $data['is_reserve'] ?? false,
            'retries' => $data['retries'] ?? 0,
        ];
    }

    /**
     * Baixa relatório de settlements
     * API: GET /v1/account/settlement_report/{file_name}
     *
     * @param string $fileName Nome do arquivo do relatório
     * @return array|string Conteúdo do relatório ou erro
     */
    public function downloadSettlementsReport(string $fileName): array|string
    {
        $client = $this->getClient();

        $data = $client->get("/v1/account/settlement_report/{$fileName}");

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao baixar relatório'];
        }

        return $data;
    }

    /**
     * Obtém configurações do relatório de settlements
     * API: GET /v1/account/settlement_report/config
     *
     * @return array Configurações
     */
    public function getSettlementsReportConfig(): array
    {
        $client = $this->getClient();

        $data = $client->get('/v1/account/settlement_report/config');

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao obter configurações'];
        }

        return [
            'file_name_prefix' => $data['file_name_prefix'] ?? null,
            'display_timezone' => $data['display_timezone'] ?? 'GMT-03',
            'scheduled' => $data['scheduled'] ?? false,
            'frequency' => $data['frequency'] ?? null,
            'sftp_info' => $data['sftp_info'] ?? null,
            'columns' => $data['columns'] ?? [],
            'separator' => $data['separator'] ?? ',',
        ];
    }

    /**
     * Cria/Atualiza configurações do relatório de settlements
     * API: POST/PUT /v1/account/settlement_report/config
     *
     * @param array $config Configurações
     * @param bool $update Se é atualização (PUT) ou criação (POST)
     * @return array Resultado
     */
    public function saveSettlementsReportConfig(array $config, bool $update = false): array
    {
        $client = $this->getClient();

        $payload = [
            'display_timezone' => $config['display_timezone'] ?? 'GMT-03',
            'separator' => $config['separator'] ?? ',',
        ];

        if (!empty($config['file_name_prefix'])) {
            $payload['file_name_prefix'] = $config['file_name_prefix'];
        }

        if (!empty($config['columns'])) {
            $payload['columns'] = $config['columns'];
        }

        if ($update) {
            $data = $client->put('/v1/account/settlement_report/config', $payload);
        } else {
            $data = $client->post('/v1/account/settlement_report/config', $payload);
        }

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao salvar configurações'];
        }

        return [
            'success' => true,
            'config' => $data,
        ];
    }

    /**
     * Ativa geração automática de relatório de settlements
     * API: POST /v1/account/settlement_report/schedule
     *
     * @return array Resultado
     */
    public function enableSettlementsAutoGeneration(): array
    {
        $client = $this->getClient();

        $data = $client->post('/v1/account/settlement_report/schedule', []);

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao ativar geração automática'];
        }

        return [
            'success' => true,
            'scheduled' => true,
            'message' => 'Geração automática ativada',
        ];
    }

    /**
     * Desativa geração automática de relatório de settlements
     * API: DELETE /v1/account/settlement_report/schedule
     *
     * @return array Resultado
     */
    public function disableSettlementsAutoGeneration(): array
    {
        $client = $this->getClient();

        $data = $client->delete('/v1/account/settlement_report/schedule');

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao desativar geração automática'];
        }

        return [
            'success' => true,
            'scheduled' => false,
            'message' => 'Geração automática desativada',
        ];
    }

    // ============================================================================
    // CONSOLIDATED REPORTS & ANALYTICS
    // ============================================================================

    /**
     * Gera relatório consolidado de todos os tipos de relatórios MP
     *
     * @param string $beginDate Data inicial
     * @param string $endDate Data final
     * @return array Status dos relatórios
     */
    public function generateConsolidatedMPReports(string $beginDate, string $endDate): array
    {
        $releases = $this->createReleasesReport($beginDate, $endDate);
        $settlements = $this->createSettlementsReport($beginDate, $endDate);

        return [
            'period' => [
                'begin_date' => $beginDate,
                'end_date' => $endDate,
            ],
            'releases_report' => [
                'status' => isset($releases['error']) ? 'error' : 'created',
                'data' => $releases,
            ],
            'settlements_report' => [
                'status' => isset($settlements['error']) ? 'error' : 'created',
                'data' => $settlements,
            ],
            'instructions' => 'Aguarde alguns minutos para os relatórios serem gerados. Consulte o status com getReleasesReportStatus() e getSettlementsReportStatus().',
        ];
    }

    /**
     * Verifica status de todos os relatórios pendentes
     *
     * @return array Status consolidado
     */
    public function checkPendingReports(): array
    {
        $releases = $this->listReleasesReports(10, 0);
        $settlements = $this->listSettlementsReports(10, 0);

        $pendingReleases = [];
        $pendingSettlements = [];

        if (!isset($releases['error'])) {
            foreach ($releases['reports'] ?? [] as $report) {
                if ($report['status'] === 'pending') {
                    $pendingReleases[] = $report;
                }
            }
        }

        if (!isset($settlements['error'])) {
            foreach ($settlements['reports'] ?? [] as $report) {
                if ($report['status'] === 'pending') {
                    $pendingSettlements[] = $report;
                }
            }
        }

        return [
            'pending_releases' => [
                'count' => count($pendingReleases),
                'reports' => $pendingReleases,
            ],
            'pending_settlements' => [
                'count' => count($pendingSettlements),
                'reports' => $pendingSettlements,
            ],
            'total_pending' => count($pendingReleases) + count($pendingSettlements),
        ];
    }

    /**
     * Obtém todos os relatórios prontos para download
     *
     * @param int $limit Limite
     * @return array Relatórios prontos
     */
    public function getReadyReports(int $limit = 20): array
    {
        $releases = $this->listReleasesReports($limit, 0);
        $settlements = $this->listSettlementsReports($limit, 0);

        $readyReleases = [];
        $readySettlements = [];

        if (!isset($releases['error'])) {
            foreach ($releases['reports'] ?? [] as $report) {
                if ($report['status'] === 'ready' && !empty($report['download_url'])) {
                    $readyReleases[] = $report;
                }
            }
        }

        if (!isset($settlements['error'])) {
            foreach ($settlements['reports'] ?? [] as $report) {
                if ($report['status'] === 'ready' && !empty($report['download_url'])) {
                    $readySettlements[] = $report;
                }
            }
        }

        return [
            'releases' => [
                'count' => count($readyReleases),
                'reports' => $readyReleases,
            ],
            'settlements' => [
                'count' => count($readySettlements),
                'reports' => $readySettlements,
            ],
            'total_ready' => count($readyReleases) + count($readySettlements),
        ];
    }

    // ============================================================================
    // FINANCIAL FORECASTING & PROJECTIONS
    // ============================================================================

    /**
     * Calcula projeção financeira baseada em tendências
     *
     * @param int $monthsAhead Meses à frente para projetar
     * @return array Projeções
     */
    public function calculateFinancialForecast(int $monthsAhead = 3): array
    {
        // Buscar dados históricos dos últimos 6 meses
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(date_created, '%Y-%m') as month,
                COUNT(*) as order_count,
                SUM(total_amount) as revenue,
                AVG(total_amount) as avg_ticket
            FROM ml_orders
            WHERE ml_account_id = :account_id
            AND status = 'paid'
            AND date_created >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(date_created, '%Y-%m')
            ORDER BY month ASC
        ");

        $stmt->execute(['account_id' => $this->accountId]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($history) < 3) {
            return ['error' => 'Dados insuficientes para projeção (mínimo 3 meses)'];
        }

        // Calcular tendências usando regressão linear simples
        $revenues = array_column($history, 'revenue');
        $orderCounts = array_column($history, 'order_count');
        $avgTickets = array_column($history, 'avg_ticket');

        $n = count($revenues);
        $xSum = ($n * ($n - 1)) / 2; // 0+1+2+...+(n-1)
        $x2Sum = ($n * ($n - 1) * (2 * $n - 1)) / 6;

        // Tendência de receita
        $revenueYSum = array_sum($revenues);
        $revenueXYSum = 0;
        foreach ($revenues as $i => $r) {
            $revenueXYSum += $i * $r;
        }
        $revenueSlope = ($n * $revenueXYSum - $xSum * $revenueYSum) / ($n * $x2Sum - $xSum * $xSum);
        $revenueIntercept = ($revenueYSum - $revenueSlope * $xSum) / $n;

        // Tendência de pedidos
        $orderYSum = array_sum($orderCounts);
        $orderXYSum = 0;
        foreach ($orderCounts as $i => $o) {
            $orderXYSum += $i * $o;
        }
        $orderSlope = ($n * $orderXYSum - $xSum * $orderYSum) / ($n * $x2Sum - $xSum * $xSum);
        $orderIntercept = ($orderYSum - $orderSlope * $xSum) / $n;

        // Gerar projeções
        $projections = [];
        $currentMonth = new \DateTime();
        for ($i = 1; $i <= $monthsAhead; $i++) {
            $currentMonth->modify('+1 month');
            $monthKey = $currentMonth->format('Y-m');
            $x = $n + $i - 1;

            $projectedRevenue = max(0, $revenueIntercept + $revenueSlope * $x);
            $projectedOrders = max(0, round($orderIntercept + $orderSlope * $x));
            $projectedTicket = $projectedOrders > 0 ? $projectedRevenue / $projectedOrders : 0;

            $projections[] = [
                'month' => $monthKey,
                'projected_revenue' => round($projectedRevenue, 2),
                'projected_orders' => $projectedOrders,
                'projected_avg_ticket' => round($projectedTicket, 2),
                'confidence' => $this->calculateForecastConfidence($history, $i),
            ];
        }

        // Calcular crescimento esperado
        $lastRevenue = end($revenues);
        $projectedTotal = array_sum(array_column($projections, 'projected_revenue'));
        $growthRate = $lastRevenue > 0 ? (($projections[0]['projected_revenue'] - $lastRevenue) / $lastRevenue) * 100 : 0;

        return [
            'historical_data' => $history,
            'projections' => $projections,
            'trends' => [
                'revenue_trend' => $revenueSlope > 0 ? 'growing' : ($revenueSlope < 0 ? 'declining' : 'stable'),
                'revenue_slope' => round($revenueSlope, 2),
                'orders_trend' => $orderSlope > 0 ? 'growing' : ($orderSlope < 0 ? 'declining' : 'stable'),
                'orders_slope' => round($orderSlope, 2),
            ],
            'summary' => [
                'projected_revenue_total' => round($projectedTotal, 2),
                'expected_monthly_growth_rate' => round($growthRate, 2),
                'avg_historical_revenue' => round(array_sum($revenues) / $n, 2),
                'avg_historical_orders' => round(array_sum($orderCounts) / $n),
            ],
        ];
    }

    /**
     * Calcula confiança da projeção
     *
     * @param array $history Dados históricos
     * @param int $monthsAhead Meses à frente
     * @return string Nível de confiança
     */
    private function calculateForecastConfidence(array $history, int $monthsAhead): string
    {
        $dataPoints = count($history);

        // Mais dados históricos = maior confiança
        // Mais meses à frente = menor confiança
        $baseConfidence = min(100, $dataPoints * 15);
        $decayFactor = max(0, 100 - ($monthsAhead * 20));

        $confidence = ($baseConfidence * $decayFactor) / 100;

        if ($confidence >= 70) {
            return 'high';
        } elseif ($confidence >= 40) {
            return 'medium';
        }
        return 'low';
    }

    /**
     * Calcula metas financeiras e progresso
     *
     * @param float $monthlyTarget Meta mensal
     * @return array Progresso das metas
     */
    public function calculateGoalProgress(float $monthlyTarget): array
    {
        $currentMonth = date('Y-m');
        $startOfMonth = date('Y-m-01');
        $today = date('Y-m-d');
        $daysInMonth = date('t');
        $dayOfMonth = date('j');

        // Receita atual do mês
        $stmt = $this->db->prepare("
            SELECT 
                SUM(total_amount) as revenue,
                COUNT(*) as orders
            FROM ml_orders
            WHERE ml_account_id = :account_id
            AND status = 'paid'
            AND date_created >= :start_date
        ");

        $stmt->execute([
            'account_id' => $this->accountId,
            'start_date' => $startOfMonth,
        ]);

        $current = $stmt->fetch(PDO::FETCH_ASSOC);
        $currentRevenue = (float)($current['revenue'] ?? 0);
        $currentOrders = (int)($current['orders'] ?? 0);

        // Calcular projeção para fim do mês
        $dailyAverage = $dayOfMonth > 0 ? $currentRevenue / $dayOfMonth : 0;
        $projectedMonthEnd = $dailyAverage * $daysInMonth;

        // Calcular quanto falta por dia
        $remainingDays = $daysInMonth - $dayOfMonth;
        $remainingAmount = max(0, $monthlyTarget - $currentRevenue);
        $dailyRequired = $remainingDays > 0 ? $remainingAmount / $remainingDays : $remainingAmount;

        // Progresso percentual
        $progressPercentage = $monthlyTarget > 0 ? ($currentRevenue / $monthlyTarget) * 100 : 0;
        $expectedProgress = ($dayOfMonth / $daysInMonth) * 100;

        return [
            'period' => $currentMonth,
            'target' => [
                'monthly' => $monthlyTarget,
                'daily_average_needed' => round($monthlyTarget / $daysInMonth, 2),
            ],
            'current' => [
                'revenue' => round($currentRevenue, 2),
                'orders' => $currentOrders,
                'daily_average' => round($dailyAverage, 2),
            ],
            'progress' => [
                'percentage' => round($progressPercentage, 2),
                'expected_percentage' => round($expectedProgress, 2),
                'ahead_or_behind' => $progressPercentage >= $expectedProgress ? 'ahead' : 'behind',
                'difference_percentage' => round($progressPercentage - $expectedProgress, 2),
            ],
            'projection' => [
                'month_end_estimated' => round($projectedMonthEnd, 2),
                'will_achieve_target' => $projectedMonthEnd >= $monthlyTarget,
                'remaining_amount' => round($remainingAmount, 2),
                'remaining_days' => $remainingDays,
                'daily_required_to_achieve' => round($dailyRequired, 2),
            ],
            'status' => $this->getGoalStatus($progressPercentage, $expectedProgress),
        ];
    }

    /**
     * Retorna status visual da meta
     */
    private function getGoalStatus(float $progress, float $expected): string
    {
        $diff = $progress - $expected;

        if ($progress >= 100) {
            return 'achieved';
        } elseif ($diff >= 10) {
            return 'excellent';
        } elseif ($diff >= 0) {
            return 'on_track';
        } elseif ($diff >= -10) {
            return 'attention';
        }
        return 'critical';
    }

    // ============================================================================
    // WITHDRAWALS HISTORY
    // ============================================================================

    /**
     * Obtém histórico de saques/transferências
     * API: GET /v1/account/bank_report/list ou search
     *
     * @param int $limit Limite
     * @param int $offset Offset
     * @return array Histórico
     */
    public function getWithdrawalHistory(int $limit = 20, int $offset = 0): array
    {
        $client = $this->getClient();
        $sellerId = $this->getSellerId();

        // Buscar movimentações de saque
        $query = http_build_query([
            'user_id' => $sellerId,
            'limit' => $limit,
            'offset' => $offset,
        ]);

        $data = $client->get("/v1/account/bank_report/list?{$query}");

        if (isset($data['error'])) {
            // Tentar endpoint alternativo
            $data = $client->get("/users/{$sellerId}/mercadopago_account/movements?{$query}");
        }

        if (isset($data['error'])) {
            return ['error' => $data['message'] ?? 'Erro ao obter histórico de saques'];
        }

        return [
            'total' => $data['paging']['total'] ?? count($data),
            'withdrawals' => array_map(function ($w) {
                return [
                    'id' => $w['id'] ?? null,
                    'type' => $w['type'] ?? null,
                    'amount' => (float)($w['amount'] ?? 0),
                    'currency_id' => $w['currency_id'] ?? 'BRL',
                    'status' => $w['status'] ?? null,
                    'date_created' => $w['date_created'] ?? null,
                    'bank_info' => $w['bank_info'] ?? null,
                ];
            }, $data['results'] ?? $data ?? []),
        ];
    }

    // ============================================================================
    // FINANCIAL ALERTS & NOTIFICATIONS
    // ============================================================================

    /**
     * Verifica alertas financeiros baseados em regras
     *
     * @return array Lista de alertas
     */
    public function checkFinancialAlerts(): array
    {
        $alerts = [];

        // 1. Verificar taxa de reembolso alta
        $refundRate = $this->calculateRefundRate();
        if ($refundRate > 5) {
            $alerts[] = [
                'type' => 'high_refund_rate',
                'severity' => $refundRate > 10 ? 'critical' : 'warning',
                'message' => "Taxa de reembolso alta: {$refundRate}%",
                'recommendation' => 'Revise a qualidade dos produtos e descrições',
            ];
        }

        // 2. Verificar chargebacks
        $chargebackData = $this->getChargebacksRefundsReport(
            date('Y-m-01'),
            date('Y-m-d')
        );
        if (!isset($chargebackData['error']) && ($chargebackData['chargebacks']['total_count'] ?? 0) > 0) {
            $alerts[] = [
                'type' => 'chargebacks_detected',
                'severity' => 'critical',
                'message' => "Chargebacks detectados: " . ($chargebackData['chargebacks']['total_count'] ?? 0),
                'recommendation' => 'Responda às contestações imediatamente',
            ];
        }

        // 3. Verificar queda de receita
        $forecast = $this->calculateFinancialForecast(1);
        if (!isset($forecast['error']) && ($forecast['trends']['revenue_trend'] ?? '') === 'declining') {
            $alerts[] = [
                'type' => 'revenue_declining',
                'severity' => 'warning',
                'message' => 'Tendência de queda na receita detectada',
                'recommendation' => 'Analise causas e ajuste estratégias',
            ];
        }

        // 4. Verificar saldo baixo (se disponível)
        $balance = $this->getAccountBalance();
        if (!isset($balance['error']) && ($balance['available_balance'] ?? 0) < 100) {
            $alerts[] = [
                'type' => 'low_balance',
                'severity' => 'info',
                'message' => 'Saldo disponível baixo: R$ ' . number_format($balance['available_balance'] ?? 0, 2, ',', '.'),
                'recommendation' => 'Considere transferir saldo quando disponível',
            ];
        }

        // 5. Verificar relatórios pendentes
        $pendingReports = $this->checkPendingReports();
        if (($pendingReports['total_pending'] ?? 0) > 5) {
            $alerts[] = [
                'type' => 'many_pending_reports',
                'severity' => 'info',
                'message' => 'Muitos relatórios pendentes: ' . $pendingReports['total_pending'],
                'recommendation' => 'Aguarde processamento ou verifique erros',
            ];
        }

        return [
            'timestamp' => date('Y-m-d H:i:s'),
            'total_alerts' => count($alerts),
            'by_severity' => [
                'critical' => count(array_filter($alerts, fn($a) => $a['severity'] === 'critical')),
                'warning' => count(array_filter($alerts, fn($a) => $a['severity'] === 'warning')),
                'info' => count(array_filter($alerts, fn($a) => $a['severity'] === 'info')),
            ],
            'alerts' => $alerts,
        ];
    }

    /**
     * Calcula taxa de reembolso
     *
     * @return float Taxa em porcentagem
     */
    private function calculateRefundRate(): float
    {
        $startDate = date('Y-m-01', strtotime('-3 months'));
        $endDate = date('Y-m-d');

        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_orders,
                SUM(CASE WHEN status = 'refunded' THEN 1 ELSE 0 END) as refunded_orders
            FROM ml_orders
            WHERE ml_account_id = :account_id
            AND date_created BETWEEN :start_date AND :end_date
        ");

        $stmt->execute([
            'account_id' => $this->accountId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);
        $totalOrders = (int)($data['total_orders'] ?? 0);
        $refundedOrders = (int)($data['refunded_orders'] ?? 0);

        return $totalOrders > 0 ? round(($refundedOrders / $totalOrders) * 100, 2) : 0;
    }

    // ============================================================================
    // DETAILED PERIOD COMPARISONS
    // ============================================================================

    /**
     * Compara dois períodos financeiros com análise detalhada
     *
     * @param string $period1Start Início período 1
     * @param string $period1End Fim período 1
     * @param string $period2Start Início período 2
     * @param string $period2End Fim período 2
     * @return array Comparação
     */
    public function compareFinancialPeriods(
        string $period1Start,
        string $period1End,
        string $period2Start,
        string $period2End
    ): array {
        $period1 = $this->getPeriodMetrics($period1Start, $period1End);
        $period2 = $this->getPeriodMetrics($period2Start, $period2End);

        $calculateVariation = function ($current, $previous) {
            if ($previous == 0) {
                return $current > 0 ? 100 : 0;
            }
            return round((($current - $previous) / $previous) * 100, 2);
        };

        return [
            'period_1' => [
                'range' => ['start' => $period1Start, 'end' => $period1End],
                'metrics' => $period1,
            ],
            'period_2' => [
                'range' => ['start' => $period2Start, 'end' => $period2End],
                'metrics' => $period2,
            ],
            'variations' => [
                'revenue' => [
                    'absolute' => round($period2['revenue'] - $period1['revenue'], 2),
                    'percentage' => $calculateVariation($period2['revenue'], $period1['revenue']),
                ],
                'orders' => [
                    'absolute' => $period2['orders'] - $period1['orders'],
                    'percentage' => $calculateVariation($period2['orders'], $period1['orders']),
                ],
                'avg_ticket' => [
                    'absolute' => round($period2['avg_ticket'] - $period1['avg_ticket'], 2),
                    'percentage' => $calculateVariation($period2['avg_ticket'], $period1['avg_ticket']),
                ],
                'items_sold' => [
                    'absolute' => $period2['items_sold'] - $period1['items_sold'],
                    'percentage' => $calculateVariation($period2['items_sold'], $period1['items_sold']),
                ],
            ],
            'analysis' => $this->analyzePeriodComparison($period1, $period2),
        ];
    }

    /**
     * Obtém métricas de um período
     */
    private function getPeriodMetrics(string $startDate, string $endDate): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as orders,
                COALESCE(SUM(total_amount), 0) as revenue,
                COALESCE(AVG(total_amount), 0) as avg_ticket,
                COALESCE(SUM(
                    (SELECT COALESCE(SUM(quantity), 0) FROM order_items WHERE order_id = ml_orders.id)
                ), 0) as items_sold
            FROM ml_orders
            WHERE ml_account_id = :account_id
            AND status = 'paid'
            AND date_created BETWEEN :start_date AND :end_date
        ");

        $stmt->execute([
            'account_id' => $this->accountId,
            'start_date' => $startDate,
            'end_date' => $endDate,
        ]);

        $data = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'orders' => (int)($data['orders'] ?? 0),
            'revenue' => round((float)($data['revenue'] ?? 0), 2),
            'avg_ticket' => round((float)($data['avg_ticket'] ?? 0), 2),
            'items_sold' => (int)($data['items_sold'] ?? 0),
        ];
    }

    /**
     * Analisa comparação de períodos
     */
    private function analyzePeriodComparison(array $period1, array $period2): array
    {
        $insights = [];

        // Análise de receita
        $revenueDiff = $period2['revenue'] - $period1['revenue'];
        if ($revenueDiff > 0) {
            $insights[] = [
                'metric' => 'revenue',
                'status' => 'positive',
                'message' => 'Receita aumentou R$ ' . number_format($revenueDiff, 2, ',', '.'),
            ];
        } elseif ($revenueDiff < 0) {
            $insights[] = [
                'metric' => 'revenue',
                'status' => 'negative',
                'message' => 'Receita diminuiu R$ ' . number_format(abs($revenueDiff), 2, ',', '.'),
            ];
        }

        // Análise de ticket médio
        $ticketDiff = $period2['avg_ticket'] - $period1['avg_ticket'];
        if ($ticketDiff > 0) {
            $insights[] = [
                'metric' => 'avg_ticket',
                'status' => 'positive',
                'message' => 'Ticket médio aumentou R$ ' . number_format($ticketDiff, 2, ',', '.'),
            ];
        } elseif ($ticketDiff < 0) {
            $insights[] = [
                'metric' => 'avg_ticket',
                'status' => 'negative',
                'message' => 'Ticket médio diminuiu R$ ' . number_format(abs($ticketDiff), 2, ',', '.'),
            ];
        }

        // Análise de volume
        $ordersDiff = $period2['orders'] - $period1['orders'];
        if ($ordersDiff > 0) {
            $insights[] = [
                'metric' => 'orders',
                'status' => 'positive',
                'message' => "Volume de pedidos aumentou em {$ordersDiff} pedidos",
            ];
        } elseif ($ordersDiff < 0) {
            $insights[] = [
                'metric' => 'orders',
                'status' => 'negative',
                'message' => 'Volume de pedidos diminuiu em ' . abs($ordersDiff) . ' pedidos',
            ];
        }

        return $insights;
    }

    // =========================================================================
    // MERCADO PAGO - ASSINATURAS (SUBSCRIPTIONS/PREAPPROVAL)
    // =========================================================================

    /**
     * Cria uma nova assinatura no MP
     * POST /preapproval
     *
     * @param array $subscriptionData Dados da assinatura
     * @return array Dados da assinatura criada
     */
    public function createSubscription(array $subscriptionData): array
    {
        $client = $this->getMercadoPagoClient();

        // Estrutura padrão da assinatura
        $payload = [
            'payer_email' => $subscriptionData['payer_email'],
            'back_url' => $subscriptionData['back_url'] ?? ($_ENV['APP_URL'] ?? 'https://eskill.com.br') . '/subscriptions/callback',
            'status' => $subscriptionData['status'] ?? 'pending',
        ];

        // Assinatura com plano existente
        if (!empty($subscriptionData['preapproval_plan_id'])) {
            $payload['preapproval_plan_id'] = $subscriptionData['preapproval_plan_id'];
        } else {
            // Assinatura sem plano (valores customizados)
            $payload['reason'] = $subscriptionData['reason'];
            $payload['external_reference'] = $subscriptionData['external_reference'] ?? null;
            $payload['auto_recurring'] = [
                'frequency' => $subscriptionData['frequency'] ?? 1,
                'frequency_type' => $subscriptionData['frequency_type'] ?? 'months',
                'transaction_amount' => $subscriptionData['transaction_amount'],
                'currency_id' => 'BRL',
            ];

            if (!empty($subscriptionData['start_date'])) {
                $payload['auto_recurring']['start_date'] = $subscriptionData['start_date'];
            }
            if (!empty($subscriptionData['end_date'])) {
                $payload['auto_recurring']['end_date'] = $subscriptionData['end_date'];
            }
            if (!empty($subscriptionData['free_trial_days'])) {
                $payload['auto_recurring']['free_trial'] = [
                    'frequency' => $subscriptionData['free_trial_days'],
                    'frequency_type' => 'days',
                ];
            }
        }

        // Token do cartão para cobrança automática
        if (!empty($subscriptionData['card_token_id'])) {
            $payload['card_token_id'] = $subscriptionData['card_token_id'];
        }

        $response = $client->post('https://api.mercadopago.com/preapproval', $payload);

        // Salva localmente
        if (isset($response['id'])) {
            $this->saveSubscriptionLocally($response);
        }

        return $response;
    }

    /**
     * Busca assinaturas no MP
     * GET /preapproval/search
     *
     * @param array $filters Filtros da busca
     * @return array Lista de assinaturas
     */
    public function searchSubscriptions(array $filters = []): array
    {
        $client = $this->getMercadoPagoClient();

        $params = [];

        if (!empty($filters['status'])) {
            $params['status'] = $filters['status']; // pending, authorized, paused, cancelled
        }
        if (!empty($filters['payer_email'])) {
            $params['payer_email'] = $filters['payer_email'];
        }
        if (!empty($filters['payer_id'])) {
            $params['payer_id'] = $filters['payer_id'];
        }
        if (!empty($filters['preapproval_plan_id'])) {
            $params['preapproval_plan_id'] = $filters['preapproval_plan_id'];
        }

        $params['offset'] = $filters['offset'] ?? 0;
        $params['limit'] = $filters['limit'] ?? 50;

        $queryString = http_build_query($params);
        return $client->get("https://api.mercadopago.com/preapproval/search?{$queryString}");
    }

    /**
     * Obtém detalhes de uma assinatura
     * GET /preapproval/{id}
     *
     * @param string $subscriptionId ID da assinatura
     * @return array Detalhes da assinatura
     */
    public function getSubscription(string $subscriptionId): array
    {
        $client = $this->getMercadoPagoClient();
        return $client->get("https://api.mercadopago.com/preapproval/{$subscriptionId}");
    }

    /**
     * Atualiza uma assinatura
     * PUT /preapproval/{id}
     *
     * @param string $subscriptionId ID da assinatura
     * @param array $updateData Dados para atualizar
     * @return array Assinatura atualizada
     */
    public function updateSubscription(string $subscriptionId, array $updateData): array
    {
        $client = $this->getMercadoPagoClient();

        $payload = [];

        // Campos atualizáveis
        if (isset($updateData['status'])) {
            $payload['status'] = $updateData['status']; // authorized, paused, cancelled
        }
        if (isset($updateData['back_url'])) {
            $payload['back_url'] = $updateData['back_url'];
        }
        if (isset($updateData['card_token_id'])) {
            $payload['card_token_id'] = $updateData['card_token_id'];
        }
        if (isset($updateData['reason'])) {
            $payload['reason'] = $updateData['reason'];
        }

        // Atualização de valores recorrentes
        if (!empty($updateData['auto_recurring'])) {
            $payload['auto_recurring'] = $updateData['auto_recurring'];
        }

        $response = $client->put("https://api.mercadopago.com/preapproval/{$subscriptionId}", $payload);

        // Atualiza localmente
        if (isset($response['id'])) {
            $this->updateSubscriptionLocally($response);
        }

        return $response;
    }

    /**
     * Pausa uma assinatura
     *
     * @param string $subscriptionId ID da assinatura
     * @return array Assinatura pausada
     */
    public function pauseSubscription(string $subscriptionId): array
    {
        return $this->updateSubscription($subscriptionId, ['status' => 'paused']);
    }

    /**
     * Reativa uma assinatura pausada
     *
     * @param string $subscriptionId ID da assinatura
     * @return array Assinatura reativada
     */
    public function activateSubscription(string $subscriptionId): array
    {
        return $this->updateSubscription($subscriptionId, ['status' => 'authorized']);
    }

    /**
     * Cancela uma assinatura
     *
     * @param string $subscriptionId ID da assinatura
     * @return array Assinatura cancelada
     */
    public function cancelSubscription(string $subscriptionId): array
    {
        return $this->updateSubscription($subscriptionId, ['status' => 'cancelled']);
    }

    /**
     * Exporta assinaturas para CSV
     * GET /preapproval/export
     *
     * @param array $filters Filtros para exportação
     * @return array URL do arquivo para download
     */
    public function exportSubscriptions(array $filters = []): array
    {
        $client = $this->getMercadoPagoClient();

        $params = [];
        if (!empty($filters['status'])) {
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['date_from'])) {
            $params['date_created_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $params['date_created_to'] = $filters['date_to'];
        }

        $queryString = http_build_query($params);
        return $client->get("https://api.mercadopago.com/preapproval/export?{$queryString}");
    }

    // =========================================================================
    // MERCADO PAGO - PLANOS DE ASSINATURA
    // =========================================================================

    /**
     * Cria um plano de assinatura
     * POST /preapproval_plan
     *
     * @param array $planData Dados do plano
     * @return array Plano criado
     */
    public function createSubscriptionPlan(array $planData): array
    {
        $client = $this->getMercadoPagoClient();

        $payload = [
            'reason' => $planData['reason'],
            'auto_recurring' => [
                'frequency' => $planData['frequency'] ?? 1,
                'frequency_type' => $planData['frequency_type'] ?? 'months',
                'transaction_amount' => $planData['transaction_amount'],
                'currency_id' => 'BRL',
                'billing_day' => $planData['billing_day'] ?? 10,
                'billing_day_proportional' => $planData['billing_day_proportional'] ?? true,
            ],
            'back_url' => $planData['back_url'] ?? ($_ENV['APP_URL'] ?? 'https://eskill.com.br') . '/subscriptions/callback',
        ];

        // Período de teste gratuito
        if (!empty($planData['free_trial_days'])) {
            $payload['auto_recurring']['free_trial'] = [
                'frequency' => $planData['free_trial_days'],
                'frequency_type' => 'days',
            ];
        }

        // Repetições (número de cobranças)
        if (!empty($planData['repetitions'])) {
            $payload['auto_recurring']['repetitions'] = $planData['repetitions'];
        }

        $response = $client->post('https://api.mercadopago.com/preapproval_plan', $payload);

        // Salva localmente
        if (isset($response['id'])) {
            $this->saveSubscriptionPlanLocally($response);
        }

        return $response;
    }

    /**
     * Busca planos de assinatura
     * GET /preapproval_plan/search
     *
     * @param array $filters Filtros
     * @return array Lista de planos
     */
    public function searchSubscriptionPlans(array $filters = []): array
    {
        $client = $this->getMercadoPagoClient();

        $params = [];
        if (!empty($filters['status'])) {
            $params['status'] = $filters['status']; // active, inactive
        }
        $params['offset'] = $filters['offset'] ?? 0;
        $params['limit'] = $filters['limit'] ?? 50;

        $queryString = http_build_query($params);
        return $client->get("https://api.mercadopago.com/preapproval_plan/search?{$queryString}");
    }

    /**
     * Obtém detalhes de um plano de assinatura
     * GET /preapproval_plan/{id}
     *
     * @param string $planId ID do plano
     * @return array Detalhes do plano
     */
    public function getSubscriptionPlan(string $planId): array
    {
        $client = $this->getMercadoPagoClient();
        return $client->get("https://api.mercadopago.com/preapproval_plan/{$planId}");
    }

    /**
     * Atualiza um plano de assinatura
     * PUT /preapproval_plan/{id}
     *
     * @param string $planId ID do plano
     * @param array $updateData Dados para atualizar
     * @return array Plano atualizado
     */
    public function updateSubscriptionPlan(string $planId, array $updateData): array
    {
        $client = $this->getMercadoPagoClient();

        $payload = [];
        if (isset($updateData['reason'])) {
            $payload['reason'] = $updateData['reason'];
        }
        if (isset($updateData['back_url'])) {
            $payload['back_url'] = $updateData['back_url'];
        }
        if (isset($updateData['status'])) {
            $payload['status'] = $updateData['status']; // active, inactive
        }
        if (!empty($updateData['auto_recurring'])) {
            $payload['auto_recurring'] = $updateData['auto_recurring'];
        }

        return $client->put("https://api.mercadopago.com/preapproval_plan/{$planId}", $payload);
    }

    // =========================================================================
    // MERCADO PAGO - FATURAS DE ASSINATURA
    // =========================================================================

    /**
     * Obtém informação de uma fatura de assinatura
     * GET /authorized_payments/{id}
     *
     * @param string $invoiceId ID da fatura/pagamento autorizado
     * @return array Detalhes da fatura
     */
    public function getSubscriptionInvoice(string $invoiceId): array
    {
        $client = $this->getMercadoPagoClient();
        return $client->get("https://api.mercadopago.com/authorized_payments/{$invoiceId}");
    }

    /**
     * Busca faturas de assinaturas
     * GET /authorized_payments/search
     *
     * @param array $filters Filtros
     * @return array Lista de faturas
     */
    public function searchSubscriptionInvoices(array $filters = []): array
    {
        $client = $this->getMercadoPagoClient();

        $params = [];
        if (!empty($filters['preapproval_id'])) {
            $params['preapproval_id'] = $filters['preapproval_id'];
        }
        if (!empty($filters['status'])) {
            $params['status'] = $filters['status']; // scheduled, processed, recycling, cancelled
        }
        $params['offset'] = $filters['offset'] ?? 0;
        $params['limit'] = $filters['limit'] ?? 50;

        $queryString = http_build_query($params);
        return $client->get("https://api.mercadopago.com/authorized_payments/search?{$queryString}");
    }

    /**
     * Obtém análise de receita recorrente (MRR)
     *
     * @return array Análise de receita recorrente
     */
    public function getRecurringRevenueAnalysis(): array
    {
        // Busca assinaturas ativas
        $activeSubscriptions = $this->searchSubscriptions(['status' => 'authorized', 'limit' => 100]);

        $mrr = 0;
        $arr = 0;
        $subscriptionsByPlan = [];
        $subscriptionsByFrequency = [];
        $nextPayments = [];

        foreach ($activeSubscriptions['results'] ?? [] as $subscription) {
            // Calcula MRR baseado na frequência
            $amount = $subscription['auto_recurring']['transaction_amount'] ?? 0;
            $frequency = $subscription['auto_recurring']['frequency'] ?? 1;
            $frequencyType = $subscription['auto_recurring']['frequency_type'] ?? 'months';

            // Normaliza para mensal
            $monthlyAmount = match ($frequencyType) {
                'days' => ($amount / $frequency) * 30,
                'months' => $amount / $frequency,
                'years' => $amount / ($frequency * 12),
                default => $amount,
            };

            $mrr += $monthlyAmount;

            // Agrupa por plano
            $planId = $subscription['preapproval_plan_id'] ?? 'custom';
            if (!isset($subscriptionsByPlan[$planId])) {
                $subscriptionsByPlan[$planId] = ['count' => 0, 'mrr' => 0];
            }
            $subscriptionsByPlan[$planId]['count']++;
            $subscriptionsByPlan[$planId]['mrr'] += $monthlyAmount;

            // Agrupa por frequência
            $freqKey = "{$frequency} {$frequencyType}";
            if (!isset($subscriptionsByFrequency[$freqKey])) {
                $subscriptionsByFrequency[$freqKey] = ['count' => 0, 'mrr' => 0];
            }
            $subscriptionsByFrequency[$freqKey]['count']++;
            $subscriptionsByFrequency[$freqKey]['mrr'] += $monthlyAmount;

            // Próximos pagamentos
            if (!empty($subscription['next_payment_date'])) {
                $nextPayments[] = [
                    'subscription_id' => $subscription['id'],
                    'payer_email' => $subscription['payer_email'] ?? null,
                    'amount' => $amount,
                    'date' => $subscription['next_payment_date'],
                ];
            }
        }

        $arr = $mrr * 12;

        // Ordena próximos pagamentos por data
        usort($nextPayments, fn($a, $b) => strtotime($a['date']) - strtotime($b['date']));

        return [
            'mrr' => round($mrr, 2),
            'arr' => round($arr, 2),
            'total_active_subscriptions' => count($activeSubscriptions['results'] ?? []),
            'subscriptions_by_plan' => $subscriptionsByPlan,
            'subscriptions_by_frequency' => $subscriptionsByFrequency,
            'next_payments' => array_slice($nextPayments, 0, 10),
            'avg_subscription_value' => count($activeSubscriptions['results'] ?? []) > 0
                ? round($mrr / count($activeSubscriptions['results']), 2)
                : 0,
        ];
    }

    /**
     * Calcula churn rate de assinaturas
     *
     * @param string $month Mês no formato Y-m
     * @return array Análise de churn
     */
    public function calculateSubscriptionChurn(string $month): array
    {
        $startDate = $month . '-01';
        $endDate = date('Y-m-t', strtotime($startDate));

        // Busca assinaturas canceladas no período
        $cancelled = $this->searchSubscriptions([
            'status' => 'cancelled',
            'limit' => 100,
        ]);

        $cancelledInPeriod = array_filter(
            $cancelled['results'] ?? [],
            function ($sub) use ($startDate, $endDate) {
                $lastModified = $sub['last_modified'] ?? null;
                return $lastModified >= $startDate && $lastModified <= $endDate;
            }
        );

        // Busca total de assinaturas ativas no início do período
        $activeAtStart = $this->searchSubscriptions(['status' => 'authorized', 'limit' => 100]);
        $totalActiveAtStart = count($activeAtStart['results'] ?? []) + count($cancelledInPeriod);

        $churnRate = $totalActiveAtStart > 0
            ? (count($cancelledInPeriod) / $totalActiveAtStart) * 100
            : 0;

        $lostMrr = 0;
        foreach ($cancelledInPeriod as $sub) {
            $amount = $sub['auto_recurring']['transaction_amount'] ?? 0;
            $frequency = $sub['auto_recurring']['frequency'] ?? 1;
            $frequencyType = $sub['auto_recurring']['frequency_type'] ?? 'months';

            $monthlyAmount = match ($frequencyType) {
                'days' => ($amount / $frequency) * 30,
                'months' => $amount / $frequency,
                'years' => $amount / ($frequency * 12),
                default => $amount,
            };
            $lostMrr += $monthlyAmount;
        }

        return [
            'month' => $month,
            'active_at_start' => $totalActiveAtStart,
            'cancelled_count' => count($cancelledInPeriod),
            'churn_rate' => round($churnRate, 2),
            'lost_mrr' => round($lostMrr, 2),
            'cancelled_subscriptions' => array_map(fn($s) => [
                'id' => $s['id'],
                'reason' => $s['reason'] ?? null,
                'cancelled_at' => $s['last_modified'] ?? null,
            ], $cancelledInPeriod),
        ];
    }

    // =========================================================================
    // MERCADO PAGO - CLIENTES
    // =========================================================================

    /**
     * Cria um cliente no MP
     * POST /v1/customers
     *
     * @param array $customerData Dados do cliente
     * @return array Cliente criado
     */
    public function createCustomer(array $customerData): array
    {
        $client = $this->getMercadoPagoClient();

        $payload = [
            'email' => $customerData['email'],
        ];

        // Campos opcionais
        if (!empty($customerData['first_name'])) {
            $payload['first_name'] = $customerData['first_name'];
        }
        if (!empty($customerData['last_name'])) {
            $payload['last_name'] = $customerData['last_name'];
        }
        if (!empty($customerData['phone'])) {
            $payload['phone'] = [
                'area_code' => $customerData['phone']['area_code'] ?? '',
                'number' => $customerData['phone']['number'] ?? $customerData['phone'],
            ];
        }
        if (!empty($customerData['identification'])) {
            $payload['identification'] = [
                'type' => $customerData['identification']['type'] ?? 'CPF',
                'number' => $customerData['identification']['number'] ?? $customerData['identification'],
            ];
        }
        if (!empty($customerData['address'])) {
            $payload['address'] = $customerData['address'];
        }
        if (!empty($customerData['description'])) {
            $payload['description'] = $customerData['description'];
        }
        if (!empty($customerData['default_card'])) {
            $payload['default_card'] = $customerData['default_card'];
        }

        $response = $client->post('https://api.mercadopago.com/v1/customers', $payload);

        // Salva localmente
        if (isset($response['id'])) {
            $this->saveCustomerLocally($response);
        }

        return $response;
    }

    /**
     * Busca clientes no MP
     * GET /v1/customers/search
     *
     * @param array $filters Filtros
     * @return array Lista de clientes
     */
    public function searchCustomers(array $filters = []): array
    {
        $client = $this->getMercadoPagoClient();

        $params = [];
        if (!empty($filters['email'])) {
            $params['email'] = $filters['email'];
        }
        $params['offset'] = $filters['offset'] ?? 0;
        $params['limit'] = $filters['limit'] ?? 50;

        $queryString = http_build_query($params);
        return $client->get("https://api.mercadopago.com/v1/customers/search?{$queryString}");
    }

    /**
     * Obtém detalhes de um cliente
     * GET /v1/customers/{id}
     *
     * @param string $customerId ID do cliente
     * @return array Detalhes do cliente
     */
    public function getCustomer(string $customerId): array
    {
        $client = $this->getMercadoPagoClient();
        return $client->get("https://api.mercadopago.com/v1/customers/{$customerId}");
    }

    /**
     * Atualiza um cliente
     * PUT /v1/customers/{id}
     *
     * @param string $customerId ID do cliente
     * @param array $updateData Dados para atualizar
     * @return array Cliente atualizado
     */
    public function updateCustomer(string $customerId, array $updateData): array
    {
        $client = $this->getMercadoPagoClient();
        return $client->put("https://api.mercadopago.com/v1/customers/{$customerId}", $updateData);
    }

    // =========================================================================
    // MERCADO PAGO - CARTÕES DE CLIENTES
    // =========================================================================

    /**
     * Salva um cartão para um cliente
     * POST /v1/customers/{customer_id}/cards
     *
     * @param string $customerId ID do cliente
     * @param string $cardToken Token do cartão
     * @return array Cartão salvo
     */
    public function saveCustomerCard(string $customerId, string $cardToken): array
    {
        $client = $this->getMercadoPagoClient();

        return $client->post("https://api.mercadopago.com/v1/customers/{$customerId}/cards", [
            'token' => $cardToken,
        ]);
    }

    /**
     * Lista cartões de um cliente
     * GET /v1/customers/{customer_id}/cards
     *
     * @param string $customerId ID do cliente
     * @return array Lista de cartões
     */
    public function getCustomerCards(string $customerId): array
    {
        $client = $this->getMercadoPagoClient();
        return $client->get("https://api.mercadopago.com/v1/customers/{$customerId}/cards");
    }

    /**
     * Obtém detalhes de um cartão
     * GET /v1/customers/{customer_id}/cards/{id}
     *
     * @param string $customerId ID do cliente
     * @param string $cardId ID do cartão
     * @return array Detalhes do cartão
     */
    public function getCustomerCard(string $customerId, string $cardId): array
    {
        $client = $this->getMercadoPagoClient();
        return $client->get("https://api.mercadopago.com/v1/customers/{$customerId}/cards/{$cardId}");
    }

    /**
     * Atualiza um cartão
     * PUT /v1/customers/{customer_id}/cards/{id}
     *
     * @param string $customerId ID do cliente
     * @param string $cardId ID do cartão
     * @param array $updateData Dados para atualizar
     * @return array Cartão atualizado
     */
    public function updateCustomerCard(string $customerId, string $cardId, array $updateData): array
    {
        $client = $this->getMercadoPagoClient();
        return $client->put("https://api.mercadopago.com/v1/customers/{$customerId}/cards/{$cardId}", $updateData);
    }

    /**
     * Remove um cartão do cliente
     * DELETE /v1/customers/{customer_id}/cards/{id}
     *
     * @param string $customerId ID do cliente
     * @param string $cardId ID do cartão
     * @return array Resultado da remoção
     */
    public function deleteCustomerCard(string $customerId, string $cardId): array
    {
        $client = $this->getMercadoPagoClient();
        return $client->delete("https://api.mercadopago.com/v1/customers/{$customerId}/cards/{$cardId}");
    }

    // =========================================================================
    // MERCADO PAGO - RECLAMAÇÕES (CLAIMS)
    // =========================================================================

    /**
     * Busca reclamações no MP
     * GET /post-purchase/v1/claims/search
     *
     * @param array $filters Filtros
     * @return array Lista de reclamações
     */
    public function searchClaims(array $filters = []): array
    {
        $client = $this->getMercadoPagoClient();

        $params = [];
        if (!empty($filters['status'])) {
            $params['status'] = $filters['status'];
        }
        if (!empty($filters['resource_id'])) {
            $params['resource_id'] = $filters['resource_id']; // payment_id ou order_id
        }
        if (!empty($filters['date_from'])) {
            $params['date_created_from'] = $filters['date_from'];
        }
        if (!empty($filters['date_to'])) {
            $params['date_created_to'] = $filters['date_to'];
        }
        $params['offset'] = $filters['offset'] ?? 0;
        $params['limit'] = $filters['limit'] ?? 50;

        $queryString = http_build_query($params);
        return $client->get("https://api.mercadopago.com/post-purchase/v1/claims/search?{$queryString}");
    }

    /**
     * Obtém detalhes de uma reclamação
     * GET /post-purchase/v1/claims/{claim_id}
     *
     * @param string $claimId ID da reclamação
     * @return array Detalhes da reclamação
     */
    public function getClaim(string $claimId): array
    {
        $client = $this->getMercadoPagoClient();
        return $client->get("https://api.mercadopago.com/post-purchase/v1/claims/{$claimId}");
    }

    /**
     * Obtém motivos da reclamação
     * GET /post-purchase/v1/claims/{claim_id}/reason
     *
     * @param string $claimId ID da reclamação
     * @return array Motivos da reclamação
     */
    public function getClaimReason(string $claimId): array
    {
        $client = $this->getMercadoPagoClient();
        return $client->get("https://api.mercadopago.com/post-purchase/v1/claims/{$claimId}/reason");
    }

    /**
     * Obtém histórico da reclamação
     * GET /post-purchase/v1/claims/{claim_id}/history
     *
     * @param string $claimId ID da reclamação
     * @return array Histórico da reclamação
     */
    public function getClaimHistory(string $claimId): array
    {
        $client = $this->getMercadoPagoClient();
        return $client->get("https://api.mercadopago.com/post-purchase/v1/claims/{$claimId}/history");
    }

    /**
     * Obtém evidências da reclamação
     * GET /post-purchase/v1/claims/{claim_id}/evidence
     *
     * @param string $claimId ID da reclamação
     * @return array Evidências da reclamação
     */
    public function getClaimEvidence(string $claimId): array
    {
        $client = $this->getMercadoPagoClient();
        return $client->get("https://api.mercadopago.com/post-purchase/v1/claims/{$claimId}/evidence");
    }

    /**
     * Obtém notificações da reclamação
     * GET /post-purchase/v1/claims/{claim_id}/notifications
     *
     * @param string $claimId ID da reclamação
     * @return array Notificações da reclamação
     */
    public function getClaimNotifications(string $claimId): array
    {
        $client = $this->getMercadoPagoClient();
        return $client->get("https://api.mercadopago.com/post-purchase/v1/claims/{$claimId}/notifications");
    }

    /**
     * Obtém mensagens da reclamação
     * GET /post-purchase/v1/claims/{claim_id}/messages
     *
     * @param string $claimId ID da reclamação
     * @return array Mensagens da reclamação
     */
    public function getClaimMessages(string $claimId): array
    {
        $client = $this->getMercadoPagoClient();
        return $client->get("https://api.mercadopago.com/post-purchase/v1/claims/{$claimId}/messages");
    }

    /**
     * Envia mensagem em uma reclamação
     * POST /post-purchase/v1/claims/{claim_id}/messages
     *
     * @param string $claimId ID da reclamação
     * @param string $message Mensagem a enviar
     * @param array $attachments IDs dos arquivos anexados
     * @return array Resultado do envio
     */
    public function sendClaimMessage(string $claimId, string $message, array $attachments = []): array
    {
        $client = $this->getMercadoPagoClient();

        $payload = [
            'message' => $message,
        ];

        if (!empty($attachments)) {
            $payload['attachment_ids'] = $attachments;
        }

        return $client->post("https://api.mercadopago.com/post-purchase/v1/claims/{$claimId}/messages", $payload);
    }

    /**
     * Anexa arquivo a uma mensagem de reclamação
     * POST /post-purchase/v1/claims/{claim_id}/attachments
     *
     * @param string $claimId ID da reclamação
     * @param string $filePath Caminho do arquivo
     * @param string $fileName Nome do arquivo
     * @return array Resultado do upload
     */
    public function attachClaimFile(string $claimId, string $filePath, string $fileName): array
    {
        $client = $this->getMercadoPagoClient();

        // Envia como multipart/form-data
        $fileContent = base64_encode(file_get_contents($filePath));

        return $client->post("https://api.mercadopago.com/post-purchase/v1/claims/{$claimId}/attachments", [
            'file' => $fileContent,
            'file_name' => $fileName,
        ]);
    }

    /**
     * Solicita mediação para uma reclamação
     * POST /post-purchase/v1/claims/{claim_id}/mediation
     *
     * @param string $claimId ID da reclamação
     * @return array Resultado da solicitação
     */
    public function requestClaimMediation(string $claimId): array
    {
        $client = $this->getMercadoPagoClient();
        return $client->post("https://api.mercadopago.com/post-purchase/v1/claims/{$claimId}/mediation");
    }

    /**
     * Visualiza resoluções esperadas na mediação
     * GET /post-purchase/v1/claims/{claim_id}/resolutions
     *
     * @param string $claimId ID da reclamação
     * @return array Resoluções esperadas
     */
    public function getExpectedResolutions(string $claimId): array
    {
        $client = $this->getMercadoPagoClient();
        return $client->get("https://api.mercadopago.com/post-purchase/v1/claims/{$claimId}/resolutions");
    }

    /**
     * Carrega evidência de envio
     * POST /post-purchase/v1/claims/{claim_id}/shipping-evidence
     *
     * @param string $claimId ID da reclamação
     * @param array $shippingData Dados do envio
     * @return array Resultado
     */
    public function uploadShippingEvidence(string $claimId, array $shippingData): array
    {
        $client = $this->getMercadoPagoClient();
        return $client->post(
            "https://api.mercadopago.com/post-purchase/v1/claims/{$claimId}/shipping-evidence",
            $shippingData
        );
    }

    /**
     * Obtém análise de reclamações
     *
     * @param string $startDate Data inicial (Y-m-d)
     * @param string $endDate Data final (Y-m-d)
     * @return array Análise de reclamações
     */
    public function analyzeClaimsPerformance(string $startDate, string $endDate): array
    {
        $claims = $this->searchClaims([
            'date_from' => $startDate . 'T00:00:00.000-03:00',
            'date_to' => $endDate . 'T23:59:59.999-03:00',
            'limit' => 100,
        ]);

        $totalClaims = count($claims['results'] ?? []);
        $byStatus = [];
        $byReason = [];
        $resolved = 0;
        $pending = 0;
        $avgResolutionTime = 0;
        $resolutionTimes = [];

        foreach ($claims['results'] ?? [] as $claim) {
            // Por status
            $status = $claim['status'] ?? 'unknown';
            $byStatus[$status] = ($byStatus[$status] ?? 0) + 1;

            // Por motivo
            $reason = $claim['reason'] ?? $claim['type'] ?? 'unknown';
            $byReason[$reason] = ($byReason[$reason] ?? 0) + 1;

            // Contagem de resolvidas
            if (in_array($status, ['closed', 'resolved', 'finalized'])) {
                $resolved++;
                // Tempo de resolução
                if (!empty($claim['date_created']) && !empty($claim['last_modified'])) {
                    $created = strtotime($claim['date_created']);
                    $modified = strtotime($claim['last_modified']);
                    $resolutionTimes[] = ($modified - $created) / 86400; // Dias
                }
            } else {
                $pending++;
            }
        }

        if (!empty($resolutionTimes)) {
            $avgResolutionTime = array_sum($resolutionTimes) / count($resolutionTimes);
        }

        $resolutionRate = $totalClaims > 0 ? ($resolved / $totalClaims) * 100 : 0;

        return [
            'period' => ['start' => $startDate, 'end' => $endDate],
            'total_claims' => $totalClaims,
            'resolved' => $resolved,
            'pending' => $pending,
            'resolution_rate' => round($resolutionRate, 2),
            'avg_resolution_days' => round($avgResolutionTime, 1),
            'by_status' => $byStatus,
            'by_reason' => $byReason,
            'health_indicator' => $this->calculateClaimsHealthIndicator($totalClaims, $resolutionRate, $avgResolutionTime),
        ];
    }

    /**
     * Calcula indicador de saúde de reclamações
     */
    private function calculateClaimsHealthIndicator(int $totalClaims, float $resolutionRate, float $avgDays): array
    {
        $score = 100;

        // Penalidade por volume (mais de 10 reclamações no período é preocupante)
        if ($totalClaims > 10) {
            $score -= min(30, ($totalClaims - 10) * 2);
        }

        // Penalidade por taxa de resolução baixa
        if ($resolutionRate < 80) {
            $score -= (80 - $resolutionRate);
        }

        // Penalidade por tempo de resolução longo (ideal < 3 dias)
        if ($avgDays > 3) {
            $score -= min(20, ($avgDays - 3) * 3);
        }

        $score = max(0, min(100, $score));

        return [
            'score' => round($score),
            'status' => $score >= 80 ? 'excellent' : ($score >= 60 ? 'good' : ($score >= 40 ? 'attention' : 'critical')),
            'recommendations' => $this->getClaimsRecommendations($totalClaims, $resolutionRate, $avgDays),
        ];
    }

    /**
     * Obtém recomendações baseadas na análise de reclamações
     */
    private function getClaimsRecommendations(int $totalClaims, float $resolutionRate, float $avgDays): array
    {
        $recommendations = [];

        if ($totalClaims > 10) {
            $recommendations[] = 'Alto volume de reclamações. Revise a qualidade dos produtos/serviços.';
        }
        if ($resolutionRate < 80) {
            $recommendations[] = 'Taxa de resolução abaixo do ideal. Priorize a resolução de reclamações pendentes.';
        }
        if ($avgDays > 5) {
            $recommendations[] = 'Tempo médio de resolução muito alto. Implemente processos mais ágeis.';
        }
        if ($avgDays > 3 && $avgDays <= 5) {
            $recommendations[] = 'Considere automatizar respostas iniciais para agilizar atendimento.';
        }

        if (empty($recommendations)) {
            $recommendations[] = 'Bom desempenho no gerenciamento de reclamações. Continue monitorando.';
        }

        return $recommendations;
    }

    // =========================================================================
    // MÉTODOS AUXILIARES - PERSISTÊNCIA LOCAL
    // =========================================================================

    /**
     * Salva assinatura localmente
     */
    private function saveSubscriptionLocally(array $subscription): void
    {
        $db = Database::getInstance();

        $stmt = $db->prepare("
            INSERT INTO mp_subscriptions (
                account_id, subscription_id, payer_email, payer_id, plan_id,
                reason, status, transaction_amount, frequency, frequency_type,
                next_payment_date, date_created, external_reference
            ) VALUES (
                :account_id, :subscription_id, :payer_email, :payer_id, :plan_id,
                :reason, :status, :transaction_amount, :frequency, :frequency_type,
                :next_payment_date, :date_created, :external_reference
            )
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                next_payment_date = VALUES(next_payment_date),
                transaction_amount = VALUES(transaction_amount),
                updated_at = NOW()
        ");

        $stmt->execute([
            'account_id' => $this->accountId,
            'subscription_id' => $subscription['id'],
            'payer_email' => $subscription['payer_email'] ?? null,
            'payer_id' => $subscription['payer_id'] ?? null,
            'plan_id' => $subscription['preapproval_plan_id'] ?? null,
            'reason' => $subscription['reason'] ?? null,
            'status' => $subscription['status'] ?? 'pending',
            'transaction_amount' => $subscription['auto_recurring']['transaction_amount'] ?? 0,
            'frequency' => $subscription['auto_recurring']['frequency'] ?? 1,
            'frequency_type' => $subscription['auto_recurring']['frequency_type'] ?? 'months',
            'next_payment_date' => $subscription['next_payment_date'] ?? null,
            'date_created' => $subscription['date_created'] ?? date('Y-m-d H:i:s'),
            'external_reference' => $subscription['external_reference'] ?? null,
        ]);
    }

    /**
     * Atualiza assinatura localmente
     */
    private function updateSubscriptionLocally(array $subscription): void
    {
        $this->saveSubscriptionLocally($subscription);
    }

    /**
     * Salva plano de assinatura localmente
     */
    private function saveSubscriptionPlanLocally(array $plan): void
    {
        $db = Database::getInstance();

        $stmt = $db->prepare("
            INSERT INTO mp_subscription_plans (
                account_id, plan_id, reason, status, transaction_amount,
                frequency, frequency_type, billing_day, date_created
            ) VALUES (
                :account_id, :plan_id, :reason, :status, :transaction_amount,
                :frequency, :frequency_type, :billing_day, :date_created
            )
            ON DUPLICATE KEY UPDATE
                status = VALUES(status),
                transaction_amount = VALUES(transaction_amount),
                updated_at = NOW()
        ");

        $stmt->execute([
            'account_id' => $this->accountId,
            'plan_id' => $plan['id'],
            'reason' => $plan['reason'] ?? null,
            'status' => $plan['status'] ?? 'active',
            'transaction_amount' => $plan['auto_recurring']['transaction_amount'] ?? 0,
            'frequency' => $plan['auto_recurring']['frequency'] ?? 1,
            'frequency_type' => $plan['auto_recurring']['frequency_type'] ?? 'months',
            'billing_day' => $plan['auto_recurring']['billing_day'] ?? 10,
            'date_created' => $plan['date_created'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Salva cliente localmente
     */
    private function saveCustomerLocally(array $customer): void
    {
        $db = Database::getInstance();

        $stmt = $db->prepare("
            INSERT INTO mp_customers (
                account_id, customer_id, email, first_name, last_name,
                phone, identification_type, identification_number, date_created
            ) VALUES (
                :account_id, :customer_id, :email, :first_name, :last_name,
                :phone, :identification_type, :identification_number, :date_created
            )
            ON DUPLICATE KEY UPDATE
                email = VALUES(email),
                first_name = VALUES(first_name),
                last_name = VALUES(last_name),
                updated_at = NOW()
        ");

        $stmt->execute([
            'account_id' => $this->accountId,
            'customer_id' => $customer['id'],
            'email' => $customer['email'] ?? null,
            'first_name' => $customer['first_name'] ?? null,
            'last_name' => $customer['last_name'] ?? null,
            'phone' => isset($customer['phone']) ? json_encode($customer['phone']) : null,
            'identification_type' => $customer['identification']['type'] ?? null,
            'identification_number' => $customer['identification']['number'] ?? null,
            'date_created' => $customer['date_created'] ?? date('Y-m-d H:i:s'),
        ]);
    }

    // =========================================================================
    // DASHBOARD CONSOLIDADO FINANCEIRO
    // =========================================================================

    /**
     * Obtém dashboard financeiro consolidado com todas as métricas
     *
     * @param string $period Período: today, week, month, year
     * @return array Dashboard completo
     */
    public function getConsolidatedFinancialDashboard(string $period = 'month'): array
    {
        $dates = $this->getPeriodDates($period);
        $startDate = $dates['start'];
        $endDate = $dates['end'];

        // Coleta dados em paralelo (onde possível)
        $payments = $this->getPaymentsSummary($startDate, $endDate);
        $subscriptions = $this->getRecurringRevenueAnalysis();
        $claims = $this->analyzeClaimsPerformance($startDate, $endDate);
        $forecast = $this->calculateFinancialForecast(3);
        $alerts = $this->checkFinancialAlerts();
        $healthScore = $this->calculateFinancialHealthScore($startDate, $endDate);

        return [
            'period' => [
                'type' => $period,
                'start' => $startDate,
                'end' => $endDate,
            ],
            'summary' => [
                'total_revenue' => $payments['total_approved'] ?? 0,
                'total_pending' => $payments['total_pending'] ?? 0,
                'total_refunded' => $payments['total_refunded'] ?? 0,
                'net_revenue' => ($payments['total_approved'] ?? 0) - ($payments['total_refunded'] ?? 0),
                'mrr' => $subscriptions['mrr'],
                'arr' => $subscriptions['arr'],
            ],
            'payments' => $payments,
            'subscriptions' => [
                'mrr' => $subscriptions['mrr'],
                'arr' => $subscriptions['arr'],
                'active_count' => $subscriptions['total_active_subscriptions'],
                'avg_value' => $subscriptions['avg_subscription_value'],
            ],
            'claims' => [
                'total' => $claims['total_claims'],
                'pending' => $claims['pending'],
                'resolution_rate' => $claims['resolution_rate'],
                'health_status' => $claims['health_indicator']['status'],
            ],
            'forecast' => [
                'next_month' => $forecast['projections'][0] ?? null,
                'trend' => $forecast['trend'] ?? 'stable',
            ],
            'alerts' => [
                'critical' => count(array_filter($alerts, fn($a) => $a['severity'] === 'critical')),
                'warning' => count(array_filter($alerts, fn($a) => $a['severity'] === 'warning')),
                'items' => array_slice($alerts, 0, 5),
            ],
            'health' => [
                'score' => $healthScore['score'],
                'grade' => $healthScore['grade'],
            ],
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Obtém resumo de pagamentos para um período
     */
    private function getPaymentsSummary(string $startDate, string $endDate): array
    {
        $payments = $this->searchPayments([
            'begin_date' => $startDate . 'T00:00:00.000-03:00',
            'end_date' => $endDate . 'T23:59:59.999-03:00',
            'limit' => 100,
        ]);

        $totalApproved = 0;
        $totalPending = 0;
        $totalRefunded = 0;
        $byMethod = [];
        $count = 0;

        foreach ($payments['results'] ?? [] as $payment) {
            $amount = $payment['transaction_amount'] ?? 0;
            $status = $payment['status'] ?? '';
            $method = $payment['payment_type_id'] ?? $payment['payment_method_id'] ?? 'other';

            $count++;

            if ($status === 'approved') {
                $totalApproved += $amount;
            } elseif (in_array($status, ['pending', 'in_process', 'authorized'])) {
                $totalPending += $amount;
            } elseif (in_array($status, ['refunded', 'cancelled', 'charged_back'])) {
                $totalRefunded += $amount;
            }

            $byMethod[$method] = ($byMethod[$method] ?? 0) + $amount;
        }

        return [
            'total_approved' => round($totalApproved, 2),
            'total_pending' => round($totalPending, 2),
            'total_refunded' => round($totalRefunded, 2),
            'count' => $count,
            'avg_ticket' => $count > 0 ? round($totalApproved / $count, 2) : 0,
            'by_method' => $byMethod,
        ];
    }

    /**
     * Converte período em datas
     */
    private function getPeriodDates(string $period): array
    {
        $now = new \DateTime();

        switch ($period) {
            case 'today':
                $start = $now->format('Y-m-d');
                $end = $start;
                break;
            case 'week':
                $start = $now->modify('-7 days')->format('Y-m-d');
                $end = (new \DateTime())->format('Y-m-d');
                break;
            case 'year':
                $start = $now->format('Y') . '-01-01';
                $end = (new \DateTime())->format('Y-m-d');
                break;
            case 'month':
            default:
                $start = $now->format('Y-m') . '-01';
                $end = (new \DateTime())->format('Y-m-d');
                break;
        }

        return ['start' => $start, 'end' => $end];
    }

    /**
     * Busca pagamentos no Mercado Pago
     *
     * @param array $params Parâmetros de busca (begin_date, end_date, limit, offset, status)
     * @return array Resultados da busca com 'results' e 'paging'
     */
    private function searchPayments(array $params = []): array
    {
        try {
            $client = $this->getMercadoPagoClient();
            return $client->get('/v1/payments/search', $params);
        } catch (\Exception $e) {
            log_error('FinancialService::searchPayments error', ['error' => $e->getMessage()]);
            return ['results' => [], 'paging' => ['total' => 0]];
        }
    }
}
