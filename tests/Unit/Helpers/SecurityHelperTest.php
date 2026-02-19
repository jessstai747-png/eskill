<?php

namespace Tests\Unit\Helpers;

use Tests\TestCase;
use App\Helpers\SecurityHelper;

class SecurityHelperTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Limpar sessão para cada teste
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);
    }

    // =============================
    // TESTES DE CSRF
    // =============================

    public function testCsrfTokenReturnsString(): void
    {
        $token = SecurityHelper::csrfToken();

        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token));
    }

    public function testCsrfTokenReturnsSameTokenOnMultipleCalls(): void
    {
        $token1 = SecurityHelper::csrfToken();
        $token2 = SecurityHelper::csrfToken();

        $this->assertEquals($token1, $token2);
    }

    public function testCsrfFieldReturnsHiddenInput(): void
    {
        $field = SecurityHelper::csrfField();

        $this->assertStringContainsString('<input type="hidden"', $field);
        $this->assertStringContainsString('name="_token"', $field);
        $this->assertStringContainsString('value="', $field);
    }

    public function testCsrfFieldContainsValidToken(): void
    {
        $field = SecurityHelper::csrfField();
        $token = SecurityHelper::csrfToken();

        $this->assertStringContainsString($token, $field);
    }

    // =============================
    // TESTES DE SANITIZAÇÃO
    // =============================

    public function testEscapesHtmlTags(): void
    {
        $input = '<script>alert("xss")</script>';
        $result = SecurityHelper::e($input);

        $this->assertStringNotContainsString('<script>', $result);
        $this->assertStringContainsString('&lt;script&gt;', $result);
    }

    public function testEscapesQuotes(): void
    {
        $input = 'Test "double" and \'single\' quotes';
        $result = SecurityHelper::e($input);

        $this->assertStringContainsString('&quot;', $result);
        $this->assertStringContainsString('&#039;', $result);
    }

    public function testPreservesPlainText(): void
    {
        $input = 'Normal text without special characters';
        $result = SecurityHelper::e($input);

        $this->assertEquals($input, $result);
    }

    public function testHandlesEmptyString(): void
    {
        $result = SecurityHelper::e('');

        $this->assertEquals('', $result);
    }

    public function testHandlesUtf8Characters(): void
    {
        $input = 'Texto com acentuação: çãõéê';
        $result = SecurityHelper::e($input);

        $this->assertEquals($input, $result);
    }

    public function testEscapesAmpersand(): void
    {
        $input = 'A & B';
        $result = SecurityHelper::e($input);

        $this->assertStringContainsString('&amp;', $result);
    }

    public function testEscapesComplexXssVector(): void
    {
        $input = '<img src="x" onerror="alert(1)">';
        $result = SecurityHelper::e($input);

        // htmlspecialchars ESCAPA (não remove) - deve conter versão escapada
        $this->assertStringContainsString('&lt;img', $result);
        $this->assertStringContainsString('&gt;', $result);
        // Não deve executar como HTML
        $this->assertStringNotContainsString('<img', $result);
    }
}
