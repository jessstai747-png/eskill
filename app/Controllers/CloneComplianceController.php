<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\CloneComplianceService;

/**
 * CloneComplianceController - API para Compliance e Auditoria
 */
class CloneComplianceController extends BaseController
{
    private CloneComplianceService $complianceService;
    private ?int $accountId;

    public function __construct()
    {
        parent::__construct();
        $this->accountId = $_SESSION['account_id'] ?? null;
        $this->complianceService = new CloneComplianceService($this->accountId);
    }

    /**
     * GET /api/clone/compliance/logs
     * Lista logs de auditoria
     */
    public function getLogs(): void
    {
        try {
            $filters = [
                'user_id' => $this->request->get('user_id'),
                'job_id' => $this->request->get('job_id'),
                'item_id' => $this->request->get('item_id'),
                'event_type' => $this->request->get('event_type'),
                'severity' => $this->request->get('severity'),
                'date_from' => $this->request->get('date_from'),
                'date_to' => $this->request->get('date_to'),
                'search' => $this->request->get('search'),
                'limit' => $this->request->getInt('limit', 50),
                'offset' => $this->request->getInt('offset', 0),
            ];

            $result = $this->complianceService->getAuditLogs(array_filter($filters));

            $this->jsonResponse([
                'success' => true,
                'data' => $result,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/clone/compliance/jobs/{jobId}/trail
     * Trilha de auditoria de um job
     */
    public function getJobTrail(int $jobId): void
    {
        try {
            $trail = $this->complianceService->getJobAuditTrail($jobId);

            $this->jsonResponse([
                'success' => true,
                'data' => $trail,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/clone/compliance/items/{itemId}/trail
     * Trilha de auditoria de um item
     */
    public function getItemTrail(string $itemId): void
    {
        try {
            $trail = $this->complianceService->getItemAuditTrail($itemId);

            $this->jsonResponse([
                'success' => true,
                'data' => $trail,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/clone/compliance/report
     * Gera relatório de compliance
     */
    public function getReport(): void
    {
        try {
            $period = $this->request->get('period', '30d');
            $report = $this->complianceService->generateComplianceReport($period);

            $this->jsonResponse([
                'success' => true,
                'data' => $report,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/clone/compliance/stats
     * Estatísticas para dashboard
     */
    public function getStats(): void
    {
        try {
            $stats = $this->complianceService->getDashboardStats();

            $this->jsonResponse([
                'success' => true,
                'data' => $stats,
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * POST /api/clone/compliance/export
     * Exporta logs para CSV
     */
    public function exportLogs(): void
    {
        try {
            $input = $this->request->json() ?? [];
            
            $filters = [
                'user_id' => $input['user_id'] ?? null,
                'job_id' => $input['job_id'] ?? null,
                'event_type' => $input['event_type'] ?? null,
                'severity' => $input['severity'] ?? null,
                'date_from' => $input['date_from'] ?? null,
                'date_to' => $input['date_to'] ?? null,
            ];

            $filepath = $this->complianceService->exportAuditLogs(array_filter($filters));
            $filename = basename($filepath);

            $this->jsonResponse([
                'success' => true,
                'data' => [
                    'filename' => $filename,
                    'download_url' => "/api/clone/compliance/download/{$filename}",
                ],
            ]);
        } catch (\Exception $e) {
            $this->jsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * GET /api/clone/compliance/download/{filename}
     * Download de arquivo exportado
     */
    public function downloadExport(string $filename): void
    {
        $filepath = __DIR__ . '/../../storage/exports/' . basename($filename);

        if (!file_exists($filepath)) {
            http_response_code(404);
            echo json_encode(['error' => 'Arquivo não encontrado']);
            return;
        }

        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filepath));
        readfile($filepath);
        exit;
    }

    /**
     * GET /api/clone/compliance/event-types
     * Lista tipos de eventos disponíveis
     */
    public function getEventTypes(): void
    {
        $eventTypes = [
            'clone_started' => 'Job de clonagem iniciado',
            'clone_completed' => 'Job de clonagem concluído',
            'clone_failed' => 'Job de clonagem falhou',
            'clone_cancelled' => 'Job de clonagem cancelado',
            'item_cloned' => 'Item clonado com sucesso',
            'item_failed' => 'Falha ao clonar item',
            'item_skipped' => 'Item ignorado',
            'price_modified' => 'Preço modificado durante clone',
            'title_modified' => 'Título modificado durante clone',
            'seo_applied' => 'Otimização SEO aplicada',
            'automation_triggered' => 'Automação disparada',
            'rule_matched' => 'Regra de automação correspondeu',
            'settings_changed' => 'Configurações alteradas',
            'export_generated' => 'Relatório exportado',
            'api_access' => 'Acesso via API',
            'suspicious_activity' => 'Atividade suspeita detectada',
        ];

        $this->jsonResponse([
            'success' => true,
            'data' => $eventTypes,
        ]);
    }

    /**
     * Resposta JSON padronizada
     */
    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
