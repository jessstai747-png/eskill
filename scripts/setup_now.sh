#!/bin/bash
# =============================================================================
# SETUP COMPLETO - Mercado Livre Manager
# Executa: bash scripts/setup_now.sh
# =============================================================================

set -e
cd "$(dirname "$0")/.."
PROJECT_ROOT="$(pwd)"

RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

ok()   { echo -e "${GREEN}✅ $1${NC}"; }
warn() { echo -e "${YELLOW}⚠️  $1${NC}"; }
fail() { echo -e "${RED}❌ $1${NC}"; }
info() { echo -e "${BLUE}ℹ️  $1${NC}"; }

echo "=========================================="
echo " Mercado Livre Manager - Setup Completo"
echo "=========================================="
echo ""

ERRORS=0

# =============================================================================
# 1. PHP VERSION & EXTENSIONS
# =============================================================================
echo -e "\n${BLUE}[1/7] Verificando PHP...${NC}"

if ! command -v php &> /dev/null; then
    fail "PHP não encontrado!"
    exit 1
fi

PHP_VER=$(php -r "echo PHP_VERSION;")
PHP_VER_ID=$(php -r "echo PHP_VERSION_ID;")
if [ "$PHP_VER_ID" -lt 80000 ]; then
    fail "PHP $PHP_VER - precisa ser 8.0+"
    exit 1
fi
ok "PHP $PHP_VER"

REQUIRED_EXT="pdo pdo_mysql curl json mbstring"
for ext in $REQUIRED_EXT; do
    if php -m 2>/dev/null | grep -qi "^${ext}$"; then
        ok "ext-$ext"
    else
        fail "ext-$ext FALTANDO! Instale: apt install php-$ext"
        ERRORS=$((ERRORS + 1))
    fi
done

# Redis (opcional mas recomendado)
if php -m 2>/dev/null | grep -qi "^redis$"; then
    ok "ext-redis"
else
    warn "ext-redis não instalado (opcional, recomendado para produção)"
fi

# =============================================================================
# 2. .ENV FILE
# =============================================================================
echo -e "\n${BLUE}[2/7] Verificando .env...${NC}"

