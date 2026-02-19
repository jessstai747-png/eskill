#!/usr/bin/env php
<?php
/**
 * Map remaining failing features to available test files
 */

$features = [
    'ITEMS-002' => ['BulkEditorController'],
    'ITEMS-003' => ['ListingBuilderController', 'ListingBuilderService'],
    'ITEMS-004' => ['TechnicalSheetController', 'TechSheetService'],
    'ITEMS-005' => ['EanController', 'EanService'],
    'STOCK-001' => ['StockSyncController'],
    'ORDERS-001' => ['OrderController', 'OrderService'],
    'ORDERS-002' => ['QuestionController', 'QuestionService'],
    'SHIP-001' => ['ShippingController', 'ShippingService'],
    'REPORT-001' => ['AdvancedReportController', 'ReportService'],
    'REPORT-002' => ['FinancialReportController', 'FinancialService'],
    'REPORT-003' => ['ExportController', 'ExportService', 'PdfService'],
    'REPORT-004' => ['automated-reports-worker', 'weekly-report'],
    'NOTIF-001' => ['PushController', 'PushNotificationService'],
    'NOTIF-002' => ['RealTimeNotificationController', 'RealTimeNotificationService'],
    'NOTIF-003' => ['EmailService'],
    'NOTIF-004' => ['WhatsAppController', 'WhatsAppService', 'TelegramService'],
    'HEALTH-001' => ['AccountHealthController', 'AccountHealthService'],
    'HEALTH-002' => ['ErrorMonitoringController', 'ErrorMonitoringService'],
    'HEALTH-003' => ['HealthController'],
    'SEC-001' => ['SecurityController', 'ValidationService'],
    'SEC-002' => ['RateLimitTrackerService'],
    'SEC-003' => ['EncryptionService'],
    'SEC-004' => ['AuditLogService', 'AuditService'],
    'WEBHOOK-001' => ['MercadoLivreWebhookController', 'MercadoLivreWebhookService'],
    'PROMO-001' => ['PromotionController', 'PromotionService'],
    'BRAND-001' => ['BrandCentralController', 'BrandCentralService'],
    'BRAND-002' => ['BrandAnalyzerController', 'BrandAnalyzerService'],
    'MULTI-001' => ['MultiAccountController'],
    'TRENDS-001' => ['TrendsController', 'TrendsService'],
    'CLAIMS-001' => ['ClaimsController', 'ClaimsService'],
    'SETTINGS-001' => ['SettingsController', 'SettingsService'],
    'CACHE-001' => ['CacheService', 'AdvancedRedisCacheService', 'CacheController'],
    'SHOPEE-001' => ['ShopeeController', 'ShopeeService'],
    'ADS-001' => ['AdsController', 'AdsService'],
    'AI-008' => ['MercadoLivreAIIntegrationService', 'MLAIIntegrationController', 'AIProviderManager'],
    'AI-009' => ['MercadoLivreAIIntegrationService', 'VersioningService'],
];

// Test files already confirmed passing in batch test
$alreadyPassing = [
    'ValidationServiceTest', 'EncryptionServiceTest', 'SecurityServiceTest',
    'TechSheetServiceTest', 'CacheServiceTest', 'SeoAnalyzerServiceTest',
    'PricingStrategyServiceTest', 'EmailServiceTest', 'LoggerServiceTest',
    'JwtServiceTest', 'UnifiedTokenRefreshServiceTest', 'MercadoLivreClientExtendedTest',
    'MercadoLivreClientHeaderTokenTest', 'RealIntegrationsStructureTest',
    'TechSheetBenchmarkServiceTest', 'UserManagementControllerTest',
    'SeoControllerTest', 'BaseControllerActiveAccountIdTest',
    'AIProviderManagerTest', 'DescriptionOptimizerTest', 'TechSheetOptimizerTest',
    'TitleOptimizerTest', 'CategoriesApiServiceTest',
    'CompatibilityServiceTest', 'ContextInjectorServiceTest', 'CoverageAndHiddenFieldsTest',
    'KeywordSourceServiceTest', 'LongTailGeneratorServiceTest', 'SearchCoverageServiceTest',
    'SEOOptimizerServiceTest', 'TokenManagerTest',
    // Fixed
    'AIPredictiveAnalyticsServiceTest', 'FinancialServiceTest',
    'CompetitorIntelligenceServiceTest', 'MercadoLivreAIIntegrationHealthTest',
    'AdvancedPricingEngineTest', 'SmartQAServiceTest',
];

foreach ($features as $id => $sources) {
    $hasPassingTest = false;
    $testName = '';
    foreach ($alreadyPassing as $test) {
        foreach ($sources as $src) {
            $srcClean = str_replace(['Controller', 'Service'], '', $src);
            if (stripos($test, $srcClean) !== false) {
                $hasPassingTest = true;
                $testName = $test;
                break 2;
            }
        }
    }
    $status = $hasPassingTest ? "COVERED ($testName)" : "NEEDS TEST";
    echo "$id: $status\n";
}
