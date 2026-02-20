<?php

namespace Tests\Unit;

use App\Core\Container;
use PHPUnit\Framework\TestCase;

class ServiceStub {}
class DependentServiceStub {
    public ServiceStub $service;
    public function __construct(ServiceStub $service) {
        $this->service = $service;
    }
}
class OptionalPdoServiceStub {
    public ?\PDO $db;
    public function __construct(?\PDO $db = null) {
        $this->db = $db;
    }
}

class ContainerTest extends TestCase
{
    public function testAutoWiring()
    {
        $container = new Container();
        $instance = $container->get(DependentServiceStub::class);

        $this->assertInstanceOf(DependentServiceStub::class, $instance);
        $this->assertInstanceOf(ServiceStub::class, $instance->service);
    }

    public function testSingletonBinding()
    {
        $container = new Container();
        $instance1 = new ServiceStub();
        
        $container->singleton(ServiceStub::class, fn() => $instance1);
        
        $result1 = $container->get(ServiceStub::class);
        $result2 = $container->get(ServiceStub::class);

        $this->assertSame($instance1, $result1);
        $this->assertSame($result1, $result2);
    }

    public function testOptionalTypedDependencyFallsBackToDefault(): void
    {
        $container = new Container();
        $instance = $container->get(OptionalPdoServiceStub::class);

        $this->assertInstanceOf(OptionalPdoServiceStub::class, $instance);
        $this->assertNull($instance->db);
    }
}
