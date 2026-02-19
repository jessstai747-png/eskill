<?php

/**
 * Teste rápido do sistema de Long-Running Agents
 * 
 * Execução: php scripts/test_agent_system.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use App\Services\Agent\AgentService;
use App\Services\Agent\FeatureListManager;
use App\Services\Agent\AgentProgressTracker;

echo "🤖 Testando Sistema de Long-Running Agents\n";
echo "==========================================\n\n";

try {
    $agentService = new AgentService();
    
    // 1. Criar projeto de teste
    echo "1️⃣ Criando projeto de teste...\n";
    $result = $agentService->startProject([
        'name' => 'Simple Todo App',
        'description' => 'A simple todo application for testing the agent system',
        'category' => 'dashboard',
        'requirements' => [
            'User can create a todo',
            'User can mark todo as complete',
            'User can delete a todo',
        ],
    ]);
    
    $projectId = $result['project_id'];
    echo "✓ Projeto #{$projectId} criado com sucesso!\n";
    echo "  - Features geradas: {$result['features_count']}\n";
    echo "  - Arquivos criados: " . implode(', ', $result['init_files_created']) . "\n\n";
    
    // 2. Ver features geradas
    echo "2️⃣ Features geradas:\n";
    $featureManager = new FeatureListManager();
    $features = $featureManager->getFeatureList($projectId);
    
    foreach (array_slice($features, 0, 5) as $feature) {
        $priority = strtoupper($feature['priority']);
        $status = $feature['passes'] ? '✓' : '⏳';
        echo "   {$status} [{$priority}] {$feature['id']}: {$feature['description']}\n";
    }
    
    if (count($features) > 5) {
        echo "   ... e mais " . (count($features) - 5) . " features\n";
    }
    echo "\n";
    
    // 3. Executar primeira sessão
    echo "3️⃣ Executando primeira sessão de coding...\n";
    $sessionResult = $agentService->runCodingSession($projectId);
    
    echo "✓ Sessão #{$sessionResult['session_id']} concluída!\n";
    echo "  - Feature trabalhada: {$sessionResult['feature_worked_on']}\n";
    echo "  - Status: " . ($sessionResult['feature_completed'] ? 'Completa ✓' : 'Em progresso ⏳') . "\n";
    echo "  - Testes: " . ($sessionResult['tests_passed'] ? 'Passaram ✓' : 'Falharam ✗') . "\n";
    echo "  - Arquivos modificados: " . count($sessionResult['files_modified']) . "\n\n";
    
    // 4. Executar mais 2 sessões
    echo "4️⃣ Executando mais 2 sessões...\n";
    for ($i = 2; $i <= 3; $i++) {
        $sessionResult = $agentService->runCodingSession($projectId);
        $status = $sessionResult['feature_completed'] ? '✓' : '⏳';
        echo "   Sessão #{$i}: Feature {$sessionResult['feature_worked_on']} {$status}\n";
    }
    echo "\n";
    
    // 5. Ver status do projeto
    echo "5️⃣ Status do projeto:\n";
    $status = $agentService->getProjectStatus($projectId);
    
    echo "   Projeto: {$status['project']['name']}\n";
    echo "   Conclusão: {$status['completion_percentage']}%\n";
    echo "   Features totais: {$status['total_features']}\n";
    echo "   Features completas: {$status['completed_features']}\n";
    echo "   Features pendentes: {$status['pending_features']}\n";
    echo "   Sessões executadas: {$status['sessions_count']}\n\n";
    
    // 6. Ver breakdown por categoria
    echo "6️⃣ Features por categoria:\n";
    foreach ($status['features_breakdown'] as $category => $count) {
        if ($count > 0) {
            echo "   - " . ucfirst($category) . ": {$count}\n";
        }
    }
    echo "\n";
    
    // 7. Ver progresso recente
    echo "7️⃣ Progresso recente:\n";
    $tracker = new AgentProgressTracker();
    $recentProgress = $tracker->getRecentProgress($projectId, 3);
    
    foreach ($recentProgress as $log) {
        $timestamp = date('H:i:s', strtotime($log['created_at']));
        $type = ucfirst($log['session_type']);
        $status = ucfirst($log['status']);
        echo "   [{$timestamp}] {$type} - {$status}\n";
        if ($log['summary']) {
            echo "      {$log['summary']}\n";
        }
    }
    echo "\n";
    
    // 8. Verificar arquivos criados
    echo "8️⃣ Arquivos do projeto:\n";
    $projectPath = __DIR__ . "/../storage/agent_projects/{$projectId}";
    
    if (is_dir($projectPath)) {
        $files = scandir($projectPath);
        foreach ($files as $file) {
            if ($file === '.' || $file === '..') continue;
            $icon = is_dir("{$projectPath}/{$file}") ? '📁' : '📄';
            echo "   {$icon} {$file}\n";
        }
    }
    echo "\n";
    
    // 9. Ver próxima feature
    echo "9️⃣ Próxima feature a ser implementada:\n";
    if ($sessionResult['next_feature']) {
        $next = $sessionResult['next_feature'];
        echo "   ID: {$next['id']}\n";
        echo "   Descrição: {$next['description']}\n";
        echo "   Prioridade: " . strtoupper($next['priority']) . "\n";
    } else {
        echo "   ✓ Todas as features foram completadas!\n";
    }
    echo "\n";
    
    echo "==========================================\n";
    echo "✅ Teste concluído com sucesso!\n\n";
    echo "📁 Projeto criado em: storage/agent_projects/{$projectId}/\n";
    echo "📄 Ver feature_list.json para lista completa de features\n";
    echo "📝 Ver claude-progress.txt para log detalhado de progresso\n\n";
    echo "💡 Próximos comandos:\n";
    echo "   # Executar mais sessões\n";
    echo "   curl -X POST http://localhost/api/agent/projects/{$projectId}/session\n\n";
    echo "   # Ver status atualizado\n";
    echo "   curl http://localhost/api/agent/projects/{$projectId}/status\n\n";
    echo "   # Testar feature específica\n";
    echo "   curl -X POST http://localhost/api/agent/projects/{$projectId}/test \\\n";
    echo "     -H 'Content-Type: application/json' \\\n";
    echo "     -d '{\"feature_id\":\"F1\"}'\n\n";
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
