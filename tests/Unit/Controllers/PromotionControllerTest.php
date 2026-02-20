<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\PromotionController;
use ReflectionClass;
use Tests\TestCase;

/**
 * @covers \App\Controllers\PromotionController
 */
class PromotionControllerTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ReflectionClass(PromotionController::class);
    }

    public function testPublicEndpointsExist(): void
    {
        $methods = ['index', 'listPromotions', 'detail', 'join'];

        foreach ($methods as $method) {
            $this->assertTrue($this->reflection->hasMethod($method), "Missing method: {$method}");
            $this->assertTrue($this->reflection->getMethod($method)->isPublic(), "Method is not public: {$method}");
        }
    }

    public function testControllerHasExpectedDependencies(): void
    {
        $expectedProperties = ['promotionService', 'userService'];

        foreach ($expectedProperties as $property) {
            $this->assertTrue($this->reflection->hasProperty($property), "Missing property: {$property}");
        }
    }
}
