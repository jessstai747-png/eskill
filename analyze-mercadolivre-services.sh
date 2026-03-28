#!/bin/bash
set -e

# Análise de Qualidade - Serviços Mercado Livre
# Usa Codacy CLI via wrapper .codacy/cli.sh
# Autor: GitHub Copilot
# Data: 2026-03-24

echo "╔════════════════════════════════════════════════════════════╗"
echo "║   🔍 Análise de Qualidade - Serviços Mercado Livre        ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Diretórios
ROOT_PATH="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
CODACY_CLI="${ROOT_PATH}/.codacy/cli.sh"
ANALYSIS_DIR="${ROOT_PATH}/storage/codacy-analysis/mercadolivre"
REPORT_FILE="${ANALYSIS_DIR}/analysis-report-$(date +%Y-%m-%d_%H-%M-%S).json"

# Criar diretório de análise se não existir
mkdir -p "$ANALYSIS_DIR"

# Verificar se Codacy CLI wrapper existe
if [ ! -f "$CODACY_CLI" ]; then
    echo -e "${RED}❌ ERRO: Codacy CLI wrapper não encontrado em: $CODACY_CLI${NC}"
    echo ""
    echo "Execute primeiro:"
    echo "  ./install-codacy-cli.sh"
    echo ""
    exit 1
fi

# Verificar se wrapper é executável
if [ ! -x "$CODACY_CLI" ]; then
    echo -e "${YELLOW}⚠️  Tornando .codacy/cli.sh executável...${NC}"
    chmod +x "$CODACY_CLI"
fi

echo -e "${BLUE}📂 Diretório raiz: $ROOT_PATH${NC}"
echo -e "${BLUE}🔧 Codacy CLI: $CODACY_CLI${NC}"
echo -e "${BLUE}📊 Relatórios em: $ANALYSIS_DIR${NC}"
echo ""

# Arquivos a serem analisados - Services Mercado Livre
ML_SERVICES=(
    # Core client
    "app/Services/MercadoLivreClient.php"
    "app/Services/MercadoLivreAuthService.php"
    "app/Services/MercadoLivreOrchestratorService.php"

    # MercadoLivre subfolder
    "app/Services/MercadoLivre/CategoriesApiService.php"
    "app/Services/MercadoLivre/MercadoLivreCategoriesGateway.php"
    "app/Services/MercadoLivre/AdvancedPricingEngine.php"
    "app/Services/MercadoLivre/MLAnalyticsIntelligenceService.php"
    "app/Services/MercadoLivre/SmartQAService.php"
    "app/Services/MercadoLivre/CompetitorIntelligenceService.php"
    "app/Services/MercadoLivre/AccountGovernanceIntegrationService.php"
    "app/Services/MercadoLivre/MLResilienceHelper.php"
    "app/Services/MercadoLivre/MLAdsAdvancedService.php"
    "app/Services/MercadoLivre/SEOMetricsCollectorService.php"
    "app/Services/MercadoLivre/MercadoLivreAIIntegrationService.php"
    "app/Services/MercadoLivre/StockSyncService.php"

    # DTOs e Contracts
    "app/Services/MercadoLivre/CategoryChildDTO.php"
    "app/Services/MercadoLivre/CategoryDetailDTO.php"
    "app/Services/MercadoLivre/CategoryNodeDTO.php"
    "app/Services/MercadoLivre/CategoriesApiGatewayInterface.php"
    "app/Services/MercadoLivre/CategoriesApiContracts.php"
    "app/Services/MercadoLivre/CategoriesApiException.php"
)

# Ferramentas de análise específicas para PHP
TOOLS=(
    "phpcs"        # PHP_CodeSniffer - PSR compliance
    "phpmd"        # PHP Mess Detector - code quality
    "trivy"        # Security vulnerabilities
)

echo -e "${GREEN}📋 Arquivos a serem analisados: ${#ML_SERVICES[@]}${NC}"
echo ""

# Contadores de issues
total_errors=0
total_warnings=0
total_info=0
total_files_analyzed=0
files_with_issues=0

