<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\AuthService;
use App\Middleware\RateLimitMiddleware;

class AuthApiController extends BaseController
{
    private AuthService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new AuthService();
    }

    /**
     * POST /api/auth/login
     */
    public function login(): void
    {
        header('Content-Type: application/json');

        // Apply strict rate limit for login attempts
        $rl = new RateLimitMiddleware(10, 60);
        $rl->handle();

        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $email = $data['email'] ?? '';
        $password = $data['password'] ?? '';
        $device = $data['device_name'] ?? null;

        if (empty($email) || empty($password)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'E-mail e senha são obrigatórios']);
            return;
        }

        try {
            $result = $this->service->login($email, $password, $device);

            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'access_token' => $result['access_token'],
                    'access_expires_in' => $result['access_expires_in'],
                    'refresh_token' => $result['refresh_token'],
                    'user' => $result['user']
                ]);
                return;
            }

            // Handle 2FA request
            if (!empty($result['require_2fa'])) {
                http_response_code(403);
                echo json_encode(['success' => false, 'require_2fa' => true]);
                return;
            }

            http_response_code(401);
            echo json_encode(['success' => false, 'error' => $result['message'] ?? 'Unauthorized']);
        } catch (\Throwable $e) {
            // Do not log sensitive data
            log_error('Erro no login via API', [
                'error' => $e->getMessage(),
            ]);
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internal server error']);
        }
    }

    /**
     * POST /api/auth/refresh
     */
    public function refresh(): void
    {
        header('Content-Type: application/json');

        // Rate limit refresh as well
        $rl = new RateLimitMiddleware(20, 60);
        $rl->handle();

        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $refreshToken = $data['refresh_token'] ?? '';

        if (empty($refreshToken)) {
            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'refresh_token is required']);
            return;
        }

        try {
            $result = $this->service->refresh($refreshToken);

            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'access_token' => $result['access_token'],
                    'access_expires_in' => $result['access_expires_in'],
                    'refresh_token' => $result['refresh_token']
                ]);
                return;
            }

            http_response_code(401);
            echo json_encode(['success' => false, 'error' => $result['message'] ?? 'Invalid token']);
        } catch (\Throwable $e) {
            log_error('Erro ao renovar token via API', [
                'error' => $e->getMessage(),
            ]);
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internal server error']);
        }
    }

    /**
     * POST /api/auth/logout
     */
    public function logout(): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true) ?: [];
        $refreshToken = $data['refresh_token'] ?? null;

        // If no refresh token provided, try to revoke all tokens for the user based on Bearer
        if (empty($refreshToken)) {
            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
            if (preg_match('/Bearer\s+(\S+)/', $authHeader, $m)) {
                $access = $m[1];
                $jwt = new \App\Services\JwtService();
                $userId = $jwt->getUserIdFromToken($access);
                if ($userId) {
                    $r = $this->service->logout(null, $userId);
                    echo json_encode(['success' => true, 'revoked' => $r['revoked'] ?? 0]);
                    return;
                }
            }

            http_response_code(422);
            echo json_encode(['success' => false, 'error' => 'refresh_token or Authorization Bearer required']);
            return;
        }

        try {
            $res = $this->service->logout($refreshToken, null);
            if ($res['success']) {
                echo json_encode(['success' => true]);
            } else {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => $res['message'] ?? 'Could not revoke token']);
            }
        } catch (\Throwable $e) {
            log_error('Erro no logout via API', [
                'error' => $e->getMessage(),
            ]);
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Internal server error']);
        }
    }

    /**
     * Verifica status de autenticação
     * GET /api/auth/status
     */
    public function status(): void
    {
        header('Content-Type: application/json');

        $loggedIn = !empty($_SESSION['user_id']);

        echo json_encode([
            'success' => true,
            'authenticated' => $loggedIn,
            'user_id' => $_SESSION['user_id'] ?? null,
            'active_account_id' => $_SESSION['active_account_id'] ?? null,
        ]);
    }
}
