<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Services\QueueService;
use ReflectionClass;
use Tests\TestCase;

/**
 * Unit Tests for QueueService
 *
 * Tests structural aspects and error handling via reflection.
 * QueueService depends on Redis, so we test without active connection.
 *
 * @covers \App\Services\QueueService
 */
class QueueServiceUnitTest extends TestCase
{
    private ReflectionClass $ref;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ref = new ReflectionClass(QueueService::class);
    }

    // =========================================================================
    // INSTANTIATION
    // =========================================================================

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(QueueService::class));
    }

    public function testCanBeInstantiatedViaReflection(): void
    {
        $service = $this->ref->newInstanceWithoutConstructor();
        $this->assertInstanceOf(QueueService::class, $service);
    }

    // =========================================================================
    // PUBLIC METHOD EXISTENCE
    // =========================================================================

    public function testPublicMethodsExist(): void
    {
        $methods = ['push', 'pop', 'isConnected'];
        foreach ($methods as $method) {
            $this->assertTrue(
                $this->ref->hasMethod($method),
                "Missing public method: {$method}"
            );
        }
    }

    public function testPushMethodSignature(): void
    {
        $method = $this->ref->getMethod('push');
        $params = $method->getParameters();

        $this->assertCount(3, $params);
        $this->assertSame('jobType', $params[0]->getName());
        $this->assertSame('payload', $params[1]->getName());
        $this->assertSame('queue', $params[2]->getName());
        $this->assertTrue($params[2]->isDefaultValueAvailable());
        $this->assertSame('default', $params[2]->getDefaultValue());
    }

    public function testPopMethodSignature(): void
    {
        $method = $this->ref->getMethod('pop');
        $params = $method->getParameters();

        $this->assertCount(2, $params);
        $this->assertSame('queue', $params[0]->getName());
        $this->assertSame('timeout', $params[1]->getName());
    }

    public function testPushReturnsString(): void
    {
        $method = $this->ref->getMethod('push');
        $returnType = $method->getReturnType();

        $this->assertNotNull($returnType);
        $this->assertSame('string', $returnType->getName());
    }

    // =========================================================================
    // CONNECTED STATE VIA REFLECTION
    // =========================================================================

    public function testIsConnectedReturnsFalseWhenNotConnected(): void
    {
        $service = $this->ref->newInstanceWithoutConstructor();

        $prop = $this->ref->getProperty('connected');
        $prop->setAccessible(true);
        $prop->setValue($service, false);

        $this->assertFalse($service->isConnected());
    }

    public function testIsConnectedReturnsTrueWhenConnected(): void
    {
        $service = $this->ref->newInstanceWithoutConstructor();

        $prop = $this->ref->getProperty('connected');
        $prop->setAccessible(true);
        $prop->setValue($service, true);

        $this->assertTrue($service->isConnected());
    }

    // =========================================================================
    // ensureConnected THROWS WHEN NOT CONNECTED
    // =========================================================================

    public function testEnsureConnectedThrowsWhenDisconnected(): void
    {
        $service = $this->ref->newInstanceWithoutConstructor();

        $prop = $this->ref->getProperty('connected');
        $prop->setAccessible(true);
        $prop->setValue($service, false);

        $method = $this->ref->getMethod('ensureConnected');
        $method->setAccessible(true);

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Redis indisponível');
        $method->invoke($service);
    }

    // =========================================================================
    // PROPERTY STRUCTURE
    // =========================================================================

    public function testHasExpectedProperties(): void
    {
        $expected = ['redis', 'queueName', 'connected'];
        foreach ($expected as $prop) {
            $this->assertTrue(
                $this->ref->hasProperty($prop),
                "Missing property: {$prop}"
            );
        }
    }

    public function testDefaultQueueName(): void
    {
        $service = $this->ref->newInstanceWithoutConstructor();

        $prop = $this->ref->getProperty('queueName');
        $prop->setAccessible(true);

        $this->assertSame('default_queue', $prop->getValue($service));
    }
}
