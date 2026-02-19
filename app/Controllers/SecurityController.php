<?php

namespace App\Controllers;

use App\Helpers\ResponseHelper;
use App\Middleware\SecurityMiddleware;
use App\Services\SecureTokenService;

/**
 * SecurityController - Gerenciamento de Segurança
 * 
 * Endpoints para:
 * - Visualização de logs de segurança
 * - Gerenciamento de IPs bloqueados
 * - Estatísticas de segurança
 * - Migração de tokens para criptografia
 */
class SecurityController extends BaseController
{
    private \PDO $db;
    private SecurityMiddleware $security;

    public function __construct()
    {
        parent::__construct();
        $this->db = \App\Database::getInstance();
        $this->security = new SecurityMiddleware();
    }

    /**
     * Dashboard de segurança
     * GET /security
     */
    public function dashboard(): void
    {
        $stats = $this->security->getSecurityStats(24);

        // Últimos eventos
        $stmt = $this->db->query("
            SELECT * FROM security_audit_log 
            ORDER BY created_at DESC 
            LIMIT 50
        ");
        $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // IPs bloqueados
        $stmt = $this->db->query("
            SELECT * FROM blocked_ips 
            WHERE blocked_until IS NULL OR blocked_until > NOW()
            ORDER BY created_at DESC
        ");
        $blockedIps = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        require __DIR__ . '/../Views/security/dashboard.php';
    }

    /**
     * Lista eventos de segurança
     * GET /api/security/events
     */
    public function listEvents(): void
    {
        $hours = $this->request->getInt('hours', 24);
        $severity = $this->request->get('severity');
        $eventType = $this->request->get('type');
        $page = max(1, $this->request->getInt('page', 1));
        $limit = $this->request->getIntClamped('limit', 50, 10, 100);
        $limitSql = max(1, min((int) $limit, 100));
        $offsetSql = max(0, ($page - 1) * $limitSql);

        $where = ['1=1'];
        $params = [];

        if ($hours > 0) {
            $where[] = 'created_at > DATE_SUB(NOW(), INTERVAL :hours HOUR)';
            $params['hours'] = $hours;
        }

        if ($severity) {
            $where[] = 'severity = :severity';
            $params['severity'] = $severity;
        }

        if ($eventType) {
            $where[] = 'event_type = :event_type';
            $params['event_type'] = $eventType;
        }

        $whereClause = implode(' AND ', $where);

        // Total
        $countSql = "SELECT COUNT(*) FROM security_audit_log WHERE {$whereClause}";
        $stmt = $this->db->prepare($countSql);
        $stmt->execute($params);
        $total = (int)$stmt->fetchColumn();

        // Dados
        $sql = "SELECT * FROM security_audit_log 
            WHERE {$whereClause}
            ORDER BY created_at DESC 
            LIMIT {$limitSql} OFFSET {$offsetSql}";
        $stmt = $this->db->prepare($sql);

        foreach ($params as $key => $value) {
            $paramType = is_int($value) ? \PDO::PARAM_INT : \PDO::PARAM_STR;
            $stmt->bindValue(':' . $key, $value, $paramType);
        }

        $stmt->execute();
        $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        ResponseHelper::json([
            'success' => true,
            'data' => $events,
            'pagination' => [
                'page' => $page,
                'limit' => $limitSql,
                'total' => $total,
                'pages' => ceil($total / $limitSql)
            ]
        ]);
    }

    /**
     * Estatísticas de segurança
     * GET /api/security/stats
     */
    public function stats(): void
    {
        $hours = $this->request->getInt('hours', 24);

        $stats = $this->security->getSecurityStats($hours);

        // Adicionar estatísticas adicionais
        try {
            // Eventos por hora (últimas 24h)
            $stmt = $this->db->query("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m-%d %H:00') as hour,
                    COUNT(*) as count
                FROM security_audit_log
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY hour
                ORDER BY hour
            ");
            $stats['events_by_hour'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Top IPs por tentativas
            $stmt = $this->db->query("
                SELECT 
                    ip_address,
                    COUNT(*) as count
                FROM security_audit_log
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY ip_address
                ORDER BY count DESC
                LIMIT 10
            ");
            $stats['top_ips'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Eventos por tipo
            $stmt = $this->db->query("
                SELECT 
                    event_type,
                    COUNT(*) as count
                FROM security_audit_log
                WHERE created_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
                GROUP BY event_type
                ORDER BY count DESC
            ");
            $stats['events_by_type'] = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\Exception $e) {
            $stats['error'] = $e->getMessage();
        }

        ResponseHelper::json([
            'success' => true,
            'data' => $stats
        ]);
    }

    /**
     * Lista IPs bloqueados
     * GET /api/security/blocked-ips
     */
    public function listBlockedIps(): void
    {
        $includeExpired = $this->request->getBool('include_expired', false);

        $where = $includeExpired ? '1=1' : '(blocked_until IS NULL OR blocked_until > NOW())';

        $stmt = $this->db->query("
            SELECT * FROM blocked_ips 
            WHERE {$where}
            ORDER BY created_at DESC
        ");
        $blockedIps = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        ResponseHelper::json([
            'success' => true,
            'data' => $blockedIps
        ]);
    }

    /**
     * Bloqueia um IP
     * POST /api/security/block-ip
     */
    public function blockIp(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);

        $ip = $data['ip'] ?? null;
        $reason = $data['reason'] ?? 'Manual block';
        $duration = (int)($data['duration'] ?? 0); // 0 = permanente

        if (!$ip || !filter_var($ip, FILTER_VALIDATE_IP)) {
            ResponseHelper::error('IP inválido', 400);
            return;
        }

        $success = $this->security->blockIp($ip, $reason, $duration);

        // Registrar evento
        if ($success) {
            $this->security->logSecurityEvent('ip_blocked_manual', $ip, 'info', [
                'reason' => $reason,
                'duration' => $duration,
                'blocked_by' => $this->getUserId() ?? 'system'
            ]);
        }

        ResponseHelper::json([
            'success' => $success,
            'message' => $success ? 'IP bloqueado com sucesso' : 'Erro ao bloquear IP'
        ]);
    }

    /**
     * Desbloqueia um IP
     * POST /api/security/unblock-ip
     */
    public function unblockIp(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $ip = $data['ip'] ?? null;

        if (!$ip) {
            ResponseHelper::error('IP não informado', 400);
            return;
        }

        $stmt = $this->db->prepare("DELETE FROM blocked_ips WHERE ip_address = :ip");
        $success = $stmt->execute(['ip' => $ip]);

        if ($success) {
            $this->security->logSecurityEvent('ip_unblocked', $ip, 'info', [
                'unblocked_by' => $this->getUserId() ?? 'system'
            ]);
        }

        ResponseHelper::json([
            'success' => $success,
            'message' => $success ? 'IP desbloqueado' : 'Erro ao desbloquear IP'
        ]);
    }

    /**
     * Migra tokens não criptografados
     * POST /api/security/migrate-tokens
     */
    public function migrateTokens(): void
    {
        try {
            $tokenService = new SecureTokenService();
            $result = $tokenService->migrateUnencryptedTokens();

            $this->security->logSecurityEvent('tokens_migrated', $this->request->ip(), 'info', [
                'migrated_count' => $result['migrated'],
                'initiated_by' => $this->getUserId() ?? 'system'
            ]);

            ResponseHelper::json([
                'success' => true,
                'data' => $result
            ]);
        } catch (\Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Verifica status de criptografia dos tokens
     * GET /api/security/tokens-status
     */
    public function tokensStatus(): void
    {
        try {
            $stmt = $this->db->query("
                SELECT 
                    COUNT(*) as total,
                    SUM(CASE WHEN tokens_encrypted = 1 THEN 1 ELSE 0 END) as encrypted,
                    SUM(CASE WHEN tokens_encrypted = 0 OR tokens_encrypted IS NULL THEN 1 ELSE 0 END) as unencrypted
                FROM ml_accounts
                WHERE access_token IS NOT NULL
            ");
            $result = $stmt->fetch(\PDO::FETCH_ASSOC);

            ResponseHelper::json([
                'success' => true,
                'data' => [
                    'total_accounts' => (int)$result['total'],
                    'encrypted' => (int)$result['encrypted'],
                    'unencrypted' => (int)$result['unencrypted'],
                    'encryption_percentage' => $result['total'] > 0
                        ? round(($result['encrypted'] / $result['total']) * 100, 2)
                        : 0
                ]
            ]);
        } catch (\Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Limpa logs antigos
     * POST /api/security/cleanup-logs
     */
    public function cleanupLogs(): void
    {
        $days = $this->request->postInt('days', 30);

        try {
            $stmt = $this->db->prepare("
                DELETE FROM security_audit_log 
                WHERE created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            $stmt->execute(['days' => $days]);
            $deleted = $stmt->rowCount();

            // Limpar bloqueios expirados
            $stmt = $this->db->query("
                DELETE FROM blocked_ips 
                WHERE blocked_until IS NOT NULL AND blocked_until < NOW()
            ");
            $expiredBlocks = $stmt->rowCount();

            ResponseHelper::json([
                'success' => true,
                'data' => [
                    'logs_deleted' => $deleted,
                    'expired_blocks_removed' => $expiredBlocks
                ]
            ]);
        } catch (\Exception $e) {
            ResponseHelper::error($e->getMessage(), 500);
        }
    }

    /**
     * Exporta relatório de segurança
     * GET /api/security/export
     */
    public function exportReport(): void
    {
        $format = $this->request->get('format', 'json');
        $hours = $this->request->getInt('hours', 24);

        // Coletar dados
        $stmt = $this->db->prepare("
            SELECT * FROM security_audit_log 
            WHERE created_at > DATE_SUB(NOW(), INTERVAL :hours HOUR)
            ORDER BY created_at DESC
        ");
        $stmt->execute(['hours' => $hours]);
        $events = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $stmt = $this->db->query("
            SELECT * FROM blocked_ips 
            WHERE blocked_until IS NULL OR blocked_until > NOW()
        ");
        $blockedIps = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $report = [
            'generated_at' => date('Y-m-d H:i:s'),
            'period_hours' => $hours,
            'summary' => $this->security->getSecurityStats($hours),
            'events' => $events,
            'blocked_ips' => $blockedIps
        ];

        if ($format === 'csv') {
            $this->exportCsv($report);
        } else {
            ResponseHelper::json(
                $report,
                200,
                ['Content-Disposition' => 'attachment; filename="security_report_' . date('Y-m-d_His') . '.json"'],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
            );
        }
    }

    /**
     * Exporta relatório em CSV
     */
    private function exportCsv(array $report): void
    {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="security_report_' . date('Y-m-d_His') . '.csv"');

        $output = fopen('php://output', 'w');

        // Cabeçalho dos eventos
        fputcsv($output, ['== EVENTOS DE SEGURANÇA ==']);
        fputcsv($output, ['ID', 'Tipo', 'IP', 'User Agent', 'Severidade', 'Detalhes', 'Data']);

        foreach ($report['events'] as $event) {
            fputcsv($output, [
                $event['id'],
                $event['event_type'],
                $event['ip_address'],
                $event['user_agent'] ?? '',
                $event['severity'],
                $event['details'] ?? '',
                $event['created_at']
            ]);
        }

        fputcsv($output, []);
        fputcsv($output, ['== IPs BLOQUEADOS ==']);
        fputcsv($output, ['ID', 'IP', 'Motivo', 'Tentativas', 'Bloqueado até', 'Criado em']);

        foreach ($report['blocked_ips'] as $ip) {
            fputcsv($output, [
                $ip['id'],
                $ip['ip_address'],
                $ip['reason'],
                $ip['attempts'],
                $ip['blocked_until'] ?? 'Permanente',
                $ip['created_at']
            ]);
        }

        fclose($output);
    }
}
