<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Services\AccountGovernanceService;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use PDO;

/**
 * AccountRecoveryApplierService
 *
 * Aplica automaticamente as ações do plano de recuperação gerado pelo Raio X:
 *  - PAUSAR: pausa itens classificados como TOXICO, POLUIDOR ou MORTO via ML API
 *  - OTIMIZAR_TITULO: injecta keywords faltantes no título via ML API
 *  - REPOR_ESTOQUE: sinaliza itens sem estoque para ação manual
 *  - OTIMIZAR_PRECO: sinaliza itens com baixa conversão para revisão
 *
 * Suporta dry-run mode (simulação sem alterações reais).
 */
class AccountRecoveryApplierService
{
    private const MAX_TITLES_TO_APPLY   = 20;   // máximo de títulos otimizados por execução
    private const MAX_PAUSES_PER_RUN    = 50;   // máximo de pausas por execução
    private const PAUSE_DELAY_MS        = 300;  // ms entre pausas para respeitar rate limit
    private const TITLE_DELAY_MS        = 400;  // ms entre updates de título
    private const TITLE_TARGET_LENGTH   = 58;   // comprimento alvo do título otimizado
    private const MIN_KEYWORD_LENGTH    = 3;    // keywords com menos chars são ignoradas

    private PDO $db;
    private Logger $logger;
    private MercadoLivreClient $mlClient;

    public function __construct()
    {
        $this->db     = Database::getInstance();
        $this->logger = new Logger('recovery-applier');
        $this->logger->pushHandler(
            new StreamHandler(
                __DIR__ . '/../../storage/logs/recovery-applier.log',
                Logger::DEBUG
            )
        );
    }

    // ─────────────────────────────────────────────────────────
    // PUBLIC API
    // ─────────────────────────────────────────────────────────

    /**
     * Aplica o plano de recuperação de um relatório de Raio X.
     *
     * @param int  $reportId  ID do relatório (account_xray_reports.id)
     * @param bool $dryRun    true = simula sem fazer alterações reais
     * @param array<string> $onlyActions  limita ações a aplicar (empty = todas)
     * @return array{
     *   dry_run: bool,
     *   report_id: int,
     *   account_id: int,
     *   actions_applied: int,
     *   actions_skipped: int,
     *   actions_failed: int,
     *   paused_items: array,
     *   optimized_titles: array,
     *   stock_alerts: array,
     *   price_alerts: array,
     *   errors: array,
     *   summary: string
     * }
     */
    public function applyRecoveryPlan(
        int $reportId,
        bool $dryRun = true,
        array $onlyActions = []
    ): array {
        $report = $this->loadReport($reportId);
        if ($report === null) {
            throw new \RuntimeException("Relatório #{$reportId} não encontrado.");
        }

        $accountId = (int) $report['account_id'];
        $this->mlClient = $this->buildMlClient($accountId);

        $this->logger->info('Iniciando aplicação de plano de recuperação', [
            'report_id'  => $reportId,
            'account_id' => $accountId,
            'dry_run'    => $dryRun,
            'only'       => $onlyActions,
        ]);

        $result = [
            'dry_run'           => $dryRun,
            'report_id'         => $reportId,
            'account_id'        => $accountId,
            'actions_applied'   => 0,
            'actions_skipped'   => 0,
            'actions_failed'    => 0,
            'paused_items'      => [],
            'optimized_titles'  => [],
            'stock_alerts'      => [],
            'price_alerts'      => [],
            'errors'            => [],
            'summary'           => '',
        ];

        $reportJson = json_decode($report['report_json'] ?? '{}', true) ?? [];

        // ── 1. Pausar itens tóxicos ──
        if ($this->shouldApply('PAUSAR', $onlyActions)) {
            $this->applyPauses($reportId, $reportJson, $dryRun, $result);
        }

        // ── 2. Otimizar títulos ──
        if ($this->shouldApply('OTIMIZAR_TITULO', $onlyActions)) {
            $this->applyTitleOptimizations($reportId, $reportJson, $dryRun, $result);
        }

        // ── 3. Alertas de estoque ──
        if ($this->shouldApply('REPOR_ESTOQUE', $onlyActions)) {
            $this->collectStockAlerts($reportId, $result);
        }

        // ── 4. Alertas de preço/conversão ──
        if ($this->shouldApply('OTIMIZAR_PRECO', $onlyActions)) {
            $this->collectPriceAlerts($reportId, $result);
        }

        // ── Salvar log de aplicação no DB ──
        if (!$dryRun) {
            $this->saveApplicationLog($reportId, $result);
        }

        $result['summary'] = $this->buildSummary($result);

        $this->logger->info('Plano aplicado', [
            'report_id' => $reportId,
            'applied'   => $result['actions_applied'],
            'failed'    => $result['actions_failed'],
            'dry_run'   => $dryRun,
        ]);

        return $result;
    }

