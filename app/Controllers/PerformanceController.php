<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\CacheService;

/**
 * PerformanceController - Monitoramento de Performance
 * 
 * Endpoints para:
 * - Estatísticas de cache
 * - Métricas de queries
 * - Métricas de API
 * - Otimização de sistema
 */
class PerformanceController extends BaseController
{
    private \PDO $db;
    private CacheService $cache;

    public function __construct()
    {
        parent::__construct();
        $this->db = \App\Database::getInstance();
        $this->cache = new CacheService();
    }

    /**
     * Dashboard de performance
     * GET /api/performance/dashboard
     */
    public function dashboard(): void
    {
        header('Content-Type: application/json');

        $hours = $this->request->getInt('hours', 24);

        $data = [
            'cache' => $this->getCacheStats(),
            'queries' => $this->getQueryStats($hours),
            'api' => $this->getApiStats($hours),
            'system' => $this->getSystemStats(),
            'summary' => $this->getSummary($hours)
        ];

        echo json_encode([
            'success' => true,
            'data' => $data
        ]);
    }

    /**
     * Estatísticas de cache
     * GET /api/performance/cache
     */
    public function cacheStats(): void
    {
        header('Content-Type: application/json');

        echo json_encode([
            'success' => true,
            'data' => $this->cache->getStats()
        ]);
    }

    /**
     * Limpa cache
     * POST /api/performance/cache/flush
     */
    public function flushCache(): void
    {
        header('Content-Type: application/json');

        $tag = $this->request->post('tag');

        if ($tag) {
            $count = $this->cache->invalidateTag($tag);
            echo json_encode([
                'success' => true,
                'message' => "Cache invalidado para tag '{$tag}'",
                'keys_removed' => $count
            ]);
        } else {
            $this->cache->flush();
            echo json_encode([
                'success' => true,
                'message' => 'Cache completamente limpo'
            ]);
        }
    }

