<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\VAPID;
use PDO;
use Exception;

/**
 * Service para gerenciamento de Push Notifications via Web Push API
 *
 * Usa VAPID (Voluntary Application Server Identification) para autenticação
 * Suporta armazenamento de subscriptions e envio de notificações
 * Utiliza minishlink/web-push para envio real de notificações
 */
class PushNotificationService
{
    private ?PDO $db = null;
    private string $vapidPublicKey = '';
    private string $vapidPrivateKey = '';
    private string $vapidSubject;
    private ?WebPush $webPush = null;

    public function __construct(?PDO $db = null, ?WebPush $webPush = null, bool $skipDbAutoConnect = false)
    {
        $this->db = $db;
        $this->webPush = $webPush;
        $this->vapidPublicKey = (string) ($_ENV['VAPID_PUBLIC_KEY'] ?? getenv('VAPID_PUBLIC_KEY') ?? '');
        $this->vapidPrivateKey = (string) ($_ENV['VAPID_PRIVATE_KEY'] ?? getenv('VAPID_PRIVATE_KEY') ?? '');
        $this->vapidSubject = (string) ($_ENV['VAPID_SUBJECT'] ?? getenv('VAPID_SUBJECT') ?? 'mailto:admin@eskill.com.br');

        // Gerar chaves se não existirem
        if ($this->vapidPublicKey === '' || $this->vapidPrivateKey === '') {
            $this->generateVapidKeys();
        }

        if ($this->db === null && !$skipDbAutoConnect) {
            try {
                $this->db = Database::getInstance();
            } catch (\Throwable $e) {
                log_warning('PushNotificationService: DB indisponível', [
                    'error' => $e->getMessage(),
                ]);
                $this->db = null;
            }
        }

        if ($this->db !== null) {
            try {
                $this->ensureTable();
            } catch (\Throwable $e) {
                log_error('PushNotificationService: falha ao garantir tabela', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($this->webPush === null) {
            $this->initWebPush();
        }
    }

    /**
     * Inicializa o WebPush com VAPID
     */
    private function initWebPush(): void
    {
        try {
            $auth = [
                'VAPID' => [
                    'subject' => $this->vapidSubject,
                    'publicKey' => $this->vapidPublicKey,
                    'privateKey' => $this->vapidPrivateKey,
                ]
            ];

            $this->webPush = new WebPush($auth);
            $this->webPush->setReuseVAPIDHeaders(true);
        } catch (Exception $e) {
            log_error('WebPush init error', ['service' => 'PushNotificationService', 'error' => $e->getMessage()]);
            $this->webPush = null;
        }
    }

    /**
     * Garante que a tabela de subscriptions existe
     */
    private function ensureTable(): void
    {
        if ($this->db === null) {
            return;
        }

        $this->db->exec("
            CREATE TABLE IF NOT EXISTS push_subscriptions (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                endpoint TEXT NOT NULL,
                p256dh_key VARCHAR(255),
                auth_key VARCHAR(255),
                user_agent VARCHAR(255),
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                last_notified_at DATETIME NULL,
                INDEX idx_user_id (user_id),
                UNIQUE KEY idx_endpoint_hash (endpoint(191))
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
        ");
    }

    /**
     * Gera par de chaves VAPID usando a biblioteca
     */
    private function generateVapidKeys(): void
    {
        try {
            $keys = VAPID::createVapidKeys();
            $this->vapidPublicKey = $keys['publicKey'];
            $this->vapidPrivateKey = $keys['privateKey'];

            // Salvar em .env se possível (ou logar para manual)
            log_warning('VAPID keys generated - add to .env', [
                'service' => 'PushNotificationService',
                'public_key' => $this->vapidPublicKey,
                'note' => 'Set VAPID_PUBLIC_KEY and VAPID_PRIVATE_KEY in .env',
            ]);
        } catch (Exception $e) {
            // Fallback para chaves aleatórias (não funcionais para push real)
            $this->vapidPublicKey = base64_encode(random_bytes(65));
            $this->vapidPrivateKey = base64_encode(random_bytes(32));
        }
    }

    /**
     * Retorna chave pública VAPID para o cliente
     */
    public function getVapidPublicKey(): string
    {
        return $this->vapidPublicKey;
    }

    /**
     * Salva subscription de push notification
     */
    public function saveSubscription(int $userId, array $subscription): array
    {
        if ($this->db === null) {
            return [
                'success' => false,
                'error' => 'Banco de dados indisponível',
            ];
        }

        try {
            // Verificar se já existe subscription para este endpoint
            $existing = $this->findSubscriptionByEndpoint($subscription['endpoint']);

            if ($existing) {
                // Atualizar subscription existente
                $stmt = $this->db->prepare("
                    UPDATE push_subscriptions
                    SET user_id = :user_id,
                        p256dh_key = :p256dh,
                        auth_key = :auth,
                        updated_at = NOW()
                    WHERE endpoint = :endpoint
                ");

                $stmt->execute([
                    'user_id' => $userId,
                    'p256dh' => $subscription['keys']['p256dh'] ?? null,
                    'auth' => $subscription['keys']['auth'] ?? null,
                    'endpoint' => $subscription['endpoint']
                ]);

                return [
                    'success' => true,
                    'message' => 'Subscription atualizada com sucesso',
                    'id' => $existing['id']
                ];
            }

            // Criar nova subscription
            $stmt = $this->db->prepare("
                INSERT INTO push_subscriptions
                (user_id, endpoint, p256dh_key, auth_key, user_agent, created_at, updated_at)
                VALUES (:user_id, :endpoint, :p256dh, :auth, :user_agent, NOW(), NOW())
            ");

            $stmt->execute([
                'user_id' => $userId,
                'endpoint' => $subscription['endpoint'],
                'p256dh' => $subscription['keys']['p256dh'] ?? null,
                'auth' => $subscription['keys']['auth'] ?? null,
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
            ]);

            return [
                'success' => true,
                'message' => 'Subscription criada com sucesso',
                'id' => $this->db->lastInsertId()
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao salvar subscription: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Remove subscription
     */
    public function removeSubscription(int $userId, string $endpoint): array
    {
        if ($this->db === null) {
            return [
                'success' => false,
                'error' => 'Banco de dados indisponível',
            ];
        }

        try {
            $stmt = $this->db->prepare("
                DELETE FROM push_subscriptions
                WHERE user_id = :user_id AND endpoint = :endpoint
            ");

            $stmt->execute([
                'user_id' => $userId,
                'endpoint' => $endpoint
            ]);

            return [
                'success' => true,
                'message' => 'Subscription removida com sucesso'
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => 'Erro ao remover subscription: ' . $e->getMessage()
            ];
        }
    }

    /**
     * Encontra subscription por endpoint
     */
    public function findSubscriptionByEndpoint(string $endpoint): ?array
    {
        if ($this->db === null) {
            return null;
        }

        $stmt = $this->db->prepare("
            SELECT * FROM push_subscriptions WHERE endpoint = :endpoint
        ");
        $stmt->execute(['endpoint' => $endpoint]);

        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return $result ?: null;
    }

    /**
     * Obtém todas as subscriptions de um usuário
     */
    public function getUserSubscriptions(int $userId): array
    {
        if ($this->db === null) {
            return [];
        }

        $stmt = $this->db->prepare("
            SELECT * FROM push_subscriptions
            WHERE user_id = :user_id
            ORDER BY created_at DESC
        ");
        $stmt->execute(['user_id' => $userId]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Envia notificação push para um usuário
     */
    public function sendToUser(int $userId, array $payload): array
    {
        $subscriptions = $this->getUserSubscriptions($userId);

        if (empty($subscriptions)) {
            return [
                'success' => false,
                'error' => 'Usuário não possui subscriptions ativas'
            ];
        }

        $results = [];

        foreach ($subscriptions as $subscription) {
            $result = $this->sendNotification($subscription, $payload);
            $results[] = [
                'endpoint' => substr($subscription['endpoint'], 0, 50) . '...',
                'success' => $result['success'],
                'error' => $result['error'] ?? null
            ];

            // Se a subscription expirou, remover
            if (!$result['success'] && isset($result['expired']) && $result['expired']) {
                $this->removeSubscription($userId, $subscription['endpoint']);
            }
        }

        return [
            'success' => true,
            'sent' => count(array_filter($results, fn(array $r): bool => $r['success'])),
            'failed' => count(array_filter($results, fn(array $r): bool => !$r['success'])),
            'results' => $results
        ];
    }

    /**
     * Envia notificação push para uma subscription específica
     * Usa a biblioteca minishlink/web-push para envio real
     */
    public function sendNotification(array $subscription, array $payload): array
    {
        try {
            // Se WebPush não está disponível, usar fallback
            if (!$this->webPush) {
                return $this->sendNotificationFallback($subscription, $payload);
            }

            $endpoint = $subscription['endpoint'];
            $p256dh = $subscription['p256dh_key'];
            $auth = $subscription['auth_key'];

            // Criar objeto Subscription
            $sub = Subscription::create([
                'endpoint' => $endpoint,
                'publicKey' => $p256dh,
                'authToken' => $auth,
            ]);

            // Preparar payload
            $payloadJson = json_encode($payload);

            // Enfileirar notificação
            $this->webPush->queueNotification($sub, $payloadJson);

            // Enviar todas as notificações enfileiradas
            $reports = [];
            foreach ($this->webPush->flush() as $report) {
                $reports[] = $report;
            }

            // Verificar resultado
            if (empty($reports)) {
                return ['success' => false, 'error' => 'Nenhum relatório de envio'];
            }

            $report = $reports[0];

            if ($report->isSuccess()) {
                // Atualizar última notificação
                $this->updateLastNotified($subscription['id']);
                return ['success' => true];
            }

            // Verificar se subscription expirou
            if ($report->isSubscriptionExpired()) {
                return [
                    'success' => false,
                    'expired' => true,
                    'error' => 'Subscription expirada ou inválida'
                ];
            }

            return [
                'success' => false,
                'error' => $report->getReason()
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Fallback para envio de notificação via cURL (quando WebPush não disponível)
     */
    private function sendNotificationFallback(array $subscription, array $payload): array
    {
        try {
            $endpoint = $subscription['endpoint'];
            $payloadJson = json_encode($payload);

            $headers = [
                'Content-Type: application/json',
                'TTL: 86400',
                'Content-Length: ' . strlen($payloadJson)
            ];

            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $endpoint,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payloadJson,
                CURLOPT_HTTPHEADER => $headers,
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 30,
                CURLOPT_SSL_VERIFYPEER => true
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $error = curl_error($ch);
            curl_close($ch);

            if ($httpCode >= 200 && $httpCode < 300) {
                $this->updateLastNotified($subscription['id']);
                return ['success' => true];
            }

            if ($httpCode === 404 || $httpCode === 410) {
                return ['success' => false, 'expired' => true, 'error' => 'Subscription expirada'];
            }

            return ['success' => false, 'error' => "HTTP $httpCode: " . ($error ?: $response)];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }

    /**
     * Atualiza timestamp da última notificação
     */
    private function updateLastNotified(int $subscriptionId): void
    {
        if ($this->db === null) {
            return;
        }

        $stmt = $this->db->prepare("
            UPDATE push_subscriptions
            SET last_notified_at = NOW()
            WHERE id = :id
        ");
        $stmt->execute(['id' => $subscriptionId]);
    }

    /**
     * Envia notificação para todos os usuários
     */
    public function sendToAll(array $payload): array
    {
        if ($this->db === null) {
            return [
                'success' => false,
                'error' => 'Banco de dados indisponível',
            ];
        }

        $stmt = $this->db->query("SELECT DISTINCT user_id FROM push_subscriptions");
        $userIds = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $results = [];
        foreach ($userIds as $userId) {
            $results[$userId] = $this->sendToUser($userId, $payload);
        }

        return [
            'success' => true,
            'users_notified' => count($userIds),
            'results' => $results
        ];
    }

    /**
     * Envia notificação de nova venda
     */
    public function notifyNewSale(int $userId, array $orderData): array
    {
        $payload = [
            'title' => '🛒 Nova Venda!',
            'body' => "Pedido #{$orderData['id']} - R$ " . number_format($orderData['total'], 2, ',', '.'),
            'icon' => '/icons/icon-192x192.png',
            'badge' => '/icons/badge-72x72.png',
            'tag' => 'new-sale-' . $orderData['id'],
            'data' => [
                'type' => 'order',
                'orderId' => $orderData['id'],
                'url' => '/dashboard/orders?highlight=' . $orderData['id']
            ],
            'actions' => [
                ['action' => 'view', 'title' => 'Ver Pedido'],
                ['action' => 'dismiss', 'title' => 'Dispensar']
            ],
            'requireInteraction' => true
        ];

        return $this->sendToUser($userId, $payload);
    }

    /**
     * Envia notificação de estoque baixo
     */
    public function notifyLowStock(int $userId, array $items): array
    {
        $count = count($items);
        $payload = [
            'title' => '⚠️ Estoque Baixo',
            'body' => "$count produto(s) com estoque baixo",
            'icon' => '/icons/icon-192x192.png',
            'badge' => '/icons/badge-72x72.png',
            'tag' => 'low-stock',
            'data' => [
                'type' => 'alert',
                'alertType' => 'low_stock',
                'url' => '/dashboard?filter=low_stock'
            ]
        ];

        return $this->sendToUser($userId, $payload);
    }

    /**
     * Envia notificação de alerta genérico
     */
    public function notifyAlert(int $userId, string $title, string $message, array $data = []): array
    {
        $payload = [
            'title' => $title,
            'body' => $message,
            'icon' => '/icons/icon-192x192.png',
            'badge' => '/icons/badge-72x72.png',
            'tag' => 'alert-' . time(),
            'data' => array_merge(['type' => 'alert'], $data)
        ];

        return $this->sendToUser($userId, $payload);
    }

    /**
     * Estatísticas de subscriptions
     */
    public function getStats(): array
    {
        if ($this->db === null) {
            return [
                'total_subscriptions' => 0,
                'users_with_push' => 0,
                'active_subscriptions' => 0,
                'by_browser' => [],
            ];
        }

        $stats = [];

        // Total de subscriptions
        $stmt = $this->db->query("SELECT COUNT(*) FROM push_subscriptions");
        $stats['total_subscriptions'] = (int) $stmt->fetchColumn();

        // Subscriptions por usuário
        $stmt = $this->db->query("SELECT COUNT(DISTINCT user_id) FROM push_subscriptions");
        $stats['users_with_push'] = (int) $stmt->fetchColumn();

        // Subscriptions ativas (notificadas nos últimos 30 dias)
        $stmt = $this->db->query("
            SELECT COUNT(*) FROM push_subscriptions
            WHERE last_notified_at > DATE_SUB(NOW(), INTERVAL 30 DAY)
        ");
        $stats['active_subscriptions'] = (int) $stmt->fetchColumn();

        // Por navegador (baseado no endpoint)
        $stmt = $this->db->query("
            SELECT
                CASE
                    WHEN endpoint LIKE '%fcm.googleapis.com%' THEN 'Chrome/Firefox'
                    WHEN endpoint LIKE '%mozilla%' THEN 'Firefox'
                    WHEN endpoint LIKE '%apple%' THEN 'Safari'
                    ELSE 'Outros'
                END as browser,
                COUNT(*) as count
            FROM push_subscriptions
            GROUP BY browser
        ");
        $stats['by_browser'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $stats;
    }

    /**
     * Limpa subscriptions antigas/expiradas
     */
    public function cleanExpiredSubscriptions(int $daysOld = 90): int
    {
        if ($this->db === null) {
            return 0;
        }

        $stmt = $this->db->prepare("
            DELETE FROM push_subscriptions
            WHERE updated_at < DATE_SUB(NOW(), INTERVAL :days DAY)
        ");
        $stmt->execute(['days' => $daysOld]);

        return $stmt->rowCount();
    }
}
