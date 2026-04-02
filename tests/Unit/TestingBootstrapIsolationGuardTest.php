<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class TestingBootstrapIsolationGuardTest extends TestCase
{
    public function testBootstrapHasUnsafeDatabaseGuard(): void
    {
        $source = file_get_contents(dirname(__DIR__) . '/bootstrap.php');

        $this->assertIsString($source);
        $this->assertStringContainsString('isPhpUnitSafeDatabaseName', $source);
        $this->assertStringContainsString('PHPUNIT_ALLOW_NON_TEST_DB', $source);
        $this->assertStringContainsString('Unsafe PHPUnit database detected', $source);
    }
}