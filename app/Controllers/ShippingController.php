<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\ShippingService;
use App\Services\Shipping\ShippingSimulatorService;
use App\Services\Shipping\ShippingOptimizerService;
use App\Services\Shipping\DimensionCalculatorService;

class ShippingController
{
    private ShippingService $shippingService;
    private ?int $accountId = null;
    private Request $request;

    public function __construct(?int $accountId = null)
    {
        $this->request = new Request();

        if ($accountId === null) {
            if (session_status() === PHP_SESSION_NONE) {
                session_start();
            }
            $sessionAccount = $_SESSION['active_ml_account_id'] ?? $_SESSION['account_id'] ?? null;
            if ($sessionAccount !== null) {
                $candidate = (int) $sessionAccount;
                if ($candidate > 0) {
                    $accountId = $candidate;
                }
            }
        }

        $this->accountId = $accountId;
        $this->shippingService = new ShippingService($this->accountId);
    }

    /**
     * Renderiza a View de Expedição
     * GET /dashboard/shipping
     */
    public function index(): void
    {
        // View renders its own layout
        require __DIR__ . '/../Views/dashboard/shipping.php';
    }

    /**
     * Gera PDF da Picking List
     * POST /api/shipping/picking-list
     * Body: { "order_ids": [1, 2, 3] }
     */
    public function generatePickingList(): void
    {
        $data = json_decode(file_get_contents('php://input'), true);
        $orderIds = $data['order_ids'] ?? [];

        if (empty($orderIds)) {
            http_response_code(400);
            echo json_encode(['error' => 'No order IDs provided']);
            return;
        }

        try {
            $pdfContent = $this->shippingService->generatePickListPDF($orderIds);
            
            header('Content-Type: application/pdf');
            header('Content-Disposition: attachment; filename="picking_list_' . date('Y-m-d_Hi') . '.pdf"');
            echo $pdfContent;
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
    }

    // ========================================
    // SHIPPING STRATEGY OPTIMIZATION METHODS
    // ========================================

    /**
     * Simula custos de envio para um item
     * GET /api/shipping/simulate/{itemId}
     */
    public function simulateItem(string $itemId): void
    {
        header('Content-Type: application/json');

        try {
            $options = [];
            $zipCode = $this->request->get('zip_code');
            $includeFull = $this->request->get('include_full');
            
            // CEP de destino (opcional)
            if (!empty($zipCode)) {
                $options['zip_code'] = $zipCode;
            }

            // Incluir Full (opcional)
            if ($includeFull !== null) {
                $options['include_full'] = filter_var($includeFull, FILTER_VALIDATE_BOOLEAN);
            }

            $simulator = new ShippingSimulatorService($this->accountId);
            $result = $simulator->simulateForItem($itemId, $options);

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
     * Simula custos de envio com dados customizados
     * POST /api/shipping/simulate
     * Body: {dimensions, weight, zip_code?, include_full?}
     */
    public function simulateCustom(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['dimensions']) || !isset($input['weight'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Parâmetros obrigatórios: dimensions, weight',
                ]);
                return;
            }

            $simulator = new ShippingSimulatorService($this->accountId);
            $result = $simulator->simulateShipping($input);

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
     * Compara custos de envio para múltiplos CEPs
     * POST /api/shipping/compare
     * Body: {item_id?, dimensions?, weight?, zip_codes[]}
     */
    public function compareShipping(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['zip_codes']) || !is_array($input['zip_codes'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Parâmetro obrigatório: zip_codes (array)',
                ]);
                return;
            }

            $simulator = new ShippingSimulatorService($this->accountId);
            
            if (!empty($input['item_id'])) {
                $result = $simulator->compareShippingCosts($input['item_id'], $input['zip_codes']);
            } else if (isset($input['dimensions']) && isset($input['weight'])) {
                // Comparação com dados customizados implementada
                $result = $simulator->compareCustomShipping(
                    $input['dimensions'],
                    $input['weight'],
                    $input['zip_codes'],
                    $input['origin_zip'] ?? null
                );
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Forneça item_id OU (dimensions + weight)',
                ]);
                return;
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
     * Otimiz estratégia de envio para um item
     * GET /api/shipping/optimize/{itemId}
     */
    public function optimizeItem(string $itemId): void
    {
        header('Content-Type: application/json');

        try {
            $options = [];
            $targetMargin = $this->request->getFloat('target_margin', 0.0);
            $zipCode = $this->request->get('zip_code');

            // Margem desejada (opcional)
            if ($targetMargin > 0) {
                $options['target_margin'] = $targetMargin;
            }

            // CEP de destino (opcional)
            if (!empty($zipCode)) {
                $options['zip_code'] = $zipCode;
            }

            $optimizer = new ShippingOptimizerService($this->accountId);
            $result = $optimizer->optimizeShipping($itemId, $options);

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
     * Otimiza múltiplos itens em lote
     * POST /api/shipping/optimize/batch
     * Body: {item_ids[], options?}
     */
    public function optimizeBatch(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (empty($input['item_ids']) || !is_array($input['item_ids'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Parâmetro obrigatório: item_ids (array)',
                ]);
                return;
            }

            $options = $input['options'] ?? [];

            $optimizer = new ShippingOptimizerService($this->accountId);
            $result = $optimizer->optimizeBatch($input['item_ids'], $options);

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
     * Calcula peso cubado
     * POST /api/shipping/dimensions/cubic-weight
     * Body: {length, width, height}
     */
    public function calculateCubicWeight(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['length']) || !isset($input['width']) || !isset($input['height'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Parâmetros obrigatórios: length, width, height',
                ]);
                return;
            }

            $calculator = new DimensionCalculatorService();
            $cubicWeight = $calculator->calculateCubicWeight(
                (float)$input['length'],
                (float)$input['width'],
                (float)$input['height']
            );

            echo json_encode([
                'success' => true,
                'dimensions' => [
                    'length' => (float)$input['length'],
                    'width' => (float)$input['width'],
                    'height' => (float)$input['height'],
                ],
                'cubic_weight_kg' => $cubicWeight,
                'volume_cm3' => ($input['length'] * $input['width'] * $input['height']),
            ], JSON_PRETTY_PRINT);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Calcula peso efetivamente cobrado (maior entre real e cubado)
     * POST /api/shipping/dimensions/chargeable-weight
     * Body: {length, width, height, weight}
     */
    public function calculateChargeableWeight(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['length']) || !isset($input['width']) || 
                !isset($input['height']) || !isset($input['weight'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Parâmetros obrigatórios: length, width, height, weight',
                ]);
                return;
            }

            $calculator = new DimensionCalculatorService();
            $result = $calculator->calculateChargeableWeight(
                (float)$input['weight'],
                (float)$input['length'],
                (float)$input['width'],
                (float)$input['height']
            );

            echo json_encode([
                'success' => true,
                ...$result,
            ], JSON_PRETTY_PRINT);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Valida dimensões para uma modalidade específica
     * POST /api/shipping/dimensions/validate
     * Body: {length, width, height, weight, shipping_mode}
     */
    public function validateDimensions(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['length']) || !isset($input['width']) || 
                !isset($input['height']) || !isset($input['weight']) || 
                !isset($input['shipping_mode'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Parâmetros obrigatórios: length, width, height, weight, shipping_mode',
                ]);
                return;
            }

