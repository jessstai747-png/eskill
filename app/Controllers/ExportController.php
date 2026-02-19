<?php

namespace App\Controllers;

use App\Core\Request;
use App\Services\ExportService;
use App\Services\SearchService;
use App\Services\UserService;
use App\Helpers\SessionHelper;

class ExportController
{
    private ExportService $exportService;
    private SearchService $searchService;
    private UserService $userService;
    private Request $request;

    public function __construct()
    {
        $this->request = new Request();
        $this->exportService = new ExportService();
        $this->userService = new UserService();
        $accountId = SessionHelper::getActiveAccountId();
        $this->searchService = new SearchService($accountId);
    }

    /**
     * Exporta dados do usuário (JSON)
     */
    public function userDataJSON(): void
    {
        if (!$this->userService->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $data = $this->userService->getUserDataForExport($userId);
        $this->exportService->exportUserDataToJSON($data);
    }

    /**
     * Exporta dados do usuário (CSV/ZIP)
     */
    public function userDataCSV(): void
    {
        if (!$this->userService->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        $userId = $_SESSION['user_id'];
        $data = $this->userService->getUserDataForExport($userId);
        $this->exportService->exportUserDataToCSV($data);
    }

    /**
     * Exporta análise para CSV
     */
    public function analysisCSV(): void
    {
        $categoryId = $this->request->get('category');
        $brand = $this->request->get('brand');

        if (!$categoryId || !$brand) {
            http_response_code(400);
            echo json_encode(['error' => 'Parâmetros "category" e "brand" são obrigatórios']);
            return;
        }

        $analysis = $this->searchService->analyzeListings($categoryId, $brand);

        if (isset($analysis['error'])) {
            http_response_code(500);
            echo json_encode($analysis);
            return;
        }

        $this->exportService->exportAnalysisToCSV($analysis);
    }

    /**
     * Exporta análise para JSON
     */
    public function analysisJSON(): void
    {
        $categoryId = $this->request->get('category');
        $brand = $this->request->get('brand');

        if (!$categoryId || !$brand) {
            http_response_code(400);
            echo json_encode(['error' => 'Parâmetros "category" e "brand" são obrigatórios']);
            return;
        }

        $analysis = $this->searchService->analyzeListings($categoryId, $brand);

        if (isset($analysis['error'])) {
            http_response_code(500);
            echo json_encode($analysis);
            return;
        }

        $this->exportService->exportToJSON($analysis, 'analise_anuncios');
    }

    /**
     * Exporta relatório para PDF
     */
    public function reportPDF(): void
    {
        $reportType = $this->request->get('type', 'general') ?? 'general';

        $reportService = new \App\Services\ReportService();
        $reportData = [];

        switch ($reportType) {
            case 'account':
                $accountId = $this->request->get('account_id');
                if ($accountId) {
                    $method = 'getReportByAccount';
                    if (is_callable([$reportService, $method])) {
                        $reportData = $reportService->{$method}((int)$accountId);
                    }
                }
                break;

            case 'category':
                $categoryId = $this->request->get('category_id');
                if ($categoryId) {
                    $method = 'getReportByCategory';
                    if (is_callable([$reportService, $method])) {
                        $reportData = $reportService->{$method}($categoryId);
                    }
                }
                break;

            case 'consolidated':
                $method = 'getConsolidatedReport';
                if (is_callable([$reportService, $method])) {
                    $reportData = $reportService->{$method}();
                }
                break;

            default:
                http_response_code(400);
                echo json_encode(['error' => 'Tipo de relatório inválido']);
                return;
        }

        if (empty($reportData)) {
            http_response_code(400);
            echo json_encode(['error' => 'Dados do relatório não encontrados']);
            return;
        }

        // Preparar dados para PDF
        $pdfData = [
            'summary' => $reportData['consolidated'] ?? $reportData,
            'tables' => [],
        ];

        $html = $this->exportService->exportReportToPDF($pdfData, 'Relatório ' . ucfirst($reportType));
        $this->exportService->generatePDF($html, 'relatorio_' . $reportType);
    }
}
