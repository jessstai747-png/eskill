#!/bin/bash
#
# fix-infrastructure.sh — Restauração de serviços de infraestrutura
#
# Execute este script NO SERVIDOR HOST (não em sandbox/Docker isolado)
# para restaurar MySQL, Redis, DNS e verificar a integração com ML API.
#
# Uso:
#   sudo bash bin/fix-infrastructure.sh
#   sudo bash bin/fix-infrastructure.sh --check-only   # Apenas diagnóstico
#   sudo bash bin/fix-infrastructure.sh --fix           # Diagnóstico + correção automática
#

set +e

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
FIXED=0
MODE="${1:---fix}"

function header() {
    echo ""
    echo -e "${BOLD}${CYAN}══════════════════════════════════════════════════════${NC}"
    echo -e "${BOLD}${CYAN}  eskill.com.br — Infrastructure Fix & Diagnostics${NC}"
    echo -e "${BOLD}${CYAN}══════════════════════════════════════════════════════${NC}"
    echo -e "  Mode: ${BOLD}${MODE}${NC}"
    echo -e "  Date: $(date '+%Y-%m-%d %H:%M:%S')"
    echo ""
}

function ok()   { echo -e "  ${GREEN}✅ $1${NC}"; }
function fail() { echo -e "  ${RED}❌ $1${NC}"; ((ERRORS++)); }
function warn() { echo -e "  ${YELLOW}⚠️  $1${NC}"; }
function info() { echo -e "  ${BLUE}ℹ️  $1${NC}"; }
function fixed(){ echo -e "  ${GREEN}🔧 $1${NC}"; ((FIXED++)); }

function section() {
    echo ""
    echo -e "${BOLD}${BLUE}── $1 ──${NC}"
}

function try_fix() {
    if [[ "$MODE" == "--fix" ]]; then
        return 0
    fi
    return 1
}

# ============================================================
# 1. DNS / Network
# ============================================================
function check_dns() {
    section "1. DNS & Rede"

    # Check if we can resolve hosts
    if host api.mercadolibre.com >/dev/null 2>&1; then
        ok "DNS resolve: api.mercadolibre.com"
    else
        fail "DNS resolve: api.mercadolibre.com FALHOU"

        # Check systemd-resolved
        if systemctl is-active systemd-resolved >/dev/null 2>&1; then
            ok "systemd-resolved está ativo"
        else
            fail "systemd-resolved está PARADO"
            if try_fix; then
                info "Tentando restart systemd-resolved..."
                systemctl restart systemd-resolved 2>/dev/null
                sleep 2
                if systemctl is-active systemd-resolved >/dev/null 2>&1; then
                    fixed "systemd-resolved reiniciado com sucesso"
                else
                    fail "Não conseguiu reiniciar systemd-resolved"
                    info "Tentando configurar DNS manualmente..."
                    # Fallback: configurar DNS diretamente
                    if [[ -f /etc/resolv.conf ]]; then
                        cp /etc/resolv.conf /etc/resolv.conf.bak.$(date +%s)
                    fi
                    echo -e "nameserver 8.8.8.8\nnameserver 8.8.4.4\nnameserver 1.1.1.1" > /etc/resolv.conf
                    if host api.mercadolibre.com >/dev/null 2>&1; then
                        fixed "DNS configurado via /etc/resolv.conf (Google DNS)"
                    else
                        fail "DNS ainda não funciona. Verifique a conectividade de rede."
                    fi
                fi
            fi
        fi

        # Check /etc/resolv.conf
        if [[ -f /etc/resolv.conf ]]; then
            info "resolv.conf: $(grep -c nameserver /etc/resolv.conf) nameservers configurados"
            grep nameserver /etc/resolv.conf | head -3 | while read line; do
                info "  $line"
            done
        else
            fail "/etc/resolv.conf não existe"
        fi
    fi

    # Network connectivity
    if ping -c 1 -W 3 8.8.8.8 >/dev/null 2>&1; then
        ok "Rede: ping 8.8.8.8 OK"
    else
        fail "Rede: sem conectividade (ping 8.8.8.8 falhou)"
        info "Verifique a interface de rede e o gateway"
    fi

    # Test ML API
    local ml_status
    ml_status=$(curl -s -o /dev/null -w "%{http_code}" --connect-timeout 5 "https://api.mercadolibre.com/sites/MLB" 2>/dev/null)
    if [[ "$ml_status" == "200" ]]; then
        ok "API ML: https://api.mercadolibre.com/sites/MLB → 200 OK"
    else
        fail "API ML: status $ml_status (esperado 200)"
    fi
}

