<?php

declare(strict_types=1);

namespace App\Services\Webhooks;

use App\Database;
use PDO;

/**
 * External Webhooks Integration Service
 * 
 * Manages outbound webhooks for external integrations
 */
class WebhookService
{
    private PDO $db;
    private array $config;
    
    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->config = [
            'timeout' => 30,
            'retry_attempts' => 3,
            'retry_delay' => 5 // seconds
        ];
        $this->ensureTables();
    }
    
    /**
     * Register a new webhook endpoint
     */
    public function registerWebhook(string $event, string $url, array $config = []): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO webhooks (event_type, url, config, active, created_at)
            VALUES (:event, :url, :config, 1, NOW())
        ");
        
        $stmt->execute([
            'event' => $event,
            'url' => $url,
            'config' => json_encode($config)
        ]);
        
        return (int)$this->db->lastInsertId();
    }
    
    /**
     * Trigger webhook for event
     */
    public function triggerWebhook(string $event, array $data): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM webhooks 
            WHERE event_type = :event AND active = 1
        ");
        
        $stmt->execute(['event' => $event]);
        $webhooks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $results = [];
        
        foreach ($webhooks as $webhook) {
            $results[] = $this->executeWebhook($webhook, $data);
        }
        
        return $results;
    }
    
    /**
     * Execute individual webhook
     */
    private function executeWebhook(array $webhook, array $data): array
    {
        $payload = [
            'event' => $webhook['event_type'],
            'data' => $data,
            'timestamp' => time(),
            'webhook_id' => $webhook['id']
        ];
        
        $config = json_decode($webhook['config'], true) ?? [];
        $url = $webhook['url'];
        
        // Add URL parameters if configured
        if (!empty($config['params'])) {
            $url .= '?' . http_build_query($config['params']);
        }
        
        $ch = curl_init();
        
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode($payload),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'User-Agent: Eskill-Webhook/1.0'
            ],
            CURLOPT_TIMEOUT => $this->config['timeout'],
            CURLOPT_SSL_VERIFYPEER => !($config['insecure'] ?? false)
        ]);
        
        // Add custom headers
        if (!empty($config['headers'])) {
            $headers = ['Content-Type: application/json'];
            foreach ($config['headers'] as $name => $value) {
                $headers[] = "$name: $value";
            }
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }
        
        // Add basic auth if configured
        if (!empty($config['auth'])) {
            curl_setopt($ch, CURLOPT_USERPWD, $config['auth']['username'] . ':' . $config['auth']['password']);
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $result = [
            'webhook_id' => $webhook['id'],
            'url' => $url,
            'success' => $httpCode >= 200 && $httpCode < 300,
            'http_code' => $httpCode,
            'response' => $response,
            'error' => $error ?: null,
            'executed_at' => date('Y-m-d H:i:s')
        ];
        
        // Log webhook execution
        $this->logWebhookExecution($webhook['id'], $payload, $result);
        
        // Handle retries
        if (!$result['success'] && $config['retry'] ?? true) {
            $this->scheduleRetry($webhook, $data, $result);
        }
        
        return $result;
    }
    
    /**
     * Log webhook execution
     */
    private function logWebhookExecution(int $webhookId, array $payload, array $result): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO webhook_logs (webhook_id, payload, response, http_code, success, error, created_at)
            VALUES (:webhook_id, :payload, :response, :http_code, :success, :error, NOW())
        ");
        
        $stmt->execute([
            'webhook_id' => $webhookId,
            'payload' => json_encode($payload),
            'response' => $result['response'],
            'http_code' => $result['http_code'],
            'success' => $result['success'],
            'error' => $result['error']
        ]);
    }
    
    /**
     * Schedule webhook retry
     */
    private function scheduleRetry(array $webhook, array $data, array $lastResult): void
    {
        // For now, just log the retry attempt
        // In production, this would use a queue system
        log_warning('Webhook retry scheduled after failure', ['service' => 'WebhookService', 'webhook_id' => $webhook['id'], 'error' => $lastResult['error']]);
    }
    
    /**
     * Get webhook logs
     */
    public function getWebhookLogs(int $webhookId, int $limit = 50): array
    {
        $limitSql = max(1, min((int)$limit, 500));

        $stmt = $this->db->prepare("
            SELECT * FROM webhook_logs 
            WHERE webhook_id = :webhook_id 
            ORDER BY created_at DESC 
            LIMIT {$limitSql}
        ");

        $stmt->execute([
            'webhook_id' => $webhookId,
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * List all registered webhooks
     */
    public function listWebhooks(): array
    {
        $stmt = $this->db->prepare("
            SELECT w.*, 
                   (SELECT COUNT(*) FROM webhook_logs wl WHERE wl.webhook_id = w.id) as execution_count,
                   (SELECT COUNT(*) FROM webhook_logs wl WHERE wl.webhook_id = w.id AND wl.success = 1) as success_count
            FROM webhooks w
            ORDER BY w.created_at DESC
        ");
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * Test webhook endpoint
     */
    public function testWebhook(int $webhookId): array
    {
        $testData = [
            'test' => true,
            'message' => 'This is a test webhook from Eskill',
            'timestamp' => time()
        ];
        
        $stmt = $this->db->prepare("SELECT * FROM webhooks WHERE id = :id");
        $stmt->execute(['id' => $webhookId]);
        $webhook = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$webhook) {
            throw new \InvalidArgumentException("Webhook not found: $webhookId");
        }
        
        return $this->executeWebhook($webhook, $testData);
    }
    
    /**
     * Delete webhook
     */
    public function deleteWebhook(int $webhookId): bool
    {
        $stmt = $this->db->prepare("DELETE FROM webhooks WHERE id = :id");
        return $stmt->execute(['id' => $webhookId]);
    }
    
    /**
     * Create webhook tables
     */
    private function ensureTables(): void
    {
        // Webhooks table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS webhooks (
                id INT PRIMARY KEY AUTO_INCREMENT,
                event_type VARCHAR(100) NOT NULL,
                url TEXT NOT NULL,
                config JSON,
                active BOOLEAN DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                INDEX idx_event (event_type),
                INDEX idx_active (active)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        
        // Webhook logs table
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS webhook_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                webhook_id INT NOT NULL,
                payload JSON,
                response TEXT,
                http_code INT,
                success BOOLEAN,
                error TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_webhook_created (webhook_id, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
    
    /**
     * Common webhook events
     */
    public static function getAvailableEvents(): array
    {
        return [
            'item.created' => 'Item criado',
            'item.updated' => 'Item atualizado',
            'item.deleted' => 'Item excluído',
            'order.created' => 'Pedido criado',
            'order.updated' => 'Pedido atualizado',
            'user.registered' => 'Novo usuário',
            'seo.optimized' => 'SEO otimizado',
            'audit.completed' => 'Auditoria concluída',
            'backup.completed' => 'Backup concluído',
            'system.alert' => 'Alerta do sistema'
        ];
    }
}