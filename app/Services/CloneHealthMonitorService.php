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
    private PDO $db;
    private int $accountId;

    // Thresholds para alertas
    private array $thresholds = [
        'job_stuck_minutes' => 30,
        'error_rate_warning' => 20,
        'error_rate_critical' => 50,
        'queue_size_warning' => 100,
        'queue_size_critical' => 500,
        'api_latency_warning_ms' => 2000,
        'api_latency_critical_ms' => 5000
    ];

    public function __construct(int $accountId = 0)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    /**
     * Obtém status geral de saúde do sistema
     */
    public function getSystemHealth(): array
    {
        $health = [
            'status' => 'healthy',
            'timestamp' => date('c'),
            'checks' => [],
            'issues' => [],
            'metrics' => []
        ];

        // Check 1: Jobs ativos
        $jobsCheck = $this->checkActiveJobs();
        $health['checks']['active_jobs'] = $jobsCheck;
        if ($jobsCheck['status'] !== 'ok') {
            $health['issues'][] = $jobsCheck['message'];
        }

        // Check 2: Jobs travados
        $stuckCheck = $this->checkStuckJobs();
        $health['checks']['stuck_jobs'] = $stuckCheck;
        if ($stuckCheck['status'] !== 'ok') {
            $health['issues'][] = $stuckCheck['message'];
        }

        // Check 3: Taxa de erro
        $errorCheck = $this->checkErrorRate();
        $health['checks']['error_rate'] = $errorCheck;
        if ($errorCheck['status'] !== 'ok') {
            $health['issues'][] = $errorCheck['message'];
        }

        // Check 4: Fila pendente
        $queueCheck = $this->checkQueueSize();
        $health['checks']['queue_size'] = $queueCheck;
        if ($queueCheck['status'] !== 'ok') {
            $health['issues'][] = $queueCheck['message'];
        }

        // Check 5: Workers ativos
        $workerCheck = $this->checkWorkers();
        $health['checks']['workers'] = $workerCheck;
        if ($workerCheck['status'] !== 'ok') {
            $health['issues'][] = $workerCheck['message'];
        }

        // Check 6: Conectividade API ML
        $apiCheck = $this->checkApiConnectivity();
        $health['checks']['api_connectivity'] = $apiCheck;
        if ($apiCheck['status'] !== 'ok') {
            $health['issues'][] = $apiCheck['message'];
        }

        // Determinar status geral
        $statuses = array_column($health['checks'], 'status');
        if (in_array('critical', $statuses)) {
            $health['status'] = 'critical';
        } elseif (in_array('warning', $statuses)) {
            $health['status'] = 'degraded';
        }

        // Métricas agregadas
        $health['metrics'] = $this->getQuickMetrics();

        return $health;
    }

    /**
     * Verifica jobs ativos
     */
    private function checkActiveJobs(): array
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM catalog_clone_jobs
            WHERE status IN ('processing', 'queued')
        ");
        $stmt->execute();
        $count = (int) $stmt->fetchColumn();

        return [
            'status' => 'ok',
            'count' => $count,
            'message' => "$count jobs ativos no momento"
        ];
    }

    /**
     * Verifica jobs travados
     */
    private function checkStuckJobs(): array
    {
        $minutes = $this->thresholds['job_stuck_minutes'];

        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM catalog_clone_jobs
            WHERE status = 'processing'
            AND updated_at < DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
        ");
        $stmt->execute(['minutes' => $minutes]);
        $count = (int) $stmt->fetchColumn();

        if ($count > 0) {
            return [
                'status' => 'critical',
                'count' => $count,
                'message' => "$count jobs travados há mais de {$minutes} minutos"
            ];
        }

        return [
            'status' => 'ok',
            'count' => 0,
            'message' => 'Nenhum job travado'
        ];
    }

    /**
     * Verifica taxa de erro
     */
    private function checkErrorRate(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                SUM(successful_items) as success,
                SUM(failed_items) as failed
            FROM catalog_clone_jobs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute();
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        $success = (int) ($row['success'] ?? 0);
        $failed = (int) ($row['failed'] ?? 0);
        $total = $success + $failed;

        if ($total === 0) {
            return [
                'status' => 'ok',
                'rate' => 0,
                'message' => 'Sem processamento na última hora'
            ];
        }

        $errorRate = ($failed / $total) * 100;

        if ($errorRate >= $this->thresholds['error_rate_critical']) {
            return [
                'status' => 'critical',
                'rate' => round($errorRate, 2),
                'message' => "Taxa de erro crítica: " . round($errorRate, 1) . "%"
            ];
        }

        if ($errorRate >= $this->thresholds['error_rate_warning']) {
            return [
                'status' => 'warning',
                'rate' => round($errorRate, 2),
                'message' => "Taxa de erro elevada: " . round($errorRate, 1) . "%"
            ];
        }

        return [
            'status' => 'ok',
            'rate' => round($errorRate, 2),
            'message' => "Taxa de erro: " . round($errorRate, 1) . "%"
        ];
    }

    /**
     * Verifica tamanho da fila
     */
    private function checkQueueSize(): array
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM catalog_clone_job_items
            WHERE status = 'pending'
        ");
        $stmt->execute();
        $count = (int) $stmt->fetchColumn();

        if ($count >= $this->thresholds['queue_size_critical']) {
            return [
                'status' => 'critical',
                'count' => $count,
                'message' => "Fila muito grande: $count itens pendentes"
            ];
        }

        if ($count >= $this->thresholds['queue_size_warning']) {
            return [
                'status' => 'warning',
                'count' => $count,
                'message' => "Fila elevada: $count itens pendentes"
            ];
        }

        return [
            'status' => 'ok',
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
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM worker_execution_logs
                WHERE worker_name LIKE 'clone%'
                AND executed_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)
            ");
            $stmt->execute();
            $count = (int) $stmt->fetchColumn();

            if ($count === 0) {
                return [
                    'status' => 'warning',
                    'active' => 0,
                    'message' => 'Nenhum worker executado nos últimos 10 minutos'
                ];
            }

            return [
                'status' => 'ok',
                'active' => $count,
                'message' => "$count execuções de workers recentes"
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'ok',
                'active' => null,
                'message' => 'Verificação de workers não disponível'
            ];
        }
    }

    /**
     * Verifica conectividade com API ML
     */
    private function checkApiConnectivity(): array
    {
        // Verificar erros de API recentes
        try {
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as count
                FROM clone_sync_logs
                WHERE sync_type = 'api_error'
                AND created_at >= DATE_SUB(NOW(), INTERVAL 5 MINUTE)
            ");
            $stmt->execute();
            $errorCount = (int) $stmt->fetchColumn();

            if ($errorCount >= 10) {
                return [
                    'status' => 'critical',
                    'errors' => $errorCount,
                    'message' => "Muitos erros de API: $errorCount nos últimos 5 min"
                ];
            }

            if ($errorCount >= 3) {
                return [
                    'status' => 'warning',
                    'errors' => $errorCount,
                    'message' => "Alguns erros de API: $errorCount nos últimos 5 min"
                ];
            }

            return [
                'status' => 'ok',
                'errors' => $errorCount,
                'message' => 'Conectividade API OK'
            ];
        } catch (\Exception $e) {
            return [
                'status' => 'ok',
                'errors' => null,
                'message' => 'Verificação de API não disponível'
            ];
        }
    }

    /**
     * Métricas rápidas
     */
    private function getQuickMetrics(): array
    {
        // Últimas 24h
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as jobs_24h,
                SUM(total_items) as items_24h,
                SUM(successful_items) as success_24h,
                SUM(failed_items) as failed_24h
            FROM catalog_clone_jobs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        $last24h = $stmt->fetch(PDO::FETCH_ASSOC);

        // Última hora
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as jobs_1h,
                SUM(total_items) as items_1h
            FROM catalog_clone_jobs
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        ");
        $stmt->execute();
        $lastHour = $stmt->fetch(PDO::FETCH_ASSOC);

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
            try {
                $safeTable = preg_replace('/[^a-zA-Z0-9_]/', '', $table);
                $stmt = $this->db->query("SELECT COUNT(*) FROM `{$safeTable}`");
                $count = (int) $stmt->fetchColumn();
                $results[$table] = ['exists' => true, 'count' => $count];
            } catch (\Exception $e) {
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
        $stmt = $this->db->query("
            SELECT 
                status,
                COUNT(*) as count
            FROM catalog_clone_jobs
            GROUP BY status
        ");

        $byStatus = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $byStatus[$row['status']] = (int) $row['count'];
        }

        // Últimos jobs
        $stmt = $this->db->query("
            SELECT job_id, status, total_items, successful_items, failed_items, created_at
            FROM catalog_clone_jobs
            ORDER BY created_at DESC
            LIMIT 5
        ");
        $recent = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'by_status' => $byStatus,
            'recent' => $recent
        ];
    }

    /**
     * Diagnóstico de performance
     */
    private function diagnosePerformance(): array
    {
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
        $files = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \RecursiveDirectoryIterator::SKIP_DOTS)
        );

        foreach ($files as $file) {
            $size += $file->getSize();
        }

        return $size;
    }

    /**
     * Obtém histórico de saúde
     */
    public function getHealthHistory(int $hours = 24): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
                    COUNT(*) as checks,
                    AVG(CASE WHEN status = 'healthy' THEN 1 ELSE 0 END) * 100 as uptime_percent
                FROM clone_health_logs
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL :hours HOUR)
                GROUP BY hour
                ORDER BY hour
            ");
            $stmt->execute(['hours' => $hours]);

            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    /**
     * Registra check de saúde
     */
    public function logHealthCheck(array $health): void
    {
        try {
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
                'data' => json_encode($health)
            ]);
        } catch (\Exception $e) {
            // Tabela pode não existir
        }
    }
}
