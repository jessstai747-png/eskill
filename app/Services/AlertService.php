<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Services\MercadoLivreClient;
use App\Services\SearchService;
use App\Services\EmailService;
use App\Services\TelegramService;
use App\Services\StructuredLogService;

class AlertService
{
    private StructuredLogService $logger;

    public function __construct()
    {
        $this->logger = new StructuredLogService();
    }

    // ──────────────────────────────────────────────
    // CRUD / Query Methods (used by AlertController)
    // ──────────────────────────────────────────────

    /**
     * Lista alertas com filtros opcionais.
     *
     * @param int|null $accountId  Filtrar por conta ML
     * @param bool     $unreadOnly Apenas não lidos
     * @param int      $limit      Máximo de registros
     * @return array<int, array<string, mixed>>
     */
    public function getAlerts(?int $accountId = null, bool $unreadOnly = false, int $limit = 50): array
    {
        $db = Database::getInstance();

        $limitSql = max(1, min(200, (int)$limit));

        $sql = "SELECT * FROM alerts WHERE 1=1";
        $params = [];

        if ($accountId !== null) {
            $sql .= " AND ml_account_id = :account_id";
            $params['account_id'] = $accountId;
        }

        if ($unreadOnly) {
            $sql .= " AND read_at IS NULL";
        }

        $sql .= " ORDER BY created_at DESC LIMIT {$limitSql}";
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $val) {
            $stmt->bindValue($key, $val);
        }
        $stmt->execute();

        $alerts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        foreach ($alerts as &$alert) {
            if (isset($alert['data'])) {
                $alert['data'] = json_decode($alert['data'], true);
            }
        }

