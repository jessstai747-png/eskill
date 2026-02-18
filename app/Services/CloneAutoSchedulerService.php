<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * CloneAutoSchedulerService
 * 
 * Serviço para agendamento automático de clonagens.
 * Permite configurar regras de auto-clone por seller, categoria,
 * horário e frequência.
 */
class CloneAutoSchedulerService
{
    private PDO $db;
    private int $accountId;

    // Frequências suportadas
    public const FREQ_ONCE = 'once';
    public const FREQ_HOURLY = 'hourly';
    public const FREQ_DAILY = 'daily';
    public const FREQ_WEEKLY = 'weekly';
    public const FREQ_MONTHLY = 'monthly';

    // Status do schedule
    public const STATUS_ACTIVE = 'active';
    public const STATUS_PAUSED = 'paused';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_FAILED = 'failed';

    // Tipos de trigger
    public const TRIGGER_NEW_ITEMS = 'new_items';
    public const TRIGGER_PRICE_DROP = 'price_drop';
    public const TRIGGER_STOCK_AVAILABLE = 'stock_available';
    public const TRIGGER_SCHEDULED = 'scheduled';

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    /**
     * Cria um novo agendamento de clonagem
     */
    public function createSchedule(array $config): array
    {
        $required = ['name', 'source_type', 'source_value'];
        foreach ($required as $field) {
            if (empty($config[$field])) {
                throw new \InvalidArgumentException("Campo obrigatório: $field");
            }
        }

        $stmt = $this->db->prepare("
            INSERT INTO clone_schedules (
                account_id, name, description, source_type, source_value,
                frequency, run_at_hour, run_at_minute, run_on_days,
                trigger_type, trigger_conditions, template_id,
                max_items_per_run, filters, seo_level, is_active,
                next_run_at, created_at, updated_at
            ) VALUES (
                :account_id, :name, :description, :source_type, :source_value,
                :frequency, :run_at_hour, :run_at_minute, :run_on_days,
                :trigger_type, :trigger_conditions, :template_id,
                :max_items_per_run, :filters, :seo_level, :is_active,
                :next_run_at, NOW(), NOW()
            )
        ");

        $nextRun = $this->calculateNextRun($config);

        $stmt->execute([
            'account_id' => $this->accountId,
            'name' => $config['name'],
            'description' => $config['description'] ?? null,
            'source_type' => $config['source_type'], // seller_id, category_id, search_query
            'source_value' => $config['source_value'],
            'frequency' => $config['frequency'] ?? self::FREQ_DAILY,
            'run_at_hour' => $config['run_at_hour'] ?? 3, // Default 3 AM
            'run_at_minute' => $config['run_at_minute'] ?? 0,
            'run_on_days' => json_encode($config['run_on_days'] ?? [1, 2, 3, 4, 5, 6, 7]),
            'trigger_type' => $config['trigger_type'] ?? self::TRIGGER_SCHEDULED,
            'trigger_conditions' => json_encode($config['trigger_conditions'] ?? []),
            'template_id' => $config['template_id'] ?? null,
            'max_items_per_run' => $config['max_items_per_run'] ?? 50,
            'filters' => json_encode($config['filters'] ?? []),
            'seo_level' => $config['seo_level'] ?? 'basic',
            'is_active' => $config['is_active'] ?? true,
            'next_run_at' => $nextRun?->format('Y-m-d H:i:s'),
        ]);

        $scheduleId = (int) $this->db->lastInsertId();

        $this->logScheduleAction($scheduleId, 'created', $config);

        return [
            'success' => true,
            'schedule_id' => $scheduleId,
            'next_run_at' => $nextRun?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Atualiza um agendamento
     */
    public function updateSchedule(int $scheduleId, array $config): array
    {
        $schedule = $this->getSchedule($scheduleId);
        if (!$schedule) {
            throw new \InvalidArgumentException("Agendamento não encontrado: $scheduleId");
        }

        $updates = [];
        $params = ['id' => $scheduleId, 'account_id' => $this->accountId];

        $allowedFields = [
            'name',
            'description',
            'source_type',
            'source_value',
            'frequency',
            'run_at_hour',
            'run_at_minute',
            'template_id',
            'max_items_per_run',
            'seo_level',
            'is_active'
        ];

        foreach ($allowedFields as $field) {
            if (array_key_exists($field, $config)) {
                $updates[] = "$field = :$field";
                $params[$field] = $config[$field];
            }
        }

        // Campos JSON
        if (isset($config['run_on_days'])) {
            $updates[] = "run_on_days = :run_on_days";
            $params['run_on_days'] = json_encode($config['run_on_days']);
        }

        if (isset($config['filters'])) {
            $updates[] = "filters = :filters";
            $params['filters'] = json_encode($config['filters']);
        }

        if (isset($config['trigger_conditions'])) {
            $updates[] = "trigger_conditions = :trigger_conditions";
            $params['trigger_conditions'] = json_encode($config['trigger_conditions']);
        }

        if (empty($updates)) {
            return ['success' => true, 'message' => 'Nenhuma alteração'];
        }

        $updates[] = "updated_at = NOW()";

        // Recalcular próxima execução
        $merged = array_merge($schedule, $config);
        $nextRun = $this->calculateNextRun($merged);
        if ($nextRun) {
            $updates[] = "next_run_at = :next_run_at";
            $params['next_run_at'] = $nextRun->format('Y-m-d H:i:s');
        }

        $stmt = $this->db->prepare("
            UPDATE clone_schedules 
            SET " . implode(', ', $updates) . "
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute($params);

        $this->logScheduleAction($scheduleId, 'updated', $config);

        return [
            'success' => true,
            'schedule_id' => $scheduleId,
            'next_run_at' => $nextRun?->format('Y-m-d H:i:s'),
        ];
    }

    /**
     * Pausa um agendamento
     */
    public function pauseSchedule(int $scheduleId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE clone_schedules 
            SET is_active = 0, status = 'paused', updated_at = NOW()
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute(['id' => $scheduleId, 'account_id' => $this->accountId]);

        $this->logScheduleAction($scheduleId, 'paused');

        return $stmt->rowCount() > 0;
    }

    /**
     * Resume um agendamento
     */
    public function resumeSchedule(int $scheduleId): bool
    {
        $schedule = $this->getSchedule($scheduleId);
        if (!$schedule) {
            return false;
        }

        $nextRun = $this->calculateNextRun($schedule);

        $stmt = $this->db->prepare("
            UPDATE clone_schedules 
            SET is_active = 1, status = 'active', 
                next_run_at = :next_run, updated_at = NOW()
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute([
            'id' => $scheduleId,
            'account_id' => $this->accountId,
            'next_run' => $nextRun?->format('Y-m-d H:i:s'),
        ]);

        $this->logScheduleAction($scheduleId, 'resumed');

        return $stmt->rowCount() > 0;
    }

    /**
     * Remove um agendamento
     */
    public function deleteSchedule(int $scheduleId): bool
    {
        $this->logScheduleAction($scheduleId, 'deleted');

        $stmt = $this->db->prepare("
            DELETE FROM clone_schedules 
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute(['id' => $scheduleId, 'account_id' => $this->accountId]);

        return $stmt->rowCount() > 0;
    }

    /**
     * Obtém um agendamento por ID
     */
    public function getSchedule(int $scheduleId): ?array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM clone_schedules 
            WHERE id = :id AND account_id = :account_id
        ");
        $stmt->execute(['id' => $scheduleId, 'account_id' => $this->accountId]);

        $schedule = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($schedule) {
            $schedule['run_on_days'] = json_decode($schedule['run_on_days'] ?? '[]', true);
            $schedule['filters'] = json_decode($schedule['filters'] ?? '{}', true);
            $schedule['trigger_conditions'] = json_decode($schedule['trigger_conditions'] ?? '{}', true);
        }

        return $schedule ?: null;
    }

    /**
     * Lista todos os agendamentos
     */
    public function listSchedules(array $filters = []): array
    {
        $query = "
            SELECT cs.*, ct.name as template_name,
                   (SELECT COUNT(*) FROM clone_schedule_runs WHERE schedule_id = cs.id) as total_runs,
                   (SELECT COUNT(*) FROM clone_schedule_runs WHERE schedule_id = cs.id AND status = 'completed') as successful_runs
            FROM clone_schedules cs
            LEFT JOIN clone_templates ct ON ct.id = cs.template_id
            WHERE cs.account_id = :account_id
        ";

        $params = ['account_id' => $this->accountId];

        if (isset($filters['is_active'])) {
            $query .= " AND cs.is_active = :is_active";
            $params['is_active'] = $filters['is_active'] ? 1 : 0;
        }

        if (!empty($filters['source_type'])) {
            $query .= " AND cs.source_type = :source_type";
            $params['source_type'] = $filters['source_type'];
        }

        $query .= " ORDER BY cs.next_run_at ASC";

        if (!empty($filters['limit'])) {
            $query .= " LIMIT " . (int) $filters['limit'];
        }

        $stmt = $this->db->prepare($query);
        $stmt->execute($params);

        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($schedules as &$s) {
            $s['run_on_days'] = json_decode($s['run_on_days'] ?? '[]', true);
            $s['filters'] = json_decode($s['filters'] ?? '{}', true);
        }

        return $schedules;
    }

    /**
     * Obtém agendamentos prontos para execução
     */
    public function getDueSchedules(): array
    {
        $stmt = $this->db->prepare("
            SELECT * FROM clone_schedules
            WHERE is_active = 1
            AND next_run_at <= NOW()
            AND (status IS NULL OR status = 'active')
            ORDER BY next_run_at ASC
            LIMIT 10
        ");
        $stmt->execute();

        $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($schedules as &$s) {
            $s['run_on_days'] = json_decode($s['run_on_days'] ?? '[]', true);
            $s['filters'] = json_decode($s['filters'] ?? '{}', true);
            $s['trigger_conditions'] = json_decode($s['trigger_conditions'] ?? '{}', true);
        }

        return $schedules;
    }

    /**
     * Executa um agendamento
     */
    public function executeSchedule(int $scheduleId): array
    {
        $schedule = $this->getSchedule($scheduleId);
        if (!$schedule) {
            throw new \InvalidArgumentException("Agendamento não encontrado: $scheduleId");
        }

        // Marcar como em execução
        $this->updateScheduleStatus($scheduleId, 'running');

        $runId = $this->createRun($scheduleId);

        try {
            // Buscar itens para clonar
            $items = $this->fetchSourceItems($schedule);

            if (empty($items)) {
                $this->completeRun($runId, 0, 0, 'Nenhum item encontrado');
                $this->scheduleNextRun($scheduleId, $schedule);
                return [
                    'success' => true,
                    'run_id' => $runId,
                    'items_found' => 0,
                    'message' => 'Nenhum item encontrado para clonar',
                ];
            }

            // Limitar itens
            $maxItems = $schedule['max_items_per_run'] ?? 50;
            $items = array_slice($items, 0, $maxItems);

            // Criar job de clonagem
            $jobService = new CatalogCloneService($this->accountId);

            $jobResult = $jobService->createBatchJob([
                'item_ids' => array_column($items, 'id'),
                'template_id' => $schedule['template_id'],
                'seo_level' => $schedule['seo_level'] ?? 'basic',
                'source' => 'auto_schedule',
                'schedule_id' => $scheduleId,
            ]);

            $this->updateRunJob($runId, $jobResult['job_id'] ?? null);
            $this->completeRun($runId, count($items), 0, null, $jobResult['job_id'] ?? null);

            // Agendar próxima execução
            $this->scheduleNextRun($scheduleId, $schedule);

            return [
                'success' => true,
                'run_id' => $runId,
                'job_id' => $jobResult['job_id'] ?? null,
                'items_found' => count($items),
            ];
        } catch (\Exception $e) {
            $this->completeRun($runId, 0, 0, $e->getMessage());
            $this->updateScheduleStatus($scheduleId, 'failed');

            throw $e;
        }
    }

    /**
     * Busca itens da origem configurada
     * Usa endpoints que não requerem permissões especiais
     */
    private function fetchSourceItems(array $schedule): array
    {
        $sourceType = $schedule['source_type'];
        $sourceValue = $schedule['source_value'];
        $filters = $schedule['filters'] ?? [];

        $cloneService = new CatalogCloneService($this->accountId);

        switch ($sourceType) {
            case 'seller_id':
                $result = $cloneService->listSellerItems($sourceValue, [
                    'limit' => 100,
                    'catalog_only' => $filters['catalog_only'] ?? false,
                    'brand' => $filters['brand'] ?? null,
                ]);
                return $result['items'] ?? [];

            case 'category_id':
                // Usar highlights que funciona sem permissões especiais
                $client = new MercadoLivreClient($this->accountId);
                $response = $client->get("/highlights/MLB/category/{$sourceValue}");
                $itemIds = $response['content'] ?? [];

                if (empty($itemIds)) {
                    return [];
                }

                // Buscar detalhes dos itens
                $itemsResponse = $client->get('/items', ['ids' => implode(',', array_slice($itemIds, 0, 50))]);

                $items = [];
                foreach ($itemsResponse as $itemData) {
                    if (isset($itemData['body'])) {
                        $items[] = $itemData['body'];
                    }
                }
                return $items;

            case 'search_query':
                // Para search_query, usar trends como fallback
                $client = new MercadoLivreClient($this->accountId);
                try {
                    // Tentar trends de categoria se for um ID de categoria
                    if (preg_match('/^MLB\d+$/', $sourceValue)) {
                        $response = $client->get("/highlights/MLB/category/{$sourceValue}");
                        $itemIds = $response['content'] ?? [];

                        if (!empty($itemIds)) {
                            $itemsResponse = $client->get('/items', ['ids' => implode(',', array_slice($itemIds, 0, 50))]);
                            $items = [];
                            foreach ($itemsResponse as $itemData) {
                                if (isset($itemData['body'])) {
                                    $items[] = $itemData['body'];
                                }
                            }
                            return $items;
                        }
                    }
                } catch (\Exception $e) {
                    log_warning('Erro ao buscar items de origem via search_query', [
                        'service' => 'CloneAutoSchedulerService',
                        'error' => $e->getMessage(),
                    ]);
                }
                return [];

            default:
                return [];
        }
    }

    /**
     * Calcula a próxima execução
     */
    private function calculateNextRun(array $config): ?\DateTime
    {
        $frequency = $config['frequency'] ?? self::FREQ_DAILY;
        $hour = (int) ($config['run_at_hour'] ?? 3);
        $minute = (int) ($config['run_at_minute'] ?? 0);
        $days = $config['run_on_days'] ?? [1, 2, 3, 4, 5, 6, 7];

        if (is_string($days)) {
            $days = json_decode($days, true) ?? [1, 2, 3, 4, 5, 6, 7];
        }

        // Guard against empty days array causing infinite loop
        if (empty($days)) {
            $days = [1, 2, 3, 4, 5, 6, 7];
        }

        $now = new \DateTime();
        $next = new \DateTime();
        $next->setTime($hour, $minute, 0);

        switch ($frequency) {
            case self::FREQ_ONCE:
                if ($next <= $now) {
                    return null; // Já passou
                }
                return $next;

            case self::FREQ_HOURLY:
                $next = clone $now;
                $next->modify('+1 hour');
                $next->setTime((int) $next->format('H'), $minute, 0);
                return $next;

            case self::FREQ_DAILY:
                if ($next <= $now) {
                    $next->modify('+1 day');
                }
                // Verificar se dia está nos dias permitidos
                while (!in_array((int) $next->format('N'), $days)) {
                    $next->modify('+1 day');
                }
                return $next;

            case self::FREQ_WEEKLY:
                $next->modify('next monday');
                $next->setTime($hour, $minute, 0);
                return $next;

            case self::FREQ_MONTHLY:
                $next->modify('first day of next month');
                $next->setTime($hour, $minute, 0);
                return $next;

            default:
                return $next;
        }
    }

    /**
     * Agenda próxima execução
     */
    private function scheduleNextRun(int $scheduleId, array $schedule): void
    {
        $nextRun = $this->calculateNextRun($schedule);

        $stmt = $this->db->prepare("
            UPDATE clone_schedules 
            SET next_run_at = :next_run, 
                last_run_at = NOW(),
                status = 'active',
                updated_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $scheduleId,
            'next_run' => $nextRun?->format('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Cria registro de execução
     */
    private function createRun(int $scheduleId): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO clone_schedule_runs (
                schedule_id, status, started_at
            ) VALUES (
                :schedule_id, 'running', NOW()
            )
        ");
        $stmt->execute(['schedule_id' => $scheduleId]);

        return (int) $this->db->lastInsertId();
    }

    /**
     * Atualiza job da execução
     */
    private function updateRunJob(int $runId, ?int $jobId): void
    {
        $stmt = $this->db->prepare("
            UPDATE clone_schedule_runs SET job_id = :job_id WHERE id = :id
        ");
        $stmt->execute(['id' => $runId, 'job_id' => $jobId]);
    }

    /**
     * Completa execução
     */
    private function completeRun(int $runId, int $itemsFound, int $itemsCloned, ?string $error = null, ?int $jobId = null): void
    {
        $status = $error ? 'failed' : 'completed';

        $stmt = $this->db->prepare("
            UPDATE clone_schedule_runs 
            SET status = :status,
                items_found = :items_found,
                items_cloned = :items_cloned,
                error_message = :error,
                job_id = COALESCE(:job_id, job_id),
                completed_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute([
            'id' => $runId,
            'status' => $status,
            'items_found' => $itemsFound,
            'items_cloned' => $itemsCloned,
            'error' => $error,
            'job_id' => $jobId,
        ]);
    }

    /**
     * Atualiza status do agendamento
     */
    private function updateScheduleStatus(int $scheduleId, string $status): void
    {
        $stmt = $this->db->prepare("
            UPDATE clone_schedules SET status = :status, updated_at = NOW() WHERE id = :id
        ");
        $stmt->execute(['id' => $scheduleId, 'status' => $status]);
    }

    /**
     * Registra ação no log
     */
    private function logScheduleAction(int $scheduleId, string $action, array $data = []): void
    {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO clone_schedule_logs (
                    schedule_id, account_id, action, data, created_at
                ) VALUES (
                    :schedule_id, :account_id, :action, :data, NOW()
                )
            ");
            $stmt->execute([
                'schedule_id' => $scheduleId,
                'account_id' => $this->accountId,
                'action' => $action,
                'data' => json_encode($data),
            ]);
        } catch (\Exception $e) {
            // Ignore log errors
        }
    }

    /**
     * Obtém histórico de execuções
     */
    public function getRunHistory(int $scheduleId, int $limit = 20): array
    {
        $limitSql = max(1, min((int)$limit, 200));

        $stmt = $this->db->prepare("
            SELECT csr.*, cj.status as job_status, cj.total_items, cj.completed_items
            FROM clone_schedule_runs csr
            LEFT JOIN clone_jobs cj ON cj.id = csr.job_id
            WHERE csr.schedule_id = :schedule_id
            ORDER BY csr.started_at DESC
            LIMIT {$limitSql}
        ");
        $stmt->bindValue(':schedule_id', $scheduleId, PDO::PARAM_INT);
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Estatísticas de agendamentos
     */
    public function getStats(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_schedules,
                SUM(is_active = 1) as active_schedules,
                SUM(is_active = 0) as paused_schedules
            FROM clone_schedules
            WHERE account_id = :account_id
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        $scheduleStats = $stmt->fetch(PDO::FETCH_ASSOC);

        $stmt = $this->db->prepare("
            SELECT 
                COUNT(*) as total_runs,
                SUM(csr.status = 'completed') as successful_runs,
                SUM(csr.status = 'failed') as failed_runs,
                SUM(csr.items_cloned) as total_items_cloned
            FROM clone_schedule_runs csr
            JOIN clone_schedules cs ON cs.id = csr.schedule_id
            WHERE cs.account_id = :account_id
            AND csr.started_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        $runStats = $stmt->fetch(PDO::FETCH_ASSOC);

        return [
            'schedules' => $scheduleStats,
            'runs_last_30_days' => $runStats,
        ];
    }
}
