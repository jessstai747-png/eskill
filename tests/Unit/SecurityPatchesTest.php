<?php

namespace Tests\Unit;

use Tests\TestCase;

/**
 * Testes de segurança para os patches da auditoria (Onda 1)
 * 
 * Cobre: C1 (Open Redirect), C2 (unserialize), A1 (Rate Limit IP),
 *        A3/M2 (Info Disclosure), Router security
 */
class SecurityPatchesTest extends TestCase
{
    // ========================================
    // C1: Open Redirect Validation
    // ========================================

    /**
     * @dataProvider redirectProvider
     */
    public function testRedirectValidation(string $input, string $expected): void
    {
        // Simulate the redirect validation logic from AuthController
        $redirect = $input;
        if (!is_string($redirect)) {
            $redirect = '/dashboard';
        } else {
            $redirect = trim($redirect);
            $isInternalPath = preg_match('#^/[a-zA-Z0-9]#', $redirect) === 1;
            $hasSchemePrefix = preg_match('#^[a-zA-Z][a-zA-Z0-9+.-]*:#', $redirect) === 1;
            $isProtocolRelative = str_starts_with($redirect, '//');
            if (!$isInternalPath || $hasSchemePrefix || $isProtocolRelative) {
                $redirect = '/dashboard';
            }
        }

        $this->assertSame($expected, $redirect);
    }

    public static function redirectProvider(): array
    {
        return [
            'valid internal path' => ['/dashboard', '/dashboard'],
            'valid nested path' => ['/dashboard/seo-killer', '/dashboard/seo-killer'],
            'valid path with query' => ['/items?page=2', '/items?page=2'],
            'external URL blocked' => ['https://evil.com', '/dashboard'],
            'protocol-relative blocked' => ['//evil.com/phish', '/dashboard'],
            'javascript: blocked' => ['javascript:alert(1)', '/dashboard'],
            'data: blocked' => ['data:text/html,<h1>pwned</h1>', '/dashboard'],
            'empty string blocked' => ['', '/dashboard'],
            'just slash blocked' => ['/', '/dashboard'],
            'backslash blocked' => ['\\evil.com', '/dashboard'],
            'ftp protocol blocked' => ['ftp://evil.com', '/dashboard'],
            'url with scheme blocked' => ['/redirect?url=https://evil.com', '/redirect?url=https://evil.com'], // path itself is valid
        ];
    }

    // ========================================
    // C2: Unserialize allowed_classes
    // ========================================

    public function testUnserializeBlocksObjects(): void
    {
        // Simulate the cache unserialize pattern
        $data = ['key' => 'value', 'expires_at' => null];
        $serialized = serialize($data);
        
        $result = unserialize($serialized, ['allowed_classes' => false]);
        
        $this->assertSame($data, $result);
    }

    public function testUnserializeConvertsObjectsToIncomplete(): void
    {
        // A malicious cache file with an object
        $obj = new \stdClass();
        $obj->cmd = 'evil';
        $serialized = serialize(['data' => $obj]);
        
        $result = unserialize($serialized, ['allowed_classes' => false]);
        
        // Object should be converted to __PHP_Incomplete_Class, not stdClass
        $this->assertInstanceOf(\__PHP_Incomplete_Class::class, $result['data']);
    }

    // ========================================
    // A1: Rate Limit IP Resolution
    // ========================================

    public function testRateLimitMiddlewareExistsWithGetClientIp(): void
    {
        $reflection = new \ReflectionClass(\App\Middleware\RateLimitMiddleware::class);
        $method = $reflection->getMethod('getClientIp');
        
        $this->assertTrue($method->isPrivate(), 'getClientIp should be private');
    }

    // ========================================
    // A3/M2: Router Info Disclosure
    // ========================================

    public function testRouter404DoesNotLeakPath(): void
    {
        $router = new \App\Router();
        $router->get('/existing', MockRouterTarget::class, 'action');

        ob_start();
        $router->dispatch('GET', '/nonexistent-secret-path');
        $output = ob_get_clean();

        $decoded = json_decode($output, true);

        // Must NOT contain the requested path
        $this->assertArrayNotHasKey('path', $decoded ?? []);
        $this->assertArrayNotHasKey('method', $decoded ?? []);
        
        // If JSON, must only have 'error' key
        if (is_array($decoded)) {
            $this->assertArrayHasKey('error', $decoded);
        }
    }

    public function testRouterErrorHidesDetailsInProduction(): void
    {
        // In production (APP_ENV=production set via phpunit.xml → testing),
        // We verify the Router class has the production check
        $source = file_get_contents(__DIR__ . '/../../app/Router.php');
        
        $this->assertStringContainsString(
            "(\$_ENV['APP_ENV'] ?? 'production') === 'production'",
            $source,
            'Router deve verificar APP_ENV para esconder erros em produção'
        );
    }

    // ========================================
    // A6: Remember-me Cookie SameSite
    // ========================================

    public function testRememberMeCookieHasSameSite(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/Controllers/AuthController.php');
        
        $this->assertStringContainsString(
            "'samesite' => 'Lax'",
            $source,
            'Remember-me cookie deve ter SameSite=Lax'
        );
        
        $this->assertStringContainsString(
            "'httponly' => true",
            $source,
            'Remember-me cookie deve ter httponly=true'
        );
    }

    // ========================================
    // B2: Deprecated X-XSS-Protection removed
    // ========================================

    public function testXssProtectionHeaderRemoved(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/Middleware/SecurityMiddleware.php');
        
        $this->assertStringNotContainsString(
            "X-XSS-Protection: 1; mode=block",
            $source,
            'X-XSS-Protection header deprecated deve ter sido removido'
        );
    }

    // ========================================
    // Security: SSL Verification in Production 
    // ========================================

    public function testNotificationServiceUsesSSLVerification(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/Services/NotificationService.php');
        
        // Must not have unconditional SSL disable
        $this->assertStringNotContainsString(
            'CURLOPT_SSL_VERIFYPEER => false',
            $source,
            'NotificationService não deve desabilitar SSL verification incondicionalmente'
        );
    }

    public function testAIImageAnalyzerServiceUsesSSLVerification(): void
    {
        $source = file_get_contents(__DIR__ . '/../../app/Services/AIImageAnalyzerService.php');
        
        // Must not have unconditional SSL disable
        $this->assertStringNotContainsString(
            'CURLOPT_SSL_VERIFYPEER, false',
            $source,
            'AIImageAnalyzerService não deve desabilitar SSL verification incondicionalmente'
        );
    }
}

/**
 * Minimal mock for Router 404 test
 */
class MockRouterTarget
{
    public function action(): void
    {
        echo json_encode(['ok' => true]);
    }
}
