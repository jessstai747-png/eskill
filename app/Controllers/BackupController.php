<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\BackupService;

class BackupController extends BaseController
{
    private BackupService $backupService;

    public function __construct()
    {
        parent::__construct();
        $this->backupService = new BackupService();
    }

    /**
     * Cria backup do banco de dados
     * POST /api/backup/create
     */
    public function create(): void
    {
        $this->requireUserId();
        if (!$this->isAdmin()) {
            $this->jsonError('Acesso negado — requer permissão de administrador', 403);
        }

        $result = $this->backupService->createDatabaseBackup();

        if (!$result['success']) {
            http_response_code(500);
        }

        header('Content-Type: application/json');
        echo json_encode($result);
    }

    /**
     * Lista backups disponíveis
     * GET /api/backup/list
     */
    public function list(): void
    {
        $this->requireUserId();
        if (!$this->isAdmin()) {
            $this->jsonError('Acesso negado — requer permissão de administrador', 403);
        }

        $backups = $this->backupService->listBackups();

        header('Content-Type: application/json');
        echo json_encode($backups);
    }

    /**
     * Restaura um backup
     * POST /api/backup/restore
     */
    public function restore(): void
    {
        $this->requireUserId();
        if (!$this->isAdmin()) {
            $this->jsonError('Acesso negado — requer permissão de administrador', 403);
        }

        $data = json_decode(file_get_contents('php://input'), true);

        if (!isset($data['filename'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Campo "filename" é obrigatório']);
            return;
        }

        $result = $this->backupService->restoreBackup($data['filename']);

        if (!$result['success']) {
            http_response_code(500);
        }

        header('Content-Type: application/json');
        echo json_encode($result);
    }

    /**
     * Limpa backups antigos
     * POST /api/backup/clean
     */
    public function clean(): void
    {
        $this->requireUserId();
        if (!$this->isAdmin()) {
            $this->jsonError('Acesso negado — requer permissão de administrador', 403);
        }

        $days = $this->request->getInt('days', 30);

        $deleted = $this->backupService->cleanOldBackups($days);

        header('Content-Type: application/json');
        echo json_encode([
            'deleted' => $deleted,
            'days' => $days,
        ]);
    }
}
