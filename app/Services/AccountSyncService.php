<?php

namespace App\Services;

use App\Database;
use App\Traits\DatabaseMigrationTrait;
use PDO;
use App\Services\OrderService;
use App\Services\QuestionService;

/**
 * Service para orquestrar sincronização de contas do Mercado Livre
 * 
 * Gerencia o fluxo de sincronização após conexão de conta,
 * incluindo validação de token, sync de itens e atualização de status.
 */
class AccountSyncService
{
    use DatabaseMigrationTrait;

    private PDO $db;
    private LoggingService $logger;
    private ItemSyncService $itemSync;
    private MercadoLivreAuthService $authService;
    private bool $mlAccountsHasLastSyncedAt = false;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->logger = new LoggingService();
        $this->itemSync = new ItemSyncService();
        $this->authService = new MercadoLivreAuthService();
        $this->ensureSchema();
    }

    /**
     * Sincroniza uma conta recém-conectada ou existente
     * 
     * @param int $accountId ID da conta no banco
     * @return array Resultado da sincronização com status e estatísticas
     */
    public function syncAccount(int $accountId): array
    {
        $this->logger->info('ACCOUNT_SYNC_START', "Iniciando sincronização da conta {$accountId}");

        $result = [
            'success' => false,
            'account_id' => $accountId,
            'steps' => [],
            'stats' => [],
            'error' => null
        ];

        try {
            // 1. Validar que a conta existe
            $account = $this->getAccount($accountId);
            if (!$account) {
                throw new \Exception("Conta {$accountId} não encontrada");
            }
            $result['steps'][] = ['step' => 'validate_account', 'status' => 'success'];

            // 2. Garantir token válido
            $tokenValid = $this->ensureValidToken($accountId);
            if (!$tokenValid) {
                throw new \Exception("Token inválido ou expirado. Reconecte a conta.");
            }
            $result['steps'][] = ['step' => 'validate_token', 'status' => 'success'];

            // 3. Atualizar informações do usuário ML
            $userInfo = $this->refreshUserInfo($accountId);
            $result['steps'][] = [
                'step' => 'refresh_user_info',
                'status' => 'success',
                'data' => ['nickname' => $userInfo['nickname'] ?? 'N/A']
            ];

            // 4. Sincronizar itens
            $syncStats = $this->itemSync->syncForAccount($accountId);
            $result['stats'] = $syncStats;
            $result['steps'][] = [
                'step' => 'sync_items',
                'status' => 'success',
                'data' => $syncStats
            ];

            // 5. Sincronizar Pedidos (Recentes)
            try {
                $orderService = new OrderService($accountId);
                $orderStats = $orderService->syncOrders($accountId, 50);
                $result['steps'][] = ['step' => 'sync_orders', 'status' => 'success', 'data' => $orderStats];
            } catch (\Exception $e) {
                $this->logger->warning('ACCOUNT_SYNC_ORDERS_WARNING', "Falha parcial ao sincronizar pedidos", ['error' => $e->getMessage()]);
                $result['steps'][] = ['step' => 'sync_orders', 'status' => 'warning', 'error' => $e->getMessage()];
            }

            // 6. Sincronizar Perguntas (Recentes)
            try {
                $questionService = new QuestionService($accountId);
                $questionStats = $questionService->syncQuestions(50);
                $result['steps'][] = ['step' => 'sync_questions', 'status' => 'success', 'data' => $questionStats];
            } catch (\Exception $e) {
                $this->logger->warning('ACCOUNT_SYNC_QUESTIONS_WARNING', "Falha parcial ao sincronizar perguntas", ['error' => $e->getMessage()]);
                $result['steps'][] = ['step' => 'sync_questions', 'status' => 'warning', 'error' => $e->getMessage()];
            }

            // 8. Atualizar timestamp de última sincronização
            $this->updateLastSyncTime($accountId);
            $result['steps'][] = ['step' => 'update_sync_time', 'status' => 'success'];

            // 9. Atualizar status da conta para ativo
            $this->updateAccountStatus($accountId, 'active');

            $result['success'] = true;
            $this->logger->info('ACCOUNT_SYNC_COMPLETE', "Sincronização da conta {$accountId} concluída", $result);

        } catch (\Exception $e) {
            $result['error'] = $e->getMessage();
            $result['steps'][] = [
                'step' => $this->getCurrentStep($result['steps']),
                'status' => 'error',
                'error' => $e->getMessage()
            ];
            $this->logger->error('ACCOUNT_SYNC_ERROR', "Erro na sincronização da conta {$accountId}", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
        }

        return $result;
    }

    /**
     * Sincroniza todas as contas de um usuário
     * 
     * @param int $userId ID do usuário
     * @return array Resultados de cada conta
     */
    public function syncAllUserAccounts(int $userId): array
    {
        $accounts = $this->getUserAccounts($userId);
        $results = [];

        foreach ($accounts as $account) {
            $results[] = $this->syncAccount((int)$account['id']);
        }

        return [
            'total' => count($accounts),
            'success' => count(array_filter($results, fn($r) => $r['success'])),
            'failed' => count(array_filter($results, fn($r) => !$r['success'])),
            'details' => $results
        ];
    }

    /**
     * Verifica status de sincronização de uma conta
     * 
     * @param int $accountId ID da conta
     * @return array Status detalhado
     */
    public function getSyncStatus(int $accountId): array
    {
        $account = $this->getAccount($accountId);
        
        if (!$account) {
            return ['exists' => false];
        }

        $itemCount = $this->getItemCount($accountId);
        $lastSync = $account['last_synced_at'] ?? null;
        $tokenExpires = $account['token_expires_at'] ?? null;
        
        $tokenStatus = $this->getTokenStatus($tokenExpires);

        return [
            'exists' => true,
            'account_id' => $accountId,
            'nickname' => $account['nickname'] ?? 'N/A',
            'status' => $account['status'] ?? 'unknown',
            'token_status' => $tokenStatus,
            'token_expires_at' => $tokenExpires,
            'last_synced_at' => $lastSync,
            'items_count' => $itemCount,
            'needs_sync' => $this->needsSync($lastSync),
            'can_sync' => $tokenStatus['valid']
        ];
    }

    /**
     * Obtém dados da conta
     */
    private function getAccount(int $accountId): ?array
    {
        $lastSyncedSelect = $this->mlAccountsHasLastSyncedAt ? 'last_synced_at' : 'NULL AS last_synced_at';

        $stmt = $this->db->prepare("
            SELECT id, user_id, ml_user_id, nickname, email, status, 
                   token_expires_at, {$lastSyncedSelect}, created_at
            FROM ml_accounts 
            WHERE id = ?
        ");
        $stmt->execute([$accountId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Obtém todas as contas de um usuário
     */
    private function getUserAccounts(int $userId): array
    {
        $stmt = $this->db->prepare("
            SELECT id, ml_user_id, nickname, status 
            FROM ml_accounts 
            WHERE user_id = ?
        ");
        $stmt->execute([$userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Garante que o token está válido, renovando se necessário
     */
    private function ensureValidToken(int $accountId): bool
    {
        try {
            return $this->authService->ensureValidToken($accountId, 5);
        } catch (\Exception $e) {
            $this->logger->warning('TOKEN_VALIDATION_FAILED', "Falha ao validar token da conta {$accountId}", [
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }

    /**
     * Atualiza informações do usuário no ML
     */
    private function refreshUserInfo(int $accountId): array
    {
        $client = new MercadoLivreClient($accountId);
        $userInfo = $client->get('/users/me');

        if (isset($userInfo['id'])) {
            $stmt = $this->db->prepare("
                UPDATE ml_accounts 
                SET nickname = ?, email = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $userInfo['nickname'] ?? null,
                $userInfo['email'] ?? null,
                $accountId
            ]);
        }

        return $userInfo;
    }

    /**
     * Atualiza timestamp de última sincronização
     */
    private function updateLastSyncTime(int $accountId): void
    {
        if (!$this->mlAccountsHasLastSyncedAt) {
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE ml_accounts 
            SET last_synced_at = NOW(), updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$accountId]);
    }

    /**
     * Atualiza status da conta
     */
    private function updateAccountStatus(int $accountId, string $status): void
    {
        $stmt = $this->db->prepare("
            UPDATE ml_accounts 
            SET status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        $stmt->execute([$status, $accountId]);
    }

    /**
     * Conta itens sincronizados
     */
    private function getItemCount(int $accountId): int
    {
        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM ml_items WHERE account_id = ?");
            $stmt->execute([$accountId]);
            return (int)$stmt->fetchColumn();
        } catch (\Throwable $e) {
            $this->logger->warning('ITEM_COUNT_FAILED', "Falha ao contar itens da conta {$accountId}", [
                'error' => $e->getMessage()
            ]);
            return 0;
        }
    }

    /**
     * Verifica status do token
     */
    private function getTokenStatus(?string $expiresAt): array
    {
        if (!$expiresAt) {
            return ['valid' => false, 'status' => 'unknown', 'message' => 'Data de expiração desconhecida'];
        }

        $timestamp = strtotime($expiresAt);
        if ($timestamp === false) {
            return ['valid' => false, 'status' => 'unknown', 'message' => 'Data de expiração inválida'];
        }

        $now = time();
        $diff = $timestamp - $now;

        if ($diff < 0) {
            return ['valid' => false, 'status' => 'expired', 'message' => 'Token expirado'];
        }

        if ($diff < 3600) { // Menos de 1 hora
            return ['valid' => true, 'status' => 'expiring_soon', 'message' => 'Token expirando em breve'];
        }

        return ['valid' => true, 'status' => 'valid', 'message' => 'Token válido'];
    }

    /**
     * Verifica se precisa sincronizar
     */
    private function needsSync(?string $lastSync): bool
    {
        if (!$lastSync) {
            return true;
        }

        $lastSyncTime = strtotime($lastSync);
        $hoursSinceSync = (time() - $lastSyncTime) / 3600;

        // Sincronizar se passou mais de 6 horas
        return $hoursSinceSync > 6;
    }

    /**
     * Identifica o step atual baseado nos completados
     */
    private function getCurrentStep(array $steps): string
    {
        $allSteps = ['validate_account', 'validate_token', 'refresh_user_info', 'sync_items', 'update_sync_time'];
        $completed = array_column($steps, 'step');
        
        foreach ($allSteps as $step) {
            if (!in_array($step, $completed)) {
                return $step;
            }
        }
        
        return 'unknown';
    }

    private function ensureSchema(): void
    {
        try {
            $this->mlAccountsHasLastSyncedAt = $this->columnExists($this->db, 'ml_accounts', 'last_synced_at');
            if (!$this->mlAccountsHasLastSyncedAt) {
                $this->addColumnIfMissing($this->db, 'ml_accounts', 'last_synced_at', 'DATETIME NULL');
                $this->mlAccountsHasLastSyncedAt = $this->columnExists($this->db, 'ml_accounts', 'last_synced_at');
            }
        } catch (\Throwable $e) {
            $this->logger->warning('ACCOUNT_SYNC_SCHEMA_WARNING', 'Falha ao validar/atualizar schema de ml_accounts', [
                'error' => $e->getMessage(),
            ]);
        }
    }
}
