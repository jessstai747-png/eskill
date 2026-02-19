<?php

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "=== Verificando Configuração OAuth ===\n";

$appId = $_ENV['ML_APP_ID'] ?? '';
$secret = $_ENV['ML_CLIENT_SECRET'] ?? '';
$redirect = $_ENV['ML_REDIRECT_URI'] ?? '';

echo "App ID: " . ($appId ? "✅ Configurado (" . substr($appId, 0, 4) . "...)" : "❌ Faltando") . "\n";
echo "Secret: " . ($secret ? "✅ Configurado" : "❌ Faltando") . "\n";
echo "Redirect URI: " . ($redirect ? "✅ Configurado ($redirect)" : "❌ Faltando") . "\n";

if ($appId && $secret && $redirect) {
    echo "\nTudo pronto para gerar o Link de Autorização!\n";
    // We can simulate the URL generation here if the service exists
    try {
        $authService = new \App\Services\MercadoLivreAuthService();
        // Mock user ID 1 for generation
        $url = $authService->getAuthUrl(1);
        echo "\n🔗 Seu Link de Login:\n$url\n";
    } catch (\Exception $e) {
        echo "Erro ao gerar link: " . $e->getMessage() . "\n";
    }
} else {
    echo "\n⚠️  Ação Necessária: Preencha as variáveis acima no arquivo .env\n";
}
