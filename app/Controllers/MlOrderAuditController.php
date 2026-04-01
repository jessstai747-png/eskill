<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\MercadoLivre\MlOrderAuditService;

/**
 * Exposes the ML order audit trail (payments, claims, feedback, reconciliation)
 * via REST API.
 *
 * Routes:
 *   GET /api/ml/orders/{orderId}/trail
 */
class MlOrderAuditController extends BaseController
{
    /**
     * GET /api/ml/orders/{orderId}/trail
     *
     * Returns the full audit trail for the specified ML order, including
     * linked payments, claims, feedback, and a reconciliation summary.
     *
     * Path params:
     *   orderId (string) — the Mercado Livre order ID.
     *
     * Query params:
     *   account_id (int, optional) — scope results to a specific ML account.
     */
    public function trail(string $orderId): void
    {
        $this->requireUserId();

        $accountId = null;
        $rawAccountId = $this->request->get('account_id');
        if ($rawAccountId !== null && ctype_digit($rawAccountId) && (int)$rawAccountId > 0) {
            $accountId = (int)$rawAccountId;
        }

        $this->withErrorHandling(function () use ($orderId, $accountId): void {
            $db      = Database::getInstance();
            $service = new MlOrderAuditService($db);
            $trail   = $service->getOrderTrail($orderId, $accountId);

            if ($trail === null) {
                $this->jsonError('Order not found', 404);
                return;
            }

            $this->jsonSuccess($trail);
        }, 'MlOrderAuditController::trail');
    }
}
