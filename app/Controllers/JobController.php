<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\JobService;
use App\Core\Validator;

class JobController extends BaseController
{
    private JobService $jobService;

    public function __construct(JobService $jobService)
    {
        parent::__construct();
        $this->jobService = $jobService;
    }

    /**
     * Endpoint para consultar status de jobs específicos (Polling)
     */
    public function status()
    {
        $this->requireUserId();
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true);

        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON input']);
            return;
        }

        $validator = Validator::make($input, [
            'job_ids' => 'required|array'
        ]);

        if ($validator->fails()) {
            http_response_code(422);
            echo json_encode(['error' => 'Validation Error', 'details' => $validator->errors()]);
            return;
        }

        $statuses = $this->jobService->getJobsStatus($input['job_ids']);

        // Calcular resumo
        $summary = [
            'total' => count($input['job_ids']),
            'pending' => 0,
            'processing' => 0,
            'completed' => 0,
            'failed' => 0,
            'details' => $statuses
        ];

        foreach ($statuses as $job) {
            if (isset($summary[$job['status']])) {
                $summary[$job['status']]++;
            }
        }

        echo json_encode($summary);
    }

    /**
     * Estatísticas gerais dos jobs
     */
    public function stats()
    {
        $this->requireUserId();
        header('Content-Type: application/json');
        $stats = $this->jobService->getStats();
        echo json_encode($stats);
    }

    /**
     * Retorna detalhes de um job específico
     * GET /api/jobs/{id}
     */
    public function getJob(int $id)
    {
        $this->requireUserId();
        header('Content-Type: application/json');

        $job = $this->jobService->getJobPublic((int)$id);
        if (!$job) {
            http_response_code(404);
            echo json_encode(['error' => 'Job não encontrado']);
            return;
        }

        echo json_encode($job);
    }

    /**
     * Dispara um job manualmente (Dev/Test)
     */
    public function dispatch()
    {
        $this->requireUserId();
        if (!$this->isAdmin()) {
            $this->jsonError('Acesso negado — requer permissão de administrador', 403);
        }
        header('Content-Type: application/json');

        $input = json_decode(file_get_contents('php://input'), true);

        // Validação básica
        if (empty($input['type'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Type is required']);
            return;
        }

        $payload = $input['payload'] ?? [];
        $jobId = $this->jobService->dispatch($input['type'], $payload);

        http_response_code(201);
        echo json_encode(['job_id' => $jobId, 'status' => 'queued']);
    }

    /**
     * Processa jobs manualmente (se não houver worker)
     */
    public function process()
    {
        $this->requireUserId();
        if (!$this->isAdmin()) {
            $this->jsonError('Acesso negado — requer permissão de administrador', 403);
        }
        header('Content-Type: application/json');

        // Limite opcional via query param
        $limit = $this->request->getInt('limit', 5);

        $processed = $this->jobService->process($limit);

        echo json_encode([
            'processed_count' => count($processed),
            'results' => $processed
        ]);
    }

    /**
     * Limpa jobs antigos
     */
    public function clean()
    {
        $this->requireUserId();
        if (!$this->isAdmin()) {
            $this->jsonError('Acesso negado — requer permissão de administrador', 403);
        }
        header('Content-Type: application/json');

        $days = $this->request->getInt('days', 30);
        $deleted = $this->jobService->cleanOldJobs($days);

        echo json_encode(['deleted_count' => $deleted]);
    }
}
