<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use App\Services\SEO\VersioningService;
use App\Services\TechSheetSEOIntegrationService;
use PDO;
use Exception;

/**
 * 🚀 Bulk SEO Service
 * 
 * Implementa fluxo seguro de otimização em lote para Título e Descrição:
 * - Dry-run: gera sugestões e calcula impacto sem aplicar
 * - Diff/Preview: compara antes/depois com risk flags
 * - Batch Apply: aplica com rate limit, snapshots e rollback
 * - Relatório: resumo de aplicados, no-ops, falhas
 * 
 * Fluxo típico:
 * 1. dryRunBatch($itemIds) → retorna lista com preview/diff/risk
 * 2. Usuário revisa e aprova itens individuais
 * 3. applyBatch($approvedItems) → aplica com versionamento
 * 
 * @package App\Services
 */
class BulkSEOService
{
    private const MAX_ITEMS_PER_BATCH = 50;
    private const RATE_LIMIT_DELAY_MS = 200; // 200ms entre chamadas
    private const MAX_TITLE_LENGTH = 60;
    private const MIN_DESCRIPTION_LENGTH = 100;
    
    // Risk flags
    private const RISK_NONE = 'none';
    private const RISK_LOW = 'low';
    private const RISK_MEDIUM = 'medium';
    private const RISK_HIGH = 'high';

