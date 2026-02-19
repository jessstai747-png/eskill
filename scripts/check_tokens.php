<?php

/**
 * Script para verificar tokens do Mercado Livre expirando
 * Executar via CRON: 0 9 * * * php scripts/check_tokens.php
 */

require_once __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente
if (file_exists(__DIR__ . '/../.env')) {
    $dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
    $dotenv->load();
}

use App\Services\TelegramService;
use App\Services\MercadoLivreAuthService;

echo "==============================================\n";
echo "Verificação de Tokens ML: " . date('Y-m-d H:i:s') . "\n";
echo "==============================================\n\n";

try {
    $db = App\Database::getInstance();

    // Buscar tokens que vão expirar em 7 dias
    $stmt = $db->prepare("
        SELECT 
            id,
            user_id,
            ml_user_id,
            nickname,
            email,
            token_expires_at,
            TIMESTAMPDIFF(DAY, NOW(), token_expires_at) as days_until_expiry,
            status
        FROM ml_accounts 
        WHERE status = 'active'
        AND token_expires_at IS NOT NULL
        ORDER BY token_expires_at ASC
    ");
    $stmt->execute();
    $accounts = $stmt->fetchAll();

    $expiringAccounts = [];
    $expiredAccounts = [];

    foreach ($accounts as $account) {
        $daysLeft = (int)$account['days_until_expiry'];

        if ($daysLeft <= 0) {
            $expiredAccounts[] = $account;
            echo "❌ EXPIRADO: {$account['nickname']} (ML ID: {$account['ml_user_id']})\n";
            echo "   Expirou em: {$account['token_expires_at']}\n\n";
        } elseif ($daysLeft <= 7) {
            $expiringAccounts[] = $account;
            echo "⚠️ EXPIRANDO: {$account['nickname']} (ML ID: {$account['ml_user_id']})\n";
            echo "   Expira em: {$account['token_expires_at']} ({$daysLeft} dias)\n\n";
        } else {
            echo "✅ OK: {$account['nickname']} - expira em {$daysLeft} dias\n";
        }
    }

    // Tentar renovar tokens automaticamente
    $renewed = 0;
    foreach ($expiringAccounts as $account) {
        echo "\n🔄 Tentando renovar token de {$account['nickname']}...\n";

        try {
            // Buscar refresh_token
            $stmt = $db->prepare("SELECT refresh_token FROM ml_accounts WHERE id = ?");
            $stmt->execute([$account['id']]);
            $data = $stmt->fetch();

            if (!empty($data['refresh_token'])) {
                $authService = new MercadoLivreAuthService();
                $result = $authService->refreshToken($data['refresh_token']);

                if (!empty($result['access_token'])) {
                    // Atualizar tokens no banco
                    $expiresAt = date('Y-m-d H:i:s', time() + ($result['expires_in'] ?? 21600));

                    $updateStmt = $db->prepare("
                        UPDATE ml_accounts 
                        SET access_token = ?,
                            refresh_token = ?,
                            token_expires_at = ?,
                            updated_at = NOW()
                        WHERE id = ?
                    ");
                    $updateStmt->execute([
                        $result['access_token'],
                        $result['refresh_token'] ?? $data['refresh_token'],
                        $expiresAt,
                        $account['id']
                    ]);

                    echo "   ✅ Token renovado com sucesso! Novo vencimento: {$expiresAt}\n";
                    $renewed++;
                } else {
                    echo "   ❌ Falha ao renovar - resposta inválida\n";
                }
            } else {
                echo "   ❌ Sem refresh_token disponível\n";
            }
        } catch (Exception $e) {
            echo "   ❌ Erro: " . $e->getMessage() . "\n";
        }
    }

    // Enviar notificação via Telegram se houver problemas
    $telegram = new TelegramService();

    if (count($expiredAccounts) > 0 || (count($expiringAccounts) - $renewed) > 0) {
        $message = "🔔 <b>Alerta de Tokens ML</b>\n\n";

        if (count($expiredAccounts) > 0) {
            $message .= "❌ <b>Tokens Expirados:</b>\n";
            foreach ($expiredAccounts as $acc) {
                $message .= "• {$acc['nickname']}\n";
            }
            $message .= "\n";
        }

        $stillExpiring = count($expiringAccounts) - $renewed;
        if ($stillExpiring > 0) {
            $message .= "⚠️ <b>Tokens Expirando (precisam re-autorização):</b>\n";
            foreach ($expiringAccounts as $acc) {
                $message .= "• {$acc['nickname']} ({$acc['days_until_expiry']} dias)\n";
            }
            $message .= "\n";
        }

        if ($renewed > 0) {
            $message .= "✅ <b>{$renewed} token(s) renovado(s) automaticamente</b>\n";
        }

        $message .= "\n🔗 Acesse o dashboard para re-autorizar: " . ($_ENV['APP_URL'] ?? 'https://eskill.com.br') . "/dashboard";

        if ($telegram->isEnabled()) {
            $telegram->sendMessage($message);
            echo "\n📱 Notificação enviada via Telegram\n";
        } else {
            echo "\n📱 Telegram não configurado - notificação não enviada\n";
        }
    }

    // Resumo
    echo "\n==============================================\n";
    echo "RESUMO:\n";
    echo "  Total de contas: " . count($accounts) . "\n";
    echo "  Tokens expirados: " . count($expiredAccounts) . "\n";
    echo "  Tokens expirando: " . count($expiringAccounts) . "\n";
    echo "  Tokens renovados: {$renewed}\n";
    echo "==============================================\n";
} catch (Exception $e) {
    echo "❌ Erro: " . $e->getMessage() . "\n";
    error_log("check_tokens.php error: " . $e->getMessage());
    exit(1);
}
