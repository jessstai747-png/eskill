<?php
require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

$userService = new App\Services\UserService();

// Check if user already exists
$db = App\Database::getInstance();
$stmt = $db->prepare("SELECT id FROM users WHERE email = :email");
$stmt->execute(['email' => 'test@example.com']);
if ($stmt->fetch()) {
    echo "Usuário test@example.com já existe.\n";
} else {
    $result = $userService->register('Test User', 'test@example.com', 'password');
    if ($result['success']) {
        echo "Usuário de teste criado com sucesso!\n";
        echo "Email: test@example.com\n";
        echo "Senha: password\n";
    } else {
        echo "Falha ao criar usuário de teste: " . $result['message'] . "\n";
    }
}