# ============================================================
# 2. MySQL
# ============================================================
function check_mysql() {
    section "2. MySQL"

    # Check if MySQL is running
    if systemctl is-active mysql >/dev/null 2>&1 || systemctl is-active mysqld >/dev/null 2>&1 || systemctl is-active mariadb >/dev/null 2>&1; then
        ok "MySQL service está ativo"
    else
        fail "MySQL service está PARADO"
        if try_fix; then
            info "Tentando iniciar MySQL..."
            # Try different service names
            for svc in mysql mysqld mariadb; do
                if systemctl start "$svc" 2>/dev/null; then
                    sleep 3
                    if systemctl is-active "$svc" >/dev/null 2>&1; then
                        fixed "MySQL ($svc) iniciado com sucesso"
                        break
                    fi
                fi
            done

            # Verify
            if ! systemctl is-active mysql >/dev/null 2>&1 && ! systemctl is-active mysqld >/dev/null 2>&1; then
                fail "Não conseguiu iniciar MySQL"
                info "Verifique logs: journalctl -u mysql -n 20"
            fi
        fi
    fi

    # Check socket
    local socket_path="/var/run/mysqld/mysqld.sock"
    if [[ -S "$socket_path" ]]; then
        ok "Socket: $socket_path existe"
    else
        fail "Socket: $socket_path não encontrado"
    fi

    # Check data directory
    if [[ -d /var/lib/mysql ]]; then
        ok "Data dir: /var/lib/mysql existe"
    else
        fail "Data dir: /var/lib/mysql NÃO existe"
        info "MySQL pode precisar ser reinstalado ou inicializado"
    fi

    # Load env credentials
    source_env

    local db_host="${DB_HOST:-localhost}"
    local db_port="${DB_PORT:-3306}"
    local db_name="${DB_DATABASE:-${DB_NAME:-meli}}"
    local db_user="${DB_USERNAME:-${DB_USER:-}}"
    local db_pass="${DB_PASSWORD:-${DB_PASS:-}}"

    if [[ -z "$db_user" ]]; then
        fail "DB_USERNAME/DB_USER não configurado no .env"
        info "Defina usuário de aplicação (não-root) antes de executar diagnóstico de MySQL"
        return
    fi

    # ML-005: Warn about root usage in production
    if [[ "$db_user" == "root" ]]; then
        warn "DB_USERNAME=root — em produção use um usuário de aplicação com grants mínimos (ML-005)"
        if try_fix; then
            info "Para criar usuário de aplicação substituto:"
            info "  mysql -u root -p -e \"CREATE USER IF NOT EXISTS 'meli_app'@'localhost' IDENTIFIED BY 'SENHA_FORTE';\""
            info "  mysql -u root -p -e \"GRANT SELECT,INSERT,UPDATE,DELETE,CREATE,DROP,INDEX,ALTER ON \\\`${db_name}\\\`.* TO 'meli_app'@'localhost';\""
            info "  mysql -u root -p -e \"FLUSH PRIVILEGES;\""
            info "Depois atualize DB_USERNAME e DB_PASSWORD em .env e reinicie os workers."
        fi
    fi

    # Test connection with .env credentials
    if command -v mysql >/dev/null 2>&1; then
        if mysql -h "$db_host" -P "$db_port" -u "$db_user" -p"$db_pass" -e "SELECT 1" >/dev/null 2>&1; then
            ok "Conexão MySQL: OK (user=$db_user@$db_host)"
        else
            fail "Conexão MySQL: FALHOU com credenciais do .env"
            info "Host=$db_host Port=$db_port User=$db_user"
        fi

        # Check if database exists
        if mysql -h "$db_host" -P "$db_port" -u "$db_user" -p"$db_pass" -e "USE $db_name" >/dev/null 2>&1; then
            ok "Database '$db_name' existe"

            # Check key tables
            local tables
            tables=$(mysql -h "$db_host" -P "$db_port" -u "$db_user" -p"$db_pass" "$db_name" -N -e "SHOW TABLES" 2>/dev/null | wc -l)
            info "Database '$db_name' tem $tables tabelas"

            # Check for ml_accounts table
            if mysql -h "$db_host" -P "$db_port" -u "$db_user" -p"$db_pass" "$db_name" -N -e "SELECT COUNT(*) FROM ml_accounts" >/dev/null 2>&1; then
                local acc_count
                acc_count=$(mysql -h "$db_host" -P "$db_port" -u "$db_user" -p"$db_pass" "$db_name" -N -e "SELECT COUNT(*) FROM ml_accounts" 2>/dev/null)
                ok "ml_accounts: $acc_count conta(s) cadastrada(s)"

                # Check for disconnected accounts (ML-001)
                local disconnected_count
                disconnected_count=$(mysql -h "$db_host" -P "$db_port" -u "$db_user" -p"$db_pass" "$db_name" -N -e "SELECT COUNT(*) FROM ml_accounts WHERE status='disconnected'" 2>/dev/null || echo "0")
                if [[ "$disconnected_count" -gt 0 ]]; then
                    warn "${disconnected_count} conta(s) com status=disconnected — reautorização OAuth necessária (ML-001)"
                    info "Acesse /auth/authorize para reconectar as contas desconectadas"
                fi
            else
                warn "Tabela ml_accounts não encontrada — rode as migrations"
                info "  php bin/apply-migrations.php"
            fi

            # Check for observability/monitoring tables (ML-004)
            local monitor_tables="worker_execution_logs clone_health_logs clone_duplicate_registry clone_sync_logs"
            local missing_monitor=""
            for tbl in $monitor_tables; do
                if ! mysql -h "$db_host" -P "$db_port" -u "$db_user" -p"$db_pass" "$db_name" -N -e "SELECT 1 FROM \`${tbl}\` LIMIT 1" >/dev/null 2>&1; then
                    missing_monitor="${missing_monitor} ${tbl}"
                fi
            done
            if [[ -n "$missing_monitor" ]]; then
                warn "Tabelas de monitoramento ausentes:${missing_monitor} (ML-004)"
                info "Aplique: mysql -u \$DB_USERNAME -p\$DB_PASSWORD \$DB_DATABASE < database/migrations/2026_02_26_000001_stabilize_production_schema.sql"
                if try_fix; then
                    info "Tentando aplicar migration de estabilização..."
                    local migration_file="${PROJECT_DIR}/database/migrations/2026_02_26_000001_stabilize_production_schema.sql"
                    if [[ -f "$migration_file" ]]; then
                        if mysql -h "$db_host" -P "$db_port" -u "$db_user" -p"$db_pass" "$db_name" < "$migration_file" 2>/dev/null; then
                            fixed "Migration 2026_02_26_000001 aplicada com sucesso"
                        else
                            fail "Falha ao aplicar migration — verifique permissões e SQL"
                        fi
                    else
                        fail "Migration não encontrada: $migration_file"
                    fi
                fi
            else
                ok "Tabelas de monitoramento presentes (ML-004 OK)"
            fi
        else
            fail "Database '$db_name' NÃO existe"
            if try_fix; then
                info "Criando database $db_name..."
                mysql -h "$db_host" -P "$db_port" -u "$db_user" -p"$db_pass" -e "CREATE DATABASE IF NOT EXISTS \`$db_name\` DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci" 2>/dev/null
                if [[ $? -eq 0 ]]; then
                    fixed "Database '$db_name' criado"
                    info "Rodando migrations..."
                    cd "$PROJECT_DIR" && php bin/apply-migrations.php 2>&1 | tail -5
                fi
            fi
        fi
    else
        warn "Cliente mysql não encontrado no PATH"
    fi

    # Test PHP PDO connection
    php -r "
        try {
            \$pdo = new PDO('mysql:host=$db_host;port=$db_port;dbname=$db_name', '$db_user', '$db_pass', [PDO::ATTR_TIMEOUT => 5]);
            echo 'PHP PDO: OK';
        } catch (Exception \$e) {
            echo 'PHP PDO: FAILED - ' . \$e->getMessage();
        }
    " 2>/dev/null | while read line; do
        if [[ "$line" == *"OK"* ]]; then
            ok "$line"
        else
            fail "$line"
        fi
    done
}

