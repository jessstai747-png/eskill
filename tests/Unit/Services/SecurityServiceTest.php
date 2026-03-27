<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\SecurityService;

class SecurityServiceTest extends TestCase
{
    private SecurityService $security;

    protected function setUp(): void
    {
        parent::setUp();
        $this->security = new SecurityService();
    }

    // =============================
    // TESTES DE CSRF
    // =============================

    public function testGenerateCsrfTokenReturnsString(): void
    {
        $token = $this->security->generateCsrfToken();

        $this->assertIsString($token);
        $this->assertEquals(64, strlen($token)); // 32 bytes em hex = 64 chars
    }

    public function testGetCsrfTokenReturnsSameTokenIfNotExpired(): void
    {
        $token1 = $this->security->getCsrfToken();
        $token2 = $this->security->getCsrfToken();

        $this->assertEquals($token1, $token2);
    }

    public function testValidateCsrfTokenReturnsTrueForValidToken(): void
    {
        $token = $this->security->generateCsrfToken();
        $isValid = $this->security->validateCsrfToken($token);

        $this->assertTrue($isValid);
    }

    public function testValidateCsrfTokenReturnsFalseForInvalidToken(): void
    {
        $this->security->generateCsrfToken();
        $isValid = $this->security->validateCsrfToken('invalid_token_here');

        $this->assertFalse($isValid);
    }

    public function testValidateCsrfTokenReturnsFalseWhenNoTokenGenerated(): void
    {
        // Limpar sessão para garantir que não há token
        unset($_SESSION['csrf_token'], $_SESSION['csrf_token_time']);

        $isValid = $this->security->validateCsrfToken('any_token');

        $this->assertFalse($isValid);
    }

    // =============================
    // TESTES DE SANITIZAÇÃO
    // =============================

    public function testSanitizeEscapesHtml(): void
    {
        $input = '<script>alert("xss")</script>';
        $expected = '&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;';

        $this->assertEquals($expected, $this->security->sanitize($input));
    }

    public function testSanitizeEscapesQuotes(): void
    {
        $input = "Test 'single' and \"double\" quotes";
        $result = $this->security->sanitize($input);

        $this->assertStringContainsString('&#039;', $result);
        $this->assertStringContainsString('&quot;', $result);
    }

    public function testSanitizeArrayProcessesNestedArrays(): void
    {
        $input = [
            'name' => '<b>John</b>',
            'nested' => [
                'value' => '<script>bad</script>'
            ]
        ];

        $result = $this->security->sanitizeArray($input);

        $this->assertEquals('&lt;b&gt;John&lt;/b&gt;', $result['name']);
        $this->assertStringContainsString('&lt;script&gt;', $result['nested']['value']);
    }

    // =============================
    // TESTES DE SENHA
    // =============================

    public function testHashPasswordReturnsBcryptHash(): void
    {
        $hash = $this->security->hashPassword('password123');

        $this->assertStringStartsWith('$2y$', $hash);
    }

    public function testVerifyPasswordReturnsTrueForCorrectPassword(): void
    {
        $hash = $this->security->hashPassword('secret');
        $isValid = $this->security->verifyPassword('secret', $hash);

        $this->assertTrue($isValid);
    }

    public function testVerifyPasswordReturnsFalseForWrongPassword(): void
    {
        $hash = $this->security->hashPassword('secret');
        $isValid = $this->security->verifyPassword('wrong', $hash);

        $this->assertFalse($isValid);
    }

    // =============================
    // TESTES DE TOKENS SEGUROS
    // =============================

    public function testGenerateSecureTokenReturnsCorrectLength(): void
    {
        $token16 = $this->security->generateSecureToken(16);
        $token32 = $this->security->generateSecureToken(32);

        $this->assertEquals(32, strlen($token16)); // 16 bytes = 32 hex chars
        $this->assertEquals(64, strlen($token32)); // 32 bytes = 64 hex chars
    }

    public function testGenerateSecureTokenIsUnique(): void
    {
        $token1 = $this->security->generateSecureToken();
        $token2 = $this->security->generateSecureToken();

        $this->assertNotEquals($token1, $token2);
    }

    // =============================
    // TESTES DE CRIPTOGRAFIA
    // =============================

    public function testEncryptAndDecryptRoundTrip(): void
    {
        $original = 'Dados sensíveis aqui!';
        $encrypted = $this->security->encrypt($original);
        $decrypted = $this->security->decrypt($encrypted);

        $this->assertNotEquals($original, $encrypted);
        $this->assertEquals($original, $decrypted);
    }

    public function testEncryptProducesDifferentOutputForSameInput(): void
    {
        $data = 'same data';
        $encrypted1 = $this->security->encrypt($data);
        $encrypted2 = $this->security->encrypt($data);

        // Devido ao IV aleatório, outputs devem ser diferentes
        $this->assertNotEquals($encrypted1, $encrypted2);
    }
}
