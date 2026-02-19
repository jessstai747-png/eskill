<?php

namespace App\Services;

/**
 * Serviço de exportação de dados
 *
 * Exporta dados em JSON, CSV e PDF para download.
 */
class ExportService
{
    /**
     * Exportar dados do usuário como JSON
     */
    public function exportUserDataToJSON(array $data): void
    {
        $filename = 'dados_usuario_' . date('Y-m-d_His') . '.json';

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Cache-Control: no-cache, no-store, must-revalidate');

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Exportar dados do usuário como CSV
     */
    public function exportUserDataToCSV(array $data): void
    {
        $filename = 'dados_usuario_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        if (!empty($data)) {
            $firstKey = array_key_first($data);
            $firstSection = $data[$firstKey];

            if (is_array($firstSection) && !empty($firstSection)) {
                $rows = isset($firstSection[0]) ? $firstSection : [$firstSection];
                fputcsv($output, array_keys(is_array($rows[0]) ? $rows[0] : $rows), ';');
                foreach ($rows as $row) {
                    if (is_array($row)) {
                        fputcsv($output, array_map(function ($v) {
                            return is_array($v) ? json_encode($v) : $v;
                        }, $row), ';');
                    }
                }
            }
        }

        fclose($output);
        exit;
    }

    /**
     * Exportar análise de anúncios como CSV
     */
    public function exportAnalysisToCSV(array $analysis): void
    {
        $filename = 'analise_anuncios_' . date('Y-m-d_His') . '.csv';

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        $output = fopen('php://output', 'w');
        fprintf($output, chr(0xEF) . chr(0xBB) . chr(0xBF));

        $items = $analysis['items'] ?? $analysis['results'] ?? [];

        if (!empty($items)) {
            fputcsv($output, array_keys($items[0]), ';');
            foreach ($items as $item) {
                fputcsv($output, array_map(function ($v) {
                    return is_array($v) ? json_encode($v) : $v;
                }, $item), ';');
            }
        }

        fclose($output);
        exit;
    }

    /**
     * Exportar dados genéricos como JSON
     */
    public function exportToJSON(array $data, string $prefix = 'export'): void
    {
        $filename = $prefix . '_' . date('Y-m-d_His') . '.json';

        header('Content-Type: application/json; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . $filename . '"');

        echo json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        exit;
    }

    /**
     * Gerar HTML para relatório PDF
     */
    public function exportReportToPDF(array $data, string $title = 'Relatório'): string
    {
        $html = '<!DOCTYPE html><html><head><meta charset="utf-8">';
        $html .= '<style>body{font-family:Arial,sans-serif;margin:20px}';
        $html .= 'h1{color:#333}table{width:100%;border-collapse:collapse;margin:15px 0}';
        $html .= 'th,td{border:1px solid #ddd;padding:8px;text-align:left}';
        $html .= 'th{background:#f4f4f4}</style></head><body>';
        $html .= '<h1>' . htmlspecialchars($title) . '</h1>';
        $html .= '<p>Gerado em: ' . date('d/m/Y H:i:s') . '</p>';

        if (isset($data['summary'])) {
            $html .= '<h2>Resumo</h2><table>';
            foreach ($data['summary'] as $key => $value) {
                if (!is_array($value)) {
                    $html .= '<tr><th>' . htmlspecialchars($key) . '</th>';
                    $html .= '<td>' . htmlspecialchars((string) $value) . '</td></tr>';
                }
            }
            $html .= '</table>';
        }

        $html .= '</body></html>';
        return $html;
    }

    /**
     * Gerar e enviar PDF usando Dompdf
     */
    public function generatePDF(string $html, string $prefix = 'relatorio'): void
    {
        $filename = $prefix . '_' . date('Y-m-d_His') . '.pdf';

        $dompdf = new \Dompdf\Dompdf(['defaultFont' => 'Arial']);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        echo $dompdf->output();
        exit;
    }
}