    /**
     * Retorna histórico de aplicações de planos para uma conta.
     *
     * @return array<int, array>
     */
    public function getApplicationHistory(int $accountId): array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM xray_recovery_logs
             WHERE account_id = :account_id
             ORDER BY created_at DESC
             LIMIT 50'
        );
        $stmt->execute(['account_id' => $accountId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];
    }

    // ─────────────────────────────────────────────────────────
    // PRIVATE: PAUSE TOXIC ITEMS
    // ─────────────────────────────────────────────────────────

    /** @param array<string, mixed> $reportJson */
    private function applyPauses(
        int $reportId,
        array $reportJson,
        bool $dryRun,
        array &$result
    ): void {
        $pausableClasses = [
            AccountGovernanceService::CLASS_TOXICO,
            AccountGovernanceService::CLASS_POLUIDOR,
            AccountGovernanceService::CLASS_MORTO,
        ];

        // Buscar itens classificados como pausáveis no DB
        $placeholders = implode(',', array_fill(0, count($pausableClasses), '?'));
        $stmt = $this->db->prepare(
            "SELECT item_id, title, classification, score_overall
             FROM xray_item_scores
             WHERE report_id = ?
               AND classification IN ({$placeholders})
               AND score_overall < 30
             ORDER BY score_overall ASC
             LIMIT " . self::MAX_PAUSES_PER_RUN
        );
        $stmt->execute([$reportId, ...$pausableClasses]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($items)) {
            $this->logger->debug('Nenhum item para pausar encontrado', ['report_id' => $reportId]);
            return;
        }

        foreach ($items as $item) {
            $itemId = $item['item_id'];
            $cls    = $item['classification'];

            if ($dryRun) {
                $result['paused_items'][] = [
                    'item_id'        => $itemId,
                    'title'          => $item['title'],
                    'classification' => $cls,
                    'score'          => (int) $item['score_overall'],
                    'action'         => 'DRY_RUN — seria pausado',
                    'applied'        => false,
                ];
                $result['actions_skipped']++;
                continue;
            }

            try {
                $response = $this->mlClient->updateItem($itemId, ['status' => 'paused']);

                if ($response['success'] ?? false) {
                    $result['paused_items'][] = [
                        'item_id'        => $itemId,
                        'title'          => $item['title'],
                        'classification' => $cls,
                        'score'          => (int) $item['score_overall'],
                        'action'         => 'PAUSADO via API ML',
                        'applied'        => true,
                    ];
                    $result['actions_applied']++;
                } else {
                    $errMsg = $response['message'] ?? 'Erro desconhecido';
                    $result['errors'][] = "Falha ao pausar {$itemId}: {$errMsg}";
                    $result['paused_items'][] = [
                        'item_id'        => $itemId,
                        'title'          => $item['title'],
                        'classification' => $cls,
                        'score'          => (int) $item['score_overall'],
                        'action'         => "FALHA: {$errMsg}",
                        'applied'        => false,
                    ];
                    $result['actions_failed']++;
                }
            } catch (\Throwable $e) {
                $this->logger->error('Erro ao pausar item', [
                    'item_id' => $itemId,
                    'error'   => $e->getMessage(),
                ]);
                $result['errors'][] = "Exceção ao pausar {$itemId}: " . $e->getMessage();
                $result['actions_failed']++;
            }

            usleep(self::PAUSE_DELAY_MS * 1000);
        }
    }

    // ─────────────────────────────────────────────────────────
    // PRIVATE: TITLE OPTIMIZATION
    // ─────────────────────────────────────────────────────────

    /** @param array<string, mixed> $reportJson */
    private function applyTitleOptimizations(
        int $reportId,
        array $reportJson,
        bool $dryRun,
        array &$result
    ): void {
        // Pegar itens com SEO baixo que têm keywords faltando
        $stmt = $this->db->prepare(
            'SELECT item_id, title, score_seo, missing_keywords_json, gap_keywords_json
             FROM xray_item_scores
             WHERE report_id = ?
               AND score_seo < 60
               AND (missing_keywords_json IS NOT NULL AND missing_keywords_json != "[]")
             ORDER BY score_seo ASC
             LIMIT ' . self::MAX_TITLES_TO_APPLY
        );
        $stmt->execute([$reportId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        if (empty($items)) {
            return;
        }

        foreach ($items as $item) {
            $itemId          = $item['item_id'];
            $currentTitle    = $item['title'];
            $missingKeywords = json_decode($item['missing_keywords_json'] ?? '[]', true) ?? [];
            $gapKeywords     = json_decode($item['gap_keywords_json'] ?? '[]', true) ?? [];

            $allKeywords = array_unique(array_merge($missingKeywords, $gapKeywords));
            $allKeywords = array_filter($allKeywords, fn(string $kw): bool => mb_strlen($kw) >= self::MIN_KEYWORD_LENGTH);
            $allKeywords = array_values($allKeywords);

            if (empty($allKeywords)) {
                $result['actions_skipped']++;
                continue;
            }

            $optimizedTitle = $this->buildOptimizedTitle($currentTitle, $allKeywords);

            if ($optimizedTitle === $currentTitle) {
                $result['actions_skipped']++;
                continue;
            }

            if ($dryRun) {
                $result['optimized_titles'][] = [
                    'item_id'         => $itemId,
                    'original_title'  => $currentTitle,
                    'optimized_title' => $optimizedTitle,
                    'keywords_added'  => $this->extractAddedKeywords($currentTitle, $optimizedTitle),
                    'seo_score_before'=> (int) $item['score_seo'],
                    'action'          => 'DRY_RUN — seria atualizado',
                    'applied'         => false,
                ];
                $result['actions_skipped']++;
                continue;
            }

            try {
                $response = $this->mlClient->updateItem($itemId, ['title' => $optimizedTitle]);

                if ($response['success'] ?? false) {
                    $result['optimized_titles'][] = [
                        'item_id'         => $itemId,
                        'original_title'  => $currentTitle,
                        'optimized_title' => $optimizedTitle,
                        'keywords_added'  => $this->extractAddedKeywords($currentTitle, $optimizedTitle),
                        'seo_score_before'=> (int) $item['score_seo'],
                        'action'          => 'TÍTULO ATUALIZADO via API ML',
                        'applied'         => true,
                    ];
                    $result['actions_applied']++;
                } else {
                    $errMsg = $response['message'] ?? 'Erro desconhecido';
                    $result['errors'][] = "Falha ao otimizar título de {$itemId}: {$errMsg}";
                    $result['optimized_titles'][] = [
                        'item_id'         => $itemId,
                        'original_title'  => $currentTitle,
                        'optimized_title' => $optimizedTitle,
                        'action'          => "FALHA: {$errMsg}",
                        'applied'         => false,
                    ];
                    $result['actions_failed']++;
                }
            } catch (\Throwable $e) {
                $this->logger->error('Erro ao otimizar título', [
                    'item_id' => $itemId,
                    'error'   => $e->getMessage(),
                ]);
                $result['errors'][] = "Exceção ao otimizar {$itemId}: " . $e->getMessage();
                $result['actions_failed']++;
            }

            usleep(self::TITLE_DELAY_MS * 1000);
        }
    }

    // ─────────────────────────────────────────────────────────
    // PRIVATE: STOCK & PRICE ALERTS
    // ─────────────────────────────────────────────────────────

    private function collectStockAlerts(int $reportId, array &$result): void
    {
        $stmt = $this->db->prepare(
            "SELECT item_id, title, score_overall
             FROM xray_item_scores
             WHERE report_id = ?
               AND classification = ?
             ORDER BY score_overall ASC"
        );
        $stmt->execute([$reportId, AccountGovernanceService::CLASS_SEM_ESTOQUE]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($items as $item) {
            $result['stock_alerts'][] = [
                'item_id' => $item['item_id'],
                'title'   => $item['title'],
                'urgency' => 'CRITICA',
                'message' => 'Sem estoque — anúncio invisível. Repor imediatamente.',
            ];
            $result['actions_skipped']++;
        }
    }

    private function collectPriceAlerts(int $reportId, array &$result): void
    {
        // Buscar itens com ação OTIMIZAR_PRECO na actions_json
        $stmt = $this->db->prepare(
            "SELECT item_id, title, score_overall, actions_json
             FROM xray_item_scores
             WHERE report_id = ?
               AND actions_json LIKE '%OTIMIZAR_PRECO%'
             ORDER BY score_overall ASC
             LIMIT 30"
        );
        $stmt->execute([$reportId]);
        $items = $stmt->fetchAll(PDO::FETCH_ASSOC) ?: [];

        foreach ($items as $item) {
            $actions = json_decode($item['actions_json'] ?? '[]', true) ?? [];
            $priceAction = null;
            foreach ($actions as $action) {
                if (($action['type'] ?? '') === 'OTIMIZAR_PRECO') {
                    $priceAction = $action;
                    break;
                }
            }

            $result['price_alerts'][] = [
                'item_id' => $item['item_id'],
                'title'   => $item['title'],
                'urgency' => $priceAction['urgency'] ?? 'MEDIA',
                'message' => $priceAction['detail'] ?? 'Revisar preço e fotos.',
            ];
            $result['actions_skipped']++;
        }
    }

    // ─────────────────────────────────────────────────────────
    // PRIVATE: TITLE BUILDER
    // ─────────────────────────────────────────────────────────

    /**
     * Constrói título otimizado injetando keywords faltantes sem ultrapassar
     * o limite do ML (60 chars), respeitando a semântica original.
     *
     * Estratégia:
     *  1. Tenta adicionar cada keyword ao final com vírgula/espaço
     *  2. Se não cabe, tenta substituir palavras genéricas por keywords relevantes
     *  3. Garante que o resultado tenha entre 40-60 chars
     *
     * @param string[] $missingKeywords
     */
    private function buildOptimizedTitle(string $title, array $missingKeywords): string
    {
        $currentLen = mb_strlen($title);
        $optimized  = $title;

        // Palavras genéricas substituíveis
        $genericWords = ['acessório', 'peça', 'item', 'produto', 'compatível'];

        foreach ($missingKeywords as $kw) {
            $kw = trim($kw);
            if (mb_strlen($kw) < self::MIN_KEYWORD_LENGTH) {
                continue;
            }

            // Já contém a keyword? Skip
            if (mb_stripos($optimized, $kw) !== false) {
                continue;
            }

            $currentLen = mb_strlen($optimized);
            $kwLen      = mb_strlen($kw);

            // Cabe sem problema — adicionar ao final
            if ($currentLen + $kwLen + 1 <= self::TITLE_TARGET_LENGTH) {
                $optimized  = rtrim($optimized) . ' ' . $kw;
                continue;
            }

            // Tentar substituir palavra genérica
            foreach ($genericWords as $generic) {
                if (mb_stripos($optimized, $generic) !== false) {
                    $candidate = preg_replace(
                        '/' . preg_quote($generic, '/') . '/iu',
                        $kw,
                        $optimized,
                        1
                    );
                    if ($candidate !== null && mb_strlen($candidate) <= self::TITLE_TARGET_LENGTH) {
                        $optimized = $candidate;
                        break;
                    }
                }
            }
        }

        // Garantir comprimento máximo
        if (mb_strlen($optimized) > 60) {
            $optimized = mb_substr($optimized, 0, 57) . '...';
        }

        return $optimized;
    }

    /** @return string[] */
    private function extractAddedKeywords(string $original, string $optimized): array
    {
        $originalWords  = array_filter(explode(' ', mb_strtolower($original)));
        $optimizedWords = array_filter(explode(' ', mb_strtolower($optimized)));
        return array_values(array_diff($optimizedWords, $originalWords));
    }

    // ─────────────────────────────────────────────────────────
    // PRIVATE: PERSISTENCE & HELPERS
    // ─────────────────────────────────────────────────────────

    private function saveApplicationLog(int $reportId, array $result): void
    {
        try {
            // Criar tabela se não existir
            $this->db->exec(
                "CREATE TABLE IF NOT EXISTS xray_recovery_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    report_id INT NOT NULL,
                    account_id INT NOT NULL,
                    dry_run TINYINT(1) DEFAULT 0,
                    actions_applied INT DEFAULT 0,
                    actions_failed INT DEFAULT 0,
                    paused_count INT DEFAULT 0,
                    titles_count INT DEFAULT 0,
                    result_json MEDIUMTEXT,
                    summary TEXT,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    INDEX idx_account_id (account_id),
                    INDEX idx_report_id (report_id)
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
            );

            $stmt = $this->db->prepare(
                'INSERT INTO xray_recovery_logs
                 (report_id, account_id, dry_run, actions_applied, actions_failed,
                  paused_count, titles_count, result_json, summary)
                 VALUES
                 (:report_id, :account_id, :dry_run, :applied, :failed,
                  :paused, :titles, :result_json, :summary)'
            );
            $stmt->execute([
                'report_id'   => $reportId,
                'account_id'  => $result['account_id'],
                'dry_run'     => 0,
                'applied'     => $result['actions_applied'],
                'failed'      => $result['actions_failed'],
                'paused'      => count($result['paused_items']),
                'titles'      => count($result['optimized_titles']),
                'result_json' => json_encode($result),
                'summary'     => $result['summary'],
            ]);
        } catch (\Throwable $e) {
            $this->logger->error('Falha ao salvar log de aplicação', ['error' => $e->getMessage()]);
        }
    }

    private function loadReport(int $reportId): ?array
    {
        $stmt = $this->db->prepare(
            'SELECT * FROM account_xray_reports WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $reportId]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function buildMlClient(int $accountId): MercadoLivreClient
    {
        $stmt = $this->db->prepare(
            'SELECT access_token, refresh_token FROM ml_accounts WHERE id = :id LIMIT 1'
        );
        $stmt->execute(['id' => $accountId]);
        $account = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$account) {
            throw new \RuntimeException("Conta ML #{$accountId} não encontrada.");
        }

        return new MercadoLivreClient(
            $account['access_token'],
            $account['refresh_token']
        );
    }

    /** @param string[] $onlyActions */
    private function shouldApply(string $actionType, array $onlyActions): bool
    {
        return empty($onlyActions) || in_array($actionType, $onlyActions, true);
    }

    private function buildSummary(array $result): string
    {
        $parts = [];

        $paused   = count(array_filter($result['paused_items'], fn(array $i): bool => $i['applied']));
        $pausedDr = count(array_filter($result['paused_items'], fn(array $i): bool => !$i['applied']));
        $titles   = count(array_filter($result['optimized_titles'], fn(array $i): bool => $i['applied']));
        $titlesDr = count(array_filter($result['optimized_titles'], fn(array $i): bool => !$i['applied']));
        $stocks   = count($result['stock_alerts']);
        $prices   = count($result['price_alerts']);

        if ($result['dry_run']) {
            $parts[] = "🔍 DRY RUN — nenhuma alteração real foi feita.";
            if ($pausedDr > 0) {
                $parts[] = "  • {$pausedDr} itens SERIAM pausados (tóxicos/poluidores/mortos)";
            }
            if ($titlesDr > 0) {
                $parts[] = "  • {$titlesDr} títulos SERIAM otimizados com keywords faltantes";
            }
        } else {
            if ($paused > 0) {
                $parts[] = "✅ {$paused} itens pausados com sucesso";
            }
            if ($titles > 0) {
                $parts[] = "✅ {$titles} títulos otimizados com sucesso";
            }
            if ($result['actions_failed'] > 0) {
                $parts[] = "❌ {$result['actions_failed']} ações falharam — ver erros";
            }
        }

        if ($stocks > 0) {
            $parts[] = "⚠️  {$stocks} itens sem estoque precisam de reposição manual";
        }
        if ($prices > 0) {
            $parts[] = "💡 {$prices} itens com baixa conversão — revisar preço/fotos";
        }

        return implode("\n", $parts) ?: 'Nenhuma ação necessária para este relatório.';
    }
}
