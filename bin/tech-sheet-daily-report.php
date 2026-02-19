#!/usr/bin/env php
<?php
/**
 * CLI Worker para Envio Automático de Relatórios Diários
 * 
 * Envia relatórios de ficha técnica por email para todos os usuários configurados
 * 
 * Uso:
 *   php bin/tech-sheet-daily-report.php [options]
 * 
 * Opções:
 *   --account=ID    ID da conta (obrigatório)
 *   --email=EMAIL   Email do destinatário (obrigatório)
 *   --name=NAME     Nome do destinatário (opcional)
 *   --dry-run       Simular sem enviar
 *   --help          Exibir ajuda
 */

require_once __DIR__ . '/../vendor/autoload.php';

use App\Services\TechSheetEmailService;
use App\Services\TechSheetNotificationService;

// Parse argumentos
$options = getopt('', ['account:', 'email:', 'name:', 'dry-run', 'help']);

if (isset($options['help'])) {
    showHelp();
    exit(0);
}

if (!isset($options['account']) || !isset($options['email'])) {
    error("❌ Erro: --account=ID e --email=EMAIL são obrigatórios\n");
    showHelp();
    exit(1);
}

$accountId = (int) $options['account'];
$email = $options['email'];
$name = $options['name'] ?? 'Usuário';
$dryRun = isset($options['dry-run']);

// Banner
echo "╔══════════════════════════════════════════════════════════╗\n";
echo "║       Tech Sheet Daily Report Sender                    ║\n";
echo "║       Envio Automático de Relatórios                     ║\n";
echo "╚══════════════════════════════════════════════════════════╝\n\n";

info("Preparando relatório diário...");
info("Conta: $accountId");
info("Destinatário: $name <$email>");
info("Modo: " . ($dryRun ? 'Simulação (DRY-RUN)' : 'Produção'));
echo "\n";

try {
    // Gerar preview do relatório
    info("Gerando dados do relatório...");
    $notificationService = new TechSheetNotificationService($accountId);
    $report = $notificationService->generateDailyReport();
    
    // Exibir preview
    echo "📊 Preview do Relatório:\n";
    echo "   • Data: {$report['date']}\n";
    echo "   • Total de itens: {$report['overview']['total_items']}\n";
    echo "   • Completude média: {$report['overview']['avg_completeness']}%\n";
    echo "   • Prioridade: {$report['alerts']['summary']['priority_level']}\n";
    echo "   • Alertas críticos: {$report['alerts']['summary']['total_critical']}\n";
    echo "   • Missing required: {$report['alerts']['summary']['total_missing_required']}\n";
    echo "   • Ações recomendadas: " . count($report['action_items']) . "\n";
    
    echo "\n";
    
    if ($dryRun) {
        success("🔍 Simulação concluída. Email não foi enviado.");
        exit(0);
    }
    
    // Enviar email
    info("Enviando email...");
    $emailService = new TechSheetEmailService();
    $sent = $emailService->sendDailyReport($accountId, $email, $name);
    
    if ($sent) {
        success("✅ Relatório enviado com sucesso!");
        echo "\n";
        echo "📧 Email enviado para: $email\n";
        exit(0);
    } else {
        error("❌ Erro ao enviar email. Verifique os logs.");
        exit(1);
    }
    
} catch (Exception $e) {
    echo "\n";
    error("❌ ERRO: " . $e->getMessage());
    exit(1);
}

// Funções auxiliares
function showHelp(): void {
    echo <<<HELP
    
Tech Sheet Daily Report - Envio Automático de Relatórios

USO:
    php bin/tech-sheet-daily-report.php [options]

OPÇÕES:
    --account=ID    ID da conta ML (obrigatório)
    --email=EMAIL   Email do destinatário (obrigatório)
    --name=NAME     Nome do destinatário (opcional, padrão: "Usuário")
    --dry-run       Simular sem enviar email
    --help          Exibir esta ajuda

EXEMPLOS:
    # Simular envio
    php bin/tech-sheet-daily-report.php --account=123 --email=user@example.com --dry-run
    
    # Enviar relatório
    php bin/tech-sheet-daily-report.php --account=123 --email=user@example.com --name="João Silva"

CONFIGURAÇÃO:
    Configure SMTP em config/app.php:
    - 'email' => ['enabled' => true, 'smtp_host' => ..., ...]

CRON:
    # Enviar diariamente às 08:00
    0 8 * * * cd /var/www && php bin/tech-sheet-daily-report.php --account=123 --email=user@example.com

HELP;
}

function info(string $msg): void {
    echo "\033[34mℹ\033[0m  $msg\n";
}

function success(string $msg): void {
    echo "\033[32m✓\033[0m  $msg\n";
}

function error(string $msg): void {
    echo "\033[31m✗\033[0m  $msg\n";
}
