<?php

use App\Controllers\CatalogCloneController;
use App\Controllers\CloneNotificationController;
use App\Controllers\CloneABTestingController;
use App\Controllers\CloneAutomationController;
use App\Controllers\CloneSellerRecommendationController;
use App\Controllers\CloneROIAnalysisController;
use App\Controllers\CloneSEOIntegrationController;
use App\Controllers\CloneManagementController;
use App\Controllers\CatalogController;
use App\Controllers\CategoryController;
use App\Controllers\SearchController;
use App\Controllers\ItemController;
use App\Controllers\ExportController;
use App\Controllers\BackupController;
use App\Controllers\StatisticsController;
use App\Controllers\AnalyticsController;
use App\Controllers\NotificationController;
use App\Controllers\SyncController;
use App\Controllers\DashboardController;
use App\Controllers\DashboardApiController;
use App\Controllers\OrderController;
use App\Controllers\AlertController;
use App\Controllers\CompetitorController;
use App\Controllers\OpportunityController;
use App\Controllers\AuditController;
use App\Controllers\JobController;
use App\Controllers\ReportController;
use App\Controllers\PollingController;
use App\Controllers\CompatibilityController;
use App\Controllers\QuestionController;
use App\Controllers\SEOToolsController;
use App\Controllers\DeepResearchController;
use App\Controllers\AgentController;
use App\Controllers\SecurityController;
use App\Controllers\PerformanceController;
use App\Controllers\AdvancedReportController;
use App\Controllers\PdfController;
use App\Controllers\BrandAnalyzerController;
use App\Controllers\PushController;
use App\Controllers\RealTimeNotificationController;
use App\Controllers\HealthController;
use App\Controllers\ApiTokenController;
use App\Controllers\EanController;
use App\Controllers\MessageController;
use App\Controllers\SEOKillerController;
use App\Controllers\TechnicalSheetController;
use App\Controllers\TokenDashboardController;
use App\Controllers\MultiAccountController;
use App\Controllers\BrandCentralController;
use App\Controllers\TrendsController;
use App\Controllers\InventoryAdvancedController;
use App\Controllers\MessagingController;
use App\Controllers\DynamicPricingController;
use App\Controllers\AIPredictionsController;
use App\Controllers\ChatbotAIController;
use App\Controllers\AIOptimizationController;
use App\Controllers\ErrorMonitoringController;
use App\Controllers\CompetitorMonitorController;
use App\Controllers\AIController;
use App\Controllers\AuthMonitorApiController;
use App\Controllers\AuthApiController;
use App\Controllers\AuthController;
use App\Controllers\RenderController;
use App\Controllers\BrevoIntegrationController;
use App\Controllers\UserController;
use App\Controllers\OnboardingController;
use App\Controllers\MarketDataController;
use App\Controllers\ProxyController;

/** @var \App\Router $router */

// ========================================
// 🛡️ Auth Failure Monitor API
// ========================================
$router->get('api/auth-monitor/status', AuthMonitorApiController::class, 'getStatus');
$router->get('api/auth-monitor/blocked-ips', AuthMonitorApiController::class, 'getBlockedIPs');
$router->get('api/auth-monitor/failures', AuthMonitorApiController::class, 'getFailures');
$router->get('api/auth-monitor/stats', AuthMonitorApiController::class, 'getStatistics');
$router->get('api/auth-monitor/ip/{ip}', AuthMonitorApiController::class, 'getIPDetails');
$router->post('api/auth-monitor/block-ip', AuthMonitorApiController::class, 'blockIP');
$router->delete('api/auth-monitor/unblock-ip/{ip}', AuthMonitorApiController::class, 'unblockIP');

// ========================================
// 🏠 Dashboard API - Dados Reais
// ========================================
$router->get('api/dashboard/search-suggestions', DashboardApiController::class, 'searchSuggestions');
$router->get('api/dashboard/recent-activity', DashboardApiController::class, 'recentActivity');
$router->get('api/dashboard/user-statistics', DashboardApiController::class, 'userStatistics');
$router->get('api/dashboard/notifications', DashboardApiController::class, 'notifications');
$router->get('api/dashboard/ai-insights', DashboardApiController::class, 'aiInsights');
$router->get('api/dashboard/menu-items', DashboardApiController::class, 'menuItems');
$router->get('api/dashboard/recent-documents', DashboardApiController::class, 'recentDocuments');
$router->get('api/dashboard/system-analytics', DashboardApiController::class, 'systemAnalytics');
$router->get('api/dashboard/team-status', DashboardApiController::class, 'teamStatus');
$router->get('api/dashboard/audit-trail', DashboardApiController::class, 'auditTrail');
$router->get('api/dashboard/predictive-search', DashboardApiController::class, 'predictiveSearch');
$router->get('api/menu-items', DashboardApiController::class, 'menuItems'); // Alias para compatibilidade

// Authentication endpoints (API)
$router->post('api/auth/login', AuthApiController::class, 'login');
$router->post('api/auth/refresh', AuthApiController::class, 'refresh');
$router->post('api/auth/logout', AuthApiController::class, 'logout');
$router->get('api/auth/status', AuthApiController::class, 'status');
$router->get('api/accounts/{accountId}/sync/status', AuthController::class, 'getSyncStatus');

// User preferences (theme)
$router->post('api/user/theme', UserController::class, 'updateTheme');
$router->post('api/user/profile', UserController::class, 'updateProfile');
$router->post('api/user/password', UserController::class, 'changePassword');
$router->post('api/user/2fa/disable', UserController::class, 'disable2fa');
$router->post('api/user/sessions/logout-all', UserController::class, 'logoutAllSessions');
$router->get('api/user/info', UserController::class, 'me');
$router->post('api/user/activity', UserController::class, 'activity');

// Onboarding & guided tours
$router->post('api/onboarding/complete', OnboardingController::class, 'complete');
$router->post('api/tours/complete', OnboardingController::class, 'completeTour');

// Render endpoints (E2E Testing)
$router->post('api/render', RenderController::class, 'create');
$router->get('api/render/{jobId}', RenderController::class, 'status');
$router->delete('api/render/cleanup', RenderController::class, 'cleanup');

// ========================================
// 📩 Integrações - Brevo (Marketing API)
// ========================================
$router->get('api/integrations/brevo/health', BrevoIntegrationController::class, 'health');
$router->get('api/integrations/brevo/status', BrevoIntegrationController::class, 'status');
$router->get('api/integrations/brevo/contacts', BrevoIntegrationController::class, 'listContacts');
$router->post('api/integrations/brevo/contacts', BrevoIntegrationController::class, 'createContact');
$router->get('api/integrations/brevo/contacts/{email}', BrevoIntegrationController::class, 'getContact');
$router->put('api/integrations/brevo/contacts/{email}', BrevoIntegrationController::class, 'updateContact');
$router->delete('api/integrations/brevo/contacts/{email}', BrevoIntegrationController::class, 'deleteContact');
$router->get('api/integrations/brevo/lists', BrevoIntegrationController::class, 'listLists');
$router->post('api/integrations/brevo/lists', BrevoIntegrationController::class, 'createList');
$router->get('api/integrations/brevo/lists/{listId}', BrevoIntegrationController::class, 'getList');
$router->put('api/integrations/brevo/lists/{listId}', BrevoIntegrationController::class, 'updateList');
$router->delete('api/integrations/brevo/lists/{listId}', BrevoIntegrationController::class, 'deleteList');
$router->post('api/integrations/brevo/lists/{listId}/contacts/add', BrevoIntegrationController::class, 'addContactsToList');
$router->post('api/integrations/brevo/lists/{listId}/contacts/remove', BrevoIntegrationController::class, 'removeContactsFromList');
$router->post('api/integrations/brevo/sync/lists', BrevoIntegrationController::class, 'syncLists');
$router->post('api/integrations/brevo/sync/contacts', BrevoIntegrationController::class, 'syncContacts');
$router->post('api/integrations/brevo/sync/all', BrevoIntegrationController::class, 'syncAll');


// ========================================
// 🧠 SEO Phase 1: Synonyms & Semantics
// ========================================
$router->get('api/seo/synonyms/{categoryId}', \App\Controllers\SeoSynonymsController::class, 'getHierarchy');
$router->post('api/seo/synonyms/expand', \App\Controllers\SeoSynonymsController::class, 'expand');
$router->post('api/seo/synonyms/model', \App\Controllers\SeoSynonymsController::class, 'generateModel');
$router->post('api/seo/score/calculate', \App\Controllers\SeoSynonymsController::class, 'calculateScore');
$router->get('api/seo/contexts/{categoryId}', \App\Controllers\SeoSynonymsController::class, 'getContexts');

// ========================================
// 📊 SEO Phase 2: Keyword Distribution
// ========================================
$router->post('api/seo/keywords/distribute', \App\Controllers\SeoKeywordsController::class, 'distribute');
$router->post('api/seo/keywords/classify', \App\Controllers\SeoKeywordsController::class, 'classify');
$router->get('api/seo/keywords/fetch/{categoryId}', \App\Controllers\SeoKeywordsController::class, 'fetch');
$router->post('api/seo/keywords/generate/{categoryId}', \App\Controllers\SeoKeywordsController::class, 'generate');
$router->post('api/seo/density/validate', \App\Controllers\SeoKeywordsController::class, 'validateDensity');
$router->post('api/seo/density/calculate', \App\Controllers\SeoKeywordsController::class, 'calculateDensity');
$router->get('api/seo/weights', \App\Controllers\SeoKeywordsController::class, 'getWeights');
$router->delete('api/seo/keywords/cache/{categoryId}', \App\Controllers\SeoKeywordsController::class, 'invalidateCache');

// ========================================
// � SEO Keyword Mining (ML API)
// ========================================
$router->get('api/seo/keywords/mine/{categoryId}', \App\Controllers\SeoKeywordsController::class, 'mine');
$router->get('api/seo/keywords/mine-moto', \App\Controllers\SeoKeywordsController::class, 'mineMoto');
$router->get('api/seo/keywords/attributes/{categoryId}', \App\Controllers\SeoKeywordsController::class, 'getAttributeKeywords');
$router->get('api/seo/keywords/discover', \App\Controllers\SeoKeywordsController::class, 'discover');
$router->post('api/seo/keywords/suggest-title', \App\Controllers\SeoKeywordsController::class, 'suggestTitle');

// ========================================
// 📊 Market Data API - Dados Reais do ML
// ========================================
$router->get('api/market/analyze/{categoryId}', MarketDataController::class, 'analyzeMarket');
$router->get('api/market/category/{categoryId}', MarketDataController::class, 'getCategory');
$router->get('api/market/pricing/{categoryId}', MarketDataController::class, 'analyzePricing');
$router->get('api/market/competitors/{categoryId}', MarketDataController::class, 'analyzeCompetitors');
$router->get('api/market/trends/{categoryId}', MarketDataController::class, 'getTrends');
$router->get('api/market/filters/{categoryId}', MarketDataController::class, 'getFilters');
$router->post('api/market/similar', MarketDataController::class, 'findSimilar');
$router->get('api/market/quality/{itemId}', MarketDataController::class, 'analyzeQuality');
$router->post('api/market/suggest-price', MarketDataController::class, 'suggestPrice');
$router->get('api/market/search', MarketDataController::class, 'search');
$router->get('api/market/item/{itemId}', MarketDataController::class, 'getItem');
$router->get('api/market/autocomplete', MarketDataController::class, 'autocompleteSearch');
$router->get('api/market/discover', MarketDataController::class, 'discover');
$router->get('api/market/attributes/{categoryId}', MarketDataController::class, 'getAttributes');
$router->get('api/market/children/{categoryId}', MarketDataController::class, 'getChildren');
$router->get('api/market/stats', MarketDataController::class, 'getStats');
$router->get('api/market/requirements/{categoryId}', MarketDataController::class, 'getRequirements');

// ========================================
// �📝 SEO Phase 3: Description Builder
// ========================================
$router->post('api/seo/description/build', \App\Controllers\SeoDescriptionController::class, 'build');
$router->post('api/seo/description/block', \App\Controllers\SeoDescriptionController::class, 'generateBlock');
$router->post('api/seo/description/faq', \App\Controllers\SeoDescriptionController::class, 'generateFaq');
$router->post('api/seo/description/validate', \App\Controllers\SeoDescriptionController::class, 'validate');
$router->post('api/seo/longtail/generate', \App\Controllers\SeoDescriptionController::class, 'generateLongTail');

// ========================================
// 🤖 ML ↔ AI Integration Pipeline
// ========================================
$router->get('api/ml-ai/health', \App\Controllers\MLAIIntegrationController::class, 'health');
$router->get('api/ml-ai/items', \App\Controllers\MLAIIntegrationController::class, 'listItems');
$router->get('api/ml-ai/status/{itemId}', \App\Controllers\MLAIIntegrationController::class, 'itemStatus');
$router->get('api/ml-ai/enrich/{itemId}', \App\Controllers\MLAIIntegrationController::class, 'enrich');
$router->post('api/ml-ai/optimize/{itemId}', \App\Controllers\MLAIIntegrationController::class, 'optimize');
$router->post('api/ml-ai/apply/{itemId}', \App\Controllers\MLAIIntegrationController::class, 'apply');
$router->put('api/ml-ai/description/{itemId}', \App\Controllers\MLAIIntegrationController::class, 'updateDescription');
$router->post('api/ml-ai/pipeline/{itemId}', \App\Controllers\MLAIIntegrationController::class, 'pipeline');
$router->post('api/ml-ai/batch', \App\Controllers\MLAIIntegrationController::class, 'batch');
$router->get('api/ml-ai/history/{itemId}', \App\Controllers\MLAIIntegrationController::class, 'history');
$router->post('api/ml-ai/rollback/{itemId}', \App\Controllers\MLAIIntegrationController::class, 'rollback');
$router->get('api/ml-ai/stats', \App\Controllers\MLAIIntegrationController::class, 'stats');
$router->get('api/ml-ai/compare', \App\Controllers\MLAIIntegrationController::class, 'compare');
$router->post('api/ml-ai/impact/{versionId}', \App\Controllers\MLAIIntegrationController::class, 'impact');
$router->post('api/ml-ai/cleanup', \App\Controllers\MLAIIntegrationController::class, 'cleanup');

// ========================================
// 🔥 SEO KILLER - Sistema Matador
// ========================================
$router->get('api/seo-killer/gsc/status', SEOKillerController::class, 'gscStatus');
$router->post('api/seo-killer/gsc/auth-url', SEOKillerController::class, 'gscAuthUrl');
$router->get('api/seo-killer/gsc/callback', SEOKillerController::class, 'gscCallback');
$router->get('api/seo-killer/gsc/data', SEOKillerController::class, 'gscData');

$router->get('api/seo-killer/diagnose', SEOKillerController::class, 'diagnose');
$router->post('api/seo-killer/title', SEOKillerController::class, 'generateTitle');
$router->post('api/seo-killer/description', SEOKillerController::class, 'generateDescription');

// FASE 4: Campos Ocultos e Cobertura
$router->get('api/seo/hidden-fields/{itemId}', \App\Controllers\SeoCoverageController::class, 'detectHiddenFields');
$router->post('api/seo/hidden-fields/generate', \App\Controllers\SeoCoverageController::class, 'generateHiddenFieldValues');
$router->post('api/seo/hidden-fields/apply', \App\Controllers\SeoCoverageController::class, 'applyHiddenFields');
$router->get('api/seo/coverage/{itemId}', \App\Controllers\SeoCoverageController::class, 'analyzeCoverage');
$router->get('api/seo/coverage/gaps/{itemId}', \App\Controllers\SeoCoverageController::class, 'getCoverageGaps');
$router->get('api/seo/compatibility/{categoryId}', \App\Controllers\SeoCoverageController::class, 'listCompatibility');

// FASE 5: Integração e Dashboard
$router->post('api/seo/strategies/optimize/full/{itemId}', \App\Controllers\SeoStrategiesController::class, 'optimizeFull');
$router->post('api/seo/strategies/optimize/partial/{itemId}', \App\Controllers\SeoStrategiesController::class, 'optimizePartial');
$router->get('api/seo/strategies/preview/{itemId}', \App\Controllers\SeoStrategiesController::class, 'preview');
$router->post('api/seo/strategies/apply/{itemId}', \App\Controllers\SeoStrategiesController::class, 'apply');
$router->get('api/seo/strategies/score/{itemId}', \App\Controllers\SeoStrategiesController::class, 'getScore');
$router->get('api/seo/strategies/history/{itemId}', \App\Controllers\SeoStrategiesController::class, 'history');
$router->post('api/seo/monitoring/schedule/{itemId}', \App\Controllers\SeoStrategiesController::class, 'scheduleMonitoring');
$router->get('api/seo/monitoring/metrics/{itemId}', \App\Controllers\SeoStrategiesController::class, 'getMetrics');


$router->post('api/seo-killer/description/analyze', SEOKillerController::class, 'analyzeDescription');
$router->post('api/seo-killer/attributes', SEOKillerController::class, 'fillAttributes');
$router->get('api/seo-killer/hidden-attributes/{categoryId}', SEOKillerController::class, 'getHiddenAttributes');
$router->post('api/seo-killer/optimize', SEOKillerController::class, 'optimizeItem');
$router->post('api/seo-killer/optimize/{productId}', SEOKillerController::class, 'engineOptimizeItem');
$router->post('api/seo-killer/sync', SEOKillerController::class, 'sync');
$router->get('api/seo-killer/report', SEOKillerController::class, 'completenessReport');
$router->get('api/seo-killer/schema/{itemId}', SEOKillerController::class, 'generateSchema');
$router->get('api/seo-killer/export/pdf/{type}/{itemId}', SEOKillerController::class, 'exportPdf');
$router->get('api/seo-killer/api-keys', SEOKillerController::class, 'listApiKeys');
$router->post('api/seo-killer/api-keys', SEOKillerController::class, 'createApiKey');
$router->delete('api/seo-killer/api-keys/{clientId}', SEOKillerController::class, 'revokeApiKey');
$router->post('api/seo-killer/backlinks/analyze', SEOKillerController::class, 'analyzeBacklinks');

