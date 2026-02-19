#!/usr/bin/env php
<?php
/**
 * Test each file individually to find which ones pass
 */
$baseDir = __DIR__;

// All existing test files NOT in phpunit.xml Unit suite
$candidates = [
    'tests/Unit/Services/ValidationServiceTest.php',
    'tests/Unit/Services/EncryptionServiceTest.php',
    'tests/Unit/Services/SecurityServiceTest.php',
    'tests/Unit/Services/FinancialServiceTest.php',
    'tests/Unit/Services/OrderServiceTest.php',
    'tests/Unit/Services/ListingBuilderServiceTest.php',
    'tests/Unit/Services/TechSheetServiceTest.php',
    'tests/Unit/Services/CacheServiceTest.php',
    'tests/Unit/Services/SeoAnalyzerServiceTest.php',
    'tests/Unit/Services/PricingStrategyServiceTest.php',
    'tests/Unit/Services/AuthServiceTest.php',
    'tests/Unit/Services/EmailServiceTest.php',
    'tests/Unit/Services/LoggerServiceTest.php',
    'tests/Unit/Services/JwtServiceTest.php',
    'tests/Unit/Services/UserServiceTest.php',
    'tests/Unit/Services/PasswordResetServiceTest.php',
    'tests/Unit/Services/UnifiedTokenRefreshServiceTest.php',
    'tests/Unit/Services/MercadoLivreAuthServiceTest.php',
    'tests/Unit/Services/MercadoLivreClientTest.php',
    'tests/Unit/Services/MercadoLivreClientExtendedTest.php',
    'tests/Unit/Services/MercadoLivreClientHeaderTokenTest.php',
    'tests/Unit/Services/AttributeSuggestionServiceTest.php',
    'tests/Unit/Services/CloneMetricsServiceTest.php',
    'tests/Unit/Services/CloneMonitoringServiceTest.php',
    'tests/Unit/Services/ClonePostActionsServiceTest.php',
    'tests/Unit/Services/AIPredictiveAnalyticsServiceTest.php',
    'tests/Unit/Services/MLAnalyticsIntelligenceServiceTest.php',
    'tests/Unit/Services/RealIntegrationsStructureTest.php',
    'tests/Unit/Services/VersioningServiceRollbackTest.php',
    'tests/Unit/Services/TechSheetAlertServiceTest.php',
    'tests/Unit/Services/TechSheetAnalyticsServiceTest.php',
    'tests/Unit/Services/TechSheetAutoOptimizerServiceTest.php',
    'tests/Unit/Services/TechSheetBatchOptimizerServiceTest.php',
    'tests/Unit/Services/TechSheetBenchmarkServiceTest.php',
    'tests/Unit/Services/TechSheetChartsServiceTest.php',
    'tests/Unit/Services/TechSheetExportServiceTest.php',
    'tests/Unit/Services/TechSheetNotificationServiceTest.php',
    'tests/Unit/Services/TechSheetSchedulerServiceTest.php',
    'tests/Unit/Services/TechSheetSEOIntegrationServiceApplyTest.php',
    'tests/Unit/Services/TechSheetWebhookServiceTest.php',
    'tests/Unit/Controllers/HealthControllerTest.php',
    'tests/Unit/Controllers/UserManagementControllerTest.php',
    'tests/Unit/Controllers/SeoControllerTest.php',
    'tests/Unit/Controllers/BaseControllerActiveAccountIdTest.php',
    // Subdirectory tests
    'tests/Unit/Services/AI/AIProviderManagerTest.php',
    'tests/Unit/Services/AI/DescriptionOptimizerTest.php',
    'tests/Unit/Services/AI/TechSheetOptimizerTest.php',
    'tests/Unit/Services/AI/TitleOptimizerTest.php',
    'tests/Unit/Services/MercadoLivre/AdvancedPricingEngineTest.php',
    'tests/Unit/Services/MercadoLivre/CategoriesApiServiceTest.php',
    'tests/Unit/Services/MercadoLivre/CompetitorIntelligenceServiceTest.php',
    'tests/Unit/Services/MercadoLivre/MercadoLivreAIIntegrationHealthTest.php',
    'tests/Unit/Services/MercadoLivre/MLAdsAdvancedServiceTest.php',
    'tests/Unit/Services/MercadoLivre/SmartQAServiceTest.php',
    'tests/Unit/Services/SEO/CompatibilityServiceTest.php',
    'tests/Unit/Services/SEO/CompetitorAnalysisServiceTest.php',
    'tests/Unit/Services/SEO/ContextInjectorServiceTest.php',
    'tests/Unit/Services/SEO/CoverageAndHiddenFieldsTest.php',
    'tests/Unit/Services/SEO/DescriptionBuilderServiceTest.php',
    'tests/Unit/Services/SEO/HiddenAttributesDetectorTest.php',
    'tests/Unit/Services/SEO/KeywordDistributionServiceTest.php',
    'tests/Unit/Services/SEO/KeywordSourceServiceTest.php',
    'tests/Unit/Services/SEO/LongTailGeneratorServiceTest.php',
    'tests/Unit/Services/SEO/Phase5IntegrationTest.php',
    'tests/Unit/Services/SEO/SearchCoverageServiceTest.php',
    'tests/Unit/Services/SEO/SemanticScoreServiceTest.php',
    'tests/Unit/Services/SEO/SEOAuditServiceTest.php',
    'tests/Unit/Services/SEO/SEOOptimizerServiceTest.php',
    'tests/Unit/Services/SEO/SynonymExpansionServiceTest.php',
    'tests/Unit/Services/SEO/TokenManagerTest.php',
    'tests/Unit/Services/AI/SEO',
];

$passing = [];
$failing = [];

foreach ($candidates as $file) {
    $fullPath = $baseDir . '/' . $file;
    
    // Check if it's a directory
    if (is_dir($fullPath)) {
        continue; // Skip directories for now
    }
    
    if (!file_exists($fullPath)) {
        $failing[] = ['file' => $file, 'reason' => 'FILE NOT FOUND'];
        continue;
    }
    
    // Run test with Unit testsuite bootstrap context
    $cmd = "php vendor/bin/phpunit --testsuite=Unit --no-coverage " . escapeshellarg($fullPath) . " 2>&1";
    $output = shell_exec($cmd);
    
    // Check for OK result
    if (preg_match('/OK \((\d+) test/', $output, $m)) {
        $passing[] = ['file' => $file, 'tests' => (int)$m[1]];
        echo "PASS: {$file} ({$m[1]} tests)\n";
    } else {
        // Extract error summary
        $lines = explode("\n", trim($output));
        $lastLines = array_slice($lines, -3);
        $reason = implode(' | ', $lastLines);
        $failing[] = ['file' => $file, 'reason' => substr($reason, 0, 200)];
        echo "FAIL: {$file}\n";
    }
}

echo "\n=== SUMMARY ===\n";
echo "Passing: " . count($passing) . "\n";
foreach ($passing as $p) {
    echo "  PASS: {$p['file']} ({$p['tests']} tests)\n";
}
echo "\nFailing: " . count($failing) . "\n";
foreach ($failing as $f) {
    echo "  FAIL: {$f['file']} — {$f['reason']}\n";
}
