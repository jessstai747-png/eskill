<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\BrandCentralController;
use ReflectionClass;
use Tests\TestCase;

/**
 * @covers \App\Controllers\BrandCentralController
 */
class BrandCentralControllerTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ReflectionClass(BrandCentralController::class);
    }

    public function testClassHasStrictTypesDeclaration(): void
    {
        $file = $this->reflection->getFileName();
        $this->assertNotFalse($file);

        $content = file_get_contents($file);
        $this->assertNotFalse($content);
        $this->assertStringContainsString('declare(strict_types=1);', $content);
    }

    public function testConstructorAcceptsAccountId(): void
    {
        $constructor = $this->reflection->getConstructor();
        $this->assertNotNull($constructor);
        $this->assertCount(1, $constructor->getParameters());
        $this->assertSame('accountId', $constructor->getParameters()[0]->getName());
    }

    public function testPublicEndpointsExist(): void
    {
        $methods = [
            'getStore',
            'updateStore',
            'getProducts',
            'addToShowcase',
            'removeFromShowcase',
            'getPerformance',
            'manageSections',
        ];

        foreach ($methods as $method) {
            $this->assertTrue($this->reflection->hasMethod($method), "Missing method: {$method}");
            $this->assertTrue($this->reflection->getMethod($method)->isPublic(), "Method is not public: {$method}");
        }
    }

    public function testClassHasExpectedDependencies(): void
    {
        $expected = ['brandService', 'request'];

        foreach ($expected as $property) {
            $this->assertTrue($this->reflection->hasProperty($property), "Missing property: {$property}");
        }
    }
}