// Keywords & Competitors
$router->post('api/seo-killer/keywords', SEOKillerController::class, 'researchKeywords');
$router->post('api/seo-killer/spy', SEOKillerController::class, 'spyCompetitors');
$router->post('api/seo-killer/competitors/analyze/{itemId}', SEOKillerController::class, 'analyzeCompetitors');
// Bulk Operations
$router->get('api/seo-killer/bulk/select', SEOKillerController::class, 'bulkSelect');
$router->post('api/seo-killer/bulk/start', SEOKillerController::class, 'bulkStart');
$router->post('api/seo-killer/bulk/process/{jobId}', SEOKillerController::class, 'bulkProcess');
$router->get('api/seo-killer/bulk/status/{jobId}', SEOKillerController::class, 'bulkStatus');
$router->get('api/seo-killer/bulk/jobs', SEOKillerController::class, 'bulkJobs');
$router->get('api/seo-killer/bulk/monitor', SEOKillerController::class, 'bulkMonitor');
$router->post('api/seo-killer/bulk/cancel/{jobId}', SEOKillerController::class, 'bulkCancel');
$router->post('api/seo-killer/bulk/retry/{jobId}', SEOKillerController::class, 'bulkRetry');
// Auto-Pilot
$router->get('api/seo-killer/autopilot/config', SEOKillerController::class, 'getAutopilotConfig');
$router->post('api/seo-killer/autopilot/config', SEOKillerController::class, 'saveAutopilotConfig');
$router->post('api/seo-killer/autopilot/enable', SEOKillerController::class, 'enableAutopilot');
$router->post('api/seo-killer/autopilot/disable', SEOKillerController::class, 'disableAutopilot');
$router->post('api/seo-killer/autopilot/run', SEOKillerController::class, 'runAutopilot');
$router->get('api/seo-killer/autopilot/history', SEOKillerController::class, 'autopilotHistory');
$router->get('api/seo-killer/autopilot/history/{runId}', SEOKillerController::class, 'autopilotRunDetails');
$router->get('api/seo-killer/autopilot/stats', SEOKillerController::class, 'autopilotStats');
$router->get('api/seo-killer/autopilot/scores', SEOKillerController::class, 'getScoreEvolution');
// Performance Tracking
$router->get('api/seo-killer/performance/dashboard', SEOKillerController::class, 'getPerformanceDashboard');
$router->get('api/seo-killer/performance/item/{itemId}', SEOKillerController::class, 'getItemPerformance');
$router->get('api/seo-killer/performance/compare/{itemId}', SEOKillerController::class, 'compareBeforeAfter');
$router->get('api/seo-killer/performance/top', SEOKillerController::class, 'getTopPerformers');
$router->get('api/seo-killer/performance/consolidated', SEOKillerController::class, 'getConsolidatedMetrics');
$router->get('api/seo-killer/performance/evolution', SEOKillerController::class, 'getMetricsEvolution');
$router->get('api/seo-killer/performance/categories', SEOKillerController::class, 'getCategoryPerformance');
$router->get('api/seo-killer/performance/export', SEOKillerController::class, 'exportPerformanceReport');
// Image Killer
$router->get('api/seo-killer/images/analyze/{itemId}', SEOKillerController::class, 'analyzeImages');
// A/B Testing
$router->post('api/seo-killer/ab-test', SEOKillerController::class, 'createABTest');
$router->post('api/seo-killer/ab-test/title/{itemId}', SEOKillerController::class, 'createTitleABTest');
$router->get('api/seo-killer/ab-test', SEOKillerController::class, 'listABTests');
$router->get('api/seo-killer/ab-test/{testId}', SEOKillerController::class, 'getABTest');
$router->post('api/seo-killer/ab-test/stop/{id}', SEOKillerController::class, 'stopABTest');
$router->post('api/seo-killer/ab-test/apply/{id}', SEOKillerController::class, 'applyABTestWinner');
$router->get('api/seo-killer/ab-test/analysis/{id}', SEOKillerController::class, 'getABTestAnalysis');
// Competitor Watchlist
$router->post('api/seo-killer/watchlist', SEOKillerController::class, 'addToWatchlist');
$router->get('api/seo-killer/watchlist', SEOKillerController::class, 'getWatchlist');
$router->post('api/seo-killer/watchlist/{id}/update', SEOKillerController::class, 'updateWatchlistItem');
$router->delete('api/seo-killer/watchlist/{id}', SEOKillerController::class, 'removeFromWatchlist');
$router->get('api/seo-killer/watchlist/{id}/history', SEOKillerController::class, 'getWatchlistHistory');
$router->get('api/seo-killer/alerts', SEOKillerController::class, 'getAlerts');
$router->post('api/seo-killer/alerts/{id}/read', SEOKillerController::class, 'markAlertAsRead');

// SEO Score Calculator v2
$router->get('api/seo-killer/score/{itemId}', SEOKillerController::class, 'calculateScore');
$router->get('api/seo-killer/score/history/{itemId}', SEOKillerController::class, 'getScoreHistory');
$router->get('api/seo-killer/alerts/score', SEOKillerController::class, 'getScoreAlerts');
$router->get('api/seo-killer/benchmarks', SEOKillerController::class, 'getBenchmarks');
$router->get('api/seo-killer/audit/log', SEOKillerController::class, 'getAuditLog');
$router->get('api/seo-killer/settings', SEOKillerController::class, 'getSettings');
$router->post('api/seo-killer/settings', SEOKillerController::class, 'saveSettings');
$router->get('api/seo-killer/compare/{itemId}/{categoryId}', SEOKillerController::class, 'compareWithCategory');
$router->get('api/seo-killer/top-performers', SEOKillerController::class, 'getTopPerformingItems');
$router->get('api/seo-killer/autopilot/status', SEOKillerController::class, 'getAutopilotRealStatus');

// 🚀 Advanced SEO Features
$router->post('api/seo-killer/advanced-maximize', SEOKillerController::class, 'advancedMaximizeSEO');
$router->post('api/seo-killer/predict-performance', SEOKillerController::class, 'predictPerformance');
$router->post('api/seo-killer/intelligent-auto-optimize', SEOKillerController::class, 'intelligentAutoOptimize');
$router->post('api/seo-killer/advanced-keywords', SEOKillerController::class, 'advancedKeywordsAnalysis');
$router->post('api/seo-killer/advanced-competitor-analysis', SEOKillerController::class, 'advancedCompetitorAnalysis');
$router->get('api/seo-killer/optimization-stats', SEOKillerController::class, 'getOptimizationStats');

// SEO Strategies Integration (12 Strategies Engine)
$router->get('api/seo-killer/strategies/analyze/{itemId}', SEOKillerController::class, 'runStrategiesAnalysis');
$router->get('api/seo-killer/strategies/score/{itemId}', SEOKillerController::class, 'getStrategiesScore');
$router->post('api/seo-killer/strategies/optimize/{itemId}', SEOKillerController::class, 'optimizeWithStrategies');
$router->post('api/seo-killer/strategies/batch', SEOKillerController::class, 'batchStrategiesAnalysis');
$router->get('api/seo-killer/strategies/dashboard', SEOKillerController::class, 'getStrategiesDashboard');
$router->get('api/seo-killer/strategies/cache/stats', SEOKillerController::class, 'getStrategiesCacheStats');
$router->post('api/seo-killer/strategies/cache/clear', SEOKillerController::class, 'clearStrategiesCache');

// Export PDF & Intelligence
$router->post('api/seo-killer/export/competitor', SEOKillerController::class, 'exportCompetitorPdf');
$router->get('api/seo-killer/export/watchlist/{id}', SEOKillerController::class, 'exportWatchlistPdf');
$router->get('api/seo-killer/intelligence/dashboard', SEOKillerController::class, 'getIntelligenceDashboard');
$router->post('api/seo-killer/intelligence/swot', SEOKillerController::class, 'getSwotAnalysis');
$router->post('api/seo-killer/spy/copy-strategy', SEOKillerController::class, 'copyCompetitorStrategy');
$router->post('api/seo-killer/item/update', SEOKillerController::class, 'updateItem');

// Image Killer
$router->post('api/seo-killer/images/upload', SEOKillerController::class, 'uploadImage');
$router->post('api/seo-killer/images/update/{itemId}', SEOKillerController::class, 'updateImages');

// Advanced Analytics (v1.8.0)
$router->get('api/seo-killer/analytics/demand-forecast', SEOKillerController::class, 'getDemandForecast');
$router->get('api/seo-killer/analytics/seasonality', SEOKillerController::class, 'getSeasonalityAnalysis');
$router->get('api/seo-killer/analytics/opportunities', SEOKillerController::class, 'getEmergingOpportunities');
$router->get('api/seo-killer/analytics/market-sentiment', SEOKillerController::class, 'getMarketSentiment');

// ========================================
// 🎯 SEO STRATEGIES - Estratégias Avançadas (v2.0.0)
// ========================================
// Sinônimos - Hierarquia 4 níveis (E1)
$router->post('api/seo-killer/strategies/synonyms/expand', SEOKillerController::class, 'expandSynonyms');
$router->get('api/seo-killer/strategies/synonyms/hierarchy/{categoryId}', SEOKillerController::class, 'getSynonymHierarchy');
$router->post('api/seo-killer/strategies/synonyms/generate', SEOKillerController::class, 'generateSynonymHierarchy');
$router->post('api/seo-killer/strategies/synonyms/select', SEOKillerController::class, 'selectSynonymsForField');
$router->post('api/seo-killer/strategies/synonyms/model', SEOKillerController::class, 'generateOptimizedModel');
// Score Semântico (E9)
$router->post('api/seo-killer/strategies/score/calculate', SEOKillerController::class, 'calculateSemanticScore');
$router->post('api/seo-killer/strategies/score/rank', SEOKillerController::class, 'rankBySemanticScore');
$router->post('api/seo-killer/strategies/score/filter', SEOKillerController::class, 'filterBySemanticScore');
// Keywords - Arquitetura Híbrida
$router->post('api/seo-killer/strategies/keywords/fetch', SEOKillerController::class, 'fetchKeywords');
$router->get('api/seo-killer/strategies/keywords/trending/{categoryId}', SEOKillerController::class, 'getTrendingKeywords');
$router->get('api/seo-killer/strategies/keywords/autocomplete', SEOKillerController::class, 'getAutocompleteKeywords');
$router->post('api/seo-killer/strategies/keywords/competitor', SEOKillerController::class, 'getCompetitorKeywords');
$router->delete('api/seo-killer/strategies/keywords/cache/{categoryId}', SEOKillerController::class, 'invalidateKeywordCache');
// Contextos e Configuração
$router->get('api/seo-killer/strategies/contexts/{categoryId}', SEOKillerController::class, 'getUseContexts');
$router->get('api/seo-killer/strategies/config/{categoryId}', SEOKillerController::class, 'getStrategyConfig');
$router->post('api/seo-killer/strategies/config/{categoryId}', SEOKillerController::class, 'saveStrategyConfig');
// Hidden Fields - Campos Ocultos (E2)
$router->get('api/seo-killer/strategies/hidden-fields/{itemId}', SEOKillerController::class, 'analyzeHiddenFields');
$router->post('api/seo-killer/strategies/hidden-fields/suggest', SEOKillerController::class, 'suggestHiddenFields');
$router->post('api/seo-killer/strategies/hidden-fields/apply/{itemId}', SEOKillerController::class, 'applyHiddenFields');
$router->get('api/seo-killer/strategies/hidden-fields/available/{categoryId}', SEOKillerController::class, 'getAvailableHiddenFields');
// Keyword Injector - Injeção Natural (E3)
$router->post('api/seo-killer/strategies/inject/title', SEOKillerController::class, 'injectKeywordsTitle');
$router->post('api/seo-killer/strategies/inject/description', SEOKillerController::class, 'injectKeywordsDescription');
$router->post('api/seo-killer/strategies/inject/density', SEOKillerController::class, 'analyzeKeywordDensity');
$router->post('api/seo-killer/strategies/inject/points', SEOKillerController::class, 'suggestInjectionPoints');
// Search Type Coverage - Cobertura de Busca (E4)
$router->post('api/seo-killer/strategies/coverage/analyze', SEOKillerController::class, 'analyzeCoverage');
$router->post('api/seo-killer/strategies/coverage/keywords', SEOKillerController::class, 'generateCoverageKeywords');
$router->post('api/seo-killer/strategies/coverage/optimize', SEOKillerController::class, 'optimizeForCoverage');
$router->get('api/seo-killer/strategies/coverage/classify', SEOKillerController::class, 'classifySearchQuery');
$router->post('api/seo-killer/strategies/coverage/missing', SEOKillerController::class, 'suggestMissingKeywords');
// Field Weight - Distribuição por Peso (E5)
$router->post('api/seo-killer/strategies/weight/distribute', SEOKillerController::class, 'distributeByWeight');
$router->post('api/seo-killer/strategies/weight/analyze', SEOKillerController::class, 'analyzeDistribution');
$router->post('api/seo-killer/strategies/weight/optimize', SEOKillerController::class, 'optimizeDistribution');
$router->post('api/seo-killer/strategies/weight/reallocate', SEOKillerController::class, 'suggestReallocation');
$router->post('api/seo-killer/strategies/weight/efficiency', SEOKillerController::class, 'calculateIndexingEfficiency');
$router->get('api/seo-killer/strategies/weight/fields', SEOKillerController::class, 'getFieldWeights');
$router->post('api/seo-killer/strategies/weight/maximize', SEOKillerController::class, 'getWeightMaximizationStrategy');

// Use Context - Contextos de Uso (E6)
$router->get('api/seo-killer/strategies/contexts/available', SEOKillerController::class, 'getAvailableContexts');
$router->get('api/seo-killer/strategies/contexts/category/{categoryId}', SEOKillerController::class, 'getContextsForCategory');
$router->post('api/seo-killer/strategies/contexts/detect', SEOKillerController::class, 'detectContexts');
$router->post('api/seo-killer/strategies/contexts/keywords', SEOKillerController::class, 'generateContextKeywords');
$router->post('api/seo-killer/strategies/contexts/suggest', SEOKillerController::class, 'suggestContexts');
$router->post('api/seo-killer/strategies/contexts/enrich', SEOKillerController::class, 'enrichWithContexts');

// Long Tail Generator (E7)
$router->post('api/seo-killer/strategies/longtail/generate', SEOKillerController::class, 'generateLongTails');
$router->get('api/seo-killer/strategies/longtail/autocomplete/{keyword}', SEOKillerController::class, 'generateLongTailsFromAutocomplete');
$router->post('api/seo-killer/strategies/longtail/competitors', SEOKillerController::class, 'generateLongTailsFromCompetitors');
$router->post('api/seo-killer/strategies/longtail/ai', SEOKillerController::class, 'generateLongTailsWithAI');
$router->post('api/seo-killer/strategies/longtail/analyze', SEOKillerController::class, 'analyzeLongTail');
$router->post('api/seo-killer/strategies/longtail/missing', SEOKillerController::class, 'suggestMissingLongTails');

// Compatibility Service (E10)
$router->get('api/seo-killer/strategies/compatibility/analyze/{itemId}', SEOKillerController::class, 'analyzeCompatibility');
$router->post('api/seo-killer/strategies/compatibility/expand', SEOKillerController::class, 'expandCompatibility');
$router->post('api/seo-killer/strategies/compatibility/fetch', SEOKillerController::class, 'fetchCompatibilityFromML');
$router->post('api/seo-killer/strategies/compatibility/attribute', SEOKillerController::class, 'generateCompatibleModelsAttribute');
$router->post('api/seo-killer/strategies/compatibility/suggest-by-specs', SEOKillerController::class, 'suggestCompatibilityBySpecs');
$router->post('api/seo-killer/strategies/compatibility/validate', SEOKillerController::class, 'validateCompatibility');
$router->get('api/seo-killer/strategies/compatibility/models', SEOKillerController::class, 'getAllModels');
$router->get('api/seo-killer/strategies/compatibility/models/{brand}', SEOKillerController::class, 'getAllModels');

// FAQ Optimizer (E11)
$router->post('api/seo-killer/strategies/faq/generate', SEOKillerController::class, 'generateFAQs');
$router->post('api/seo-killer/strategies/faq/ai', SEOKillerController::class, 'generateFAQsWithAI');
$router->post('api/seo-killer/strategies/faq/optimize', SEOKillerController::class, 'optimizeFAQs');
$router->post('api/seo-killer/strategies/faq/schema', SEOKillerController::class, 'generateFAQSchema');
$router->post('api/seo-killer/strategies/faq/html', SEOKillerController::class, 'generateFAQHTML');
$router->post('api/seo-killer/strategies/faq/description-text', SEOKillerController::class, 'generateFAQDescriptionText');
$router->post('api/seo-killer/strategies/faq/validate', SEOKillerController::class, 'validateFAQs');
$router->get('api/seo-killer/strategies/faq/suggest/{categoryId}', SEOKillerController::class, 'suggestFAQsForCategory');
$router->get('api/seo-killer/questions/keywords/{itemId}', SEOKillerController::class, 'mineKeywordsFromQuestions');

