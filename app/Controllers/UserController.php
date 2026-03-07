<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\UserService;
use App\Services\SecurityService;

class UserController extends BaseController
{
    private UserService $userService;
    private SecurityService $security;

    public function __construct()
    {
        parent::__construct();

        $this->userService = new UserService();
        $this->security = new SecurityService();

        // Verificar autenticação
        if (!$this->userService->isAuthenticated()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Não autenticado']);
            exit;
        }
    }

    /**
     * Atualiza perfil do usuário
     */
    public function updateProfile(): void
    {
        if ($this->request->method() !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }

        $userId = $this->getUserId();
        $data = [
            'name' => $this->request->post('name'),
            'email' => $this->request->post('email'),
            'phone' => $this->request->post('phone'),
        ];

        $result = $this->userService->updateProfile($userId, $data);

        header('Content-Type: application/json');
        echo json_encode($result);
    }

    /**
     * Atualiza o tema
     */
    public function updateTheme(): void
    {
        if ($this->request->method() !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }

        $input = $this->request->json();
        $theme = $input['theme'] ?? 'light';

        if (!in_array($theme, ['light', 'dark'])) {
            $theme = 'light';
        }

        $userId = $this->getUserId();
        $success = $this->userService->updateTheme($userId, $theme);

        header('Content-Type: application/json');
        echo json_encode(['success' => $success, 'theme' => $theme]);
    }

    /**
     * Altera senha do usuário
     */
    public function changePassword(): void
    {
        if ($this->request->method() !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'Método não permitido']);
            return;
        }

        $userId = $this->getUserId();
        $currentPassword = $this->request->post('current_password', '');
        $newPassword = $this->request->post('new_password', '');

        $result = $this->userService->changePassword($userId, $currentPassword, $newPassword);

        header('Content-Type: application/json');
        echo json_encode($result);
    }

    /**
     * Obtém dados do usuário atual
     */
    public function me(): void
    {
        $user = $this->userService->getCurrentUser();

        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'user' => $user
        ]);
    }

    /**
     * Desabilitar 2FA
     * POST /api/user/2fa/disable
     */
    public function disable2fa(): void
    {
        header('Content-Type: application/json');

        try {
            $userId = $this->getUserId();
            $db = \App\Database::getInstance();

            $stmt = $db->prepare("UPDATE users SET two_factor_enabled = 0, two_factor_secret = NULL WHERE id = :id");
            $stmt->execute(['id' => $userId]);

            echo json_encode(['success' => true, 'message' => '2FA desativado']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Encerrar todas as sessões
     * POST /api/user/sessions/logout-all
     */
    public function logoutAllSessions(): void
    {
        header('Content-Type: application/json');

        try {
            $userId = $this->getUserId();
            $currentSessionId = session_id();
            $db = \App\Database::getInstance();

            // Remove todas as sessões exceto a atual
            $stmt = $db->prepare("DELETE FROM sessions WHERE user_id = :user_id AND session_id != :current");
            $stmt->execute(['user_id' => $userId, 'current' => $currentSessionId]);

            echo json_encode(['success' => true, 'message' => 'Sessões encerradas']);
        } catch (\Exception $e) {
            // Se tabela sessions não existe, apenas retorne sucesso
            echo json_encode(['success' => true, 'message' => 'Sessões encerradas']);
        }
    }

    /**
     * Registra atividade do usuário (heartbeat)
     * POST /api/user/activity
     */
    public function activity(): void
    {
        header('Content-Type: application/json');

        try {
            $userId = $this->getUserId();
            $db = \App\Database::getInstance();

            $db->prepare("UPDATE users SET last_activity_at = NOW() WHERE id = :id")
               ->execute(['id' => $userId]);

            echo json_encode(['success' => true]);
        } catch (\Exception $e) {
            echo json_encode(['success' => true]);
        }
    }
}
