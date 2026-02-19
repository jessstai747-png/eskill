#!/bin/bash
# ============================================================
# 🚀 Setup — Copilot Agent Autônomo
# ============================================================
# Uso: bash setup-copilot.sh /caminho/do/seu/projeto
# 
# Este script copia todos os arquivos de configuração do Copilot
# para o seu projeto. Execute uma vez por workspace.
# ============================================================

set -e

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Verificar argumento
if [ -z "$1" ]; then
    echo -e "${RED}❌ Erro: Informe o caminho do projeto${NC}"
    echo "Uso: bash setup-copilot.sh /caminho/do/seu/projeto"
    exit 1
fi

PROJECT_DIR="$1"
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"

if [ ! -d "$PROJECT_DIR" ]; then
    echo -e "${RED}❌ Erro: Diretório '$PROJECT_DIR' não existe${NC}"
    exit 1
fi

echo -e "${BLUE}🚀 Configurando Copilot Agent Autônomo${NC}"
echo -e "${BLUE}   Projeto: $PROJECT_DIR${NC}"
echo ""

# Criar estrutura de pastas
echo -e "${YELLOW}📁 Criando estrutura de pastas...${NC}"
mkdir -p "$PROJECT_DIR/.github/agents"
mkdir -p "$PROJECT_DIR/.github/instructions"
mkdir -p "$PROJECT_DIR/.github/prompts"

# Copiar AGENTS.md
echo -e "${GREEN}✅ AGENTS.md${NC}"
cp "$SCRIPT_DIR/AGENTS.md" "$PROJECT_DIR/AGENTS.md"

# Copiar copilot-instructions.md
echo -e "${GREEN}✅ .github/copilot-instructions.md${NC}"
cp "$SCRIPT_DIR/.github/copilot-instructions.md" "$PROJECT_DIR/.github/copilot-instructions.md"

# Copiar agents
echo -e "${GREEN}✅ .github/agents/ (5 agents)${NC}"
cp "$SCRIPT_DIR/.github/agents/"*.agent.md "$PROJECT_DIR/.github/agents/"

# Copiar instructions
echo -e "${GREEN}✅ .github/instructions/ (4 instruction files)${NC}"
cp "$SCRIPT_DIR/.github/instructions/"*.instructions.md "$PROJECT_DIR/.github/instructions/"

# Copiar prompts
echo -e "${GREEN}✅ .github/prompts/ (6 prompt files)${NC}"
cp "$SCRIPT_DIR/.github/prompts/"*.prompt.md "$PROJECT_DIR/.github/prompts/"

echo ""
echo -e "${BLUE}============================================================${NC}"
echo -e "${GREEN}✅ Setup completo!${NC}"
echo ""
echo -e "${YELLOW}📋 Arquivos instalados:${NC}"
echo ""
echo "  AGENTS.md                              → Instruções universais para todos os agents"
echo "  .github/copilot-instructions.md        → Instruções globais do Copilot"
echo ""
echo "  .github/agents/implementador.agent.md  → Agent que implementa código real"
echo "  .github/agents/revisor.agent.md        → Agent que revisa código"
echo "  .github/agents/arquiteto.agent.md      → Agent que planeja antes de codar"
echo "  .github/agents/debugger.agent.md       → Agent que diagnostica e corrige bugs"
echo "  .github/agents/mercadolivre.agent.md   → Agent especialista em Mercado Livre"
echo ""
echo "  .github/instructions/react-*.md        → Regras para componentes React"
echo "  .github/instructions/services-*.md     → Regras para services/API"
echo "  .github/instructions/tests.*.md        → Regras para testes"
echo "  .github/instructions/prisma-*.md       → Regras para Prisma/DB"
echo ""
echo "  .github/prompts/implementar-api.md     → /implementar-api"
echo "  .github/prompts/criar-crud.md          → /criar-crud"
echo "  .github/prompts/criar-componente.md    → /criar-componente"
echo "  .github/prompts/refatorar.md           → /refatorar"
echo "  .github/prompts/corrigir-bug.md        → /corrigir-bug"
echo "  .github/prompts/auditar-projeto.md     → /auditar-projeto"
echo "  .github/prompts/otimizar-anuncio-ml.md → /otimizar-anuncio-ml"
echo ""
echo -e "${YELLOW}⚠️  PRÓXIMOS PASSOS:${NC}"
echo ""
echo "  1. Copie as settings do arquivo settings.json para seu VS Code"
echo "     (Ctrl+Shift+P → 'Open User Settings JSON')"
echo ""
echo "  2. Personalize o .github/copilot-instructions.md para seu projeto específico"
echo ""
echo "  3. Recarregue o VS Code (Ctrl+Shift+P → 'Reload Window')"
echo ""
echo "  4. No Chat, use os slash commands: /implementar-api, /criar-crud, etc."
echo ""
echo "  5. Para alternar entre agents, use /agents no chat"
echo ""
echo -e "${BLUE}============================================================${NC}"
