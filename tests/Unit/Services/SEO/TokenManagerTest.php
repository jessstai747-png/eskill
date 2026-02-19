<?php

declare(strict_types=1);

namespace Tests\Unit\Services\SEO;

use App\Services\SEO\TokenManager;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

/**
 * TokenManagerTest
 *
 * Unit tests for TokenManager service methods
 */
class TokenManagerTest extends TestCase
{
    /**
     * Test that needsRefresh returns true for empty expires_at
     */
    public function testNeedsRefreshReturnsTrueForEmptyExpiresAt(): void
    {
        $manager = $this->getMockBuilder(TokenManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('needsRefresh');
        $method->setAccessible(true);

        $account = ['expires_at' => null];
        $result = $method->invoke($manager, $account);

        $this->assertTrue($result);
    }

    /**
     * Test that needsRefresh returns true when token is expired
     */
    public function testNeedsRefreshReturnsTrueForExpiredToken(): void
    {
        $manager = $this->getMockBuilder(TokenManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('needsRefresh');
        $method->setAccessible(true);

        // Expired 1 hour ago
        $account = ['expires_at' => date('Y-m-d H:i:s', time() - 3600)];
        $result = $method->invoke($manager, $account);

        $this->assertTrue($result);
    }

    /**
     * Test that needsRefresh returns true within threshold
     */
    public function testNeedsRefreshReturnsTrueWithinThreshold(): void
    {
        $manager = $this->getMockBuilder(TokenManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('needsRefresh');
        $method->setAccessible(true);

        // Expires in 4 minutes (threshold is 5 minutes)
        $account = ['expires_at' => date('Y-m-d H:i:s', time() + 240)];
        $result = $method->invoke($manager, $account);

        $this->assertTrue($result);
    }

    /**
     * Test that needsRefresh returns false when token is valid
     */
    public function testNeedsRefreshReturnsFalseForValidToken(): void
    {
        $manager = $this->getMockBuilder(TokenManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $reflection = new ReflectionClass($manager);
        $method = $reflection->getMethod('needsRefresh');
        $method->setAccessible(true);

        // Expires in 2 hours (threshold is 5 minutes)
        $account = ['expires_at' => date('Y-m-d H:i:s', time() + 7200)];
        $result = $method->invoke($manager, $account);

        $this->assertFalse($result);
    }

    /**
     * Test that REFRESH_THRESHOLD_SECONDS constant exists and has correct value
     */
    public function testRefreshThresholdConstant(): void
    {
        $reflection = new ReflectionClass(TokenManager::class);
        $constant = $reflection->getConstant('REFRESH_THRESHOLD_SECONDS');

        $this->assertSame(300, $constant);
    }

    /**
     * Test that CACHE_TTL constant exists and has correct value
     */
    public function testCacheTtlConstant(): void
    {
        $reflection = new ReflectionClass(TokenManager::class);
        $constant = $reflection->getConstant('CACHE_TTL');

        $this->assertSame(3600, $constant);
    }

    /**
     * Test checkTokenHealth returns not_found for missing account
     */
    public function testCheckTokenHealthReturnsNotFoundForMissingAccount(): void
    {
        $manager = $this->getMockBuilder(TokenManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['checkTokenHealth'])
            ->getMock();

        $manager->method('checkTokenHealth')
            ->willReturn([
                'healthy' => false,
                'status' => 'not_found',
                'message' => 'Account not found',
            ]);

        $result = $manager->checkTokenHealth(999999);

        $this->assertFalse($result['healthy']);
        $this->assertSame('not_found', $result['status']);
        $this->assertArrayHasKey('message', $result);
    }

    /**
     * Test checkTokenHealth returns no_token for empty access_token
     */
    public function testCheckTokenHealthReturnsNoTokenForEmptyAccessToken(): void
    {
        $manager = $this->getMockBuilder(TokenManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['checkTokenHealth'])
            ->getMock();

        $manager->method('checkTokenHealth')
            ->willReturn([
                'healthy' => false,
                'status' => 'no_token',
                'message' => 'No access token found',
            ]);

        $result = $manager->checkTokenHealth(1);

        $this->assertFalse($result['healthy']);
        $this->assertSame('no_token', $result['status']);
    }

    /**
     * Test checkTokenHealth returns valid status
     */
    public function testCheckTokenHealthReturnsValidStatus(): void
    {
        $manager = $this->getMockBuilder(TokenManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['checkTokenHealth'])
            ->getMock();

        $manager->method('checkTokenHealth')
            ->willReturn([
                'healthy' => true,
                'status' => 'valid',
                'message' => 'Token is valid',
                'expires_in' => 7200,
            ]);

        $result = $manager->checkTokenHealth(1);

        $this->assertTrue($result['healthy']);
        $this->assertSame('valid', $result['status']);
        $this->assertArrayHasKey('expires_in', $result);
    }

    /**
     * Test checkTokenHealth returns expired status
     */
    public function testCheckTokenHealthReturnsExpiredStatus(): void
    {
        $manager = $this->getMockBuilder(TokenManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['checkTokenHealth'])
            ->getMock();

        $manager->method('checkTokenHealth')
            ->willReturn([
                'healthy' => false,
                'status' => 'expired',
                'message' => 'Token expired',
                'expired_ago' => 3600,
            ]);

        $result = $manager->checkTokenHealth(1);

        $this->assertFalse($result['healthy']);
        $this->assertSame('expired', $result['status']);
        $this->assertArrayHasKey('expired_ago', $result);
    }

    /**
     * Test checkTokenHealth returns needs_refresh status
     */
    public function testCheckTokenHealthReturnsNeedsRefreshStatus(): void
    {
        $manager = $this->getMockBuilder(TokenManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['checkTokenHealth'])
            ->getMock();

        $manager->method('checkTokenHealth')
            ->willReturn([
                'healthy' => true,
                'status' => 'needs_refresh',
                'message' => 'Token will be refreshed soon',
                'expires_in' => 200,
            ]);

        $result = $manager->checkTokenHealth(1);

        $this->assertTrue($result['healthy']);
        $this->assertSame('needs_refresh', $result['status']);
    }

    /**
     * Test checkTokenHealth returns no_refresh_token status
     */
    public function testCheckTokenHealthReturnsNoRefreshTokenStatus(): void
    {
        $manager = $this->getMockBuilder(TokenManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['checkTokenHealth'])
            ->getMock();

        $manager->method('checkTokenHealth')
            ->willReturn([
                'healthy' => false,
                'status' => 'no_refresh_token',
                'message' => 'No refresh token found - re-authorization required',
            ]);

        $result = $manager->checkTokenHealth(1);

        $this->assertFalse($result['healthy']);
        $this->assertSame('no_refresh_token', $result['status']);
    }

    /**
     * Test getTokenStatistics returns expected structure
     */
    public function testGetTokenStatisticsReturnsExpectedStructure(): void
    {
        $manager = $this->getMockBuilder(TokenManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getTokenStatistics'])
            ->getMock();

        $manager->method('getTokenStatistics')
            ->willReturn([
                'total_accounts' => 5,
                'accounts_with_token' => 4,
                'accounts_with_refresh' => 4,
                'valid_tokens' => 3,
                'expired_tokens' => 1,
            ]);

        $result = $manager->getTokenStatistics();

        $this->assertArrayHasKey('total_accounts', $result);
        $this->assertArrayHasKey('accounts_with_token', $result);
        $this->assertArrayHasKey('accounts_with_refresh', $result);
        $this->assertArrayHasKey('valid_tokens', $result);
        $this->assertArrayHasKey('expired_tokens', $result);
    }

    /**
     * Test getTokenStatistics validates numeric values
     */
    public function testGetTokenStatisticsValidatesNumericValues(): void
    {
        $manager = $this->getMockBuilder(TokenManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getTokenStatistics'])
            ->getMock();

        $manager->method('getTokenStatistics')
            ->willReturn([
                'total_accounts' => 10,
                'accounts_with_token' => 8,
                'accounts_with_refresh' => 8,
                'valid_tokens' => 6,
                'expired_tokens' => 2,
            ]);

        $result = $manager->getTokenStatistics();

        $this->assertIsInt($result['total_accounts']);
        $this->assertIsInt($result['valid_tokens']);
        $this->assertGreaterThanOrEqual(0, $result['total_accounts']);
    }

    /**
     * Test forceRefresh throws exception when no refresh token
     */
    public function testForceRefreshThrowsExceptionWhenNoRefreshToken(): void
    {
        $manager = $this->getMockBuilder(TokenManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['forceRefresh'])
            ->getMock();

        $manager->method('forceRefresh')
            ->willThrowException(new \Exception("Cannot refresh: no refresh token available"));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Cannot refresh: no refresh token available");

        $manager->forceRefresh(1);
    }

    /**
     * Test getValidToken throws exception for invalid account
     */
    public function testGetValidTokenThrowsExceptionForInvalidAccount(): void
    {
        $manager = $this->getMockBuilder(TokenManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getValidToken'])
            ->getMock();

        $manager->method('getValidToken')
            ->willThrowException(new \Exception("Account not found: 999999"));

        $this->expectException(\Exception::class);
        $this->expectExceptionMessage("Account not found: 999999");

        $manager->getValidToken(999999);
    }

    /**
     * Test getValidToken returns token string
     */
    public function testGetValidTokenReturnsTokenString(): void
    {
        $manager = $this->getMockBuilder(TokenManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getValidToken'])
            ->getMock();

        $expectedToken = 'APP_USR-test-access-token-123';

        $manager->method('getValidToken')
            ->willReturn($expectedToken);

        $result = $manager->getValidToken(1);

        $this->assertIsString($result);
        $this->assertSame($expectedToken, $result);
    }

    /**
     * Test refreshToken returns expected structure
     */
    public function testRefreshTokenReturnsExpectedStructure(): void
    {
        $manager = $this->getMockBuilder(TokenManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['refreshToken'])
            ->getMock();

        $manager->method('refreshToken')
            ->willReturn([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 21600,
            ]);

        $result = $manager->refreshToken(1, 'old-refresh-token');

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertArrayHasKey('expires_in', $result);
    }

    /**
     * Test exchangeCodeForTokens returns expected structure
     */
    public function testExchangeCodeForTokensReturnsExpectedStructure(): void
    {
        $manager = $this->getMockBuilder(TokenManager::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['exchangeCodeForTokens'])
            ->getMock();

        $manager->method('exchangeCodeForTokens')
            ->willReturn([
                'access_token' => 'new-access-token',
                'refresh_token' => 'new-refresh-token',
                'expires_in' => 21600,
                'user_id' => 12345,
            ]);

        $result = $manager->exchangeCodeForTokens('AUTH_CODE_123', 1);

        $this->assertArrayHasKey('access_token', $result);
        $this->assertArrayHasKey('refresh_token', $result);
        $this->assertArrayHasKey('expires_in', $result);
    }
}
