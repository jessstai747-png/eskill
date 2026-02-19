<?php
/**
 * Script para atualizar credenciais do Mercado Livre no .env
 */

$envFile = __DIR__ . '/../.env';

if (!file_exists($envFile)) {
    die("❌ Arquivo .env não encontrado!\n");
}

// Credenciais fornecidas
$appId = '757032559637450';
$clientSecret = 'Qq7AwTHymrP9m8L2CWAj0m2m1frhlL0m';

echo "🔧 Atualizando credenciais do Mercado Livre...\n\n";

// Ler arquivo .env
$lines = file($envFile, FILE_IGNORE_NEW_LINES);

$updated = false;
$newLines = [];

foreach ($lines as $line) {
    $trimmed = trim($line);
    
    // Atualizar ML_APP_ID
    if (preg_match('/^ML_APP_ID\s*=/i', $trimmed)) {
        $newLines[] = "ML_APP_ID={$appId}";
        $updated = true;
        echo "✅ ML_APP_ID atualizado: {$appId}\n";
        continue;
    }
    
    // Atualizar ML_CLIENT_SECRET
    if (preg_match('/^ML_CLIENT_SECRET\s*=/i', $trimmed)) {
        $newLines[] = "ML_CLIENT_SECRET={$clientSecret}";
        $updated = true;
        echo "✅ ML_CLIENT_SECRET atualizado\n";
        continue;
    }
    
    // Manter linha original
    $newLines[] = $line;
}

// Se não encontrou as variáveis, adicionar no final
if (!$updated) {
    echo "⚠️  Variáveis não encontradas. Adicionando ao final do arquivo...\n";
    $newLines[] = "";
    $newLines[] = "# Mercado Livre Credentials";
    $newLines[] = "ML_APP_ID={$appId}";
    $newLines[] = "ML_CLIENT_SECRET={$clientSecret}";
    $newLines[] = "ML_REDIRECT_URI=http://localhost/eskill/public/auth/callback";
    echo "✅ Credenciais adicionadas\n";
}

// Escrever arquivo atualizado
file_put_contents($envFile, implode("\n", $newLines));

echo "\n✅ Arquivo .env atualizado com sucesso!\n";
echo "\n📋 Credenciais configuradas:\n";
echo "   ML_APP_ID: {$appId}\n";
echo "   ML_CLIENT_SECRET: " . substr($clientSecret, 0, 10) . "...\n";
echo "\n⚠️  IMPORTANTE: Verifique se ML_REDIRECT_URI está correto no .env\n";
echo "   URL esperada: http://localhost/eskill/public/auth/callback\n";