    private PDO $db;
    private int $accountId;
    private MercadoLivreClient $mlClient;
    private TechSheetSEOIntegrationService $seoService;
    private VersioningService $versioning;

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->mlClient = new MercadoLivreClient($accountId);
        $this->seoService = new TechSheetSEOIntegrationService($accountId);
        $this->versioning = new VersioningService($accountId);
    }

    /**
     * 🔍 Dry-run em lote: gera sugestões e preview sem aplicar
     * 
     * Para cada item:
     * - Obtém dados atuais do ML
     * - Gera sugestão de título otimizado
     * - Gera sugestão de descrição otimizada
     * - Calcula diff e risk flags
     * - Verifica se há mudança real (normalized compare)
     * 
     * @param string[] $itemIds Lista de item IDs
     * @param array $options Opções: optimize_title, optimize_description
     * @return array Resultado do dry-run com preview por item
     */
    public function dryRunBatch(array $itemIds, array $options = []): array
    {
        $itemIds = $this->sanitizeItemIds($itemIds);
        
        if (empty($itemIds)) {
            return [
                'success' => false,
                'error' => 'Nenhum item válido para processar',
            ];
        }

        if (count($itemIds) > self::MAX_ITEMS_PER_BATCH) {
            return [
                'success' => false,
                'error' => 'Limite excedido: máximo ' . self::MAX_ITEMS_PER_BATCH . ' itens por dry-run',
            ];
        }

        $optimizeTitle = (bool)($options['optimize_title'] ?? true);
        $optimizeDescription = (bool)($options['optimize_description'] ?? true);

        $results = [];
        $stats = [
            'total' => count($itemIds),
            'has_changes' => 0,
            'no_op' => 0,
            'errors' => 0,
            'high_risk' => 0,
            'medium_risk' => 0,
            'low_risk' => 0,
        ];

        foreach ($itemIds as $itemId) {
            $result = $this->dryRunSingleItem($itemId, $optimizeTitle, $optimizeDescription);
            $results[$itemId] = $result;

            if (!$result['success']) {
                $stats['errors']++;
                continue;
            }

            if ($result['has_changes']) {
                $stats['has_changes']++;
                
                $risk = $result['risk_level'] ?? self::RISK_NONE;
                switch ($risk) {
                    case self::RISK_HIGH:
                        $stats['high_risk']++;
                        break;
                    case self::RISK_MEDIUM:
                        $stats['medium_risk']++;
                        break;
                    case self::RISK_LOW:
                        $stats['low_risk']++;
                        break;
                }
            } else {
                $stats['no_op']++;
            }

            // Rate limit entre chamadas
            usleep(self::RATE_LIMIT_DELAY_MS * 1000);
        }

        return [
            'success' => true,
            'dry_run' => true,
            'stats' => $stats,
            'items' => $results,
            'options' => [
                'optimize_title' => $optimizeTitle,
                'optimize_description' => $optimizeDescription,
            ],
        ];
    }

    /**
     * Dry-run para um item individual
     */
    private function dryRunSingleItem(string $itemId, bool $optimizeTitle, bool $optimizeDescription): array
    {
        try {
            // Obter dados atuais do ML
            $item = $this->mlClient->get("/items/{$itemId}");
            
            if (!$item || isset($item['error']) || empty($item['id'])) {
                return [
                    'success' => false,
                    'error' => $item['message'] ?? $item['error'] ?? 'Item não encontrado',
                    'status' => 'error',
                ];
            }

            $currentTitle = trim((string)($item['title'] ?? ''));
            $currentDescription = $this->getItemDescription($itemId);

            $preview = [
                'item_id' => $itemId,
                'success' => true,
                'current' => [
                    'title' => $currentTitle,
                    'title_length' => mb_strlen($currentTitle),
                    'description_length' => mb_strlen($currentDescription),
                ],
                'suggested' => [
                    'title' => null,
                    'description' => null,
                ],
                'changes' => [
                    'title' => false,
                    'description' => false,
                ],
                'diffs' => [],
                'risks' => [],
                'has_changes' => false,
                'status' => 'no_op',
            ];

            // Gerar sugestão de título
            if ($optimizeTitle) {
                $titleResult = $this->seoService->optimizeTitle($itemId);
                
                if ($titleResult['success'] ?? false) {
                    $suggestedTitle = trim((string)($titleResult['optimized_title'] ?? ''));
                    $preview['suggested']['title'] = $suggestedTitle;
                    $preview['suggested']['title_length'] = mb_strlen($suggestedTitle);
                    $preview['suggested']['title_meta'] = [
                        'changes' => $titleResult['changes'] ?? [],
                        'weight_improvement' => $titleResult['weight_improvement'] ?? null,
                        'missing_coverage_types' => $titleResult['missing_coverage_types'] ?? [],
                    ];

                    // Verificar se há mudança real
                    $titleChanged = $this->hasRealChange($currentTitle, $suggestedTitle);
                    $preview['changes']['title'] = $titleChanged;

                    if ($titleChanged) {
                        $preview['diffs']['title'] = $this->generateTextDiff($currentTitle, $suggestedTitle);
                        $titleRisks = $this->assessTitleRisks($currentTitle, $suggestedTitle);
                        $preview['risks']['title'] = $titleRisks;
                    }
                } else {
                    $preview['suggested']['title_error'] = $titleResult['error'] ?? 'Falha ao gerar sugestão';
                }
            }

            // Gerar sugestão de descrição
            if ($optimizeDescription) {
                $descResult = $this->seoService->optimizeDescription($itemId);
                
                if ($descResult['success'] ?? false) {
                    $suggestedDescription = trim((string)($descResult['optimized_description'] ?? ''));
                    $preview['suggested']['description'] = $suggestedDescription;
                    $preview['suggested']['description_length'] = mb_strlen($suggestedDescription);
                    $preview['suggested']['description_meta'] = [
                        'faqs_added' => $descResult['faqs_added'] ?? 0,
                        'keywords_injected' => $descResult['keywords_injected'] ?? 0,
                        'density_before' => $descResult['density_before'] ?? 0,
                    ];

                    // Verificar se há mudança real
                    $descChanged = $this->hasRealChange($currentDescription, $suggestedDescription);
                    $preview['changes']['description'] = $descChanged;

                    if ($descChanged) {
                        $preview['diffs']['description'] = $this->generateDescriptionDiff(
                            $currentDescription,
                            $suggestedDescription
                        );
                        $descRisks = $this->assessDescriptionRisks($currentDescription, $suggestedDescription);
                        $preview['risks']['description'] = $descRisks;
                    }
                } else {
                    $preview['suggested']['description_error'] = $descResult['error'] ?? 'Falha ao gerar sugestão';
                }
            }

            // Consolidar status
            $hasChanges = $preview['changes']['title'] || $preview['changes']['description'];
            $preview['has_changes'] = $hasChanges;
            $preview['status'] = $hasChanges ? 'pending' : 'no_op';

            // Calcular risk level consolidado
            $preview['risk_level'] = $this->calculateOverallRisk($preview['risks']);
            $preview['risk_summary'] = $this->getRiskSummary($preview['risks']);

            return $preview;

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
                'status' => 'error',
            ];
        }
    }

    /**
     * ✅ Aplica otimizações em lote após revisão
     * 
     * @param array $approvedItems Array de itens aprovados com formato:
     *   [
     *     'item_id' => 'MLB123',
     *     'apply_title' => true,
     *     'apply_description' => false,
     *     'title' => 'Título otimizado...',
     *     'description' => 'Descrição otimizada...',
     *   ]
     * @param int $userId ID do usuário que está aplicando
     * @param array $meta Metadados adicionais (reason, strategy, etc)
     * @return array Resultado da aplicação em lote
     */
    public function applyBatch(array $approvedItems, int $userId, array $meta = []): array
    {
        if (empty($approvedItems)) {
            return [
                'success' => false,
                'error' => 'Nenhum item aprovado para aplicar',
            ];
        }

        if (count($approvedItems) > self::MAX_ITEMS_PER_BATCH) {
            return [
                'success' => false,
                'error' => 'Limite excedido: máximo ' . self::MAX_ITEMS_PER_BATCH . ' itens por batch',
            ];
        }

        $results = [];
        $stats = [
            'total' => count($approvedItems),
            'titles_applied' => 0,
            'descriptions_applied' => 0,
            'no_op' => 0,
            'errors' => 0,
            'version_ids' => [],
        ];
        $failures = [];

        foreach ($approvedItems as $approvedItem) {
            $itemId = (string)($approvedItem['item_id'] ?? '');
            if ($itemId === '') {
                continue;
            }

            $applyTitle = (bool)($approvedItem['apply_title'] ?? false);
            $applyDescription = (bool)($approvedItem['apply_description'] ?? false);

            if (!$applyTitle && !$applyDescription) {
                $results[$itemId] = [
                    'success' => true,
                    'status' => 'no_op',
                    'message' => 'Nenhuma alteração selecionada',
                ];
                $stats['no_op']++;
                continue;
            }

            $result = $this->applySingleItem(
                $itemId,
                $applyTitle ? (string)($approvedItem['title'] ?? '') : null,
                $applyDescription ? (string)($approvedItem['description'] ?? '') : null,
                $userId,
                $meta
            );

            $results[$itemId] = $result;

            if (!$result['success']) {
                $stats['errors']++;
                $failures[] = [
                    'item_id' => $itemId,
                    'error' => $result['error'] ?? 'Erro desconhecido',
                ];
            } else {
                if ($result['title_applied'] ?? false) {
                    $stats['titles_applied']++;
                }
                if ($result['description_applied'] ?? false) {
                    $stats['descriptions_applied']++;
                }
                if (isset($result['version_ids'])) {
                    $stats['version_ids'] = array_merge($stats['version_ids'], $result['version_ids']);
                }
            }

            // Rate limit entre chamadas à API
            usleep(self::RATE_LIMIT_DELAY_MS * 1000);
        }

        return [
            'success' => true,
            'stats' => $stats,
            'items' => $results,
            'failures' => $failures,
            'message' => $this->generateSummaryMessage($stats),
        ];
    }

    /**
     * Aplica otimização em um item individual
     */
    private function applySingleItem(
        string $itemId,
        ?string $title,
        ?string $description,
        int $userId,
        array $meta
    ): array {
        $result = [
            'success' => true,
            'item_id' => $itemId,
            'title_applied' => false,
            'description_applied' => false,
            'version_ids' => [],
        ];

        try {
            // Aplicar título
            if ($title !== null && $title !== '') {
                $titleResult = $this->seoService->applyOptimizedTitle($itemId, $title, $userId, $meta);
                
                if ($titleResult['success'] ?? false) {
                    $result['title_applied'] = true;
                    if (isset($titleResult['version_id'])) {
                        $result['version_ids'][] = $titleResult['version_id'];
                    }
                } else {
                    $result['success'] = false;
                    $result['error'] = $titleResult['error'] ?? 'Falha ao aplicar título';
                    return $result;
                }
            }

            // Aplicar descrição
            if ($description !== null && $description !== '') {
                $descResult = $this->seoService->applyOptimizedDescription($itemId, $description, $userId, $meta);
                
                if ($descResult['success'] ?? false) {
                    $result['description_applied'] = true;
                    if (isset($descResult['version_id'])) {
                        $result['version_ids'][] = $descResult['version_id'];
                    }
                } else {
                    // Se título foi aplicado mas descrição falhou, marcar como parcial
                    if ($result['title_applied']) {
                        $result['status'] = 'partial';
                        $result['description_error'] = $descResult['error'] ?? 'Falha ao aplicar descrição';
                    } else {
                        $result['success'] = false;
                        $result['error'] = $descResult['error'] ?? 'Falha ao aplicar descrição';
                    }
                }
            }

            $result['status'] = $result['success'] ? 'applied' : 'error';

        } catch (Exception $e) {
            $result['success'] = false;
            $result['error'] = $e->getMessage();
            $result['status'] = 'error';
        }

        return $result;
    }

    /**
     * Verifica se há mudança real entre dois textos (normalizado)
     */
    private function hasRealChange(string $before, string $after): bool
    {
        $normalizedBefore = $this->normalizeForComparison($before);
        $normalizedAfter = $this->normalizeForComparison($after);
        
        return $normalizedBefore !== $normalizedAfter;
    }

    /**
     * Normaliza texto para comparação
     */
    private function normalizeForComparison(string $text): string
    {
        // Normaliza espaços, case e pontuação para comparação
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/\s+/', ' ', $text) ?? $text;
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', '', $text) ?? $text;
        return trim($text);
    }

    /**
     * Gera diff legível entre dois textos
     */
    private function generateTextDiff(string $before, string $after): array
    {
        $beforeWords = preg_split('/\s+/', $before);
        $afterWords = preg_split('/\s+/', $after);

        $beforeWords = is_array($beforeWords) ? $beforeWords : [];
        $afterWords = is_array($afterWords) ? $afterWords : [];

        $removed = array_diff($beforeWords, $afterWords);
        $added = array_diff($afterWords, $beforeWords);

        return [
            'before' => $before,
            'after' => $after,
            'before_length' => mb_strlen($before),
            'after_length' => mb_strlen($after),
            'length_change' => mb_strlen($after) - mb_strlen($before),
            'words_removed' => array_values($removed),
            'words_added' => array_values($added),
            'similarity' => $this->calculateSimilarity($before, $after),
        ];
    }

    /**
     * Gera diff para descrição (resumido)
     */
    private function generateDescriptionDiff(string $before, string $after): array
    {
        $beforeLen = mb_strlen($before);
        $afterLen = mb_strlen($after);

        return [
            'before_length' => $beforeLen,
            'after_length' => $afterLen,
            'length_change' => $afterLen - $beforeLen,
            'length_change_percent' => $beforeLen > 0 
                ? round((($afterLen - $beforeLen) / $beforeLen) * 100, 1) 
                : 100,
            'before_preview' => mb_substr($before, 0, 200) . ($beforeLen > 200 ? '...' : ''),
            'after_preview' => mb_substr($after, 0, 200) . ($afterLen > 200 ? '...' : ''),
            'similarity' => $this->calculateSimilarity($before, $after),
        ];
    }

    /**
     * Calcula similaridade entre dois textos (0-100)
     */
    private function calculateSimilarity(string $a, string $b): float
    {
        if ($a === '' && $b === '') {
            return 100.0;
        }
        if ($a === '' || $b === '') {
            return 0.0;
        }

        similar_text($a, $b, $percent);
        return round($percent, 1);
    }

    /**
     * Avalia riscos de mudança no título
     */
    private function assessTitleRisks(string $before, string $after): array
    {
        $risks = [];
        $beforeLen = mb_strlen($before);
        $afterLen = mb_strlen($after);

        // Risk: título muito longo
        if ($afterLen > self::MAX_TITLE_LENGTH) {
            $risks[] = [
                'type' => 'title_too_long',
                'level' => self::RISK_HIGH,
                'message' => "Título excede {$afterLen}/{" . self::MAX_TITLE_LENGTH . "} caracteres",
            ];
        }

        // Risk: mudança muito grande (> 50%)
        $similarity = $this->calculateSimilarity($before, $after);
        if ($similarity < 50) {
            $risks[] = [
                'type' => 'major_change',
                'level' => self::RISK_MEDIUM,
                'message' => "Mudança significativa (similaridade: {$similarity}%)",
            ];
        }

        // Risk: redução brusca de tamanho (> 30%)
        if ($beforeLen > 0 && $afterLen < $beforeLen * 0.7) {
            $reduction = round((($beforeLen - $afterLen) / $beforeLen) * 100, 1);
            $risks[] = [
                'type' => 'length_reduction',
                'level' => self::RISK_MEDIUM,
                'message' => "Redução de {$reduction}% no tamanho do título",
            ];
        }

        // Risk: título muito curto
        if ($afterLen < 20) {
            $risks[] = [
                'type' => 'title_too_short',
                'level' => self::RISK_LOW,
                'message' => "Título muito curto ({$afterLen} caracteres)",
            ];
        }

        return $risks;
    }

    /**
     * Avalia riscos de mudança na descrição
     */
    private function assessDescriptionRisks(string $before, string $after): array
    {
        $risks = [];
        $beforeLen = mb_strlen($before);
        $afterLen = mb_strlen($after);

        // Risk: descrição muito curta
        if ($afterLen < self::MIN_DESCRIPTION_LENGTH) {
            $risks[] = [
                'type' => 'description_too_short',
                'level' => self::RISK_MEDIUM,
                'message' => "Descrição curta ({$afterLen} caracteres, mínimo recomendado: " . self::MIN_DESCRIPTION_LENGTH . ")",
            ];
        }

        // Risk: redução brusca (> 40%)
        if ($beforeLen > 0 && $afterLen < $beforeLen * 0.6) {
            $reduction = round((($beforeLen - $afterLen) / $beforeLen) * 100, 1);
            $risks[] = [
                'type' => 'length_reduction',
                'level' => self::RISK_HIGH,
                'message' => "Redução de {$reduction}% no tamanho da descrição",
            ];
        }

        // Risk: aumento muito grande (> 200%)
        if ($beforeLen > 0 && $afterLen > $beforeLen * 3) {
            $increase = round((($afterLen - $beforeLen) / $beforeLen) * 100, 1);
            $risks[] = [
                'type' => 'major_increase',
                'level' => self::RISK_LOW,
                'message' => "Aumento de {$increase}% no tamanho (verifique qualidade)",
            ];
        }

        return $risks;
    }

    /**
     * Calcula risk level geral baseado nos risks individuais
     */
    private function calculateOverallRisk(array $allRisks): string
    {
        $levels = [
            self::RISK_HIGH => 0,
            self::RISK_MEDIUM => 0,
            self::RISK_LOW => 0,
        ];

        foreach ($allRisks as $fieldRisks) {
            if (!is_array($fieldRisks)) {
                continue;
            }
            foreach ($fieldRisks as $risk) {
                $level = $risk['level'] ?? self::RISK_NONE;
                if (isset($levels[$level])) {
                    $levels[$level]++;
                }
            }
        }

        if ($levels[self::RISK_HIGH] > 0) {
            return self::RISK_HIGH;
        }
        if ($levels[self::RISK_MEDIUM] > 0) {
            return self::RISK_MEDIUM;
        }
        if ($levels[self::RISK_LOW] > 0) {
            return self::RISK_LOW;
        }
        return self::RISK_NONE;
    }

    /**
     * Gera resumo de riscos legível
     */
    private function getRiskSummary(array $allRisks): string
    {
        $messages = [];
        
        foreach ($allRisks as $field => $risks) {
            if (!is_array($risks)) {
                continue;
            }
            foreach ($risks as $risk) {
                $messages[] = ucfirst($field) . ': ' . ($risk['message'] ?? 'Risco detectado');
            }
        }

        return empty($messages) ? 'Sem riscos detectados' : implode('; ', $messages);
    }

    /**
     * Gera mensagem de resumo para o resultado do batch
     */
    private function generateSummaryMessage(array $stats): string
    {
        $parts = [];

        $applied = $stats['titles_applied'] + $stats['descriptions_applied'];
        if ($applied > 0) {
            $parts[] = "✅ {$applied} alterações aplicadas";
            if ($stats['titles_applied'] > 0) {
                $parts[] = "({$stats['titles_applied']} títulos";
            }
            if ($stats['descriptions_applied'] > 0) {
                $parts[] = ($stats['titles_applied'] > 0 ? ', ' : '(') . "{$stats['descriptions_applied']} descrições)";
            } else {
                $parts[] = ')';
            }
        }

        if ($stats['no_op'] > 0) {
            $parts[] = "⏭️ {$stats['no_op']} sem alteração";
        }

        if ($stats['errors'] > 0) {
            $parts[] = "❌ {$stats['errors']} erros";
        }

        return implode(' | ', $parts) ?: 'Nenhuma alteração processada';
    }

    /**
     * 🛡️ Interpreta erros da API do ML de forma amigável
     * 
     * Erros comuns:
     * - 400: Payload inválido
     * - 401: Token expirado/inválido
     * - 403: Sem permissão (item não é seu)
     * - 404: Item não encontrado
     * - 429: Rate limit
     * - 500+: Erro interno ML
     */
    private function interpretMLError(array $response, string $context = ''): string
    {
        $status = (int)($response['status'] ?? 0);
        $message = $response['message'] ?? $response['error'] ?? '';
        $cause = $response['cause'] ?? [];
        
        // Erros específicos conhecidos
        if (isset($response['error']) && $response['error'] === 'forbidden') {
            return 'Sem permissão para editar este item (pode não ser seu)';
        }
        
        if (isset($response['error']) && $response['error'] === 'not_found') {
            return 'Item não encontrado no Mercado Livre';
        }

        if (is_array($cause) && !empty($cause)) {
            // Extrair mensagens das causas
            $causeMessages = [];
            foreach ($cause as $c) {
                if (isset($c['message'])) {
                    $causeMessages[] = $c['message'];
                } elseif (isset($c['code'])) {
                    $causeMessages[] = $c['code'];
                }
            }
            if (!empty($causeMessages)) {
                return implode('; ', $causeMessages);
            }
        }

        // Por status HTTP
        switch ($status) {
            case 400:
                return 'Dados inválidos: ' . ($message ?: 'verifique os campos');
            case 401:
                return 'Token expirado ou inválido - reconecte a conta';
            case 403:
                return 'Sem permissão para esta operação';
            case 404:
                return 'Recurso não encontrado';
            case 429:
                return 'Limite de requisições atingido - aguarde alguns segundos';
            case 500:
            case 502:
            case 503:
                return 'Erro temporário do Mercado Livre - tente novamente';
        }

        // Fallback
        return $message ?: "Erro desconhecido ({$context})";
    }

    /**
     * 🔄 Verifica se um erro é recuperável (pode tentar novamente)
     */
    private function isRetryableError(array $response): bool
    {
        $status = (int)($response['status'] ?? 0);
        $error = $response['error'] ?? '';

        // Rate limit e erros de servidor são recuperáveis
        if (in_array($status, [429, 500, 502, 503, 504])) {
            return true;
        }

        // Alguns erros específicos
        if ($error === 'temporarily_unavailable') {
            return true;
        }

        return false;
    }

    /**
     * Sanitiza lista de item IDs
     */
    private function sanitizeItemIds(array $itemIds): array
    {
        $itemIds = array_filter(array_map('strval', $itemIds), fn($id) => $id !== '');
        return array_values(array_unique($itemIds));
    }

    /**
     * Obtém descrição plain text do item
     */
    private function getItemDescription(string $itemId): string
    {
        try {
            $desc = $this->mlClient->get("/items/{$itemId}/description");
            return is_array($desc) ? trim((string)($desc['plain_text'] ?? '')) : '';
        } catch (Exception $e) {
            return '';
        }
    }

    /**
     * � Inicia job assíncrono para aplicação em lote
     * 
     * @param array $approvedItems Itens aprovados para aplicar
     * @param int $userId ID do usuário
     * @param array $meta Metadados
     * @return array Job info com ID para polling
     */
    public function startBatchJob(array $approvedItems, int $userId, array $meta = []): array
    {
        if (empty($approvedItems)) {
            return ['success' => false, 'error' => 'Nenhum item aprovado'];
        }

        $jobId = 'bulk_seo_' . uniqid() . '_' . time();
        
        // Preparar dados do job
        $jobData = [
            'id' => $jobId,
            'type' => 'bulk_seo_apply',
            'account_id' => $this->accountId,
            'user_id' => $userId,
            'status' => 'pending',
            'total_items' => count($approvedItems),
            'processed_items' => 0,
            'successful_items' => 0,
            'failed_items' => 0,
            'items' => $approvedItems,
            'meta' => $meta,
            'created_at' => date('Y-m-d H:i:s'),
            'started_at' => null,
            'completed_at' => null,
            'results' => [],
        ];

        // Salvar job no banco
        $stmt = $this->db->prepare("
            INSERT INTO bulk_seo_jobs (
                job_id, account_id, user_id, status, total_items, 
                job_data, created_at
            ) VALUES (
                :job_id, :account_id, :user_id, :status, :total_items,
                :job_data, NOW()
            )
        ");

        // Verificar se tabela existe antes de inserir
        $this->assertBulkJobsTableExists();
        
        $stmt->execute([
            'job_id' => $jobId,
            'account_id' => $this->accountId,
            'user_id' => $userId,
            'status' => 'pending',
            'total_items' => count($approvedItems),
            'job_data' => json_encode($jobData),
        ]);

        // Para lotes pequenos, executa síncrono
        if (count($approvedItems) <= 10) {
            $result = $this->processJobSync($jobId, $approvedItems, $userId, $meta);
            return [
                'success' => true,
                'job_id' => $jobId,
                'status' => 'completed',
                'executed_sync' => true,
                'result' => $result,
            ];
        }

        // Para lotes grandes, dispara processamento em background
        $dispatchResult = $this->dispatchBackgroundJob($jobId);

        // Retornar status real do banco (não assumir)
        $currentStatus = $this->getJobStatusFromDb($jobId);

        return [
            'success' => true,
            'job_id' => $jobId,
            'status' => $currentStatus,
            'dispatch_method' => $dispatchResult['method'] ?? 'unknown',
            'message' => 'Job iniciado. Use GET /bulk/job/{jobId}/status para acompanhar.',
        ];
    }

    /**
     * Processa job de forma síncrona
     */
    private function processJobSync(string $jobId, array $items, int $userId, array $meta): array
    {
        $this->updateJobStatus($jobId, 'processing', ['started_at' => date('Y-m-d H:i:s')]);
        
        $result = $this->applyBatch($items, $userId, $meta);
        
        // Calcular successful_items corretamente (soma de títulos e descrições aplicados)
        $titlesApplied = $result['stats']['titles_applied'] ?? 0;
        $descriptionsApplied = $result['stats']['descriptions_applied'] ?? 0;
        $successfulItems = ($titlesApplied > 0 || $descriptionsApplied > 0) 
            ? count($items) - ($result['stats']['errors'] ?? 0) - ($result['stats']['no_op'] ?? 0)
            : 0;
        
        $this->updateJobStatus($jobId, 'completed', [
            'completed_at' => date('Y-m-d H:i:s'),
            'processed_items' => count($items),
            'successful_items' => $successfulItems,
            'failed_items' => $result['stats']['errors'] ?? 0,
            'results' => $result,
        ]);

        return $result;
    }

    /**
     * Dispara job em background
     * 
     * @return array Informações sobre o dispatch (method, success)
     */
    private function dispatchBackgroundJob(string $jobId): array
    {
        // Tenta usar o worker do sistema
        $workerPath = realpath(__DIR__ . '/../../bin/bulk-seo-worker.php');
        
        if ($workerPath && file_exists($workerPath) && is_readable($workerPath)) {
            // Detectar PHP binary
            $phpBinary = PHP_BINARY ?: 'php';
            
            // Construir comando com log
            $logPath = realpath(__DIR__ . '/../../storage/logs') ?: '/tmp';
            $logFile = $logPath . '/bulk-seo-worker-' . date('Y-m-d') . '.log';
            
            // Usar nohup para garantir que o processo continue após desconexão
            $cmd = sprintf(
                'nohup %s %s --job=%s >> %s 2>&1 &',
                escapeshellarg($phpBinary),
                escapeshellarg($workerPath),
                escapeshellarg($jobId),
                escapeshellarg($logFile)
            );
            
            // Executar em background
            if (function_exists('exec') && !$this->isExecDisabled()) {
                exec($cmd);
                // Marcar como queued - o worker vai mudar para processing quando pegar
                $this->updateJobStatus($jobId, 'queued');
                return ['method' => 'exec', 'success' => true];
            }
            
            // Fallback com shell_exec
            if (function_exists('shell_exec') && !$this->isShellExecDisabled()) {
                shell_exec($cmd);
                $this->updateJobStatus($jobId, 'queued');
                return ['method' => 'shell_exec', 'success' => true];
            }
            
            // Fallback com popen
            if (function_exists('popen')) {
                $handle = @popen($cmd, 'r');
                if ($handle) {
                    pclose($handle);
                    $this->updateJobStatus($jobId, 'queued');
                    return ['method' => 'popen', 'success' => true];
                }
            }
        }
        
        // Fallback: marca para processamento pelo cron
        $this->updateJobStatus($jobId, 'queued');
        return ['method' => 'cron_fallback', 'success' => false];
    }
    
    /**
     * Verifica se exec está desabilitado
     */
    private function isExecDisabled(): bool
    {
        $disabled = explode(',', ini_get('disable_functions'));
        return in_array('exec', array_map('trim', $disabled));
    }
    
    /**
     * Verifica se shell_exec está desabilitado
     */
    private function isShellExecDisabled(): bool
    {
        $disabled = explode(',', ini_get('disable_functions'));
        return in_array('shell_exec', array_map('trim', $disabled));
    }
    
    /**
     * Obtém status atual do job do banco
     */
    private function getJobStatusFromDb(string $jobId): string
    {
        $stmt = $this->db->prepare("SELECT status FROM bulk_seo_jobs WHERE job_id = :job_id");
        $stmt->execute(['job_id' => $jobId]);
        return $stmt->fetchColumn() ?: 'pending';
    }

    /**
     * Atualiza status do job
     */
    private function updateJobStatus(string $jobId, string $status, array $extra = []): void
    {
        $updates = ['status' => $status];
        $updates = array_merge($updates, $extra);
        
        $setParts = [];
        $params = ['job_id' => $jobId];
        
        foreach ($updates as $key => $value) {
            if ($key === 'results' || $key === 'job_data') {
                $setParts[] = "{$key} = :{$key}";
                $params[$key] = json_encode($value);
            } else {
                $setParts[] = "{$key} = :{$key}";
                $params[$key] = $value;
            }
        }

        $setClause = implode(', ', $setParts);
        
        $stmt = $this->db->prepare("
            UPDATE bulk_seo_jobs
            SET {$setClause}, updated_at = NOW()
            WHERE job_id = :job_id
        ");
        $stmt->execute($params);
    }

    /**
     * Verifica se a tabela bulk_seo_jobs existe
     * 
     * @throws \RuntimeException Se a tabela não existir
     */
    private function assertBulkJobsTableExists(): void
    {
        static $checked = false;
        if ($checked) {
            return;
        }
        
        try {
            $stmt = $this->db->query("SELECT 1 FROM bulk_seo_jobs LIMIT 1");
            $checked = true;
        } catch (\PDOException $e) {
            throw new \RuntimeException(
                'Tabela bulk_seo_jobs não encontrada. Execute as migrations: ' .
                'php bin/apply-migrations.php ou importe database/migrations/2026_01_30_create_bulk_seo_jobs.sql',
                0,
                $e
            );
        }
    }

    /**
     * 📊 Obtém status de um job específico
     */
    public function getJobStatus(string $jobId): array
    {
        $this->assertBulkJobsTableExists();

        $stmt = $this->db->prepare("
            SELECT 
                job_id, status, total_items, processed_items,
                successful_items, failed_items, created_at,
                started_at, completed_at, results
            FROM bulk_seo_jobs
            WHERE job_id = :job_id AND account_id = :account_id
        ");
        $stmt->execute([
            'job_id' => $jobId,
            'account_id' => $this->accountId,
        ]);
        $job = $stmt->fetch(\PDO::FETCH_ASSOC);

        if (!$job) {
            return ['success' => false, 'error' => 'Job não encontrado'];
        }

        // Decodificar resultados se existirem
        if (!empty($job['results'])) {
            $job['results'] = json_decode($job['results'], true);
        }

        return array_merge(['success' => true], $job);
    }

    /**
     * 📜 Obtém histórico de operações Bulk SEO
     */
    public function getBulkHistory(int $limit = 50, int $offset = 0): array
    {
        $this->assertBulkJobsTableExists();

        $limitSql = max(1, min((int)$limit, 200));
        $offsetSql = max(0, (int)$offset);
        
        $stmt = $this->db->prepare("
            SELECT 
                job_id, status, total_items, processed_items,
                successful_items, failed_items, created_at, 
                started_at, completed_at
            FROM bulk_seo_jobs
            WHERE account_id = :account_id
            ORDER BY created_at DESC
            LIMIT {$limitSql} OFFSET {$offsetSql}
        ");
        $stmt->bindValue(':account_id', $this->accountId, PDO::PARAM_INT);
        $stmt->execute();

        $jobs = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Contar total
        $countStmt = $this->db->prepare("
            SELECT COUNT(*) FROM bulk_seo_jobs WHERE account_id = :account_id
        ");
        $countStmt->execute(['account_id' => $this->accountId]);
        $total = (int)$countStmt->fetchColumn();

        return [
            'success' => true,
            'jobs' => $jobs,
            'pagination' => [
                'total' => $total,
                'limit' => $limitSql,
                'offset' => $offsetSql,
            ],
        ];
    }

    /**
     * 📊 Obtém histórico de otimizações em lote por item
     */
    public function getBatchHistory(array $itemIds, int $limit = 10): array
    {
        $itemIds = $this->sanitizeItemIds($itemIds);
        if (empty($itemIds)) {
            return ['success' => false, 'error' => 'Nenhum item válido'];
        }

        $limitSql = max(1, min(200, (int)$limit));

        $placeholders = implode(',', array_fill(0, count($itemIds), '?'));
        
        $stmt = $this->db->prepare("
            SELECT * FROM seo_optimization_history
            WHERE item_id IN ({$placeholders})
            AND account_id = ?
            ORDER BY applied_at DESC
            LIMIT {$limitSql}
        ");

        $params = array_merge($itemIds, [$this->accountId]);
        $stmt->execute($params);
        
        return [
            'success' => true,
            'history' => $stmt->fetchAll(PDO::FETCH_ASSOC),
        ];
    }

    /**
     * 🔄 Rollback em lote
     */
    public function rollbackBatch(array $versionIds, int $userId, string $reason = ''): array
    {
        if (empty($versionIds)) {
            return ['success' => false, 'error' => 'Nenhuma versão para rollback'];
        }

        $results = [];
        $stats = [
            'total' => count($versionIds),
            'successful' => 0,
            'failed' => 0,
        ];

        foreach ($versionIds as $versionId) {
            $versionId = (int)$versionId;
            if ($versionId <= 0) {
                continue;
            }

            try {
                // Obter dados da versão para pegar o item_id
                $version = $this->versioning->getVersion($versionId);
                if (!$version) {
                    $results[$versionId] = [
                        'success' => false,
                        'error' => 'Versão não encontrada',
                    ];
                    $stats['failed']++;
                    continue;
                }

                $itemId = $version['item_id'];
                $ok = $this->versioning->rollback($itemId, $versionId, $reason, $userId, 'user');
                
                if ($ok) {
                    $results[$versionId] = ['success' => true, 'item_id' => $itemId];
                    $stats['successful']++;
                } else {
                    $results[$versionId] = ['success' => false, 'error' => 'Rollback falhou'];
                    $stats['failed']++;
                }

            } catch (Exception $e) {
                $results[$versionId] = ['success' => false, 'error' => $e->getMessage()];
                $stats['failed']++;
            }

            usleep(self::RATE_LIMIT_DELAY_MS * 1000);
        }

        return [
            'success' => true,
            'stats' => $stats,
            'results' => $results,
        ];
    }
}
