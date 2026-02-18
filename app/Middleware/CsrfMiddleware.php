<?php

declare(strict_types=1);

namespace App\Middleware;

use App\Services\SecurityService;

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
        foreach (getallheaders() as $key => $value) {
            $headers[strtolower($key)] = $value;
        }
        
        // Obter token do header (várias variações) ou POST
        $token = $_SERVER['HTTP_X_CSRF_TOKEN'] 
            ?? $_SERVER['HTTP_X_CSRF_Token']
            ?? $headers['x-csrf-token'] ?? null
            ?? $_POST['_token'] 
            ?? null;
        
        if (!$token || !$this->security->validateCsrfToken($token)) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'error' => 'Token CSRF inválido ou expirado',
                'code' => 'CSRF_TOKEN_INVALID'
            ]);
            exit;
        }
    }
}

