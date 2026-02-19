#!/bin/bash

# ====================================
# SCRIPT DE CONFIGURAÇÃO DO BANCO DE DADOS
# Criar usuário dedicado e configurar segurança
# ====================================

set -e

# Cores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m'

log() {
    echo -e "${GREEN}[INFO]${NC} $1"
}

warning() {
    echo -e "${YELLOW}[WARNING]${NC} $1"
}

error() {
    echo -e "${RED}[ERROR]${NC} $1"
    exit 1
}

info() {
    echo -e "${BLUE}[NOTA]${NC} $1"
}

echo "======================================"
echo "🗄️  CONFIGURAÇÃO BANCO DE DADOS"
echo "======================================"

# Verificar se MySQL está rodando
if ! systemctl is-active --quiet mysql; then
    error "MySQL/MariaDB não está rodando!"
fi

# Gerar senha forte
generate_password() {
    openssl rand -base64 32 | tr -d "=+/" | cut -c1-25
}

# Obter informações
echo "Configuração do banco de dados:"
echo ""

read -p "Nome do banco de dados [mercadolivre_prod]: " DB_NAME
DB_NAME=${DB_NAME:-mercadolivre_prod}

read -p "Nome do usuário [ml_user]: " DB_USER  
DB_USER=${DB_USER:-ml_user}

echo ""
echo "Escolha como definir a senha:"
echo "1) Gerar automaticamente (recomendado)"
echo "2) Digitar manualmente"
read -p "Opção [1]: " PASS_OPTION
PASS_OPTION=${PASS_OPTION:-1}

if [ "$PASS_OPTION" = "1" ]; then
    DB_PASS=$(generate_password)
    log "Senha gerada automaticamente"
