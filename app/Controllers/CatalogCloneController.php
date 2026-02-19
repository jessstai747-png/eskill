<?php

namespace App\Controllers;

use App\Services\CatalogCloneService;
use App\Services\CloneMetricsService;
use App\Services\ClonePostActionsService;
use App\Services\CloneTemplateService;
use App\Services\JobService;
use App\Services\UserService;

class CatalogCloneController extends BaseController
{
    private CatalogCloneService $service;
    private JobService $jobService;
    private UserService $userService;
    private ?CloneTemplateService $templateService = null;
    private ?CloneMetricsService $metricsService = null;
    private ?ClonePostActionsService $postActionsService = null;

    public function __construct()
    {
        parent::__construct();
        $this->service = new CatalogCloneService();
        $this->jobService = new JobService();
        $this->userService = new UserService();
    }

    /**
     * Lazy load template service
     */
    private function getTemplateService(): CloneTemplateService
    {
        if ($this->templateService === null) {
            $this->templateService = new CloneTemplateService();
        }
        return $this->templateService;
    }

    /**
     * Lazy load metrics service
     */
    private function getMetricsService(): CloneMetricsService
    {
        if ($this->metricsService === null) {
            $this->metricsService = new CloneMetricsService();
        }
        return $this->metricsService;
    }

    /**
     * Lazy load post actions service
     */
    private function getPostActionsService(): ClonePostActionsService
    {
        if ($this->postActionsService === null) {
            $this->postActionsService = new ClonePostActionsService();
        }
        return $this->postActionsService;
    }

    /**
     * Display catalog clone dashboard
     */
    public function index(): void
    {
        $pageTitle = 'Clonador de Catálogo';
        $activePage = 'catalog-clone';

        ob_start();
        require __DIR__ . '/../Views/dashboard/catalog_clone.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layouts/modern/app.php';
    }

    public function cloneItem()
    {
        header('Content-Type: application/json');

        // Ler input JSON
        $input = $this->request->json();

        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON input']);
            return;
        }

        // Validar campos obrigatórios
        // Suporta target_account_id (string/int) ou target_account_ids (array)
        if (empty($input['source_account_id']) || empty($input['source_item_id'])) {
             http_response_code(400);
             echo json_encode(['error' => "Fields 'source_account_id' and 'source_item_id' are required"]);
             return;
        }

        if (empty($input['target_account_id']) && empty($input['target_account_ids'])) {
             http_response_code(400);
             echo json_encode(['error' => "Field 'target_account_id' or 'target_account_ids' is required"]);
             return;
        }

