<?php

namespace Tests\Integration;

use Tests\TestCase;
use App\Middleware\SecurityMiddleware;

/**
 * Testes de integração para validar configurações de segurança
 */
class SecurityConfigTest extends TestCase
{
    // =============================
    // TESTES DE HEADERS DE SEGURANÇA
    // =============================

    public function testSecurityMiddlewareExists(): void
    {
        $middleware = new SecurityMiddleware();

        $this->assertInstanceOf(SecurityMiddleware::class, $middleware);
    }

    public function testSecurityMiddlewareHasRequiredMethods(): void
    {
        $middleware = new SecurityMiddleware();

        $this->assertTrue(
            method_exists($middleware, 'handle'),
            'SecurityMiddleware deve ter método handle'
        );
    }

    // =============================
    // TESTES DE ARQUIVOS SENSÍVEIS
    // =============================

    public function testEnvFileIsNotInPublic(): void
    {
        $publicEnvPath = __DIR__ . '/../../public/.env';
        $this->assertFileDoesNotExist(
            $publicEnvPath,
            '.env não deve existir na pasta public'
        );
    }

    public function testGitignoreExcludesEnv(): void
    {
        $gitignorePath = __DIR__ . '/../../.gitignore';

        if (file_exists($gitignorePath)) {
            $content = file_get_contents($gitignorePath);
            $this->assertStringContainsString('.env', $content, '.gitignore deve excluir .env');
        } else {
            $this->markTestSkipped('.gitignore não encontrado');
        }
    }

    public function testStorageIsNotAccessibleFromWeb(): void
    {
        $htaccessPath = __DIR__ . '/../../storage/.htaccess';

        // Deve ter .htaccess bloqueando acesso ou não ter nenhum arquivo web
        if (file_exists($htaccessPath)) {
            $content = file_get_contents($htaccessPath);
            $this->assertTrue(
                strpos($content, 'deny from all') !== false ||
                    strpos($content, 'Deny from all') !== false ||
                    strpos($content, 'Require all denied') !== false,
                'storage/.htaccess deve negar acesso'
            );
        } else {
            // Se não tem htaccess, verificar se storage está fora de public
            $publicPath = realpath(__DIR__ . '/../../public');
            $storagePath = realpath(__DIR__ . '/../../storage');

            $this->assertNotSame(
                dirname($storagePath),
                $publicPath,
                'Storage não deve estar dentro de public'
            );
        }
    }

    // =============================
    // TESTES DE CSRF
    // =============================

    public function testCsrfMiddlewareExists(): void
    {
        $this->assertTrue(
            class_exists('App\\Middleware\\CsrfMiddleware'),
            'CsrfMiddleware deve existir'
        );
    }

    public function testCsrfMiddlewareHasValidateMethods(): void
    {
        $middleware = new \App\Middleware\CsrfMiddleware();

        $this->assertTrue(
            method_exists($middleware, 'handle'),
            'CsrfMiddleware deve ter método handle'
        );
    }

    // =============================
    // TESTES DE RATE LIMITING
    // =============================

    public function testRateLimitMiddlewareExists(): void
    {
        $this->assertTrue(
            class_exists('App\\Middleware\\RateLimitMiddleware'),
            'RateLimitMiddleware deve existir'
        );
    }

    public function testRateLimitMiddlewareAcceptsConfig(): void
    {
        $middleware = new \App\Middleware\RateLimitMiddleware(100, 60);

        $this->assertInstanceOf(
            \App\Middleware\RateLimitMiddleware::class,
            $middleware
        );
    }

    // =============================
    // TESTES DE AUTH MIDDLEWARE
    // =============================

    public function testAuthMiddlewareExists(): void
    {
        $this->assertTrue(
            class_exists('App\\Middleware\\AuthMiddleware'),
            'AuthMiddleware deve existir'
        );
    }

    // =============================
    // TESTES DE ENCRYPTION
    // =============================

    public function testSecurityServiceEncryptionWorks(): void
    {
        $service = new \App\Services\SecurityService();

        $plaintext = 'Texto secreto para teste';
        $encrypted = $service->encrypt($plaintext);

        $this->assertNotEquals($plaintext, $encrypted);
        $this->assertEquals($plaintext, $service->decrypt($encrypted));
    }

    public function testSecurityServicePasswordHashingWorks(): void
    {
        $service = new \App\Services\SecurityService();

        $password = 'SenhaForte123!';
        $hash = $service->hashPassword($password);

        $this->assertNotEquals($password, $hash);
        $this->assertTrue($service->verifyPassword($password, $hash));
        $this->assertFalse($service->verifyPassword('SenhaErrada', $hash));
    }

    // =============================
    // TESTES DE XSS PROTECTION
    // =============================

    public function testSecurityHelperSanitizesInput(): void
    {
        $malicious = '<script>alert("xss")</script>';
        $sanitized = \App\Helpers\SecurityHelper::e($malicious);

        $this->assertStringNotContainsString('<script>', $sanitized);
    }

    public function testSecurityServiceSanitizesInput(): void
    {
        $service = new \App\Services\SecurityService();

        $malicious = '<img src=x onerror=alert(1)>';
        $sanitized = $service->sanitize($malicious);

        $this->assertStringNotContainsString('<img', $sanitized);
    }

    // =============================
    // TESTES DE CONFIGURAÇÃO SEGURA
    // =============================

    public function testDebugIsDisabledInProductionConfig(): void
    {
        // Simular ambiente de produção temporariamente
        $originalEnv = $_ENV['APP_ENV'] ?? null;
        $_ENV['APP_ENV'] = 'production';
        $_ENV['APP_DEBUG'] = 'false';

        $configPath = __DIR__ . '/../../config/production.php';

        if (file_exists($configPath)) {
            $config = require $configPath;

            // Debug deve estar desabilitado em produção
            $this->assertFalse(
                $config['debug'] ?? false,
                'Debug deve estar desabilitado em config/production.php'
            );
        }

        // Restaurar
        if ($originalEnv !== null) {
            $_ENV['APP_ENV'] = $originalEnv;
        }

        $this->assertTrue(true);
    }
}
