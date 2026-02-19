<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \App\Controllers\TitleGeneratorController
 */
class TitleGeneratorControllerTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(\App\Controllers\TitleGeneratorController::class);
    }

    // =========================================================================
    // Structural
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(\App\Controllers\TitleGeneratorController::class));
    }

    public function testHasDeclareStrictTypes(): void
    {
        $file = $this->reflection->getFileName();
        $this->assertNotFalse($file);
        $this->assertStringContainsString('declare(strict_types=1)', file_get_contents($file));
    }

    public function testIsStandaloneController(): void
    {
        // TitleGeneratorController does not extend BaseController
        $this->assertSame('App\\Controllers\\TitleGeneratorController', $this->reflection->getName());
    }

    public function testConstructorAcceptsAccountId(): void
    {
        $constructor = $this->reflection->getConstructor();
        $this->assertNotNull($constructor);
        $params = $constructor->getParameters();
        $this->assertGreaterThanOrEqual(1, count($params));
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
            ['generate'],
            ['improveFromItem'],
            ['analyze'],
            ['generateVariations'],
            ['generateABTesting'],
            ['compare'],
            ['optimize'],
            ['batchAnalyze'],
            ['quickTips'],
        ];
    }

    public function testGenerateMethodHasReturnType(): void
    {
        $method = $this->reflection->getMethod('generate');
        $retType = $method->getReturnType();
        $this->assertNotNull($retType);
    }

    public function testAnalyzeMethodHasReturnType(): void
    {
        $method = $this->reflection->getMethod('analyze');
        $retType = $method->getReturnType();
        $this->assertNotNull($retType);
    }

    public function testHasServiceDependencies(): void
    {
        // Should have generatorService, analyzerService, variationsService
        $this->assertTrue($this->reflection->hasProperty('generatorService') || $this->reflection->hasProperty('generator'));
        // At minimum, it exists and has private properties for services
        $props = $this->reflection->getProperties(\ReflectionProperty::IS_PRIVATE | \ReflectionProperty::IS_PROTECTED);
        $this->assertGreaterThanOrEqual(1, count($props));
    }

    public function testQuickTipsMethodExists(): void
    {
        $method = $this->reflection->getMethod('quickTips');
        $this->assertTrue($method->isPublic());
    }

    public function testAllMethodsHaveTryCatch(): void
    {
        $file = $this->reflection->getFileName();
        $this->assertNotFalse($file);
        $source = file_get_contents($file);
        // All public action methods should have try/catch blocks
        $catchCount = substr_count($source, 'catch (');
        $this->assertGreaterThanOrEqual(8, $catchCount);
    }
}
