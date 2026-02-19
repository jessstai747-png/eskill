#!/bin/bash
# ============================================================================
# Script de Diagnóstico - Clonador de Anúncios em Lote
# ============================================================================
# 
# Uso: bash bin/clone-diagnostics.sh
# Saída: Relatório completo de status e saúde do sistema
#
# ============================================================================

set -e

# Cores para output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}============================================================================${NC}"
echo -e "${BLUE}  DIAGNÓSTICO DO SISTEMA DE CLONAGEM EM LOTE${NC}"
echo -e "${BLUE}============================================================================${NC}"
echo ""
echo "Data/Hora: $(date '+%Y-%m-%d %H:%M:%S')"
echo ""

# ============================================================================
# 1. VERIFICAR AMBIENTE
# ============================================================================
echo -e "${YELLOW}[1/10] Verificando Ambiente...${NC}"

# PHP Version
PHP_VERSION=$(php -v | head -n 1)
echo "✓ PHP: $PHP_VERSION"

# Composer
if command -v composer &> /dev/null; then
    COMPOSER_VERSION=$(composer --version 2>&1 | head -n 1)
    echo "✓ Composer: $COMPOSER_VERSION"
else
    echo -e "${RED}✗ Composer não encontrado${NC}"
fi

# MySQL
if command -v mysql &> /dev/null; then
    MYSQL_VERSION=$(mysql --version)
    echo "✓ MySQL: $MYSQL_VERSION"
else
    echo -e "${RED}✗ MySQL client não encontrado${NC}"
fi

echo ""

# ============================================================================
# 2. VERIFICAR ARQUIVOS CRÍTICOS
# ============================================================================
echo -e "${YELLOW}[2/10] Verificando Arquivos Críticos...${NC}"

FILES=(
    "app/Services/CatalogCloneService.php"
    "app/Services/CloneTemplateService.php"
    "app/Services/ClonePostActionsService.php"
    "app/Services/CloneMetricsService.php"
    "app/Controllers/CatalogCloneController.php"
    "bin/catalog-clone-worker.php"
    "bin/clone-post-actions-worker.php"
)

for file in "${FILES[@]}"; do
    if [ -f "$file" ]; then
        echo "✓ $file"
    else
        echo -e "${RED}✗ $file (AUSENTE)${NC}"
    fi
done

echo ""

# ============================================================================
# 3. VERIFICAR PERMISSÕES
# ============================================================================
echo -e "${YELLOW}[3/10] Verificando Permissões...${NC}"

DIRS=(
    "storage/logs"
    "storage/locks"
    "storage/cache"
)

for dir in "${DIRS[@]}"; do
    if [ -d "$dir" ] && [ -w "$dir" ]; then
        echo "✓ $dir (gravável)"
    else
        echo -e "${RED}✗ $dir (não gravável ou inexistente)${NC}"
    fi
done

# Workers
if [ -x "bin/catalog-clone-worker.php" ]; then
    echo "✓ bin/catalog-clone-worker.php (executável)"
else
    echo -e "${RED}✗ bin/catalog-clone-worker.php (não executável)${NC}"
fi

if [ -x "bin/clone-post-actions-worker.php" ]; then
    echo "✓ bin/clone-post-actions-worker.php (executável)"
else
    echo -e "${RED}✗ bin/clone-post-actions-worker.php (não executável)${NC}"
fi

echo ""

# ============================================================================
# 4. VERIFICAR CONFIGURAÇÃO (.env)
# ============================================================================
echo -e "${YELLOW}[4/10] Verificando Configuração...${NC}"

