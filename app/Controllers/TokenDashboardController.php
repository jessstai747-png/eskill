<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\UnifiedTokenRefreshService;

/**
 * Controller para Dashboard de Tokens ML
 * 
 * Endpoints para visualização e gerenciamento do estado dos tokens
 * do Mercado Livre, incluindo métricas de saúde, histórico e ações.
 */
class TokenDashboardController extends BaseController
{
    private UnifiedTokenRefreshService $tokenService;

    public function __construct()
    {
        parent::__construct();
        $this->tokenService = new UnifiedTokenRefreshService();
    }

    /**
     * GET /api/tokens/dashboard
     * 
     * Retorna métricas completas de saúde dos tokens
     * 
     * Response:
     * {
     *   "timestamp": "2026-02-09 12:00:00",
     *   "total_accounts": 5,
     *   "active_accounts": 3,
     *   "expired_accounts": 2,
     *   "expiring_24h": 1,
     *   "expiring_48h": 2,
     *   "refresh_attempts_24h": 10,
     *   "refresh_successes_24h": 8,
     *   "refresh_failures_24h": 2,
     *   "failure_rate_24h": 20.0,
     *   "accounts_with_failures": 1,
     *   "last_refresh_avg_hours": 12.5,
     *   "health_status": "warning"
     * }
     */
    public function getMetrics(): void
    {
        $this->requireUserId();
        header('Content-Type: application/json');

        try {
            $metrics = $this->tokenService->getHealthMetrics();

            echo json_encode([
                'success' => true,
                'data' => $metrics,
                'timestamp' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Falha ao obter métricas: ' . $e->getMessage(),
            ]);
        }
    }

