<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * Tech Sheet Alert Service
 * 
 * Sistema avançado de alertas personalizados:
 * - Thresholds customizados
 * - Múltiplos canais (email, webhook, push)
 * - Agrupamento de alertas
 * - Cooldown para evitar spam
 */
class TechSheetAlertService
{
    private PDO $db;
    private int $accountId;
    private array $config;

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        
        $this->config = [
            'cooldown_minutes' => 60,  // Não enviar mesmo alerta antes de 1h
            'max_alerts_per_hour' => 10,
        ];
    }

    /**
     * Cria regra de alerta personalizada
     * 
     * @param array $rule
     * @return int ruleId
     */
    public function createAlertRule(array $rule): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO tech_sheet_alert_rules 
            (account_id, name, type, conditions, channels, cooldown_minutes, status, created_at)
            VALUES 
            (:account_id, :name, :type, :conditions, :channels, :cooldown, 'active', NOW())
        ");
        
        $stmt->execute([
            ':account_id' => $this->accountId,
            ':name' => $rule['name'],
            ':type' => $rule['type'],
            ':conditions' => json_encode($rule['conditions']),
            ':channels' => json_encode($rule['channels'] ?? ['email']),
            ':cooldown' => $rule['cooldown_minutes'] ?? $this->config['cooldown_minutes'],
        ]);
        
        return (int) $this->db->lastInsertId();
    }

    /**
     * Verifica e dispara alertas baseado em condições
     * 
     * @param string $type
     * @param array $data
     * @return array
     */
    public function checkAndTriggerAlerts(string $type, array $data): array
    {
        // Buscar regras ativas para este tipo
        $rules = $this->getActiveRulesForType($type);
        
        $triggeredAlerts = [];
        
        foreach ($rules as $rule) {
            if ($this->shouldTriggerAlert($rule, $data)) {
                if (!$this->isInCooldown($rule['id'])) {
                    $alert = $this->triggerAlert($rule, $data);
                    $triggeredAlerts[] = $alert;
                }
            }
        }
        
        return $triggeredAlerts;
    }

    /**
     * Verifica se alerta deve ser disparado
     */
    private function shouldTriggerAlert(array $rule, array $data): bool
    {
        $conditions = json_decode($rule['conditions'], true);
        
        foreach ($conditions as $condition) {
            $field = $condition['field'];
            $operator = $condition['operator'];
            $value = $condition['value'];
            
            if (!isset($data[$field])) {
                continue;
            }
            
            $actualValue = $data[$field];
            
            switch ($operator) {
                case '<':
                    if (!($actualValue < $value)) return false;
                    break;
                case '<=':
                    if (!($actualValue <= $value)) return false;
                    break;
                case '>':
                    if (!($actualValue > $value)) return false;
                    break;
                case '>=':
                    if (!($actualValue >= $value)) return false;
                    break;
                case '==':
                    if (!($actualValue == $value)) return false;
                    break;
                case '!=':
                    if (!($actualValue != $value)) return false;
                    break;
                case 'contains':
                    if (strpos($actualValue, $value) === false) return false;
                    break;
                case 'not_contains':
                    if (strpos($actualValue, $value) !== false) return false;
                    break;
            }
        }
        
        return true;
    }

    /**
     * Dispara alerta em todos os canais configurados
     */
    private function triggerAlert(array $rule, array $data): array
    {
        $channels = json_decode($rule['channels'], true);
        
        $alert = [
            'rule_id' => $rule['id'],
            'rule_name' => $rule['name'],
            'type' => $rule['type'],
            'data' => $data,
            'channels' => [],
            'triggered_at' => date('Y-m-d H:i:s'),
        ];
        
        // Log do alerta
        $alertId = $this->logAlert($rule['id'], $data);
        $alert['id'] = $alertId;
        
        // Enviar para cada canal
        foreach ($channels as $channel) {
            try {
                $result = $this->sendToChannel($channel, $rule, $data);
                $alert['channels'][$channel] = $result;
            } catch (\Exception $e) {
                $alert['channels'][$channel] = [
                    'success' => false,
                    'error' => $e->getMessage(),
                ];
            }
        }
        
        return $alert;
    }

    /**
     * Envia alerta para canal específico
     */
    private function sendToChannel(string $channel, array $rule, array $data): array
    {
        switch ($channel) {
            case 'email':
                return $this->sendEmailAlert($rule, $data);
                
            case 'webhook':
                return $this->sendWebhookAlert($rule, $data);
                
            case 'slack':
                return $this->sendSlackAlert($rule, $data);
                
            case 'telegram':
                return $this->sendTelegramAlert($rule, $data);
                
            default:
                throw new \Exception("Canal desconhecido: $channel");
        }
    }

    /**
     * Envia alerta por email
     */
    private function sendEmailAlert(array $rule, array $data): array
    {
        $emailService = new TechSheetEmailService($this->accountId);
        
        // Buscar destinatários configurados
        $recipients = $this->getAlertRecipients($rule['id']);
        
        if (empty($recipients)) {
            return ['success' => false, 'error' => 'Nenhum destinatário configurado'];
        }
        
        $sent = $emailService->sendCriticalAlert(
            $this->accountId,
            $recipients,
            [
                'title' => $rule['name'],
                'type' => $rule['type'],
                'data' => $data,
            ]
        );
        
        return ['success' => $sent];
    }

    /**
     * Envia alerta via webhook
     */
    private function sendWebhookAlert(array $rule, array $data): array
    {
        $webhookService = new TechSheetWebhookService($this->accountId);
        
        $results = $webhookService->notify('alert.triggered', [
            'rule_name' => $rule['name'],
            'rule_type' => $rule['type'],
            'data' => $data,
        ]);
        
        return [
            'success' => !empty($results),
            'webhooks' => $results,
        ];
    }

    /**
     * Envia alerta para Slack
     */
    private function sendSlackAlert(array $rule, array $data): array
    {
        $webhookService = new TechSheetWebhookService($this->accountId);
        
        return $webhookService->notify('alert.triggered', [
            'rule_name' => $rule['name'],
            'rule_type' => $rule['type'],
            'data' => $data,
        ]);
    }

    /**
     * Envia alerta para Telegram
     */
    private function sendTelegramAlert(array $rule, array $data): array
    {
        $webhookService = new TechSheetWebhookService($this->accountId);
        
        return $webhookService->notify('alert.triggered', [
            'rule_name' => $rule['name'],
            'rule_type' => $rule['type'],
            'data' => $data,
        ]);
    }

    /**
     * Verifica se regra está em cooldown
     */
    private function isInCooldown(int $ruleId): bool
    {
        $stmt = $this->db->prepare("
            SELECT COUNT(*) 
            FROM tech_sheet_alerts
            WHERE rule_id = :rule_id
              AND created_at >= DATE_SUB(NOW(), INTERVAL :minutes MINUTE)
        ");
        
        $stmt->execute([
            ':rule_id' => $ruleId,
            ':minutes' => $this->config['cooldown_minutes'],
        ]);
        
        return $stmt->fetchColumn() > 0;
    }

    /**
     * Registra alerta disparado
     */
    private function logAlert(int $ruleId, array $data): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO tech_sheet_alerts 
            (account_id, rule_id, data, created_at)
            VALUES 
            (:account_id, :rule_id, :data, NOW())
        ");
        
        $stmt->execute([
            ':account_id' => $this->accountId,
            ':rule_id' => $ruleId,
            ':data' => json_encode($data),
        ]);
        
        return (int) $this->db->lastInsertId();
    }

    /**
     * Busca regras ativas por tipo
     */
    private function getActiveRulesForType(string $type): array
    {
        $stmt = $this->db->prepare("
            SELECT *
            FROM tech_sheet_alert_rules
            WHERE account_id = :account_id
              AND type = :type
              AND status = 'active'
        ");
        
        $stmt->execute([
            ':account_id' => $this->accountId,
            ':type' => $type,
        ]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Busca destinatários de uma regra
     */
    private function getAlertRecipients(int $ruleId): array
    {
        $stmt = $this->db->prepare("
            SELECT email
            FROM tech_sheet_alert_recipients
            WHERE rule_id = :rule_id
              AND status = 'active'
        ");
        
        $stmt->execute([':rule_id' => $ruleId]);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * Lista regras de alerta
     */
    public function listAlertRules(array $filters = []): array
    {
        $where = ['account_id = :account_id'];
        $params = [':account_id' => $this->accountId];
        
        if (isset($filters['type'])) {
            $where[] = 'type = :type';
            $params[':type'] = $filters['type'];
        }
        
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        
        $sql = "
            SELECT 
                id,
                name,
                type,
                conditions,
                channels,
                cooldown_minutes,
                status,
                trigger_count,
                last_triggered_at,
                created_at
            FROM tech_sheet_alert_rules
            WHERE " . implode(' AND ', $where) . "
            ORDER BY created_at DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return array_map(function($row) {
            $row['conditions'] = json_decode($row['conditions'], true);
            $row['channels'] = json_decode($row['channels'], true);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Atualiza regra de alerta
     */
    public function updateAlertRule(int $ruleId, array $data): bool
    {
        $updates = [];
        $params = [':id' => $ruleId, ':account_id' => $this->accountId];
        
        if (isset($data['name'])) {
            $updates[] = 'name = :name';
            $params[':name'] = $data['name'];
        }
        
        if (isset($data['conditions'])) {
            $updates[] = 'conditions = :conditions';
            $params[':conditions'] = json_encode($data['conditions']);
        }
        
        if (isset($data['channels'])) {
            $updates[] = 'channels = :channels';
            $params[':channels'] = json_encode($data['channels']);
        }
        
        if (isset($data['status'])) {
            $updates[] = 'status = :status';
            $params[':status'] = $data['status'];
        }
        
        if (empty($updates)) {
            return false;
        }
        
        $sql = "
            UPDATE tech_sheet_alert_rules
            SET " . implode(', ', $updates) . "
            WHERE id = :id AND account_id = :account_id
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Deleta regra de alerta
     */
    public function deleteAlertRule(int $ruleId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM tech_sheet_alert_rules
            WHERE id = :id AND account_id = :account_id
        ");
        
        $stmt->execute([
            ':id' => $ruleId,
            ':account_id' => $this->accountId,
        ]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Adiciona destinatário a regra
     */
    public function addRecipient(int $ruleId, string $email): bool
    {
        $stmt = $this->db->prepare("
            INSERT INTO tech_sheet_alert_recipients 
            (rule_id, email, status, created_at)
            VALUES 
            (:rule_id, :email, 'active', NOW())
        ");
        
        $stmt->execute([
            ':rule_id' => $ruleId,
            ':email' => $email,
        ]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Remove destinatário de regra
     */
    public function removeRecipient(int $ruleId, string $email): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM tech_sheet_alert_recipients
            WHERE rule_id = :rule_id AND email = :email
        ");
        
        $stmt->execute([
            ':rule_id' => $ruleId,
            ':email' => $email,
        ]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Histórico de alertas disparados
     */
    public function getAlertHistory(array $filters = []): array
    {
        $where = ['a.account_id = :account_id'];
        $params = [':account_id' => $this->accountId];
        
        if (isset($filters['rule_id'])) {
            $where[] = 'a.rule_id = :rule_id';
            $params[':rule_id'] = $filters['rule_id'];
        }
        
        if (isset($filters['days'])) {
            $where[] = 'a.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)';
            $params[':days'] = $filters['days'];
        }
        
        $limitInput = $filters['limit'] ?? 100;
        $limitSql = max(1, min((int)$limitInput, 500));
        
        $sql = "
            SELECT 
                a.id,
                a.rule_id,
                r.name as rule_name,
                r.type as rule_type,
                a.data,
                a.created_at
            FROM tech_sheet_alerts a
            INNER JOIN tech_sheet_alert_rules r ON a.rule_id = r.id
            WHERE " . implode(' AND ', $where) . "
            ORDER BY a.created_at DESC
            LIMIT {$limitSql}
        ";
        
        $stmt = $this->db->prepare($sql);
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        
        return array_map(function($row) {
            $row['data'] = json_decode($row['data'], true);
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }
}
