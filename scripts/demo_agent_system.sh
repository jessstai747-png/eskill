#!/bin/bash

# Demo do Sistema de Long-Running Agents
# Baseado em: https://www.anthropic.com/engineering/effective-harnesses-for-long-running-agents

echo "🤖 Long-Running Agents System - Demo"
echo "===================================="
echo ""

# 1. Executar migration
echo "1️⃣ Criando tabelas do sistema..."
php scripts/migrate_agent_system.php
echo ""

# 2. Criar projeto de exemplo
echo "2️⃣ Criando projeto de exemplo: Todo App"
curl -s -X POST http://localhost/api/agent/projects/start \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Todo App with Authentication",
    "description": "A complete todo application with user authentication, categories, priorities, and due dates",
    "category": "dashboard",
    "requirements": [
      "User can register with email and password",
      "User can login and logout",
      "User can create a new todo item",
      "User can edit existing todo",
      "User can delete todo",
      "User can mark todo as complete",
      "User can assign category to todo",
      "User can set priority (high/medium/low)",
      "User can set due date",
      "User can filter todos by status",
      "User can filter todos by category",
      "User can search todos",
      "Dashboard shows todo statistics",
      "User receives notifications for overdue todos"
    ]
  }' | jq '.'

echo ""
echo "✓ Projeto criado!"
echo ""

# Aguardar um pouco
sleep 2

# 3. Executar primeira sessão
echo "3️⃣ Executando primeira sessão de coding..."
curl -s -X POST http://localhost/api/agent/projects/1/session | jq '.'
echo ""

# 4. Ver status
echo "4️⃣ Verificando status do projeto..."
curl -s http://localhost/api/agent/projects/1/status | jq '.'
echo ""

# 5. Executar mais sessões
echo "5️⃣ Executando mais 3 sessões..."
for i in {1..3}; do
  echo "   Sessão #$((i+1))..."
  curl -s -X POST http://localhost/api/agent/projects/1/session | jq -c '.data | {feature: .feature_worked_on, completed: .feature_completed, tests: .tests_passed}'
  sleep 1
done
echo ""

# 6. Status final
echo "6️⃣ Status final do projeto:"
curl -s http://localhost/api/agent/projects/1/status | jq '.data | {completion: .completion_percentage, completed: .completed_features, pending: .pending_features, sessions: .sessions_count}'
echo ""

# 7. Verificar arquivos criados
echo "7️⃣ Arquivos criados no projeto:"
PROJECT_DIR="storage/agent_projects/1"
if [ -d "$PROJECT_DIR" ]; then
  echo "   📁 $PROJECT_DIR/"
  ls -la "$PROJECT_DIR" | tail -n +4 | awk '{print "      " $9}'
  
  echo ""
  echo "   📄 feature_list.json (primeiras 20 linhas):"
  if [ -f "$PROJECT_DIR/feature_list.json" ]; then
    head -n 20 "$PROJECT_DIR/feature_list.json" | sed 's/^/      /'
  fi
  
  echo ""
  echo "   📝 claude-progress.txt (últimas 30 linhas):"
  if [ -f "$PROJECT_DIR/claude-progress.txt" ]; then
    tail -n 30 "$PROJECT_DIR/claude-progress.txt" | sed 's/^/      /'
  fi
else
  echo "   ⚠️  Diretório do projeto não encontrado"
fi

echo ""
echo "✅ Demo completo!"
echo ""
echo "📚 Próximos passos:"
echo "   - Ver documentação: docs/LONG_RUNNING_AGENTS.md"
echo "   - Continuar sessões: curl -X POST http://localhost/api/agent/projects/1/session"
echo "   - Testar feature: curl -X POST http://localhost/api/agent/projects/1/test -d '{\"feature_id\":\"F1\"}'"
echo ""
