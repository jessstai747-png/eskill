#!/bin/bash

# Script para desenvolvimento contínuo usando PHP CLI
# Usage: ./scripts/cli_continuous_dev.sh <project_id> [sessions_count]

PROJECT_ID=$1
SESSIONS_COUNT=${2:-10}

if [ -z "$PROJECT_ID" ]; then
    echo "❌ Erro: ID do projeto não fornecido"
    echo "Usage: $0 <project_id> [sessions_count]"
    exit 1
fi

echo "🤖 Continuous Development Mode (CLI)"
echo "====================================="
echo "Project ID: $PROJECT_ID"
echo "Sessions to run: $SESSIONS_COUNT"
echo ""

# Verificar status inicial
php << 'PHPCODE'
<?php
require_once 'vendor/autoload.php';
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

use App\Services\Agent\AgentService;

$projectId = (int)$argv[1];
$agentService = new AgentService();

try {
    $status = $agentService->getProjectStatus($projectId);
    
    echo "✅ Projeto encontrado: {$status['name']}\n";
    echo "📊 Status Inicial:\n";
    echo "   Total: {$status['total_features']} features\n";
    echo "   Completas: {$status['completed_features']}\n";
    echo "   Pendentes: {$status['pending_features']}\n";
    echo "   Progresso: " . number_format($status['completion_percentage'], 1) . "%\n";
    echo "\n";
    
} catch (\Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    exit(1);
}
PHPCODE

# Loop de desenvolvimento
SUCCESSFUL=0
FAILED=0
START_TIME=$(date +%s)

for i in $(seq 1 $SESSIONS_COUNT); do
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "🔄 Session #$i/$SESSIONS_COUNT"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    # Executar sessão via PHP
    php << PHPCODE
<?php
require_once 'vendor/autoload.php';
\$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
\$dotenv->load();

use App\Services\Agent\AgentService;

\$projectId = (int)$PROJECT_ID;
\$agentService = new AgentService();

try {
    \$result = \$agentService->runCodingSession(\$projectId);
    
    echo "   Feature: {\$result['feature_worked_on']}\n";
    echo "   Descrição: {\$result['feature_description']}\n";
    
    if (\$result['feature_completed'] && \$result['tests_passed']) {
        echo "   Status: ✅ Completa e testada\n";
        exit(0); // success
    } elseif (\$result['feature_completed']) {
        echo "   Status: ⚠️  Completa mas testes falharam\n";
        exit(2); // partial failure
    } else {
        echo "   Status: ⏳ Em progresso\n";
        exit(1); // in progress
    }
    
} catch (\Exception \$e) {
    echo "   ❌ Erro: " . \$e->getMessage() . "\n";
    exit(3); // error
}
PHPCODE
    
    EXIT_CODE=$?
    
    case $EXIT_CODE in
        0)
            SUCCESSFUL=$((SUCCESSFUL + 1))
            ;;
        2|3)
            FAILED=$((FAILED + 1))
            ;;
    esac
    
    echo ""
    sleep 1
done

# Status final
END_TIME=$(date +%s)
ELAPSED=$((END_TIME - START_TIME))

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 Resumo Final"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

php << PHPCODE
<?php
require_once 'vendor/autoload.php';
\$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
\$dotenv->load();

use App\Services\Agent\AgentService;

\$projectId = (int)$PROJECT_ID;
\$agentService = new AgentService();

\$status = \$agentService->getProjectStatus(\$projectId);

echo "Sessões executadas: $SESSIONS_COUNT\n";
echo "Features completadas nesta execução: $SUCCESSFUL\n";
echo "Falhas: $FAILED\n";
echo "Tempo decorrido: ${ELAPSED}s\n";
echo "\n";
echo "Status do Projeto:\n";
echo "  Total: {\$status['total_features']} features\n";
echo "  Completas: {\$status['completed_features']} (" . number_format(\$status['completion_percentage'], 1) . "%)\n";
echo "  Pendentes: {\$status['pending_features']}\n";
echo "\n";

if (\$status['pending_features'] == 0) {
    echo "🎉 PROJETO COMPLETO! Todas as features foram implementadas!\n";
} else {
    \$avgPerSession = $SUCCESSFUL > 0 ? $SUCCESSFUL : 1;
    \$remainingSessions = (int)ceil(\$status['pending_features'] / \$avgPerSession);
    echo "⏰ Estimativa para conclusão: ~\$remainingSessions sessões adicionais\n";
}
PHPCODE

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""
echo "✅ Desenvolvimento contínuo concluído!"
echo ""
