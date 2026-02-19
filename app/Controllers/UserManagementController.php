<?php

namespace App\Controllers;

use App\Services\UserService;
use App\Database;
use PDO;

class UserManagementController
{
    private UserService $userService;
    private PDO $db;

    public function __construct()
    {
        $this->userService = new UserService();
        $this->db = Database::getInstance();
        $this->ensureAdmin();
    }

    private function ensureAdmin(): void
    {
        $user = $this->userService->getCurrentUser();
        if (($user['role'] ?? 'manager') !== 'admin') {
            header('Location: /dashboard?error=access_denied');
            exit;
        }
    }

    /**
     * Render User Management View
     * GET /dashboard/settings/users
     */
    public function index(): void
    {
        $pageTitle = 'Gerenciamento de Usuários';
        $activePage = 'settings';

        ob_start();
        require __DIR__ . '/../Views/settings/users.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layouts/modern/app.php';
    }

    /**
     * Get all users
     * GET /api/users
     */
    public function listUsers(): void
    {
        header('Content-Type: application/json');

        $stmt = $this->db->query("SELECT id, name, email, role, created_at FROM users ORDER BY name ASC");
        echo json_encode(['success' => true, 'users' => $stmt->fetchAll(PDO::FETCH_ASSOC)]);
    }

    /**
     * Invite/Register a new user
     * POST /api/users/invite
     */
    public function invite(): void
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        // Require an explicit password — never use insecure defaults
        if (empty($data['password']) || strlen($data['password']) < 8) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Senha obrigatória com no mínimo 8 caracteres.',
            ]);
            return;
        }

        $password = $data['password'];
        $result = $this->userService->register($data['name'], $data['email'], $password);

        if ($result['success']) {
            // Update role immediately
            $role = $data['role'] ?? 'support';
            $stmt = $this->db->prepare("UPDATE users SET role = :role, email_verified_at = NOW() WHERE id = :id");
            $stmt->execute(['role' => $role, 'id' => $result['user_id']]);
        }

        echo json_encode($result);
    }

    /**
     * Configure Role
     * POST /api/users/role
     */
    public function updateRole(): void
    {
        header('Content-Type: application/json');
        $data = json_decode(file_get_contents('php://input'), true);

        $id = (int) ($data['id'] ?? 0);
        $role = $data['role'] ?? '';

        // Validate role against whitelist
        $allowedRoles = ['admin', 'manager', 'support', 'user', 'viewer'];
        if (!in_array($role, $allowedRoles, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'Role inválida. Permitidas: ' . implode(', ', $allowedRoles)]);
            return;
        }

        if ($id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'message' => 'ID de usuário inválido']);
            return;
        }

        // Prevent removing last admin
        if ($role !== 'admin') {
            $stmt = $this->db->query("SELECT COUNT(*) FROM users WHERE role = 'admin'");
            if ($stmt->fetchColumn() <= 1) {
                // Check if target is the last admin
                $stmt = $this->db->prepare("SELECT role FROM users WHERE id = :id");
                $stmt->execute(['id' => $id]);
                if ($stmt->fetchColumn() === 'admin') {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Cannot change role of the last admin.']);
                    return;
                }
            }
        }

        $stmt = $this->db->prepare("UPDATE users SET role = :role WHERE id = :id");
        $success = $stmt->execute(['role' => $role, 'id' => $id]);

        echo json_encode(['success' => $success]);
    }
}
