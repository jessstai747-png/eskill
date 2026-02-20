<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\BulkEditorController;
use ReflectionClass;
use Tests\TestCase;

/**
 * @covers \App\Controllers\BulkEditorController
 */
class BulkEditorControllerTest extends TestCase
{
    private BulkEditorController $controller;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reflection = new ReflectionClass(BulkEditorController::class);
        $this->controller = $this->reflection->newInstanceWithoutConstructor();
    }

    public function testPublicMethodsExist(): void
    {
        $methods = ['index', 'applyUpdates', 'previewChanges'];

        foreach ($methods as $method) {
            $this->assertTrue($this->reflection->hasMethod($method), "Missing method: {$method}");
            $this->assertTrue($this->reflection->getMethod($method)->isPublic(), "Method is not public: {$method}");
        }
    }

    public function testExpectedPropertiesExist(): void
    {
        $expectedProperties = ['itemService', 'mlClient', 'db', 'accountId', 'logger', 'userService', 'request'];

        foreach ($expectedProperties as $property) {
            $this->assertTrue($this->reflection->hasProperty($property), "Missing property: {$property}");
        }
    }

    public function testAllowedActionsContainCanonicalActions(): void
    {
        $constant = $this->reflection->getReflectionConstant('ALLOWED_ACTIONS');
        $this->assertNotFalse($constant);

        $actions = $constant->getValue();
        $this->assertContains('price_increase', $actions);
        $this->assertContains('price_decrease', $actions);
        $this->assertContains('price_set', $actions);
        $this->assertContains('stock_set', $actions);
        $this->assertContains('stock_add', $actions);
        $this->assertContains('pause', $actions);
        $this->assertContains('activate', $actions);
    }

    public function testNormalizeActionConvertsLegacyAliases(): void
    {
        $method = $this->reflection->getMethod('normalizeAction');
        $method->setAccessible(true);

        $this->assertSame('price_increase', $method->invoke($this->controller, 'price_increase_percent'));
        $this->assertSame('price_decrease', $method->invoke($this->controller, 'price_decrease_percent'));
        $this->assertSame('pause', $method->invoke($this->controller, 'status_pause'));
        $this->assertSame('activate', $method->invoke($this->controller, 'status_activate'));
        $this->assertSame('price_set', $method->invoke($this->controller, 'price_set'));
        $this->assertNull($method->invoke($this->controller, ''));
        $this->assertNull($method->invoke($this->controller, null));
    }

    public function testCalculateChangeForPriceIncreaseWithAlias(): void
    {
        $method = $this->reflection->getMethod('calculateChange');
        $method->setAccessible(true);

        $item = ['price' => 100.0, 'available_quantity' => 10, 'status' => 'active'];
        $result = $method->invoke($this->controller, $item, 'price_increase_percent', 10);

        $this->assertSame('price', $result['field']);
        $this->assertSame(100.0, $result['current']);
        $this->assertSame(110.0, $result['new']);
    }

    public function testCalculateChangeForPriceDecreaseRespectsMinimum(): void
    {
        $method = $this->reflection->getMethod('calculateChange');
        $method->setAccessible(true);

        $item = ['price' => 3.0, 'available_quantity' => 10, 'status' => 'active'];
        $result = $method->invoke($this->controller, $item, 'price_decrease', 90);

        $this->assertSame('price', $result['field']);
        $this->assertSame(1, $result['new']);
    }

    public function testCalculateChangeForStockActions(): void
    {
        $method = $this->reflection->getMethod('calculateChange');
        $method->setAccessible(true);

        $item = ['price' => 100.0, 'available_quantity' => 5, 'status' => 'active'];

        $setResult = $method->invoke($this->controller, $item, 'stock_set', -2);
        $addResult = $method->invoke($this->controller, $item, 'stock_add', -10);

        $this->assertSame('stock', $setResult['field']);
        $this->assertSame(0, $setResult['new']);
        $this->assertSame('stock', $addResult['field']);
        $this->assertSame(0, $addResult['new']);
    }

    public function testCalculateChangeForStatusAliases(): void
    {
        $method = $this->reflection->getMethod('calculateChange');
        $method->setAccessible(true);

        $item = ['price' => 100.0, 'available_quantity' => 5, 'status' => 'active'];

        $pauseResult = $method->invoke($this->controller, $item, 'status_pause', null);
        $activateResult = $method->invoke($this->controller, $item, 'status_activate', null);

        $this->assertSame('status', $pauseResult['field']);
        $this->assertSame('paused', $pauseResult['new']);
        $this->assertSame('status', $activateResult['field']);
        $this->assertSame('active', $activateResult['new']);
    }

    public function testCalculateChangeForInvalidActionReturnsUnknown(): void
    {
        $method = $this->reflection->getMethod('calculateChange');
        $method->setAccessible(true);

        $item = ['price' => 100.0, 'available_quantity' => 5, 'status' => 'active'];
        $result = $method->invoke($this->controller, $item, 'invalid_action', 1);

        $this->assertSame('unknown', $result['field']);
        $this->assertNull($result['current']);
        $this->assertNull($result['new']);
    }
}
