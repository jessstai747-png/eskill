<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\OrderService;
use App\Services\UserService;
use PDO;

class CustomerController extends BaseController
{
    private OrderService $orderService;
    private UserService $userService;
    private PDO $db;
    private ?int $accountId;

    public function __construct(UserService $userService)
    {
        parent::__construct();
        $this->userService = $userService;
        $this->accountId = isset($_SESSION['active_ml_account_id']) ? (int) $_SESSION['active_ml_account_id'] : null;
        $this->orderService = new OrderService($this->accountId);
        $this->db = Database::getInstance();
    }

    /**
     * Render Customer CRM Dashboard
     */
    public function index(): void
    {
        if (!$this->userService->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        $pageTitle = 'Gestão de Clientes';
        $activePage = 'customers';

        ob_start();
        require __DIR__ . '/../Views/dashboard/customers/index.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layouts/modern/app.php';
    }

    /**
     * API: List Customers (Buyers) - dados reais do banco
     */
    public function listCustomers(): void
    {

        try {
            $page = $this->request->getIntClamped('page', 1, 1000, 1);
            $limit = $this->request->getIntClamped('limit', 10, 100, 50);
            $limitSql = max(1, min((int) $limit, 100));
            $offsetSql = max(0, ($page - 1) * $limitSql);
            $search = $this->request->get('search');
            $sortBy = $this->request->getEnum('sort', ['total_spent', 'total_orders', 'last_order_date', 'first_order_date'], 'total_spent');
            $sortDir = $this->request->getSortDir('dir', 'DESC');

            // Query para agregar clientes dos pedidos sincronizados (ml_orders.order_data)
            $query = "
                SELECT
                    x.buyer_id as id,
                    x.buyer_nickname as nickname,
                    COALESCE(x.buyer_first_name, '') as first_name,
                    COALESCE(x.buyer_last_name, '') as last_name,
                    CONCAT(COALESCE(x.buyer_first_name, ''), ' ', COALESCE(x.buyer_last_name, '')) as name,
                    MAX(DATE(x.date_created)) as last_order_date,
                    MIN(DATE(x.date_created)) as first_order_date,
                    COUNT(DISTINCT x.id) as total_orders,
                    SUM(x.total_amount) as total_spent,
                    MAX(x.buyer_state) as state,
                    MAX(x.buyer_city) as city
                FROM (
                    SELECT
                        o.id,
                        o.date_created,
                        o.total_amount,
                        JSON_UNQUOTE(JSON_EXTRACT(o.order_data, '$.buyer.id')) as buyer_id,
                        JSON_UNQUOTE(JSON_EXTRACT(o.order_data, '$.buyer.nickname')) as buyer_nickname,
                        JSON_UNQUOTE(JSON_EXTRACT(o.order_data, '$.buyer.first_name')) as buyer_first_name,
                        JSON_UNQUOTE(JSON_EXTRACT(o.order_data, '$.buyer.last_name')) as buyer_last_name,
                        JSON_UNQUOTE(JSON_EXTRACT(o.order_data, '$.shipping.receiver_address.state.name')) as buyer_state,
                        JSON_UNQUOTE(JSON_EXTRACT(o.order_data, '$.shipping.receiver_address.city.name')) as buyer_city
                    FROM ml_orders o
                    WHERE o.ml_account_id = :account_id
                    AND o.status NOT IN ('cancelled')
                ) x
                WHERE x.buyer_id IS NOT NULL AND x.buyer_id != ''
            ";

            $params = ['account_id' => $this->accountId];

            if ($search) {
                $query .= " AND (x.buyer_nickname LIKE :search
                           OR x.buyer_first_name LIKE :search2
                           OR x.buyer_last_name LIKE :search3)";
                $params['search'] = "%{$search}%";
                $params['search2'] = "%{$search}%";
                $params['search3'] = "%{$search}%";
            }

            $query .= " GROUP BY x.buyer_id, x.buyer_nickname, x.buyer_first_name, x.buyer_last_name";
            $query .= " ORDER BY {$sortBy} {$sortDir}";
            $query .= " LIMIT {$limitSql} OFFSET {$offsetSql}";

            $stmt = $this->db->prepare($query);

            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }
            $stmt->execute();
            $customers = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Adicionar tags baseadas em comportamento
            foreach ($customers as &$customer) {
                $customer['total_spent'] = round((float)$customer['total_spent'], 2);
                $customer['tags'] = $this->calculateCustomerTags($customer);
                $customer['ltv_tier'] = $this->calculateLtvTier($customer['total_spent']);
            }

            // Contar total
            $countQuery = "
                SELECT COUNT(DISTINCT JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.buyer.id'))) as total
                FROM ml_orders
                WHERE ml_account_id = :account_id
                AND status NOT IN ('cancelled')
                AND JSON_EXTRACT(order_data, '$.buyer.id') IS NOT NULL
            ";
            $countStmt = $this->db->prepare($countQuery);
            $countStmt->execute(['account_id' => $this->accountId]);
            $total = (int)$countStmt->fetchColumn();
        $this->json([
                'success' => true,
                'customers' => $customers,
                'pagination' => [
                    'page' => $page,
                    'limit' => $limitSql,
                    'total' => $total,
                    'pages' => ceil($total / $limitSql)
                ]
            ]);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * API: Get Customer Detail - dados reais do banco
     */
    public function detail(): void
    {
        $buyerId = $this->request->get('id');

        if (!$buyerId) {
            $this->jsonError('ID do cliente é obrigatório', 400);
        }

        try {
            // Buscar dados agregados do cliente
            $stmt = $this->db->prepare("
                SELECT
                    x.buyer_id as id,
                    MAX(x.buyer_nickname) as nickname,
                    MAX(x.buyer_first_name) as first_name,
                    MAX(x.buyer_last_name) as last_name,
                    MAX(x.buyer_email) as email,
                    MAX(x.buyer_phone) as phone,
                    CONCAT(COALESCE(MAX(x.buyer_city), ''), ', ', COALESCE(MAX(x.buyer_state), '')) as location,
                    MIN(DATE(x.date_created)) as first_purchase,
                    MAX(DATE(x.date_created)) as last_purchase,
                    COUNT(DISTINCT x.id) as total_orders,
                    SUM(x.total_amount) as total_spent,
                    AVG(x.total_amount) as avg_order_value
                FROM (
                    SELECT
                        o.id,
                        o.date_created,
                        o.total_amount,
                        JSON_UNQUOTE(JSON_EXTRACT(o.order_data, '$.buyer.id')) as buyer_id,
                        JSON_UNQUOTE(JSON_EXTRACT(o.order_data, '$.buyer.nickname')) as buyer_nickname,
                        JSON_UNQUOTE(JSON_EXTRACT(o.order_data, '$.buyer.first_name')) as buyer_first_name,
                        JSON_UNQUOTE(JSON_EXTRACT(o.order_data, '$.buyer.last_name')) as buyer_last_name,
                        JSON_UNQUOTE(JSON_EXTRACT(o.order_data, '$.buyer.email')) as buyer_email,
                        COALESCE(
                            JSON_UNQUOTE(JSON_EXTRACT(o.order_data, '$.buyer.phone.number')),
                            JSON_UNQUOTE(JSON_EXTRACT(o.order_data, '$.buyer.phone'))
                        ) as buyer_phone,
                        JSON_UNQUOTE(JSON_EXTRACT(o.order_data, '$.shipping.receiver_address.city.name')) as buyer_city,
                        JSON_UNQUOTE(JSON_EXTRACT(o.order_data, '$.shipping.receiver_address.state.name')) as buyer_state
                    FROM ml_orders o
                    WHERE o.ml_account_id = :account_id
                    AND o.status NOT IN ('cancelled')
                ) x
                WHERE x.buyer_id = :buyer_id
                GROUP BY x.buyer_id
            ");
            $stmt->execute([
                'account_id' => $this->accountId,
                'buyer_id' => $buyerId
            ]);

            $customer = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$customer) {
                $this->jsonError('Cliente não encontrado', 404);
            }

            // Buscar histórico de pedidos
            $ordersStmt = $this->db->prepare("
                SELECT
                    id,
                    ml_order_id,
                    DATE(date_created) as order_date,
                    total_amount,
                    status
                FROM ml_orders
                WHERE ml_account_id = :account_id
                AND JSON_UNQUOTE(JSON_EXTRACT(order_data, '$.buyer.id')) = :buyer_id
                ORDER BY date_created DESC
                LIMIT 20
            ");
            $ordersStmt->execute([
                'account_id' => $this->accountId,
                'buyer_id' => $buyerId
            ]);
            $orderHistory = $ordersStmt->fetchAll(PDO::FETCH_ASSOC);

            // Buscar produtos mais comprados
            $productsStmt = $this->db->prepare("
                SELECT
                    oi.title,
                    oi.item_id,
                    SUM(oi.quantity) as quantity,
                    COUNT(DISTINCT o.id) as times_purchased
                FROM order_items oi
                JOIN ml_orders o ON (oi.order_id = o.id OR oi.order_id = o.ml_order_id)
                WHERE o.ml_account_id = :account_id
                AND JSON_UNQUOTE(JSON_EXTRACT(o.order_data, '$.buyer.id')) = :buyer_id
                GROUP BY oi.item_id, oi.title
                ORDER BY quantity DESC
                LIMIT 5
            ");
            $productsStmt->execute([
                'account_id' => $this->accountId,
                'buyer_id' => $buyerId
            ]);
            $topProducts = $productsStmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular métricas
            $totalSpent = (float)$customer['total_spent'];
            $customer['total_spent'] = round($totalSpent, 2);
            $customer['avg_order_value'] = round((float)$customer['avg_order_value'], 2);
            $customer['ltv_tier'] = $this->calculateLtvTier($totalSpent);
            $customer['tags'] = $this->calculateCustomerTags($customer);

            // Buscar notas do cliente (se tabela existir)
            $customer['notes'] = $this->getCustomerNotes($buyerId);

            $this->jsonSuccess([
                'customer' => $customer,
                'order_history' => $orderHistory,
                'top_products' => $topProducts
            ]);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * API: Save Customer Notes
     */
    public function saveNotes(): void
    {

        $data = $this->request->json();
        $buyerId = $data['buyer_id'] ?? null;
        $notes = $data['notes'] ?? '';

        if (!$buyerId) {
            $this->jsonError('ID do cliente é obrigatório', 400);
        }

        try {
            // Criar tabela se não existir
            $this->ensureCustomerNotesTable();

            $stmt = $this->db->prepare("
                INSERT INTO customer_notes (account_id, buyer_id, notes, updated_at)
                VALUES (:account_id, :buyer_id, :notes, NOW())
                ON DUPLICATE KEY UPDATE notes = VALUES(notes), updated_at = NOW()
            ");
            $stmt->execute([
                'account_id' => $this->accountId,
                'buyer_id' => $buyerId,
                'notes' => $notes
            ]);

            $this->jsonSuccess([], 'Notas salvas com sucesso');
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * Calcula o tier LTV do cliente
     */
    private function calculateLtvTier(float $totalSpent): string
    {
        if ($totalSpent >= 10000) return 'Diamond';
        if ($totalSpent >= 5000) return 'Platinum';
        if ($totalSpent >= 1000) return 'Gold';
        if ($totalSpent >= 500) return 'Silver';
        return 'Bronze';
    }

    /**
     * Calcula tags baseadas no comportamento do cliente
     */
    private function calculateCustomerTags(array $customer): array
    {
        $tags = [];

        $totalSpent = (float)($customer['total_spent'] ?? 0);
        $totalOrders = (int)($customer['total_orders'] ?? 0);
        $lastOrder = $customer['last_order_date'] ?? null;
        $firstOrder = $customer['first_order_date'] ?? null;

        // Tag VIP: gastou mais de R$5000
        if ($totalSpent >= 5000) {
            $tags[] = 'vip';
        }

        // Tag Whale: gastou mais de R$10000
        if ($totalSpent >= 10000) {
            $tags[] = 'whale';
        }

        // Tag Frequent: mais de 5 pedidos
        if ($totalOrders >= 5) {
            $tags[] = 'frequent';
        }

        // Tag New: primeiro pedido nos últimos 30 dias
        if ($firstOrder && strtotime($firstOrder) >= strtotime('-30 days')) {
            $tags[] = 'new';
        }

        // Tag Inactive: sem pedidos há mais de 90 dias
        if ($lastOrder && strtotime($lastOrder) < strtotime('-90 days')) {
            $tags[] = 'inactive';
        }

        // Tag Reseller: média de pedido > R$500 e mais de 3 pedidos
        if ($totalOrders >= 3 && ($totalSpent / $totalOrders) > 500) {
            $tags[] = 'reseller';
        }

        // Tag Returning: mais de 1 pedido
        if ($totalOrders > 1) {
            $tags[] = 'returning';
        }

        return $tags;
    }

    /**
     * Busca notas do cliente
     */
    private function getCustomerNotes(string $buyerId): ?string
    {
        try {
            $stmt = $this->db->prepare("
                SELECT notes FROM customer_notes
                WHERE account_id = :account_id AND buyer_id = :buyer_id
            ");
            $stmt->execute([
                'account_id' => $this->accountId,
                'buyer_id' => $buyerId
            ]);
            return $stmt->fetchColumn() ?: null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Cria tabela de notas se não existir
     */
    private function ensureCustomerNotesTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS customer_notes (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                account_id INT UNSIGNED NOT NULL,
                buyer_id VARCHAR(100) NOT NULL,
                notes TEXT,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_account_buyer (account_id, buyer_id),
                INDEX idx_account (account_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }
}
