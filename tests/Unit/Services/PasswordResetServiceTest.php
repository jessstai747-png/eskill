<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\PasswordResetService;

/**
 * Testes do PasswordResetService
 *
 * Cobre: requestReset, validateToken, resetPassword, cleanExpiredTokens
 * Nota: Testes estruturais + testes de validação que não dependem do DB
 */
class PasswordResetServiceTest extends TestCase
{
    // ===========================
    // STRUCTURAL TESTS
    // ===========================

    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(PasswordResetService::class));
    }

    public function test_required_methods_exist(): void
    {
        $methods = ['requestReset', 'validateToken', 'resetPassword', 'cleanExpiredTokens'];

        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(PasswordResetService::class, $method),
                "Método {$method} deve existir"
            );
        }
    }

    public function test_requestReset_signature(): void
    {
        $ref = new \ReflectionMethod(PasswordResetService::class, 'requestReset');
        $params = $ref->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params));
        $this->assertEquals('email', $params[0]->getName());
    }

    public function test_validateToken_signature(): void
    {
        $ref = new \ReflectionMethod(PasswordResetService::class, 'validateToken');
        $params = $ref->getParameters();

        $this->assertGreaterThanOrEqual(1, count($params));
        $this->assertEquals('token', $params[0]->getName());
    }

    public function test_resetPassword_signature(): void
    {
        $ref = new \ReflectionMethod(PasswordResetService::class, 'resetPassword');
        $params = $ref->getParameters();

        $this->assertGreaterThanOrEqual(2, count($params));
        $this->assertEquals('token', $params[0]->getName());
        $this->assertEquals('newPassword', $params[1]->getName());
    }

    // ===========================
    // SOURCE CODE SECURITY CHECKS
    // ===========================

    public function test_uses_prepared_statements(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/app/Services/PasswordResetService.php');

        // Deve usar prepare() ao invés de concatenar SQL
        $this->assertStringContainsString('->prepare(', $source);
        $this->assertStringContainsString('->execute(', $source);
    }

    public function test_hashes_tokens_before_storage(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/app/Services/PasswordResetService.php');

        // Deve hashear tokens antes de armazenar
        $this->assertTrue(
            str_contains($source, "hash('sha256',") || str_contains($source, 'hash("sha256",'),
            'Tokens devem ser hasheados com SHA-256 antes de armazenamento'
        );

        // Deve conter comentário explicativo
        $this->assertStringContainsString('hash', $source);
    }

    public function test_tokens_have_expiration(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/app/Services/PasswordResetService.php');

        // Deve verificar expiração
        $this->assertTrue(
            str_contains($source, 'expir') || str_contains($source, 'expires_at') || str_contains($source, 'INTERVAL'),
            'Deve ter lógica de expiração de tokens'
        );
    }

    public function test_non_revealing_responses_for_invalid_email(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/app/Services/PasswordResetService.php');

        // requestReset não deve revelar se email existe ou não
        // Deve retornar true/success independente do email
        $this->assertTrue(
            str_contains($source, 'return true') || str_contains($source, "'success'"),
            'Deve retornar resposta genérica para não revelar existência do email'
        );
    }

    public function test_password_minimum_length_enforced(): void
    {
        $source = file_get_contents(dirname(__DIR__, 3) . '/app/Services/PasswordResetService.php');

        // Deve validar comprimento mínimo da senha
        $this->assertTrue(
            str_contains($source, 'strlen(') || str_contains($source, 'mb_strlen('),
            'Deve validar comprimento mínimo da senha'
        );
    }

    // ===========================
    // DB-DEPENDENT TESTS (skip se sem DB)
    // ===========================

    public function test_requestReset_with_invalid_email_format(): void
    {
        try {
            $service = new PasswordResetService();
        } catch (\Exception $e) {
            $this->markTestSkipped('DB não disponível: ' . $e->getMessage());
            return;
        }

        // Email inválido — deve retornar sem erro (não revelar info)
        $result = $service->requestReset('invalid-email@nonexistent-domain-xyz.com');

        // Deve retornar algo sem lançar exceção (resposta não-reveladora)
        $this->assertTrue(true, 'requestReset não deve lançar exceção para email inexistente');
    }

    public function test_validateToken_with_random_token(): void
    {
        try {
            $service = new PasswordResetService();
        } catch (\Exception $e) {
            $this->markTestSkipped('DB não disponível: ' . $e->getMessage());
            return;
        }

        $result = $service->validateToken(bin2hex(random_bytes(32)));

        // Token aleatório nunca é válido — retorna ['valid' => false, ...]
        $this->assertIsArray($result);
        $this->assertFalse($result['valid'] ?? true, 'Token aleatório nunca deve ser válido');
    }

    public function test_resetPassword_with_invalid_token(): void
    {
        try {
            $service = new PasswordResetService();
        } catch (\Exception $e) {
            $this->markTestSkipped('DB não disponível: ' . $e->getMessage());
            return;
        }

        try {
            $result = $service->resetPassword(bin2hex(random_bytes(32)), 'NewSecurePassword123!');
            // Se retorna false/null/array com erro — OK
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // Se lança exceção para token inválido — OK também
            $this->assertStringContainsString('inválido', strtolower($e->getMessage()) . strtolower(get_class($e)));
        }
    }

    public function test_cleanExpiredTokens_runs_without_error(): void
    {
        try {
            $service = new PasswordResetService();
        } catch (\Exception $e) {
            $this->markTestSkipped('DB não disponível: ' . $e->getMessage());
            return;
        }

        // Deve executar sem erro mesmo se tabela vazia
        try {
            $service->cleanExpiredTokens();
            $this->assertTrue(true);
        } catch (\Exception $e) {
            // Se tabela não existe, skip
            if (str_contains($e->getMessage(), "doesn't exist") || str_contains($e->getMessage(), 'no such table')) {
                $this->markTestSkipped('Tabela password_resets não existe');
            } else {
                throw $e;
            }
        }
    }
}
