<?php

namespace App\Controllers;

use App\Services\ApiTokenService;

/**
 * ApiTokenController
 * 
 * Controller para gerenciar tokens de API
 */
class ApiTokenController
{
    private ApiTokenService $tokenService;
    private int $userId;

    public function __construct()
    {
        $this->tokenService = new ApiTokenService();

        // Requer autenticação de usuário
        session_start();
        if (!isset($_SESSION['user_id'])) {
            http_response_code(401);
            header('Location: /login');
            exit;
        }

        $this->userId = $_SESSION['user_id'];
    }

    /**
     * Listar tokens do usuário
     * GET /api/tokens
     */
    public function index(): void
    {
        header('Content-Type: application/json');

        $tokens = $this->tokenService->listTokens($this->userId);

        echo json_encode([
            'success' => true,
            'tokens' => $tokens
        ]);
    }

    /**
     * Criar novo token
     * POST /api/tokens
     */
    public function create(): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);

        $name = $data['name'] ?? '';
        $scopes = $data['scopes'] ?? [];
        $expiresInDays = $data['expires_in_days'] ?? null;

        if (empty($name)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Nome do token é obrigatório'
            ]);
            return;
        }

        try {
            $token = $this->tokenService->createToken($this->userId, $name, $scopes, $expiresInDays);

            echo json_encode([
                'success' => true,
                'token' => $token,
                'message' => 'Token criado com sucesso! Guarde-o em local seguro, não será exibido novamente.'
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao criar token: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Revogar token
     * DELETE /api/tokens/{id}
     */
    public function revoke(int $tokenId): void
    {
        header('Content-Type: application/json');

        try {
            $result = $this->tokenService->revokeToken($tokenId, $this->userId);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Token revogado com sucesso'
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Token não encontrado'
                ]);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao revogar token: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Atualizar nome do token
     * PUT /api/tokens/{id}
     */
    public function update(int $tokenId): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);
        $name = $data['name'] ?? '';

        if (empty($name)) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'message' => 'Nome é obrigatório'
            ]);
            return;
        }

        try {
            $result = $this->tokenService->updateTokenName($tokenId, $this->userId, $name);

            if ($result) {
                echo json_encode([
                    'success' => true,
                    'message' => 'Token atualizado com sucesso'
                ]);
            } else {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'message' => 'Token não encontrado'
                ]);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'message' => 'Erro ao atualizar token: ' . $e->getMessage()
            ]);
        }
    }

    /**
     * Ver estatísticas do token
     * GET /api/tokens/{id}/stats
     */
    public function stats(int $tokenId): void
    {
        header('Content-Type: application/json');

        $stats = $this->tokenService->getTokenStats($tokenId, $this->userId);

        if ($stats) {
            echo json_encode([
                'success' => true,
                'stats' => $stats
            ]);
        } else {
            http_response_code(404);
            echo json_encode([
                'success' => false,
                'message' => 'Token não encontrado'
            ]);
        }
    }

    /**
     * Listar escopos disponíveis
     * GET /api/tokens/scopes
     */
    public function scopes(): void
    {
        header('Content-Type: application/json');

        echo json_encode([
            'success' => true,
            'scopes' => ApiTokenService::getAvailableScopes()
        ]);
    }

    /**
     * Página de gerenciamento de tokens
     * GET /dashboard/api-tokens
     */
    public function page(): void
    {
        require_once __DIR__ . '/../Views/dashboard/api-tokens.php';
    }
}
