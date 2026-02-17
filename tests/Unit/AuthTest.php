<?php
namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class AuthTest extends TestCase
{
    protected function setUp(): void
    {
        // Ensure APP_KEY exists for services that require it
        putenv('APP_KEY=UnitTestFallbackKey_ThisIs32CharsLong!');
        // Use the test MySQL database instance (provided by bootstrap)
        $pdo = \App\Database::getInstance();

        // Ensure we have a clean state for users/refresh_tokens for each test
        try {
            $pdo->exec('DELETE FROM refresh_tokens');
            $pdo->exec('DELETE FROM users');
        } catch (\Throwable $e) {
            // If tables don't exist yet, create them (MySQL DDL)
            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255),
                email VARCHAR(255) UNIQUE,
                password VARCHAR(255),
                role VARCHAR(50) DEFAULT 'admin',
                email_verified_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                two_factor_enabled TINYINT(1) DEFAULT 0
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS refresh_tokens (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                selector VARCHAR(64) NOT NULL,
                hashed_validator VARCHAR(255) NOT NULL,
                device_info VARCHAR(255),
                ip_address VARCHAR(45),
                expires_at DATETIME NOT NULL,
                revoked TINYINT(1) DEFAULT 0,
                replaced_by INT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_selector (selector),
                INDEX idx_user (user_id)
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        }

        // Ensure 'role' column exists (application expects it)
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'role'");
            $col = $stmt->fetch();
            if (!$col) {
                $pdo->exec("ALTER TABLE users ADD COLUMN role VARCHAR(50) DEFAULT 'admin' AFTER email");
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Ensure 'two_factor_enabled' column exists
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM users LIKE 'two_factor_enabled'");
            $col = $stmt->fetch();
            if (!$col) {
                $pdo->exec("ALTER TABLE users ADD COLUMN two_factor_enabled TINYINT(1) DEFAULT 0 AFTER email_verified_at");
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Insert a test user (mark email as verified)
        $password = password_hash('secret123', PASSWORD_BCRYPT);
        $stmt = $pdo->prepare('INSERT INTO users (name, email, password, email_verified_at) VALUES (?, ?, ?, NOW())');
        $stmt->execute(['Test User', 'test@example.com', $password]);
    }

    protected function tearDown(): void
    {
        // Reset App\Database::$instance to avoid leaking in-memory SQLite into other tests
        try {
            $ref = new \ReflectionClass(\App\Database::class);
            if ($ref->hasProperty('instance')) {
                $prop = $ref->getProperty('instance');
                $prop->setAccessible(true);
                $prop->setValue(null, null);
            }
        } catch (\Throwable $e) {
            // if reflection fails, ignore — tests will fail hard later which is fine
        }

        // Cleanup inserted test user(s)
        try {
            $pdo = \App\Database::getInstance();
            $pdo->exec('DELETE FROM refresh_tokens');
            $pdo->exec('DELETE FROM users');
        } catch (\Throwable $e) {
            // ignore
        }

        parent::tearDown();
    }

    public function testLoginSuccess()
    {
        $auth = new \App\Services\AuthService();
        $res = $auth->login('test@example.com', 'secret123', 'phpunit');

        $this->assertTrue($res['success']);
        $this->assertArrayHasKey('access_token', $res);
        $this->assertArrayHasKey('refresh_token', $res);
    }

    public function testLoginFailure()
    {
        $auth = new \App\Services\AuthService();
        $res = $auth->login('test@example.com', 'wrongpassword', 'phpunit');

        $this->assertFalse($res['success']);
        $this->assertArrayHasKey('message', $res);
    }

    public function testRefreshRotatesToken()
    {
        $auth = new \App\Services\AuthService();
        $login = $auth->login('test@example.com', 'secret123', 'device-x');
        $this->assertTrue($login['success']);

        $oldRefresh = $login['refresh_token'];
        $refreshRes = $auth->refresh($oldRefresh);

        $this->assertTrue($refreshRes['success']);
        $this->assertArrayHasKey('access_token', $refreshRes);
        $this->assertArrayHasKey('refresh_token', $refreshRes);

        // Old token should not be usable again
        $reuse = $auth->refresh($oldRefresh);
        $this->assertFalse($reuse['success']);
    }

    public function testLogoutRevokesToken()
    {
        $auth = new \App\Services\AuthService();
        $login = $auth->login('test@example.com', 'secret123', 'device-y');
        $this->assertTrue($login['success']);

        $refresh = $login['refresh_token'];
        $out = $auth->logout($refresh, null);
        $this->assertTrue($out['success']);

        // Cannot refresh with revoked token
        $after = $auth->refresh($refresh);
        $this->assertFalse($after['success']);
    }
}
