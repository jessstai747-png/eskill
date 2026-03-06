<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\MercadoLivreClient;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \App\Services\AccountHealthService
 */
class AccountHealthServiceTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(\App\Services\AccountHealthService::class);
    }

    private function createInstance(): object
    {
        $instance = $this->reflection->newInstanceWithoutConstructor();
        // Initialize typed properties that methods may access
        $prop = $this->reflection->getProperty('accountId');
        $prop->setAccessible(true);
        $prop->setValue($instance, null);
        return $instance;
    }

    private function invokePrivateMethod(string $methodName, array $args = []): mixed
    {
        $method = $this->reflection->getMethod($methodName);
        $method->setAccessible(true);
        $instance = $this->createInstance();
        return $method->invokeArgs($instance, $args);
    }

    private function getPrivateConstant(string $name): mixed
    {
        return $this->reflection->getConstant($name);
    }

    private function createInstanceWithClient(MercadoLivreClient $client): object
    {
        $instance = $this->createInstance();

        $clientProperty = $this->reflection->getProperty('client');
        $clientProperty->setAccessible(true);
        $clientProperty->setValue($instance, $client);

        return $instance;
    }

    // =========================================================================
    // Structural
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(\App\Services\AccountHealthService::class));
    }

    public function testSourceFileExists(): void
    {
        $file = $this->reflection->getFileName();
        $this->assertNotFalse($file);
        $this->assertFileExists($file);
    }

    public function testPillarWeightsConstantExists(): void
    {
        $weights = $this->getPrivateConstant('PILLAR_WEIGHTS');
        $this->assertIsArray($weights);
        $this->assertArrayHasKey('reputation', $weights);
        $this->assertArrayHasKey('seo_quality', $weights);
        $this->assertArrayHasKey('competitiveness', $weights);
        $this->assertArrayHasKey('operation', $weights);
        $this->assertArrayHasKey('sales', $weights);
    }

    public function testPillarWeightsSumTo100(): void
    {
        $weights = $this->getPrivateConstant('PILLAR_WEIGHTS');
        $this->assertSame(100, array_sum($weights));
    }

    public function testScoreThresholdsConstantExists(): void
    {
        $thresholds = $this->getPrivateConstant('SCORE_THRESHOLDS');
        $this->assertIsArray($thresholds);
        $this->assertArrayHasKey('critical', $thresholds);
        $this->assertArrayHasKey('warning', $thresholds);
        $this->assertArrayHasKey('good', $thresholds);
        $this->assertArrayHasKey('great', $thresholds);
    }

    public function testScoreThresholdsAreInAscendingOrder(): void
    {
        $thresholds = $this->getPrivateConstant('SCORE_THRESHOLDS');
        $this->assertLessThan($thresholds['warning'], $thresholds['critical']);
        $this->assertLessThan($thresholds['good'], $thresholds['warning']);
        $this->assertLessThan($thresholds['great'], $thresholds['good']);
    }

    // =========================================================================
    // calculateOverallScore
    // =========================================================================

    public function testCalculateOverallScoreAllPillarsEqual(): void
    {
        $pillars = [
            'reputation'      => ['score' => 80],
            'seo_quality'     => ['score' => 80],
            'competitiveness' => ['score' => 80],
            'operation'       => ['score' => 80],
            'sales'           => ['score' => 80],
        ];
        $result = $this->invokePrivateMethod('calculateOverallScore', [$pillars]);
        $this->assertSame(80, $result);
    }

    public function testCalculateOverallScoreEmptyPillars(): void
    {
        $result = $this->invokePrivateMethod('calculateOverallScore', [[]]);
        $this->assertSame(0, $result);
    }

    public function testCalculateOverallScorePartialPillars(): void
    {
        $pillars = [
            'reputation'  => ['score' => 100],
            'seo_quality' => ['score' => 50],
        ];
        $result = $this->invokePrivateMethod('calculateOverallScore', [$pillars]);
        $this->assertSame(75, $result);
    }

    public function testCalculateOverallScoreWeightedCorrectly(): void
    {
        $pillars = [
            'reputation'      => ['score' => 100],
            'seo_quality'     => ['score' => 0],
            'competitiveness' => ['score' => 50],
            'operation'       => ['score' => 50],
            'sales'           => ['score' => 50],
        ];
        $result = $this->invokePrivateMethod('calculateOverallScore', [$pillars]);
        $this->assertSame(50, $result);
    }

    public function testCalculateOverallScoreAllZero(): void
    {
        $pillars = [
            'reputation'      => ['score' => 0],
            'seo_quality'     => ['score' => 0],
            'competitiveness' => ['score' => 0],
            'operation'       => ['score' => 0],
            'sales'           => ['score' => 0],
        ];
        $result = $this->invokePrivateMethod('calculateOverallScore', [$pillars]);
        $this->assertSame(0, $result);
    }

    public function testCalculateOverallScoreAllPerfect(): void
    {
        $pillars = [
            'reputation'      => ['score' => 100],
            'seo_quality'     => ['score' => 100],
            'competitiveness' => ['score' => 100],
            'operation'       => ['score' => 100],
            'sales'           => ['score' => 100],
        ];
        $result = $this->invokePrivateMethod('calculateOverallScore', [$pillars]);
        $this->assertSame(100, $result);
    }

    public function testCalculateOverallScoreMissingScoreKey(): void
    {
        $pillars = [
            'reputation'      => ['score' => 80],
            'seo_quality'     => [],
            'competitiveness' => ['score' => 80],
            'operation'       => ['score' => 80],
            'sales'           => ['score' => 80],
        ];
        $result = $this->invokePrivateMethod('calculateOverallScore', [$pillars]);
        $this->assertSame(60, $result);
    }

    // =========================================================================
    // getScoreLevel
    // =========================================================================

    /**
     * @dataProvider scoreLevelProvider
     */
    public function testGetScoreLevel(int $score, string $expectedLevel): void
    {
        $result = $this->invokePrivateMethod('getScoreLevel', [$score]);
        $this->assertSame($expectedLevel, $result);
    }

    public static function scoreLevelProvider(): array
    {
        return [
            'score 0 is critical'  => [0, 'critical'],
            'score 29 is critical' => [29, 'critical'],
            'score 30 is critical' => [30, 'critical'],
            'score 49 is critical' => [49, 'critical'],
            'score 50 is warning'  => [50, 'warning'],
            'score 69 is warning'  => [69, 'warning'],
            'score 70 is good'     => [70, 'good'],
            'score 84 is good'     => [84, 'good'],
            'score 85 is great'    => [85, 'great'],
            'score 100 is great'   => [100, 'great'],
        ];
    }

    // =========================================================================
    // getScoreLabel
    // =========================================================================

    /**
     * @dataProvider scoreLabelProvider
     */
    public function testGetScoreLabel(int $score, string $expectedLabel): void
    {
        $result = $this->invokePrivateMethod('getScoreLabel', [$score]);
        $this->assertSame($expectedLabel, $result);
    }

    public static function scoreLabelProvider(): array
    {
        return [
            'score 0'   => [0, "Cr\u{00ED}tica"],
            'score 30'  => [30, "Cr\u{00ED}tica"],
            'score 49'  => [49, "Cr\u{00ED}tica"],
            'score 50'  => [50, "Aten\u{00E7}\u{00E3}o"],
            'score 69'  => [69, "Aten\u{00E7}\u{00E3}o"],
            'score 70'  => [70, 'Boa'],
            'score 84'  => [84, 'Boa'],
            'score 85'  => [85, 'Excelente'],
            'score 100' => [100, 'Excelente'],
        ];
    }

    // =========================================================================
    // getActionPriority
    // =========================================================================

    /**
     * @dataProvider actionPriorityProvider
     */
    public function testGetActionPriority(string $severity, int $expected): void
    {
        $result = $this->invokePrivateMethod('getActionPriority', [$severity]);
        $this->assertSame($expected, $result);
    }

    public static function actionPriorityProvider(): array
    {
        return [
            'critical' => ['critical', 1],
            'warning'  => ['warning', 2],
            'info'     => ['info', 3],
            'unknown'  => ['unknown', 4],
            'empty'    => ['', 4],
        ];
    }

    public function testGetAccountStatusDiagnosticIgnoresErrorPayloadsFromOptionalEndpoints(): void
    {
        $client = $this->createMock(MercadoLivreClient::class);
        $client->method('getSellerId')->willReturn('123');
        $client->method('get')->willReturnCallback(function (string $endpoint) {
            return match ($endpoint) {
                '/users/123' => [
                    'status' => [
                        'site_status' => 'active',
                        'confirmed_email' => true,
                        'mercadopago_account_type' => 'personal',
                    ],
                    'seller_reputation' => [
                        'metrics' => [
                            'cancellations' => ['rate' => 0.01],
                        ],
                        'power_seller_status' => null,
                    ],
                    'tags' => [],
                    'site_id' => 'MLB',
                ],
                '/users/123/shipping_preferences' => [
                    'error' => 'feature_unavailable',
                    'message' => 'Envios não configurado',
                ],
                '/users/123/brands_official_store' => [
                    'error' => 'brand_central_unavailable',
                    'message' => 'Conta sem loja oficial',
                ],
                default => [],
            };
        });

        $instance = $this->createInstanceWithClient($client);
        $result = $instance->getAccountStatusDiagnostic();

        $this->assertFalse($result['official_store']);
        $this->assertFalse($result['features']['shipping_configured']);
    }

    // =========================================================================
    // getStaleRecommendation
    // =========================================================================

    public function testStaleRecommendationZeroItems(): void
    {
        $result = $this->invokePrivateMethod('getStaleRecommendation', [0.0, 0]);
        $this->assertStringContainsString('Nenhum', $result);
    }

    public function testStaleRecommendationUrgent(): void
    {
        $result = $this->invokePrivateMethod('getStaleRecommendation', [55.0, 20]);
        $this->assertStringContainsString('URGENTE', $result);
    }

    public function testStaleRecommendationAttention(): void
    {
        $result = $this->invokePrivateMethod('getStaleRecommendation', [35.0, 10]);
        $this->assertStringContainsString('ATEN', $result);
    }

    public function testStaleRecommendationRecommended(): void
    {
        $result = $this->invokePrivateMethod('getStaleRecommendation', [20.0, 5]);
        $this->assertStringContainsString('Recomendado', $result);
    }

    public function testStaleRecommendationMonitor(): void
    {
        $result = $this->invokePrivateMethod('getStaleRecommendation', [10.0, 3]);
        $this->assertStringContainsString('3', $result);
    }

    // =========================================================================
    // avgSubScore
    // =========================================================================

    public function testAvgSubScoreEmpty(): void
    {
        $result = $this->invokePrivateMethod('avgSubScore', [[], 'score']);
        $this->assertSame(0.0, $result);
    }

    public function testAvgSubScoreSingleItem(): void
    {
        $items = [['score' => 80]];
        $result = $this->invokePrivateMethod('avgSubScore', [$items, 'score']);
        $this->assertSame(80.0, $result);
    }

    public function testAvgSubScoreMultiple(): void
    {
        $items = [['score' => 80], ['score' => 60], ['score' => 100]];
        $result = $this->invokePrivateMethod('avgSubScore', [$items, 'score']);
        $this->assertSame(80.0, $result);
    }

    public function testAvgSubScoreDecimals(): void
    {
        $items = [['v' => 33], ['v' => 33], ['v' => 34]];
        $result = $this->invokePrivateMethod('avgSubScore', [$items, 'v']);
        $this->assertSame(33.3, $result);
    }

    // =========================================================================
    // calculateAccountStatusScore
    // =========================================================================

    public function testAccountStatusScoreFull(): void
    {
        $v = ['is_verified' => true, 'confirmed_email' => true];
        $f = ['mercado_envios' => true, 'mercado_pago' => true, 'catalog_enabled' => true, 'full_eligible' => true];
        $this->assertSame(100, $this->invokePrivateMethod('calculateAccountStatusScore', [$v, $f, true]));
    }

    public function testAccountStatusScoreNone(): void
    {
        $v = ['is_verified' => false, 'confirmed_email' => false];
        $f = ['mercado_envios' => false, 'mercado_pago' => false, 'catalog_enabled' => false, 'full_eligible' => false];
        $this->assertSame(0, $this->invokePrivateMethod('calculateAccountStatusScore', [$v, $f, false]));
    }

    public function testAccountStatusScoreOnlyVerified(): void
    {
        $v = ['is_verified' => true, 'confirmed_email' => true];
        $f = ['mercado_envios' => false, 'mercado_pago' => false, 'catalog_enabled' => false, 'full_eligible' => false];
        $this->assertSame(40, $this->invokePrivateMethod('calculateAccountStatusScore', [$v, $f, false]));
    }

    public function testAccountStatusScoreOfficialStoreBonus(): void
    {
        $v = ['is_verified' => false, 'confirmed_email' => false];
        $f = ['mercado_envios' => false, 'mercado_pago' => false, 'catalog_enabled' => false, 'full_eligible' => false];
        $this->assertSame(20, $this->invokePrivateMethod('calculateAccountStatusScore', [$v, $f, true]));
    }

    public function testAccountStatusScoreCapped(): void
    {
        $v = ['is_verified' => true, 'confirmed_email' => true];
        $f = ['mercado_envios' => true, 'mercado_pago' => true, 'catalog_enabled' => true, 'full_eligible' => true];
        $this->assertSame(100, $this->invokePrivateMethod('calculateAccountStatusScore', [$v, $f, true]));
    }

    // =========================================================================
    // calculateCustomerServiceScore
    // =========================================================================

    public function testCustomerServiceScorePerfect(): void
    {
        $m = ['avg_response_hours' => 2.0, 'response_rate_24h' => 95.0];
        $this->assertSame(100, $this->invokePrivateMethod('calculateCustomerServiceScore', [$m, 0]));
    }

    public function testCustomerServiceScoreManyUnanswered(): void
    {
        $m = ['avg_response_hours' => 2.0, 'response_rate_24h' => 95.0];
        $this->assertSame(80, $this->invokePrivateMethod('calculateCustomerServiceScore', [$m, 15]));
    }

    public function testCustomerServiceScoreSlow(): void
    {
        $m = ['avg_response_hours' => 50.0, 'response_rate_24h' => 40.0];
        $this->assertSame(80, $this->invokePrivateMethod('calculateCustomerServiceScore', [$m, 0]));
    }

    public function testCustomerServiceScoreModerateUnanswered(): void
    {
        $m = ['avg_response_hours' => 2.0, 'response_rate_24h' => 60.0];
        $this->assertSame(80, $this->invokePrivateMethod('calculateCustomerServiceScore', [$m, 7]));
    }

    public function testCustomerServiceScoreFewUnanswered(): void
    {
        $m = ['avg_response_hours' => 5.0, 'response_rate_24h' => 80.0];
        $this->assertSame(95, $this->invokePrivateMethod('calculateCustomerServiceScore', [$m, 3]));
    }

    public function testCustomerServiceScoreFloor(): void
    {
        $m = ['avg_response_hours' => 100.0, 'response_rate_24h' => 0.0];
        $this->assertSame(50, $this->invokePrivateMethod('calculateCustomerServiceScore', [$m, 50]));
    }

    // =========================================================================
    // assessQuestionImpact
    // =========================================================================

    public function testQuestionImpactCritical(): void
    {
        $r = $this->invokePrivateMethod('assessQuestionImpact', [['avg_response_hours' => 5.0], 15]);
        $this->assertStringContainsString('CR', $r);
    }

    public function testQuestionImpactHigh(): void
    {
        $r = $this->invokePrivateMethod('assessQuestionImpact', [['avg_response_hours' => 5.0], 7]);
        $this->assertStringContainsString('ALTO', $r);
    }

    public function testQuestionImpactMedium(): void
    {
        $r = $this->invokePrivateMethod('assessQuestionImpact', [['avg_response_hours' => 30.0], 2]);
        $this->assertStringContainsString('DIO', $r);
    }

    public function testQuestionImpactLow(): void
    {
        $r = $this->invokePrivateMethod('assessQuestionImpact', [['avg_response_hours' => 5.0], 0]);
        $this->assertStringContainsString('BAIXO', $r);
    }

    // =========================================================================
    // assessVerificationStatus
    // =========================================================================

    public function testVerificationStatusFullyVerified(): void
    {
        $user = [
            'status' => ['site_status' => 'active', 'confirmed_email' => true, 'mercadopago_account_type' => 'personal', 'required_action' => null],
            'user_type' => 'normal',
            'seller_reputation' => ['power_seller_status' => 'gold'],
        ];
        $result = $this->invokePrivateMethod('assessVerificationStatus', [$user]);
        $this->assertTrue($result['is_verified']);
        $this->assertTrue($result['confirmed_email']);
        $this->assertSame('active', $result['site_status']);
        $this->assertSame('gold', $result['power_seller_status']);
    }

    public function testVerificationStatusNotVerified(): void
    {
        $user = ['status' => ['site_status' => 'pending', 'confirmed_email' => false], 'user_type' => 'normal'];
        $result = $this->invokePrivateMethod('assessVerificationStatus', [$user]);
        $this->assertFalse($result['is_verified']);
        $this->assertFalse($result['confirmed_email']);
    }

    public function testVerificationStatusEmptyUser(): void
    {
        $result = $this->invokePrivateMethod('assessVerificationStatus', [[]]);
        $this->assertFalse($result['is_verified']);
        $this->assertFalse($result['confirmed_email']);
        $this->assertSame('unknown', $result['site_status']);
        $this->assertSame('unknown', $result['user_type']);
    }

    // =========================================================================
    // assessFeatureAvailability
    // =========================================================================

    public function testFeatureAvailabilityAllEnabled(): void
    {
        $user = [
            'tags' => ['normal', 'mshops', 'mercadopago_account', 'catalog_product', 'fulfillment', 'credits_priority'],
            'seller_reputation' => ['metrics' => ['cancellations' => ['rate' => 0.02]]],
        ];
        $result = $this->invokePrivateMethod('assessFeatureAvailability', [$user, ['mode' => 'me2']]);
        $this->assertTrue($result['mercado_envios']);
        $this->assertTrue($result['mercado_pago']);
        $this->assertTrue($result['catalog_enabled']);
        $this->assertTrue($result['full_eligible']);
        $this->assertTrue($result['credits_enabled']);
        $this->assertTrue($result['can_list']);
        $this->assertTrue($result['shipping_configured']);
    }

    public function testFeatureAvailabilityNoneEnabled(): void
    {
        $user = [
            'tags' => [],
            'seller_reputation' => ['metrics' => ['cancellations' => ['rate' => 0.15]]],
        ];
        $result = $this->invokePrivateMethod('assessFeatureAvailability', [$user, null]);
        $this->assertFalse($result['mercado_envios']);
        $this->assertFalse($result['mercado_pago']);
        $this->assertFalse($result['catalog_enabled']);
        $this->assertFalse($result['full_eligible']);
        $this->assertFalse($result['credits_enabled']);
        $this->assertFalse($result['can_list']);
        $this->assertFalse($result['shipping_configured']);
    }

    // =========================================================================
    // calculateCatalogHealthScore
    // =========================================================================

    /**
     * @dataProvider catalogHealthScoreProvider
     */
    public function testCalculateCatalogHealthScore(float $catalogRatio, int $dupes, int $total, int $expected): void
    {
        $result = $this->invokePrivateMethod('calculateCatalogHealthScore', [$catalogRatio, $dupes, $total]);
        $this->assertSame($expected, $result);
    }

    public static function catalogHealthScoreProvider(): array
    {
        return [
            'perfect'      => [85.0, 0, 100, 100],
            'good small'   => [80.0, 0, 10, 90],
            'medium'       => [60.0, 3, 30, 60],
            'low'          => [20.0, 6, 5, 15],
            'zero'         => [0.0, 10, 0, 0],
            'catalog only' => [40.0, 0, 25, 65],
            'high w dupes' => [90.0, 8, 50, 70],
        ];
    }

    // =========================================================================
    // detectDuplicateListings
    // =========================================================================

    public function testDetectDuplicatesNone(): void
    {
        $items = [
            ['id' => 'MLB1', 'title' => 'Bagageiro CG 160 Titan'],
            ['id' => 'MLB2', 'title' => 'Retrovisor CB 300'],
            ['id' => 'MLB3', 'title' => 'Bau Moto 45L'],
        ];
        $this->assertEmpty($this->invokePrivateMethod('detectDuplicateListings', [$items]));
    }

    public function testDetectDuplicatesFound(): void
    {
        $items = [
            ['id' => 'MLB1', 'title' => 'Bagageiro CG 160 Titan 2023'],
            ['id' => 'MLB2', 'title' => 'Bagageiro CG Titan'],
            ['id' => 'MLB3', 'title' => 'Retrovisor CB 300'],
        ];
        $result = $this->invokePrivateMethod('detectDuplicateListings', [$items]);
        $this->assertNotEmpty($result);
        $this->assertSame('MLB2', $result[0]['item2']);
    }

    public function testDetectDuplicatesEmpty(): void
    {
        $this->assertEmpty($this->invokePrivateMethod('detectDuplicateListings', [[]]));
    }

    public function testDetectDuplicatesSkipsEmptyTitles(): void
    {
        $items = [['id' => 'MLB1', 'title' => ''], ['id' => 'MLB2', 'title' => '']];
        $this->assertEmpty($this->invokePrivateMethod('detectDuplicateListings', [$items]));
    }

    // =========================================================================
    // getCatalogBenefits
    // =========================================================================

    public function testGetCatalogBenefitsNonEmpty(): void
    {
        $result = $this->invokePrivateMethod('getCatalogBenefits', []);
        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
    }

    // =========================================================================
    // generateCatalogRecommendations
    // =========================================================================

    public function testCatalogRecommendationsLowRatio(): void
    {
        $result = $this->invokePrivateMethod('generateCatalogRecommendations', [30.0, 0]);
        $this->assertNotEmpty($result);
        $this->assertSame('high', $result[0]['priority']);
    }

    public function testCatalogRecommendationsWithDuplicates(): void
    {
        $result = $this->invokePrivateMethod('generateCatalogRecommendations', [80.0, 5]);
        $this->assertCount(1, $result);
        $this->assertSame('medium', $result[0]['priority']);
    }

    public function testCatalogRecommendationsNoIssues(): void
    {
        $this->assertEmpty($this->invokePrivateMethod('generateCatalogRecommendations', [80.0, 0]));
    }

    public function testCatalogRecommendationsBothIssues(): void
    {
        $this->assertCount(2, $this->invokePrivateMethod('generateCatalogRecommendations', [30.0, 3]));
    }

    // =========================================================================
    // generateAccountStatusRecommendations
    // =========================================================================

    public function testAccountRecsAllGood(): void
    {
        $v = ['confirmed_email' => true];
        $f = ['mercado_envios' => true, 'mercado_pago' => true, 'full_eligible' => true, 'available_tags' => ['logistics']];
        $this->assertEmpty($this->invokePrivateMethod('generateAccountStatusRecommendations', [$v, $f]));
    }

    public function testAccountRecsEmailNotConfirmed(): void
    {
        $v = ['confirmed_email' => false];
        $f = ['mercado_envios' => true, 'mercado_pago' => true, 'full_eligible' => false];
        $result = $this->invokePrivateMethod('generateAccountStatusRecommendations', [$v, $f]);
        $this->assertNotEmpty($result);
        $this->assertSame('critical', $result[0]['priority']);
    }

    public function testAccountRecsMissingMP(): void
    {
        $v = ['confirmed_email' => true];
        $f = ['mercado_envios' => true, 'mercado_pago' => false, 'full_eligible' => false];
        $result = $this->invokePrivateMethod('generateAccountStatusRecommendations', [$v, $f]);
        $found = false;
        foreach ($result as $rec) {
            if (str_contains($rec['action'], 'Mercado Pago')) {
                $found = true;
            }
        }
        $this->assertTrue($found);
    }

    // =========================================================================
    // generateCustomerServiceRecommendations
    // =========================================================================

    public function testCSRecsNoIssues(): void
    {
        $m = ['avg_response_hours' => 2.0, 'response_rate_24h' => 90.0];
        $this->assertEmpty($this->invokePrivateMethod('generateCustomerServiceRecommendations', [$m, 0]));
    }

    public function testCSRecsWithUnanswered(): void
    {
        $m = ['avg_response_hours' => 2.0, 'response_rate_24h' => 90.0];
        $result = $this->invokePrivateMethod('generateCustomerServiceRecommendations', [$m, 8]);
        $this->assertNotEmpty($result);
        $this->assertSame('critical', $result[0]['priority']);
    }

    public function testCSRecsFewUnanswered(): void
    {
        $m = ['avg_response_hours' => 2.0, 'response_rate_24h' => 90.0];
        $result = $this->invokePrivateMethod('generateCustomerServiceRecommendations', [$m, 3]);
        $this->assertNotEmpty($result);
        $this->assertSame('high', $result[0]['priority']);
    }

    public function testCSRecsSlowResponse(): void
    {
        $m = ['avg_response_hours' => 15.0, 'response_rate_24h' => 90.0];
        $this->assertNotEmpty($this->invokePrivateMethod('generateCustomerServiceRecommendations', [$m, 0]));
    }

    public function testCSRecsLowResponseRate(): void
    {
        $m = ['avg_response_hours' => 2.0, 'response_rate_24h' => 50.0];
        $this->assertNotEmpty($this->invokePrivateMethod('generateCustomerServiceRecommendations', [$m, 0]));
    }

    // =========================================================================
    // calculateQuestionMetrics
    // =========================================================================

    public function testQuestionMetricsEmpty(): void
    {
        $result = $this->invokePrivateMethod('calculateQuestionMetrics', [[]]);
        $this->assertSame(0, $result['total']);
        $this->assertSame(0, $result['answered']);
        $this->assertSame(0.0, $result['avg_response_hours']);
        $this->assertSame(0.0, $result['response_rate_24h']);
    }

    public function testQuestionMetricsWithAnswered(): void
    {
        $now = date('Y-m-d\TH:i:s.000-0300');
        $twoHoursAgo = date('Y-m-d\TH:i:s.000-0300', strtotime('-2 hours'));
        $questions = [
            ['status' => 'ANSWERED', 'date_created' => $twoHoursAgo, 'answer' => ['date_created' => $now]],
        ];
        $result = $this->invokePrivateMethod('calculateQuestionMetrics', [$questions]);
        $this->assertSame(1, $result['answered']);
        $this->assertGreaterThan(0, $result['avg_response_hours']);
    }

    public function testQuestionMetricsCountsByStatus(): void
    {
        $d = date('Y-m-d\TH:i:s');
        $questions = [
            ['status' => 'ANSWERED', 'date_created' => $d],
            ['status' => 'ANSWERED', 'date_created' => $d],
            ['status' => 'UNANSWERED', 'date_created' => $d],
            ['status' => 'CLOSED', 'date_created' => $d],
        ];
        $result = $this->invokePrivateMethod('calculateQuestionMetrics', [$questions]);
        $this->assertSame(2, $result['by_status']['ANSWERED']);
        $this->assertSame(1, $result['by_status']['UNANSWERED']);
        $this->assertSame(1, $result['by_status']['CLOSED']);
    }

    // =========================================================================
    // getUrgentQuestions
    // =========================================================================

    public function testUrgentQuestionsEmpty(): void
    {
        $this->assertEmpty($this->invokePrivateMethod('getUrgentQuestions', [[]]));
    }

    public function testUrgentQuestionsFiltersOld(): void
    {
        $tenHoursAgo = gmdate('Y-m-d\TH:i:s', strtotime('-10 hours')) . '.000-0000';
        $twoHoursAgo = gmdate('Y-m-d\TH:i:s', strtotime('-2 hours')) . '.000-0000';
        $unanswered = [
            ['id' => 1, 'text' => 'Old', 'date_created' => $tenHoursAgo, 'item_id' => 'MLB1'],
            ['id' => 2, 'text' => 'New', 'date_created' => $twoHoursAgo, 'item_id' => 'MLB2'],
        ];
        $result = $this->invokePrivateMethod('getUrgentQuestions', [$unanswered]);
        $this->assertCount(1, $result);
        $this->assertSame(1, $result[0]['id']);
        $this->assertGreaterThan(6, $result[0]['age_hours']);
    }

    public function testUrgentQuestionsSkipsMissingDate(): void
    {
        $this->assertEmpty($this->invokePrivateMethod('getUrgentQuestions', [[['id' => 1, 'text' => 'No date']]]));
    }

    // =========================================================================
    // assessDataQuality
    // =========================================================================

    public function testDataQualityAllReal(): void
    {
        $pillars = [
            'reputation'      => ['score' => 80, 'details' => ['some' => 'data']],
            'seo_quality'     => ['score' => 70, 'details' => ['seo' => 'info']],
            'competitiveness' => ['score' => 60, 'details' => ['comp' => 'data']],
        ];
        $result = $this->invokePrivateMethod('assessDataQuality', [$pillars]);
        $this->assertSame('real', $result['quality']);
        $this->assertTrue($result['is_fully_real']);
        $this->assertSame(3, $result['real_data']);
        $this->assertSame(0, $result['mock_data']);
    }

    public function testDataQualityAllMock(): void
    {
        $pillars = [
            'reputation'      => ['score' => 0, 'details' => ['error' => 'X', 'is_mock' => true]],
            'seo_quality'     => ['score' => 0, 'details' => ['error' => 'Y', 'is_mock' => true]],
            'competitiveness' => ['score' => 0, 'details' => ['error' => 'Z', 'is_mock' => true]],
        ];
        $result = $this->invokePrivateMethod('assessDataQuality', [$pillars]);
        $this->assertSame('mostly_mock', $result['quality']);
        $this->assertFalse($result['is_fully_real']);
    }

    public function testDataQualityPartial(): void
    {
        $pillars = [
            'reputation'      => ['score' => 80, 'details' => ['real' => true]],
            'seo_quality'     => ['score' => 70, 'details' => ['real' => true]],
            'competitiveness' => ['score' => 0, 'details' => ['error' => 'Fails', 'is_mock' => true]],
        ];
        $result = $this->invokePrivateMethod('assessDataQuality', [$pillars]);
        $this->assertSame('partial', $result['quality']);
        $this->assertSame(2, $result['real_data']);
        $this->assertSame(1, $result['mock_data']);
    }

    public function testDataQualityEmptyPillars(): void
    {
        $result = $this->invokePrivateMethod('assessDataQuality', [[]]);
        $this->assertSame(0, $result['total_pillars']);
    }

    // =========================================================================
    // generateSummary
    // =========================================================================

    public function testSummaryWorstAndBest(): void
    {
        $pillars = [
            'reputation'      => ['score' => 90, 'name' => 'Reputa'],
            'seo_quality'     => ['score' => 30, 'name' => 'SEO'],
            'competitiveness' => ['score' => 60, 'name' => 'Comp'],
        ];
        $result = $this->invokePrivateMethod('generateSummary', [60, $pillars, []]);
        $this->assertSame('SEO', $result['worst_pillar']);
        $this->assertSame(30, $result['worst_pillar_score']);
        $this->assertSame('Reputa', $result['best_pillar']);
        $this->assertSame(90, $result['best_pillar_score']);
    }

    public function testSummaryPotentialGain(): void
    {
        $pillars = ['seo_quality' => ['score' => 20, 'name' => 'SEO']];
        $result = $this->invokePrivateMethod('generateSummary', [20, $pillars, []]);
        $this->assertGreaterThan(0, $result['potential_gain']);
    }

    public function testSummaryCriticalCount(): void
    {
        $actions = [
            ['severity' => 'critical'],
            ['severity' => 'critical'],
            ['severity' => 'warning'],
            ['severity' => 'info'],
        ];
        $result = $this->invokePrivateMethod('generateSummary', [50, ['reputation' => ['score' => 50, 'name' => 'Rep']], $actions]);
        $this->assertSame(2, $result['critical_count']);
        $this->assertSame(1, $result['warning_count']);
        $this->assertSame(4, $result['total_actions']);
    }

    public function testSummaryUrgentRecommendation(): void
    {
        $actions = array_fill(0, 5, ['severity' => 'critical']);
        $pillars = ['reputation' => ['score' => 20, 'name' => 'Rep']];
        $result = $this->invokePrivateMethod('generateSummary', [20, $pillars, $actions]);
        $this->assertStringContainsString('urgente', strtolower($result['recommendation']));
    }

    public function testSummaryExcellentRecommendation(): void
    {
        $pillars = ['reputation' => ['score' => 95, 'name' => 'Rep']];
        $result = $this->invokePrivateMethod('generateSummary', [95, $pillars, []]);
        $this->assertStringContainsString('Parab', $result['recommendation']);
    }

    // =========================================================================
    // generateActionItems
    // =========================================================================

    public function testActionItemsEmpty(): void
    {
        $this->assertEmpty($this->invokePrivateMethod('generateActionItems', [[]]));
    }

    public function testActionItemsSortedByImpact(): void
    {
        $pillars = [
            'reputation' => [
                'name' => 'Rep', 'score' => 30,
                'issues' => [['type' => 'rep_low', 'severity' => 'critical', 'message' => 'Low', 'impact' => 'High', 'action' => 'Fix']],
            ],
            'seo_quality' => [
                'name' => 'SEO', 'score' => 90,
                'issues' => [['type' => 'seo_minor', 'severity' => 'info', 'message' => 'Minor', 'impact' => 'Low', 'action' => 'Opt']],
            ],
        ];
        $result = $this->invokePrivateMethod('generateActionItems', [$pillars]);
        $this->assertCount(2, $result);
        $this->assertGreaterThanOrEqual($result[1]['impact_score'], $result[0]['impact_score']);
    }

    public function testActionItemsDeduplicates(): void
    {
        $pillars = [
            'reputation' => [
                'name' => 'Rep', 'score' => 50,
                'issues' => [['type' => 'dup', 'severity' => 'warning', 'message' => 'M', 'impact' => 'I', 'action' => 'A']],
            ],
            'seo_quality' => [
                'name' => 'SEO', 'score' => 50,
                'issues' => [['type' => 'dup', 'severity' => 'warning', 'message' => 'M2', 'impact' => 'I2', 'action' => 'A2']],
            ],
        ];
        $this->assertCount(1, $this->invokePrivateMethod('generateActionItems', [$pillars]));
    }

    // =========================================================================
    // generateCrossPillarInsights
    // =========================================================================

    public function testCrossPillarInsightsEmpty(): void
    {
        $pillars = [
            'seo_quality'     => ['score' => 80, 'details' => []],
            'competitiveness' => ['score' => 80, 'details' => []],
            'sales'           => ['score' => 80, 'details' => []],
            'operation'       => ['score' => 80, 'details' => []],
            'reputation'      => ['score' => 80, 'details' => []],
        ];
        $this->assertEmpty($this->invokePrivateMethod('generateCrossPillarInsights', [$pillars]));
    }

    public function testCrossPillarInsightSeoConversion(): void
    {
        $pillars = [
            'seo_quality'     => ['score' => 40, 'details' => []],
            'competitiveness' => ['score' => 80, 'details' => []],
            'sales'           => ['score' => 50, 'details' => ['visits_30d' => 500]],
            'operation'       => ['score' => 80, 'details' => []],
            'reputation'      => ['score' => 80, 'details' => []],
        ];
        $result = $this->invokePrivateMethod('generateCrossPillarInsights', [$pillars]);
        $types = array_column($result, 'type');
        $this->assertContains('seo_conversion_opportunity', $types);
    }

    public function testCrossPillarInsightSalesOps(): void
    {
        $pillars = [
            'seo_quality'     => ['score' => 80, 'details' => []],
            'competitiveness' => ['score' => 80, 'details' => []],
            'sales'           => ['score' => 30, 'details' => ['sales_growth' => -20]],
            'operation'       => ['score' => 50, 'details' => []],
            'reputation'      => ['score' => 80, 'details' => []],
        ];
        $result = $this->invokePrivateMethod('generateCrossPillarInsights', [$pillars]);
        $types = array_column($result, 'type');
        $this->assertContains('sales_ops_correlation', $types);
    }

    // =========================================================================
    // emptyPillar
    // =========================================================================

    public function testEmptyPillarStructure(): void
    {
        $result = $this->invokePrivateMethod('emptyPillar', ['reputation', 'Test error']);
        $this->assertSame(0, $result['score']);
        $this->assertSame('critical', $result['level']);
        $this->assertSame('Test error', $result['details']['error']);
        $this->assertTrue($result['details']['is_mock']);
        $this->assertCount(1, $result['issues']);
        $this->assertSame('no_data', $result['issues'][0]['type']);
    }

    public function testEmptyPillarUnknownName(): void
    {
        $result = $this->invokePrivateMethod('emptyPillar', ['unknown_pillar', 'Error']);
        $this->assertSame('unknown_pillar', $result['name']);
        $this->assertSame('bi-question-circle', $result['icon']);
    }

    // =========================================================================
    // Empty data return methods
    // =========================================================================

    public function testEmptySalesData(): void
    {
        $result = $this->invokePrivateMethod('emptySalesData', []);
        $this->assertArrayHasKey('sales_30d', $result);
        $this->assertArrayHasKey('revenue_30d', $result);
        $this->assertSame(0, $result['sales_30d']);
    }

    public function testEmptyStaleListings(): void
    {
        $result = $this->invokePrivateMethod('emptyStaleListings', []);
        $this->assertArrayHasKey('summary', $result);
        $this->assertArrayHasKey('items', $result);
        $this->assertSame(0, $result['summary']['total_active']);
    }

    public function testEmptyAccountStatus(): void
    {
        $result = $this->invokePrivateMethod('emptyAccountStatus', []);
        $this->assertSame(0, $result['score']);
        $this->assertFalse($result['verification']['is_verified']);
    }

    public function testEmptyCustomerService(): void
    {
        $result = $this->invokePrivateMethod('emptyCustomerService', []);
        $this->assertSame(0, $result['score']);
        $this->assertSame(0, $result['unanswered_count']);
    }

    public function testEmptyCatalogHealth(): void
    {
        $result = $this->invokePrivateMethod('emptyCatalogHealth', []);
        $this->assertSame(0, $result['score']);
        $this->assertSame(0, $result['total_items']);
        $this->assertEmpty($result['recommendations']);
    }

    // =========================================================================
    // getDataSourcesInfo
    // =========================================================================

    public function testDataSourcesInfoWithMLQuality(): void
    {
        $pillars = [
            'seo_quality' => [
                'details' => ['ml_quality' => ['some' => 'data'], 'score_source' => 'ml_real'],
            ],
        ];
        $result = $this->invokePrivateMethod('getDataSourcesInfo', [$pillars]);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('ml_signals', $result);
        $this->assertNotEmpty($result['ml_signals']);
    }

    public function testDataSourcesInfoWithoutMLQuality(): void
    {
        $pillars = ['seo_quality' => ['details' => []]];
        $result = $this->invokePrivateMethod('getDataSourcesInfo', [$pillars]);
        $this->assertArrayHasKey('ml_signals', $result);
    }
}