        return $alerts;
    }

    /**
     * Marca um alerta específico como lido.
     */
    public function markRead(int $alertId): bool
    {
        $db = Database::getInstance();
        $stmt = $db->prepare("UPDATE alerts SET read_at = NOW() WHERE id = :id AND read_at IS NULL");
        $stmt->execute(['id' => $alertId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Marca todos os alertas como lidos (opcionalmente filtrado por conta).
     *
     * @return int Número de registros atualizados
     */
    public function markAllRead(?int $accountId = null): int
    {
        $db = Database::getInstance();

        $sql = "UPDATE alerts SET read_at = NOW() WHERE read_at IS NULL";
        $params = [];

        if ($accountId !== null) {
            $sql .= " AND ml_account_id = :account_id";
            $params['account_id'] = $accountId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return $stmt->rowCount();
    }

    /**
     * Conta alertas não lidos.
     */
    public function countUnread(?int $accountId = null): int
    {
        $db = Database::getInstance();

        $sql = "SELECT COUNT(*) as total FROM alerts WHERE read_at IS NULL";
        $params = [];

        if ($accountId !== null) {
            $sql .= " AND ml_account_id = :account_id";
            $params['account_id'] = $accountId;
        }

        $stmt = $db->prepare($sql);
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    // ──────────────────────────────────────────────
    // Detection / Background Methods
    // ──────────────────────────────────────────────

    /**
     * Executa verificações de alertas "globais" (agregador simples)
     *
     * Retorna um resumo para uso em jobs/cron.
     */
    public function checkAllAlerts(): array
    {
        $this->logger->info('Running checkAllAlerts');
        $checks = 0;
        $triggered = 0;
        $details = [];

        // 1) Tokens próximos de expirar
        $checks++;
        try {
            $expiring = $this->checkExpiringTokens(7);
            $triggered += is_array($expiring) ? count($expiring) : 0;
            $details['token_expiring'] = [
                'count' => is_array($expiring) ? count($expiring) : 0,
            ];
        } catch (\Exception $e) {
            $this->logger->exception($e, ['stage' => 'checkExpiringTokens']);
            $details['token_expiring'] = [
                'error' => $e->getMessage(),
            ];
        }

        return [
            'checked' => $checks,
            'triggered' => $triggered,
            'details' => $details,
        ];
    }

    /**
     * Verifica tokens próximos de expirar
     */
    public function checkExpiringTokens(int $daysAhead = 7): array
    {
        $db = Database::getInstance();

        $stmt = $db->prepare("
            SELECT id, nickname, token_expires_at
            FROM ml_accounts
            WHERE status = 'active'
            AND token_expires_at <= DATE_ADD(NOW(), INTERVAL :days DAY)
            AND token_expires_at > NOW()
        ");
        $stmt->execute(['days' => $daysAhead]);

        $expiring = $stmt->fetchAll();

        foreach ($expiring as &$account) {
            $alertData = [
                'nickname' => $account['nickname'],
                'expires_at' => $account['token_expires_at'],
            ];

            $this->createAlert($account['id'], 'token_expiring', $alertData);

            // Enviar e-mail se configurado
            $this->sendTokenExpiringEmail($account['id'], $alertData);

            // Enviar Telegram se configurado
            $this->sendTokenExpiringTelegram($account['id'], $alertData);
        }

        return $expiring;
    }

    /**
     * Detecta novos concorrentes em uma categoria/marca
     */
    public function detectNewCompetitors(string $categoryId, string $brand, int $accountId): array
    {
        $searchService = new SearchService($accountId);
        $analysis = $searchService->analyzeListings($categoryId, $brand);

        if (isset($analysis['error'])) {
            return $analysis;
        }

        // Obter vendedores atuais
        $sellers = [];
        foreach (($analysis['catalog']['items'] ?? []) as $item) {
            $sellerId = $item['seller']['id'] ?? null;
            if ($sellerId) {
                $sellers[$sellerId] = $item['seller']['nickname'] ?? 'Unknown';
            }
        }

        foreach (($analysis['common']['items'] ?? []) as $item) {
            $sellerId = $item['seller']['id'] ?? null;
            if ($sellerId) {
                $sellers[$sellerId] = $item['seller']['nickname'] ?? 'Unknown';
            }
        }

        // Comparar com histórico real de sellers conhecidos
        $db = Database::getInstance();
        $this->ensureCompetitorHistoryTable();

        $knownSellers = [];
        try {
            $stmt = $db->prepare("
                SELECT seller_id FROM competitor_history
                WHERE category_id = :category_id
                  AND account_id = :account_id
            ");
            $stmt->execute([
                'category_id' => $categoryId,
                'account_id'  => $accountId,
            ]);
            $knownSellers = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } catch (\Exception $e) {
            $this->logger->warning('competitor_history_query_failed', ['error' => $e->getMessage()]);
        }

        $knownSet = array_flip($knownSellers);
        $newSellers = [];

        foreach ($sellers as $sellerId => $nickname) {
            // Registrar/atualizar seller no histórico
            try {
                $stmt = $db->prepare("
                    INSERT INTO competitor_history (category_id, account_id, seller_id, seller_nickname, first_seen, last_seen)
                    VALUES (:cat, :acc, :sid, :nick, NOW(), NOW())
                    ON DUPLICATE KEY UPDATE seller_nickname = :nick2, last_seen = NOW()
                ");
                $stmt->execute([
                    'cat'   => $categoryId,
                    'acc'   => $accountId,
                    'sid'   => (string)$sellerId,
                    'nick'  => $nickname,
                    'nick2' => $nickname,
                ]);
            } catch (\Exception $e) {
                error_log('AlertService: failed to track competitor - ' . $e->getMessage());
            }

            // Só alertar se for genuinamente novo
            if (isset($knownSet[(string)$sellerId])) {
                continue;
            }

            $newSellers[] = [
                'seller_id' => $sellerId,
                'nickname' => $nickname,
            ];

            $alertData = [
                'category_id' => $categoryId,
                'brand' => $brand,
                'seller_id' => $sellerId,
                'seller_nickname' => $nickname,
            ];

            $this->createAlert($accountId, 'new_competitor', $alertData);

            // Enviar Telegram se configurado
            $telegramService = new TelegramService();
            if (method_exists($telegramService, 'isEnabled') ? $telegramService->isEnabled() : false) {
                $telegramService->sendNewCompetitorNotification($alertData);
            }
        }

        return $newSellers;
    }

    /**
     * Detecta variação significativa de preço
     */
    public function detectPriceVariation(string $categoryId, string $brand, float $threshold = 0.15): array
    {
        $searchService = new SearchService();
        $analysis = $searchService->analyzeListings($categoryId, $brand);

        if (isset($analysis['error']) || !isset($analysis['prices']['avg'])) {
            return [];
        }

        $avgPrice = $analysis['prices']['avg'];
        $minPrice = $analysis['prices']['min'];
        $maxPrice = $analysis['prices']['max'];

        $variation = $avgPrice > 0 ? ($maxPrice - $minPrice) / $avgPrice : 0.0;

        if ($variation > $threshold) {
            return [
                'variation' => $variation,
                'avg_price' => $avgPrice,
                'min_price' => $minPrice,
                'max_price' => $maxPrice,
                'alert' => true,
            ];
        }

        return [
            'variation' => $variation,
            'alert' => false,
        ];
    }

    /**
     * Cria um alerta no sistema
     */
    public function createAlert(?int $accountId, string $type, array $data): void
    {
        $db = Database::getInstance();

        $this->ensureAlertsTable();

        try {
            $stmt = $db->prepare("
                INSERT INTO alerts
                (ml_account_id, type, severity, message, data, read_at, created_at)
                VALUES
                (:account_id, :type, :severity, :message, :data, NULL, NOW())
            ");

            $severity = $this->getSeverity($type);
            $message = $this->getMessage($type, $data);

            $stmt->execute([
                'account_id' => $accountId,
                'type' => $type,
                'severity' => $severity,
                'message' => $message,
                'data' => json_encode($data),
            ]);

            $this->logger->info("alert_created", ['account_id' => $accountId, 'type' => $type, 'severity' => $severity]);
        } catch (\Exception $e) {
            $this->logger->exception($e, ['account_id' => $accountId, 'type' => $type]);
        }
    }

    /**
     * Detecta novos produtos em uma categoria
     */
    public function detectNewProductsInCategory(string $categoryId, ?string $brand = null, ?int $accountId = null): array
    {
        $searchService = new SearchService($accountId);

        // Buscar produtos recentes (últimos 7 dias)
        $filters = [
            'category' => $categoryId,
            'sort' => 'relevance', // Produtos mais recentes primeiro
            'limit' => 50,
        ];

        if ($brand) {
            $filters['BRAND'] = $brand;
        }

        $results = $searchService->search($filters);

        if (isset($results['error'])) {
            return $results;
        }

        $newProducts = [];
        $sevenDaysAgo = strtotime('-7 days');

        foreach ($results['results'] ?? [] as $item) {
            // Verificar se o produto foi criado recentemente
            $dateCreated = $item['date_created'] ?? null;
            if ($dateCreated) {
                $createdTimestamp = strtotime($dateCreated);

                if ($createdTimestamp >= $sevenDaysAgo) {
                    $newProducts[] = [
                        'item_id' => $item['id'] ?? null,
                        'title' => $item['title'] ?? '',
                        'price' => $item['price'] ?? 0,
                        'seller_id' => $item['seller']['id'] ?? null,
                        'seller_nickname' => $item['seller']['nickname'] ?? '',
                        'date_created' => $dateCreated,
                        'permalink' => $item['permalink'] ?? '',
                    ];

                    // Criar alerta
                    if ($accountId) {
                        $alertData = [
                            'category_id' => $categoryId,
                            'brand' => $brand,
                            'item_id' => $item['id'] ?? null,
                            'item_title' => $item['title'] ?? '',
                            'seller_nickname' => $item['seller']['nickname'] ?? '',
                        ];

                        $this->createAlert($accountId, 'new_product_in_category', $alertData);

                        // Enviar Telegram se configurado
                        $telegramService = new TelegramService();
                        if (method_exists($telegramService, 'isEnabled') ? $telegramService->isEnabled() : false) {
                            $telegramService->sendNewProductNotification($alertData);
                        }
                    }
                }
            }
        }

        return [
            'category_id' => $categoryId,
            'brand' => $brand,
            'new_products_count' => count($newProducts),
            'new_products' => $newProducts,
        ];
    }

    /**
     * Obtém severidade do alerta
     */
    private function getSeverity(string $type): string
    {
        $severities = [
            'token_expiring' => 'warning',
            'new_competitor' => 'info',
            'price_variation' => 'warning',
            'new_order' => 'success',
            'new_product_in_category' => 'info',
            'ai_billing_error' => 'danger',
            'ai_provider_error' => 'danger',
            'awa_new_seller' => 'warning',
            'awa_volume_spike' => 'warning',
            'awa_unidentified_seller' => 'info',
        ];

        return $severities[$type] ?? 'info';
    }

    /**
     * Gera mensagem do alerta
     */
    private function getMessage(string $type, array $data): string
    {
        switch ($type) {
            case 'token_expiring':
                return "Token da conta {$data['nickname']} expira em breve";

            case 'new_competitor':
                return "Novo concorrente detectado: {$data['seller_nickname']}";

            case 'price_variation':
                return "Variação significativa de preços detectada";

            case 'new_order':
                return "Novo pedido recebido: R$ " . number_format($data['total'], 2, ',', '.');

            case 'new_product_in_category':
                $brandText = isset($data['brand']) ? " ({$data['brand']})" : '';
                return "Novo produto detectado na categoria{$brandText}: {$data['item_title']}";

            case 'ai_billing_error':
                return "CRÍTICO: A IA parou de funcionar. Motivo: {$data['error']}. Ação: {$data['action_required']}";

            case 'ai_provider_error':
                return "ERRO IA: Falha em todos os provedores. Último erro: {$data['last_error']}";

            case 'awa_new_seller':
                $count = (int) ($data['new_seller_count'] ?? 1);
                return "{$count} novo(s) vendedor(es) AWA detectado(s) no scan #{$data['scan_id']}";

            case 'awa_volume_spike':
                return "Pico de volume AWA: vendedor {$data['nickname']} subiu de {$data['items_before']} para {$data['items_after']} anúncios";

            case 'awa_unidentified_seller':
                $count = (int) ($data['unidentified_count'] ?? 1);
                return "{$count} vendedor(es) AWA sem identificação há mais de {$data['days']} dias";

            default:
                return "Alerta: {$type}";
        }
    }

    /**
     * Envia e-mail de token expirando
     */
    private function sendTokenExpiringEmail(int $accountId, array $accountData): void
    {
        try {
            $db = Database::getInstance();

            $stmt = $db->prepare("
                SELECT u.email
                FROM ml_accounts ma
                JOIN users u ON ma.user_id = u.id
                WHERE ma.id = :account_id
            ");
            $stmt->execute(['account_id' => $accountId]);
            $account = $stmt->fetch();

            if ($account && !empty($account['email'])) {
                $emailService = new EmailService();
                $emailService->sendTokenExpiringNotification(
                    $account['email'],
                    $accountData
                );
            }
        } catch (\Exception $e) {
            $this->logger->exception($e, ['account_id' => $accountId]);
        }
    }

    /**
     * Garante que a tabela competitor_history existe.
     */
    private function ensureCompetitorHistoryTable(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }

        try {
            $db = Database::getInstance();
            $db->exec("
                CREATE TABLE IF NOT EXISTS competitor_history (
                    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                    category_id VARCHAR(50) NOT NULL,
                    account_id INT NOT NULL,
                    seller_id VARCHAR(50) NOT NULL,
                    seller_nickname VARCHAR(255) DEFAULT NULL,
                    first_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    last_seen DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                    UNIQUE KEY uq_cat_acc_seller (category_id, account_id, seller_id),
                    INDEX idx_category (category_id),
                    INDEX idx_account (account_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
            ");
            $checked = true;
        } catch (\Exception $e) {
            $this->logger->warning('competitor_history_table_creation_failed', ['error' => $e->getMessage()]);
        }
    }

    /**
     * Envia notificação Telegram de token expirando
     */
    private function sendTokenExpiringTelegram(int $accountId, array $accountData): void
    {
        try {
            $telegramService = new TelegramService();
            if (method_exists($telegramService, 'isEnabled') ? $telegramService->isEnabled() : false) {
                $telegramService->sendTokenExpiringNotification($accountData);
            }
        } catch (\Exception $e) {
            $this->logger->exception($e, ['account_id' => $accountId]);
        }
    }

    /**
     * Garante que tabela de alertas existe
     */
    private function ensureAlertsTable(): void
    {
        $db = Database::getInstance();
        $db->exec("
            CREATE TABLE IF NOT EXISTS alerts (
                id INT PRIMARY KEY AUTO_INCREMENT,
                ml_account_id INT NULL,
                type VARCHAR(50) NOT NULL,
                severity ENUM('info', 'warning', 'danger', 'success') DEFAULT 'info',
                message TEXT NOT NULL,
                data JSON NULL,
                read_at DATETIME NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (ml_account_id) REFERENCES ml_accounts(id) ON DELETE CASCADE,
                INDEX idx_account_id (ml_account_id),
                INDEX idx_type (type),
                INDEX idx_severity (severity),
                INDEX idx_read_at (read_at),
                INDEX idx_created_at (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
