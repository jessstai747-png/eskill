#!/bin/bash
# commit-prod-validation.sh — Git commit dos arquivos de validação de produção

set -e

echo "╔════════════════════════════════════════════════════╗"
echo "║   📦 Git Commit - Validação de Produção E2E        ║"
echo "╚════════════════════════════════════════════════════╝"
echo ""

# Verificar se estamos em um repositório git
if [ ! -d .git ]; then
    echo "❌ Erro: Não está em um repositório git"
    exit 1
fi

echo "📋 Arquivos criados/modificados:"
echo ""
echo "  ✅ tests/e2e/production-validation.spec.ts"
echo "  ✅ playwright.config.ts"
echo "  ✅ package.json"
echo "  ✅ run-prod-validation.sh"
echo "  ✅ quick-prod-validation.sh"
echo "  ✅ prod-validation.py"
echo "  ✅ setup-prod-validation.sh"
echo "  ✅ IMPLEMENTATION_SUMMARY.md"
echo "  ✅ PRODUCTION_VALIDATION_REPORT.md"
echo "  ✅ PRODUCTION_VALIDATION_GUIDE.md"
echo "  ✅ QUICKSTART_PROD_VALIDATION.md"
echo "  ✅ claude-progress.txt"
echo "  ✅ project-status.json"
echo "  ✅ commit-prod-validation.sh (este arquivo)"
echo ""

# Fazer git add de todos os arquivos
echo "📦 Adicionando arquivos ao git..."
git add tests/e2e/production-validation.spec.ts \
        playwright.config.ts \
        package.json \
        run-prod-validation.sh \
        quick-prod-validation.sh \
        prod-validation.py \
        setup-prod-validation.sh \
        IMPLEMENTATION_SUMMARY.md \
        PRODUCTION_VALIDATION_REPORT.md \
        PRODUCTION_VALIDATION_GUIDE.md \
        QUICKSTART_PROD_VALIDATION.md \
        claude-progress.txt \
        project-status.json \
        commit-prod-validation.sh

echo "✅ Arquivos adicionados ao staging"
echo ""

# Fazer commit
echo "💾 Criando commit..."
git commit -m "feat(testing): Implementa suite completa de validação E2E de produção

- Testes Playwright E2E para https://eskill.com.br (tests/e2e/production-validation.spec.ts)
- Inspeção completa de página de login (campos, CSRF tokens, cookies)
- Autenticação com credenciais reais e redirecionamento para dashboard
- Smoke test de 12 rotas do dashboard com captura de screenshots
- Monitoramento de console errors e network failures (XHR/Fetch 4xx/5xx)
- Teste diagnóstico de API /api/auth/login como fallback
- Scripts alternativos: curl (quick-prod-validation.sh), Python (prod-validation.py)
- Setup automatizado (setup-prod-validation.sh)
- Configuração --no-sandbox para execução como root (playwright.config.ts)
- Documentação completa (IMPLEMENTATION_SUMMARY.md + guides)
- Adicionado @types/node ao package.json para suporte TypeScript

Closes: PROD-001

Co-authored-by: GitHub Copilot <copilot@github.com>"

echo ""
echo "✅ Commit criado com sucesso!"
echo ""
echo "📊 Log do commit:"
git log --oneline -1
echo ""
echo "🎯 Próximo passo: git push origin main"
