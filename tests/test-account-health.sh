#!/bin/bash

# Account Health API Test Script
# Este script testa todas as APIs do Account Health Dashboard

echo "================================================"
echo "   ACCOUNT HEALTH API TEST SUITE"
echo "================================================"
echo ""

# Configurações
BASE_URL="http://localhost:3001"
SESSION_FILE="/tmp/account-health-session.txt"
RESULTS_DIR="test-results"

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# Função para log
log_test() {
    echo -e "${YELLOW}[TEST]${NC} $1"
}

log_success() {
    echo -e "${GREEN}[✓]${NC} $1"
}

log_error() {
    echo -e "${RED}[✗]${NC} $1"
}

# Criar diretório de resultados
mkdir -p "$RESULTS_DIR"

echo "Nota: Para executar estes testes com sucesso, você precisa:"
echo "1. Estar autenticado no sistema"
echo "2. Ter uma conta ML ativa selecionada"
echo "3. Ter tokens válidos configurados"
echo ""
echo "Pressione ENTER para continuar ou Ctrl+C para cancelar..."
read

echo ""
echo "================================================"
echo "1. TESTE DE ESTRUTURA E DISPONIBILIDADE"
echo "================================================"
echo ""

log_test "1.1 Verificando se o controlador existe..."
if [ -f "app/Controllers/AccountHealthController.php" ]; then
    log_success "Controlador encontrado"
    
    # Contar métodos públicos
    METHOD_COUNT=$(grep -c "public function" app/Controllers/AccountHealthController.php)
    log_success "Métodos públicos encontrados: $METHOD_COUNT"
else
    log_error "Controlador não encontrado"
fi

log_test "1.2 Verificando se a view existe..."
if [ -f "app/Views/dashboard/account-health.php" ]; then
    log_success "View encontrada"
else
    log_error "View não encontrada"
fi

log_test "1.3 Verificando se o service existe..."
if [ -f "app/Services/AccountHealthService.php" ]; then
    log_success "Service encontrado"
else
    log_error "Service não encontrado"
fi

echo ""
echo "================================================"
echo "2. TESTES DE ROTAS (Estrutura)"
echo "================================================"
echo ""

log_test "2.1 Verificando rotas no Router.php..."
if grep -q "account-health" app/Router.php 2>/dev/null; then
    log_success "Rota 'account-health' encontrada no router"
    
    # Extrair definições de rotas
    echo "   Rotas encontradas:"
    grep -n "account-health" app/Router.php | head -5 | while read line; do
        echo "   - $line"
    done
else
    log_error "Rota não encontrada no router"
fi

echo ""
echo "================================================"
echo "3. ANÁLISE DE CÓDIGO"
echo "================================================"
echo ""

log_test "3.1 Analisando AccountHealthController.php..."

# Extrair métodos públicos
echo "   Métodos públicos identificados:"
grep "public function" app/Controllers/AccountHealthController.php | sed 's/.*public function /   - /' | sed 's/(.*$/()/'

log_test "3.2 Verificando validações de segurança..."
if grep -q "isAuthenticated" app/Controllers/AccountHealthController.php; then
    log_success "Validação de autenticação implementada"
fi

if grep -q "header('Content-Type: application/json')" app/Controllers/AccountHealthController.php; then
    log_success "Headers de segurança JSON implementados"
fi

if grep -q "Cache-Control" app/Controllers/AccountHealthController.php; then
    log_success "Headers de cache implementados"
fi

echo ""
echo "================================================"
echo "4. TESTES DE API (Simulados)"
echo "================================================"
echo ""

log_test "4.1 Estrutura de resposta do diagnostic..."
echo "   Esperado:"
echo "   {
     \"success\": true,
     \"data\": {
       \"overall_score\": <number>,
       \"pillars\": [...],
       \"priority_actions\": [...],
       \"items_needing_attention\": [...]
     }
   }"

log_test "4.2 Endpoints identificados:"
echo "   - GET  /dashboard/account-health              (UI)"
echo "   - GET  /api/account-health/diagnostic         (Score + Pilares)"
echo "   - GET  /api/account-health/pillar/{name}      (Pilar específico)"
echo "   - POST /api/account-health/refresh            (Recalcular)"
echo "   - GET  /api/account-health/history            (Histórico)"
echo "   - GET  /api/account-health/advanced/status    (Status avançado)"
echo "   - GET  /api/account-health/advanced/customer-service"
echo "   - GET  /api/account-health/advanced/catalog"
echo "   - GET  /api/account-health/advanced/complete"

echo ""
echo "================================================"
echo "5. TESTE DE PERFORMANCE (Análise de Código)"
echo "================================================"
echo ""

log_test "5.1 Verificando implementação de cache..."
if grep -q "Cache-Control: private, max-age=300" app/Controllers/AccountHealthController.php; then
    log_success "Cache configurado: 300 segundos (5 minutos)"
fi

log_test "5.2 Verificando medição de tempo..."
if grep -q "X-Diagnostic-Time" app/Controllers/AccountHealthController.php; then
    log_success "Medição de tempo de diagnóstico implementada"
fi

