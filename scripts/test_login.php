<?php
require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "🔧 Testando a funcionalidade de login...\n";

$userService = new App\Services\UserService();

$email = 'test@example.com';
$password = 'password';

$result = $userService->login($email, $password);

if ($result['success']) {
    echo "✅ Teste de login bem-sucedido!\n";
    echo "Usuário '{$result['user']['name']}' autenticado com sucesso.\n";
    // Simular o início da sessão para os próximos passos
    $_SESSION['user_id'] = $result['user']['id'];
    $_SESSION['user_name'] = $result['user']['name'];
    echo "Sessão simulada para o usuário ID: {$result['user']['id']}\n";
} else {
    echo "❌ Falha no teste de login.\n";
    echo "Motivo: " . ($result['message'] ?? 'Desconhecido') . "\n";
}
