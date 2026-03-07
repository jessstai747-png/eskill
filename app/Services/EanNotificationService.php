<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * Serviço de notificações do sistema EAN
 *
 * Envia relatórios periódicos por email.
 */
class EanNotificationService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Enviar relatório diário de vendas por email
     */
    public function sendDailySalesReport(): bool
    {
        $reportService = new EanReportService();

        $yesterday = date('Y-m-d', strtotime('-1 day'));
        $report = $reportService->getSalesReport($yesterday, $yesterday);
        $inventory = $reportService->getInventoryReport();

        $subject = "Relatório EAN - {$yesterday}";

        $body = "<h2>Relatório Diário de EANs - {$yesterday}</h2>";
        $body .= "<h3>Vendas</h3>";
        $body .= "<ul>";
        $body .= "<li>Total de vendas: {$report['summary']['total_purchases']}</li>";
        $body .= "<li>EANs vendidos: {$report['summary']['total_quantity']}</li>";
        $body .= "<li>Receita: R$ " . number_format($report['summary']['total_revenue'], 2, ',', '.') . "</li>";
        $body .= "</ul>";
        $body .= "<h3>Inventário</h3>";
        $body .= "<ul>";
        $body .= "<li>Total: {$inventory['total']}</li>";
        $body .= "<li>Disponíveis: " . ($inventory['status_summary']['available'] ?? 0) . "</li>";
        $body .= "<li>Reservados: " . ($inventory['status_summary']['reserved'] ?? 0) . "</li>";
        $body .= "<li>Vendidos: " . ($inventory['status_summary']['sold'] ?? 0) . "</li>";
        $body .= "</ul>";

        // Enviar usando o sistema de email existente
        try {
            $emailService = new \App\Services\EmailService();
            $adminEmail = $_ENV['ADMIN_EMAIL'] ?? 'admin@eskill.com.br';
            return $emailService->send($adminEmail, $subject, $body);
        } catch (\Exception $e) {
            log_error('Falha ao enviar relatório de EAN por e-mail', [
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }
}
