<?php

declare(strict_types=1);

namespace App\Services;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

class EmailService
{
    private array $config;
    private bool $enabled;

    public function __construct()
    {
        $this->config = \App\Core\Config::getInstance()->all();
        $this->enabled = $this->config['email']['enabled'] ?? false;
    }

    /**
     * Verifica se e-mail está habilitado
     */
    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    /**
     * Envia e-mail usando PHPMailer
     * Suporta SMTP e anexos
     */
    public function send(string $to, string $subject, string $message, string $type = 'html', array $attachments = []): bool
    {
        if (!$this->enabled) {
            if (($this->config['debug'] ?? false)) {
                log_info('E-mail desabilitado, mensagem ignorada', [
                    'to' => $to,
                    'subject' => $subject,
                ]);
            }
            return false;
        }

        $mail = new PHPMailer(true);

        try {
            // Configurações de Servidor
            if (!empty($this->config['email']['smtp_host'])) {
                $mail->isSMTP();
                $mail->Host       = $this->config['email']['smtp_host'];
                $mail->SMTPAuth   = true;
                $mail->Username   = $this->config['email']['smtp_user'];
                $mail->Password   = $this->config['email']['smtp_pass'];
                $mail->SMTPSecure = $this->config['email']['smtp_secure'] ?? PHPMailer::ENCRYPTION_STARTTLS;
                $mail->Port       = $this->config['email']['smtp_port'] ?? 587;
            } else {
                // Fallback para mail()
                $mail->isMail();
            }

            // Remetente e Destinatário
            $from = $this->config['email']['from'] ?? 'noreply@mercadolivre-manager.com';
            $fromName = $this->config['email']['from_name'] ?? 'Mercado Livre Manager';

            $mail->setFrom($from, $fromName);
            $mail->addAddress($to);

            $replyTo = $this->config['email']['reply_to'] ?? $from;
            $mail->addReplyTo($replyTo);

            // Anexos
            foreach ($attachments as $attachment) {
                if (is_array($attachment) && isset($attachment['path'])) {
                    $mail->addAttachment($attachment['path'], $attachment['name'] ?? '');
                } elseif (is_string($attachment)) {
                    $mail->addAttachment($attachment);
                }
            }

            // Conteúdo
            $mail->isHTML($type === 'html');
            $mail->Subject = $subject;
            $mail->Body    = $message;
            $mail->AltBody = strip_tags($message);
            $mail->CharSet = 'UTF-8';

            $mail->send();
            return true;
        } catch (Exception $e) {
            log_error('Falha ao enviar e-mail', [
                'to' => $to,
                'subject' => $subject,
                'error' => $mail->ErrorInfo,
            ]);
            return false;
        }
    }

    /**
     * Envia e-mail de recuperação de senha
     */
    public function sendPasswordReset(string $to, string $name, string $resetToken, string $resetUrl): bool
    {
        $subject = "Recuperação de Senha - Mercado Livre Manager";

        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #667eea; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .button { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Recuperação de Senha</h1>
                </div>
                <div class='content'>
                    <p>Olá, <strong>{$name}</strong>!</p>
                    <p>Você solicitou a recuperação de senha para sua conta no Mercado Livre Manager.</p>
                    <p>Clique no botão abaixo para redefinir sua senha:</p>
                    <p style='text-align: center;'>
                        <a href='{$resetUrl}' class='button'>Redefinir Senha</a>
                    </p>
                    <p>Ou copie e cole este link no seu navegador:</p>
                    <p style='word-break: break-all;'>{$resetUrl}</p>
                    <p><strong>Este link expira em 1 hora.</strong></p>
                    <p>Se você não solicitou esta recuperação, ignore este e-mail.</p>
                </div>
                <div class='footer'>
                    <p>Mercado Livre Manager - Sistema de Gestão Multi-Contas</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return $this->send($to, $subject, $message, 'html');
    }

    /**
     * Envia e-mail de verificação
     */
    public function sendVerification(string $to, string $name, string $token): bool
    {
        $baseUrl = $this->config['app_url'] ?? 'https://eskill.com.br';
        $verifyUrl = "{$baseUrl}/auth/verify-email?token={$token}";
        $subject = "Verifique seu e-mail - Mercado Livre Manager";

        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #667eea; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .button { display: inline-block; padding: 12px 24px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Verifique seu E-mail</h1>
                </div>
                <div class='content'>
                    <p>Olá, <strong>{$name}</strong>!</p>
                    <p>Obrigado por se cadastrar no Mercado Livre Manager.</p>
                    <p>Para ativar sua conta, por favor verifique seu endereço de e-mail clicando no botão abaixo:</p>
                    <p style='text-align: center;'>
                        <a href='{$verifyUrl}' class='button'>Verificar E-mail</a>
                    </p>
                    <p>Ou copie e cole este link no seu navegador:</p>
                    <p style='word-break: break-all;'>{$verifyUrl}</p>
                </div>
                <div class='footer'>
                    <p>Mercado Livre Manager - Sistema de Gestão Multi-Contas</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return $this->send($to, $subject, $message, 'html');
    }

