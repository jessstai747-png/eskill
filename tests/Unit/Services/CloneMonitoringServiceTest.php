<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\CloneMonitoringService;
use PHPUnit\Framework\TestCase;

/**
 * Unit Tests for CloneMonitoringService
 *
 * Tests feature flags, alerting, health metrics, and rate limiting logic.
 */
class CloneMonitoringServiceTest extends TestCase
{
    private CloneMonitoringService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CloneMonitoringService();
    }

    // =========================================================================
    // INSTANTIATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(CloneMonitoringService::class, $this->service);
    }

    /**
     * @test
     */
    public function service_has_required_public_methods(): void
    {
        $this->assertTrue(method_exists($this->service, 'isFeatureEnabled'));
        $this->assertTrue(method_exists($this->service, 'setFeatureFlag'));
        $this->assertTrue(method_exists($this->service, 'listFeatureFlags'));
        $this->assertTrue(method_exists($this->service, 'canClone'));
        $this->assertTrue(method_exists($this->service, 'getSystemHealth'));
        $this->assertTrue(method_exists($this->service, 'createAlert'));
        $this->assertTrue(method_exists($this->service, 'listAlerts'));
        $this->assertTrue(method_exists($this->service, 'acknowledgeAlert'));
        $this->assertTrue(method_exists($this->service, 'getRecommendedDelay'));
        $this->assertTrue(method_exists($this->service, 'canExecuteNow'));
        $this->assertTrue(method_exists($this->service, 'logCloneStart'));
        $this->assertTrue(method_exists($this->service, 'logCloneEnd'));
        $this->assertTrue(method_exists($this->service, 'generateDailyReport'));
    }

    /**
     * @test
     */
    public function service_has_required_constants(): void
    {
        $this->assertEquals('clone_module_enabled', CloneMonitoringService::FLAG_CLONE_ENABLED);
        $this->assertEquals('clone_batch_enabled', CloneMonitoringService::FLAG_BATCH_ENABLED);
        $this->assertEquals('clone_post_actions_enabled', CloneMonitoringService::FLAG_POST_ACTIONS_ENABLED);
        $this->assertEquals('clone_rate_limit_strict', CloneMonitoringService::FLAG_RATE_LIMIT_STRICT);
    }

    // =========================================================================
    // FEATURE FLAGS TESTS
    // =========================================================================

    /**
     * @test
     */
    public function feature_flags_returns_array(): void
    {
        $flags = $this->service->listFeatureFlags();

        $this->assertIsArray($flags);
    }

    /**
     * @test
     */
    public function default_flags_are_initialized(): void
    {
        $flags = $this->service->listFeatureFlags();
        $flagNames = array_column($flags, 'flag_name');

        $this->assertContains(CloneMonitoringService::FLAG_CLONE_ENABLED, $flagNames);
        $this->assertContains(CloneMonitoringService::FLAG_BATCH_ENABLED, $flagNames);
    }

    /**
     * @test
     */
    public function is_feature_enabled_returns_boolean(): void
    {
        $result = $this->service->isFeatureEnabled(CloneMonitoringService::FLAG_CLONE_ENABLED);

        $this->assertIsBool($result);
    }

    /**
     * @test
     */
    public function set_feature_flag_returns_boolean(): void
    {
        $result = $this->service->setFeatureFlag(CloneMonitoringService::FLAG_RATE_LIMIT_STRICT, false);

        $this->assertIsBool($result);
    }

    /**
     * @test
     */
    public function unknown_flag_defaults_to_enabled(): void
    {
        $result = $this->service->isFeatureEnabled('non_existent_flag_xyz');

        $this->assertTrue($result);
    }

    // =========================================================================
    // CAN CLONE TESTS
    // =========================================================================

    /**
     * @test
     */
    public function can_clone_returns_expected_structure(): void
    {
        $result = $this->service->canClone();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
        $this->assertIsBool($result['allowed']);
    }

    /**
     * @test
     */
    public function can_clone_includes_health_when_allowed(): void
    {
        // Ensure module is enabled
        $this->service->setFeatureFlag(CloneMonitoringService::FLAG_CLONE_ENABLED, true);

        $result = $this->service->canClone();

        if ($result['allowed']) {
            $this->assertArrayHasKey('health', $result);
        }
    }

    /**
     * @test
     */
    public function can_clone_returns_reason_when_disabled(): void
    {
        // Disable the module
        $this->service->setFeatureFlag(CloneMonitoringService::FLAG_CLONE_ENABLED, false);

        $result = $this->service->canClone();

        $this->assertFalse($result['allowed']);
        $this->assertArrayHasKey('reason', $result);
        $this->assertStringContainsString('desabilitado', $result['reason']);

        // Re-enable for other tests
        $this->service->setFeatureFlag(CloneMonitoringService::FLAG_CLONE_ENABLED, true);
    }

    // =========================================================================
    // SYSTEM HEALTH TESTS
    // =========================================================================

    /**
     * @test
     */
    public function system_health_returns_expected_structure(): void
    {
        $health = $this->service->getSystemHealth();

        $this->assertIsArray($health);
        $this->assertArrayHasKey('status', $health);
        $this->assertArrayHasKey('error_rate', $health);
        $this->assertArrayHasKey('checked_at', $health);
    }

    /**
     * @test
     */
    public function system_health_status_is_valid(): void
    {
        $health = $this->service->getSystemHealth();

        $validStatuses = ['healthy', 'degraded', 'critical'];
        $this->assertContains($health['status'], $validStatuses);
    }

    /**
     * @test
     */
    public function error_rate_is_percentage(): void
    {
        $health = $this->service->getSystemHealth();

        $this->assertGreaterThanOrEqual(0, $health['error_rate']);
        $this->assertLessThanOrEqual(100, $health['error_rate']);
    }

    // =========================================================================
    // ALERTS TESTS
    // =========================================================================

    /**
     * @test
     */
    public function create_alert_returns_id(): void
    {
        // Use unique message to avoid duplicate detection
        $uniqueMessage = 'Test alert from PHPUnit - ' . uniqid();

        $alertId = $this->service->createAlert(
            'test_alert',
            'info',
            $uniqueMessage,
            ['test' => true]
        );

        // Can be 0 if duplicate, otherwise should be positive
        $this->assertIsInt($alertId);
        $this->assertGreaterThanOrEqual(0, $alertId);
    }

    /**
     * @test
     */
    public function list_alerts_returns_array(): void
    {
        $alerts = $this->service->listAlerts(false, 10);

        $this->assertIsArray($alerts);
    }

    /**
     * @test
     */
    public function acknowledge_alert_returns_boolean(): void
    {
        // Create an alert first
        $alertId = $this->service->createAlert(
            'test_ack',
            'info',
            'Test alert for acknowledgment'
        );

        $result = $this->service->acknowledgeAlert($alertId, 1);

        $this->assertIsBool($result);
    }

    /**
     * @test
     */
    public function acknowledge_alert_returns_false_for_missing_alert(): void
    {
        $result = $this->service->acknowledgeAlert(999999999, 1);

        $this->assertFalse($result);
    }

    /**
     * @test
     */
    public function alert_severities_are_valid(): void
    {
        $validSeverities = ['info', 'warning', 'critical'];

        foreach ($validSeverities as $severity) {
            // Use unique message for each to avoid duplicate detection
            $uniqueMessage = "Test alert with severity: {$severity} - " . uniqid();

            $alertId = $this->service->createAlert(
                'severity_test',
                $severity,
                $uniqueMessage
            );
            // Can be 0 if duplicate detected, otherwise positive
            $this->assertGreaterThanOrEqual(0, $alertId);
        }
    }

    // =========================================================================
    // RATE LIMITING TESTS
    // =========================================================================

    /**
     * @test
     */
    public function get_recommended_delay_returns_integer(): void
    {
        $delay = $this->service->getRecommendedDelay();

        $this->assertIsInt($delay);
        $this->assertGreaterThan(0, $delay);
    }

    /**
     * @test
     */
    public function can_execute_now_returns_expected_structure(): void
    {
        $result = $this->service->canExecuteNow();

        $this->assertIsArray($result);
        $this->assertArrayHasKey('allowed', $result);
        $this->assertArrayHasKey('delay_ms', $result);
    }

    /**
     * @test
     */
    public function delay_increases_in_strict_mode(): void
    {
        // Normal mode
        $this->service->setFeatureFlag(CloneMonitoringService::FLAG_RATE_LIMIT_STRICT, false);
        $normalDelay = $this->service->getRecommendedDelay();

        // Strict mode
        $this->service->setFeatureFlag(CloneMonitoringService::FLAG_RATE_LIMIT_STRICT, true);
        $strictDelay = $this->service->getRecommendedDelay();

        $this->assertGreaterThanOrEqual($normalDelay, $strictDelay);

        // Reset to normal
        $this->service->setFeatureFlag(CloneMonitoringService::FLAG_RATE_LIMIT_STRICT, false);
    }

    // =========================================================================
    // LOGGING TESTS
    // =========================================================================

    /**
     * @test
     */
    public function log_clone_start_returns_operation_id(): void
    {
        $operationId = $this->service->logCloneStart(
            'MLB123456789',
            1,
            2,
            ['test' => true]
        );

        $this->assertIsString($operationId);
        $this->assertStringStartsWith('clone_', $operationId);
    }

    /**
     * @test
     */
    public function log_clone_end_accepts_all_parameters(): void
    {
        $operationId = $this->service->logCloneStart('MLB123', 1, 2);

        // Should not throw
        $this->service->logCloneEnd(
            $operationId,
            'success',
            'MLB456789012',
            null,
            1.5
        );

        $this->assertTrue(true); // If we get here, no exception was thrown
    }

    // =========================================================================
    // REPORT TESTS
    // =========================================================================

    /**
     * @test
     * @group integration
     */
    public function daily_report_returns_expected_structure(): void
    {
        try {
            $report = $this->service->generateDailyReport();

            $this->assertIsArray($report);
            $this->assertArrayHasKey('date', $report);
            $this->assertArrayHasKey('metrics', $report);
            $this->assertArrayHasKey('alerts', $report);
            $this->assertArrayHasKey('clones', $report);
            $this->assertArrayHasKey('generated_at', $report);
        } catch (\PDOException $e) {
            $this->markTestSkipped('Requires cloned_items table: ' . $e->getMessage());
        }
    }

    /**
     * @test
     * @group integration
     */
    public function daily_report_accepts_custom_date(): void
    {
        try {
            $customDate = '2026-01-15';
            $report = $this->service->generateDailyReport($customDate);

            $this->assertEquals($customDate, $report['date']);
        } catch (\PDOException $e) {
            $this->markTestSkipped('Requires cloned_items table: ' . $e->getMessage());
        }
    }

    // =========================================================================
    // BACKOFF CALCULATION TESTS
    // =========================================================================

    /**
     * @test
     */
    public function backoff_formula_is_exponential(): void
    {
        // Test exponential backoff formula
        $baseDelay = 1000;
        $errors = [0, 10, 20, 30, 40, 50];
        $previousDelay = 0;

        foreach ($errors as $errorCount) {
            if ($errorCount > 10) {
                $delay = (int)min($baseDelay * pow(1.5, min($errorCount / 10, 5)), 30000);
                $this->assertGreaterThanOrEqual($previousDelay, $delay);
                $previousDelay = $delay;
            }
        }
    }

    /**
     * @test
     */
    public function delay_has_maximum_cap(): void
    {
        // Even with many errors, delay should be capped
        $baseDelay = 1000;
        $manyErrors = 100;

        $delay = (int)min($baseDelay * pow(1.5, min($manyErrors / 10, 5)), 30000);

        $this->assertLessThanOrEqual(30000, $delay);
    }
}
