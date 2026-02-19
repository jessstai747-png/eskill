#!/bin/bash

# Script para desenvolvimento contínuo automatizado
# Usage: ./scripts/continuous_development.sh <project_id> [sessions_count]

PROJECT_ID=$1
SESSIONS_COUNT=${2:-10}

if [ -z "$PROJECT_ID" ]; then
    echo "❌ Erro: ID do projeto não fornecido"
    echo "Usage: $0 <project_id> [sessions_count]"
    echo ""
    echo "Exemplo: $0 4 20"
    exit 1
fi

echo "🤖 Continuous Development Mode"
echo "=============================="
echo "Project ID: $PROJECT_ID"
echo "Sessions to run: $SESSIONS_COUNT"
echo ""

# Verificar se projeto existe
STATUS=$(curl -s "http://localhost/api/agent/projects/${PROJECT_ID}/status" 2>/dev/null)

if [ $? -ne 0 ]; then
    echo "❌ Erro: Não foi possível conectar à API"
    exit 1
fi

# Verificar se projeto existe
if echo "$STATUS" | grep -q "error"; then
    echo "❌ Erro: Projeto #${PROJECT_ID} não encontrado"
    exit 1
fi

echo "✅ Projeto encontrado"
echo ""

# Mostrar status inicial
TOTAL_FEATURES=$(echo "$STATUS" | grep -o '"total_features":[0-9]*' | cut -d':' -f2)
COMPLETED=$(echo "$STATUS" | grep -o '"completed_features":[0-9]*' | cut -d':' -f2)
PENDING=$(echo "$STATUS" | grep -o '"pending_features":[0-9]*' | cut -d':' -f2)

echo "📊 Status Inicial:"
echo "   Total: $TOTAL_FEATURES features"
echo "   Completas: $COMPLETED"
echo "   Pendentes: $PENDING"
echo ""

# Loop de desenvolvimento
SUCCESSFUL=0
FAILED=0
START_TIME=$(date +%s)

for i in $(seq 1 $SESSIONS_COUNT); do
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    echo "🔄 Session #$i/$SESSIONS_COUNT"
    echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
    
    # Executar sessão
    RESULT=$(curl -s -X POST "http://localhost/api/agent/projects/${PROJECT_ID}/session" 2>/dev/null)
    
    if [ $? -ne 0 ]; then
        echo "❌ Erro ao executar sessão"
        FAILED=$((FAILED + 1))
        continue
    fi
    
    # Extrair informações
    FEATURE_ID=$(echo "$RESULT" | grep -o '"feature_worked_on":"[^"]*"' | cut -d'"' -f4)
    FEATURE_DESC=$(echo "$RESULT" | grep -o '"feature_description":"[^"]*"' | cut -d'"' -f4)
    COMPLETED_FLAG=$(echo "$RESULT" | grep -o '"feature_completed":[^,]*' | cut -d':' -f2)
    TESTS_PASSED=$(echo "$RESULT" | grep -o '"tests_passed":[^,]*' | cut -d':' -f2)
    
    echo "   Feature: $FEATURE_ID"
    echo "   Descrição: $FEATURE_DESC"
    
    if [ "$COMPLETED_FLAG" = "true" ] && [ "$TESTS_PASSED" = "true" ]; then
        echo "   Status: ✅ Completa e testada"
        SUCCESSFUL=$((SUCCESSFUL + 1))
    elif [ "$COMPLETED_FLAG" = "true" ]; then
        echo "   Status: ⚠️  Completa mas testes falharam"
        FAILED=$((FAILED + 1))
    else
        echo "   Status: ⏳ Em progresso"
    fi
    
    echo ""
    
    # Delay entre sessões
    sleep 2
done

# Status final
END_TIME=$(date +%s)
ELAPSED=$((END_TIME - START_TIME))

echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo "📊 Resumo Final"
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"

STATUS=$(curl -s "http://localhost/api/agent/projects/${PROJECT_ID}/status" 2>/dev/null)

TOTAL_FEATURES=$(echo "$STATUS" | grep -o '"total_features":[0-9]*' | cut -d':' -f2)
COMPLETED_NOW=$(echo "$STATUS" | grep -o '"completed_features":[0-9]*' | cut -d':' -f2)
PENDING_NOW=$(echo "$STATUS" | grep -o '"pending_features":[0-9]*' | cut -d':' -f2)
PERCENTAGE=$(echo "$STATUS" | grep -o '"completion_percentage":[0-9.]*' | cut -d':' -f2)

echo "Sessões executadas: $SESSIONS_COUNT"
echo "Features completadas: $SUCCESSFUL"
echo "Falhas: $FAILED"
echo "Tempo decorrido: ${ELAPSED}s"
echo ""
echo "Status do Projeto:"
echo "  Total: $TOTAL_FEATURES features"
echo "  Completas: $COMPLETED_NOW ($PERCENTAGE%)"
echo "  Pendentes: $PENDING_NOW"
echo ""

# Progresso
PROGRESS_MADE=$((COMPLETED_NOW - COMPLETED))
echo "📈 Progresso nesta execução: +$PROGRESS_MADE features"

if [ $PENDING_NOW -eq 0 ]; then
    echo ""
    echo "🎉 PROJETO COMPLETO! Todas as features foram implementadas!"
else
    REMAINING_SESSIONS=$((PENDING_NOW / (PROGRESS_MADE > 0 ? PROGRESS_MADE : 1) * SESSIONS_COUNT))
    echo ""
    echo "⏰ Estimativa para conclusão: ~$REMAINING_SESSIONS sessões adicionais"
fi

echo ""
echo "━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━"
echo ""

# Sugestões
echo "💡 Próximas ações:"
echo ""
echo "1. Ver detalhes do projeto:"
echo "   curl http://localhost/api/agent/projects/${PROJECT_ID}/status | jq '.'"
echo ""
echo "2. Continuar desenvolvimento:"
echo "   $0 ${PROJECT_ID} 20"
echo ""
echo "3. Ver arquivos do projeto:"
echo "   ls -la storage/agent_projects/${PROJECT_ID}/"
echo ""
echo "4. Ver progresso narrativo:"
echo "   cat storage/agent_projects/${PROJECT_ID}/claude-progress.txt"
echo ""
echo "5. Ver feature list:"
echo "   cat storage/agent_projects/${PROJECT_ID}/feature_list.json | jq '.'"
echo ""
