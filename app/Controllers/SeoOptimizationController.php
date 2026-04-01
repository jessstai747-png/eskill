<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Services\SEO\SEOOptimizerService;

/**
 * Controller de otimização SEO.
 *
 * Fornece endpoints para análise e otimização SEO de anúncios,
 * validação de inputs e health check dos serviços SEO.
 */
class SeoOptimizationController extends BaseController
{
    private SEOOptimizerService $optimizer;

    public function __construct()
    {
        parent::__construct();
        $this->optimizer = new SEOOptimizerService();
    }

    /**
     * Health check dos serviços SEO.
     * GET /api/seo/optimizer/health
     */
    public function healthCheck(): void
    {
        $services = [
            'SEOOptimizerService',
            'TitleOptimizerService',
            'KeywordResearchService',
            'ListingBuilderService',
        ];

        $results = [];
        $overallHealthy = true;

        foreach ($services as $serviceName) {
            $check = $this->checkService($serviceName);
            $results[$serviceName] = $check;
            if ($check['status'] !== 'healthy') {
                $overallHealthy = false;
            }
        }

        $this->json([
            'services' => $results,
            'overall_status' => $overallHealthy ? 'healthy' : 'degraded',
            'timestamp' => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Otimização completa de produto via IA.
     * POST /api/seo/optimizer/product
     */
    public function optimizeProduct(): void
    {
        if (!$this->userService->isAuthenticated()) {
            http_response_code(401);
            $this->json(['error' => 'Autenticação necessária']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            http_response_code(400);
            $this->json(['error' => 'JSON inválido']);
            return;
        }

        try {
            $this->validateRequired($input, ['title']);
        } catch (\InvalidArgumentException $e) {
            http_response_code(422);
            $this->json(['error' => $e->getMessage()]);
            return;
        }

        try {
            $result = $this->optimizer->optimizeProduct($input);
            $this->json($result);
        } catch (\Throwable $e) {
            http_response_code(500);
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Otimização de título com IA.
     * POST /api/seo/optimizer/title
     */
    public function optimizeTitle(): void
    {
        if (!$this->userService->isAuthenticated()) {
            http_response_code(401);
            $this->json(['error' => 'Autenticação necessária']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);

        if (!is_array($input)) {
            http_response_code(400);
            $this->json(['error' => 'JSON inválido']);
            return;
        }

        try {
            $this->validateRequired($input, ['title']);
        } catch (\InvalidArgumentException $e) {
            http_response_code(422);
            $this->json(['error' => $e->getMessage()]);
            return;
        }

        $title    = (string) $input['title'];
        $context  = is_array($input['context'] ?? null) ? $input['context'] : [];

        try {
            $result = $this->optimizer->optimizeTitle($title, $context);
            $this->json($result);
        } catch (\Throwable $e) {
            http_response_code(500);
            $this->json(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Verifica disponibilidade de um serviço SEO.
     *
     * @param string $serviceName Nome do serviço
     * @return array{status: string, message?: string}
     */
    private function checkService(string $serviceName): array
    {
        try {
            $fqcn = $this->resolveServiceClass($serviceName);

            if (!class_exists($fqcn)) {
                return [
                    'status' => 'unhealthy',
                    'message' => "Classe {$serviceName} não encontrada",
                ];
            }

            // Tenta instanciar (sem args)
            $service = new $fqcn();

            // Se o serviço tem isAvailable(), usa
            if (method_exists($service, 'isAvailable')) {
                return [
                    'status' => $service->isAvailable() ? 'healthy' : 'unhealthy',
                ];
            }

            return ['status' => 'healthy'];
        } catch (\Throwable $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage(),
            ];
        }
    }

    /**
     * Valida campos obrigatórios no input.
     *
     * @param array $input    Dados de entrada
     * @param array $required Campos obrigatórios
     * @throws \InvalidArgumentException Se campo obrigatório estiver ausente
     */
    private function validateRequired(array $input, array $required): void
    {
        $missing = [];
        foreach ($required as $field) {
            if (!array_key_exists($field, $input)) {
                $missing[] = $field;
            }
        }

        if (!empty($missing)) {
            throw new \InvalidArgumentException(
                'Campos obrigatórios ausentes: ' . implode(', ', $missing)
            );
        }
    }

    /**
     * Resolve o FQCN de um serviço SEO pelo nome.
     */
    private function resolveServiceClass(string $name): string
    {
        $namespaces = [
            "App\\Services\\SEO\\{$name}",
            "App\\Services\\{$name}",
        ];

        foreach ($namespaces as $fqcn) {
            if (class_exists($fqcn)) {
                return $fqcn;
            }
        }

        return "App\\Services\\SEO\\{$name}";
    }
}
