#!/bin/bash
set -e

# Script para commit dos arquivos de análise de qualidade (MCP Phase)
# Autor: GitHub Copilot
# Data: 2026-03-23

echo "╔════════════════════════════════════════════════════════╗"
echo "║  Git Commit - Quality Analysis Implementation (MCP)   ║"
echo "╚════════════════════════════════════════════════════════╝"
echo ""

# Verificar se estamos em um repositório git
if [ ! -d ".git" ]; then
    echo "❌ ERRO: Este diretório não é um repositório git"
    exit 1
fi

# Lista de arquivos a serem comitados
FILES=(
    "install-codacy-cli.sh"
    "analyze-prod-validation.sh"
    "QUALITY_ANALYSIS.md"
    "IMPLEMENTATION_SUMMARY.md"
    "claude-progress.txt"
    "project-status.json"
)

echo "📋 Arquivos a serem comitados:"
echo ""
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "  ✓ $file"
    else
        echo "  ✗ $file (NÃO ENCONTRADO)"
    fi
done
echo ""

# Verificar se há arquivos não encontrados
missing_count=0
for file in "${FILES[@]}"; do
    if [ ! -f "$file" ]; then
        ((missing_count++))
    fi
done

if [ $missing_count -gt 0 ]; then
    echo "⚠️  AVISO: $missing_count arquivo(s) não encontrado(s)"
    read -p "Deseja continuar mesmo assim? (y/N): " -n 1 -r
    echo ""
    if [[ ! $REPLY =~ ^[Yy]$ ]]; then
        echo "❌ Commit cancelado"
        exit 1
    fi
fi

# Mensagem de commit
COMMIT_MSG="feat(quality): add Codacy CLI integration and quality analysis tools

- Add install-codacy-cli.sh: interactive Codacy CLI installer with 3 installation options
- Add analyze-prod-validation.sh: automated analysis using .codacy/cli.sh wrapper
- Add QUALITY_ANALYSIS.md: comprehensive guide on MCP, Codacy, and manual analysis
- Update IMPLEMENTATION_SUMMARY.md: include quality analysis section and files

Features:
- MCP (Model Context Protocol) integration attempt documented
- Fallback to manual Codacy CLI installation
- Leverages existing .codacy/cli.sh infrastructure
- Automated analysis for all validation files
- Security scanning with Trivy
- ESLint, ShellCheck, Pylint integration
- Issue counting and severity classification
- CI/CD integration guide

Related to production validation suite implementation
Complements PROD-001 feature in project-status.json

Co-authored-by: Jess <jess@awamotos.com.br>"

echo "📝 Mensagem do commit:"
echo "────────────────────────────────────────────────────────"
echo "$COMMIT_MSG"
echo "────────────────────────────────────────────────────────"
echo ""

# Confirmar commit
read -p "Deseja prosseguir com o commit? (y/N): " -n 1 -r
echo ""
if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "❌ Commit cancelado"
    exit 1
fi

# Adicionar arquivos ao stage
echo "📦 Adicionando arquivos ao stage..."
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        git add "$file"
        echo "  ✓ git add $file"
    fi
done
echo ""

# Fazer commit
echo "💾 Criando commit..."
git commit -m "$COMMIT_MSG"

if [ $? -eq 0 ]; then
    echo ""
    echo "✅ COMMIT CRIADO COM SUCESSO!"
    echo ""
    echo "📊 Status do repositório:"
    git status
    echo ""
    echo "📜 Último commit:"
    git log -1 --oneline
    echo ""
    echo "🚀 Próximo passo:"
    echo "   git push origin main"
else
    echo "❌ ERRO ao criar commit"
    exit 1
fi
