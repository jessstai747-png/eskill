<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \App\Controllers\CloneAutomationController
 */
class CloneAutomationControllerTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(\App\Controllers\CloneAutomationController::class);
    }

    public function testClassExists(): void
    {
        $this->assertTrue(class_exists(\App\Controllers\CloneAutomationController::class));
    }

    public function testHasDeclareStrictTypes(): void
    {
        $file = $this->reflection->getFileName();
        $this->assertNotFalse($file);
        $content = file_get_contents($file);
        $this->assertNotFalse($content);
        $this->assertStringContainsString('declare(strict_types=1)', $content);
    }

    public function testNamespace(): void
    {
        $this->assertSame('App\\Controllers', $this->reflection->getNamespaceName());
    }

    /**
     * @dataProvider publicEndpointsProvider
     */
    public function testPublicEndpointExists(string $method): void
    {
        $this->assertTrue($this->reflection->hasMethod($method));
        $this->assertTrue($this->reflection->getMethod($method)->isPublic());
    }

    public static function publicEndpointsProvider(): array
    {
        return [
            ['__construct'],
            ['listRules'],
            ['createRule'],
            ['getRule'],
            ['updateRule'],
            ['deleteRule'],
            ['enableRule'],
            ['pauseRule'],
            ['executeRule'],
            ['previewExecution'],
            ['getExecutionHistory'],
            ['getStats'],
            ['getTriggerTypes'],
        ];
    }

    public function testGetRuleAcceptsIntParameter(): void
    {
        $method = $this->reflection->getMethod('getRule');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('id', $params[0]->getName());
    }

    public function testUpdateRuleAcceptsIntParameter(): void
    {
        $method = $this->reflection->getMethod('updateRule');
        $params = $method->getParameters();
        $this->assertCount(1, $params);
        $this->assertSame('id', $params[0]->getName());
    }

    public function testHasSessionDerivedProperties(): void
    {
        $this->assertTrue($this->reflection->hasProperty('accountId'));
        $this->assertTrue($this->reflection->hasProperty('request'));
    }
}
