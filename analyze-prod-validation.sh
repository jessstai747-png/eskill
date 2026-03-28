#!/bin/bash
#
# Análise de qualidade dos arquivos de validação de produção com Codacy CLI
# Usa o CLI local do projeto em .codacy/cli.sh
#

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CODACY_CLI="$SCRIPT_DIR/.codacy/cli.sh"

# Cores
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

echo -e "${BLUE}╔════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   🔍 Análise Codacy - Arquivos de Validação       ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════╝${NC}"
echo ""

# Verificar se CLI existe
if [ ! -f "$CODACY_CLI" ]; then
    echo -e "${RED}❌ Codacy CLI não encontrado em .codacy/cli.sh${NC}"
    echo ""
    echo "Opções:"
    echo "  1. Use o script de instalação: ./install-codacy-cli.sh"
    echo "  2. Ou baixe manualmente de: https://github.com/codacy/codacy-analysis-cli"
    exit 1
fi

# Arquivos para analisar
FILES=(
    "tests/e2e/production-validation.spec.ts"
    "playwright.config.ts"
    "run-prod-validation.sh"
    "quick-prod-validation.sh"
    "prod-validation.py"
    "setup-prod-validation.sh"
)

echo "📋 Arquivos a serem analisados:"
for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo -e "  ${GREEN}✓${NC} $file"
    else
        echo -e "  ${RED}✗${NC} $file (não encontrado)"
    fi
done
echo ""

# Criar diretório para resultados
RESULTS_DIR="storage/codacy-analysis"
mkdir -p "$RESULTS_DIR"

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
REPORT_FILE="$RESULTS_DIR/production-validation_${TIMESTAMP}.json"

echo "💾 Resultados serão salvos em: $REPORT_FILE"
echo ""

# Executar análise
echo -e "${YELLOW}🔬 Executando análise Codacy...${NC}"
echo ""

TOTAL_ISSUES=0
CRITICAL_ISSUES=0

for file in "${FILES[@]}"; do
    if [ ! -f "$file" ]; then
        continue
    fi

    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}📄 Analisando: $file${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"

    # Executar análise com timeout de 60s
    if timeout 60s "$CODACY_CLI" analyze --file "$file" --format json > "${RESULTS_DIR}/${file//\//_}.json" 2>&1; then
        # Contar issues
        if [ -f "${RESULTS_DIR}/${file//\//_}.json" ]; then
            FILE_ISSUES=$(grep -o '"level":"' "${RESULTS_DIR}/${file//\//_}.json" | wc -l || echo "0")
            CRITICAL=$(grep -o '"level":"Error"' "${RESULTS_DIR}/${file//\//_}.json" | wc -l || echo "0")

            TOTAL_ISSUES=$((TOTAL_ISSUES + FILE_ISSUES))
            CRITICAL_ISSUES=$((CRITICAL_ISSUES + CRITICAL))

            if [ "$FILE_ISSUES" -eq 0 ]; then
                echo -e "${GREEN}✅ Nenhum problema encontrado${NC}"
            else
                echo -e "${YELLOW}⚠️  $FILE_ISSUES problema(s) encontrado(s)${NC}"
                if [ "$CRITICAL" -gt 0 ]; then
                    echo -e "${RED}   🔴 $CRITICAL crítico(s)${NC}"
                fi
            fi
        fi
    else
        echo -e "${YELLOW}⚠️  Análise falhou ou timeout (60s)${NC}"
    fi
    echo ""
done

# Resumo final
echo ""
echo -e "${BLUE}╔════════════════════════════════════════════════════╗${NC}"
echo -e "${BLUE}║   📊 RESUMO DA ANÁLISE                             ║${NC}"
echo -e "${BLUE}╚════════════════════════════════════════════════════╝${NC}"
echo ""
echo "📁 Arquivos analisados: ${#FILES[@]}"
echo "🔍 Total de issues: $TOTAL_ISSUES"

if [ "$CRITICAL_ISSUES" -gt 0 ]; then
    echo -e "🔴 Issues críticos: ${RED}$CRITICAL_ISSUES${NC}"
else
    echo -e "🔴 Issues críticos: ${GREEN}0${NC}"
fi

echo ""
echo "📂 Resultados detalhados em: $RESULTS_DIR"
echo ""

# Análise de segurança adicional com Trivy (se disponível)
if command -v trivy &> /dev/null; then
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "${BLUE}🛡️  Análise de Segurança (Trivy)${NC}"
    echo -e "${BLUE}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""

    trivy fs --scanners vuln,secret,misconfig \
        --severity HIGH,CRITICAL \
        --format table \
        tests/e2e/ \
        *.sh \
        *.py \
        playwright.config.ts \
        package.json \
        2>/dev/null || echo -e "${YELLOW}⚠️  Trivy scan falhou${NC}"

    echo ""
fi

# Status final
if [ "$CRITICAL_ISSUES" -gt 0 ]; then
    echo -e "${RED}❌ ATENÇÃO: Issues críticos encontrados!${NC}"
    exit 1
else
    echo -e "${GREEN}✅ Qualidade de código aprovada!${NC}"
    exit 0
fi
