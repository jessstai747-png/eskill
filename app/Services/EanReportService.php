<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

/**
 * Serviço de relatórios do sistema EAN
 *
 * Gera relatórios de vendas, uso e inventário para admin.
 */
class EanReportService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = Database::getInstance();
    }

    /**
     * Relatório de vendas em período
     */
    public function getSalesReport(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT ep.*, pkg.name AS package_name
             FROM ean_purchases ep
             LEFT JOIN ean_packages pkg ON pkg.id = ep.package_id
             WHERE ep.payment_status = 'paid'
               AND ep.paid_at BETWEEN :start AND :end
             ORDER BY ep.paid_at DESC"
        );
        $stmt->execute(['start' => $start . ' 00:00:00', 'end' => $end . ' 23:59:59']);
        $purchases = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $totalRevenue = array_sum(array_column($purchases, 'total_amount'));
        $totalQuantity = array_sum(array_column($purchases, 'quantity'));

        return [
            'period' => ['start' => $start, 'end' => $end],
            'purchases' => $purchases,
            'summary' => [
                'total_purchases' => count($purchases),
                'total_quantity' => $totalQuantity,
                'total_revenue' => round($totalRevenue, 2),
                'avg_ticket' => count($purchases) > 0 ? round($totalRevenue / count($purchases), 2) : 0,
            ],
        ];
    }

    /**
     * Relatório de uso de EANs em período
     */
    public function getUsageReport(string $start, string $end): array
    {
        $stmt = $this->db->prepare(
            "SELECT ea.*, ei.ean
             FROM ean_assignments ea
             JOIN ean_inventory ei ON ei.id = ea.ean_id
             WHERE ea.ml_item_id IS NOT NULL
               AND ea.assigned_at BETWEEN :start AND :end
             ORDER BY ea.assigned_at DESC"
        );
        $stmt->execute(['start' => $start . ' 00:00:00', 'end' => $end . ' 23:59:59']);
        $usages = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Uso por conta
        $byAccount = [];
        foreach ($usages as $usage) {
            $accId = $usage['account_id'];
            $byAccount[$accId] = ($byAccount[$accId] ?? 0) + 1;
        }

        return [
            'period' => ['start' => $start, 'end' => $end],
            'usages' => $usages,
            'summary' => [
                'total_used' => count($usages),
                'unique_accounts' => count($byAccount),
                'usage_by_account' => $byAccount,
            ],
        ];
    }

    /**
     * Relatório de inventário atual
     */
    public function getInventoryReport(): array
    {
        $stmt = $this->db->query(
            "SELECT status, COUNT(*) as count FROM ean_inventory GROUP BY status"
        );
        $statusCounts = [];
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            $statusCounts[$row['status']] = (int) $row['count'];
        }

        $stmt = $this->db->query(
            "SELECT purchase_batch, COUNT(*) as count, MIN(created_at) as first_added
             FROM ean_inventory
             GROUP BY purchase_batch
             ORDER BY first_added DESC
             LIMIT 20"
        );
        $batches = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'status_summary' => $statusCounts,
            'total' => array_sum($statusCounts),
            'recent_batches' => $batches,
        ];
    }

    /**
     * Exportar vendas para CSV
     */
    public function exportSalesToCsv(string $start, string $end): string
    {
        $report = $this->getSalesReport($start, $end);

        $output = fopen('php://temp', 'r+');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        fputcsv($output, ['ID', 'Conta', 'Pacote', 'Qtd', 'Valor', 'Status', 'Pago Em'], ';');

        foreach ($report['purchases'] as $purchase) {
            fputcsv($output, [
                $purchase['id'],
                $purchase['account_id'],
                $purchase['package_name'] ?? '',
                $purchase['quantity'],
                $purchase['total_amount'],
                $purchase['payment_status'],
                $purchase['paid_at'] ?? '',
            ], ';');
        }

        rewind($output);
        $csv = stream_get_contents($output);
        fclose($output);

        return $csv;
    }
}
