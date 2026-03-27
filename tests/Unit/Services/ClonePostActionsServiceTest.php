<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\ClonePostActionsService;
use PHPUnit\Framework\TestCase;

/**
 * Tests for ClonePostActionsService
 * 
 * Note: These tests verify the service structure and action definitions.
 * Full integration tests would need the clone_post_actions_log table.
 */
class ClonePostActionsServiceTest extends TestCase
{
    private ClonePostActionsService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ClonePostActionsService();
    }

    /**
     * @test
     */
    public function service_can_be_instantiated(): void
    {
        $this->assertInstanceOf(ClonePostActionsService::class, $this->service);
    }

    /**
     * @test
     */
    public function it_can_list_available_actions(): void
    {
        $actions = $this->service->getAvailableActions();

        $this->assertIsArray($actions);
        $this->assertArrayHasKey('tech_sheet', $actions);
        $this->assertArrayHasKey('seo_optimize', $actions);
        $this->assertArrayHasKey('pricing_apply', $actions);
        $this->assertArrayHasKey('activate', $actions);
    }

    /**
     * @test
     */
    public function available_actions_have_required_fields(): void
    {
        $actions = $this->service->getAvailableActions();

        foreach ($actions as $key => $action) {
            $this->assertIsString($key, "Action key should be a string");
            $this->assertArrayHasKey('name', $action, "Action {$key} should have 'name'");
            $this->assertArrayHasKey('description', $action, "Action {$key} should have 'description'");
            $this->assertArrayHasKey('enabled', $action, "Action {$key} should have 'enabled'");
        }
    }

    /**
     * @test
     */
    public function tech_sheet_action_is_defined(): void
    {
        $actions = $this->service->getAvailableActions();

        $this->assertArrayHasKey('tech_sheet', $actions);
        $this->assertNotEmpty($actions['tech_sheet']['name']);
        $this->assertIsBool($actions['tech_sheet']['enabled']);
    }

    /**
     * @test
     */
    public function seo_optimize_action_is_defined(): void
    {
        $actions = $this->service->getAvailableActions();

        $this->assertArrayHasKey('seo_optimize', $actions);
        $this->assertNotEmpty($actions['seo_optimize']['name']);
    }

    /**
     * @test
     */
    public function pricing_apply_action_is_defined(): void
    {
        $actions = $this->service->getAvailableActions();

        $this->assertArrayHasKey('pricing_apply', $actions);
        $this->assertNotEmpty($actions['pricing_apply']['name']);
    }

    /**
     * @test
     */
    public function activate_action_is_defined(): void
    {
        $actions = $this->service->getAvailableActions();

        $this->assertArrayHasKey('activate', $actions);
        $this->assertNotEmpty($actions['activate']['name']);
    }

    /**
     * @test
     */
    public function action_types_are_valid_strings(): void
    {
        $validActions = ['tech_sheet', 'seo_optimize', 'pricing_apply', 'activate'];
        $actions = $this->service->getAvailableActions();

        foreach (array_keys($actions) as $actionType) {
            $this->assertContains($actionType, $validActions, "Unknown action type: {$actionType}");
        }
    }

    /**
     * @test
     */
    public function actions_count_is_correct(): void
    {
        $actions = $this->service->getAvailableActions();

        // Should have exactly 4 action types
        $this->assertCount(4, $actions);
    }
}
