<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use App\Controllers\AdvancedReportController;
use ReflectionClass;
use Tests\TestCase;

/**
 * @covers \App\Controllers\AdvancedReportController
 */
class AdvancedReportControllerTest extends TestCase
{
    private AdvancedReportController $controller;
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ReflectionClass(AdvancedReportController::class);
        $this->controller = $this->reflection->newInstanceWithoutConstructor();
    }

    public function testPublicEndpointsExist(): void
    {
        $methods = ['salesTimeline', 'topProducts', 'hourly', 'consolidated', 'export', 'byCategory'];
        foreach ($methods as $method) {
            $this->assertTrue($this->reflection->hasMethod($method), "Missing method: {$method}");
        }
    }

    public function testGetPeriodStatsReturnsFallbackWhenDbThrows(): void
    {
        $db = $this->createMock(\PDO::class);
        $db->method('prepare')->willThrowException(new \PDOException('db unavailable'));
        $this->setPrivateProperty('db', $db);

        $method = $this->reflection->getMethod('getPeriodStats');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, 30, 'all', false);

        $this->assertSame(0, $result['total_sales']);
        $this->assertSame(0, $result['total_revenue']);
        $this->assertSame(0, $result['avg_ticket']);
        $this->assertSame(0, $result['active_listings']);
    }

    public function testGetTimelineDataReturnsEmptyArrayOnPdoException(): void
    {
        $db = $this->createMock(\PDO::class);
        $db->method('prepare')->willThrowException(new \PDOException('table missing'));
        $this->setPrivateProperty('db', $db);

        $method = $this->reflection->getMethod('getTimelineData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, 30, 'all', null, null);
        $this->assertSame([], $result);
    }

    public function testGetTopProductsDataReturnsEmptyArrayOnPdoException(): void
    {
        $db = $this->createMock(\PDO::class);
        $db->method('prepare')->willThrowException(new \PDOException('table missing'));
        $this->setPrivateProperty('db', $db);

        $method = $this->reflection->getMethod('getTopProductsData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, 10, 'all');
        $this->assertSame([], $result);
    }

    public function testGetHourlyDataReturnsEmptyArrayOnPdoException(): void
    {
        $db = $this->createMock(\PDO::class);
        $db->method('prepare')->willThrowException(new \PDOException('table missing'));
        $this->setPrivateProperty('db', $db);

        $method = $this->reflection->getMethod('getHourlyData');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, 7, 'all');
        $this->assertSame([], $result);
    }

    public function testGetSalesByCategoryReturnsEmptyArrayOnPdoException(): void
    {
        $db = $this->createMock(\PDO::class);
        $db->method('prepare')->willThrowException(new \PDOException('table missing'));
        $this->setPrivateProperty('db', $db);

        $method = $this->reflection->getMethod('getSalesByCategory');
        $method->setAccessible(true);

        $result = $method->invoke($this->controller, 'all');
        $this->assertSame([], $result);
    }

    private function setPrivateProperty(string $property, mixed $value): void
    {
        $prop = $this->reflection->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($this->controller, $value);
    }
}
