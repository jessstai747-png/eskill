<?php
// Script de login automático temporário para fins de teste.
// ESTE ARQUIVO DEVE SER REMOVIDO APÓS O USO.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

use App\Services\UserService;
use App\Helpers\SessionHelper;

$userService = new UserService();

// Credenciais do usuário de teste
$email = 'test@example.com';
$password = 'password';

$result = $userService->login($email, $password);

if ($result['success'] ?? false) {
    $user = $result['user'];

    // Preservar token CSRF antes de regenerar sessão
    $csrfToken = $_SESSION['csrf_token'] ?? null;
    $csrfTokenTime = $_SESSION['csrf_token_time'] ?? null;
    
    // Regenera o ID da sessão para evitar fixação de sessão
    session_regenerate_id(true);
    
    // Restaurar token CSRF após regeneração
    if ($csrfToken) {
        $_SESSION['csrf_token'] = $csrfToken;
        $_SESSION['csrf_token_time'] = $csrfTokenTime;
    }

    // Define as variáveis de sessão
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];

    // Redireciona para o dashboard
    header('Location: /dashboard');
    exit;
}

header('Content-Type: text/plain');
http_response_code(500);
echo "Falha no login automático. Verifique as credenciais e se o usuário de teste existe.";
exit;
