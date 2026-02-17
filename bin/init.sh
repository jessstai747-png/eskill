#!/bin/bash
#
# init.sh — Inicialização do ambiente de desenvolvimento
# Baseado em: anthropic.com/engineering/effective-harnesses-for-long-running-agents
#
# Este script é executado por agentes IA no início de cada sessão para:
# 1. Verificar o ambiente (PHP, Composer, Redis, MySQL)
# 2. Rodar smoke tests básicos
# 3. Garantir que o sistema está funcional antes de implementar features
#

set +e  # Não parar em erros — relatamos individualmente

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"

# Colors
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'
BOLD='\033[1m'

ERRORS=0
WARNINGS=0

function header() {
    echo ""
    echo -e "${BOLD}${CYAN}══════════════════════════════════════════════${NC}"
    echo -e "${BOLD}${CYAN}  eskill.com.br — Init & Smoke Tests${NC}"
    echo -e "${BOLD}${CYAN}══════════════════════════════════════════════${NC}"
    echo ""
}

function check_pass() {
    echo -e "  ${GREEN}✅ $1${NC}"
}

function check_fail() {
    echo -e "  ${RED}❌ $1${NC}"
    ((ERRORS++))
}

function check_warn() {
    echo -e "  ${YELLOW}⚠️  $1${NC}"
    ((WARNINGS++))
}

function section() {
    echo ""
    echo -e "${BOLD}${BLUE}── $1 ──${NC}"
}

# ============================================================
# 1. Environment Check
# ============================================================
function check_environment() {
    section "1. Ambiente"

    # PHP
    if command -v php &> /dev/null; then
        PHP_VERSION=$(php -r "echo PHP_MAJOR_VERSION.'.'.PHP_MINOR_VERSION;")
        if [[ $(echo "$PHP_VERSION >= 8.0" | bc -l 2>/dev/null || echo 1) -eq 1 ]]; then
            check_pass "PHP $PHP_VERSION"
        else
            check_fail "PHP $PHP_VERSION (requer 8.0+)"
        fi
    else
        check_fail "PHP não encontrado"
    fi

    # Composer
    if [ -f "$PROJECT_DIR/vendor/autoload.php" ]; then
        check_pass "Composer autoload"
    else
        check_fail "vendor/autoload.php não encontrado (rodar: composer install)"
    fi

    # Redis
    if php -r "exit(extension_loaded('redis') ? 0 : 1);" 2>/dev/null; then
        check_pass "ext-redis"
    else
        check_warn "ext-redis não disponível (cache degradado)"
    fi

    # PDO MySQL
    if php -r "exit(extension_loaded('pdo_mysql') ? 0 : 1);" 2>/dev/null; then
        check_pass "ext-pdo_mysql"
    else
        check_fail "ext-pdo_mysql não disponível"
    fi

    # .env
    if [ -f "$PROJECT_DIR/.env" ]; then
        check_pass ".env presente"
    else
        check_warn ".env não encontrado (verificar variáveis de ambiente)"
    fi
}

# ============================================================
# 2. Project Structure Check
# ============================================================
function check_structure() {
    section "2. Estrutura do Projeto"

    local dirs=("app/Controllers" "app/Services" "app/Models" "app/Views" "app/Routes" "app/Middleware" "bin" "config" "storage/logs" "public")
    for dir in "${dirs[@]}"; do
        if [ -d "$PROJECT_DIR/$dir" ]; then
            check_pass "$dir/"
        else
            check_fail "$dir/ não existe"
        fi
    done

    # Progress files
    if [ -f "$PROJECT_DIR/project-status.json" ]; then
        local total=$(php -r "echo count(json_decode(file_get_contents('$PROJECT_DIR/project-status.json'), true)['features']);" 2>/dev/null || echo "?")
        local passing=$(php -r "\$f=json_decode(file_get_contents('$PROJECT_DIR/project-status.json'),true)['features']; echo count(array_filter(\$f,fn(\$x)=>\$x['passes']));" 2>/dev/null || echo "?")
        check_pass "project-status.json ($passing/$total features passando)"
    else
        check_warn "project-status.json não encontrado"
    fi

    if [ -f "$PROJECT_DIR/claude-progress.txt" ]; then
        check_pass "claude-progress.txt"
    else
        check_warn "claude-progress.txt não encontrado"
    fi
}

