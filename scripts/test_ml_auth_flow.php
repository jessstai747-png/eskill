<?php
require 'vendor/autoload.php';

// Inicia a sessão para que possamos simular o usuário logado
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "🔧 Simulando o fluxo de autorização do Mercado Livre...\n";

// 1. Simular usuário logado
$userId = 2; // ID do nosso 'Test User'
$_SESSION['user_id'] = $userId;
echo "Passo 1: Usuário ID {$userId} está logado (simulado).\n";

// 2. Obter a URL de autorização
$authService = new App\Services\MercadoLivreAuthService();
$authUrl = $authService->getAuthUrl($userId);
echo "Passo 2: URL de autorização gerada.\n";
echo "URL: {$authUrl}\n";

// 3. Simular o callback do Mercado Livre
echo "Passo 3: Simulando o redirecionamento para o ML e o callback...\n";

// Extrair 'state' da URL para usar no callback
parse_str(parse_url($authUrl, PHP_URL_QUERY), $queryParams);
$state = $queryParams['state'];
$fakeCode = 'TG-FAKE-CODE-FROM-ML-' . time(); // Código de autorização falso

echo "State extraído: {$state}\n";
echo "Código de autorização simulado: {$fakeCode}\n";

// 4. Chamar o método que troca o código por tokens
// Em um cenário real, o Guzzle faria uma requisição POST.
// Vamos precisar mockar a resposta do Guzzle para evitar a chamada real.
// Por enquanto, vamos apenas verificar se o fluxo até aqui está correto.

echo "\nPróximo passo seria chamar `exchangeCodeForTokens`.\n";
echo "Isso requer mockar a resposta da API do ML para simular a obtenção dos tokens.\n";

// Recuperar o code_verifier da sessão para verificar se foi salvo
$codeVerifier = $_SESSION['pkce_code_verifier'] ?? null;
if ($codeVerifier) {
    echo "✅ PKCE Code Verifier foi salvo na sessão com sucesso.\n";
} else {
    echo "❌ Falha ao salvar o PKCE Code Verifier na sessão.\n";
}

echo "\n✅ Simulação do início do fluxo de autorização concluída.\n";
