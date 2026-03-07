<?php

declare(strict_types=1);

namespace Tests\Unit\Helpers;

use App\Helpers\EnvValidator;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Helpers\EnvValidator
 */
class EnvValidatorTest extends TestCase
{
    private array $originalEnv = [];
    private array $trackedKeys = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalEnv = $_ENV;
        $this->trackedKeys = [];
    }

    protected function tearDown(): void
    {
        // Only restore keys we explicitly set during the test
        foreach ($this->trackedKeys as $key) {
            if (array_key_exists($key, $this->originalEnv)) {
                $_ENV[$key] = $this->originalEnv[$key];
            } else {
                unset($_ENV[$key]);
            }
            // Also clean putenv
            putenv($key);
        }
        parent::tearDown();
    }

    private function setEnv(string $key, string $value): void
    {
        $this->trackedKeys[] = $key;
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }

    private function unsetEnv(string $key): void
    {
        $this->trackedKeys[] = $key;
        unset($_ENV[$key]);
        putenv($key);
    }

    // --- validate() basic ---

    public function testValidatePassesWithAllRequiredVars(): void
    {
        $this->setEnv("APP_KEY", str_repeat("a", 32));
        $this->setEnv("DB_HOST", "localhost");
        $this->setEnv("DB_DATABASE", "testdb");
        $this->setEnv("DB_USERNAME", "root");
        $this->setEnv("DB_PASSWORD", "secret");

        $v = new EnvValidator();
        $this->assertTrue($v->validate(false));
        $this->assertEmpty($v->getErrors());
    }

    public function testValidateFailsWithMissingRequired(): void
    {
        $this->unsetEnv("APP_KEY");
        $this->unsetEnv("DB_HOST");
        $this->unsetEnv("DB_DATABASE");
        $this->unsetEnv("DB_USERNAME");
        $this->unsetEnv("DB_PASSWORD");

        $v = new EnvValidator();
        $this->assertFalse($v->validate(false));
        $errors = $v->getErrors();
        $this->assertNotEmpty($errors);

        $errorVars = array_column($errors, "variable");
        $this->assertContains("APP_KEY", $errorVars);
        $this->assertContains("DB_HOST", $errorVars);
    }

    public function testValidateFailsWithShortAppKey(): void
    {
        $this->setEnv("APP_KEY", "tooshort");
        $this->setEnv("DB_HOST", "localhost");
        $this->setEnv("DB_DATABASE", "testdb");
        $this->setEnv("DB_USERNAME", "root");
        $this->setEnv("DB_PASSWORD", "secret");

        $v = new EnvValidator();
        $this->assertFalse($v->validate(false));

        $errors = $v->getErrors();
        $found = false;
        foreach ($errors as $e) {
            if ($e["variable"] === "APP_KEY" && $e["type"] === "invalid") {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, "Should report APP_KEY as invalid (too short)");
    }

    public function testValidateEmptyStringCountsAsMissing(): void
    {
        $this->setEnv("APP_KEY", str_repeat("a", 32));
        $this->setEnv("DB_HOST", "");
        $this->setEnv("DB_DATABASE", "testdb");
        $this->setEnv("DB_USERNAME", "root");
        $this->setEnv("DB_PASSWORD", "secret");

        $v = new EnvValidator();
        $this->assertFalse($v->validate(false));

        $errorVars = array_column($v->getErrors(), "variable");
        $this->assertContains("DB_HOST", $errorVars);
    }

    public function testValidateNullStringCountsAsMissing(): void
    {
        $this->setEnv("APP_KEY", str_repeat("a", 32));
        $this->setEnv("DB_HOST", "null");
        $this->setEnv("DB_DATABASE", "testdb");
        $this->setEnv("DB_USERNAME", "root");
        $this->setEnv("DB_PASSWORD", "secret");

        $v = new EnvValidator();
        $this->assertFalse($v->validate(false));

        $errorVars = array_column($v->getErrors(), "variable");
        $this->assertContains("DB_HOST", $errorVars);
    }

    // --- Production validation ---

    public function testProductionRequiresMLVars(): void
    {
        $this->setEnv("APP_KEY", str_repeat("a", 32));
        $this->setEnv("DB_HOST", "localhost");
        $this->setEnv("DB_DATABASE", "testdb");
        $this->setEnv("DB_USERNAME", "root");
        $this->setEnv("DB_PASSWORD", "secret");
        $this->unsetEnv("ML_APP_ID");
        $this->unsetEnv("ML_CLIENT_SECRET");
        $this->unsetEnv("ML_REDIRECT_URI");
        $this->unsetEnv("APP_URL");

        $v = new EnvValidator();
        $this->assertFalse($v->validate(true));

        $errorVars = array_column($v->getErrors(), "variable");
        $this->assertContains("ML_APP_ID", $errorVars);
        $this->assertContains("ML_CLIENT_SECRET", $errorVars);
        $this->assertContains("ML_REDIRECT_URI", $errorVars);
        $this->assertContains("APP_URL", $errorVars);
    }

    public function testProductionWarnsOnDebugTrue(): void
    {
        $this->setEnv("APP_KEY", str_repeat("a", 32));
        $this->setEnv("DB_HOST", "localhost");
        $this->setEnv("DB_DATABASE", "testdb");
        $this->setEnv("DB_USERNAME", "root");
        $this->setEnv("DB_PASSWORD", "secret");
        $this->setEnv("APP_URL", "https://example.com");
        $this->setEnv("ML_APP_ID", "12345");
        $this->setEnv("ML_CLIENT_SECRET", "secret");
        $this->setEnv("ML_REDIRECT_URI", "https://example.com/callback");
        $this->setEnv("APP_DEBUG", "true");

        $v = new EnvValidator();
        $v->validate(true);

        $warnings = $v->getWarnings();
        $warningVars = array_column($warnings, "variable");
        $this->assertContains("APP_DEBUG", $warningVars);
    }

    public function testNonProductionDoesNotRequireMLVars(): void
    {
        $this->setEnv("APP_KEY", str_repeat("a", 32));
        $this->setEnv("DB_HOST", "localhost");
        $this->setEnv("DB_DATABASE", "testdb");
        $this->setEnv("DB_USERNAME", "root");
        $this->setEnv("DB_PASSWORD", "secret");

        $v = new EnvValidator();
        $this->assertTrue($v->validate(false));
    }

    // --- URL validation ---

    public function testInvalidURLFormatReported(): void
    {
        $this->setEnv("APP_KEY", str_repeat("a", 32));
        $this->setEnv("DB_HOST", "localhost");
        $this->setEnv("DB_DATABASE", "testdb");
        $this->setEnv("DB_USERNAME", "root");
        $this->setEnv("DB_PASSWORD", "secret");
        $this->setEnv("APP_URL", "not-a-valid-url");

        $v = new EnvValidator();
        $v->validate(false);

        $errors = $v->getErrors();
        $found = false;
        foreach ($errors as $e) {
            if ($e["variable"] === "APP_URL" && $e["type"] === "invalid_format") {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, "Should detect invalid APP_URL format");
    }

    public function testValidURLPasses(): void
    {
        $this->setEnv("APP_KEY", str_repeat("a", 32));
        $this->setEnv("DB_HOST", "localhost");
        $this->setEnv("DB_DATABASE", "testdb");
        $this->setEnv("DB_USERNAME", "root");
        $this->setEnv("DB_PASSWORD", "secret");
        $this->setEnv("APP_URL", "https://seusite.com");

        $v = new EnvValidator();
        $this->assertTrue($v->validate(false));
    }

    // --- Recommended vars warnings ---

    public function testRecommendedVarsGenerateWarnings(): void
    {
        $this->setEnv("APP_KEY", str_repeat("a", 32));
        $this->setEnv("DB_HOST", "localhost");
        $this->setEnv("DB_DATABASE", "testdb");
        $this->setEnv("DB_USERNAME", "root");
        $this->setEnv("DB_PASSWORD", "secret");
        $this->unsetEnv("EMAIL_ENABLED");
        $this->unsetEnv("TELEGRAM_ENABLED");

        $v = new EnvValidator();
        $v->validate(false);

        $warnings = $v->getWarnings();
        $warningVars = array_column($warnings, "variable");
        $this->assertContains("EMAIL_ENABLED", $warningVars);
        $this->assertContains("TELEGRAM_ENABLED", $warningVars);
    }

    // --- renderErrorPage ---

    public function testRenderErrorPageReturnsHTML(): void
    {
        $this->unsetEnv("APP_KEY");
        $this->unsetEnv("DB_HOST");
        $this->unsetEnv("DB_DATABASE");
        $this->unsetEnv("DB_USERNAME");
        $this->unsetEnv("DB_PASSWORD");

        $v = new EnvValidator();
        $v->validate(false);

        $html = $v->renderErrorPage();
        $this->assertStringContainsString("<!DOCTYPE html>", $html);
        $this->assertStringContainsString("Erro de Configura", $html);
        $this->assertStringContainsString("APP_KEY", $html);
    }

    // --- Reset between calls ---

    public function testValidateResetsErrorsBetweenCalls(): void
    {
        $this->unsetEnv("APP_KEY");
        $this->unsetEnv("DB_HOST");
        $this->unsetEnv("DB_DATABASE");
        $this->unsetEnv("DB_USERNAME");
        $this->unsetEnv("DB_PASSWORD");

        $v = new EnvValidator();
        $v->validate(false);
        $firstErrors = $v->getErrors();

        $this->setEnv("APP_KEY", str_repeat("b", 32));
        $this->setEnv("DB_HOST", "localhost");
        $this->setEnv("DB_DATABASE", "testdb");
        $this->setEnv("DB_USERNAME", "root");
        $this->setEnv("DB_PASSWORD", "secret");

        $v->validate(false);
        $secondErrors = $v->getErrors();

        $this->assertNotEmpty($firstErrors);
        $this->assertEmpty($secondErrors);
    }
}