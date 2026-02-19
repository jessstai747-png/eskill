<?php

namespace App\Controllers;

use App\Services\AlertService;

class AlertController extends BaseController
{
    private AlertService $alertService;

    public function __construct()
    {
        parent::__construct();
        $this->alertService = new AlertService();
    }

    /**
     * Lista alertas (opcionalmente filtrados por conta e status de leitura)
     */
    public function index(): void
    {
        $this->withErrorHandling(function () {
            $accountId = $this->request->getInt('account_id', 0) ?: null;
            $unreadOnly = ($this->request->get('unread', '') ?? '') === '1';

            $alerts = $this->alertService->getAlerts($accountId, $unreadOnly);
            $this->json($alerts);
        }, 'AlertController::index');
    }

    /**
     * Marca alerta como lido
     */
    public function markRead(string $alertId): void
    {
        $this->withErrorHandling(function () use ($alertId) {
            $this->alertService->markRead((int) $alertId);
            $this->jsonSuccess([], 'Alerta marcado como lido');
        }, 'AlertController::markRead');
    }

    /**
     * Marca todos como lidos
     */
    public function markAllRead(): void
    {
        $this->withErrorHandling(function () {
            $accountId = $this->request->getInt('account_id', 0) ?: null;
            $updated = $this->alertService->markAllRead($accountId);
            $this->jsonSuccess(['updated' => $updated]);
        }, 'AlertController::markAllRead');
    }

    /**
     * Conta alertas não lidos
     */
    public function count(): void
    {
        $this->withErrorHandling(function () {
            $accountId = $this->request->getInt('account_id', 0) ?: null;
            $count = $this->alertService->countUnread($accountId);
            $this->json(['count' => $count]);
        }, 'AlertController::count');
    }

    /**
     * Detecta novos produtos em uma categoria
     */
    public function detectNewProducts(): void
    {
        $this->withErrorHandling(function () {
            $categoryId = $this->request->get('category');
            $brand = $this->request->get('brand');
            $accountId = $this->request->getInt('account_id', 0) ?: null;

            if (!$categoryId) {
                throw new \InvalidArgumentException('Parâmetro "category" é obrigatório');
            }

            $result = $this->alertService->detectNewProductsInCategory($categoryId, $brand, $accountId);
            $this->json($result);
        }, 'AlertController::detectNewProducts');
    }
}

