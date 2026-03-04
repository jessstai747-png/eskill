<?php

declare(strict_types=1);

namespace App\Services;

use App\Services\OrderService;
use App\Services\ItemService;
use App\Services\QuestionService;
use App\Services\NotificationService;
use App\Services\StructuredLogService;
use PDO;

/**
 * Service to process Mercado Livre Webhooks
 * Handles routing of events to specific domain services.
 */
class MercadoLivreWebhookService
{
    private int $accountId;
    private StructuredLogService $logger;
    private ?OrderService $orderService = null;
    private ?ItemService $itemService = null;
    private ?QuestionService $questionService = null;
    private ?NotificationService $notificationService = null;
    private ?PDO $db = null;
    private bool $skipDbAutoConnect;

    /**
     * @param int $accountId ID da conta ML
     * @param StructuredLogService|null $logger Logger (injetável para testes)
     * @param OrderService|null $orderService (injetável para testes)
     * @param ItemService|null $itemService (injetável para testes)
     * @param QuestionService|null $questionService (injetável para testes)
     * @param NotificationService|null $notificationService (injetável para testes)
     * @param PDO|null $db Conexão ao banco (injetável para testes)
     * @param bool $skipDbAutoConnect Se true, não conecta ao DB automaticamente
     */
    public function __construct(
        int $accountId,
        ?StructuredLogService $logger = null,
        ?OrderService $orderService = null,
        ?ItemService $itemService = null,
        ?QuestionService $questionService = null,
        ?NotificationService $notificationService = null,
        ?PDO $db = null,
        bool $skipDbAutoConnect = false
    ) {
        $this->accountId = $accountId;
        $this->logger = $logger ?? new StructuredLogService();
        $this->orderService = $orderService;
        $this->itemService = $itemService;
        $this->questionService = $questionService;
        $this->notificationService = $notificationService;
        $this->db = $db;
        $this->skipDbAutoConnect = $skipDbAutoConnect;
    }

