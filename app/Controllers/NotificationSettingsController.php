<?php

namespace App\Controllers;

use App\Services\RealTimeNotificationService;

class NotificationSettingsController extends BaseController
{
    private RealTimeNotificationService $service;

    public function __construct()
    {
        // parent::__construct();
        $this->service = new RealTimeNotificationService();
    }

    /**
     * Render Settings View
     */
    public function index(): void
    {
        $settings = $this->service->getSettings($_SESSION['active_ml_account_id'] ?? 0);
        require __DIR__ . '/../Views/dashboard/notifications/settings.php';
    }

    /**
     * API to Get Settings
     */
    public function getSettings(): void
    {
        header('Content-Type: application/json');
        $accountId = $_SESSION['active_ml_account_id'] ?? 0;
        
        if (!$accountId) {
             echo json_encode(['success' => false, 'message' => 'Nenhuma conta ativa']);
             return;
        }
        
        $settings = $this->service->getSettings($accountId);
        echo json_encode(['success' => true, 'settings' => $settings]);
    }

    /**
     * API to Save Settings
     */
    public function saveSettings(): void
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);
        $accountId = $_SESSION['active_ml_account_id'] ?? 0;
        
        if (!$accountId) {
             echo json_encode(['success' => false, 'message' => 'Nenhuma conta ativa']);
             return;
        }
        
        try {
            // Bool conversion
            foreach (['email_orders', 'email_questions', 'whatsapp_orders', 'whatsapp_questions', 'whatsapp_low_stock', 'sound_enabled', 'desktop_enabled'] as $field) {
                if (isset($data[$field])) {
                    $data[$field] = filter_var($data[$field], FILTER_VALIDATE_BOOLEAN);
                }
            }
            
            $this->service->saveSettings($accountId, $data);
            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'message' => $e->getMessage()]);
        }
    }
}
