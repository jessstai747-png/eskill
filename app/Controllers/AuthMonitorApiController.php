<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use PDO;

/**
 * API REST para Auth Failure Monitor
 * Endpoints para consultar bloqueios, falhas e estatísticas
 */
class AuthMonitorApiController
{
    private PDO $db;
    private Request $request;

    public function __construct()
    {
        $this->request = new Request();
        $dbHost = $_ENV['DB_HOST'] ?? 'localhost';
        $dbName = $_ENV['DB_DATABASE'] ?? 'meli';
        $dbUser = $_ENV['DB_USERNAME'] ?? 'root';
        $dbPass = $_ENV['DB_PASSWORD'] ?? '';

        $this->db = new PDO(
            "mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4",
            $dbUser,
            $dbPass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }

    /**
     * GET /api/auth-monitor/status
     * Retorna status geral do sistema
     */
    public function getStatus(): void
    {
        header('Content-Type: application/json');

        try {
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM auth_blocked_ips");
            $totalBlocks = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $this->db->query("SELECT COUNT(*) as active FROM auth_blocked_ips WHERE expires_at > NOW()");
            $activeBlocks = $stmt->fetch(PDO::FETCH_ASSOC)['active'];

            $stmt = $this->db->query("SELECT COUNT(*) as total FROM auth_failure_log");
            $totalFailures = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            $stmt = $this->db->query("SELECT COUNT(DISTINCT ip_address) as unique_ips FROM auth_failure_log");
            $uniqueIPs = $stmt->fetch(PDO::FETCH_ASSOC)['unique_ips'];

            $stmt = $this->db->query("
                SELECT COUNT(*) as today 
                FROM auth_failure_log 
                WHERE DATE(detected_at) = CURDATE()
            ");
            $failuresToday = $stmt->fetch(PDO::FETCH_ASSOC)['today'];

            echo json_encode([
                'success' => true,
                'data' => [
                    'total_blocks' => (int)$totalBlocks,
                    'active_blocks' => (int)$activeBlocks,
                    'total_failures' => (int)$totalFailures,
                    'unique_ips' => (int)$uniqueIPs,
                    'failures_today' => (int)$failuresToday,
                    'timestamp' => date('Y-m-d H:i:s')
                ]
            ], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * GET /api/auth-monitor/blocked-ips
     * Lista IPs bloqueados (ativos ou todos)
     */
    public function getBlockedIPs(): void
    {
        header('Content-Type: application/json');

        try {
            $activeOnly = $this->request->get('active_only', 'true') ?? 'true';
            $limit = max(1, min($this->request->getInt('limit', 100), 1000));
            $offset = max(0, $this->request->getInt('offset', 0));

            $sql = "SELECT * FROM auth_blocked_ips";
            if ($activeOnly === 'true') {
                $sql .= " WHERE expires_at > NOW()";
            }
            $sql .= " ORDER BY blocked_at DESC LIMIT {$limit} OFFSET {$offset}";

            $stmt = $this->db->prepare($sql);
            $stmt->execute();

            $ips = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $ips,
                'meta' => [
                    'count' => count($ips),
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * GET /api/auth-monitor/failures
     * Lista falhas de autenticação
     */
    public function getFailures(): void
    {
        header('Content-Type: application/json');

        try {
            $limit = max(1, min($this->request->getInt('limit', 100), 1000));
            $offset = max(0, $this->request->getInt('offset', 0));
            $ipAddress = $this->request->get('ip');
            $since = $this->request->get('since');

            $sql = "SELECT * FROM auth_failure_log WHERE 1=1";
            $params = [];

            if ($ipAddress) {
                $sql .= " AND ip_address = :ip";
                $params[':ip'] = $ipAddress;
            }

            if ($since) {
                $sql .= " AND detected_at >= :since";
                $params[':since'] = $since;
            }

            $sql .= " ORDER BY detected_at DESC LIMIT {$limit} OFFSET {$offset}";

            $stmt = $this->db->prepare($sql);
            foreach ($params as $key => $value) {
                $stmt->bindValue($key, $value);
            }
            $stmt->execute();

            $failures = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => $failures,
                'meta' => [
                    'count' => count($failures),
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * GET /api/auth-monitor/stats
     * Estatísticas detalhadas
     */
    public function getStatistics(): void
    {
        header('Content-Type: application/json');

        try {
            // Top 10 IPs
            $stmt = $this->db->query("
                SELECT ip_address, COUNT(*) as count
                FROM auth_failure_log
                GROUP BY ip_address
                ORDER BY count DESC
                LIMIT 10
            ");
            $topIPs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Estatísticas por dia (últimos 7 dias)
            $stmt = $this->db->query("
                SELECT 
                    DATE(detected_at) as date,
                    COUNT(*) as count
                FROM auth_failure_log
                WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
                GROUP BY DATE(detected_at)
                ORDER BY date DESC
            ");
            $dailyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Estatísticas por tipo de falha
            $stmt = $this->db->query("
                SELECT 
                    failure_type,
                    COUNT(*) as count
                FROM auth_failure_log
                WHERE failure_type IS NOT NULL
                GROUP BY failure_type
                ORDER BY count DESC
            ");
            $failureTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Estatísticas por hora do dia
            $stmt = $this->db->query("
                SELECT 
                    HOUR(detected_at) as hour,
                    COUNT(*) as count
                FROM auth_failure_log
                WHERE detected_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY HOUR(detected_at)
                ORDER BY hour
            ");
            $hourlyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'top_ips' => $topIPs,
                    'daily_stats' => $dailyStats,
                    'failure_types' => $failureTypes,
                    'hourly_stats' => $hourlyStats
                ]
            ], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * GET /api/auth-monitor/ip/{ip}
     * Informações detalhadas sobre um IP específico
     */
    public function getIPDetails(string $ip): void
    {
        header('Content-Type: application/json');

        try {
            // Verificar se está bloqueado
            $stmt = $this->db->prepare("
                SELECT * FROM auth_blocked_ips 
                WHERE ip_address = :ip 
                ORDER BY blocked_at DESC 
                LIMIT 1
            ");
            $stmt->execute([':ip' => $ip]);
            $blockInfo = $stmt->fetch(PDO::FETCH_ASSOC);

            // Contar falhas
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as total FROM auth_failure_log 
                WHERE ip_address = :ip
            ");
            $stmt->execute([':ip' => $ip]);
            $failureCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Últimas falhas
            $stmt = $this->db->prepare("
                SELECT * FROM auth_failure_log 
                WHERE ip_address = :ip 
                ORDER BY detected_at DESC 
                LIMIT 10
            ");
            $stmt->execute([':ip' => $ip]);
            $recentFailures = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Primeira e última ocorrência
            $stmt = $this->db->prepare("
                SELECT 
                    MIN(detected_at) as first_seen,
                    MAX(detected_at) as last_seen
                FROM auth_failure_log 
                WHERE ip_address = :ip
            ");
            $stmt->execute([':ip' => $ip]);
            $timeline = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode([
                'success' => true,
                'data' => [
                    'ip_address' => $ip,
                    'is_blocked' => !empty($blockInfo) && strtotime($blockInfo['expires_at'] ?? '') > time(),
                    'block_info' => $blockInfo ?: null,
                    'failure_count' => (int)$failureCount,
                    'first_seen' => $timeline['first_seen'],
                    'last_seen' => $timeline['last_seen'],
                    'recent_failures' => $recentFailures
                ]
            ], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * POST /api/auth-monitor/block-ip
     * Bloquear IP manualmente
     */
    public function blockIP(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            
            if (empty($input['ip_address'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'IP address is required'
                ]);
                return;
            }

            $ip = $input['ip_address'];
            $reason = $input['reason'] ?? 'Manual block via API';
            $duration = (int)($input['duration'] ?? 3600);
            $isPermanent = (bool)($input['is_permanent'] ?? false);

            $expiresAt = $isPermanent ? null : date('Y-m-d H:i:s', time() + $duration);

            $stmt = $this->db->prepare("
                INSERT INTO auth_blocked_ips 
                (ip_address, reason, failure_count, expires_at, is_permanent, created_by) 
                VALUES (:ip, :reason, 0, :expires_at, :is_permanent, 'API')
            ");

            $stmt->execute([
                ':ip' => $ip,
                ':reason' => $reason,
                ':expires_at' => $expiresAt,
                ':is_permanent' => (int)$isPermanent
            ]);

            echo json_encode([
                'success' => true,
                'data' => [
                    'ip_address' => $ip,
                    'blocked_until' => $expiresAt,
                    'is_permanent' => $isPermanent,
                    'message' => 'IP blocked successfully'
                ]
            ], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * DELETE /api/auth-monitor/unblock-ip/{ip}
     * Desbloquear IP manualmente
     */
    public function unblockIP(string $ip): void
    {
        header('Content-Type: application/json');

        try {
            $stmt = $this->db->prepare("
                UPDATE auth_blocked_ips 
                SET expires_at = NOW() 
                WHERE ip_address = :ip AND expires_at > NOW()
            ");
            $stmt->execute([':ip' => $ip]);

            $affected = $stmt->rowCount();

            echo json_encode([
                'success' => true,
                'data' => [
                    'ip_address' => $ip,
                    'unblocked' => $affected > 0,
                    'message' => $affected > 0 ? 'IP unblocked successfully' : 'IP was not blocked'
                ]
            ], JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage()
            ], JSON_PRETTY_PRINT);
        }
    }
}
