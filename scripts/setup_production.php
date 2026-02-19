<?php
/**
 * Script de Configuração Inicial para Produção
 * Executar uma vez após deploy: php scripts/setup_production.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "🔧 Configurando sistema para produção...\n\n";

// 1. Verificar .env
echo "1. Verificando .env...\n";
$envFile = __DIR__ . '/../.env';
if (!file_exists($envFile)) {
    die("❌ Arquivo .env não encontrado! Copie .env.production.example para .env\n");
}

$env = parse_ini_file($envFile);

if (($env['APP_ENV'] ?? '') !== 'production') {
    echo "⚠️  AVISO: APP_ENV não está definido como 'production'\n";
}

if (($env['APP_DEBUG'] ?? 'true') === 'true') {
    echo "⚠️  AVISO: APP_DEBUG está como 'true' - DESABILITE em produção!\n";
}

if (empty($env['APP_KEY'])) {
    echo "⚠️  AVISO: APP_KEY não configurado - gere uma chave forte!\n";
    echo "   Execute: php -r \"echo bin2hex(random_bytes(32));\"\n";
}

// 2. Verificar permissões
echo "\n2. Verificando permissões...\n";
$dirs = [
    'storage/cache',
    'storage/logs',
];

foreach ($dirs as $dir) {
    $path = __DIR__ . '/../' . $dir;
    if (!is_writable($path)) {
        echo "⚠️  {$dir} não é gravável\n";
    } else {
        echo "✅ {$dir} OK\n";
    }
}

// 3. Verificar banco
echo "\n3. Verificando banco de dados...\n";
try {
    $db = App\Database::getInstance();
    $db->query("SELECT 1");
    echo "✅ Conexão com banco OK\n";
    
    // Verificar tabelas essenciais
    $tables = ['users', 'ml_accounts'];
    foreach ($tables as $table) {
        $stmt = $db->query("SHOW TABLES LIKE '{$table}'");
        if ($stmt->rowCount() > 0) {
            echo "✅ Tabela {$table} existe\n";
        } else {
            echo "⚠️  Tabela {$table} não encontrada - execute migrations\n";
        }
    }
} catch (Exception $e) {
    echo "❌ Erro ao conectar banco: " . $e->getMessage() . "\n";
}

// 4. Limpar cache
echo "\n4. Limpando cache...\n";
try {
    $cache = new App\Services\CacheService();
    $cache->clear();
    echo "✅ Cache limpo\n";
} catch (Exception $e) {
    echo "⚠️  Erro ao limpar cache: " . $e->getMessage() . "\n";
}

// 5. Verificar SSL
echo "\n5. Verificando configuração...\n";
if (strpos($env['APP_URL'] ?? '', 'https://') === false) {
    echo "⚠️  AVISO: APP_URL não usa HTTPS - configure SSL!\n";
} else {
    echo "✅ APP_URL usa HTTPS\n";
}

echo "\n✅ Configuração concluída!\n";
echo "\nPróximos passos:\n";
echo "1. Configure SSL/HTTPS\n";
echo "2. Configure backup automatizado\n";
echo "3. Configure monitoramento\n";
echo "4. Teste todas as funcionalidades\n";
