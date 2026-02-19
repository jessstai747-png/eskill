<?php

namespace Tests\Unit\Helpers;

use Tests\TestCase;
use App\Helpers\EnvValidator;

class EnvValidatorTest extends TestCase
{
    private array $originalEnv;

    protected function setUp(): void
    {
        parent::setUp();
        // Salvar estado original
        $this->originalEnv = $_ENV;
    }

    protected function tearDown(): void
    {
        // Restaurar estado original
        $_ENV = $this->originalEnv;
        parent::tearDown();
    }

    // =============================
    // TESTES DE VALIDAÇÃO
    // =============================

    public function testValidatePassesWithAllRequiredVars(): void
    {
        $_ENV['APP_KEY'] = str_repeat('x', 32);
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_DATABASE'] = 'test_db';
        $_ENV['DB_USERNAME'] = 'test_user';
        $_ENV['DB_PASSWORD'] = 'test_pass';

        $validator = new EnvValidator();
        $result = $validator->validate(false);

        $this->assertTrue($result);
        $this->assertEmpty($validator->getErrors());
    }

    public function testValidateFailsWithoutAppKey(): void
    {
        $_ENV['APP_KEY'] = '';
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_DATABASE'] = 'test_db';
        $_ENV['DB_USERNAME'] = 'test_user';
        $_ENV['DB_PASSWORD'] = 'test_pass';

        $validator = new EnvValidator();
        $result = $validator->validate(false);

        $this->assertFalse($result);

        $errors = $validator->getErrors();
        $errorVars = array_column($errors, 'variable');
        $this->assertContains('APP_KEY', $errorVars);
    }

    public function testValidateFailsWithShortAppKey(): void
    {
        $_ENV['APP_KEY'] = 'short_key'; // Menos de 32 caracteres
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_DATABASE'] = 'test_db';
        $_ENV['DB_USERNAME'] = 'test_user';
        $_ENV['DB_PASSWORD'] = 'test_pass';

        $validator = new EnvValidator();
        $result = $validator->validate(false);

        $this->assertFalse($result);

        $errors = $validator->getErrors();
        $found = false;
        foreach ($errors as $error) {
            if ($error['variable'] === 'APP_KEY' && $error['type'] === 'invalid') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'APP_KEY curto deveria gerar erro do tipo invalid');
    }

    public function testValidateFailsWithoutDbVars(): void
    {
        $_ENV['APP_KEY'] = str_repeat('x', 32);
        $_ENV['DB_HOST'] = '';
        $_ENV['DB_DATABASE'] = '';
        $_ENV['DB_USERNAME'] = '';
        $_ENV['DB_PASSWORD'] = '';

        $validator = new EnvValidator();
        $result = $validator->validate(false);

        $this->assertFalse($result);

        $errors = $validator->getErrors();
        $errorVars = array_column($errors, 'variable');

        $this->assertContains('DB_HOST', $errorVars);
        $this->assertContains('DB_DATABASE', $errorVars);
        $this->assertContains('DB_USERNAME', $errorVars);
    }

    // =============================
    // TESTES DE PRODUÇÃO
    // =============================

    public function testValidateProductionRequiresMlVars(): void
    {
        $_ENV['APP_KEY'] = str_repeat('x', 32);
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_DATABASE'] = 'test_db';
        $_ENV['DB_USERNAME'] = 'test_user';
        $_ENV['DB_PASSWORD'] = 'test_pass';
        $_ENV['APP_URL'] = '';
        $_ENV['ML_APP_ID'] = '';
        $_ENV['ML_CLIENT_SECRET'] = '';
        $_ENV['ML_REDIRECT_URI'] = '';

        $validator = new EnvValidator();
        $result = $validator->validate(true); // isProduction = true

        $this->assertFalse($result);

        $errors = $validator->getErrors();
        $errorVars = array_column($errors, 'variable');

        $this->assertContains('APP_URL', $errorVars);
        $this->assertContains('ML_APP_ID', $errorVars);
        $this->assertContains('ML_CLIENT_SECRET', $errorVars);
    }

    public function testValidateProductionWarnsAboutDebugMode(): void
    {
        $_ENV['APP_KEY'] = str_repeat('x', 32);
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_DATABASE'] = 'test_db';
        $_ENV['DB_USERNAME'] = 'test_user';
        $_ENV['DB_PASSWORD'] = 'test_pass';
        $_ENV['APP_URL'] = 'https://example.com';
        $_ENV['APP_DEBUG'] = 'true'; // Deveria ser false em produção
        $_ENV['ML_APP_ID'] = 'test_id';
        $_ENV['ML_CLIENT_SECRET'] = 'test_secret';
        $_ENV['ML_REDIRECT_URI'] = 'https://example.com/callback';

        $validator = new EnvValidator();
        $validator->validate(true);

        $warnings = $validator->getWarnings();
        $warningVars = array_column($warnings, 'variable');

        $this->assertContains('APP_DEBUG', $warningVars);
    }