// SEO Strategies Engine - Orchestrator (E12)
$router->get('api/seo-killer/strategies/engine/analyze/{itemId}', SEOKillerController::class, 'engineAnalyzeItem');
$router->post('api/seo-killer/strategies/engine/analyze', SEOKillerController::class, 'engineAnalyzeData');
$router->post('api/seo-killer/strategies/engine/optimize/{itemId}', SEOKillerController::class, 'engineOptimizeItem');
$router->get('api/seo-killer/strategies/engine/dashboard', SEOKillerController::class, 'engineDashboard');
$router->get('api/seo-killer/strategies/engine/dashboard/{categoryId}', SEOKillerController::class, 'engineDashboard');
$router->get('api/seo-killer/strategies/engine/report/{itemId}', SEOKillerController::class, 'engineOptimizationReport');
$router->post('api/seo-killer/strategies/engine/compare', SEOKillerController::class, 'engineCompareItems');
$router->post('api/seo-killer/strategies/engine/monitor', SEOKillerController::class, 'engineMonitorKeywords');

// ========================================
// ✅ QUALITY CHECK - Health, Score & Validation
// ========================================
use App\Controllers\QualityController;

// Health Check - Verificação de Saúde
$router->get('api/quality/health/{itemId}', QualityController::class, 'checkHealth');
$router->post('api/quality/health/batch', QualityController::class, 'checkHealthBatch');
$router->get('api/quality/health/{itemId}/recommendations', QualityController::class, 'getHealthRecommendations');

// Quality Score - Pontuação de Qualidade
$router->get('api/quality/score/{itemId}', QualityController::class, 'calculateScore');

// Validation - Validação Pré-Publicação
$router->post('api/quality/validate', QualityController::class, 'validateListing');
$router->post('api/quality/validate/batch', QualityController::class, 'validateBatch');
$router->post('api/quality/autofix', QualityController::class, 'autoFix');

// Complete Report - Relatório Completo
$router->get('api/quality/report/{itemId}', QualityController::class, 'getCompleteReport');

// Dashboard APIs (NEW - implementado)
$router->get('api/quality/dashboard/stats', QualityController::class, 'getDashboardStats');
$router->get('api/quality/dashboard/items', QualityController::class, 'getDashboardItems');

// ========================================
// 📦 SHIPPING STRATEGY OPTIMIZER - Otimização de Envios
// ========================================
use App\Controllers\ShippingController;

// Shipping Simulation - Simulação de Custos
$router->get('api/shipping/simulate/{itemId}', ShippingController::class, 'simulateItem');
$router->post('api/shipping/simulate', ShippingController::class, 'simulateCustom');
$router->post('api/shipping/compare', ShippingController::class, 'compareShipping');

// Shipping Optimization - Otimização de Estratégia
$router->get('api/shipping/optimize/{itemId}', ShippingController::class, 'optimizeItem');
$router->post('api/shipping/optimize/batch', ShippingController::class, 'optimizeBatch');

// Dimensions Calculator - Cálculos de Dimensões
$router->post('api/shipping/dimensions/cubic-weight', ShippingController::class, 'calculateCubicWeight');
$router->post('api/shipping/dimensions/chargeable-weight', ShippingController::class, 'calculateChargeableWeight');
$router->post('api/shipping/dimensions/validate', ShippingController::class, 'validateDimensions');
$router->post('api/shipping/dimensions/validate-all', ShippingController::class, 'validateAllModes');
$router->post('api/shipping/dimensions/suggest-packaging', ShippingController::class, 'suggestPackaging');
$router->post('api/shipping/dimensions/optimize', ShippingController::class, 'optimizeDimensions');
$router->post('api/shipping/dimensions/analyze', ShippingController::class, 'analyzeDimensions');

// ========================================
// 📝 LISTING BUILDER - Construtor de Anúncios
// ========================================
use App\Controllers\ListingBuilderController;

// Wizard Workflow - Fluxo do Construtor
$router->post('api/listing-builder/start', ListingBuilderController::class, 'start');
$router->post('api/listing-builder/validate/{step}', ListingBuilderController::class, 'validateStep');
$router->post('api/listing-builder/build', ListingBuilderController::class, 'build');
$router->post('api/listing-builder/publish', ListingBuilderController::class, 'publish');

// Draft Management - Gerenciamento de Rascunhos
$router->post('api/listing-builder/draft/save', ListingBuilderController::class, 'saveDraft');
$router->get('api/listing-builder/draft/{draftId}', ListingBuilderController::class, 'loadDraft');

// Clone Functionality - Clonagem de Anúncios
$router->post('api/listing-builder/clone', ListingBuilderController::class, 'clone');

// Template Management - Gerenciamento de Templates
$router->get('api/listing-builder/templates', ListingBuilderController::class, 'listTemplates');
$router->get('api/listing-builder/templates/{templateId}', ListingBuilderController::class, 'getTemplate');
$router->post('api/listing-builder/templates/{templateId}/render', ListingBuilderController::class, 'renderTemplate');
$router->post('api/listing-builder/templates/custom', ListingBuilderController::class, 'createCustomTemplate');

// Block Management - Blocos Reutilizáveis
$router->get('api/listing-builder/blocks', ListingBuilderController::class, 'listBlocks');

// ========================================
// 🏷️ TITLE GENERATOR - Gerador de Títulos
// ========================================
use App\Controllers\TitleGeneratorController;

// Title Generation - Geração de Títulos
$router->post('api/title-generator/generate', TitleGeneratorController::class, 'generate');
$router->post('api/title-generator/improve/{itemId}', TitleGeneratorController::class, 'improveFromItem');
$router->post('api/title-generator/optimize', TitleGeneratorController::class, 'optimize');

// Title Analysis - Análise de Títulos
$router->post('api/title-generator/analyze', TitleGeneratorController::class, 'analyze');
$router->post('api/title-generator/compare', TitleGeneratorController::class, 'compare');
$router->get('api/title-generator/quick-tips', TitleGeneratorController::class, 'quickTips');

// Title Variations - Variações
$router->post('api/title-generator/variations', TitleGeneratorController::class, 'generateVariations');
$router->post('api/title-generator/ab-testing', TitleGeneratorController::class, 'generateABTesting');

// Batch Operations - Operações em Lote
$router->post('api/title-generator/batch/analyze', TitleGeneratorController::class, 'batchAnalyze');

// ========================================
// 🤖 AI/ML SERVICES - Machine Learning
// ========================================
use App\Controllers\AIMLController;

// Category Learning - Padrões por Categoria
$router->post('api/ai/ml/category/learn', AIMLController::class, 'learnCategory');
$router->get('api/ai/ml/category/learning/{categoryId}', AIMLController::class, 'getCategoryLearning');
$router->get('api/ai/ml/category/template/{categoryId}', AIMLController::class, 'getCategoryTemplate');
$router->get('api/ai/ml/category/learned', AIMLController::class, 'listLearnedCategories');
$router->post('api/ai/ml/category/refresh/{categoryId}', AIMLController::class, 'refreshCategoryLearning');

// Keyword Classification - CORE/SUPPORT/LONG_TAIL
$router->post('api/ai/ml/keywords/classify', AIMLController::class, 'classifyKeywords');
$router->post('api/ai/ml/keywords/group', AIMLController::class, 'groupKeywords');
$router->post('api/ai/ml/keywords/optimize-title', AIMLController::class, 'optimizeKeywordsForTitle');
$router->get('api/ai/ml/keywords/stats', AIMLController::class, 'getKeywordStats');
$router->post('api/ai/ml/keywords/reclassify', AIMLController::class, 'reclassifyKeywords');

// Trend Prediction - Tendências e Sazonalidade
$router->get('api/ai/ml/trends/predict', AIMLController::class, 'predictTrend');
$router->get('api/ai/ml/trends/seasonality', AIMLController::class, 'analyzeSeasonality');
$router->get('api/ai/ml/trends/rising/{categoryId}', AIMLController::class, 'findRisingKeywords');
$router->get('api/ai/ml/trends/report/{categoryId}', AIMLController::class, 'getCategoryTrendReport');
$router->post('api/ai/ml/trends/batch', AIMLController::class, 'batchTrendPrediction');

// ========================================
// �🧾 FICHA TÉCNICA (SEO) - Lista + Aprovação
// ========================================
$router->get('api/seo/technical-sheet/items', TechnicalSheetController::class, 'listItems');
$router->get('api/technical-sheet/items', TechnicalSheetController::class, 'listItems');
$router->get('api/seo/technical-sheet/stats', TechnicalSheetController::class, 'stats');
$router->get('api/technical-sheet/stats', TechnicalSheetController::class, 'stats');
$router->get('api/seo/technical-sheet/items/{itemId}', TechnicalSheetController::class, 'getItem');
$router->get('api/technical-sheet/items/{itemId}', TechnicalSheetController::class, 'getItem');
$router->post('api/seo/technical-sheet/items/{itemId}/refresh', TechnicalSheetController::class, 'refreshItem');
$router->post('api/seo/technical-sheet/items/{itemId}/suggestions/generate', TechnicalSheetController::class, 'generateSuggestions');
$router->post('api/seo/technical-sheet/items/{itemId}/suggestions/quick', TechnicalSheetController::class, 'quickSuggestions');
$router->post('api/seo/technical-sheet/items/{itemId}/suggestions/model', TechnicalSheetController::class, 'modelSuggestions');
$router->post('api/seo/technical-sheet/items/{itemId}/suggestions/decisions', TechnicalSheetController::class, 'saveDecisions');
$router->post('api/seo/technical-sheet/items/{itemId}/suggestions', TechnicalSheetController::class, 'addSuggestions');
$router->post('api/seo/technical-sheet/items/{itemId}/apply', TechnicalSheetController::class, 'applyApproved');

// Batch (jobs)
$router->post('api/seo/technical-sheet/batch/suggestions/generate', TechnicalSheetController::class, 'batchGenerateSuggestions');
$router->post('api/seo/technical-sheet/batch/apply', TechnicalSheetController::class, 'batchApplyApproved');
$router->post('api/seo/technical-sheet/batch/approve', TechnicalSheetController::class, 'batchApprovePending');

// Analytics
$router->get('api/seo/technical-sheet/analytics/dashboard', TechnicalSheetController::class, 'analyticsDashboard');
$router->get('api/seo/technical-sheet/analytics/priorities', TechnicalSheetController::class, 'analyticsPriorities');
$router->get('api/seo/technical-sheet/alerts', TechnicalSheetController::class, 'getAlerts');
$router->post('api/seo/technical-sheet/auto-optimize', TechnicalSheetController::class, 'autoOptimize');
$router->get('api/seo/technical-sheet/auto-optimize/stats', TechnicalSheetController::class, 'autoOptimizeStats');
$router->get('api/seo/technical-sheet/export', TechnicalSheetController::class, 'exportSuggestions');
$router->post('api/seo/technical-sheet/import', TechnicalSheetController::class, 'importSuggestions');
$router->post('api/seo/technical-sheet/send-report', TechnicalSheetController::class, 'sendDailyReport');
$router->get('api/seo/technical-sheet/export/template/{categoryId}', TechnicalSheetController::class, 'exportCategoryTemplate');

// ML Trends Integration - Mineração de Keywords via API do Mercado Livre
$router->get('api/seo/technical-sheet/items/{itemId}/trends', TechnicalSheetController::class, 'enrichWithTrends');
$router->get('api/seo/technical-sheet/trends/site', TechnicalSheetController::class, 'getSiteTrends');
$router->get('api/seo/technical-sheet/trends/category/{categoryId}', TechnicalSheetController::class, 'getCategoryTrends');

// Charts & Visualizations
$router->get('api/seo/technical-sheet/charts', TechnicalSheetController::class, 'getCharts');

// Batch Performance
$router->post('api/seo/technical-sheet/batch/process', TechnicalSheetController::class, 'processBatch');
$router->get('api/seo/technical-sheet/batch/performance', TechnicalSheetController::class, 'getBatchPerformance');

// Scheduler
$router->get('api/seo/technical-sheet/scheduler/jobs', TechnicalSheetController::class, 'listScheduledJobs');
$router->post('api/seo/technical-sheet/scheduler/jobs', TechnicalSheetController::class, 'createScheduledJob');
$router->post('api/seo/technical-sheet/scheduler/jobs/{jobId}/run', TechnicalSheetController::class, 'runScheduledJob');
$router->put('api/seo/technical-sheet/scheduler/jobs/{jobId}/pause', TechnicalSheetController::class, 'pauseScheduledJob');
$router->put('api/seo/technical-sheet/scheduler/jobs/{jobId}/resume', TechnicalSheetController::class, 'resumeScheduledJob');
$router->delete('api/seo/technical-sheet/scheduler/jobs/{jobId}', TechnicalSheetController::class, 'deleteScheduledJob');
$router->get('api/seo/technical-sheet/scheduler/stats', TechnicalSheetController::class, 'getSchedulerStats');

// Webhooks
$router->get('api/seo/technical-sheet/webhooks', TechnicalSheetController::class, 'listWebhooks');
$router->post('api/seo/technical-sheet/webhooks', TechnicalSheetController::class, 'createWebhook');
$router->put('api/seo/technical-sheet/webhooks/{webhookId}', TechnicalSheetController::class, 'updateWebhook');
$router->delete('api/seo/technical-sheet/webhooks/{webhookId}', TechnicalSheetController::class, 'deleteWebhook');
$router->post('api/seo/technical-sheet/webhooks/{webhookId}/test', TechnicalSheetController::class, 'testWebhook');

// Alert Rules
$router->get('api/seo/technical-sheet/alerts/rules', TechnicalSheetController::class, 'listAlertRules');
$router->post('api/seo/technical-sheet/alerts/rules', TechnicalSheetController::class, 'createAlertRule');
$router->put('api/seo/technical-sheet/alerts/rules/{ruleId}', TechnicalSheetController::class, 'updateAlertRule');
$router->delete('api/seo/technical-sheet/alerts/rules/{ruleId}', TechnicalSheetController::class, 'deleteAlertRule');
$router->post('api/seo/technical-sheet/alerts/rules/{ruleId}/recipients', TechnicalSheetController::class, 'addAlertRecipient');
$router->delete('api/seo/technical-sheet/alerts/rules/{ruleId}/recipients/{email}', TechnicalSheetController::class, 'removeAlertRecipient');
$router->get('api/seo/technical-sheet/alerts/history', TechnicalSheetController::class, 'getAlertHistory');

// Advanced Features
$router->post('api/seo/technical-sheet/items/{itemId}/extract-from-title', TechnicalSheetController::class, 'extractFromTitle');
$router->get('api/seo/technical-sheet/items/{itemId}/compare-competitors', TechnicalSheetController::class, 'compareCompetitors');
$router->post('api/seo/technical-sheet/items/{itemId}/preview', TechnicalSheetController::class, 'previewChanges');

// SEO Strategies Integration
$router->get('api/seo/technical-sheet/items/{itemId}/seo-analysis', TechnicalSheetController::class, 'seoAnalysis');
$router->post('api/seo/technical-sheet/items/{itemId}/seo-suggestions', TechnicalSheetController::class, 'generateSEOSuggestions');
$router->post('api/seo/technical-sheet/items/{itemId}/optimize-title', TechnicalSheetController::class, 'optimizeItemTitle');
$router->post('api/seo/technical-sheet/items/{itemId}/optimize-description', TechnicalSheetController::class, 'optimizeItemDescription');
$router->post('api/seo/technical-sheet/items/{itemId}/apply-optimized-title', TechnicalSheetController::class, 'applyOptimizedTitle');
$router->post('api/seo/technical-sheet/items/{itemId}/apply-optimized-description', TechnicalSheetController::class, 'applyOptimizedDescription');
$router->get('api/seo/technical-sheet/items/{itemId}/description', TechnicalSheetController::class, 'getItemPlainTextDescription');
$router->get('api/seo/technical-sheet/items/{itemId}/history', TechnicalSheetController::class, 'getOptimizationHistory');
$router->post('api/seo/technical-sheet/items/{itemId}/rollback/{versionId}', TechnicalSheetController::class, 'rollbackOptimization');
$router->get('api/seo/technical-sheet/items/{itemId}/seo-score', TechnicalSheetController::class, 'getSEOScore');
$router->get('api/seo/technical-sheet/items/{itemId}/seo-report', TechnicalSheetController::class, 'getSEOReport');
$router->post('api/seo/technical-sheet/batch/seo-suggestions', TechnicalSheetController::class, 'batchSEOSuggestions');

// 🎯 Smart Gap Filler - Preenchimento inteligente de lacunas
$router->post('api/seo/technical-sheet/items/{itemId}/smart-fill', TechnicalSheetController::class, 'smartFillGaps');
$router->post('api/seo/technical-sheet/batch/smart-fill', TechnicalSheetController::class, 'batchSmartFillGaps');
$router->get('api/seo/technical-sheet/items/{itemId}/coverage-analysis', TechnicalSheetController::class, 'coverageAnalysis');

// 🎯 Smart Fill Dashboard & Metrics
$router->get('api/seo/technical-sheet/smart-fill/dashboard', TechnicalSheetController::class, 'smartFillDashboard');
$router->get('api/seo/technical-sheet/smart-fill/widget', TechnicalSheetController::class, 'smartFillWidget');
$router->get('api/seo/technical-sheet/smart-fill/by-source', TechnicalSheetController::class, 'smartFillBySource');
$router->get('api/seo/technical-sheet/smart-fill/success-rate', TechnicalSheetController::class, 'smartFillSuccessRate');
$router->post('api/seo/technical-sheet/smart-fill/auto-approve', TechnicalSheetController::class, 'smartFillAutoApprove');