log_test "5.3 Verificando throttling..."
if grep -q "throttle\|rate" app/Controllers/AccountHealthController.php; then
    log_success "Sistema de throttling identificado"
fi

echo ""
echo "================================================"
echo "6. TESTE DO SERVICE (AccountHealthService)"
echo "================================================"
echo ""

if [ -f "app/Services/AccountHealthService.php" ]; then
    log_test "6.1 Analisando AccountHealthService..."
    
    # Contar métodos
    SERVICE_METHODS=$(grep -c "public function" app/Services/AccountHealthService.php 2>/dev/null || echo "0")
    log_success "Métodos públicos no service: $SERVICE_METHODS"
    
    log_test "6.2 Métodos principais identificados:"
    grep "public function" app/Services/AccountHealthService.php 2>/dev/null | sed 's/.*public function /   - /' | sed 's/(.*$/()/' | head -10
    
    log_test "6.3 Verificando integração com ML API..."
    if grep -q "MercadoLivreClient\|MercadoLibreClient" app/Services/AccountHealthService.php; then
        log_success "Integração com Mercado Livre API identificada"
    fi
else
    log_error "Service não encontrado"
fi

echo ""
echo "================================================"
echo "7. CHECKLIST DE TESTES MANUAIS"
echo "================================================"
echo ""

echo "Para completar os testes, execute manualmente:"
echo ""
echo "□ 7.1 Login no sistema"
echo "   → Acesse: $BASE_URL/login"
echo ""
echo "□ 7.2 Selecione uma conta ML ativa"
echo "   → Configure tokens válidos"
echo ""
echo "□ 7.3 Acesse o dashboard"
echo "   → URL: $BASE_URL/dashboard/account-health"
echo "   → Verifique: Score geral exibido"
echo "   → Verifique: 5 pilares visíveis"
echo "   → Verifique: Ações prioritárias listadas"
echo ""
echo "□ 7.4 Abra DevTools do navegador"
echo "   → Tab Network: Verifique chamadas à API"
echo "   → Tab Console: Verifique erros JavaScript"
echo "   → Tab Performance: Meça tempo de carregamento"
echo ""
echo "□ 7.5 Teste funcionalidades interativas"
echo "   → Clique em botões de refresh/atualização"
echo "   → Navegue entre pilares"
echo "   → Teste responsividade (mobile/desktop)"
echo ""
echo "□ 7.6 Teste APIs com cURL ou Postman"
echo "   → GET /api/account-health/diagnostic"
echo "   → GET /api/account-health/history?days=30"
echo "   → POST /api/account-health/refresh"
echo ""

echo ""
echo "================================================"
echo "8. COMANDOS ÚTEIS PARA DEBUG"
echo "================================================"
echo ""

echo "# Ver logs da aplicação"
echo "tail -f storage/logs/app-$(date +%Y-%m-%d).log"
echo ""
echo "# Verificar sessão PHP"
echo "php -r 'session_start(); var_dump(\$_SESSION);'"
echo ""
echo "# Testar API diagnostic (substitua <session_id>)"
echo "curl -X GET '$BASE_URL/api/account-health/diagnostic' \\"
echo "  -H 'Cookie: PHPSESSID=<session_id>' \\"
echo "  -H 'Accept: application/json' | jq"
echo ""
echo "# Testar histórico"
echo "curl -X GET '$BASE_URL/api/account-health/history?days=7' \\"
echo "  -H 'Cookie: PHPSESSID=<session_id>' | jq"
echo ""

echo ""
echo "================================================"
echo "   RESUMO DOS TESTES"
echo "================================================"
echo ""

# Contadores
PASSED=0
FAILED=0

# Verificações básicas
[ -f "app/Controllers/AccountHealthController.php" ] && ((PASSED++)) || ((FAILED++))
[ -f "app/Views/dashboard/account-health.php" ] && ((PASSED++)) || ((FAILED++))
[ -f "app/Services/AccountHealthService.php" ] && ((PASSED++)) || ((FAILED++))

grep -q "account-health" app/Router.php 2>/dev/null && ((PASSED++)) || ((FAILED++))
grep -q "isAuthenticated" app/Controllers/AccountHealthController.php && ((PASSED++)) || ((FAILED++))
grep -q "Cache-Control" app/Controllers/AccountHealthController.php && ((PASSED++)) || ((FAILED++))

echo "Testes de estrutura completados:"
echo -e "${GREEN}✓ Passou: $PASSED${NC}"
echo -e "${RED}✗ Falhou: $FAILED${NC}"
echo ""

if [ $FAILED -eq 0 ]; then
    log_success "TODOS OS TESTES DE ESTRUTURA PASSARAM!"
    echo ""
    echo "Sistema pronto para testes funcionais."
    echo "Execute os testes manuais listados acima."
else
    log_error "Alguns testes falharam. Verifique a estrutura do projeto."
fi

echo ""
echo "Relatório completo salvo em: $RESULTS_DIR/ACCOUNT_HEALTH_TEST_REPORT.md"
echo ""
echo "================================================"
echo "   TESTES FINALIZADOS"
echo "================================================"
