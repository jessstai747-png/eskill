<?php

declare(strict_types=1);

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

/**
 * @covers ::env
 * @covers ::config
 * @covers ::base_path
 * @covers ::app_path
 * @covers ::storage_path
 */
class FunctionsTest extends TestCase
{
    // ─── env() ───────────────────────────────────────────────────────────────

    public function testEnvReturnsStringValue(): void
    {
        $_ENV['TEST_STRING'] = 'hello';
        $this->assertSame('hello', env('TEST_STRING'));
        unset($_ENV['TEST_STRING']);
    }

    public function testEnvReturnsTrueForStringTrue(): void
    {
        $_ENV['TEST_BOOL_T'] = 'true';
        $this->assertSame(true, env('TEST_BOOL_T'));
        unset($_ENV['TEST_BOOL_T']);
    }

    public function testEnvReturnsFalseForStringFalse(): void
    {
        $_ENV['TEST_BOOL_F'] = 'false';
        $this->assertSame(false, env('TEST_BOOL_F'));
        unset($_ENV['TEST_BOOL_F']);
    }

    public function testEnvReturnsNullForStringNull(): void
    {
        $_ENV['TEST_NULL'] = 'null';
        $this->assertNull(env('TEST_NULL'));
        unset($_ENV['TEST_NULL']);
    }

    public function testEnvReturnsIntForNumericString(): void
    {
        $_ENV['TEST_INT'] = '42';
        $result = env('TEST_INT');
        $this->assertSame(42, $result);
        unset($_ENV['TEST_INT']);
    }

    public function testEnvReturnsFloatForDecimalString(): void
    {
        $_ENV['TEST_FLOAT'] = '3.14';
        $result = env('TEST_FLOAT');
        $this->assertSame(3.14, $result);
        unset($_ENV['TEST_FLOAT']);
    }

    public function testEnvStripsQuotesFromQuotedString(): void
    {
        $_ENV['TEST_QUOTED'] = '"quoted value"';
        $this->assertSame('quoted value', env('TEST_QUOTED'));
        unset($_ENV['TEST_QUOTED']);
    }

    public function testEnvReturnsEmptyStringForEmptyPlaceholder(): void
    {
        $_ENV['TEST_EMPTY'] = '(empty)';
        $this->assertSame('', env('TEST_EMPTY'));
        unset($_ENV['TEST_EMPTY']);
    }

    public function testEnvReturnsDefaultForMissingKey(): void
    {
        $this->assertSame('fallback', env('TEST_MISSING_KEY_XYZ', 'fallback'));
    }

    public function testEnvReturnsNullDefaultForMissingKeyWithNoDefault(): void
    {
        $this->assertNull(env('TEST_MISSING_KEY_XYZ_2'));
    }

    public function testEnvHandlesCaseInsensitiveTrueUppercase(): void
    {
        $_ENV['TEST_UPPER_TRUE'] = 'TRUE';
        $this->assertTrue(env('TEST_UPPER_TRUE'));
        unset($_ENV['TEST_UPPER_TRUE']);
    }

    public function testEnvHandlesNegativeInt(): void
    {
        $_ENV['TEST_NEG'] = '-5';
        $this->assertSame(-5, env('TEST_NEG'));
        unset($_ENV['TEST_NEG']);
    }

    // ─── config() ────────────────────────────────────────────────────────────

    public function testConfigReturnsNullForMissingKey(): void
    {
        $this->assertNull(config('__non_existent_config_key__'));
    }

    public function testConfigReturnsDefaultForMissingKey(): void
    {
        $this->assertSame('default-value', config('__non_existent_key__', 'default-value'));
    }

    public function testConfigIsFunctionCallable(): void
    {
        // As long as the function exists and returns without throwing, it works
        $result = config('__missing__', 42);
        $this->assertSame(42, $result);
    }

    // ─── base_path() ─────────────────────────────────────────────────────────

    public function testBasePathReturnsStringWithoutArgs(): void
    {
        $path = base_path();
        $this->assertIsString($path);
        $this->assertNotEmpty($path);
    }

    public function testBasePathJoinsSegments(): void
    {
        $path = base_path('config', 'app.php');
        $this->assertStringContainsString('config', $path);
        $this->assertStringContainsString('app.php', $path);
    }

    public function testBasePathEndsWithSegmentFile(): void
    {
        $path = base_path('composer.json');
        $this->assertFileExists($path);
    }

    // ─── app_path() ──────────────────────────────────────────────────────────

    public function testAppPathReturnsString(): void
    {
        $path = app_path();
        $this->assertIsString($path);
    }

    public function testAppPathJoinsSegments(): void
    {
        $path = app_path('Core', 'Router.php');
        $this->assertStringContainsString('Core', $path);
    }

    // ─── storage_path() ──────────────────────────────────────────────────────

    public function testStoragePathReturnsString(): void
    {
        $path = storage_path();
        $this->assertIsString($path);
        $this->assertStringContainsString('storage', $path);
    }

    public function testStoragePathJoinsSegments(): void
    {
        $path = storage_path('logs', 'app.log');
        $this->assertStringContainsString('logs', $path);
        $this->assertStringContainsString('app.log', $path);
    }
}