// 🚀 Bulk SEO - Fluxo Seguro de Otimização em Lote
$router->post('api/seo/technical-sheet/bulk/dry-run', TechnicalSheetController::class, 'bulkDryRun');
$router->post('api/seo/technical-sheet/bulk/apply', TechnicalSheetController::class, 'bulkApply');
$router->post('api/seo/technical-sheet/bulk/apply-async', TechnicalSheetController::class, 'bulkApplyAsync');
$router->get('api/seo/technical-sheet/bulk/job/{jobId}/status', TechnicalSheetController::class, 'bulkJobStatus');
$router->get('api/seo/technical-sheet/bulk/history', TechnicalSheetController::class, 'bulkHistory');
$router->post('api/seo/technical-sheet/bulk/rollback', TechnicalSheetController::class, 'bulkRollback');

// 🔧 Attribute Suggestions - Aplicação real de campos da Ficha Técnica
$router->get('api/seo/technical-sheet/items/{itemId}/attribute-suggestions/preview', TechnicalSheetController::class, 'previewAttributeSuggestions');
$router->post('api/seo/technical-sheet/items/{itemId}/attribute-suggestions/apply', TechnicalSheetController::class, 'applyAttributeSuggestion');
$router->get('api/seo/technical-sheet/items/{itemId}/applicable-attributes', TechnicalSheetController::class, 'getApplicableAttributes');

// ========================================
// 🎯 SEO INTELLIGENCE MODULE
// ========================================

// Dashboard & Listings
$router->get('api/seo/intelligence/dashboard', \App\Controllers\SEOController::class, 'dashboard');
$router->get('api/seo/intelligence/listings', \App\Controllers\SEOController::class, 'listings');
$router->get('api/seo/intelligence/listings/{itemId}', \App\Controllers\SEOController::class, 'listingDetail');

// Audits
$router->post('api/seo/intelligence/audit/{itemId}', \App\Controllers\SEOController::class, 'auditListing');
$router->post('api/seo/intelligence/audit/batch', \App\Controllers\SEOController::class, 'batchAudit');
$router->get('api/seo/intelligence/audit/status/{jobId}', \App\Controllers\SEOController::class, 'getAuditJobStatus');
$router->get('api/seo/intelligence/history/{itemId}', \App\Controllers\SEOController::class, 'history');

// Competitors
$router->post('api/seo/intelligence/competitors/{itemId}/refresh', \App\Controllers\SEOController::class, 'refreshCompetitors');

// Hidden Attributes
$router->post('api/seo/intelligence/hidden-attributes/{itemId}/detect', \App\Controllers\SEOController::class, 'detectHiddenAttributes');
$router->post('api/seo/intelligence/hidden-attributes/{itemId}/apply', \App\Controllers\SEOController::class, 'applyHiddenAttribute');

// AI Center API
$router->get('api/ai-center/stats', \App\Controllers\AICenterController::class, 'getOverviewStats');
$router->get('api/ai-center/status', \App\Controllers\AICenterController::class, 'getAutomationStatus');
$router->post('api/ai/config/save', \App\Controllers\AICenterController::class, 'saveConfig');

// AI Optimization History API
$router->get('api/ai/optimization/history', \App\Controllers\AIOptimizationController::class, 'getHistory');
$router->get('api/ai/optimization/history/{logId}', \App\Controllers\AIOptimizationController::class, 'getHistoryDetail');

// Decision Engine API
// Versioning & Rollback
$router->post('api/seo/intelligence/rollback/{itemId}/{versionId}', \App\Controllers\SEOController::class, 'rollback');


// Multi-Account Management (v1.9.0)
$router->get('api/multi-account/dashboard', MultiAccountController::class, 'getDashboard');
$router->get('api/multi-account/compare', MultiAccountController::class, 'comparePerformance');
$router->post('api/multi-account/bulk-optimize', MultiAccountController::class, 'bulkOptimize');
$router->get('api/multi-account/report', MultiAccountController::class, 'getConsolidatedReport');
$router->post('api/multi-account/groups', MultiAccountController::class, 'manageGroups');
$router->post('api/multi-account/switch', MultiAccountController::class, 'switchAccount');
$router->get('api/multi-account/accounts', MultiAccountController::class, 'listAccounts');

// Token Management (Auto-refresh)
$router->get('api/multi-account/tokens/status', MultiAccountController::class, 'getTokensStatus');
$router->post('api/multi-account/tokens/refresh', MultiAccountController::class, 'refreshToken');
$router->post('api/multi-account/tokens/refresh-all', MultiAccountController::class, 'refreshAllTokens');

// Rotas de Mensagens Automáticas
$router->get('api/messages/templates', MessageController::class, 'index');
$router->post('api/messages/templates', MessageController::class, 'store');
$router->put('api/messages/templates/{id}', MessageController::class, 'update');
$router->delete('api/messages/templates/{id}', MessageController::class, 'delete');

// Rotas de Clonagem de Catálogo (API)
$router->post('api/catalog/clone', CatalogCloneController::class, 'cloneItem');
$router->post('api/catalog/clone/batch', CatalogCloneController::class, 'cloneBatch');
$router->post('api/catalog/clone/simulate', CatalogCloneController::class, 'simulate');
$router->post('api/catalog/clone/price-preview', CatalogCloneController::class, 'pricePreview');
$router->get('api/catalog/clone/metrics', CatalogCloneController::class, 'getMetrics');
$router->get('api/catalog/clone/schedules', CatalogCloneController::class, 'getSchedules');
$router->post('api/catalog/clone/schedules', CatalogCloneController::class, 'createSchedule');
$router->delete('api/catalog/clone/schedules/{id}', CatalogCloneController::class, 'cancelSchedule');
$router->get('api/catalog/metrics', CatalogCloneController::class, 'getMetrics');
$router->get('api/catalog/schedules', CatalogCloneController::class, 'getSchedules');
$router->post('api/catalog/schedules', CatalogCloneController::class, 'createSchedule');
$router->delete('api/catalog/schedules/{id}', CatalogCloneController::class, 'cancelSchedule');
$router->get('api/catalog/losing', CatalogController::class, 'listLosingItems');

// FASE 1-4: Novas rotas de Clonagem em Lote (Multi-conta)
$router->get('api/catalog/clone/source/seller/{sellerId}/items', CatalogCloneController::class, 'listSellerItems');
$router->get('api/catalog/clone/source/seller/{sellerId}/summary', CatalogCloneController::class, 'getSellerSummary');
$router->post('api/catalog/clone/source/items', CatalogCloneController::class, 'resolveItemIds');
$router->post('api/catalog/clone/dry-run', CatalogCloneController::class, 'dryRun');
$router->post('api/catalog/clone/item', CatalogCloneController::class, 'cloneItemNew');
$router->get('api/catalog/clone/jobs', CatalogCloneController::class, 'listJobs');
$router->post('api/catalog/clone/jobs', CatalogCloneController::class, 'createJob');
$router->get('api/catalog/clone/jobs/{jobId}/status', CatalogCloneController::class, 'getJobStatus');
$router->get('api/catalog/clone/history', CatalogCloneController::class, 'getHistory');

// FASE 5: Templates de Clonagem
$router->get('api/catalog/clone/templates', CatalogCloneController::class, 'listTemplates');
$router->get('api/catalog/clone/templates/{idOrSlug}', CatalogCloneController::class, 'getTemplate');
$router->post('api/catalog/clone/templates', CatalogCloneController::class, 'createTemplate');
$router->put('api/catalog/clone/templates/{id}', CatalogCloneController::class, 'updateTemplate');
$router->delete('api/catalog/clone/templates/{id}', CatalogCloneController::class, 'deleteTemplate');
$router->post('api/catalog/clone/templates/preview', CatalogCloneController::class, 'previewTemplate');

// FASE 6: Métricas e Observabilidade
$router->get('api/catalog/clone/metrics/dashboard', CatalogCloneController::class, 'getMetricsDashboard');
$router->get('api/catalog/clone/metrics/jobs', CatalogCloneController::class, 'getJobsMetrics');
$router->get('api/catalog/clone/metrics/errors', CatalogCloneController::class, 'getTopErrors');
$router->get('api/catalog/clone/metrics/weekly', CatalogCloneController::class, 'getWeeklyComparison');

// FASE 6: Ações Pós-Clone
$router->get('api/catalog/clone/post-actions/stats', CatalogCloneController::class, 'getPostActionsStats');
$router->post('api/catalog/clone/post-actions/process', CatalogCloneController::class, 'processPostActions');

// FASE 6: Monitoramento e Hardening
$router->get('api/catalog/clone/monitoring/health', CatalogCloneController::class, 'getSystemHealth');
$router->get('api/catalog/clone/monitoring/alerts', CatalogCloneController::class, 'listAlerts');
$router->post('api/catalog/clone/monitoring/alerts/{id}/acknowledge', CatalogCloneController::class, 'acknowledgeAlert');
$router->get('api/catalog/clone/monitoring/flags', CatalogCloneController::class, 'listFeatureFlags');
$router->put('api/catalog/clone/monitoring/flags/{name}', CatalogCloneController::class, 'updateFeatureFlag');
$router->get('api/catalog/clone/monitoring/report', CatalogCloneController::class, 'getDailyReport');

// FASE 9: Notificações Slack/Discord
$router->get('api/clone/notifications/webhooks', CloneNotificationController::class, 'listWebhooks');
$router->post('api/clone/notifications/slack', CloneNotificationController::class, 'configureSlack');
$router->post('api/clone/notifications/discord', CloneNotificationController::class, 'configureDiscord');
$router->post('api/clone/notifications/webhook/{webhookId}/test', CloneNotificationController::class, 'testWebhook');
$router->put('api/clone/notifications/webhook/{webhookId}/enable', CloneNotificationController::class, 'enableWebhook');
$router->put('api/clone/notifications/webhook/{webhookId}/disable', CloneNotificationController::class, 'disableWebhook');
$router->delete('api/clone/notifications/webhook/{webhookId}', CloneNotificationController::class, 'deleteWebhook');
$router->get('api/clone/notifications/history', CloneNotificationController::class, 'getHistory');
$router->get('api/clone/notifications/events', CloneNotificationController::class, 'listEvents');

// FASE 9: A/B Testing de Variações
$router->get('api/clone/ab-tests', CloneABTestingController::class, 'listTests');
$router->post('api/clone/ab-tests', CloneABTestingController::class, 'createTest');
$router->get('api/clone/ab-tests/{testId}', CloneABTestingController::class, 'getTest');
$router->post('api/clone/ab-tests/{testId}/start', CloneABTestingController::class, 'startTest');
$router->post('api/clone/ab-tests/{testId}/pause', CloneABTestingController::class, 'pauseTest');
$router->post('api/clone/ab-tests/{testId}/complete', CloneABTestingController::class, 'completeTest');
$router->delete('api/clone/ab-tests/{testId}', CloneABTestingController::class, 'cancelTest');
$router->post('api/clone/ab-tests/{testId}/apply-winner', CloneABTestingController::class, 'applyWinner');
$router->post('api/clone/ab-tests/{testId}/sync-metrics', CloneABTestingController::class, 'syncMetrics');
$router->get('api/clone/ab-tests/{testId}/winner', CloneABTestingController::class, 'getWinner');
$router->post('api/clone/ab-tests/generate-variations', CloneABTestingController::class, 'generateVariations');
$router->post('api/clone/ab-tests/variations/{variationId}/metrics', CloneABTestingController::class, 'recordMetrics');

// FASE 9: Auto-Clonagem Programada por Regras
$router->get('api/clone/automation/rules', CloneAutomationController::class, 'listRules');
$router->post('api/clone/automation/rules', CloneAutomationController::class, 'createRule');
$router->get('api/clone/automation/rules/{id}', CloneAutomationController::class, 'getRule');
$router->put('api/clone/automation/rules/{id}', CloneAutomationController::class, 'updateRule');
$router->delete('api/clone/automation/rules/{id}', CloneAutomationController::class, 'deleteRule');
$router->post('api/clone/automation/rules/{id}/enable', CloneAutomationController::class, 'enableRule');
$router->post('api/clone/automation/rules/{id}/pause', CloneAutomationController::class, 'pauseRule');
$router->post('api/clone/automation/rules/{id}/execute', CloneAutomationController::class, 'executeRule');
$router->post('api/clone/automation/rules/{id}/preview', CloneAutomationController::class, 'previewExecution');
$router->get('api/clone/automation/rules/{id}/history', CloneAutomationController::class, 'getExecutionHistory');
$router->get('api/clone/automation/stats', CloneAutomationController::class, 'getStats');
$router->get('api/clone/automation/triggers', CloneAutomationController::class, 'getTriggerTypes');

// FASE 10: Recomendações de Sellers (ML-powered)
$router->get('api/clone/recommendations/sellers', CloneSellerRecommendationController::class, 'getRecommendations');
$router->get('api/clone/recommendations/sellers/{sellerId}/similar', CloneSellerRecommendationController::class, 'getSimilarSellers');
$router->get('api/clone/recommendations/sellers/by-category', CloneSellerRecommendationController::class, 'getTopByCategory');
$router->get('api/clone/recommendations/trends', CloneSellerRecommendationController::class, 'getTrends');
$router->get('api/clone/recommendations/stats', CloneSellerRecommendationController::class, 'getStats');

// FASE 10: Análise de ROI e Performance
$router->get('api/clone/roi/analysis', CloneROIAnalysisController::class, 'getAnalysis');
$router->get('api/clone/roi/items/{itemId}', CloneROIAnalysisController::class, 'getItemComparison');
$router->post('api/clone/roi/items/{itemId}/metrics', CloneROIAnalysisController::class, 'recordMetrics');
$router->post('api/clone/roi/sync', CloneROIAnalysisController::class, 'syncMetrics');
$router->get('api/clone/roi/timeline', CloneROIAnalysisController::class, 'getTimeline');

// FASE 10: SEO Integration no Fluxo de Clone
$router->post('api/clone/seo/optimize', CloneSEOIntegrationController::class, 'optimize');
$router->post('api/clone/seo/score', CloneSEOIntegrationController::class, 'getScore');
$router->get('api/clone/seo/settings', CloneSEOIntegrationController::class, 'getSettings');
$router->put('api/clone/seo/settings', CloneSEOIntegrationController::class, 'updateSettings');
$router->get('api/clone/seo/stats', CloneSEOIntegrationController::class, 'getStats');
$router->post('api/clone/seo/preview-batch', CloneSEOIntegrationController::class, 'previewBatch');

// FASE 11: Compliance e Auditoria
$router->get('api/clone/compliance/logs', \App\Controllers\CloneComplianceController::class, 'getLogs');
$router->get('api/clone/compliance/jobs/{jobId}/trail', \App\Controllers\CloneComplianceController::class, 'getJobTrail');
$router->get('api/clone/compliance/items/{itemId}/trail', \App\Controllers\CloneComplianceController::class, 'getItemTrail');
$router->get('api/clone/compliance/report', \App\Controllers\CloneComplianceController::class, 'getReport');
$router->get('api/clone/compliance/stats', \App\Controllers\CloneComplianceController::class, 'getStats');
$router->post('api/clone/compliance/export', \App\Controllers\CloneComplianceController::class, 'exportLogs');
$router->get('api/clone/compliance/download/{filename}', \App\Controllers\CloneComplianceController::class, 'downloadExport');
$router->get('api/clone/compliance/event-types', \App\Controllers\CloneComplianceController::class, 'getEventTypes');

// FASE 11: Analytics Integration
$router->get('api/clone/analytics/dashboard', \App\Controllers\CloneAnalyticsController::class, 'getDashboard');
$router->get('api/clone/analytics/kpis', \App\Controllers\CloneAnalyticsController::class, 'getKPIs');
$router->get('api/clone/analytics/trends', \App\Controllers\CloneAnalyticsController::class, 'getTrends');
$router->get('api/clone/analytics/performance', \App\Controllers\CloneAnalyticsController::class, 'getPerformance');
$router->get('api/clone/analytics/breakdown', \App\Controllers\CloneAnalyticsController::class, 'getBreakdown');
$router->get('api/clone/analytics/compare', \App\Controllers\CloneAnalyticsController::class, 'comparePeriods');
$router->get('api/clone/analytics/projection', \App\Controllers\CloneAnalyticsController::class, 'getProjection');
$router->post('api/clone/analytics/events', \App\Controllers\CloneAnalyticsController::class, 'trackEvent');
$router->get('api/clone/analytics/events', \App\Controllers\CloneAnalyticsController::class, 'getEvents');

// FASE 12: Gerenciamento de Itens Clonados e Sincronização
$router->get('api/clone/items', CloneManagementController::class, 'listItems');
$router->get('api/clone/items/stats', CloneManagementController::class, 'getStats');
$router->get('api/clone/items/top-sellers', CloneManagementController::class, 'getTopSellers');
$router->get('api/clone/items/attention', CloneManagementController::class, 'getItemsNeedingAttention');
$router->get('api/clone/items/distribution/category', CloneManagementController::class, 'getCategoryDistribution');
$router->get('api/clone/items/distribution/seller', CloneManagementController::class, 'getSellerDistribution');
$router->get('api/clone/items/export', CloneManagementController::class, 'exportItems');
$router->get('api/clone/items/{itemId}', CloneManagementController::class, 'getItem');
$router->put('api/clone/items/{itemId}', CloneManagementController::class, 'updateItem');
$router->post('api/clone/items/{itemId}/pause', CloneManagementController::class, 'pauseItem');
$router->post('api/clone/items/{itemId}/activate', CloneManagementController::class, 'activateItem');
$router->post('api/clone/items/{itemId}/close', CloneManagementController::class, 'closeItem');
$router->post('api/clone/items/batch', CloneManagementController::class, 'batchOperation');

