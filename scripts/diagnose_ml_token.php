<?php

/**
 * Script de diagnóstico para tokens do Mercado Livre
 * Verifica o estado dos tokens e tenta renovar se necessário
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente
$envFile = __DIR__ . '/../.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos(trim($line), '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($name, $value) = explode('=', $line, 2);
            $_ENV[trim($name)] = trim($value);
            putenv(trim($name) . '=' . trim($value));
        }
    }
}

use App\Database;
use App\Services\MercadoLivreAuthService;
use App\Services\SecurityService;

echo "=== Diagnóstico de Tokens do Mercado Livre ===\n\n";

try {
    $db = Database::getInstance();
    $security = new SecurityService();
    $authService = new MercadoLivreAuthService();

    // Buscar todas as contas
    $stmt = $db->query("
        SELECT id, ml_user_id, nickname, status, token_expires_at, 
               access_token, refresh_token, created_at, updated_at
        FROM ml_accounts
        ORDER BY id
    ");

    $accounts = $stmt->fetchAll();

    if (empty($accounts)) {
        echo "❌ Nenhuma conta do Mercado Livre encontrada no banco de dados.\n";
        echo "   Por favor, conecte uma conta em /auth/authorize\n";
        exit(1);
    }

    echo "Encontradas " . count($accounts) . " conta(s):\n\n";

    foreach ($accounts as $account) {
        echo "─────────────────────────────────────────\n";
        echo "📦 Conta ID: {$account['id']}\n";
        echo "   ML User ID: {$account['ml_user_id']}\n";
        echo "   Nickname: {$account['nickname']}\n";
        echo "   Status: {$account['status']}\n";
        echo "   Criada em: {$account['created_at']}\n";
        echo "   Atualizada em: {$account['updated_at']}\n";

        // Verificar expiração do token
        $expiresAt = strtotime($account['token_expires_at']);
        $now = time();
        $diff = $expiresAt - $now;

        if ($diff < 0) {
            echo "   ⚠️  Token EXPIRADO há " . abs(round($diff / 60)) . " minutos\n";
        } elseif ($diff < 300) {
            echo "   ⚠️  Token expira em " . round($diff / 60) . " minutos (quase expirando)\n";
        } else {
            echo "   ✅ Token válido por mais " . round($diff / 3600, 1) . " horas\n";
        }

        // Verificar se os tokens estão presentes e criptografados
        $hasAccessToken = !empty($account['access_token']);
        $hasRefreshToken = !empty($account['refresh_token']);

        echo "   Access Token: " . ($hasAccessToken ? "✅ Presente" : "❌ Ausente") . "\n";
        echo "   Refresh Token: " . ($hasRefreshToken ? "✅ Presente" : "❌ Ausente") . "\n";

        // Tentar descriptografar
        if ($hasAccessToken) {
            try {
                $decrypted = $security->decrypt($account['access_token']);
                $tokenLength = strlen($decrypted);
                echo "   Access Token decriptografado: ✅ OK ({$tokenLength} chars)\n";
            } catch (\Exception $e) {
                echo "   Access Token decriptografado: ❌ ERRO - " . $e->getMessage() . "\n";
            }
        }

        if ($hasRefreshToken) {
            try {
                $decrypted = $security->decrypt($account['refresh_token']);
                $tokenLength = strlen($decrypted);
                echo "   Refresh Token decriptografado: ✅ OK ({$tokenLength} chars)\n";
            } catch (\Exception $e) {
                echo "   Refresh Token decriptografado: ❌ ERRO - " . $e->getMessage() . "\n";
            }
        }

        // Perguntar se quer tentar renovar
        if ($account['status'] === 'active' && $hasRefreshToken) {
            echo "\n   Tentando renovar token...\n";

            $result = $authService->refreshToken($account['id']);

            if ($result) {
                echo "   ✅ Token renovado com SUCESSO!\n";

                // Verificar novo token
                $stmt2 = $db->prepare("SELECT token_expires_at FROM ml_accounts WHERE id = ?");
                $stmt2->execute([$account['id']]);
                $newExpiry = $stmt2->fetchColumn();
                echo "   Nova expiração: {$newExpiry}\n";
            } else {
                echo "   ❌ FALHA ao renovar token\n";
                echo "   O refresh_token pode estar inválido ou revogado.\n";
                echo "   Solução: Reconecte a conta em /auth/authorize\n";
            }
        }

        echo "\n";
    }

    echo "─────────────────────────────────────────\n";
    echo "\n=== Diagnóstico Concluído ===\n";
} catch (\Exception $e) {
    echo "❌ Erro durante diagnóstico: " . $e->getMessage() . "\n";
    echo "Stack trace:\n" . $e->getTraceAsString() . "\n";
    exit(1);
}