    // =============================
    // TESTES DE URL
    // =============================

    public function testValidateRejectsInvalidUrl(): void
    {
        $_ENV['APP_KEY'] = str_repeat('x', 32);
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_DATABASE'] = 'test_db';
        $_ENV['DB_USERNAME'] = 'test_user';
        $_ENV['DB_PASSWORD'] = 'test_pass';
        $_ENV['APP_URL'] = 'not-a-valid-url'; // URL inválida

        $validator = new EnvValidator();
        $result = $validator->validate(true);

        $this->assertFalse($result);

        $errors = $validator->getErrors();
        $found = false;
        foreach ($errors as $error) {
            if ($error['variable'] === 'APP_URL' && $error['type'] === 'invalid_format') {
                $found = true;
                break;
            }
        }
        $this->assertTrue($found, 'URL inválida deveria gerar erro de formato');
    }

    public function testValidateAcceptsValidUrl(): void
    {
        $_ENV['APP_KEY'] = str_repeat('x', 32);
        $_ENV['DB_HOST'] = 'localhost';
        $_ENV['DB_DATABASE'] = 'test_db';
        $_ENV['DB_USERNAME'] = 'test_user';
        $_ENV['DB_PASSWORD'] = 'test_pass';
        $_ENV['APP_URL'] = 'https://example.com';
        $_ENV['ML_APP_ID'] = 'test_id';
        $_ENV['ML_CLIENT_SECRET'] = 'test_secret';
        $_ENV['ML_REDIRECT_URI'] = 'https://example.com/callback';

        $validator = new EnvValidator();
        $result = $validator->validate(true);

        // Deve passar sem erros de URL
        $errors = $validator->getErrors();
        $urlErrors = array_filter($errors, fn($e) => $e['type'] === 'invalid_format');

        $this->assertEmpty($urlErrors);
    }

    // =============================
    // TESTES DE RENDERIZAÇÃO
    // =============================

    public function testRenderErrorPageReturnsHtml(): void
    {
        $_ENV['APP_KEY'] = '';
        $_ENV['DB_HOST'] = '';

        $validator = new EnvValidator();
        $validator->validate(false);

        $html = $validator->renderErrorPage();

        $this->assertStringContainsString('<!DOCTYPE html>', $html);
        $this->assertStringContainsString('Erro de Configuração', $html);
        $this->assertStringContainsString('APP_KEY', $html);
    }

    public function testRenderErrorPageIncludesAllErrors(): void
    {
        $_ENV['APP_KEY'] = '';
        $_ENV['DB_HOST'] = '';
        $_ENV['DB_DATABASE'] = '';

        $validator = new EnvValidator();
        $validator->validate(false);

        $html = $validator->renderErrorPage();

        // Deve incluir todos os erros no HTML
        $this->assertStringContainsString('APP_KEY', $html);
        $this->assertStringContainsString('DB_HOST', $html);
        $this->assertStringContainsString('DB_DATABASE', $html);
    }

    // =============================
    // TESTES DE EDGE CASES
    // =============================

    public function testValidateTreatsNullStringAsEmpty(): void
    {
        $_ENV['APP_KEY'] = str_repeat('x', 32);
        $_ENV['DB_HOST'] = 'null'; // String 'null' deve ser tratada como vazio
        $_ENV['DB_DATABASE'] = 'test_db';
        $_ENV['DB_USERNAME'] = 'test_user';
        $_ENV['DB_PASSWORD'] = 'test_pass';

        $validator = new EnvValidator();
        $result = $validator->validate(false);

        $this->assertFalse($result);

        $errors = $validator->getErrors();
        $errorVars = array_column($errors, 'variable');
        $this->assertContains('DB_HOST', $errorVars);
    }

    public function testGetErrorsReturnsEmptyArrayInitially(): void
    {
        $validator = new EnvValidator();

        $this->assertIsArray($validator->getErrors());
        $this->assertEmpty($validator->getErrors());
    }

    public function testGetWarningsReturnsEmptyArrayInitially(): void
    {
        $validator = new EnvValidator();

        $this->assertIsArray($validator->getWarnings());
        $this->assertEmpty($validator->getWarnings());
    }
}
