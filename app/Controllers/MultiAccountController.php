<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\AI\SEO\MultiAccountManager;
use App\Services\MercadoLivreAuthService;
use App\Database;

/**
 * Multi-Account Controller
 *
 * Gerencia endpoints para operações multi-conta do SEO Killer
 *
 * @package App\Controllers
 * @version 1.9.0
 * @since 2025-12-31
 */
class MultiAccountController
{
    private MultiAccountManager $manager;
    private int $userId;
    private Request $request;

    public function __construct()
    {
        $this->request = new Request();
        $this->userId = $_SESSION['user_id'] ?? 0;

        if (!$this->userId) {
            $this->jsonError('Unauthorized', 401);
            exit;
        }

        $this->manager = new MultiAccountManager($this->userId);
    }

    /**
     * GET /api/multi-account/dashboard
     *
     * Dashboard consolidado de todas as contas
     * Query params:
     * - account_ids: array opcional de IDs específicos
     * - limit_alerts: int (default 10)
     */
    public function getDashboard(): void
    {
        try {
            $accountIds = $this->request->get('account_ids');

            if ($accountIds && is_string($accountIds)) {
                $accountIds = array_map('intval', explode(',', $accountIds));
            }

            $options = [
                'limit_alerts' => $this->request->getInt('limit_alerts', 10)
            ];

            $dashboard = $this->manager->getDashboard($accountIds, $options);

            $this->jsonResponse($dashboard);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/multi-account/compare
     *
     * Compara performance entre múltiplas contas
     * Query params REQUIRED:
     * - account_ids: string comma-separated (ex: "1,2,3")
     * - metric: score|sales|views|conversions (default: score)
     * - days: int (default: 30)
     */
    public function comparePerformance(): void
    {
        try {
            $accountIdsParam = $this->request->get('account_ids');

            if (!$accountIdsParam) {
                $this->jsonError('account_ids parameter is required', 400);
                return;
            }

            $accountIds = array_map('intval', explode(',', $accountIdsParam));
            $metric = $this->request->get('metric', 'score') ?? 'score';
            $days = $this->request->getInt('days', 30);

            $comparison = $this->manager->comparePerformance($accountIds, $metric, $days);

            $this->jsonResponse($comparison);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/multi-account/bulk-optimize
     *
     * Otimização em lote cross-account
     * Body JSON:
     * {
     *   "account_ids": [1, 2, 3],
     *   "filters": {"seo_score": {"max": 70}},
     *   "optimizations": {
     *     "optimize_title": true,
     *     "optimize_description": true,
     *     "fill_attributes": true
     *   },
     *   "auto_apply": false,
     *   "max_items_per_account": 50
     * }
     */
    public function bulkOptimize(): void
    {
        try {
            $body = $this->getJsonBody();

            if (!isset($body['account_ids']) || !is_array($body['account_ids'])) {
                $this->jsonError('account_ids array is required', 400);
                return;
            }

            $accountIds = array_map('intval', $body['account_ids']);

            $options = [
                'filters' => $body['filters'] ?? ['seo_score' => ['max' => 70]],
                'optimizations' => $body['optimizations'] ?? [
                    'optimize_title' => true,
                    'optimize_description' => true,
                    'fill_attributes' => true
                ],
                'auto_apply' => $body['auto_apply'] ?? false,
                'max_items_per_account' => $body['max_items_per_account'] ?? 50
            ];

            $result = $this->manager->bulkOptimize($accountIds, $options);

            $this->jsonResponse($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/multi-account/report
     *
     * Relatório consolidado de múltiplas contas
     * Query params:
     * - account_ids: string comma-separated (opcional, default: todas)
     * - period: daily|weekly|monthly (default: monthly)
     * - avg_product_price: float (default: 150.0)
     */
    public function getConsolidatedReport(): void
    {
        try {
            $accountIdsParam = $this->request->get('account_ids');

            $accountIds = $accountIdsParam
                ? array_map('intval', explode(',', $accountIdsParam))
                : null;

            if (!$accountIds) {
                // Buscar todas as contas do usuário
                $stmt = \App\Database::getInstance()->prepare("
                    SELECT id FROM ml_accounts WHERE user_id = ? AND status = 'active'
                ");
                $stmt->execute([$this->userId]);
                $accountIds = $stmt->fetchAll(\PDO::FETCH_COLUMN);
            }

            $period = $this->request->get('period', 'monthly') ?? 'monthly';
            $options = [
                'avg_product_price' => $this->request->getFloat('avg_product_price', 150.0)
            ];

            $report = $this->manager->consolidatedReport($accountIds, $period, $options);

            $this->jsonResponse($report);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/multi-account/groups
     *
     * Gerenciar grupos de contas
     * Body JSON:
     * {
     *   "action": "create|update|delete|list|add_account|remove_account",
     *   "group_id": 123,
     *   "name": "Lojas Premium",
     *   "description": "Contas com maior volume",
     *   "account_ids": [1, 2, 3]
     * }
     */
    public function manageGroups(): void
    {
        try {
            $body = $this->getJsonBody();

            if (!isset($body['action'])) {
                $this->jsonError('action parameter is required', 400);
                return;
            }

            $result = $this->manager->manageAccountGroups($body['action'], $body);

            $this->jsonResponse($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/multi-account/switch
     *
     * Trocar contexto de conta ativa
     * Body JSON:
     * {
     *   "account_id": 123
     * }
     */
    public function switchAccount(): void
    {
        try {
            $body = $this->getJsonBody();

            if (!isset($body['account_id'])) {
                $this->jsonError('account_id parameter is required', 400);
                return;
            }

            $result = $this->manager->switchAccount((int)$body['account_id']);

            $this->jsonResponse($result);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/multi-account/accounts
     *
     * Lista todas as contas do usuário com estatísticas básicas
     */
    public function getAccounts(): void
    {
        $this->listAccounts();
    }

    /**
     * GET /api/multi-account/accounts
     *
     * Lista todas as contas do usuário com estatísticas básicas
     */
    public function listAccounts(): void
    {
        try {
            $db = \App\Database::getInstance();

            $stmt = $db->prepare("
                SELECT
                    ma.id,
                    ma.nickname,
                    ma.country_id,
                    ma.status,
                    ma.created_at,
                    COUNT(DISTINCT so.item_id) as items_count,
                    COUNT(so.id) as optimizations_count,
                    AVG(so.score_after) as avg_score,
                    (SELECT COUNT(*) FROM competitor_watchlist WHERE account_id = ma.id) as watchlist_count
                FROM ml_accounts ma
                LEFT JOIN seo_optimizations so ON so.account_id = ma.id
                WHERE ma.user_id = ?
                GROUP BY ma.id
                ORDER BY (ma.status = 'active') DESC, optimizations_count DESC
            ");
            $stmt->execute([$this->userId]);
            $accounts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $this->jsonResponse([
                'accounts' => $accounts,
                'total' => count($accounts),
                'active' => count(array_filter($accounts, fn($a) => ($a['status'] ?? '') === 'active'))
            ]);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * GET /api/multi-account/tokens/status
     *
     * Verifica status dos tokens de todas as contas
     */
    public function getTokensStatus(): void
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT
                    id,
                    nickname,
                    status,
                    token_expires_at,
                    CASE
                        WHEN token_expires_at IS NULL THEN 'unknown'
                        WHEN token_expires_at < NOW() THEN 'expired'
                        WHEN token_expires_at < DATE_ADD(NOW(), INTERVAL 2 HOUR) THEN 'expiring_soon'
                        ELSE 'valid'
                    END as token_status,
                    TIMESTAMPDIFF(MINUTE, NOW(), token_expires_at) as minutes_until_expiration
                FROM ml_accounts
                WHERE user_id = ?
                ORDER BY token_expires_at ASC
            ");
            $stmt->execute([$this->userId]);
            $accounts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $summary = [
                'total' => count($accounts),
                'valid' => 0,
                'expiring_soon' => 0,
                'expired' => 0,
                'unknown' => 0
            ];

            foreach ($accounts as $account) {
                $summary[$account['token_status']]++;
            }

            $this->jsonResponse([
                'accounts' => $accounts,
                'summary' => $summary,
                'needs_attention' => $summary['expired'] > 0 || $summary['expiring_soon'] > 0
            ]);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/multi-account/tokens/refresh
     *
     * Tenta renovar token de uma conta específica
     * Body: { "account_id": int }
     */
    public function refreshToken(): void
    {
        try {
            $data = $this->getJsonBody();
            $accountId = (int)($data['account_id'] ?? 0);

            if (!$accountId) {
                $this->jsonError('account_id is required', 400);
                return;
            }

            // Verificar se a conta pertence ao usuário
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT id, nickname FROM ml_accounts WHERE id = ? AND user_id = ?");
            $stmt->execute([$accountId, $this->userId]);
            $account = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$account) {
                $this->jsonError('Account not found or access denied', 404);
                return;
            }

            $authService = new MercadoLivreAuthService();
            $success = $authService->refreshToken($accountId);

            if ($success) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => "Token da conta '{$account['nickname']}' renovado com sucesso",
                    'account_id' => $accountId
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'message' => "Não foi possível renovar o token. A conta pode precisar ser reconectada manualmente.",
                    'account_id' => $accountId,
                    'needs_reconnect' => true,
                    'reconnect_url' => '/auth/authorize?reconnect=' . (int)$accountId
                ], 401);
            }
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    /**
     * POST /api/multi-account/tokens/refresh-all
     *
     * Tenta renovar tokens de todas as contas do usuário
     */
    public function refreshAllTokens(): void
    {
        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("
                SELECT id, nickname
                FROM ml_accounts
                WHERE user_id = ?
                AND (token_expires_at IS NULL OR token_expires_at < DATE_ADD(NOW(), INTERVAL 2 HOUR))
            ");
            $stmt->execute([$this->userId]);
            $accounts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            if (empty($accounts)) {
                $this->jsonResponse([
                    'success' => true,
                    'message' => 'Todos os tokens estão válidos',
                    'refreshed' => 0,
                    'failed' => 0
                ]);
                return;
            }

            $authService = new MercadoLivreAuthService();
            $results = [
                'refreshed' => [],
                'failed' => []
            ];

            foreach ($accounts as $account) {
                try {
                    $success = $authService->refreshToken($account['id']);
                } catch (\Throwable $e) {
                    $success = false;
                    try {
                        (new \App\Services\StructuredLogService())->error('Exceção ao renovar token de conta ML', [
                            'account_id' => $account['id'],
                            'nickname'   => $account['nickname'],
                            'error'      => $e->getMessage(),
                        ]);
                    } catch (\Throwable $ignored) {
                        // best-effort
                    }
                }
                if ($success) {
                    $results['refreshed'][] = $account['nickname'];
                } else {
                    $results['failed'][] = $account['nickname'];
                }
            }

            $this->jsonResponse([
                'success' => empty($results['failed']),
                'message' => count($results['refreshed']) . ' conta(s) renovada(s), ' . count($results['failed']) . ' falha(s)',
                'refreshed' => $results['refreshed'],
                'failed' => $results['failed'],
                'needs_reconnect' => !empty($results['failed']),
                'reconnect_url' => !empty($results['failed']) ? '/dashboard/accounts' : null
            ]);
        } catch (\Exception $e) {
            $this->jsonError($e->getMessage(), 500);
        }
    }

    // ==================== HELPER METHODS ====================

    private function getJsonBody(): array
    {
        $body = file_get_contents('php://input');
        $data = json_decode($body, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->jsonError('Invalid JSON body', 400);
            exit;
        }

        return $data ?? [];
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    private function jsonError(string $message, int $statusCode = 400): void
    {
        $this->jsonResponse([
            'error' => true,
            'message' => $message
        ], $statusCode);
    }
}
