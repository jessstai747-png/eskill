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
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Options
$options = getopt('', ['alert', 'verbose', 'help']);

if (isset($options['help'])) {
    echo "Uso: php bin/clone-health-monitor.php [options]\n";
    echo "\nOpções:\n";
    echo "  --alert      Gerar alertas para problemas\n";
    echo "  --verbose    Mostrar detalhes\n";
    echo "  --help       Mostrar esta ajuda\n";
    exit(0);
}

$generateAlerts = isset($options['alert']);
$verbose = isset($options['verbose']);

$healthScore = 100;
$issues = [];
$warnings = [];

try {
    $db = Database::getInstance();

    // 1. Verificar jobs travados
    $stmt = $db->query("
        SELECT COUNT(*) as count
        FROM catalog_clone_jobs
        WHERE status = 'processing'
        AND updated_at < DATE_SUB(NOW(), INTERVAL 30 MINUTE)
    ");
    $stuckJobs = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    if ($stuckJobs > 0) {
        $healthScore -= 20;
        $issues[] = "⚠️  $stuckJobs job(s) travado(s) (>30 min sem update)";
    }

    // 2. Verificar taxa de falha recente (últimas 24h)
    $stmt = $db->query("
        SELECT 
            COUNT(*) as total,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed
        FROM catalog_clone_jobs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
    ");
    $recent = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($recent['total'] > 0) {
        $failureRate = ($recent['failed'] / $recent['total']) * 100;

        if ($failureRate > 50) {
            $healthScore -= 30;
            $issues[] = "🔴 Taxa de falha crítica: " . number_format($failureRate, 1) . "% (últimas 24h)";
        } elseif ($failureRate > 20) {
            $healthScore -= 15;
            $warnings[] = "🟡 Taxa de falha alta: " . number_format($failureRate, 1) . "% (últimas 24h)";
        }
    }

    // 3. Verificar workers inativos (nenhum job processado nas últimas 2 horas)
    $stmt = $db->query("
        SELECT MAX(updated_at) as last_update
        FROM catalog_clone_jobs
        WHERE status IN ('completed', 'failed')
    ");
    $lastUpdate = $stmt->fetch(PDO::FETCH_ASSOC)['last_update'];

    if ($lastUpdate) {
        $lastUpdateTime = strtotime($lastUpdate);
        $hoursSinceUpdate = (time() - $lastUpdateTime) / 3600;

        if ($hoursSinceUpdate > 2) {
            $healthScore -= 10;
            $warnings[] = "⚠️  Worker pode estar inativo (sem updates há " . number_format($hoursSinceUpdate, 1) . " horas)";
        }
    }

    // 4. Verificar acúmulo de jobs pendentes
    $stmt = $db->query("
        SELECT COUNT(*) as count
        FROM catalog_clone_jobs
        WHERE status = 'pending'
    ");
    $pendingJobs = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

    if ($pendingJobs > 50) {
        $healthScore -= 15;
        $warnings[] = "🟡 Alto acúmulo de jobs pendentes: $pendingJobs";
    } elseif ($pendingJobs > 100) {
        $healthScore -= 25;
        $issues[] = "🔴 Acúmulo crítico de jobs pendentes: $pendingJobs";
    }

    // 5. Verificar erros recorrentes
    $stmt = $db->query("
        SELECT error_message, COUNT(*) as count
        FROM catalog_clone_job_items
        WHERE status = 'failed'
        AND created_at >= DATE_SUB(NOW(), INTERVAL 1 HOUR)
        GROUP BY error_message
        HAVING count > 10
        ORDER BY count DESC
        LIMIT 1
    ");
    $recurringError = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($recurringError) {
        $healthScore -= 10;
        $warnings[] = "⚠️  Erro recorrente: " . substr($recurringError['error_message'], 0, 50) . " ({$recurringError['count']}x na última hora)";
    }

    // 6. Verificar espaço em disco (logs)
    $logsDir = __DIR__ . '/../storage/logs';
    $diskSpace = disk_free_space($logsDir);
    $diskSpaceGB = $diskSpace / (1024 * 1024 * 1024);

    if ($diskSpaceGB < 1) {
        $healthScore -= 20;
        $issues[] = "🔴 Espaço em disco crítico: " . number_format($diskSpaceGB, 2) . " GB livres";
    } elseif ($diskSpaceGB < 5) {
        $healthScore -= 10;
        $warnings[] = "🟡 Espaço em disco baixo: " . number_format($diskSpaceGB, 2) . " GB livres";
    }

    // Determinar status de saúde
    $healthStatus = '🟢 SAUDÁVEL';
    if ($healthScore < 50) {
        $healthStatus = '🔴 CRÍTICO';
    } elseif ($healthScore < 70) {
        $healthStatus = '🟡 ATENÇÃO';
    }

    // Gerar relatório
    echo "\n";
    echo "╔═══════════════════════════════════════════════════════════════╗\n";
    echo "║                                                               ║\n";
    echo "║     MONITORAMENTO DE SAÚDE - CLONAGEM DE ANÚNCIOS            ║\n";
    echo "║                                                               ║\n";
    echo "╚═══════════════════════════════════════════════════════════════╝\n";
    echo "\n";
    echo "Data/Hora: " . date('d/m/Y H:i:s') . "\n";
    echo "Status: $healthStatus\n";
    echo "Pontuação: $healthScore/100\n";
    echo "\n";

    if (!empty($issues)) {
        echo "┌───────────────────────────────────────────────────────────────┐\n";
        echo "│  PROBLEMAS CRÍTICOS                                           │\n";
        echo "└───────────────────────────────────────────────────────────────┘\n";
        echo "\n";
        foreach ($issues as $issue) {
            echo "$issue\n";
        }
        echo "\n";
    }

    if (!empty($warnings)) {
        echo "┌───────────────────────────────────────────────────────────────┐\n";
        echo "│  AVISOS                                                       │\n";
        echo "└───────────────────────────────────────────────────────────────┘\n";
        echo "\n";
        foreach ($warnings as $warning) {
            echo "$warning\n";
        }
        echo "\n";
    }

    if (empty($issues) && empty($warnings)) {
        echo "✅ Sistema funcionando normalmente. Nenhum problema detectado.\n";
        echo "\n";
    }

    // Métricas rápidas
    echo "┌───────────────────────────────────────────────────────────────┐\n";
    echo "│  MÉTRICAS RÁPIDAS                                             │\n";
    echo "└───────────────────────────────────────────────────────────────┘\n";
    echo "\n";
    echo "Jobs Travados:        $stuckJobs\n";
    echo "Jobs Pendentes:       $pendingJobs\n";
    if ($recent['total'] > 0) {
        echo "Taxa Falha (24h):     " . number_format(($recent['failed'] / $recent['total']) * 100, 1) . "%\n";
    }
    echo "Espaço Livre:         " . number_format($diskSpaceGB, 2) . " GB\n";
    echo "\n";

    // Salvar em log de saúde
    $healthLog = [
        'timestamp' => date('Y-m-d H:i:s'),
        'health_score' => $healthScore,
        'status' => $healthStatus,
        'issues_count' => count($issues),
        'warnings_count' => count($warnings),
        'metrics' => [
            'stuck_jobs' => $stuckJobs,
            'pending_jobs' => $pendingJobs,
            'failure_rate_24h' => $recent['total'] > 0 ? ($recent['failed'] / $recent['total']) * 100 : 0,
            'disk_space_gb' => $diskSpaceGB,
        ],
    ];

    // Salvar em tabela de health metrics (se existir)
    try {
        $stmt = $db->prepare("
            INSERT INTO clone_health_metrics 
            (timestamp, health_score, status, issues_count, warnings_count, metrics_json)
            VALUES (:timestamp, :score, :status, :issues, :warnings, :metrics)
        ");
        $stmt->execute([
            'timestamp' => $healthLog['timestamp'],
            'score' => $healthLog['health_score'],
            'status' => $healthStatus,
            'issues' => count($issues),
            'warnings' => count($warnings),
            'metrics' => json_encode($healthLog['metrics']),
        ]);
    } catch (Exception $e) {
        // Tabela pode não existir ainda, ignorar
    }

    // Gerar alertas se solicitado
    if ($generateAlerts && ($healthScore < 70 || !empty($issues))) {
        try {
            require_once __DIR__ . '/../app/Services/EmailService.php';
            $emailService = new \App\Services\EmailService();

            if ($emailService->isEnabled()) {
                $severity = $healthScore < 50 ? 'CRÍTICO' : ($healthScore < 70 ? 'ALERTA' : 'AVISO');
                $severityColor = $healthScore < 50 ? '#dc3545' : ($healthScore < 70 ? '#ffc107' : '#28a745');

                $subject = "🚨 [$severity] Monitor de Saúde - Clonagem de Anúncios";

                $issuesHtml = "";
                foreach ($issues as $issue) {
                    $icon = $issue['severity'] === 'critical' ? '🔴' : ($issue['severity'] === 'warning' ? '🟡' : '🟢');
                    $issuesHtml .= "<li>{$icon} <strong>{$issue['component']}:</strong> {$issue['message']}</li>";
                }

                $emailBody = "
                <html>
                <body style='font-family: Arial, sans-serif; margin: 20px;'>
                    <div style='background: $severityColor; color: white; padding: 20px; border-radius: 8px; margin-bottom: 20px; text-align: center;'>
                        <h1 style='margin: 0; font-size: 2em;'>🚨 ALERTA DE SAÚDE DO SISTEMA</h1>
                        <p style='margin: 10px 0 0 0; font-size: 1.2em;'>Nível: $severity | Pontuação: " . number_format($healthScore, 1) . "/100</p>
                    </div>
                    
                    <div style='background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                        <h3 style='color: #2c3e50; margin-top: 0;'>📊 Status Geral</h3>
                        <table style='width: 100%; border-collapse: collapse;'>
                            <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Jobs Ativos</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$healthStatus['active_jobs']}</td></tr>
                            <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Jobs Falhados (24h)</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$healthStatus['failed_jobs_24h']}</td></tr>
                            <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Jobs Presos</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$healthStatus['stuck_jobs']}</td></tr>
                            <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Uso de Memória</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>" . number_format($healthStatus['memory_usage'], 1) . "%</td></tr>
                            <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Último Backup</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$healthStatus['last_backup']}</td></tr>
                        </table>
                    </div>
                    
                    " . (!empty($issues) ? "
                    <div style='background: #fff3cd; border: 1px solid #ffeaa7; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                        <h3 style='color: #856404; margin-top: 0;'>⚠️ Problemas Detectados</h3>
                        <ul style='margin: 10px 0; padding-left: 20px;'>
                            $issuesHtml
                        </ul>
                    </div>
                    " : "") . "
                    
                    <div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 20px; border-radius: 8px; margin: 20px 0;'>
                        <h3 style='color: #155724; margin-top: 0;'>🔧 Ações Recomendadas</h3>
                        <ol style='margin: 10px 0; padding-left: 20px;'>
                            <li>Verificar o log de erros recentes: <code>tail -f storage/logs/app.log</code></li>
                            <li>Reiniciar serviços se necessário: <code>systemctl restart apache2 php-fpm</code></li>
                            <li>Verificar espaço em disco: <code>df -h</code></li>
                            <li>Monitorar performance em tempo real</li>
                            " . ($healthScore < 50 ? "<li><strong>Contatar equipe de infraestrutura imediatamente</strong></li>" : "") . "
                        </ol>
                    </div>
                    
                    <div style='background: #e2e3e5; padding: 15px; border-radius: 8px; margin: 30px 0; text-align: center;'>
                        <p style='margin: 0; color: #383d41;'><strong>Data do Alerta:</strong> " . date('d/m/Y H:i:s') . "</p>
                        <p style='margin: 5px 0 0 0; color: #383d41;'><strong>Servidor:</strong> " . ($_ENV['APP_NAME'] ?? 'Mercado Livre Manager') . "</p>
                    </div>
                    
                    <hr style='margin: 30px 0; border: none; border-top: 1px solid #ddd;'>
                    <p style='font-size: 12px; color: #6c757d; text-align: center;'>
                        Este é um alerta automático do Monitor de Saúde do Sistema.<br>
                        Para configurar as notificações, acesse o arquivo .env e defina EMAIL_ENABLED=true.
                    </p>
                </body>
                </html>";

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
                    sendTelegramAlert($healthScore, $issues);
                }
            } else {
                echo "⚠️ Email não configurado. Use EMAIL_ENABLED=true e configure as variáveis SMTP.\n";
            }
        } catch (Exception $e) {
            echo "❌ Erro ao enviar alertas: " . $e->getMessage() . "\n";
        }
    }

    // Exit code baseado na saúde
    exit($healthScore < 50 ? 1 : 0);
} catch (Exception $e) {
    echo "ERRO ao verificar saúde: " . $e->getMessage() . "\n";
    exit(1);
}

/**
 * Envia alerta via Telegram
 */
function sendTelegramAlert(float $healthScore, array $issues): void
{
    try {
        $botToken = $_ENV['TELEGRAM_BOT_TOKEN'] ?? '';
        $chatId = $_ENV['TELEGRAM_CHAT_ID'] ?? '';

        if (empty($botToken) || empty($chatId)) {
            return;
        }

        $severity = $healthScore < 50 ? '🔴 CRÍTICO' : ($healthScore < 70 ? '🟡 ALERTA' : '🟢 AVISO');

        $message = "*🚨 ALERTA DE SAÚDE DO SISTEMA*\n\n";
        $message .= "*Nível:* $severity\n";
        $message .= "*Pontuação:* " . number_format($healthScore, 1) . "/100\n";
        $message .= "*Data:* " . date('d/m/Y H:i:s') . "\n\n";

        if (!empty($issues)) {
            $message .= "*Problemas Detectados:*\n";
            foreach (array_slice($issues, 0, 5) as $issue) {
                $icon = $issue['severity'] === 'critical' ? '🔴' : ($issue['severity'] === 'warning' ? '🟡' : '🟢');
                $message .= "$icon {$issue['component']}: {$issue['message']}\n";
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
