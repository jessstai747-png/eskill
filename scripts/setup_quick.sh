#!/bin/bash

# ====================================
# CONFIGURAÇÃO RÁPIDA - ML Manager
# ====================================

set -e

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(dirname "$SCRIPT_DIR")"
ENV_FILE="$PROJECT_DIR/.env"

# Cores
GREEN='\033[0;32m'
RED='\033[0;31m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
CYAN='\033[0;36m'
NC='\033[0m'

echo -e "${CYAN}"
echo "======================================"
echo "  🔧 CONFIGURAÇÃO RÁPIDA"
echo "     ML Manager"
echo "======================================"
echo -e "${NC}"

# Menu principal
show_menu() {
    echo ""
    echo "O que você deseja configurar?"
    echo ""
    echo "  1) 📱 Telegram (alertas)"
    echo "  2) ☁️  Google Drive (backup)"
    echo "  3) 🪣 AWS S3 (backup)"
    echo "  4) 📊 Ver status atual"
    echo "  5) 🚪 Sair"
    echo ""
    read -p "Escolha [1-5]: " choice
}

# Configurar Telegram
setup_telegram() {
    echo ""
    echo -e "${BLUE}📱 Configuração do Telegram${NC}"
    echo "=============================="
    echo ""
    echo "Para criar um bot Telegram:"
    echo "  1. Abra @BotFather no Telegram"
    echo "  2. Envie /newbot"
    echo "  3. Siga as instruções e copie o token"
    echo ""
    echo "Para obter seu Chat ID:"
    echo "  1. Envie uma mensagem para @userinfobot"
    echo "  2. Copie o ID retornado"
    echo ""
    
    read -p "Token do Bot: " bot_token
    read -p "Chat ID: " chat_id
    
    if [ -n "$bot_token" ] && [ -n "$chat_id" ]; then
        # Atualizar .env
        sed -i "s|^TELEGRAM_ENABLED=.*|TELEGRAM_ENABLED=true|" "$ENV_FILE"
        sed -i "s|^TELEGRAM_BOT_TOKEN=.*|TELEGRAM_BOT_TOKEN=$bot_token|" "$ENV_FILE"
        sed -i "s|^TELEGRAM_CHAT_ID=.*|TELEGRAM_CHAT_ID=$chat_id|" "$ENV_FILE"
        
        echo ""
        echo -e "${GREEN}✅ Telegram configurado!${NC}"
        
        # Testar
        echo ""
        read -p "Enviar mensagem de teste? [s/N]: " test_msg
        if [ "$test_msg" = "s" ] || [ "$test_msg" = "S" ]; then
            curl -s -X POST "https://api.telegram.org/bot$bot_token/sendMessage" \
                -d chat_id="$chat_id" \
                -d text="✅ ML Manager conectado com sucesso!" \
                -d parse_mode="HTML" > /dev/null
            echo -e "${GREEN}✅ Mensagem enviada!${NC}"
        fi
    else
        echo -e "${RED}❌ Configuração cancelada${NC}"
    fi
}

# Configurar Google Drive
setup_gdrive() {
    echo ""
    echo -e "${BLUE}☁️ Configuração do Google Drive${NC}"
    echo "=================================="
    echo ""
    
    if ! command -v rclone &> /dev/null; then
        echo -e "${RED}❌ rclone não está instalado${NC}"
        echo ""
        echo "Instale com:"
        echo "  curl https://rclone.org/install.sh | sudo bash"
        return
    fi
    
    echo "Iniciando configuração do rclone..."
    echo "Siga as instruções na tela."
    echo ""
    echo "Dicas:"
    echo "  - Name: gdrive"
    echo "  - Storage: 18 (Google Drive)"
    echo "  - Scope: 1 (full access)"
    echo ""
    
    read -p "Pressione Enter para continuar..."
    
    rclone config
    
    # Verificar se foi criado
    if rclone listremotes | grep -q "gdrive:"; then
        echo ""
        echo -e "${GREEN}✅ Google Drive configurado!${NC}"
        
        # Atualizar .env
        sed -i "s|^BACKUP_REMOTE_TYPE=.*|BACKUP_REMOTE_TYPE=rclone|" "$ENV_FILE"
        sed -i "s|^BACKUP_REMOTE_NAME=.*|BACKUP_REMOTE_NAME=gdrive|" "$ENV_FILE"
        
        # Testar
        echo ""
        read -p "Fazer upload de teste? [s/N]: " test_upload
        if [ "$test_upload" = "s" ] || [ "$test_upload" = "S" ]; then
            echo "test" > /tmp/rclone_test.txt
            rclone copy /tmp/rclone_test.txt gdrive:/backups/eskill/
            rm /tmp/rclone_test.txt
            echo -e "${GREEN}✅ Upload de teste concluído!${NC}"
        fi
    fi
}

