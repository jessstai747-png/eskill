#!/usr/bin/env php
<?php
/**
 * Generate Clone Metrics Report
 * 
 * Gera relatório diário de métricas de clonagem
 * 
 * Uso:
 *   php bin/generate-clone-metrics-report.php
 *   php bin/generate-clone-metrics-report.php --email=admin@example.com
 *   php bin/generate-clone-metrics-report.php --format=json
 */

declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Services\CloneMetricsService;

// Load environment
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

// Options
$options = getopt('', ['email:', 'format:', 'period:', 'help']);

if (isset($options['help'])) {
    echo "Uso: php bin/generate-clone-metrics-report.php [options]\n";
    echo "\nOpções:\n";
    echo "  --email=EMAIL    Enviar relatório por email\n";
    echo "  --format=FORMAT  Formato: text|json|html (padrão: text)\n";
    echo "  --period=DAYS    Período em dias (padrão: 7)\n";
    echo "  --help           Mostrar esta ajuda\n";
    exit(0);
}

$email = $options['email'] ?? null;
$format = $options['format'] ?? 'text';
$period = isset($options['period']) ? (int)$options['period'] : 7;

try {
    $db = Database::getInstance();
    
    // Gerar timestamp
    $timestamp = date('Y-m-d H:i:s');
    $reportDate = date('d/m/Y');
    
    // Coletar métricas diretamente (CloneMetricsService pode não ter todos os métodos ainda)
    $dashboard = getDashboardMetrics($db, $period);
    $timeseries = getTimeseriesMetrics($db, $period);
    $templateStats = getTemplateStats($db, $period);
    $topErrors = getTopErrors($db, $period, 10);
    
    // Gerar relatório baseado no formato
    if ($format === 'json') {
        $report = json_encode([
            'timestamp' => $timestamp,
            'period_days' => $period,
            'dashboard' => $dashboard,
            'timeseries' => $timeseries,
            'templates' => $templateStats,
            'top_errors' => $topErrors,
        ], JSON_PRETTY_PRINT);
        
    } elseif ($format === 'html') {
        $report = generateHtmlReport($timestamp, $reportDate, $period, $dashboard, $timeseries, $templateStats, $topErrors);
        
    } else { // text
        $report = generateTextReport($timestamp, $reportDate, $period, $dashboard, $timeseries, $templateStats, $topErrors);
    }
    
    // Salvar relatório
    $filename = 'clone-metrics-report-' . date('Y-m-d') . '.' . ($format === 'text' ? 'txt' : $format);
    $filepath = __DIR__ . '/../storage/reports/' . $filename;
    
    $dir = dirname($filepath);
    if (!is_dir($dir)) mkdir($dir, 0777, true);
    
    file_put_contents($filepath, $report);
    echo "Relatório salvo em: $filepath\n";
    
    // Enviar por email se solicitado
    if ($email) {
        try {
            require_once __DIR__ . '/../app/Services/EmailService.php';
            $emailService = new \App\Services\EmailService();
            
            if ($emailService->isEnabled()) {
                $subject = "Relatório de Métricas - Clonagem de Anúncios - $reportDate";
                
                if ($format === 'html') {
                    $reportHtml = generateHtmlReport($timestamp, $reportDate, $period, $dashboard, $timeseries, $templateStats, $topErrors);
                    $attachments = [];
                } else {
                    // Para formato texto, criar arquivo temporário para anexar
                    $tempFile = sys_get_temp_dir() . "/metrics_report_$timestamp.txt";
                    file_put_contents($tempFile, $report);
                    $reportHtml = "<pre>$report</pre>";
                    $attachments = [$tempFile];
                }
                
                $emailBody = "
                <html>
                <body style='font-family: Arial, sans-serif; margin: 20px;'>
                    <h2 style='color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 10px;'>
                        📊 Relatório de Métricas - Clonagem de Anúncios
                    </h2>
                    <div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 20px 0;'>
                        <p><strong>📅 Data:</strong> $reportDate</p>
                        <p><strong>⏱️ Período:</strong> Últimos $period dias</p>
                        <p><strong>📈 Status:</strong> " . ($dashboard['success_rate'] >= 80 ? '✅ Bom' : ($dashboard['success_rate'] >= 60 ? '⚠️ Regular' : '❌ Crítico')) . "</p>
                    </div>
                    <div style='margin: 20px 0;'>
                        <h3 style='color: #34495e;'>📋 Resumo Executivo</h3>
                        <table style='border-collapse: collapse; width: 100%; margin: 10px 0;'>
                            <tr style='background: #ecf0f1;'><td style='padding: 8px; border: 1px solid #ddd;'><strong>Total de Jobs</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$dashboard['total_jobs']}</td></tr>
                            <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Concluídos</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$dashboard['completed_jobs']}</td></tr>
                            <tr style='background: #ecf0f1;'><td style='padding: 8px; border: 1px solid #ddd;'><strong>Falhas</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>{$dashboard['failed_jobs']}</td></tr>
                            <tr><td style='padding: 8px; border: 1px solid #ddd;'><strong>Taxa de Sucesso</strong></td><td style='padding: 8px; border: 1px solid #ddd;'>" . number_format($dashboard['success_rate'], 2) . "%</td></tr>
                        </table>
                    </div>
                    $reportHtml
                    <hr style='margin: 30px 0; border: none; border-top: 1px solid #ddd;'>
                    <p style='font-size: 12px; color: #7f8c8d;'>
                        Este é um email automático gerado pelo Mercado Livre Manager.<br>
                        Para alterar as configurações de notificação, acesse o painel de administração.
                    </p>
                </body>
                </html>";
                
                $sent = $emailService->send($email, $subject, $emailBody, 'html', $attachments);
                
                if ($sent) {
                    echo "✅ Relatório enviado por email para: $email\n";
                } else {
                    echo "❌ Falha ao enviar email para: $email\n";
                }
                
                // Limpar arquivo temporário
                if (isset($tempFile) && file_exists($tempFile)) {
                    unlink($tempFile);
                }
            } else {
                echo "⚠️ Email não configurado. Use EMAIL_ENABLED=true e configure as variáveis SMTP.\n";
            }
        } catch (Exception $e) {
            echo "❌ Erro ao enviar email: " . $e->getMessage() . "\n";
        }
    }
    
    // Exibir no console se formato texto
    if ($format === 'text') {
        echo "\n$report\n";
    }
    
} catch (Exception $e) {
    echo "ERRO: " . $e->getMessage() . "\n";
    exit(1);
}

