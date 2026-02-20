<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Database;
use App\Services\ItemService;
use App\Services\MercadoLivreClient;
use App\Services\LogService;
use App\Services\UserService;
use PDO;

class BulkEditorController extends BaseController
{
    private ItemService $itemService;
    private ?MercadoLivreClient $mlClient = null;
    private PDO $db;
    private ?int $accountId;
    private LogService $logger;
    private UserService $userService;

    // Ações permitidas
    private const ALLOWED_ACTIONS = [
        'price_increase',      // Aumentar preço em %
        'price_decrease',      // Diminuir preço em %
        'price_set',           // Definir preço fixo
        'stock_set',           // Definir estoque
        'stock_add',           // Adicionar ao estoque
        'pause',               // Pausar anúncios
        'activate',            // Ativar anúncios
        'update_title_prefix', // Adicionar prefixo ao título
        'update_title_suffix', // Adicionar sufixo ao título
    ];

    // Compatibilidade retroativa com payloads legados da view
    private const ACTION_ALIASES = [
        'price_increase_percent' => 'price_increase',
        'price_decrease_percent' => 'price_decrease',
        'status_pause' => 'pause',
        'status_activate' => 'activate',
    ];

    private const ACTIONS_REQUIRING_NUMERIC_VALUE = [
        'price_increase',
        'price_decrease',
        'price_set',
        'stock_set',
        'stock_add',
    ];

    public function __construct(UserService $userService)
    {
        $this->userService = $userService;
        $this->accountId = $_SESSION['active_ml_account_id'] ?? null;
        $this->itemService = new ItemService($this->accountId);
        $this->db = Database::getInstance();
        $this->logger = new LogService();

        try {
            $this->mlClient = new MercadoLivreClient();
        } catch (\Throwable $e) {
            // ML client não disponível
        }
    }

    /**
     * Render Bulk Editor View
     */
    public function index(): void
    {
        if (!$this->userService->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        $pageTitle = 'Editor em Massa';
        $activePage = 'items';

        ob_start();
        require __DIR__ . '/../Views/dashboard/items/bulk.php';
        $content = ob_get_clean();
        require __DIR__ . '/../Views/layouts/modern/app.php';
    }

    /**
     * API: Apply Bulk Updates - Implementação real
     */
    public function applyUpdates(): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);
        $ids = $data['ids'] ?? [];
        $action = $this->normalizeAction($data['action'] ?? null);
        $value = $data['value'] ?? null;

        // Validações
        if (empty($ids)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Nenhum item selecionado']);
            return;
        }

        if (!$action || !in_array($action, self::ALLOWED_ACTIONS, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ação inválida: ' . $action]);
            return;
        }

        // Validar valor para ações que precisam
        if (in_array($action, self::ACTIONS_REQUIRING_NUMERIC_VALUE, true)) {
            if ($value === null || !is_numeric($value)) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Valor numérico é obrigatório para esta ação']);
                return;
            }
        }