# Configurar AWS S3
setup_s3() {
    echo ""
    echo -e "${BLUE}🪣 Configuração do AWS S3${NC}"
    echo "==========================="
    echo ""
    
    read -p "Access Key ID: " aws_key
    read -p "Secret Access Key: " aws_secret
    read -p "Região (ex: us-east-1): " aws_region
    read -p "Nome do Bucket: " aws_bucket
    
    if [ -n "$aws_key" ] && [ -n "$aws_secret" ] && [ -n "$aws_bucket" ]; then
        # Configurar via rclone
        rclone config create s3backup s3 \
            provider AWS \
            access_key_id "$aws_key" \
            secret_access_key "$aws_secret" \
            region "${aws_region:-us-east-1}" \
            env_auth false
        
        # Atualizar .env
        sed -i "s|^BACKUP_REMOTE_TYPE=.*|BACKUP_REMOTE_TYPE=s3|" "$ENV_FILE"
        sed -i "s|^BACKUP_REMOTE_NAME=.*|BACKUP_REMOTE_NAME=s3backup|" "$ENV_FILE"
        sed -i "s|^BACKUP_REMOTE_PATH=.*|BACKUP_REMOTE_PATH=$aws_bucket/backups|" "$ENV_FILE"
        
        echo ""
        echo -e "${GREEN}✅ AWS S3 configurado!${NC}"
    else
        echo -e "${RED}❌ Configuração cancelada${NC}"
    fi
}

# Mostrar status
show_status() {
    echo ""
    echo -e "${BLUE}📊 Status Atual${NC}"
    echo "================"
    echo ""
    
    # Telegram
    if grep -q "TELEGRAM_ENABLED=true" "$ENV_FILE"; then
        echo -e "  📱 Telegram: ${GREEN}✅ Configurado${NC}"
    else
        echo -e "  📱 Telegram: ${RED}❌ Não configurado${NC}"
    fi
    
    # Backup remoto
    remote_type=$(grep "^BACKUP_REMOTE_TYPE=" "$ENV_FILE" | cut -d= -f2)
    if [ "$remote_type" != "none" ] && [ -n "$remote_type" ]; then
        remote_name=$(grep "^BACKUP_REMOTE_NAME=" "$ENV_FILE" | cut -d= -f2)
        echo -e "  ☁️  Backup Remoto: ${GREEN}✅ $remote_name ($remote_type)${NC}"
    else
        echo -e "  ☁️  Backup Remoto: ${RED}❌ Não configurado${NC}"
    fi
    
    # Rclone remotes
    echo ""
    echo "  Remotes rclone:"
    if command -v rclone &> /dev/null; then
        remotes=$(rclone listremotes 2>/dev/null)
        if [ -n "$remotes" ]; then
            echo "$remotes" | while read remote; do
                echo -e "    ${GREEN}✅${NC} $remote"
            done
        else
            echo "    Nenhum configurado"
        fi
    else
        echo "    rclone não instalado"
    fi
    
    echo ""
}

# Loop principal
while true; do
    show_menu
    
    case $choice in
        1)
            setup_telegram
            ;;
        2)
            setup_gdrive
            ;;
        3)
            setup_s3
            ;;
        4)
            show_status
            ;;
        5)
            echo ""
            echo -e "${GREEN}👋 Até logo!${NC}"
            exit 0
            ;;
        *)
            echo -e "${RED}Opção inválida${NC}"
            ;;
    esac
done
