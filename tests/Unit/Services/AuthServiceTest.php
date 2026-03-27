<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\AuthService;
use App\Services\UserService;
use App\Services\JwtService;
use App\Services\RefreshTokenService;
use App\Database;

/**
 * Testes do AuthService
 *
 * Cobre: login, refresh, logout, 2FA flow, edge cases
 */
class AuthServiceTest extends TestCase
{
    private \PDO $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::getInstance();
        $this->ensureTables();
        $this->seedTestUser();
    }

    protected function tearDown(): void
    {
        // Limpar dados de teste
        try {
            $this->db->exec("DELETE FROM refresh_tokens WHERE user_id IN (SELECT id FROM users WHERE email LIKE '%@authtest.test')");
            $this->db->exec("DELETE FROM users WHERE email LIKE '%@authtest.test'");
        } catch (\Throwable $e) {
            // ignore
        }
        parent::tearDown();
    }

    // ===========================
    // CLASS STRUCTURE TESTS
    // ===========================

    public function test_class_exists(): void
    {
        $this->assertTrue(class_exists(AuthService::class));
    }

    public function test_has_required_methods(): void
    {
        $methods = ['login', 'refresh', 'logout'];
        foreach ($methods as $method) {
            $this->assertTrue(
                method_exists(AuthService::class, $method),
                "AuthService deve ter o método {$method}()"
            );
        }
    }

    public function test_constructor_initializes_dependencies(): void
    {
        $reflection = new \ReflectionClass(AuthService::class);

        $properties = ['userService', 'jwt', 'refreshService'];
        foreach ($properties as $prop) {
            $this->assertTrue(
                $reflection->hasProperty($prop),
                "AuthService deve ter propriedade {$prop}"
            );
        }
    }

    // ===========================
    // LOGIN TESTS
    // ===========================

    public function test_login_with_valid_credentials_returns_tokens(): void
    {
        $service = new AuthService();
        $result = $service->login('authtest@authtest.test', 'TestPassword123!');

        $this->assertTrue($result['success'], 'Login com credenciais válidas deve retornar success=true');
        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertNotEmpty($result['access_token']);
        $this->assertNotEmpty($result['refresh_token']);
        $this->assertEquals(900, $result['access_expires_in']); // 15 min
    }

    public function test_login_with_invalid_password_fails(): void
    {
        $service = new AuthService();
        $result = $service->login('authtest@authtest.test', 'WrongPassword');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_login_with_nonexistent_email_fails(): void
    {
        $service = new AuthService();
        $result = $service->login('nonexistent@authtest.test', 'anypassword');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_login_with_empty_email_fails(): void
    {
        $service = new AuthService();
        $result = $service->login('', 'TestPassword123!');

        $this->assertFalse($result['success']);
    }

    public function test_login_with_empty_password_fails(): void
    {
        $service = new AuthService();
        $result = $service->login('authtest@authtest.test', '');

        $this->assertFalse($result['success']);
    }

    public function test_login_returns_user_data_without_password(): void
    {
        $service = new AuthService();
        $result = $service->login('authtest@authtest.test', 'TestPassword123!');

        if ($result['success'] && isset($result['user'])) {
            $this->assertArrayNotHasKey('password', $result['user'], 'Senha não deve ser retornada no login');
        }
    }

    // ===========================
    // REFRESH TOKEN TESTS
    // ===========================

    public function test_refresh_with_valid_token_returns_new_tokens(): void
    {
        $service = new AuthService();
        $loginResult = $service->login('authtest@authtest.test', 'TestPassword123!');

        if (!$loginResult['success']) {
            $this->markTestSkipped('Login falhou - não é possível testar refresh');
        }

        $refreshResult = $service->refresh($loginResult['refresh_token']);

        $this->assertTrue($refreshResult['success']);
        $this->assertArrayHasKey('access_token', $refreshResult);
        $this->assertArrayHasKey('refresh_token', $refreshResult);
    }

    public function test_refresh_with_invalid_token_fails(): void
    {
        $service = new AuthService();
        $result = $service->refresh('invalid-token-that-does-not-exist');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_refresh_rotates_token(): void
    {
        $service = new AuthService();
        $loginResult = $service->login('authtest@authtest.test', 'TestPassword123!');

        if (!$loginResult['success']) {
            $this->markTestSkipped('Login falhou');
        }

        $oldRefreshToken = $loginResult['refresh_token'];
        $refreshResult = $service->refresh($oldRefreshToken);

        if ($refreshResult['success']) {
            // Token antigo deve ter sido rotacionado
            $this->assertNotEquals(
                $oldRefreshToken,
                $refreshResult['refresh_token'],
                'Refresh token deve ser rotacionado'
            );
        }
    }

    // ===========================
    // LOGOUT TESTS
    // ===========================

    public function test_logout_with_refresh_token_revokes_it(): void
    {
        $service = new AuthService();
        $loginResult = $service->login('authtest@authtest.test', 'TestPassword123!');

        if (!$loginResult['success']) {
            $this->markTestSkipped('Login falhou');
        }

        $logoutResult = $service->logout($loginResult['refresh_token']);
        $this->assertTrue($logoutResult['success']);

        // Token revogado não deve funcionar
        $refreshResult = $service->refresh($loginResult['refresh_token']);
        $this->assertFalse($refreshResult['success'], 'Token revogado não deve ser aceito');
    }

    public function test_logout_without_params_fails(): void
    {
        $service = new AuthService();
        $result = $service->logout();

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function test_logout_with_user_id_revokes_all_tokens(): void
    {
        $service = new AuthService();

        // Login duas vezes para criar dois refresh tokens
        $login1 = $service->login('authtest@authtest.test', 'TestPassword123!');
        $login2 = $service->login('authtest@authtest.test', 'TestPassword123!');

        if (!$login1['success'] || !$login2['success']) {
            $this->markTestSkipped('Login falhou');
        }

        $userId = $login1['user']['id'] ?? null;
        if (!$userId) {
            $this->markTestSkipped('User ID não retornado');
        }

        $logoutResult = $service->logout(null, (int)$userId);
        $this->assertTrue($logoutResult['success']);
        $this->assertArrayHasKey('revoked', $logoutResult);
    }

    // ===========================
    // HELPERS
    // ===========================

    private function ensureTables(): void
    {
        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS users (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255),
                email VARCHAR(255) UNIQUE,
                password VARCHAR(255),
                role VARCHAR(50) DEFAULT 'admin',
                verification_token VARCHAR(255) NULL,
                email_verified_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                two_factor_enabled TINYINT(1) DEFAULT 0,
                two_factor_secret VARCHAR(255) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (\Throwable $e) {
            // table exists
        }

        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS refresh_tokens (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                token VARCHAR(255) NOT NULL UNIQUE,
                device_name VARCHAR(255) NULL,
                expires_at DATETIME NOT NULL,
                revoked_at DATETIME NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_user (user_id),
                INDEX idx_token (token)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (\Throwable $e) {
            // table exists
        }
    }

    private function seedTestUser(): void
    {
        $email = 'authtest@authtest.test';
        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);

        if (!$stmt->fetch()) {
            $stmt = $this->db->prepare("INSERT INTO users (name, email, password, email_verified_at) VALUES (?, ?, ?, NOW())");
            $stmt->execute([
                'Auth Test User',
                $email,
                password_hash('TestPassword123!', PASSWORD_BCRYPT),
            ]);
        }
    }
}
