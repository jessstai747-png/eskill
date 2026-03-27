#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Script de Verificação do Sistema SEO
 * 
 * Verifica se todos os componentes necessários estão configurados
 * e funcionando corretamente.
 */

require_once __DIR__ . '/../vendor/autoload.php';

echo "\n";
echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║  Sistema de Otimização SEO - Verificação de Instalação    ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

$checks = [];
$errors = [];
$warnings = [];

// ============================================================================
// 1. Verificar PHP e Extensões
// ============================================================================

echo "📋 Verificando PHP e Extensões...\n";

$phpVersion = PHP_VERSION;
$checks['php_version'] = version_compare($phpVersion, '8.0.0', '>=');
echo "  PHP Version: {$phpVersion} " . ($checks['php_version'] ? '✓' : '✗') . "\n";

if (!$checks['php_version']) {
    $errors[] = "PHP 8.0+ é obrigatório. Versão atual: {$phpVersion}";
}

$requiredExtensions = ['json', 'curl', 'pdo', 'mbstring'];
foreach ($requiredExtensions as $ext) {
    $checks["ext_{$ext}"] = extension_loaded($ext);
    echo "  Extensão {$ext}: " . ($checks["ext_{$ext}"] ? '✓' : '✗') . "\n";

    if (!$checks["ext_{$ext}"]) {
        $errors[] = "Extensão PHP '{$ext}' não está instalada";
    }
}

echo "\n";

// ============================================================================
// 2. Verificar Arquivo .env
// ============================================================================

echo "🔐 Verificando Configurações (.env)...\n";

$envFile = __DIR__ . '/../.env';
$checks['env_exists'] = file_exists($envFile);
echo "  Arquivo .env existe: " . ($checks['env_exists'] ? '✓' : '✗') . "\n";

if (!$checks['env_exists']) {
    $errors[] = "Arquivo .env não encontrado. Execute: cp .env.example .env";
} else {
    // Carregar .env
    if (class_exists('Dotenv\Dotenv')) {
        $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
        $dotenv->load();
    }

    // Verificar variáveis críticas
    $requiredVars = [
        'DB_HOST' => 'Host do banco de dados',
        'DB_NAME' => 'Nome do banco de dados',
        'DB_USER' => 'Usuário do banco',
        'DB_PASS' => 'Senha do banco',
        'ML_APP_ID' => 'App ID do Mercado Livre',
        'ML_CLIENT_SECRET' => 'Client Secret do ML',
        'AI_API_KEY' => 'Chave da API de IA',
        'APP_KEY' => 'Chave de criptografia'
    ];

    foreach ($requiredVars as $var => $desc) {
        $value = $_ENV[$var] ?? getenv($var) ?? '';
        $checks["env_{$var}"] = !empty($value) && $value !== 'your_' && $value !== 'change_me';

        if ($checks["env_{$var}"]) {
            echo "  {$var}: ✓\n";
        } else {
            echo "  {$var}: ✗\n";
            $errors[] = "{$desc} não configurado ({$var})";
        }
    }
}

echo "\n";

// ============================================================================
// 3. Verificar Conexão com Banco de Dados
// ============================================================================

echo "🗄️  Verificando Banco de Dados...\n";

try {
    $db = App\Database::getInstance();
    $checks['db_connection'] = true;
    echo "  Conexão com banco: ✓\n";

    // Verificar tabelas
    $requiredTables = [
        'seo_synonym_hierarchy',
        'seo_use_contexts',
        'seo_monitoring_schedule',
        'seo_optimizations'
    ];

    foreach ($requiredTables as $table) {
        $stmt = $db->prepare("SHOW TABLES LIKE :table");
        $stmt->execute(['table' => $table]);
        $exists = $stmt->rowCount() > 0;
        $checks["table_{$table}"] = $exists;
        echo "  Tabela {$table}: " . ($exists ? '✓' : '✗') . "\n";

        if (!$exists) {
            $warnings[] = "Tabela '{$table}' não existe. Execute: ./setup-seo-database.sh";
        }
    }
} catch (Exception $e) {
    $checks['db_connection'] = false;
    echo "  Conexão com banco: ✗\n";
    $errors[] = "Erro ao conectar no banco: " . $e->getMessage();
}

echo "\n";

// ============================================================================
// 4. Verificar Diretórios e Permissões
// ============================================================================

echo "📁 Verificando Diretórios...\n";

$requiredDirs = [
    'storage/logs' => true,
    'storage/cache' => true,
    'storage/sessions' => true,
    'logs' => true
];