// FASE 12: Sincronização Bidirecional
$router->post('api/clone/sync/all', CloneManagementController::class, 'syncAll');
$router->post('api/clone/sync/item/{itemId}', CloneManagementController::class, 'syncItem');
$router->put('api/clone/sync/price/{itemId}', CloneManagementController::class, 'updatePrice');
$router->put('api/clone/sync/stock/{itemId}', CloneManagementController::class, 'updateStock');
$router->put('api/clone/sync/status/{itemId}', CloneManagementController::class, 'updateStatus');
$router->post('api/clone/sync/prices/batch', CloneManagementController::class, 'batchUpdatePrices');
$router->get('api/clone/sync/history/{itemId}', CloneManagementController::class, 'getSyncHistory');
$router->get('api/clone/sync/settings', CloneManagementController::class, 'getSyncSettings');
$router->put('api/clone/sync/settings', CloneManagementController::class, 'updateSyncSettings');
$router->get('api/clone/sync/alerts', CloneManagementController::class, 'getAlerts');
$router->post('api/clone/sync/alerts/{alertId}/resolve', CloneManagementController::class, 'resolveAlert');

// FASE 12+: SEO Avançado, Export, Health, Batch Operations
$router->post('api/clone/seo/analyze', \App\Controllers\CloneAdvancedController::class, 'analyzeSeo');
$router->post('api/clone/seo/analyze/batch', \App\Controllers\CloneAdvancedController::class, 'analyzeBatchSeo');
$router->post('api/clone/seo/optimize/title', \App\Controllers\CloneAdvancedController::class, 'optimizeTitle');
$router->post('api/clone/seo/optimize/description', \App\Controllers\CloneAdvancedController::class, 'optimizeDescription');

// Export de Dados
$router->post('api/clone/export/items/csv', \App\Controllers\CloneAdvancedController::class, 'exportItemsCsv');
$router->post('api/clone/export/items/json', \App\Controllers\CloneAdvancedController::class, 'exportItemsJson');
$router->post('api/clone/export/jobs', \App\Controllers\CloneAdvancedController::class, 'exportJobs');
$router->post('api/clone/export/metrics', \App\Controllers\CloneAdvancedController::class, 'exportMetrics');
$router->post('api/clone/export/report', \App\Controllers\CloneAdvancedController::class, 'exportFullReport');
$router->get('api/clone/export/list', \App\Controllers\CloneAdvancedController::class, 'listExports');
$router->get('api/clone/export/download/{filename}', \App\Controllers\CloneAdvancedController::class, 'downloadExport');

// Health Monitoring
$router->get('api/clone/health', \App\Controllers\CloneAdvancedController::class, 'getHealth');
$router->get('api/clone/health/diagnostics', \App\Controllers\CloneAdvancedController::class, 'getDiagnostics');

// Batch Operations Avançadas
$router->post('api/clone/batch/repricing', \App\Controllers\CloneAdvancedController::class, 'batchRepricing');
$router->post('api/clone/batch/stock', \App\Controllers\CloneAdvancedController::class, 'batchStockUpdate');
$router->post('api/clone/batch/status', \App\Controllers\CloneAdvancedController::class, 'batchStatusChange');
$router->post('api/clone/batch/titles', \App\Controllers\CloneAdvancedController::class, 'batchTitleUpdate');
$router->post('api/clone/batch/prices', \App\Controllers\CloneAdvancedController::class, 'batchPriceUpdate');
$router->post('api/clone/batch/sync-metrics', \App\Controllers\CloneAdvancedController::class, 'batchSyncMetrics');
$router->post('api/clone/batch/seo-optimize', \App\Controllers\CloneAdvancedController::class, 'batchSeoOptimize');
$router->post('api/clone/batch/close-stale', \App\Controllers\CloneAdvancedController::class, 'closeStaleItems');
$router->get('api/clone/batch/history', \App\Controllers\CloneAdvancedController::class, 'getBatchHistory');

// Analytics Extras
$router->get('api/clone/analytics/summary', \App\Controllers\CloneAdvancedController::class, 'getAnalyticsSummary');

// Auto-Scheduler (Clonagem Automática Programada)
$router->get('api/clone/schedules', \App\Controllers\CloneSchedulerController::class, 'listSchedules');
$router->get('api/clone/schedules/stats', \App\Controllers\CloneSchedulerController::class, 'getScheduleStats');
$router->post('api/clone/schedules', \App\Controllers\CloneSchedulerController::class, 'createSchedule');
$router->get('api/clone/schedules/{id}', \App\Controllers\CloneSchedulerController::class, 'getSchedule');
$router->put('api/clone/schedules/{id}', \App\Controllers\CloneSchedulerController::class, 'updateSchedule');
$router->delete('api/clone/schedules/{id}', \App\Controllers\CloneSchedulerController::class, 'deleteSchedule');
$router->post('api/clone/schedules/{id}/pause', \App\Controllers\CloneSchedulerController::class, 'pauseSchedule');
$router->post('api/clone/schedules/{id}/resume', \App\Controllers\CloneSchedulerController::class, 'resumeSchedule');
$router->post('api/clone/schedules/{id}/execute', \App\Controllers\CloneSchedulerController::class, 'executeSchedule');
$router->get('api/clone/schedules/{id}/history', \App\Controllers\CloneSchedulerController::class, 'getScheduleHistory');

// ML Recommendations (Recomendações Inteligentes)
// Note: 'sellers' and 'trends' routes are defined above (CloneSellerRecommendationController, FASE 10)
$router->get('api/clone/recommendations/products', \App\Controllers\CloneSchedulerController::class, 'getProductRecommendations');
$router->get('api/clone/recommendations/categories', \App\Controllers\CloneSchedulerController::class, 'getCategoryRecommendations');
$router->get('api/clone/recommendations/predict/{itemId}', \App\Controllers\CloneSchedulerController::class, 'predictPerformance');

// Clone Event Triggers
$router->get('api/clone/triggers', \App\Controllers\CloneEventTriggerController::class, 'listTriggers');
$router->post('api/clone/triggers', \App\Controllers\CloneEventTriggerController::class, 'createTrigger');
$router->get('api/clone/triggers/stats', \App\Controllers\CloneEventTriggerController::class, 'getTriggerStats');
$router->get('api/clone/triggers/{triggerId}', \App\Controllers\CloneEventTriggerController::class, 'getTrigger');
$router->put('api/clone/triggers/{triggerId}', \App\Controllers\CloneEventTriggerController::class, 'updateTrigger');
$router->delete('api/clone/triggers/{triggerId}', \App\Controllers\CloneEventTriggerController::class, 'deleteTrigger');
$router->post('api/clone/triggers/{triggerId}/activate', \App\Controllers\CloneEventTriggerController::class, 'activateTrigger');
$router->post('api/clone/triggers/{triggerId}/deactivate', \App\Controllers\CloneEventTriggerController::class, 'deactivateTrigger');
$router->post('api/clone/triggers/{triggerId}/test', \App\Controllers\CloneEventTriggerController::class, 'testTrigger');
$router->get('api/clone/triggers/{triggerId}/history', \App\Controllers\CloneEventTriggerController::class, 'getTriggerHistory');

// Clone Trend Charts
$router->get('api/clone/charts/dashboard', \App\Controllers\CloneEventTriggerController::class, 'getDashboardCharts');
$router->get('api/clone/charts/clones-per-day', \App\Controllers\CloneEventTriggerController::class, 'getClonesPerDayChart');
$router->get('api/clone/charts/success-by-hour', \App\Controllers\CloneEventTriggerController::class, 'getSuccessByHourChart');
$router->get('api/clone/charts/by-category', \App\Controllers\CloneEventTriggerController::class, 'getByCategoryChart');
$router->get('api/clone/charts/seller-performance', \App\Controllers\CloneEventTriggerController::class, 'getSellerPerformanceChart');
$router->get('api/clone/charts/clone-time', \App\Controllers\CloneEventTriggerController::class, 'getCloneTimeChart');
$router->get('api/clone/charts/status-distribution', \App\Controllers\CloneEventTriggerController::class, 'getStatusDistributionChart');
$router->get('api/clone/charts/schedule-executions', \App\Controllers\CloneEventTriggerController::class, 'getScheduleExecutionsChart');
$router->get('api/clone/charts/events-by-type', \App\Controllers\CloneEventTriggerController::class, 'getEventsByTypeChart');
$router->get('api/clone/charts/quality-metrics', \App\Controllers\CloneEventTriggerController::class, 'getQualityMetricsChart');

// Rotas de categorias
$router->get('api/categories', CategoryController::class, 'index');
$router->get('api/categories/tree', CategoryController::class, 'tree');
$router->get('api/categories/search', CategoryController::class, 'search');
$router->get('api/categories/{id}', CategoryController::class, 'show');
$router->get('api/categories/{id}/brands', CategoryController::class, 'brands');
$router->get('api/categories/{id}/subcategories', CategoryController::class, 'subcategories');
$router->get('api/categories/{id}/attributes', CategoryController::class, 'attributes');
$router->get('api/categories/{id}/filterable-attributes', CategoryController::class, 'filterableAttributes');
$router->get('api/categories/{id}/required-attributes', CategoryController::class, 'requiredAttributes');
$router->post('api/categories/{id}/validate-attributes', CategoryController::class, 'validateAttributes');
$router->get('api/categories/{id}/attributes/{attributeId}/values', CategoryController::class, 'attributeValues');

// Rotas de busca
$router->get('api/search', SearchController::class, 'search');
$router->get('api/search/analyze', SearchController::class, 'analyze');
$router->get('api/search/lazy', SearchController::class, 'lazySearch');
$router->get('api/search/load-more', SearchController::class, 'loadMore');

// Rotas de anúncios (items)
$router->get('api/items', ItemController::class, 'index');
$router->get('api/items/stats', ItemController::class, 'stats');
$router->get('api/items/categories', ItemController::class, 'categories');
$router->get('api/items/{id}', ItemController::class, 'show');
$router->post('api/items', ItemController::class, 'create');
$router->put('api/items/{id}', ItemController::class, 'update');
$router->delete('api/items/{id}', ItemController::class, 'delete');
$router->post('api/items/sync', ItemController::class, 'sync');
$router->post('api/items/{id}/pause', ItemController::class, 'pause');
$router->post('api/items/{id}/activate', ItemController::class, 'activate');
$router->post('api/items/{id}/close', ItemController::class, 'close');
$router->put('api/items/{id}/price', ItemController::class, 'updatePrice');
$router->put('api/items/{id}/stock', ItemController::class, 'updateStock');
$router->put('api/items/{id}/description', ItemController::class, 'updateDescription');
$router->get('api/items/status/{status}', ItemController::class, 'byStatus');
$router->get('api/items/category/{categoryId}', ItemController::class, 'byCategory');

// Rotas de exportação
$router->get('api/export/analysis/csv', ExportController::class, 'analysisCSV');
$router->get('api/export/analysis/json', ExportController::class, 'analysisJSON');
$router->get('api/export/report/pdf', ExportController::class, 'reportPDF');
$router->get('api/export/user/json', ExportController::class, 'userDataJSON');
$router->get('api/export/user/csv', ExportController::class, 'userDataCSV');

// Rotas de backup
$router->get('api/backup/list', BackupController::class, 'list');
$router->post('api/backup/create', BackupController::class, 'create');
$router->post('api/backup/restore', BackupController::class, 'restore');
$router->post('api/backup/clean', BackupController::class, 'clean');

// Rotas de estatísticas
$router->get('api/statistics', StatisticsController::class, 'index');
$router->get('api/statistics/period', StatisticsController::class, 'byPeriod');
$router->get('api/statistics/top-products', StatisticsController::class, 'topProducts');
$router->get('api/statistics/by-category', StatisticsController::class, 'byCategory');

// Rotas de analytics
$router->get('api/analytics/dashboard', AnalyticsController::class, 'dashboard');
$router->get('api/analytics/api-stats', AnalyticsController::class, 'apiStats');
$router->get('api/analytics/requests-chart', AnalyticsController::class, 'requestsChart');
$router->get('api/analytics/sales', AnalyticsController::class, 'salesMetrics');
$router->get('api/analytics/listings', AnalyticsController::class, 'listingsMetrics');
$router->get('api/analytics/questions', AnalyticsController::class, 'questionsMetrics');
$router->get('api/analytics/export', AnalyticsController::class, 'exportReport');

// Rotas de notificações
$router->get('api/notifications', NotificationController::class, 'index');
$router->get('api/notifications/unread-count', NotificationController::class, 'unreadCount');
$router->post('api/notifications/{id}/read', NotificationController::class, 'markAsRead');
$router->post('api/notifications/mark-all-read', NotificationController::class, 'markAllAsRead');
$router->delete('api/notifications/{id}', NotificationController::class, 'delete');
$router->delete('api/notifications/clear-old', NotificationController::class, 'clearOld');
$router->get('api/notifications/preferences', NotificationController::class, 'preferences');
$router->put('api/notifications/preferences', NotificationController::class, 'updatePreferences');

// Rotas de sincronização
$router->get('api/sync/status', SyncController::class, 'status');
$router->post('api/sync/trigger', SyncController::class, 'trigger');
$router->get('api/sync/history', SyncController::class, 'history');

// Rotas de dashboard API
$router->get('api/dashboard/metrics', DashboardController::class, 'metrics');
$router->get('api/dashboard/preferences', DashboardController::class, 'getPreferences');
$router->post('api/dashboard/preferences', DashboardController::class, 'savePreferences');
$router->get('api/dashboard/accounts', DashboardController::class, 'accounts');
$router->post('api/dashboard/switch-account', DashboardController::class, 'switchAccount');
$router->post('api/settings/global', \App\Controllers\SettingsController::class, 'saveGlobal');
$router->get('api/settings/global', \App\Controllers\SettingsController::class, 'getGlobal');
$router->get('api/settings/ml-diagnostico', \App\Controllers\SettingsController::class, 'mlDiagnostico');
$router->post('api/settings/ml-refresh', \App\Controllers\SettingsController::class, 'mlRefresh');
$router->post('api/settings/notifications', \App\Controllers\SettingsController::class, 'saveNotifications');
$router->post('api/settings/telegram', \App\Controllers\SettingsController::class, 'saveTelegram');
$router->post('api/settings/sync', \App\Controllers\SettingsController::class, 'saveSync');

// Proxy management
$router->get('api/proxies', ProxyController::class, 'index');
$router->get('api/proxies/status', ProxyController::class, 'status');
$router->post('api/proxies', ProxyController::class, 'store');
$router->post('api/proxies/{id}/test', ProxyController::class, 'test');
$router->post('api/proxies/test-all', ProxyController::class, 'testAll');
$router->delete('api/proxies/{id}', ProxyController::class, 'destroy');
$router->post('api/proxies/clear-blacklist', ProxyController::class, 'clearBlacklist');

// Rotas de pedidos
$router->get('api/orders', OrderController::class, 'index');
$router->get('api/orders/all', OrderController::class, 'all');
$router->get('api/orders/{id}', OrderController::class, 'show');
$router->post('api/orders/sync', OrderController::class, 'sync');
$router->get('api/orders/sync', OrderController::class, 'sync');

// Rotas de alertas
$router->get('api/alerts', AlertController::class, 'index');
$router->get('api/alerts/count', AlertController::class, 'count');
$router->get('api/alerts/detect-new-products', AlertController::class, 'detectNewProducts');
$router->post('api/alerts/{id}/read', AlertController::class, 'markRead');
$router->post('api/alerts/read-all', AlertController::class, 'markAllRead');

// Rotas de análise de concorrência
$router->get('api/competitors', CompetitorController::class, 'index');
$router->post('api/competitors', CompetitorController::class, 'add');
$router->delete('api/competitors/{sellerId}', CompetitorController::class, 'remove');
$router->get('api/competitors/analyze', CompetitorController::class, 'analyze');
$router->get('api/competitors/opportunities', CompetitorController::class, 'opportunities');

// Rotas de oportunidades
$router->get('api/opportunities/products-without-catalog', OpportunityController::class, 'productsWithoutCatalog');
$router->get('api/opportunities/low-competition', OpportunityController::class, 'lowCompetitionCategories');
$router->get('api/opportunities/best-sellers', OpportunityController::class, 'bestSellersWithoutListing');

// Rotas de histórico de preços (removidas - PriceHistoryController sem dados)

// Rotas de auditoria
$router->get('api/audit', AuditController::class, 'index');

// Rotas de jobs
$router->get('api/jobs/stats', JobController::class, 'stats');
$router->post('api/jobs/status', JobController::class, 'status');
$router->post('api/jobs', JobController::class, 'dispatch');
$router->post('api/jobs/process', JobController::class, 'process');
$router->post('api/jobs/clean', JobController::class, 'clean');
$router->get('api/jobs/{id}', JobController::class, 'getJob');

// Rotas de relatórios
$router->get('api/reports/account/{accountId}', ReportController::class, 'byAccount');
$router->get('api/reports/category/{categoryId}', ReportController::class, 'byCategory');
$router->get('api/reports/brand/{brand}', ReportController::class, 'byBrand');
$router->get('api/reports/consolidated', ReportController::class, 'consolidated');

