<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * CloneComplianceService - Sistema de Compliance e Auditoria
 * 
 * Gerencia auditoria detalhada de todas as operações de clonagem:
 * - Log de todas as ações (quem, quando, o quê)
 * - Trilha de auditoria completa
 * - Relatórios de compliance
 * - Verificação de políticas
 * - Detecção de anomalias
 */
class CloneComplianceService
{
    private PDO $db;
    private ?int $accountId;

    // Tipos de eventos de auditoria
    private const EVENT_TYPES = [
        'clone_started' => 'Job de clonagem iniciado',
        'clone_completed' => 'Job de clonagem concluído',
        'clone_failed' => 'Job de clonagem falhou',
        'clone_cancelled' => 'Job de clonagem cancelado',
        'item_cloned' => 'Item clonado com sucesso',
        'item_failed' => 'Falha ao clonar item',
        'item_skipped' => 'Item ignorado',
        'price_modified' => 'Preço modificado durante clone',
        'title_modified' => 'Título modificado durante clone',
        'seo_applied' => 'Otimização SEO aplicada',
        'automation_triggered' => 'Automação disparada',
        'rule_matched' => 'Regra de automação correspondeu',
        'settings_changed' => 'Configurações alteradas',
        'export_generated' => 'Relatório exportado',
        'api_access' => 'Acesso via API',
        'suspicious_activity' => 'Atividade suspeita detectada',
    ];

    // Níveis de severidade
    private const SEVERITY_INFO = 'info';
    private const SEVERITY_WARNING = 'warning';
    private const SEVERITY_ERROR = 'error';
    private const SEVERITY_CRITICAL = 'critical';