    /**
     * GET /api/tokens/accounts
     * 
     * Lista todas as contas com informações de tokens
     * 
     * Query params:
     * - status: active|expired|all (default: all)
     * - sort: expires_at|last_refresh|failures (default: expires_at)
     * - order: asc|desc (default: asc)
     * 
     * Response:
     * {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 1,
     *       "nickname": "Loja Exemplo",
     *       "ml_user_id": "123456",
     *       "status": "active",
     *       "token_expires_at": "2026-02-10 14:30:00",
     *       "last_refresh_at": "2026-02-09 12:00:00",
     *       "refresh_failure_count": 0,
     *       "hours_until_expiration": 26.5,
     *       "requires_attention": false
     *     }
     *   ],
     *   "total": 5,
     *   "filters": {...}
     * }
     */
    public function listAccounts(): void
    {
        $this->requireUserId();
        header('Content-Type: application/json');

        try {
            $db = \App\Database::getInstance();

            // Parâmetros de filtro
            $status = $this->request->get('status', 'all') ?? 'all';
            $sort = $this->request->get('sort', 'expires_at') ?? 'expires_at';
            $order = $this->request->get('order', 'asc') ?? 'asc';

            // Validação
            $allowedStatus = ['active', 'expired', 'expiring', 'all'];
            $allowedSort = ['expires_at', 'last_refresh', 'failures', 'name', 'failure_count'];
            $allowedOrder = ['asc', 'desc'];

            if (!in_array($status, $allowedStatus, true)) {
                $status = 'all';
            }
            if (!in_array($sort, $allowedSort, true)) {
                $sort = 'expires_at';
            }
            if (!in_array($order, $allowedOrder, true)) {
                $order = 'asc';
            }

            // Construir query
            $sql = "
                SELECT 
                    id,
                    nickname,
                    ml_user_id,
                    status,
                    token_expires_at,
                    last_refresh_at,
                    last_oauth_connection_at,
                    refresh_failure_count,
                    last_refresh_error,
                    TIMESTAMPDIFF(HOUR, NOW(), token_expires_at) as hours_until_expiration,
                    TIMESTAMPDIFF(HOUR, last_refresh_at, NOW()) as hours_since_refresh,
                    created_at
                FROM ml_accounts
            ";

            $params = [];
            $whereClauses = [];

            if ($status === 'expiring') {
                $whereClauses[] = "status = 'active'";
                $whereClauses[] = 'token_expires_at > NOW()';
                $whereClauses[] = "token_expires_at <= DATE_ADD(NOW(), INTERVAL 24 HOUR)";
            } elseif ($status !== 'all') {
                $whereClauses[] = 'status = :status';
                $params['status'] = $status;
            }

            if ($whereClauses !== []) {
                $sql .= ' WHERE ' . implode(' AND ', $whereClauses);
            }

            // Ordenação
            $orderByMap = [
                'expires_at' => 'token_expires_at',
                'last_refresh' => 'last_refresh_at',
                'failures' => 'refresh_failure_count',
                'name' => 'nickname',
                'failure_count' => 'refresh_failure_count',
            ];
            $sql .= " ORDER BY " . $orderByMap[$sort] . " " . strtoupper($order);

            $stmt = $db->prepare($sql);
            $stmt->execute($params);

            $accounts = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Enriquecer dados
            foreach ($accounts as &$account) {
                $hoursUntil = (float)$account['hours_until_expiration'];
                $account['requires_attention'] = (
                    $hoursUntil < 24 ||
                    $account['refresh_failure_count'] >= 3 ||
                    $account['status'] === 'expired'
                );

                $apiValidationStatus = $this->resolveApiValidationStatus($account);
                $account['api_validation_status'] = $apiValidationStatus;
                $account['diagnostic_label'] = $this->resolveDiagnosticLabel($apiValidationStatus);
                $account['diagnostic_message'] = $this->buildDiagnosticMessage($account);

                // Formatar datas para melhor legibilidade
                $account['expires_at_formatted'] = $account['token_expires_at']
                    ? date('d/m/Y H:i', strtotime($account['token_expires_at']))
                    : null;

                $account['last_refresh_formatted'] = $account['last_refresh_at']
                    ? date('d/m/Y H:i', strtotime($account['last_refresh_at']))
                    : 'Nunca';
            }

            echo json_encode([
                'success' => true,
                'data' => $accounts,
                'total' => count($accounts),
                'filters' => [
                    'status' => $status,
                    'sort' => $sort,
                    'order' => $order,
                ],
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * POST /api/tokens/refresh/{accountId}
     * 
     * Força renovação de token de uma conta específica
     * 
     * Response:
     * {
     *   "success": true,
     *   "message": "Token renovado com sucesso",
     *   "account_id": 123
     * }
     */
    public function refreshAccount(int $accountId): void
    {
        $this->requireUserId();
        if (!$this->isAdmin()) {
            $this->jsonError('Acesso negado', 403);
        }
        header('Content-Type: application/json');

        try {
            $result = $this->tokenService->refreshAccount($accountId);

            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'message' => $result['message'],
                    'account_id' => $accountId,
                    'timestamp' => $result['timestamp'],
                ]);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => $result['message'],
                    'account_id' => $accountId,
                ]);
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * POST /api/tokens/refresh-all
     * 
     * Força renovação de todos os tokens
     * 
     * Query params:
     * - mode: expiring_only|force_all (default: expiring_only)
     * 
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "started_at": "2026-02-09 12:00:00",
     *     "finished_at": "2026-02-09 12:00:15",
     *     "mode": "expiring_only",
     *     "accounts_checked": 3,
     *     "tokens_refreshed": 2,
     *     "tokens_failed": 1,
     *     "tokens_skipped": 0
     *   }
     * }
     */
    public function refreshAll(): void
    {
        $this->requireUserId();
        if (!$this->isAdmin()) {
            $this->jsonError('Acesso negado', 403);
        }
        header('Content-Type: application/json');

        try {
            $mode = $this->request->input('mode', 'expiring_only');
            $forceAll = ($mode === 'force_all');

            $results = $forceAll
                ? $this->tokenService->forceRefreshAll()
                : $this->tokenService->refreshExpiring();

            echo json_encode([
                'success' => true,
                'data' => $results,
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * GET /api/tokens/audit/{accountId}
     * 
     * Retorna histórico de auditoria de uma conta
     * 
     * Query params:
     * - limit: número de registros (default: 50, max: 200)
     * - action: filtrar por ação (refresh_success, refresh_failed, etc)
     * 
     * Response:
     * {
     *   "success": true,
     *   "data": [
     *     {
     *       "id": 123,
     *       "action": "refresh_success",
     *       "http_code": 200,
     *       "execution_time_ms": 1234,
     *       "expires_at_before": "2026-02-09 10:00:00",
     *       "expires_at_after": "2026-02-15 10:00:00",
     *       "created_at": "2026-02-09 12:00:00"
     *     }
     *   ],
     *   "total": 50,
     *   "account_id": 123
     * }
     */
    public function getAuditHistory(int $accountId): void
    {
        $this->requireUserId();
        header('Content-Type: application/json');

        try {
            $db = \App\Database::getInstance();

            $limit = min($this->request->getInt('limit', 50), 200);
            $action = $this->request->get('action');

            $limitSql = max(1, min(200, (int)$limit));

            $sql = "
                SELECT 
                    id,
                    action,
                    details,
                    http_code,
                    error_message,
                    expires_at_before,
                    expires_at_after,
                    execution_time_ms,
                    created_at
                FROM token_refresh_audit
                WHERE account_id = :account_id
            ";

            if ($action) {
                $sql .= " AND action = :action";
            }

            $sql .= " ORDER BY created_at DESC LIMIT {$limitSql}";

            $stmt = $db->prepare($sql);
            $stmt->bindValue(':account_id', $accountId, \PDO::PARAM_INT);
            if ($action) {
                $stmt->bindValue(':action', $action, \PDO::PARAM_STR);
            }
            $stmt->execute();

            $audit = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Decodificar JSON details
            foreach ($audit as &$entry) {
                if ($entry['details']) {
                    $entry['details'] = json_decode($entry['details'], true);
                }
            }

            echo json_encode([
                'success' => true,
                'data' => $audit,
                'total' => count($audit),
                'account_id' => $accountId,
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * GET /api/tokens/stats
     * 
     * Estatísticas agregadas para gráficos
     * 
     * Query params:
     * - period: 24h|7d|30d (default: 24h)
     * 
     * Response:
     * {
     *   "success": true,
     *   "data": {
     *     "success_rate": 85.5,
     *     "failure_rate": 14.5,
     *     "total_attempts": 100,
     *     "avg_execution_time_ms": 1234,
     *     "timeline": [...]
     *   }
     * }
     */
    public function getStats(): void
    {
        $this->requireUserId();
        header('Content-Type: application/json');

        try {
            $db = \App\Database::getInstance();
            $period = $this->request->get('period', '24h') ?? '24h';

            // Security: use numeric values with prepared statements instead of interval interpolation
            $hoursMap = [
                '24h' => 24,
                '7d' => 168,
                '30d' => 720,
            ];

            $hours = $hoursMap[$period] ?? 24;

            // Estatísticas gerais
            $stmt = $db->prepare("
                SELECT 
                    COUNT(*) as total_attempts,
                    SUM(CASE WHEN action = 'refresh_success' THEN 1 ELSE 0 END) as successes,
                    SUM(CASE WHEN action = 'refresh_failed' THEN 1 ELSE 0 END) as failures,
                    AVG(execution_time_ms) as avg_execution_time_ms
                FROM token_refresh_audit
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
                AND action IN ('refresh_success', 'refresh_failed')
            ");
            $stmt->execute(['hours' => $hours]);

            $stats = $stmt->fetch(\PDO::FETCH_ASSOC);

            $successRate = $stats['total_attempts'] > 0
                ? round(($stats['successes'] / $stats['total_attempts']) * 100, 2)
                : 0;

            $failureRate = $stats['total_attempts'] > 0
                ? round(($stats['failures'] / $stats['total_attempts']) * 100, 2)
                : 0;

            // Timeline (últimas 24 horas agrupadas por hora)
            $stmt = $db->query("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
                    SUM(CASE WHEN action = 'refresh_success' THEN 1 ELSE 0 END) as successes,
                    SUM(CASE WHEN action = 'refresh_failed' THEN 1 ELSE 0 END) as failures
                FROM token_refresh_audit
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                AND action IN ('refresh_success', 'refresh_failed')
                GROUP BY hour
                ORDER BY hour ASC
            ");

            $timeline = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'success_rate' => $successRate,
                    'failure_rate' => $failureRate,
                    'total_attempts' => (int)$stats['total_attempts'],
                    'successes' => (int)$stats['successes'],
                    'failures' => (int)$stats['failures'],
                    'avg_execution_time_ms' => round((float)$stats['avg_execution_time_ms']),
                    'timeline' => $timeline,
                    'period' => $period,
                ],
            ]);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * @param array<string, mixed> $account
     */
    private function resolveApiValidationStatus(array $account): string
    {
        $error = strtolower(trim((string)($account['last_refresh_error'] ?? '')));
        $failureCount = (int)($account['refresh_failure_count'] ?? 0);

        if ($error === '' || $failureCount === 0) {
            return 'ok';
        }

        if (str_contains($error, 'ml_user_id_mismatch') || str_contains($error, 'mismatch')) {
            return 'identity_mismatch';
        }

        if (
            str_contains($error, '401')
            || str_contains($error, 'unauthorized')
            || str_contains($error, 'invalid token')
            || str_contains($error, 'invalid_token')
            || str_contains($error, 'invalid access token')
            || str_contains($error, 'missing_access_token')
        ) {
            return 'auth_error';
        }

        return 'api_error';
    }

    private function resolveDiagnosticLabel(string $status): string
    {
        return match ($status) {
            'ok' => 'API OK',
            'identity_mismatch' => 'Mismatch ML User',
            'auth_error' => 'Erro de Autenticação',
            'api_error' => 'Falha na Validação API',
            default => 'Sem Diagnóstico',
        };
    }

    /**
     * @param array<string, mixed> $account
     */
    private function buildDiagnosticMessage(array $account): ?string
    {
        $message = trim((string)($account['last_refresh_error'] ?? ''));
        if ($message === '') {
            return null;
        }

        return mb_substr($message, 0, 200);
    }
}