// Rotas de polling
$router->get('api/polling/status', PollingController::class, 'status');
$router->post('api/polling/orders', PollingController::class, 'pollOrders');
$router->post('api/polling/items', PollingController::class, 'pollItems');
$router->post('api/polling/all', PollingController::class, 'pollAll');

// Rotas de compatibilidade
$router->get('api/compatibility/search', CompatibilityController::class, 'search');
$router->post('api/compatibility/validate/{itemId}', CompatibilityController::class, 'validate');
$router->get('api/compatibility/suggest', CompatibilityController::class, 'suggest');
$router->get('api/compatibility/attributes/{categoryId}', CompatibilityController::class, 'attributes');

// Rotas de Perguntas e Respostas (Q&A)
$router->get('api/questions', QuestionController::class, 'index');
$router->get('api/questions/unanswered/count', QuestionController::class, 'countUnanswered');
$router->get('api/questions/{id}', QuestionController::class, 'show');
$router->post('api/questions/{id}/answer', QuestionController::class, 'answer');
$router->post('api/questions/{id}/draft', QuestionController::class, 'draft');
$router->delete('api/questions/{id}', QuestionController::class, 'delete');

// ROTAS DE SEO
$router->get('api/seo/dashboard', \App\Controllers\SEOToolsController::class, 'dashboard');
$router->get('api/seo/analyze/{itemId}', \App\Controllers\SEOToolsController::class, 'analyzeItem');
$router->post('api/seo/analyze', \App\Controllers\SEOToolsController::class, 'analyze');
$router->post('api/seo/analyze/batch', \App\Controllers\SEOToolsController::class, 'analyzeBatch');
$router->get('api/seo/keywords/{categoryId}', \App\Controllers\SEOToolsController::class, 'keywords');
$router->post('api/seo/keywords/volume', \App\Controllers\SEOToolsController::class, 'keywordVolume');
$router->get('api/seo/keywords/variations', \App\Controllers\SEOToolsController::class, 'keywordVariations');
$router->get('api/seo/trends/{categoryId}', \App\Controllers\SEOToolsController::class, 'trends');
$router->post('api/seo/title/optimize', \App\Controllers\SEOToolsController::class, 'optimizeTitle');
$router->post('api/seo/title/analyze', \App\Controllers\SEOToolsController::class, 'analyzeTitle');
$router->post('api/seo/title/suggest', \App\Controllers\SEOToolsController::class, 'suggestTitle');
$router->post('api/seo/listing/build', \App\Controllers\SEOToolsController::class, 'buildListing');
$router->post('api/seo/listing/description', \App\Controllers\SEOToolsController::class, 'buildDescription');
$router->post('api/seo/listing/publish', \App\Controllers\SEOToolsController::class, 'publishListing');
$router->get('api/seo/listing/duplicate/{itemId}', \App\Controllers\SEOToolsController::class, 'duplicateListing');
$router->get('api/seo/pricing/{categoryId}', \App\Controllers\SEOToolsController::class, 'pricing');
$router->post('api/seo/pricing/suggest', \App\Controllers\SEOToolsController::class, 'suggestPrice');
$router->post('api/seo/pricing/compare', \App\Controllers\SEOToolsController::class, 'comparePrice');
$router->post('api/seo/pricing/calculate', \App\Controllers\SEOToolsController::class, 'calculatePrice');
$router->get('api/seo/pricing/track/{itemId}', \App\Controllers\SEOToolsController::class, 'trackPrice');

// DEEP RESEARCH
$router->get('api/research/brand/{categoryId}/{brand}', DeepResearchController::class, 'researchBrand');
$router->post('api/research/brand', DeepResearchController::class, 'researchBrandPost');
$router->get('api/research/quick/{categoryId}/{brand}', DeepResearchController::class, 'quickResearch');
$router->get('api/research/compare/{categoryId}/{brand1}/{brand2}', DeepResearchController::class, 'compareBrands');
$router->get('api/research/sellers/{categoryId}', DeepResearchController::class, 'topSellersCategory');
$router->get('api/research/opportunities/{categoryId}/{brand}', DeepResearchController::class, 'getOpportunities');
$router->get('api/research/pricing-analysis/{categoryId}/{brand}', DeepResearchController::class, 'pricingAnalysis');
$router->get('api/research/shipping-analysis/{categoryId}/{brand}', DeepResearchController::class, 'shippingAnalysis');
$router->post('api/research/simulate-profitability', DeepResearchController::class, 'simulateProfitability');
$router->post('api/research/keywords/analyze', DeepResearchController::class, 'analyzeKeywords');

// AGENTS
$router->post('api/agent/projects/start', AgentController::class, 'startProject');
$router->post('api/agent/projects/{id}/session', AgentController::class, 'runCodingSession');
$router->get('api/agent/projects/{id}/status', AgentController::class, 'getStatus');
$router->post('api/agent/projects/{id}/test', AgentController::class, 'testFeature');
$router->get('api/agent/projects', AgentController::class, 'listProjects');

// SECURITY
$router->get('security', SecurityController::class, 'dashboard');
$router->get('api/security/events', SecurityController::class, 'listEvents');
$router->get('api/security/stats', SecurityController::class, 'stats');
$router->get('api/security/blocked-ips', SecurityController::class, 'listBlockedIps');
$router->post('api/security/block-ip', SecurityController::class, 'blockIp');
$router->post('api/security/unblock-ip', SecurityController::class, 'unblockIp');
$router->post('api/security/migrate-tokens', SecurityController::class, 'migrateTokens');
$router->get('api/security/tokens-status', SecurityController::class, 'tokensStatus');
$router->post('api/security/cleanup-logs', SecurityController::class, 'cleanupLogs');
$router->get('api/security/export', SecurityController::class, 'exportReport');

// PERFORMANCE
$router->get('api/performance/dashboard', PerformanceController::class, 'dashboard');
$router->get('api/performance/cache', PerformanceController::class, 'cacheStats');
$router->post('api/performance/cache/flush', PerformanceController::class, 'flushCache');
$router->get('api/performance/slow-queries', PerformanceController::class, 'slowQueries');
$router->get('api/performance/api-metrics', PerformanceController::class, 'apiMetrics');
$router->get('api/performance/jobs', PerformanceController::class, 'jobs');
$router->post('api/performance/optimize', PerformanceController::class, 'optimizeTables');
$router->post('api/performance/cleanup', PerformanceController::class, 'cleanup');
$router->get('api/performance/config', PerformanceController::class, 'config');
$router->post('api/performance/config', PerformanceController::class, 'updateConfig');

// RELATÓRIOS AVANÇADOS
$router->get('api/reports/sales-timeline', AdvancedReportController::class, 'salesTimeline');
$router->get('api/reports/top-products', AdvancedReportController::class, 'topProducts');
$router->get('api/reports/hourly', AdvancedReportController::class, 'hourly');
$router->get('api/reports/by-category', AdvancedReportController::class, 'byCategory');
$router->get('api/export/dashboard', AdvancedReportController::class, 'export');

// PDF
$router->get('api/pdf/sales', PdfController::class, 'salesReport');
$router->get('api/pdf/market', PdfController::class, 'marketAnalysis');
$router->get('api/pdf/orders', PdfController::class, 'ordersReport');
$router->get('api/pdf/dashboard', PdfController::class, 'executiveDashboard');
$router->get('api/pdf/listing/{itemId}', PdfController::class, 'listingAnalysis');
$router->get('api/pdf/brand/awa', PdfController::class, 'brandAnalysisReport');

// BRAND ANALYZER
$router->get('api/brand/awa/dashboard', BrandAnalyzerController::class, 'dashboard');
$router->get('api/brand/awa/analyze', BrandAnalyzerController::class, 'analyzeAwa');
$router->get('api/brand/awa/quick', BrandAnalyzerController::class, 'quickAnalysis');
$router->get('api/brand/awa/gaps', BrandAnalyzerController::class, 'getGaps');
$router->get('api/brand/awa/inconsistencies', BrandAnalyzerController::class, 'getInconsistencies');
$router->get('api/brand/awa/sellers', BrandAnalyzerController::class, 'getSellers');
$router->get('api/brand/awa/summary', BrandAnalyzerController::class, 'getSummary');
$router->get('api/brand/awa/history', BrandAnalyzerController::class, 'getHistory');
$router->get('api/brand/awa/pricing', BrandAnalyzerController::class, 'getPricing');
$router->get('api/brand/awa/shipping', BrandAnalyzerController::class, 'getShipping');
$router->get('api/brand/awa/items', BrandAnalyzerController::class, 'listItems');
$router->get('api/brand/awa/export/csv', BrandAnalyzerController::class, 'exportCSV');
$router->get('api/brand/awa/export/json', BrandAnalyzerController::class, 'exportJSON');
$router->get('api/brand/awa/compare', BrandAnalyzerController::class, 'compareCompetitors');
$router->get('api/brand/awa/trends', BrandAnalyzerController::class, 'getTrends');
$router->get('api/brand/awa/alerts', BrandAnalyzerController::class, 'getAlerts');
$router->get('api/brand/awa/top-products', BrandAnalyzerController::class, 'getTopProducts');
$router->get('api/brand/awa/opportunities', BrandAnalyzerController::class, 'getOpportunities');
$router->get('api/brand/awa/seller-stats', BrandAnalyzerController::class, 'getSellerStats');
$router->get('api/brand/awa/patterns', BrandAnalyzerController::class, 'getPatterns');
$router->get('api/brand/awa/report', BrandAnalyzerController::class, 'getFullReport');
$router->get('api/brand/awa/export/fix-list', BrandAnalyzerController::class, 'exportFixList');
$router->get('api/brand/awa/metrics', BrandAnalyzerController::class, 'getMetrics');

// PUSH
$router->get('api/push/vapid-key', PushController::class, 'vapidKey');
$router->post('api/push/subscribe', PushController::class, 'subscribe');
$router->post('api/push/unsubscribe', PushController::class, 'unsubscribe');
$router->get('api/push/subscriptions', PushController::class, 'subscriptions');
$router->post('api/push/test', PushController::class, 'test');
$router->post('api/push/send', PushController::class, 'send');
$router->get('api/push/stats', PushController::class, 'stats');
$router->get('api/push/status', PushController::class, 'status');
$router->post('api/push/track-install', PushController::class, 'trackInstall');
$router->post('api/push/device/register', PushController::class, 'registerDevice');
$router->post('api/push/device/unregister', PushController::class, 'unregisterDevice');

// REALTIME NOTIFICATIONS
$router->get('api/notifications/poll', RealTimeNotificationController::class, 'poll');
$router->get('api/notifications/realtime/poll', RealTimeNotificationController::class, 'poll');
$router->get('api/notifications/realtime/unread', RealTimeNotificationController::class, 'unread');
$router->post('api/notifications/realtime/{id}/read', RealTimeNotificationController::class, 'markRead');
$router->post('api/notifications/realtime/read-all', RealTimeNotificationController::class, 'markAllRead');
$router->get('api/notifications/realtime/settings', RealTimeNotificationController::class, 'getSettings');
$router->post('api/notifications/realtime/settings', RealTimeNotificationController::class, 'saveSettings');
$router->get('api/notifications/realtime/stats', RealTimeNotificationController::class, 'stats');
$router->post('api/notifications/test-sound', RealTimeNotificationController::class, 'testSound');

// HEALTH
$router->get('api/health', HealthController::class, 'check');
$router->get('api/health/live', HealthController::class, 'live');
$router->get('api/health/ready', HealthController::class, 'ready');
$router->get('api/health/ml', HealthController::class, 'mercadoLivre');
$router->get('api/health/integrations', HealthController::class, 'integrations');

// API TOKENS
$router->get('api/tokens', ApiTokenController::class, 'index');
$router->post('api/tokens', ApiTokenController::class, 'create');
$router->delete('api/tokens/{tokenId}', ApiTokenController::class, 'revoke');
$router->put('api/tokens/{tokenId}', ApiTokenController::class, 'update');
$router->get('api/tokens/{tokenId}/stats', ApiTokenController::class, 'stats');
$router->get('api/tokens/scopes', ApiTokenController::class, 'scopes');

// EAN
$router->get('api/ean/packages', EanController::class, 'packages');
$router->get('api/ean/validate/{ean}', EanController::class, 'validate');
$router->get('api/ean/balance', EanController::class, 'balance');
$router->get('api/ean/my-eans', EanController::class, 'myEans');
$router->get('api/ean/purchases', EanController::class, 'purchases');
$router->get('api/ean/transactions', EanController::class, 'transactions');
$router->post('api/ean/purchase', EanController::class, 'purchase');
$router->post('api/ean/use', EanController::class, 'useEan');
$router->get('api/ean/suggest', EanController::class, 'suggest');
$router->post('api/ean/use-for-item', EanController::class, 'useForItem');
$router->get('api/ean/by-item/{mlItemId}', EanController::class, 'getByItem');
$router->get('api/ean/export', EanController::class, 'exportEans');
$router->get('api/ean/stats', EanController::class, 'sellerStats');
$router->post('api/ean/unlink', EanController::class, 'unlinkEan');
$router->get('api/ean/low-stock', EanController::class, 'checkLowStock');
$router->get('api/ean/widget', EanController::class, 'widget');
$router->get('api/ean/preview', EanController::class, 'preview');
$router->post('api/ean/auto-assign', EanController::class, 'autoAssign');
$router->post('api/ean/webhook/mercadopago', EanController::class, 'webhookMercadoPago');
$router->get('api/ean/admin/webhook-status', EanController::class, 'adminWebhookStatus');
$router->get('api/ean/admin/webhook-sla', EanController::class, 'adminWebhookSla');
$router->get('api/ean/admin/alerts/operational', EanController::class, 'adminOperationalAlerts');
$router->get('api/ean/admin/runbook/status', EanController::class, 'adminRunbookStatus');
$router->get('api/ean/admin/runbook/escalation', EanController::class, 'adminRunbookEscalation');
$router->get('api/ean/admin/operational/trend', EanController::class, 'adminOperationalTrend');
$router->get('api/ean/admin/operational/predictive', EanController::class, 'adminOperationalPredictive');
$router->get('api/ean/admin/operational/circuit-breaker', EanController::class, 'adminOperationalCircuitBreaker');
$router->post('api/ean/admin/operational/circuit-breaker/reset', EanController::class, 'adminOperationalCircuitBreakerReset');
$router->post('api/ean/admin/runbook/execute', EanController::class, 'adminRunbookExecute');
$router->get('api/ean/admin/dashboard', EanController::class, 'adminDashboard');
$router->get('api/ean/admin/purchases', EanController::class, 'adminPurchases');
$router->get('api/ean/admin/inventory', EanController::class, 'adminInventory');
$router->post('api/ean/admin/inventory/add', EanController::class, 'adminAddInventory');
$router->post('api/ean/admin/inventory/import', EanController::class, 'adminImportInventory');
$router->post('api/ean/admin/confirm-payment', EanController::class, 'confirmPayment');
$router->post('api/ean/admin/config/mercadopago', EanController::class, 'adminConfigMercadoPago');
$router->get('api/ean/admin/config/mercadopago/test', EanController::class, 'adminTestMercadoPago');
$router->post('api/ean/admin/reconcile-payments', EanController::class, 'adminReconcilePayments');
$router->get('api/ean/admin/reconcile-status', EanController::class, 'adminReconcileStatus');
$router->get('api/ean/admin/reconciliation/preview', EanController::class, 'adminReconciliationPreview');
$router->get('api/ean/admin/reconciliation/preview-history', EanController::class, 'adminReconciliationPreviewHistory');
$router->get('api/ean/admin/reconciliation/drift', EanController::class, 'adminReconciliationDrift');
$router->get('api/ean/admin/reconciliation/divergences', EanController::class, 'adminReconciliationDivergences');
$router->post('api/ean/admin/reconciliation/auto-heal-safe', EanController::class, 'adminAutoHealReconciliationSafe');
$router->post('api/ean/admin/reconciliation/remediate-low-risk', EanController::class, 'adminRemediateLowRiskReconciliation');
$router->get('api/ean/admin/reports/sales', EanController::class, 'adminSalesReport');
$router->get('api/ean/admin/reports/usage', EanController::class, 'adminUsageReport');
$router->get('api/ean/admin/reports/inventory', EanController::class, 'adminInventoryReport');
$router->get('api/ean/admin/reports/sales/export', EanController::class, 'adminExportSales');
$router->post('api/ean/admin/reports/send-daily', EanController::class, 'adminSendDailyReport');

// ========================================
// 🏪 FASE 3 - Brand Central, Trends, Inventory Advanced, Messaging
// ========================================

// Brand Central - Lojas Oficiais
$router->get('api/brand/{accountId}/store', BrandCentralController::class, 'getStore');
$router->put('api/brand/{accountId}/store', BrandCentralController::class, 'updateStore');
$router->get('api/brand/{accountId}/products', BrandCentralController::class, 'getProducts');
$router->post('api/brand/{accountId}/showcase', BrandCentralController::class, 'addToShowcase');
$router->delete('api/brand/{accountId}/showcase/{itemId}', BrandCentralController::class, 'removeFromShowcase');
$router->get('api/brand/{accountId}/performance', BrandCentralController::class, 'getPerformance');
$router->put('api/brand/{accountId}/sections', BrandCentralController::class, 'manageSections');

// Trends - Análise de Tendências
$router->get('api/trends/{accountId}/category/{categoryId}', TrendsController::class, 'getCategoryTrends');
$router->get('api/trends/{accountId}/hot-products', TrendsController::class, 'getHotProducts');
$router->get('api/trends/{accountId}/seasonality/{keyword}', TrendsController::class, 'analyzeSeasonality');
$router->get('api/trends/{accountId}/opportunities', TrendsController::class, 'findOpportunities');
$router->get('api/trends/{accountId}/forecast/{keyword}', TrendsController::class, 'forecastDemand');

