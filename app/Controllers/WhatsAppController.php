<?php

namespace App\Controllers;

use App\Services\WhatsAppService;
use App\Services\UserService;
use App\Services\AuditLogService;

class WhatsAppController extends BaseController
{
    private WhatsAppService $whatsappService;
    private UserService $userService;
    private AuditLogService $auditService;

    public function __construct()
    {
        parent::__construct();
        $this->userService = new UserService();
        $this->auditService = new AuditLogService();

        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        if (!$this->userService->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        $this->whatsappService = new WhatsAppService($_SESSION['user_id']);
    }

    public function index(): void
    {
        $settings = $this->whatsappService->getSettings();
        $logs = $this->whatsappService->getLogs();
        $user = $this->userService->getCurrentUser();

        $pageTitle = 'WhatsApp Integration';
        $activePage = 'whatsapp';

        ob_start();
        require __DIR__ . '/../Views/dashboard/whatsapp.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layouts/modern/app.php';
    }

    public function save(): void
    {
        if ($this->request->method() !== 'POST') {
            header('Location: /dashboard/whatsapp');
            exit;
        }

        $data = [
            'provider' => $this->request->post('provider', ''),
            'api_url' => $this->request->post('api_url'),
            'api_key' => $this->request->post('api_key'),
            'api_secret' => $this->request->post('api_secret'),
            'from_number' => $this->request->post('from_number'),
            'is_active' => $this->request->postInt('is_active', 0) ? 1 : 0
        ];

        $this->whatsappService->saveSettings($data);

        $this->auditService->log('whatsapp_settings_update', $_SESSION['user_id'], null, [
            'provider' => $data['provider'],
            'is_active' => $data['is_active']
        ]);

        $_SESSION['flash_message'] = 'Configurações salvas com sucesso!';
        $_SESSION['flash_type'] = 'success';

        header('Location: /dashboard/whatsapp');
    }

    public function test(): void
    {
        if ($this->request->method() !== 'POST') {
            $this->jsonError('Método não permitido', 405);
        }

        $phone = $this->request->post('phone', '');
        $message = $this->request->post('message', '');

        if (empty($phone) || empty($message)) {
            $this->jsonError('Telefone e mensagem são obrigatórios', 400);
        }

        $result = $this->whatsappService->send($phone, $message);
        $this->json($result);
    }
}