function generateTextReport($timestamp, $reportDate, $period, $dashboard, $timeseries, $templateStats, $topErrors): string
{
    $report = "";
    
    $report .= "╔═══════════════════════════════════════════════════════════════╗\n";
    $report .= "║                                                               ║\n";
    $report .= "║     RELATÓRIO DE MÉTRICAS - CLONAGEM DE ANÚNCIOS             ║\n";
    $report .= "║                                                               ║\n";
    $report .= "╚═══════════════════════════════════════════════════════════════╝\n";
    $report .= "\n";
    $report .= "Data do Relatório: $reportDate\n";
    $report .= "Período Analisado: Últimos $period dias\n";
    $report .= "Gerado em: $timestamp\n";
    $report .= "\n";
    
    // Dashboard
    $report .= "┌───────────────────────────────────────────────────────────────┐\n";
    $report .= "│  RESUMO GERAL                                                 │\n";
    $report .= "└───────────────────────────────────────────────────────────────┘\n";
    $report .= "\n";
    $report .= sprintf("Total de Jobs:        %d\n", $dashboard['total_jobs']);
    $report .= sprintf("  Completados:        %d (%.1f%%)\n", $dashboard['completed_jobs'], $dashboard['success_rate']);
    $report .= sprintf("  Falhos:             %d\n", $dashboard['failed_jobs']);
    $report .= sprintf("  Em Andamento:       %d\n", $dashboard['processing_jobs']);
    $report .= sprintf("  Pendentes:          %d\n", $dashboard['pending_jobs']);
    $report .= "\n";
    $report .= sprintf("Total de Itens:       %d\n", $dashboard['total_items']);
    $report .= sprintf("  Clonados:           %d\n", $dashboard['successful_items']);
    $report .= sprintf("  Falhos:             %d\n", $dashboard['failed_items']);
    $report .= sprintf("  Ignorados:          %d\n", $dashboard['skipped_items']);
    $report .= "\n";
    $report .= sprintf("Tempo Médio/Job:      %.1f minutos\n", $dashboard['avg_processing_time_minutes']);
    $report .= sprintf("Taxa de Sucesso:      %.1f%%\n", $dashboard['success_rate']);
    $report .= "\n";
    
    // Templates mais usados
    $report .= "┌───────────────────────────────────────────────────────────────┐\n";
    $report .= "│  TEMPLATES MAIS USADOS                                        │\n";
    $report .= "└───────────────────────────────────────────────────────────────┘\n";
    $report .= "\n";
    
    foreach (array_slice($templateStats, 0, 5) as $template) {
        $report .= sprintf("%-30s %5d usos (%.1f%% sucesso)\n", 
            $template['name'], 
            $template['usage_count'], 
            $template['success_rate']
        );
    }
    $report .= "\n";
    
    // Erros mais comuns
    $report .= "┌───────────────────────────────────────────────────────────────┐\n";
    $report .= "│  ERROS MAIS FREQUENTES                                        │\n";
    $report .= "└───────────────────────────────────────────────────────────────┘\n";
    $report .= "\n";
    
    if (empty($topErrors)) {
        $report .= "Nenhum erro registrado no período.\n";
    } else {
        foreach ($topErrors as $idx => $error) {
            $report .= sprintf("%d. %s (%d ocorrências)\n", 
                $idx + 1, 
                substr($error['error_message'], 0, 60), 
                $error['count']
            );
        }
    }
    $report .= "\n";
    
    // Tendências (últimos 7 dias)
    if (!empty($timeseries)) {
        $report .= "┌───────────────────────────────────────────────────────────────┐\n";
        $report .= "│  TENDÊNCIAS (ÚLTIMOS 7 DIAS)                                  │\n";
        $report .= "└───────────────────────────────────────────────────────────────┘\n";
        $report .= "\n";
        
        foreach (array_slice($timeseries, -7) as $day) {
            $report .= sprintf("%s: %d jobs, %d itens, %.1f%% sucesso\n",
                date('d/m', strtotime($day['date'])),
                $day['total_jobs'],
                $day['total_items'],
                $day['success_rate']
            );
        }
        $report .= "\n";
    }
    
    $report .= "╔═══════════════════════════════════════════════════════════════╗\n";
    $report .= "║  FIM DO RELATÓRIO                                             ║\n";
    $report .= "╚═══════════════════════════════════════════════════════════════╝\n";
    
    return $report;
}