if [ -f ".env" ]; then
    echo "✓ .env existe"
    
    # Verificar variáveis críticas
    if grep -q "^DB_HOST=" .env; then
        DB_HOST=$(grep "^DB_HOST=" .env | cut -d '=' -f2)
        echo "  DB_HOST: $DB_HOST"
    else
        echo -e "${RED}  ✗ DB_HOST não definido${NC}"
    fi
    
    if grep -q "^DB_DATABASE=" .env; then
        DB_DATABASE=$(grep "^DB_DATABASE=" .env | cut -d '=' -f2)
        echo "  DB_DATABASE: $DB_DATABASE"
    else
        echo -e "${RED}  ✗ DB_DATABASE não definido${NC}"
    fi
else
    echo -e "${RED}✗ .env NÃO ENCONTRADO${NC}"
fi

echo ""

# ============================================================================
# 5. VERIFICAR CRONTAB
# ============================================================================
echo -e "${YELLOW}[5/10] Verificando Crontab...${NC}"

CRON_COUNT=$(crontab -l 2>/dev/null | grep -c "catalog-clone-worker" || true)

if [ "$CRON_COUNT" -gt 0 ]; then
    echo "✓ Crontab configurado ($CRON_COUNT entradas)"
    echo ""
    echo "Entradas relacionadas:"
    crontab -l 2>/dev/null | grep "catalog-clone-worker" || true
else
    echo -e "${RED}✗ Crontab NÃO CONFIGURADO${NC}"
    echo "  Execute: crontab -e"
    echo "  Veja: crontab.catalog-clone.example"
fi

echo ""

# ============================================================================
# 6. VERIFICAR BANCO DE DADOS
# ============================================================================
echo -e "${YELLOW}[6/10] Verificando Banco de Dados...${NC}"

