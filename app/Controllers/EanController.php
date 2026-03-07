<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\EanService;
use App\Services\MercadoPagoService;
use App\Services\EanReportService;
use App\Services\EanNotificationService;
use App\Services\WebhookInboxService;
use App\Services\StructuredLogService;
use App\Services\JobService;
use Exception;

/**
 * Controller para API de EANs
 */
class EanController extends BaseController
{
    private EanService $eanService;
    private ?int $accountId = null;
    private ?int $userId = null;
    
    public function __construct()
    {
        parent::__construct();
        $this->eanService = new EanService();
        $this->accountId = $this->getAccountId();
        $this->userId = $this->getUserId();
    }
    
    /**
     * Listar pacotes disponíveis
     * GET /api/ean/packages
     */
    public function packages(): void
    {
        $this->jsonResponse([
            'success' => true,
            'packages' => $this->eanService->getPackages(),
        ]);
    }
    
    /**
     * Obter saldo do seller
     * GET /api/ean/balance
     */
    public function balance(): void
    {
        $this->requireAuth();
        
        $this->jsonResponse([
            'success' => true,
            'balance' => $this->eanService->getBalance($this->accountId),
        ]);
    }
    
    /**
     * Listar EANs do seller
     * GET /api/ean/my-eans
     */
    public function myEans(): void
    {
        $this->requireAuth();
        
        $onlyAvailable = $this->request->get('available') === '1';
        
        $this->jsonResponse([
            'success' => true,
            'eans' => $this->eanService->getSellerEans($this->accountId, $onlyAvailable),
            'balance' => $this->eanService->getBalance($this->accountId),
        ]);
    }
    
    /**
     * Histórico de compras
     * GET /api/ean/purchases
     */
    public function purchases(): void
    {
        $this->requireAuth();
        
        $this->jsonResponse([
            'success' => true,
            'purchases' => $this->eanService->getPurchaseHistory($this->accountId),
        ]);
    }
    
    /**
     * Histórico de transações
     * GET /api/ean/transactions
     */
    public function transactions(): void
    {
        $this->requireAuth();
        
        $this->jsonResponse([
            'success' => true,
            'transactions' => $this->eanService->getTransactionHistory($this->accountId),
        ]);
    }
    
    /**
     * Iniciar compra de pacote
     * POST /api/ean/purchase
     */
    public function purchase(): void
    {
        $this->requireAuth();
        
        $input = $this->getJsonInput();
        
        $packageId = $input['package_id'] ?? null;
        $paymentMethod = $input['payment_method'] ?? 'pix';
        
        if (!$packageId) {
            $this->jsonResponse(['success' => false, 'error' => 'package_id é obrigatório'], 400);
            return;
        }
        
        try {
            $result = $this->eanService->initiatePurchase(
                $this->accountId,
                (int) $packageId,
                $paymentMethod
            );
            
            $this->jsonResponse([
                'success' => true,
                'purchase' => $result,
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
    
    /**
     * Usar um EAN
     * POST /api/ean/use
     */
    public function useEan(): void
    {
        $this->requireAuth();
        
        $input = $this->getJsonInput();
        
        $mlItemId = $input['ml_item_id'] ?? null;
        $title = $input['title'] ?? null;
        
        try {
            $result = $this->eanService->useEan($this->accountId, $mlItemId, $title);
            
            if ($result) {
                $this->jsonResponse([
                    'success' => true,
                    'ean' => $result['ean'],
                    'assignment_id' => $result['assignment_id'],
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Nenhum EAN disponível. Compre mais EANs.',
                ], 400);
            }
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Desvincular EAN de um item
     * POST /api/ean/unlink
     */
    public function unlinkEan(): void
    {
        $this->requireAuth();
        
        $input = $this->getJsonInput();
        $assignmentId = $input['assignment_id'] ?? null;
        
        if (!$assignmentId) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'assignment_id é obrigatório',
            ], 400);
            return;
        }
        
        try {
            $result = $this->eanService->unlinkEan($this->accountId, (int) $assignmentId);
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'EAN desvinculado com sucesso',
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 400);
        }
    }
    
    /**
     * Verificar estoque baixo
     * GET /api/ean/low-stock
     */
    public function checkLowStock(): void
    {
        $this->requireAuth();
        
        $stock = $this->eanService->checkLowStock($this->accountId);
        
        $this->jsonResponse([
            'success' => true,
            'stock' => $stock,
        ]);
    }
    
    /**
     * Validar EAN
     * GET /api/ean/validate/{ean}
     */
    public function validate(string $ean): void
    {
        $isValid = $this->eanService->validateEan($ean);
        $exists = $this->eanService->findEan($ean);
        
        $this->jsonResponse([
            'success' => true,
            'ean' => $ean,
            'valid_format' => $isValid,
            'exists_in_system' => $exists !== null,
            'details' => $exists,
        ]);
    }
    
    /**
     * Sugerir EAN para novo anúncio (integração)
     * GET /api/ean/suggest
     */
    public function suggest(): void
    {
        $this->requireAuth();
        
        $balance = $this->eanService->getBalance($this->accountId);
        
        if ($balance['available'] <= 0) {
            $this->jsonResponse([
                'success' => true,
                'has_ean' => false,
                'message' => 'Sem EANs disponíveis. Adquira um pacote.',
                'buy_url' => '/dashboard/ean',
                'balance' => $balance,
            ]);
            return;
        }
        
        // Buscar próximo EAN disponível (sem usar)
        $eans = $this->eanService->getSellerEans($this->accountId, true);
        $nextEan = $eans[0]['ean'] ?? null;
        
        $this->jsonResponse([
            'success' => true,
            'has_ean' => true,
            'suggested_ean' => $nextEan,
            'available_count' => $balance['available'],
            'balance' => $balance,
        ]);
    }
    
    /**
     * Usar EAN e vincular a um item (integração com criação de anúncio)
     * POST /api/ean/use-for-item
     */
    public function useForItem(): void
    {
        $this->requireAuth();
        
        $input = $this->getJsonInput();
        $mlItemId = $input['ml_item_id'] ?? null;
        $title = $input['title'] ?? null;
        
        if (!$mlItemId) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'ml_item_id é obrigatório',
            ], 400);
            return;
        }
        
