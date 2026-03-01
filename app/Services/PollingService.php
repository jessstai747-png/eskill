<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * Orquestra polling de dados do Mercado Livre (pedidos, anúncios) e agenda jobs.
 * Utiliza configurações de polling em config/app.php e garante tokens válidos antes de despachar jobs.
 */
class PollingService
{
    private PDO $db;
    private array $config;
    private JobService $jobs;
    private MercadoLivreAuthService $auth;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->config = \App\Core\Config::getInstance()->all();
        $this->jobs = new JobService();
        $this->auth = new MercadoLivreAuthService();
    }

    public function isPollingEnabled(): bool
    {
        return filter_var($this->config['polling']['enabled'] ?? true, FILTER_VALIDATE_BOOLEAN);
    }

    public function getPollingInterval(): int
    {
        return (int)($this->config['polling']['interval_minutes'] ?? 30);
    }

    /**
     * Agenda sincronização de pedidos para todas as contas ativas.
     */
    public function pollOrders(int $limitPerAccount = 100): array
    {
        if (!$this->isPollingEnabled()) {
            return [
                'success' => false,
                'message' => 'Polling desabilitado nas configurações',
                'total_accounts' => 0,
                'jobs_created' => 0,
                'errors' => [],
            ];
        }

        $accounts = $this->fetchAccounts(['active']);
        if (empty($accounts)) {
            return [
                'success' => true,
                'message' => 'Nenhuma conta ativa encontrada',
                'total_accounts' => 0,
                'jobs_created' => 0,
                'errors' => [],
            ];
        }

        $jobsCreated = 0;
        $errors = [];

        foreach ($accounts as $account) {
            $accountId = (int)$account['id'];

            if (!$this->ensureFreshToken($accountId, $account['token_expires_at'] ?? null)) {
                $errors[] = [
                    'account_id' => $accountId,
                    'nickname' => $account['nickname'] ?? null,
                    'error' => 'token_invalid',
                ];
                continue;
            }

            try {
                $this->jobs->dispatch('sync_orders', [
                    'account_id' => $accountId,
                    'limit' => $limitPerAccount,
                ]);
                $jobsCreated++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'account_id' => $accountId,
                    'nickname' => $account['nickname'] ?? null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => empty($errors),
            'total_accounts' => count($accounts),
            'jobs_created' => $jobsCreated,
            'errors' => $errors,
        ];
    }

    /**
     * Agenda sincronização de anúncios para todas as contas ativas.
     */
    public function pollItems(int $limitPerAccount = 100): array
    {
        if (!$this->isPollingEnabled()) {
            return [
                'success' => false,
                'message' => 'Polling desabilitado nas configurações',
                'total_accounts' => 0,
                'jobs_created' => 0,
                'errors' => [],
            ];
        }

        $accounts = $this->fetchAccounts(['active']);
        if (empty($accounts)) {
            return [
                'success' => true,
                'message' => 'Nenhuma conta ativa encontrada',
                'total_accounts' => 0,
                'jobs_created' => 0,
                'errors' => [],
            ];
        }

        $jobsCreated = 0;
        $errors = [];

        foreach ($accounts as $account) {
            $accountId = (int)$account['id'];

            if (!$this->ensureFreshToken($accountId, $account['token_expires_at'] ?? null)) {
                $errors[] = [
                    'account_id' => $accountId,
                    'nickname' => $account['nickname'] ?? null,
                    'error' => 'token_invalid',
                ];
                continue;
            }

            try {
                $this->jobs->dispatch('sync_items', [
                    'account_id' => $accountId,
                    'limit' => $limitPerAccount,
                ]);
                $jobsCreated++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'account_id' => $accountId,
                    'nickname' => $account['nickname'] ?? null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => empty($errors),
            'total_accounts' => count($accounts),
            'jobs_created' => $jobsCreated,
            'errors' => $errors,
        ];
    }

    /**
     * Executa polling de pedidos e anúncios em sequência.
     */
    public function pollAll(): array
    {
        $orders = $this->pollOrders();
        $items = $this->pollItems();
        $questions = $this->pollQuestions();

        return [
            'orders' => $orders,
            'items' => $items,
            'questions' => $questions,
        ];
    }

    /**
     * Agenda sincronização de perguntas para todas as contas ativas.
     */
    public function pollQuestions(int $limitPerAccount = 50): array
    {
        if (!$this->isPollingEnabled()) {
            return [
                'success' => false,
                'message' => 'Polling desabilitado nas configurações',
                'total_accounts' => 0,
                'jobs_created' => 0,
                'errors' => [],
            ];
        }

        $accounts = $this->fetchAccounts(['active']);
        if (empty($accounts)) {
            return [
                'success' => true,
                'message' => 'Nenhuma conta ativa encontrada',
                'total_accounts' => 0,
                'jobs_created' => 0,
                'errors' => [],
            ];
        }

        $jobsCreated = 0;
        $errors = [];

        foreach ($accounts as $account) {
            $accountId = (int)$account['id'];

            if (!$this->ensureFreshToken($accountId, $account['token_expires_at'] ?? null)) {
                $errors[] = [
                    'account_id' => $accountId,
                    'nickname' => $account['nickname'] ?? null,
                    'error' => 'token_invalid',
                ];
                continue;
            }

            try {
                $this->jobs->dispatch('sync_questions', [
                    'account_id' => $accountId,
                    'limit' => $limitPerAccount,
                ]);
                $jobsCreated++;
            } catch (\Throwable $e) {
                $errors[] = [
                    'account_id' => $accountId,
                    'nickname' => $account['nickname'] ?? null,
                    'error' => $e->getMessage(),
                ];
            }
        }

        return [
            'success' => empty($errors),
            'total_accounts' => count($accounts),
            'jobs_created' => $jobsCreated,
            'errors' => $errors,
        ];
    }

    private function fetchAccounts(array $statuses): array
    {
        $placeholders = implode(',', array_fill(0, count($statuses), '?'));
        $stmt = $this->db->prepare("
            SELECT id, nickname, token_expires_at, status
            FROM ml_accounts
            WHERE status IN ($placeholders)
            ORDER BY id
        ");
        $stmt->execute($statuses);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return is_array($rows) ? $rows : [];
    }

    private function ensureFreshToken(int $accountId, ?string $expiresAt): bool
    {
        // Sem data de expiração, tenta seguir mesmo assim.
        if (empty($expiresAt)) {
            return true;
        }

        $secondsLeft = strtotime($expiresAt) - time();
        // Se expira em menos de 30 minutos, tentar refresh.
        if ($secondsLeft <= 1800) {
            return $this->auth->ensureValidToken($accountId, 60);
        }

        return true;
    }
}
