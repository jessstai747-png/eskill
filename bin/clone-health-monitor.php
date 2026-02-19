#!/usr/bin/env php
<?php
/**
 * Clone Health Monitor
 * 
 * Monitora saúde do sistema de clonagem e gera alertas
 * 
 * Uso:
 *   php bin/clone-health-monitor.php
 *   php bin/clone-health-monitor.php --alert
 *   php bin/clone-health-monitor.php --json
 *   php bin/clone-health-monitor.php --account-id=123
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\CloneHealthMonitorService;
use App\Services\EmailService;
use App\Services\MercadoLivreClient;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Options
$options = getopt('', ['alert', 'verbose', 'json', 'help', 'account-id:']);

if (isset($options['help'])) {
    echo "Uso: php bin/clone-health-monitor.php [options]\n";
    echo "\nOpções:\n";
    echo "  --alert      Gerar alertas para problemas\n";
    echo "  --verbose    Mostrar detalhes\n";
    echo "  --json       Output JSON (útil para cron/integrações)\n";
    echo "  --account-id Filtrar por conta de destino (target_account_id)\n";
    echo "  --help       Mostrar esta ajuda\n";
    exit(0);
}

$generateAlerts = isset($options['alert']);
$verbose = isset($options['verbose']);
$jsonOutput = isset($options['json']);

$accountId = 0;
if (isset($options['account-id'])) {
    $accountId = (int) $options['account-id'];
    if ($accountId < 0) {
        $accountId = 0;
    }
}

// Criar ML client para diagnóstico real da API (best-effort)
$mlClient = null;
try {
    $mlClient = new MercadoLivreClient($accountId > 0 ? $accountId : null);
} catch (\Throwable $e) {
    // ML client indisponível — fallback para logs DB
    if ($verbose) {
        echo "[info] ML client indisponível: {$e->getMessage()}\n";
    }
}

try {
    $service = new CloneHealthMonitorService($accountId, null, $mlClient);
    $health = $service->getSystemHealth();

    // Persistir check (best-effort)
    $service->logHealthCheck($health);

    $status = (string) ($health['status'] ?? 'unknown');
    $score = (int) ($health['score'] ?? 0);
    $issues = $health['issues'] ?? [];

    if ($jsonOutput) {
        echo json_encode($health, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    } else {
        // Gerar relatório
        echo "\n";
        echo "╔═══════════════════════════════════════════════════════════════╗\n";
        echo "║                                                               ║\n";
        echo "║     MONITORAMENTO DE SAÚDE - CLONAGEM DE ANÚNCIOS            ║\n";
        echo "║                                                               ║\n";
        echo "╚═══════════════════════════════════════════════════════════════╝\n";
        echo "\n";
        echo "Data/Hora: " . date('d/m/Y H:i:s') . "\n";
        if ($accountId > 0) {
            echo "Conta:    #{$accountId}\n";
        }
        echo "Status:   " . formatOverallStatus($status) . "\n";
        echo "Score:    {$score}/100\n";
        echo "\n";

        if (!empty($issues)) {
            echo "┌───────────────────────────────────────────────────────────────┐\n";
            echo "│  ISSUES                                                       │\n";
            echo "└───────────────────────────────────────────────────────────────┘\n";
            echo "\n";
            foreach ($issues as $issue) {
                $sev = (string) ($issue['severity'] ?? 'warning');
                $component = (string) ($issue['component'] ?? 'unknown');
                $message = (string) ($issue['message'] ?? '');
                echo sprintf("  %s [%s] %s\n", $sev === 'critical' ? '🔴' : '🟡', $component, $message);
            }
            echo "\n";
        } else {
            echo "✅ Sistema funcionando normalmente. Nenhum problema detectado.\n\n";
        }

        echo "┌───────────────────────────────────────────────────────────────┐\n";
        echo "│  CHECKS                                                      │\n";
        echo "└───────────────────────────────────────────────────────────────┘\n";
        echo "\n";

        $checks = $health['checks'] ?? [];
        foreach ($checks as $key => $check) {
            $checkStatus = (string) ($check['status'] ?? 'unknown');
            $value = $check['value'] ?? null;
            $label = str_replace('_', ' ', (string) $key);
            echo sprintf(
                "  - %-20s | %-8s | %s\n",
                $label,
                strtoupper($checkStatus),
                $value === null ? 'N/A' : (string) $value
            );
            if ($verbose && !empty($check['message'])) {
                echo "      " . (string) $check['message'] . "\n";
            }
        }

        echo "\n";
    }
    // Gerar alertas se solicitado
    if ($generateAlerts && ($status === 'critical' || $status === 'warning')) {
        try {
            $emailService = new EmailService();

            if ($emailService->isEnabled()) {
                $severity = $status === 'critical' ? 'CRÍTICO' : 'ATENÇÃO';
                $subject = "🚨 [{$severity}] Monitor de Saúde - Clonagem";

                $issuesHtml = '';
                foreach (array_slice($issues, 0, 20) as $issue) {
                    $sev = (string) ($issue['severity'] ?? 'warning');
                    $component = (string) ($issue['component'] ?? 'unknown');
                    $message = (string) ($issue['message'] ?? '');
                    $icon = $sev === 'critical' ? '🔴' : '🟡';
                    $issuesHtml .= "<li>{$icon} <strong>" . htmlspecialchars($component, ENT_QUOTES, 'UTF-8') . ":</strong> " . htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . "</li>";
                }

                $checksHtml = '';
                foreach (($health['checks'] ?? []) as $key => $check) {
                    $label = str_replace('_', ' ', (string) $key);
                    $value = $check['value'] ?? 'N/A';
                    $checkStatus = strtoupper((string) ($check['status'] ?? 'unknown'));
                    $checksHtml .= "<tr><td style='padding:8px;border:1px solid #ddd;'><strong>" . htmlspecialchars($label, ENT_QUOTES, 'UTF-8') . "</strong></td><td style='padding:8px;border:1px solid #ddd;'>" . htmlspecialchars($checkStatus, ENT_QUOTES, 'UTF-8') . "</td><td style='padding:8px;border:1px solid #ddd;'>" . htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8') . "</td></tr>";
                }

                $emailBody = "<!DOCTYPE html><html><body style='font-family: Arial, sans-serif; margin: 20px;'>
                    <h2>🚨 Monitor de Saúde — Clonagem</h2>
                    <p><strong>Status:</strong> " . htmlspecialchars($status, ENT_QUOTES, 'UTF-8') . "<br>
                    <strong>Score:</strong> {$score}/100<br>
                    <strong>Conta:</strong> " . ($accountId > 0 ? (string) $accountId : 'todas') . "<br>
                    <strong>Data:</strong> " . htmlspecialchars(date('d/m/Y H:i:s'), ENT_QUOTES, 'UTF-8') . "</p>

                    <h3>Checks</h3>
                    <table style='width:100%;border-collapse:collapse;'>
                        <tr><th style='text-align:left;padding:8px;border:1px solid #ddd;'>Check</th><th style='text-align:left;padding:8px;border:1px solid #ddd;'>Status</th><th style='text-align:left;padding:8px;border:1px solid #ddd;'>Valor</th></tr>
                        {$checksHtml}
                    </table>

                    <h3>Issues</h3>
                    <ul>{$issuesHtml}</ul>

                    <p style='font-size: 12px; color: #666;'>Alerta automático do sistema.</p>
                </body></html>";

                // Enviar para emails de alerta configurados
                $alertEmails = array_filter(array_map('trim', explode(',', $_ENV['ALERT_EMAILS'] ?? '')));
                if (empty($alertEmails)) {
                    $alertEmails = [$_ENV['EMAIL_REPLY_TO'] ?? 'admin@eskill.com.br'];
                }

                $successCount = 0;
                foreach ($alertEmails as $email) {
                    if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        if ($emailService->send($email, $subject, $emailBody, 'html')) {
                            $successCount++;
                        }
                    }
                }

                if ($successCount > 0) {
                    echo "✅ Alertas enviados por email para $successCount destinatário(s)\n";
                } else {
                    echo "❌ Falha ao enviar alertas por email\n";
                }

                // Tentar enviar alerta via Telegram se configurado
                if (($_ENV['TELEGRAM_ENABLED'] ?? false) && !empty($_ENV['TELEGRAM_BOT_TOKEN'])) {
                    sendTelegramAlert($score, $status, $issues, $accountId);
                }
            } else {
                echo "⚠️ Email não configurado. Use EMAIL_ENABLED=true e configure as variáveis SMTP.\n";
            }
        } catch (Exception $e) {
            echo "❌ Erro ao enviar alertas: " . $e->getMessage() . "\n";
        }
    }

    // Exit code baseado na saúde
    exit($status === 'critical' ? 1 : 0);
} catch (Exception $e) {
    echo "ERRO ao verificar saúde: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Envia alerta via Telegram
 */
