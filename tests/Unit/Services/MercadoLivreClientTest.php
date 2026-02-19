<?php
namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\MercadoLivreClient;
use App\Services\EncryptionService;
use App\Database;

class MercadoLivreClientTest extends TestCase
{
    private int $accountId;

    protected function setUp(): void
    {
        // Ensure APP_KEY for EncryptionService
        putenv('APP_KEY=UnitTestFallbackKey_ThisIs32CharsLong!');

        $pdo = Database::getInstance();

        // Create table if not exists (safe DDL for tests)
        $pdo->exec("CREATE TABLE IF NOT EXISTS ml_accounts (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id INT UNSIGNED NOT NULL,
            ml_user_id VARCHAR(64) NOT NULL,
            nickname VARCHAR(255) NULL,
            email VARCHAR(255) NULL,
            site_id VARCHAR(10) NOT NULL DEFAULT 'MLB',
            access_token TEXT NULL,
            refresh_token TEXT NULL,
            token_expires_at DATETIME NULL,
            last_synced_at DATETIME NULL,
            scopes VARCHAR(255) NULL,
            status VARCHAR(20) NOT NULL DEFAULT 'active',
            tokens_encrypted TINYINT(1) NOT NULL DEFAULT 0,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

        // Clean up
        $pdo->exec('DELETE FROM ml_accounts');

        // Ensure users table and a test user exist (ml_accounts.user_id FK)
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS users (
                id INT PRIMARY KEY AUTO_INCREMENT,
                name VARCHAR(255),
                email VARCHAR(255),
                password VARCHAR(255),
                role VARCHAR(50) DEFAULT 'admin',
                email_verified_at DATETIME DEFAULT CURRENT_TIMESTAMP
            ) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;");
        } catch (\Throwable $e) {
            // ignore
        }

        // Insert user id=1 if missing
        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id = :id');
            $stmt->execute(['id' => 1]);
            if (!$stmt->fetch()) {
                $pdo->prepare('INSERT INTO users (id, name, email, password) VALUES (:id, :name, :email, :password)')
                    ->execute(['id' => 1, 'name' => 'ML Test User', 'email' => 'mltest1@example.com', 'password' => password_hash('secret', PASSWORD_BCRYPT)]);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Ensure tokens_encrypted column exists (older test DBs may miss it)
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM ml_accounts LIKE 'tokens_encrypted'");
            $col = $stmt->fetch();
            if (!$col) {
                $pdo->exec("ALTER TABLE ml_accounts ADD COLUMN tokens_encrypted TINYINT(1) NOT NULL DEFAULT 0");
            }
        } catch (\Throwable $e) {
            // ignore - best effort
        }

        // Insert an account with encrypted tokens
        $enc = new EncryptionService();
        $accessEnc = $enc->encrypt('initial-access-token');
        $refreshEnc = $enc->encrypt('initial-refresh-token');
        $expiresAt = date('Y-m-d H:i:s', time() + 3600);

        $stmt = $pdo->prepare("INSERT INTO ml_accounts
            (user_id, ml_user_id, nickname, email, site_id, access_token, refresh_token, token_expires_at, tokens_encrypted, status, created_at, updated_at)
            VALUES (:user_id, :ml_user_id, :nickname, :email, :site_id, :access_token, :refresh_token, :expires_at, :tokens_encrypted, 'active', NOW(), NOW())");

        $stmt->execute([
            'user_id' => 1,
            'ml_user_id' => 'MLUSER123',
            'nickname' => 'unittest-ml',
            'email' => 'ml@example.com',
            'site_id' => 'MLB',
            'access_token' => $accessEnc,
            'refresh_token' => $refreshEnc,
            'expires_at' => $expiresAt,
            'tokens_encrypted' => 1
        ]);

        $this->accountId = (int)$pdo->lastInsertId();
    }

    public function testLoadAccountDecryptsToken()
    {
        $client = new MercadoLivreClient($this->accountId);
        $client->loadAccount();

        $this->assertEquals('initial-access-token', $client->getAccessToken());
    }

    public function testEnsureValidAccessToken_uses_refresh_when_expired()
    {
        $pdo = Database::getInstance();

        // Expire the token
        $pdo->prepare('UPDATE ml_accounts SET token_expires_at = :exp WHERE id = :id')
            ->execute(['exp' => date('Y-m-d H:i:s', time() - 3600), 'id' => $this->accountId]);

        // Create a Test AuthService that will simulate refresh by updating the DB
        $testAuth = new class extends \App\Services\MercadoLivreAuthService {
            public function refreshToken(int $accountId, int $maxRetries = 3): bool
            {
                $db = Database::getInstance();
                $enc = new EncryptionService();
                $newAccess = $enc->encrypt('refreshed-access-token');
                $newRefresh = $enc->encrypt('refreshed-refresh-token');
                $expiresAt = date('Y-m-d H:i:s', time() + 7200);

                $upd = $db->prepare('UPDATE ml_accounts SET access_token = :access_token, refresh_token = :refresh_token, token_expires_at = :expires_at, tokens_encrypted = 1, updated_at = NOW() WHERE id = :id');
                $upd->execute(['access_token' => $newAccess, 'refresh_token' => $newRefresh, 'expires_at' => $expiresAt, 'id' => $accountId]);
                return (bool)$upd->rowCount();
            }
        };

        $client = new MercadoLivreClient($this->accountId, $testAuth);
        $ok = $client->ensureValidAccessToken();

        $this->assertTrue($ok, 'ensureValidAccessToken should return true after simulated refresh');
        $this->assertEquals('refreshed-access-token', $client->getAccessToken());
    }

    public function testNetworkDisabledInTestingEnvironment(): void
    {
        $previousEnv = $_ENV['APP_ENV'] ?? null;
        $previousAllow = $_ENV['ML_ALLOW_NETWORK'] ?? null;

        putenv('APP_ENV=testing');
        $_ENV['APP_ENV'] = 'testing';
        putenv('ML_ALLOW_NETWORK');
        unset($_ENV['ML_ALLOW_NETWORK']);

        $client = new MercadoLivreClient(null);
        $response = $client->get('/sites/MLB/search', ['q' => 'test'], 0, true);

        $this->assertEquals('network_disabled', $response['error'] ?? null);
        $this->assertEquals(503, $response['status'] ?? null);

        if ($previousEnv !== null) {
            putenv("APP_ENV={$previousEnv}");
            $_ENV['APP_ENV'] = $previousEnv;
        } else {
            putenv('APP_ENV');
            unset($_ENV['APP_ENV']);
        }

        if ($previousAllow !== null) {
            putenv("ML_ALLOW_NETWORK={$previousAllow}");
            $_ENV['ML_ALLOW_NETWORK'] = $previousAllow;
        } else {
            putenv('ML_ALLOW_NETWORK');
            unset($_ENV['ML_ALLOW_NETWORK']);
        }
    }

    public function testNetworkCallsAreOptIn(): void
    {
        $allow = $_ENV['ML_ALLOW_NETWORK'] ?? getenv('ML_ALLOW_NETWORK') ?? null;
        if (!filter_var($allow, FILTER_VALIDATE_BOOLEAN)) {
            $this->markTestSkipped('ML_ALLOW_NETWORK not enabled');
        }

        $client = new MercadoLivreClient(null);
        $response = $client->get('/sites/MLB/search', ['q' => 'notebook'], 0, true);

        $this->assertIsArray($response);
    }
}
