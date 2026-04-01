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
TOKEN_ERROR_FILE="$(mktemp "${TMPDIR:-/tmp}/mcp-ml-token.XXXXXX")"
TOKEN_SOURCE="database"

cleanup() {
    rm -f "$TOKEN_ERROR_FILE"
}

trap cleanup EXIT

normalize_token() {
    local raw_token="$1"

    raw_token="${raw_token#Bearer }"
    raw_token="${raw_token#bearer }"

    printf '%s' "$raw_token"
}

is_token_format_valid() {
    local raw_token="$1"

    if [ -z "$raw_token" ]; then
        return 1
    fi

    case "$raw_token" in
        APP_USR-*)
            [ "${#raw_token}" -ge 32 ]
            return
            ;;
        *)
            return 1
            ;;
    esac
}

validate_token_against_api() {
    local raw_token="$1"
    local http_code

    if ! command -v curl >/dev/null 2>&1; then
        return 2
    fi

    http_code=$(curl -sS -o /dev/null -w '%{http_code}' \
        --connect-timeout 5 \
        --max-time 15 \
        -H "Authorization: Bearer ${raw_token}" \
        https://api.mercadolibre.com/users/me 2>/dev/null || printf '000')

    case "$http_code" in
        200)
            return 0
            ;;
        401|403)
            return 1
            ;;
        *)
            return 2
            ;;
    esac
}

# Buscar token do banco de dados
set +e
TOKEN=$(php "$SCRIPT_DIR/mcp-ml-token.php" --format=bearer "$@" 2>"$TOKEN_ERROR_FILE")
EXIT_CODE=$?
set -e

if [ -s "$TOKEN_ERROR_FILE" ]; then
    cat "$TOKEN_ERROR_FILE" >&2
fi

if [ $EXIT_CODE -ne 0 ] || [ -z "$TOKEN" ]; then
    # Fallback: tentar variável de ambiente
    if [ -n "${ML_MCP_ACCESS_TOKEN:-}" ]; then
        TOKEN_SOURCE="environment"
        TOKEN_RAW="$(normalize_token "$ML_MCP_ACCESS_TOKEN")"
        TOKEN="Bearer ${TOKEN_RAW}"
        echo "[MCP-ML] Usando token da variável ML_MCP_ACCESS_TOKEN" >&2
    else
        echo "[MCP-ML] ERRO: Nenhum token válido encontrado." >&2
        echo "[MCP-ML] Opções:" >&2
        echo "[MCP-ML]   1. Conecte uma conta ML via OAuth: https://eskill.com.br/auth/authorize" >&2
        echo "[MCP-ML]   2. Defina ML_MCP_ACCESS_TOKEN no ambiente" >&2
        exit 1
    fi
fi

TOKEN_RAW="$(normalize_token "${TOKEN#Bearer }")"

if ! is_token_format_valid "$TOKEN_RAW"; then
    echo "[MCP-ML] ERRO: O token obtido via ${TOKEN_SOURCE} não parece ser um access token válido do Mercado Livre." >&2
    echo "[MCP-ML] Um token válido normalmente começa com 'APP_USR-' e deve ser informado completo." >&2
    echo "[MCP-ML] Corrija ML_MCP_ACCESS_TOKEN ou reconecte a conta ML para gerar um novo access_token." >&2
    exit 1
fi

set +e
validate_token_against_api "$TOKEN_RAW"
TOKEN_VALIDATION_STATUS=$?
set -e

if [ "$TOKEN_VALIDATION_STATUS" -eq 1 ]; then
    echo "[MCP-ML] ERRO: O token obtido via ${TOKEN_SOURCE} foi rejeitado pela API do Mercado Livre (401/403)." >&2
    echo "[MCP-ML] Renove o access_token e tente novamente." >&2
    echo "[MCP-ML] Dicas:" >&2
    echo "[MCP-ML]   - Reconecte a conta em https://eskill.com.br/auth/authorize" >&2
    echo "[MCP-ML]   - Ou informe um access token real em ML_MCP_ACCESS_TOKEN" >&2
    exit 1
fi

if [ "$TOKEN_VALIDATION_STATUS" -eq 2 ]; then
    echo "[MCP-ML] AVISO: Não foi possível validar o token previamente via /users/me; continuando com mcp-remote." >&2
fi

# Executar mcp-remote com o token
if ! command -v npx >/dev/null 2>&1; then
    echo "[MCP-ML] ERRO: npx não encontrado no ambiente." >&2
    echo "[MCP-ML] Instale Node.js/npm ou execute o MCP em um ambiente que tenha npx disponível." >&2
    exit 1
fi

exec npx -y mcp-remote@0.1.38 \
    "https://mcp.mercadolibre.com/mcp" \
    --header "Authorization:${TOKEN}"
