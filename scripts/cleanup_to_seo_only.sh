#!/bin/bash

# Script de limpeza para transformar sistema em SEO-Only
# Data: 08/01/2026
# IMPORTANTE: Backup já foi criado antes de executar este script

# set -e  # Continue even on error to complete cleanup

echo "🎯 INICIANDO LIMPEZA PARA SISTEMA SEO-ONLY"
echo "=========================================="
echo ""

# Verificar se backup existe
backup_count=$(ls backup_pre_seo_cleanup_*.tar.gz 2>/dev/null | wc -l)
if [ "$backup_count" -eq 0 ]; then
    echo "❌ ERRO: Backup não encontrado!"
    echo "Execute: tar -czf backup_pre_seo_cleanup_\$(date +%Y%m%d).tar.gz ..."
    exit 1
fi

echo "✅ Backup encontrado:"
ls -lh backup_pre_seo_cleanup_*.tar.gz | tail -1
echo ""

# Contador
removed_count=0

echo "📦 FASE 1: Removendo Módulo Mercado Livre..."
echo "-------------------------------------------"

files_to_remove=(
    "app/Services/MercadoLivreAuthService.php"
    "app/Services/MercadoLivreService.php"
    "app/Services/MercadoLivreApiLogService.php"
    "app/Services/MercadoLivreClient.php"
    "app/Services/MercadoLivreWebhookService.php"
    "app/Services/MercadoLivreAccountService.php"
    "app/Services/MercadoLivreItemService.php"
    "app/Services/MercadoLivreOrderService.php"
    "app/Services/MercadoLivreMessagingService.php"
    "app/Jobs/MercadoLivreSyncJob.php"
    "bin/sync-ml.php"
    "bin/test-ml-integration.php"
    "database/migrations/100_create_ml_messages_table.sql"
    "database/migrations/101_create_ml_webhooks_and_logs_table.sql"
    "public/webhook-ml.php"
    "MERCADOLIVRE_INTEGRATION.md"
    "INTEGRACAO_MERCADOLIVRE_SUMARIO.md"
    "cron-mercadolivre.txt"
)

for file in "${files_to_remove[@]}"; do
    if [ -f "$file" ]; then
        rm -f "$file"
        echo "  ✓ Removido: $file"
        ((removed_count++))
    fi
done

echo ""
echo "📹 FASE 2: Removendo Módulo Video Creation..."
echo "-------------------------------------------"

if [ -d "app/Services/VideoCreation" ]; then
    rm -rf "app/Services/VideoCreation"
    echo "  ✓ Removido: app/Services/VideoCreation/"
    ((removed_count++))
fi

files_video=(
    "bin/create-video.php"
    "bin/test-video-creation.php"
    "VIDEO_CREATION_SYSTEM.md"
    "VIDEO_CREATION_SUMARIO.md"
    "VIDEO_CREATION_SYSTEM_PRODUCT_BACKLOG.md"
)

for file in "${files_video[@]}"; do
    if [ -f "$file" ]; then
        rm -f "$file"
        echo "  ✓ Removido: $file"
        ((removed_count++))
    fi
done

echo ""
echo "🛒 FASE 3: Removendo Módulos E-commerce não-SEO..."
echo "-------------------------------------------"

files_ecommerce=(
    "app/Services/OrderService.php"
    "app/Services/ReturnService.php"
    "app/Services/ClaimsService.php"
    "app/Services/MessageService.php"
    "app/Services/FinancialService.php"
    "app/Services/RepricingService.php"
    "app/Services/PriceHistoryService.php"
    "app/Services/PricingStrategyService.php"
    "app/Services/MercadoPagoService.php"
)

for file in "${files_ecommerce[@]}"; do
    if [ -f "$file" ]; then
        rm -f "$file"
        echo "  ✓ Removido: $file"
        ((removed_count++))
    fi
done

echo ""
echo "🔢 FASE 4: Removendo Módulos EAN..."
echo "-------------------------------------------"

files_ean=(
    "app/Services/EanService.php"
    "app/Services/EanReportService.php"
    "app/Services/EanNotificationService.php"
    "app/Services/EanIntegrationService.php"
)

for file in "${files_ean[@]}"; do
    if [ -f "$file" ]; then
        rm -f "$file"
        echo "  ✓ Removido: $file"
        ((removed_count++))
    fi
