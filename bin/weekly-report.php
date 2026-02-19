#!/usr/bin/env php
<?php
declare(strict_types=1);

/**
 * Auth Failure Monitor - Weekly Report Generator
 * Gera e envia relatório semanal por email
 */

require_once __DIR__ . '/../vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->load();

// Configuração
$dbHost = $_ENV['DB_HOST'] ?? 'localhost';
$dbName = $_ENV['DB_DATABASE'] ?? 'meli';
$dbUser = $_ENV['DB_USERNAME'] ?? 'root';
$dbPass = $_ENV['DB_PASSWORD'] ?? '';

try {
    $db = new PDO("mysql:host=$dbHost;dbname=$dbName;charset=utf8mb4", $dbUser, $dbPass);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    echo "❌ Erro de conexão: {$e->getMessage()}\n";
    exit(1);
}

echo "🔍 Gerando relatório semanal...\n\n";

// Período do relatório (últimos 7 dias)
$startDate = date('Y-m-d', strtotime('-7 days'));
$endDate = date('Y-m-d');

// 1. Estatísticas Gerais
$stmt = $db->query("
    SELECT 
        COUNT(DISTINCT ip_address) as unique_ips,
        COUNT(*) as total_failures
    FROM auth_failure_log
    WHERE detected_at >= '$startDate'
");
$generalStats = $stmt->fetch(PDO::FETCH_ASSOC);

$stmt = $db->query("
    SELECT COUNT(*) as total_blocks
    FROM auth_blocked_ips
    WHERE blocked_at >= '$startDate'
");
$blocksThisWeek = $stmt->fetch(PDO::FETCH_ASSOC)['total_blocks'];

// 2. Top 20 IPs Atacantes
$stmt = $db->query("
    SELECT 
        ip_address,
        COUNT(*) as attempts,
        MIN(detected_at) as first_seen,
        MAX(detected_at) as last_seen
    FROM auth_failure_log
    WHERE detected_at >= '$startDate'
    GROUP BY ip_address
    ORDER BY attempts DESC
    LIMIT 20
");
$topAttackers = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 3. Estatísticas por Dia
$stmt = $db->query("
    SELECT 
        DATE(detected_at) as date,
        COUNT(*) as failures,
        COUNT(DISTINCT ip_address) as unique_ips
    FROM auth_failure_log
    WHERE detected_at >= '$startDate'
    GROUP BY DATE(detected_at)
    ORDER BY date
");
$dailyStats = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 4. Estatísticas por Tipo de Falha
$stmt = $db->query("
    SELECT 
        failure_type,
        COUNT(*) as count
    FROM auth_failure_log
    WHERE detected_at >= '$startDate' AND failure_type IS NOT NULL
    GROUP BY failure_type
    ORDER BY count DESC
");
$failureTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 5. Padrões de Horário (horas com mais ataques)
$stmt = $db->query("
    SELECT 
        HOUR(detected_at) as hour,
        COUNT(*) as count
    FROM auth_failure_log
    WHERE detected_at >= '$startDate'
    GROUP BY HOUR(detected_at)
    ORDER BY count DESC
    LIMIT 5
");
$peakHours = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 6. IPs Bloqueados Esta Semana
$stmt = $db->query("
    SELECT 
        ip_address,
        failure_count,
        blocked_at,
        expires_at,
        is_permanent
    FROM auth_blocked_ips
    WHERE blocked_at >= '$startDate'
    ORDER BY failure_count DESC
    LIMIT 20
");
$blockedIPs = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Calcular tendências (comparar com semana anterior)
$prevStartDate = date('Y-m-d', strtotime('-14 days'));
$prevEndDate = date('Y-m-d', strtotime('-7 days'));

$stmt = $db->query("
    SELECT COUNT(*) as prev_failures
    FROM auth_failure_log
    WHERE detected_at >= '$prevStartDate' AND detected_at < '$prevEndDate'
");
$prevFailures = $stmt->fetch(PDO::FETCH_ASSOC)['prev_failures'];

$trend = $prevFailures > 0 
    ? round((($generalStats['total_failures'] - $prevFailures) / $prevFailures) * 100, 1)
    : 0;

$trendIcon = $trend > 0 ? '📈' : ($trend < 0 ? '📉' : '➡️');
$trendText = $trend > 0 ? "aumento" : ($trend < 0 ? "redução" : "estável");

// Gerar HTML do Relatório
$html = "
<!DOCTYPE html>
<html>
<head>
    <meta charset=\"UTF-8\">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 800px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; text-align: center; }
        .stats-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 20px; margin: 30px 0; }
        .stat-card { background: #f7f7f7; padding: 20px; border-radius: 8px; text-align: center; }
        .stat-number { font-size: 36px; font-weight: bold; color: #667eea; }
        .stat-label { font-size: 14px; color: #666; margin-top: 5px; }
        .section { margin: 30px 0; }
        .section-title { font-size: 20px; font-weight: bold; color: #667eea; border-bottom: 2px solid #667eea; padding-bottom: 10px; margin-bottom: 20px; }
        table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        th { background: #667eea; color: white; padding: 12px; text-align: left; }
        td { padding: 10px; border-bottom: 1px solid #ddd; }
        tr:hover { background: #f5f5f5; }
        .trend { font-size: 18px; font-weight: bold; }
        .trend.up { color: #e74c3c; }
        .trend.down { color: #27ae60; }
        .bar { background: #667eea; height: 20px; border-radius: 3px; display: inline-block; }
        .footer { text-align: center; color: #999; margin-top: 40px; padding-top: 20px; border-top: 1px solid #ddd; }
    </style>
</head>
<body>
    <div class=\"container\">
        <div class=\"header\">
            <h1>🛡️ Auth Failure Monitor</h1>
            <h2>Relatório Semanal de Segurança</h2>
            <p>Período: $startDate a $endDate</p>
        </div>

        <div class=\"stats-grid\">
            <div class=\"stat-card\">
                <div class=\"stat-number\">" . number_format($generalStats['total_failures']) . "</div>
                <div class=\"stat-label\">Tentativas de Ataque</div>
            </div>
            <div class=\"stat-card\">
                <div class=\"stat-number\">" . number_format($generalStats['unique_ips']) . "</div>
                <div class=\"stat-label\">IPs Únicos</div>
            </div>
            <div class=\"stat-card\">
                <div class=\"stat-number\">" . number_format($blocksThisWeek) . "</div>
                <div class=\"stat-label\">IPs Bloqueados</div>
            </div>
        </div>

        <div class=\"section\">
            <div class=\"section-title\">📊 Tendência Semanal</div>
            <p class=\"trend " . ($trend > 0 ? 'up' : 'down') . "\">
                $trendIcon " . abs($trend) . "% de $trendText comparado à semana anterior
            </p>
        </div>

        <div class=\"section\">
            <div class=\"section-title\">🎯 Top 20 IPs Atacantes</div>
            <table>
                <thead>
                    <tr>
                        <th>#</th>
                        <th>IP Address</th>
                        <th>Tentativas</th>
                        <th>Primeira Detecção</th>
                        <th>Última Detecção</th>
                    </tr>
                </thead>
                <tbody>";

foreach ($topAttackers as $idx => $attacker) {
    $html .= "
                    <tr>
                        <td>" . ($idx + 1) . "</td>
                        <td><strong>{$attacker['ip_address']}</strong></td>
                        <td>" . number_format($attacker['attempts']) . "</td>
                        <td>" . date('d/m H:i', strtotime($attacker['first_seen'])) . "</td>
                        <td>" . date('d/m H:i', strtotime($attacker['last_seen'])) . "</td>
                    </tr>";
}

$html .= "
                </tbody>
            </table>
        </div>

        <div class=\"section\">
            <div class=\"section-title\">📅 Atividade Diária</div>
            <table>
                <thead>
                    <tr>
                        <th>Data</th>
                        <th>Falhas</th>
                        <th>IPs Únicos</th>
                        <th>Gráfico</th>
                    </tr>
                </thead>
                <tbody>";

$maxFailures = max(array_column($dailyStats, 'failures'));
foreach ($dailyStats as $day) {
    $barWidth = ($day['failures'] / $maxFailures) * 300;
    $html .= "
                    <tr>
                        <td>" . date('d/m/Y', strtotime($day['date'])) . "</td>
                        <td>" . number_format($day['failures']) . "</td>
                        <td>" . number_format($day['unique_ips']) . "</td>
                        <td><div class=\"bar\" style=\"width: {$barWidth}px;\"></div></td>
                    </tr>";
}

$html .= "
                </tbody>
            </table>
        </div>

        <div class=\"section\">
            <div class=\"section-title\">🕐 Horários de Pico</div>
            <table>
                <thead>
                    <tr>
                        <th>Hora</th>
                        <th>Tentativas</th>
                    </tr>
                </thead>
                <tbody>";

foreach ($peakHours as $hour) {
    $html .= "
                    <tr>
                        <td>{$hour['hour']}:00 - {$hour['hour']}:59</td>
                        <td>" . number_format($hour['count']) . "</td>
                    </tr>";
}

$html .= "
                </tbody>
            </table>
        </div>";

if (!empty($failureTypes)) {
    $html .= "
        <div class=\"section\">
            <div class=\"section-title\">⚠️ Tipos de Falha</div>
            <table>
                <thead>
                    <tr>
                        <th>Tipo</th>
                        <th>Quantidade</th>
                    </tr>
                </thead>
                <tbody>";
    
    foreach ($failureTypes as $type) {
        $html .= "
                    <tr>
                        <td>{$type['failure_type']}</td>
                        <td>" . number_format($type['count']) . "</td>
                    </tr>";
    }
    
    $html .= "
                </tbody>
            </table>
        </div>";
}

if (!empty($blockedIPs)) {
    $html .= "
        <div class=\"section\">
            <div class=\"section-title\">🚫 IPs Bloqueados Esta Semana</div>
            <table>
                <thead>
                    <tr>
                        <th>IP Address</th>
                        <th>Falhas</th>
                        <th>Bloqueado Em</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>";
    
    foreach ($blockedIPs as $ip) {
        $status = $ip['is_permanent'] 
            ? '🔒 Permanente' 
            : (strtotime($ip['expires_at']) > time() ? '⏳ Ativo' : '✅ Expirado');
        
        $html .= "
                    <tr>
                        <td><strong>{$ip['ip_address']}</strong></td>
                        <td>" . number_format($ip['failure_count']) . "</td>
                        <td>" . date('d/m/Y H:i', strtotime($ip['blocked_at'])) . "</td>
                        <td>$status</td>
                    </tr>";
    }
    
    $html .= "
                </tbody>
            </table>
        </div>";
}

$html .= "
        <div class=\"footer\">
            <p>Relatório gerado automaticamente pelo Auth Failure Monitor</p>
            <p>" . date('d/m/Y H:i:s') . "</p>
        </div>
    </div>
</body>
</html>";

// Enviar por email
echo "📧 Enviando relatório por email...\n";

$mail = new PHPMailer(true);

try {
    // Configuração SMTP
    $mail->isSMTP();
    $mail->Host = $_ENV['SMTP_HOST'] ?? 'email-ssl.com.br';
    $mail->SMTPAuth = true;
    $mail->Username = $_ENV['SMTP_USERNAME'] ?? 'eskill@jessestain.com.br';
    $mail->Password = $_ENV['SMTP_PASSWORD'] ?? '';
    $mail->SMTPSecure = $_ENV['SMTP_SECURE'] ?? PHPMailer::ENCRYPTION_SMTPS;
    $mail->Port = (int)($_ENV['SMTP_PORT'] ?? 465);
    $mail->CharSet = 'UTF-8';

    // Remetente e destinatário
    $mail->setFrom('noreply@eskill.com.br', 'Auth Monitor');
    $mail->addAddress($_ENV['ADMIN_EMAIL'] ?? 'eskill@jessestain.com.br');

    // Conteúdo
    $mail->isHTML(true);
    $mail->Subject = "🛡️ Relatório Semanal de Segurança - " . date('d/m/Y');
    $mail->Body = $html;
    $mail->AltBody = strip_tags($html);

    $mail->send();
    echo "✅ Relatório enviado com sucesso!\n";
    
    // Salvar cópia local
    $reportFile = __DIR__ . '/../storage/reports/weekly-' . date('Y-m-d') . '.html';
    @mkdir(dirname($reportFile), 0755, true);
    file_put_contents($reportFile, $html);
    echo "📄 Cópia salva em: $reportFile\n";
    
} catch (Exception $e) {
    echo "❌ Erro ao enviar email: {$mail->ErrorInfo}\n";
    exit(1);
}

echo "\n✅ Relatório semanal gerado e enviado com sucesso!\n";
