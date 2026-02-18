<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\UserService;
use App\Services\AuditLogService;
use App\Database;

class SettingsController
{
    private UserService $userService;
    private AuditLogService $auditLog;
    private Request $request;

    public function __construct()
    {
        $this->request = new Request();
        $this->userService = new UserService();
        $this->auditLog = new AuditLogService();
    }

    /**
     * Página principal de configurações (com novo layout)
     */
    public function index(): void
    {
        if (!$this->userService->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        $currentUser = $this->userService->getCurrentUser();
        $pageTitle = 'Configurações';
        $activePage = 'settings';

        ob_start();
        require __DIR__ . '/../Views/dashboard/settings-content.php';
        $content = ob_get_clean();

        require __DIR__ . '/../Views/layouts/modern/app.php';
    }

    /**
     * Salva preferências de notificações
     */
    public function saveNotifications(): void
    {
        if (!$this->userService->isAuthenticated()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Autenticação necessária']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $_SESSION['user_id'];

        // Whitelist de setting keys para notificações
        $allowedKeys = [
            'email_orders',
            'email_questions',
            'email_claims',
            'push_enabled',
            'sms_enabled',
            'telegram_enabled',
            'notify_low_stock',
            'notify_price_change',
            'notify_new_sale',
            'notify_shipment',
            'notify_return',
            'digest_daily',
        ];

        // Salvar no banco (criar tabela user_settings se necessário)
        $db = Database::getInstance();

        // Criar tabela se não existir
        $db->exec("
            CREATE TABLE IF NOT EXISTS user_settings (
                id INT PRIMARY KEY AUTO_INCREMENT,
                user_id INT NOT NULL,
                setting_key VARCHAR(100) NOT NULL,
                setting_value TEXT,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_user_setting (user_id, setting_key),
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        foreach ($input as $key => $value) {
            // Only accept whitelisted setting keys
            if (!in_array($key, $allowedKeys, true)) {
                continue;
            }
            $stmt = $db->prepare("
                INSERT INTO user_settings (user_id, setting_key, setting_value)
                VALUES (:user_id, :key, :value)
                ON DUPLICATE KEY UPDATE setting_value = :value, updated_at = NOW()
            ");
            $stmt->execute([
                'user_id' => $userId,
                'key' => $key,
                'value' => $value ? '1' : '0'
            ]);
        }

        $this->auditLog->log('settings_update', $userId, null, ['description' => 'Preferências de notificação atualizadas']);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Preferências salvas']);
    }

    /**
     * Salva configurações do Telegram
     */
    public function saveTelegram(): void
    {
        if (!$this->userService->isAuthenticated()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Autenticação necessária']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $_SESSION['user_id'];

        $db = Database::getInstance();

        $stmt = $db->prepare("
            INSERT INTO user_settings (user_id, setting_key, setting_value)
            VALUES 
                (:user_id, 'telegram_bot_token', :token),
                (:user_id, 'telegram_chat_id', :chat_id)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        $stmt->execute([
            'user_id' => $userId,
            'token' => $input['token'] ?? '',
            'chat_id' => $input['chatId'] ?? ''
        ]);

        $this->auditLog->log('settings_update', $userId, null, ['description' => 'Configuração do Telegram atualizada']);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Configuração do Telegram salva']);
    }

    /**
     * Salva configurações de sincronização
     */
    public function saveSync(): void
    {
        if (!$this->userService->isAuthenticated()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Autenticação necessária']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $userId = $_SESSION['user_id'];

        $db = Database::getInstance();

        $stmt = $db->prepare("
            INSERT INTO user_settings (user_id, setting_key, setting_value)
            VALUES 
                (:user_id, 'auto_sync_orders', :auto_sync),
                (:user_id, 'sync_interval', :interval)
            ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()
        ");
        $stmt->execute([
            'user_id' => $userId,
            'auto_sync' => $input['autoSync'] ? '1' : '0',
            'interval' => $input['interval'] ?? '30'
        ]);

        $this->auditLog->log('settings_update', $userId, null, ['description' => 'Configurações de sincronização atualizadas']);

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Configurações de sincronização salvas']);
    }

    /**
     * Salva configurações globais (Taxas, Precificação)
     */
    public function saveGlobal(): void
    {
        if (!$this->userService->isAuthenticated()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Autenticação necessária']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $accountId = $input['account_id'] ?? \App\Helpers\SessionHelper::getActiveAccountId();

        if (!$accountId) {
            http_response_code(400);
            echo json_encode(['error' => 'Account ID required']);
            return;
        }

        $settings = new \App\Services\SettingsService((int)$accountId);

        if (isset($input['default_tax_rate'])) {
            $settings->set('default_tax_rate', $input['default_tax_rate']);
        }

        if (isset($input['default_pricing_strategy'])) {
            $settings->set('default_pricing_strategy', $input['default_pricing_strategy']);
        }

        if (isset($input['min_margin_percent'])) {
            $settings->set('min_margin_percent', $input['min_margin_percent']);
        }

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'message' => 'Configurações globais salvas']);
    }

    /**
     * Obtém configurações globais
     */
    public function getGlobal(): void
    {
        if (!$this->userService->isAuthenticated()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['error' => 'Autenticação necessária']);
            return;
        }

        $accountId = $this->request->get('account_id') ?? \App\Helpers\SessionHelper::getActiveAccountId();

        if (!$accountId) {
            http_response_code(400);
            echo json_encode(['error' => 'Account ID required']);
            return;
        }

        $settings = new \App\Services\SettingsService((int)$accountId);
        $data = $settings->getAll();

        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'settings' => $data]);
    }

    /**
     * Página de gerenciamento de proxies
     */
    public function proxies(): void
    {
        if (!$this->userService->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        require __DIR__ . '/../Views/settings/proxies.php';
    }

    /**
     * Diagnóstico da conexão com Mercado Livre
     * Endpoint: GET /api/settings/ml-diagnostico
     */
    public function mlDiagnostico(): void
    {
        header('Content-Type: application/json');

        if (!$this->userService->isAuthenticated()) {
            http_response_code(401);
            echo json_encode(['error' => 'Não autorizado']);
            return;
        }

        $userId = $_SESSION['user_id'] ?? 0;
        $accountId = $this->request->get('account_id') ?? \App\Helpers\SessionHelper::getActiveAccountId();

        $result = [
            'timestamp' => date('Y-m-d H:i:s'),
            'user_id' => $userId,
            'diagnostics' => [],
        ];

        try {
            // Se um account_id específico foi passado, diagnosticar apenas ele
            if ($accountId) {
                $client = new \App\Services\MercadoLivreClient($accountId);
                $result['diagnostics'][$accountId] = $client->diagnose();
            } else {
                // Listar todas as contas do usuário e diagnosticar cada uma
                $accounts = \App\Helpers\SessionHelper::getUserAccounts();

                if (empty($accounts)) {
                    $result['status'] = 'no_accounts';
                    $result['message'] = 'Nenhuma conta do Mercado Livre conectada';
                    $result['action_required'] = 'Conecte sua conta do Mercado Livre nas configurações';

                    // Gerar URL de autorização
                    $authService = new \App\Services\MercadoLivreAuthService();
                    $result['auth_url'] = $authService->getAuthUrl($userId);
                } else {
                    foreach ($accounts as $acc) {
                        $accClient = new \App\Services\MercadoLivreClient($acc['id']);
                        $result['diagnostics'][$acc['id']] = $accClient->diagnose();
                        $result['diagnostics'][$acc['id']]['nickname'] = $acc['nickname'];
                    }
                }
            }

            // Resumo geral
            $connected = 0;
            $errors = 0;
            foreach ($result['diagnostics'] as $diag) {
                if (($diag['status'] ?? '') === 'connected') {
                    $connected++;
                } else {
                    $errors++;
                }
            }

            $result['summary'] = [
                'total_accounts' => count($result['diagnostics']),
                'connected' => $connected,
                'with_errors' => $errors,
            ];

            if ($connected > 0) {
                $result['data_source'] = 'mercadolivre_api';
                $result['message'] = "Conectado à API do Mercado Livre ({$connected} conta(s))";
            } elseif (!empty($result['diagnostics'])) {
                $result['data_source'] = 'local_cache';
                $result['message'] = 'Usando cache local - verifique os erros de conexão';
            }

            echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Erro ao executar diagnóstico',
                'message' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Força refresh do token ML
     * Endpoint: POST /api/settings/ml-refresh
     */
    public function mlRefresh(): void
    {
        header('Content-Type: application/json');

        if (!$this->userService->isAuthenticated()) {
            http_response_code(401);
            echo json_encode(['error' => 'Não autorizado']);
            return;
        }

        $input = json_decode(file_get_contents('php://input'), true);
        $accountId = $input['account_id'] ?? \App\Helpers\SessionHelper::getActiveAccountId();

        if (!$accountId) {
            http_response_code(400);
            echo json_encode(['error' => 'account_id é obrigatório']);
            return;
        }

        try {
            $authService = new \App\Services\MercadoLivreAuthService();
            $success = $authService->refreshToken((int)$accountId);

            if ($success) {
                // Diagnosticar após refresh
                $client = new \App\Services\MercadoLivreClient($accountId);
                $diagnosis = $client->diagnose();

                echo json_encode([
                    'success' => true,
                    'message' => 'Token renovado com sucesso',
                    'diagnosis' => $diagnosis,
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            } else {
                http_response_code(400);
                echo json_encode([
                    'success' => false,
                    'message' => 'Falha ao renovar token. Reautorize o app no Mercado Livre.',
                    'action_required' => 'reauthorize',
                ]);
            }
        } catch (\Throwable $e) {
            http_response_code(500);
            echo json_encode([
                'error' => 'Erro ao renovar token',
                'message' => $e->getMessage(),
            ]);
        }
    }
}
