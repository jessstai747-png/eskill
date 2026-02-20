<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\PushController;
use ReflectionClass;
use Tests\TestCase;

/**
 * @covers \App\Controllers\PushController
 */
class PushControllerTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ReflectionClass(PushController::class);
    }

    public function testPublicEndpointsExist(): void
    {
        $methods = [
            'vapidKey',
            'subscribe',
            'unsubscribe',
            'registerDevice',
            'unregisterDevice',
            'subscriptions',
            'test',
            'send',
            'stats',
            'status',
            'trackInstall',
        ];

        foreach ($methods as $method) {
            $this->assertTrue($this->reflection->hasMethod($method), "Missing method: {$method}");
            $this->assertTrue($this->reflection->getMethod($method)->isPublic(), "Method is not public: {$method}");
        }
    }

    public function testControllerHasExpectedDependencies(): void
    {
        $expectedProperties = [
            'pushService',
            'userService',
            'mobileDeviceService',
        ];

        foreach ($expectedProperties as $property) {
            $this->assertTrue($this->reflection->hasProperty($property), "Missing property: {$property}");
        }
    }

    public function testJsonResponseHelperExistsAsPrivateMethod(): void
    {
        $this->assertTrue($this->reflection->hasMethod('jsonResponse'));
        $this->assertTrue($this->reflection->getMethod('jsonResponse')->isPrivate());
    }
}

