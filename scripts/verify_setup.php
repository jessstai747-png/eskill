<?php
/**
 * Script de Verificação do Setup
 * Verifica se tudo está configurado corretamente
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "🔍 Verificando configuração do sistema...\n\n";

$errors = [];
$warnings = [];
$success = [];

// 1. Verificar .env
echo "1. Verificando arquivo .env...\n";
if (file_exists(__DIR__ . '/../.env')) {
    $success[] = "✅ Arquivo .env existe";
} else {
    $errors[] = "❌ Arquivo .env não encontrado";
}

// 2. Verificar banco de dados
echo "2. Verificando conexão com banco de dados...\n";
try {
    $db = App\Database::getInstance();
    $success[] = "✅ Conexão com banco de dados OK";
    
    // Verificar tabelas principais
    $tables = ['users', 'ml_accounts', 'password_resets', 'activity_logs'];
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            $success[] = "✅ Tabela '{$table}' existe";
        } else {
            $errors[] = "❌ Tabela '{$table}' não encontrada";
        }
    }
} catch (Exception $e) {
    $errors[] = "❌ Erro ao conectar ao banco: " . $e->getMessage();
}

// 3. Verificar diretórios
echo "3. Verificando diretórios...\n";
$dirs = [
    'storage/logs' => 'Logs',
    'storage/cache' => 'Cache',
];
foreach ($dirs as $dir => $name) {
    $path = __DIR__ . '/../' . $dir;
    if (is_dir($path) && is_writable($path)) {
        $success[] = "✅ Diretório {$name} existe e é gravável";
    } else {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
            $success[] = "✅ Diretório {$name} criado";
        } else {
            $warnings[] = "⚠️  Diretório {$name} não é gravável";
        }
    }
}

// 4. Verificar extensões PHP
echo "4. Verificando extensões PHP...\n";
$extensions = ['pdo', 'pdo_mysql', 'openssl', 'json', 'mbstring'];
foreach ($extensions as $ext) {
    if (extension_loaded($ext)) {
        $success[] = "✅ Extensão PHP '{$ext}' carregada";
    } else {
        $errors[] = "❌ Extensão PHP '{$ext}' não encontrada";
    }
}

// 5. Verificar configurações importantes
echo "5. Verificando configurações...\n";
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $env = parse_ini_file($envFile);
    
    if (empty($env['APP_KEY'])) {
        $warnings[] = "⚠️  APP_KEY não configurado (recomendado para produção)";
    } else {
        $success[] = "✅ APP_KEY configurado";
    }
    
    if (empty($env['ML_APP_ID']) || empty($env['ML_CLIENT_SECRET'])) {
        $warnings[] = "⚠️  Credenciais do Mercado Livre não configuradas";
    } else {
        $success[] = "✅ Credenciais do Mercado Livre configuradas";
    }
}

// Resumo
echo "\n" . str_repeat("=", 50) . "\n";
echo "RESUMO DA VERIFICAÇÃO\n";
echo str_repeat("=", 50) . "\n\n";

if (count($success) > 0) {
    echo "✅ SUCESSOS (" . count($success) . "):\n";
    foreach ($success as $msg) {
        echo "   {$msg}\n";
    }
    echo "\n";
}

if (count($warnings) > 0) {
    echo "⚠️  AVISOS (" . count($warnings) . "):\n";
    foreach ($warnings as $msg) {
        echo "   {$msg}\n";
    }
    echo "\n";
}

if (count($errors) > 0) {
    echo "❌ ERROS (" . count($errors) . "):\n";
    foreach ($errors as $msg) {
        echo "   {$msg}\n";
    }
    echo "\n";
    exit(1);
} else {
    echo "✅ Sistema configurado corretamente!\n";
    exit(0);
}