if [ -f .env ]; then
    ok ".env existe"
    
    # Validar APP_KEY
    APP_KEY=$(grep -oP '^APP_KEY=\K.*' .env 2>/dev/null || echo "")
    if [ ${#APP_KEY} -ge 32 ]; then
        ok "APP_KEY válida (${#APP_KEY} chars)"
    else
        fail "APP_KEY muito curta (${#APP_KEY} chars, precisa >=32)"
        ERRORS=$((ERRORS + 1))
    fi
    
    # Validar DB_PASSWORD
    DB_PASS=$(grep -oP '^DB_PASS(WORD)?=\K.*' .env 2>/dev/null | head -1 || echo "")
    if [ -n "$DB_PASS" ] && [ "$DB_PASS" != "CHANGE_ME" ] && [ "$DB_PASS" != "secure_password" ]; then
        ok "DB_PASSWORD configurado"
    else
        fail "DB_PASSWORD não configurado no .env"
        ERRORS=$((ERRORS + 1))
    fi
    
    # Validar ML_APP_ID
    ML_ID=$(grep -oP '^ML_APP_ID=\K.*' .env 2>/dev/null || echo "")
    if [ -n "$ML_ID" ] && [ "$ML_ID" != "your_mercadolibre_app_id" ]; then
        ok "ML_APP_ID configurado ($ML_ID)"
    else
        warn "ML_APP_ID não configurado (necessário para sincronização)"
    fi
    
    # Verificar QUEUE_CONNECTION
    QUEUE=$(grep -oP '^QUEUE_CONNECTION=\K.*' .env 2>/dev/null || echo "sync")
    if [ "$QUEUE" = "sync" ]; then
        warn "QUEUE_CONNECTION=sync (use 'database' em produção)"
    else
        ok "QUEUE_CONNECTION=$QUEUE"
    fi
else
    fail ".env não existe!"
    info "Restaurando do backup mais recente..."
    LATEST_BACKUP=$(ls -t storage/backups/env_*.backup 2>/dev/null | head -1)
    if [ -n "$LATEST_BACKUP" ]; then
        cp "$LATEST_BACKUP" .env
        ok ".env restaurado de $LATEST_BACKUP"
    else
        cp .env.example .env
        warn ".env criado do .env.example — EDITE AS CREDENCIAIS!"
        ERRORS=$((ERRORS + 1))
    fi
fi

# =============================================================================
# 3. COMPOSER DEPENDENCIES
# =============================================================================
echo -e "\n${BLUE}[3/7] Verificando dependências Composer...${NC}"

if [ -d vendor ] && [ -f vendor/autoload.php ]; then
    ok "vendor/ existe"
else
    if command -v composer &> /dev/null; then
        info "Instalando dependências..."
        composer install --no-dev --optimize-autoloader 2>&1 | tail -5
        ok "Dependências instaladas"
    else
        fail "Composer não instalado! https://getcomposer.org/download/"
        ERRORS=$((ERRORS + 1))
    fi
fi

# =============================================================================
# 4. STORAGE DIRECTORIES
# =============================================================================
echo -e "\n${BLUE}[4/7] Verificando diretórios de storage...${NC}"

DIRS="storage/logs storage/cache storage/sessions storage/locks storage/temp storage/backups"
for dir in $DIRS; do
    if [ ! -d "$dir" ]; then
        mkdir -p "$dir"
        ok "Criado: $dir"
    else
        ok "$dir existe"
    fi
done

chmod -R 775 storage/ 2>/dev/null
ok "Permissões 775 aplicadas em storage/"

# =============================================================================
# 5. DATABASE CONNECTION & MIGRATIONS
# =============================================================================
echo -e "\n${BLUE}[5/7] Verificando banco de dados...${NC}"

# Testar conexão
DB_TEST=$(php -r "
    require_once 'vendor/autoload.php';
    if (file_exists('.env')) {
        \$dotenv = Dotenv\Dotenv::createImmutable('.');
        \$dotenv->load();
    }
    try {
        \$host = \$_ENV['DB_HOST'] ?? 'localhost';
        \$port = \$_ENV['DB_PORT'] ?? '3306';
        \$db   = \$_ENV['DB_DATABASE'] ?? \$_ENV['DB_NAME'] ?? 'meli';
        \$user = \$_ENV['DB_USERNAME'] ?? \$_ENV['DB_USER'] ?? 'root';
        \$pass = \$_ENV['DB_PASSWORD'] ?? \$_ENV['DB_PASS'] ?? '';
        \$pdo = new PDO(\"mysql:host=\$host;port=\$port;dbname=\$db\", \$user, \$pass);
        \$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        \$tables = \$pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        echo 'OK:' . count(\$tables);
    } catch (Exception \$e) {
        echo 'ERROR:' . \$e->getMessage();
    }
" 2>&1)

if [[ "$DB_TEST" == OK:* ]]; then
    TABLE_COUNT="${DB_TEST#OK:}"
    ok "Conexão MySQL OK — $TABLE_COUNT tabelas existentes"
    
    if [ "$TABLE_COUNT" -lt 10 ]; then
        info "Poucas tabelas encontradas. Rodando migrations..."
        php bin/migrate.php 2>&1 | tail -20
        ok "Migrations executadas"
    else
        info "Banco já tem $TABLE_COUNT tabelas."
        # Rodar mesmo assim para aplicar pendentes
        info "Verificando migrations pendentes..."
        php bin/migrate.php 2>&1 | tail -10
    fi
else
    ERROR_MSG="${DB_TEST#ERROR:}"
    fail "Conexão MySQL falhou: $ERROR_MSG"
    ERRORS=$((ERRORS + 1))
    
    # Verificar se é erro de database não existente
    if echo "$ERROR_MSG" | grep -q "Unknown database"; then
        info "Tentando criar o banco de dados..."
        DB_NAME=$(grep -oP '^DB_DATABASE=\K.*' .env 2>/dev/null || echo "meli")
        DB_USER=$(grep -oP '^DB_USERNAME=\K.*' .env 2>/dev/null || echo "root")
        DB_PASS=$(grep -oP '^DB_PASS(WORD)?=\K.*' .env 2>/dev/null | head -1 || echo "")
        
        mysql -u"$DB_USER" -p"$DB_PASS" -e "CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" 2>&1
        
        if [ $? -eq 0 ]; then
            ok "Banco $DB_NAME criado!"
            info "Rodando migrations..."
            php bin/migrate.php 2>&1 | tail -20
        else
            fail "Falha ao criar banco. Crie manualmente:"
            echo "  mysql -u root -p -e \"CREATE DATABASE $DB_NAME CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;\""
        fi
    fi
fi

# =============================================================================
# 6. CRONTAB
# =============================================================================
echo -e "\n${BLUE}[6/7] Verificando Crontab...${NC}"

CRON_LINES=$(crontab -l 2>/dev/null | grep -v "^#" | grep -v "^$" | wc -l)
if [ "$CRON_LINES" -gt 5 ]; then
    ok "Crontab ativo com $CRON_LINES jobs"
else
    warn "Crontab tem apenas $CRON_LINES jobs ativos"
    info "Instalando crontab de current_crontab..."
    if [ -f current_crontab ]; then
        crontab current_crontab 2>&1
        if [ $? -eq 0 ]; then
            NEW_LINES=$(crontab -l 2>/dev/null | grep -v "^#" | grep -v "^$" | wc -l)
            ok "Crontab instalado com $NEW_LINES jobs"
        else
            fail "Falha ao instalar crontab"
            ERRORS=$((ERRORS + 1))
        fi
    else
        fail "current_crontab não encontrado!"
        ERRORS=$((ERRORS + 1))
    fi
fi

# Verificar se cron está rodando
if systemctl is-active --quiet cron 2>/dev/null || systemctl is-active --quiet crond 2>/dev/null; then
    ok "Serviço cron está ativo"
elif pgrep -x cron > /dev/null 2>&1 || pgrep -x crond > /dev/null 2>&1; then
    ok "Processo cron está rodando"
else
    warn "Serviço cron pode não estar ativo. Execute: systemctl start cron"
fi

# =============================================================================
# 7. VERIFICAÇÃO FINAL
# =============================================================================
echo -e "\n${BLUE}[7/7] Verificação final...${NC}"

# Testar que o index.php carrega sem erros
STARTUP_TEST=$(php -r "
    \$_SERVER['REQUEST_METHOD'] = 'GET';
    \$_SERVER['REQUEST_URI'] = '/api/health';
    \$_SERVER['HTTP_HOST'] = 'eskill.com.br';
    ob_start();
    try {
        require_once 'vendor/autoload.php';
        if (file_exists('.env')) {
            \$dotenv = Dotenv\Dotenv::createImmutable('.');
            \$dotenv->load();
        }
        require_once 'app/Services/StartupValidator.php';
        App\Services\StartupValidator::validate();
        echo 'STARTUP_OK';
    } catch (Throwable \$e) {
        echo 'STARTUP_ERROR:' . \$e->getMessage();
    }
    ob_end_clean();
" 2>&1)

if [[ "$STARTUP_TEST" == *"STARTUP_OK"* ]]; then
    ok "StartupValidator passou"
else
    ERROR_MSG="${STARTUP_TEST#*STARTUP_ERROR:}"
    fail "StartupValidator falhou: $ERROR_MSG"
    ERRORS=$((ERRORS + 1))
fi

# Verificar contas ML autenticadas
ML_ACCOUNTS=$(php -r "
    require_once 'vendor/autoload.php';
    if (file_exists('.env')) {
        \$dotenv = Dotenv\Dotenv::createImmutable('.');
        \$dotenv->load();
    }
    try {
        \$host = \$_ENV['DB_HOST'] ?? 'localhost';
        \$port = \$_ENV['DB_PORT'] ?? '3306';
        \$db   = \$_ENV['DB_DATABASE'] ?? \$_ENV['DB_NAME'] ?? 'meli';
        \$user = \$_ENV['DB_USERNAME'] ?? \$_ENV['DB_USER'] ?? 'root';
        \$pass = \$_ENV['DB_PASSWORD'] ?? \$_ENV['DB_PASS'] ?? '';
        \$pdo = new PDO(\"mysql:host=\$host;port=\$port;dbname=\$db\", \$user, \$pass);
        \$stmt = \$pdo->query('SELECT COUNT(*) as cnt FROM ml_accounts WHERE access_token IS NOT NULL AND access_token != \"\"');
        \$row = \$stmt->fetch(PDO::FETCH_ASSOC);
        echo \$row['cnt'];
    } catch (Exception \$e) {
        echo '0';
    }
" 2>&1)

if [ "$ML_ACCOUNTS" -gt 0 ] 2>/dev/null; then
    ok "$ML_ACCOUNTS conta(s) ML autenticada(s)"
else
    warn "Nenhuma conta ML autenticada — acesse https://eskill.com.br/dashboard para autenticar"
fi

# =============================================================================
# RESULTADO
# =============================================================================
echo ""
echo "=========================================="
if [ $ERRORS -eq 0 ]; then
    echo -e "${GREEN}🎉 SETUP COMPLETO — Sistema pronto!${NC}"
    echo ""
    echo "Próximos passos:"
    echo "  1. Acesse https://eskill.com.br"
    echo "  2. Autentique conta ML em /dashboard"
    echo "  3. Monitore logs: tail -f storage/logs/auto-token-refresh.log"
else
    echo -e "${RED}⚠️  SETUP COM $ERRORS ERRO(S) — Corrija antes de usar${NC}"
fi
echo "=========================================="
