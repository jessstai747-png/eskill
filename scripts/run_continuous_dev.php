<?php

/**
 * Script CLI para desenvolvimento contínuo
 * Usage: php scripts/run_continuous_dev.php <project_id> [sessions_count]
 */

require_once __DIR__ . '/../vendor/autoload.php';

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use App\Services\Agent\AgentService;

// Argumentos
$projectId = (int)($argv[1] ?? 0);
$sessionsCount = (int)($argv[2] ?? 10);

if ($projectId === 0) {
    echo "❌ Erro: ID do projeto não fornecido\n";
    echo "Usage: php {$argv[0]} <project_id> [sessions_count]\n\n";
    echo "Exemplo: php {$argv[0]} 4 20\n";
    exit(1);
}

echo "🤖 Continuous Development Mode\n";
echo "=============================\n";
echo "Project ID: {$projectId}\n";
echo "Sessions to run: {$sessionsCount}\n";
echo "\n";

// Inicializar serviço
$agentService = new AgentService();

try {
    // Status inicial
    $initialStatus = $agentService->getProjectStatus($projectId);
    
    echo "✅ Projeto encontrado: {$initialStatus['name']}\n";
    echo "📊 Status Inicial:\n";
    echo "   Total: {$initialStatus['total_features']} features\n";
    echo "   Completas: {$initialStatus['completed_features']}\n";
    echo "   Pendentes: {$initialStatus['pending_features']}\n";
    echo "   Progresso: " . number_format($initialStatus['completion_percentage'], 1) . "%\n";
    echo "\n";
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}

// Estatísticas
$successful = 0;
$failed = 0;
$startTime = time();

// Loop de desenvolvimento
for ($i = 1; $i <= $sessionsCount; $i++) {
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    echo "🔄 Session #{$i}/{$sessionsCount}\n";
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
    
    try {
        $result = $agentService->runCodingSession($projectId);
        
        echo "   Feature: {$result['feature_worked_on']}\n";
        echo "   Descrição: {$result['feature_description']}\n";
        
        if ($result['feature_completed'] && $result['tests_passed']) {
            echo "   Status: ✅ Completa e testada\n";
            $successful++;
        } elseif ($result['feature_completed']) {
            echo "   Status: ⚠️  Completa mas testes falharam\n";
            $failed++;
        } else {
            echo "   Status: ⏳ Em progresso\n";
        }
        
    } catch (\Exception $e) {
        echo "   ❌ Erro: " . $e->getMessage() . "\n";
        $failed++;
    }
    
    echo "\n";
    usleep(500000); // 500ms delay
}

// Status final
$endTime = time();
$elapsed = $endTime - $startTime;

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";
echo "📊 Resumo Final\n";
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n";

$finalStatus = $agentService->getProjectStatus($projectId);

echo "Sessões executadas: {$sessionsCount}\n";
echo "Features completadas nesta execução: {$successful}\n";
echo "Falhas: {$failed}\n";
echo "Tempo decorrido: {$elapsed}s\n";
echo "\n";
echo "Status do Projeto:\n";
echo "  Total: {$finalStatus['total_features']} features\n";
echo "  Completas: {$finalStatus['completed_features']} (" . number_format($finalStatus['completion_percentage'], 1) . "%)\n";
echo "  Pendentes: {$finalStatus['pending_features']}\n";
echo "\n";

$progressMade = $finalStatus['completed_features'] - $initialStatus['completed_features'];
echo "📈 Progresso nesta execução: +{$progressMade} features\n";

if ($finalStatus['pending_features'] == 0) {
    echo "\n🎉 PROJETO COMPLETO! Todas as features foram implementadas!\n";
} else {
    $avgPerSession = $successful > 0 ? ($sessionsCount / $successful) : 1;
    $remainingSessions = (int)ceil($finalStatus['pending_features'] * $avgPerSession);
    echo "\n⏰ Estimativa para conclusão: ~{$remainingSessions} sessões adicionais\n";
}

echo "\n━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━\n\n";

// Próximas ações
echo "💡 Próximas ações:\n\n";
echo "1. Continuar desenvolvimento:\n";
echo "   php {$argv[0]} {$projectId} 20\n\n";
echo "2. Ver arquivos do projeto:\n";
echo "   ls -la storage/agent_projects/{$projectId}/\n\n";
echo "3. Ver progresso narrativo:\n";
echo "   cat storage/agent_projects/{$projectId}/claude-progress.txt\n\n";
echo "4. Ver feature list:\n";
echo "   cat storage/agent_projects/{$projectId}/feature_list.json | jq '.'\n\n";

echo "✅ Desenvolvimento contínuo concluído!\n\n";