// Inventory Advanced - Estoque Multi-Origem
$router->get('api/inventory/{accountId}/multi-origin/{sku}', InventoryAdvancedController::class, 'getMultiOrigin');
$router->put('api/inventory/{accountId}/origin', InventoryAdvancedController::class, 'updateOrigin');
$router->post('api/inventory/{accountId}/reservation', InventoryAdvancedController::class, 'createReservation');
$router->delete('api/inventory/{accountId}/reservation/{reservationId}', InventoryAdvancedController::class, 'releaseReservation');
$router->post('api/inventory/{accountId}/cleanup-reservations', InventoryAdvancedController::class, 'cleanupReservations');
$router->post('api/inventory/{accountId}/bulk-sync', InventoryAdvancedController::class, 'bulkSync');
$router->get('api/inventory/{accountId}/movements/{sku}', InventoryAdvancedController::class, 'getMovements');

// Messaging - Sistema de Mensagens
$router->get('api/messaging/{accountId}/conversations', MessagingController::class, 'listConversations');
$router->get('api/messaging/{accountId}/messages/{threadId}', MessagingController::class, 'getMessages');
$router->post('api/messaging/{accountId}/send', MessagingController::class, 'sendMessage');
$router->post('api/messaging/{accountId}/template', MessagingController::class, 'createTemplate');
$router->get('api/messaging/{accountId}/templates', MessagingController::class, 'listTemplates');
$router->post('api/messaging/{accountId}/send-template', MessagingController::class, 'sendFromTemplate');
$router->post('api/messaging/{accountId}/auto-response', MessagingController::class, 'setAutoResponse');
$router->get('api/messaging/{accountId}/auto-responses', MessagingController::class, 'listAutoResponses');
$router->post('api/messaging/{accountId}/webhook', MessagingController::class, 'processWebhook');
$router->get('api/messaging/{accountId}/stats', MessagingController::class, 'getStats');

// ========================================
// 🤖 FASE 4 - Dynamic Pricing, AI Predictions, Chatbot AI
// ========================================

// Dynamic Pricing - Precificação Automática (Legacy)
$router->post('api/pricing/{accountId}/calculate/{itemId}', DynamicPricingController::class, 'calculateOptimalPrice');
$router->post('api/pricing/{accountId}/demand/{itemId}', DynamicPricingController::class, 'demandBasedPricing');
$router->post('api/pricing/{accountId}/liquidation/{sku}', DynamicPricingController::class, 'inventoryLiquidation');
$router->post('api/pricing/{accountId}/apply/{itemId}', DynamicPricingController::class, 'applyPriceAdjustment');
$router->post('api/pricing/{accountId}/batch', DynamicPricingController::class, 'batchAnalysis');

