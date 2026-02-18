<?php

namespace App\Services;

use App\Database;
use PDO;

use App\Services\OrderService;
use App\Services\ItemService;
use App\Services\CatalogCloneService;
use App\Services\MercadoLivreClient;
use App\Services\MercadoLivreWebhookService;
use App\Services\TechSheetService;
use App\Services\TechSheetAutoOptimizerService;
use App\Services\AI\SEO\BulkOptimizer;
use App\Services\EanService;
use App\Services\WebhookInboxService;
use App\Services\AutonomousAgentService;

class JobService
{
    private \PDO $db;


    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->ensureTableExists();
    }

    /**
     * Cria tabela de jobs se não existir
     */
    private function ensureTableExists(): void
    {
        try {
            $this->db->exec("
                CREATE TABLE IF NOT EXISTS jobs (
                    id INT PRIMARY KEY AUTO_INCREMENT,
                    type VARCHAR(100) NOT NULL,
                    payload JSON NOT NULL,
                    status ENUM('pending', 'processing', 'completed', 'failed') DEFAULT 'pending',
                    attempts INT DEFAULT 0,
                    max_attempts INT DEFAULT 3,
                    error_message TEXT NULL,
                    result JSON NULL,
                    scheduled_at DATETIME NULL,
                    started_at DATETIME NULL,
                    completed_at DATETIME NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    INDEX idx_status (status),
                    INDEX idx_type (type),
                    INDEX idx_scheduled_at (scheduled_at)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");

            // Para instalações antigas: garante colunas novas
            $this->ensureColumnsExist();
        } catch (\Exception $e) {
            log_error('Erro ao criar tabela jobs', [
                'service' => 'JobService',
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Garante colunas adicionais em instalações pré-existentes.
     */
    private function ensureColumnsExist(): void
    {
        try {
            $stmt = $this->db->query("SELECT DATABASE() AS db");
            $dbName = $stmt ? ($stmt->fetch(PDO::FETCH_ASSOC)['db'] ?? null) : null;
            if (!$dbName) {
                return;
            }

            $colsStmt = $this->db->prepare("
                SELECT COLUMN_NAME
                FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = :db
                  AND TABLE_NAME = 'jobs'
            ");
            $colsStmt->execute([':db' => $dbName]);
            $cols = $colsStmt->fetchAll(PDO::FETCH_COLUMN);
            $cols = is_array($cols) ? $cols : [];
            $colsMap = array_fill_keys($cols, true);

            if (!isset($colsMap['result'])) {
                $this->db->exec("ALTER TABLE jobs ADD COLUMN result JSON NULL");
            }

            if (!isset($colsMap['updated_at'])) {
                $this->db->exec("ALTER TABLE jobs ADD COLUMN updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP");
            }
        } catch (\Exception $e) {
            error_log('JobService: schema migration skipped - ' . $e->getMessage());
        }
    }

    /**
     * Adiciona um job à fila
     */
    public function dispatch(string $type, array $payload, ?\DateTime $scheduledAt = null): int
    {
        $stmt = $this->db->prepare("
            INSERT INTO jobs (type, payload, scheduled_at)
            VALUES (:type, :payload, :scheduled_at)
        ");

        $stmt->execute([
            ':type' => $type,
            ':payload' => json_encode($payload),
            ':scheduled_at' => $scheduledAt ? $scheduledAt->format('Y-m-d H:i:s') : null,
        ]);

        $jobId = (int)$this->db->lastInsertId();

        // Push to Redis Queue if not scheduled for future
        if (!$scheduledAt || $scheduledAt <= new \DateTime()) {
            try {
                $queue = new QueueService();
                $queue->push('process_job', ['job_id' => $jobId]);
            } catch (\Exception $e) {
                // Redis failure shouldn't block the request, worker fallback (polling) handles it
                log_warning('Falha ao enviar job para Redis', [
                    'service' => 'JobService',
                    'job_id' => $jobId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $jobId;
    }

    /**
     * Busca um job pelo ID
     */
    public function getJob(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM jobs WHERE id = :id");
        $stmt->execute([':id' => $id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * Busca jobs pendentes
     */
    public function getPendingJobs(int $limit = 50): array
    {
        $limitSql = max(1, min((int)$limit, 200));

        $stmt = $this->db->prepare("
            SELECT * FROM jobs
            WHERE status = 'pending'
            AND (scheduled_at IS NULL OR scheduled_at <= NOW())
            AND attempts < max_attempts
            ORDER BY created_at DESC
            LIMIT {$limitSql}
        ");
        $stmt->execute();

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Processa jobs pendentes
     */
    public function process(int $limit = 10): array
    {
        $processed = [];

        $limitSql = max(1, min((int)$limit, 200));

        // Buscar jobs pendentes ou agendados
        $stmt = $this->db->prepare("
            SELECT * FROM jobs
            WHERE status = 'pending'
            AND (scheduled_at IS NULL OR scheduled_at <= NOW())
            AND attempts < max_attempts
            ORDER BY created_at ASC
            LIMIT {$limitSql}
        ");
        $stmt->execute();
        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($jobs as $job) {
            $result = $this->processJob($job);
            $processed[] = $result;
        }

        return $processed;
    }

    /**
     * Processa um job específico
     */
    public function processJob(array $job): array
    {
        $jobId = $job['id'];
        $type = $job['type'];
        $payload = json_decode($job['payload'], true);

        // Marcar como processando
        $this->updateJobStatus($jobId, 'processing', null, new \DateTime());

        try {
            // Executar job baseado no tipo
            $result = $this->executeJob($type, $payload, $jobId);

            // Marcar como concluído
            $this->updateJobStatus($jobId, 'completed', null, null, new \DateTime(), null, $result);

            return [
                'id' => $jobId,
                'type' => $type,
                'status' => 'completed',
                'result' => $result,
            ];
        } catch (\Exception $e) {
            $attempts = (int)$job['attempts'] + 1;
            $maxAttempts = (int)$job['max_attempts'];

            if ($attempts >= $maxAttempts) {
                // Marcar como falhou
                $this->updateJobStatus($jobId, 'failed', $e->getMessage());
            } else {
                // Reagendar para tentar novamente
                $this->updateJobStatus($jobId, 'pending', $e->getMessage(), null, null, $attempts);
            }

            return [
                'id' => $jobId,
                'type' => $type,
                'status' => $attempts >= $maxAttempts ? 'failed' : 'pending',
                'error' => $e->getMessage(),
                'attempts' => $attempts,
            ];
        }
    }

    /**
     * Executa o job baseado no tipo
     */
    private function executeJob(string $type, array $payload, int $jobId = null): mixed
    {
        switch ($type) {
            case 'sync_orders':
                return $this->syncOrdersJob($payload);

            case 'sync_items':
                return $this->syncItemsJob($payload);

            case 'update_price_history':
                return $this->updatePriceHistoryJob($payload);

            case 'check_alerts':
                return $this->checkAlertsJob($payload);

            case 'catalog_clone_item':
                return $this->catalogCloneItemJob($payload, $jobId);

            case 'catalog_clone_batch':
                return $this->catalogCloneBatchJob($payload, $jobId);

            case 'gap_analysis':
                return $this->gapAnalysisJob($payload);

            case 'ai_generation':
                return $this->aiGenerationJob($payload);

            case 'tech_sheet_generate_suggestions':
                return $this->techSheetGenerateSuggestionsJob($payload);

            case 'tech_sheet_apply_approved':
                return $this->techSheetApplyApprovedJob($payload);

            case 'tech_sheet_approve_pending':
                return $this->techSheetApprovePendingJob($payload);

            case 'tech_sheet_auto_optimize':
                return $this->techSheetAutoOptimizeJob($payload);

            case 'bulk_optimize_exec':
                return $this->bulkOptimizeExecJob($payload);

            case 'ml_webhook':
                return $this->mlWebhookJob($payload);

            case 'ean_mp_webhook':
                return $this->eanMercadoPagoWebhookJob($payload);

            case 'run_agent':
                return $this->runAutonomousAgentJob($payload);

            default:
                throw new \Exception("Tipo de job desconhecido: {$type}");
        }
    }

    /**
     * Job: processar webhook do Mercado Pago (módulo EAN) e sincronizar ML.
     */
    private function eanMercadoPagoWebhookJob(array $payload): array
    {
        $eventKey = (string)($payload['event_key'] ?? '');
        $requestId = (string)($payload['request_id'] ?? '');
        $data = $payload['data'] ?? null;

        if ($eventKey === '' || !is_array($data)) {
            throw new \Exception('event_key e data são obrigatórios para ean_mp_webhook');
        }

        $inbox = new WebhookInboxService();
        $eanService = new EanService();

        try {
            $result = $eanService->processPaymentWebhook($data);
            $inbox->markProcessed('mercadopago', $eventKey, array_merge($result, [
                'processed_by' => 'job_service',
                'request_id' => $requestId,
            ]));

            $accountId = $this->resolveAccountIdByPaymentWebhookData($data);
            $syncJobs = [];

            if (
                $accountId !== null
                && in_array((string)($result['status'] ?? ''), ['confirmed', 'already_paid'], true)
            ) {
                $syncOrdersJobId = $this->dispatch('sync_orders', [
                    'account_id' => $accountId,
                    'limit' => 100,
                    'source' => 'ean_mp_webhook',
                ]);

                $syncItemsJobId = $this->dispatch('sync_items', [
                    'account_id' => $accountId,
                    'limit' => 50,
                    'source' => 'ean_mp_webhook',
                ]);

                $syncJobs = [
                    'sync_orders_job_id' => $syncOrdersJobId,
                    'sync_items_job_id' => $syncItemsJobId,
                ];
            }

            return [
                'success' => true,
                'event_key' => $eventKey,
                'request_id' => $requestId,
                'process_result' => $result,
                'ml_sync_jobs' => $syncJobs,
            ];
        } catch (\Throwable $e) {
            $inbox->markFailed('mercadopago', $eventKey, $e->getMessage());
            throw $e;
        }
    }

    /**
     * Resolve account_id pela referência de pagamento do webhook MP.
     */
    private function resolveAccountIdByPaymentWebhookData(array $data): ?int
    {
        $paymentId = (string)($data['data']['id'] ?? $data['id'] ?? '');
        if ($paymentId === '') {
            return null;
        }

        $stmt = $this->db->prepare(
            "SELECT account_id
             FROM ean_purchases
             WHERE payment_id = :payment_id OR payment_external_id = :payment_id
             ORDER BY id DESC
             LIMIT 1"
        );
        $stmt->execute(['payment_id' => $paymentId]);
        $accountId = $stmt->fetchColumn();

        return $accountId !== false ? (int)$accountId : null;
    }

    /**
     * Job: Executar Agente Autônomo (Sniper, Guardian, etc)
     */
    private function runAutonomousAgentJob(array $payload): array
    {
        $agentCode = $payload['agent'] ?? '';
        $accountId = $payload['account_id'] ?? null;

        if (empty($agentCode)) {
            throw new \Exception('Agent code is required');
        }

        $service = new AutonomousAgentService($accountId);
        return $service->runAgent($agentCode);
    }

    /**
     * Job: Processar evento de webhook Mercado Livre
     */
    private function mlWebhookJob(array $payload): array
    {
        $accountId = isset($payload['internal_account_id']) ? (int)$payload['internal_account_id'] : 0;
        if ($accountId <= 0) {
            throw new \Exception('internal_account_id é obrigatório para ml_webhook');
        }

        $webhookService = new MercadoLivreWebhookService($accountId);
        $result = $webhookService->processWebhookEvent($payload);

        if (!(bool)($result['success'] ?? false)) {
            throw new \Exception((string)($result['error'] ?? 'Erro desconhecido no processamento de webhook'));
        }

        return [
            'account_id' => $accountId,
            'event_hash' => $payload['event_hash'] ?? null,
            'topic' => $payload['topic'] ?? null,
            'resource' => $payload['resource'] ?? null,
            'event_id' => $result['event_id'] ?? null,
            'success' => true,
        ];
    }

    /**
     * Processa um job batch de clonagem (tabela catalog_clone_jobs/catalog_clone_job_items).
     * Estratégia: processa um pequeno lote por execução e re-despacha se ainda houver pendências.
     */
    private function catalogCloneBatchJob(array $payload, ?int $jobId = null): array
    {
        $batchJobId = (string)($payload['batch_job_id'] ?? '');
        if ($batchJobId === '') {
            throw new \InvalidArgumentException('batch_job_id é obrigatório');
        }

        $stmtJob = $this->db->prepare('SELECT * FROM catalog_clone_jobs WHERE job_id = :job_id');
        $stmtJob->execute([':job_id' => $batchJobId]);
        $job = $stmtJob->fetch(PDO::FETCH_ASSOC);

        if (!$job) {
            throw new \RuntimeException('Job batch não encontrado: ' . $batchJobId);
        }

        $status = $job['status'] ?? 'pending';
        if (in_array($status, ['completed', 'failed', 'cancelled'], true)) {
            return [
                'status' => 'noop',
                'batch_job_id' => $batchJobId,
                'message' => 'Job batch já finalizado (' . $status . ')'
            ];
        }

        // Marcar como processing na primeira execução
        if (in_array($status, ['pending', 'queued'], true)) {
            $stmtStart = $this->db->prepare("
                UPDATE catalog_clone_jobs
                SET status = 'processing',
                    started_at = IFNULL(started_at, NOW())
                WHERE job_id = :job_id
            ");
            $stmtStart->execute([':job_id' => $batchJobId]);
        }

        $targetAccountId = (int)($job['target_account_id'] ?? 0);
        $sourceAccountId = !empty($job['source_account_id']) ? (int)$job['source_account_id'] : null;
        $options = [];
        if (!empty($job['options'])) {
            $decoded = json_decode((string)$job['options'], true);
            if (is_array($decoded)) {
                $options = $decoded;
            }
        }

        // Extrair pricing/stock do options quando vierem aninhados
        $pricingStrategy = $options['pricing_strategy'] ?? null;
        $stockStrategy = $options['stock_strategy'] ?? null;

        // Tamanho do lote por execução (evita timeouts em batch grande)
        $batchSize = (int)($payload['batch_size'] ?? 3);
        $batchSize = max(1, min($batchSize, 10));

        // Selecionar pendências com lock leve
        $this->db->beginTransaction();
        try {
            $batchSizeSql = max(1, min(10, (int)$batchSize));
            $stmtItems = $this->db->prepare("
                SELECT id, source_item_id, source_snapshot, attempts
                FROM catalog_clone_job_items
                WHERE job_id = :job_id
                  AND status = 'pending'
                ORDER BY id ASC
                                LIMIT {$batchSizeSql}
                FOR UPDATE
            ");
            $stmtItems->bindValue(':job_id', $batchJobId);
            $stmtItems->execute();
            $items = $stmtItems->fetchAll(PDO::FETCH_ASSOC);

            if (empty($items)) {
                $this->db->commit();
                // Se não há pendências, finalizar se tudo processado
                $stmtPending = $this->db->prepare("SELECT COUNT(*) FROM catalog_clone_job_items WHERE job_id = :job_id AND status IN ('pending','processing')");
                $stmtPending->execute([':job_id' => $batchJobId]);
                $remaining = (int)$stmtPending->fetchColumn();

                if ($remaining === 0) {
                    $stmtFinish = $this->db->prepare("
                        UPDATE catalog_clone_jobs
                        SET status = 'completed',
                            completed_at = IFNULL(completed_at, NOW())
                        WHERE job_id = :job_id
                    ");
                    $stmtFinish->execute([':job_id' => $batchJobId]);
                }

                return [
                    'status' => 'ok',
                    'batch_job_id' => $batchJobId,
                    'processed_now' => 0,
                    'remaining' => $remaining,
                    'message' => $remaining === 0 ? 'Job batch finalizado' : 'Sem itens pendentes no momento'
                ];
            }

            $ids = array_map(static fn($r) => (int)$r['id'], $items);
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $stmtMark = $this->db->prepare("
                UPDATE catalog_clone_job_items
                SET status = 'processing',
                    attempts = attempts + 1,
                    updated_at = NOW()
                WHERE id IN ($placeholders)
            ");
            $stmtMark->execute($ids);

            $this->db->commit();
        } catch (\Throwable $e) {
            $this->db->rollBack();
            throw $e;
        }

        $cloneService = new CatalogCloneService();
        $sourceClient = $sourceAccountId ? new MercadoLivreClient($sourceAccountId) : new MercadoLivreClient();

        $processedNow = 0;
        $continuationNeeded = false;

        foreach ($items as $row) {
            $jobItemId = (int)$row['id'];
            $sourceItemId = (string)$row['source_item_id'];
            $snapshot = null;

            // Capturar snapshot/brand/is_catalog (para reporting/facets)
            if (!empty($row['source_snapshot'])) {
                $decoded = json_decode((string)$row['source_snapshot'], true);
                if (is_array($decoded)) {
                    $snapshot = $decoded;
                }
            }

            if (!$snapshot) {
                $itemData = $sourceClient->get("/items/{$sourceItemId}");
                if (!isset($itemData['error'])) {
                    $snapshot = $itemData;
                }
            }

            $isCatalog = 0;
            $brand = null;
            if (is_array($snapshot)) {
                $isCatalog = !empty($snapshot['catalog_product_id']) ? 1 : 0;
                $brand = $this->extractBrandFromAttributes($snapshot['attributes'] ?? []);

                $stmtSnap = $this->db->prepare("
                    UPDATE catalog_clone_job_items
                    SET source_snapshot = IFNULL(source_snapshot, :snap),
                        is_catalog = :is_catalog,
                        brand = :brand
                    WHERE id = :id
                ");
                $stmtSnap->execute([
                    ':snap' => json_encode($snapshot),
                    ':is_catalog' => $isCatalog,
                    ':brand' => $brand,
                    ':id' => $jobItemId,
                ]);
            }

            // Montar parâmetros de clonagem
            $params = [
                'source_item_id' => $sourceItemId,
                'target_account_id' => $targetAccountId,
                'source_account_id' => $sourceAccountId,
                'options' => $options,
                'job_id' => $jobId,
            ];

            if (!empty($job['template_slug'])) {
                $params['template_slug'] = $job['template_slug'];
            }
            if (is_array($pricingStrategy)) {
                $params['pricing_strategy'] = $pricingStrategy;
            }
            if (is_array($stockStrategy)) {
                $params['stock_strategy'] = $stockStrategy;
            }

            $result = null;
            try {
                $result = $cloneService->cloneItem($params);
            } catch (\Throwable $e) {
                $result = [
                    'status' => 'error',
                    'message' => $e->getMessage(),
                ];
            }

            $processedNow++;
            $processedAt = (new \DateTime())->format('Y-m-d H:i:s');

            $itemStatus = $result['status'] ?? 'error';
            $message = (string)($result['message'] ?? '');
            $targetItemId = $result['target_item_id'] ?? null;

            if ($itemStatus === 'success') {
                $stmtOk = $this->db->prepare("
                    UPDATE catalog_clone_job_items
                    SET status = 'completed',
                        target_item_id = :target_item_id,
                        error_message = NULL,
                        processed_at = :processed_at,
                        result = :result
                    WHERE id = :id
                ");
                $stmtOk->execute([
                    ':target_item_id' => $targetItemId,
                    ':processed_at' => $processedAt,
                    ':result' => json_encode($result),
                    ':id' => $jobItemId,
                ]);

                $stmtInc = $this->db->prepare("
                    UPDATE catalog_clone_jobs
                    SET processed_items = processed_items + 1,
                        successful_items = successful_items + 1
                    WHERE job_id = :job_id
                ");
                $stmtInc->execute([':job_id' => $batchJobId]);
            } elseif ($itemStatus === 'skipped_duplicate') {
                $stmtSkip = $this->db->prepare("
                    UPDATE catalog_clone_job_items
                    SET status = 'skipped',
                        error_message = :error_message,
                        processed_at = :processed_at,
                        result = :result
                    WHERE id = :id
                ");
                $stmtSkip->execute([
                    ':error_message' => $message,
                    ':processed_at' => $processedAt,
                    ':result' => json_encode($result),
                    ':id' => $jobItemId,
                ]);

                $stmtInc = $this->db->prepare("
                    UPDATE catalog_clone_jobs
                    SET processed_items = processed_items + 1,
                        skipped_items = skipped_items + 1
                    WHERE job_id = :job_id
                ");
                $stmtInc->execute([':job_id' => $batchJobId]);
            } else {
                $stmtFail = $this->db->prepare("
                    UPDATE catalog_clone_job_items
                    SET status = 'failed',
                        error_message = :error_message,
                        processed_at = :processed_at,
                        result = :result
                    WHERE id = :id
                ");
                $stmtFail->execute([
                    ':error_message' => $message,
                    ':processed_at' => $processedAt,
                    ':result' => json_encode($result),
                    ':id' => $jobItemId,
                ]);

                $stmtInc = $this->db->prepare("
                    UPDATE catalog_clone_jobs
                    SET processed_items = processed_items + 1,
                        failed_items = failed_items + 1
                    WHERE job_id = :job_id
                ");
                $stmtInc->execute([':job_id' => $batchJobId]);
            }
        }

        // Verificar se ainda há pendências e re-despachar continuação
        $stmtRemain = $this->db->prepare("SELECT COUNT(*) FROM catalog_clone_job_items WHERE job_id = :job_id AND status = 'pending'");
        $stmtRemain->execute([':job_id' => $batchJobId]);
        $remainingPending = (int)$stmtRemain->fetchColumn();

        if ($remainingPending > 0) {
            $continuationNeeded = true;
            $this->dispatch('catalog_clone_batch', ['batch_job_id' => $batchJobId, 'batch_size' => $batchSize], new \DateTime('+2 seconds'));
        }

        if ($remainingPending === 0) {
            $stmtFinish = $this->db->prepare("
                UPDATE catalog_clone_jobs
                SET status = 'completed',
                    completed_at = IFNULL(completed_at, NOW())
                WHERE job_id = :job_id
            ");
            $stmtFinish->execute([':job_id' => $batchJobId]);
        }

        return [
            'status' => 'ok',
            'batch_job_id' => $batchJobId,
            'processed_now' => $processedNow,
            'remaining_pending' => $remainingPending,
            'continuation_scheduled' => $continuationNeeded,
        ];
    }

    private function extractBrandFromAttributes(array $attributes): ?string
    {
        foreach ($attributes as $attr) {
            $id = (string)($attr['id'] ?? '');
            if (in_array($id, ['BRAND', 'MARCA', 'brand'], true)) {
                $name = $attr['value_name'] ?? null;
                return $name ? (string)$name : null;
            }
        }
        return null;
    }

    /**
     * Job: Ficha Técnica - Aprovar pendentes por confiança (batch)
     *
     * Payload:
     * - account_id (int) obrigatório
     * - user_id (int) obrigatório
     * - item_ids (string[]) obrigatório
     * - min_confidence (int) opcional (0-100)
     */
    private function techSheetApprovePendingJob(array $payload): array
    {
        $accountId = (int)($payload['account_id'] ?? 0);
        if ($accountId <= 0) {
            throw new \Exception('account_id é obrigatório para tech_sheet_approve_pending');
        }

        $userId = (int)($payload['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new \Exception('user_id é obrigatório para tech_sheet_approve_pending');
        }

        $itemIds = $payload['item_ids'] ?? null;
        if (!is_array($itemIds) || !$itemIds) {
            throw new \Exception('item_ids é obrigatório (array)');
        }

        $itemIds = array_values(array_unique(array_filter(array_map('strval', $itemIds))));
        if (!$itemIds) {
            throw new \Exception('item_ids vazio após sanitização');
        }

        if (count($itemIds) > 200) {
            throw new \Exception('Limite excedido: máximo 200 item_ids por job');
        }

        $minConfidence = isset($payload['min_confidence']) ? (int)$payload['min_confidence'] : 85;
        if ($minConfidence < 0) {
            $minConfidence = 0;
        }
        if ($minConfidence > 100) {
            $minConfidence = 100;
        }

        $service = new TechSheetService($accountId);
        $processed = 0;
        $successful = 0;
        $failed = 0;
        $approvedTotal = 0;
        $failures = [];

        foreach ($itemIds as $itemId) {
            $processed++;
            try {
                $res = $service->approvePendingByConfidence($itemId, $userId, $minConfidence);
                if (($res['success'] ?? false) === true) {
                    $successful++;
                    $approvedTotal += (int)($res['approved'] ?? 0);
                } else {
                    $failed++;
                    if (count($failures) < 20) {
                        $failures[] = [
                            'item_id' => $itemId,
                            'error' => $res['error'] ?? 'Falha ao aprovar pendentes',
                        ];
                    }
                }
            } catch (\Exception $e) {
                $failed++;
                if (count($failures) < 20) {
                    $failures[] = [
                        'item_id' => $itemId,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return [
            'success' => true,
            'account_id' => $accountId,
            'min_confidence' => $minConfidence,
            'processed_items' => $processed,
            'successful_items' => $successful,
            'failed_items' => $failed,
            'suggestions_approved_total' => $approvedTotal,
            'failures' => $failures,
        ];
    }

    /**
     * Job: Ficha Técnica - Gerar sugestões (batch)
     *
     * Payload:
     * - account_id (int) obrigatório
     * - item_ids (string[]) obrigatório
     */
    private function techSheetGenerateSuggestionsJob(array $payload): array
    {
        $accountId = (int)($payload['account_id'] ?? 0);
        if ($accountId <= 0) {
            throw new \Exception('account_id é obrigatório para tech_sheet_generate_suggestions');
        }

        $itemIds = $payload['item_ids'] ?? null;
        if (!is_array($itemIds) || !$itemIds) {
            throw new \Exception('item_ids é obrigatório (array)');
        }

        $itemIds = array_values(array_unique(array_filter(array_map('strval', $itemIds))));
        if (!$itemIds) {
            throw new \Exception('item_ids vazio após sanitização');
        }

        // Guardrail para evitar jobs gigantescos em uma única execução
        if (count($itemIds) > 200) {
            throw new \Exception('Limite excedido: máximo 200 item_ids por job');
        }

        $service = new TechSheetService($accountId);
        $processed = 0;
        $successful = 0;
        $failed = 0;
        $createdTotal = 0;
        $failures = [];

        foreach ($itemIds as $itemId) {
            $processed++;
            try {
                $res = $service->generateSuggestions($itemId);
                if (($res['success'] ?? false) === true) {
                    $successful++;
                    $createdTotal += (int)($res['created'] ?? 0);
                } else {
                    $failed++;
                    if (count($failures) < 20) {
                        $failures[] = [
                            'item_id' => $itemId,
                            'error' => $res['error'] ?? 'Falha ao gerar sugestões',
                        ];
                    }
                }
            } catch (\Exception $e) {
                $failed++;
                if (count($failures) < 20) {
                    $failures[] = [
                        'item_id' => $itemId,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return [
            'success' => true,
            'account_id' => $accountId,
            'processed_items' => $processed,
            'successful_items' => $successful,
            'failed_items' => $failed,
            'suggestions_created_total' => $createdTotal,
            'failures' => $failures,
        ];
    }

    /**
     * Job: Ficha Técnica - Aplicar sugestões aprovadas (batch)
     *
     * Payload:
     * - account_id (int) obrigatório
     * - user_id (int) obrigatório (para auditoria)
     * - item_ids (string[]) obrigatório
     */
    private function techSheetApplyApprovedJob(array $payload): array
    {
        $accountId = (int)($payload['account_id'] ?? 0);
        if ($accountId <= 0) {
            throw new \Exception('account_id é obrigatório para tech_sheet_apply_approved');
        }

        $userId = (int)($payload['user_id'] ?? 0);
        if ($userId <= 0) {
            throw new \Exception('user_id é obrigatório para tech_sheet_apply_approved');
        }

        $itemIds = $payload['item_ids'] ?? null;
        if (!is_array($itemIds) || !$itemIds) {
            throw new \Exception('item_ids é obrigatório (array)');
        }

        $itemIds = array_values(array_unique(array_filter(array_map('strval', $itemIds))));
        if (!$itemIds) {
            throw new \Exception('item_ids vazio após sanitização');
        }

        if (count($itemIds) > 200) {
            throw new \Exception('Limite excedido: máximo 200 item_ids por job');
        }

        $service = new TechSheetService($accountId);
        $processed = 0;
        $successful = 0;
        $failed = 0;
        $appliedTotal = 0;
        $failures = [];

        foreach ($itemIds as $itemId) {
            $processed++;
            try {
                $res = $service->applyApproved($itemId, $userId);
                if (($res['success'] ?? false) === true) {
                    $successful++;
                    $appliedTotal += (int)($res['applied'] ?? 0);
                } else {
                    $failed++;
                    if (count($failures) < 20) {
                        $failures[] = [
                            'item_id' => $itemId,
                            'error' => $res['error'] ?? 'Falha ao aplicar no ML',
                        ];
                    }
                }
            } catch (\Exception $e) {
                $failed++;
                if (count($failures) < 20) {
                    $failures[] = [
                        'item_id' => $itemId,
                        'error' => $e->getMessage(),
                    ];
                }
            }
        }

        return [
            'success' => true,
            'account_id' => $accountId,
            'processed_items' => $processed,
            'successful_items' => $successful,
            'failed_items' => $failed,
            'attributes_applied_total' => $appliedTotal,
            'failures' => $failures,
        ];
    }

    /**
     * Job: Ficha Técnica - Auto-optimize (aplica sugestões de alta confiança)
     *
     * Payload:
     * - account_id (int) obrigatório
     * - options (array) opcional: limit, force, dry_run
     */
    private function techSheetAutoOptimizeJob(array $payload): array
    {
        $accountId = (int)($payload['account_id'] ?? 0);
        if ($accountId <= 0) {
            throw new \Exception('account_id é obrigatório para tech_sheet_auto_optimize');
        }

        $options = $payload['options'] ?? [];

        // Força execução pois foi disparado explicitamente
        $options['force'] = $options['force'] ?? true;
        $options['limit'] = min($options['limit'] ?? 100, 200);
        $options['dry_run'] = $options['dry_run'] ?? false;

        $service = new TechSheetAutoOptimizerService($accountId);
        $result = $service->autoOptimize($options);

        return [
            'success' => $result['success'] ?? false,
            'account_id' => $accountId,
            'dry_run' => $result['dry_run'] ?? false,
            'total_eligible' => $result['results']['total_eligible'] ?? 0,
            'processed' => $result['results']['processed'] ?? 0,
            'approved' => $result['results']['approved'] ?? 0,
            'applied' => $result['results']['applied'] ?? 0,
            'errors' => $result['results']['errors'] ?? 0,
            'error' => $result['error'] ?? null,
        ];
    }

    /**
     * Job: Sincronizar pedidos
     */
    private function syncOrdersJob(array $payload): array
    {
        $accountId = $payload['account_id'] ?? null;

        if (!$accountId) {
            throw new \Exception('account_id é obrigatório para sync_orders');
        }

        $orderService = new OrderService($accountId);
        $limit = (int)($payload['limit'] ?? 100);
        $result = $orderService->syncOrders(null, $limit);

        return [
            'account_id' => $accountId,
            'synced' => $result['synced'] ?? 0,
            'errors' => $result['errors'] ?? 0,
        ];
    }

    /**
     * Job: Sincronizar anúncios
     */
    private function syncItemsJob(array $payload): array
    {
        $accountId = $payload['account_id'] ?? null;

        if (!$accountId) {
            throw new \Exception('account_id é obrigatório para sync_items');
        }

        $itemService = new ItemService($accountId);
        $limit = (int)($payload['limit'] ?? 50);
        $result = $itemService->syncItems($limit);

        return [
            'account_id' => $accountId,
            'success' => $result['success'] ?? false,
            'synced' => $result['synced'] ?? 0,
            'errors' => $result['errors'] ?? 0,
            'total_found' => $result['total_found'] ?? null,
        ];
    }

    /**
     * Job: Atualizar histórico de preços
     */
    private function updatePriceHistoryJob(array $payload): array
    {
        $categoryId = $payload['category_id'] ?? null;
        $brand = $payload['brand'] ?? null;

        if (!$categoryId || !$brand) {
            throw new \Exception('category_id e brand são obrigatórios');
        }

        $searchService = new SearchService();
        $analysis = $searchService->analyzeListings($categoryId, $brand);

        // Registrar histórico de preços
        if (isset($analysis['prices']['avg'])) {
            $priceHistoryService = new PriceHistoryService();
            // API atual do service: faz a análise e grava o histórico
            $priceHistoryService->recordPriceHistory((string)$categoryId, (string)$brand);
        }

        return [
            'category_id' => $categoryId,
            'brand' => $brand,
            'avg_price' => $analysis['prices']['avg'] ?? null,
        ];
    }

    /**
     * Job: Verificar alertas
     */
    private function checkAlertsJob(array $payload): array
    {
        $alertService = new AlertService();
        $result = $alertService->checkAllAlerts();

        return [
            'alerts_checked' => $result['checked'] ?? 0,
            'alerts_triggered' => $result['triggered'] ?? 0,
        ];
    }

    /**
     * Atualiza status de um job
     */
    private function updateJobStatus(
        int $jobId,
        string $status,
        ?string $errorMessage = null,
        ?\DateTime $startedAt = null,
        ?\DateTime $completedAt = null,
        ?int $attempts = null,
        mixed $result = null
    ): void {
        $updates = ['status = :status'];
        $params = [':status' => $status, ':id' => $jobId];

        if ($errorMessage !== null) {
            $updates[] = 'error_message = :error_message';
            $params[':error_message'] = $errorMessage;
        }

        if ($startedAt !== null) {
            $updates[] = 'started_at = :started_at';
            $params[':started_at'] = $startedAt->format('Y-m-d H:i:s');
        }

        if ($completedAt !== null) {
            $updates[] = 'completed_at = :completed_at';
            $params[':completed_at'] = $completedAt->format('Y-m-d H:i:s');
        }

        if ($attempts !== null) {
            $updates[] = 'attempts = :attempts';
            $params[':attempts'] = $attempts;
        }

        if ($result !== null) {
            $updates[] = 'result = :result';
            $encoded = json_encode($result, JSON_UNESCAPED_UNICODE);
            $params[':result'] = $encoded === false ? null : $encoded;
        }

        $sql = "UPDATE jobs SET " . implode(', ', $updates) . " WHERE id = :id";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
    }

    /**
     * Retorna detalhes do job para consumo por UI/polling.
     * Não inclui payload para evitar vazamento de dados sensíveis.
     */
    public function getJobPublic(int $id): ?array
    {
        $stmt = $this->db->prepare("SELECT id, type, status, attempts, max_attempts, error_message, started_at, completed_at, created_at, updated_at, result FROM jobs WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $job = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$job) {
            return null;
        }

        if (!empty($job['result'])) {
            $decoded = json_decode($job['result'], true);
            $job['result'] = is_array($decoded) ? $decoded : $job['result'];
        } else {
            $job['result'] = null;
        }

        return $job;
    }

    /**
     * Job: Clonar item de catálogo
     */
    private function catalogCloneItemJob(array $payload, ?int $jobId = null): array
    {
        $service = new CatalogCloneService();

        // Injetar o job_id no payload para tracking
        $payload['job_id'] = $jobId;

        // O payload do job deve corresponder exatamente ao que o service espera
        // source_account_id, source_item_id, target_account_id, pricing_strategy, stock_strategy

        $result = $service->cloneCatalogItem($payload);

        if (($result['status'] ?? 'error') === 'error') {
            throw new \Exception($result['message'] ?? 'Erro desconhecido na clonagem');
        }

        return $result;
    }

    /**
     * Job: Análise de Gap em Background
     */
    private function gapAnalysisJob(array $payload): array
    {
        $categoryId = $payload['category_id'] ?? null;
        if (!$categoryId) {
            throw new \Exception('Category ID Required');
        }

        $service = new \App\Services\GapHunterService();
        return $service->analyzeCategory($categoryId, $payload['options'] ?? []);
    }

    /**
     * Job: Geração de AI em Background
     */
    private function aiGenerationJob(array $payload): array
    {
        // Payload: [prompt, system, complexity, context, user_id]
        $prompt = $payload['prompt'] ?? '';
        $system = $payload['system'] ?? '';
        $complexity = $payload['complexity'] ?? 'basic';

        if (empty($prompt)) {
            throw new \Exception('Prompt Required');
        }

        $llm = new \App\Services\LLMService();
        $result = $llm->generate($prompt, $system, $complexity);

        if (!$result['success']) {
            throw new \Exception("AI Error: " . ($result['error'] ?? 'Unknown'));
        }

        return $result;
    }
    /**
     * Obtém estatísticas dos jobs
     */
    public function getStats(): array
    {
        $stmt = $this->db->query("
            SELECT 
                status,
                COUNT(*) as count
            FROM jobs
            GROUP BY status
        ");

        $stats = [
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
        ];

        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $stats[$row['status']] = (int)$row['count'];
        }

        return $stats;
    }

    /**
     * Limpa jobs antigos (completados há mais de X dias)
     */
    public function cleanOldJobs(int $days = 30): int
    {
        $stmt = $this->db->prepare("
            DELETE FROM jobs
            WHERE status IN ('completed', 'failed')
            AND completed_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");

        $stmt->execute([':days' => $days]);

        return $stmt->rowCount();
    }
    /**
     * Obtém status de múltiplos jobs
     */
    public function getJobsStatus(array $jobIds): array
    {
        if (empty($jobIds)) {
            return [];
        }

        // Sanitizar IDs (apenas inteiros)
        $ids = array_map('intval', $jobIds);
        $inQuery = implode(',', $ids);

        $stmt = $this->db->query("
            SELECT id, status, error_message 
            FROM jobs 
            WHERE id IN ($inQuery)
        ");

        $result = [];
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $result[$row['id']] = $row;
        }

        return $result;
    }
    /**
     * Job: Execução de Otimização em Massa
     */
    private function bulkOptimizeExecJob(array $payload): array
    {
        $seoJobId = $payload['seo_job_id'] ?? null;
        if (!$seoJobId) {
            throw new \Exception('seo_job_id é obrigatório para bulk_optimize_exec');
        }

        // Recuperar o account_id do job de SEO para instanciar o optimizer
        $stmt = $this->db->prepare("SELECT account_id FROM seo_bulk_jobs WHERE id = ?");
        $stmt->execute([$seoJobId]);
        $accountId = $stmt->fetchColumn();

        if (!$accountId) {
            throw new \Exception("SEO Bulk Job #{$seoJobId} não encontrado");
        }

        $optimizer = new BulkOptimizer((int)$accountId);
        return $optimizer->processJob((int)$seoJobId);
    }
}
