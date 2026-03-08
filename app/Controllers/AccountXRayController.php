<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Services\AccountXRayService;
use App\Services\UserService;
use App\Services\MercadoLivreClient;
use App\Database;
use PDO;

/**
 * AccountXRayController — Raio X de Conta Mercado Livre
 *
 * Endpoints:
 *  GET  /dashboard/raio-x                    → dashboard HTML
 *  POST /api/xray/run                         → iniciar análise
 *  GET  /api/xray/results/{id}                → buscar resultado
 *  GET  /api/xray/list                        → listar relatórios
 *  GET  /api/xray/accounts                    → contas conectadas
 *  GET  /api/xray/item-scores/{report_id}     → scores por item
 */
class AccountXRayController
{
    private UserService $userService;
    private Request $request;

    public function __construct()
    {
        $this->userService = new UserService();
        $this->request     = new Request();
    }

    // ─────────────────────────────────────────────────────────
    // WEB: dashboard
    // ─────────────────────────────────────────────────────────

    public function index(): void
    {
        if (!$this->userService->isAuthenticated()) {
            header('Location: /login');
            exit;
        }

        $pageTitle   = 'Raio X — Diagnóstico de Conta';
        $currentPage = 'raio-x';

        ob_start();
        require __DIR__ . '/../Views/dashboard/account-xray.php';
        $content = ob_get_clean();

        require __DIR__ . '/../Views/layouts/modern/app.php';
    }

    // ─────────────────────────────────────────────────────────
    // API: listar contas conectadas
    // ─────────────────────────────────────────────────────────

