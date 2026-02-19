<?php

namespace App\Services;

use App\Database;
use PDO;

/**
 * Tech Sheet Export/Import Service
 * 
 * Exporta e importa sugestões de ficha técnica em CSV/JSON
 * Permite backup, migração entre contas, compartilhamento de templates
 */
class TechSheetExportService
{
    private PDO $db;
    private int $accountId;

    public function __construct(int $accountId)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
    }

    /**
     * Exporta sugestões para CSV
     * 
     * @param array $options
     * @return string CSV content
     */
    public function exportToCSV(array $options = []): string
    {
        $filters = $this->buildFilters($options);
        $suggestions = $this->fetchSuggestions($filters);
        
        if (empty($suggestions)) {
            return '';
        }

        // Header CSV
        $csv = "item_id,title,category_id,attribute_id,attribute_name,suggested_value,source,confidence,status,created_at\n";
        
        // Dados
        foreach ($suggestions as $sug) {
            $csv .= $this->escapeCSV($sug['item_id']) . ',';
            $csv .= $this->escapeCSV($sug['title']) . ',';
            $csv .= $this->escapeCSV($sug['category_id']) . ',';
            $csv .= $this->escapeCSV($sug['attribute_id']) . ',';
            $csv .= $this->escapeCSV($sug['attribute_name']) . ',';
            $csv .= $this->escapeCSV($sug['suggested_value']) . ',';
            $csv .= $this->escapeCSV($sug['source']) . ',';
            $csv .= $sug['confidence'] . ',';
            $csv .= $this->escapeCSV($sug['status']) . ',';
            $csv .= $this->escapeCSV($sug['created_at']) . "\n";
        }
        
        return $csv;
    }

    /**
     * Exporta sugestões para JSON
     * 
     * @param array $options
     * @return string JSON content
     */
    public function exportToJSON(array $options = []): string
    {
        $filters = $this->buildFilters($options);
        $suggestions = $this->fetchSuggestions($filters);
        
        $export = [
            'version' => '1.0',
            'exported_at' => date('Y-m-d H:i:s'),
            'account_id' => $this->accountId,
            'total' => count($suggestions),
            'filters' => $options,
            'suggestions' => $suggestions,
        ];
        
        return json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Exporta relatório completo (itens + opcionalmente summary/gaps + opcionalmente sugestões) em JSON.
     */
    public function exportReportToJSON(array $options = []): string
    {
        $includeGaps = (bool)($options['include_gaps'] ?? false);
        $includeSuggestions = (bool)($options['include_suggestions'] ?? true);

        $filters = $this->buildReportFilters($options);
        $items = $this->fetchItems($filters);

        $itemIds = array_values(array_map(static fn($r) => (string)$r['ml_item_id'], $items));
        $summariesByItemId = $includeGaps ? $this->fetchSummariesByItemId($itemIds) : [];
        $suggestionsByItemId = $includeSuggestions ? $this->fetchSuggestionsGroupedByItemId($filters, $itemIds) : [];

        $exportItems = [];
        foreach ($items as $item) {
            $itemId = (string)$item['ml_item_id'];

            $row = [
                'item' => [
                    'item_id' => $itemId,
                    'title' => $item['title'],
                    'category_id' => $item['category_id'],
                    'price' => $item['price'],
                    'currency_id' => $item['currency_id'],
                    'status' => $item['status'],
                    'updated_at' => $item['updated_at'],
                ],
            ];

            if ($includeGaps) {
                $row['summary'] = $summariesByItemId[$itemId] ?? null;
            }

            if ($includeSuggestions) {
                $row['suggestions'] = $suggestionsByItemId[$itemId] ?? [];
            }

            $exportItems[] = $row;
        }

        $export = [
            'version' => '1.1',
            'type' => 'tech_sheet_report',
            'exported_at' => date('Y-m-d H:i:s'),
            'account_id' => $this->accountId,
            'filters' => $filters,
            'include_gaps' => $includeGaps,
            'include_suggestions' => $includeSuggestions,
            'total_items' => count($exportItems),
            'items' => $exportItems,
        ];

        return json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Exporta relatório completo em CSV.
     * - Se include_suggestions=true: 1 linha por sugestão (com colunas do item e do summary se solicitado)
     * - Se include_suggestions=false: 1 linha por item
     */
    public function exportReportToCSV(array $options = []): string
    {
        $includeGaps = (bool)($options['include_gaps'] ?? false);
        $includeSuggestions = (bool)($options['include_suggestions'] ?? true);

        $filters = $this->buildReportFilters($options);
        $items = $this->fetchItems($filters);
        if (empty($items)) {
            return '';
        }

        $itemIds = array_values(array_map(static fn($r) => (string)$r['ml_item_id'], $items));
        $summariesByItemId = $includeGaps ? $this->fetchSummariesByItemId($itemIds) : [];
        $suggestionsByItemId = $includeSuggestions ? $this->fetchSuggestionsGroupedByItemId($filters, $itemIds) : [];

        $baseHeader = [
            'item_id',
            'title',
            'category_id',
            'price',
            'currency_id',
            'status',
            'updated_at',
        ];

        $summaryHeader = [
            'total_available',
            'filled',
            'missing',
            'completeness_percent',
            'missing_required',
            'missing_filter',
            'missing_hidden',
            'missing_recommended',
            'last_analyzed_at',
        ];

        $suggestionHeader = [
            'attribute_id',
            'attribute_name',
            'suggested_value',
            'source',
            'confidence',
            'suggestion_status',
            'suggestion_created_at',
        ];

        $header = $baseHeader;
        if ($includeGaps) {
            $header = array_merge($header, $summaryHeader);
        }
        if ($includeSuggestions) {
            $header = array_merge($header, $suggestionHeader);
        }

        $csv = implode(',', $header) . "\n";

        foreach ($items as $item) {
            $itemId = (string)$item['ml_item_id'];
            $summary = $includeGaps ? ($summariesByItemId[$itemId] ?? null) : null;

            if ($includeSuggestions) {
                $suggestions = $suggestionsByItemId[$itemId] ?? [];
                if (empty($suggestions)) {
                    // Ainda exporta a linha do item sem sugestão
                    $csv .= $this->buildCsvReportRow($item, $summary, null, $includeGaps, true) . "\n";
                    continue;
                }

                foreach ($suggestions as $sug) {
                    $csv .= $this->buildCsvReportRow($item, $summary, $sug, $includeGaps, true) . "\n";
                }
            } else {
                $csv .= $this->buildCsvReportRow($item, $summary, null, $includeGaps, false) . "\n";
            }
        }

        return $csv;
    }

    /**
     * Importa sugestões de CSV
     * 
     * @param string $csvContent
     * @param array $options
     * @return array resultado da importação
     */
    public function importFromCSV(string $csvContent, array $options = []): array
    {
        $lines = explode("\n", trim($csvContent));
        
        if (count($lines) < 2) {
            return [
                'success' => false,
                'error' => 'CSV vazio ou inválido',
            ];
        }

        // Remover header
        array_shift($lines);
        
        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($lines as $lineNum => $line) {
            if (empty(trim($line))) {
                continue;
            }
            
            try {
                $data = str_getcsv($line);
                
                if (count($data) < 10) {
                    $skipped++;
                    continue;
                }
                
                $suggestion = [
                    'item_id' => $data[0],
                    'attribute_id' => $data[3],
                    'attribute_name' => $data[4],
                    'suggested_value' => $data[5],
                    'source' => $data[6],
                    'confidence' => (int) $data[7],
                    'status' => $options['force_status'] ?? 'pending',
                ];
                
                // Validar
                if (empty($suggestion['item_id']) || empty($suggestion['attribute_id'])) {
                    $skipped++;
                    continue;
                }
                
                // Verificar se já existe
                if ($this->suggestionExists($suggestion['item_id'], $suggestion['attribute_id'])) {
                    if (!($options['overwrite'] ?? false)) {
                        $skipped++;
                        continue;
                    }
                }
                
                // Inserir
                $this->insertSuggestion($suggestion);
                $imported++;
                
            } catch (\Exception $e) {
                $errors[] = "Linha " . ($lineNum + 2) . ": " . $e->getMessage();
            }
        }
        
        return [
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'total_lines' => count($lines),
        ];
    }

    /**
     * Importa sugestões de JSON
     * 
     * @param string $jsonContent
     * @param array $options
     * @return array resultado da importação
     */
    public function importFromJSON(string $jsonContent, array $options = []): array
    {
        $data = json_decode($jsonContent, true);
        
        if (!$data || !isset($data['suggestions'])) {
            return [
                'success' => false,
                'error' => 'JSON inválido ou formato incompatível',
            ];
        }

        $imported = 0;
        $skipped = 0;
        $errors = [];
        
        foreach ($data['suggestions'] as $idx => $sug) {
            try {
                // Validar campos obrigatórios
                if (empty($sug['item_id']) || empty($sug['attribute_id'])) {
                    $skipped++;
                    continue;
                }
                
                // Verificar se já existe
                if ($this->suggestionExists($sug['item_id'], $sug['attribute_id'])) {
                    if (!($options['overwrite'] ?? false)) {
                        $skipped++;
                        continue;
                    }
                }
                
                $suggestion = [
                    'item_id' => $sug['item_id'],
                    'attribute_id' => $sug['attribute_id'],
                    'attribute_name' => $sug['attribute_name'] ?? '',
                    'suggested_value' => $sug['suggested_value'],
                    'source' => $sug['source'] ?? 'import',
                    'confidence' => $sug['confidence'] ?? 50,
                    'status' => $options['force_status'] ?? 'pending',
                ];
                
                $this->insertSuggestion($suggestion);
                $imported++;
                
            } catch (\Exception $e) {
                $errors[] = "Item " . ($idx + 1) . ": " . $e->getMessage();
            }
        }
        
        return [
            'success' => true,
            'imported' => $imported,
            'skipped' => $skipped,
            'errors' => $errors,
            'total_suggestions' => count($data['suggestions']),
            'source_version' => $data['version'] ?? 'unknown',
        ];
    }

    /**
     * Exporta template de categoria (atributos comuns)
     * 
     * @param string $categoryId
     * @return string JSON template
     */
    public function exportCategoryTemplate(string $categoryId): string
    {
        // Buscar atributos mais comuns da categoria
        $stmt = $this->db->prepare("
            SELECT 
                sug.attribute_id,
                sug.attribute_name,
                sug.suggested_value,
                COUNT(*) as frequency,
                AVG(sug.confidence) as avg_confidence
            FROM tech_sheet_suggestions sug
            INNER JOIN items i ON sug.item_id = i.ml_item_id AND sug.account_id = i.account_id
            WHERE i.category_id = :category_id
              AND sug.status IN ('approved', 'applied')
            GROUP BY sug.attribute_id, sug.attribute_name, sug.suggested_value
            HAVING frequency >= 3
            ORDER BY frequency DESC, avg_confidence DESC
            LIMIT 50
        ");
        
        $stmt->execute([':category_id' => $categoryId]);
        $attributes = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $template = [
            'version' => '1.0',
            'type' => 'category_template',
            'category_id' => $categoryId,
            'created_at' => date('Y-m-d H:i:s'),
            'attributes' => $attributes,
        ];
        
        return json_encode($template, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Monta filtros SQL
     */
    private function buildFilters(array $options): array
    {
        $filters = [
            'status' => $options['status'] ?? null,
            'source' => $options['source'] ?? null,
            'min_confidence' => $options['min_confidence'] ?? null,
            'category_id' => $options['category_id'] ?? null,
            'item_ids' => $options['item_ids'] ?? null,
            'limit' => $options['limit'] ?? 10000,
        ];
        
        return $filters;
    }

    /**
     * Busca sugestões com filtros
     */
    private function fetchSuggestions(array $filters): array
    {
        $where = ['sug.account_id = :account_id'];
        $params = [':account_id' => $this->accountId];
        
        if ($filters['status']) {
            $where[] = 'sug.status = :status';
            $params[':status'] = $filters['status'];
        }
        
        if ($filters['source']) {
            $where[] = 'sug.source = :source';
            $params[':source'] = $filters['source'];
        }
        
        if ($filters['min_confidence']) {
            $where[] = 'sug.confidence >= :min_conf';
            $params[':min_conf'] = $filters['min_confidence'];
        }
        
        if ($filters['category_id']) {
            $where[] = 'i.category_id = :category_id';
            $params[':category_id'] = $filters['category_id'];
        }

        $itemIds = $this->normalizeItemIds($filters['item_ids'] ?? null);
        if (!empty($itemIds)) {
            $inPlaceholders = [];
            foreach ($itemIds as $idx => $itemId) {
                $ph = ':item_id_' . $idx;
                $inPlaceholders[] = $ph;
                $params[$ph] = $itemId;
            }
            $where[] = 'sug.item_id IN (' . implode(',', $inPlaceholders) . ')';
        }
        
        $whereClause = implode(' AND ', $where);

        $limitInput = isset($filters['limit']) ? (int)$filters['limit'] : 10000;
        $limitSql = max(1, min(10000, $limitInput));
        
        $stmt = $this->db->prepare("
            SELECT 
                sug.item_id,
                i.title,
                i.category_id,
                sug.attribute_id,
                sug.attribute_name,
                sug.suggested_value,
                sug.source,
                sug.confidence,
                sug.status,
                sug.created_at
            FROM tech_sheet_suggestions sug
            INNER JOIN items i ON sug.item_id = i.ml_item_id AND sug.account_id = i.account_id
            WHERE {$whereClause}
            ORDER BY sug.created_at DESC
            LIMIT {$limitSql}
        ");
        
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }
        
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function buildReportFilters(array $options): array
    {
        $filters = $this->buildFilters($options);
        $filters['item_ids'] = $this->normalizeItemIds($options['item_ids'] ?? null);
        $filters['include_gaps'] = (bool)($options['include_gaps'] ?? false);
        $filters['include_suggestions'] = (bool)($options['include_suggestions'] ?? true);

        return $filters;
    }

    private function normalizeItemIds($value): array
    {
        if ($value === null) {
            return [];
        }

        if (is_string($value)) {
            $value = array_map('trim', explode(',', $value));
        }

        if (!is_array($value)) {
            return [];
        }

        $out = [];
        foreach ($value as $v) {
            $v = trim((string)$v);
            if ($v !== '') {
                $out[] = $v;
            }
        }

        return array_values(array_unique($out));
    }

    private function fetchItems(array $filters): array
    {
        $where = ['i.account_id = :account_id'];
        $params = [':account_id' => $this->accountId];

        if (!empty($filters['category_id'])) {
            $where[] = 'i.category_id = :category_id';
            $params[':category_id'] = $filters['category_id'];
        }

        $itemIds = $this->normalizeItemIds($filters['item_ids'] ?? null);
        if (!empty($itemIds)) {
            $inPlaceholders = [];
            foreach ($itemIds as $idx => $itemId) {
                $ph = ':item_id_' . $idx;
                $inPlaceholders[] = $ph;
                $params[$ph] = $itemId;
            }
            $where[] = 'i.ml_item_id IN (' . implode(',', $inPlaceholders) . ')';
        }

        $whereClause = implode(' AND ', $where);
        $limit = isset($filters['limit']) ? (int)$filters['limit'] : 10000;
        $limitSql = max(1, min(100000, (int)$limit));

        $stmt = $this->db->prepare("
            SELECT
                i.ml_item_id,
                i.title,
                i.category_id,
                i.price,
                i.currency_id,
                i.status,
                i.updated_at
            FROM items i
            WHERE {$whereClause}
            ORDER BY i.updated_at DESC
            LIMIT {$limitSql}
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function fetchSummariesByItemId(array $itemIds): array
    {
        $itemIds = $this->normalizeItemIds($itemIds);
        if (empty($itemIds)) {
            return [];
        }

        $params = [':account_id' => $this->accountId];
        $inPlaceholders = [];
        foreach ($itemIds as $idx => $itemId) {
            $ph = ':sum_item_id_' . $idx;
            $inPlaceholders[] = $ph;
            $params[$ph] = $itemId;
        }

        $stmt = $this->db->prepare("
            SELECT
                s.item_id,
                s.category_id,
                s.total_available,
                s.filled,
                s.missing,
                s.completeness_percent,
                s.missing_required,
                s.missing_filter,
                s.missing_hidden,
                s.missing_recommended,
                s.last_analyzed_at,
                s.meta,
                s.updated_at
            FROM tech_sheet_item_summary s
            WHERE s.account_id = :account_id
              AND s.item_id IN (" . implode(',', $inPlaceholders) . ")
        ");

        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value, is_int($value) ? PDO::PARAM_INT : PDO::PARAM_STR);
        }

        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $out = [];
        foreach ($rows as $row) {
            if (!empty($row['meta'])) {
                $meta = json_decode($row['meta'], true);
                $row['meta'] = is_array($meta) ? $meta : null;
            } else {
                $row['meta'] = null;
            }

            $out[(string)$row['item_id']] = $row;
        }

        return $out;
    }

    private function fetchSuggestionsGroupedByItemId(array $filters, array $itemIds): array
    {
        $filters = $filters;
        $filters['item_ids'] = $this->normalizeItemIds($itemIds);

        // Se estamos exportando itens selecionados, aumenta limite para não truncar sugestões.
        if (!empty($filters['item_ids'])) {
            $filters['limit'] = max((int)($filters['limit'] ?? 10000), 200000);
        }

        $rows = $this->fetchSuggestions($filters);
        $out = [];
        foreach ($rows as $row) {
            $key = (string)$row['item_id'];
            if (!isset($out[$key])) {
                $out[$key] = [];
            }
            $out[$key][] = $row;
        }
        return $out;
    }

    private function buildCsvReportRow(array $item, ?array $summary, ?array $suggestion, bool $includeGaps, bool $includeSuggestions): string
    {
        $cols = [];
        $cols[] = $this->escapeCSV($item['ml_item_id'] ?? '');
        $cols[] = $this->escapeCSV($item['title'] ?? '');
        $cols[] = $this->escapeCSV($item['category_id'] ?? '');
        $cols[] = $this->escapeCSV((string)($item['price'] ?? ''));
        $cols[] = $this->escapeCSV($item['currency_id'] ?? '');
        $cols[] = $this->escapeCSV($item['status'] ?? '');
        $cols[] = $this->escapeCSV($item['updated_at'] ?? '');

        if ($includeGaps) {
            $cols[] = $this->escapeCSV((string)($summary['total_available'] ?? ''));
            $cols[] = $this->escapeCSV((string)($summary['filled'] ?? ''));
            $cols[] = $this->escapeCSV((string)($summary['missing'] ?? ''));
            $cols[] = $this->escapeCSV((string)($summary['completeness_percent'] ?? ''));
            $cols[] = $this->escapeCSV((string)($summary['missing_required'] ?? ''));
            $cols[] = $this->escapeCSV((string)($summary['missing_filter'] ?? ''));
            $cols[] = $this->escapeCSV((string)($summary['missing_hidden'] ?? ''));
            $cols[] = $this->escapeCSV((string)($summary['missing_recommended'] ?? ''));
            $cols[] = $this->escapeCSV((string)($summary['last_analyzed_at'] ?? ''));
        }

        if ($includeSuggestions) {
            $cols[] = $this->escapeCSV($suggestion['attribute_id'] ?? '');
            $cols[] = $this->escapeCSV($suggestion['attribute_name'] ?? '');
            $cols[] = $this->escapeCSV($suggestion['suggested_value'] ?? '');
            $cols[] = $this->escapeCSV($suggestion['source'] ?? '');
            $cols[] = $this->escapeCSV((string)($suggestion['confidence'] ?? ''));
            $cols[] = $this->escapeCSV($suggestion['status'] ?? '');
            $cols[] = $this->escapeCSV($suggestion['created_at'] ?? '');
        }

        return implode(',', $cols);
    }

    /**
     * Verifica se sugestão já existe
     */
    private function suggestionExists(string $itemId, string $attributeId): bool
    {
        $stmt = $this->db->prepare("
            SELECT 1 
            FROM tech_sheet_suggestions 
            WHERE account_id = :account_id 
              AND item_id = :item_id 
              AND attribute_id = :attribute_id
            LIMIT 1
        ");
        
        $stmt->execute([
            ':account_id' => $this->accountId,
            ':item_id' => $itemId,
            ':attribute_id' => $attributeId,
        ]);
        
        return $stmt->fetch() !== false;
    }

    /**
     * Insere sugestão no banco
     */
    private function insertSuggestion(array $suggestion): void
    {
        $stmt = $this->db->prepare("
            INSERT INTO tech_sheet_suggestions 
            (account_id, item_id, attribute_id, attribute_name, suggested_value, source, confidence, status, created_at)
            VALUES 
            (:account_id, :item_id, :attribute_id, :attribute_name, :suggested_value, :source, :confidence, :status, NOW())
            ON DUPLICATE KEY UPDATE
                suggested_value = VALUES(suggested_value),
                confidence = VALUES(confidence),
                status = VALUES(status)
        ");
        
        $stmt->execute([
            ':account_id' => $this->accountId,
            ':item_id' => $suggestion['item_id'],
            ':attribute_id' => $suggestion['attribute_id'],
            ':attribute_name' => $suggestion['attribute_name'],
            ':suggested_value' => $suggestion['suggested_value'],
            ':source' => $suggestion['source'],
            ':confidence' => $suggestion['confidence'],
            ':status' => $suggestion['status'],
        ]);
    }

    /**
     * Escapa valor para CSV
     */
    private function escapeCSV(?string $value): string
    {
        if ($value === null) {
            return '';
        }
        
        // Se contém vírgula, aspas ou quebra de linha, envolver em aspas
        if (strpos($value, ',') !== false || 
            strpos($value, '"') !== false || 
            strpos($value, "\n") !== false) {
            return '"' . str_replace('"', '""', $value) . '"';
        }
        
        return $value;
    }
}
