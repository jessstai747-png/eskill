#!/bin/bash
set -e

# Script para commit dos arquivos de análise de qualidade Mercado Livre
# Autor: GitHub Copilot
# Data: 2026-03-27

echo "╔════════════════════════════════════════════════════════════════╗"
echo "║  Git Commit - Mercado Livre Quality Analysis Implementation   ║"
echo "╚════════════════════════════════════════════════════════════════╝"
echo ""

# Verificar se estamos em um repositório git
if [ ! -d ".git" ]; then
    echo "❌ ERRO: Este diretório não é um repositório git"
    exit 1
fi

# Lista de arquivos a serem comitados
FILES=(
    "analyze-mercadolivre-services.sh"
    "MERCADOLIVRE_QUALITY_GUIDE.md"
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
missing_files=()
for file in "${FILES[@]}"; do
    if [ ! -f "$file" ]; then
        missing_files+=("$file")
    fi
done

if [ ${#missing_files[@]} -gt 0 ]; then
    echo "❌ ERRO: Alguns arquivos não foram encontrados:"
    for file in "${missing_files[@]}"; do
        echo "   - $file"
    done
    echo ""
    echo "Abortando commit."
    exit 1
fi

# Mensagem de commit
COMMIT_MSG="feat(quality): add Mercado Livre quality analysis infrastructure

- analyze-mercadolivre-services.sh: automated analysis for 21 ML PHP files
  * Core: MercadoLivreClient.php, MercadoLivreAuthService.php, MercadoLivreOrchestratorService.php
  * Specialized: CategoriesApiService, AdvancedPricingEngine, MLAnalyticsIntelligenceService, SmartQAService, CompetitorIntelligenceService, SEOMetricsCollectorService, MercadoLivreAIIntegrationService, MLAdsAdvancedService, StockSyncService, MLResilienceHelper, AccountGovernanceIntegrationService
  * DTOs/Contracts: CategoryChildDTO, CategoryDetailDTO, CategoryNodeDTO, CategoriesApiGatewayInterface
  * PHP-specific tools: phpcs (PSR compliance), phpmd (code quality), trivy (security)
  * JSON reports in storage/codacy-analysis/mercadolivre/
  * Issue counting by severity with colored output
  * Exit code 1 if critical issues found

- MERCADOLIVRE_QUALITY_GUIDE.md: comprehensive ML quality analysis guide
  * Architecture overview: 21 files in 3 tiers (core/specialized/DTOs)
  * Core patterns: OAuth 2.0, circuit breaker, exponential backoff retry, rate limiting
  * Quality checklist: 27 checks (Code Quality, Security, Performance, ML API Best Practices)
  * Troubleshooting guide (phpcs not found, CLI missing, vulnerability remediation)
  * References QUALITY_ANALYSIS.md for MCP troubleshooting

- Updated tracking files:
  * IMPLEMENTATION_SUMMARY.md: added ML analysis files to Quality Analysis section
  * claude-progress.txt: new session entry documenting ML discovery and analysis
  * project-status.json: new feature PROD-002 for ML quality analysis

Context: User requested 'continue utilize mcp ML' - applied MCP quality analysis
approach specifically to Mercado Livre integration services. No ML-specific MCP
tools available, created specialized analysis leveraging existing Codacy CLI/.codacy/cli.sh
wrapper. Focus on production PHP code (21 files) vs validation scripts (6 files),
with emphasis on OAuth security, circuit breaker patterns, rate limiting, and ML API
integration best practices.

Ready for execution: chmod +x analyze-mercadolivre-services.sh && ./analyze-mercadolivre-services.sh"

echo "📝 Mensagem de commit:"
echo "────────────────────────────────────────────────────────────────"
echo "$COMMIT_MSG"
echo "────────────────────────────────────────────────────────────────"
echo ""

# Confirmação do usuário
read -p "Deseja prosseguir com o commit? (y/n): " -n 1 -r
echo ""

if [[ ! $REPLY =~ ^[Yy]$ ]]; then
    echo "❌ Commit cancelado pelo usuário"
    exit 1
fi

# Adicionar arquivos ao staging
echo ""
echo "📦 Adicionando arquivos ao staging..."
for file in "${FILES[@]}"; do
    git add "$file"
    echo "  ✓ git add $file"
done

# Verificar status
echo ""
echo "📊 Status do repositório:"
git status --short

# Fazer commit
echo ""
echo "💾 Realizando commit..."
git commit -m "$COMMIT_MSG"

echo ""
echo "✅ Commit realizado com sucesso!"
echo ""
echo "📌 Próximos passos:"
echo "   1. git push origin main (ou sua branch atual)"
echo "   2. chmod +x analyze-mercadolivre-services.sh"
echo "   3. ./analyze-mercadolivre-services.sh"
echo "   4. Revisar relatórios em storage/codacy-analysis/mercadolivre/"
echo ""
