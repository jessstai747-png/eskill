<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * CloneHealthMonitorService
 * 
 * Monitoramento de saúde do sistema de clonagem.
 * Métricas em tempo real, alertas e diagnósticos.
 */
class CloneHealthMonitorService
{
    /**
     * Thresholds para alertas.
     *
     * @var array<string, int>
     */
    private const THRESHOLDS = [
        'job_stuck_minutes' => 30,
        'job_activity_stale_minutes' => 120,
        'error_rate_warning' => 20,
        'error_rate_critical' => 50,
        'queue_size_warning' => 100,
        'queue_size_critical' => 500,
        'worker_heartbeat_minutes' => 10,
        'api_error_window_minutes' => 5,
    ];

    private const DISK_SPACE_WARNING_GB = 5.0;
    private const DISK_SPACE_CRITICAL_GB = 1.0;

    /**
     * Penalidades por status de check.
     *
     * @var array<string, int>
     */
    private const SCORE_PENALTY_BY_STATUS = [
        'ok' => 0,
        'warning' => 10,
        'critical' => 25,
        'unknown' => 30,
    ];

    private ?PDO $db;
    private int $accountId;
    private ?string $dbError = null;
    private ?MercadoLivreClient $mlClient = null;

    public function __construct(int $accountId = 0, ?PDO $db = null, ?MercadoLivreClient $mlClient = null)
    {
        $this->accountId = $accountId;
        $this->db = $db;
        $this->mlClient = $mlClient;

        if ($this->db === null) {
            try {
                $this->db = Database::getInstance();
            } catch (\Throwable $e) {
                $this->db = null;
                $this->dbError = $e->getMessage();
                log_warning('CloneHealthMonitorService: DB indisponível', [
                    'error' => $this->dbError,
                    'account_id' => $this->accountId,
                ]);
            }
        }
    }

    /**
     * Obtém status geral de saúde do sistema
     */
    public function getSystemHealth(): array
    {
        $health = [
            'status' => 'healthy',
            'score' => 100,
            'timestamp' => date('c'),
            'checks' => [],
            'issues' => [],
            'metrics' => []
        ];

        // Se DB estiver indisponível, degradar imediatamente e evitar queries.
        if ($this->db === null) {
            $health['checks']['database'] = [
                'status' => 'critical',
                'value' => 'offline',
                'message' => 'Banco de dados indisponível — não foi possível executar checks',
                'error' => $this->dbError,
            ];
            $health['issues'] = $this->buildIssuesFromChecks($health['checks']);
            $health['status'] = 'critical';
            $health['score'] = 0;

            return $health;
        }

        // Check 1: Jobs ativos
        $jobsCheck = $this->checkActiveJobs();
        $health['checks']['active_jobs'] = $jobsCheck;

        // Check 2: Jobs travados
        $stuckCheck = $this->checkStuckJobs();
        $health['checks']['stuck_jobs'] = $stuckCheck;

        // Check 3: Taxa de erro
        $errorCheck = $this->checkErrorRate();
        $health['checks']['error_rate'] = $errorCheck;

        // Check 4: Fila pendente
        $queueCheck = $this->checkQueueSize();
        $health['checks']['queue_size'] = $queueCheck;

        // Check 5: Atividade recente do pipeline (sem depender de tabela de logs)
        $activityCheck = $this->checkRecentJobActivity();
        $health['checks']['recent_activity'] = $activityCheck;

        // Check 6: Workers ativos (se tabela existir)
        $workerCheck = $this->checkWorkers();
        $health['checks']['workers'] = $workerCheck;

        // Check 7: Conectividade API ML (se tabela existir)
        $apiCheck = $this->checkApiConnectivity();
        $health['checks']['api_connectivity'] = $apiCheck;

        // Check 8: Espaço em disco
        $diskCheck = $this->checkDiskSpace();
        $health['checks']['disk_space'] = $diskCheck;

        $health['issues'] = $this->buildIssuesFromChecks($health['checks']);

        // Determinar status geral
        $statuses = array_column($health['checks'], 'status');
        $health['status'] = $this->determineOverallStatus($statuses);
        $health['score'] = $this->calculateHealthScore($statuses);

        // Métricas agregadas
        $health['metrics'] = $this->getQuickMetrics();

        return $health;
    }

