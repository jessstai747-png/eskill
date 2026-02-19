<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\MercadoLivre\StockSyncService;

/**
 * StockSyncController — API para sincronização de estoque entre contas ML
 *
 * Endpoints:
 * - GET  api/stock-sync/rules          Lista regras
 * - POST api/stock-sync/rules          Cria regra
 * - PUT  api/stock-sync/rules/{id}     Atualiza regra
 * - DELETE api/stock-sync/rules/{id}   Deleta regra
 * - POST api/stock-sync/full           Executa sync completo
 * - POST api/stock-sync/process        Processa fila
 * - POST api/stock-sync/webhook        Receber webhook do ML
 * - GET  api/stock-sync/history        Histórico
 * - GET  api/stock-sync/stats          Estatísticas
 * - GET  api/stock-sync/settings       Configurações
 * - POST api/stock-sync/settings       Atualiza configurações
 * - POST api/stock-sync/manual         Sync manual de um item
 */
class StockSyncController extends BaseController
{
    private StockSyncService $service;

    public function __construct()
    {
        parent::__construct();
        $this->service = new StockSyncService();
    }

    /**
     * Lista regras de sincronização do usuário
     */
    public function listRules(): void
    {
        $this->withErrorHandling(function (): void {
            $userId = $this->requireUserId();
            $activeOnly = (bool) ($_GET['active_only'] ?? false);
            $rules = $this->service->getRulesByUser($userId, $activeOnly);

            $this->jsonSuccess(['rules' => $rules, 'total' => count($rules)]);
        }, 'StockSyncController::listRules');
    }

    /**
     * Cria uma nova regra de sincronização
     */
    public function createRule(): void
    {
        $this->withErrorHandling(function (): void {
            $userId = $this->requireUserId();
            $input = $this->getJsonInput();

            $required = ['source_account_id', 'target_account_id', 'source_item_id', 'target_item_id'];
            foreach ($required as $field) {
                if (empty($input[$field])) {
                    $this->jsonError("Campo obrigatório: {$field}", 422);
                }
            }

            $input['user_id'] = $userId;
            $result = $this->service->createRule($input);

            if ($result['error']) {
                $this->jsonError($result['error'], 422);
            }

            $this->json(['success' => true, 'rule' => $result['data']], 201);
        }, 'StockSyncController::createRule');
    }

    /**
     * Atualiza uma regra existente
     */
    public function updateRule(int $ruleId): void
    {
        $this->withErrorHandling(function () use ($ruleId): void {
            $userId = $this->requireUserId();
            $input = $this->getJsonInput();

            // Verificar que a regra pertence ao usuário
            $rule = $this->service->getRuleById($ruleId);
            if (!$rule || (int) $rule['user_id'] !== $userId) {
                $this->jsonError('Regra não encontrada', 404);
            }

            $result = $this->service->updateRule($ruleId, $input);

            if ($result['error']) {
                $this->jsonError($result['error'], 422);
            }

            $this->jsonSuccess(['rule' => $result['data']], 'Regra atualizada com sucesso');
        }, 'StockSyncController::updateRule');
    }

    /**
     * Deleta uma regra
     */
    public function deleteRule(int $ruleId): void
    {
        $this->withErrorHandling(function () use ($ruleId): void {
            $userId = $this->requireUserId();

            $rule = $this->service->getRuleById($ruleId);
            if (!$rule || (int) $rule['user_id'] !== $userId) {
                $this->jsonError('Regra não encontrada', 404);
            }

            $this->service->deleteRule($ruleId);
            $this->jsonSuccess([], 'Regra excluída com sucesso');
        }, 'StockSyncController::deleteRule');
    }

    /**
     * Executa sincronização completa de todas as regras ativas
     */
    public function fullSync(): void
    {
        $this->withErrorHandling(function (): void {
            $userId = $this->requireUserId();
            $stats = $this->service->fullSync($userId);

            $this->jsonSuccess([
                'stats' => $stats,
                'message' => "Sync completo: {$stats['queued']} enfileirados, {$stats['skipped']} sem alteração, {$stats['errors']} erros",
            ]);
        }, 'StockSyncController::fullSync');
    }

    /**
     * Processa a fila de sincronização.
     * Filtra por user_id para isolamento multi-tenant.
     */
    public function processQueue(): void
    {
        $this->withErrorHandling(function (): void {
            $userId = $this->requireUserId();
            $limit = min(100, max(1, (int) ($_GET['limit'] ?? 50)));
            $stats = $this->service->processQueue($limit, $userId);

            $this->jsonSuccess(['stats' => $stats]);
        }, 'StockSyncController::processQueue');
    }

