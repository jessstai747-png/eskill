<?php
namespace Tests\Integration;

use PHPUnit\Framework\TestCase;
use App\Services\MercadoLivreClient;
use App\Services\EncryptionService;
use App\Database;

class MercadoLivreAccountPersistenceTest extends TestCase
{
    private int $accountId;

    protected function setUp(): void
    {
        putenv('APP_KEY=UnitTestFallbackKey_ThisIs32CharsLong!');
        $pdo = Database::getInstance();

        // Ensure table exists
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

        $pdo->exec('DELETE FROM ml_accounts');

        // Ensure tokens_encrypted column exists
        try {
            $stmt = $pdo->query("SHOW COLUMNS FROM ml_accounts LIKE 'tokens_encrypted'");
            $col = $stmt->fetch();
            if (!$col) {
                $pdo->exec("ALTER TABLE ml_accounts ADD COLUMN tokens_encrypted TINYINT(1) NOT NULL DEFAULT 0");
            }
        } catch (\Throwable $e) {
            // ignore
        }

        // Ensure users table exists and insert user id=2 for FK
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

        try {
            $stmt = $pdo->prepare('SELECT id FROM users WHERE id = :id');
            $stmt->execute(['id' => 2]);
            if (!$stmt->fetch()) {
                $pdo->prepare('INSERT INTO users (id, name, email, password) VALUES (:id, :name, :email, :password)')
                    ->execute(['id' => 2, 'name' => 'ML Persist User', 'email' => 'persist@example.com', 'password' => password_hash('secret', PASSWORD_BCRYPT)]);
            }
        } catch (\Throwable $e) {
            // ignore
        }

        $enc = new EncryptionService();
        $accessEnc = $enc->encrypt('persist-access');
        $refreshEnc = $enc->encrypt('persist-refresh');

        $stmt = $pdo->prepare("INSERT INTO ml_accounts
            (user_id, ml_user_id, nickname, email, site_id, access_token, refresh_token, token_expires_at, tokens_encrypted, status, created_at, updated_at)
            VALUES (:user_id, :ml_user_id, :nickname, :email, :site_id, :access_token, :refresh_token, :expires_at, :tokens_encrypted, 'active', NOW(), NOW())");

        $stmt->execute([
            'user_id' => 2,
            'ml_user_id' => 'PERSIST123',
            'nickname' => 'persist-ml',
            'email' => 'persist@example.com',
            'site_id' => 'MLB',
            'access_token' => $accessEnc,
            'refresh_token' => $refreshEnc,
            'expires_at' => date('Y-m-d H:i:s', time() + 3600),
            'tokens_encrypted' => 1
        ]);

        $this->accountId = (int)$pdo->lastInsertId();
    }

    public function testAccountPersistenceAndDecryption()
    {
        $client = new MercadoLivreClient($this->accountId);
        $client->loadAccount();
        $this->assertEquals('persist-access', $client->getAccessToken());
    }
}
