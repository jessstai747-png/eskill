<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\AwaSellerDiscoveryService;
use App\Services\AwaSellerRegistryService;

class AwaSellerController extends BaseController
{
    public function scan(): void
    {
        $this->withErrorHandling(function (): void {
            $accountId = $this->requireActiveMlAccountId();
            $discovery = new AwaSellerDiscoveryService($accountId);

            $result = $discovery->runScan($this->buildScanOptions());

            $this->jsonSuccess([
                'data' => $result,
            ], 'Varredura persistente de sellers AWA concluída com sucesso.');
        }, 'AwaSellerController::scan');
    }

    public function getScan(string $scanId): void
    {
        $this->withErrorHandling(function () use ($scanId): void {
            $accountId = $this->requireActiveMlAccountId();
            $scanRunId = (int) $scanId;

            if ($scanRunId <= 0) {
                $this->jsonError('ID do scan inválido.', 422);
                return;
            }

            $registry = new AwaSellerRegistryService($accountId);
            $scan = $registry->getScanRun($scanRunId);
            if ($scan === null) {
                $this->jsonError('Scan AWA não encontrado para a conta ativa.', 404);
                return;
            }

            $this->jsonSuccess([
                'data' => $scan,
            ]);
        }, 'AwaSellerController::getScan');
    }

    public function getMetrics(): void
    {
        $this->withErrorHandling(function (): void {
            $accountId = $this->requireActiveMlAccountId();
            $registry = new AwaSellerRegistryService($accountId);

            $this->jsonSuccess([
                'data' => $registry->getMetrics(),
            ]);
        }, 'AwaSellerController::getMetrics');
    }

    /**
     * @return array<string, mixed>
     */
    private function buildScanOptions(): array
    {
        $body = $this->request->json();
        $queryCategories = $this->request->get('categories');
        $bodyCategories = is_array($body) ? ($body['categories'] ?? null) : null;

        $options = [
            'max_results' => max(1, min(5000, $this->request->inputInt('max_results', 500))),
        ];

        $categories = $bodyCategories ?? $queryCategories;
        if (is_array($categories) || is_string($categories)) {
            $options['categories'] = $categories;
        }

        return $options;
    }

    private function requireActiveMlAccountId(): int
    {
        $accountId = $this->getActiveAccountId();
        if ($accountId === null || $accountId <= 0) {
            $this->jsonError('Conta Mercado Livre ativa não informada.', 401);
        }

        return $accountId;
    }
}
