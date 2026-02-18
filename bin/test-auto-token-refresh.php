#!/usr/bin/env php
<?php

/**
 * Teste do Sistema de Auto Token Refresh
 * Simula execução do worker sem fazer alterações reais
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "╔═══════════════════════════════════════════════════════════╗\n";
echo "║     TESTE - Auto Token Refresh & Data Sync               ║\n";
echo "╚═══════════════════════════════════════════════════════════╝\n\n";

$tests = [
    'UnifiedTokenRefreshService' => false,
    'MercadoLivreClient' => false,
    'Database Connection' => false,
    'Worker File' => false,
    'Cron Config' => false,
    'Log Directory' => false,
];

// Test 1: UnifiedTokenRefreshService
echo "✅ [1/6] Testando UnifiedTokenRefreshService...\n";
try {
    $service = new \App\Services\UnifiedTokenRefreshService();
    $reflection = new ReflectionClass($service);

    $methods = ['refreshExpiring', 'forceRefreshAll', 'refreshAccount', 'getHealthMetrics'];
    foreach ($methods as $method) {
        if ($reflection->hasMethod($method)) {
            echo "   ✓ Método {$method}() existe\n";
        }
    }

    $tests['UnifiedTokenRefreshService'] = true;
} catch (Exception $e) {
    echo "   ✗ Erro: " . $e->getMessage() . "\n";
}

// Test 2: MercadoLivreClient
echo "\n✅ [2/6] Testando MercadoLivreClient...\n";
try {
    $client = new \App\Services\MercadoLivreClient(null);
    echo "   ✓ Client instanciado (modo público)\n";
    $tests['MercadoLivreClient'] = true;
} catch (Exception $e) {
    echo "   ✗ Erro: " . $e->getMessage() . "\n";
}

// Test 3: Database
echo "\n✅ [3/6] Testando conexão com banco...\n";
try {
    $db = \App\Database::getInstance();

    // Testar se tabelas necessárias existem
    $tables = ['ml_accounts', 'items', 'orders', 'questions'];
    $existingTables = [];

    foreach ($tables as $table) {
        $stmt = $db->prepare("SHOW TABLES LIKE :table");
        $stmt->execute(['table' => $table]);
        if ($stmt->rowCount() > 0) {
            $existingTables[] = $table;
            echo "   ✓ Tabela '{$table}' existe\n";
        }
    }

    if (count($existingTables) >= 2) {
        $tests['Database Connection'] = true;
    }
} catch (Exception $e) {
    echo "   ✗ Erro: " . $e->getMessage() . "\n";
}

// Test 4: Worker File
echo "\n✅ [4/6] Verificando worker file...\n";
$workerFile = __DIR__ . '/../bin/auto-token-refresh-worker.php';
if (file_exists($workerFile)) {
    echo "   ✓ Worker existe: {$workerFile}\n";

    if (is_executable($workerFile)) {
        echo "   ✓ Worker é executável\n";
    } else {
        echo "   ⚠️  Worker não é executável (chmod +x recomendado)\n";
    }

    // Test syntax
    exec("php -l " . escapeshellarg($workerFile) . " 2>&1", $output, $return);
    if ($return === 0) {
        echo "   ✓ Sintaxe PHP válida\n";
        $tests['Worker File'] = true;
    } else {
        echo "   ✗ Erro de sintaxe:\n";
        echo implode("\n", $output) . "\n";
    }
} else {
    echo "   ✗ Worker não encontrado\n";
}

// Test 5: Cron Config
echo "\n✅ [5/6] Verificando configuração de cron...\n";
$cronFile = __DIR__ . '/../crontab.auto-token-refresh.example';
if (file_exists($cronFile)) {
    echo "   ✓ Arquivo de exemplo existe\n";

    $content = file_get_contents($cronFile);
    if (strpos($content, 'auto-token-refresh-worker.php') !== false) {
        echo "   ✓ Configuração do worker encontrada\n";
        $tests['Cron Config'] = true;
    }
} else {
    echo "   ✗ Arquivo de configuração não encontrado\n";
}

// Test 6: Log Directory
echo "\n✅ [6/6] Verificando diretório de logs...\n";
$logDir = __DIR__ . '/../storage/logs';
if (is_dir($logDir)) {
    echo "   ✓ Diretório existe: {$logDir}\n";

    if (is_writable($logDir)) {
        echo "   ✓ Diretório é writable\n";
        $tests['Log Directory'] = true;
    } else {
        echo "   ✗ Diretório não é writable\n";
    }
} else {
    echo "   ⚠️  Diretório não existe (será criado automaticamente)\n";
    @mkdir($logDir, 0755, true);
    if (is_dir($logDir)) {
        echo "   ✓ Diretório criado com sucesso\n";
        $tests['Log Directory'] = true;
    }
}

// Summary
echo "\n" . str_repeat('═', 60) . "\n";
echo "📊 RESULTADO DOS TESTES\n";
echo str_repeat('═', 60) . "\n";

$passed = 0;
$total = count($tests);

foreach ($tests as $test => $result) {
    $status = $result ? '✅' : '❌';
    echo "{$status} {$test}\n";
    if ($result) $passed++;
}

echo "\n";
echo "Total: {$passed}/{$total} testes passaram\n";

if ($passed === $total) {
    echo "\n🎉 TODOS OS TESTES PASSARAM!\n";
    echo "\n📋 Próximos passos:\n";
    echo "   1. Instalar crontab:\n";
    echo "      cp crontab.auto-token-refresh.example /tmp/crontab-temp\n";
    echo "      crontab /tmp/crontab-temp\n";
    echo "\n   2. Testar worker manualmente:\n";
    echo "      php bin/auto-token-refresh-worker.php\n";
    echo "\n   3. Verificar logs:\n";
    echo "      tail -f storage/logs/auto-token-refresh.log\n";
    echo "\n   4. Verificar crontab instalado:\n";
    echo "      crontab -l\n";
    exit(0);
} else {
    echo "\n⚠️  ALGUNS TESTES FALHARAM\n";
    echo "Por favor, corrija os problemas acima antes de continuar.\n";
    exit(1);
}