    /**
     * GET /api/xray/accounts
     *
     * @return void
     */
    public function accounts(): void
    {
        $this->requireAuth();
        $this->jsonHeader();

        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                "SELECT a.id, a.ml_user_id AS seller_id, a.nickname, a.email,
                        a.status, a.last_synced_at, a.created_at,
                        r.id AS last_report_id,
                        r.score_overall AS last_score,
                        r.account_status AS last_account_status,
                        r.created_at AS last_report_at
                 FROM ml_accounts a
                 LEFT JOIN account_xray_reports r
                     ON r.account_id = a.id
                     AND r.id = (
                         SELECT id FROM account_xray_reports
                         WHERE account_id = a.id AND status = 'completed'
                         ORDER BY created_at DESC LIMIT 1
                     )
                 WHERE a.user_id = :user_id
                   AND a.status IN ('active', 'inactive', 'expired')
                 ORDER BY a.nickname ASC"
            );
            $stmt->execute(['user_id' => $this->userId()]);
            $accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode([
                'success'  => true,
                'accounts' => $accounts,
                'count'    => count($accounts),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            $this->error500($e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────
    // API: iniciar Raio X
    // ─────────────────────────────────────────────────────────

    /**
     * POST /api/xray/run
     *
     * Body: {
     *   "account_id": 1,
     *   "max_items": 200,
     *   "include_paused": true,
     *   "deep_seo": false,
     *   "include_financial": true
     * }
     */
    public function run(): void
    {
        $this->requireAuth();
        $this->jsonHeader();

        try {
            $input     = $this->jsonInput();
            $accountId = (int) ($input['account_id'] ?? 0);

            if ($accountId <= 0) {
                $this->error400('account_id obrigatório');
                return;
            }

            // Verificar se conta pertence ao usuário
            if (!$this->userOwnsAccount($accountId)) {
                $this->error403('Conta não encontrada ou sem permissão');
                return;
            }

            $options = [
                'max_items'         => min((int) ($input['max_items'] ?? 200), 500),
                'include_paused'    => (bool) ($input['include_paused'] ?? true),
                'deep_seo'          => (bool) ($input['deep_seo'] ?? false),
                'include_financial' => (bool) ($input['include_financial'] ?? true),
            ];

            $service = new AccountXRayService($accountId);
            $result  = $service->run($options);

            if (!$result['success']) {
                http_response_code(422);
                echo json_encode([
                    'success'   => false,
                    'error'     => $result['error'] ?? 'Falha na análise',
                    'report_id' => $result['report_id'] ?? null,
                ], JSON_UNESCAPED_UNICODE);
                return;
            }

            // Retornar resumo (não o relatório completo — pode ser muito grande)
            $report = $result['report'];
            echo json_encode([
                'success'    => true,
                'report_id'  => $result['report_id'],
                'summary'    => [
                    'score_overall'    => $report['score_overall'],
                    'account_status'   => $report['account_status'],
                    'nickname'         => $report['meta']['nickname'] ?? null,
                    'items_fetched'    => $report['meta']['items_fetched'],
                    'items_analyzed'   => $report['meta']['items_analyzed'],
                    'critical_issues'  => $report['diagnosis']['critical_count'] ?? 0,
                    'main_bottleneck'  => $report['diagnosis']['main_bottleneck'] ?? null,
                    'elapsed_ms'       => $report['meta']['elapsed_ms'],
                ],
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            $this->error500($e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────
    // API: buscar resultado completo
    // ─────────────────────────────────────────────────────────

    /**
     * GET /api/xray/results/{id}
     */
    public function results(int $id): void
    {
        $this->requireAuth();
        $this->jsonHeader();

        try {
            $accountId = $this->activeAccountId();
            if (!$accountId) {
                $this->error400('Nenhuma conta ML ativa na sessão');
                return;
            }

            $service = new AccountXRayService($accountId);
            $row     = $service->loadReport($id);

            if (!$row) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Relatório não encontrado'], JSON_UNESCAPED_UNICODE);
                return;
            }

            echo json_encode(['success' => true, 'report' => $row], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            $this->error500($e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────
    // API: listar relatórios
    // ─────────────────────────────────────────────────────────

    /**
     * GET /api/xray/list?account_id=1&limit=10
     */
    public function list(): void
    {
        $this->requireAuth();
        $this->jsonHeader();

        try {
            $accountId = (int) ($_GET['account_id'] ?? $this->activeAccountId() ?? 0);
            $limit     = min((int) ($_GET['limit'] ?? 10), 50);

            if ($accountId <= 0 || !$this->userOwnsAccount($accountId)) {
                $this->error400('account_id inválido');
                return;
            }

            $service = new AccountXRayService($accountId);
            $reports = $service->listReports($limit);

            echo json_encode([
                'success' => true,
                'reports' => $reports,
                'count'   => count($reports),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            $this->error500($e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────
    // API: scores por item de um relatório
    // ─────────────────────────────────────────────────────────

    /**
     * GET /api/xray/item-scores/{report_id}?sort=seo_score&order=asc&classification=FRACO
     */
    public function itemScores(int $reportId): void
    {
        $this->requireAuth();
        $this->jsonHeader();

        try {
            $db   = Database::getInstance();

            // Verificar que o relatório pertence ao usuário
            $stmt = $db->prepare(
                'SELECT r.id, r.account_id FROM account_xray_reports r
                 JOIN ml_accounts a ON r.account_id = a.id
                 WHERE r.id = :rid AND a.user_id = :uid LIMIT 1'
            );
            $stmt->execute(['rid' => $reportId, 'uid' => $this->userId()]);

            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Relatório não encontrado'], JSON_UNESCAPED_UNICODE);
                return;
            }

            $sort  = in_array($_GET['sort'] ?? '', ['seo_score', 'score_overall', 'classification', 'visits_30d'], true)
                ? ($_GET['sort'] ?? 'seo_score') : 'seo_score';
            $order = ($_GET['order'] ?? 'asc') === 'desc' ? 'DESC' : 'ASC';
            $cls   = $_GET['classification'] ?? null;

            $where = 'report_id = :rid';
            $params = ['rid' => $reportId];

            if ($cls !== null) {
                $where .= ' AND classification = :cls';
                $params['cls'] = $cls;
            }

            $stmt2 = $db->prepare(
                "SELECT * FROM xray_item_scores WHERE {$where} ORDER BY {$sort} {$order} LIMIT 200"
            );
            $stmt2->execute($params);
            $items = $stmt2->fetchAll(PDO::FETCH_ASSOC);

            // Decode JSON fields
            foreach ($items as &$item) {
                $item['missing_keywords'] = json_decode($item['missing_keywords_json'] ?? '[]', true) ?? [];
                $item['gap_keywords']     = json_decode($item['gap_keywords_json'] ?? '[]', true) ?? [];
                $item['actions']          = json_decode($item['actions_json'] ?? '[]', true) ?? [];
                unset($item['missing_keywords_json'], $item['gap_keywords_json'], $item['actions_json']);
            }
            unset($item);

            echo json_encode([
                'success' => true,
                'items'   => $items,
                'count'   => count($items),
            ], JSON_UNESCAPED_UNICODE);
        } catch (\Throwable $e) {
            $this->error500($e->getMessage());
        }
    }

    // ─────────────────────────────────────────────────────────
    // PRIVATE helpers
    // ─────────────────────────────────────────────────────────

    private function requireAuth(): void
    {
        if (!$this->userService->isAuthenticated()) {
            http_response_code(401);
            echo json_encode(['error' => 'Não autenticado'], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }

    private function jsonHeader(): void
    {
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-cache, no-store');
    }

    private function userId(): int
    {
        return (int) ($_SESSION['user_id'] ?? 0);
    }

    private function activeAccountId(): ?int
    {
        $id = isset($_SESSION['active_ml_account_id']) ? (int) $_SESSION['active_ml_account_id'] : null;
        return $id > 0 ? $id : null;
    }

    private function userOwnsAccount(int $accountId): bool
    {
        try {
            $db   = Database::getInstance();
            $stmt = $db->prepare(
                'SELECT id FROM ml_accounts WHERE id = :id AND user_id = :uid LIMIT 1'
            );
            $stmt->execute(['id' => $accountId, 'uid' => $this->userId()]);
            return (bool) $stmt->fetch();
        } catch (\Throwable) {
            return false;
        }
    }

    private function jsonInput(): array
    {
        $raw = file_get_contents('php://input');
        if (empty($raw)) {
            return [];
        }
        return json_decode($raw, true) ?? [];
    }

    private function error400(string $message): void
    {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    }

    private function error403(string $message): void
    {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => $message], JSON_UNESCAPED_UNICODE);
    }

    private function error500(string $message): void
    {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error'   => 'Erro interno',
        ], JSON_UNESCAPED_UNICODE);
    }
}
