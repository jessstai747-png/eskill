<?php

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

class ReportService
{
    private $db;

    public function __construct()
    {
        // $this->db = Database::getInstance(); // If needed for direct queries
    }

    /**
     * Generate Sales Report PDF
     */
    public function generateSalesReport(string $startDate, string $endDate): string
    {
        // 1. Fetch Real Data
        $data = $this->getRealSalesReportData($startDate, $endDate);

        // 2. Setup Dompdf
        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);

        // 3. Render HTML
        $html = $this->renderHtml('sales_report', $data);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // 4. Save/Return
        $filename = 'relatorio_vendas_' . date('Ymd_His') . '.pdf';
        $path = __DIR__ . '/../../public/storage/reports/' . $filename;

        if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);

        file_put_contents($path, $dompdf->output());

        return '/storage/reports/' . $filename;
    }

    /**
     * Generate CSV Export
     */
    public function generateCsvExport(string $startDate, string $endDate): string
    {
        $db = \App\Database::getInstance();
        $filename = 'export_sales_' . date('Ymd_His') . '.csv';
        $path = __DIR__ . '/../../public/storage/reports/' . $filename;
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);

        $fp = fopen($path, 'w');

        // Headers
        fputcsv($fp, ['ML Order ID', 'Date', 'Status', 'Total Amount', 'Net Profit']);

        // Query
        $stmt = $db->prepare("SELECT ml_order_id, date_created, status, total_amount, net_profit 
                              FROM ml_orders 
                              WHERE date_created BETWEEN ? AND ? 
                              ORDER BY date_created DESC");
        $stmt->execute([$startDate . ' 00:00:00', $endDate . ' 23:59:59']);

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            fputcsv($fp, $row);
        }

        fclose($fp);
        return '/storage/reports/' . $filename;
    }

    private function getRealSalesReportData($start, $end): array
    {
        $db = \App\Database::getInstance();

        // 1. Aggregates
        $sql = "SELECT 
                    COUNT(*) as count,
                    SUM(total_amount) as revenue,
                    SUM(net_profit) as profit
                FROM ml_orders 
                WHERE date_created BETWEEN ? AND ? 
                AND status = 'paid'";

        $stmt = $db->prepare($sql);
        $stmt->execute([$start . ' 00:00:00', $end . ' 23:59:59']);
        $aggs = $stmt->fetch(\PDO::FETCH_ASSOC);

        $totalSales = (float)($aggs['revenue'] ?? 0);
        $count = (int)($aggs['count'] ?? 0);

        // 2. Top Products (PHP Aggregation)
        $topProducts = $this->getTopProducts($start, $end);

        return [
            'period' => date('d/m/Y', strtotime($start)) . ' a ' . date('d/m/Y', strtotime($end)),
            'total_sales' => $totalSales,
            'orders_count' => $count,
            'avg_ticket' => $count > 0 ? $totalSales / $count : 0,
            'net_profit' => (float)($aggs['profit'] ?? 0),
            'top_products' => $topProducts
        ];
    }

    private function getTopProducts($start, $end): array
    {
        $db = \App\Database::getInstance();
        // Limit to 1000 to prevent OOM
        $stmt = $db->prepare("SELECT order_data FROM ml_orders WHERE date_created BETWEEN ? AND ? AND status = 'paid' LIMIT 1000");
        $stmt->execute([$start . ' 00:00:00', $end . ' 23:59:59']);

        $products = [];

        while ($row = $stmt->fetch(\PDO::FETCH_ASSOC)) {
            $json = json_decode($row['order_data'], true);
            if (!isset($json['order_items'])) continue;

            foreach ($json['order_items'] as $item) {
                $sku = $item['item']['id'] ?? 'Unknown';
                $title = $item['item']['title'] ?? 'Unknown Item';
                $qty = $item['quantity'] ?? 1;
                $price = $item['unit_price'] ?? 0;

                if (!isset($products[$sku])) {
                    $products[$sku] = [
                        'name' => $title,
                        'qty' => 0,
                        'total' => 0
                    ];
                }
                $products[$sku]['qty'] += $qty;
                $products[$sku]['total'] += ($qty * $price);
            }
        }

        // Sort by Total Revenue
        uasort($products, function ($a, $b) {
            return $b['total'] <=> $a['total'];
        });

        return array_slice($products, 0, 10);
    }

    /**
     * Generate Inventory Report PDF (Valuation)
     */
    public function generateInventoryReport(): string
    {
        $db = \App\Database::getInstance();

        // 1. Fetch Inventory Data (Active items)
        $sql = "SELECT ml_item_id, title, available_quantity, price, cost_price 
                FROM items 
                WHERE status = 'active'
                ORDER BY available_quantity DESC";
        $stmt = $db->query($sql);
        $items = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        // Calculate Totals
        $totalItems = 0;
        $totalValue = 0;
        $totalCost = 0;

        foreach ($items as $item) {
            $qty = (int)$item['available_quantity'];
            $price = (float)$item['price'];
            $cost = (float)($item['cost_price'] ?? 0);

            $totalItems += $qty;
            $totalValue += ($qty * $price);
            $totalCost += ($qty * $cost);
        }

        $data = [
            'type' => 'inventory',
            'date' => date('d/m/Y H:i'),
            'total_items' => $totalItems,
            'total_sell_value' => $totalValue,
            'total_cost_value' => $totalCost,
            'potential_profit' => $totalValue - $totalCost,
            'items' => array_slice($items, 0, 100) // Top 100 for PDF to avoid overflow
        ];

        // 2. Setup Dompdf
        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $options->set('isRemoteEnabled', true);
        $dompdf = new Dompdf($options);

        // 3. Render HTML
        $html = $this->renderHtml('inventory_report', $data);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        // 4. Save
        $filename = 'relatorio_estoque_' . date('Ymd_His') . '.pdf';
        $path = __DIR__ . '/../../public/storage/reports/' . $filename;
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
        file_put_contents($path, $dompdf->output());

        return '/storage/reports/' . $filename;
    }

    /**
     * Generate Customer Report (PDF)
     */
    public function generateCustomerReport(): string
    {
        $db = \App\Database::getInstance();

        // Top Customers by Revenue
        $sql = "SELECT c.name, c.total_purchases as revenue, c.total_orders as orders, c.state
                FROM ml_customers c
                ORDER BY c.total_purchases DESC
                LIMIT 50";
        $stmt = $db->query($sql);
        $customers = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        $data = [
            'type' => 'customer',
            'date' => date('d/m/Y H:i'),
            'top_customers' => $customers
        ];

        $options = new Options();
        $options->set('defaultFont', 'Helvetica');
        $dompdf = new Dompdf($options);
        $html = $this->renderHtml('customer_report', $data);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        $filename = 'relatorio_clientes_' . date('Ymd_His') . '.pdf';
        $path = __DIR__ . '/../../public/storage/reports/' . $filename;
        if (!is_dir(dirname($path))) mkdir(dirname($path), 0755, true);
        file_put_contents($path, $dompdf->output());

        return '/storage/reports/' . $filename;
    }

    private function renderHtml($template, $data): string
    {
        ob_start();
?>
        <html>

        <head>
            <meta charset="UTF-8">
            <style>
                body {
                    font-family: sans-serif;
                    color: #333;
                }

                table {
                    width: 100%;
                    border-collapse: collapse;
                    margin-top: 20px;
                }

                th,
                td {
                    border: 1px solid #ddd;
                    padding: 8px;
                    text-align: left;
                    font-size: 11px;
                }

                th {
                    background-color: #f8f9fa;
                    font-weight: bold;
                }

                .header {
                    text-align: center;
                    margin-bottom: 20px;
                    border-bottom: 2px solid #6f42c1;
                    padding-bottom: 10px;
                }

                .summary {
                    display: flex;
                    justify-content: space-between;
                    margin-bottom: 20px;
                    background: #f8f9fa;
                    padding: 15px;
                    border-radius: 5px;
                }

                .value {
                    font-size: 16px;
                    font-weight: bold;
                    color: #6f42c1;
                }

                h1 {
                    margin: 0;
                    font-size: 20px;
                    color: #6f42c1;
                }
            </style>
        </head>

        <body>
            <div class="header">
                <?php if ($data['type'] === 'inventory'): ?>
                    <h1>Relatório de Valorização de Estoque</h1>
                <?php elseif ($data['type'] === 'customer'): ?>
                    <h1>Relatório de Melhores Clientes</h1>
                <?php else: ?>
                    <h1>Relatório de Vendas</h1>
                    <p>Período: <?= $data['period'] ?? '' ?></p>
                <?php endif; ?>
                <p>Gerado em: <?= date('d/m/Y H:i') ?></p>
            </div>

            <?php if ($data['type'] === 'inventory'): ?>
                <table style="border: none; margin-bottom: 20px;">
                    <tr style="border: none;">
                        <td style="border: none; text-align: center;">
                            <div class="value"><?= number_format($data['total_items'], 0, ',', '.') ?></div>
                            <div>Itens Totais</div>
                        </td>
                        <td style="border: none; text-align: center;">
                            <div class="value">R$ <?= number_format($data['total_sell_value'], 2, ',', '.') ?></div>
                            <div>Valor de Venda (Bruto)</div>
                        </td>
                        <td style="border: none; text-align: center;">
                            <div class="value">R$ <?= number_format($data['items'][0]['price'] ?? 0, 2, ',', '.') ?></div>
                            <div>Maior Preço</div>
                        </td>
                    </tr>
                </table>
                <h3>Top 100 Itens (Quantidade)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qtd</th>
                            <th>Preço Venda</th>
                            <th>Total (Venda)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['items'] as $item): ?>
                            <tr>
                                <td><?= htmlspecialchars($item['title']) ?></td>
                                <td><?= $item['available_quantity'] ?></td>
                                <td>R$ <?= number_format((float)$item['price'], 2, ',', '.') ?></td>
                                <td>R$ <?= number_format((float)$item['price'] * $item['available_quantity'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php elseif ($data['type'] === 'customer'): ?>
                <h3>Top 50 Clientes (Receita)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Nome</th>
                            <th>Total Comprado</th>
                            <th>Pedidos</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['top_customers'] as $c): ?>
                            <tr>
                                <td><?= htmlspecialchars($c['name']) ?></td>
                                <td>R$ <?= number_format((float)$c['revenue'], 2, ',', '.') ?></td>
                                <td><?= $c['orders'] ?></td>
                                <td><?= $c['state'] ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <!-- SALES REPORT LOGIC (Existing) -->
                <table style="border: none; margin-bottom: 20px;">
                    <tr style="border: none;">
                        <td style="border: none; text-align: center;">
                            <div class="value">R$ <?= number_format($data['total_sales'], 2, ',', '.') ?></div>
                            <div>Receita Total</div>
                        </td>
                        <td style="border: none; text-align: center;">
                            <div class="value"><?= $data['orders_count'] ?></div>
                            <div>Pedidos Pagos</div>
                        </td>
                        <td style="border: none; text-align: center;">
                            <div class="value">R$ <?= number_format($data['avg_ticket'], 2, ',', '.') ?></div>
                            <div>Ticket Médio</div>
                        </td>
                        <td style="border: none; text-align: center;">
                            <div class="value">R$ <?= number_format($data['net_profit'], 2, ',', '.') ?></div>
                            <div>Lucro Líquido</div>
                        </td>
                    </tr>
                </table>
                <h3>Top 10 Produtos (Receita)</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Produto</th>
                            <th>Qtd</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($data['top_products'] as $p): ?>
                            <tr>
                                <td><?= htmlspecialchars($p['name']) ?></td>
                                <td><?= $p['qty'] ?></td>
                                <td>R$ <?= number_format($p['total'], 2, ',', '.') ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div style="margin-top: 50px; text-align: center; color: #999;">eSkill ERP</div>
        </body>

        </html>
<?php
        return ob_get_clean();
    }
}