    /**
     * Envia e-mail de boas-vindas
     */
    public function sendWelcome(string $to, string $name): bool
    {
        $subject = "Bem-vindo ao Mercado Livre Manager!";

        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #667eea; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Bem-vindo!</h1>
                </div>
                <div class='content'>
                    <p>Olá, <strong>{$name}</strong>!</p>
                    <p>Sua conta foi criada com sucesso no Mercado Livre Manager.</p>
                    <p>Você já pode começar a usar o sistema:</p>
                    <ul>
                        <li>Vincular suas contas do Mercado Livre</li>
                        <li>Analisar anúncios e concorrência</li>
                        <li>Gerenciar pedidos de múltiplas contas</li>
                        <li>Gerar relatórios detalhados</li>
                    </ul>
                    <p>Se tiver dúvidas, consulte nossa central de ajuda.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return $this->send($to, $subject, $message, 'html');
    }

    /**
     * Envia notificação de novo pedido
     */
    public function sendNewOrderNotification(string $to, string $name, array $orderData): bool
    {
        $subject = "Novo Pedido Recebido - #{$orderData['id']}";

        $total = number_format($orderData['total_amount'] ?? 0, 2, ',', '.');

        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #28a745; color: white; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .order-info { background: white; padding: 15px; border-radius: 5px; margin: 10px 0; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Novo Pedido!</h1>
                </div>
                <div class='content'>
                    <p>Olá, <strong>{$name}</strong>!</p>
                    <p>Você recebeu um novo pedido no Mercado Livre.</p>
                    <div class='order-info'>
                        <p><strong>Pedido #{$orderData['id']}</strong></p>
                        <p><strong>Total:</strong> R$ {$total}</p>
                        <p><strong>Status:</strong> {$orderData['status']}</p>
                    </div>
                    <p>Acesse o dashboard para ver mais detalhes.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return $this->send($to, $subject, $message, 'html');
    }

