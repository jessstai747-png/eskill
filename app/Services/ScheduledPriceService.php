<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * Scheduled Price Service
 * 
 * Gerencia agendamento de mudanças de preço:
 * - Agendamento único ou recorrente
 * - Campanhas com início e fim
 * - Rollback automático após promoção
 * - Fila de execução com prioridade
 * 
 * @package App\Services
 */
class ScheduledPriceService
{
    private int $accountId;
    private PDO $db;
    private MercadoLivreClient $mlClient;

    // Status de agendamentos
    public const STATUS_PENDING = 'pending';
    public const STATUS_PROCESSING = 'processing';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';
    public const STATUS_CANCELLED = 'cancelled';
    public const STATUS_ROLLED_BACK = 'rolled_back';

    // Tipos de recorrência
    public const RECURRENCE_NONE = 'none';
    public const RECURRENCE_DAILY = 'daily';
    public const RECURRENCE_WEEKLY = 'weekly';
    public const RECURRENCE_MONTHLY = 'monthly';

    public function __construct(int $accountId)
    {
        $this->accountId = $accountId;
        $this->db = Database::getInstance();
        $this->mlClient = new MercadoLivreClient($accountId);
    }

    /**
     * Criar agendamento de preço
     */
    public function createSchedule(array $data): array
    {
        $required = ['item_id', 'new_price', 'scheduled_at'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Campo {$field} é obrigatório"];
            }
        }

        // Validar item existe
        $item = $this->mlClient->get("/items/{$data['item_id']}");
        if (!$item || isset($item['error'])) {
            return ['success' => false, 'message' => 'Item não encontrado no Mercado Livre'];
        }

        $currentPrice = (float)($item['price'] ?? 0);
        $newPrice = (float)$data['new_price'];

        // Validar preço
        if ($newPrice <= 0) {
            return ['success' => false, 'message' => 'Preço inválido'];
        }

        // Validar data
        $scheduledAt = strtotime($data['scheduled_at']);
        if ($scheduledAt === false || $scheduledAt < time()) {
            return ['success' => false, 'message' => 'Data de agendamento inválida ou no passado'];
        }

        $stmt = $this->db->prepare("
            INSERT INTO pricing_schedules 
            (account_id, item_id, item_title, current_price, new_price, 
             scheduled_at, rollback_at, rollback_price, recurrence_type, 
             recurrence_value, campaign_name, notes, status, created_at)
            VALUES 
            (:account_id, :item_id, :item_title, :current_price, :new_price,
             :scheduled_at, :rollback_at, :rollback_price, :recurrence_type,
             :recurrence_value, :campaign_name, :notes, :status, NOW())
        ");

        $stmt->execute([
            'account_id' => $this->accountId,
            'item_id' => $data['item_id'],
            'item_title' => $item['title'] ?? '',
            'current_price' => $currentPrice,
            'new_price' => $newPrice,
            'scheduled_at' => date('Y-m-d H:i:s', $scheduledAt),
            'rollback_at' => isset($data['rollback_at']) ? date('Y-m-d H:i:s', strtotime($data['rollback_at'])) : null,
            'rollback_price' => $data['rollback_price'] ?? $currentPrice,
            'recurrence_type' => $data['recurrence_type'] ?? self::RECURRENCE_NONE,
            'recurrence_value' => $data['recurrence_value'] ?? null,
            'campaign_name' => $data['campaign_name'] ?? null,
            'notes' => $data['notes'] ?? null,
            'status' => self::STATUS_PENDING
        ]);

        $scheduleId = $this->db->lastInsertId();

        $this->logAction($scheduleId, 'created', $data);

        return [
            'success' => true,
            'schedule_id' => $scheduleId,
            'message' => 'Agendamento criado com sucesso',
            'scheduled_for' => date('Y-m-d H:i:s', $scheduledAt)
        ];
    }

