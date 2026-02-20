<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\StockSyncController;
use ReflectionClass;
use Tests\TestCase;

/**
 * @covers \App\Controllers\StockSyncController
 */
class StockSyncControllerTest extends TestCase
{
    private StockSyncController $controller;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ReflectionClass(StockSyncController::class);
        $this->controller = $this->reflection->newInstanceWithoutConstructor();
    }

    public function testControllerCanBeInstantiatedViaReflection(): void
    {
        $this->assertInstanceOf(StockSyncController::class, $this->controller);
    }

    public function testControllerHasStrictTypesDeclaration(): void
    {
        $file = $this->reflection->getFileName();
        $this->assertNotFalse($file);

        $content = file_get_contents($file);
        $this->assertNotFalse($content);
        $this->assertStringContainsString('declare(strict_types=1);', $content);
    }

    public function testPublicEndpointsExist(): void
    {
        $methods = [
            'listRules',
            'createRule',
            'updateRule',
            'deleteRule',
            'fullSync',
            'processQueue',
            'webhook',
            'manualSync',
            'history',
            'stats',
            'getSettings',
            'updateSettings',
        ];

        foreach ($methods as $method) {
            $this->assertTrue($this->reflection->hasMethod($method), "Missing method: {$method}");
            $this->assertTrue($this->reflection->getMethod($method)->isPublic(), "Method is not public: {$method}");
        }
    }

    public function testClassHasExpectedProperties(): void
    {
        $expected = ['service', 'request'];

        foreach ($expected as $property) {
            $this->assertTrue($this->reflection->hasProperty($property), "Missing property: {$property}");
        }
    }
}