# ============================================================
# 3. Redis
# ============================================================
function check_redis() {
    section "3. Redis"

    if systemctl is-active redis >/dev/null 2>&1 || systemctl is-active redis-server >/dev/null 2>&1; then
        ok "Redis service está ativo"
    else
        fail "Redis service está PARADO"
        if try_fix; then
            info "Tentando iniciar Redis..."
            for svc in redis redis-server; do
                if systemctl start "$svc" 2>/dev/null; then
                    sleep 2
                    if systemctl is-active "$svc" >/dev/null 2>&1; then
                        fixed "Redis ($svc) iniciado com sucesso"
                        break
                    fi
                fi
            done
        fi
    fi

    # Test connection
    if command -v redis-cli >/dev/null 2>&1; then
        local redis_host="${REDIS_HOST:-127.0.0.1}"
        local redis_port="${REDIS_PORT:-6379}"
        local pong
        pong=$(redis-cli -h "$redis_host" -p "$redis_port" ping 2>/dev/null)
        if [[ "$pong" == "PONG" ]]; then
            ok "Redis PING: PONG (${redis_host}:${redis_port})"
        else
            fail "Redis PING falhou (${redis_host}:${redis_port})"
        fi
    else
        warn "redis-cli não encontrado"
    fi
}

# ============================================================
# 4. ML API Token
# ============================================================
function check_ml_token() {
    section "4. Token Mercado Livre"

    source_env

    local token="${ML_ACCESS_TOKEN:-}"
    if [[ -z "$token" ]]; then
        fail "ML_ACCESS_TOKEN não configurado no .env"
        return
    fi

    # Check if it's the placeholder
    if [[ "$token" == "APP_USR-1234567890"* ]]; then
        fail "ML_ACCESS_TOKEN é um PLACEHOLDER (não é token real)"
        warn "Token atual: ${token:0:30}..."
        info ""
        info "Para obter um token real:"
        info "  1. Acesse: https://auth.mercadolibre.com.ar/authorization?response_type=code&client_id=${ML_APP_ID}&redirect_uri=${ML_REDIRECT_URI}"
        info "  2. Autorize o app"
        info "  3. Copie o 'code' da URL de redirecionamento"
        info "  4. Execute: php bin/mcp-ml-auth.php --code=SEU_CODE"
        info ""
        info "  OU use o fluxo automático:"
        info "  php bin/mcp-ml-auth.php --open"
        return
    fi

    # Validate token with API
    local ml_response
    ml_response=$(curl -s --connect-timeout 5 -H "Authorization: Bearer $token" "https://api.mercadolibre.com/users/me" 2>/dev/null)

    if [[ -z "$ml_response" ]]; then
        fail "Não foi possível verificar token (sem resposta da API)"
        return
    fi

    local ml_error
    ml_error=$(echo "$ml_response" | php -r 'echo json_decode(file_get_contents("php://stdin"))->error ?? "";' 2>/dev/null)

    if [[ -n "$ml_error" ]]; then
        fail "Token inválido ou expirado: $ml_error"
        local ml_message
        ml_message=$(echo "$ml_response" | php -r 'echo json_decode(file_get_contents("php://stdin"))->message ?? "";' 2>/dev/null)
        if [[ -n "$ml_message" ]]; then
            info "  Mensagem: $ml_message"
        fi
        info ""
        info "Para renovar o token:"
        info "  php bin/mcp-ml-auth.php --open"
    else
        local ml_nickname
        ml_nickname=$(echo "$ml_response" | php -r 'echo json_decode(file_get_contents("php://stdin"))->nickname ?? "unknown";' 2>/dev/null)
        local ml_id
        ml_id=$(echo "$ml_response" | php -r 'echo json_decode(file_get_contents("php://stdin"))->id ?? "unknown";' 2>/dev/null)
        ok "Token válido: $ml_nickname (ID: $ml_id)"

        # Check seller items
        local items_response
        items_response=$(curl -s --connect-timeout 5 -H "Authorization: Bearer $token" "https://api.mercadolibre.com/users/$ml_id/items/search?limit=1" 2>/dev/null)
        local total_items
        total_items=$(echo "$items_response" | php -r 'echo json_decode(file_get_contents("php://stdin"))->paging->total ?? 0;' 2>/dev/null)
        ok "Itens do vendedor: $total_items anúncios"
    fi
}

