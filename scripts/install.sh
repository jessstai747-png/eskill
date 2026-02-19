#!/bin/bash

# Script de Instalação Automatizada - Mercado Livre Manager
# Execute: bash scripts/install.sh

set -e

echo "🚀 Instalando Mercado Livre Manager..."
echo ""

# Cores
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m' # No Color

# Verificar PHP
echo -e "${YELLOW}Verificando PHP...${NC}"
if ! command -v php &> /dev/null; then
    echo -e "${RED}PHP não encontrado. Instale PHP 8.0 ou superior.${NC}"
    exit 1
fi

PHP_VERSION=$(php -r 'echo PHP_MAJOR_VERSION;')
if [ "$PHP_VERSION" -lt 8 ]; then
    echo -e "${RED}PHP 8.0 ou superior é necessário. Versão atual: $(php -v | head -n 1)${NC}"
    exit 1
fi

echo -e "${GREEN}✓ PHP encontrado: $(php -v | head -n 1)${NC}"

# Verificar Composer
echo -e "${YELLOW}Verificando Composer...${NC}"
if ! command -v composer &> /dev/null; then
    echo -e "${RED}Composer não encontrado. Instalando...${NC}"
    curl -sS https://getcomposer.org/installer | php
    sudo mv composer.phar /usr/local/bin/composer
fi

echo -e "${GREEN}✓ Composer encontrado${NC}"

# Instalar dependências
echo -e "${YELLOW}Instalando dependências do Composer...${NC}"
composer install --no-interaction
echo -e "${GREEN}✓ Dependências instaladas${NC}"

# Criar .env se não existir
if [ ! -f .env ]; then
    echo -e "${YELLOW}Criando arquivo .env...${NC}"
    cp .env.example .env
    
    # Gerar chave
    APP_KEY=$(php -r "echo bin2hex(random_bytes(32));")
    
    # Atualizar .env com chave gerada
    if [[ "$OSTYPE" == "darwin"* ]]; then
        # macOS
        sed -i '' "s/APP_KEY=$/APP_KEY=$APP_KEY/" .env
    else
        # Linux
        sed -i "s/APP_KEY=$/APP_KEY=$APP_KEY/" .env
    fi
    
    echo -e "${GREEN}✓ Arquivo .env criado${NC}"
    echo -e "${YELLOW}⚠ Configure o arquivo .env com suas credenciais antes de continuar!${NC}"
else
    echo -e "${GREEN}✓ Arquivo .env já existe${NC}"
fi

# Criar diretórios necessários
echo -e "${YELLOW}Criando diretórios...${NC}"
mkdir -p storage/cache
mkdir -p storage/logs
chmod -R 775 storage
echo -e "${GREEN}✓ Diretórios criados${NC}"

# Verificar MySQL
echo -e "${YELLOW}Verificando MySQL...${NC}"
if ! command -v mysql &> /dev/null; then
    echo -e "${YELLOW}MySQL não encontrado. Você precisará configurá-lo manualmente.${NC}"
else
    echo -e "${GREEN}✓ MySQL encontrado${NC}"
    echo -e "${YELLOW}Para criar o banco de dados, execute:${NC}"
    echo "mysql -u root -p < database/migrations/000_install_all.sql"
fi

echo ""
echo -e "${GREEN}✅ Instalação concluída!${NC}"
echo ""
echo "Próximos passos:"
echo "1. Configure o arquivo .env com suas credenciais"
echo "2. Crie o banco de dados e execute as migrations"
echo "3. Configure seu servidor web (Apache/Nginx)"
echo "4. Acesse: http://localhost/eskill/public/dashboard"
echo ""
echo "Documentação:"
echo "- README.md - Visão geral"
echo "- INSTALL.md - Guia de instalação detalhado"
echo "- docs/API_DOCUMENTATION.md - Documentação da API"
echo "- docs/USER_MANUAL.md - Manual do usuário"
echo ""

