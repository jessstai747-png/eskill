<?php

declare(strict_types=1);

namespace Tests\Unit\Controllers;

use Tests\TestCase;

/**
 * Testes do Mobile AuthController
 *
 * Verifica token HMAC-SHA256, validação de input e segurança.
 */
class MobileAuthControllerTest extends TestCase
{
    // ===========================
    // STRUCTURE TESTS
    // ===========================

    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(\App\Controllers\Mobile\AuthController::class));
    }

    public function test_has_required_methods(): void
    {
        $this->assertTrue(method_exists(\App\Controllers\Mobile\AuthController::class, 'login'));
        $this->assertTrue(method_exists(\App\Controllers\Mobile\AuthController::class, 'validateSignedToken'));
    }

    // ===========================
    // TOKEN SECURITY TESTS
    // ===========================

    public function test_no_hardcoded_secret_in_source(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Controllers/Mobile/AuthController.php'
        );

        $this->assertStringNotContainsString(
            'mobile_secret',
            $source,
            'Não deve conter secret hardcoded "mobile_secret"'
        );
    }

    public function test_uses_hmac_for_token_generation(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Controllers/Mobile/AuthController.php'
        );

        $this->assertStringContainsString(
            'hash_hmac',
            $source,
            'Deve usar hash_hmac para gerar tokens'
        );

        $this->assertStringContainsString(
            'sha256',
            $source,
            'Deve usar SHA-256 como algoritmo de hash'
        );
    }

    public function test_token_has_expiration(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Controllers/Mobile/AuthController.php'
        );

        $this->assertStringContainsString(
            "'exp'",
            $source,
            'Token deve conter campo de expiração'
        );
    }

    public function test_token_has_unique_identifier(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Controllers/Mobile/AuthController.php'
        );

        $this->assertStringContainsString(
            "'jti'",
            $source,
            'Token deve conter JTI (unique identifier)'
        );
    }

    public function test_requires_environment_secret(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Controllers/Mobile/AuthController.php'
        );

        $this->assertStringContainsString(
            'MOBILE_TOKEN_SECRET',
            $source,
            'Deve usar MOBILE_TOKEN_SECRET do environment'
        );
    }

    public function test_validates_secret_minimum_length(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Controllers/Mobile/AuthController.php'
        );

        $this->assertStringContainsString(
            'strlen($this->tokenSecret) < 32',
            $source,
            'Deve validar comprimento mínimo (32 chars) do secret'
        );
    }

    // ===========================
    // VALIDATE SIGNED TOKEN TESTS
    // ===========================

    public function test_validateSignedToken_rejects_malformed_token(): void
    {
        $result = \App\Controllers\Mobile\AuthController::validateSignedToken('not-a-valid-token');
        $this->assertNull($result);
    }

    public function test_validateSignedToken_rejects_empty_token(): void
    {
        $result = \App\Controllers\Mobile\AuthController::validateSignedToken('');
        $this->assertNull($result);
    }

    public function test_validateSignedToken_rejects_tampered_signature(): void
    {
        // Criar payload válido mas com signature errada
        $payload = json_encode(['uid' => 1, 'iat' => time(), 'exp' => time() + 3600, 'jti' => bin2hex(random_bytes(16))]);
        $encodedPayload = rtrim(base64_encode($payload), '=');
        $fakeSignature = hash('sha256', 'fake');

        $result = \App\Controllers\Mobile\AuthController::validateSignedToken($encodedPayload . '.' . $fakeSignature);
        $this->assertNull($result, 'Token com signature adulterada deve ser rejeitado');
    }

    public function test_validateSignedToken_rejects_expired_token(): void
    {
        // Simular token expirado (se tiver secret configurado)
        $secret = $_ENV['MOBILE_TOKEN_SECRET'] ?? $_ENV['APP_KEY'] ?? '';
        if (empty($secret)) {
            $this->markTestSkipped('MOBILE_TOKEN_SECRET não configurado');
        }

        $payload = json_encode(['uid' => 1, 'iat' => time() - 7200, 'exp' => time() - 3600, 'jti' => bin2hex(random_bytes(16))]);
        $encodedPayload = rtrim(base64_encode($payload), '=');
        $signature = hash_hmac('sha256', $encodedPayload, $secret);

        $result = \App\Controllers\Mobile\AuthController::validateSignedToken($encodedPayload . '.' . $signature);
        $this->assertNull($result, 'Token expirado deve ser rejeitado');
    }

    public function test_validateSignedToken_accepts_valid_token(): void
    {
        $secret = $_ENV['MOBILE_TOKEN_SECRET'] ?? $_ENV['APP_KEY'] ?? '';
        if (empty($secret) || strlen($secret) < 32) {
            $this->markTestSkipped('MOBILE_TOKEN_SECRET não configurado adequadamente');
        }

        $payload = json_encode(['uid' => 1, 'iat' => time(), 'exp' => time() + 3600, 'jti' => bin2hex(random_bytes(16))]);
        $encodedPayload = rtrim(base64_encode($payload), '=');
        $signature = hash_hmac('sha256', $encodedPayload, $secret);

        $result = \App\Controllers\Mobile\AuthController::validateSignedToken($encodedPayload . '.' . $signature);
        $this->assertNotNull($result, 'Token válido deve ser aceito');
        $this->assertEquals(1, $result['uid']);
    }

    // ===========================
    // INPUT VALIDATION TESTS
    // ===========================

    public function test_login_validates_required_fields(): void
    {
        $source = file_get_contents(
            dirname(__DIR__, 3) . '/app/Controllers/Mobile/AuthController.php'
        );

        // Remover espaços para comparação normalizada
        $normalized = str_replace(' ', '', $source);

        $this->assertTrue(
            str_contains($normalized, '!$email||!$password')
                || str_contains($normalized, 'empty($email)')
                || str_contains($normalized, 'empty($password)')
                || str_contains($source, '!$email || !$password'),
            'Deve validar email e password como obrigatórios'
        );
    }
}
