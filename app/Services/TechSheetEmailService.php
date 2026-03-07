<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

/**
 * Tech Sheet Email Service
 * 
 * Serviço de envio de emails para notificações de ficha técnica
 * Relatórios diários, alertas críticos, resumos semanais
 */
class TechSheetEmailService
{
    private array $config;
    private PHPMailer $mailer;

    public function __construct()
    {
        $appConfig = \App\Core\Config::getInstance()->all();
        $this->config = $appConfig['email'];
        
        $this->mailer = new PHPMailer(true);
        $this->configureMailer();
    }

    /**
     * Configura PHPMailer
     */
    private function configureMailer(): void
    {
        if (!$this->config['enabled']) {
            return;
        }

        $this->mailer->isSMTP();
        $this->mailer->Host = $this->config['smtp_host'];
        $this->mailer->SMTPAuth = true;
        $this->mailer->Username = $this->config['smtp_user'];
        $this->mailer->Password = $this->config['smtp_pass'];
        $this->mailer->SMTPSecure = $this->config['smtp_secure'];
        $this->mailer->Port = $this->config['smtp_port'];
        $this->mailer->CharSet = 'UTF-8';
        
        $this->mailer->setFrom($this->config['from'], 'Mercado Livre Manager');
        $this->mailer->addReplyTo($this->config['reply_to'], 'Suporte');
    }

