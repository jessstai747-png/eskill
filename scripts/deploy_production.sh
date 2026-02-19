#!/bin/bash
# Script de Deploy para Produção
# Uso: ./deploy_production.sh

set -e  # Parar em caso de erro

echo "🚀 Iniciando deploy para produção..."

# 1. Manutenção
echo "1. Ativando modo manutenção..."
touch storage/maintenance.lock

# 2. Atualizar código
echo "2. Atualizando código..."
git pull origin main  # ou master

# 3. Instalar dependências
echo "3. Instalando dependências..."
composer install --no-dev --optimize-autoloader

# 4. Executar migrations
echo "4. Executando migrations..."
php scripts/migrate.php

# 5. Limpar cache
echo "5. Limpando cache..."
php -r "require 'vendor/autoload.php'; (new App\Services\CacheService())->clear();"

# 6. Otimizar autoloader
echo "6. Otimizando autoloader..."
composer dump-autoload --optimize

# 7. Verificar permissões
echo "7. Verificando permissões..."
chmod -R 755 storage
chmod -R 755 bootstrap/cache 2>/dev/null || true

# 8. Desativar manutenção
echo "8. Desativando modo manutenção..."
rm -f storage/maintenance.lock

echo "✅ Deploy concluído com sucesso!"
