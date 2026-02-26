<?php
declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;
use Exception;

/**
 * Clone Alert Notification Service
 * 
 * Gerencia alertas e notificações para o sistema de clonagem
 * - Detecta jobs stuck (>30min sem progresso)
 * - Monitora taxa de falha alta (>20%)
 * - Envia notificações por email
 * - Escalação de alertas
 * 
 * @version 1.0.0
 */
class CloneAlertNotificationService
{
    private PDO $db;
    private array $config;
    /** @var array<string, bool> */
    private array $cloneAlertsColumns = [];
    
    // Thresholds para alertas
    private const STUCK_JOB_MINUTES = 30;
    private const HIGH_FAILURE_RATE = 20.0; // 20%
    private const CRITICAL_FAILURE_RATE = 50.0; // 50%
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->cloneAlertsColumns = $this->loadCloneAlertsColumns();
        
        $this->config = [
            'smtp_enabled' => getenv('SMTP_ENABLED') === 'true',
            'smtp_host' => getenv('SMTP_HOST') ?: 'localhost',
            'smtp_port' => (int)(getenv('SMTP_PORT') ?: 587),
            'smtp_user' => getenv('SMTP_USER'),
            'smtp_pass' => getenv('SMTP_PASS'),
            'from_email' => getenv('ALERT_FROM_EMAIL') ?: 'alerts@eskill.com.br',
            'from_name' => getenv('ALERT_FROM_NAME') ?: 'eskill Clone System',
            'alert_emails' => explode(',', getenv('ALERT_TO_EMAILS') ?: 'admin@eskill.com.br'),
            'alert_cooldown' => (int)(getenv('ALERT_COOLDOWN_MINUTES') ?: 60), // Não repetir alerta antes de 1h
        ];
    }
    
    /**
     * Verifica e envia alertas para jobs stuck
     * 
     * @return array Lista de alertas criados
     */
    public function checkStuckJobs(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                job_id,
                target_account_id,
                total_items,
                processed_items,
                started_at,
                TIMESTAMPDIFF(MINUTE, updated_at, NOW()) as minutes_stuck
            FROM catalog_clone_jobs
            WHERE status = 'processing'
              AND updated_at < DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
        ");
        $stmt->execute(['minutes' => self::STUCK_JOB_MINUTES]);
        $stuckJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $alerts = [];
        
        foreach ($stuckJobs as $job) {
            // Verificar se já existe alerta recente para este job
            if ($this->hasRecentAlert('stuck_job', $job['job_id'])) {
                continue;
            }
            
            $alert = $this->createAlert(
                'stuck_job',
                'error',
                "Job {$job['job_id']} stuck",
                sprintf(
                    "Job %s está sem progresso há %d minutos. " .
                    "Processados: %d/%d itens. Iniciado em: %s",
                    $job['job_id'],
                    $job['minutes_stuck'],
                    $job['processed_items'],
                    $job['total_items'],
                    $job['started_at']
                ),
                [
                    'job_id' => $job['job_id'],
                    'minutes_stuck' => $job['minutes_stuck'],
                    'processed' => $job['processed_items'],
                    'total' => $job['total_items']
                ]
            );
            
            // Enviar notificação
            if ($this->config['smtp_enabled']) {
                $this->sendEmailAlert($alert);
            }
            
            $alerts[] = $alert;
        }
        
        return $alerts;
    }
    
    /**
     * Verifica e alerta sobre taxa de falha alta
     * 
     * @param string|null $jobId Job específico ou null para todos
     * @return array Lista de alertas criados
     */
    public function checkHighFailureRate(?string $jobId = null): array
    {
        $sql = "
            SELECT 
                job_id,
                total_items,
                successful_items,
                failed_items,
                ROUND((failed_items / NULLIF(total_items, 0)) * 100, 2) as failure_rate
            FROM catalog_clone_jobs
            WHERE status IN ('processing', 'completed')
              AND total_items > 0
              AND (failed_items / total_items) * 100 > :threshold
        ";
        
        if ($jobId) {
            $sql .= " AND job_id = :job_id";
        } else {
            $sql .= " AND updated_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)";
        }
        
        $stmt = $this->db->prepare($sql);
        $params = ['threshold' => self::HIGH_FAILURE_RATE];
        if ($jobId) {
            $params['job_id'] = $jobId;
        }
        $stmt->execute($params);
        $highFailureJobs = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $alerts = [];
        
        foreach ($highFailureJobs as $job) {
            if ($this->hasRecentAlert('high_failure_rate', $job['job_id'])) {
                continue;
            }
            
            $severity = $job['failure_rate'] >= self::CRITICAL_FAILURE_RATE ? 'critical' : 'warning';
            
            $alert = $this->createAlert(
                'high_failure_rate',
                $severity,
                "Alta taxa de falha no job {$job['job_id']}",
                sprintf(
                    "Job %s tem taxa de falha de %.1f%%. " .
                    "Sucessos: %d, Falhas: %d, Total: %d",
                    $job['job_id'],
                    $job['failure_rate'],
                    $job['successful_items'],
                    $job['failed_items'],
                    $job['total_items']
                ),
                [
                    'job_id' => $job['job_id'],
                    'failure_rate' => $job['failure_rate'],
                    'successful' => $job['successful_items'],
                    'failed' => $job['failed_items']
                ]
            );
            
            if ($this->config['smtp_enabled']) {
                $this->sendEmailAlert($alert);
            }
            
            $alerts[] = $alert;
        }
        
        return $alerts;
    }
    
    /**
     * Verifica problemas de quota/rate limit do ML
     * 
     * @return array Lista de alertas
     */
    public function checkApiQuotaIssues(): array
    {
        // Verificar erros 429 (rate limit) nas últimas 24h
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as error_count,
                error_code
            FROM catalog_clone_job_items
            WHERE error_code LIKE '%429%'
              AND processed_at > DATE_SUB(NOW(), INTERVAL 1 HOUR)
            GROUP BY error_code
            HAVING error_count > 10
        ");
        $stmt->execute();
        $quotaErrors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $alerts = [];
        
        foreach ($quotaErrors as $error) {
            if ($this->hasRecentAlert('api_quota', 'rate_limit_429')) {
                continue;
            }
            
            $alert = $this->createAlert(
                'api_quota',
                'warning',
                'Rate limit do Mercado Livre atingido',
                sprintf(
                    "Detectados %d erros 429 (rate limit) na última hora. " .
                    "O sistema está sendo limitado pela API do ML.",
                    $error['error_count']
                ),
                ['error_count' => $error['error_count'], 'error_code' => $error['error_code']]
            );
            
            if ($this->config['smtp_enabled']) {
                $this->sendEmailAlert($alert);
            }
            
            $alerts[] = $alert;
        }
        
        return $alerts;
    }
    
    /**
     * Executa todas as verificações e retorna resumo
     * 
     * @return array
     */
    public function runAllChecks(): array
    {
        return [
            'stuck_jobs' => $this->checkStuckJobs(),
            'high_failure_rate' => $this->checkHighFailureRate(),
            'api_quota_issues' => $this->checkApiQuotaIssues(),
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
    
    /**
     * Cria um alerta no banco
     * 
     * @param string $type
     * @param string $severity info|warning|error|critical
     * @param string $title
     * @param string $message
     * @param array $context
     * @return array
     */
    private function createAlert(
        string $type,
        string $severity,
        string $title,
        string $message,
        array $context = []
    ): array {
        $normalizedSeverity = $this->normalizeSeverity($severity);
        $encodedContext = json_encode($context);
        if ($encodedContext === false) {
            $encodedContext = '{}';
        }

        if (
            $this->hasCloneAlertColumn('title')
            && $this->hasCloneAlertColumn('context_json')
            && $this->hasCloneAlertColumn('triggered_at')
            && $this->hasCloneAlertColumn('notification_sent')
        ) {
            $stmt = $this->db->prepare("
                INSERT INTO clone_alerts 
                (alert_type, severity, title, message, context_json, triggered_at, notification_sent)
                VALUES (:type, :severity, :title, :message, :context, NOW(), 0)
            ");

            $stmt->execute([
                'type' => $type,
                'severity' => $normalizedSeverity,
                'title' => $title,
                'message' => $message,
                'context' => $encodedContext
            ]);
        } else {
            $contextColumn = $this->hasCloneAlertColumn('context') ? 'context' : 'context_json';
            $stmt = $this->db->prepare("
                INSERT INTO clone_alerts 
                (alert_type, severity, message, {$contextColumn})
                VALUES (:type, :severity, :message, :context)
            ");

            $stmt->execute([
                'type' => $type,
                'severity' => $normalizedSeverity,
                'message' => $message,
                'context' => $encodedContext
            ]);
        }
        
        return [
            'id' => (int) $this->db->lastInsertId(),
            'type' => $type,
            'severity' => $normalizedSeverity,
            'title' => $title,
            'message' => $message,
            'context' => $context
        ];
    }
    
    /**
     * Verifica se já existe alerta recente (cooldown)
     * 
     * @param string $type
     * @param string $identifier
     * @return bool
     */
    private function hasRecentAlert(string $type, string $identifier): bool
    {
        $contextColumn = $this->hasCloneAlertColumn('context_json') ? 'context_json' : 'context';
        $timestampColumn = $this->hasCloneAlertColumn('triggered_at') ? 'triggered_at' : 'created_at';
        $activeCondition = $this->hasCloneAlertColumn('resolved_at')
            ? 'resolved_at IS NULL'
            : ($this->hasCloneAlertColumn('acknowledged') ? 'acknowledged = 0' : '1=1');

        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM clone_alerts
            WHERE alert_type = :type
              AND {$contextColumn} LIKE :identifier
              AND {$timestampColumn} > DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
              AND {$activeCondition}
        ");
        
        $stmt->execute([
            'type' => $type,
            'identifier' => "%{$identifier}%",
            'minutes' => $this->config['alert_cooldown']
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['count'] ?? 0) > 0;
    }
    
    /**
     * Envia alerta por email
     * 
     * @param array $alert
     * @return bool
     */
    private function sendEmailAlert(array $alert): bool
    {
        try {
            $subject = "[{$alert['severity']}] {$alert['title']}";
            
            $body = "
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; }
        .header { background: #f44336; color: white; padding: 20px; }
        .header.warning { background: #ff9800; }
        .header.info { background: #2196F3; }
        .content { padding: 20px; }
        .details { background: #f5f5f5; padding: 15px; border-left: 4px solid #f44336; }
        .footer { padding: 20px; font-size: 12px; color: #666; }
    </style>
</head>
<body>
    <div class='header {$alert['severity']}'>
        <h2>{$alert['title']}</h2>
    </div>
    <div class='content'>
        <p>{$alert['message']}</p>
        <div class='details'>
            <h3>Detalhes:</h3>
            <pre>" . json_encode($alert['context'], JSON_PRETTY_PRINT) . "</pre>
        </div>
        <p><strong>Horário:</strong> " . date('d/m/Y H:i:s') . "</p>
    </div>
    <div class='footer'>
        <p>Este é um alerta automático do sistema de clonagem eskill.com.br</p>
    </div>
</body>
</html>";
            
            $headers = [
                'MIME-Version: 1.0',
                'Content-type: text/html; charset=UTF-8',
                "From: {$this->config['from_name']} <{$this->config['from_email']}>",
                'X-Mailer: PHP/' . phpversion()
            ];
            
            foreach ($this->config['alert_emails'] as $email) {
                $sent = mail(trim($email), $subject, $body, implode("\r\n", $headers));
                
                if ($sent) {
                    // Marcar como enviado quando o schema suportar a coluna.
                    if ($this->hasCloneAlertColumn('notification_sent')) {
                        $stmt = $this->db->prepare("
                            UPDATE clone_alerts 
                            SET notification_sent = 1 
                            WHERE id = :id
                        ");
                        $stmt->execute(['id' => $alert['id']]);
                    }
                }
            }
            
            return true;
        } catch (Exception $e) {
            log_error('Falha ao enviar e-mail de alerta de clone', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
    
    /**
     * Resolve (marca como resolvido) um alerta
     * 
     * @param int $alertId
     * @param int|null $userId
     * @param string|null $notes
     * @return bool
     */
    public function resolveAlert(int $alertId, ?int $userId = null, ?string $notes = null): bool
    {
        if ($this->hasCloneAlertColumn('resolved_at')) {
            $stmt = $this->db->prepare("
                UPDATE clone_alerts 
                SET resolved_at = NOW(),
                    resolved_by_user_id = :user_id,
                    resolution_notes = :notes
                WHERE id = :id
                  AND resolved_at IS NULL
            ");

            return $stmt->execute([
                'id' => $alertId,
                'user_id' => $userId,
                'notes' => $notes
            ]);
        }

        if ($this->hasCloneAlertColumn('acknowledged')) {
            $stmt = $this->db->prepare("
                UPDATE clone_alerts
                SET acknowledged = 1,
                    acknowledged_by = :user_id,
                    acknowledged_at = NOW()
                WHERE id = :id
                  AND acknowledged = 0
            ");

            return $stmt->execute([
                'id' => $alertId,
                'user_id' => $userId
            ]);
        }

        $stmt = $this->db->prepare("UPDATE clone_alerts SET id = id WHERE id = :id");
        return $stmt->execute(['id' => $alertId]);
    }
    
    /**
     * Lista alertas ativos
     * 
     * @param string|null $severity Filtrar por severidade
     * @return array
     */
    public function getActiveAlerts(?string $severity = null): array
    {
        $titleSelect = $this->hasCloneAlertColumn('title')
            ? 'title'
            : 'NULL AS title';
        $contextSelect = $this->hasCloneAlertColumn('context_json')
            ? 'context_json'
            : ($this->hasCloneAlertColumn('context') ? 'context AS context_json' : 'NULL AS context_json');
        $timestampSelect = $this->hasCloneAlertColumn('triggered_at')
            ? 'triggered_at'
            : ($this->hasCloneAlertColumn('created_at') ? 'created_at AS triggered_at' : 'NOW() AS triggered_at');
        $notificationSentSelect = $this->hasCloneAlertColumn('notification_sent')
            ? 'notification_sent'
            : '0 AS notification_sent';
        $activeCondition = $this->hasCloneAlertColumn('resolved_at')
            ? 'resolved_at IS NULL'
            : ($this->hasCloneAlertColumn('acknowledged') ? 'acknowledged = 0' : '1=1');

        $sql = "
            SELECT 
                id,
                alert_type,
                severity,
                {$titleSelect},
                message,
                {$contextSelect},
                {$timestampSelect},
                {$notificationSentSelect}
            FROM clone_alerts
            WHERE {$activeCondition}
        ";
        
        if ($severity) {
            $sql .= " AND severity = :severity";
        }
        
        $sql .= " ORDER BY 
            CASE severity
                WHEN 'critical' THEN 1
                WHEN 'error' THEN 2
                WHEN 'warning' THEN 3
                ELSE 4
            END,
            triggered_at DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        
        if ($severity) {
            $stmt->execute(['severity' => $severity]);
        } else {
            $stmt->execute();
        }
        
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Decode JSON context
        foreach ($alerts as &$alert) {
            $context = json_decode((string)($alert['context_json'] ?? ''), true);
            $alert['context'] = is_array($context) ? $context : [];
            if (!isset($alert['title']) || $alert['title'] === null || $alert['title'] === '') {
                $alert['title'] = $this->buildDefaultTitle((string)($alert['alert_type'] ?? 'alerta'));
            }
            unset($alert['context_json']);
        }
        
        return $alerts;
    }

    /**
     * @return array<string, bool>
     */
    private function loadCloneAlertsColumns(): array
    {
        try {
            $stmt = $this->db->query('SHOW COLUMNS FROM clone_alerts');
            $columns = [];
            foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
                if (!isset($row['Field'])) {
                    continue;
                }
                $columns[(string)$row['Field']] = true;
            }
            return $columns;
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function hasCloneAlertColumn(string $column): bool
    {
        return isset($this->cloneAlertsColumns[$column]);
    }

    private function normalizeSeverity(string $severity): string
    {
        $normalized = strtolower(trim($severity));
        if ($normalized === 'error') {
            $normalized = 'critical';
        }
        if (!in_array($normalized, ['info', 'warning', 'critical'], true)) {
            return 'warning';
        }
        return $normalized;
    }

    private function buildDefaultTitle(string $alertType): string
    {
        $label = str_replace('_', ' ', trim($alertType));
        if ($label === '') {
            return 'Alerta de clonagem';
        }
        return 'Alerta: ' . ucfirst($label);
    }
}
