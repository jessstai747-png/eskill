<?php
/**
 * Script para verificar configuração do Mercado Livre
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "🔍 Verificando configuração do Mercado Livre...\n\n";

// Carregar .env manualmente primeiro
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line) || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') === false) continue;
        list($key, $value) = explode('=', $line, 2);
        $_ENV[trim($key)] = trim($value);
    }
}

// Carregar configuração
$config = require __DIR__ . '/../config/app.php';
$mlConfig = $config['mercadolivre'] ?? [];

echo "📋 Configuração Atual:\n";
echo "   App ID: " . ($mlConfig['app_id'] ?: '❌ Não configurado') . "\n";
echo "   Client Secret: " . ($mlConfig['client_secret'] ? substr($mlConfig['client_secret'], 0, 10) . '...' : '❌ Não configurado') . "\n";
echo "   Redirect URI: " . ($mlConfig['redirect_uri'] ?: '❌ Não configurado') . "\n";
echo "   Auth URL: " . ($mlConfig['auth_url'] ?? 'N/A') . "\n";
echo "   Token URL: " . ($mlConfig['token_url'] ?? 'N/A') . "\n";
echo "   API URL: " . ($mlConfig['api_url'] ?? 'N/A') . "\n";
echo "   Site ID: " . ($mlConfig['site_id'] ?? 'N/A') . "\n";

echo "\n";

// Verificar se está completo
$isComplete = !empty($mlConfig['app_id']) && !empty($mlConfig['client_secret']) && !empty($mlConfig['redirect_uri']);

if ($isComplete) {
    echo "✅ Configuração completa!\n";
    echo "\n🚀 Próximos passos:\n";
    echo "   1. Acesse: http://localhost/eskill/public/auth/authorize\n";
    echo "   2. Faça login com sua conta do Mercado Livre\n";
    echo "   3. Autorize o acesso da aplicação\n";
    echo "   4. Você será redirecionado de volta ao sistema\n";
} else {
    echo "⚠️  Configuração incompleta!\n";
    echo "\n📝 Verifique o arquivo .env e configure:\n";
    echo "   - ML_APP_ID\n";
    echo "   - ML_CLIENT_SECRET\n";
    echo "   - ML_REDIRECT_URI\n";
}

echo "\n";
