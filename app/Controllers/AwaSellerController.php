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

    // =========================================================================
    // FASE 2 — UI OPERACIONAL
    // =========================================================================

    /**
     * Renderiza a tela Sellers AWA.
     */
    public function index(): void
    {
        $this->renderView('dashboard/awa-sellers/index', [
            'pageTitle' => 'AWA Sellers',
            'pageSubtitle' => 'Monitore lojas que anunciam AWA, consulte evidências e acompanhe a identificação da base local.',
        ]);
    }

    /**
     * GET api/brand/awa/sellers
     * Lista paginada de sellers com filtros.
     */
    public function listSellers(): void
    {
        $this->withErrorHandling(function (): void {
            $accountId = $this->requireActiveMlAccountId();
            $registry  = new AwaSellerRegistryService($accountId);

            $filters = $this->buildRegistryFilters();

            $page    = max(1, (int) ($this->request->get('page') ?? 1));
            $perPage = max(1, min(200, (int) ($this->request->get('per_page') ?? 50)));

            $sellers = $registry->listSellers($filters, $page, $perPage);
            $total   = $registry->countSellers($filters);

            $this->jsonSuccess([
                'data'       => $sellers,
                'filters'    => $filters,
                'pagination' => [
                    'page'       => $page,
                    'per_page'   => $perPage,
                    'total'      => $total,
                    'last_page'  => (int) ceil($total / $perPage),
                ],
            ]);
        }, 'AwaSellerController::listSellers');
    }

    /**
     * GET api/brand/awa/sellers/{id}
     * Detalhes de um seller pelo ID interno.
     */
    public function getSellerDetail(string $id): void
    {
        $this->withErrorHandling(function () use ($id): void {
            $accountId  = $this->requireActiveMlAccountId();
            $sellerId   = (int) $id;

            if ($sellerId <= 0) {
                $this->jsonError('ID de seller inválido.', 422);
                return;
            }

            $registry = new AwaSellerRegistryService($accountId);
            $seller   = $registry->getSellerById($sellerId);

            if ($seller === null) {
                $this->jsonError('Seller não encontrado.', 404);
                return;
            }

            $this->jsonSuccess(['data' => $seller]);
        }, 'AwaSellerController::getSellerDetail');
    }

    /**
     * GET api/brand/awa/sellers/{id}/items
     * Itens de um seller específico com paginação.
     */
    public function getSellerItems(string $id): void
    {
        $this->withErrorHandling(function () use ($id): void {
            $accountId  = $this->requireActiveMlAccountId();
            $sellerId   = (int) $id;

            if ($sellerId <= 0) {
                $this->jsonError('ID de seller inválido.', 422);
                return;
            }

            $registry = new AwaSellerRegistryService($accountId);

            if ($registry->getSellerById($sellerId) === null) {
                $this->jsonError('Seller não encontrado.', 404);
                return;
            }

            $page    = max(1, (int) ($this->request->get('page') ?? 1));
            $perPage = max(1, min(200, (int) ($this->request->get('per_page') ?? 50)));

            $items = $registry->listSellerItems($sellerId, $page, $perPage);
            $total = $registry->countSellerItems($sellerId);

            $this->jsonSuccess([
                'data'       => $items,
                'pagination' => [
                    'page'      => $page,
                    'per_page'  => $perPage,
                    'total'     => $total,
                    'last_page' => (int) ceil($total / $perPage),
                ],
            ]);
        }, 'AwaSellerController::getSellerItems');
    }

    /**
     * GET api/brand/awa/sellers/filters/options
     * Valores distintos para filtros da UI.
     */
    public function getFiltersOptions(): void
    {
        $this->withErrorHandling(function (): void {
            $accountId = $this->requireActiveMlAccountId();
            $registry  = new AwaSellerRegistryService($accountId);

            $this->jsonSuccess(['data' => $registry->getFilterOptions()]);
        }, 'AwaSellerController::getFiltersOptions');
    }

    /**
     * GET api/brand/awa/sellers/{id}/identification
     * Retorna a identificação jurídica de um seller.
     */
    public function getIdentification(string $id): void
    {
        $this->withErrorHandling(function () use ($id): void {
            $accountId = $this->requireActiveMlAccountId();
            $sellerId  = (int) $id;

            if ($sellerId <= 0) {
                $this->jsonError('ID de seller inválido.', 422);
                return;
            }

            $service = new AwaSellerIdentificationService($accountId);
            $record  = $service->getByRegistryId($sellerId);

            $this->jsonSuccess(['data' => $record]);
        }, 'AwaSellerController::getIdentification');
    }

    /**
     * PUT api/brand/awa/sellers/{id}/identification
     * Salva / atualiza a identificação jurídica do seller.
     */
    public function saveIdentification(string $id): void
    {
        $this->withErrorHandling(function () use ($id): void {
            $accountId = $this->requireActiveMlAccountId();
            $sellerId  = (int) $id;

            if ($sellerId <= 0) {
                $this->jsonError('ID de seller inválido.', 422);
                return;
            }

            $body = $this->request->json();
            if (!is_array($body)) {
                $this->jsonError('Payload JSON inválido.', 422);
                return;
            }

            $payload = $this->sanitizeIdentificationPayload($body);

            $userId = $this->getUserId();
            if ($userId !== null) {
                $payload['created_by'] = (string) $userId;
            }

            $service = new AwaSellerIdentificationService($accountId);
            $service->upsert($sellerId, $payload);

            $record = $service->getByRegistryId($sellerId);

            $this->jsonSuccess(['data' => $record], 'Identificação salva com sucesso.');
        }, 'AwaSellerController::saveIdentification');
    }

    /**
     * POST api/brand/awa/sellers/{id}/identification/verify
     * Marca identificação do seller como verificada.
     */
    public function verifyIdentification(string $id): void
    {
        $this->withErrorHandling(function () use ($id): void {
            $accountId = $this->requireActiveMlAccountId();
            $sellerId  = (int) $id;

            if ($sellerId <= 0) {
                $this->jsonError('ID de seller inválido.', 422);
                return;
            }

            $body       = $this->request->json();
            $verifiedBy = is_array($body) ? ($body['verified_by'] ?? null) : null;

            $service = new AwaSellerIdentificationService($accountId);
            $service->verify($sellerId, is_string($verifiedBy) ? $verifiedBy : null);

            $this->jsonSuccess([], 'Identificação verificada com sucesso.');
        }, 'AwaSellerController::verifyIdentification');
    }

    /**
     * GET api/brand/awa/sellers/identification/summary
     * Contagem de sellers por status de identificação + sem identificação.
     */
    public function identificationSummary(): void
    {
        $this->withErrorHandling(function (): void {
            $accountId = $this->requireActiveMlAccountId();
            $service   = new AwaSellerIdentificationService($accountId);

            $this->jsonSuccess([
                'data' => [
                    'by_status'    => $service->countByStatus(),
                    'unidentified' => $service->countUnidentified(),
                ],
            ]);
        }, 'AwaSellerController::identificationSummary');
    }

    /**
     * GET api/brand/awa/sellers/export/csv
     * Exporta sellers ativos como CSV.
     */
    public function exportCsv(): void
    {
        $accountId = $this->requireActiveMlAccountId();
        $registry  = new AwaSellerRegistryService($accountId);
        $filters   = $this->buildRegistryFilters();

        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="awa-sellers-' . date('Ymd') . '.csv"');

        $out = fopen('php://output', 'w');
        if ($out === false) {
            $this->jsonError('Erro ao gerar CSV.', 500);
            return;
        }

        fputcsv($out, [
            'seller_id', 'nickname', 'permalink', 'city', 'state',
            'reputation_level', 'power_seller_status', 'items_count',
            'first_seen_at', 'last_seen_at',
            'cnpj', 'razao_social', 'id_status',
        ]);

        foreach ($registry->iterateSellersForExport($filters) as $row) {
            fputcsv($out, [
                $row['seller_id'],
                $row['nickname'],
                $row['permalink'],
                $row['city'],
                $row['state'],
                $row['reputation_level'],
                $row['power_seller_status'],
                $row['items_count'],
                $row['first_seen_at'],
                $row['last_seen_at'],
                $row['cnpj'],
                $row['razao_social'],
                $row['id_status'],
            ]);
        }

        fclose($out);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildRegistryFilters(): array
    {
        $filters = [
            'search' => trim((string) ($this->request->get('search') ?? '')),
            'state' => trim((string) ($this->request->get('state') ?? '')),
            'city' => trim((string) ($this->request->get('city') ?? '')),
            'category_id' => trim((string) ($this->request->get('category_id') ?? '')),
            'reputation_level' => trim((string) ($this->request->get('reputation_level') ?? '')),
            'id_status' => trim((string) ($this->request->get('id_status') ?? '')),
        ];

        $minItems = (int) ($this->request->get('min_items') ?? 0);
        if ($minItems > 0) {
            $filters['min_items'] = $minItems;
        }

        $isActive = $this->request->get('is_active');
        if ($isActive !== null && $isActive !== '') {
            $filters['is_active'] = (int) $isActive === 1;
        }

        return array_filter(
            $filters,
            static fn ($value): bool => $value !== '' && $value !== null
        );
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    private function sanitizeIdentificationPayload(array $payload): array
    {
        $allowedSourceTypes = [
            'manual',
            'authorized_ml_account',
            'internal_registry',
            'external_registry',
            'website_review',
            'legal_team_validation',
        ];
        $allowedStatuses = ['verified', 'pending', 'not_available', 'conflict'];

        $sourceType = trim((string) ($payload['source_type'] ?? 'manual'));
        if (!in_array($sourceType, $allowedSourceTypes, true)) {
            $this->jsonError('source_type inválido.', 422);
        }

        $verificationStatus = trim((string) ($payload['verification_status'] ?? 'pending'));
        if (!in_array($verificationStatus, $allowedStatuses, true)) {
            $this->jsonError('verification_status inválido.', 422);
        }

        $confidenceScore = max(0, min(100, (int) ($payload['confidence_score'] ?? 50)));

        return [
            'cnpj' => $this->nullableTrim($payload['cnpj'] ?? null),
            'razao_social' => $this->nullableTrim($payload['razao_social'] ?? null),
            'source_type' => $sourceType,
            'source_reference' => $this->nullableTrim($payload['source_reference'] ?? null),
            'confidence_score' => $confidenceScore,
            'verification_status' => $verificationStatus,
            'notes' => $this->nullableTrim($payload['notes'] ?? null),
        ];
    }

    private function nullableTrim(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $normalized = trim((string) $value);

        return $normalized === '' ? null : $normalized;
    }

    private function requireActiveMlAccountId(): int
    {
        $accountId = $this->getActiveAccountId();
        if ($accountId === null || $accountId <= 0) {
            $this->jsonError('Conta Mercado Livre ativa não informada.', 401);
        }

        return $accountId;
    }

    /**
     * GET api/brand/awa/sellers/history
     * Retorna vendedores novos detectados nos últimos N dias e histórico de scans.
     */
    public function getHistory(): void
    {
        $this->withErrorHandling(function (): void {
            $accountId = $this->requireActiveMlAccountId();
            $days      = max(1, min(90, (int) ($this->request->get('days') ?? 7)));
            $registry  = new AwaSellerRegistryService($accountId);

            $this->jsonSuccess([
                'new_sellers' => $registry->getNewSellersHistory($days),
                'scan_runs'   => $registry->listScanRuns(20),
                'days'        => $days,
            ]);
        }, 'AwaSellerController::getHistory');
    }
}