        try {
            $targets = [];
            
            if (!empty($input['target_account_ids']) && is_array($input['target_account_ids'])) {
                $targets = $input['target_account_ids'];
            } else {
                $targets = [$input['target_account_id']];
            }

            $results = [];
            $hasSuccess = false;

            foreach ($targets as $targetId) {
                // Prepare input for single clone
                $singleInput = $input;
                $singleInput['target_account_id'] = $targetId;
                
                $result = $this->service->cloneCatalogItem($singleInput);
                $results[$targetId] = $result;
                
                if ($result['status'] === 'success') {
                    $hasSuccess = true;
                }
            }

            if (count($targets) === 1) {
                // Mantém comportamento original para single target
                $result = reset($results);
                if ($result['status'] === 'success') {
                    http_response_code(201);
                } elseif ($result['status'] === 'skipped_duplicate') {
                    http_response_code(409);
                } else {
                    http_response_code(400);
                }
                echo json_encode($result);
            } else {
                // Resposta multi-target
                http_response_code($hasSuccess ? 201 : 400);
                echo json_encode([
                    'status' => $hasSuccess ? 'success' : 'error',
                    'message' => $hasSuccess ? 'Processamento multi-contas finalizado' : 'Falha ao clonar para contas',
                    'results' => $results
                ]);
            }

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
        }
    }

    public function cloneBatch()
    {
        header('Content-Type: application/json');

        $input = $this->request->json();

        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON input']);
            return;
        }

        $validator = \App\Core\Validator::make($input, [
            'source_account_id' => 'required',
            'target_account_id' => 'required',
            'items' => 'required|array'
        ]);

        if ($validator->fails()) {
            http_response_code(422);
            echo json_encode(['error' => 'Validation Error', 'details' => $validator->errors()]);
            return;
        }


        // Processamento
        $jobsCreated = 0;
        $jobIds = [];
        
        // Determine target accounts (single or array)
        $targetAccountIds = [];
        if (!empty($input['target_account_ids']) && is_array($input['target_account_ids'])) {
            $targetAccountIds = $input['target_account_ids'];
        } elseif (!empty($input['target_account_id'])) {
            $targetAccountIds = [$input['target_account_id']];
        } else {
            http_response_code(400); // Bad Request if no target
            echo json_encode(['error' => 'No target account specified']);
            return;
        }

        foreach ($input['items'] as $itemId) {
            $itemId = trim($itemId);
            if (empty($itemId)) continue;

            // Para cada conta destino, criar um job
            foreach ($targetAccountIds as $targetAccountId) {
                // Ignore se conta destino == conta origem (embora frontend já filtre)
                if ($targetAccountId == $input['source_account_id']) continue;

                $payload = [
                    'source_account_id' => $input['source_account_id'],
                    'source_item_id' => $itemId,
                    'target_account_id' => $targetAccountId,
                    'pricing_strategy' => $input['pricing_strategy'] ?? [],
                    'stock_strategy' => $input['stock_strategy'] ?? []
                ];
    
                $jobId = $this->jobService->dispatch('catalog_clone_item', $payload);
                $jobIds[] = $jobId;
                $jobsCreated++;
            }
        }

        http_response_code(202); // Accepted
        echo json_encode([
            'status' => 'accepted',
            'message' => "$jobsCreated jobs de clonagem criados com sucesso (Multi-conta).",
            'jobs_count' => $jobsCreated,
            'job_ids' => $jobIds
        ]);
    }

    public function getMetrics()
    {
        header('Content-Type: application/json');

        try {
            $metrics = $this->service->getCloneMetrics();
            
            http_response_code(200);
            echo json_encode($metrics);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
        }
    }

    public function searchWithFilters()
    {
        header('Content-Type: application/json');

        try {
            $sourceAccountId = $this->request->get('source_account_id');
            if (!$sourceAccountId) {
                http_response_code(400);
                echo json_encode(['error' => 'source_account_id is required']);
                return;
            }

            $filters = [
                'category_id' => $this->request->get('category_id'),
                'min_price' => $this->request->get('min_price'),
                'max_price' => $this->request->get('max_price'),
                'keyword' => $this->request->get('keyword'),
                'status' => $this->request->get('status')
            ];

            $results = $this->service->searchItemsWithFilters($sourceAccountId, $filters);
            
            http_response_code(200);
            echo json_encode($results);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
        }
    }

    public function createSchedule()
    {
        header('Content-Type: application/json');

        $input = $this->request->json();

        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON input']);
            return;
        }

        // Compatibilidade: frontend envia scheduled_at "YYYY-MM-DD HH:MM"
        // Convertemos para scheduled_date/scheduled_time esperados pelo service.
        if (!empty($input['scheduled_at']) && empty($input['scheduled_date']) && empty($input['scheduled_time'])) {
            $parts = explode(' ', trim($input['scheduled_at']));
            if (count($parts) === 2) {
                [$input['scheduled_date'], $input['scheduled_time']] = $parts;
            }
        }

        $validator = \App\Core\Validator::make($input, [
            'source_account_id' => 'required',
            'target_account_id' => 'required',
            'scheduled_date' => 'required',
            'scheduled_time' => 'required'
        ]);

        if ($validator->fails()) {
            http_response_code(422);
            echo json_encode(['error' => 'Validation Error', 'details' => $validator->errors()]);
            return;
        }

        try {
            $result = $this->service->createCloneSchedule($input);
            
            http_response_code(201);
            echo json_encode($result);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
        }
    }

    public function getSchedules()
    {
        header('Content-Type: application/json');

        try {
            $schedules = $this->service->getActiveSchedules();
            
            http_response_code(200);
            echo json_encode(['schedules' => $schedules]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
        }
    }

    public function cancelSchedule($scheduleId)
    {
        header('Content-Type: application/json');

        if (!$scheduleId) {
            http_response_code(400);
            echo json_encode(['error' => 'Schedule ID is required']);
            return;
        }

        try {
            $result = $this->service->cancelSchedule($scheduleId);
            
            if ($result) {
                http_response_code(200);
                echo json_encode(['status' => 'success', 'message' => 'Schedule canceled successfully']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Schedule not found']);
            }

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Internal Server Error', 'message' => $e->getMessage()]);
        }
    }

    public function simulate()
    {
        header('Content-Type: application/json');
        
        $input = $this->request->json();
        
        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            return;
        }

        $result = $this->service->simulateClone($input);
        echo json_encode($result);
    }

    /**
     * Preview de preço em lote (sem criar anúncios)
     * POST /api/catalog/clone/price-preview
     */
    public function pricePreview(): void
    {
        header('Content-Type: application/json');

        $input = $this->request->json();

        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            return;
        }

        $validator = \App\Core\Validator::make($input, [
            'item_ids' => 'required|array',
            'target_account_id' => 'required',
        ]);

        if ($validator->fails()) {
            http_response_code(422);
            echo json_encode(['error' => 'Validation Error', 'details' => $validator->errors()]);
            return;
        }

        try {
            $result = $this->service->pricePreviewBatch($input);
            http_response_code(200);
            echo json_encode($result);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao gerar preview de preços', 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // FASE 1: Endpoints para listagem por Seller ID
    // =========================================================================

    /**
     * Lista anúncios de um seller público
     * GET /api/catalog/clone/source/seller/{sellerId}/items
     */
    public function listSellerItems(string $sellerId): void
    {
        header('Content-Type: application/json');

        // Limpar Seller ID (remover caracteres não numéricos)
        $sellerId = preg_replace('/\D/', '', $sellerId);

        if (empty($sellerId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Seller ID inválido. Informe apenas os números.']);
            return;
        }

        try {
            $filters = [
                'category' => $this->request->get('category'),
                'brand' => $this->request->get('brand'),
                'is_catalog' => $this->request->get('is_catalog'),
                'keyword' => $this->request->get('keyword') ?? $this->request->get('q'),
                'offset' => $this->request->getInt('offset', 0),
                'limit' => $this->request->getInt('limit', 50),
            ];

            $result = $this->service->listSellerItems($sellerId, $filters);
            
            http_response_code(200);
            echo json_encode($result);

        } catch (\Exception $e) {
            $message = $e->getMessage();
            // Se for o erro de API bloqueada, retornar erro amigável com status 422 ou 403
            if (strpos($message, 'minha conta') !== false || strpos($message, 'não permite mais') !== false) {
                 http_response_code(403);
                 echo json_encode(['error' => 'Acesso Negado', 'message' => $message]);
            } else {
                 http_response_code(500);
                 echo json_encode(['error' => 'Erro ao listar itens do seller', 'message' => $message]);
            }
        }
    }

    /**
     * Obtém resumo/summary de um seller (contadores + facets)
     * GET /api/catalog/clone/source/seller/{sellerId}/summary
     */
    public function getSellerSummary(string $sellerId): void
    {
        header('Content-Type: application/json');

        // Limpar Seller ID
        $sellerId = preg_replace('/\D/', '', $sellerId);

        if (empty($sellerId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Seller ID inválido. Informe apenas os números.']);
            return;
        }

        try {
            $result = $this->service->getSellerSummary($sellerId);
            
            http_response_code(200);
            echo json_encode($result);

        } catch (\Exception $e) {
            $message = $e->getMessage();
            // Se for o erro de API bloqueada, retornar erro amigável com status 403
            if (strpos($message, 'minha conta') !== false || strpos($message, 'não permite mais') !== false) {
                http_response_code(403);
                echo json_encode([
                    'error' => 'Acesso Negado',
                    'message' => $message,
                    'code' => 'ML_API_FORBIDDEN',
                ]);
                return;
            }
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao obter summary do seller', 'message' => $message]);
        }
    }

    /**
     * Resolve lista de Item IDs para obter detalhes
     * POST /api/catalog/clone/source/items
     */
    public function resolveItemIds(): void
    {
        header('Content-Type: application/json');

        $input = $this->request->json();

        if (!$input || empty($input['item_ids'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Campo item_ids é obrigatório (array de IDs)']);
            return;
        }

        try {
            $itemIds = is_array($input['item_ids']) ? $input['item_ids'] : explode(',', $input['item_ids']);
            $itemIds = array_map('trim', $itemIds);
            $itemIds = array_filter($itemIds);

            $result = $this->service->resolveItemIds($itemIds);
            
            http_response_code(200);
            echo json_encode($result);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao resolver item IDs', 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // FASE 3: Dry-run avançado
    // =========================================================================

    /**
     * Executa dry-run em lote com validações detalhadas
     * POST /api/catalog/clone/dry-run
     */
    public function dryRun(): void
    {
        header('Content-Type: application/json');

        $input = $this->request->json();

        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            return;
        }

        $validator = \App\Core\Validator::make($input, [
            'item_ids' => 'required|array',
            'target_account_id' => 'required',
        ]);

        if ($validator->fails()) {
            http_response_code(422);
            echo json_encode(['error' => 'Validation Error', 'details' => $validator->errors()]);
            return;
        }

        try {
            $result = $this->service->dryRunBatch($input);
            
            http_response_code(200);
            echo json_encode($result);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro no dry-run', 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // FASE 1+3: Clone de item (catálogo ou não-catálogo)
    // =========================================================================

    /**
     * Clona um item (suporta catálogo e não-catálogo)
     * POST /api/catalog/clone/item
     */
    public function cloneItemNew(): void
    {
        header('Content-Type: application/json');

        $input = $this->request->json();

        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            return;
        }

        if (empty($input['source_item_id']) || empty($input['target_account_id'])) {
            http_response_code(400);
            echo json_encode(['error' => "Campos 'source_item_id' e 'target_account_id' são obrigatórios"]);
            return;
        }

        try {
            $result = $this->service->cloneItem($input);
            
            $statusCode = $result['status'] === 'success' ? 201 : 
                         ($result['status'] === 'skipped_duplicate' ? 409 : 400);
            
            http_response_code($statusCode);
            echo json_encode($result);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao clonar item', 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // FASE 4: Jobs em lote assíncronos
    // =========================================================================

    /**
     * Cria job de clonagem em lote
     * POST /api/catalog/clone/jobs
     */
    public function createJob(): void
    {
        header('Content-Type: application/json');

        $input = $this->request->json();

        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            return;
        }

        $validator = \App\Core\Validator::make($input, [
            'item_ids' => 'required|array',
            'target_account_id' => 'required',
        ]);

        if ($validator->fails()) {
            http_response_code(422);
            echo json_encode(['error' => 'Validation Error', 'details' => $validator->errors()]);
            return;
        }

        try {
            // Adicionar user_id da sessão se disponível
            $userId = $this->getUserId();
            if ($userId) {
                $input['user_id'] = $userId;
            }

            $result = $this->service->createBatchJob($input);
            
            http_response_code(202); // Accepted
            echo json_encode($result);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao criar job de clonagem', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Obtém status de um job de clonagem
     * GET /api/catalog/clone/jobs/{jobId}/status
     */
    public function getJobStatus(string $jobId): void
    {
        header('Content-Type: application/json');

        if (empty($jobId)) {
            http_response_code(400);
            echo json_encode(['error' => 'Job ID é obrigatório']);
            return;
        }

        try {
            $result = $this->service->getJobStatus($jobId);
            
            http_response_code(200);
            echo json_encode($result);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao obter status do job', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Lista jobs de clonagem recentes
     * GET /api/catalog/clone/jobs
     */
    public function listJobs(): void
    {
        header('Content-Type: application/json');

        try {
            $limit = $this->request->getInt('limit', 20);
            $status = $this->request->get('status');

            $limitSql = max(1, min(200, (int)$limit));

            $db = \App\Database::getInstance();
            
            $sql = "SELECT job_id, target_account_id, source_type, source_seller_id, status, 
                           total_items, processed_items, successful_items, failed_items, 
                           created_at, started_at, completed_at
                    FROM catalog_clone_jobs";
            
            $params = [];
            
            if ($status) {
                $sql .= " WHERE status = :status";
                $params['status'] = $status;
            }
            
            $sql .= " ORDER BY created_at DESC LIMIT {$limitSql}";
            
            $stmt = $db->prepare($sql);
            
            foreach ($params as $key => $value) {
                $stmt->bindValue(":$key", $value);
            }
            
            $stmt->execute();
            $jobs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'jobs' => $jobs,
                'total' => count($jobs),
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao listar jobs', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Lista jobs ativos (pending/processing)
     * GET /api/catalog/clone/jobs/active
     */
    public function listActiveJobs(): void
    {
        header('Content-Type: application/json');

        try {
            $db = \App\Database::getInstance();

            $stmt = $db->prepare("
                SELECT job_id, target_account_id, source_type, source_seller_id, status,
                       total_items, processed_items, successful_items, failed_items,
                       created_at, started_at
                FROM catalog_clone_jobs
                WHERE status IN ('pending', 'processing')
                ORDER BY created_at DESC
                LIMIT 50
            ");
            $stmt->execute();
            $jobs = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            echo json_encode([
                'status' => 'success',
                'jobs' => $jobs,
                'total' => count($jobs),
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao listar jobs ativos', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Retorna histórico de clonagens com dados expandidos
     * GET /api/catalog/clone/history
     */
    public function getHistory(): void
    {
        header('Content-Type: application/json');

        try {
            $limit = $this->request->getInt('limit', 50);
            $history = $this->service->getCloneHistory($limit);

            // Formatar para o frontend
            $formatted = array_map(function ($item) {
                return [
                    'id' => $item['id'],
                    'source_item_id' => $item['source_item_id'],
                    'target_item_id' => $item['target_item_id'],
                    'item_title' => $item['source_item_id'], // Placeholder - idealmente buscar título
                    'source_account' => $item['source_account_name'] ?? 'Conta ' . $item['source_account_id'],
                    'target_account' => $item['target_account_name'] ?? 'Conta ' . $item['target_account_id'],
                    'status' => $item['status'] === 'created' ? 'success' : $item['status'],
                    'is_catalog' => (bool)($item['is_catalog'] ?? true),
                    'brand' => $item['brand'] ?? null,
                    'error_message' => $item['error_message'],
                    'created_at' => $item['created_at'],
                ];
            }, $history);

            http_response_code(200);
            echo json_encode(['status' => 'success', 'history' => $formatted]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao carregar histórico', 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // FASE 5: Templates de Clonagem
    // =========================================================================

    /**
     * Lista templates disponíveis
     * GET /api/catalog/clone/templates
     */
    public function listTemplates(): void
    {
        header('Content-Type: application/json');

        try {
            $accountId = $this->request->get('account_id') ? $this->request->getInt('account_id') : null;
            $includeSystem = ($this->request->get('include_system', 'true')) !== 'false';

            $templates = $this->getTemplateService()->listTemplates($accountId, $includeSystem);

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'templates' => $templates,
                'total' => count($templates),
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao listar templates', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Obtém um template específico
     * GET /api/catalog/clone/templates/{idOrSlug}
     */
    public function getTemplate(string $idOrSlug): void
    {
        header('Content-Type: application/json');

        try {
            $template = $this->getTemplateService()->getTemplate($idOrSlug);

            if (!$template) {
                http_response_code(404);
                echo json_encode(['error' => 'Template não encontrado']);
                return;
            }

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'template' => $template,
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao obter template', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Cria um novo template
     * POST /api/catalog/clone/templates
     */
    public function createTemplate(): void
    {
        header('Content-Type: application/json');

        $input = $this->request->json();

        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            return;
        }

        $validator = \App\Core\Validator::make($input, [
            'name' => 'required|min:3|max:100',
            'slug' => 'required|min:2|max:50',
        ]);

        if ($validator->fails()) {
            http_response_code(422);
            echo json_encode(['error' => 'Validation Error', 'details' => $validator->errors()]);
            return;
        }

        try {
            // Adicionar account_id se for template de usuário
            if (empty($input['is_system'])) {
                $accountId = $this->getAccountId();
                if ($accountId) {
                    $input['account_id'] = $accountId;
                }
            }

            $template = $this->getTemplateService()->createTemplate($input);

            http_response_code(201);
            echo json_encode([
                'status' => 'success',
                'message' => 'Template criado com sucesso',
                'template' => $template,
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao criar template', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Atualiza um template
     * PUT /api/catalog/clone/templates/{id}
     */
    public function updateTemplate(int $id): void
    {
        header('Content-Type: application/json');

        $input = $this->request->json();

        if (!$input) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid JSON']);
            return;
        }

        try {
            $success = $this->getTemplateService()->updateTemplate($id, $input);

            if (!$success) {
                http_response_code(404);
                echo json_encode(['error' => 'Template não encontrado ou não atualizado']);
                return;
            }

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Template atualizado com sucesso',
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao atualizar template', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Remove um template
     * DELETE /api/catalog/clone/templates/{id}
     */
    public function deleteTemplate(int $id): void
    {
        header('Content-Type: application/json');

        try {
            $success = $this->getTemplateService()->deleteTemplate($id);

            if (!$success) {
                http_response_code(404);
                echo json_encode(['error' => 'Template não encontrado ou é template de sistema']);
                return;
            }

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'message' => 'Template removido com sucesso',
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao remover template', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Preview de aplicação de template em itens
     * POST /api/catalog/clone/templates/preview
     */
    public function previewTemplate(): void
    {
        header('Content-Type: application/json');

        $input = $this->request->json();

        if (!$input || empty($input['template_slug']) || empty($input['items'])) {
            http_response_code(400);
            echo json_encode(['error' => 'template_slug e items são obrigatórios']);
            return;
        }

        try {
            $template = $this->getTemplateService()->getTemplate($input['template_slug']);

            if (!$template) {
                http_response_code(404);
                echo json_encode(['error' => 'Template não encontrado']);
                return;
            }

            $previews = [];
            foreach ($input['items'] as $item) {
                $preview = $this->getTemplateService()->applyTemplateRules($item, $template);
                $previews[] = [
                    'original' => $item,
                    'transformed' => $preview,
                    'changes' => $this->detectChanges($item, $preview),
                ];
            }

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'template' => $template['name'],
                'previews' => $previews,
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao gerar preview', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Detecta mudanças entre original e transformado
     */
    private function detectChanges(array $original, array $transformed): array
    {
        $changes = [];
        
        if (($original['price'] ?? 0) !== ($transformed['price'] ?? 0)) {
            $changes[] = [
                'field' => 'price',
                'from' => $original['price'] ?? 0,
                'to' => $transformed['price'] ?? 0,
            ];
        }
        
        if (($original['available_quantity'] ?? 0) !== ($transformed['available_quantity'] ?? 0)) {
            $changes[] = [
                'field' => 'available_quantity',
                'from' => $original['available_quantity'] ?? 0,
                'to' => $transformed['available_quantity'] ?? 0,
            ];
        }
        
        if (($original['title'] ?? '') !== ($transformed['title'] ?? '')) {
            $changes[] = [
                'field' => 'title',
                'from' => $original['title'] ?? '',
                'to' => $transformed['title'] ?? '',
            ];
        }

        return $changes;
    }

    // =========================================================================
    // FASE 6: Métricas e Observabilidade
    // =========================================================================

    /**
     * Dashboard de métricas de clonagem
     * GET /api/catalog/clone/metrics/dashboard
     */
    public function getMetricsDashboard(): void
    {
        header('Content-Type: application/json');

        try {
            $accountId = $this->request->get('account_id') ? $this->request->getInt('account_id') : null;
            $days = $this->request->getInt('days', 30);

            $dashboard = $this->getMetricsService()->getDashboard($accountId, $days);

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'dashboard' => $dashboard,
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao carregar dashboard', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Métricas de jobs recentes
     * GET /api/catalog/clone/metrics/jobs
     */
    public function getJobsMetrics(): void
    {
        header('Content-Type: application/json');

        try {
            $accountId = $this->request->get('account_id') ? $this->request->getInt('account_id') : null;
            $limit = $this->request->getInt('limit', 10);

            $jobs = $this->getMetricsService()->getRecentJobsMetrics($accountId, $limit);

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'jobs' => $jobs,
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao carregar métricas de jobs', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Top erros de clonagem
     * GET /api/catalog/clone/metrics/errors
     */
    public function getTopErrors(): void
    {
        header('Content-Type: application/json');

        try {
            $accountId = $this->request->get('account_id') ? $this->request->getInt('account_id') : null;
            $days = $this->request->getInt('days', 30);
            $limit = $this->request->getInt('limit', 10);

            $errors = $this->getMetricsService()->getTopErrors($accountId, $days, $limit);

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'errors' => $errors,
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao carregar top erros', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Comparativo semanal
     * GET /api/catalog/clone/metrics/weekly
     */
    public function getWeeklyComparison(): void
    {
        header('Content-Type: application/json');

        try {
            $accountId = $this->request->get('account_id') ? $this->request->getInt('account_id') : null;

            $comparison = $this->getMetricsService()->getWeeklyComparison($accountId);

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'comparison' => $comparison,
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao carregar comparativo', 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // FASE 6: Ações Pós-Clone
    // =========================================================================

    /**
     * Estatísticas de ações pós-clone
     * GET /api/catalog/clone/post-actions/stats
     */
    public function getPostActionsStats(): void
    {
        header('Content-Type: application/json');

        try {
            $jobId = $this->request->get('job_id');
            $days = $this->request->getInt('days', 7);

            $stats = $this->getPostActionsService()->getActionStats($jobId, $days);

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'stats' => $stats,
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao carregar estatísticas', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Processa ações pós-clone pendentes manualmente
     * POST /api/catalog/clone/post-actions/process
     */
    public function processPostActions(): void
    {
        header('Content-Type: application/json');

        $input = $this->request->json();

        try {
            $jobId = $input['job_id'] ?? null;
            $itemId = $input['item_id'] ?? null;
            $limit = (int)($input['limit'] ?? 50);

            $results = $this->getPostActionsService()->processPendingActions($jobId, $itemId, $limit);

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'processed' => count($results),
                'results' => $results,
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao processar ações', 'message' => $e->getMessage()]);
        }
    }

    // =========================================================================
    // FASE 6: MONITORAMENTO E HARDENING
    // =========================================================================

    /**
     * Obtém saúde geral do sistema de clonagem
     * GET /api/catalog/clone/monitoring/health
     */
    public function getSystemHealth(): void
    {
        header('Content-Type: application/json');

        try {
            $monitoring = new \App\Services\CloneMonitoringService();
            $health = $monitoring->getSystemHealth();

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'health' => $health
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao verificar saúde', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Lista alertas do sistema
     * GET /api/catalog/clone/monitoring/alerts
     */
    public function listAlerts(): void
    {
        header('Content-Type: application/json');

        try {
            $onlyUnacknowledged = ($this->request->get('unacknowledged', '1')) === '1';
            $limit = $this->request->getInt('limit', 50);

            $monitoring = new \App\Services\CloneMonitoringService();
            $alerts = $monitoring->listAlerts($onlyUnacknowledged, $limit);

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'alerts' => $alerts,
                'count' => count($alerts)
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao listar alertas', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Reconhece um alerta
     * POST /api/catalog/clone/monitoring/alerts/{id}/acknowledge
     */
    public function acknowledgeAlert(string $id): void
    {
        header('Content-Type: application/json');

        try {
            $userId = $this->getUserId() ?? 0;

            $monitoring = new \App\Services\CloneMonitoringService();
            $success = $monitoring->acknowledgeAlert((int)$id, $userId);

            if ($success) {
                http_response_code(200);
                echo json_encode(['status' => 'success', 'message' => 'Alerta reconhecido']);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Alerta não encontrado']);
            }

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao reconhecer alerta', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Lista feature flags
     * GET /api/catalog/clone/monitoring/flags
     */
    public function listFeatureFlags(): void
    {
        header('Content-Type: application/json');

        try {
            $monitoring = new \App\Services\CloneMonitoringService();
            $flags = $monitoring->listFeatureFlags();

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'flags' => $flags
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao listar flags', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Atualiza uma feature flag
     * PUT /api/catalog/clone/monitoring/flags/{name}
     */
    public function updateFeatureFlag(string $name): void
    {
        header('Content-Type: application/json');

        $input = $this->request->json();

        try {
            if (!isset($input['enabled'])) {
                http_response_code(400);
                echo json_encode(['error' => "Campo 'enabled' é obrigatório"]);
                return;
            }

            $userId = $this->getUserId() ?? 0;
            $enabled = (bool)$input['enabled'];

            $monitoring = new \App\Services\CloneMonitoringService();
            $success = $monitoring->setFeatureFlag($name, $enabled, $userId);

            if ($success) {
                http_response_code(200);
                echo json_encode([
                    'status' => 'success',
                    'message' => "Flag '{$name}' " . ($enabled ? 'habilitada' : 'desabilitada')
                ]);
            } else {
                http_response_code(500);
                echo json_encode(['error' => 'Erro ao atualizar flag']);
            }

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao atualizar flag', 'message' => $e->getMessage()]);
        }
    }

    /**
     * Obtém relatório diário
     * GET /api/catalog/clone/monitoring/report
     */
    public function getDailyReport(): void
    {
        header('Content-Type: application/json');

        try {
            $date = $this->request->get('date') ?? date('Y-m-d');

            $monitoring = new \App\Services\CloneMonitoringService();
            $report = $monitoring->generateDailyReport($date);

            http_response_code(200);
            echo json_encode([
                'status' => 'success',
                'report' => $report
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => 'Erro ao gerar relatório', 'message' => $e->getMessage()]);
        }
    }

    // ==================== DASHBOARD REAL-TIME (FASE 8) ====================

    /**
     * Stream SSE do dashboard em tempo real
     * GET /api/catalog/clone/dashboard/stream[?account_id=123]
     */
    public function streamDashboard(): void
    {
        $accountId = $this->request->get('account_id') ?? $this->getActiveAccountId();
        $accountId = $accountId ? (int)$accountId : null;

        $dashboard = new \App\Services\CloneRealtimeDashboardService();
        $dashboard->streamDashboardData($accountId);
    }

    /**
     * Snapshot único do dashboard
     * GET /api/catalog/clone/dashboard/snapshot[?account_id=123]
     */
    public function getDashboardSnapshot(): void
    {
        header('Content-Type: application/json');

        try {
            $accountId = $this->request->get('account_id') ?? $this->getActiveAccountId();
            $accountId = $accountId ? (int)$accountId : null;

            $dashboard = new \App\Services\CloneRealtimeDashboardService();
            $snapshot = $dashboard->getDashboardSnapshot($accountId);
            
            echo json_encode([
                'success' => true,
                'data' => $snapshot,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Progresso de job específico (dashboard widget)
     * GET /api/catalog/clone/dashboard/job/{jobId}/progress
     */
    public function getJobProgressWidget(int $jobId): void
    {
        header('Content-Type: application/json');

        try {
            $dashboard = new \App\Services\CloneRealtimeDashboardService();
            $progress = $dashboard->getJobProgress($jobId);
            
            echo json_encode([
                'success' => true,
                'data' => $progress,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ==================== EXPORT DE RELATÓRIOS (FASE 8) ====================

    /**
     * Exportar relatório
     * POST /api/catalog/clone/reports/export
     * Body: {"format": "pdf|excel|csv", "filters": {...}, "options": {...}}
     */
    public function exportReport(): void
    {
        header('Content-Type: application/json');

        try {
            $input = $this->request->json();
            
            $format = $input['format'] ?? 'csv';
            $filters = $input['filters'] ?? [];
            $options = $input['options'] ?? [];

            // Se não especificar account_id, usar da sessão
            if (empty($filters['account_id'])) {
                $activeAccountId = $this->getActiveAccountId();
                if ($activeAccountId) {
                    $filters['account_id'] = $activeAccountId;
                }
            }

            $exporter = new \App\Services\CloneReportExportService();
            $result = $exporter->exportReport($format, $filters, $options);

            if ($result['success']) {
                echo json_encode([
                    'success' => true,
                    'file' => [
                        'filename' => $result['filename'],
                        'download_url' => $result['download_url'],
                    ],
                    'message' => 'Relatório gerado com sucesso',
                ]);
            } else {
                http_response_code(400);
                echo json_encode($result);
            }
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Download de relatório gerado
     * GET /api/catalog/clone/reports/download/{filename}
     */
    public function downloadReport(string $filename): void
    {
        // Validar filename para prevenir path traversal
        $filename = basename($filename);
        $filePath = __DIR__ . '/../../storage/exports/' . $filename;

        if (!file_exists($filePath)) {
            http_response_code(404);
            echo json_encode(['error' => 'File not found']);
            return;
        }

        // Detectar tipo MIME
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $mimeTypes = [
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'csv' => 'text/csv',
            'html' => 'text/html',
        ];
        $mimeType = $mimeTypes[$extension] ?? 'application/octet-stream';

        // Headers para download
        header('Content-Type: ' . $mimeType);
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . filesize($filePath));
        header('Cache-Control: must-revalidate');
        header('Pragma: public');

        readfile($filePath);
    }

    // ==================== INTEGRAÇÃO SEO (FASE 8) ====================

    /**
     * Analisar item antes de clonar (SEO)
     * POST /api/catalog/clone/seo/analyze
     * Body: {"item_id": "MLB123", "optimization_level": "basic|advanced|aggressive"}
     */
    public function analyzeSeo(): void
    {
        header('Content-Type: application/json');

        $accountId = $this->getActiveAccountId();
        if (!$accountId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'No active account selected',
            ]);
            return;
        }

        try {
            $input = $this->request->json();
            
            $itemId = $input['item_id'] ?? null;
            $level = $input['optimization_level'] ?? \App\Services\CloneSeoIntegrationService::OPTIMIZATION_BASIC;

            if (!$itemId) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'item_id is required',
                ]);
                return;
            }

            $seoService = new \App\Services\CloneSeoIntegrationService($accountId);
            $analysis = $seoService->analyzeBeforeClone($itemId, $level);

            echo json_encode([
                'success' => true,
                'data' => $analysis,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Aplicar otimizações SEO a dados de item
     * POST /api/catalog/clone/seo/optimize
     * Body: {"item_data": {...}, "optimization_level": "basic", "options": {}}
     */
    public function applyOptimizations(): void
    {
        header('Content-Type: application/json');

        $accountId = $this->getActiveAccountId();
        if (!$accountId) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'No active account selected',
            ]);
            return;
        }

        try {
            $input = $this->request->json();
            
            $itemData = $input['item_data'] ?? null;
            $level = $input['optimization_level'] ?? \App\Services\CloneSeoIntegrationService::OPTIMIZATION_BASIC;
            $options = $input['options'] ?? [];

            if (!$itemData) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'item_data is required',
                ]);
                return;
            }

            $seoService = new \App\Services\CloneSeoIntegrationService($accountId);
            $optimized = $seoService->applyOptimizations($itemData, $level, $options);

            echo json_encode([
                'success' => true,
                'data' => $optimized,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    // ==================== PROGRESS TRACKING (FASE 8) ====================

    /**
     * Obter progresso de um job
     * GET /api/catalog/clone/progress/{jobId}
     */
    public function getJobProgress(int $jobId): void
    {
        header('Content-Type: application/json');

        try {
            $tracker = new \App\Services\CloneProgressTrackerService();
            $progress = $tracker->getJobProgress($jobId);

            if ($progress === null) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Job not found or progress not tracked',
                ]);
                return;
            }

            echo json_encode([
                'success' => true,
                'data' => $progress,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obter histórico de progresso de um job
     * GET /api/catalog/clone/progress/{jobId}/history[?limit=50]
     */
    public function getProgressHistory(int $jobId): void
    {
        header('Content-Type: application/json');

        try {
            $limit = min($this->request->getInt('limit', 50), 1000);

            $tracker = new \App\Services\CloneProgressTrackerService();
            $history = $tracker->getProgressHistory($jobId, $limit);

            echo json_encode([
                'success' => true,
                'data' => $history,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Obter progresso de múltiplos jobs
     * POST /api/catalog/clone/progress/batch
     * Body: {"job_ids": [123, 456, 789]}
     */
    public function getBatchProgress(): void
    {
        header('Content-Type: application/json');

        try {
            $input = $this->request->json();
            $jobIds = $input['job_ids'] ?? [];

            if (empty($jobIds) || !is_array($jobIds)) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'job_ids array is required',
                ]);
                return;
            }

            $tracker = new \App\Services\CloneProgressTrackerService();
            $progress = $tracker->getBatchProgress($jobIds);

            echo json_encode([
                'success' => true,
                'data' => $progress,
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