# ============================================================
# 3. PHP Syntax Check (critical files)
# ============================================================
function check_syntax() {
    section "3. Sintaxe PHP (arquivos críticos)"

    local files=(
        "app/Database.php"
        "app/Router.php"
        "app/routes.php"
        "app/Controllers/BaseController.php"
        "app/Controllers/DashboardController.php"
        "app/Controllers/AuthController.php"
        "app/Controllers/SEOKillerController.php"
        "app/Services/AuthService.php"
        "app/Services/MercadoLivreClient.php"
        "app/Services/MercadoLivreAuthService.php"
    )

    local syntax_errors=0
    for file in "${files[@]}"; do
        if [ -f "$PROJECT_DIR/$file" ]; then
            if php -l "$PROJECT_DIR/$file" &>/dev/null; then
                check_pass "$file"
            else
                check_fail "$file — ERRO DE SINTAXE"
                ((syntax_errors++))
            fi
        else
            check_warn "$file não encontrado"
        fi
    done

    if [ $syntax_errors -eq 0 ]; then
        echo -e "  ${GREEN}Todos os arquivos críticos OK${NC}"
    fi
}

# ============================================================
# 4. Database Connectivity
# ============================================================
function check_database() {
    section "4. Banco de Dados"

    local db_check=$(php -r "
        require '$PROJECT_DIR/vendor/autoload.php';
        try {
            \$dotenv = Dotenv\Dotenv::createImmutable('$PROJECT_DIR');
            \$dotenv->safeLoad();
            \$host = \$_ENV['DB_HOST'] ?? '127.0.0.1';
            \$port = \$_ENV['DB_PORT'] ?? '3306';
            \$db = \$_ENV['DB_DATABASE'] ?? (\$_ENV['DB_NAME'] ?? 'meli');
            \$user = \$_ENV['DB_USERNAME'] ?? (\$_ENV['DB_USER'] ?? 'root');
            \$pass = \$_ENV['DB_PASSWORD'] ?? (\$_ENV['DB_PASS'] ?? '');
            \$dsn = 'mysql:host=' . \$host . ';port=' . \$port . ';dbname=' . \$db . ';charset=utf8mb4';
            \$pdo = new PDO(\$dsn, \$user, \$pass);
            echo 'OK';
        } catch (Exception \$e) {
            echo 'FAIL:' . \$e->getMessage();
        }
    " 2>/dev/null)

    if [[ "$db_check" == "OK" ]]; then
        check_pass "Conexão MySQL OK"
    else
        check_warn "MySQL: ${db_check#FAIL:}"
    fi
}

# ============================================================
# 5. PHPUnit Tests
# ============================================================
function check_tests() {
    section "5. Testes"

    if [ -f "$PROJECT_DIR/vendor/bin/phpunit" ]; then
        local test_result=$(cd "$PROJECT_DIR" && php vendor/bin/phpunit --no-coverage --colors=never 2>&1 | tail -5)
        if echo "$test_result" | grep -q "OK"; then
            check_pass "PHPUnit: $test_result"
        elif echo "$test_result" | grep -q "FAILURES"; then
            check_warn "PHPUnit: Alguns testes falhando"
            echo "         $test_result"
        else
            check_warn "PHPUnit: $test_result"
        fi
    else
        check_warn "PHPUnit não instalado (vendor/bin/phpunit)"
    fi
}

# ============================================================
# 6. Git Status
# ============================================================
function check_git() {
    section "6. Git"

    if command -v git &> /dev/null && [ -d "$PROJECT_DIR/.git" ]; then
        local branch=$(cd "$PROJECT_DIR" && git branch --show-current 2>/dev/null)
        local status=$(cd "$PROJECT_DIR" && git status --porcelain 2>/dev/null | wc -l)
        local last_commit=$(cd "$PROJECT_DIR" && git log --oneline -1 2>/dev/null)

        check_pass "Branch: $branch"
        if [ "$status" -gt 0 ]; then
            check_warn "$status arquivo(s) modificados/não commitados"
        else
            check_pass "Working tree limpa"
        fi
        echo -e "  ${CYAN}Último commit: $last_commit${NC}"

        # Show recent commits
        echo -e "  ${CYAN}Commits recentes:${NC}"
        cd "$PROJECT_DIR" && git log --oneline -5 2>/dev/null | while read -r line; do
            echo -e "    ${line}"
        done
    else
        check_warn "Git não disponível ou não é um repositório"
    fi
}

# ============================================================
# 7. Feature Summary
# ============================================================
function show_feature_summary() {
    section "7. Resumo de Features (project-status.json)"

    if [ ! -f "$PROJECT_DIR/project-status.json" ]; then
        check_warn "project-status.json não encontrado — pulando resumo"
        return
    fi

    php -r "
        \$data = json_decode(file_get_contents('$PROJECT_DIR/project-status.json'), true);
        \$features = \$data['features'];
        \$total = count(\$features);
        \$passing = count(array_filter(\$features, fn(\$f) => \$f['passes']));
        \$failing = \$total - \$passing;

        echo \"  Total: \$total | ✅ Passando: \$passing | ❌ Falhando: \$failing\n\";
        echo \"  Progresso: \" . round((\$passing/\$total)*100) . \"%\n\n\";

        // Group by category
        \$categories = [];
        foreach (\$features as \$f) {
            \$cat = \$f['category'];
            if (!isset(\$categories[\$cat])) \$categories[\$cat] = ['pass' => 0, 'fail' => 0];
            \$f['passes'] ? \$categories[\$cat]['pass']++ : \$categories[\$cat]['fail']++;
        }

        echo \"  Categoria           | ✅  | ❌\n\";
        echo \"  --------------------|-----|----\n\";
        foreach (\$categories as \$cat => \$counts) {
            printf(\"  %-20s | %2d  | %2d\n\", \$cat, \$counts['pass'], \$counts['fail']);
        }

        echo \"\n  Features falhando (próximas a implementar):\n\";
        \$i = 0;
        foreach (\$features as \$f) {
            if (!\$f['passes'] && \$i < 10) {
                echo \"  → [{\$f['id']}] {\$f['description']}\n\";
                \$i++;
            }
        }
        if (\$failing > 10) echo \"  ... e mais \" . (\$failing - 10) . \" features\n\";
    " 2>/dev/null || check_warn "Erro ao ler project-status.json"
}

# ============================================================
# 8. Progress Summary
# ============================================================
function show_progress() {
    section "8. Último Progresso (claude-progress.txt)"

    if [ -f "$PROJECT_DIR/claude-progress.txt" ]; then
        # Show the most recent session (first entry after the header)
        head -30 "$PROJECT_DIR/claude-progress.txt" | tail -25
    else
        check_warn "claude-progress.txt não encontrado"
    fi
}

# ============================================================
# Summary
# ============================================================
function show_summary() {
    echo ""
    echo -e "${BOLD}${CYAN}══════════════════════════════════════════════${NC}"
    if [ $ERRORS -eq 0 ]; then
        echo -e "${BOLD}${GREEN}  ✅ Sistema pronto — $WARNINGS avisos${NC}"
    else
        echo -e "${BOLD}${RED}  ❌ $ERRORS erros encontrados — corrigir antes de implementar${NC}"
    fi
    echo -e "${BOLD}${CYAN}══════════════════════════════════════════════${NC}"
    echo ""
}

# ============================================================
# Main
# ============================================================
header
check_environment
check_structure
check_syntax
check_database
check_tests
check_git
show_feature_summary
show_progress
show_summary
