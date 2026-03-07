<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Services\NotificationService;
use App\Helpers\SessionHelper;
use App\Database;

/**
 * Controller para Notificações
 */
class NotificationController extends BaseController
{
    private NotificationService $notificationService;
    private \PDO $db;

    public function __construct()
    {
        parent::__construct();
        $this->db = Database::getInstance();
        $accountId = SessionHelper::getActiveAccountId();
        $this->notificationService = new NotificationService($accountId);
    }

    /**
     * Lista notificações do usuário
     */
    public function index(): void
    {
        $this->requireAuth();
        $userId = $this->getUserId();

        $page = max(1, (int) ($this->request->getInt('page', 1)));
        $limit = (int) ($this->request->getInt('limit', 20));
        $limitSql = max(1, min($limit, 100));
        $offsetSql = max(0, ($page - 1) * $limitSql);
        $unreadOnly = $this->request->getBool('unread', false);

        try {
            $where = "user_id = :user_id";
            $params = ['user_id' => $userId];

            if ($unreadOnly) {
                $where .= " AND read_at IS NULL";
            }

            // Total
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM notifications WHERE {$where}");
            $stmt->execute($params);
            $total = $stmt->fetchColumn();

            // Notificações
            $stmt = $this->db->prepare("
                SELECT id, type, title, message, data, read_at, created_at
                FROM notifications
                WHERE {$where}
                ORDER BY created_at DESC
                LIMIT {$limitSql} OFFSET {$offsetSql}
            ");
            $stmt->execute($params);
            $notifications = $stmt->fetchAll(\PDO::FETCH_ASSOC);

            // Parse JSON data
            foreach ($notifications as &$n) {
                $n['data'] = $n['data'] ? json_decode($n['data'], true) : null;
                $n['is_read'] = $n['read_at'] !== null;
            }

            // Contar não lidas
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND read_at IS NULL");
            $stmt->execute(['user_id' => $userId]);
            $unreadCount = $stmt->fetchColumn();

            $this->json([
                'success' => true,
                'data' => $notifications,
                'pagination' => [
                    'total' => (int) $total,
                    'page' => $page,
                    'limit' => $limitSql,
                    'pages' => ceil($total / $limitSql),
                ],
                'unread_count' => (int) $unreadCount,
            ]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Conta notificações não lidas
     */
    public function unreadCount(): void
    {
        $this->requireAuth();
        $userId = $this->getUserId();

        try {
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND read_at IS NULL");
            $stmt->execute(['user_id' => $userId]);
            $count = $stmt->fetchColumn();

            $this->json([
                'success' => true,
                'count' => (int) $count,
            ]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Marca notificação como lida
     */
    public function markAsRead(string $id): void
    {
        $this->requireAuth();
        $userId = $this->getUserId();

        try {
            $stmt = $this->db->prepare("
                UPDATE notifications 
                SET read_at = NOW() 
                WHERE id = :id AND user_id = :user_id AND read_at IS NULL
            ");
            $stmt->execute(['id' => $id, 'user_id' => $userId]);

            $this->json([
                'success' => true,
                'message' => 'Notificação marcada como lida',
            ]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Marca todas como lidas
     */
    public function markAllAsRead(): void
    {
        $this->requireAuth();
        $userId = $this->getUserId();

        try {
            $stmt = $this->db->prepare("
                UPDATE notifications 
                SET read_at = NOW() 
                WHERE user_id = :user_id AND read_at IS NULL
            ");
            $stmt->execute(['user_id' => $userId]);
            $count = $stmt->rowCount();

            $this->json([
                'success' => true,
                'message' => "{$count} notificações marcadas como lidas",
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Exclui notificação
     */
    public function delete(string $id): void
    {
        $this->requireAuth();
        $userId = $this->getUserId();

        try {
            $stmt = $this->db->prepare("DELETE FROM notifications WHERE id = :id AND user_id = :user_id");
            $stmt->execute(['id' => $id, 'user_id' => $userId]);

            if ($stmt->rowCount() === 0) {
                $this->json(['error' => 'Notificação não encontrada'], 404);
                return;
            }

            $this->json([
                'success' => true,
                'message' => 'Notificação excluída',
            ]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Limpa notificações antigas
     */
    public function clearOld(): void
    {
        $this->requireAuth();
        $userId = $this->getUserId();
        $days = (int) ($this->request->getInt('days', 30));

        try {
            $stmt = $this->db->prepare("
                DELETE FROM notifications 
                WHERE user_id = :user_id 
                AND read_at IS NOT NULL 
                AND created_at < DATE_SUB(NOW(), INTERVAL :days DAY)
            ");
            $stmt->execute(['user_id' => $userId, 'days' => $days]);
            $count = $stmt->rowCount();

            $this->json([
                'success' => true,
                'message' => "{$count} notificações antigas removidas",
                'count' => $count,
            ]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Retorna preferências de notificação
     */
    public function preferences(): void
    {
        $this->requireAuth();
        $userId = $this->getUserId();

        try {
            $stmt = $this->db->prepare("SELECT notification_preferences FROM users WHERE id = :id");
            $stmt->execute(['id' => $userId]);
            $prefs = $stmt->fetchColumn();

            $this->json([
                'success' => true,
                'preferences' => $prefs ? json_decode($prefs, true) : $this->getDefaultPreferences(),
            ]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Atualiza preferências de notificação
     */
    public function updatePreferences(): void
    {
        $this->requireAuth();
        $userId = $this->getUserId();

        $input = $this->request->json();

        try {
            $stmt = $this->db->prepare("
                UPDATE users 
                SET notification_preferences = :prefs 
                WHERE id = :id
            ");
            $stmt->execute([
                'id' => $userId,
                'prefs' => json_encode($input),
            ]);

            $this->json([
                'success' => true,
                'message' => 'Preferências atualizadas',
            ]);
        } catch (\Exception $e) {
            $this->json(['error' => $e->getMessage()], 500);
        }
    }

    /**
     * Preferências padrão
     */
    private function getDefaultPreferences(): array
    {
        return [
            'email' => [
                'orders' => true,
                'questions' => true,
                'alerts' => true,
                'marketing' => false,
            ],
            'push' => [
                'orders' => true,
                'questions' => true,
                'alerts' => true,
            ],
            'telegram' => [
                'orders' => true,
                'questions' => true,
                'alerts' => true,
            ],
        ];
    }

    /**
     * Verifica autenticação
     */
    private function requireAuth(): void
    {
        if (!$this->getUserId()) {
            $this->json(['error' => 'Não autenticado'], 401);
            exit;
        }
    }

    /**
     * Retorna JSON
     */
    protected function json(array $data, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json');
        echo json_encode($data);
    }
}
