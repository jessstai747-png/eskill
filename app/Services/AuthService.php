<?php

declare(strict_types=1);

namespace App\Services;

class AuthService
{
    private UserService $userService;
    private JwtService $jwt;
    private RefreshTokenService $refreshService;

    public function __construct(
        ?UserService $userService = null,
        ?JwtService $jwt = null,
        ?RefreshTokenService $refreshService = null
    )
    {
        $this->userService = $userService ?? new UserService();
        $this->jwt = $jwt ?? new JwtService();
        $this->refreshService = $refreshService ?? new RefreshTokenService();
    }

    /**
     * Attempt login and return tokens on success
     */
    public function login(string $email, string $password, ?string $deviceName = null): array
    {
        $result = $this->userService->login($email, $password);

        if (!$result['success']) {
            // propagate reason (avoid user enumeration: generic message)
            return ['success' => false, 'message' => $result['message'] ?? 'E-mail ou senha incorretos'];
        }

        if (!empty($result['require_2fa'])) {
            return ['success' => false, 'require_2fa' => true, 'user_id' => $result['user_id']];
        }

        $user = $result['user'];
        $userId = (int)$user['id'];

        // Create access token (short lived)
        $accessTtl = 15 * 60; // 15 minutes
        $accessToken = $this->jwt->generateToken($userId, $accessTtl);

        // Create refresh token (rotatable)
        $refreshToken = $this->refreshService->createToken($userId, $deviceName);

        return [
            'success' => true,
            'access_token' => $accessToken,
            'access_expires_in' => $accessTtl,
            'refresh_token' => $refreshToken,
            'refresh_expires_days' => 30,
            'user' => $user
        ];
    }

    /**
     * Refresh access token using refresh token (rotates refresh token)
     */
    public function refresh(string $refreshToken): array
    {
        $rotated = $this->refreshService->validateAndRotate($refreshToken);
        if (!$rotated) {
            return ['success' => false, 'message' => 'Refresh token inválido ou expirado'];
        }

        $userId = (int)$rotated['user_id'];

        $accessTtl = 15 * 60;
        $accessToken = $this->jwt->generateToken($userId, $accessTtl);

        return [
            'success' => true,
            'access_token' => $accessToken,
            'access_expires_in' => $accessTtl,
            'refresh_token' => $rotated['refresh_token']
        ];
    }

    /**
     * Logout: revoke provided refresh token or all tokens for user
     */
    public function logout(?string $refreshToken = null, ?int $userId = null): array
    {
        if ($refreshToken) {
            $ok = $this->refreshService->revokeToken($refreshToken);
            return ['success' => (bool)$ok];
        }

        if ($userId) {
            $count = $this->refreshService->revokeAllForUser($userId);
            return ['success' => true, 'revoked' => $count];
        }

        return ['success' => false, 'message' => 'refresh_token or user_id required'];
    }
}