    /**
     * Process a single webhook event
     * 
     * @param array $payload Webhook payload from ML
     * @return array Result ['success' => bool, 'error' => ?string, 'event_id' => ?string]
     */
    public function processWebhookEvent(array $payload): array
    {
        $topic = $payload['topic'] ?? null;
        $resource = $payload['resource'] ?? null;
        $userId = $payload['user_id'] ?? null;
        $applicationId = $payload['application_id'] ?? null;

        // Basic validation
        if (!$topic || !$resource) {
            $this->logger->warning("Webhook received without topic or resource", ['payload' => $payload]);
            return ['success' => false, 'error' => 'Missing topic or resource'];
        }

        $eventId = uniqid('evt_');
        $this->logger->info("Processing Webhook Event [{$eventId}]", [
            'topic' => $topic,
            'resource' => $resource,
            'account_id' => $this->accountId
        ]);

        try {
            $handled = true;
            switch ($topic) {
                case 'orders_v2':
                case 'orders':
                    $this->handleOrderEvent($resource);
                    break;

                case 'items':
                    $this->handleItemEvent($resource);
                    break;

                case 'questions':
                    $this->handleQuestionEvent($resource);
                    break;
                
                case 'claims':
                    $this->handleClaimEvent($resource);
                    break;

                case 'messages':
                    $this->handleMessageEvent($resource);
                    break;

                case 'shipments':
                    $this->handleShipmentEvent($resource);
                    break;

                case 'payment':
                case 'payments':
                    $this->handlePaymentEvent($resource);
                    break;

                case 'feedback':
                case 'created_in_feedback':
                    $this->handleFeedbackEvent($resource);
                    break;

                default:
                    $handled = false;
                    break;
            }

            if (!$handled) {
                $strictMode = $this->isStrictUnknownTopicModeEnabled();
                $this->logger->warning('Webhook topic desconhecido', [
                    'topic' => $topic,
                    'resource' => $resource,
                    'account_id' => $this->accountId,
                    'strict_mode' => $strictMode,
                ]);

                if ($strictMode) {
                    return [
                        'success' => false,
                        'error' => 'Unknown webhook topic: ' . (string)$topic,
                        'event_id' => $eventId,
                        'ignored' => false,
                        'strict_mode' => true,
                    ];
                }

                return [
                    'success' => true,
                    'event_id' => $eventId,
                    'ignored' => true,
                    'ignored_reason' => 'unknown_topic',
                    'topic' => (string)$topic,
                ];
            }

            return ['success' => true, 'event_id' => $eventId];

        } catch (\Throwable $e) {
            $this->logger->error("Error processing webhook [{$eventId}]", [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    private function isStrictUnknownTopicModeEnabled(): bool
    {
        $value = $_ENV['ML_WEBHOOK_STRICT_TOPICS'] ?? getenv('ML_WEBHOOK_STRICT_TOPICS') ?? '0';
        $normalized = strtolower(trim((string)$value));
        return in_array($normalized, ['1', 'true', 'yes', 'on', 'enabled'], true);
    }

    private function handleOrderEvent(string $resource): void
    {
        // Resource format: /orders/{checkoutId} or /orders/{orderId}
        // Actually ML sends /orders/{id}
        $parts = explode('/', $resource);
        $orderId = end($parts);

        if (!$orderId) {
            throw new \Exception("Invalid order resource: {$resource}");
        }

        $order = $this->getOrderService()->getOrder($orderId, ['allow_local_cache' => true]); // Sync/cache com fallback explícito
        if (!empty($order['error'])) {
            throw new \RuntimeException((string)($order['message'] ?? 'Falha ao carregar pedido do webhook'));
        }

        // Notify UI/User
        $userId = $this->getUserIdFromAccount(); 
        if ($userId) {
            $total = $order['total_amount'] ?? ($order['data']['total_amount'] ?? '---');
            $this->getNotificationService()->create(
                $userId,
                'order_new',
                "Nova Venda #{$orderId}",
                "Valor: {$total}",
                ['order_id' => $orderId]
            );
        }
    }

    private function handleItemEvent(string $resource): void
    {
        // Resource format: /items/{id}
        $parts = explode('/', $resource);
        $itemId = end($parts);

        if (!$itemId) {
            throw new \Exception("Invalid item resource: {$resource}");
        }

        // 1. Force Sync Item to Local DB
        $this->getItemService()->syncItem($itemId); 

        // 2. Refresh Tech Sheet Analysis
        try {
            $this->getTechSheetService()->getItem($itemId);
            $this->logger->info("TechSheet refreshed for item {$itemId}");
        } catch (\Exception $e) {
             $this->logger->warning("Failed to refresh TechSheet for {$itemId}", ['error' => $e->getMessage()]);
        }
    }

    private function handleQuestionEvent(string $resource): void
    {
        // Resource format: /questions/{id}
        $parts = explode('/', $resource);
        $questionId = end($parts);

        if (!$questionId) {
            throw new \Exception("Invalid question resource: {$resource}");
        }

        $qService = $this->getQuestionService();
        $question = $qService->syncSingleQuestion($questionId);
        if (!empty($question['error'])) {
            throw new \RuntimeException((string)($question['message'] ?? 'Falha ao sincronizar pergunta do webhook'));
        }
        
        // Notify User
        $userId = $this->getUserIdFromAccount();
        if ($userId) {
             $itemTitle = $question['item_title'] ?? ($question['item']['title'] ?? 'Anúncio');
             $text = $question['text'] ?? 'Nova pergunta';
             $this->getNotificationService()->create(
                $userId,
                'question_new',
                "Nova Pergunta",
                "Item: {$itemTitle}\nPgta: {$text}",
                ['question_id' => $questionId, 'item_id' => $question['item_id'] ?? null]
            );
        }
        
        // Attempt auto-reply or draft generation
        try {
            $qService->generateDraftAnswer($questionId);
        } catch (\Exception $e) {
            $this->logger->warning("Failed to generate draft for question {$questionId}", ['error' => $e->getMessage()]);
        }
    }

    private function handleMessageEvent(string $resource): void
    {
        $this->logger->info("New Message event", ['resource' => $resource]);
        
        $parts = explode('/', $resource);
        $id = end($parts);
        
        if (!$id) {
            return;
        }

        $messagingService = new \App\Services\MessagingService($this->accountId);
        $message = $messagingService->getMessage($id);
        if (!empty($message['error'])) {
            $this->logger->warning('Failed to fetch message', ['message_id' => $id, 'error' => $message['error']]);
            return;
        }

        $messagingService->processIncomingMessage([
            'text' => $message['text'] ?? '',
            'from' => ['user_id' => $message['from'] ?? null],
            'to' => ['user_id' => $message['to'] ?? null],
        ]);
    }

    private function handleShipmentEvent(string $resource): void
    {
        // Resource: /shipments/{shipmentId}
        $parts = explode('/', $resource);
        $shipmentId = end($parts);

        if (!$shipmentId) {
            throw new \Exception("Invalid shipment resource: {$resource}");
        }

        $orderService = $this->getOrderService();
        $shipment = $orderService->getShipment($shipmentId);

        if (!empty($shipment['error'])) {
            $this->logger->warning('ML_WEBHOOK_SHIPMENT_FETCH_FAILED', [
                'shipment_id' => $shipmentId,
                'error' => $shipment['message'] ?? $shipment['error'],
            ]);
            return;
        }

        $status = $shipment['status'] ?? 'unknown';
        $substatus = $shipment['substatus'] ?? null;
        $trackingNumber = $shipment['tracking_number'] ?? null;

        $this->logger->info('ML_WEBHOOK_SHIPMENT_UPDATED', [
            'shipment_id' => $shipmentId,
            'status' => $status,
            'substatus' => $substatus,
            'tracking_number' => $trackingNumber,
            'account_id' => $this->accountId,
        ]);

        // Notify user only on significant status transitions
        $notifyStatuses = ['ready_to_ship', 'shipped', 'delivered', 'not_delivered', 'cancelled'];
        if (in_array($status, $notifyStatuses, true)) {
            $userId = $this->getUserIdFromAccount();
            if ($userId) {
                $statusLabels = [
                    'ready_to_ship' => '📦 Pronto para Envio',
                    'shipped' => '🚚 Enviado',
                    'delivered' => '✅ Entregue',
                    'not_delivered' => '⚠️ Não Entregue',
                    'cancelled' => '❌ Cancelado',
                ];
                $label = $statusLabels[$status] ?? ucfirst($status);
                $detail = $trackingNumber ? "Rastreio: {$trackingNumber}" : "Envio #{$shipmentId}";
                $this->getNotificationService()->create(
                    $userId,
                    'shipment_' . $status,
                    "Envio {$label}",
                    $detail,
                    ['shipment_id' => $shipmentId, 'status' => $status, 'tracking_number' => $trackingNumber]
                );
            }
        }
    }

    private function handlePaymentEvent(string $resource): void
    {
        // Resource: /collections/{paymentId} or /payments/{paymentId}
        $parts = explode('/', $resource);
        $paymentId = end($parts);

        if (!$paymentId) {
            $this->logger->warning('ML_WEBHOOK_PAYMENT_INVALID_RESOURCE', ['resource' => $resource]);
            return;
        }

        // Fetch payment details from ML
        $client = $this->getMlClient();
        $paymentData = [];
        try {
            // The ML payments API endpoint
            $response = $client->get("/collections/notifications/{$paymentId}");
            if (!isset($response['error'])) {
                $paymentData = $response['collection'] ?? $response;
            }
        } catch (\Throwable $e) {
            $this->logger->warning('ML_WEBHOOK_PAYMENT_FETCH_FAILED', [
                'payment_id' => $paymentId,
                'error' => $e->getMessage(),
            ]);
        }

        $status = $paymentData['status'] ?? 'unknown';
        $orderId = $paymentData['order_id'] ?? null;

        $this->logger->info('ML_WEBHOOK_PAYMENT_RECEIVED', [
            'payment_id' => $paymentId,
            'status' => $status,
            'order_id' => $orderId,
            'account_id' => $this->accountId,
        ]);

        // For approved payments, trigger order sync to get latest state
        if (in_array($status, ['approved', 'in_process'], true) && $orderId) {
            try {
                $this->getOrderService()->getOrder((string)$orderId);
            } catch (\Throwable $e) {
                $this->logger->warning('ML_WEBHOOK_PAYMENT_ORDER_SYNC_FAILED', [
                    'order_id' => $orderId,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // Notify user on approved payment
        if ($status === 'approved') {
            $userId = $this->getUserIdFromAccount();
            if ($userId) {
                $amount = isset($paymentData['transaction_amount'])
                    ? 'R$ ' . number_format((float)$paymentData['transaction_amount'], 2, ',', '.')
                    : '---';
                $this->getNotificationService()->create(
                    $userId,
                    'payment_approved',
                    '💰 Pagamento Aprovado',
                    "Valor: {$amount}" . ($orderId ? " | Pedido #{$orderId}" : ''),
                    ['payment_id' => $paymentId, 'order_id' => $orderId, 'amount' => $paymentData['transaction_amount'] ?? null]
                );
            }
        }
    }

    private function handleFeedbackEvent(string $resource): void
    {
        // Resource: /orders/{orderId}/feedbacks/{id} or /feedback/{id}
        $parts = explode('/', ltrim($resource, '/'));
        $feedbackId = end($parts);

        $this->logger->info('ML_WEBHOOK_FEEDBACK_RECEIVED', [
            'resource' => $resource,
            'feedback_id' => $feedbackId,
            'account_id' => $this->accountId,
        ]);

        // Notify user — feedbacks affect seller reputation
        $userId = $this->getUserIdFromAccount();
        if ($userId) {
            $this->getNotificationService()->create(
                $userId,
                'feedback_new',
                '⭐ Nova Avaliação Recebida',
                'Verifique sua reputação no painel.',
                ['resource' => $resource, 'feedback_id' => $feedbackId]
            );
        }
    }

    private function handleClaimEvent(string $resource): void
    {
        // Resource: /v1/claims/{claimId}
        $parts = explode('/', $resource);
        $claimId = end($parts);
        
        if (!$claimId) {
            throw new \Exception("Invalid claim resource: {$resource}");
        }
        
        $claimService = new \App\Services\ClaimsService($this->accountId);
        $claimService->syncClaim($claimId);
        
        // Notify Urgent
        $userId = $this->getUserIdFromAccount();
        if ($userId) {
             $this->getNotificationService()->create(
                $userId,
                'claim_new',
                "⚠️ Nova Reclamação #{$claimId}",
                "Responda imediatamente para evitar impacto na reputação.",
                ['claim_id' => $claimId, 'priority' => 'critical']
            );
            
            // External Alert
            $this->getNotificationService()->sendAlert(
                "Nova Reclamação #{$claimId}", 
                "Conta {$this->accountId}: Nova reclamação recebida.", 
                "CRITICAL"
            );
        }
        
        $this->logger->info("Claim Synced", ['claim_id' => $claimId]);
    }
    
    private function getUserIdFromAccount(): ?int
    {
        try {
            $db = $this->db;
            if ($db === null && !$this->skipDbAutoConnect) {
                $db = \App\Database::getInstance();
            }
            if ($db === null) {
                return null;
            }
            $stmt = $db->prepare("SELECT user_id FROM ml_accounts WHERE id = ?");
            $stmt->execute([$this->accountId]);
            $result = $stmt->fetchColumn();
            return $result !== false && $result !== null ? (int)$result : null;
        } catch (\Exception $e) {
            return null;
        }
    }

    // Lazy Loaders (respeitam instâncias injetadas via construtor)
    
    private function getOrderService(): OrderService
    {
        if ($this->orderService === null) {
            $this->orderService = new OrderService($this->accountId);
        }
        return $this->orderService;
    }

    /** Expõe o MercadoLivreClient para uso interno dos handlers */
    private function getMlClient(): \App\Services\MercadoLivreClient
    {
        return new \App\Services\MercadoLivreClient($this->accountId);
    }

    private function getItemService(): ItemService
    {
        if ($this->itemService === null) {
            $this->itemService = new ItemService($this->accountId);
        }
        return $this->itemService;
    }
    
    private function getTechSheetService(): \App\Services\TechSheetService
    {
        return new \App\Services\TechSheetService($this->accountId);
    }

    private function getQuestionService(): QuestionService
    {
        if ($this->questionService === null) {
            $this->questionService = new QuestionService($this->accountId);
        }
        return $this->questionService;
    }

    private function getNotificationService(): NotificationService
    {
        if ($this->notificationService === null) {
            $this->notificationService = new NotificationService();
        }
        return $this->notificationService;
    }
}
