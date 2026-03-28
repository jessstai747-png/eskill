#!/bin/bash
#
# Setup Script - Prepara ambiente para validação de produção
# Executa todas as instalações necessárias
#

set -e

echo "🚀 Preparando ambiente para validação de produção..."
echo ""

# 1. Tornar scripts executáveis
echo "📝 1/4 - Tornando scripts executáveis..."
chmod +x run-prod-validation.sh
chmod +x quick-prod-validation.sh
chmod +x prod-validation.py
echo "   ✓ Scripts prontos"

# 2. Verificar Node.js instalado
echo ""
echo "🔍 2/4 - Verificando Node.js..."
if command -v node &> /dev/null; then
    NODE_VERSION=$(node --version)
    echo "   ✓ Node.js instalado: $NODE_VERSION"
else
    echo "   ✗ Node.js não encontrado"
    echo "   Instale: https://nodejs.org/"
    exit 1
fi

# 3. Instalar dependências npm
echo ""
echo "📦 3/4 - Instalando dependências npm..."
if [ ! -d "node_modules/@playwright/test" ]; then
    npm install
    echo "   ✓ Dependências instaladas"
else
    echo "   ✓ Dependências já instaladas"
fi

# 4. Instalar browsers do Playwright
echo ""
echo "🌐 4/4 - Instalando browsers do Playwright..."
if [ ! -d "$HOME/.cache/ms-playwright" ]; then
    npx playwright install chromium --with-deps
    echo "   ✓ Chromium instalado"
else
    echo "   ✓ Chromium já instalado"
fi

# 5. Criar diretório de screenshots
echo ""
echo "📁 Criando diretório de screenshots..."
mkdir -p storage/playwright-screenshots
echo "   ✓ Diretório criado"

# 6. Verificar Python (opcional)
echo ""
echo "🐍 Verificando Python (opcional)..."
if command -v python3 &> /dev/null; then
    PYTHON_VERSION=$(python3 --version)
    echo "   ✓ Python instalado: $PYTHON_VERSION"

    # Verificar pip
    if command -v pip3 &> /dev/null; then
        echo "   Instalando bibliotecas Python..."
        pip3 install --quiet requests beautifulsoup4 2>/dev/null || echo "   ⚠ Falha ao instalar (não crítico)"
    fi
else
    echo "   ⚠ Python3 não encontrado (opcional - script Python não funcionará)"
fi

echo ""
echo "╔════════════════════════════════════════════════╗"
echo "║  ✅ Setup Completo! Ambiente Pronto           ║"
echo "╚════════════════════════════════════════════════╝"
echo ""
echo "📚 Próximos passos:"
echo ""
echo "   1. Execute a validação:"
echo "      ./run-prod-validation.sh seu@email.com suasenha"
echo ""
echo "   2. OU validação rápida:"
echo "      ./quick-prod-validation.sh seu@email.com suasenha"
echo ""
echo "   3. Ver resultados:"
echo "      ls -lh storage/playwright-screenshots/"
echo "      npx playwright show-report"
echo ""
echo "📖 Documentação:"
echo "   - QUICKSTART_PROD_VALIDATION.md"
echo "   - PRODUCTION_VALIDATION_REPORT.md"
echo ""
