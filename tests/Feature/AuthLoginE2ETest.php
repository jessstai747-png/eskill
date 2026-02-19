<?php

declare(strict_types=1);

namespace Tests\Feature;

use PHPUnit\Framework\TestCase;
use App\Services\AuthService;
use App\Database;

/**
 * Testes E2E de Login/Logout
 *
 * Cobre: fluxo completo de autenticação com banco real
 *
 * @covers \App\Services\AuthService
 * @covers \App\Services\UserService
 * @covers \App\Services\JwtService
 * @covers \App\Services\RefreshTokenService
 */
class AuthLoginE2ETest extends TestCase
{
    private \PDO $db;
    private string $testEmail;
    private string $testPassword = 'TestPassword123!';
    private ?int $testUserId = null;

    protected function setUp(): void
    {
        parent::setUp();

        $this->db = Database::getInstance();
        $this->testEmail = 'e2e-auth-' . bin2hex(random_bytes(4)) . '@test.local';
        $this->createTestUser();
    }

    protected function tearDown(): void
    {
        $this->cleanupTestData();
        parent::tearDown();
    }

    private function createTestUser(): void
    {
        $passwordHash = password_hash($this->testPassword, PASSWORD_ARGON2ID);

        $stmt = $this->db->prepare("
            INSERT INTO users (name, email, password, status, created_at, updated_at)
            VALUES (:name, :email, :password, 'active', NOW(), NOW())
        ");

        $stmt->execute([
            'name' => 'E2E Auth Test User',
            'email' => $this->testEmail,
            'password' => $passwordHash,
        ]);

        $this->testUserId = (int)$this->db->lastInsertId();
    }

    private function cleanupTestData(): void
    {
        try {
            if ($this->testUserId) {
                $this->db->prepare('DELETE FROM refresh_tokens WHERE user_id = :id')
                    ->execute(['id' => $this->testUserId]);
                $this->db->prepare('DELETE FROM users WHERE id = :id')
                    ->execute(['id' => $this->testUserId]);
            }
        } catch (\Throwable $e) {
            // ignore cleanup errors
        }
    }

    // ===========================
    // LOGIN E2E TESTS
    // ===========================

    public function testDeveRetornarTokensQuandoLoginComCredenciaisValidas(): void
    {
        $authService = new AuthService();

        $result = $authService->login($this->testEmail, $this->testPassword);

        $this->assertTrue($result['success'], 'Login deve retornar success=true');
        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertArrayHasKey('user', $result);
        $this->assertNotEmpty($result['access_token']);
        $this->assertNotEmpty($result['refresh_token']);
    }

    public function testDeveFalharQuandoLoginComSenhaErrada(): void
    {
        $authService = new AuthService();

        $result = $authService->login($this->testEmail, 'SenhaErrada123!');

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testDeveFalharQuandoLoginComEmailInexistente(): void
    {
        $authService = new AuthService();

        $result = $authService->login('usuario-inexistente@test.local', $this->testPassword);

        $this->assertFalse($result['success']);
    }

    // ===========================
    // REFRESH TOKEN E2E TESTS
    // ===========================

    public function testDeveRenovarAccessTokenComRefreshTokenValido(): void
    {
        $authService = new AuthService();

        // Login para obter tokens
        $loginResult = $authService->login($this->testEmail, $this->testPassword);
        $this->assertTrue($loginResult['success']);

        $refreshToken = $loginResult['refresh_token'];

        // Refresh
        $refreshResult = $authService->refresh($refreshToken);

        $this->assertTrue($refreshResult['success'], 'Refresh deve retornar success=true');
        $this->assertArrayHasKey('access_token', $refreshResult);
        $this->assertArrayHasKey('refresh_token', $refreshResult);
        $this->assertNotEmpty($refreshResult['access_token']);
    }

    public function testDeveFalharRefreshComTokenInvalido(): void
    {
        $authService = new AuthService();

        $result = $authService->refresh('token-invalido-' . bin2hex(random_bytes(16)));

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('message', $result);
    }

    public function testDeveRotacionarRefreshTokenAposUso(): void
    {
        $authService = new AuthService();

        // Login
        $loginResult = $authService->login($this->testEmail, $this->testPassword);
        $firstRefreshToken = $loginResult['refresh_token'];

        // Primeiro refresh
        $refreshResult1 = $authService->refresh($firstRefreshToken);
        $this->assertTrue($refreshResult1['success']);
        $secondRefreshToken = $refreshResult1['refresh_token'];

        // Token antigo deve ser diferente do novo (rotação)
        $this->assertNotEquals($firstRefreshToken, $secondRefreshToken);

        // Token antigo não deve funcionar mais
        $refreshResult2 = $authService->refresh($firstRefreshToken);
        $this->assertFalse($refreshResult2['success'], 'Token antigo não deve funcionar após rotação');
    }

    // ===========================
    // LOGOUT E2E TESTS
    // ===========================

    public function testDeveRevogarRefreshTokenNoLogout(): void
    {
        $authService = new AuthService();

        // Login
        $loginResult = $authService->login($this->testEmail, $this->testPassword);
        $refreshToken = $loginResult['refresh_token'];

        // Logout
        $logoutResult = $authService->logout($refreshToken);
        $this->assertTrue($logoutResult['success']);

        // Token não deve funcionar mais
        $refreshResult = $authService->refresh($refreshToken);
        $this->assertFalse($refreshResult['success']);
    }

    public function testDeveRevogarTodosTokensDoUsuario(): void
    {
        $authService = new AuthService();

        // Login múltiplos dispositivos
        $login1 = $authService->login($this->testEmail, $this->testPassword, 'device1');
        $login2 = $authService->login($this->testEmail, $this->testPassword, 'device2');

        // Logout revogando todos
        $logoutResult = $authService->logout(null, $this->testUserId);
        $this->assertTrue($logoutResult['success']);
        $this->assertGreaterThanOrEqual(2, $logoutResult['revoked'] ?? 0);

        // Nenhum token deve funcionar
        $refresh1 = $authService->refresh($login1['refresh_token']);
        $refresh2 = $authService->refresh($login2['refresh_token']);

        $this->assertFalse($refresh1['success']);
        $this->assertFalse($refresh2['success']);
    }
}
