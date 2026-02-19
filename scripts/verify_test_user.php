<?php
require 'vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "🔧 Verificando o e-mail do usuário de teste...\n";

$userService = new App\Services\UserService();
$db = App\Database::getInstance();

$email = 'test@example.com';

// Obter o token de verificação do usuário
$stmt = $db->prepare("SELECT verification_token, email_verified_at FROM users WHERE email = :email");
$stmt->execute(['email' => $email]);
$user = $stmt->fetch();

if (!$user) {
    echo "❌ Usuário de teste não encontrado.\n";
    exit(1);
}

if ($user['email_verified_at'] !== null) {
    echo "✅ E-mail do usuário já está verificado.\n";
    exit(0);
}

if (empty($user['verification_token'])) {
    echo "❌ Usuário não possui um token de verificação. Talvez já tenha sido usado ou houve um erro no registro.\n";
    exit(1);
}

// Chamar o método de verificação
$success = $userService->verifyEmail($user['verification_token']);

if ($success) {
    echo "✅ E-mail do usuário '{$email}' verificado com sucesso!\n";
} else {
    echo "❌ Falha ao verificar o e-mail do usuário.\n";
}