    /**
     * Queries lentas
     * GET /api/performance/slow-queries
     */
    public function slowQueries(): void
    {
        header('Content-Type: application/json');

        $hours = $this->request->getInt('hours', 24);
        $limit = $this->request->getIntClamped('limit', 1, 100, 50);
        $threshold = $this->request->getFloat('threshold', 1.0);
        $limitSql = max(1, min(100, (int)$limit));

        try {
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    SUBSTRING(sql_text, 1, 500) as sql_text,
                    duration,
                    row_count,
                    error,
                    created_at
                FROM query_log 
                WHERE duration > :threshold 
                AND created_at > DATE_SUB(NOW(), INTERVAL :hours HOUR)
                ORDER BY duration DESC 
                LIMIT {$limitSql}
            ");

            $stmt->bindValue('threshold', $threshold);
            $stmt->bindValue('hours', $hours, \PDO::PARAM_INT);
            $stmt->execute();

            $queries = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $queries,
                'count' => count($queries)
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Métricas de API do ML
     * GET /api/performance/api-metrics
     */
    public function apiMetrics(): void
    {
        header('Content-Type: application/json');

        $hours = $this->request->getInt('hours', 24);
        $accountId = $this->request->get('account_id');

        $where = 'created_at > DATE_SUB(NOW(), INTERVAL :hours HOUR)';
        $params = ['hours' => $hours];

        if ($accountId) {
            $where .= ' AND account_id = :account_id';
            $params['account_id'] = $accountId;
        }

        try {
            // Métricas agregadas
            $stmt = $this->db->prepare("
                SELECT 
                    endpoint,
                    COUNT(*) as total_calls,
                    ROUND(AVG(response_time) * 1000, 2) as avg_response_ms,
                    ROUND(MAX(response_time) * 1000, 2) as max_response_ms,
                    SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as error_count,
                    ROUND(AVG(response_size) / 1024, 2) as avg_response_kb
                FROM api_metrics
                WHERE {$where}
                GROUP BY endpoint
                ORDER BY total_calls DESC
                LIMIT 50
            ");
            $stmt->execute($params);
            $byEndpoint = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Erros por status
            $stmt = $this->db->prepare("
                SELECT 
                    status_code,
                    COUNT(*) as count
                FROM api_metrics
                WHERE {$where}
                GROUP BY status_code
                ORDER BY status_code
            ");
            $stmt->execute($params);
            $byStatus = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Timeline (por hora)
            $stmt = $this->db->prepare("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m-%d %H:00') as hour,
                    COUNT(*) as calls,
                    ROUND(AVG(response_time) * 1000, 2) as avg_ms
                FROM api_metrics
                WHERE {$where}
                GROUP BY hour
                ORDER BY hour
            ");
            $stmt->execute($params);
            $timeline = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'by_endpoint' => $byEndpoint,
                    'by_status' => $byStatus,
                    'timeline' => $timeline
                ]
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Jobs em background
     * GET /api/performance/jobs
     */
    public function jobs(): void
    {
        header('Content-Type: application/json');

        $status = $this->request->getEnum('status', ['pending', 'processing', 'completed', 'failed']);

        $where = '1=1';
        $params = [];

        if ($status) {
            $where .= ' AND status = :status';
            $params['status'] = $status;
        }

        try {
            // Contagem por status
            $stmt = $this->db->query("
                SELECT status, COUNT(*) as count
                FROM background_jobs
                GROUP BY status
            ");
            $statusCounts = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);

            // Jobs recentes
            $stmt = $this->db->prepare("
                SELECT 
                    id, job_type, status, priority,
                    attempts, max_attempts,
                    started_at, completed_at,
                    SUBSTRING(error_message, 1, 200) as error_preview,
                    created_at
                FROM background_jobs
                WHERE {$where}
                ORDER BY 
                    CASE status 
                        WHEN 'processing' THEN 1 
                        WHEN 'pending' THEN 2 
                        ELSE 3 
                    END,
                    priority ASC,
                    created_at DESC
                LIMIT 100
            ");
            $stmt->execute($params);
            $jobs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'counts' => $statusCounts,
                    'jobs' => $jobs
                ]
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Otimizar tabelas
     * POST /api/performance/optimize
     */
    public function optimizeTables(): void
    {
        header('Content-Type: application/json');

        $tables = $this->request->postArray('tables');

        // Security: get allowed tables whitelist from database
        $stmt = $this->db->query("SHOW TABLES");
        $allowedTables = $stmt->fetchAll(\PDO::FETCH_COLUMN);

        if (empty($tables)) {
            $tables = $allowedTables;
        } else {
            // Security: validate table names against whitelist to prevent SQL injection
            $tables = array_filter($tables, function ($t) use ($allowedTables) {
                return in_array($t, $allowedTables, true);
            });
        }

        $results = [];

        foreach ($tables as $table) {
            try {
                // Security: table name validated against whitelist above
                $this->db->query("OPTIMIZE TABLE `" . str_replace('`', '``', $table) . "`");
                $results[$table] = 'OK';
            } catch (\Exception $e) {
                $results[$table] = 'Error: ' . $e->getMessage();
            }
        }

        echo json_encode([
            'success' => true,
            'data' => $results
        ]);
    }

    /**
     * Limpar logs antigos
     * POST /api/performance/cleanup
     */
    public function cleanup(): void
    {
        header('Content-Type: application/json');

        $days = $this->request->postInt('days', 30);

        try {
            $stmt = $this->db->prepare("CALL cleanup_performance_logs(:days)");
            $stmt->execute(['days' => $days]);
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $result,
                'message' => "Logs mais antigos que {$days} dias foram removidos"
            ]);
        } catch (\Exception $e) {
            // Procedure pode não existir
            $deleted = [];

            $stmt = $this->db->prepare("DELETE FROM query_log WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)");
            $stmt->execute(['days' => $days]);
            $deleted['query_log'] = $stmt->rowCount();

            echo json_encode([
                'success' => true,
                'data' => $deleted
            ]);
        }
    }

    /**
     * Configurações do sistema
     * GET /api/performance/config
     */
    public function config(): void
    {
        header('Content-Type: application/json');

        try {
            $stmt = $this->db->query("SELECT config_key, config_value, description FROM system_config");
            $configs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            $result = [];
            foreach ($configs as $config) {
                $result[$config['config_key']] = [
                    'value' => json_decode($config['config_value']),
                    'description' => $config['description']
                ];
            }

            echo json_encode([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ]);
        }
    }

    /**
     * Atualizar configuração
     * POST /api/performance/config
     */
    public function updateConfig(): void
    {
        header('Content-Type: application/json');

        $key = $this->request->post('key');
        $value = $this->request->post('value');

        if (!$key) {
            $this->jsonError('Key é obrigatório', 400);
        }

        try {
            $stmt = $this->db->prepare("
                UPDATE system_config 
                SET config_value = :value 
                WHERE config_key = :key
            ");
            $stmt->execute([
                'key' => $key,
                'value' => json_encode($value)
            ]);

            echo json_encode([
                'success' => $stmt->rowCount() > 0,
                'message' => $stmt->rowCount() > 0 ? 'Configuração atualizada' : 'Configuração não encontrada'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // === Métodos privados ===

    private function getCacheStats(): array
    {
        return $this->cache->getStats();
    }

    private function getQueryStats(int $hours): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total,
                    ROUND(AVG(duration) * 1000, 2) as avg_ms,
                    ROUND(MAX(duration) * 1000, 2) as max_ms,
                    SUM(CASE WHEN duration > 1.0 THEN 1 ELSE 0 END) as slow_count,
                    SUM(CASE WHEN error IS NOT NULL THEN 1 ELSE 0 END) as error_count
                FROM query_log
                WHERE created_at > DATE_SUB(NOW(), INTERVAL :hours HOUR)
            ");
            $stmt->execute(['hours' => $hours]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getApiStats(int $hours): array
    {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_calls,
                    ROUND(AVG(response_time) * 1000, 2) as avg_response_ms,
                    SUM(CASE WHEN status_code >= 400 THEN 1 ELSE 0 END) as errors,
                    ROUND(SUM(response_size) / 1024 / 1024, 2) as total_response_mb
                FROM api_metrics
                WHERE created_at > DATE_SUB(NOW(), INTERVAL :hours HOUR)
            ");
            $stmt->execute(['hours' => $hours]);
            return $stmt->fetch(\PDO::FETCH_ASSOC) ?: [];
        } catch (\Exception $e) {
            return ['error' => $e->getMessage()];
        }
    }

    private function getSystemStats(): array
    {
        $stats = [];

        // Uso de memória PHP
        $stats['php_memory'] = [
            'current' => $this->formatBytes(memory_get_usage(true)),
            'peak' => $this->formatBytes(memory_get_peak_usage(true)),
            'limit' => ini_get('memory_limit')
        ];

        // Uso de disco
        $cacheDir = __DIR__ . '/../../storage/cache';
        $logsDir = __DIR__ . '/../../storage/logs';

        $stats['disk'] = [
            'cache_size' => $this->getDirSize($cacheDir),
            'logs_size' => $this->getDirSize($logsDir)
        ];

        // Status do MySQL
        try {
            $stmt = $this->db->query("SHOW STATUS WHERE Variable_name IN ('Uptime', 'Threads_connected', 'Questions', 'Slow_queries')");
            $mysqlStatus = $stmt->fetchAll(\PDO::FETCH_KEY_PAIR);
            $stats['mysql'] = $mysqlStatus;
        } catch (\Exception $e) {
            $stats['mysql'] = ['error' => $e->getMessage()];
        }

        return $stats;
    }

    private function getSummary(int $hours): array
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM v_performance_summary WHERE date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)");
            $stmt->execute();
            return $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            return [];
        }
    }

    private function formatBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB'];
        $i = 0;

        while ($bytes >= 1024 && $i < count($units) - 1) {
            $bytes /= 1024;
            $i++;
        }

        return round($bytes, 2) . ' ' . $units[$i];
    }

    private function getDirSize(string $dir): string
    {
        if (!is_dir($dir)) {
            return '0 B';
        }

        $size = 0;
        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir)) as $file) {
            if ($file->isFile()) {
                $size += $file->getSize();
            }
        }

        return $this->formatBytes($size);
    }
}