        try {
            $result = $this->eanService->useEan($this->accountId, $mlItemId, $title);
            
            if ($result) {
                $this->jsonResponse([
                    'success' => true,
                    'ean' => $result['ean'],
                    'assignment_id' => $result['assignment_id'],
                    'ml_item_id' => $mlItemId,
                ]);
            } else {
                $this->jsonResponse([
                    'success' => false,
                    'error' => 'Nenhum EAN disponível',
                    'buy_url' => '/dashboard/ean',
                ], 400);
            }
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    /**
     * Obter EAN vinculado a um item do ML
     * GET /api/ean/by-item/{mlItemId}
     */
    public function getByItem(string $mlItemId): void
    {
        $this->requireAuth();
        
        $assignment = (new \App\Models\EanAssignment())->getByMlItem($mlItemId);
        
        if ($assignment && $assignment['account_id'] === $this->accountId) {
            $this->jsonResponse([
                'success' => true,
                'found' => true,
                'ean' => $assignment['ean'],
                'assignment' => $assignment,
            ]);
        } else {
            $this->jsonResponse([
                'success' => true,
                'found' => false,
            ]);
        }
    }
    
    /**
     * Exportar EANs do seller para CSV
     * GET /api/ean/export
     */
    public function exportEans(): void
    {
        $this->requireAuth();
        
        $filter = $this->request->get('filter') ?? 'all'; // all, available, used
        
        $eans = $this->eanService->getSellerEans($this->accountId);
        
        // Filtrar
        if ($filter === 'available') {
            $eans = array_filter($eans, fn($e) => empty($e['ml_item_id']));
        } elseif ($filter === 'used') {
            $eans = array_filter($eans, fn($e) => !empty($e['ml_item_id']));
        }
        
        // Headers para download CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="meus_eans_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // BOM para UTF-8
        fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
        
        // Header do CSV
        fputcsv($output, ['Código EAN', 'Status', 'Item ML', 'Título do Produto', 'SKU', 'Data de Atribuição'], ';');
        
        // Dados
        foreach ($eans as $ean) {
            fputcsv($output, [
                $ean['ean'] ?? '',
                empty($ean['ml_item_id']) ? 'Disponível' : 'Em Uso',
                $ean['ml_item_id'] ?? '',
                $ean['product_title'] ?? '',
                $ean['product_sku'] ?? '',
                $ean['assigned_at'] ?? '',
            ], ';');
        }
        
        fclose($output);
        exit;
    }
    
    /**
     * Estatísticas detalhadas do seller
     * GET /api/ean/stats
     */
    public function sellerStats(): void
    {
        $this->requireAuth();
        
        $balance = $this->eanService->getBalance($this->accountId);
        $eans = $this->eanService->getSellerEans($this->accountId);
        $purchases = $this->eanService->getPurchaseHistory($this->accountId);
        
        // Calcular estatísticas
        $usedEans = array_filter($eans, fn($e) => !empty($e['ml_item_id']));
        $totalSpent = array_sum(array_column($purchases, 'total_amount'));
        $paidPurchases = array_filter($purchases, fn($p) => $p['payment_status'] === 'paid');
        
        // Uso por mês (últimos 6 meses)
        $usageByMonth = [];
        foreach ($usedEans as $ean) {
            if (!empty($ean['assigned_at'])) {
                $month = date('Y-m', strtotime($ean['assigned_at']));
                $usageByMonth[$month] = ($usageByMonth[$month] ?? 0) + 1;
            }
        }
        
        $this->jsonResponse([
            'success' => true,
            'stats' => [
                'total_purchased' => $balance['total_purchased'],
                'available' => $balance['available'],
                'used' => $balance['total_used'],
                'usage_rate' => $balance['total_purchased'] > 0 
                    ? round(($balance['total_used'] / $balance['total_purchased']) * 100, 1) 
                    : 0,
                'total_spent' => $totalSpent,
                'purchases_count' => count($paidPurchases),
                'avg_price_per_ean' => $balance['total_purchased'] > 0 
                    ? round($totalSpent / $balance['total_purchased'], 2) 
                    : 0,
                'usage_by_month' => $usageByMonth,
            ],
        ]);
    }
    
    /**
     * Webhook do Mercado Pago
     * POST /api/ean/webhook/mercadopago
     */
    public function webhookMercadoPago(): void
    {
        $input = (string)file_get_contents('php://input');
        $data = json_decode($input, true);
        $requestId = bin2hex(random_bytes(8));
        $logger = new StructuredLogService();

        $logger->info('EAN_MP_WEBHOOK_RECEIVED', [
            'request_id' => $requestId,
            'payload_size' => strlen($input),
        ]);

        if (!$data || json_last_error() !== JSON_ERROR_NONE) {
            $this->jsonResponse(['status' => 'invalid_payload', 'request_id' => $requestId], 400);
            return;
        }

        $secret = trim((string)($_ENV['MP_WEBHOOK_SECRET'] ?? $_ENV['MERCADOPAGO_WEBHOOK_SECRET'] ?? ''));
        if ($secret !== '' && !$this->validateMpWebhookSignature($input, $secret)) {
            $logger->warning('EAN_MP_WEBHOOK_INVALID_SIGNATURE', [
                'request_id' => $requestId,
            ]);
            $this->jsonResponse(['status' => 'invalid_signature', 'request_id' => $requestId], 401);
            return;
        }

        $type = (string)($data['type'] ?? $data['action'] ?? '');
        if ($type !== 'payment' && strpos($type, 'payment') === false) {
            $this->jsonResponse(['status' => 'ignored', 'type' => $type, 'request_id' => $requestId]);
            return;
        }

        $eventKey = $this->buildMpEventKey($data, $input);
        $inbox = new WebhookInboxService();
        $accepted = $inbox->registerIncoming('mercadopago', $eventKey, $data, [
            'request_id' => $requestId,
            'type' => $type,
        ]);

        if (!$accepted) {
            $logger->info('EAN_MP_WEBHOOK_DUPLICATE_IGNORED', [
                'request_id' => $requestId,
                'event_key' => $eventKey,
            ]);
            $this->jsonResponse(['status' => 'duplicate_ignored', 'request_id' => $requestId]);
            return;
        }

        $asyncWebhook = $this->parseBooleanInput($_ENV['EAN_MP_WEBHOOK_ASYNC'] ?? null, true);

        if ($asyncWebhook) {
            try {
                $jobService = new JobService();
                $jobId = $jobService->dispatch('ean_mp_webhook', [
                    'event_key' => $eventKey,
                    'request_id' => $requestId,
                    'data' => $data,
                    'received_at' => date('c'),
                ]);

                $inbox->markQueued('mercadopago', $eventKey, $jobId, [
                    'request_id' => $requestId,
                ]);

                $logger->info('EAN_MP_WEBHOOK_QUEUED', [
                    'request_id' => $requestId,
                    'event_key' => $eventKey,
                    'job_id' => $jobId,
                ]);

                $this->jsonResponse([
                    'status' => 'queued',
                    'job_id' => $jobId,
                    'event_key' => $eventKey,
                    'request_id' => $requestId,
                ]);
                return;
            } catch (Exception $queueException) {
                $logger->warning('EAN_MP_WEBHOOK_QUEUE_FALLBACK_SYNC', [
                    'request_id' => $requestId,
                    'event_key' => $eventKey,
                    'error' => $queueException->getMessage(),
                ]);
            }
        }

        try {
            $result = $this->eanService->processPaymentWebhook($data);
            $inbox->markProcessed('mercadopago', $eventKey, $result);
            $result['event_key'] = $eventKey;
            $result['request_id'] = $requestId;
            $this->jsonResponse($result);
        } catch (Exception $e) {
            $inbox->markFailed('mercadopago', $eventKey, $e->getMessage());
            $logger->error('EAN_MP_WEBHOOK_PROCESSING_ERROR', [
                'request_id' => $requestId,
                'event_key' => $eventKey,
                'error' => $e->getMessage(),
            ]);
            $this->jsonResponse([
                'status' => 'error',
                'message' => $e->getMessage(),
                'request_id' => $requestId,
            ], 500);
        }
    }

    /**
     * Status de processamento do webhook MP EAN por event_key/request_id (admin)
     * GET /api/ean/admin/webhook-status
     */
    public function adminWebhookStatus(): void
    {
        $this->requireAdmin();

        $eventKey = trim((string)($this->request->get('event_key') ?? ''));
        $requestId = trim((string)($this->request->get('request_id') ?? ''));

        if ($eventKey === '' && $requestId === '') {
            $this->jsonResponse([
                'success' => false,
                'error' => 'event_key ou request_id é obrigatório',
            ], 400);
            return;
        }

        try {
            $inbox = new WebhookInboxService();
            $status = $inbox->getEventStatus('mercadopago', $eventKey !== '' ? $eventKey : null, $requestId !== '' ? $requestId : null);

            if ($status === null) {
                $this->jsonResponse([
                    'success' => true,
                    'found' => false,
                    'status' => null,
                ]);
                return;
            }

            $this->jsonResponse([
                'success' => true,
                'found' => true,
                'status' => $status,
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Métricas de SLA e timeline do webhook MP EAN (admin)
     * GET /api/ean/admin/webhook-sla
     */
    public function adminWebhookSla(): void
    {
        $this->requireAdmin();

        $hoursBack = (int)$this->request->getInt('hours_back', 24);
        $recentLimit = (int)$this->request->getInt('recent_limit', 200);

        try {
            $inbox = new WebhookInboxService();
            $metrics = $inbox->getProviderSlaMetrics('mercadopago', $hoursBack, $recentLimit);

            $this->jsonResponse([
                'success' => true,
                'metrics' => $metrics,
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Alertas operacionais do pipeline EAN/MP/ML (admin)
     * GET /api/ean/admin/alerts/operational
     */
    public function adminOperationalAlerts(): void
    {
        $this->requireAdmin();

        $limit = (int)$this->request->getInt('limit', 50);
        $type = trim((string)($this->request->get('type') ?? ''));
        $severity = trim((string)($this->request->get('severity') ?? ''));

        try {
            $alerts = $this->eanService->getOperationalAlerts(
                $limit,
                $type !== '' ? $type : null,
                $severity !== '' ? $severity : null
            );

            $this->jsonResponse([
                'success' => true,
                'alerts' => $alerts,
                'count' => count($alerts),
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Status e histórico do runbook operacional (admin)
     * GET /api/ean/admin/runbook/status
     */
    public function adminRunbookStatus(): void
    {
        $this->requireAdmin();

        $historyLimit = (int)$this->request->getInt('history_limit', 20);

        try {
            $status = $this->eanService->getOperationalRunbookStatus($historyLimit);
            $this->jsonResponse([
                'success' => true,
                'status' => $status,
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Status de escalonamento operacional (admin)
     * GET /api/ean/admin/runbook/escalation
     */
    public function adminRunbookEscalation(): void
    {
        $this->requireAdmin();

        try {
            $status = $this->eanService->getOperationalEscalationStatus();
            $this->jsonResponse([
                'success' => true,
                'status' => $status,
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Tendência operacional e predição baseada em série temporal (admin)
     * GET /api/ean/admin/operational/trend
     */
    public function adminOperationalTrend(): void
    {
        $this->requireAdmin();

        $hoursBack = (int)$this->request->getInt('hours_back', 24);
        $limit = (int)$this->request->getInt('limit', 500);

        try {
            $trend = $this->eanService->getOperationalTimeseriesTrend($hoursBack, $limit);
            $this->jsonResponse([
                'success' => true,
                'trend' => $trend,
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Status preditivo operacional com thresholds efetivos (admin)
     * GET /api/ean/admin/operational/predictive
     */
    public function adminOperationalPredictive(): void
    {
        $this->requireAdmin();

        $hoursBack = (int)$this->request->getInt('hours_back', (int)(getenv('EAN_PREDICTIVE_TREND_HOURS') ?: 6));
        $limit = (int)$this->request->getInt('limit', 500);

        try {
            $trend = $this->eanService->getOperationalTimeseriesTrend($hoursBack, $limit);

            $thresholds = [
                'max_divergences' => (int)(getenv('EAN_PREDICTIVE_MAX_DIVERGENCES') ?: 30),
                'max_avg_seconds' => (int)(getenv('EAN_PREDICTIVE_MAX_AVG_SECONDS') ?: 75),
                'max_failure_rate_percent' => (float)(getenv('EAN_PREDICTIVE_MAX_FAILURE_RATE_PERCENT') ?: 12),
                'predictive_runbook_enabled' => filter_var(getenv('EAN_PREDICTIVE_RUNBOOK_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
            ];

            $projection = is_array($trend['projection_next_window'] ?? null) ? $trend['projection_next_window'] : [];
            $shouldTrigger = false;
            $reasons = [];

            if (!empty($projection)) {
                if ((int)($projection['total_divergences'] ?? 0) > $thresholds['max_divergences']) {
                    $shouldTrigger = true;
                    $reasons[] = 'projected_divergences_exceeded';
                }

                if ((float)($projection['webhook_avg_processing_seconds'] ?? 0.0) > $thresholds['max_avg_seconds']) {
                    $shouldTrigger = true;
                    $reasons[] = 'projected_webhook_avg_seconds_exceeded';
                }

                if ((float)($projection['webhook_failure_rate_percent'] ?? 0.0) > $thresholds['max_failure_rate_percent']) {
                    $shouldTrigger = true;
                    $reasons[] = 'projected_webhook_failure_rate_exceeded';
                }
            }

            $this->jsonResponse([
                'success' => true,
                'trend' => $trend,
                'thresholds' => $thresholds,
                'predictive' => [
                    'should_trigger' => $shouldTrigger,
                    'reasons' => $reasons,
                ],
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Status do circuit breaker operacional (admin)
     * GET /api/ean/admin/operational/circuit-breaker
     */
    public function adminOperationalCircuitBreaker(): void
    {
        $this->requireAdmin();

        try {
            $status = $this->eanService->getOperationalCircuitBreakerStatus();
            $this->jsonResponse([
                'success' => true,
                'status' => $status,
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Reset manual do circuit breaker operacional (admin)
     * POST /api/ean/admin/operational/circuit-breaker/reset
     */
    public function adminOperationalCircuitBreakerReset(): void
    {
        $this->requireAdmin();

        try {
            $status = $this->eanService->evaluateOperationalCircuitBreaker([
                'force_close' => true,
                'threshold_cycles' => (int)(getenv('EAN_CIRCUIT_BREAKER_THRESHOLD_CYCLES') ?: 3),
                'open_minutes' => (int)(getenv('EAN_CIRCUIT_BREAKER_OPEN_MINUTES') ?: 15),
                'predictive_trigger' => false,
                'critical_trigger' => false,
            ]);

            $this->jsonResponse([
                'success' => true,
                'status' => $status,
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Executa runbook operacional manualmente (admin)
     * POST /api/ean/admin/runbook/execute
     */
    public function adminRunbookExecute(): void
    {
        $this->requireAdmin();

        $input = $this->getJsonInput();
        $issues = $input['issues'] ?? [];
        if (!is_array($issues)) {
            $issues = [];
        }

        $force = $this->parseBooleanInput($input['force'] ?? null, true);
        $cooldownSeconds = (int)($input['cooldown_seconds'] ?? 0);

        try {
            $result = $this->eanService->executeOperationalRunbook($issues, [
                'source' => 'admin_manual',
                'force' => $force,
                'cooldown_seconds' => $cooldownSeconds,
            ]);

            $this->jsonResponse([
                'success' => true,
                'result' => $result,
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Monta chave determinística para idempotência do evento MP.
     */
    private function buildMpEventKey(array $data, string $rawPayload): string
    {
        $paymentId = (string)($data['data']['id'] ?? $data['id'] ?? '');
        $action = (string)($data['action'] ?? $data['type'] ?? '');
        $topic = (string)($data['topic'] ?? 'payment');

        if ($paymentId !== '') {
            return 'payment:' . $paymentId . ':' . $action . ':' . $topic;
        }

        return 'hash:' . hash('sha256', $rawPayload);
    }

    /**
     * Valida assinatura opcional de webhook Mercado Pago.
     */
    private function validateMpWebhookSignature(string $rawPayload, string $secret): bool
    {
        $header = $this->getRequestHeaderInsensitive('X-Signature')
            ?? $this->getRequestHeaderInsensitive('X-Hub-Signature-256');

        if (!$header) {
            return false;
        }

        $parts = explode('=', trim($header), 2);
        $received = count($parts) === 2 ? trim($parts[1]) : trim($parts[0]);

        if ($received === '') {
            return false;
        }

        $calculated = hash_hmac('sha256', $rawPayload, $secret);
        return hash_equals($calculated, $received);
    }

    /**
     * Busca header HTTP de forma case-insensitive.
     */
    private function getRequestHeaderInsensitive(string $name): ?string
    {
        if (function_exists('getallheaders')) {
            $headers = getallheaders();
            foreach ($headers as $key => $value) {
                if (strcasecmp($key, $name) === 0) {
                    return is_string($value) ? $value : null;
                }
            }
        }

        $serverKey = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        if (isset($_SERVER[$serverKey]) && is_string($_SERVER[$serverKey])) {
            return $_SERVER[$serverKey];
        }

        return null;
    }
    
    /**
     * Confirmar pagamento manualmente (admin)
     * POST /api/ean/admin/confirm-payment
     */
    public function confirmPayment(): void
    {
        $this->requireAdmin();
        
        $input = $this->getJsonInput();
        $purchaseId = $input['purchase_id'] ?? null;
        
        if (!$purchaseId) {
            $this->jsonResponse(['success' => false, 'error' => 'purchase_id é obrigatório'], 400);
            return;
        }
        
        try {
            $result = $this->eanService->confirmPayment((int) $purchaseId);
            $this->jsonResponse(['success' => true, 'result' => $result]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 400);
        }
    }
    
    /**
     * Dashboard admin
     * GET /api/ean/admin/dashboard
     */
    public function adminDashboard(): void
    {
        $this->requireAdmin();
        
        $this->jsonResponse([
            'success' => true,
            'data' => $this->eanService->getAdminDashboard(),
        ]);
    }
    
    /**
     * Listar compras (admin)
     * GET /api/ean/admin/purchases
     */
    public function adminPurchases(): void
    {
        $this->requireAdmin();
        
        $page = $this->request->getInt('page', 1);
        $status = $this->request->get('status');
        
        $this->jsonResponse([
            'success' => true,
            'data' => $this->eanService->listAllPurchases($page, $status),
        ]);
    }
    
    /**
     * Listar inventário (admin)
     * GET /api/ean/admin/inventory
     */
    public function adminInventory(): void
    {
        $this->requireAdmin();
        
        $page = $this->request->getInt('page', 1);
        $status = $this->request->get('status');
        $batch = $this->request->get('batch');
        
        $this->jsonResponse([
            'success' => true,
            'data' => $this->eanService->listInventory($page, $status, $batch),
        ]);
    }
    
    /**
     * Adicionar EANs ao inventário (admin)
     * POST /api/ean/admin/inventory/add
     */
    public function adminAddInventory(): void
    {
        $this->requireAdmin();
        
        $input = $this->getJsonInput();
        
        $eans = $input['eans'] ?? [];
        $batch = $input['batch'] ?? 'BATCH-' . date('Ymd-His');
        $cost = (float) ($input['cost'] ?? 0);
        $supplier = $input['supplier'] ?? '';
        
        if (empty($eans)) {
            $this->jsonResponse(['success' => false, 'error' => 'Lista de EANs vazia'], 400);
            return;
        }
        
        try {
            $added = $this->eanService->addToInventory($eans, $batch, $cost, $supplier);
            
            $this->jsonResponse([
                'success' => true,
                'added' => $added,
                'total_sent' => count($eans),
                'batch' => $batch,
            ]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Importar EANs de arquivo (admin)
     * POST /api/ean/admin/inventory/import
     */
    public function adminImportInventory(): void
    {
        $this->requireAdmin();
        
        if (!isset($_FILES['file'])) {
            $this->jsonResponse(['success' => false, 'error' => 'Arquivo não enviado'], 400);
            return;
        }
        
        $file = $_FILES['file'];
        $batch = $this->request->post('batch') ?? 'IMPORT-' . date('Ymd-His');
        $cost = (float) ($this->request->post('cost') ?? 0);
        $supplier = $this->request->post('supplier') ?? '';
        
        try {
            $result = $this->eanService->importFromFile($file['tmp_name'], $batch, $cost, $supplier);
            
            $this->jsonResponse([
                'success' => true,
                'result' => $result,
            ]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Configurar Mercado Pago (admin)
     * POST /api/ean/admin/config/mercadopago
     */
    public function adminConfigMercadoPago(): void
    {
        $this->requireAdmin();
        
        $input = $this->getJsonInput();
        
        $accessToken = $input['access_token'] ?? '';
        $publicKey = $input['public_key'] ?? '';
        $webhookSecret = $input['webhook_secret'] ?? '';
        
        if (empty($accessToken)) {
            $this->jsonResponse(['success' => false, 'error' => 'access_token é obrigatório'], 400);
            return;
        }
        
        try {
            MercadoPagoService::saveCredentials($accessToken, $publicKey, $webhookSecret);
            
            // Testar conexão
            $mpService = new MercadoPagoService();
            $testResult = $mpService->testConnection();
            
            $this->jsonResponse([
                'success' => true,
                'message' => 'Credenciais salvas',
                'test' => $testResult,
            ]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    /**
     * Testar conexão Mercado Pago (admin)
     * GET /api/ean/admin/config/mercadopago/test
     */
    public function adminTestMercadoPago(): void
    {
        $this->requireAdmin();
        
        $mpService = new MercadoPagoService();
        $result = $mpService->testConnection();
        
        $this->jsonResponse([
            'success' => $result['success'],
            'data' => $result,
        ]);
    }

    /**
     * Reconcilia pagamentos pendentes no Mercado Pago (admin)
     * POST /api/ean/admin/reconcile-payments
     */
    public function adminReconcilePayments(): void
    {
        $this->requireAdmin();

        $input = $this->getJsonInput();
        $limit = (int)($input['limit'] ?? $this->request->getInt('limit', 100));
        $minAgeMinutes = (int)($input['min_age_minutes'] ?? $this->request->getInt('min_age_minutes', 2));
        $retryFailedWebhooks = $this->parseBooleanInput($input['retry_failed_webhooks'] ?? null, true);
        $retryWebhookLimit = (int)($input['retry_webhook_limit'] ?? $this->request->getInt('retry_webhook_limit', 50));

        $limit = max(1, min(1000, $limit));
        $minAgeMinutes = max(0, min(1440, $minAgeMinutes));
        $retryWebhookLimit = max(1, min(500, $retryWebhookLimit));

        try {
            $startedAt = date('c');
            $result = $this->eanService->reconcilePendingPayments($limit, $minAgeMinutes);
            $retry = null;

            if ($retryFailedWebhooks) {
                $retry = $this->eanService->retryFailedMercadoPagoWebhookEvents($retryWebhookLimit);
            }

            $this->eanService->storeReconcileExecution([
                'source' => 'admin_api',
                'started_at' => $startedAt,
                'finished_at' => date('c'),
                'ok' => true,
                'config' => [
                    'limit' => $limit,
                    'min_age_minutes' => $minAgeMinutes,
                    'retry_failed_webhooks' => $retryFailedWebhooks,
                    'retry_webhook_limit' => $retryWebhookLimit,
                ],
                'result' => $result,
                'retry' => $retry,
            ]);

            $this->jsonResponse([
                'success' => true,
                'result' => $result,
                'retry_failed_webhooks' => $retry,
            ]);
        } catch (Exception $e) {
            $this->eanService->storeReconcileExecution([
                'source' => 'admin_api',
                'started_at' => $startedAt ?? date('c'),
                'finished_at' => date('c'),
                'ok' => false,
                'config' => [
                    'limit' => $limit,
                    'min_age_minutes' => $minAgeMinutes,
                    'retry_failed_webhooks' => $retryFailedWebhooks,
                    'retry_webhook_limit' => $retryWebhookLimit,
                ],
                'error' => $e->getMessage(),
            ]);

            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Status operacional da reconciliação de pagamentos EAN (admin)
     * GET /api/ean/admin/reconcile-status
     */
    public function adminReconcileStatus(): void
    {
        $this->requireAdmin();

        try {
            $status = $this->eanService->getReconcileStatus();

            $this->jsonResponse([
                'success' => true,
                'status' => $status,
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Relatório de divergências financeiras EAN (admin)
     * GET /api/ean/admin/reconciliation/divergences
     */
    public function adminReconciliationDivergences(): void
    {
        $this->requireAdmin();

        $hoursBack = (int)$this->request->getInt('hours_back', 72);
        $limit = (int)$this->request->getInt('limit', 200);

        try {
            $report = $this->eanService->getFinancialDivergenceReport($hoursBack, $limit);

            $this->jsonResponse([
                'success' => true,
                'report' => $report,
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Preview do plano de reconciliação sem executar mudanças (admin)
     * GET /api/ean/admin/reconciliation/preview
     */
    public function adminReconciliationPreview(): void
    {
        $this->requireAdmin();

        $hoursBack = (int)$this->request->getInt('hours_back', 72);
        $limit = (int)$this->request->getInt('limit', 200);
        $saveSnapshot = $this->parseBooleanInput($this->request->get('save_snapshot'), false);
        $source = (string)($this->request->get('source') ?? 'admin_api_preview');

        try {
            $plan = $this->eanService->previewReconciliationPlan($hoursBack, $limit);

            if ($saveSnapshot) {
                $this->eanService->storeReconciliationPreviewSnapshot($plan, $source);
            }

            $this->jsonResponse([
                'success' => true,
                'plan' => $plan,
                'snapshot_saved' => $saveSnapshot,
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Histórico de snapshots do preview da reconciliação (admin)
     * GET /api/ean/admin/reconciliation/preview-history
     */
    public function adminReconciliationPreviewHistory(): void
    {
        $this->requireAdmin();

        $limit = (int)$this->request->getInt('limit', 20);

        try {
            $history = $this->eanService->getReconciliationPreviewHistory($limit);

            $this->jsonResponse([
                'success' => true,
                'history' => $history,
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Drift entre último preview e última execução de reconciliação (admin)
     * GET /api/ean/admin/reconciliation/drift
     */
    public function adminReconciliationDrift(): void
    {
        $this->requireAdmin();

        try {
            $drift = $this->eanService->getReconciliationDrift();

            $this->jsonResponse([
                'success' => true,
                'drift' => $drift,
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Executa auto-healing seguro da reconciliação EAN (admin)
     * POST /api/ean/admin/reconciliation/auto-heal-safe
     */
    public function adminAutoHealReconciliationSafe(): void
    {
        $this->requireAdmin();

        $input = $this->getJsonInput();
        $hoursBack = (int)($input['hours_back'] ?? $this->request->getInt('hours_back', 72));
        $limit = (int)($input['limit'] ?? $this->request->getInt('limit', 200));
        $retryFailedWebhooks = $this->parseBooleanInput($input['retry_failed_webhooks'] ?? null, true);

        try {
            $result = $this->eanService->autoHealSafeDivergences($hoursBack, $limit, $retryFailedWebhooks);

            $this->jsonResponse([
                'success' => true,
                'result' => $result,
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remediação de baixo risco de divergências (admin)
     * POST /api/ean/admin/reconciliation/remediate-low-risk
     */
    public function adminRemediateLowRiskReconciliation(): void
    {
        $this->requireAdmin();

        $input = $this->getJsonInput();
        $hoursBack = (int)($input['hours_back'] ?? $this->request->getInt('hours_back', 72));
        $limit = (int)($input['limit'] ?? $this->request->getInt('limit', 200));
        $dryRun = $this->parseBooleanInput($input['dry_run'] ?? null, true);
        $rollbackOnWorsening = $this->parseBooleanInput($input['rollback_on_worsening'] ?? null, true);

        try {
            $result = $this->eanService->remediateLowRiskDivergences(
                $hoursBack,
                $limit,
                $dryRun,
                $rollbackOnWorsening
            );

            $this->jsonResponse([
                'success' => true,
                'result' => $result,
            ]);
        } catch (Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }
    
    // =====================================
    // Relatórios (Admin)
    // =====================================
    
    /**
     * Relatório de vendas
     * GET /api/ean/admin/reports/sales?start=YYYY-MM-DD&end=YYYY-MM-DD
     */
    public function adminSalesReport(): void
    {
        $this->requireAdmin();
        
        $start = $this->request->get('start') ?? date('Y-m-01');
        $end = $this->request->get('end') ?? date('Y-m-d');
        
        $reportService = new EanReportService();
        
        $this->jsonResponse([
            'success' => true,
            'data' => $reportService->getSalesReport($start, $end),
        ]);
    }
    
    /**
     * Relatório de uso
     * GET /api/ean/admin/reports/usage?start=YYYY-MM-DD&end=YYYY-MM-DD
     */
    public function adminUsageReport(): void
    {
        $this->requireAdmin();
        
        $start = $this->request->get('start') ?? date('Y-m-01');
        $end = $this->request->get('end') ?? date('Y-m-d');
        
        $reportService = new EanReportService();
        
        $this->jsonResponse([
            'success' => true,
            'data' => $reportService->getUsageReport($start, $end),
        ]);
    }
    
    /**
     * Relatório de inventário
     * GET /api/ean/admin/reports/inventory
     */
    public function adminInventoryReport(): void
    {
        $this->requireAdmin();
        
        $reportService = new EanReportService();
        
        $this->jsonResponse([
            'success' => true,
            'data' => $reportService->getInventoryReport(),
        ]);
    }
    
    /**
     * Exportar vendas CSV
     * GET /api/ean/admin/reports/sales/export?start=YYYY-MM-DD&end=YYYY-MM-DD
     */
    public function adminExportSales(): void
    {
        $this->requireAdmin();
        
        $start = $this->request->get('start') ?? date('Y-m-01');
        $end = $this->request->get('end') ?? date('Y-m-d');
        
        $reportService = new EanReportService();
        $csv = $reportService->exportSalesToCsv($start, $end);
        
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="vendas_ean_' . $start . '_' . $end . '.csv"');
        echo $csv;
        exit;
    }
    
    /**
     * Enviar relatório diário por email
     * POST /api/ean/admin/reports/send-daily
     */
    public function adminSendDailyReport(): void
    {
        $this->requireAdmin();
        
        try {
            $notificationService = new EanNotificationService();
            $sent = $notificationService->sendDailySalesReport();
            
            $this->jsonResponse([
                'success' => $sent,
                'message' => $sent ? 'Relatório enviado' : 'Falha ao enviar',
            ]);
        } catch (Exception $e) {
            $this->jsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }
    
    // =====================================
    // Helpers
    // =====================================
    
    private function requireAuth(): void
    {
        if (!$this->accountId) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Autenticação necessária',
            ], 401);
            exit;
        }
    }

    private function parseBooleanInput($value, bool $default = false): bool
    {
        if ($value === null) {
            return $default;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return ((int)$value) !== 0;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));
            if ($normalized === '') {
                return $default;
            }

            if (in_array($normalized, ['1', 'true', 'yes', 'on', 'sim'], true)) {
                return true;
            }

            if (in_array($normalized, ['0', 'false', 'no', 'off', 'nao', 'não'], true)) {
                return false;
            }
        }

        return (bool)$value;
    }
    
    /**
     * Widget compacto para exibição em outras telas
     * GET /api/ean/widget
     * Retorna dados resumidos para mostrar um mini widget de EAN
     */
    public function widget(): void
    {
        $balance = $this->eanService->getBalance($this->accountId);
        $available = $balance['available'] ?? 0;
        
        // Definir estado do alerta
        $alertLevel = 'ok';
        $alertMessage = '';
        
        if ($available === 0) {
            $alertLevel = 'danger';
            $alertMessage = 'Sem EANs disponíveis';
        } elseif ($available <= 5) {
            $alertLevel = 'danger';
            $alertMessage = 'Estoque crítico';
        } elseif ($available <= 10) {
            $alertLevel = 'warning';
            $alertMessage = 'Estoque baixo';
        }
        
        $this->jsonResponse([
            'success' => true,
            'widget' => [
                'available' => $available,
                'total_purchased' => $balance['total_purchased'] ?? 0,
                'total_used' => $balance['total_used'] ?? 0,
                'alert_level' => $alertLevel,
                'alert_message' => $alertMessage,
                'can_use_ean' => $available > 0,
                'purchase_url' => '/dashboard/ean#packages',
            ],
        ]);
    }
    
    /**
     * Obter próximo EAN disponível sem usá-lo
     * GET /api/ean/preview
     * Útil para mostrar preview do EAN que será usado
     */
    public function preview(): void
    {
        // Verificar saldo
        $balance = $this->eanService->getBalance($this->accountId);
        
        if (($balance['available'] ?? 0) === 0) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Sem EANs disponíveis',
                'available' => 0,
                'purchase_url' => '/dashboard/ean#packages',
            ]);
            return;
        }
        
        // Buscar próximo disponível sem marcar como usado
        $nextEan = $this->eanService->getNextAvailableEan($this->accountId);
        
        if (!$nextEan) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Nenhum EAN atribuído disponível',
            ]);
            return;
        }
        
        $this->jsonResponse([
            'success' => true,
            'preview' => [
                'ean' => $nextEan['ean'],
                'available_after_use' => ($balance['available'] ?? 1) - 1,
            ],
        ]);
    }
    
    /**
     * Obter EAN automaticamente para um novo anúncio
     * POST /api/ean/auto-assign
     * Usado quando o seller quer que o sistema escolha um EAN automaticamente
     */
    public function autoAssign(): void
    {
        $input = $this->getJsonInput();
        
        $mlItemId = $input['ml_item_id'] ?? null;
        $title = $input['title'] ?? null;
        
        if (!$mlItemId) {
            $this->jsonResponse(['success' => false, 'error' => 'ID do item é obrigatório'], 400);
            return;
        }
        
        // Verificar se já existe EAN para este item
        $existingEan = $this->eanService->getEanByItem($mlItemId);
        if ($existingEan) {
            $this->jsonResponse([
                'success' => true,
                'already_assigned' => true,
                'ean' => $existingEan,
            ]);
            return;
        }
        
        // Usar EAN automaticamente
        $result = $this->eanService->useEan($this->accountId, $mlItemId, $title);
        
        if (!$result) {
            $balance = $this->eanService->getBalance($this->accountId);
            $this->jsonResponse([
                'success' => false,
                'error' => 'Não foi possível atribuir um EAN',
                'available' => $balance['available'] ?? 0,
                'purchase_url' => '/dashboard/ean#packages',
            ], 400);
            return;
        }
        
        $this->jsonResponse([
            'success' => true,
            'ean' => $result['ean'],
            'assignment_id' => $result['assignment_id'],
        ]);
    }
    
    private function requireAdmin(): void
    {
        if (!$this->isAdmin()) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Acesso não autorizado',
            ], 403);
            exit;
        }
    }
    
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        $this->json($data, $statusCode);
    }

    private function getJsonInput(): array
    {
        return $this->request->json() ?? [];
    }
}