    /**
     * Envia notificação de token expirando
     */
    public function sendTokenExpiringNotification(string $to, array $accountData): bool
    {
        $nickname = $accountData['nickname'] ?? 'Conta';
        $expiresAt = $accountData['expires_at'] ?? 'em breve';

        $subject = 'Token do Mercado Livre expirando';

        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #ffc107; color: #212529; padding: 20px; text-align: center; }
                .content { padding: 20px; background: #f9f9f9; }
                .box { background: #fff; border: 1px solid #eee; border-radius: 6px; padding: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>Token expirando</h1>
                </div>
                <div class='content'>
                    <p>Olá!</p>
                    <p>O token da conta <strong>{$nickname}</strong> está próximo de expirar.</p>
                    <div class='box'>
                        <p><strong>Expira em:</strong> {$expiresAt}</p>
                    </div>
                    <p>Recomendação: renovar o token o quanto antes para evitar interrupções.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return $this->send($to, $subject, $message, 'html');
    }

    /**
     * Envia alerta crítico sobre saúde dos tokens
     * 
     * @param string $to Email de destino
     * @param array $metrics Métricas do sistema (total_accounts, expired_accounts, etc)
     * @param array $issues Problemas detectados (critical, warning, info)
     * @param array $accounts Contas com problemas (opcional)
     * @return bool
     */
    public function sendTokenHealthAlert(string $to, array $metrics, array $issues, array $accounts = []): bool
    {
        $healthStatus = $metrics['health_status'] ?? 'unknown';
        $statusIcon = match($healthStatus) {
            'critical' => '🔴',
            'warning' => '⚠️',
            'ok' => '✅',
            default => '❓'
        };
        
        $statusColor = match($healthStatus) {
            'critical' => '#dc3545',
            'warning' => '#ffc107',
            'ok' => '#28a745',
            default => '#6c757d'
        };
        
        $subject = "{$statusIcon} Alerta de Tokens ML - Status: " . mb_strtoupper($healthStatus);
        
        // Construir lista de problemas críticos
        $criticalHtml = '';
        if (!empty($issues['critical'])) {
            $criticalHtml = '<div class="alert alert-danger">';
            $criticalHtml .= '<h3>❌ Problemas Críticos</h3><ul>';
            foreach ($issues['critical'] as $issue) {
                $criticalHtml .= "<li>{$issue}</li>";
            }
            $criticalHtml .= '</ul></div>';
        }
        
        // Construir lista de avisos
        $warningHtml = '';
        if (!empty($issues['warning'])) {
            $warningHtml = '<div class="alert alert-warning">';
            $warningHtml .= '<h3>⚠️ Avisos</h3><ul>';
            foreach ($issues['warning'] as $warning) {
                $warningHtml .= "<li>{$warning}</li>";
            }
            $warningHtml .= '</ul></div>';
        }
        
        // Construir tabela de contas com problemas
        $accountsHtml = '';
        if (!empty($accounts)) {
            $accountsHtml = '<div class="accounts-table">';
            $accountsHtml .= '<h3>📊 Contas que Necessitam Atenção</h3>';
            $accountsHtml .= '<table>';
            $accountsHtml .= '<thead><tr><th>Conta</th><th>Status</th><th>Expira em</th><th>Falhas</th></tr></thead>';
            $accountsHtml .= '<tbody>';
            foreach ($accounts as $account) {
                $accountsHtml .= '<tr>';
                $accountsHtml .= "<td><strong>{$account['nickname']}</strong></td>";
                $accountsHtml .= "<td><span class='badge badge-{$account['status']}'>{$account['status']}</span></td>";
                $accountsHtml .= "<td>{$account['expires_at']}</td>";
                $accountsHtml .= "<td>{$account['failure_count']}</td>";
                $accountsHtml .= '</tr>';
            }
            $accountsHtml .= '</tbody></table>';
            $accountsHtml .= '</div>';
        }
        
        $baseUrl = $this->config['app_url'] ?? 'https://eskill.com.br';
        $dashboardUrl = "{$baseUrl}/tokens/dashboard";
        
        $message = "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; background: #f4f4f4; }
                .container { max-width: 700px; margin: 0 auto; background: white; }
                .header { background: {$statusColor}; color: white; padding: 30px 20px; text-align: center; }
                .header h1 { margin: 0; font-size: 24px; }
                .content { padding: 30px 20px; }
                .metrics { display: grid; grid-template-columns: repeat(2, 1fr); gap: 15px; margin: 20px 0; }
                .metric-box { background: #f8f9fa; padding: 15px; border-radius: 8px; text-align: center; border-left: 4px solid #667eea; }
                .metric-box .value { font-size: 32px; font-weight: bold; color: #667eea; margin: 5px 0; }
                .metric-box .label { font-size: 12px; color: #666; text-transform: uppercase; }
                .alert { padding: 15px; margin: 20px 0; border-radius: 8px; }
                .alert-danger { background: #f8d7da; border-left: 4px solid #dc3545; }
                .alert-warning { background: #fff3cd; border-left: 4px solid #ffc107; }
                .alert h3 { margin-top: 0; font-size: 16px; }
                .alert ul { margin: 10px 0; padding-left: 20px; }
                .alert li { margin: 5px 0; }
                .accounts-table { margin: 20px 0; }
                .accounts-table h3 { font-size: 16px; margin-bottom: 10px; }
                table { width: 100%; border-collapse: collapse; }
                th, td { padding: 10px; text-align: left; border-bottom: 1px solid #ddd; }
                th { background: #f8f9fa; font-weight: 600; font-size: 12px; text-transform: uppercase; }
                .badge { padding: 4px 8px; border-radius: 4px; font-size: 11px; font-weight: 600; }
                .badge-expired { background: #dc3545; color: white; }
                .badge-expiring { background: #ffc107; color: #000; }
                .badge-active { background: #28a745; color: white; }
                .button { display: inline-block; padding: 12px 30px; background: #667eea; color: white; text-decoration: none; border-radius: 5px; margin: 20px 0; font-weight: 600; }
                .button:hover { background: #5568d3; }
                .footer { text-align: center; padding: 20px; background: #f8f9fa; color: #666; font-size: 12px; }
                .timestamp { text-align: center; color: #999; font-size: 11px; margin-top: 20px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>{$statusIcon} Alerta de Saúde dos Tokens ML</h1>
                    <p style='margin: 10px 0 0 0; font-size: 14px;'>Status: " . mb_strtoupper($healthStatus) . "</p>
                </div>
                <div class='content'>
                    <h2>📈 Métricas Gerais</h2>
                    <div class='metrics'>
                        <div class='metric-box'>
                            <div class='value'>{$metrics['total_accounts']}</div>
                            <div class='label'>Total de Contas</div>
                        </div>
                        <div class='metric-box' style='border-left-color: #dc3545;'>
                            <div class='value' style='color: #dc3545;'>{$metrics['expired_accounts']}</div>
                            <div class='label'>Contas Expiradas</div>
                        </div>
                        <div class='metric-box' style='border-left-color: #ffc107;'>
                            <div class='value' style='color: #ffc107;'>{$metrics['expiring_soon']}</div>
                            <div class='label'>Expirando em 24h</div>
                        </div>
                        <div class='metric-box' style='border-left-color: #dc3545;'>
                            <div class='value' style='color: #dc3545;'>{$metrics['failure_rate_24h']}%</div>
                            <div class='label'>Taxa de Falha 24h</div>
                        </div>
                    </div>
                    
                    {$criticalHtml}
                    {$warningHtml}
                    {$accountsHtml}
                    
                    <div style='text-align: center; margin-top: 30px;'>
                        <a href='{$dashboardUrl}' class='button'>Acessar Dashboard de Tokens</a>
                    </div>
                    
                    <div class='timestamp'>
                        Alerta gerado em: " . date('d/m/Y H:i:s') . "
                    </div>
                </div>
                <div class='footer'>
                    <p><strong>Mercado Livre Manager</strong> - Sistema de Gestão Multi-Contas</p>
                    <p>Este é um alerta automático do monitor de saúde dos tokens.</p>
                </div>
            </div>
        </body>
        </html>
        ";

        return $this->send($to, $subject, $message, 'html');
    }
}