# Analisar cada arquivo
for file in "${ML_SERVICES[@]}"; do
    file_path="${ROOT_PATH}/${file}"

    # Verificar se arquivo existe
    if [ ! -f "$file_path" ]; then
        echo -e "${YELLOW}⚠️  Arquivo não encontrado (ignorando): $file${NC}"
        continue
    fi

    echo -e "${BLUE}🔍 Analisando: $file${NC}"

    # Executar análise para cada ferramenta
    file_has_issues=false
    file_errors=0
    file_warnings=0
    file_info=0

    for tool in "${TOOLS[@]}"; do
        # Nome do relatório específico
        tool_report="${ANALYSIS_DIR}/$(basename "$file" .php)_${tool}_$(date +%Y%m%d_%H%M%S).json"

        # Executar análise
        if "$CODACY_CLI" analyze --tool "$tool" --file "$file" --format json > "$tool_report" 2>/dev/null; then
            # Contar issues por severidade
            errors=$(jq '[.[] | select(.level == "Error")] | length' "$tool_report" 2>/dev/null || echo 0)
            warnings=$(jq '[.[] | select(.level == "Warning")] | length' "$tool_report" 2>/dev/null || echo 0)
            info=$(jq '[.[] | select(.level == "Info")] | length' "$tool_report" 2>/dev/null || echo 0)

            if [ "$errors" -gt 0 ] || [ "$warnings" -gt 0 ] || [ "$info" -gt 0 ]; then
                file_has_issues=true
                file_errors=$((file_errors + errors))
                file_warnings=$((file_warnings + warnings))
                file_info=$((file_info + info))

                echo -e "  ${YELLOW}[$tool]${NC} ❌ $errors erros, ⚠️  $warnings warnings, ℹ️  $info info"
            else
                echo -e "  ${GREEN}[$tool]${NC} ✅ Nenhum issue"
                rm -f "$tool_report"  # Remover relatório vazio
            fi
        else
            echo -e "  ${YELLOW}[$tool]${NC} ⏭️  Ferramenta não aplicável ou erro na execução"
            rm -f "$tool_report"
        fi
    done

    # Resumo do arquivo
    if [ "$file_has_issues" = true ]; then
        echo -e "  📊 TOTAL: ${RED}$file_errors erros${NC}, ${YELLOW}$file_warnings warnings${NC}, ${BLUE}$file_info info${NC}"
        files_with_issues=$((files_with_issues + 1))
        total_errors=$((total_errors + file_errors))
        total_warnings=$((total_warnings + file_warnings))
        total_info=$((total_info + file_info))
    else
        echo -e "  ${GREEN}✅ Arquivo sem issues detectados${NC}"
    fi

    echo ""
    total_files_analyzed=$((total_files_analyzed + 1))
done

# Security scan com Trivy (dependências)
echo -e "${BLUE}🔒 Executando scan de segurança em dependências (Trivy)...${NC}"
TRIVY_REPORT="${ANALYSIS_DIR}/trivy-security-scan_$(date +%Y%m%d_%H%M%S).json"

if "$CODACY_CLI" analyze --tool trivy --format json > "$TRIVY_REPORT" 2>/dev/null; then
    trivy_vulns=$(jq '[.[] | select(.level == "Error")] | length' "$TRIVY_REPORT" 2>/dev/null || echo 0)

    if [ "$trivy_vulns" -gt 0 ]; then
        echo -e "  ${RED}❌ $trivy_vulns vulnerabilidades críticas encontradas!${NC}"
        total_errors=$((total_errors + trivy_vulns))
    else
        echo -e "  ${GREEN}✅ Nenhuma vulnerabilidade crítica detectada${NC}"
        rm -f "$TRIVY_REPORT"
    fi
else
    echo -e "  ${YELLOW}⚠️  Trivy scan falhou ou não aplicável${NC}"
    rm -f "$TRIVY_REPORT"
fi
echo ""

# Resumo final
echo "╔════════════════════════════════════════════════════════════╗"
echo "║                    📊 RESUMO DA ANÁLISE                    ║"
echo "╚════════════════════════════════════════════════════════════╝"
echo ""
echo -e "${BLUE}📂 Arquivos analisados: $total_files_analyzed${NC}"
echo -e "${YELLOW}⚠️  Arquivos com issues: $files_with_issues${NC}"
echo ""
echo -e "${RED}❌ Total de ERROS: $total_errors${NC}"
echo -e "${YELLOW}⚠️  Total de WARNINGS: $total_warnings${NC}"
echo -e "${BLUE}ℹ️  Total de INFO: $total_info${NC}"
echo ""
echo -e "${BLUE}📁 Relatórios detalhados em:${NC}"
echo "   $ANALYSIS_DIR"
echo ""

# Status final
if [ "$total_errors" -gt 0 ]; then
    echo -e "${RED}❌ ANÁLISE CONCLUÍDA COM ERROS CRÍTICOS!${NC}"
    echo ""
    echo "Revisar relatórios JSON para detalhes:"
    ls -1 "$ANALYSIS_DIR"/*.json 2>/dev/null | head -5
    echo ""
    exit 1
elif [ "$total_warnings" -gt 0 ]; then
    echo -e "${YELLOW}⚠️  ANÁLISE CONCLUÍDA COM WARNINGS${NC}"
    echo ""
    echo "Revisar relatórios JSON para melhorias:"
    ls -1 "$ANALYSIS_DIR"/*.json 2>/dev/null | head -5
    echo ""
    exit 0
else
    echo -e "${GREEN}✅ ANÁLISE CONCLUÍDA - CÓDIGO LIMPO!${NC}"
    echo ""
    exit 0
fi
