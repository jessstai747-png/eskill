<?php

namespace Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use App\Services\JwtService;

/**
 * Testes do JwtService
 *
 * Cobre: generateToken, validateToken, getUserIdFromToken
 * Verifica: formato JWT, claims, expiração, assinatura HMAC-SHA256
 */
class JwtServiceTest extends TestCase
{
    private ?JwtService $service = null;
    private string $testKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->testKey = str_repeat('t', 32);
        try {
            $this->service = new JwtService();
        } catch (\Exception $e) {
            // APP_KEY pode não estar configurada — instanciar com reflexão
            $this->service = $this->createServiceWithKey($this->testKey);
        }
    }

    private function createServiceWithKey(string $key): JwtService
    {
        $service = new \ReflectionClass(JwtService::class);
        $instance = $service->newInstanceWithoutConstructor();

        $secretProp = $service->getProperty('secret');
        $secretProp->setAccessible(true);
        $secretProp->setValue($instance, $key);

        if ($service->hasProperty('issuer')) {
            $issuerProp = $service->getProperty('issuer');
            $issuerProp->setAccessible(true);
            $issuerProp->setValue($instance, 'test-app');
        }

        return $instance;
    }

    // ===========================
    // FORMAT TESTS
    // ===========================

    public function test_generateToken_returns_three_part_jwt(): void
    {
        $token = $this->service->generateToken(42);

        $parts = explode('.', $token);
        $this->assertCount(3, $parts, 'JWT deve ter 3 partes separadas por ponto');
    }

    public function test_generateToken_header_is_valid_json(): void
    {
        $token = $this->service->generateToken(1);
        $parts = explode('.', $token);

        $header = json_decode($this->base64UrlDecode($parts[0]), true);
        $this->assertNotNull($header);
        $this->assertEquals('JWT', $header['typ'] ?? null);
        $this->assertEquals('HS256', $header['alg'] ?? null);
    }

    public function test_generateToken_payload_contains_required_claims(): void
    {
        $userId = 99;
        $token = $this->service->generateToken($userId);
        $parts = explode('.', $token);

        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        $this->assertNotNull($payload);
        $this->assertEquals($userId, $payload['sub'] ?? null);
        $this->assertArrayHasKey('iat', $payload);
        $this->assertArrayHasKey('exp', $payload);
        $this->assertArrayHasKey('jti', $payload);
    }

    public function test_generateToken_respects_ttl(): void
    {
        $token = $this->service->generateToken(1, 3600);
        $parts = explode('.', $token);
        $payload = json_decode($this->base64UrlDecode($parts[1]), true);

        $this->assertEquals($payload['iat'] + 3600, $payload['exp']);
    }

    // ===========================
    // VALIDATION TESTS
    // ===========================

    public function test_validateToken_accepts_valid_token(): void
    {
        $token = $this->service->generateToken(42);
        $payload = $this->service->validateToken($token);

        $this->assertNotNull($payload);
        $this->assertIsArray($payload);
        $this->assertEquals(42, $payload['sub'] ?? null);
    }

    public function test_validateToken_rejects_expired_token(): void
    {
        // Gerar token com TTL de 1 segundo e expirar manualmente
        $service = $this->createServiceWithKey($this->testKey);

        // Criar token expirado via reflexão do payload
        $header = $this->base64UrlEncode(json_encode(['typ' => 'JWT', 'alg' => 'HS256']));
        $payload = $this->base64UrlEncode(json_encode([
            'sub' => 1,
            'iat' => time() - 7200,
            'exp' => time() - 3600, // expirou há 1 hora
            'jti' => bin2hex(random_bytes(16)),
            'iss' => 'test-app',
        ]));
        $signature = $this->base64UrlEncode(
            hash_hmac('sha256', "$header.$payload", $this->testKey, true)
        );
        $expiredToken = "$header.$payload.$signature";

        $result = $service->validateToken($expiredToken);
        $this->assertNull($result, 'Token expirado deve retornar null');
    }

    public function test_validateToken_rejects_tampered_payload(): void
    {
        $token = $this->service->generateToken(42);
        $parts = explode('.', $token);

        // Alterar o payload (mudar userId)
        $payload = json_decode($this->base64UrlDecode($parts[1]), true);
        $payload['sub'] = 999;
        $parts[1] = $this->base64UrlEncode(json_encode($payload));
        $tampered = implode('.', $parts);

        $result = $this->service->validateToken($tampered);
        $this->assertNull($result, 'Token com payload alterado deve ser rejeitado');
    }

    public function test_validateToken_rejects_tampered_signature(): void
    {
        $token = $this->service->generateToken(42);
        $tampered = substr($token, 0, -4) . 'XYZW';

        $result = $this->service->validateToken($tampered);
        $this->assertNull($result);
    }

    public function test_validateToken_rejects_malformed_token(): void
    {
        $result = $this->service->validateToken('not.a.jwt');
        $this->assertNull($result);
    }

    public function test_validateToken_rejects_token_with_wrong_parts(): void
    {
        $result = $this->service->validateToken('only-one-part');
        $this->assertNull($result);
    }

    public function test_validateToken_rejects_empty_string(): void
    {
        $result = $this->service->validateToken('');
        $this->assertNull($result);
    }

    // ===========================
    // getUserIdFromToken
    // ===========================

    public function test_getUserIdFromToken_returns_correct_id(): void
    {
        $token = $this->service->generateToken(123);
        $userId = $this->service->getUserIdFromToken($token);

        $this->assertEquals(123, $userId);
    }

    public function test_getUserIdFromToken_returns_null_for_invalid(): void
    {
        $result = $this->service->getUserIdFromToken('invalid-token');
        $this->assertNull($result);
    }

    // ===========================
    // CROSS-KEY VALIDATION
    // ===========================

    public function test_token_signed_with_different_key_is_rejected(): void
    {
        $service1 = $this->createServiceWithKey(str_repeat('a', 32));
        $service2 = $this->createServiceWithKey(str_repeat('b', 32));

        $token = $service1->generateToken(42);
        $result = $service2->validateToken($token);

        $this->assertNull($result, 'Token assinado com chave diferente deve ser rejeitado');
    }

    // ===========================
    // HELPERS
    // ===========================

    private function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    private function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/'));
    }
}
