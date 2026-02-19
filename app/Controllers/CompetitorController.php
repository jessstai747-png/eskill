<?php

namespace App\Controllers;

use App\Core\Request;
use App\Services\CompetitorAnalysisService;

class CompetitorController
{
    private CompetitorAnalysisService $competitorService;
    private Request $request;
    
    public function __construct()
    {
        $this->request = new Request();
        $this->competitorService = new CompetitorAnalysisService();
    }
    
    /**
     * Analisa concorrência
     */
    public function analyze(): void
    {
        $categoryId = $this->request->get('category');
        $brand = $this->request->get('brand');
        $accountId = $this->request->getInt('account_id', 0) ?: null;
        
        if (!$categoryId || !$brand) {
            http_response_code(400);
            echo json_encode(['error' => 'Parâmetros "category" e "brand" são obrigatórios']);
            return;
        }
        
        $analysis = $this->competitorService->analyzeCompetition($categoryId, $brand, $accountId);
        
        header('Content-Type: application/json');
        echo json_encode($analysis);
    }
    
    /**
     * Detecta oportunidades
     */
    public function opportunities(): void
    {
        $categoryId = $this->request->get('category');
        $brand = $this->request->get('brand');
        $accountId = $this->request->getInt('account_id', 0) ?: null;
        
        if (!$categoryId || !$brand) {
            http_response_code(400);
            echo json_encode(['error' => 'Parâmetros "category" e "brand" são obrigatórios']);
            return;
        }
        
        $opportunities = $this->competitorService->detectOpportunities($categoryId, $brand, $accountId);
        
        header('Content-Type: application/json');
        echo json_encode($opportunities);
    }

    /**
     * Lista concorrentes monitorados
     * GET /api/competitors
     */
    public function index(): void
    {
        header('Content-Type: application/json');

        try {
            $db = \App\Database::getInstance();
            $accountId = $_SESSION['active_account_id'] ?? null;

            $this->ensureTable($db);

            $stmt = $db->prepare("
                SELECT seller_id, nickname, reputation_level, total_items,
                       sales_completed, created_at
                FROM monitored_competitors
                WHERE account_id = :account_id
                ORDER BY created_at DESC
            ");
            $stmt->execute(['account_id' => $accountId]);
            $competitors = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'competitors' => $competitors,
                'total' => count($competitors),
                'total_products' => array_sum(array_column($competitors, 'total_items')),
                'price_alerts' => 0,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Adiciona concorrente para monitoramento
     * POST /api/competitors
     */
    public function add(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true) ?? [];
            $identifier = $input['input'] ?? '';
            $accountId = $_SESSION['active_account_id'] ?? null;

            if (empty($identifier)) {
                http_response_code(400);
                echo json_encode(['error' => 'ID ou link do vendedor é obrigatório']);
                return;
            }

            // Extrair seller ID de URL ou usar direto
            $sellerId = $identifier;
            if (preg_match('/perfil\/([A-Z0-9_]+)/i', $identifier, $m)) {
                $sellerId = $m[1];
            } elseif (preg_match('/\/(\d+)$/', $identifier, $m)) {
                $sellerId = $m[1];
            }

            $db = \App\Database::getInstance();
            $this->ensureTable($db);

            // Buscar dados do vendedor na API do ML
            $nickname = $sellerId;
            $reputationLevel = 'unknown';
            $totalItems = 0;
            $salesCompleted = 0;

            try {
                $client = new \App\Services\MercadoLivreClient($accountId);
                $userData = $client->get("/users/{$sellerId}");
                $nickname = $userData['nickname'] ?? $sellerId;
                $reputationLevel = $userData['seller_reputation']['level_id'] ?? 'unknown';
                $totalItems = $userData['seller_reputation']['transactions']['total'] ?? 0;
                $salesCompleted = $userData['seller_reputation']['transactions']['completed'] ?? 0;
            } catch (\Exception $e) {
                // Continua mesmo sem dados da API
            }

            $stmt = $db->prepare("
                INSERT INTO monitored_competitors
                    (account_id, seller_id, nickname, reputation_level, total_items, sales_completed, created_at)
                VALUES
                    (:account_id, :seller_id, :nickname, :reputation_level, :total_items, :sales_completed, NOW())
                ON DUPLICATE KEY UPDATE
                    nickname = VALUES(nickname),
                    reputation_level = VALUES(reputation_level),
                    total_items = VALUES(total_items),
                    sales_completed = VALUES(sales_completed)
            ");
            $stmt->execute([
                'account_id' => $accountId,
                'seller_id' => $sellerId,
                'nickname' => $nickname,
                'reputation_level' => $reputationLevel,
                'total_items' => $totalItems,
                'sales_completed' => $salesCompleted,
            ]);

            echo json_encode(['success' => true, 'message' => 'Concorrente adicionado']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Remove concorrente do monitoramento
     * DELETE /api/competitors/{sellerId}
     */
    public function remove(string $sellerId): void
    {
        header('Content-Type: application/json');

        try {
            $accountId = $_SESSION['active_account_id'] ?? null;
            $db = \App\Database::getInstance();

            $stmt = $db->prepare("
                DELETE FROM monitored_competitors
                WHERE account_id = :account_id AND seller_id = :seller_id
            ");
            $stmt->execute(['account_id' => $accountId, 'seller_id' => $sellerId]);

            echo json_encode(['success' => true, 'message' => 'Concorrente removido']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    /**
     * Garante que a tabela de concorrentes monitorados existe
     */
    private function ensureTable(\PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS monitored_competitors (
                id INT AUTO_INCREMENT PRIMARY KEY,
                account_id INT,
                seller_id VARCHAR(100) NOT NULL,
                nickname VARCHAR(255) DEFAULT '',
                reputation_level VARCHAR(50) DEFAULT 'unknown',
                total_items INT DEFAULT 0,
                sales_completed INT DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                UNIQUE KEY uk_account_seller (account_id, seller_id),
                INDEX idx_account (account_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}

