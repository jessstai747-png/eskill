#!/bin/bash

# Script de Setup Completo do Banco de Dados SEO
# Este script cria todas as tabelas necessárias para o sistema funcionar

set -e

echo "🚀 Iniciando setup do banco de dados SEO..."

# Carregar variáveis de ambiente
if [ -f .env ]; then
    export $(cat .env | grep -v '^#' | xargs)
else
    echo "❌ Arquivo .env não encontrado!"
    exit 1
fi

# Verificar variáveis obrigatórias
if [ -z "$DB_HOST" ] || [ -z "$DB_NAME" ] || [ -z "$DB_USER" ] || [ -z "$DB_PASS" ]; then
    echo "❌ Variáveis de banco de dados não configuradas no .env"
    exit 1
fi

echo "📊 Conectando ao banco: $DB_NAME@$DB_HOST"

# Executar migrações SEO
echo "📝 Executando migrações SEO..."

mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/migrations/2026_01_22_create_seo_synonyms_tables.sql
echo "✓ Tabelas de sinônimos criadas"

mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/migrations/2026_01_22_create_seo_monitoring_schedule.sql
echo "✓ Tabela de monitoramento criada"

mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/migrations/2026_01_23_create_seo_strategies_tables.sql
echo "✓ Tabelas de estratégias criadas"

mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/migrations/2026_01_24_create_seo_hidden_attributes_table.sql
echo "✓ Tabela de atributos ocultos criada"

mysql -h"$DB_HOST" -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" < database/migrations/2026_01_01_000002_create_seo_optimizations_table.sql
echo "✓ Tabela de otimizações criada"

echo ""
echo "✅ Setup do banco de dados concluído com sucesso!"
echo ""
echo "📋 Próximos passos:"
echo "1. Configure suas credenciais da API do Mercado Livre no .env"
echo "2. Configure sua chave de API de IA (OpenAI) no .env"
echo "3. Execute: composer install"
echo "4. Execute: php vendor/bin/phpunit"
echo ""