# Carregar credenciais do .env
if [ -f ".env" ]; then
    source <(grep "^DB_" .env | sed 's/ *= */=/g')
    
    # Testar conexão
    if mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "SELECT 1" &> /dev/null; then
        echo "✓ Conexão com banco de dados OK"
        
        # Verificar tabelas
        TABLES=(
            "catalog_clone_jobs"
            "catalog_clone_job_items"
            "clone_templates"
            "clone_post_actions_log"
            "clone_metrics"
            "cloned_items"
        )
        
        for table in "${TABLES[@]}"; do
            COUNT=$(mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -N -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$DB_DATABASE' AND table_name='$table'" 2>/dev/null || echo "0")
            if [ "$COUNT" -eq 1 ]; then
                echo "✓ Tabela $table existe"
            else
                echo -e "${RED}✗ Tabela $table NÃO EXISTE${NC}"
            fi
        done
        
    else
        echo -e "${RED}✗ Falha na conexão com banco de dados${NC}"
    fi
else
    echo -e "${RED}✗ .env não encontrado, não é possível testar conexão${NC}"
fi

echo ""

# ============================================================================
# 7. VERIFICAR JOBS ATIVOS
# ============================================================================
echo -e "${YELLOW}[7/10] Verificando Jobs Ativos...${NC}"

if [ -f ".env" ]; then
    source <(grep "^DB_" .env | sed 's/ *= */=/g')
    
    echo "Jobs por status:"
    mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "
        SELECT status, COUNT(*) as count
        FROM catalog_clone_jobs
        GROUP BY status
    " 2>/dev/null || echo -e "${RED}Erro ao consultar jobs${NC}"
    
    echo ""
    echo "Jobs em progresso:"
    mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "
        SELECT job_id, 
               total_items, 
               processed_items,
               ROUND(processed_items / total_items * 100, 2) as progress_pct,
               TIMESTAMPDIFF(MINUTE, started_at, NOW()) as running_minutes
        FROM catalog_clone_jobs
        WHERE status IN ('processing', 'queued')
        LIMIT 10
    " 2>/dev/null || echo "Nenhum job em progresso"
    
    echo ""
    echo "Jobs travados (>30 min sem update):"
    STUCK_COUNT=$(mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -N -e "
        SELECT COUNT(*)
        FROM catalog_clone_jobs
        WHERE status = 'processing'
        AND updated_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    " 2>/dev/null || echo "0")
    
    if [ "$STUCK_COUNT" -gt 0 ]; then
        echo -e "${RED}⚠️  $STUCK_COUNT job(s) travado(s)${NC}"
        echo "   Execute: php bin/catalog-clone-worker.php --recover-stuck"
    else
        echo "✓ Nenhum job travado"
    fi
fi

echo ""

# ============================================================================
# 8. VERIFICAR LOGS RECENTES
# ============================================================================
echo -e "${YELLOW}[8/10] Verificando Logs Recentes...${NC}"

LOG_FILES=(
    "storage/logs/catalog-clone-worker.log"
    "storage/logs/clone-post-actions-worker.log"
    "storage/logs/cron-catalog-clone.log"
)

for log in "${LOG_FILES[@]}"; do
    if [ -f "$log" ]; then
        SIZE=$(du -h "$log" | cut -f1)
        LAST_MODIFIED=$(stat -c %y "$log" 2>/dev/null | cut -d'.' -f1 || stat -f "%Sm" "$log" 2>/dev/null)
        echo "✓ $log ($SIZE, última modificação: $LAST_MODIFIED)"
        
        # Verificar erros recentes
        ERROR_COUNT=$(tail -100 "$log" | grep -ci "error" || true)
        if [ "$ERROR_COUNT" -gt 0 ]; then
            echo -e "  ${YELLOW}⚠️  $ERROR_COUNT erro(s) nas últimas 100 linhas${NC}"
        fi
    else
        echo -e "${YELLOW}⚠️  $log não encontrado (pode ser normal se nunca executou)${NC}"
    fi
done

echo ""

# ============================================================================
# 9. VERIFICAR MÉTRICAS
# ============================================================================
echo -e "${YELLOW}[9/10] Verificando Métricas (últimos 7 dias)...${NC}"

if [ -f ".env" ]; then
    source <(grep "^DB_" .env | sed 's/ *= */=/g')
    
    echo "Taxa de sucesso:"
    mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "
        SELECT 
            COUNT(*) as total_jobs,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed,
            ROUND(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) / COUNT(*) * 100, 2) as success_rate_pct
        FROM catalog_clone_jobs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    " 2>/dev/null || echo -e "${RED}Erro ao consultar métricas${NC}"
    
    echo ""
    echo "Templates mais usados:"
    mysql -h"$DB_HOST" -u"$DB_USERNAME" -p"$DB_PASSWORD" "$DB_DATABASE" -e "
        SELECT name, usage_count, is_system
        FROM clone_templates
        ORDER BY usage_count DESC
        LIMIT 5
    " 2>/dev/null || echo -e "${RED}Erro ao consultar templates${NC}"
fi

echo ""

# ============================================================================
# 10. TESTAR WORKERS
# ============================================================================
echo -e "${YELLOW}[10/10] Testando Workers...${NC}"

echo "Testando catalog-clone-worker.php..."
if timeout 5 php bin/catalog-clone-worker.php --once --verbose 2>&1 | head -5; then
    echo "✓ Worker executou sem erros"
else
    echo -e "${RED}✗ Worker falhou ou timeout${NC}"
fi

echo ""
echo "Testando clone-post-actions-worker.php..."
if timeout 5 php bin/clone-post-actions-worker.php --once 2>&1 | head -5; then
    echo "✓ Worker executou sem erros"
else
    echo -e "${RED}✗ Worker falhou ou timeout${NC}"
fi

echo ""

# ============================================================================
# RESUMO FINAL
# ============================================================================
echo -e "${BLUE}============================================================================${NC}"
echo -e "${GREEN}  DIAGNÓSTICO COMPLETO${NC}"
echo -e "${BLUE}============================================================================${NC}"
echo ""
echo "Para mais detalhes, verifique os logs em storage/logs/"
echo "Para suporte, envie este relatório para: suporte@eskill.com.br"
echo ""
echo -e "${BLUE}============================================================================${NC}"
