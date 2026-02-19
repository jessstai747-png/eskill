<?php
/**
 * Validação Completa do Módulo de Clonagem de Anúncios
 * 
 * Este script verifica todos os componentes do módulo:
 * - Infraestrutura (tabelas, conexões)
 * - Serviços (CatalogCloneService, JobService, etc)
 * - Endpoints da API
 * - Workers
 * - Interface (Views, JS)
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Database;
use App\Services\CatalogCloneService;
use App\Services\JobService;
use App\Services\CloneMetricsService;
use App\Services\ClonePostActionsService;
use App\Services\CloneMonitoringService;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║       VALIDAÇÃO COMPLETA - MÓDULO DE CLONAGEM v2.1            ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n";

$db = Database::getInstance();
$checks = ['passed' => 0, 'failed' => 0, 'warnings' => 0];

function check(string $category, string $name, callable $fn, array &$checks, bool $critical = true): void {
    echo "  ▶ $name... ";
    try {
        $result = $fn();
        if ($result === true) {
            echo "✅\n";
            $checks['passed']++;
        } elseif ($result === 'warn') {
            echo "⚠️  (aviso)\n";
            $checks['warnings']++;
        } else {
            echo $critical ? "❌\n" : "⚠️\n";
            $critical ? $checks['failed']++ : $checks['warnings']++;
        }
    } catch (Exception $e) {
        echo "❌ " . substr($e->getMessage(), 0, 60) . "\n";
        $checks['failed']++;
    }
}

// ═══════════════════════════════════════════════════════════════════
// SEÇÃO 1: INFRAESTRUTURA DE BANCO DE DADOS
// ═══════════════════════════════════════════════════════════════════
echo "\n📦 INFRAESTRUTURA (Banco de Dados)\n";
echo str_repeat('─', 66) . "\n";

check('db', 'Conexão com banco de dados', function() use ($db) {
    return $db->query("SELECT 1")->fetch() !== false;
}, $checks);

$requiredTables = ['cloned_items', 'clone_post_actions_log', 'jobs', 'ml_accounts', 'clone_templates'];
foreach ($requiredTables as $table) {
    check('db', "Tabela '$table' existe", function() use ($db, $table) {
        $stmt = $db->query("SHOW TABLES LIKE '$table'");
        return $stmt->rowCount() > 0;
    }, $checks, $table !== 'clone_templates');
}

// Campos de tracking em cloned_items
check('db', 'Campos de tracking (job_id, pricing_strategy, etc)', function() use ($db) {
    $stmt = $db->query("DESCRIBE cloned_items");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    $required = ['job_id', 'pricing_strategy', 'original_price', 'final_price', 'processing_time_ms'];
    foreach ($required as $col) {
        if (!in_array($col, $columns)) return false;
    }
    return true;
}, $checks);

// ═══════════════════════════════════════════════════════════════════
// SEÇÃO 2: SERVIÇOS
// ═══════════════════════════════════════════════════════════════════
echo "\n🔧 SERVIÇOS\n";
echo str_repeat('─', 66) . "\n";

$services = [
    'CatalogCloneService' => CatalogCloneService::class,
    'JobService' => JobService::class,
    'CloneMetricsService' => CloneMetricsService::class,
    'ClonePostActionsService' => ClonePostActionsService::class,
    'CloneMonitoringService' => CloneMonitoringService::class,
];

foreach ($services as $name => $class) {
    check('services', $name, function() use ($class) {
        $instance = new $class();
        return $instance !== null;
    }, $checks);
}

// ═══════════════════════════════════════════════════════════════════
// SEÇÃO 3: WORKERS
// ═══════════════════════════════════════════════════════════════════
echo "\n⚙️  WORKERS\n";
echo str_repeat('─', 66) . "\n";

$workers = [
    'catalog-clone-worker.php' => 'bin/catalog-clone-worker.php',
    'clone-post-actions-worker.php' => 'bin/clone-post-actions-worker.php',
];

foreach ($workers as $name => $path) {
    check('workers', $name, function() use ($path) {
        $fullPath = __DIR__ . '/../' . $path;
        if (!file_exists($fullPath)) return false;
        
        // Check for syntax errors
        $output = shell_exec("php -l $fullPath 2>&1");
        return strpos($output, 'No syntax errors') !== false;
    }, $checks);
}

// ═══════════════════════════════════════════════════════════════════
// SEÇÃO 4: ARQUIVOS DE CÓDIGO
// ═══════════════════════════════════════════════════════════════════
echo "\n📄 ARQUIVOS DE CÓDIGO\n";
echo str_repeat('─', 66) . "\n";

$codeFiles = [
    'Controller' => 'app/Controllers/CatalogCloneController.php',
    'Service' => 'app/Services/CatalogCloneService.php',
    'Routes (api.php)' => 'app/Routes/api.php',
    'View (dashboard)' => 'app/Views/dashboard/catalog_clone.php',
    'JavaScript' => 'public/js/catalog-clone.js',
];

foreach ($codeFiles as $name => $path) {
    check('files', $name, function() use ($path) {
        $fullPath = __DIR__ . '/../' . $path;
        return file_exists($fullPath) && filesize($fullPath) > 100;
    }, $checks);
}

// ═══════════════════════════════════════════════════════════════════
// SEÇÃO 5: ROTAS DA API
// ═══════════════════════════════════════════════════════════════════
echo "\n🌐 ROTAS DA API (verificação via routes)\n";
echo str_repeat('─', 66) . "\n";

$routesFile = file_get_contents(__DIR__ . '/../app/Routes/api.php');
$expectedRoutes = [
    'api/catalog/clone' => 'POST clone único',
    'api/catalog/clone/batch' => 'POST clone em lote',
    'api/catalog/clone/simulate' => 'POST simulação',
    'api/catalog/clone/metrics' => 'GET métricas',
    'api/catalog/clone/history' => 'GET histórico',
    'api/catalog/clone/jobs' => 'GET/POST jobs',
    'api/catalog/clone/dry-run' => 'POST dry-run',
];

foreach ($expectedRoutes as $route => $desc) {
    check('routes', "$desc ($route)", function() use ($routesFile, $route) {
        return strpos($routesFile, $route) !== false;
    }, $checks);
}

// ═══════════════════════════════════════════════════════════════════
// SEÇÃO 6: CONTAS MERCADO LIVRE
// ═══════════════════════════════════════════════════════════════════
echo "\n🏪 CONTAS MERCADO LIVRE\n";
echo str_repeat('─', 66) . "\n";

$stmt = $db->query("SELECT id, nickname, status FROM ml_accounts ORDER BY id");
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);
$activeCount = 0;

foreach ($accounts as $a) {
    $isActive = $a['status'] === 'active';
    if ($isActive) $activeCount++;
    $icon = $isActive ? '✅' : '⚪';
    echo "  $icon Conta {$a['id']}: {$a['nickname']} ({$a['status']})\n";
}

echo "\n";
check('accounts', 'Pelo menos 1 conta ativa', function() use ($activeCount) {
    return $activeCount >= 1;
}, $checks);

check('accounts', 'Pelo menos 2 contas ativas (para clonagem)', function() use ($activeCount) {
    return $activeCount >= 2 ? true : 'warn';
}, $checks, false);

// ═══════════════════════════════════════════════════════════════════
// SEÇÃO 7: FUNCIONALIDADES DO SERVIÇO
// ═══════════════════════════════════════════════════════════════════
echo "\n🔬 FUNCIONALIDADES DO SERVIÇO\n";
echo str_repeat('─', 66) . "\n";

check('func', 'Validação: mesma conta bloqueada', function() {
    $service = new CatalogCloneService();
    $result = $service->cloneCatalogItem([
        'source_account_id' => 1,
        'source_item_id' => 'MLB123',
        'target_account_id' => 1
    ]);
    return $result['status'] === 'error' && strpos($result['message'], 'mesma conta') !== false;
}, $checks);

check('func', 'Métricas retornam dados', function() {
    $service = new CatalogCloneService();
    $metrics = $service->getCloneMetrics();
    return isset($metrics['total']);
}, $checks);

check('func', 'Histórico retorna array', function() {
    $service = new CatalogCloneService();
    $history = $service->getCloneHistory();
    return is_array($history);
}, $checks);

check('func', 'JobService dispatch funciona', function() {
    $service = new JobService();
    $jobId = $service->dispatch('test_job', ['test' => true]);
    if ($jobId) {
        // Cleanup test job
        $db = Database::getInstance();
        $db->prepare("DELETE FROM jobs WHERE id = ?")->execute([$jobId]);
    }
    return $jobId > 0;
}, $checks);

// ═══════════════════════════════════════════════════════════════════
// SEÇÃO 8: MONITORAMENTO E HARDENING (FASE 6)
// ═══════════════════════════════════════════════════════════════════
echo "\n🛡️  MONITORAMENTO E HARDENING\n";
echo str_repeat('─', 66) . "\n";

check('monitoring', 'Tabela clone_alerts existe', function() use ($db) {
    $stmt = $db->query("SHOW TABLES LIKE 'clone_alerts'");
    return $stmt->rowCount() > 0;
}, $checks);

check('monitoring', 'Tabela clone_health_metrics existe', function() use ($db) {
    $stmt = $db->query("SHOW TABLES LIKE 'clone_health_metrics'");
    return $stmt->rowCount() > 0;
}, $checks);

check('monitoring', 'Feature flags configuradas', function() use ($db) {
    $stmt = $db->query("SELECT COUNT(*) FROM feature_flags WHERE flag_name LIKE 'clone%'");
    return (int)$stmt->fetchColumn() >= 4;
}, $checks);

check('monitoring', 'Sistema de saúde funcionando', function() {
    $service = new CloneMonitoringService();
    $health = $service->getSystemHealth();
    return isset($health['status']) && in_array($health['status'], ['healthy', 'warning', 'critical']);
}, $checks);

check('monitoring', 'Rate limiting inteligente', function() {
    $service = new CloneMonitoringService();
    $result = $service->canExecuteNow();
    return isset($result['allowed']) && isset($result['delay_ms']);
}, $checks);

// ═══════════════════════════════════════════════════════════════════
// RESUMO FINAL
// ═══════════════════════════════════════════════════════════════════
echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                      RESULTADO FINAL                          ║\n";
echo "╠════════════════════════════════════════════════════════════════╣\n";
printf("║   ✅ Aprovados:   %-4d                                        ║\n", $checks['passed']);
printf("║   ⚠️  Avisos:      %-4d                                        ║\n", $checks['warnings']);
printf("║   ❌ Falhas:      %-4d                                        ║\n", $checks['failed']);
echo "╠════════════════════════════════════════════════════════════════╣\n";

$total = $checks['passed'] + $checks['warnings'] + $checks['failed'];
$successRate = $total > 0 ? round(($checks['passed'] / $total) * 100) : 0;

if ($checks['failed'] === 0) {
    echo "║   🎉 MÓDULO PRONTO PARA PRODUÇÃO!                             ║\n";
    echo "║                                                                ║\n";
    if ($checks['warnings'] > 0) {
        echo "║   ⚠️  Atenção: Ative a segunda conta ML para clonagem real.   ║\n";
    }
} else {
    echo "║   ⚠️  ATENÇÃO: Existem itens pendentes de correção.            ║\n";
}

echo "╚════════════════════════════════════════════════════════════════╝\n\n";

// Saída do status
if ($checks['failed'] > 0) {
    exit(1);
}
exit(0);