// ========================================
// 💰 Pricing Intelligence - Precificador Inteligente
// ========================================
$router->get('api/pricing-intelligence/{accountId}/status', \App\Controllers\PricingIntelligenceController::class, 'getStatus');
$router->post('api/pricing-intelligence/{accountId}/refresh-token', \App\Controllers\PricingIntelligenceController::class, 'refreshToken');
$router->post('api/pricing-intelligence/{accountId}/margin/calculate', \App\Controllers\PricingIntelligenceController::class, 'calculateMargin');
$router->post('api/pricing-intelligence/{accountId}/margin/minimum', \App\Controllers\PricingIntelligenceController::class, 'calculateMinimumPrice');
$router->post('api/pricing-intelligence/{accountId}/simulate-discount', \App\Controllers\PricingIntelligenceController::class, 'simulateDiscount');
$router->post('api/pricing-intelligence/{accountId}/ranking-impact', \App\Controllers\PricingIntelligenceController::class, 'analyzeRankingImpact');
$router->get('api/pricing-intelligence/{accountId}/costs/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'getCosts');
$router->post('api/pricing-intelligence/{accountId}/costs/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'saveCosts');
$router->get('api/pricing-intelligence/{accountId}/history/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'getHistory');
$router->get('api/pricing-intelligence/{accountId}/competitors/{categoryId}', \App\Controllers\PricingIntelligenceController::class, 'analyzeCompetitors');
$router->post('api/pricing-intelligence/{accountId}/suggest-price', \App\Controllers\PricingIntelligenceController::class, 'suggestPrice');
$router->get('api/pricing-intelligence/{accountId}/dashboard', \App\Controllers\PricingIntelligenceController::class, 'getDashboard');
$router->get('api/pricing-intelligence/{accountId}/alerts', \App\Controllers\PricingIntelligenceController::class, 'getAlerts');
$router->post('api/pricing-intelligence/{accountId}/alerts/{alertId}/read', \App\Controllers\PricingIntelligenceController::class, 'markAlertRead');
$router->post('api/pricing-intelligence/{accountId}/apply/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'applyPrice');
$router->get('api/pricing-intelligence/{accountId}/items', \App\Controllers\PricingIntelligenceController::class, 'listItems');
$router->post('api/pricing-intelligence/{accountId}/bulk-costs', \App\Controllers\PricingIntelligenceController::class, 'bulkSaveCosts');

// Ranking Alert Service - Análise e monitoramento de posição
$router->get('api/pricing-intelligence/{accountId}/alerts/analyze/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'analyzeItemRanking');
$router->post('api/pricing-intelligence/{accountId}/alerts/analyze-batch', \App\Controllers\PricingIntelligenceController::class, 'analyzeItemsBatch');
$router->get('api/pricing-intelligence/{accountId}/alerts/unresolved', \App\Controllers\PricingIntelligenceController::class, 'getUnresolvedAlerts');
$router->post('api/pricing-intelligence/{accountId}/alerts/mark-read', \App\Controllers\PricingIntelligenceController::class, 'markAlertsRead');
$router->post('api/pricing-intelligence/{accountId}/alerts/{alertId}/resolve', \App\Controllers\PricingIntelligenceController::class, 'resolveAlert');
$router->get('api/pricing-intelligence/{accountId}/alerts/item/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'getItemAlertHistory');
$router->get('api/pricing-intelligence/{accountId}/alerts/stats', \App\Controllers\PricingIntelligenceController::class, 'getAlertStats');

// Promotion Simulator - Simulador de Promoções
$router->post('api/pricing-intelligence/{accountId}/promotion/simulate/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'simulatePromotion');
$router->post('api/pricing-intelligence/{accountId}/promotion/scenarios/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'getPromotionScenarios');
$router->post('api/pricing-intelligence/{accountId}/promotion/apply/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'applyPromotion');
$router->post('api/pricing-intelligence/{accountId}/promotion/central-ofertas/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'simulateCentralOfertas');
$router->get('api/pricing-intelligence/{accountId}/promotion/history', \App\Controllers\PricingIntelligenceController::class, 'getPromotionHistory');
$router->get('api/pricing-intelligence/{accountId}/promotion/history/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'getPromotionHistory');

// Pricing Scenarios - Cenários e Estratégias
$router->get('api/pricing-intelligence/{accountId}/scenarios/strategies/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'compareStrategies');
$router->post('api/pricing-intelligence/{accountId}/scenarios/what-if/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'createWhatIfScenario');
$router->get('api/pricing-intelligence/{accountId}/scenarios/strategies', \App\Controllers\PricingIntelligenceController::class, 'listStrategies');

// Pricing Rules - Regras de Precificação Automática
$router->get('api/pricing-intelligence/{accountId}/rules', \App\Controllers\PricingIntelligenceController::class, 'listPricingRules');
$router->post('api/pricing-intelligence/{accountId}/rules', \App\Controllers\PricingIntelligenceController::class, 'createPricingRule');
$router->post('api/pricing-intelligence/{accountId}/rules/{ruleId}/execute', \App\Controllers\PricingIntelligenceController::class, 'executePricingRule');
$router->post('api/pricing-intelligence/{accountId}/rules/{ruleId}/toggle', \App\Controllers\PricingIntelligenceController::class, 'togglePricingRule');
$router->delete('api/pricing-intelligence/{accountId}/rules/{ruleId}', \App\Controllers\PricingIntelligenceController::class, 'deletePricingRule');

// Exportação e Relatórios
$router->get('api/pricing-intelligence/{accountId}/export/csv', \App\Controllers\PricingIntelligenceController::class, 'exportCsv');
$router->get('api/pricing-intelligence/{accountId}/export/history', \App\Controllers\PricingIntelligenceController::class, 'exportHistory');

// Análise de Tendências e Métricas
$router->get('api/pricing-intelligence/{accountId}/trends/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'getItemTrends');
$router->get('api/pricing-intelligence/{accountId}/metrics', \App\Controllers\PricingIntelligenceController::class, 'getAdvancedMetrics');

// Relatórios de Performance
$router->get('api/pricing-intelligence/{accountId}/performance', \App\Controllers\PricingIntelligenceController::class, 'getPerformanceReport');

// Sugestão Automática de Preço
$router->post('api/pricing-intelligence/{accountId}/auto-suggest/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'autoSuggestPrice');

// Monitoramento de Concorrentes
$router->get('api/pricing-intelligence/{accountId}/monitor/competitors/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'monitorCompetitors');

// Previsão de Margem
$router->post('api/pricing-intelligence/{accountId}/forecast', \App\Controllers\PricingIntelligenceController::class, 'forecastMargin');

// Alertas de Preço
$router->get('api/pricing-intelligence/{accountId}/price-alerts', \App\Controllers\PricingIntelligenceController::class, 'listPriceAlerts');
$router->post('api/pricing-intelligence/{accountId}/price-alerts', \App\Controllers\PricingIntelligenceController::class, 'createPriceAlert');
$router->delete('api/pricing-intelligence/{accountId}/price-alerts/{alertId}', \App\Controllers\PricingIntelligenceController::class, 'deletePriceAlert');

// Importação de Custos
$router->post('api/pricing-intelligence/{accountId}/import/costs', \App\Controllers\PricingIntelligenceController::class, 'importCosts');

// Preço Ideal e Rentabilidade
$router->post('api/pricing-intelligence/{accountId}/calculate-ideal-price', \App\Controllers\PricingIntelligenceController::class, 'calculateIdealPrice');
$router->get('api/pricing-intelligence/{accountId}/profitability', \App\Controllers\PricingIntelligenceController::class, 'analyzeProfitability');

// Auto-Otimizador de Preços
$router->get('api/pricing-intelligence/{accountId}/auto-optimizer/config', \App\Controllers\PricingIntelligenceController::class, 'getAutoOptimizerConfig');
$router->post('api/pricing-intelligence/{accountId}/auto-optimizer/config', \App\Controllers\PricingIntelligenceController::class, 'saveAutoOptimizerConfig');
$router->post('api/pricing-intelligence/{accountId}/auto-optimizer/run', \App\Controllers\PricingIntelligenceController::class, 'runAutoOptimizer');
$router->get('api/pricing-intelligence/{accountId}/auto-optimizer/analyze/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'analyzeItemForOptimization');
$router->get('api/pricing-intelligence/{accountId}/auto-optimizer/stats', \App\Controllers\PricingIntelligenceController::class, 'getAutoOptimizerStats');
$router->get('api/pricing-intelligence/{accountId}/auto-optimizer/history', \App\Controllers\PricingIntelligenceController::class, 'getAutoOptimizerHistory');
$router->post('api/pricing-intelligence/{accountId}/auto-optimizer/apply/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'applyOptimizerSuggestion');

// Testes A/B de Preços
$router->get('api/pricing-intelligence/{accountId}/ab-tests', \App\Controllers\PricingIntelligenceController::class, 'listAbTests');
$router->post('api/pricing-intelligence/{accountId}/ab-tests', \App\Controllers\PricingIntelligenceController::class, 'createAbTest');
$router->get('api/pricing-intelligence/{accountId}/ab-tests/stats', \App\Controllers\PricingIntelligenceController::class, 'getAbTestStats');
$router->get('api/pricing-intelligence/{accountId}/ab-tests/{testId}', \App\Controllers\PricingIntelligenceController::class, 'getAbTest');
$router->post('api/pricing-intelligence/{accountId}/ab-tests/{testId}/start', \App\Controllers\PricingIntelligenceController::class, 'startAbTest');
$router->post('api/pricing-intelligence/{accountId}/ab-tests/{testId}/pause', \App\Controllers\PricingIntelligenceController::class, 'pauseAbTest');
$router->post('api/pricing-intelligence/{accountId}/ab-tests/{testId}/complete', \App\Controllers\PricingIntelligenceController::class, 'completeAbTest');
$router->post('api/pricing-intelligence/{accountId}/ab-tests/{testId}/cancel', \App\Controllers\PricingIntelligenceController::class, 'cancelAbTest');
$router->get('api/pricing-intelligence/{accountId}/ab-tests/{testId}/analyze', \App\Controllers\PricingIntelligenceController::class, 'analyzeAbTest');
$router->get('api/pricing-intelligence/{accountId}/ab-tests/{testId}/results', \App\Controllers\PricingIntelligenceController::class, 'getAbTestResults');
$router->post('api/pricing-intelligence/{accountId}/ab-tests/{testId}/results', \App\Controllers\PricingIntelligenceController::class, 'recordAbTestResults');
$router->get('api/pricing-intelligence/{accountId}/ab-tests/{testId}/log', \App\Controllers\PricingIntelligenceController::class, 'getAbTestLog');

// Monitoramento de Concorrentes
$router->get('api/pricing-intelligence/{accountId}/competitors/watchlist', \App\Controllers\PricingIntelligenceController::class, 'getWatchlist');
$router->post('api/pricing-intelligence/{accountId}/competitors/watchlist', \App\Controllers\PricingIntelligenceController::class, 'addToWatchlist');
$router->delete('api/pricing-intelligence/{accountId}/competitors/watchlist/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'removeFromWatchlist');
$router->get('api/pricing-intelligence/{accountId}/competitors/scan/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'scanCompetitors');
$router->get('api/pricing-intelligence/{accountId}/competitors/analysis/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'getMarketAnalysis');
$router->get('api/pricing-intelligence/{accountId}/competitors/alerts', \App\Controllers\PricingIntelligenceController::class, 'getMarketAlerts');
$router->post('api/pricing-intelligence/{accountId}/competitors/alerts/read', \App\Controllers\PricingIntelligenceController::class, 'markMarketAlertsAsRead');
$router->get('api/pricing-intelligence/{accountId}/competitors/stats', \App\Controllers\PricingIntelligenceController::class, 'getMonitoringStats');

// Ações em Lote
$router->post('api/pricing-intelligence/{accountId}/bulk/apply-rules', \App\Controllers\PricingIntelligenceController::class, 'bulkApplyRule');
$router->post('api/pricing-intelligence/{accountId}/bulk/update-costs', \App\Controllers\PricingIntelligenceController::class, 'bulkUpdateCosts');

// ========================================
// 🚀 PHASE 3: Advanced Pricing Features
// ========================================

// Rules Engine (Motor de Regras Avançado)
$router->post('api/pricing-intelligence/{accountId}/rules-engine', \App\Controllers\PricingIntelligenceController::class, 'createEngineRule');
$router->get('api/pricing-intelligence/{accountId}/rules-engine', \App\Controllers\PricingIntelligenceController::class, 'listEngineRules');
$router->get('api/pricing-intelligence/{accountId}/rules-engine/templates', \App\Controllers\PricingIntelligenceController::class, 'getEngineRuleTemplates');
$router->get('api/pricing-intelligence/{accountId}/rules-engine/{ruleId}', \App\Controllers\PricingIntelligenceController::class, 'getEngineRule');
$router->put('api/pricing-intelligence/{accountId}/rules-engine/{ruleId}', \App\Controllers\PricingIntelligenceController::class, 'updateEngineRule');
$router->delete('api/pricing-intelligence/{accountId}/rules-engine/{ruleId}', \App\Controllers\PricingIntelligenceController::class, 'deleteEngineRule');
$router->post('api/pricing-intelligence/{accountId}/rules-engine/{ruleId}/toggle', \App\Controllers\PricingIntelligenceController::class, 'toggleEngineRule');
$router->post('api/pricing-intelligence/{accountId}/rules-engine/execute/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'executeEngineRulesForItem');
$router->post('api/pricing-intelligence/{accountId}/rules-engine/execute-all', \App\Controllers\PricingIntelligenceController::class, 'executeAllEngineRules');
$router->post('api/pricing-intelligence/{accountId}/rules-engine/simulate', \App\Controllers\PricingIntelligenceController::class, 'simulateEngineRules');

// Scheduled Prices (Agendamento de Preços)
$router->post('api/pricing-intelligence/{accountId}/schedules', \App\Controllers\PricingIntelligenceController::class, 'createSchedule');
$router->get('api/pricing-intelligence/{accountId}/schedules', \App\Controllers\PricingIntelligenceController::class, 'listSchedules');
$router->get('api/pricing-intelligence/{accountId}/schedules/calendar', \App\Controllers\PricingIntelligenceController::class, 'getScheduleCalendar');
$router->get('api/pricing-intelligence/{accountId}/schedules/summary', \App\Controllers\PricingIntelligenceController::class, 'getScheduleSummary');
$router->post('api/pricing-intelligence/{accountId}/schedules/{scheduleId}/cancel', \App\Controllers\PricingIntelligenceController::class, 'cancelSchedule');
$router->post('api/pricing-intelligence/{accountId}/schedules/campaign', \App\Controllers\PricingIntelligenceController::class, 'createCampaign');
$router->get('api/pricing-intelligence/{accountId}/schedules/campaigns', \App\Controllers\PricingIntelligenceController::class, 'listCampaigns');
$router->post('api/pricing-intelligence/{accountId}/schedules/campaign/{campaignId}/cancel', \App\Controllers\PricingIntelligenceController::class, 'cancelCampaign');

// Price Analytics (Analytics Avançados)
$router->get('api/pricing-intelligence/{accountId}/analytics/dashboard', \App\Controllers\PricingIntelligenceController::class, 'getAnalyticsDashboard');
$router->get('api/pricing-intelligence/{accountId}/analytics/trend/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'getPriceTrend');
$router->get('api/pricing-intelligence/{accountId}/analytics/elasticity/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'analyzeElasticity');
$router->get('api/pricing-intelligence/{accountId}/analytics/competitive/{categoryId}', \App\Controllers\PricingIntelligenceController::class, 'getCompetitiveAnalysis');
$router->post('api/pricing-intelligence/{accountId}/analytics/roi', \App\Controllers\PricingIntelligenceController::class, 'calculatePriceChangeROI');
$router->get('api/pricing-intelligence/{accountId}/analytics/forecast/{itemId}', \App\Controllers\PricingIntelligenceController::class, 'forecastPrice');
$router->get('api/pricing-intelligence/{accountId}/analytics/report', \App\Controllers\PricingIntelligenceController::class, 'generateAnalyticsReport');

// Bulk Price Editor (Editor em Massa)
$router->post('api/pricing-intelligence/{accountId}/bulk-editor/preview', \App\Controllers\PricingIntelligenceController::class, 'previewBulkEdit');
$router->post('api/pricing-intelligence/{accountId}/bulk-editor/apply', \App\Controllers\PricingIntelligenceController::class, 'applyBulkEdit');
$router->get('api/pricing-intelligence/{accountId}/bulk-editor/batches', \App\Controllers\PricingIntelligenceController::class, 'listBulkBatches');
$router->get('api/pricing-intelligence/{accountId}/bulk-editor/templates', \App\Controllers\PricingIntelligenceController::class, 'getBulkOperationTemplates');
$router->get('api/pricing-intelligence/{accountId}/bulk-editor/{batchId}', \App\Controllers\PricingIntelligenceController::class, 'getBulkBatch');
$router->post('api/pricing-intelligence/{accountId}/bulk-editor/{batchId}/rollback', \App\Controllers\PricingIntelligenceController::class, 'rollbackBulkEdit');

// Notifications (Alertas e Notificações)
$router->post('api/pricing-intelligence/{accountId}/notifications/send', \App\Controllers\PricingIntelligenceController::class, 'sendNotification');
$router->post('api/pricing-intelligence/{accountId}/notifications/channels', \App\Controllers\PricingIntelligenceController::class, 'createNotificationChannel');
$router->get('api/pricing-intelligence/{accountId}/notifications/channels', \App\Controllers\PricingIntelligenceController::class, 'listNotificationChannels');
$router->put('api/pricing-intelligence/{accountId}/notifications/channels/{channelId}', \App\Controllers\PricingIntelligenceController::class, 'updateNotificationChannel');
$router->delete('api/pricing-intelligence/{accountId}/notifications/channels/{channelId}', \App\Controllers\PricingIntelligenceController::class, 'deleteNotificationChannel');
$router->post('api/pricing-intelligence/{accountId}/notifications/channels/{channelId}/test', \App\Controllers\PricingIntelligenceController::class, 'testNotificationChannel');
$router->post('api/pricing-intelligence/{accountId}/notifications/channels/{channelId}/subscribe', \App\Controllers\PricingIntelligenceController::class, 'subscribeToEvent');
$router->delete('api/pricing-intelligence/{accountId}/notifications/channels/{channelId}/subscribe/{event}', \App\Controllers\PricingIntelligenceController::class, 'unsubscribeFromEvent');
$router->get('api/pricing-intelligence/{accountId}/notifications/channels/{channelId}/subscriptions', \App\Controllers\PricingIntelligenceController::class, 'getChannelSubscriptions');
$router->get('api/pricing-intelligence/{accountId}/notifications/history', \App\Controllers\PricingIntelligenceController::class, 'getNotificationHistory');
$router->get('api/pricing-intelligence/{accountId}/notifications/events', \App\Controllers\PricingIntelligenceController::class, 'getNotificationEvents');

// AI Predictions - Previsões com Machine Learning
$router->get('api/ai/{accountId}/predict-sales/{itemId}', AIPredictionsController::class, 'predictSales');
$router->get('api/ai/{accountId}/rising-stars', AIPredictionsController::class, 'identifyRisingStars');
$router->get('api/ai/{accountId}/best-promo-time/{itemId}', AIPredictionsController::class, 'predictBestPromotionTime');
$router->get('api/ai/{accountId}/category-demand/{categoryId}', AIPredictionsController::class, 'predictCategoryDemand');

// Chatbot AI - Atendimento Inteligente
$router->post('api/chatbot/{accountId}/process', ChatbotAIController::class, 'processMessage');
$router->get('api/chatbot/{accountId}/stats', ChatbotAIController::class, 'getStats');

// ========================================
// 🔥 AI Optimization Engine
// ========================================
$router->get('api/ai/items-by-score', AIOptimizationController::class, 'fetchItemsByScore');
$router->post('api/ai/optimize/title', AIOptimizationController::class, 'optimizeTitle');
$router->post('api/ai/optimize/description', AIOptimizationController::class, 'optimizeDescription');
$router->post('api/ai/optimize/tech-sheet', AIOptimizationController::class, 'optimizeTechSheet');
$router->post('api/ai/optimize/complete', AIOptimizationController::class, 'optimizeComplete');
$router->get('api/ai/suggestions/{itemId}', AIOptimizationController::class, 'suggestions');

// Batch Operations
$router->post('api/ai/batch/start', AIOptimizationController::class, 'startBatchOptimization');
$router->get('api/ai/batch/{batchId}/status', AIOptimizationController::class, 'getBatchStatus');
$router->get('api/ai/batch/{batchId}/results', AIOptimizationController::class, 'getBatchResults');
$router->get('api/ai/queue/stats', AIOptimizationController::class, 'getQueueStats');

// Preview & Apply
$router->post('api/ai/preview/generate', AIOptimizationController::class, 'generatePreview');
$router->post('api/ai/preview/{previewId}/apply', AIOptimizationController::class, 'applyPreview');

// Audit & History
$router->get('api/ai/audit/{itemId}/history', AIOptimizationController::class, 'getAuditHistory');
$router->post('api/ai/audit/{logId}/rollback', AIOptimizationController::class, 'rollbackOptimization');

// Analytics
$router->get('api/ai/analytics/dashboard', AIOptimizationController::class, 'getDashboardAnalytics');
$router->get('api/ai/analytics/summary', AIOptimizationController::class, 'getExecutiveSummary');
$router->get('api/ai/analytics/costs', AIOptimizationController::class, 'getCostAnalytics');

// ========================================
// 📊 Error Monitoring & Observability
// ========================================
$router->get('api/monitoring/errors/recent', ErrorMonitoringController::class, 'recent');
$router->get('api/monitoring/errors/stats', ErrorMonitoringController::class, 'stats');
$router->get('api/monitoring/errors/analyze-log', ErrorMonitoringController::class, 'analyzeLog');
$router->post('api/monitoring/errors/clean', ErrorMonitoringController::class, 'clean');
$router->post('api/monitoring/errors/log', ErrorMonitoringController::class, 'log');

// ========================================
// 🤖 AI-Powered Insights (v2.0.0)
// Powered by GPT-4 Turbo & Machine Learning
// ========================================

// === Strategic Insights ===
$router->post('api/ai/insights/strategic', AIController::class, 'generateStrategicInsights');
$router->post('api/ai/insights/ab-tests', AIController::class, 'suggestABTests');
$router->get('api/ai/insights/trends', AIController::class, 'analyzeTrends');
$router->post('api/ai/insights/explain-metric', AIController::class, 'explainMetric');
$router->get('api/ai/insights/recommendations', AIController::class, 'getPrioritizedRecommendations');
$router->get('api/ai/insights/sentiment', AIController::class, 'analyzeMarketSentiment');

// === Image Analysis (Computer Vision) ===
// Compat endpoint (body: { item_id })
$router->post('api/ai/images/analyze', AIController::class, 'analyzeProductImages');
// Novos endpoints usados pelo painel SEO Killer de Imagens
$router->get('api/ai/images/analyze/{itemId}', AIController::class, 'analyzeProductImagesById');
$router->post('api/ai/images/reorder/{itemId}', AIController::class, 'reorderImages');
$router->delete('api/ai/images/remove', AIController::class, 'removeImage');
$router->post('api/ai/images/upload', AIController::class, 'uploadImage');

$router->post('api/ai/images/compare-practices', AIController::class, 'compareWithBestPractices');
$router->post('api/ai/images/suggest-order', AIController::class, 'suggestOptimalOrder');
$router->post('api/ai/images/detect-similar', AIController::class, 'detectSimilarImages');

// === Dynamic Pricing (ML Optimization) ===
$router->post('api/ai/pricing/suggest', AIController::class, 'suggestOptimalPrice');
$router->post('api/ai/pricing/elasticity', AIController::class, 'analyzePriceElasticity');
$router->post('api/ai/pricing/optimize-margin', AIController::class, 'optimizeMargin');
$router->post('api/ai/pricing/dynamic-rules', AIController::class, 'createDynamicPricingRules');
$router->get('api/ai/pricing/competitive/{itemId}', AIController::class, 'analyzeCompetitivePricing');
$router->post('api/ai/pricing/forecast', AIController::class, 'forecastRevenue');

// === Conversational AI Assistant ===
$router->post('api/ai/chat', AIController::class, 'chat');
$router->post('api/ai/chat/explain-metric', AIController::class, 'explainMetricQuick');
$router->post('api/ai/chat/help-feature', AIController::class, 'helpWithFeature');
$router->get('api/ai/chat/suggest-actions', AIController::class, 'suggestNextActions');
$router->delete('api/ai/chat/history', AIController::class, 'clearHistory');

// ========================================
//  Competitor Monitoring Automation
// ========================================
$router->get('api/competitor/tracked', CompetitorMonitorController::class, 'getTracked');
$router->get('api/competitor/alerts', CompetitorMonitorController::class, 'getAlerts');
$router->get('api/competitor/stats', CompetitorMonitorController::class, 'getStats');
$router->post('api/competitor/track', CompetitorMonitorController::class, 'track');
$router->post('api/competitor/monitoring/start', CompetitorMonitorController::class, 'startMonitoring');
$router->post('api/competitor/monitoring/pause', CompetitorMonitorController::class, 'pauseMonitoring');
$router->post('api/competitor/toggle/{id}', CompetitorMonitorController::class, 'toggleMonitoring');
$router->delete('api/competitor/{id}', CompetitorMonitorController::class, 'remove');
$router->post('api/competitor/alert/{id}/read', CompetitorMonitorController::class, 'markAlertRead');
$router->post('api/competitor/settings', CompetitorMonitorController::class, 'saveSettings');

// ========================================
// 🔍 SEO API - Otimização com IA Real
// ========================================
use App\Controllers\SEOApiController;

// Status do serviço
$router->get('api/seo/status', SEOApiController::class, 'status');

// SEO Optimizer
$router->post('api/seo/analyze', SEOApiController::class, 'analyze');
$router->post('api/seo/optimize-title', SEOApiController::class, 'optimizeTitle');
$router->post('api/seo/generate-description', SEOApiController::class, 'generateDescription');
$router->post('api/seo/keywords', SEOApiController::class, 'researchKeywords');
$router->post('api/seo/competitors', SEOApiController::class, 'analyzeCompetitors');
$router->post('api/seo/optimize', SEOApiController::class, 'optimizeProduct');

// SEO Dashboard (análise avançada)
$router->post('api/seo/analyze-product', SEOApiController::class, 'analyzeProduct');
$router->post('api/seo/analyze-keyword-gaps', SEOApiController::class, 'analyzeKeywordGaps');
$router->post('api/seo/analyze-semantic', SEOApiController::class, 'analyzeSemantic');
$router->post('api/seo/optimize-model-attribute', SEOApiController::class, 'optimizeModelAttribute');
$router->post('api/seo/monitor-optimization', SEOApiController::class, 'monitorOptimization');

// Tech Sheet (Ficha Técnica)
$router->post('api/seo/tech-sheet/generate', SEOApiController::class, 'generateTechSheet');
$router->post('api/seo/tech-sheet/extract', SEOApiController::class, 'extractFromTitle');
$router->post('api/seo/tech-sheet/complete', SEOApiController::class, 'completeTechSheet');
$router->post('api/seo/tech-sheet/validate', SEOApiController::class, 'validateTechSheet');
$router->post('api/seo/tech-sheet/suggest', SEOApiController::class, 'suggestAttributes');

// ========================================
// 🔑 TOKEN MANAGEMENT - Gerenciamento de Tokens ML
// ========================================

// Dashboard de Tokens
$router->get('api/tokens/dashboard', TokenDashboardController::class, 'getMetrics');
$router->get('api/tokens/accounts', TokenDashboardController::class, 'listAccounts');
$router->get('api/tokens/stats', TokenDashboardController::class, 'getStats');

// Renovação de Tokens
$router->post('api/tokens/refresh/{accountId}', TokenDashboardController::class, 'refreshAccount');
$router->post('api/tokens/refresh-all', TokenDashboardController::class, 'refreshAll');

// Auditoria e Histórico
$router->get('api/tokens/audit/{accountId}', TokenDashboardController::class, 'getAuditHistory');

// ========================================
// 🤖 AUTOMATION - Workflows e Automações
// ========================================

use App\Controllers\AutomationController;

$router->post('api/automation/workflow/create', AutomationController::class, 'createWorkflow');
$router->post('api/automation/workflow/{workflowId}/execute', AutomationController::class, 'executeWorkflow');
$router->post('api/automation/queue/process', AutomationController::class, 'processQueue');
$router->post('api/automation/smart-automation/create', AutomationController::class, 'createSmartAutomation');
$router->post('api/automation/optimize', AutomationController::class, 'optimizeAutomations');
$router->get('api/automation/dashboard', AutomationController::class, 'getDashboard');
$router->get('api/automation/workflow/templates', AutomationController::class, 'createWorkflowTemplates');
$router->post('api/automation/workflow/template/{templateId}/instantiate', AutomationController::class, 'instantiateFromTemplate');
$router->get('api/automation/workflow/{workflowId}/status', AutomationController::class, 'getWorkflowStatus');

// ========================================
// 📊 MONITORING - Monitoramento do Sistema
// ========================================

use App\Controllers\MonitoringController;

$router->get('api/monitoring/realtime-metrics', MonitoringController::class, 'realTimeMetrics');
$router->get('api/monitoring/alerts', MonitoringController::class, 'checkAlerts');
$router->get('api/monitoring/performance-report', MonitoringController::class, 'performanceReport');
$router->get('api/monitoring/feature-flags', MonitoringController::class, 'featureFlags');
$router->post('api/monitoring/feature-flags', MonitoringController::class, 'featureFlags');
$router->get('api/monitoring/logs', MonitoringController::class, 'systemLogs');
$router->get('api/monitoring/health', MonitoringController::class, 'healthCheck');
$router->get('api/monitoring/system-metrics', MonitoringController::class, 'systemMetrics');
$router->get('api/monitoring/health-advanced', MonitoringController::class, 'healthAdvanced');
$router->get('api/monitoring/system-alerts', MonitoringController::class, 'systemAlerts');
$router->get('api/monitoring/performance-advanced', MonitoringController::class, 'performanceAdvanced');
$router->get('api/monitoring/metrics', MonitoringController::class, 'metrics');
$router->post('api/monitoring/clean', MonitoringController::class, 'clean');

// ========================================
// 🔧 OPTIMIZATION - Otimização de Banco de Dados
// ========================================

use App\Controllers\OptimizationController;

$router->get('api/optimization/analyze', OptimizationController::class, 'analyze');
$router->post('api/optimization/analyze-tables', OptimizationController::class, 'analyzeTables');
$router->post('api/optimization/optimize-table', OptimizationController::class, 'optimizeTable');
$router->get('api/optimization/index-stats', OptimizationController::class, 'indexStats');

// ========================================
// 📦 STOCK SYNC - Sincronização de Estoque entre Contas
// ========================================

use App\Controllers\StockSyncController;

// Regras CRUD
$router->get('api/stock-sync/rules', StockSyncController::class, 'listRules');
$router->post('api/stock-sync/rules', StockSyncController::class, 'createRule');
$router->put('api/stock-sync/rules/{id}', StockSyncController::class, 'updateRule');
$router->delete('api/stock-sync/rules/{id}', StockSyncController::class, 'deleteRule');

// Sincronização
$router->post('api/stock-sync/full', StockSyncController::class, 'fullSync');
$router->post('api/stock-sync/process', StockSyncController::class, 'processQueue');
$router->post('api/stock-sync/manual', StockSyncController::class, 'manualSync');

// Webhook ML
$router->post('api/stock-sync/webhook', StockSyncController::class, 'webhook');

// Histórico e Stats
$router->get('api/stock-sync/history', StockSyncController::class, 'history');
$router->get('api/stock-sync/stats', StockSyncController::class, 'stats');

// Configurações
$router->get('api/stock-sync/settings', StockSyncController::class, 'getSettings');
$router->post('api/stock-sync/settings', StockSyncController::class, 'updateSettings');