    /**
     * Verifica jobs ativos
     */
    private function checkActiveJobs(): array
    {
        if ($this->db === null) {
            return [
                'status' => 'unknown',
                'value' => null,
                'count' => null,
                'message' => 'Check indisponível (DB offline)'
            ];
        }

        try {
            $params = [];
            $accountFilter = '';

            if ($this->accountId > 0) {
                $accountFilter = 'AND target_account_id = :account_id';
                $params['account_id'] = $this->accountId;
            }

            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM catalog_clone_jobs
                WHERE status IN ('processing', 'queued')
                {$accountFilter}
            ");
            $stmt->execute($params);
            $count = (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            log_error('CloneHealthMonitorService: falha ao verificar jobs ativos', [
                'error' => $e->getMessage(),
                'account_id' => $this->accountId,
            ]);
            return [
                'status' => 'critical',
                'value' => 'N/A',
                'count' => null,
                'message' => 'Falha ao verificar jobs ativos',
                'error' => $e->getMessage(),
            ];
        }

        return [
            'status' => 'ok',
            'value' => $count,
            'count' => $count,
            'message' => "$count jobs ativos no momento"
        ];
    }

    /**
     * Verifica jobs travados
     */
    private function checkStuckJobs(): array
    {
        if ($this->db === null) {
            return [
                'status' => 'unknown',
                'value' => null,
                'count' => null,
                'message' => 'Check indisponível (DB offline)'
            ];
        }

        try {
            $minutes = self::THRESHOLDS['job_stuck_minutes'];
            $cutoff = $this->dateTimeToSql((new \DateTimeImmutable('now'))->modify('-' . $minutes . ' minutes'));

            $params = ['cutoff' => $cutoff];
            $accountFilter = '';

            if ($this->accountId > 0) {
                $accountFilter = 'AND target_account_id = :account_id';
                $params['account_id'] = $this->accountId;
            }

            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM catalog_clone_jobs
                WHERE status = 'processing'
                AND updated_at < :cutoff
                {$accountFilter}
            ");
            $stmt->execute($params);
            $count = (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            log_error('CloneHealthMonitorService: falha ao verificar jobs travados', [
                'error' => $e->getMessage(),
                'account_id' => $this->accountId,
            ]);
            return [
                'status' => 'critical',
                'value' => 'N/A',
                'count' => null,
                'message' => 'Falha ao verificar jobs travados',
                'error' => $e->getMessage(),
            ];
        }

        if ($count > 0) {
            return [
                'status' => 'critical',
                'value' => $count,
                'count' => $count,
                'message' => "$count jobs travados há mais de {$minutes} minutos"
            ];
        }

        return [
            'status' => 'ok',
            'value' => 0,
            'count' => 0,
            'message' => 'Nenhum job travado'
        ];
    }

    /**
     * Verifica taxa de erro
     */
    private function checkErrorRate(): array
    {
        if ($this->db === null) {
            return [
                'status' => 'unknown',
                'value' => null,
                'rate' => null,
                'message' => 'Check indisponível (DB offline)'
            ];
        }

        try {
            $cutoff = $this->dateTimeToSql((new \DateTimeImmutable('now'))->modify('-1 hour'));
            $params = ['cutoff' => $cutoff];
            $accountFilter = '';

            if ($this->accountId > 0) {
                $accountFilter = 'AND target_account_id = :account_id';
                $params['account_id'] = $this->accountId;
            }

            $stmt = $this->db->prepare("
                SELECT 
                    SUM(successful_items) as success,
                    SUM(failed_items) as failed
                FROM catalog_clone_jobs
                WHERE created_at >= :cutoff
                {$accountFilter}
            ");
            $stmt->execute($params);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            log_error('CloneHealthMonitorService: falha ao verificar taxa de erro', [
                'error' => $e->getMessage(),
                'account_id' => $this->accountId,
            ]);
            return [
                'status' => 'critical',
                'value' => 'N/A',
                'rate' => null,
                'message' => 'Falha ao verificar taxa de erro',
                'error' => $e->getMessage(),
            ];
        }

        $success = (int) ($row['success'] ?? 0);
        $failed = (int) ($row['failed'] ?? 0);
        $total = $success + $failed;

        if ($total === 0) {
            return [
                'status' => 'ok',
                'value' => '0%',
                'rate' => 0,
                'message' => 'Sem processamento na última hora'
            ];
        }

        $errorRate = ($failed / $total) * 100;

        if ($errorRate >= self::THRESHOLDS['error_rate_critical']) {
            return [
                'status' => 'critical',
                'value' => round($errorRate, 1) . '%',
                'rate' => round($errorRate, 2),
                'message' => "Taxa de erro crítica: " . round($errorRate, 1) . "%"
            ];
        }

        if ($errorRate >= self::THRESHOLDS['error_rate_warning']) {
            return [
                'status' => 'warning',
                'value' => round($errorRate, 1) . '%',
                'rate' => round($errorRate, 2),
                'message' => "Taxa de erro elevada: " . round($errorRate, 1) . "%"
            ];
        }

        return [
            'status' => 'ok',
            'value' => round($errorRate, 1) . '%',
            'rate' => round($errorRate, 2),
            'message' => "Taxa de erro: " . round($errorRate, 1) . "%"
        ];
    }

    /**
     * Verifica tamanho da fila
     */
    private function checkQueueSize(): array
    {
        if ($this->db === null) {
            return [
                'status' => 'unknown',
                'value' => null,
                'count' => null,
                'message' => 'Check indisponível (DB offline)'
            ];
        }

        try {
            $params = [];
            $joinAccount = '';
            $accountFilter = '';

            if ($this->accountId > 0) {
                $joinAccount = 'JOIN catalog_clone_jobs ccj ON ccj.job_id = cji.job_id';
                $accountFilter = 'AND ccj.target_account_id = :account_id';
                $params['account_id'] = $this->accountId;
            }

            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM catalog_clone_job_items cji
                {$joinAccount}
                WHERE cji.status = 'pending'
                {$accountFilter}
            ");
            $stmt->execute($params);
            $count = (int) $stmt->fetchColumn();
        } catch (\Throwable $e) {
            log_error('CloneHealthMonitorService: falha ao verificar tamanho da fila', [
                'error' => $e->getMessage(),
                'account_id' => $this->accountId,
            ]);
            return [
                'status' => 'critical',
                'value' => 'N/A',
                'count' => null,
                'message' => 'Falha ao verificar tamanho da fila',
                'error' => $e->getMessage(),
            ];
        }

        if ($count >= self::THRESHOLDS['queue_size_critical']) {
            return [
                'status' => 'critical',
                'value' => $count,
                'count' => $count,
                'message' => "Fila muito grande: $count itens pendentes"
            ];
        }

        if ($count >= self::THRESHOLDS['queue_size_warning']) {
            return [
                'status' => 'warning',
                'value' => $count,
                'count' => $count,
                'message' => "Fila elevada: $count itens pendentes"
            ];
        }

        return [
            'status' => 'ok',
            'value' => $count,
            'count' => $count,
            'message' => "$count itens na fila"
        ];
    }

    /**
     * Verifica workers ativos
     */
    private function checkWorkers(): array
    {
        // Verificar último heartbeat de workers
        try {
            if ($this->db === null) {
                return [
                    'status' => 'unknown',
                    'value' => null,
                    'active' => null,
                    'message' => 'Check indisponível (DB offline)'
                ];
            }

            $minutes = self::THRESHOLDS['worker_heartbeat_minutes'];
            $cutoff = $this->dateTimeToSql((new \DateTimeImmutable('now'))->modify('-' . $minutes . ' minutes'));

            $params = ['cutoff' => $cutoff];

            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM worker_execution_logs
                WHERE worker_name LIKE 'clone%'
                AND executed_at >= :cutoff
            ");
            $stmt->execute($params);
            $count = (int) $stmt->fetchColumn();

            if ($count === 0) {
                return [
                    'status' => 'warning',
                    'value' => 0,
                    'active' => 0,
                    'message' => 'Nenhum worker executado nos últimos 10 minutos'
                ];
            }

            return [
                'status' => 'ok',
                'value' => $count,
                'active' => $count,
                'message' => "$count execuções de workers recentes"
            ];
        } catch (\Throwable $e) {
            log_debug('CloneHealthMonitorService: checkWorkers indisponível', [
                'error' => $e->getMessage(),
                'account_id' => $this->accountId,
            ]);
            return [
                'status' => 'ok',
                'value' => 'N/A',
                'active' => null,
                'message' => 'Verificação de workers não disponível'
            ];
        }
    }

    /**
     * Verifica conectividade com API ML.
     *
     * Quando um MercadoLivreClient está disponível, executa diagnose() real
     * (verifica token, API pública, autenticação, itens).
     * Caso contrário, faz fallback para contagem de erros no clone_sync_logs.
     */
    private function checkApiConnectivity(): array
    {
        // Preferência: diagnose real via MercadoLivreClient
        if ($this->mlClient !== null) {
            return $this->checkApiConnectivityViaClient();
        }

        // Fallback: contagem de erros em clone_sync_logs
        return $this->checkApiConnectivityViaLogs();
    }

    /**
     * Diagnóstico real da API do Mercado Livre via MercadoLivreClient::diagnose().
     */
    private function checkApiConnectivityViaClient(): array
    {
        try {
            /** @var MercadoLivreClient $client */
            $client = $this->mlClient;
            $diag = $client->diagnose();

            $connected = (bool) ($diag['connected'] ?? false);
            $apiAccessible = (bool) ($diag['api_accessible'] ?? false);
            $tokenStatus = (string) ($diag['token_status'] ?? 'unknown');
            $sellerId = $diag['seller_id'] ?? null;
            $itemsCount = (int) ($diag['items_count'] ?? 0);
            $error = $diag['error'] ?? null;

            // Nível 1: API pública inacessível → critical
            if (!$apiAccessible) {
                return [
                    'status' => 'critical',
                    'value' => 'API inacessível',
                    'errors' => null,
                    'connected' => false,
                    'api_accessible' => false,
                    'token_status' => $tokenStatus,
                    'seller_id' => null,
                    'items_count' => 0,
                    'message' => 'API do Mercado Livre inacessível',
                    'error' => $error,
                ];
            }

            // Nível 2: token inválido ou ausente → warning
            if (!$connected) {
                $reason = match ($tokenStatus) {
                    'missing', 'unknown' => 'Token ausente ou não configurado',
                    'invalid' => 'Token inválido — reautentique a conta',
                    'error' => 'Erro ao validar token: ' . ($error ?? 'desconhecido'),
                    default => 'Não conectado ao Mercado Livre',
                };

                return [
                    'status' => 'warning',
                    'value' => $tokenStatus,
                    'errors' => null,
                    'connected' => false,
                    'api_accessible' => true,
                    'token_status' => $tokenStatus,
                    'seller_id' => null,
                    'items_count' => 0,
                    'message' => $reason,
                    'error' => $error,
                ];
            }

            // Nível 3: tudo OK
            return [
                'status' => 'ok',
                'value' => 'conectado',
                'errors' => 0,
                'connected' => true,
                'api_accessible' => true,
                'token_status' => 'valid',
                'seller_id' => $sellerId,
                'items_count' => $itemsCount,
                'message' => 'Conectividade API OK — seller ' . ($sellerId ?? '?') . ' (' . $itemsCount . ' itens)',
            ];
        } catch (\Throwable $e) {
            log_warning('CloneHealthMonitorService: diagnose ML falhou', [
                'error' => $e->getMessage(),
                'account_id' => $this->accountId,
            ]);

            return [
                'status' => 'warning',
                'value' => 'erro',
                'errors' => null,
                'connected' => false,
                'api_accessible' => false,
                'token_status' => 'unknown',
                'seller_id' => null,
                'items_count' => 0,
                'message' => 'Falha ao diagnosticar API: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Fallback: verifica erros de API recentes via clone_sync_logs.
     */
    private function checkApiConnectivityViaLogs(): array
    {
        try {
            if ($this->db === null) {
                return [
                    'status' => 'unknown',
                    'value' => null,
                    'errors' => null,
                    'message' => 'Check indisponível (DB offline)'
                ];
            }

            $minutes = self::THRESHOLDS['api_error_window_minutes'];
            $cutoff = $this->dateTimeToSql((new \DateTimeImmutable('now'))->modify('-' . $minutes . ' minutes'));

            $params = ['cutoff' => $cutoff];
            $accountFilter = '';

            if ($this->accountId > 0) {
                $accountFilter = 'AND account_id = :account_id';
                $params['account_id'] = $this->accountId;
            }

            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM clone_sync_logs
                WHERE sync_type = 'api_error'
                AND created_at >= :cutoff
                {$accountFilter}
            ");
            $stmt->execute($params);
            $errorCount = (int) $stmt->fetchColumn();

            if ($errorCount >= 10) {
                return [
                    'status' => 'critical',
                    'value' => $errorCount,
                    'errors' => $errorCount,
                    'message' => "Muitos erros de API: $errorCount nos últimos 5 min"
                ];
            }

            if ($errorCount >= 3) {
                return [
                    'status' => 'warning',
                    'value' => $errorCount,
                    'errors' => $errorCount,
                    'message' => "Alguns erros de API: $errorCount nos últimos 5 min"
                ];
            }

            return [
                'status' => 'ok',
                'value' => $errorCount,
                'errors' => $errorCount,
                'message' => 'Conectividade API OK'
            ];
        } catch (\Throwable $e) {
            log_debug('CloneHealthMonitorService: checkApiConnectivityViaLogs indisponível', [
                'error' => $e->getMessage(),
                'account_id' => $this->accountId,
            ]);
            return [
                'status' => 'ok',
                'value' => 'N/A',
                'errors' => null,
                'message' => 'Verificação de API não disponível'
            ];
        }
    }

    /**
     * Verifica atividade recente do pipeline via catalog_clone_jobs.
     */
    private function checkRecentJobActivity(): array
    {
        if ($this->db === null) {
            return [
                'status' => 'unknown',
                'value' => null,
                'minutes_since_last_update' => null,
                'message' => 'Check indisponível (DB offline)'
            ];
        }

        try {
            $params = [];
            $accountFilter = '';

            if ($this->accountId > 0) {
                $accountFilter = 'AND target_account_id = :account_id';
                $params['account_id'] = $this->accountId;
            }

            $stmt = $this->db->prepare("
                SELECT MAX(updated_at) as last_update
                FROM catalog_clone_jobs
                WHERE status IN ('completed', 'failed', 'processing')
                {$accountFilter}
            ");
            $stmt->execute($params);
            $lastUpdate = (string) ($stmt->fetchColumn() ?: '');
        } catch (\Throwable $e) {
            log_error('CloneHealthMonitorService: falha ao verificar atividade recente', [
                'error' => $e->getMessage(),
                'account_id' => $this->accountId,
            ]);
            return [
                'status' => 'unknown',
                'value' => 'N/A',
                'minutes_since_last_update' => null,
                'message' => 'Falha ao verificar atividade recente',
                'error' => $e->getMessage(),
            ];
        }

        if ($lastUpdate === '') {
            return [
                'status' => 'ok',
                'value' => 'N/A',
                'minutes_since_last_update' => null,
                'message' => 'Sem atividade recente detectável (nenhum job)' 
            ];
        }

        $lastTs = strtotime($lastUpdate);
        if ($lastTs === false) {
            return [
                'status' => 'unknown',
                'value' => null,
                'minutes_since_last_update' => null,
                'message' => 'Não foi possível interpretar last_update'
            ];
        }

        $minutes = (int) floor((time() - $lastTs) / 60);

        if ($minutes >= self::THRESHOLDS['job_activity_stale_minutes']) {
            return [
                'status' => 'warning',
                'value' => $minutes . ' min',
                'minutes_since_last_update' => $minutes,
                'message' => 'Possível inatividade do pipeline: sem updates há ' . $minutes . ' minutos'
            ];
        }

        return [
            'status' => 'ok',
            'value' => $minutes . ' min',
            'minutes_since_last_update' => $minutes,
            'message' => 'Atividade recente OK (último update há ' . $minutes . ' minutos)'
        ];
    }

    /**
     * Verifica espaço em disco na pasta storage/.
     */
    private function checkDiskSpace(): array
    {
        $storagePath = dirname(__DIR__, 2) . '/storage';
        $free = @disk_free_space($storagePath);

        if ($free === false) {
            return [
                'status' => 'unknown',
                'value' => null,
                'free_gb' => null,
                'message' => 'Não foi possível obter espaço livre em disco'
            ];
        }

        $freeGb = $free / 1073741824; // 1024^3

        if ($freeGb < self::DISK_SPACE_CRITICAL_GB) {
            return [
                'status' => 'critical',
                'value' => number_format($freeGb, 2) . ' GB',
                'free_gb' => round($freeGb, 2),
                'message' => 'Espaço em disco crítico: ' . number_format($freeGb, 2) . ' GB livres'
            ];
        }

        if ($freeGb < self::DISK_SPACE_WARNING_GB) {
            return [
                'status' => 'warning',
                'value' => number_format($freeGb, 2) . ' GB',
                'free_gb' => round($freeGb, 2),
                'message' => 'Espaço em disco baixo: ' . number_format($freeGb, 2) . ' GB livres'
            ];
        }

        return [
            'status' => 'ok',
            'value' => number_format($freeGb, 2) . ' GB',
            'free_gb' => round($freeGb, 2),
            'message' => 'Espaço em disco OK'
        ];
    }

    /**
     * Métricas rápidas
     */
    private function getQuickMetrics(): array
    {
        if ($this->db === null) {
            return [
                'last_24h' => ['jobs' => 0, 'items' => 0, 'success' => 0, 'failed' => 0],
                'last_hour' => ['jobs' => 0, 'items' => 0],
                'throughput' => ['items_per_hour' => 0, 'items_per_day' => 0],
                'note' => 'DB offline'
            ];
        }

        $cutoff24h = $this->dateTimeToSql((new \DateTimeImmutable('now'))->modify('-24 hours'));
        $cutoff1h = $this->dateTimeToSql((new \DateTimeImmutable('now'))->modify('-1 hour'));

        $params24h = ['cutoff' => $cutoff24h];
        $params1h = ['cutoff' => $cutoff1h];
        $accountFilter = '';

        if ($this->accountId > 0) {
            $accountFilter = 'AND target_account_id = :account_id';
            $params24h['account_id'] = $this->accountId;
            $params1h['account_id'] = $this->accountId;
        }

        try {
            // Últimas 24h
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as jobs_24h,
                    SUM(total_items) as items_24h,
                    SUM(successful_items) as success_24h,
                    SUM(failed_items) as failed_24h
                FROM catalog_clone_jobs
                WHERE created_at >= :cutoff
                {$accountFilter}
            ");
            $stmt->execute($params24h);
            $last24h = $stmt->fetch(PDO::FETCH_ASSOC);

            // Última hora
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as jobs_1h,
                    SUM(total_items) as items_1h
                FROM catalog_clone_jobs
                WHERE created_at >= :cutoff
                {$accountFilter}
            ");
            $stmt->execute($params1h);
            $lastHour = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            log_debug('CloneHealthMonitorService: getQuickMetrics falhou', [
                'error' => $e->getMessage(),
                'account_id' => $this->accountId,
            ]);

            return [
                'last_24h' => ['jobs' => 0, 'items' => 0, 'success' => 0, 'failed' => 0],
                'last_hour' => ['jobs' => 0, 'items' => 0],
                'throughput' => ['items_per_hour' => 0, 'items_per_day' => 0],
                'error' => $e->getMessage(),
            ];
        }

        return [
            'last_24h' => [
                'jobs' => (int) ($last24h['jobs_24h'] ?? 0),
                'items' => (int) ($last24h['items_24h'] ?? 0),
                'success' => (int) ($last24h['success_24h'] ?? 0),
                'failed' => (int) ($last24h['failed_24h'] ?? 0)
            ],
            'last_hour' => [
                'jobs' => (int) ($lastHour['jobs_1h'] ?? 0),
                'items' => (int) ($lastHour['items_1h'] ?? 0)
            ],
            'throughput' => [
                'items_per_hour' => (int) ($lastHour['items_1h'] ?? 0),
                'items_per_day' => (int) ($last24h['items_24h'] ?? 0)
            ]
        ];
    }

    /**
     * Diagnóstico detalhado
     */
    public function runDiagnostics(): array
    {
        $diagnostics = [
            'timestamp' => date('c'),
            'database' => $this->diagnoseDatabase(),
            'storage' => $this->diagnoseStorage(),
            'jobs' => $this->diagnoseJobs(),
            'performance' => $this->diagnosePerformance()
        ];

        return $diagnostics;
    }

    /**
     * Diagnóstico de banco de dados
     */
    private function diagnoseDatabase(): array
    {
        $tables = [
            'cloned_items',
            'catalog_clone_jobs',
            'catalog_clone_job_items',
            'clone_templates',
            'clone_item_metrics',
            'clone_sync_logs'
        ];

        $results = [];

        foreach ($tables as $table) {
            if ($this->db === null) {
                $results[$table] = ['exists' => false, 'error' => 'DB offline'];
                continue;
            }

            try {
                $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
                $stmt = $this->db->query("SELECT COUNT(*) FROM `{$safeTable}`");
                $count = (int) $stmt->fetchColumn();
                $results[$table] = ['exists' => true, 'count' => $count];
            } catch (\Throwable $e) {
                $results[$table] = ['exists' => false, 'error' => $e->getMessage()];
            }
        }

        return $results;
    }

    /**
     * Diagnóstico de storage
     */
    private function diagnoseStorage(): array
    {
        $storagePath = dirname(__DIR__, 2) . '/storage';

        $paths = [
            'logs' => $storagePath . '/logs',
            'cache' => $storagePath . '/cache',
            'exports' => $storagePath . '/exports/clone'
        ];

        $results = [];

        foreach ($paths as $name => $path) {
            $results[$name] = [
                'path' => $path,
                'exists' => is_dir($path),
                'writable' => is_writable($path),
                'size_mb' => is_dir($path) ? round($this->getDirectorySize($path) / 1048576, 2) : 0
            ];
        }

        return $results;
    }

    /**
     * Diagnóstico de jobs
     */
    private function diagnoseJobs(): array
    {
        if ($this->db === null) {
            return [
                'by_status' => [],
                'recent' => [],
                'error' => 'DB offline',
            ];
        }

        try {
            $params = [];
            $accountFilter = '';

            if ($this->accountId > 0) {
                $accountFilter = 'WHERE target_account_id = :account_id';
                $params['account_id'] = $this->accountId;
            }

            $stmt = $this->db->prepare("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM catalog_clone_jobs
                {$accountFilter}
                GROUP BY status
            ");
            $stmt->execute($params);

            $byStatus = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $byStatus[(string) $row['status']] = (int) $row['count'];
            }

            $stmt = $this->db->prepare("
                SELECT job_id, status, total_items, successful_items, failed_items, created_at
                FROM catalog_clone_jobs
                {$accountFilter}
                ORDER BY created_at DESC
                LIMIT 5
            ");
            $stmt->execute($params);
            $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'by_status' => $byStatus,
                'recent' => $recent
            ];
        } catch (\Throwable $e) {
            return [
                'by_status' => [],
                'recent' => [],
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Diagnóstico de performance
     */
    private function diagnosePerformance(): array
    {
        if ($this->db === null) {
            return [
                'avg_job_duration_seconds' => 0.0,
                'avg_items_per_job' => 0.0,
                'items_per_second' => 0.0,
                'error' => 'DB offline',
            ];
        }

        try {
            // Tempo médio de processamento
            $stmt = $this->db->query("
                SELECT 
                    AVG(TIMESTAMPDIFF(SECOND, started_at, completed_at)) as avg_duration_seconds,
                    AVG(total_items) as avg_items_per_job,
                    AVG(total_items / NULLIF(TIMESTAMPDIFF(SECOND, started_at, completed_at), 0)) as items_per_second
                FROM catalog_clone_jobs
                WHERE status = 'completed'
                AND started_at IS NOT NULL
                AND completed_at IS NOT NULL
                AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            ");

            $perf = $stmt->fetch(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            return [
                'avg_job_duration_seconds' => 0.0,
                'avg_items_per_job' => 0.0,
                'items_per_second' => 0.0,
                'error' => $e->getMessage(),
            ];
        }

        return [
            'avg_job_duration_seconds' => round((float) ($perf['avg_duration_seconds'] ?? 0), 2),
            'avg_items_per_job' => round((float) ($perf['avg_items_per_job'] ?? 0), 2),
            'items_per_second' => round((float) ($perf['items_per_second'] ?? 0), 4)
        ];
    }

    /**
     * Calcula tamanho de diretório
     */
    private function getDirectorySize(string $path): int
    {
        $size = 0;

        try {
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
            );

            foreach ($files as $file) {
                $size += $file->getSize();
            }
        } catch (\Throwable $e) {
            return 0;
        }

        return $size;
    }

    /**
     * Obtém histórico de saúde
     */
    public function getHealthHistory(int $hours = 24): array
    {
        try {
            if ($this->db === null) {
                return [];
            }

            $hoursClamped = max(1, min($hours, 168));
            $cutoff = $this->dateTimeToSql((new \DateTimeImmutable('now'))->modify('-' . $hoursClamped . ' hours'));

            $stmt = $this->db->prepare("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
                    COUNT(*) as checks,
                    AVG(CASE WHEN status = 'healthy' THEN 1 ELSE 0 END) * 100 as uptime_percent
                FROM clone_health_logs
                WHERE created_at >= :cutoff
                GROUP BY hour
                ORDER BY hour
            ");
            $stmt->execute(['cutoff' => $cutoff]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Throwable $e) {
            log_debug('CloneHealthMonitorService: getHealthHistory falhou', [
                'error' => $e->getMessage(),
                'account_id' => $this->accountId,
            ]);
            return [];
        }
    }

    /**
     * Registra check de saúde
     */
    public function logHealthCheck(array $health): void
    {
        try {
            if ($this->db === null) {
                return;
            }

            $stmt = $this->db->prepare("
                INSERT INTO clone_health_logs (
                    status, issues_count, check_data, created_at
                ) VALUES (
                    :status, :issues, :data, NOW()
                )
            ");

            $stmt->execute([
                'status' => $health['status'],
                'issues' => count($health['issues']),
                'data' => $this->safeJsonEncode($health)
            ]);
        } catch (\Throwable $e) {
            // Tabela pode não existir
            log_debug('CloneHealthMonitorService: logHealthCheck falhou', [
                'error' => $e->getMessage(),
                'account_id' => $this->accountId,
            ]);
        }
    }

    /**
     * @param array<int, string> $checkStatuses
     */
    private function determineOverallStatus(array $checkStatuses): string
    {
        if (in_array('critical', $checkStatuses, true)) {
            return 'critical';
        }

        if (in_array('warning', $checkStatuses, true)) {
            return 'warning';
        }

        if (in_array('unknown', $checkStatuses, true)) {
            return 'warning';
        }

        return 'healthy';
    }

    /**
     * @param array<int, string> $checkStatuses
     */
    private function calculateHealthScore(array $checkStatuses): int
    {
        $score = 100;

        foreach ($checkStatuses as $status) {
            $penalty = self::SCORE_PENALTY_BY_STATUS[$status] ?? self::SCORE_PENALTY_BY_STATUS['unknown'];
            $score -= (int) $penalty;
        }

        return max(0, min(100, $score));
    }

    /**
     * @param array<string, array<string, mixed>> $checks
     * @return array<int, array{severity: string, component: string, message: string}>
     */
    private function buildIssuesFromChecks(array $checks): array
    {
        $issues = [];

        foreach ($checks as $component => $check) {
            $status = (string) ($check['status'] ?? 'unknown');
            if ($status === 'ok') {
                continue;
            }

            $severity = $status === 'critical' ? 'critical' : 'warning';
            $message = (string) ($check['message'] ?? '');

            if ($message === '') {
                $message = 'Problema detectado em ' . $component;
            }

            $issues[] = [
                'severity' => $severity,
                'component' => (string) $component,
                'message' => $message,
            ];
        }

        return $issues;
    }

    private function safeJsonEncode(array $data): string
    {
        try {
            return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        } catch (\Throwable $e) {
            return '{}';
        }
    }

    private function dateTimeToSql(\DateTimeImmutable $dt): string
    {
        return $dt->format('Y-m-d H:i:s');
    }
}