    /**
     * Criar campanha (múltiplos itens)
     */
    public function createCampaign(array $data): array
    {
        $required = ['name', 'items', 'start_at'];
        foreach ($required as $field) {
            if (empty($data[$field])) {
                return ['success' => false, 'message' => "Campo {$field} é obrigatório"];
            }
        }

        if (!is_array($data['items']) || count($data['items']) === 0) {
            return ['success' => false, 'message' => 'Lista de itens vazia'];
        }

        $this->db->beginTransaction();

        try {
            // Criar registro da campanha
            $stmt = $this->db->prepare("
                INSERT INTO pricing_campaigns
                (account_id, name, description, start_at, end_at, 
                 discount_type, discount_value, status, created_at)
                VALUES
                (:account_id, :name, :description, :start_at, :end_at,
                 :discount_type, :discount_value, 'pending', NOW())
            ");

            $stmt->execute([
                'account_id' => $this->accountId,
                'name' => $data['name'],
                'description' => $data['description'] ?? null,
                'start_at' => $data['start_at'],
                'end_at' => $data['end_at'] ?? null,
                'discount_type' => $data['discount_type'] ?? 'percent', // percent, fixed
                'discount_value' => $data['discount_value'] ?? 0
            ]);

            $campaignId = $this->db->lastInsertId();
            $schedulesCreated = [];
            $errors = [];

            foreach ($data['items'] as $itemData) {
                $itemId = is_array($itemData) ? $itemData['item_id'] : $itemData;
                $customPrice = is_array($itemData) ? ($itemData['new_price'] ?? null) : null;

                // Obter item
                $item = $this->mlClient->get("/items/{$itemId}");
                if (!$item || isset($item['error'])) {
                    $errors[] = ['item_id' => $itemId, 'error' => 'Item não encontrado'];
                    continue;
                }

                $currentPrice = (float)($item['price'] ?? 0);

                // Calcular novo preço
                if ($customPrice) {
                    $newPrice = (float)$customPrice;
                } elseif ($data['discount_type'] === 'percent') {
                    $newPrice = $currentPrice * (1 - ($data['discount_value'] / 100));
                } else {
                    $newPrice = $currentPrice - $data['discount_value'];
                }

                $newPrice = max(1, round($newPrice, 2));

                // Criar agendamento
                $scheduleStmt = $this->db->prepare("
                    INSERT INTO pricing_schedules 
                    (account_id, item_id, item_title, current_price, new_price, 
                     scheduled_at, rollback_at, rollback_price, campaign_id,
                     campaign_name, status, created_at)
                    VALUES 
                    (:account_id, :item_id, :item_title, :current_price, :new_price,
                     :scheduled_at, :rollback_at, :rollback_price, :campaign_id,
                     :campaign_name, 'pending', NOW())
                ");

                $scheduleStmt->execute([
                    'account_id' => $this->accountId,
                    'item_id' => $itemId,
                    'item_title' => $item['title'] ?? '',
                    'current_price' => $currentPrice,
                    'new_price' => $newPrice,
                    'scheduled_at' => $data['start_at'],
                    'rollback_at' => $data['end_at'] ?? null,
                    'rollback_price' => $currentPrice,
                    'campaign_id' => $campaignId,
                    'campaign_name' => $data['name']
                ]);

                $schedulesCreated[] = [
                    'schedule_id' => $this->db->lastInsertId(),
                    'item_id' => $itemId,
                    'current_price' => $currentPrice,
                    'new_price' => $newPrice
                ];
            }

            // Atualizar total de itens na campanha
            $stmt = $this->db->prepare("
                UPDATE pricing_campaigns 
                SET total_items = :total 
                WHERE id = :id
            ");
            $stmt->execute([
                'total' => count($schedulesCreated),
                'id' => $campaignId
            ]);

            $this->db->commit();

            return [
                'success' => true,
                'campaign_id' => $campaignId,
                'schedules_created' => count($schedulesCreated),
                'schedules' => $schedulesCreated,
                'errors' => $errors,
                'message' => 'Campanha criada com sucesso'
            ];

        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Erro ao criar campanha: ' . $e->getMessage()];
        }
    }

    /**
     * Listar agendamentos
     */
    public function listSchedules(array $filters = []): array
    {
        $where = ['account_id = :account_id'];
        $params = ['account_id' => $this->accountId];

        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }

        if (isset($filters['item_id'])) {
            $where[] = 'item_id = :item_id';
            $params['item_id'] = $filters['item_id'];
        }

        if (isset($filters['campaign_id'])) {
            $where[] = 'campaign_id = :campaign_id';
            $params['campaign_id'] = $filters['campaign_id'];
        }

        if (isset($filters['from_date'])) {
            $where[] = 'scheduled_at >= :from_date';
            $params['from_date'] = $filters['from_date'];
        }

        if (isset($filters['to_date'])) {
            $where[] = 'scheduled_at <= :to_date';
            $params['to_date'] = $filters['to_date'];
        }

        $whereClause = implode(' AND ', $where);

        // Whitelist ORDER BY to prevent SQL injection
        $allowedOrders = [
            'scheduled_at ASC', 'scheduled_at DESC',
            'created_at ASC', 'created_at DESC',
            'item_id ASC', 'item_id DESC',
            'new_price ASC', 'new_price DESC',
        ];
        $orderBy = in_array($filters['order_by'] ?? '', $allowedOrders, true)
            ? $filters['order_by'] : 'scheduled_at ASC';

        $limit = $filters['limit'] ?? 50;
        $offset = $filters['offset'] ?? 0;
        $limitSql = max(1, min((int)$limit, 200));
        $offsetSql = max(0, (int)$offset);

        $stmt = $this->db->prepare("
            SELECT * FROM pricing_schedules 
            WHERE {$whereClause}
            ORDER BY {$orderBy}
            LIMIT {$limitSql} OFFSET {$offsetSql}
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        $stmt->execute();
        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count total
        $countStmt = $this->db->prepare("SELECT COUNT(*) FROM pricing_schedules WHERE {$whereClause}");
        foreach ($params as $key => $value) {
            $countStmt->bindValue(":{$key}", $value);
        }
        $countStmt->execute();
        $total = $countStmt->fetchColumn();

        return [
            'success' => true,
            'schedules' => $schedules,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset
        ];
    }

    /**
     * Listar campanhas
     */
    public function listCampaigns(array $filters = []): array
    {
        $where = ['account_id = :account_id'];
        $params = ['account_id' => $this->accountId];

        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params['status'] = $filters['status'];
        }

        $whereClause = implode(' AND ', $where);

        $limit = $filters['limit'] ?? 20;
        $offset = $filters['offset'] ?? 0;
        $limitSql = max(1, min((int)$limit, 200));
        $offsetSql = max(0, (int)$offset);

        $stmt = $this->db->prepare("
            SELECT c.*, 
                   COUNT(DISTINCT s.id) as total_schedules,
                   SUM(CASE WHEN s.status = 'completed' THEN 1 ELSE 0 END) as completed_schedules
            FROM pricing_campaigns c
            LEFT JOIN pricing_schedules s ON c.id = s.campaign_id
            WHERE c.{$whereClause}
            GROUP BY c.id
            ORDER BY c.created_at DESC
            LIMIT {$limitSql} OFFSET {$offsetSql}
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue(":{$key}", $value);
        }

        $stmt->execute();
        $campaigns = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'success' => true,
            'campaigns' => $campaigns
        ];
    }

    /**
     * Obter agendamento por ID
     */
    public function getSchedule(int $scheduleId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM pricing_schedules 
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute(['id' => $scheduleId, 'account_id' => $this->accountId]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Cancelar agendamento
     */
    public function cancelSchedule(int $scheduleId): array
    {
        $schedule = $this->getSchedule($scheduleId);
        if (!$schedule) {
            return ['success' => false, 'message' => 'Agendamento não encontrado'];
        }

        if ($schedule['status'] !== self::STATUS_PENDING) {
            return ['success' => false, 'message' => 'Agendamento não pode ser cancelado'];
        }

        $stmt = $this->db->prepare("
            UPDATE pricing_schedules 
            SET status = :status, updated_at = NOW()
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute([
            'status' => self::STATUS_CANCELLED,
            'id' => $scheduleId,
            'account_id' => $this->accountId
        ]);

        $this->logAction($scheduleId, 'cancelled', []);

        return ['success' => true, 'message' => 'Agendamento cancelado'];
    }

    /**
     * Cancelar campanha inteira
     */
    public function cancelCampaign(int $campaignId): array
    {
        $this->db->beginTransaction();

        try {
            // Atualizar campanha
            $stmt = $this->db->prepare("
                UPDATE pricing_campaigns 
                SET status = 'cancelled', updated_at = NOW()
                WHERE id = :id AND account_id = :account_id
            ");
            $stmt->execute(['id' => $campaignId, 'account_id' => $this->accountId]);

            // Cancelar todos os agendamentos pendentes
            $stmt = $this->db->prepare("
                UPDATE pricing_schedules 
                SET status = 'cancelled', updated_at = NOW()
                WHERE campaign_id = :campaign_id AND status = 'pending'
            ");
            $stmt->execute(['campaign_id' => $campaignId]);

            $this->db->commit();

            return ['success' => true, 'message' => 'Campanha cancelada'];

        } catch (\Exception $e) {
            $this->db->rollBack();
            return ['success' => false, 'message' => 'Erro ao cancelar campanha'];
        }
    }

    /**
     * Executar agendamentos pendentes (worker)
     */
    public function processPendingSchedules(): array
    {
        $results = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'details' => []
        ];

        // Buscar agendamentos para executar
        $stmt = $this->db->prepare("
            SELECT * FROM pricing_schedules 
            WHERE account_id = :account_id 
            AND status = :status
            AND scheduled_at <= NOW()
            ORDER BY scheduled_at ASC
            LIMIT 100
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'status' => self::STATUS_PENDING
        ]);

        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($schedules as $schedule) {
            $results['processed']++;

            // Marcar como processando
            $this->updateStatus($schedule['id'], self::STATUS_PROCESSING);

            try {
                // Aplicar preço
                $result = $this->mlClient->put("/items/{$schedule['item_id']}", [
                    'price' => (float)$schedule['new_price']
                ]);

                if (!$result || isset($result['error'])) {
                    throw new \Exception($result['error'] ?? 'Erro ao atualizar preço');
                }

                // Sucesso
                $this->updateStatus($schedule['id'], self::STATUS_COMPLETED, [
                    'executed_at' => date('Y-m-d H:i:s'),
                    'previous_price' => $schedule['current_price'],
                    'applied_price' => $schedule['new_price']
                ]);

                $results['success']++;
                $results['details'][] = [
                    'schedule_id' => $schedule['id'],
                    'item_id' => $schedule['item_id'],
                    'status' => 'success',
                    'new_price' => $schedule['new_price']
                ];

                // Verificar recorrência
                $this->handleRecurrence($schedule);

                // Registrar no histórico
                $this->recordPriceHistory($schedule);

                $this->logAction($schedule['id'], 'executed', [
                    'new_price' => $schedule['new_price']
                ]);

            } catch (\Exception $e) {
                // Falhou
                $this->updateStatus($schedule['id'], self::STATUS_FAILED, [
                    'error' => $e->getMessage()
                ]);

                $results['failed']++;
                $results['details'][] = [
                    'schedule_id' => $schedule['id'],
                    'item_id' => $schedule['item_id'],
                    'status' => 'failed',
                    'error' => $e->getMessage()
                ];

                $this->logAction($schedule['id'], 'failed', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Processar rollbacks pendentes
     */
    public function processRollbacks(): array
    {
        $results = [
            'processed' => 0,
            'success' => 0,
            'failed' => 0,
            'details' => []
        ];

        // Buscar agendamentos completados com rollback pendente
        $stmt = $this->db->prepare("
            SELECT * FROM pricing_schedules 
            WHERE account_id = :account_id 
            AND status = :status
            AND rollback_at IS NOT NULL
            AND rollback_at <= NOW()
            AND rollback_price IS NOT NULL
            ORDER BY rollback_at ASC
            LIMIT 100
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'status' => self::STATUS_COMPLETED
        ]);

        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($schedules as $schedule) {
            $results['processed']++;

            try {
                // Aplicar rollback
                $result = $this->mlClient->put("/items/{$schedule['item_id']}", [
                    'price' => (float)$schedule['rollback_price']
                ]);

                if (!$result || isset($result['error'])) {
                    throw new \Exception($result['error'] ?? 'Erro ao fazer rollback');
                }

                // Sucesso
                $this->updateStatus($schedule['id'], self::STATUS_ROLLED_BACK, [
                    'rolled_back_at' => date('Y-m-d H:i:s')
                ]);

                $results['success']++;
                $results['details'][] = [
                    'schedule_id' => $schedule['id'],
                    'item_id' => $schedule['item_id'],
                    'status' => 'rolled_back',
                    'rollback_price' => $schedule['rollback_price']
                ];

                $this->logAction($schedule['id'], 'rolled_back', [
                    'rollback_price' => $schedule['rollback_price']
                ]);

            } catch (\Exception $e) {
                $results['failed']++;
                $results['details'][] = [
                    'schedule_id' => $schedule['id'],
                    'item_id' => $schedule['item_id'],
                    'status' => 'rollback_failed',
                    'error' => $e->getMessage()
                ];

                $this->logAction($schedule['id'], 'rollback_failed', [
                    'error' => $e->getMessage()
                ]);
            }
        }

        return $results;
    }

    /**
     * Atualizar status do agendamento
     */
    private function updateStatus(int $scheduleId, string $status, array $extra = []): void
    {
        $updates = ['status = :status', 'updated_at = NOW()'];
        $params = ['status' => $status, 'id' => $scheduleId];

        if (isset($extra['executed_at'])) {
            $updates[] = 'executed_at = :executed_at';
            $params['executed_at'] = $extra['executed_at'];
        }

        if (isset($extra['error'])) {
            $updates[] = 'error_message = :error';
            $params['error'] = $extra['error'];
        }

        $updateClause = implode(', ', $updates);

        $stmt = $this->db->prepare("
            UPDATE pricing_schedules 
            SET {$updateClause}
            WHERE id = :id
        ");
        $stmt->execute($params);
    }

    /**
     * Lidar com recorrência
     */
    private function handleRecurrence(array $schedule): void
    {
        if ($schedule['recurrence_type'] === self::RECURRENCE_NONE) {
            return;
        }

        $nextDate = match ($schedule['recurrence_type']) {
            self::RECURRENCE_DAILY => strtotime('+1 day', strtotime($schedule['scheduled_at'])),
            self::RECURRENCE_WEEKLY => strtotime('+1 week', strtotime($schedule['scheduled_at'])),
            self::RECURRENCE_MONTHLY => strtotime('+1 month', strtotime($schedule['scheduled_at'])),
            default => null
        };

        if (!$nextDate) {
            return;
        }

        // Verificar limite de recorrência
        $recurrenceValue = (int)$schedule['recurrence_value'];
        if ($recurrenceValue > 0) {
            $countStmt = $this->db->prepare("
                SELECT COUNT(*) FROM pricing_schedules 
                WHERE account_id = :account_id 
                AND item_id = :item_id 
                AND recurrence_type = :recurrence_type
                AND status IN ('completed', 'pending')
            ");
            $countStmt->execute([
                'account_id' => $this->accountId,
                'item_id' => $schedule['item_id'],
                'recurrence_type' => $schedule['recurrence_type']
            ]);

            if ($countStmt->fetchColumn() >= $recurrenceValue) {
                return; // Limite atingido
            }
        }

        // Criar próximo agendamento
        $stmt = $this->db->prepare("
            INSERT INTO pricing_schedules 
            (account_id, item_id, item_title, current_price, new_price,
             scheduled_at, rollback_at, rollback_price, recurrence_type,
             recurrence_value, campaign_name, notes, status, created_at)
            SELECT 
                account_id, item_id, item_title, new_price, new_price,
                :next_date, NULL, :rollback_price, recurrence_type,
                recurrence_value, campaign_name, notes, 'pending', NOW()
            FROM pricing_schedules WHERE id = :id
        ");

        $stmt->execute([
            'next_date' => date('Y-m-d H:i:s', $nextDate),
            'rollback_price' => $schedule['current_price'],
            'id' => $schedule['id']
        ]);
    }

    /**
     * Registrar no histórico de preços
     */
    private function recordPriceHistory(array $schedule): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO pricing_history 
            (account_id, item_id, old_price, new_price, change_type, 
             change_source, schedule_id, created_at)
            VALUES 
            (:account_id, :item_id, :old_price, :new_price, :change_type,
             'scheduled', :schedule_id, NOW())
        ");

        $changeType = $schedule['new_price'] > $schedule['current_price'] ? 'increase' : 'decrease';

        $stmt->execute([
            'account_id' => $this->accountId,
            'item_id' => $schedule['item_id'],
            'old_price' => $schedule['current_price'],
            'new_price' => $schedule['new_price'],
            'change_type' => $changeType,
            'schedule_id' => $schedule['id']
        ]);
    }

    /**
     * Obter calendário de agendamentos
     */
    public function getCalendar(string $startDate, string $endDate): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                DATE(scheduled_at) as date,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
            FROM pricing_schedules
            WHERE account_id = :account_id
            AND scheduled_at BETWEEN :start_date AND :end_date
            GROUP BY DATE(scheduled_at)
            ORDER BY date ASC
        ");

        $stmt->execute([
            'account_id' => $this->accountId,
            'start_date' => $startDate,
            'end_date' => $endDate
        ]);

        return [
            'success' => true,
            'calendar' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    /**
     * Obter agendamentos de um dia específico
     */
    public function getSchedulesForDate(string $date): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM pricing_schedules
            WHERE account_id = :account_id
            AND DATE(scheduled_at) = :date
            ORDER BY scheduled_at ASC
        ");

        $stmt->execute([
            'account_id' => $this->accountId,
            'date' => $date
        ]);

        return [
            'success' => true,
            'date' => $date,
            'schedules' => $stmt->fetchAll(PDO::FETCH_ASSOC)
        ];
    }

    /**
     * Obter resumo de agendamentos
     */
    public function getSummary(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                status,
                COUNT(*) as count
            FROM pricing_schedules
            WHERE account_id = :account_id
            GROUP BY status
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        $byStatus = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

        // Próximos agendamentos
        $nextStmt = $this->db->prepare("
            SELECT * FROM pricing_schedules
            WHERE account_id = :account_id
            AND status = 'pending'
            AND scheduled_at > NOW()
            ORDER BY scheduled_at ASC
            LIMIT 5
        ");
        $nextStmt->execute(['account_id' => $this->accountId]);
        $upcoming = $nextStmt->fetchAll(PDO::FETCH_ASSOC);

        // Campanhas ativas
        $campaignStmt = $this->db->prepare("
            SELECT COUNT(*) FROM pricing_campaigns
            WHERE account_id = :account_id
            AND status = 'active'
        ");
        $campaignStmt->execute(['account_id' => $this->accountId]);
        $activeCampaigns = $campaignStmt->fetchColumn();

        return [
            'success' => true,
            'summary' => [
                'by_status' => $byStatus,
                'total_pending' => $byStatus['pending'] ?? 0,
                'total_completed' => $byStatus['completed'] ?? 0,
                'total_failed' => $byStatus['failed'] ?? 0,
                'active_campaigns' => $activeCampaigns,
                'upcoming' => $upcoming
            ]
        ];
    }

    /**
     * Log de ação
     */
    private function logAction(int $scheduleId, string $action, array $data): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO pricing_schedule_logs (schedule_id, action, data, created_at)
            VALUES (:schedule_id, :action, :data, NOW())
        ");
        $stmt->execute([
            'schedule_id' => $scheduleId,
            'action' => $action,
            'data' => json_encode($data)
        ]);
    }
}
