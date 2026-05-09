<?php
declare(strict_types=1);

namespace Tests\Unit\Security;

use App\Controllers\SecurityController;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * @covers \App\Controllers\SecurityController
 */
class SecurityControllerAccessTest extends TestCase
{
    private ReflectionClass $reflection;
    private SecurityController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->reflection = new ReflectionClass(SecurityController::class);
        /** @var SecurityController $controller */
        $controller = $this->reflection->newInstanceWithoutConstructor();
        $this->controller = $controller;
        $_SESSION = [];
        unset($_SERVER['API_TOKEN_DATA']);
    }

    protected function tearDown(): void
    {
        $_SESSION = [];
        unset($_SERVER['API_TOKEN_DATA']);
        parent::tearDown();
    }

    public function testAdminSessionCanAccessSecurityScope(): void
    {
        $_SESSION['is_admin'] = true;

        $this->assertTrue($this->invokeCanAccessSecurityScope('security:manage'));
    }

    public function testBearerTokenWithMatchingScopeCanAccess(): void
    {
        $_SERVER['API_TOKEN_DATA'] = [
            'user_id' => 5,
            'scopes' => ['security:read'],
        ];

        $this->assertTrue($this->invokeCanAccessSecurityScope('security:read'));
        $this->assertFalse($this->invokeCanAccessSecurityScope('security:manage'));
    }

    public function testBearerTokenWithAdminScopeCanAccessEverything(): void
    {
        $_SERVER['API_TOKEN_DATA'] = [
            'user_id' => 9,
            'scopes' => ['admin'],
        ];

        $this->assertTrue($this->invokeCanAccessSecurityScope('security:read'));
        $this->assertTrue($this->invokeCanAccessSecurityScope('security:manage'));
    }

    public function testUnauthenticatedActorIsDenied(): void
    {
        $this->assertFalse($this->invokeCanAccessSecurityScope('security:read'));
    }

    private function invokeCanAccessSecurityScope(string $requiredScope): bool
    {
        $method = $this->reflection->getMethod('canAccessSecurityScope');
        $method->setAccessible(true);

        return (bool) $method->invoke($this->controller, $requiredScope);
    }
}
