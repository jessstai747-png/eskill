<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\MultiAccountController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;

/**
 * @covers \App\Controllers\MultiAccountController
 */
class MultiAccountControllerTest extends TestCase
{
    /**
     * Test controller has required methods
     */
    public function testControllerHasRequiredMethods(): void
    {
        $reflection = new ReflectionClass(MultiAccountController::class);

        $requiredMethods = [
            'getDashboard',
            'getAccounts',
            'switchAccount',
        ];

        foreach ($requiredMethods as $method) {
            $this->assertTrue(
                $reflection->hasMethod($method),
                "Missing required method: {$method}"
            );
        }
    }

    /**
     * Test controller has manager property
     */
    public function testControllerHasManagerProperty(): void
    {
        $reflection = new ReflectionClass(MultiAccountController::class);
        $this->assertTrue($reflection->hasProperty('manager'));
    }

    /**
     * Test controller has userId property
     */
    public function testControllerHasUserIdProperty(): void
    {
        $reflection = new ReflectionClass(MultiAccountController::class);
        $this->assertTrue($reflection->hasProperty('userId'));
    }

    /**
     * Test controller has request property
     */
    public function testControllerHasRequestProperty(): void
    {
        $reflection = new ReflectionClass(MultiAccountController::class);
        $this->assertTrue($reflection->hasProperty('request'));
    }

    /**
     * Test getDashboard method is public
     */
    public function testGetDashboardMethodIsPublic(): void
    {
        $reflection = new ReflectionMethod(MultiAccountController::class, 'getDashboard');
        $this->assertTrue($reflection->isPublic());
    }

    /**
     * Test getDashboard returns void (outputs JSON)
     */
    public function testGetDashboardReturnsVoid(): void
    {
        $reflection = new ReflectionMethod(MultiAccountController::class, 'getDashboard');
        $returnType = $reflection->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertSame('void', $returnType->getName());
    }

    /**
     * Test controller has jsonError method
     */
    public function testControllerHasJsonErrorMethod(): void
    {
        $reflection = new ReflectionClass(MultiAccountController::class);
        $this->assertTrue($reflection->hasMethod('jsonError'));
    }

    /**
     * Test jsonError method is private or protected
     */
    public function testJsonErrorMethodIsNotPublic(): void
    {
        $reflection = new ReflectionMethod(MultiAccountController::class, 'jsonError');
        $this->assertFalse($reflection->isPublic());
    }

    /**
     * Test controller uses MultiAccountManager
     */
    public function testControllerUsesMultiAccountManager(): void
    {
        $reflection = new ReflectionClass(MultiAccountController::class);
        $property = $reflection->getProperty('manager');
        $property->setAccessible(true);

        // Check the type hint
        $type = $property->getType();
        $this->assertNotNull($type);
        $this->assertSame('App\Services\AI\SEO\MultiAccountManager', $type->getName());
    }

    /**
     * Test controller uses Request class
     */
    public function testControllerUsesRequestClass(): void
    {
        $reflection = new ReflectionClass(MultiAccountController::class);
        $property = $reflection->getProperty('request');
        $property->setAccessible(true);

        $type = $property->getType();
        $this->assertNotNull($type);
        $this->assertSame('App\Core\Request', $type->getName());
    }

    /**
     * Test getAccounts method exists and is public
     */
    public function testGetAccountsMethodIsPublic(): void
    {
        $reflection = new ReflectionMethod(MultiAccountController::class, 'getAccounts');
        $this->assertTrue($reflection->isPublic());
    }

    /**
     * Test switchAccount method exists and is public
     */
    public function testSwitchAccountMethodIsPublic(): void
    {
        $reflection = new ReflectionMethod(MultiAccountController::class, 'switchAccount');
        $this->assertTrue($reflection->isPublic());
    }
}
