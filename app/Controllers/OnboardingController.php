<?php

namespace App\Controllers;

use App\Database;

class OnboardingController
{
    public function __construct()
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (empty($_SESSION['user_id'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Não autenticado']);
            exit;
        }
    }

    public function complete(): void
    {
        $this->persistEvent('onboarding_complete');
    }

    public function completeTour(): void
    {
        $input = json_decode(file_get_contents('php://input'), true) ?? [];
        $tourId = $input['tour_id'] ?? null;
        $this->persistEvent('tour_complete', $tourId ? ['tour_id' => $tourId] : []);
    }

    private function persistEvent(string $action, array $data = []): void
    {
        $db = Database::getInstance();
        $userId = $_SESSION['user_id'];
        $mlAccountId = $_SESSION['active_ml_account_id'] ?? null;

        try {
            $stmt = $db->prepare("
                INSERT INTO audit_logs (user_id, ml_account_id, action, ip_address, user_agent, data, created_at)
                VALUES (:user_id, :ml_account_id, :action, :ip, :ua, :data, NOW())
            ");
            $stmt->execute([
                'user_id' => $userId,
                'ml_account_id' => $mlAccountId,
                'action' => $action,
                'ip' => $_SERVER['REMOTE_ADDR'] ?? null,
                'ua' => $_SERVER['HTTP_USER_AGENT'] ?? null,
                'data' => json_encode($data, JSON_UNESCAPED_UNICODE)
            ]);

            header('Content-Type: application/json');
            echo json_encode(['success' => true, 'action' => $action]);
        } catch (\Throwable $e) {
            http_response_code(500);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }
}
