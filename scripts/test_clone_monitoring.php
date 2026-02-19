<?php
/**
 * Teste do Sistema de Monitoramento de Clonagem
 * 
 * Testa:
 * - Criação de tabelas
 * - Feature flags
 * - Sistema de alertas
 * - Métricas de saúde
 * - Rate limiting
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Services\CloneMonitoringService;

echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║     TESTE - SISTEMA DE MONITORAMENTO DE CLONAGEM             ║\n";
echo "╚════════════════════════════════════════════════════════════════╝\n\n";

$results = ['passed' => 0, 'failed' => 0];

function test(string $name, callable $fn, array &$results): void {
    echo "▶ $name... ";
    try {
        $result = $fn();
        if ($result) {
            echo "✅ PASSED\n";
            $results['passed']++;
        } else {
            echo "❌ FAILED\n";
            $results['failed']++;
        }
    } catch (Exception $e) {
        echo "❌ ERROR: " . $e->getMessage() . "\n";
        $results['failed']++;
    }
}

// ═══════════════════════════════════════════════════════════════
// FASE 1: INSTANCIAÇÃO
// ═══════════════════════════════════════════════════════════════
echo "📦 INFRAESTRUTURA\n";
echo str_repeat('─', 66) . "\n";

test("CloneMonitoringService instancia corretamente", function() {
    $service = new CloneMonitoringService();
    return $service !== null;
}, $results);

test("Tabela clone_alerts existe", function() {
    $db = \App\Database::getInstance();
    $stmt = $db->query("SHOW TABLES LIKE 'clone_alerts'");
    return $stmt->rowCount() > 0;
}, $results);

test("Tabela feature_flags existe", function() {
    $db = \App\Database::getInstance();
    $stmt = $db->query("SHOW TABLES LIKE 'feature_flags'");
    return $stmt->rowCount() > 0;
}, $results);

test("Tabela clone_health_metrics existe", function() {
    $db = \App\Database::getInstance();
    $stmt = $db->query("SHOW TABLES LIKE 'clone_health_metrics'");
    return $stmt->rowCount() > 0;
}, $results);

// ═══════════════════════════════════════════════════════════════
// FASE 2: FEATURE FLAGS
// ═══════════════════════════════════════════════════════════════
echo "\n🚩 FEATURE FLAGS\n";
echo str_repeat('─', 66) . "\n";

test("Feature flags padrão inicializadas", function() {
    $service = new CloneMonitoringService();
    $flags = $service->listFeatureFlags();
    return count($flags) >= 4; // clone_module_enabled, clone_batch_enabled, etc.
}, $results);

test("Verificar flag clone_module_enabled", function() {
    $service = new CloneMonitoringService();
    return $service->isFeatureEnabled(CloneMonitoringService::FLAG_CLONE_ENABLED);
}, $results);

test("Desabilitar e reabilitar flag", function() {
    $service = new CloneMonitoringService();
    
    // Desabilitar
    $service->setFeatureFlag(CloneMonitoringService::FLAG_RATE_LIMIT_STRICT, true);
    $enabled1 = $service->isFeatureEnabled(CloneMonitoringService::FLAG_RATE_LIMIT_STRICT);
    
    // Reabilitar
    $service->setFeatureFlag(CloneMonitoringService::FLAG_RATE_LIMIT_STRICT, false);
    $enabled2 = $service->isFeatureEnabled(CloneMonitoringService::FLAG_RATE_LIMIT_STRICT);
    
    return $enabled1 === true && $enabled2 === false;
}, $results);

test("canClone() retorna allowed quando módulo habilitado", function() {
    $service = new CloneMonitoringService();
    $result = $service->canClone();
    return $result['allowed'] === true;
}, $results);

// ═══════════════════════════════════════════════════════════════
// FASE 3: SISTEMA DE ALERTAS
// ═══════════════════════════════════════════════════════════════
echo "\n🚨 SISTEMA DE ALERTAS\n";
echo str_repeat('─', 66) . "\n";

test("Criar alerta de teste", function() {
    $service = new CloneMonitoringService();
    $alertId = $service->createAlert('test_alert', 'info', 'Alerta de teste do sistema', ['test' => true]);
    return $alertId > 0;
}, $results);

test("Listar alertas não reconhecidos", function() {
    $service = new CloneMonitoringService();
    $alerts = $service->listAlerts(true);
    return is_array($alerts);
}, $results);

test("Reconhecer alerta", function() {
    $service = new CloneMonitoringService();
    $db = \App\Database::getInstance();
    
    // Buscar um alerta não reconhecido
    $stmt = $db->query("SELECT id FROM clone_alerts WHERE acknowledged = FALSE LIMIT 1");
    $alert = $stmt->fetch(\PDO::FETCH_ASSOC);
    
    if (!$alert) {
        // Criar um novo para testar
        $alertId = $service->createAlert('test_ack', 'info', 'Teste de reconhecimento');
        $service->acknowledgeAlert($alertId, 1);
        return true;
    }
    
    return $service->acknowledgeAlert($alert['id'], 1);
}, $results);

// ═══════════════════════════════════════════════════════════════
// FASE 4: MÉTRICAS
// ═══════════════════════════════════════════════════════════════
echo "\n📊 MÉTRICAS E SAÚDE\n";
echo str_repeat('─', 66) . "\n";

test("Registrar métrica", function() {
    $service = new CloneMonitoringService();
    $service->recordMetric('test_metric', 42.5, 'count', ['test' => true]);
    return true; // Se não lançar exceção, passou
}, $results);

test("Obter métricas agregadas", function() {
    $service = new CloneMonitoringService();
    $metrics = $service->getMetrics('1h');
    return is_array($metrics);
}, $results);

test("Obter saúde do sistema", function() {
    $service = new CloneMonitoringService();
    $health = $service->getSystemHealth();
    
    return isset($health['status']) && 
           isset($health['error_rate']) && 
           isset($health['pending_jobs']);
}, $results);

test("Saúde retorna status válido", function() {
    $service = new CloneMonitoringService();
    $health = $service->getSystemHealth();
    return in_array($health['status'], ['healthy', 'warning', 'critical']);
}, $results);

// ═══════════════════════════════════════════════════════════════
// FASE 5: RATE LIMITING
// ═══════════════════════════════════════════════════════════════
echo "\n⏱️  RATE LIMITING\n";
echo str_repeat('─', 66) . "\n";

test("Obter delay recomendado", function() {
    $service = new CloneMonitoringService();
    $delay = $service->getRecommendedDelay();
    return $delay >= 1000; // Pelo menos 1 segundo
}, $results);

test("Verificar se pode executar agora", function() {
    $service = new CloneMonitoringService();
    $result = $service->canExecuteNow();
    return isset($result['allowed']) && isset($result['delay_ms']);
}, $results);

// ═══════════════════════════════════════════════════════════════
// FASE 6: LOGGING
// ═══════════════════════════════════════════════════════════════
echo "\n📝 LOGGING ESTRUTURADO\n";
echo str_repeat('─', 66) . "\n";

test("Log de início de clonagem", function() {
    $service = new CloneMonitoringService();
    $operationId = $service->logCloneStart('MLB123456', 1, 2, ['pricing_strategy' => ['type' => 'copy']]);
    return !empty($operationId) && strpos($operationId, 'clone_') === 0;
}, $results);

test("Log de fim de clonagem (sucesso)", function() {
    $service = new CloneMonitoringService();
    $operationId = $service->logCloneStart('MLB123456', 1, 2);
    $service->logCloneEnd($operationId, 'success', 'MLB789012', null, 2.5);
    return true;
}, $results);

test("Log de erro de API", function() {
    $service = new CloneMonitoringService();
    $service->logApiError('/items/MLB123', 404, 'Item not found', ['account_id' => 1]);
    return true;
}, $results);

// ═══════════════════════════════════════════════════════════════
// FASE 7: RELATÓRIOS
// ═══════════════════════════════════════════════════════════════
echo "\n📄 RELATÓRIOS\n";
echo str_repeat('─', 66) . "\n";

test("Gerar relatório diário", function() {
    $service = new CloneMonitoringService();
    $report = $service->generateDailyReport();
    
    return isset($report['date']) && 
           isset($report['metrics']) && 
           isset($report['alerts']);
}, $results);

// ═══════════════════════════════════════════════════════════════
// LIMPEZA
// ═══════════════════════════════════════════════════════════════
echo "\n🧹 LIMPEZA\n";
echo str_repeat('─', 66) . "\n";

// Limpar dados de teste
$db = \App\Database::getInstance();
$db->exec("DELETE FROM clone_alerts WHERE alert_type LIKE 'test%'");
$db->exec("DELETE FROM clone_health_metrics WHERE metric_name = 'test_metric'");
echo "  ✅ Dados de teste removidos\n";

// ═══════════════════════════════════════════════════════════════
// RESUMO
// ═══════════════════════════════════════════════════════════════
echo "\n";
echo "╔════════════════════════════════════════════════════════════════╗\n";
echo "║                      RESULTADO FINAL                          ║\n";
echo "╠════════════════════════════════════════════════════════════════╣\n";
printf("║   ✅ Aprovados:   %-4d                                        ║\n", $results['passed']);
printf("║   ❌ Falhas:      %-4d                                        ║\n", $results['failed']);
echo "╠════════════════════════════════════════════════════════════════╣\n";

if ($results['failed'] === 0) {
    echo "║   🎉 SISTEMA DE MONITORAMENTO FUNCIONANDO PERFEITAMENTE!      ║\n";
} else {
    echo "║   ⚠️  Alguns testes falharam. Verifique os logs.              ║\n";
}

echo "╚════════════════════════════════════════════════════════════════╝\n\n";

exit($results['failed'] > 0 ? 1 : 0);
