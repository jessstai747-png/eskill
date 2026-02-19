<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\PollingService;

class PollingController extends BaseController
{
    private PollingService $pollingService;

    public function __construct()
    {
        parent::__construct();
        $this->pollingService = new PollingService();
    }

    /**
     * Executa polling de pedidos
     * POST /api/polling/orders
     */
    public function pollOrders(): void
    {
        $this->requireUserId();
        $result = $this->pollingService->pollOrders();

        header('Content-Type: application/json');
        echo json_encode($result);
    }

    /**
     * Executa polling de anúncios
     * POST /api/polling/items
     */
    public function pollItems(): void
    {
        $this->requireUserId();
        $result = $this->pollingService->pollItems();

        header('Content-Type: application/json');
        echo json_encode($result);
    }

    /**
     * Executa polling completo
     * POST /api/polling/all
     */
    public function pollAll(): void
    {
        $this->requireUserId();
        $result = $this->pollingService->pollAll();

        header('Content-Type: application/json');
        echo json_encode($result);
    }

    /**
     * Obtém status do polling
     * GET /api/polling/status
     */
    public function status(): void
    {
        $this->requireUserId();
        $status = [
            'enabled' => $this->pollingService->isPollingEnabled(),
            'interval_minutes' => $this->pollingService->getPollingInterval(),
        ];

        header('Content-Type: application/json');
        echo json_encode($status);
    }
}