        try {
            $results = [];
            $successCount = 0;
            $failCount = 0;
            $errors = [];

            // Processar cada item
            foreach ($ids as $itemId) {
                $result = $this->processItemUpdate($itemId, $action, $value);

                if ($result['success']) {
                    $successCount++;
                } else {
                    $failCount++;
                    $errors[] = [
                        'item_id' => $itemId,
                        'error' => $result['error']
                    ];
                }

                $results[] = $result;
            }

            // Log da operação
            $this->logger->info('Bulk update completed', [
                'account_id' => $this->accountId,
                'action' => $action,
                'value' => $value,
                'total' => count($ids),
                'success' => $successCount,
                'failed' => $failCount
            ]);

            echo json_encode([
                'success' => true,
                'processed' => count($ids),
                'success_count' => $successCount,
                'fail_count' => $failCount,
                'errors' => array_slice($errors, 0, 10), // Limitar erros retornados
                'message' => "Processados {$successCount} itens com sucesso" .
                            ($failCount > 0 ? ", {$failCount} falharam" : "")
            ]);

        } catch (\Exception $e) {
            $this->logger->error('Bulk update failed', [
                'error' => $e->getMessage(),
                'action' => $action
            ]);

            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Processa atualização de um item individual
     */
    private function processItemUpdate(string $itemId, string $action, $value): array
    {
        try {
            // Buscar item atual do banco
            $stmt = $this->db->prepare("
                SELECT ml_item_id, title, price, available_quantity, status
                FROM items
                WHERE (ml_item_id = :id OR id = :id2)
                AND account_id = :account_id
            ");
            $stmt->execute([
                'id' => $itemId,
                'id2' => $itemId,
                'account_id' => $this->accountId
            ]);
            $item = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$item) {
                return ['success' => false, 'item_id' => $itemId, 'error' => 'Item não encontrado'];
            }

            $mlItemId = $item['ml_item_id'];
            $updateData = [];
            $localUpdate = [];

            // Determinar atualização baseada na ação
            switch ($action) {
                case 'price_increase':
                    $currentPrice = (float)$item['price'];
                    $newPrice = round($currentPrice * (1 + ($value / 100)), 2);
                    $updateData['price'] = $newPrice;
                    $localUpdate['price'] = $newPrice;
                    break;

                case 'price_decrease':
                    $currentPrice = (float)$item['price'];
                    $newPrice = round($currentPrice * (1 - ($value / 100)), 2);
                    $updateData['price'] = max(1, $newPrice); // Preço mínimo R$1
                    $localUpdate['price'] = $updateData['price'];
                    break;

                case 'price_set':
                    $updateData['price'] = round((float)$value, 2);
                    $localUpdate['price'] = $updateData['price'];
                    break;

                case 'stock_set':
                    $updateData['available_quantity'] = max(0, (int)$value);
                    $localUpdate['available_quantity'] = $updateData['available_quantity'];
                    break;

                case 'stock_add':
                    $currentStock = (int)$item['available_quantity'];
                    $newStock = max(0, $currentStock + (int)$value);
                    $updateData['available_quantity'] = $newStock;
                    $localUpdate['available_quantity'] = $newStock;
                    break;

                case 'pause':
                    $updateData['status'] = 'paused';
                    $localUpdate['status'] = 'paused';
                    break;

                case 'activate':
                    $updateData['status'] = 'active';
                    $localUpdate['status'] = 'active';
                    break;

                case 'update_title_prefix':
                    $newTitle = $value . ' ' . $item['title'];
                    $updateData['title'] = substr($newTitle, 0, 60); // Limite ML
                    $localUpdate['title'] = $updateData['title'];
                    break;

                case 'update_title_suffix':
                    $newTitle = $item['title'] . ' ' . $value;
                    $updateData['title'] = substr($newTitle, 0, 60);
                    $localUpdate['title'] = $updateData['title'];
                    break;
            }

            // Tentar atualizar no ML primeiro (se cliente disponível)
            $mlUpdated = false;
            if ($this->mlClient && !empty($updateData)) {
                try {
                    // Use ItemService wrapper which handles the API call correctly
                    $mlResult = $this->itemService->updateItem($mlItemId, $updateData);
                    $mlUpdated = $mlResult['success'] ?? false;
                } catch (\Throwable $e) {
                    // Log mas continua com atualização local
                    $this->logger->warning('ML API update failed', [
                        'item_id' => $mlItemId,
                        'error' => $e->getMessage()
                    ]);
                }
            }

            // Atualizar no banco local
            if (!empty($localUpdate)) {
                $this->updateLocalItem($mlItemId, $localUpdate);
            }

            return [
                'success' => true,
                'item_id' => $itemId,
                'ml_item_id' => $mlItemId,
                'ml_synced' => $mlUpdated,
                'changes' => $localUpdate
            ];

        } catch (\Throwable $e) {
            return [
                'success' => false,
                'item_id' => $itemId,
                'error' => $e->getMessage()
            ];
        }
    }

    /**
     * Atualiza item no banco de dados local
     */
    private function updateLocalItem(string $mlItemId, array $data): bool
    {
        if (empty($data)) {
            return false;
        }

        $setParts = [];
        $params = ['ml_item_id' => $mlItemId, 'account_id' => $this->accountId];

        foreach ($data as $field => $value) {
            // Whitelist de campos permitidos
            if (in_array($field, ['price', 'available_quantity', 'status', 'title'])) {
                $setParts[] = "{$field} = :{$field}";
                $params[$field] = $value;
            }
        }

        if (empty($setParts)) {
            return false;
        }

        $setParts[] = "updated_at = NOW()";

        $sql = "UPDATE items SET " . implode(', ', $setParts) .
               " WHERE ml_item_id = :ml_item_id AND account_id = :account_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * API: Preview das alterações antes de aplicar
     */
    public function previewChanges(): void
    {
        header('Content-Type: application/json');

        $data = json_decode(file_get_contents('php://input'), true);
        $ids = $data['ids'] ?? [];
        $action = $this->normalizeAction($data['action'] ?? null);
        $value = $data['value'] ?? null;

        if (empty($ids) || !$action) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Parâmetros inválidos']);
            return;
        }

        if (!in_array($action, self::ALLOWED_ACTIONS, true)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Ação inválida: ' . $action]);
            return;
        }

        try {
            // Buscar itens selecionados
            $placeholders = implode(',', array_fill(0, count($ids), '?'));
            $params = array_merge($ids, [$this->accountId]);

            $stmt = $this->db->prepare("
                SELECT ml_item_id, title, price, available_quantity, status
                FROM items
                WHERE ml_item_id IN ({$placeholders})
                AND account_id = ?
            ");
            $stmt->execute($params);
            $items = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Calcular preview das alterações
            $preview = [];
            foreach ($items as $item) {
                $change = $this->calculateChange($item, $action, $value);
                $preview[] = [
                    'item_id' => $item['ml_item_id'],
                    'title' => substr($item['title'], 0, 50),
                    'current' => $change['current'],
                    'new' => $change['new'],
                    'field' => $change['field']
                ];
            }

            echo json_encode([
                'success' => true,
                'preview' => $preview,
                'total_items' => count($preview)
            ]);

        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Calcula a mudança para preview
     */
    private function calculateChange(array $item, string $action, $value): array
    {
        $action = $this->normalizeAction($action);

        switch ($action) {
            case 'price_increase':
                return [
                    'field' => 'price',
                    'current' => (float)$item['price'],
                    'new' => round((float)$item['price'] * (1 + ($value / 100)), 2)
                ];

            case 'price_decrease':
                return [
                    'field' => 'price',
                    'current' => (float)$item['price'],
                    'new' => max(1, round((float)$item['price'] * (1 - ($value / 100)), 2))
                ];

            case 'price_set':
                return [
                    'field' => 'price',
                    'current' => (float)$item['price'],
                    'new' => round((float)$value, 2)
                ];

            case 'stock_set':
                return [
                    'field' => 'stock',
                    'current' => (int)$item['available_quantity'],
                    'new' => max(0, (int)$value)
                ];

            case 'stock_add':
                return [
                    'field' => 'stock',
                    'current' => (int)$item['available_quantity'],
                    'new' => max(0, (int)$item['available_quantity'] + (int)$value)
                ];

            case 'pause':
            case 'activate':
                return [
                    'field' => 'status',
                    'current' => $item['status'],
                    'new' => $action === 'pause' ? 'paused' : 'active'
                ];

            default:
                return [
                    'field' => 'unknown',
                    'current' => null,
                    'new' => null
                ];
        }
    }

    private function normalizeAction(?string $action): ?string
    {
        if ($action === null) {
            return null;
        }

        $trimmed = trim($action);
        if ($trimmed === '') {
            return null;
        }

        return self::ACTION_ALIASES[$trimmed] ?? $trimmed;
    }
}