else
    read -s -p "Digite a senha: " DB_PASS
    echo ""
    
    if [ ${#DB_PASS} -lt 12 ]; then
        error "Senha deve ter pelo menos 12 caracteres!"
    fi
fi

echo ""
echo "📋 Configurações:"
echo "   Banco: $DB_NAME"
echo "   Usuário: $DB_USER"
echo "   Senha: [OCULTA - ${#DB_PASS} caracteres]"
echo ""

read -p "Prosseguir com a configuração? (y/N): " CONFIRM
if [[ ! "$CONFIRM" =~ ^[Yy]$ ]]; then
    log "Operação cancelada"
    exit 0
fi

# ====================================
# CONECTAR COMO ROOT
# ====================================

echo ""
log "Conectando ao MySQL..."

# Verificar autenticação root
if mysql -u root -p -e "SELECT 1" &>/dev/null; then
    MYSQL_ROOT="mysql -u root -p"
    warning "Digite a senha do root MySQL quando solicitado"
elif mysql -u root -e "SELECT 1" &>/dev/null; then
    MYSQL_ROOT="mysql -u root"
    info "Conexão root sem senha (desenvolvimento)"
else
    error "Não conseguiu conectar como root MySQL!"
fi

# ====================================
# CRIAR BANCO E USUÁRIO
# ====================================

log "Criando banco e usuário..."

# Script SQL
SQL_SCRIPT=$(cat << EOF
-- Criar banco se não existir
CREATE DATABASE IF NOT EXISTS \`$DB_NAME\` 
CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Remover usuário se já existir
DROP USER IF EXISTS '$DB_USER'@'localhost';

-- Criar usuário com senha
CREATE USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';

-- Conceder privilégios específicos (não ALL)
GRANT SELECT, INSERT, UPDATE, DELETE, CREATE, ALTER, INDEX, DROP 
ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';

-- Remover acesso a outros bancos
REVOKE ALL PRIVILEGES ON *.* FROM '$DB_USER'@'localhost';

-- Aplicar mudanças
FLUSH PRIVILEGES;

-- Verificar usuário
SELECT User, Host FROM mysql.user WHERE User = '$DB_USER';
EOF
)

# Executar SQL
echo "$SQL_SCRIPT" | $MYSQL_ROOT

if [ $? -eq 0 ]; then
    log "✅ Banco e usuário criados com sucesso!"
else
    error "❌ Falha na criação do banco/usuário"
fi

# ====================================
# EXECUTAR MIGRAÇÕES
# ====================================

log "Executando migrações..."

# Configurar variáveis para teste
export DB_HOST="localhost"
export DB_PORT="3306"
export DB_NAME="$DB_NAME"
export DB_USER="$DB_USER"
export DB_PASS="$DB_PASS"

# Testar conexão
log "Testando conexão..."
php -r "
try {
    \$pdo = new PDO(
        'mysql:host=$DB_HOST;port=$DB_PORT;dbname=$DB_NAME;charset=utf8mb4',
        '$DB_USER',
        '$DB_PASS',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    echo 'Conexão OK' . PHP_EOL;
} catch (Exception \$e) {
    echo 'ERRO: ' . \$e->getMessage() . PHP_EOL;
    exit(1);
}
"

if [ $? -eq 0 ]; then
    info "✅ Conexão testada com sucesso"
    
    # Executar migrações se o arquivo existir
    if [ -f "scripts/migrate.php" ]; then
        log "Executando migrações..."
        php scripts/migrate.php
        
        if [ $? -eq 0 ]; then
            log "✅ Migrações executadas"
        else
            warning "Falha nas migrações - verifique manualmente"
        fi
    else
        warning "Script de migração não encontrado"
    fi
else
    error "❌ Falha na conexão - verifique as configurações"
fi

# ====================================
# CONFIGURAR .env
# ====================================

log "Atualizando .env..."

if [ -f ".env" ]; then
    # Fazer backup
    cp .env .env.backup.$(date +%Y%m%d_%H%M%S)
    
    # Atualizar configurações do banco
    sed -i "s/^DB_HOST=.*/DB_HOST=localhost/" .env
    sed -i "s/^DB_PORT=.*/DB_PORT=3306/" .env
    sed -i "s/^DB_NAME=.*/DB_NAME=$DB_NAME/" .env
    sed -i "s/^DB_USER=.*/DB_USER=$DB_USER/" .env
    sed -i "s/^DB_PASS=.*/DB_PASS=$DB_PASS/" .env
    
    log "✅ .env atualizado"
else
    warning ".env não encontrado - configure manualmente"
fi

# ====================================
# CONFIGURAR SEGURANÇA MYSQL
# ====================================

echo ""
log "Aplicando configurações de segurança..."

# Script de segurança MySQL
SECURITY_SQL=$(cat << 'EOF'
-- Remover usuários anônimos
DELETE FROM mysql.user WHERE User='';

-- Remover banco de teste
DROP DATABASE IF EXISTS test;
DELETE FROM mysql.db WHERE Db='test' OR Db='test\\_%';

-- Remover acesso remoto do root (produção)
-- DELETE FROM mysql.user WHERE User='root' AND Host NOT IN ('localhost', '127.0.0.1', '::1');

-- Aplicar mudanças
FLUSH PRIVILEGES;
EOF
)

echo "Deseja aplicar configurações de segurança MySQL? (y/N)"
read -r SECURITY_CONFIRM

if [[ "$SECURITY_CONFIRM" =~ ^[Yy]$ ]]; then
    echo "$SECURITY_SQL" | $MYSQL_ROOT
    log "✅ Configurações de segurança aplicadas"
else
    info "Configurações de segurança puladas"
fi

# ====================================
# CONFIGURAR BACKUP
# ====================================

log "Configurando backup..."

# Criar arquivo de credenciais MySQL
MYSQL_CNF="/home/$(whoami)/.mysql_backup.cnf"
cat << EOF > "$MYSQL_CNF"
[client]
user=$DB_USER
password=$DB_PASS
host=localhost
EOF

chmod 600 "$MYSQL_CNF"
log "Credenciais de backup salvas em $MYSQL_CNF"

# Script básico de backup
BACKUP_SCRIPT="/home/$(whoami)/backup_database.sh"
cat << EOF > "$BACKUP_SCRIPT"
#!/bin/bash
# Backup automático do banco $DB_NAME

BACKUP_DIR="/backup/mysql"
DATE=\$(date +%Y%m%d_%H%M%S)
BACKUP_FILE="\$BACKUP_DIR/${DB_NAME}_\$DATE.sql"

# Criar diretório se não existir
mkdir -p \$BACKUP_DIR

# Fazer backup
mysqldump --defaults-file=$MYSQL_CNF $DB_NAME > "\$BACKUP_FILE"

if [ \$? -eq 0 ]; then
    echo "Backup criado: \$BACKUP_FILE"
    
    # Comprimir
    gzip "\$BACKUP_FILE"
    echo "Backup comprimido: \$BACKUP_FILE.gz"
    
    # Manter apenas 7 dias
    find \$BACKUP_DIR -name "${DB_NAME}_*.sql.gz" -mtime +7 -delete
    echo "Backups antigos removidos"
else
    echo "ERRO no backup!"
    exit 1
fi
EOF

chmod +x "$BACKUP_SCRIPT"
log "Script de backup criado: $BACKUP_SCRIPT"

# ====================================
# RELATÓRIO FINAL
# ====================================

echo ""
echo "======================================"
echo "✅ CONFIGURAÇÃO CONCLUÍDA!"
echo "======================================"
echo ""
echo "📊 BANCO DE DADOS:"
echo "   Host: localhost:3306"
echo "   Banco: $DB_NAME"
echo "   Usuário: $DB_USER"
echo "   Senha: $DB_PASS"
echo ""
echo "📁 ARQUIVOS CRIADOS:"
echo "   - Credenciais backup: $MYSQL_CNF"
echo "   - Script backup: $BACKUP_SCRIPT"
echo "   - .env atualizado (backup criado)"
echo ""
echo "🔒 SEGURANÇA:"
echo "   - Usuário com privilégios mínimos"
echo "   - Acesso apenas ao banco específico"
echo "   - Senha forte gerada"
echo ""
echo "💾 BACKUP:"
echo "   - Script manual: $BACKUP_SCRIPT"
echo "   - Para automatizar (CRON):"
echo "     0 2 * * * $BACKUP_SCRIPT"
echo ""
echo "✅ PRÓXIMOS PASSOS:"
echo "   1. Testar aplicação com nova configuração"
echo "   2. Configurar backup automático"
echo "   3. Monitorar logs de banco"
echo ""

info "🎉 Banco configurado com sucesso!"