            $calculator = new DimensionCalculatorService();
            $result = $calculator->validateDimensions(
                (float)$input['length'],
                (float)$input['width'],
                (float)$input['height'],
                (float)$input['weight'],
                $input['shipping_mode']
            );

            echo json_encode([
                'success' => true,
                ...$result,
            ], JSON_PRETTY_PRINT);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Valida dimensões para todas as modalidades
     * POST /api/shipping/dimensions/validate-all
     * Body: {length, width, height, weight}
     */
    public function validateAllModes(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['length']) || !isset($input['width']) || 
                !isset($input['height']) || !isset($input['weight'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Parâmetros obrigatórios: length, width, height, weight',
                ]);
                return;
            }

            $calculator = new DimensionCalculatorService();
            $result = $calculator->validateForAllModes(
                (float)$input['length'],
                (float)$input['width'],
                (float)$input['height'],
                (float)$input['weight']
            );

            echo json_encode([
                'success' => true,
                ...$result,
            ], JSON_PRETTY_PRINT);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Sugere embalagem adequada
     * POST /api/shipping/dimensions/suggest-packaging
     * Body: {length, width, height}
     */
    public function suggestPackaging(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['length']) || !isset($input['width']) || !isset($input['height'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Parâmetros obrigatórios: length, width, height',
                ]);
                return;
            }

            $calculator = new DimensionCalculatorService();
            $result = $calculator->suggestPackaging(
                (float)$input['length'],
                (float)$input['width'],
                (float)$input['height']
            );

            echo json_encode([
                'success' => true,
                ...$result,
            ], JSON_PRETTY_PRINT);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Otimiza dimensões para reduzir frete
     * POST /api/shipping/dimensions/optimize
     * Body: {length, width, height, weight, target_mode?}
     */
    public function optimizeDimensions(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['length']) || !isset($input['width']) || 
                !isset($input['height']) || !isset($input['weight'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Parâmetros obrigatórios: length, width, height, weight',
                ]);
                return;
            }

            $targetMode = $input['target_mode'] ?? 'me2';

            $calculator = new DimensionCalculatorService();
            $result = $calculator->optimizeDimensions(
                (float)$input['length'],
                (float)$input['width'],
                (float)$input['height'],
                (float)$input['weight'],
                $targetMode
            );

            echo json_encode([
                'success' => true,
                ...$result,
            ], JSON_PRETTY_PRINT);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }

    /**
     * Análise completa de dimensões
     * POST /api/shipping/dimensions/analyze
     * Body: {length, width, height, weight}
     */
    public function analyzeDimensions(): void
    {
        header('Content-Type: application/json');

        try {
            $input = json_decode(file_get_contents('php://input'), true);

            if (!isset($input['length']) || !isset($input['width']) || 
                !isset($input['height']) || !isset($input['weight'])) {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'error' => 'Parâmetros obrigatórios: length, width, height, weight',
                ]);
                return;
            }

            $calculator = new DimensionCalculatorService();
            $result = $calculator->analyzeComplete(
                (float)$input['length'],
                (float)$input['width'],
                (float)$input['height'],
                (float)$input['weight']
            );

            echo json_encode([
                'success' => true,
                ...$result,
            ], JSON_PRETTY_PRINT);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => $e->getMessage(),
            ], JSON_PRETTY_PRINT);
        }
    }
}