function sendTelegramAlert(int $score, string $status, array $issues, int $accountId = 0): void
{
    try {
        $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
        $chatId = $_ENV['TELEGRAM_CHAT_ID'] ?? '';

        if (empty($botToken) || empty($chatId)) {
            return;
        }

        $severity = $status === 'critical' ? '🔴 CRÍTICO' : ($status === 'warning' ? '🟡 ATENÇÃO' : '🟢 OK');

        $message = "*🚨 ALERTA DE SAÚDE DO SISTEMA*\n\n";
        $message .= "*Nível:* $severity\n";
        $message .= "*Pontuação:* " . number_format((float) $score, 0) . "/100\n";
        $message .= "*Data:* " . date('d/m/Y H:i:s') . "\n\n";
        if ($accountId > 0) {
            $message .= "*Conta:* #{$accountId}\n\n";
        }

        if (!empty($issues)) {
            $message .= "*Problemas Detectados:*\n";
            foreach (array_slice($issues, 0, 5) as $issue) {
                $sev = (string) ($issue['severity'] ?? 'warning');
                $component = (string) ($issue['component'] ?? 'unknown');
                $msg = (string) ($issue['message'] ?? '');
                $icon = $sev === 'critical' ? '🔴' : '🟡';
                $message .= "$icon {$component}: {$msg}\n";
            }

            if (count($issues) > 5) {
                $message .= "... e mais " . (count($issues) - 5) . " problemas\n";
            }
        }

        $message .= "\n*Ações:* Verificar logs do sistema";

        $url = "https://api.telegram.org/bot$botToken/sendMessage";
        $data = [
            'chat_id' => $chatId,
            'text' => $message,
            'parse_mode' => 'Markdown',
            'disable_web_page_preview' => true
        ];

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode === 200) {
            echo "✅ Alerta enviado via Telegram\n";
        } else {
            echo "⚠️ Falha ao enviar alerta via Telegram (HTTP $httpCode)\n";
        }
    } catch (Exception $e) {
        echo "⚠️ Erro ao enviar Telegram: " . $e->getMessage() . "\n";
    }
}

function formatOverallStatus(string $status): string
{
    return match ($status) {
        'healthy' => '🟢 SAUDÁVEL',
        'warning' => '🟡 ATENÇÃO',
        'critical' => '🔴 CRÍTICO',
        default => '⚪ INDEFINIDO',
    };
}
