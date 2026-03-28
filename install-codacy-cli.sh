#!/bin/bash
#
# Install Codacy CLI - Ferramenta de análise de código estática
# https://github.com/codacy/codacy-analysis-cli
#

set -e

echo "╔════════════════════════════════════════════════════╗"
echo "║   📦 Instalando Codacy CLI                         ║"
echo "╚════════════════════════════════════════════════════╝"
echo ""

# Detectar arquitetura e OS
ARCH=$(uname -m)
OS=$(uname -s | tr '[:upper:]' '[:lower:]')
echo "🔍 Sistema detectado: $OS / $ARCH"

TMP_FILE="/tmp/codacy-cli-$$"

# ──────────────────────────────────────────────
# Estratégia 1: Usar GitHub API para pegar URL correta do release
# ──────────────────────────────────────────────
install_via_github_release() {
    echo ""
    echo "📦 Estratégia 1: GitHub Releases API..."

    local ASSET_NAME="codacy-analysis-cli-linux-${ARCH}"
    local API_URL="https://api.github.com/repos/codacy/codacy-analysis-cli/releases/latest"

    # Buscar URL do asset via API
    local DOWNLOAD_URL
    if command -v curl &> /dev/null; then
        DOWNLOAD_URL=$(curl -sL \
            -H "Accept: application/vnd.github+json" \
            "$API_URL" \
            | grep -o '"browser_download_url": *"[^"]*'"$ASSET_NAME"'[^"]*"' \
            | head -1 \
            | sed 's/.*"browser_download_url": *"\([^"]*\)".*/\1/')
    fi

    if [ -z "$DOWNLOAD_URL" ]; then
        echo "⚠️  Não foi possível obter URL via API. Tentando URL direta..."
        # Tenta versão conhecida estável
        DOWNLOAD_URL="https://github.com/codacy/codacy-analysis-cli/releases/download/7.9.6/${ASSET_NAME}"
    fi

    echo "   URL: $DOWNLOAD_URL"
    echo ""

    if command -v curl &> /dev/null; then
        curl -L --fail -o "$TMP_FILE" "$DOWNLOAD_URL" 2>&1
    elif command -v wget &> /dev/null; then
        wget -O "$TMP_FILE" "$DOWNLOAD_URL"
    else
        echo "❌ Erro: curl ou wget não encontrado"
        return 1
    fi

    # Verificar que baixou algo maior que 1KB (não uma página de erro)
    local SIZE
    SIZE=$(wc -c < "$TMP_FILE" 2>/dev/null || echo 0)
    if [ "$SIZE" -lt 1024 ]; then
        echo "❌ Arquivo baixado muito pequeno ($SIZE bytes) — URL inválida ou bloqueada"
        rm -f "$TMP_FILE"
        return 1
    fi

    chmod +x "$TMP_FILE"

    echo "🧪 Testando binário..."
    if "$TMP_FILE" --version &> /dev/null; then
        echo "✅ Codacy CLI funciona!"
        return 0
    else
        echo "❌ Binário não funciona"
        rm -f "$TMP_FILE"
        return 1
    fi
}

# ──────────────────────────────────────────────
# Estratégia 2: Docker wrapper (se Docker disponível)
# ──────────────────────────────────────────────
install_via_docker() {
    echo ""
    echo "🐳 Estratégia 2: Docker wrapper..."

    if ! command -v docker &> /dev/null; then
        echo "❌ Docker não encontrado"
        return 1
    fi

    # Pull da imagem para verificar disponibilidade
    if ! docker pull codacy/codacy-analysis-cli:latest --quiet 2>/dev/null; then
        echo "❌ Não foi possível baixar a imagem Docker"
        return 1
    fi

    # Criar wrapper script
    cat > "$TMP_FILE" << 'DOCKER_WRAPPER'
#!/bin/bash
# Codacy CLI via Docker
docker run --rm \
    -v "$(pwd):/code" \
    -v "/var/run/docker.sock:/var/run/docker.sock" \
    codacy/codacy-analysis-cli:latest "$@"
DOCKER_WRAPPER

    chmod +x "$TMP_FILE"
    echo "✅ Docker wrapper criado!"
    return 0
}

# ──────────────────────────────────────────────
# Tentar estratégias em ordem
# ──────────────────────────────────────────────
INSTALL_PATH="/usr/local/bin/codacy"

if install_via_github_release; then
    echo "✅ Instalação via GitHub Release bem-sucedida"
elif install_via_docker; then
    echo "✅ Instalação via Docker bem-sucedida"
else
    echo ""
    echo "❌ Todas as estratégias de instalação falharam."
    echo ""
    echo "💡 Alternativas manuais:"
    echo "   1. Verifique conectividade: curl -I https://github.com/codacy/codacy-analysis-cli/releases"
    echo "   2. Baixe manualmente de: https://github.com/codacy/codacy-analysis-cli/releases"
    echo "   3. Use Docker: docker run --rm codacy/codacy-analysis-cli:latest --version"
    echo ""
    echo "   O MCP Codacy funciona sem o CLI — apenas codacy_cli_analyze ficará indisponível."
    exit 1
fi

# Instalar em ~/.local/bin (não requer sudo — adequado para ambiente VS Code)
INSTALL_PATH="$HOME/.local/bin/codacy"
mkdir -p "$HOME/.local/bin"
mv "$TMP_FILE" "$INSTALL_PATH"
chmod +x "$INSTALL_PATH"
INSTALLED_PATH="$INSTALL_PATH"

if [[ ":$PATH:" != *":$HOME/.local/bin:"* ]]; then
    echo ""
    echo "⚠️  IMPORTANTE: Adicione ao seu PATH:"
    echo "   export PATH=\"\$HOME/.local/bin:\$PATH\""
    echo ""
    echo "   Adicione ao ~/.bashrc ou ~/.zshrc para permanência"
fi
echo "✅ Codacy CLI instalado com sucesso!"
echo ""
echo "📋 Informações:"
"$INSTALLED_PATH" --version
echo ""
echo "📍 Localização: $INSTALLED_PATH"
echo ""
echo "🚀 Próximos passos:"
echo "   1. Analise um arquivo:"
echo "      codacy analyze --file tests/e2e/production-validation.spec.ts"
echo ""
echo "   2. Analise o projeto completo:"
echo "      codacy analyze --directory ."
echo ""
echo "   3. Use com configuração do projeto:"
echo "      codacy analyze --directory . --config .codacy.yml"
echo ""
