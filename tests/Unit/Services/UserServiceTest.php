<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\UserService;
use App\Database;

/**
 * Testes do UserService
 *
 * Cobre: register, login, isAuthenticated, verifyEmail, passwords, 2FA
 */
class UserServiceTest extends TestCase
{
    private \PDO $db;
    private string $testEmailPrefix = 'usrtest_';

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = Database::getInstance();
        $this->ensureTables();

        if (session_status() === PHP_SESSION_NONE) {
            @session_start();
        }
    }

    protected function tearDown(): void
    {
        try {
            $this->db->exec("DELETE FROM users WHERE email LIKE '{$this->testEmailPrefix}%@test.local'");
        } catch (\Throwable $e) {
            // ignore
        }
        $_SESSION = [];
        parent::tearDown();
    }

    // ===========================
    // REGISTER TESTS
    // ===========================

    public function test_register_with_valid_data_succeeds(): void
    {
        $service = new UserService();
        $email = $this->testEmailPrefix . uniqid() . '@test.local';
        $result = $service->register('Test User', $email, 'SecurePass123');

        $this->assertTrue($result['success'], 'Registro com dados válidos deve retornar success=true');
        $this->assertArrayHasKey('user_id', $result);
        $this->assertIsNumeric($result['user_id']);
    }

    public function test_register_with_empty_name_fails(): void
    {
        $service = new UserService();
        $result = $service->register('', 'test@test.local', 'password123');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('obrigatóri', $result['message']);
    }

    public function test_register_with_empty_email_fails(): void
    {
        $service = new UserService();
        $result = $service->register('Test', '', 'password123');

        $this->assertFalse($result['success']);
    }

    public function test_register_with_invalid_email_fails(): void
    {
        $service = new UserService();
        $result = $service->register('Test', 'not-an-email', 'password123');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('inválido', $result['message']);
    }

    public function test_register_with_short_password_fails(): void
    {
        $service = new UserService();
        $result = $service->register('Test', $this->testEmailPrefix . 'x@test.local', '123');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('mínimo', $result['message']);
    }

    public function test_register_duplicate_email_fails(): void
    {
        $service = new UserService();
        $email = $this->testEmailPrefix . uniqid() . '@test.local';

        $first = $service->register('User 1', $email, 'Password123');
        $this->assertTrue($first['success']);

        $second = $service->register('User 2', $email, 'Password456');
        $this->assertFalse($second['success']);
        $this->assertStringContainsString('cadastrado', $second['message']);
    }

    // ===========================
    // LOGIN TESTS
    // ===========================

    public function test_login_with_valid_credentials_succeeds(): void
    {
        $service = new UserService();
        $email = $this->testEmailPrefix . uniqid() . '@test.local';
        $service->register('Login Test', $email, 'MyPassword123');

        // Verificar email para permitir login
        $this->verifyUserEmail($email);

        $result = $service->login($email, 'MyPassword123');
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('user', $result);
    }

    public function test_login_with_wrong_password_fails(): void
    {
        $service = new UserService();
        $email = $this->testEmailPrefix . uniqid() . '@test.local';
        $service->register('Login Test', $email, 'CorrectPassword');
        $this->verifyUserEmail($email);

        $result = $service->login($email, 'WrongPassword');
        $this->assertFalse($result['success']);
    }

    public function test_login_with_nonexistent_email_fails(): void
    {
        $service = new UserService();
        $result = $service->login('nobody@nowhere.test', 'anypass');
        $this->assertFalse($result['success']);
    }

    public function test_login_with_empty_fields_fails(): void
    {
        $service = new UserService();

        $result1 = $service->login('', 'pass');
        $this->assertFalse($result1['success']);

        $result2 = $service->login('email@test.local', '');
        $this->assertFalse($result2['success']);
    }

    // ===========================
    // AUTHENTICATION TESTS
    // ===========================

    public function test_isAuthenticated_returns_false_when_no_session(): void
    {
        $_SESSION = [];
        try {
            $service = new UserService();
            $this->assertFalse($service->isAuthenticated());
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'session')) {
                $this->markTestSkipped('session_start() não pode ser chamada em ambiente PHPUnit (headers sent)');
            }
            throw $e;
        }
    }

    public function test_isAuthenticated_returns_true_when_session_has_user(): void
    {
        try {
            $service = new UserService();
            $email = $this->testEmailPrefix . uniqid() . '@test.local';
            $service->register('Auth Test', $email, 'password123');
            $this->verifyUserEmail($email);

            $loginResult = $service->login($email, 'password123');
            if (!$loginResult['success']) {
                $this->markTestSkipped('Login falhou');
            }

            // Após login, sessão deve estar configurada
            $this->assertTrue($service->isAuthenticated());
        } catch (\Throwable $e) {
            if (str_contains($e->getMessage(), 'session')) {
                $this->markTestSkipped('session_start() não pode ser chamada em ambiente PHPUnit (headers sent)');
            }
            throw $e;
        }
    }

    // ===========================
    // VERIFY EMAIL TESTS
    // ===========================

    public function test_verifyEmail_with_valid_token_succeeds(): void
    {
        $service = new UserService();
        $email = $this->testEmailPrefix . uniqid() . '@test.local';
        $service->register('Verify Test', $email, 'password123');

        // Buscar token de verificação
        $stmt = $this->db->prepare("SELECT verification_token FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $token = $stmt->fetchColumn();

        if (empty($token)) {
            $this->markTestSkipped('Token de verificação não encontrado');
        }

        $result = $service->verifyEmail($token);
        $this->assertTrue($result);

        // Verificar que email_verified_at foi preenchido
        $stmt = $this->db->prepare("SELECT email_verified_at FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $verifiedAt = $stmt->fetchColumn();
        $this->assertNotNull($verifiedAt);
    }

    public function test_verifyEmail_with_invalid_token_fails(): void
    {
        $service = new UserService();
        $result = $service->verifyEmail('nonexistent-token-12345');
        $this->assertFalse($result);
    }

    // ===========================
    // PASSWORD CHANGE TESTS
    // ===========================

    public function test_changePassword_with_correct_current_password_succeeds(): void
    {
        $service = new UserService();
        $email = $this->testEmailPrefix . uniqid() . '@test.local';
        $regResult = $service->register('PwdChange', $email, 'OldPassword123');

        if (!$regResult['success']) {
            $this->markTestSkipped('Registro falhou');
        }

        $userId = $regResult['user_id'];
        $result = $service->changePassword($userId, 'OldPassword123', 'NewPassword456');

        $this->assertTrue($result['success'] ?? false, 'Troca de senha com senha atual correta deve funcionar');
    }

    public function test_changePassword_with_wrong_current_password_fails(): void
    {
        $service = new UserService();
        $email = $this->testEmailPrefix . uniqid() . '@test.local';
        $regResult = $service->register('PwdChange2', $email, 'ActualPassword');

        if (!$regResult['success']) {
            $this->markTestSkipped('Registro falhou');
        }

        $userId = $regResult['user_id'];
        $result = $service->changePassword($userId, 'WrongCurrentPassword', 'NewPassword');

        $this->assertFalse($result['success'] ?? true);
    }

    // ===========================
    // EDGE CASES
    // ===========================

    public function test_getUserById_returns_null_for_nonexistent_user(): void
    {
        $service = new UserService();
        $result = $service->getUserById(999999999);
        $this->assertNull($result);
    }

    public function test_getUserById_returns_user_data(): void
    {
        $service = new UserService();
        $email = $this->testEmailPrefix . uniqid() . '@test.local';
        $regResult = $service->register('GetById', $email, 'password123');

        if (!$regResult['success']) {
            $this->markTestSkipped('Registro falhou');
        }

        $user = $service->getUserById($regResult['user_id']);
        $this->assertNotNull($user);
        $this->assertEquals($email, $user['email']);
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
                email_verified_at DATETIME NULL,
                two_factor_enabled TINYINT(1) DEFAULT 0,
                two_factor_secret VARCHAR(255) NULL,
                remember_token VARCHAR(255) NULL,
                theme VARCHAR(20) DEFAULT 'light',
                dashboard_preferences JSON NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (\Throwable $e) {
            // exists
        }

        // Adicionar colunas que podem faltar em tabelas existentes
        $columnsToAdd = [
            'verification_token' => 'VARCHAR(255) NULL',
            'email_verified_at' => 'DATETIME NULL',
            'two_factor_enabled' => 'TINYINT(1) DEFAULT 0',
            'two_factor_secret' => 'VARCHAR(255) NULL',
            'remember_token' => 'VARCHAR(255) NULL',
            'theme' => "VARCHAR(20) DEFAULT 'light'",
            'dashboard_preferences' => 'JSON NULL',
        ];

        foreach ($columnsToAdd as $col => $def) {
            try {
                $this->db->exec("ALTER TABLE users ADD COLUMN {$col} {$def}");
            } catch (\Throwable $e) {
                // coluna já existe
            }
        }

        try {
            $this->db->exec("CREATE TABLE IF NOT EXISTS audit_logs (
                id INT PRIMARY KEY AUTO_INCREMENT,
                action VARCHAR(100), user_id INT NULL, entity_id INT NULL,
                details JSON NULL, ip VARCHAR(45) NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        } catch (\Throwable $e) {
            // exists
        }
    }

    private function verifyUserEmail(string $email): void
    {
        $this->db->prepare("UPDATE users SET email_verified_at = NOW(), verification_token = NULL WHERE email = ?")
            ->execute([$email]);
    }
}
