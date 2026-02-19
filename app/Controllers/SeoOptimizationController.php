<?php

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
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Health check dos serviços SEO.
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