function generateHtmlReport($timestamp, $reportDate, $period, $dashboard, $timeseries, $templateStats, $topErrors): string
{
    $timeseriesJson = json_encode($timeseries);
    $templateStatsJson = json_encode($templateStats);
    $topErrorsJson = json_encode(array_map(fn($error) => [
        'name' => substr($error['error_message'], 0, 50) . '...',
        'count' => $error['count']
    ], $topErrors));
    
    return "
    <!DOCTYPE html>
    <html>
    <head>
        <title>Relatório de Métricas - Clonagem de Anúncios</title>
        <script src=\"https://cdn.jsdelivr.net/npm/chart.js\"></script>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
            .container { max-width: 1200px; margin: 0 auto; background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
            .header { text-align: center; margin-bottom: 30px; padding: 20px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; border-radius: 10px; }
            .header h1 { margin: 0; font-size: 2.5em; }
            .header p { margin: 10px 0 0 0; opacity: 0.9; }
            .metrics-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin: 30px 0; }
            .metric-card { background: #f8f9fa; padding: 20px; border-radius: 8px; text-align: center; border-left: 4px solid #007bff; }
            .metric-value { font-size: 2em; font-weight: bold; color: #2c3e50; }
            .metric-label { color: #6c757d; margin-top: 5px; }
            .chart-container { margin: 30px 0; }
            .chart-box { background: white; padding: 20px; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
            .status-good { border-left-color: #28a745; }
            .status-warning { border-left-color: #ffc107; }
            .status-danger { border-left-color: #dc3545; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
            th { background: #f8f9fa; font-weight: bold; }
            .footer { margin-top: 40px; padding-top: 20px; border-top: 1px solid #eee; text-align: center; color: #6c757d; font-size: 0.9em; }
            canvas { max-height: 400px; }
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>📊 Relatório de Métricas</h1>
                <p>Clonagem de Anúncios - $reportDate | Período: $period dias</p>
            </div>
            
            <div class='metrics-grid'>
                <div class='metric-card " . ($dashboard['success_rate'] >= 80 ? 'status-good' : ($dashboard['success_rate'] >= 60 ? 'status-warning' : 'status-danger')) . "'>
                    <div class='metric-value'>" . number_format($dashboard['success_rate'], 1) . "%</div>
                    <div class='metric-label'>Taxa de Sucesso</div>
                </div>
                <div class='metric-card status-good'>
                    <div class='metric-value'>{$dashboard['total_jobs']}</div>
                    <div class='metric-label'>Total de Jobs</div>
                </div>
                <div class='metric-card " . ($dashboard['failed_jobs'] <= $dashboard['total_jobs'] * 0.1 ? 'status-good' : 'status-warning') . "'>
                    <div class='metric-value'>{$dashboard['failed_jobs']}</div>
                    <div class='metric-label'>Falhas</div>
                </div>
                <div class='metric-card status-good'>
                    <div class='metric-value'>" . round($dashboard['avg_processing_time_minutes'] ?? 0, 1) . " min</div>
                    <div class='metric-label'>Tempo Médio</div>
                </div>
            </div>
            
            <div class='chart-container'>
                <div class='chart-box'>
                    <h3>📈 Evolução Diária dos Jobs</h3>
                    <canvas id='timeseriesChart'></canvas>
                </div>
            </div>
            
            <div class='chart-container'>
                <div class='chart-box'>
                    <h3>📋 Estatísticas por Template</h3>
                    <canvas id='templateChart'></canvas>
                </div>
            </div>
            
            " . (!empty($topErrors) ? "
            <div class='chart-container'>
                <div class='chart-box'>
                    <h3>🚨 Principais Erros</h3>
                    <canvas id='errorsChart'></canvas>
                </div>
            </div>
            " : "") . "
            
            <div class='chart-box'>
                <h3>📊 Detalhamento dos Jobs</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Métrica</th>
                            <th>Quantidade</th>
                            <th>Percentual</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr>
                            <td>✅ Jobs Concluídos</td>
                            <td>{$dashboard['completed_jobs']}</td>
                            <td>" . number_format(($dashboard['completed_jobs'] / max($dashboard['total_jobs'], 1)) * 100, 1) . "%</td>
                        </tr>
                        <tr>
                            <td>⏳ Jobs em Processamento</td>
                            <td>{$dashboard['processing_jobs']}</td>
                            <td>" . number_format(($dashboard['processing_jobs'] / max($dashboard['total_jobs'], 1)) * 100, 1) . "%</td>
                        </tr>
                        <tr>
                            <td>⏸️ Jobs Pendentes</td>
                            <td>{$dashboard['pending_jobs']}</td>
                            <td>" . number_format(($dashboard['pending_jobs'] / max($dashboard['total_jobs'], 1)) * 100, 1) . "%</td>
                        </tr>
                        <tr>
                            <td>❌ Jobs Falhados</td>
                            <td>{$dashboard['failed_jobs']}</td>
                            <td>" . number_format(($dashboard['failed_jobs'] / max($dashboard['total_jobs'], 1)) * 100, 1) . "%</td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class='footer'>
                <p>Relatório gerado em: " . date('d/m/Y H:i:s') . " | Sistema: Mercado Livre Manager</p>
            </div>
        </div>
        
        <script>
            // Gráfico de Série Temporal
            const timeseriesCtx = document.getElementById('timeseriesChart').getContext('2d');
            new Chart(timeseriesCtx, {
                type: 'line',
                data: {
                    labels: $timeseriesJson.map(d => d.date),
                    datasets: [{
                        label: 'Jobs Concluídos',
                        data: $timeseriesJson.map(d => d.completed),
                        borderColor: '#28a745',
                        backgroundColor: 'rgba(40, 167, 69, 0.1)',
                        tension: 0.4
                    }, {
                        label: 'Jobs Falhados',
                        data: $timeseriesJson.map(d => d.failed),
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { position: 'top' } },
                    scales: { y: { beginAtZero: true } }
                }
            });
            
            // Gráfico de Templates
            const templateCtx = document.getElementById('templateChart').getContext('2d');
            new Chart(templateCtx, {
                type: 'doughnut',
                data: {
                    labels: $templateStatsJson.map(d => d.template_name),
                    datasets: [{
                        data: $templateStatsJson.map(d => d.count),
                        backgroundColor: ['#007bff', '#28a745', '#ffc107', '#dc3545', '#6f42c1', '#fd7e14']
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { 
                        legend: { position: 'right' },
                        tooltip: { callbacks: { label: (context) => context.label + ': ' + context.parsed } }
                    }
                }
            });
            
            " . (!empty($topErrors) ? "
            // Gráfico de Erros
            const errorsCtx = document.getElementById('errorsChart').getContext('2d');
            new Chart(errorsCtx, {
                type: 'bar',
                data: {
                    labels: $topErrorsJson.map(d => d.name),
                    datasets: [{
                        label: 'Ocorrências',
                        data: $topErrorsJson.map(d => d.count),
                        backgroundColor: '#dc3545'
                    }]
                },
                options: {
                    responsive: true,
                    plugins: { legend: { display: false } },
                    scales: { y: { beginAtZero: true } }
                }
            });
            " : "") . "
        </script>
    </body>
    </html>";
}

function getDashboardMetrics($db, $period): array
{
    $stmt = $db->prepare("
        SELECT 
            COUNT(*) as total_jobs,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
            SUM(CASE WHEN status = 'failed' THEN 1 ELSE 0 END) as failed_jobs,
            SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_jobs,
            SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_jobs,
            SUM(total_items) as total_items,
            SUM(successful_items) as successful_items,
            SUM(failed_items) as failed_items,
            SUM(skipped_items) as skipped_items,
            AVG(TIMESTAMPDIFF(MINUTE, started_at, completed_at)) as avg_processing_time_minutes
        FROM catalog_clone_jobs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
    ");
    $stmt->execute(['days' => $period]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    
    $result['success_rate'] = $result['total_jobs'] > 0 
        ? ($result['completed_jobs'] / $result['total_jobs']) * 100 
        : 0;
    
    return $result;
}

function getTimeseriesMetrics($db, $period): array
{
    $stmt = $db->prepare("
        SELECT 
            DATE(created_at) as date,
            COUNT(*) as total_jobs,
            SUM(total_items) as total_items,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_jobs,
            ROUND(SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) as success_rate
        FROM catalog_clone_jobs
        WHERE created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
        GROUP BY DATE(created_at)
        ORDER BY date ASC
    ");
    $stmt->execute(['days' => $period]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTemplateStats($db, $period): array
{
    $stmt = $db->prepare("
        SELECT 
            t.name,
            t.usage_count,
            COUNT(j.id) as recent_usage,
            SUM(CASE WHEN j.status = 'completed' THEN 1 ELSE 0 END) as completed,
            ROUND(SUM(CASE WHEN j.status = 'completed' THEN 1 ELSE 0 END) / COUNT(j.id) * 100, 1) as success_rate
        FROM clone_templates t
        LEFT JOIN catalog_clone_jobs j ON JSON_EXTRACT(j.options, '$.template_id') = t.id
            AND j.created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
        WHERE t.is_active = 1
        GROUP BY t.id, t.name, t.usage_count
        HAVING recent_usage > 0
        ORDER BY recent_usage DESC
    ");
    $stmt->execute(['days' => $period]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function getTopErrors($db, $period, $limit): array
{
    $limitSql = max(1, min(200, (int)$limit));
    $stmt = $db->prepare("
        SELECT 
            error_message,
            COUNT(*) as count
        FROM catalog_clone_job_items
        WHERE status = 'failed'
        AND error_message IS NOT NULL
        AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
        GROUP BY error_message
        ORDER BY count DESC
        LIMIT {$limitSql}
    ");
    $stmt->execute(['days' => $period]);
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}