# ============================================================
# 5. PHP Application
# ============================================================
function check_php_app() {
    section "5. Aplicação PHP"

    cd "$PROJECT_DIR"

    # Check autoloader
    if [[ -f vendor/autoload.php ]]; then
        ok "vendor/autoload.php existe"
    else
        fail "vendor/autoload.php não encontrado"
        if try_fix; then
            info "Executando composer install..."
            composer install --no-dev --no-interaction 2>&1 | tail -3
            if [[ -f vendor/autoload.php ]]; then
                fixed "Dependências instaladas"
            fi
        fi
    fi

    # Check .env
    if [[ -f .env ]]; then
        ok ".env existe"
    elif [[ -f .env.example ]]; then
        fail ".env não encontrado (mas .env.example existe)"
        if try_fix; then
            cp .env.example .env
            fixed ".env criado a partir do .env.example — EDITE AS CREDENCIAIS"
        fi
    else
        fail ".env não encontrado"
    fi

    # PHP syntax check on critical files
    local critical_files=(
        "app/Controllers/SEOKillerController.php"
        "app/Services/ItemService.php"
        "app/Services/AI/SEO/SEOKillerEngine.php"
        "app/Services/MercadoLivreClient.php"
        "app/Database.php"
    )
    local syntax_ok=0
    local syntax_fail=0
    for f in "${critical_files[@]}"; do
        if [[ -f "$f" ]]; then
            if php -l "$f" >/dev/null 2>&1; then
                ((syntax_ok++))
            else
                fail "Syntax error: $f"
                ((syntax_fail++))
            fi
        fi
    done
    if [[ $syntax_fail -eq 0 ]]; then
        ok "PHP syntax: $syntax_ok arquivos críticos OK"
    fi

    # Run PHPUnit if available
    if [[ -f vendor/bin/phpunit ]]; then
        info "Rodando testes unitários..."
        local test_result
        test_result=$(php vendor/bin/phpunit --testsuite=Unit --no-coverage 2>&1 | tail -1)
        if echo "$test_result" | grep -q "OK"; then
            ok "PHPUnit: $test_result"
        else
            warn "PHPUnit: $test_result"
        fi
    fi
}

