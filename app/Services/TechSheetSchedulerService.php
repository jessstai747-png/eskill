<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * Tech Sheet Scheduled Jobs Manager
 * 
 * Gerencia e monitora jobs agendados:
 * - Auto-optimizer diário
 * - Email reports
 * - Análise em lote
 * - Cleanup de dados antigos
 */
class TechSheetSchedulerService
{
    private PDO $db;
    private int $accountId;
    private array $config;

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        
        $this->config = [
            'auto_optimizer' => [
                'enabled' => true,
                'schedule' => '0 2 * * *',    // 02:00 diário
                'max_items' => 100,
            ],
            'email_report' => [
                'enabled' => true,
                'schedule' => '0 8 * * *',    // 08:00 diário
            ],
            'batch_analysis' => [
                'enabled' => true,
                'schedule' => '0 */6 * * *',  // A cada 6 horas
                'batch_size' => 50,
            ],
            'cleanup' => [
                'enabled' => true,
                'schedule' => '0 3 * * 0',    // 03:00 domingo
                'days_to_keep' => 90,
            ],
        ];
    }

    /**
     * Registra um novo job agendado
     * 
     * @param string $jobType
     * @param array $config
     * @return int jobId
     */
    public function scheduleJob(string $jobType, array $config = []): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO tech_sheet_scheduled_jobs 
            (account_id, job_type, schedule_cron, config, status, created_at)
            VALUES 
            (:account_id, :job_type, :schedule, :config, 'active', NOW())
        ");
        
        $stmt->execute([
            ':account_id' => $this->accountId,
            ':job_type' => $jobType,
            ':schedule' => $config['schedule'] ?? '0 0 * * *',
            ':config' => json_encode($config),
        ]);
        
        return (int) $this->db->lastInsertId();
    }

    /**
     * Lista jobs agendados
     * 
     * @param array $filters
     * @return array
     */
    public function listJobs(array $filters = []): array
    {
        $where = ['account_id = :account_id'];
        $params = [':account_id' => $this->accountId];
        
        if (isset($filters['status'])) {
            $where[] = 'status = :status';
            $params[':status'] = $filters['status'];
        }
        
        if (isset($filters['job_type'])) {
            $where[] = 'job_type = :job_type';
            $params[':job_type'] = $filters['job_type'];
        }
        
        $sql = "
            SELECT 
                id,
                job_type,
                schedule_cron,
                config,
                status,
                last_run_at,
                next_run_at,
                last_result,
                run_count,
                created_at
            FROM tech_sheet_scheduled_jobs
            WHERE " . implode(' AND ', $where) . "
            ORDER BY created_at DESC
        ";
        
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        
        return array_map(function($row) {
            $row['config'] = json_decode($row['config'], true);
            $row['last_result'] = $row['last_result'] ? json_decode($row['last_result'], true) : null;
            return $row;
        }, $stmt->fetchAll(PDO::FETCH_ASSOC));
    }

    /**
     * Executa um job agendado
     * 
     * @param int $jobId
     * @return array resultado
     */
    public function runJob(int $jobId): array
    {
        // Buscar configuração do job
        $stmt = $this->db->prepare("
            SELECT * FROM tech_sheet_scheduled_jobs
            WHERE id = :id AND account_id = :account_id
        ");
        
        $stmt->execute([
            ':id' => $jobId,
            ':account_id' => $this->accountId,
        ]);
        
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if (!$job) {
            throw new \Exception("Job não encontrado");
        }
        
        $startTime = microtime(true);
        $result = ['success' => false];
        
        try {
            // Executar job baseado no tipo
            switch ($job['job_type']) {
                case 'auto_optimizer':
                    $result = $this->runAutoOptimizer($job);
                    break;
                    
                case 'email_report':
                    $result = $this->runEmailReport($job);
                    break;
                    
                case 'batch_analysis':
                    $result = $this->runBatchAnalysis($job);
                    break;
                    
                case 'cleanup':
                    $result = $this->runCleanup($job);
                    break;
                    
                default:
                    throw new \Exception("Tipo de job desconhecido: {$job['job_type']}");
            }
            
            $result['success'] = true;
            
        } catch (\Exception $e) {
            $result = [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
        
        $duration = microtime(true) - $startTime;
        $result['duration'] = round($duration, 3);
        
        // Atualizar registro do job
        $this->updateJobExecution($jobId, $result);
        
        return $result;
    }

    /**
     * Executa auto-optimizer
     */
    private function runAutoOptimizer(array $job): array
    {
        $config = json_decode($job['config'], true);
        $limit = $config['max_items'] ?? 100;
        
        $autoOptimizer = new TechSheetAutoOptimizerService($this->accountId);
        
        // Usa autoOptimize() com opções compatíveis
        return $autoOptimizer->autoOptimize([
            'limit' => $limit,
            'force' => true,
            'dry_run' => false,
        ]);
    }

    /**
     * Executa envio de email report
     */
    private function runEmailReport(array $job): array
    {
        $config = json_decode($job['config'], true);
        
        if (empty($config['email'])) {
            throw new \Exception("Email não configurado");
        }
        
        $emailService = new TechSheetEmailService($this->accountId);
        
        $sent = $emailService->sendDailyReport(
            $this->accountId,
            $config['email'],
            $config['name'] ?? 'Usuário'
        );
        
        return [
            'sent' => $sent,
            'email' => $config['email'],
        ];
    }

    /**
     * Executa análise em lote
     */
    private function runBatchAnalysis(array $job): array
    {
        $config = json_decode($job['config'], true);
        $batchSize = $config['batch_size'] ?? 50;

        $limitSql = max(1, min((int)$batchSize, 500));
        
        // Buscar itens pendentes de análise
        $stmt = $this->db->prepare("
            SELECT i.ml_item_id
            FROM items i
            LEFT JOIN tech_sheet_item_summary s 
                ON i.ml_item_id = s.item_id AND i.account_id = s.account_id
            WHERE i.account_id = :account_id
              AND i.status = 'active'
              AND (
                s.last_analyzed_at IS NULL 
                OR s.last_analyzed_at < DATE_SUB(NOW(), INTERVAL 7 DAY)
              )
            ORDER BY s.last_analyzed_at ASC
                        LIMIT {$limitSql}
        ");
        
        $stmt->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
        $stmt->execute();
        
        $itemIds = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        if (empty($itemIds)) {
            return [
                'analyzed' => 0,
                'message' => 'Nenhum item pendente',
            ];
        }
        
        $batchOptimizer = new TechSheetBatchOptimizerService($this->accountId);
        
        return $batchOptimizer->generateBatchSuggestions($itemIds, [
            'use_title' => true,
            'use_benchmark' => false,
        ]);
    }

    /**
     * Executa cleanup de dados antigos
     */
    private function runCleanup(array $job): array
    {
        $config = json_decode($job['config'], true);
        $daysToKeep = $config['days_to_keep'] ?? 90;
        
        $deleted = [
            'logs' => 0,
            'suggestions' => 0,
        ];
        
        // Limpar logs antigos
        $stmt = $this->db->prepare("
            DELETE FROM tech_sheet_execution_log
            WHERE account_id = :account_id
              AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        
        $stmt->execute([
            ':account_id' => $this->accountId,
            ':days' => $daysToKeep,
        ]);
        
        $deleted['logs'] = $stmt->rowCount();
        
        // Limpar sugestões rejeitadas antigas
        $stmt = $this->db->prepare("
            DELETE FROM tech_sheet_suggestions
            WHERE account_id = :account_id
              AND status = 'rejected'
              AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        
        $stmt->execute([
            ':account_id' => $this->accountId,
            ':days' => $daysToKeep,
        ]);
        
        $deleted['suggestions'] = $stmt->rowCount();
        
        return $deleted;
    }

    /**
     * Atualiza registro de execução do job
     */
    private function updateJobExecution(int $jobId, array $result): void
    {
        $stmt = $this->db->prepare("
            UPDATE tech_sheet_scheduled_jobs
            SET 
                last_run_at = NOW(),
                next_run_at = DATE_ADD(NOW(), INTERVAL 1 DAY),
                last_result = :result,
                run_count = run_count + 1
            WHERE id = :id
        ");
        
        $stmt->execute([
            ':id' => $jobId,
            ':result' => json_encode($result),
        ]);
    }

    /**
     * Pausa um job
     */
    public function pauseJob(int $jobId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tech_sheet_scheduled_jobs
            SET status = 'paused'
            WHERE id = :id AND account_id = :account_id
        ");
        
        $stmt->execute([
            ':id' => $jobId,
            ':account_id' => $this->accountId,
        ]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Reativa um job pausado
     */
    public function resumeJob(int $jobId): bool
    {
        $stmt = $this->db->prepare("
            UPDATE tech_sheet_scheduled_jobs
            SET status = 'active'
            WHERE id = :id AND account_id = :account_id
        ");
        
        $stmt->execute([
            ':id' => $jobId,
            ':account_id' => $this->accountId,
        ]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Deleta um job
     */
    public function deleteJob(int $jobId): bool
    {
        $stmt = $this->db->prepare("
            DELETE FROM tech_sheet_scheduled_jobs
            WHERE id = :id AND account_id = :account_id
        ");
        
        $stmt->execute([
            ':id' => $jobId,
            ':account_id' => $this->accountId,
        ]);
        
        return $stmt->rowCount() > 0;
    }

    /**
     * Obtém estatísticas de jobs
     */
    public function getJobsStats(): array
    {
        $stmt = $this->db->prepare("
            SELECT 
                job_type,
                COUNT(*) as total,
                SUM(CASE WHEN status = 'active' THEN 1 ELSE 0 END) as active,
                SUM(CASE WHEN status = 'paused' THEN 1 ELSE 0 END) as paused,
                AVG(run_count) as avg_runs,
                MAX(last_run_at) as last_execution
            FROM tech_sheet_scheduled_jobs
            WHERE account_id = :account_id
            GROUP BY job_type
        ");
        
        $stmt->execute([':account_id' => $this->accountId]);
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Verifica jobs prontos para executar
     * 
     * @return array jobIds
     */
    public function checkDueJobs(): array
    {
        $stmt = $this->db->prepare("
            SELECT id
            FROM tech_sheet_scheduled_jobs
            WHERE account_id = :account_id
              AND status = 'active'
              AND (
                next_run_at IS NULL 
                OR next_run_at <= NOW()
              )
        ");
        
        $stmt->execute([':account_id' => $this->accountId]);
        
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
}
