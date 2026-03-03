<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\ApiTokenService;

/**
 * ApiAuthMiddleware
 *
 * Middleware para autenticação via API Token
 * Valida o token no header Authorization: Bearer {token}
 */
class ApiAuthMiddleware
{
    private ?ApiTokenService $tokenService = null;

    public function __construct()
    {
        try {
            $this->tokenService = new ApiTokenService();
        } catch (\Exception $e) {
            $this->tokenService = null;
        }
    }

    /**
     * Executar middleware
     *
     * @param callable $next Próxima função na cadeia
     * @param array $requiredScopes Escopos necessários
     */
    public function handle(callable $next, array $requiredScopes = []): void
    {
        $token = $this->extractToken();

        if (!$token) {
            $this->unauthorizedResponse('Token não fornecido');
            return;
        }

        $tokenData = $this->tokenService !== null ? $this->tokenService->validateToken($token) : null;

        if (!$tokenData) {
            $this->unauthorizedResponse('Token inválido ou expirado');
            return;
        }

        // Verificar escopos se necessário
        if (!empty($requiredScopes)) {
            $hasPermission = false;
            foreach ($requiredScopes as $scope) {
                if ($this->tokenService !== null && $this->tokenService->hasScope($tokenData, $scope)) {
                    $hasPermission = true;
                    break;
                }
            }

            if (!$hasPermission) {
                $this->forbiddenResponse('Permissão insuficiente');
                return;
            }
        }

        // Adicionar dados do token ao contexto seguro do servidor
        // Usar $_SERVER em vez de $_REQUEST para evitar que input do usuário
        // pré-popule esses valores via GET/POST/COOKIE
        $_SERVER['API_TOKEN_DATA'] = $tokenData;
        $_SERVER['API_USER_ID'] = $tokenData['user_id'];

        // Continuar para próximo middleware/controller
        $next();
    }

    /**
     * Extrair token do header Authorization
     */
    private function extractToken(): ?string
    {
        // Verificar header Authorization
        $headers = getallheaders();
        $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;

        if ($authHeader && preg_match('/Bearer\s+(.+)/i', $authHeader, $matches)) {
            return $matches[1];
        }

        // Security: removed query parameter fallback ($_GET['api_token'])
        // Tokens in URLs leak via server logs, browser history, and referrers
        return null;
    }

    /**
     * Resposta 401 Unauthorized
     */
    private function unauthorizedResponse(string $message): void
    {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Unauthorized',
            'message' => $message
        ]);
        exit;
    }

    /**
     * Resposta 403 Forbidden
     */
    private function forbiddenResponse(string $message): void
    {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode([
            'error' => 'Forbidden',
            'message' => $message
        ]);
        exit;
    }

    /**
     * Obter dados do usuário autenticado via API
     */
    public static function getApiUser(): ?array
    {
        return $_SERVER['API_TOKEN_DATA'] ?? null;
    }

    /**
     * Obter ID do usuário autenticado via API
     */
    public static function getApiUserId(): ?int
    {
        return $_SERVER['API_USER_ID'] ?? null;
    }
}
