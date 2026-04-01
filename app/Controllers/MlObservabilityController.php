<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\MercadoLivre\MlObservabilityService;

/**
 * Exposes Mercado Livre operational observability metrics via REST API.
 *
 * Routes:
 *   GET /api/ml/observability/summary
 */
class MlObservabilityController extends BaseController
{
    /**
     * GET /api/ml/observability/summary
     *
     * Returns a structured observability summary for the authenticated user's
     * Mercado Livre accounts.  Optionally scoped to a single account via the
     * `account_id` query parameter.
     *
     * Query params:
     *   account_id (int, optional) — scope the summary to a specific account.
     */
    public function summary(): void
    {
        $userId = $this->requireUserId();

        $accountId = null;
        $rawAccountId = $this->request->get('account_id');
        if ($rawAccountId !== null && ctype_digit($rawAccountId) && (int)$rawAccountId > 0) {
            $accountId = (int)$rawAccountId;
        }

        $this->withErrorHandling(function () use ($userId, $accountId): void {
            $db      = Database::getInstance();
            $service = new MlObservabilityService($db);
            $summary = $service->getSummary($userId, $accountId);
            $this->jsonSuccess($summary);
        }, 'MlObservabilityController::summary');
    }
}
