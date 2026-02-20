<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PushNotificationService;
use App\Services\UserService;
use App\Services\MobileDeviceService;

/**
 * Controller para gerenciamento de Push Notifications
 */
class PushController
{
    private PushNotificationService $pushService;
    private UserService $userService;
    private MobileDeviceService $mobileDeviceService;

    public function __construct()
    {
        $this->pushService = new PushNotificationService();
        $this->userService = new UserService();
        $this->mobileDeviceService = new MobileDeviceService();
    }

    /**
     * Retorna chave pública VAPID para o cliente
     * GET /api/push/vapid-key
     */
    public function vapidKey(): void
    {
        $this->jsonResponse([
            'success' => true,
            'publicKey' => $this->pushService->getVapidPublicKey()
        ]);
    }

    /**
     * Salva subscription de push notification
     * POST /api/push/subscribe
     */
    public function subscribe(): void
    {
        $user = $this->userService->getCurrentUser();

        if (!$user) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Usuário não autenticado'
            ], 401);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['subscription']) || !isset($input['subscription']['endpoint'])) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Subscription inválida'
            ], 400);
            return;
        }

        $result = $this->pushService->saveSubscription($user['id'], $input['subscription']);

        $this->jsonResponse($result, $result['success'] ? 200 : 500);
    }

    /**
     * Remove subscription de push notification
     * POST /api/push/unsubscribe
     */
    public function unsubscribe(): void
    {
        $user = $this->userService->getCurrentUser();

        if (!$user) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Usuário não autenticado'
            ], 401);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['endpoint'])) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Endpoint não fornecido'
            ], 400);
            return;
        }

        $result = $this->pushService->removeSubscription($user['id'], $input['endpoint']);

        $this->jsonResponse($result);
    }

    /**
     * Registra dispositivo móvel
     * POST /api/push/device/register
     */
    public function registerDevice(): void
    {
        $user = $this->userService->getCurrentUser();

        if (!$user) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($input['device_id']) || !isset($input['fcm_token'])) {
            $this->jsonResponse(['success' => false, 'error' => 'Invalid data'], 400);
            return;
        }

        $result = $this->mobileDeviceService->registerDevice(
            $user['id'],
            $input['device_id'],
            $input['fcm_token'],
            $input['platform'] ?? 'unknown',
            $input['app_version'] ?? '1.0.0'
        );

        $this->jsonResponse($result);
    }

    /**
     * Remove dispositivo móvel
     * POST /api/push/device/unregister
     */
    public function unregisterDevice(): void
    {
        $user = $this->userService->getCurrentUser();

        if (!$user) {
            $this->jsonResponse(['success' => false, 'error' => 'Unauthorized'], 401);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!isset($input['device_id'])) {
            $this->jsonResponse(['success' => false, 'error' => 'Device ID required'], 400);
            return;
        }

        $success = $this->mobileDeviceService->unregisterDevice($user['id'], $input['device_id']);

        $this->jsonResponse(['success' => $success]);
    }

    /**
     * Lista subscriptions do usuário
     * GET /api/push/subscriptions
     */
    public function subscriptions(): void
    {
        $user = $this->userService->getCurrentUser();

        if (!$user) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Usuário não autenticado'
            ], 401);
            return;
        }

        $subscriptions = $this->pushService->getUserSubscriptions($user['id']);

        // Sanitizar dados sensíveis
        $sanitized = array_map(function ($sub) {
            return [
                'id' => $sub['id'],
                'endpoint_preview' => substr($sub['endpoint'], 0, 50) . '...',
                'user_agent' => $sub['user_agent'],
                'created_at' => $sub['created_at'],
                'last_notified_at' => $sub['last_notified_at']
            ];
        }, $subscriptions);

        $this->jsonResponse([
            'success' => true,
            'subscriptions' => $sanitized,
            'count' => count($subscriptions)
        ]);
    }

    /**
     * Envia notificação de teste para o usuário atual
     * POST /api/push/test
     */
    public function test(): void
    {
        $user = $this->userService->getCurrentUser();

        if (!$user) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Usuário não autenticado'
            ], 401);
            return;
        }

        $result = $this->pushService->notifyAlert(
            $user['id'],
            '🔔 Teste de Notificação',
            'Suas notificações push estão funcionando corretamente!',
            ['type' => 'test', 'url' => '/dashboard']
        );

        $this->jsonResponse($result);
    }

    /**
     * Envia notificação para um usuário específico (admin)
     * POST /api/push/send
     */
    public function send(): void
    {
        $user = $this->userService->getCurrentUser();

        if (!$user) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Usuário não autenticado'
            ], 401);
            return;
        }

        // Verificar se é admin (role='admin' no banco)
        $isAdmin = isset($user['role']) && $user['role'] === 'admin';

        $input = json_decode(file_get_contents('php://input'), true);

        $targetUserId = $input['user_id'] ?? $user['id'];

        // Apenas admin pode enviar para outros usuários
        if (!$isAdmin && $targetUserId != $user['id']) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Sem permissão para enviar notificações a outros usuários'
            ], 403);
            return;
        }

        $title = $input['title'] ?? 'Notificação';
        $message = $input['message'] ?? 'Você tem uma nova notificação';
        $data = $input['data'] ?? [];

        $result = $this->pushService->notifyAlert($targetUserId, $title, $message, $data);

        $this->jsonResponse($result);
    }

    /**
     * Estatísticas de push notifications
     * GET /api/push/stats
     */
    public function stats(): void
    {
        $user = $this->userService->getCurrentUser();

        if (!$user) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Usuário não autenticado'
            ], 401);
            return;
        }

        $stats = $this->pushService->getStats();

        $this->jsonResponse([
            'success' => true,
            'stats' => $stats
        ]);
    }

    /**
     * Verifica status das notificações push
     * GET /api/push/status
     */
    public function status(): void
    {
        $user = $this->userService->getCurrentUser();

        if (!$user) {
            $this->jsonResponse([
                'success' => false,
                'error' => 'Usuário não autenticado'
            ], 401);
            return;
        }

        $subscriptions = $this->pushService->getUserSubscriptions($user['id']);

        $this->jsonResponse([
            'success' => true,
            'enabled' => count($subscriptions) > 0,
            'subscriptions_count' => count($subscriptions),
            'vapid_configured' => !empty($this->pushService->getVapidPublicKey())
        ]);
    }

    /**
     * Rastreia instalação do PWA
     * POST /api/push/track-install
     */
    public function trackInstall(): void
    {
        $user = $this->userService->getCurrentUser();
        $input = json_decode(file_get_contents('php://input'), true);

        $data = [
            'user_id' => $user['id'] ?? null,
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'platform' => $input['platform'] ?? 'unknown',
            'installed_at' => date('Y-m-d H:i:s'),
        ];

        // Log da instalação
        $logFile = __DIR__ . '/../../storage/logs/pwa_installs.log';
        file_put_contents(
            $logFile,
            date('[Y-m-d H:i:s] ') . json_encode($data) . PHP_EOL,
            FILE_APPEND
        );

        $this->jsonResponse([
            'success' => true,
            'message' => 'Instalação rastreada com sucesso',
        ]);
    }

    /**
     * Envia resposta JSON
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