done

echo ""
echo "📊 FASE 5: Removendo Módulos de Monitoramento..."
echo "-------------------------------------------"

files_monitoring=(
    "app/Services/AdvancedHealthCheckService.php"
    "app/Services/PerformanceMetricsService.php"
    "app/Services/PerformanceMonitoringService.php"
    "app/Services/ErrorTrackingService.php"
    "app/Services/AdvancedAnalyticsService.php"
)

for file in "${files_monitoring[@]}"; do
    if [ -f "$file" ]; then
        rm -f "$file"
        echo "  ✓ Removido: $file"
        ((removed_count++))
    fi
done

echo ""
echo "📧 FASE 6: Removendo Módulos de Notificação..."
echo "-------------------------------------------"

files_notification=(
    "app/Services/TelegramService.php"
    "app/Services/RealTimeNotificationService.php"
    "app/Services/NotificationBroadcaster.php"
    "app/Services/EmailSchedulerService.php"
)

for file in "${files_notification[@]}"; do
    if [ -f "$file" ]; then
        rm -f "$file"
        echo "  ✓ Removido: $file"
        ((removed_count++))
    fi
done

echo ""
echo "🔧 FASE 7: Removendo Outros Módulos Não-SEO..."
echo "-------------------------------------------"

files_others=(
    "app/Services/FlexService.php"
    "app/Services/GapHunterService.php"
    "app/Services/ListingBuilderService.php"
    "app/Services/ExportService.php"
    "app/Services/PollingService.php"
    "app/Services/ProxyService.php"
    "app/Services/WebhookProcessorService.php"
    "app/Services/CatalogCloneMonitoringService.php"
    "app/Services/CompetitorMonitoringService.php"
    "app/Services/AutomationOrchestratorService.php"
    "app/Services/AutonomousAgentService.php"
    "app/Services/LearningPipelineService.php"
    "app/Services/DynamicTemplateService.php"
    "app/Services/CompatibilityService.php"
    "app/Services/SentimentService.php"
)

for file in "${files_others[@]}"; do
    if [ -f "$file" ]; then
        rm -f "$file"
        echo "  ✓ Removido: $file"
        ((removed_count++))
    fi
done

# Remover diretório Agent se existir
if [ -d "app/Services/Agent" ]; then
    rm -rf "app/Services/Agent"
    echo "  ✓ Removido: app/Services/Agent/"
    ((removed_count++))
fi

echo ""
echo "📄 FASE 8: Removendo Documentação Antiga..."
echo "-------------------------------------------"

if [ -f "README_SYSTEMS.md" ]; then
    rm -f "README_SYSTEMS.md"
    echo "  ✓ Removido: README_SYSTEMS.md"
    ((removed_count++))
fi

echo ""
echo "🧹 FASE 9: Limpando Cache e Temporários..."
echo "-------------------------------------------"

# Limpar jobs de vídeo
if [ -d "storage/temp" ]; then
    find storage/temp -name "job_*" -type d -exec rm -rf {} + 2>/dev/null || true
    echo "  ✓ Removido: storage/temp/job_*"
fi

# Limpar cache TTS
if [ -d "storage/cache/tts" ]; then
    rm -rf "storage/cache/tts"
    echo "  ✓ Removido: storage/cache/tts/"
fi

# Limpar assets de vídeo
if [ -d "storage/assets" ]; then
    # Manter estrutura, apenas limpar conteúdo
    find storage/assets -type f -name "*.png" -o -name "*.jpg" -o -name "*.mp4" | xargs rm -f 2>/dev/null || true
    echo "  ✓ Limpado: storage/assets/"
fi

echo ""
echo "=========================================="
echo "✅ LIMPEZA CONCLUÍDA!"
echo "=========================================="
echo ""
echo "📊 Estatísticas:"
echo "  • Arquivos/diretórios removidos: $removed_count"
echo "  • Backup disponível: backup_pre_seo_cleanup_*.tar.gz"
echo ""
echo "📋 Próximos passos:"
echo "  1. Revisar arquivos removidos"
echo "  2. Testar sistema SEO"
echo "  3. Atualizar documentação"
echo ""
echo "⚠️  Para reverter, restaure o backup:"
echo "  tar -xzf backup_pre_seo_cleanup_*.tar.gz"
echo ""