foreach ($requiredDirs as $dir => $writable) {
    $path = __DIR__ . '/../' . $dir;
    $exists = is_dir($path);
    $checks["dir_{$dir}"] = $exists;

    if ($exists && $writable) {
        $isWritable = is_writable($path);
        $checks["writable_{$dir}"] = $isWritable;
        echo "  {$dir}: " . ($isWritable ? '✓ (gravável)' : '⚠ (não gravável)') . "\n";

        if (!$isWritable) {
            $warnings[] = "Diretório '{$dir}' não é gravável. Execute: chmod -R 775 {$dir}";
        }
    } else {
        echo "  {$dir}: " . ($exists ? '✓' : '✗') . "\n";

        if (!$exists) {
            $warnings[] = "Diretório '{$dir}' não existe. Execute: mkdir -p {$dir}";
        }
    }
}

echo "\n";

// ============================================================================
// 5. Verificar Composer e Dependências
// ============================================================================

echo "📦 Verificando Dependências...\n";

$vendorDir = __DIR__ . '/../vendor';
$checks['composer_installed'] = is_dir($vendorDir);
echo "  Composer instalado: " . ($checks['composer_installed'] ? '✓' : '✗') . "\n";

if (!$checks['composer_installed']) {
    $errors[] = "Dependências não instaladas. Execute: composer install";
}

$autoloadFile = $vendorDir . '/autoload.php';
$checks['autoload_exists'] = file_exists($autoloadFile);
echo "  Autoload disponível: " . ($checks['autoload_exists'] ? '✓' : '✗') . "\n";

echo "\n";

// ============================================================================
// 6. Verificar Classes SEO
// ============================================================================

echo "🔧 Verificando Classes SEO...\n";

$requiredClasses = [
    'App\\Services\\SEO\\SEOStrategiesEngine',
    'App\\Services\\SEO\\SynonymExpansionService',
    'App\\Services\\SEO\\KeywordDistributionService',
    'App\\Services\\SEO\\DescriptionBuilderService',
    'App\\Controllers\\Api\\SeoStrategiesController'
];

foreach ($requiredClasses as $class) {
    $exists = class_exists($class);
    $checks["class_{$class}"] = $exists;
    $shortName = substr($class, strrpos($class, '\\') + 1);
    echo "  {$shortName}: " . ($exists ? '✓' : '✗') . "\n";

    if (!$exists) {
        $errors[] = "Classe '{$class}' não encontrada";
    }
}

echo "\n";

// ============================================================================
// 7. Resumo Final
// ============================================================================

$totalChecks = count($checks);
$passedChecks = count(array_filter($checks));
$percentage = round(($passedChecks / $totalChecks) * 100);

echo "╔════════════════════════════════════════════════════════════╗\n";
echo "║                      RESUMO FINAL                          ║\n";
echo "╚════════════════════════════════════════════════════════════╝\n";
echo "\n";

echo "Verificações: {$passedChecks}/{$totalChecks} ({$percentage}%)\n";
echo "\n";

if (!empty($errors)) {
    echo "❌ ERROS CRÍTICOS ({" . count($errors) . "}):\n";
    foreach ($errors as $i => $error) {
        echo "  " . ($i + 1) . ". {$error}\n";
    }
    echo "\n";
}

if (!empty($warnings)) {
    echo "⚠️  AVISOS ({" . count($warnings) . "}):\n";
    foreach ($warnings as $i => $warning) {
        echo "  " . ($i + 1) . ". {$warning}\n";
    }
    echo "\n";
}

if (empty($errors) && empty($warnings)) {
    echo "✅ Sistema configurado corretamente!\n";
    echo "\n";
    echo "Próximos passos:\n";
    echo "  1. Teste o sistema: php examples/seo_real_usage_example.php\n";
    echo "  2. Execute os testes: php vendor/bin/phpunit\n";
    echo "  3. Acesse o dashboard: http://seu-dominio.com/seo-dashboard.html\n";
    echo "\n";
    exit(0);
} elseif (empty($errors)) {
    echo "⚠️  Sistema funcional com avisos.\n";
    echo "Corrija os avisos para melhor performance.\n";
    echo "\n";
    exit(0);
} else {
    echo "❌ Sistema NÃO está pronto para uso.\n";
    echo "Corrija os erros críticos antes de continuar.\n";
    echo "\n";
    echo "Consulte: INSTALLATION_GUIDE.md\n";
    echo "\n";
    exit(1);
}
