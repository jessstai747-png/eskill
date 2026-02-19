<?php
// scripts/test_ml_full_auth.php

require 'vendor/autoload.php';

use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "🔧 Testando o fluxo de autorização COMPLETO do ML (com mock)...\n";

// --- Início da Simulação ---

// 1. Simular usuário logado e obter URL de autorização
$userId = 2;
$_SESSION['user_id'] = $userId;
$authService = new App\Services\MercadoLivreAuthService();
$authUrl = $authService->getAuthUrl($userId);
parse_str(parse_url($authUrl, PHP_URL_QUERY), $queryParams);
$state = $queryParams['state'];
$fakeCode = 'TG-REAL-ISTIC-CODE-' . uniqid();

echo "Passo 1: Usuário logado e URL de autorização gerada.\n";

// 2. Preparar o Mock do Guzzle para a troca de token
$mock = new MockHandler([
    new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'access_token' => 'APP_USR-MOCK-ACCESS-TOKEN',
        'token_type' => 'bearer',
        'expires_in' => 21600,
        'scope' => 'offline_access read write',
        'user_id' => 123456789, // ML User ID
        'refresh_token' => 'TG-MOCK-REFRESH-TOKEN',
    ])),
    new Response(200, ['Content-Type' => 'application/json'], json_encode([
        'id' => 123456789,
        'nickname' => 'TESTE_MOCK_USER',
        'first_name' => 'Teste',
        'last_name' => 'Mock',
        'email' => 'test_mock@test.com',
    ])),
]);
$handlerStack = HandlerStack::create($mock);
$mockClient = new Client(['handler' => $handlerStack]);

// 3. Injetar o cliente mockado no serviço de autenticação
// (Usando Reflection para acessar a propriedade privada e injetar o mock)
$reflection = new ReflectionClass($authService);
$property = $reflection->getProperty('httpClient');
$property->setAccessible(true);
$property->setValue($authService, $mockClient);

echo "Passo 2: Mock da API do Mercado Livre configurado.\n";

// 4. Executar a troca de código por token
echo "Passo 3: Executando a troca do código de autorização por tokens...\n";
try {
    $result = $authService->exchangeCodeForTokens($fakeCode, $state);

    if ($result['success']) {
        echo "✅ Sucesso! A troca de código por token funcionou.\n";
        echo "Nickname do usuário ML: " . ($result['user_info']['nickname'] ?? 'N/A') . "\n";

        // 5. Verificar se a conta foi salva no banco de dados
        $db = App\Database::getInstance();
        $stmt = $db->prepare("SELECT * FROM ml_accounts WHERE ml_user_id = :ml_user_id");
        $stmt->execute(['ml_user_id' => 123456789]);
        $account = $stmt->fetch();

        if ($account) {
            echo "✅ Conta do Mercado Livre foi salva no banco de dados com sucesso!\n";
            echo "ID da Conta no sistema: " . $account['id'] . "\n";
        } else {
            echo "❌ ERRO: A conta do Mercado Livre não foi encontrada no banco de dados após a autenticação.\n";
        }
    } else {
        echo "❌ Falha na troca de código por token.\n";
    }
} catch (Exception $e) {
    echo "❌ ERRO CRÍTICO durante a troca de tokens: " . $e->getMessage() . "\n";
}

echo "\n🔧 Teste de fluxo de autorização concluído.\n";