    /**
     * Webhook de recebimento do Mercado Livre.
     * Valida assinatura HMAC quando ML_WEBHOOK_SECRET está configurado.
     */
    public function webhook(): void
    {
        $this->withErrorHandling(function (): void {
            // Ler body raw ANTES de decodificar (necessário para verificação de assinatura)
            $rawBody = file_get_contents('php://input');

            // Validar assinatura HMAC se secret configurado
            $webhookSecret = (string) ($_ENV['ML_WEBHOOK_SECRET'] ?? getenv('ML_WEBHOOK_SECRET') ?? '');
            if ($webhookSecret !== '') {
                $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE']
                    ?? $_SERVER['HTTP_X_SIGNATURE']
                    ?? '';

                if ($signature === '') {
                    log_warning('Stock sync webhook sem assinatura', [
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    ]);
                    $this->jsonError('Assinatura ausente', 403);
                    return;
                }

                $expectedSignature = hash_hmac('sha256', $rawBody ?: '', $webhookSecret);
                if (!hash_equals($expectedSignature, $signature)) {
                    log_warning('Stock sync webhook assinatura inválida', [
                        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
                    ]);
                    $this->jsonError('Assinatura inválida', 403);
                    return;
                }
            }

            $input = is_string($rawBody) && $rawBody !== ''
                ? (json_decode($rawBody, true) ?: [])
                : [];

            if (empty($input)) {
                $this->jsonError('Payload vazio', 400);
                return;
            }

            log_info('Stock sync webhook received', [
                'topic' => $input['topic'] ?? 'unknown',
                'resource' => $input['resource'] ?? 'unknown',
            ]);

            $result = $this->service->processWebhook($input);
            $this->json(['success' => true, 'result' => $result]);
        }, 'StockSyncController::webhook');
    }

    /**
     * Sync manual de um item específico
     */
    public function manualSync(): void
    {
        $this->withErrorHandling(function (): void {
            $userId = $this->requireUserId();
            $input = $this->getJsonInput();

            if (empty($input['source_item_id'])) {
                $this->jsonError('source_item_id é obrigatório', 422);
            }

            $sourceItemId = (string) $input['source_item_id'];

            // Buscar estoque do source
            $ruleStmt = $this->service->getRulesByUser($userId, true);
            $matchingRules = array_filter($ruleStmt, fn(array $r) => $r['source_item_id'] === $sourceItemId);

            if (empty($matchingRules)) {
                $this->jsonError("Nenhuma regra ativa para o item {$sourceItemId}", 404);
            }

            $firstRule = reset($matchingRules);
            $currentStock = $this->service->fetchItemStock(
                (int) $firstRule['source_account_id'],
                $sourceItemId
            );

            if ($currentStock === null) {
                $this->jsonError('Não foi possível obter estoque atual do item', 502);
            }

            $result = $this->service->handleStockChange($sourceItemId, $currentStock, 'manual');

            $this->jsonSuccess([
                'source_item_id' => $sourceItemId,
                'current_stock' => $currentStock,
                'queued' => $result['queued'],
                'rules_matched' => $result['rules_matched'],
            ], 'Sync manual enfileirado com sucesso');
        }, 'StockSyncController::manualSync');
    }

    /**
     * Histórico de sincronizações
     */
    public function history(): void
    {
        $this->withErrorHandling(function (): void {
            $userId = $this->requireUserId();

            $filters = [
                'rule_id' => !empty($_GET['rule_id']) ? (int) $_GET['rule_id'] : null,
                'item_id' => $_GET['item_id'] ?? null,
                'status' => $_GET['status'] ?? null,
                'trigger_type' => $_GET['trigger_type'] ?? null,
                'date_from' => $_GET['date_from'] ?? null,
                'date_to' => $_GET['date_to'] ?? null,
                'limit' => (int) ($_GET['limit'] ?? 50),
                'offset' => (int) ($_GET['offset'] ?? 0),
            ];

            $result = $this->service->getHistory($userId, array_filter($filters));

            $this->jsonSuccess([
                'history' => $result['items'],
                'total' => $result['total'],
                'limit' => $filters['limit'],
                'offset' => $filters['offset'],
            ]);
        }, 'StockSyncController::history');
    }

    /**
     * Estatísticas de sincronização
     */
    public function stats(): void
    {
        $this->withErrorHandling(function (): void {
            $userId = $this->requireUserId();
            $stats = $this->service->getStats($userId);

            $this->jsonSuccess(['stats' => $stats]);
        }, 'StockSyncController::stats');
    }

    /**
     * Obtém configurações de sync
     */
    public function getSettings(): void
    {
        $this->withErrorHandling(function (): void {
            $userId = $this->requireUserId();
            $settings = $this->service->getSettings($userId);

            $this->jsonSuccess(['settings' => $settings]);
        }, 'StockSyncController::getSettings');
    }

    /**
     * Atualiza configurações de sync
     */
    public function updateSettings(): void
    {
        $this->withErrorHandling(function (): void {
            $userId = $this->requireUserId();
            $input = $this->getJsonInput();
            $settings = $this->service->updateSettings($userId, $input);

            $this->jsonSuccess(['settings' => $settings], 'Configurações atualizadas');
        }, 'StockSyncController::updateSettings');
    }

    /**
     * Lê JSON do corpo da requisição
     *
     * @return array<string, mixed>
     */
    private function getJsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if ($raw === false || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }
}
