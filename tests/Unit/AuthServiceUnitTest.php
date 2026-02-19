<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Services\AuthService;
use App\Services\JwtService;
use App\Services\RefreshTokenService;
use App\Services\UserService;
use PHPUnit\Framework\TestCase;

/**
 * @covers \App\Services\AuthService
 */
final class AuthServiceUnitTest extends TestCase
{
    public function testDeveRetornarFalhaQuandoUserServiceFalhaNoLogin(): void
    {
        $userService = $this->createMock(UserService::class);
        $userService->expects($this->once())
            ->method('login')
            ->with('a@test.local', 'wrong')
            ->willReturn(['success' => false, 'message' => 'Credenciais inválidas']);

        $jwt = $this->createMock(JwtService::class);
        $jwt->expects($this->never())->method('generateToken');

        $refresh = $this->createMock(RefreshTokenService::class);
        $refresh->expects($this->never())->method('createToken');

        $service = new AuthService($userService, $jwt, $refresh);

        $result = $service->login('a@test.local', 'wrong');

        $this->assertSame(['success' => false, 'message' => 'Credenciais inválidas'], $result);
    }

    public function testDeveRetornarRequire2faQuandoUsuarioExige2fa(): void
    {
        $userService = $this->createMock(UserService::class);
        $userService->expects($this->once())
            ->method('login')
            ->willReturn(['success' => true, 'require_2fa' => true, 'user_id' => 123]);

        $jwt = $this->createMock(JwtService::class);
        $jwt->expects($this->never())->method('generateToken');

        $refresh = $this->createMock(RefreshTokenService::class);
        $refresh->expects($this->never())->method('createToken');

        $service = new AuthService($userService, $jwt, $refresh);

        $result = $service->login('a@test.local', 'any');

        $this->assertSame(['success' => false, 'require_2fa' => true, 'user_id' => 123], $result);
    }

    public function testDeveRetornarTokensQuandoLoginOk(): void
    {
        $userService = $this->createMock(UserService::class);
        $userService->expects($this->once())
            ->method('login')
            ->willReturn([
                'success' => true,
                'user' => ['id' => 7, 'name' => 'User', 'email' => 'user@test.local', 'role' => 'admin'],
            ]);

        $jwt = $this->createMock(JwtService::class);
        $jwt->expects($this->once())
            ->method('generateToken')
            ->with(7, 900)
            ->willReturn('ACCESS');

        $refresh = $this->createMock(RefreshTokenService::class);
        $refresh->expects($this->once())
            ->method('createToken')
            ->with(7, 'device-1')
            ->willReturn('REFRESH');

        $service = new AuthService($userService, $jwt, $refresh);

        $result = $service->login('user@test.local', 'secret', 'device-1');

        $this->assertSame([
            'success' => true,
            'access_token' => 'ACCESS',
            'access_expires_in' => 900,
            'refresh_token' => 'REFRESH',
            'refresh_expires_days' => 30,
            'user' => ['id' => 7, 'name' => 'User', 'email' => 'user@test.local', 'role' => 'admin'],
        ], $result);
    }

    public function testDeveFalharRefreshQuandoTokenInvalido(): void
    {
        $userService = $this->createMock(UserService::class);

        $jwt = $this->createMock(JwtService::class);
        $jwt->expects($this->never())->method('generateToken');

        $refresh = $this->createMock(RefreshTokenService::class);
        $refresh->expects($this->once())
            ->method('validateAndRotate')
            ->with('bad')
            ->willReturn(null);

        $service = new AuthService($userService, $jwt, $refresh);

        $result = $service->refresh('bad');

        $this->assertSame(['success' => false, 'message' => 'Refresh token inválido ou expirado'], $result);
    }

    public function testDeveRetornarNovoAccessTokenQuandoRefreshOk(): void
    {
        $userService = $this->createMock(UserService::class);

        $jwt = $this->createMock(JwtService::class);
        $jwt->expects($this->once())
            ->method('generateToken')
            ->with(9, 900)
            ->willReturn('NEW_ACCESS');

        $refresh = $this->createMock(RefreshTokenService::class);
        $refresh->expects($this->once())
            ->method('validateAndRotate')
            ->with('good')
            ->willReturn(['user_id' => 9, 'refresh_token' => 'NEW_REFRESH']);

        $service = new AuthService($userService, $jwt, $refresh);

        $result = $service->refresh('good');

        $this->assertSame([
            'success' => true,
            'access_token' => 'NEW_ACCESS',
            'access_expires_in' => 900,
            'refresh_token' => 'NEW_REFRESH',
        ], $result);
    }

    public function testDeveRevogarRefreshTokenQuandoLogoutComToken(): void
    {
        $userService = $this->createMock(UserService::class);
        $jwt = $this->createMock(JwtService::class);

        $refresh = $this->createMock(RefreshTokenService::class);
        $refresh->expects($this->once())
            ->method('revokeToken')
            ->with('tok')
            ->willReturn(true);

        $service = new AuthService($userService, $jwt, $refresh);

        $this->assertSame(['success' => true], $service->logout('tok'));
    }

    public function testDeveRevogarTodosTokensQuandoLogoutComUserId(): void
    {
        $userService = $this->createMock(UserService::class);
        $jwt = $this->createMock(JwtService::class);

        $refresh = $this->createMock(RefreshTokenService::class);
        $refresh->expects($this->once())
            ->method('revokeAllForUser')
            ->with(5)
            ->willReturn(2);

        $service = new AuthService($userService, $jwt, $refresh);

        $this->assertSame(['success' => true, 'revoked' => 2], $service->logout(null, 5));
    }
}
