<?php

declare(strict_types=1);

namespace App\Services;

use Dompdf\Dompdf;
use Dompdf\Options;

/**
 * Serviço de Geração de PDF
 *
 * Gera relatórios PDF profissionais para:
 * - Relatórios de vendas
 * - Análises de mercado
 * - Dashboard executivo
 * - Exportações de dados
 */
class PdfService
{
    private Dompdf $dompdf;
    private array $config;

    public function __construct()
    {
        $options = new Options();
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('tempDir', __DIR__ . '/../../storage/cache');

        $this->dompdf = new Dompdf($options);

        $this->config = [
            'company_name' => 'Mercado Livre Manager',
            'logo_url' => '',
            'primary_color' => '#3483fa',
            'secondary_color' => '#2d3748',
        ];
    }

    /**
     * Gera PDF de relatório de vendas
     */
    public function generateSalesReport(array $data, string $period = 'month'): string
    {
        $html = $this->getBaseHtml('Relatório de Vendas');

        $html .= '<div class="header-info">';
        $html .= '<p><strong>Período:</strong> ' . $this->formatPeriod($period, $data) . '</p>';
        $html .= '<p><strong>Gerado em:</strong> ' . date('d/m/Y H:i:s') . '</p>';
        $html .= '</div>';

        // KPIs principais
        $html .= '<div class="kpi-grid">';
        $html .= $this->renderKpiCard('Total de Vendas', $this->formatCurrency($data['total_sales'] ?? 0), 'currency');
        $html .= $this->renderKpiCard('Pedidos', number_format($data['total_orders'] ?? 0), 'orders');
        $html .= $this->renderKpiCard('Ticket Médio', $this->formatCurrency($data['average_ticket'] ?? 0), 'average');
        $html .= $this->renderKpiCard('Taxa de Conversão', ($data['conversion_rate'] ?? 0) . '%', 'rate');
        $html .= '</div>';

        // Gráfico de vendas por período (representação simplificada em tabela)
        if (!empty($data['sales_by_period'])) {
            $html .= '<div class="section">';
            $html .= '<h2>Vendas por Período</h2>';
            $html .= '<table class="data-table">';
            $html .= '<thead><tr><th>Período</th><th>Pedidos</th><th>Valor Total</th><th>Variação</th></tr></thead>';
            $html .= '<tbody>';

            $previousValue = null;
            foreach ($data['sales_by_period'] as $item) {
                $variation = '';
                if ($previousValue !== null) {
                    $diff = $item['value'] - $previousValue;
                    $percent = $previousValue > 0 ? round(($diff / $previousValue) * 100, 1) : 0;
                    $variationClass = $diff >= 0 ? 'positive' : 'negative';
                    $variationIcon = $diff >= 0 ? '↑' : '↓';
                    $variation = '<span class="' . $variationClass . '">' . $variationIcon . ' ' . abs($percent) . '%</span>';
                }

                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($item['period'] ?? '') . '</td>';
                $html .= '<td>' . number_format($item['orders'] ?? 0) . '</td>';
                $html .= '<td>' . $this->formatCurrency($item['value'] ?? 0) . '</td>';
                $html .= '<td>' . $variation . '</td>';
                $html .= '</tr>';

                $previousValue = $item['value'] ?? 0;
            }

            $html .= '</tbody></table></div>';
        }

        // Top produtos
        if (!empty($data['top_products'])) {
            $html .= '<div class="section">';
            $html .= '<h2>Top Produtos</h2>';
            $html .= '<table class="data-table">';
            $html .= '<thead><tr><th>#</th><th>Produto</th><th>Qtd. Vendida</th><th>Receita</th></tr></thead>';
            $html .= '<tbody>';

            foreach ($data['top_products'] as $index => $product) {
                $html .= '<tr>';
                $html .= '<td class="rank">' . ($index + 1) . '</td>';
                $html .= '<td>' . htmlspecialchars($product['title'] ?? 'Produto') . '</td>';
                $html .= '<td>' . number_format($product['quantity'] ?? 0) . '</td>';
                $html .= '<td>' . $this->formatCurrency($product['revenue'] ?? 0) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table></div>';
        }

        // Vendas por categoria
        if (!empty($data['sales_by_category'])) {
            $html .= '<div class="section">';
            $html .= '<h2>Vendas por Categoria</h2>';
            $html .= '<table class="data-table">';
            $html .= '<thead><tr><th>Categoria</th><th>Pedidos</th><th>Valor</th><th>% do Total</th></tr></thead>';
            $html .= '<tbody>';

            $totalSales = $data['total_sales'] ?? 1;
            foreach ($data['sales_by_category'] as $category) {
                $percent = $totalSales > 0 ? round(($category['value'] / $totalSales) * 100, 1) : 0;
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($category['name'] ?? 'Categoria') . '</td>';
                $html .= '<td>' . number_format($category['orders'] ?? 0) . '</td>';
                $html .= '<td>' . $this->formatCurrency($category['value'] ?? 0) . '</td>';
                $html .= '<td>';
                $html .= '<div class="progress-bar-container">';
                $html .= '<div class="progress-bar" style="width: ' . $percent . '%"></div>';
                $html .= '</div>';
                $html .= '<span>' . $percent . '%</span>';
                $html .= '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table></div>';
        }

        $html .= $this->getFooterHtml();

        return $this->renderPdf($html, 'relatorio_vendas', false);
    }

    /**
     * Gera PDF de análise de mercado
     */
    public function generateMarketAnalysis(array $data): string
    {
        $html = $this->getBaseHtml('Análise de Mercado');

        $html .= '<div class="header-info">';
        if (isset($data['category'])) {
            $html .= '<p><strong>Categoria:</strong> ' . htmlspecialchars($data['category']['name'] ?? '') . '</p>';
        }
        if (isset($data['brand'])) {
            $html .= '<p><strong>Marca:</strong> ' . htmlspecialchars($data['brand']) . '</p>';
        }
        $html .= '<p><strong>Gerado em:</strong> ' . date('d/m/Y H:i:s') . '</p>';
        $html .= '</div>';

        // Resumo do mercado
        $html .= '<div class="kpi-grid">';
        $html .= $this->renderKpiCard('Total de Anúncios', number_format($data['total_listings'] ?? 0), 'listings');
        $html .= $this->renderKpiCard('Preço Médio', $this->formatCurrency($data['average_price'] ?? 0), 'average');
        $html .= $this->renderKpiCard('Preço Mínimo', $this->formatCurrency($data['min_price'] ?? 0), 'min');
        $html .= $this->renderKpiCard('Preço Máximo', $this->formatCurrency($data['max_price'] ?? 0), 'max');
        $html .= '</div>';

        // Distribuição por tipo
        if (isset($data['catalog_count']) || isset($data['common_count'])) {
            $html .= '<div class="section">';
            $html .= '<h2>Distribuição por Tipo de Anúncio</h2>';
            $html .= '<div class="distribution-grid">';

            $catalogCount = $data['catalog_count'] ?? 0;
            $commonCount = $data['common_count'] ?? 0;
            $total = $catalogCount + $commonCount;

            if ($total > 0) {
                $catalogPercent = round(($catalogCount / $total) * 100, 1);
                $commonPercent = round(($commonCount / $total) * 100, 1);

                $html .= '<div class="distribution-item catalog">';
                $html .= '<div class="distribution-value">' . number_format($catalogCount) . '</div>';
                $html .= '<div class="distribution-label">Catálogo</div>';
                $html .= '<div class="distribution-percent">' . $catalogPercent . '%</div>';
                $html .= '</div>';

                $html .= '<div class="distribution-item common">';
                $html .= '<div class="distribution-value">' . number_format($commonCount) . '</div>';
                $html .= '<div class="distribution-label">Comum</div>';
                $html .= '<div class="distribution-percent">' . $commonPercent . '%</div>';
                $html .= '</div>';
            }

            $html .= '</div></div>';
        }

        // Análise de concorrência
        if (!empty($data['competitors'])) {
            $html .= '<div class="section">';
            $html .= '<h2>Top Vendedores</h2>';
            $html .= '<table class="data-table">';
            $html .= '<thead><tr><th>#</th><th>Vendedor</th><th>Anúncios</th><th>Preço Médio</th><th>Reputação</th></tr></thead>';
            $html .= '<tbody>';

            foreach ($data['competitors'] as $index => $seller) {
                $html .= '<tr>';
                $html .= '<td class="rank">' . ($index + 1) . '</td>';
                $html .= '<td>' . htmlspecialchars($seller['nickname'] ?? 'Vendedor') . '</td>';
                $html .= '<td>' . number_format($seller['listings'] ?? 0) . '</td>';
                $html .= '<td>' . $this->formatCurrency($seller['average_price'] ?? 0) . '</td>';
                $html .= '<td>' . $this->renderReputationBadge($seller['reputation'] ?? 'green') . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table></div>';
        }

        // Análise de preços
        if (!empty($data['price_ranges'])) {
            $html .= '<div class="section">';
            $html .= '<h2>Distribuição de Preços</h2>';
            $html .= '<table class="data-table">';
            $html .= '<thead><tr><th>Faixa de Preço</th><th>Quantidade</th><th>Porcentagem</th></tr></thead>';
            $html .= '<tbody>';

            $totalListings = $data['total_listings'] ?? 1;
            foreach ($data['price_ranges'] as $range) {
                $percent = $totalListings > 0 ? round(($range['count'] / $totalListings) * 100, 1) : 0;
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($range['label'] ?? '') . '</td>';
                $html .= '<td>' . number_format($range['count']) . '</td>';
                $html .= '<td>';
                $html .= '<div class="progress-bar-container">';
                $html .= '<div class="progress-bar" style="width: ' . $percent . '%"></div>';
                $html .= '</div>';
                $html .= '<span>' . $percent . '%</span>';
                $html .= '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table></div>';
        }

        $html .= $this->getFooterHtml();

        return $this->renderPdf($html, 'analise_mercado', false);
    }

    /**
     * Gera PDF de relatório de pedidos
     */
    public function generateOrdersReport(array $orders, array $summary = []): string
    {
        $html = $this->getBaseHtml('Relatório de Pedidos');

        $html .= '<div class="header-info">';
        $html .= '<p><strong>Total de Pedidos:</strong> ' . count($orders) . '</p>';
        $html .= '<p><strong>Gerado em:</strong> ' . date('d/m/Y H:i:s') . '</p>';
        $html .= '</div>';

        // Resumo
        if (!empty($summary)) {
            $html .= '<div class="kpi-grid">';
            $html .= $this->renderKpiCard('Valor Total', $this->formatCurrency($summary['total_value'] ?? 0), 'currency');
            $html .= $this->renderKpiCard('Pagos', number_format($summary['paid'] ?? 0), 'success');
            $html .= $this->renderKpiCard('Enviados', number_format($summary['shipped'] ?? 0), 'shipping');
            $html .= $this->renderKpiCard('Entregues', number_format($summary['delivered'] ?? 0), 'delivered');
            $html .= '</div>';
        }

        // Lista de pedidos
        $html .= '<div class="section">';
        $html .= '<h2>Lista de Pedidos</h2>';
        $html .= '<table class="data-table">';
        $html .= '<thead><tr><th>ID</th><th>Data</th><th>Comprador</th><th>Itens</th><th>Total</th><th>Status</th></tr></thead>';
        $html .= '<tbody>';

        foreach ($orders as $order) {
            $date = isset($order['date_created']) ?
                date('d/m/Y', strtotime($order['date_created'])) : '-';

            $buyerName = $order['buyer']['nickname'] ??
                $order['buyer']['first_name'] ?? 'Comprador';

            $itemsCount = count($order['order_items'] ?? []);

            $html .= '<tr>';
            $html .= '<td><strong>#' . ($order['id'] ?? '-') . '</strong></td>';
            $html .= '<td>' . $date . '</td>';
            $html .= '<td>' . htmlspecialchars($buyerName) . '</td>';
            $html .= '<td>' . $itemsCount . ' item(s)</td>';
            $html .= '<td>' . $this->formatCurrency($order['total_amount'] ?? 0) . '</td>';
            $html .= '<td>' . $this->renderStatusBadge($order['status'] ?? 'unknown') . '</td>';
            $html .= '</tr>';
        }

        $html .= '</tbody></table></div>';

        $html .= $this->getFooterHtml();

        return $this->renderPdf($html, 'relatorio_pedidos', false);
    }

    /**
     * Gera PDF de dashboard executivo
     */
    public function generateExecutiveDashboard(array $data): string
    {
        $html = $this->getBaseHtml('Dashboard Executivo', true);

        $html .= '<div class="header-info">';
        $html .= '<p><strong>Período:</strong> ' . ($data['period'] ?? 'Últimos 30 dias') . '</p>';
        $html .= '<p><strong>Gerado em:</strong> ' . date('d/m/Y H:i:s') . '</p>';
        $html .= '</div>';

        // KPIs principais
        $html .= '<div class="executive-kpis">';
        $html .= '<div class="kpi-row">';
        $html .= $this->renderExecutiveKpi(
            'Faturamento',
            $this->formatCurrency($data['revenue'] ?? 0),
            $data['revenue_growth'] ?? null,
            'blue'
        );
        $html .= $this->renderExecutiveKpi(
            'Pedidos',
            number_format($data['orders'] ?? 0),
            $data['orders_growth'] ?? null,
            'green'
        );
        $html .= $this->renderExecutiveKpi(
            'Ticket Médio',
            $this->formatCurrency($data['average_ticket'] ?? 0),
            $data['ticket_growth'] ?? null,
            'purple'
        );
        $html .= '</div>';

        $html .= '<div class="kpi-row">';
        $html .= $this->renderExecutiveKpi(
            'Anúncios Ativos',
            number_format($data['active_listings'] ?? 0),
            null,
            'orange'
        );
        $html .= $this->renderExecutiveKpi(
            'Taxa de Conversão',
            ($data['conversion_rate'] ?? 0) . '%',
            $data['conversion_growth'] ?? null,
            'teal'
        );
        $html .= $this->renderExecutiveKpi(
            'Contas Ativas',
            number_format($data['active_accounts'] ?? 0),
            null,
            'gray'
        );
        $html .= '</div>';
        $html .= '</div>';

        // Desempenho por conta
        if (!empty($data['accounts_performance'])) {
            $html .= '<div class="section">';
            $html .= '<h2>Desempenho por Conta</h2>';
            $html .= '<table class="data-table">';
            $html .= '<thead><tr><th>Conta</th><th>Faturamento</th><th>Pedidos</th><th>Ticket Médio</th><th>% do Total</th></tr></thead>';
            $html .= '<tbody>';

            $totalRevenue = $data['revenue'] ?? 1;
            foreach ($data['accounts_performance'] as $account) {
                $percent = $totalRevenue > 0 ? round(($account['revenue'] / $totalRevenue) * 100, 1) : 0;

                $html .= '<tr>';
                $html .= '<td><strong>' . htmlspecialchars($account['name'] ?? 'Conta') . '</strong></td>';
                $html .= '<td>' . $this->formatCurrency($account['revenue'] ?? 0) . '</td>';
                $html .= '<td>' . number_format($account['orders'] ?? 0) . '</td>';
                $html .= '<td>' . $this->formatCurrency($account['average_ticket'] ?? 0) . '</td>';
                $html .= '<td>';
                $html .= '<div class="progress-bar-container">';
                $html .= '<div class="progress-bar" style="width: ' . $percent . '%"></div>';
                $html .= '</div>';
                $html .= '<span>' . $percent . '%</span>';
                $html .= '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table></div>';
        }

        // Alertas importantes
        if (!empty($data['alerts'])) {
            $html .= '<div class="section alerts-section">';
            $html .= '<h2>⚠️ Alertas Importantes</h2>';
            $html .= '<div class="alerts-list">';

            foreach ($data['alerts'] as $alert) {
                $alertClass = $alert['severity'] ?? 'info';
                $html .= '<div class="alert-item ' . $alertClass . '">';
                $html .= '<strong>' . htmlspecialchars($alert['title'] ?? 'Alerta') . '</strong>';
                $html .= '<p>' . htmlspecialchars($alert['message'] ?? '') . '</p>';
                $html .= '</div>';
            }

            $html .= '</div></div>';
        }

        $html .= $this->getFooterHtml();

        return $this->renderPdf($html, 'dashboard_executivo', false);
    }

    /**
     * Gera PDF de análise de anúncio
     */
    public function generateListingAnalysis(array $listing, array $seoScore = []): string
    {
        $html = $this->getBaseHtml('Análise de Anúncio');

        $html .= '<div class="header-info">';
        $html .= '<p><strong>ID do Anúncio:</strong> ' . ($listing['id'] ?? '-') . '</p>';
        $html .= '<p><strong>Gerado em:</strong> ' . date('d/m/Y H:i:s') . '</p>';
        $html .= '</div>';

        // Informações do anúncio
        $html .= '<div class="listing-header">';
        $html .= '<h2>' . htmlspecialchars($listing['title'] ?? 'Título não disponível') . '</h2>';
        $html .= '<div class="listing-meta">';
        $html .= '<span class="price">' . $this->formatCurrency($listing['price'] ?? 0) . '</span>';
        $html .= '<span class="condition">' . ($listing['condition'] === 'new' ? 'Novo' : 'Usado') . '</span>';
        $html .= '</div>';
        $html .= '</div>';

        // Score SEO
        if (!empty($seoScore)) {
            $score = $seoScore['total_score'] ?? 0;
            $scoreClass = $score >= 80 ? 'excellent' : ($score >= 60 ? 'good' : ($score >= 40 ? 'fair' : 'poor'));

            $html .= '<div class="seo-score-section">';
            $html .= '<h2>Score SEO</h2>';
            $html .= '<div class="score-circle ' . $scoreClass . '">';
            $html .= '<span class="score-value">' . $score . '</span>';
            $html .= '<span class="score-label">/ 100</span>';
            $html .= '</div>';

            // Detalhes do score
            $html .= '<div class="score-details">';
            foreach ($seoScore['components'] ?? [] as $component => $value) {
                $html .= '<div class="score-item">';
                $html .= '<span class="score-item-label">' . ucfirst($component) . '</span>';
                $html .= '<div class="score-item-bar">';
                $html .= '<div class="score-item-fill" style="width: ' . $value . '%"></div>';
                $html .= '</div>';
                $html .= '<span class="score-item-value">' . $value . '%</span>';
                $html .= '</div>';
            }
            $html .= '</div></div>';
        }

        // Recomendações
        if (!empty($seoScore['recommendations'])) {
            $html .= '<div class="section">';
            $html .= '<h2>Recomendações</h2>';
            $html .= '<div class="recommendations-list">';

            foreach ($seoScore['recommendations'] as $rec) {
                $priorityClass = $rec['priority'] ?? 'medium';
                $html .= '<div class="recommendation-item ' . $priorityClass . '">';
                $html .= '<strong>' . htmlspecialchars($rec['title'] ?? '') . '</strong>';
                $html .= '<p>' . htmlspecialchars($rec['description'] ?? '') . '</p>';
                $html .= '</div>';
            }

            $html .= '</div></div>';
        }

        $html .= $this->getFooterHtml();

        return $this->renderPdf($html, 'analise_anuncio_' . ($listing['id'] ?? 'novo'), false);
    }

    /**
     * HTML base para todos os relatórios
     */
    private function getBaseHtml(string $title, bool $landscape = false): string
    {
        $orientation = $landscape ? 'landscape' : 'portrait';

        return '<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        @page { size: A4 ' . $orientation . '; margin: 15mm; }

        * { box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, Arial, sans-serif;
            font-size: 11px;
            line-height: 1.4;
            color: #333;
            margin: 0;
            padding: 0;
        }

        .report-header {
            background: linear-gradient(135deg, ' . $this->config['primary_color'] . ' 0%, #1a237e 100%);
            color: white;
            padding: 20px;
            margin: -15mm -15mm 20px -15mm;
            text-align: center;
        }

        .report-header h1 {
            margin: 0 0 5px 0;
            font-size: 22px;
            font-weight: bold;
        }

        .report-header .subtitle {
            font-size: 12px;
            opacity: 0.9;
        }

        .header-info {
            background-color: #f8f9fa;
            padding: 12px 15px;
            border-radius: 6px;
            margin-bottom: 20px;
            border-left: 4px solid ' . $this->config['primary_color'] . ';
        }

        .header-info p {
            margin: 3px 0;
            font-size: 10px;
        }

        .kpi-grid {
            display: table;
            width: 100%;
            margin-bottom: 20px;
        }

        .kpi-card {
            display: table-cell;
            width: 25%;
            padding: 5px;
        }

        .kpi-card-inner {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            text-align: center;
            border-top: 3px solid ' . $this->config['primary_color'] . ';
        }

        .kpi-value {
            font-size: 18px;
            font-weight: bold;
            color: ' . $this->config['primary_color'] . ';
            margin-bottom: 5px;
        }

        .kpi-label {
            font-size: 9px;
            color: #666;
            text-transform: uppercase;
        }

        .section {
            margin-bottom: 25px;
            page-break-inside: avoid;
        }

        .section h2 {
            font-size: 14px;
            color: ' . $this->config['secondary_color'] . ';
            border-bottom: 2px solid ' . $this->config['primary_color'] . ';
            padding-bottom: 8px;
            margin-bottom: 15px;
        }

        .data-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 10px;
        }

        .data-table th {
            background-color: ' . $this->config['primary_color'] . ';
            color: white;
            padding: 10px 8px;
            text-align: left;
            font-weight: bold;
        }

        .data-table td {
            padding: 8px;
            border-bottom: 1px solid #e0e0e0;
        }

        .data-table tr:nth-child(even) {
            background-color: #f9f9f9;
        }

        .data-table tr:hover {
            background-color: #f0f7ff;
        }

        .rank {
            font-weight: bold;
            color: ' . $this->config['primary_color'] . ';
            text-align: center;
            width: 30px;
        }

        .positive { color: #28a745; font-weight: bold; }
        .negative { color: #dc3545; font-weight: bold; }

        .progress-bar-container {
            display: inline-block;
            width: 60px;
            height: 8px;
            background-color: #e0e0e0;
            border-radius: 4px;
            margin-right: 8px;
            vertical-align: middle;
        }

        .progress-bar {
            height: 100%;
            background-color: ' . $this->config['primary_color'] . ';
            border-radius: 4px;
        }

        .distribution-grid {
            display: table;
            width: 100%;
        }

        .distribution-item {
            display: table-cell;
            width: 50%;
            padding: 15px;
            text-align: center;
        }

        .distribution-item.catalog {
            background-color: #e3f2fd;
            border-radius: 8px 0 0 8px;
        }

        .distribution-item.common {
            background-color: #fff3e0;
            border-radius: 0 8px 8px 0;
        }

        .distribution-value {
            font-size: 24px;
            font-weight: bold;
            color: #333;
        }

        .distribution-label {
            font-size: 11px;
            color: #666;
            margin: 5px 0;
        }

        .distribution-percent {
            font-size: 14px;
            font-weight: bold;
            color: ' . $this->config['primary_color'] . ';
        }

        .status-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 12px;
            font-size: 9px;
            font-weight: bold;
            text-transform: uppercase;
        }

        .status-paid { background: #d4edda; color: #155724; }
        .status-confirmed { background: #cce5ff; color: #004085; }
        .status-shipped { background: #fff3cd; color: #856404; }
        .status-delivered { background: #d4edda; color: #155724; }
        .status-cancelled { background: #f8d7da; color: #721c24; }
        .status-unknown { background: #e2e3e5; color: #383d41; }

        .reputation-badge {
            display: inline-block;
            padding: 2px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: bold;
        }

        .reputation-green { background: #28a745; color: white; }
        .reputation-light_green { background: #5cb85c; color: white; }
        .reputation-yellow { background: #ffc107; color: #333; }
        .reputation-orange { background: #fd7e14; color: white; }
        .reputation-red { background: #dc3545; color: white; }

        .executive-kpis { margin-bottom: 25px; }

        .kpi-row {
            display: table;
            width: 100%;
            margin-bottom: 10px;
        }

        .executive-kpi {
            display: table-cell;
            width: 33.33%;
            padding: 5px;
        }

        .executive-kpi-inner {
            padding: 15px;
            border-radius: 8px;
            text-align: center;
        }

        .executive-kpi-inner.blue { background: #e3f2fd; border-left: 4px solid #1976d2; }
        .executive-kpi-inner.green { background: #e8f5e9; border-left: 4px solid #388e3c; }
        .executive-kpi-inner.purple { background: #f3e5f5; border-left: 4px solid #7b1fa2; }
        .executive-kpi-inner.orange { background: #fff3e0; border-left: 4px solid #f57c00; }
        .executive-kpi-inner.teal { background: #e0f2f1; border-left: 4px solid #00796b; }
        .executive-kpi-inner.gray { background: #f5f5f5; border-left: 4px solid #616161; }

        .executive-kpi-value {
            font-size: 20px;
            font-weight: bold;
            color: #333;
        }

        .executive-kpi-label {
            font-size: 10px;
            color: #666;
            margin-top: 5px;
        }

        .executive-kpi-growth {
            font-size: 10px;
            margin-top: 3px;
        }

        .alerts-list { margin-top: 10px; }

        .alert-item {
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 10px;
        }

        .alert-item.critical { background: #f8d7da; border-left: 4px solid #dc3545; }
        .alert-item.warning { background: #fff3cd; border-left: 4px solid #ffc107; }
        .alert-item.info { background: #cce5ff; border-left: 4px solid #007bff; }

        .alert-item strong { display: block; margin-bottom: 3px; }
        .alert-item p { margin: 0; font-size: 10px; color: #666; }

        .seo-score-section {
            text-align: center;
            margin-bottom: 25px;
        }

        .score-circle {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: inline-block;
            text-align: center;
            line-height: 100px;
            margin: 15px 0;
        }

        .score-circle.excellent { background: linear-gradient(135deg, #28a745, #20c997); color: white; }
        .score-circle.good { background: linear-gradient(135deg, #17a2b8, #20c997); color: white; }
        .score-circle.fair { background: linear-gradient(135deg, #ffc107, #fd7e14); color: #333; }
        .score-circle.poor { background: linear-gradient(135deg, #dc3545, #c82333); color: white; }

        .score-value { font-size: 28px; font-weight: bold; }
        .score-label { font-size: 12px; }

        .score-details { margin-top: 20px; }

        .score-item {
            display: table;
            width: 100%;
            margin-bottom: 8px;
        }

        .score-item-label {
            display: table-cell;
            width: 100px;
            text-align: left;
            font-size: 10px;
        }

        .score-item-bar {
            display: table-cell;
            padding: 0 10px;
        }

        .score-item-bar > div {
            height: 8px;
            background: #e0e0e0;
            border-radius: 4px;
        }

        .score-item-fill {
            height: 100%;
            background: ' . $this->config['primary_color'] . ';
            border-radius: 4px;
        }

        .score-item-value {
            display: table-cell;
            width: 50px;
            text-align: right;
            font-size: 10px;
            font-weight: bold;
        }

        .recommendations-list { margin-top: 10px; }

        .recommendation-item {
            padding: 10px 15px;
            border-radius: 6px;
            margin-bottom: 10px;
            border-left: 4px solid;
        }

        .recommendation-item.high {
            background: #fff3e0;
            border-color: #f57c00;
        }

        .recommendation-item.medium {
            background: #fff8e1;
            border-color: #ffc107;
        }

        .recommendation-item.low {
            background: #e8f5e9;
            border-color: #4caf50;
        }

        .recommendation-item strong {
            display: block;
            margin-bottom: 5px;
            font-size: 11px;
        }

        .recommendation-item p {
            margin: 0;
            font-size: 10px;
            color: #666;
        }

        .listing-header {
            background: #f8f9fa;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
        }

        .listing-header h2 {
            margin: 0 0 10px 0;
            font-size: 14px;
            border: none;
            padding: 0;
        }

        .listing-meta .price {
            font-size: 18px;
            font-weight: bold;
            color: ' . $this->config['primary_color'] . ';
            margin-right: 15px;
        }

        .listing-meta .condition {
            background: #e0e0e0;
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 10px;
        }

        .footer {
            margin-top: 30px;
            padding-top: 15px;
            border-top: 1px solid #e0e0e0;
            text-align: center;
            font-size: 9px;
            color: #999;
        }
    </style>
</head>
<body>
    <div class="report-header">
        <h1>' . htmlspecialchars($title) . '</h1>
        <div class="subtitle">' . $this->config['company_name'] . '</div>
    </div>';
    }

    /**
     * Rodapé do relatório
     */
    private function getFooterHtml(): string
    {
        return '
    <div class="footer">
        <p>Relatório gerado pelo ' . $this->config['company_name'] . '</p>
        <p>Este documento é confidencial e destinado apenas ao destinatário.</p>
    </div>
</body>
</html>';
    }

    /**
     * Renderiza um card KPI
     */
    private function renderKpiCard(string $label, string $value, string $type = ''): string
    {
        return '<div class="kpi-card">
            <div class="kpi-card-inner">
                <div class="kpi-value">' . $value . '</div>
                <div class="kpi-label">' . htmlspecialchars($label) . '</div>
            </div>
        </div>';
    }

    /**
     * Renderiza KPI executivo com crescimento
     */
    private function renderExecutiveKpi(string $label, string $value, ?float $growth, string $color): string
    {
        $growthHtml = '';
        if ($growth !== null) {
            $growthClass = $growth >= 0 ? 'positive' : 'negative';
            $growthIcon = $growth >= 0 ? '↑' : '↓';
            $growthHtml = '<div class="executive-kpi-growth ' . $growthClass . '">' .
                $growthIcon . ' ' . abs($growth) . '% vs período anterior</div>';
        }

        return '<div class="executive-kpi">
            <div class="executive-kpi-inner ' . $color . '">
                <div class="executive-kpi-value">' . $value . '</div>
                <div class="executive-kpi-label">' . htmlspecialchars($label) . '</div>
                ' . $growthHtml . '
            </div>
        </div>';
    }

    /**
     * Renderiza badge de status
     */
    private function renderStatusBadge(string $status): string
    {
        $statusLabels = [
            'paid' => 'Pago',
            'confirmed' => 'Confirmado',
            'ready_to_ship' => 'Pronto p/ Envio',
            'shipped' => 'Enviado',
            'delivered' => 'Entregue',
            'cancelled' => 'Cancelado',
        ];

        $statusClass = 'status-' . mb_strtolower($status);
        $label = $statusLabels[mb_strtolower($status)] ?? ucfirst($status);

        return '<span class="status-badge ' . $statusClass . '">' . $label . '</span>';
    }

    /**
     * Renderiza badge de reputação
     */
    private function renderReputationBadge(string $reputation): string
    {
        $labels = [
            'green' => '★★★★★',
            'light_green' => '★★★★☆',
            'yellow' => '★★★☆☆',
            'orange' => '★★☆☆☆',
            'red' => '★☆☆☆☆',
        ];

        $label = $labels[$reputation] ?? $reputation;

        return '<span class="reputation-badge reputation-' . $reputation . '">' . $label . '</span>';
    }

    /**
     * Gera PDF de análise de marca
     */
    public function generateBrandAnalysisReport(array $data): string
    {
        $html = $this->getBaseHtml('Análise de Marca - ' . ($data['brand'] ?? 'AWA'));

        $html .= '<div class="header-info">';
        $html .= '<p><strong>Marca Analisada:</strong> ' . htmlspecialchars($data['brand'] ?? 'AWA') . '</p>';
        $html .= '<p><strong>Data da Análise:</strong> ' . ($data['analysis_date'] ?? date('d/m/Y H:i:s')) . '</p>';
        $html .= '<p><strong>Tempo de Execução:</strong> ' . ($data['execution_time'] ?? 'N/A') . '</p>';
        $html .= '</div>';

        // KPIs principais
        $html .= '<div class="kpi-grid">';
        $html .= $this->renderKpiCard('Total de Anúncios', number_format($data['total_listings'] ?? 0), 'listings');
        $html .= $this->renderKpiCard('Score de Consistência', ($data['brand_consistency_score'] ?? 0) . '%', 'score');
        $html .= $this->renderKpiCard('Com Marca', number_format($data['listings_with_brand'] ?? 0), 'with-brand');
        $html .= $this->renderKpiCard('Sem Marca', number_format($data['listings_without_brand'] ?? 0), 'without-brand');
        $html .= '</div>';

        // Status de saúde
        $summary = $data['summary'] ?? [];
        $health = $summary['health_status'] ?? [];

        $html .= '<div class="section health-section">';
        $html .= '<h2>Status de Saúde da Marca</h2>';
        $html .= '<div class="health-indicator ' . ($health['status'] ?? 'unknown') . '">';
        $html .= '<div class="health-score">' . ($health['score'] ?? 0) . '/100</div>';
        $html .= '<div class="health-label">' . ucfirst($health['status'] ?? 'Desconhecido') . '</div>';
        $html .= '</div>';

        if (!empty($health['issues'])) {
            $html .= '<div class="health-issues">';
            $html .= '<p><strong>Áreas de Melhoria:</strong></p>';
            $html .= '<ul>';
            foreach ($health['issues'] as $issue) {
                $html .= '<li>' . htmlspecialchars($issue) . '</li>';
            }
            $html .= '</ul></div>';
        }
        $html .= '</div>';

        // Análise de marca por categoria
        if (!empty($data['categories_analyzed'])) {
            $html .= '<div class="section">';
            $html .= '<h2>Análise por Categoria</h2>';
            $html .= '<table class="data-table">';
            $html .= '<thead><tr><th>Categoria</th><th>ID</th><th>Total</th><th>Com Marca</th><th>Sem Marca</th></tr></thead>';
            $html .= '<tbody>';

            foreach ($data['categories_analyzed'] as $category) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($category['name'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($category['id'] ?? '') . '</td>';
                $html .= '<td>' . number_format($category['total_found'] ?? 0) . '</td>';
                $html .= '<td class="positive">' . number_format($category['with_brand'] ?? 0) . '</td>';
                $html .= '<td class="negative">' . number_format($category['without_brand'] ?? 0) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table></div>';
        }

        // Análise de Preços
        $priceAnalysis = $data['price_analysis'] ?? [];
        if (!empty($priceAnalysis)) {
            $html .= '<div class="section">';
            $html .= '<h2>Análise de Preços</h2>';
            $html .= '<div class="price-grid">';
            $html .= '<div class="price-item"><span class="label">Mínimo</span><span class="value">' . $this->formatCurrency($priceAnalysis['min'] ?? 0) . '</span></div>';
            $html .= '<div class="price-item"><span class="label">Máximo</span><span class="value">' . $this->formatCurrency($priceAnalysis['max'] ?? 0) . '</span></div>';
            $html .= '<div class="price-item"><span class="label">Médio</span><span class="value">' . $this->formatCurrency($priceAnalysis['avg'] ?? 0) . '</span></div>';
            $html .= '<div class="price-item"><span class="label">Mediana</span><span class="value">' . $this->formatCurrency($priceAnalysis['median'] ?? 0) . '</span></div>';
            $html .= '</div>';

            // Faixas de preço
            if (!empty($priceAnalysis['price_ranges'])) {
                $html .= '<h3>Distribuição por Faixa de Preço</h3>';
                $html .= '<table class="data-table">';
                $html .= '<thead><tr><th>Faixa (R$)</th><th>Quantidade</th></tr></thead>';
                $html .= '<tbody>';

                foreach ($priceAnalysis['price_ranges'] as $range => $count) {
                    $html .= '<tr>';
                    $html .= '<td>' . htmlspecialchars($range) . '</td>';
                    $html .= '<td>' . number_format($count) . '</td>';
                    $html .= '</tr>';
                }

                $html .= '</tbody></table>';
            }
            $html .= '</div>';
        }

        // Análise de Frete
        $shippingAnalysis = $data['shipping_analysis'] ?? [];
        if (!empty($shippingAnalysis)) {
            $html .= '<div class="section">';
            $html .= '<h2>Análise de Frete</h2>';
            $html .= '<table class="data-table">';
            $html .= '<thead><tr><th>Tipo</th><th>Quantidade</th><th>Percentual</th></tr></thead>';
            $html .= '<tbody>';

            $html .= '<tr>';
            $html .= '<td>Frete Grátis</td>';
            $html .= '<td>' . number_format($shippingAnalysis['free_shipping']['count'] ?? 0) . '</td>';
            $html .= '<td>' . ($shippingAnalysis['free_shipping']['percentage'] ?? 0) . '%</td>';
            $html .= '</tr>';

            $html .= '<tr>';
            $html .= '<td>Frete Pago</td>';
            $html .= '<td>' . number_format($shippingAnalysis['paid_shipping']['count'] ?? 0) . '</td>';
            $html .= '<td>' . ($shippingAnalysis['paid_shipping']['percentage'] ?? 0) . '%</td>';
            $html .= '</tr>';

            $html .= '<tr>';
            $html .= '<td>Mercado Envios Full</td>';
            $html .= '<td>' . number_format($shippingAnalysis['full_shipping']['count'] ?? 0) . '</td>';
            $html .= '<td>' . ($shippingAnalysis['full_shipping']['percentage'] ?? 0) . '%</td>';
            $html .= '</tr>';

            $html .= '</tbody></table></div>';
        }

        // Top Vendedores
        $topSellers = $summary['top_sellers'] ?? [];
        if (!empty($topSellers)) {
            $html .= '<div class="section">';
            $html .= '<h2>Top Vendedores</h2>';
            $html .= '<table class="data-table">';
            $html .= '<thead><tr><th>#</th><th>Vendedor</th><th>ID</th><th>Anúncios</th></tr></thead>';
            $html .= '<tbody>';

            foreach ($topSellers as $index => $seller) {
                $html .= '<tr>';
                $html .= '<td class="rank">' . ($index + 1) . '</td>';
                $html .= '<td>' . htmlspecialchars($seller['nickname'] ?? 'Desconhecido') . '</td>';
                $html .= '<td>' . ($seller['id'] ?? '') . '</td>';
                $html .= '<td>' . number_format($seller['items_count'] ?? 0) . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table></div>';
        }

        // Lacunas de Dados (Gaps)
        $gaps = $data['gaps_detected'] ?? [];
        if (!empty($gaps)) {
            $html .= '<div class="section gaps-section">';
            $html .= '<h2>Lacunas de Dados Identificadas</h2>';
            $html .= '<p>Total: <strong>' . count($gaps) . '</strong> anúncios com lacunas</p>';
            $html .= '<table class="data-table">';
            $html .= '<thead><tr><th>Item ID</th><th>Tipo</th><th>Título</th></tr></thead>';
            $html .= '<tbody>';

            // Mostrar apenas os primeiros 20
            $gapsToShow = array_slice($gaps, 0, 20);
            foreach ($gapsToShow as $gap) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($gap['item_id'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($this->translateGapType($gap['type'] ?? '')) . '</td>';
                $html .= '<td>' . htmlspecialchars(mb_substr($gap['title'] ?? '', 0, 50)) . '...</td>';
                $html .= '</tr>';
            }

            if (count($gaps) > 20) {
                $html .= '<tr><td colspan="3" class="more-items">... e mais ' . (count($gaps) - 20) . ' itens</td></tr>';
            }

            $html .= '</tbody></table></div>';
        }

        // Inconsistências
        $inconsistencies = $data['inconsistencies'] ?? [];
        if (!empty($inconsistencies)) {
            $html .= '<div class="section inconsistencies-section">';
            $html .= '<h2>Inconsistências Encontradas</h2>';
            $html .= '<p>Total: <strong>' . count($inconsistencies) . '</strong> anúncios com inconsistências</p>';
            $html .= '<table class="data-table">';
            $html .= '<thead><tr><th>Item ID</th><th>Tipo</th><th>Valor Atual</th><th>Valor Esperado</th></tr></thead>';
            $html .= '<tbody>';

            foreach ($inconsistencies as $issue) {
                $html .= '<tr>';
                $html .= '<td>' . htmlspecialchars($issue['item_id'] ?? '') . '</td>';
                $html .= '<td>' . htmlspecialchars($this->translateInconsistencyType($issue['type'] ?? '')) . '</td>';
                $html .= '<td class="negative">' . htmlspecialchars($issue['current_value'] ?? 'N/A') . '</td>';
                $html .= '<td class="positive">' . htmlspecialchars($issue['expected_value'] ?? 'AWA') . '</td>';
                $html .= '</tr>';
            }

            $html .= '</tbody></table></div>';
        }

        // Problemas Críticos
        $criticalIssues = $summary['critical_issues'] ?? [];
        if (!empty($criticalIssues)) {
            $html .= '<div class="section critical-section">';
            $html .= '<h2>⚠️ Problemas Críticos</h2>';

            foreach ($criticalIssues as $issue) {
                $severityClass = $issue['severity'] ?? 'medium';
                $html .= '<div class="critical-item ' . $severityClass . '">';
                $html .= '<div class="critical-type">' . ucfirst($severityClass) . '</div>';
                $html .= '<div class="critical-message">' . htmlspecialchars($issue['message'] ?? '') . '</div>';
                $html .= '<div class="critical-recommendation">' . htmlspecialchars($issue['recommendation'] ?? '') . '</div>';
                $html .= '</div>';
            }

            $html .= '</div>';
        }

        // Recomendações
        $recommendations = $summary['recommendations'] ?? [];
        if (!empty($recommendations)) {
            $html .= '<div class="section recommendations-section">';
            $html .= '<h2>📋 Recomendações</h2>';

            foreach ($recommendations as $rec) {
                $html .= '<div class="recommendation-item">';
                $html .= '<div class="rec-priority">Prioridade ' . ($rec['priority'] ?? 'N/A') . '</div>';
                $html .= '<div class="rec-action"><strong>' . htmlspecialchars($rec['action'] ?? '') . '</strong></div>';
                $html .= '<div class="rec-description">' . htmlspecialchars($rec['description'] ?? '') . '</div>';
                $html .= '<div class="rec-impact">Impacto: ' . htmlspecialchars($rec['impact'] ?? 'N/A') . '</div>';
                $html .= '</div>';
            }

            $html .= '</div>';
        }

        $html .= $this->getFooterHtml();

        return $this->renderPdf($html, 'analise_marca_' . strtolower($data['brand'] ?? 'awa'));
    }

    /**
     * Traduz tipo de gap
     */
    private function translateGapType(string $type): string
    {
        $translations = [
            'missing_brand' => 'Marca ausente',
            'brand_in_title_not_attribute' => 'Marca no título, não no atributo',
            'other' => 'Outro',
        ];

        return $translations[$type] ?? $type;
    }

    /**
     * Traduz tipo de inconsistência
     */
    private function translateInconsistencyType(string $type): string
    {
        $translations = [
            'wrong_brand' => 'Marca incorreta',
            'misspelled_brand' => 'Marca com erro de digitação',
            'other' => 'Outro',
        ];

        return $translations[$type] ?? $type;
    }

    /**
     * Formata moeda brasileira
     */
    private function formatCurrency(float $value): string
    {
        return 'R$ ' . number_format($value, 2, ',', '.');
    }

    /**
     * Formata período
     */
    private function formatPeriod(string $period, array $data): string
    {
        switch ($period) {
            case 'day':
                return date('d/m/Y');
            case 'week':
                return 'Semana ' . date('W/Y');
            case 'month':
                return date('F Y');
            case 'year':
                return date('Y');
            case 'custom':
                $from = $data['date_from'] ?? date('Y-m-01');
                $to = $data['date_to'] ?? date('Y-m-d');
                return date('d/m/Y', strtotime($from)) . ' a ' . date('d/m/Y', strtotime($to));
            default:
                return $period;
        }
    }

    /**
     * Renderiza o PDF
     */
    private function renderPdf(string $html, string $filename, bool $stream = true): string
    {
        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', 'portrait');
        $this->dompdf->render();

        if ($stream) {
            // Stream para download
            $this->dompdf->stream($filename . '_' . date('Y-m-d') . '.pdf', [
                'Attachment' => true
            ]);
            return $filename;
        } else {
            return $this->dompdf->output();
        }
    }

    /**
     * Retorna o binário do PDF (para envio por email, etc)
     */
    public function getPdfOutput(string $html): string
    {
        return $this->renderPdf($html, 'temp', false);
    }

    /**
     * Retorna PDF como string (para salvar ou enviar por email)
     */
    public function getPdfContent(string $html): string
    {
        $this->dompdf->loadHtml($html);
        $this->dompdf->setPaper('A4', 'portrait');
        $this->dompdf->render();

        return $this->dompdf->output();
    }
}
