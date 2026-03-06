#!/bin/bash
# =============================================================================
# Setup de Produção — eskill.com.br
#
# Este script:
#   1. Verifica que MySQL está rodando
#   2. Aplica migrations pendentes
#   3. Cria usuário admin (se não existir)
#   4. Configura cron para monitoramento de erros
#   5. Testa o error monitor
#
# Uso:
#   bash bin/setup-production.sh
#   bash bin/setup-production.sh --skip-cron
# =============================================================================

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "$0")/.." && pwd)"
cd "$PROJECT_DIR"

# Cores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

ok()   { echo -e "${GREEN}✓${NC} $1"; }
fail() { echo -e "${RED}✗${NC} $1"; }
info() { echo -e "${CYAN}→${NC} $1"; }
warn() { echo -e "${YELLOW}!${NC} $1"; }

SKIP_CRON=false
[[ "${1:-}" == "--skip-cron" ]] && SKIP_CRON=true

echo ""
echo "═══════════════════════════════════════════"
echo "  eskill.com.br — Setup de Produção"
echo "═══════════════════════════════════════════"
echo ""

# ─── 1. Verificar MySQL ───────────────────────────────────────
info "Verificando MySQL..."
if mysqladmin ping --silent 2>/dev/null; then
    ok "MySQL está rodando"
else
    fail "MySQL NÃO está rodando!"
    echo ""
    echo "Tente iniciar com:"
    echo "  sudo systemctl start mysql"
    echo "  ou: sudo service mysql start"
    echo ""
    exit 1
fi

# ─── 2. Verificar .env ────────────────────────────────────────
info "Verificando .env..."
if [[ -f .env ]]; then
    ok ".env encontrado"
else
    fail ".env não encontrado! Copie .env.example para .env e configure."
    exit 1
fi

# ─── 3. Testar conexão com banco ──────────────────────────────
info "Testando conexão com banco de dados..."
DB_TEST=$(php -r "
require_once '$PROJECT_DIR/vendor/autoload.php';
require_once '$PROJECT_DIR/autoload.php';
if (file_exists('$PROJECT_DIR/.env')) {
    \$dotenv = Dotenv\Dotenv::createImmutable('$PROJECT_DIR');
    \$dotenv->load();
}
try {
    \$db = App\Database::getInstance();
    echo 'OK';
} catch (\Exception \$e) {
    echo 'FAIL:' . \$e->getMessage();
}
" 2>&1)

if [[ "$DB_TEST" == "OK" ]]; then
    ok "Conexão com banco OK"
else
    fail "Falha na conexão: $DB_TEST"
    exit 1
fi

# ─── 4. Aplicar migrations ───────────────────────────────────
info "Aplicando migrations..."
if [[ -f bin/apply-migrations.php ]]; then
    php bin/apply-migrations.php 2>&1 || true
    ok "Migrations processadas"
else
    warn "bin/apply-migrations.php não encontrado, pulando"
fi

# ─── 5. Criar admin ──────────────────────────────────────────
info "Verificando se admin existe..."
ADMIN_EXISTS=$(php -r "
require_once '$PROJECT_DIR/vendor/autoload.php';
require_once '$PROJECT_DIR/autoload.php';
if (file_exists('$PROJECT_DIR/.env')) {
    \$dotenv = Dotenv\Dotenv::createImmutable('$PROJECT_DIR');
    \$dotenv->load();
}
try {
    \$db = App\Database::getInstance();
    \$stmt = \$db->prepare('SELECT COUNT(*) FROM users WHERE role = ?');
    \$stmt->execute(['admin']);
    echo \$stmt->fetchColumn() > 0 ? 'EXISTS' : 'NOT_EXISTS';
} catch (\Exception \$e) {
    echo 'ERROR:' . \$e->getMessage();
}
" 2>&1)

if [[ "$ADMIN_EXISTS" == "EXISTS" ]]; then
    ok "Admin já existe no banco"
elif [[ "$ADMIN_EXISTS" == "NOT_EXISTS" ]]; then
    warn "Nenhum admin encontrado. Criando..."
    echo ""
    php bin/create-admin.php
    echo ""
else
    fail "Erro ao verificar admin: $ADMIN_EXISTS"
fi

# ─── 6. Testar error monitor ─────────────────────────────────
info "Testando error monitor..."
if php bin/error-monitor.php --status 2>/dev/null; then
    ok "Error monitor funcional"
else
    warn "Error monitor reportou problemas (verifique a saída acima)"
fi

# ─── 7. Configurar cron ──────────────────────────────────────
if [[ "$SKIP_CRON" == "false" ]]; then
    info "Configurando cron para error monitor..."

    CRON_LINE="*/5 * * * * cd $PROJECT_DIR && php bin/error-monitor.php >> storage/logs/monitor.log 2>&1"

    if crontab -l 2>/dev/null | grep -q "error-monitor.php"; then
        ok "Cron para error monitor já configurado"
    else
        (crontab -l 2>/dev/null; echo "$CRON_LINE") | crontab -
        ok "Cron adicionado: a cada 5 minutos"
    fi
else
    info "Pulando configuração de cron (--skip-cron)"
fi

# ─── Resumo ───────────────────────────────────────────────────
echo ""
echo "═══════════════════════════════════════════"
echo -e "  ${GREEN}Setup completo!${NC}"
echo "═══════════════════════════════════════════"
echo ""
echo "Próximos passos:"
echo "  1. Acesse https://eskill.com.br/login"
echo "  2. Faça login com o admin criado"
echo "  3. Verifique o dashboard em /dashboard"
echo ""
echo "Monitoramento:"
echo "  • Error monitor: php bin/error-monitor.php --verbose"
echo "  • Logs: tail -f storage/logs/error.log"
echo "  • Monitor log: tail -f storage/logs/monitor.log"
echo ""