    public function __construct(?int $accountId = null)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->ensureTablesExist();
    }

    /**
     * Registra um evento de auditoria
     */
    public function logEvent(
        string $eventType,
        array $eventData = [],
        ?string $severity = null,
        ?int $userId = null,
        ?int $jobId = null,
        ?string $itemId = null
    ): int {
        $severity = $severity ?? self::SEVERITY_INFO;
        $userId = $userId ?? ($_SESSION['user_id'] ?? null);

        $stmt = $this->db->prepare("
            INSERT INTO clone_audit_logs (
                account_id, user_id, job_id, item_id,
                event_type, event_description, event_data,
                severity, ip_address, user_agent,
                created_at
            ) VALUES (
                :account_id, :user_id, :job_id, :item_id,
                :event_type, :event_description, :event_data,
                :severity, :ip_address, :user_agent,
                NOW()
            )
        ");

        $stmt->execute([
            'account_id' => $this->accountId,
            'user_id' => $userId,
            'job_id' => $jobId,
            'item_id' => $itemId,
            'event_type' => $eventType,
            'event_description' => self::EVENT_TYPES[$eventType] ?? $eventType,
            'event_data' => json_encode($eventData),
            'severity' => $severity,
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? null,
        ]);

        $logId = (int) $this->db->lastInsertId();

        // Verificar políticas de compliance
        $this->checkCompliancePolicies($eventType, $eventData, $logId);

        return $logId;
    }

    /**
     * Busca logs de auditoria com filtros
     */
    public function getAuditLogs(array $filters = []): array
    {
        $where = ['1=1'];
        $params = [];

        if ($this->accountId) {
            $where[] = 'account_id = :account_id';
            $params['account_id'] = $this->accountId;
        }

        if (!empty($filters['user_id'])) {
            $where[] = 'user_id = :user_id';
            $params['user_id'] = $filters['user_id'];
        }

        if (!empty($filters['job_id'])) {
            $where[] = 'job_id = :job_id';
            $params['job_id'] = $filters['job_id'];
        }

        if (!empty($filters['item_id'])) {
            $where[] = 'item_id = :item_id';
            $params['item_id'] = $filters['item_id'];
        }

        if (!empty($filters['event_type'])) {
            $where[] = 'event_type = :event_type';
            $params['event_type'] = $filters['event_type'];
        }

        if (!empty($filters['severity'])) {
            $where[] = 'severity = :severity';
            $params['severity'] = $filters['severity'];
        }

        if (!empty($filters['date_from'])) {
            $where[] = 'created_at >= :date_from';
            $params['date_from'] = $filters['date_from'];
        }

        if (!empty($filters['date_to'])) {
            $where[] = 'created_at <= :date_to';
            $params['date_to'] = $filters['date_to'];
        }

        if (!empty($filters['search'])) {
            $where[] = "(event_type LIKE :search OR event_data LIKE :search_data)";
            $params['search'] = '%' . $filters['search'] . '%';
            $params['search_data'] = '%' . $filters['search'] . '%';
        }

        // Whitelist ORDER BY to prevent SQL injection
        $allowedOrders = [
            'created_at DESC', 'created_at ASC',
            'event_type ASC', 'event_type DESC',
            'user_id ASC', 'user_id DESC',
        ];
        $orderBy = in_array($filters['order_by'] ?? '', $allowedOrders, true)
            ? $filters['order_by'] : 'created_at DESC';
        $limit = min((int) ($filters['limit'] ?? 50), 500);
        $offset = (int) ($filters['offset'] ?? 0);

        $sql = "
            SELECT 
                al.*,
                u.name as user_name,
                u.email as user_email
            FROM clone_audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY {$orderBy}
            LIMIT {$limit} OFFSET {$offset}
        ";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Decode event_data JSON
        foreach ($logs as &$log) {
            $log['event_data'] = json_decode($log['event_data'] ?? '{}', true);
        }

        // Count total
        $countSql = "SELECT COUNT(*) FROM clone_audit_logs al WHERE " . implode(' AND ', $where);
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        return [
            'logs' => $logs,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Obtém trilha de auditoria completa de um job
     */
    public function getJobAuditTrail(int $jobId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                al.*,
                u.name as user_name
            FROM clone_audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.job_id = :job_id
            ORDER BY al.created_at ASC
        ");

        $stmt->execute(['job_id' => $jobId]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($logs as &$log) {
            $log['event_data'] = json_decode($log['event_data'] ?? '{}', true);
        }

        // Calcular timeline com duração entre eventos
        $timeline = [];
        $prevTime = null;

        foreach ($logs as $log) {
            $currentTime = strtotime($log['created_at']);
            $duration = $prevTime ? ($currentTime - $prevTime) : 0;

            $timeline[] = [
                'event' => $log['event_type'],
                'description' => $log['event_description'],
                'timestamp' => $log['created_at'],
                'duration_seconds' => $duration,
                'severity' => $log['severity'],
                'user' => $log['user_name'],
                'details' => $log['event_data'],
            ];

            $prevTime = $currentTime;
        }

        return [
            'job_id' => $jobId,
            'total_events' => count($logs),
            'timeline' => $timeline,
        ];
    }

    /**
     * Obtém trilha de auditoria de um item
     */
    public function getItemAuditTrail(string $itemId): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                al.*,
                u.name as user_name
            FROM clone_audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.item_id = :item_id
            ORDER BY al.created_at ASC
        ");

        $stmt->execute(['item_id' => $itemId]);
        $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($logs as &$log) {
            $log['event_data'] = json_decode($log['event_data'] ?? '{}', true);
        }

        return [
            'item_id' => $itemId,
            'total_events' => count($logs),
            'events' => $logs,
        ];
    }

    /**
     * Gera relatório de compliance
     */
    public function generateComplianceReport(string $period = '30d'): array
    {
        $dateFrom = $this->parsePeriod($period);

        // Estatísticas gerais
        $stats = $this->getComplianceStats($dateFrom);

        // Violações de políticas
        $violations = $this->getPolicyViolations($dateFrom);

        // Top usuários por atividade
        $topUsers = $this->getTopUsersByActivity($dateFrom);

        // Eventos críticos
        $criticalEvents = $this->getCriticalEvents($dateFrom);

        // Anomalias detectadas
        $anomalies = $this->detectAnomalies($dateFrom);

        return [
            'period' => $period,
            'generated_at' => date('Y-m-d H:i:s'),
            'account_id' => $this->accountId,
            'statistics' => $stats,
            'policy_violations' => $violations,
            'top_users' => $topUsers,
            'critical_events' => $criticalEvents,
            'anomalies' => $anomalies,
            'compliance_score' => $this->calculateComplianceScore($stats, $violations),
        ];
    }

    /**
     * Obtém estatísticas de compliance
     */
    private function getComplianceStats(string $dateFrom): array
    {
        $params = ['date_from' => $dateFrom];
        $accountFilter = '';

        if ($this->accountId) {
            $accountFilter = 'AND account_id = :account_id';
            $params['account_id'] = $this->accountId;
        }

        // Total de eventos por tipo
        $stmt = $this->db->prepare("
            SELECT 
                event_type,
                COUNT(*) as count,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT job_id) as unique_jobs
            FROM clone_audit_logs
            WHERE created_at >= :date_from {$accountFilter}
            GROUP BY event_type
            ORDER BY count DESC
        ");
        $stmt->execute($params);
        $byType = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Eventos por severidade
        $stmt = $this->db->prepare("
            SELECT 
                severity,
                COUNT(*) as count
            FROM clone_audit_logs
            WHERE created_at >= :date_from {$accountFilter}
            GROUP BY severity
        ");
        $stmt->execute($params);
        $bySeverity = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Eventos por dia
        $stmt = $this->db->prepare("
            SELECT 
                DATE(created_at) as date,
                COUNT(*) as count
            FROM clone_audit_logs
            WHERE created_at >= :date_from {$accountFilter}
            GROUP BY DATE(created_at)
            ORDER BY date
        ");
        $stmt->execute($params);
        $byDay = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Total de atividades
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_events,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT job_id) as total_jobs,
                COUNT(DISTINCT item_id) as total_items
            FROM clone_audit_logs
            WHERE created_at >= :date_from {$accountFilter}
        ");
        $stmt->execute($params);
        $totals = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'totals' => $totals,
            'by_type' => $byType,
            'by_severity' => $bySeverity,
            'by_day' => $byDay,
        ];
    }

    /**
     * Busca violações de políticas
     */
    private function getPolicyViolations(string $dateFrom): array
    {
        $params = ['date_from' => $dateFrom];
        $accountFilter = '';

        if ($this->accountId) {
            $accountFilter = 'AND account_id = :account_id';
            $params['account_id'] = $this->accountId;
        }

        $stmt = $this->db->prepare("
            SELECT 
                pv.*,
                u.name as user_name,
                al.event_data
            FROM clone_policy_violations pv
            LEFT JOIN users u ON pv.user_id = u.id
            LEFT JOIN clone_audit_logs al ON pv.audit_log_id = al.id
            WHERE pv.created_at >= :date_from 
            {$accountFilter}
            ORDER BY pv.created_at DESC
            LIMIT 100
        ");
        $stmt->execute($params);
        $violations = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($violations as &$v) {
            $v['event_data'] = json_decode($v['event_data'] ?? '{}', true);
        }

        return $violations;
    }

    /**
     * Top usuários por atividade
     */
    private function getTopUsersByActivity(string $dateFrom): array
    {
        $params = ['date_from' => $dateFrom];
        $accountFilter = '';

        if ($this->accountId) {
            $accountFilter = 'AND al.account_id = :account_id';
            $params['account_id'] = $this->accountId;
        }

        $stmt = $this->db->prepare("
            SELECT 
                al.user_id,
                u.name,
                u.email,
                COUNT(*) as total_events,
                COUNT(DISTINCT al.job_id) as jobs_created,
                COUNT(DISTINCT al.item_id) as items_processed,
                MAX(al.created_at) as last_activity
            FROM clone_audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.created_at >= :date_from 
            AND al.user_id IS NOT NULL
            {$accountFilter}
            GROUP BY al.user_id, u.name, u.email
            ORDER BY total_events DESC
            LIMIT 20
        ");
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Eventos críticos recentes
     */
    private function getCriticalEvents(string $dateFrom): array
    {
        $params = ['date_from' => $dateFrom];
        $accountFilter = '';

        if ($this->accountId) {
            $accountFilter = 'AND account_id = :account_id';
            $params['account_id'] = $this->accountId;
        }

        $stmt = $this->db->prepare("
            SELECT 
                al.*,
                u.name as user_name
            FROM clone_audit_logs al
            LEFT JOIN users u ON al.user_id = u.id
            WHERE al.created_at >= :date_from 
            AND al.severity IN ('error', 'critical')
            {$accountFilter}
            ORDER BY al.created_at DESC
            LIMIT 50
        ");
        $stmt->execute($params);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($events as &$e) {
            $e['event_data'] = json_decode($e['event_data'] ?? '{}', true);
        }

        return $events;
    }

    /**
     * Detecta anomalias de comportamento
     */
    private function detectAnomalies(string $dateFrom): array
    {
        $anomalies = [];
        $params = ['date_from' => $dateFrom];
        $accountFilter = '';

        if ($this->accountId) {
            $accountFilter = 'AND account_id = :account_id';
            $params['account_id'] = $this->accountId;
        }

        // 1. Usuários com atividade anormalmente alta
        $stmt = $this->db->prepare("
            SELECT 
                user_id,
                COUNT(*) as event_count,
                AVG(count_per_hour) as avg_per_hour
            FROM (
                SELECT 
                    user_id,
                    DATE_FORMAT(created_at, '%Y-%m-%d %H:00:00') as hour,
                    COUNT(*) as count_per_hour
                FROM clone_audit_logs
                WHERE created_at >= :date_from 
                AND user_id IS NOT NULL
                {$accountFilter}
                GROUP BY user_id, hour
            ) hourly
            GROUP BY user_id
            HAVING AVG(count_per_hour) > 100
        ");
        $stmt->execute($params);
        $highActivity = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($highActivity as $user) {
            $anomalies[] = [
                'type' => 'high_activity',
                'severity' => 'warning',
                'description' => "Usuário ID {$user['user_id']} com atividade anormalmente alta",
                'details' => $user,
            ];
        }

        // 2. Muitas falhas em sequência
        $stmt = $this->db->prepare("
            SELECT 
                user_id,
                job_id,
                COUNT(*) as failure_count
            FROM clone_audit_logs
            WHERE created_at >= :date_from 
            AND event_type IN ('clone_failed', 'item_failed')
            {$accountFilter}
            GROUP BY user_id, job_id
            HAVING COUNT(*) > 10
        ");
        $stmt->execute($params);
        $highFailures = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($highFailures as $failure) {
            $anomalies[] = [
                'type' => 'high_failure_rate',
                'severity' => 'warning',
                'description' => "Job {$failure['job_id']} com muitas falhas",
                'details' => $failure,
            ];
        }

        // 3. Atividade fora do horário comercial (opcional)
        $stmt = $this->db->prepare("
            SELECT 
                user_id,
                HOUR(created_at) as hour,
                COUNT(*) as event_count
            FROM clone_audit_logs
            WHERE created_at >= :date_from 
            AND HOUR(created_at) NOT BETWEEN 6 AND 22
            {$accountFilter}
            GROUP BY user_id, HOUR(created_at)
            HAVING COUNT(*) > 20
        ");
        $stmt->execute($params);
        $offHours = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($offHours as $offHour) {
            $anomalies[] = [
                'type' => 'off_hours_activity',
                'severity' => 'info',
                'description' => "Atividade significativa fora do horário comercial",
                'details' => $offHour,
            ];
        }

        return $anomalies;
    }

    /**
     * Calcula score de compliance
     */
    private function calculateComplianceScore(array $stats, array $violations): int
    {
        $score = 100;

        // Penalizar por violações
        $score -= count($violations) * 2;

        // Penalizar por eventos críticos
        $criticalCount = ($stats['by_severity']['critical'] ?? 0);
        $errorCount = ($stats['by_severity']['error'] ?? 0);
        $score -= $criticalCount * 5;
        $score -= $errorCount * 2;

        // Bonificar por boa proporção sucesso/falha
        $totalEvents = $stats['totals']['total_events'] ?? 1;
        $failedEvents = 0;
        foreach ($stats['by_type'] as $type) {
            if (strpos($type['event_type'], 'failed') !== false) {
                $failedEvents += $type['count'];
            }
        }

        $failRate = $failedEvents / max($totalEvents, 1);
        if ($failRate < 0.05) {
            $score += 10;
        } elseif ($failRate > 0.20) {
            $score -= 15;
        }

        return max(0, min(100, $score));
    }

    /**
     * Verifica políticas de compliance ao registrar evento
     */
    private function checkCompliancePolicies(string $eventType, array $eventData, int $logId): void
    {
        $violations = [];

        // Política 1: Limite de clonagens por hora
        if ($eventType === 'clone_started') {
            $hourlyCount = $this->getHourlyCloneCount();
            if ($hourlyCount > 100) {
                $violations[] = [
                    'policy' => 'hourly_clone_limit',
                    'message' => "Limite de clonagens por hora excedido ({$hourlyCount}/100)",
                    'severity' => 'warning',
                ];
            }
        }

        // Política 2: Modificação de preço significativa
        if ($eventType === 'price_modified') {
            $priceChange = $eventData['price_change_percent'] ?? 0;
            if (abs($priceChange) > 50) {
                $violations[] = [
                    'policy' => 'significant_price_change',
                    'message' => "Alteração de preço significativa: {$priceChange}%",
                    'severity' => 'warning',
                ];
            }
        }

        // Política 3: Atividade suspeita
        if ($eventType === 'suspicious_activity') {
            $violations[] = [
                'policy' => 'suspicious_activity',
                'message' => $eventData['reason'] ?? 'Atividade suspeita detectada',
                'severity' => 'critical',
            ];
        }

        // Registrar violações
        foreach ($violations as $violation) {
            $this->recordPolicyViolation($logId, $violation);
        }
    }

    /**
     * Registra violação de política
     */
    private function recordPolicyViolation(int $auditLogId, array $violation): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO clone_policy_violations (
                audit_log_id, account_id, user_id,
                policy_name, violation_message, severity,
                created_at
            ) VALUES (
                :audit_log_id, :account_id, :user_id,
                :policy_name, :violation_message, :severity,
                NOW()
            )
        ");

        $stmt->execute([
            'audit_log_id' => $auditLogId,
            'account_id' => $this->accountId,
            'user_id' => $_SESSION['user_id'] ?? null,
            'policy_name' => $violation['policy'],
            'violation_message' => $violation['message'],
            'severity' => $violation['severity'],
        ]);
    }

    /**
     * Obtém contagem de clonagens na última hora
     */
    private function getHourlyCloneCount(): int
    {
        $params = [];
        $accountFilter = '';

        if ($this->accountId) {
            $accountFilter = 'AND account_id = :account_id';
            $params['account_id'] = $this->accountId;
        }

        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM clone_audit_logs
            WHERE event_type = 'clone_started'
            AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
            {$accountFilter}
        ");
        $stmt->execute($params);

        return (int) $stmt->fetchColumn();
    }

    /**
     * Exporta logs de auditoria para CSV
     */
    public function exportAuditLogs(array $filters = []): string
    {
        $result = $this->getAuditLogs(array_merge($filters, ['limit' => 10000]));
        
        $filename = 'audit_logs_' . date('Y-m-d_His') . '.csv';
        $filepath = __DIR__ . '/../../storage/exports/' . $filename;

        $fp = fopen($filepath, 'w');
        
        // Header
        fputcsv($fp, [
            'ID', 'Data/Hora', 'Tipo', 'Descrição', 'Severidade',
            'Usuário', 'Job ID', 'Item ID', 'IP', 'Dados'
        ]);

        foreach ($result['logs'] as $log) {
            fputcsv($fp, [
                $log['id'],
                $log['created_at'],
                $log['event_type'],
                $log['event_description'],
                $log['severity'],
                $log['user_name'] ?? 'Sistema',
                $log['job_id'] ?? '',
                $log['item_id'] ?? '',
                $log['ip_address'] ?? '',
                json_encode($log['event_data']),
            ]);
        }

        fclose($fp);

        // Log export event
        $this->logEvent('export_generated', [
            'type' => 'audit_logs',
            'filename' => $filename,
            'total_records' => count($result['logs']),
        ]);

        return $filepath;
    }

    /**
     * Estatísticas de auditoria para dashboard
     */
    public function getDashboardStats(): array
    {
        $params = [];
        $accountFilter = '';

        if ($this->accountId) {
            $accountFilter = 'AND account_id = :account_id';
            $params['account_id'] = $this->accountId;
        }

        // Eventos hoje
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN severity = 'error' OR severity = 'critical' THEN 1 ELSE 0 END) as errors
            FROM clone_audit_logs
            WHERE DATE(created_at) = CURDATE()
            {$accountFilter}
        ");
        $stmt->execute($params);
        $today = $stmt->fetch(PDO::FETCH_ASSOC);

        // Violações pendentes
        $stmt = $this->db->prepare("
            SELECT COUNT(*) FROM clone_policy_violations
            WHERE resolved_at IS NULL
            " . ($this->accountId ? 'AND account_id = :account_id' : '') . "
        ");
        $stmt->execute($this->accountId ? ['account_id' => $this->accountId] : []);
        $pendingViolations = (int) $stmt->fetchColumn();

        // Score atual
        $report = $this->generateComplianceReport('7d');

        return [
            'today_events' => (int) ($today['total'] ?? 0),
            'today_errors' => (int) ($today['errors'] ?? 0),
            'pending_violations' => $pendingViolations,
            'compliance_score' => $report['compliance_score'],
        ];
    }

    /**
     * Converte período em data
     */
    private function parsePeriod(string $period): string
    {
        $map = [
            '24h' => '1 DAY',
            '7d' => '7 DAY',
            '30d' => '30 DAY',
            '90d' => '90 DAY',
            '1y' => '1 YEAR',
        ];

        $interval = $map[$period] ?? '30 DAY';
        
        $stmt = $this->db->query("SELECT DATE_SUB(NOW(), INTERVAL {$interval}) as date_from");
        return $stmt->fetchColumn();
    }

    /**
     * Cria tabelas necessárias
     */
    private function ensureTablesExist(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_audit_logs (
                id INT AUTO_INCREMENT PRIMARY KEY,
                account_id INT NULL,
                user_id INT NULL,
                job_id INT NULL,
                item_id VARCHAR(50) NULL,
                event_type VARCHAR(50) NOT NULL,
                event_description VARCHAR(255) NULL,
                event_data JSON NULL,
                severity ENUM('info', 'warning', 'error', 'critical') DEFAULT 'info',
                ip_address VARCHAR(45) NULL,
                user_agent TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_account (account_id),
                INDEX idx_user (user_id),
                INDEX idx_job (job_id),
                INDEX idx_item (item_id),
                INDEX idx_type (event_type),
                INDEX idx_severity (severity),
                INDEX idx_created (created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS clone_policy_violations (
                id INT AUTO_INCREMENT PRIMARY KEY,
                audit_log_id INT NULL,
                account_id INT NULL,
                user_id INT NULL,
                policy_name VARCHAR(100) NOT NULL,
                violation_message TEXT NULL,
                severity ENUM('info', 'warning', 'error', 'critical') DEFAULT 'warning',
                resolved_at DATETIME NULL,
                resolved_by INT NULL,
                resolution_notes TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_account (account_id),
                INDEX idx_policy (policy_name),
                INDEX idx_resolved (resolved_at),
                FOREIGN KEY (audit_log_id) REFERENCES clone_audit_logs(id) ON DELETE SET NULL
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
