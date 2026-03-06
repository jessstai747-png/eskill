<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\SecurityService;
use App\Helpers\Log;

class CsrfMiddleware
{
    private SecurityService $security;

    public function __construct()
    {
        $this->security = new SecurityService();
    }

    /**
     * Verifica token CSRF em requisições POST/PUT/DELETE
     */
    public function handle(): void
    {
        $method = $_SERVER['REQUEST_METHOD'] ?? 'GET';

        // Apenas verificar em métodos que modificam dados
        if (!in_array($method, ['POST', 'PUT', 'DELETE', 'PATCH'])) {
            return;
        }

        // Garantir que a sessão está iniciada
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        // Obter todos os headers (case-insensitive)
        $headers = [];
        if (function_exists('getallheaders')) {
            foreach (getallheaders() as $key => $value) {
                $headers[strtolower($key)] = $value;
            }
        }

        // Obter token do header (várias variações) ou POST
        $token = $_SERVER['HTTP_X_CSRF_TOKEN']
            ?? $_SERVER['HTTP_X_CSRF_Token']
            ?? $headers['x-csrf-token']
            ?? $_POST['_token']
            ?? null;

        if (!$token || !$this->security->validateCsrfToken($token)) {
            $this->logFailure($token);
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Token CSRF inválido ou expirado',
                'code' => 'CSRF_TOKEN_INVALID'
            ]);
            exit;
        }
    }

    /**
     * Log structured diagnostics on CSRF validation failure
     */
    private function logFailure(?string $token): void
    {
        $reason = 'unknown';
        if ($token === null || $token === '') {
            $reason = 'token_missing_from_request';
        } elseif (!isset($_SESSION['csrf_token'])) {
            $reason = 'token_missing_from_session';
        } elseif (!isset($_SESSION['csrf_token_time'])) {
            $reason = 'token_time_missing_from_session';
        } elseif ((time() - $_SESSION['csrf_token_time']) > 3600) {
            $reason = 'token_expired';
        } else {
            $reason = 'token_mismatch';
        }

        $context = [
            'reason' => $reason,
            'method' => $_SERVER['REQUEST_METHOD'] ?? 'unknown',
            'path' => $_SERVER['REQUEST_URI'] ?? 'unknown',
            'session_id' => session_id() ?: 'none',
            'session_has_token' => isset($_SESSION['csrf_token']),
        ];

        try {
            Log::warning('CSRF validation failed', $context);
        } catch (\Throwable $e) {
            error_log('[CSRF] Validation failed: ' . json_encode($context));
        }
    }
}