# ============================================================
# 6. Web Server
# ============================================================
function check_webserver() {
    section "6. Web Server"

    # Check nginx
    if systemctl is-active nginx >/dev/null 2>&1; then
        ok "Nginx está ativo"
    elif systemctl is-active apache2 >/dev/null 2>&1 || systemctl is-active httpd >/dev/null 2>&1; then
        ok "Apache está ativo"
    else
        warn "Nenhum web server detectado (nginx/apache)"
        if try_fix; then
            info "Tentando iniciar nginx..."
            systemctl start nginx 2>/dev/null
            if systemctl is-active nginx >/dev/null 2>&1; then
                fixed "Nginx iniciado"
            else
                info "Tentando iniciar apache2..."
                systemctl start apache2 2>/dev/null || systemctl start httpd 2>/dev/null
            fi
        fi
    fi

    # PHP-FPM
    if systemctl is-active php*-fpm >/dev/null 2>&1 || systemctl is-active php-fpm >/dev/null 2>&1; then
        ok "PHP-FPM está ativo"
    else
        local php_ver
        php_ver=$(php -v 2>/dev/null | head -1 | grep -oP '\d+\.\d+')
        if [[ -n "$php_ver" ]]; then
            if systemctl is-active "php${php_ver}-fpm" >/dev/null 2>&1; then
                ok "PHP ${php_ver}-FPM está ativo"
            else
                warn "PHP-FPM não está ativo"
                if try_fix; then
                    systemctl start "php${php_ver}-fpm" 2>/dev/null && fixed "PHP ${php_ver}-FPM iniciado"
                fi
            fi
        fi
    fi
}