    /**
     * Envia relatório diário de ficha técnica
     * 
     * @param int $accountId
     * @param string $recipientEmail
     * @param string $recipientName
     * @return bool
     */
    public function sendDailyReport(int $accountId, string $recipientEmail, string $recipientName): bool
    {
        if (!$this->config['enabled']) {
            log_info('Email desabilitado - relatório não enviado', ['service' => 'TechSheetEmailService']);
            return false;
        }

        try {
            // Gerar dados do relatório
            $notificationService = new TechSheetNotificationService($accountId);
            $report = $notificationService->generateDailyReport();
            
            // Verificar se há conteúdo relevante
            if ($this->shouldSkipReport($report)) {
                log_info('Relatório sem itens críticos - envio pulado', ['service' => 'TechSheetEmailService']);
                return true;
            }

            // Montar email
            $this->mailer->clearAddresses();
            $this->mailer->addAddress($recipientEmail, $recipientName);
            $this->mailer->Subject = $this->buildSubject($report);
            $this->mailer->isHTML(true);
            $this->mailer->Body = $this->buildHtmlBody($report);
            $this->mailer->AltBody = $this->buildTextBody($report);

            // Enviar
            $this->mailer->send();
            
            log_info('Relatório diário enviado', ['service' => 'TechSheetEmailService', 'recipient' => $recipientEmail]);
            return true;
            
        } catch (Exception $e) {
            log_error('Erro ao enviar email', ['service' => 'TechSheetEmailService', 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Envia alerta crítico imediato
     * 
     * @param int $accountId
     * @param array $recipients
     * @param array $alertData
     * @return bool
     */
    public function sendCriticalAlert(int $accountId, array $recipients, array $alertData): bool
    {
        if (!$this->config['enabled']) {
            return false;
        }

        try {
            $this->mailer->clearAddresses();
            
            foreach ($recipients as $recipient) {
                $this->mailer->addAddress($recipient['email'], $recipient['name']);
            }

            $this->mailer->Subject = "🚨 ALERTA CRÍTICO: Ficha Técnica";
            $this->mailer->isHTML(true);
            $this->mailer->Body = $this->buildCriticalAlertHtml($alertData);
            $this->mailer->AltBody = $this->buildCriticalAlertText($alertData);

            $this->mailer->send();
            return true;
            
        } catch (Exception $e) {
            log_error('Erro ao enviar alerta crítico', ['service' => 'TechSheetEmailService', 'error' => $e->getMessage()]);
            return false;
        }
    }

    /**
     * Verifica se deve pular envio do relatório
     */
    private function shouldSkipReport(array $report): bool
    {
        $alerts = $report['alerts']['summary'];
        
        // Não enviar se não houver alertas importantes
        if ($alerts['total_critical'] == 0 && 
            $alerts['total_missing_required'] == 0 &&
            $alerts['priority_level'] === 'LOW') {
            return true;
        }
        
        return false;
    }

    /**
     * Monta subject do email baseado no relatório
     */
    private function buildSubject(array $report): string
    {
        $date = date('d/m/Y');
        $priority = $report['alerts']['summary']['priority_level'];
        $overview = $report['overview'];
        
        $emoji = match($priority) {
            'CRITICAL' => '🚨',
            'HIGH' => '⚠️',
            'MEDIUM' => '📊',
            default => '✅',
        };
        
        $completeness = round($overview['avg_completeness'], 1);
        
        return "{$emoji} Ficha Técnica - {$date} | Completude: {$completeness}%";
    }

    /**
     * Monta corpo HTML do relatório
     */
    private function buildHtmlBody(array $report): string
    {
        $overview = $report['overview'];
        $alerts = $report['alerts'];
        $actions = $report['action_items'];
        
        $completenessColor = $overview['avg_completeness'] >= 70 ? '#10b981' : 
                            ($overview['avg_completeness'] >= 50 ? '#f59e0b' : '#ef4444');
        
        $html = <<<HTML
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .header { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; text-align: center; border-radius: 8px; }
        .stat-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 20px 0; }
        .stat-card { background: #f3f4f6; padding: 15px; border-radius: 8px; text-align: center; }
        .stat-value { font-size: 32px; font-weight: bold; color: #1f2937; }
        .stat-label { font-size: 14px; color: #6b7280; margin-top: 5px; }
        .progress-bar { height: 24px; background: #e5e7eb; border-radius: 12px; overflow: hidden; }
        .progress-fill { height: 100%; background: {$completenessColor}; transition: width 0.3s; }
        .alert { padding: 15px; margin: 10px 0; border-radius: 8px; border-left: 4px solid; }
        .alert-danger { background: #fee2e2; border-color: #dc2626; }
        .alert-warning { background: #fef3c7; border-color: #f59e0b; }
        .action-item { background: white; border: 1px solid #e5e7eb; padding: 12px; margin: 8px 0; border-radius: 6px; }
        .priority-badge { display: inline-block; padding: 4px 12px; border-radius: 4px; font-size: 12px; font-weight: bold; }
        .priority-high { background: #dc2626; color: white; }
        .priority-medium { background: #f59e0b; color: white; }
        .footer { text-align: center; margin-top: 30px; padding-top: 20px; border-top: 1px solid #e5e7eb; color: #6b7280; font-size: 14px; }
        .btn { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 6px; margin: 10px 5px; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📋 Relatório Diário - Ficha Técnica</h1>
            <p>{$report['date']}</p>
        </div>
        
        <h2 style="margin-top: 30px;">📊 Visão Geral</h2>
        <div class="stat-grid">
            <div class="stat-card">
                <div class="stat-value">{$overview['total_items']}</div>
                <div class="stat-label">Total de Itens</div>
            </div>
            <div class="stat-card">
                <div class="stat-value">{$overview['items_with_analysis']}</div>
                <div class="stat-label">Analisados</div>
            </div>
        </div>
        
        <div style="margin: 20px 0;">
            <strong>Completude Média: {$overview['avg_completeness']}%</strong>
            <div class="progress-bar">
                <div class="progress-fill" style="width: {$overview['avg_completeness']}%"></div>
            </div>
        </div>
HTML;

        // Alertas
        if ($alerts['summary']['total_missing_required'] > 0) {
            $count = $alerts['summary']['total_missing_required'];
            $html .= <<<HTML
        <div class="alert alert-danger">
            <strong>⚠️ {$count} itens</strong> com atributos obrigatórios faltando
        </div>
HTML;
        }

        if ($alerts['summary']['total_critical'] > 0) {
            $count = $alerts['summary']['total_critical'];
            $html .= <<<HTML
        <div class="alert alert-warning">
            <strong>📉 {$count} itens</strong> com completude crítica (&lt;30%)
        </div>
HTML;
        }

        // Ações recomendadas
        if (!empty($actions)) {
            $html .= '<h2>🎯 Ações Recomendadas</h2>';
            
            foreach ($actions as $action) {
                $badgeClass = $action['priority'] === 'HIGH' ? 'priority-high' : 'priority-medium';
                $html .= <<<HTML
        <div class="action-item">
            <span class="priority-badge {$badgeClass}">{$action['priority']}</span>
            <strong>{$action['action']}</strong> ({$action['count']} itens)
            <br><a href="https://eskill.com.br{$action['url']}" class="btn" style="margin-top: 10px;">Acessar</a>
        </div>
HTML;
            }
        }

        $html .= <<<HTML
        
        <div style="text-align: center; margin: 30px 0;">
            <a href="https://eskill.com.br/dashboard/seo/ficha-tecnica" class="btn">Ver Dashboard Completo</a>
        </div>
        
        <div class="footer">
            <p>Este é um relatório automático do sistema de gestão Mercado Livre Manager.</p>
            <p>Para desativar estes emails, acesse: Configurações → Notificações</p>
        </div>
    </div>
</body>
</html>
HTML;

        return $html;
    }

    /**
     * Monta corpo em texto plano do relatório
     */
    private function buildTextBody(array $report): string
    {
        $overview = $report['overview'];
        $alerts = $report['alerts'];
        $actions = $report['action_items'];
        
        $text = "=== RELATÓRIO DIÁRIO - FICHA TÉCNICA ===\n\n";
        $text .= "Data: {$report['date']}\n\n";
        
        $text .= "VISÃO GERAL:\n";
        $text .= "- Total de Itens: {$overview['total_items']}\n";
        $text .= "- Analisados: {$overview['items_with_analysis']}\n";
        $text .= "- Completude Média: {$overview['avg_completeness']}%\n\n";
        
        if ($alerts['summary']['total_missing_required'] > 0) {
            $count = $alerts['summary']['total_missing_required'];
            $text .= "⚠️ ALERTA: {$count} itens com atributos obrigatórios faltando\n";
        }
        
        if ($alerts['summary']['total_critical'] > 0) {
            $count = $alerts['summary']['total_critical'];
            $text .= "⚠️ ALERTA: {$count} itens com completude crítica (<30%)\n";
        }
        
        if (!empty($actions)) {
            $text .= "\nAÇÕES RECOMENDADAS:\n";
            foreach ($actions as $action) {
                $text .= "- [{$action['priority']}] {$action['action']} ({$action['count']} itens)\n";
                $text .= "  URL: https://eskill.com.br{$action['url']}\n";
            }
        }
        
        $text .= "\n---\nAcesse: https://eskill.com.br/dashboard/seo/ficha-tecnica\n";
        
        return $text;
    }

    /**
     * Monta corpo HTML de alerta crítico
     */
    private function buildCriticalAlertHtml(array $alertData): string
    {
        return <<<HTML
<!DOCTYPE html>
<html>
<head><meta charset="UTF-8"></head>
<body style="font-family: Arial, sans-serif; padding: 20px;">
    <div style="background: #dc2626; color: white; padding: 20px; border-radius: 8px; text-align: center;">
        <h1>🚨 ALERTA CRÍTICO</h1>
    </div>
    <div style="margin: 20px 0; padding: 20px; background: #fee2e2; border-radius: 8px;">
        <p><strong>Tipo:</strong> {$alertData['type']}</p>
        <p><strong>Descrição:</strong> {$alertData['message']}</p>
        <p><strong>Itens Afetados:</strong> {$alertData['affected_count']}</p>
    </div>
    <p><a href="https://eskill.com.br/dashboard/seo/ficha-tecnica" style="display: inline-block; padding: 12px 24px; background: #dc2626; color: white; text-decoration: none; border-radius: 6px;">Resolver Agora</a></p>
</body>
</html>
HTML;
    }

    /**
     * Monta corpo texto de alerta crítico
     */
    private function buildCriticalAlertText(array $alertData): string
    {
        return <<<TEXT
=== ALERTA CRÍTICO ===

Tipo: {$alertData['type']}
Descrição: {$alertData['message']}
Itens Afetados: {$alertData['affected_count']}

Acesse: https://eskill.com.br/dashboard/seo/ficha-tecnica
TEXT;
    }
}
