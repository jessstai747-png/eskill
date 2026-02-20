import sys

content = '''<?php

declare(strict_types=1);

namespace Tests\\Unit\\Services;

use PHPUnit\\Framework\\TestCase;
use ReflectionClass;

/**
 * @covers \\App\\Services\\AccountHealthService
 */
class AccountHealthServiceTest extends TestCase
{
    private ReflectionClass $reflection;

    protected function setUp(): void
    {
        $this->reflection = new ReflectionClass(\\App\\Services\\AccountHealthService::class);
    }

    private function invokePrivateMethod(string $methodName, array $args = []): mixed
    {
        $method = $this->reflection->getMethod($methodName);
        $method->setAccessible(true);
        $instance = $this->reflection->newInstanceWithoutConstructor();
        return $method->invokeArgs($instance, $args);
    }

    private function getPrivateConstant(string $name): mixed
    {
        return $this->reflection->getConstant($name);
    }
'''

with open(sys.argv[1], 'w') as f:
    f.write(content)

print('Base file written')