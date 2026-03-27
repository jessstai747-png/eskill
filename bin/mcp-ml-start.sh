#!/bin/bash
#
# Wrapper para iniciar o MCP Server do Mercado Livre com token real do banco de dados.
#
# Fluxo:
# 1. Busca o access_token ativo da tabela ml_accounts (descriptografa se necessário)
# 2. Inicia o mcp-remote apontando para https://mcp.mercadolibre.com/mcp
# 3. O VS Code se comunica via stdio com o MCP Server
#
# Uso manual: ./bin/mcp-ml-start.sh [--account-id=N]
#

set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Buscar token do banco de dados
TOKEN=$(php "$SCRIPT_DIR/mcp-ml-token.php" --format=bearer "$@" 2>/dev/null)
EXIT_CODE=$?

if [ $EXIT_CODE -ne 0 ] || [ -z "$TOKEN" ]; then
    # Fallback: tentar variável de ambiente
    if [ -n "${ML_MCP_ACCESS_TOKEN:-}" ]; then
        TOKEN="Bearer $ML_MCP_ACCESS_TOKEN"
        echo "[MCP-ML] Usando token da variável ML_MCP_ACCESS_TOKEN" >&2
    else
        echo "[MCP-ML] ERRO: Nenhum token válido encontrado." >&2
        echo "[MCP-ML] Opções:" >&2
        echo "[MCP-ML]   1. Conecte uma conta ML via OAuth: https://eskill.com.br/auth/authorize" >&2
        echo "[MCP-ML]   2. Defina ML_MCP_ACCESS_TOKEN no ambiente" >&2
        exit 1
    fi
fi

# Executar mcp-remote com o token
exec npx -y mcp-remote@0.1.38 \
    "https://mcp.mercadolibre.com/mcp" \
    --header "Authorization:${TOKEN}"
