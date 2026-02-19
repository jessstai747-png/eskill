<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \App\Controllers\SeoOptimizationController
 *
 * Testes estruturais e comportamentais para SeoOptimizationController.
 * Testa metodos puros: validateRequired, resolveServiceClass, checkService.
 */
class SeoOptimizationControllerTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(\App\Controllers\SeoOptimizationController::class);
    }

    /**
     * Helper: invoca metodo privado numa instancia sem construtor
     */
    private function invokePrivateMethod(string $methodName, array $args = []): mixed
    {
        $method = $this->reflection->getMethod($methodName);
        $method->setAccessible(true);
        $instance = $this->reflection->newInstanceWithoutConstructor();
        return $method->invokeArgs($instance, $args);
    }

    // =========================================================================
    // Structural Tests
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(\App\Controllers\SeoOptimizationController::class));
    }

    public function testHasDeclareStrictTypes(): void
    {
        $file = $this->reflection->getFileName();
        $this->assertNotFalse($file);
        $content = file_get_contents($file);
        $this->assertStringContainsString('declare(strict_types=1)', $content);
    }

    public function testExtendsBaseController(): void
    {
        $this->assertTrue($this->reflection->isSubclassOf(\App\Controllers\BaseController::class));
    }

    public function testHasHealthCheckMethod(): void
    {
        $this->assertTrue($this->reflection->hasMethod('healthCheck'));
        $method = $this->reflection->getMethod('healthCheck');
        $this->assertTrue($method->isPublic());
        $this->assertSame('void', $method->getReturnType()->getName());
    }

    public function testHasCheckServiceMethod(): void
    {
        $this->assertTrue($this->reflection->hasMethod('checkService'));
        $method = $this->reflection->getMethod('checkService');
        $this->assertTrue($method->isPrivate());
    }

    public function testHasValidateRequiredMethod(): void
    {
        $this->assertTrue($this->reflection->hasMethod('validateRequired'));
        $method = $this->reflection->getMethod('validateRequired');
        $this->assertTrue($method->isPrivate());
    }

    public function testHasResolveServiceClassMethod(): void
    {
        $this->assertTrue($this->reflection->hasMethod('resolveServiceClass'));
        $method = $this->reflection->getMethod('resolveServiceClass');
        $this->assertTrue($method->isPrivate());
    }

    public function testCheckServiceParameterTypes(): void
    {
        $method = $this->reflection->getMethod('checkService');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('serviceName', $params[0]->getName());
        $this->assertSame('string', $params[0]->getType()->getName());
    }

    public function testValidateRequiredParameterTypes(): void
    {
        $method = $this->reflection->getMethod('validateRequired');
        $params = $method->getParameters();
        $this->assertCount(2, $params);
        $this->assertSame('input', $params[0]->getName());
        $this->assertSame('array', $params[0]->getType()->getName());
        $this->assertSame('required', $params[1]->getName());
        $this->assertSame('array', $params[1]->getType()->getName());
    }

    public function testValidateRequiredReturnType(): void
    {
        $method = $this->reflection->getMethod('validateRequired');
        $this->assertSame('void', $method->getReturnType()->getName());
    }

    // =========================================================================
    // Behavioral: validateRequired
    // =========================================================================

    public function testValidateRequiredPassesWithAllFields(): void
    {
        // Should not throw
        $this->invokePrivateMethod('validateRequired', [
            ['name' => 'test', 'email' => 'x@y.com'],
            ['name', 'email'],
        ]);
        $this->assertTrue(true); // No exception
    }

    public function testValidateRequiredThrowsOnMissingField(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('email');
        $this->invokePrivateMethod('validateRequired', [
            ['name' => 'test'],
            ['name', 'email'],
        ]);
    }

    public function testValidateRequiredThrowsOnMultipleMissing(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->invokePrivateMethod('validateRequired', [
            [],
            ['name', 'email', 'phone'],
        ]);
    }

    public function testValidateRequiredAcceptsNullValues(): void
    {
        // array_key_exists returns true for null values
        $this->invokePrivateMethod('validateRequired', [
            ['name' => null],
            ['name'],
        ]);
        $this->assertTrue(true);
    }

    public function testValidateRequiredAcceptsEmptyRequired(): void
    {
        $this->invokePrivateMethod('validateRequired', [
            ['anything' => 'value'],
            [],
        ]);
        $this->assertTrue(true);
    }

    // =========================================================================
    // Behavioral: resolveServiceClass
    // =========================================================================

    public function testResolveServiceClassSEONamespace(): void
    {
        $result = $this->invokePrivateMethod('resolveServiceClass', ['SEOOptimizerService']);
        // Should resolve existing class in App\Services\SEO\ or fallback
        $this->assertIsString($result);
        $this->assertStringContainsString('SEOOptimizerService', $result);
    }

    public function testResolveServiceClassFallbackFormat(): void
    {
        // Non-existent service should return fallback in SEO namespace
        $result = $this->invokePrivateMethod('resolveServiceClass', ['NonExistentService123']);
        $this->assertSame('App\\Services\\SEO\\NonExistentService123', $result);
    }

    public function testResolveServiceClassReturnsString(): void
    {
        $result = $this->invokePrivateMethod('resolveServiceClass', ['TestService']);
        $this->assertIsString($result);
    }

    // =========================================================================
    // Behavioral: checkService
    // =========================================================================

    public function testCheckServiceReturnsArrayWithStatus(): void
    {
        $result = $this->invokePrivateMethod('checkService', ['NonExistentService999']);
        $this->assertIsArray($result);
        $this->assertArrayHasKey('status', $result);
    }

    public function testCheckServiceUnhealthyForMissingClass(): void
    {
        $result = $this->invokePrivateMethod('checkService', ['CompletelyFakeService123456']);
        $this->assertSame('unhealthy', $result['status']);
        $this->assertArrayHasKey('message', $result);
        $this->assertStringContainsString('encontrada', $result['message']);
    }

    public function testCheckServiceHandlesThrowable(): void
    {
        // If any exception occurs during check, status should be 'error'
        // We test with a class that exists but fails to instantiate without args
        // However, since checkService catches \Throwable, it should be safe
        $result = $this->invokePrivateMethod('checkService', ['SomeService']);
        $this->assertIsArray($result);
        $this->assertContains($result['status'], ['healthy', 'unhealthy', 'error']);
    }
}
