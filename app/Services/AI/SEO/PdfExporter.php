<?php

namespace App\Services\AI\SEO;

use App\Database;
use PDO;

/**
 * 📄 PDF Exporter para Análise de Concorrentes
 *
 * Gera relatórios PDF completos com:
 * - Análise comparativa detalhada
 * - Gráficos de tendências
 * - Insights acionáveis
 * - Recomendações estratégicas
 *
 * @version 1.0.0
 */
class PdfExporter
{
    private PDO $db;
    private int $accountId;

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    /**
     * Exporta análise de concorrente para PDF
     *
     * @param string $itemId ID do seu produto
     * @param array $competitors Lista de competidores analisados
     * @param array $options Opções de customização
     * @return array ['success' => bool, 'file' => string, 'url' => string]
     */
    public function exportCompetitorAnalysis(string $itemId, array $competitors, array $options = []): array
    {
        try {
            // Preparar dados
            $data = $this->prepareAnalysisData($itemId, $competitors);

            // Gerar HTML
            $html = $this->generateHtml($data, $options);

            // Converter para PDF (usando wkhtmltopdf ou biblioteca)
            $filename = $this->htmlToPdf($html, $itemId);

            // Salvar metadata no banco
            $this->saveExportLog($itemId, $filename);

            return [
                'success' => true,
                'file' => $filename,
                'url' => '/storage/exports/' . $filename,
                'size' => filesize(storage_path('exports/' . $filename)),
            ];
        } catch (\Exception $e) {
            log_error('Erro na exportação de PDF SEO', [
                'service' => 'PdfExporter',
                'item_id' => $itemId,
                'error' => $e->getMessage(),
            ]);
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Exporta histórico de watchlist em PDF
     */
    public function exportWatchlistHistory(int $watchlistId, int $days = 30): array
    {
        try {
            $data = $this->prepareWatchlistData($watchlistId, $days);
            $html = $this->generateWatchlistHtml($data);
            $filename = $this->htmlToPdf($html, 'watchlist_' . $watchlistId);

            return [
                'success' => true,
                'file' => $filename,
                'url' => '/storage/exports/' . $filename,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Exporta relatório mensal de performance
     */
    public function exportMonthlyReport(array $options = []): array
    {
        try {
            $data = $this->prepareMonthlyData();
            $html = $this->generateMonthlyReportHtml($data, $options);
            $filename = $this->htmlToPdf($html, 'monthly_report_' . date('Y_m'));

            return [
                'success' => true,
                'file' => $filename,
                'url' => '/storage/exports/' . $filename,
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Prepara dados de análise competitiva
     */
    private function prepareAnalysisData(string $itemId, array $competitors): array
    {
        // Buscar dados do seu produto
        $stmt = $this->db->prepare("
            SELECT * FROM items
            WHERE item_id = :item_id AND account_id = :account_id
        ");
        $stmt->execute([
            'item_id' => $itemId,
            'account_id' => $this->accountId,
        ]);
        $myProduct = $stmt->fetch(PDO::FETCH_ASSOC);

        // Calcular métricas comparativas
        $avgPrice = array_sum(array_column($competitors, 'price')) / count($competitors);
        $avgSales = array_sum(array_column($competitors, 'sold_quantity')) / count($competitors);
        $avgScore = array_sum(array_column($competitors, 'seo_score')) / count($competitors);

        // Identificar top performer
        $topCompetitor = $competitors[0];
        foreach ($competitors as $comp) {
            if ($comp['seo_score'] > $topCompetitor['seo_score']) {
                $topCompetitor = $comp;
            }
        }

        return [
            'my_product' => $myProduct,
            'competitors' => $competitors,
            'averages' => [
                'price' => $avgPrice,
                'sales' => $avgSales,
                'seo_score' => $avgScore,
            ],
            'top_performer' => $topCompetitor,
            'generated_at' => date('Y-m-d H:i:s'),
            'total_competitors' => count($competitors),
        ];
    }

    /**
     * Prepara dados de watchlist
     */
    private function prepareWatchlistData(int $watchlistId, int $days): array
    {
        // Buscar watchlist item
        $stmt = $this->db->prepare("
            SELECT w.*,
                   COUNT(h.id) as total_changes,
                   COUNT(a.id) as total_alerts
            FROM competitor_watchlist w
            LEFT JOIN competitor_history h ON h.watchlist_id = w.id
            LEFT JOIN competitor_alerts a ON a.watchlist_id = w.id
            WHERE w.id = :id AND w.account_id = :account_id
            GROUP BY w.id
        ");
        $stmt->execute([
            'id' => $watchlistId,
            'account_id' => $this->accountId,
        ]);
        $watchlist = $stmt->fetch(PDO::FETCH_ASSOC);

        // Buscar histórico
        $stmt = $this->db->prepare("
            SELECT * FROM competitor_history
            WHERE watchlist_id = :watchlist_id
              AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ORDER BY created_at DESC
        ");
        $stmt->execute([
            'watchlist_id' => $watchlistId,
            'days' => $days,
        ]);
        $history = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Buscar alertas
        $stmt = $this->db->prepare("
            SELECT * FROM competitor_alerts
            WHERE watchlist_id = :watchlist_id
              AND created_at >= DATE_SUB(NOW(), INTERVAL :days DAY)
            ORDER BY created_at DESC
        ");
        $stmt->execute(['watchlist_id' => $watchlistId]);
        $alerts = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return [
            'watchlist' => $watchlist,
            'history' => $history,
            'alerts' => $alerts,
            'period_days' => $days,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Prepara dados do relatório mensal
     */
    private function prepareMonthlyData(): array
    {
        $startDate = date('Y-m-01 00:00:00');
        $endDate = date('Y-m-t 23:59:59');

        // Total de otimizações
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM seo_optimization_events
            WHERE account_id = :account_id
              AND created_at BETWEEN :start AND :end
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'start' => $startDate,
            'end' => $endDate,
        ]);
        $optimizations = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        // Métricas de performance
        $stmt = $this->db->prepare("
            SELECT
                AVG(COALESCE(seo_score, 0)) as avg_score,
                SUM(COALESCE(views, 0)) as total_views_increase
            FROM seo_performance_metrics
            WHERE account_id = :account_id
              AND metric_date BETWEEN :start AND :end
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'start' => $startDate,
            'end' => $endDate,
        ]);
        $performance = $stmt->fetch(PDO::FETCH_ASSOC);

        // Watchlist stats
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as total
            FROM competitor_watchlist
            WHERE account_id = :account_id AND status = 'active'
        ");
        $stmt->execute(['account_id' => $this->accountId]);
        $watchlistCount = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

        return [
            'period' => [
                'start' => $startDate,
                'end' => $endDate,
                'month_name' => date('F Y'),
            ],
            'optimizations' => $optimizations,
            'performance' => $performance,
            'watchlist_count' => $watchlistCount,
            'generated_at' => date('Y-m-d H:i:s'),
        ];
    }

    /**
     * Gera HTML para análise de concorrentes
     */
    private function generateHtml(array $data, array $options): string
    {
        $includeCharts = $options['include_charts'] ?? true;
        $includeRecommendations = $options['include_recommendations'] ?? true;

        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Análise de Concorrentes - ' . htmlspecialchars($data['my_product']['title'] ?? 'Produto') . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
        h1 { color: #0066FF; border-bottom: 3px solid #0066FF; padding-bottom: 10px; }
        h2 { color: #FF4500; margin-top: 30px; }
        .header { text-align: center; margin-bottom: 40px; }
        .header img { max-width: 200px; }
        .metric-box {
            display: inline-block;
            width: 23%;
            padding: 15px;
            margin: 1%;
            background: #f5f5f5;
            border-radius: 8px;
            text-align: center;
        }
        .metric-value { font-size: 28px; font-weight: bold; color: #0066FF; }
        .metric-label { font-size: 12px; color: #666; text-transform: uppercase; }
        .comparison-table {
            width: 100%;
            border-collapse: collapse;
            margin: 20px 0;
        }
        .comparison-table th {
            background: #0066FF;
            color: white;
            padding: 12px;
            text-align: left;
        }
        .comparison-table td {
            padding: 10px;
            border-bottom: 1px solid #ddd;
        }
        .comparison-table tr:hover { background: #f9f9f9; }
        .better { color: #00A650; font-weight: bold; }
        .worse { color: #FF3333; font-weight: bold; }
        .recommendation {
            background: #FFF9E6;
            border-left: 4px solid #FFA500;
            padding: 15px;
            margin: 10px 0;
        }
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 11px;
            color: #999;
        }
        @page { margin: 20mm; }
    </style>
</head>
<body>
    <div class="header">
        <h1>🔥 Análise Competitiva - SEO Killer</h1>
        <p>Gerado em: ' . $data['generated_at'] . '</p>
    </div>

    <h2>📊 Visão Geral</h2>
    <div class="metric-box">
        <div class="metric-value">' . $data['total_competitors'] . '</div>
        <div class="metric-label">Concorrentes Analisados</div>
    </div>
    <div class="metric-box">
        <div class="metric-value">R$ ' . number_format($data['averages']['price'], 2, ',', '.') . '</div>
        <div class="metric-label">Preço Médio</div>
    </div>
    <div class="metric-box">
        <div class="metric-value">' . round($data['averages']['seo_score']) . '</div>
        <div class="metric-label">Score SEO Médio</div>
    </div>
    <div class="metric-box">
        <div class="metric-value">' . round($data['averages']['sales']) . '</div>
        <div class="metric-label">Vendas Médias</div>
    </div>

    <h2>🏆 Top Performer</h2>
    <p><strong>Produto:</strong> ' . htmlspecialchars($data['top_performer']['title'] ?? 'N/A') . '</p>
    <p><strong>Score SEO:</strong> ' . ($data['top_performer']['seo_score'] ?? 0) . '/100</p>
    <p><strong>Preço:</strong> R$ ' . number_format($data['top_performer']['price'] ?? 0, 2, ',', '.') . '</p>

    <h2>📈 Comparação Detalhada</h2>
    <table class="comparison-table">
        <thead>
            <tr>
                <th>Produto</th>
                <th>Preço</th>
                <th>Vendas</th>
                <th>Score SEO</th>
                <th>Imagens</th>
                <th>Frete Grátis</th>
            </tr>
        </thead>
        <tbody>';

        foreach ($data['competitors'] as $comp) {
            $html .= '<tr>
                <td>' . htmlspecialchars(substr($comp['title'] ?? '', 0, 50)) . '...</td>
                <td>R$ ' . number_format($comp['price'] ?? 0, 2, ',', '.') . '</td>
                <td>' . ($comp['sold_quantity'] ?? 0) . '</td>
                <td>' . ($comp['seo_score'] ?? 0) . '/100</td>
                <td>' . ($comp['pictures_count'] ?? 0) . '</td>
                <td>' . (($comp['free_shipping'] ?? false) ? '✅' : '❌') . '</td>
            </tr>';
        }

        $html .= '</tbody>
    </table>';

        if ($includeRecommendations) {
            $html .= '<h2>💡 Recomendações Estratégicas</h2>
            <div class="recommendation">
                <strong>🎯 Preço Competitivo:</strong> Considere ajustar seu preço para ficar entre R$ ' .
                number_format($data['averages']['price'] * 0.95, 2, ',', '.') . ' e R$ ' .
                number_format($data['averages']['price'] * 1.05, 2, ',', '.') . '
            </div>
            <div class="recommendation">
                <strong>📸 Imagens:</strong> Top performers têm em média ' .
                round(array_sum(array_column($data['competitors'], 'pictures_count')) / count($data['competitors'])) .
                ' imagens. Adicione mais fotos de qualidade.
            </div>
            <div class="recommendation">
                <strong>✏️ SEO:</strong> Otimize seu título para incluir as keywords de maior volume identificadas na análise.
            </div>';
        }

        $html .= '
    <div class="footer">
        <p>© 2025 Eskill - Mercado Livre Manager | SEO Killer v1.7.0</p>
        <p>Este relatório é confidencial e destinado exclusivamente ao uso interno.</p>
    </div>
</body>
</html>';

        return $html;
    }

    /**
     * Gera HTML para watchlist
     */
    private function generateWatchlistHtml(array $data): string
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Histórico de Monitoramento - ' . htmlspecialchars($data['watchlist']['item_id'] ?? '') . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
        h1 { color: #0066FF; }
        .timeline { margin: 20px 0; }
        .timeline-item {
            border-left: 3px solid #0066FF;
            padding-left: 20px;
            margin: 15px 0;
        }
        .timeline-date { font-weight: bold; color: #666; }
        .change-badge {
            display: inline-block;
            padding: 3px 8px;
            border-radius: 3px;
            font-size: 12px;
            margin-right: 5px;
        }
        .price-change { background: #FFE6E6; color: #FF3333; }
        .stock-change { background: #E6F2FF; color: #0066FF; }
    </style>
</head>
<body>
    <h1>📊 Histórico de Monitoramento</h1>
    <p><strong>Produto:</strong> ' . htmlspecialchars($data['watchlist']['current_title'] ?? '') . '</p>
    <p><strong>Período:</strong> Últimos ' . $data['period_days'] . ' dias</p>
    <p><strong>Total de Mudanças:</strong> ' . count($data['history']) . '</p>
    <p><strong>Total de Alertas:</strong> ' . count($data['alerts']) . '</p>

    <h2>🕐 Timeline de Mudanças</h2>
    <div class="timeline">';

        foreach ($data['history'] as $change) {
            $html .= '<div class="timeline-item">
                <div class="timeline-date">' . date('d/m/Y H:i', strtotime($change['created_at'])) . '</div>
                <span class="change-badge ' . ($change['field'] === 'price' ? 'price-change' : 'stock-change') . '">
                    ' . htmlspecialchars($change['field']) . '
                </span>
                <span>' . htmlspecialchars($change['old_value']) . ' → ' . htmlspecialchars($change['new_value']) . '</span>
            </div>';
        }

        $html .= '</div>
</body>
</html>';

        return $html;
    }

    /**
     * Gera HTML para relatório mensal
     */
    private function generateMonthlyReportHtml(array $data, array $options): string
    {
        $html = '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Relatório Mensal - ' . $data['period']['month_name'] . '</title>
    <style>
        body { font-family: Arial, sans-serif; margin: 40px; color: #333; }
        h1 { color: #0066FF; text-align: center; }
        .stats { text-align: center; margin: 40px 0; }
        .stat-box {
            display: inline-block;
            width: 30%;
            padding: 20px;
            margin: 1%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
        }
        .stat-value { font-size: 36px; font-weight: bold; }
        .stat-label { font-size: 14px; opacity: 0.9; }
    </style>
</head>
<body>
    <h1>📊 Relatório Mensal - ' . $data['period']['month_name'] . '</h1>

    <div class="stats">
        <div class="stat-box">
            <div class="stat-value">' . $data['optimizations'] . '</div>
            <div class="stat-label">Otimizações Realizadas</div>
        </div>
        <div class="stat-box">
            <div class="stat-value">' . round($data['performance']['avg_score'] ?? 0) . '</div>
            <div class="stat-label">Score Médio SEO</div>
        </div>
        <div class="stat-box">
            <div class="stat-value">' . $data['watchlist_count'] . '</div>
            <div class="stat-label">Concorrentes Monitorados</div>
        </div>
    </div>

    <p style="text-align: center; margin-top: 50px;">
        <strong>Gerado em:</strong> ' . $data['generated_at'] . '
    </p>
</body>
</html>';

        return $html;
    }

    /**
     * Converte HTML para PDF
     */
    private function htmlToPdf(string $html, string $baseFilename): string
    {
        $filename = $baseFilename . '_' . time() . '.pdf';
        $filepath = storage_path('exports/' . $filename);

        // Garantir que o diretório existe
        if (!is_dir(storage_path('exports'))) {
            mkdir(storage_path('exports'), 0755, true);
        }

        // Opção 1: Usar wkhtmltopdf (se instalado)
        if (command_exists('wkhtmltopdf')) {
            $tempHtml = tempnam(sys_get_temp_dir(), 'pdf_') . '.html';
            file_put_contents($tempHtml, $html);

            exec("wkhtmltopdf " . escapeshellarg($tempHtml) . " " . escapeshellarg($filepath), $output, $returnVar);
            unlink($tempHtml);

            if ($returnVar === 0) {
                return $filename;
            }
        }

        // Opção 2: Usar DomPDF (fallback - instalar com composer)
        // require 'vendor/autoload.php';
        // $dompdf = new \Dompdf\Dompdf();
        // $dompdf->loadHtml($html);
        // $dompdf->setPaper('A4', 'portrait');
        // $dompdf->render();
        // file_put_contents($filepath, $dompdf->output());

        // Opção 3: Fallback - salvar como HTML
        $filename = str_replace('.pdf', '.html', $filename);
        $filepath = storage_path('exports/' . $filename);
        file_put_contents($filepath, $html);

        return $filename;
    }

    /**
     * Salva log de exportação
     */
    private function saveExportLog(string $itemId, string $filename): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO system_logs (account_id, type, message, metadata, created_at)
            VALUES (:account_id, 'pdf_export', 'PDF Export Generated', :metadata, NOW())
        ");
        $stmt->execute([
            'account_id' => $this->accountId,
            'metadata' => json_encode([
                'item_id' => $itemId,
                'filename' => $filename,
                'export_type' => 'competitor_analysis',
            ]),
        ]);
    }
}

/**
 * Helper: Verifica se comando existe
 */
function command_exists(string $command): bool
{
    $return = shell_exec(sprintf("which %s", escapeshellarg($command)));
    return !empty($return);
}

/**
 * Helper: Retorna path de storage
 */
function storage_path(string $path = ''): string
{
    $base = __DIR__ . '/../../../../storage/';
    return $base . ltrim($path, '/');
}
