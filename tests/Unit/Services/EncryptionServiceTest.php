<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\EncryptionService;

/**
 * Testes do EncryptionService
 *
 * Cobre: encrypt/decrypt, encryptArray/decryptArray, isEncrypted,
 *        generateKey, hashPassword/verifyPassword, generateToken, hashToken
 */
class EncryptionServiceTest extends TestCase
{
    private ?EncryptionService $service = null;

    protected function setUp(): void
    {
        parent::setUp();
        try {
            $this->service = new EncryptionService();
        } catch (\RuntimeException $e) {
            // APP_KEY pode não estar configurada
            if (str_contains($e->getMessage(), 'Chave de criptografia')) {
                $this->service = new EncryptionService(str_repeat('a', 32));
            } else {
                throw $e;
            }
        }
    }

    // ===========================
    // CONSTRUCTOR
    // ===========================

    public function test_constructor_requires_minimum_key_length(): void
    {
        $this->expectException(\RuntimeException::class);
        new EncryptionService('short');
    }

    public function test_constructor_accepts_32_char_key(): void
    {
        $service = new EncryptionService(str_repeat('x', 32));
        $this->assertInstanceOf(EncryptionService::class, $service);
    }

    public function test_constructor_rejects_empty_key(): void
    {
        $this->expectException(\RuntimeException::class);
        new EncryptionService('');
    }

    // ===========================
    // ENCRYPT / DECRYPT
    // ===========================

    public function test_encrypt_returns_base64_string(): void
    {
        $encrypted = $this->service->encrypt('Hello World');

        $this->assertIsString($encrypted);
        $this->assertNotEmpty($encrypted);
        // Deve ser base64 válido
        $this->assertNotFalse(base64_decode($encrypted, true));
    }

    public function test_decrypt_recovers_original_data(): void
    {
        $original = 'Dados sensíveis do Mercado Livre 🔐';
        $encrypted = $this->service->encrypt($original);
        $decrypted = $this->service->decrypt($encrypted);

        $this->assertEquals($original, $decrypted);
    }

    public function test_encrypt_produces_different_ciphertexts(): void
    {
        // Mesmo plaintext deve gerar ciphertexts diferentes (IV aleatório)
        $data = 'test data';
        $enc1 = $this->service->encrypt($data);
        $enc2 = $this->service->encrypt($data);

        $this->assertNotEquals($enc1, $enc2, 'IVs aleatórios devem gerar ciphertexts diferentes');
    }

    public function test_encrypt_empty_string_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->encrypt('');
    }

    public function test_decrypt_empty_string_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->decrypt('');
    }

    public function test_decrypt_invalid_base64_throws(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->service->decrypt('not-valid-base64!!!###');
    }

    public function test_decrypt_tampered_data_throws(): void
    {
        $encrypted = $this->service->encrypt('secret');
        // Tamper with the ciphertext
        $tampered = substr($encrypted, 0, -2) . 'XX';

        $this->expectException(\RuntimeException::class);
        $this->service->decrypt($tampered);
    }

    public function test_decrypt_with_wrong_key_fails(): void
    {
        $service1 = new EncryptionService(str_repeat('a', 32));
        $service2 = new EncryptionService(str_repeat('b', 32));

        $encrypted = $service1->encrypt('secret data');

        $this->expectException(\RuntimeException::class);
        $service2->decrypt($encrypted);
    }

    // ===========================
    // ENCRYPT/DECRYPT ARRAY
    // ===========================

    public function test_encryptArray_and_decryptArray(): void
    {
        $data = ['user_id' => 42, 'token' => 'abc123', 'nested' => ['key' => 'value']];
        $encrypted = $this->service->encryptArray($data);
        $decrypted = $this->service->decryptArray($encrypted);

        $this->assertEquals($data, $decrypted);
    }

    // ===========================
    // isEncrypted
    // ===========================

    public function test_isEncrypted_detects_encrypted_data(): void
    {
        $encrypted = $this->service->encrypt('test');
        $this->assertTrue($this->service->isEncrypted($encrypted));
    }

    public function test_isEncrypted_rejects_plain_text(): void
    {
        $this->assertFalse($this->service->isEncrypted('plain text'));
    }

    public function test_isEncrypted_rejects_short_base64(): void
    {
        $this->assertFalse($this->service->isEncrypted(base64_encode('x')));
    }

    // ===========================
    // STATIC: generateKey
    // ===========================

    public function test_generateKey_returns_hex_string(): void
    {
        $key = EncryptionService::generateKey(64);

        $this->assertIsString($key);
        $this->assertEquals(64, strlen($key));
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $key);
    }

    public function test_generateKey_produces_unique_keys(): void
    {
        $key1 = EncryptionService::generateKey();
        $key2 = EncryptionService::generateKey();

        $this->assertNotEquals($key1, $key2);
    }

    // ===========================
    // PASSWORD HASHING
    // ===========================

    public function test_hashPassword_returns_bcrypt_hash(): void
    {
        $hash = $this->service->hashPassword('SecurePass123');

        $this->assertStringStartsWith('$2y$', $hash);
    }

    public function test_verifyPassword_correct(): void
    {
        $password = 'MyP@ssw0rd!';
        $hash = $this->service->hashPassword($password);

        $this->assertTrue($this->service->verifyPassword($password, $hash));
    }

    public function test_verifyPassword_incorrect(): void
    {
        $hash = $this->service->hashPassword('correct');

        $this->assertFalse($this->service->verifyPassword('wrong', $hash));
    }

    // ===========================
    // TOKEN GENERATION
    // ===========================

    public function test_generateToken_returns_hex_string(): void
    {
        $token = $this->service->generateToken(32);

        $this->assertEquals(64, strlen($token)); // 32 bytes = 64 hex chars
        $this->assertMatchesRegularExpression('/^[0-9a-f]+$/', $token);
    }

    public function test_generateToken_produces_unique_tokens(): void
    {
        $t1 = $this->service->generateToken();
        $t2 = $this->service->generateToken();

        $this->assertNotEquals($t1, $t2);
    }

    public function test_hashToken_returns_sha256(): void
    {
        $token = 'my-secret-token';
        $hash = $this->service->hashToken($token);

        $this->assertEquals(64, strlen($hash)); // SHA-256 = 64 hex chars
        $this->assertEquals(hash('sha256', $token), $hash);
    }

    // ===========================
    // LARGE DATA
    // ===========================

    public function test_encrypt_decrypt_large_data(): void
    {
        $data = str_repeat('A', 10000); // 10KB
        $encrypted = $this->service->encrypt($data);
        $decrypted = $this->service->decrypt($encrypted);

        $this->assertEquals($data, $decrypted);
    }

    public function test_encrypt_decrypt_unicode(): void
    {
        $data = 'Dados com acentuação: ção, ñ, ü, 日本語, 🔐💰';
        $encrypted = $this->service->encrypt($data);
        $decrypted = $this->service->decrypt($encrypted);

        $this->assertEquals($data, $decrypted);
    }
}
