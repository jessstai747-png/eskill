<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \App\Controllers\SeoDescriptionController
 */
class SeoDescriptionControllerTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(\App\Controllers\SeoDescriptionController::class);
    }

    // =========================================================================
    // Structural
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(\App\Controllers\SeoDescriptionController::class));
    }

    public function testHasDeclareStrictTypes(): void
    {
        $file = $this->reflection->getFileName();
        $this->assertNotFalse($file);
        $this->assertStringContainsString('declare(strict_types=1)', file_get_contents($file));
    }

    public function testExtendsBaseController(): void
    {
        $parent = $this->reflection->getParentClass();
        $this->assertNotFalse($parent);
        $this->assertSame('App\\Controllers\\BaseController', $parent->getName());
    }

    public function testClassIsDeprecated(): void
    {
        $docComment = $this->reflection->getDocComment();
        $this->assertNotFalse($docComment);
        $this->assertStringContainsString('@deprecated', $docComment);
    }

    /**
     * @dataProvider publicMethodsProvider
     */
    public function testPublicMethodExists(string $method): void
    {
        $this->assertTrue($this->reflection->hasMethod($method));
        $this->assertTrue($this->reflection->getMethod($method)->isPublic());
    }

    public static function publicMethodsProvider(): array
    {
        return [
            ['build'],
            ['generateBlock'],
            ['generateFaq'],
            ['validate'],
            ['getContexts'],
            ['generateLongTail'],
        ];
    }

    public function testBuildMethodHasReturnType(): void
    {
        $method = $this->reflection->getMethod('build');
        $this->assertNotNull($method->getReturnType());
    }

    public function testValidateMethodHasReturnType(): void
    {
        $method = $this->reflection->getMethod('validate');
        $this->assertNotNull($method->getReturnType());
    }

    public function testAllEndpointsReturnJsonResponse(): void
    {
        $file = $this->reflection->getFileName();
        $source = file_get_contents($file);
        // All endpoints use jsonResponse() for output
        $jsonCalls = substr_count($source, 'jsonResponse(');
        $this->assertGreaterThanOrEqual(6, $jsonCalls);
    }

    public function testHasSixPublicEndpoints(): void
    {
        $methods = $this->reflection->getMethods(\ReflectionMethod::IS_PUBLIC);
        $ownMethods = array_filter($methods, fn($m) => $m->getDeclaringClass()->getName() === $this->reflection->getName());
        $this->assertGreaterThanOrEqual(6, count($ownMethods));
    }

    public function testHasJsonResponseHelper(): void
    {
        $this->assertTrue($this->reflection->hasMethod('jsonResponse'));
    }
}
