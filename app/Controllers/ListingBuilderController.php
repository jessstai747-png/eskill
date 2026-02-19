<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\ListingBuilder\ListingBuilderService;
use App\Services\ListingBuilder\TemplateManagerService;

/**
 * Listing Builder Controller - API para construtor de anúncios
 */
class ListingBuilderController extends BaseController
{
    private ListingBuilderService $builder;
    private TemplateManagerService $templateManager;
    private ?int $accountId = null;

    public function __construct(?int $accountId = null)
    {
        parent::__construct();
        $this->accountId = $accountId;
        $this->builder = new ListingBuilderService($accountId);
        $this->templateManager = new TemplateManagerService();
    }

    /**
     * Inicia processo de criação de anúncio
     * POST /api/listing-builder/start
     * Body: {category_id?, product_name?}
     */
    public function start(): void
    {
        $this->requireUserId();
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);
            $result = $this->builder->startListing($input ?? []);

            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Valida step do wizard
     * POST /api/listing-builder/validate/{step}
     * Body: {dados do step}
     */
    public function validateStep(string $step): void
    {
        $this->requireUserId();
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Dados não fornecidos',
                ]);
                return;
            }

            $result = $this->builder->validateStep($input, $step);

            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Constrói anúncio completo
     * POST /api/listing-builder/build
     * Body: {title, category_id, price, ...}
     */
    public function build(): void
    {
        $this->requireUserId();
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Dados do anúncio não fornecidos',
                ]);
                return;
            }

            $result = $this->builder->buildListing($input);

            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Publica anúncio no ML
     * POST /api/listing-builder/publish
     * Body: {listing_data}
     */
    public function publish(): void
    {
        $this->requireUserId();
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!$input || empty($input['listing_data'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'listing_data é obrigatório',
                ]);
                return;
            }

            $result = $this->builder->publishListing(
                $input['listing_data'],
                $input['options'] ?? []
            );

            if ($result['success']) {
                http_response_code(201);
            } else {
                http_response_code(400);
            }

            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Salva rascunho
     * POST /api/listing-builder/draft/save
     * Body: {data, draft_name?}
     */
    public function saveDraft(): void
    {
        $this->requireUserId();
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            $result = $this->builder->saveDraft(
                $input['data'] ?? [],
                $input['draft_name'] ?? ''
            );

            echo json_encode($result, JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Carrega rascunho
     * GET /api/listing-builder/draft/{draftId}
     */
    public function loadDraft(string $draftId): void
    {
        $this->requireUserId();
        header('Content-Type: application/json');

        try {
            $result = $this->builder->loadDraft($draftId);

            if (!$result['success']) {
                http_response_code(404);
            }

            echo json_encode($result, JSON_PRETTY_PRINT);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Clona anúncio existente
     * POST /api/listing-builder/clone
     * Body: {item_id, improvements?}
     */
    public function clone(): void
    {
        $this->requireUserId();
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['item_id'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'item_id é obrigatório',
                ]);
                return;
            }

            $result = $this->builder->cloneListing(
                $input['item_id'],
                $input['improvements'] ?? []
            );

            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Lista templates disponíveis
     * GET /api/listing-builder/templates?category_id=MLB1234
     */
    public function listTemplates(): void
    {
        $this->requireUserId();
        header('Content-Type: application/json');

        try {
            $categoryId = $this->request->get('category_id');

            if ($categoryId) {
                $templates = $this->templateManager->getTemplatesByCategory($categoryId);
            } else {
                $templates = $this->templateManager->getAllTemplates();
            }

            echo json_encode([
                'success' => true,
                'templates' => $templates,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Obtém template específico
     * GET /api/listing-builder/templates/{templateId}
     */
    public function getTemplate(string $templateId): void
    {
        $this->requireUserId();
        header('Content-Type: application/json');

        try {
            $template = $this->templateManager->getTemplate($templateId);

            if (!$template) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Template não encontrado',
                ]);
                return;
            }

            echo json_encode([
                'success' => true,
                'template' => $template,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Renderiza template com dados
     * POST /api/listing-builder/templates/{templateId}/render
     * Body: {dados para preencher variáveis}
     */
    public function renderTemplate(string $templateId): void
    {
        $this->requireUserId();
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            $rendered = $this->templateManager->renderTemplate($templateId, $input ?? []);

            if (empty($rendered)) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Template não encontrado ou erro na renderização',
                ]);
                return;
            }

            echo json_encode([
                'success' => true,
                'rendered_html' => $rendered,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Cria template personalizado
     * POST /api/listing-builder/templates/custom
     * Body: {name, description, content, categories?}
     */
    public function createCustomTemplate(): void
    {
        $this->requireUserId();
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['name']) || empty($input['content'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'name e content são obrigatórios',
                ]);
                return;
            }

            $result = $this->templateManager->createCustomTemplate($input);

            http_response_code(201);
            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Lista blocos reutilizáveis
     * GET /api/listing-builder/blocks
     */
    public function listBlocks(): void
    {
        $this->requireUserId();
        header('Content-Type: application/json');

        try {
            $blocks = $this->templateManager->getBlocks();

            echo json_encode([
                'success' => true,
                'blocks' => $blocks,
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }
}