# ============================================================
# Helper: Source .env
# ============================================================
function source_env() {
    if [[ -f "$PROJECT_DIR/.env" ]]; then
        while IFS='=' read -r key val; do
            key=$(echo "$key" | xargs 2>/dev/null)
            [[ -z "$key" || "$key" == \#* ]] && continue
            val=$(echo "$val" | sed "s/^[\"']//;s/[\"']$//" | xargs 2>/dev/null)
            export "$key=$val" 2>/dev/null
        done < "$PROJECT_DIR/.env"
    fi
}

# ============================================================
# Summary
# ============================================================
function summary() {
    echo ""
    echo -e "${BOLD}${CYAN}══════════════════════════════════════════════════════${NC}"
    if [[ $ERRORS -eq 0 ]]; then
        echo -e "  ${GREEN}${BOLD}✅ Tudo OK — infraestrutura saudável${NC}"
    else
        echo -e "  ${RED}${BOLD}❌ $ERRORS problema(s) encontrado(s)${NC}"
        if [[ $FIXED -gt 0 ]]; then
            echo -e "  ${GREEN}${BOLD}🔧 $FIXED problema(s) corrigido(s) automaticamente${NC}"
        fi
    fi

    if [[ $ERRORS -gt 0 && "$MODE" == "--check-only" ]]; then
        echo ""
        echo -e "  ${YELLOW}Execute com --fix para tentar corrigir automaticamente:${NC}"
        echo -e "  ${BOLD}sudo bash bin/fix-infrastructure.sh --fix${NC}"
    fi

    echo -e "${BOLD}${CYAN}══════════════════════════════════════════════════════${NC}"
    echo ""

    # After fixes, verify ML dashboard flow
    if [[ "$MODE" == "--fix" && $ERRORS -eq 0 ]]; then
        echo -e "  ${GREEN}Próximos passos:${NC}"
        echo -e "    1. Acesse o dashboard: ${BOLD}https://eskill.com.br/dashboard/seo-killer${NC}"
        echo -e "    2. Clique em 'Sincronizar Anúncios'"
        echo -e "    3. Verifique que os itens aparecem"
        echo ""
    fi

    return $ERRORS
}

# ============================================================
# Main
# ============================================================
header
check_dns
check_mysql
check_redis
check_ml_token
check_php_app
check_webserver
summary
exit $?
