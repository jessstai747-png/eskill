<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ClaimsService;
use App\Services\MercadoLivreClient;

/**
 * Controller de Devoluções (RMA)
 *
 * Gerencia o painel de devoluções e logística reversa.
 */
class ReturnsController extends BaseController
{
    private ClaimsService $claimsService;

    public function __construct()
    {
        parent::__construct();
        $accountId = $_SESSION['active_ml_account_id'] ?? null;
        $this->claimsService = new ClaimsService($accountId);
    }

    /**
     * GET /dashboard/returns
     * Renderiza o painel de devoluções.
     */
    public function index(): void
    {
        if (!$this->userService->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        $accountId = $_SESSION['active_ml_account_id'] ?? null;
        $client = new MercadoLivreClient($accountId);

        // Fetch opened claims (pending triage)
        $pendingRaw = $this->fetchClaims($client, 'opened');
        $pending    = $pendingRaw['data'] ?? ($pendingRaw['results'] ?? []);

        // Fetch closed/resolved claims (history)
        $historyRaw = $this->fetchClaims($client, 'closed');
        $history    = $historyRaw['data'] ?? ($historyRaw['results'] ?? []);

        $pageTitle = 'Devoluções & RMA';
        ob_start();
        require __DIR__ . '/../Views/dashboard/returns/index.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layouts/modern/app.php';
    }

    /**
     * Busca reclamações da API do Mercado Livre por status.
     *
     * @param MercadoLivreClient $client
     * @param string             $status  'opened' ou 'closed'
     * @return array
     */
    private function fetchClaims(MercadoLivreClient $client, string $status): array
    {
        try {
            $response = $client->get('/post-purchase/v1/claims', [
                'limit'  => 50,
                'offset' => 0,
                'status' => $status,
            ]);

            if (isset($response['error'])) {
                return [];
            }

            return $response;
        } catch (\Throwable $e) {
            logger()->warning('ReturnsController: failed to fetch claims', [
                'status' => $status,
                'error'  => $e->getMessage(),
            ]);
            return [];
        }
    }
}
