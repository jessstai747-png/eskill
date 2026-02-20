<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\ListingBuilderController;
use ReflectionClass;
use Tests\TestCase;

/**
 * @covers \App\Controllers\ListingBuilderController
 */
class ListingBuilderControllerTest extends TestCase
{
    private ListingBuilderController $controller;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reflection = new ReflectionClass(ListingBuilderController::class);
        $this->controller = $this->reflection->newInstanceWithoutConstructor();
    }

    public function testControllerCanBeInstantiatedViaReflection(): void
    {
        $this->assertInstanceOf(ListingBuilderController::class, $this->controller);
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
            'start',
            'validateStep',
            'build',
            'publish',
            'saveDraft',
            'loadDraft',
            'clone',
            'listTemplates',
            'getTemplate',
            'renderTemplate',
            'createCustomTemplate',
            'listBlocks',
        ];

        foreach ($methods as $method) {
            $this->assertTrue($this->reflection->hasMethod($method), "Missing method: {$method}");
            $this->assertTrue($this->reflection->getMethod($method)->isPublic(), "Method is not public: {$method}");
        }
    }

    public function testClassHasExpectedProperties(): void
    {
        $expected = ['builder', 'templateManager', 'accountId', 'request'];

        foreach ($expected as $property) {
            $this->assertTrue($this->reflection->hasProperty($property), "Missing property: {$property}");
        }
    }
}
