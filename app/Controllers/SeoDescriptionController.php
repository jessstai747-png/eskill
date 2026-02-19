<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\SEO\DescriptionBuilderService;
use App\Services\SEO\ContextInjectorService;
use App\Services\SEO\LongTailGeneratorService;

/**
 * @deprecated Functionality consolidated in SEOKillerController (DescriptionKiller service).
 * API endpoints remain functional for backward compatibility.
 */
class SeoDescriptionController extends BaseController
{
    private DescriptionBuilderService $descriptionBuilderService;
    private ContextInjectorService $contextInjectorService;
    private LongTailGeneratorService $longTailGeneratorService;

    public function __construct()
    {
        parent::__construct();
        $this->descriptionBuilderService = new DescriptionBuilderService();
        $this->contextInjectorService = new ContextInjectorService();
        $this->longTailGeneratorService = new LongTailGeneratorService();
    }

    public function build(): void
    {
        $this->requireUserId();
        $data = $this->getRequestData();
        $item = $data['item'] ?? [];
        $distribution = $data['distribution'] ?? [];
        $data = $this->descriptionBuilderService->build($item, $distribution);
        $this->jsonResponse($data);
    }

    public function generateBlock(): void
    {
        $this->requireUserId();
        $data = $this->getRequestData();
        $blockType = $data['blockType'] ?? '';
        $item = $data['item'] ?? [];
        $keywords = $data['keywords'] ?? [];
        $data = $this->descriptionBuilderService->generateBlock($blockType, $item, $keywords);
        $this->jsonResponse(['html' => $data]);
    }

    public function generateFaq(): void
    {
        $this->requireUserId();
        $data = $this->getRequestData();
        $item = $data['item'] ?? [];
        $keywords = $data['keywords'] ?? [];
        // Assuming generateFAQBlock is the method to be called.
        // The documentation is a bit ambiguous here.
        $data = $this->descriptionBuilderService->generateBlock('faq', $item, $keywords);
        $this->jsonResponse(['html' => $data]);
    }

    public function validate(): void
    {
        $this->requireUserId();
        $data = $this->getRequestData();
        $description = $data['description'] ?? '';
        $data = $this->descriptionBuilderService->validateDescription($description);
        $this->jsonResponse($data);
    }

    public function getContexts(string $categoryId): void
    {
        $this->requireUserId();
        // This method is defined in the documentation for Phase 1, but also seems to fit here.
        // The documentation for Phase 3 API endpoints does not specify the controller for this route.
        // I'm assuming it's in this controller for now.
        $data = $this->contextInjectorService->detectApplicableContexts(['category_id' => $categoryId]);
        $this->jsonResponse($data);
    }

    public function generateLongTail(): void
    {
        $this->requireUserId();
        $data = $this->getRequestData();
        $title = $data['title'] ?? '';
        $categoryId = $data['categoryId'] ?? '';
        $data = $this->longTailGeneratorService->generate($title, $categoryId);
        $this->jsonResponse($data);
    }

    private function getRequestData(): array
    {
        $data = filter_input_array(INPUT_POST) ?? [];
        $raw = file_get_contents('php://input');
        if (is_string($raw) && $raw !== '') {
            $json = json_decode($raw, true);
            if (is_array($json)) {
                $data = array_merge($data, $json);
            }
        }

        return $data;
    }

    private function jsonResponse(array $data, int $statusCode = 200): void
    {
        header('Content-Type: application/json');
        http_response_code($statusCode);
        echo json_encode($data);
    }
}
