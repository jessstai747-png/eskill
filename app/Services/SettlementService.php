<?php

declare(strict_types=1);

namespace App\Services;

use App\Database;
use PDO;

class SettlementService
{
    private PDO $db;
    private ?int $accountId;

    // Tolerância para reconciliação (em centavos)
    private const TOLERANCE_CENTS = 10; // R$ 0.10

    // Formatos de CSV suportados do Mercado Livre
    private const CSV_FORMATS = [
        'ml_standard' => [
            'date' => 'DATA',
            'source_id' => 'SOURCE_ID',
            'external_ref' => 'EXTERNAL_REFERENCE',
            'description' => 'DESCRIPTION',
            'type' => 'RECORD_TYPE',
            'gross' => 'GROSS_AMOUNT',
            'net' => 'NET_CREDIT_AMOUNT',
            'fee' => 'FEE_AMOUNT',
        ],
        'ml_settlement' => [
            'date' => 'DATE_CREATED',
            'source_id' => 'RELEASE_ID',
            'external_ref' => 'ORDER_ID',
            'description' => 'DETAIL',
            'type' => 'ORIGIN_TYPE',
            'gross' => 'TOTAL_AMOUNT',
            'net' => 'NET_RECEIVED_AMOUNT',
            'fee' => 'MARKETPLACE_FEE',
        ],
        'ml_report_v2' => [
            'date' => 'Fecha',
            'source_id' => 'Número de operación',
            'external_ref' => 'Referencia externa',
            'description' => 'Descripción',
            'type' => 'Tipo',
            'gross' => 'Monto bruto',
            'net' => 'Monto neto',
            'fee' => 'Tarifa',
        ],
    ];

    public function __construct(?int $accountId = null)
    {
        $this->db = Database::getInstance();
        $this->accountId = $accountId;
        $this->ensureTable();
    }

    private function ensureTable(): void
    {
        $this->db->exec("
            CREATE TABLE IF NOT EXISTS financial_settlements (
                id INT PRIMARY KEY AUTO_INCREMENT,
                account_id INT UNSIGNED,
                ml_record_id VARCHAR(50),
                order_id VARCHAR(50),
                pack_id VARCHAR(50),
                external_reference VARCHAR(100),
                date_released DATETIME,
                description TEXT,
                type VARCHAR(50),
                gross_amount DECIMAL(10,2),
                net_amount DECIMAL(10,2),
                fee_amount DECIMAL(10,2) DEFAULT 0,
                status VARCHAR(20) DEFAULT 'PENDING',
                reconciled_at DATETIME NULL,
                reconciliation_notes TEXT NULL,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_status (status),
                INDEX idx_order (order_id),
                INDEX idx_account (account_id),
                INDEX idx_date (date_released),
                UNIQUE KEY idx_ml_record (account_id, ml_record_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        // Adicionar colunas para tabelas existentes
        try {
            $this->db->exec("ALTER TABLE financial_settlements ADD COLUMN account_id INT UNSIGNED AFTER id");
        } catch (\Exception $e) { /* Column may already exist */
        }
        try {
            $this->db->exec("ALTER TABLE financial_settlements ADD COLUMN pack_id VARCHAR(50) AFTER order_id");
        } catch (\Exception $e) { /* Column may already exist */
        }
        try {
            $this->db->exec("ALTER TABLE financial_settlements ADD COLUMN fee_amount DECIMAL(10,2) DEFAULT 0 AFTER net_amount");
        } catch (\Exception $e) { /* Column may already exist */
        }
        try {
            $this->db->exec("ALTER TABLE financial_settlements ADD COLUMN reconciled_at DATETIME NULL AFTER status");
        } catch (\Exception $e) { /* Column may already exist */
        }
        try {
            $this->db->exec("ALTER TABLE financial_settlements ADD COLUMN reconciliation_notes TEXT NULL AFTER reconciled_at");
        } catch (\Exception $e) { /* Column may already exist */
        }
    }

    /**
     * Import a Mercado Livre Settlement Report (CSV)
     * Detecta automaticamente o formato do CSV
     */
    public function importReport(string $filePath, ?string $forceFormat = null): array
    {
        if (!file_exists($filePath)) {
            return ['success' => false, 'error' => 'Arquivo não encontrado'];
        }

        $handle = fopen($filePath, 'r');
        if (!$handle) {
            return ['success' => false, 'error' => 'Falha ao abrir arquivo'];
        }

        // Detectar encoding e BOM
        $bom = fread($handle, 3);
        if ($bom !== "\xEF\xBB\xBF") {
            rewind($handle);
        }

        // Ler cabeçalho
        $header = fgetcsv($handle, 0, ',', '"');
        if (!$header) {
            fclose($handle);
            return ['success' => false, 'error' => 'Arquivo CSV vazio ou inválido'];
        }

        // Normalizar cabeçalho (remover espaços, BOM, etc)
        $header = array_map(function ($h) {
            return trim(preg_replace('/[\x00-\x1F\x80-\xFF]/', '', $h));
        }, $header);

        // Detectar formato do CSV
        $format = $forceFormat ?? $this->detectCsvFormat($header);
        if (!$format) {
            fclose($handle);
            return [
                'success' => false,
                'error' => 'Formato de CSV não reconhecido',
                'detected_headers' => $header,
                'supported_formats' => array_keys(self::CSV_FORMATS)
            ];
        }

        // Mapear índices das colunas
        $columnMap = $this->mapColumns($header, $format);

        $stmt = $this->db->prepare("
            INSERT INTO financial_settlements
            (account_id, ml_record_id, order_id, pack_id, external_reference, date_released, description, type, gross_amount, net_amount, fee_amount, status, created_at)
            VALUES
            (:account_id, :ml_id, :order_id, :pack_id, :ext_ref, :date, :desc, :type, :gross, :net, :fee, 'PENDING', NOW())
            ON DUPLICATE KEY UPDATE
                gross_amount = VALUES(gross_amount),
                net_amount = VALUES(net_amount),
                fee_amount = VALUES(fee_amount),
                description = VALUES(description)
        ");

        $count = 0;
        $errors = 0;
        $errorDetails = [];

        while (($row = fgetcsv($handle, 0, ',', '"')) !== false) {
            if (count($row) < 3) continue; // Linha muito curta

            try {
                $data = $this->extractRowData($row, $columnMap);

                // Validar dados mínimos
                if (empty($data['source_id']) && empty($data['external_ref'])) {
                    continue;
                }

                // Extrair IDs de pedido e pack
                $orderInfo = $this->extractOrderInfo($data['external_ref']);

                $stmt->execute([
                    ':account_id' => $this->accountId,
                    ':ml_id' => $data['source_id'] ?: uniqid('imp_'),
                    ':order_id' => $orderInfo['order_id'],
                    ':pack_id' => $orderInfo['pack_id'],
                    ':ext_ref' => $data['external_ref'],
                    ':date' => $this->parseDate($data['date']),
                    ':desc' => $data['description'],
                    ':type' => $this->normalizeType($data['type']),
                    ':gross' => $this->parseAmount($data['gross']),
                    ':net' => $this->parseAmount($data['net']),
                    ':fee' => $this->parseAmount($data['fee']),
                ]);
                $count++;
            } catch (\Exception $e) {
                $errors++;
                if ($errors <= 5) {
                    $errorDetails[] = "Linha " . ($count + $errors + 1) . ": " . $e->getMessage();
                }
            }
        }

        fclose($handle);

        return [
            'success' => true,
            'imported' => $count,
            'errors' => $errors,
            'error_details' => $errorDetails,
            'format_detected' => $format,
        ];
    }

    /**
     * Detecta o formato do CSV baseado nos cabeçalhos
     */
    private function detectCsvFormat(array $header): ?string
    {
        $headerLower = array_map('strtolower', $header);

        foreach (self::CSV_FORMATS as $formatName => $columns) {
            $matches = 0;
            foreach ($columns as $col) {
                if (in_array(strtolower($col), $headerLower)) {
                    $matches++;
                }
            }
            // Se 60% das colunas esperadas existem, considera match
            if ($matches >= count($columns) * 0.6) {
                return $formatName;
            }
        }

        // Fallback: detectar formato genérico
        if (in_array('date', $headerLower) || in_array('data', $headerLower) || in_array('fecha', $headerLower)) {
            return 'generic';
        }

        return null;
    }

    /**
     * Mapeia colunas do cabeçalho para índices
     */
    private function mapColumns(array $header, string $format): array
    {
        $headerLower = array_map('strtolower', $header);
        $map = [];

        if ($format === 'generic') {
            // Mapeamento genérico por posição ou nome comum
            $commonNames = [
                'date' => ['date', 'data', 'fecha', 'date_created'],
                'source_id' => ['source_id', 'release_id', 'id', 'número de operación'],
                'external_ref' => ['external_reference', 'order_id', 'referencia externa', 'reference'],
                'description' => ['description', 'descripción', 'detail', 'desc'],
                'type' => ['type', 'tipo', 'record_type', 'origin_type'],
                'gross' => ['gross_amount', 'gross', 'monto bruto', 'total_amount'],
                'net' => ['net_credit_amount', 'net_received_amount', 'net', 'monto neto'],
                'fee' => ['fee_amount', 'marketplace_fee', 'fee', 'tarifa'],
            ];

            foreach ($commonNames as $key => $names) {
                foreach ($names as $name) {
                    $idx = array_search(strtolower($name), $headerLower);
                    if ($idx !== false) {
                        $map[$key] = $idx;
                        break;
                    }
                }
            }
        } else {
            // Usar formato específico
            $formatCols = self::CSV_FORMATS[$format] ?? [];
            foreach ($formatCols as $key => $colName) {
                $idx = array_search(strtolower($colName), $headerLower);
                if ($idx !== false) {
                    $map[$key] = $idx;
                }
            }
        }

        return $map;
    }

    /**
     * Extrai dados de uma linha usando o mapa de colunas
     */
    private function extractRowData(array $row, array $columnMap): array
    {
        return [
            'date' => $row[$columnMap['date'] ?? 0] ?? '',
            'source_id' => $row[$columnMap['source_id'] ?? 1] ?? '',
            'external_ref' => $row[$columnMap['external_ref'] ?? 2] ?? '',
            'description' => $row[$columnMap['description'] ?? 3] ?? '',
            'type' => $row[$columnMap['type'] ?? 4] ?? '',
            'gross' => $row[$columnMap['gross'] ?? 5] ?? '0',
            'net' => $row[$columnMap['net'] ?? 6] ?? '0',
            'fee' => $row[$columnMap['fee'] ?? 7] ?? '0',
        ];
    }

    /**
     * Parse de data em vários formatos
     */
    private function parseDate(string $dateStr): ?string
    {
        if (empty($dateStr)) {
            return null;
        }

        // Formatos comuns do ML
        $formats = [
            'Y-m-d H:i:s',
            'Y-m-d\TH:i:s',
            'Y-m-d\TH:i:s.uP',
            'd/m/Y H:i:s',
            'd/m/Y H:i',
            'd/m/Y',
            'm/d/Y',
            'Y-m-d',
        ];

        foreach ($formats as $fmt) {
            $dt = \DateTime::createFromFormat($fmt, $dateStr);
            if ($dt !== false) {
                return $dt->format('Y-m-d H:i:s');
            }
        }

        // Tentar strtotime como fallback
        $ts = strtotime(str_replace('/', '-', $dateStr));
        if ($ts !== false) {
            return date('Y-m-d H:i:s', $ts);
        }

        return null;
    }

    /**
     * Parse de valor monetário
     */
    private function parseAmount(string $amount): float
    {
        if (empty($amount)) {
            return 0.0;
        }

        // Remover símbolos de moeda
        $amount = preg_replace('/[R$\s]/', '', $amount);

        // Detectar formato brasileiro (1.234,56) vs americano (1,234.56)
        if (preg_match('/^\d{1,3}(\.\d{3})*,\d{2}$/', $amount)) {
            // Formato brasileiro
            $amount = str_replace('.', '', $amount);
            $amount = str_replace(',', '.', $amount);
        } else {
            // Formato americano ou simples
            $amount = str_replace(',', '', $amount);
        }

        return (float)$amount;
    }

    /**
     * Normaliza tipo de transação
     */
    private function normalizeType(string $type): string
    {
        $type = strtolower(trim($type));

        $typeMap = [
            'sale' => 'sale',
            'venta' => 'sale',
            'payout' => 'payout',
            'transfer' => 'transfer',
            'fee' => 'fee',
            'tarifa' => 'fee',
            'tax' => 'tax',
            'impuesto' => 'tax',
            'refund' => 'refund',
            'reembolso' => 'refund',
            'chargeback' => 'chargeback',
            'shipping' => 'shipping',
            'envio' => 'shipping',
            'frete' => 'shipping',
            'mediation' => 'mediation',
            'mediación' => 'mediation',
        ];

        return $typeMap[$type] ?? $type;
    }

    /**
     * Reconcile Pending Settlements
     * Algoritmo de reconciliação com múltiplas estratégias de matching
     */
    public function reconcile(?int $limit = null): array
    {
        $sql = "SELECT * FROM financial_settlements WHERE status = 'PENDING' AND type IN ('sale', 'payout')";
        if ($this->accountId) {
            $sql .= " AND account_id = " . (int)$this->accountId;
        }
        if ($limit) {
            $sql .= " LIMIT " . (int)$limit;
        }

        $stmt = $this->db->query($sql);
        $settlements = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $results = [
            'matched' => 0,
            'mismatch' => 0,
            'not_found' => 0,
            'total_processed' => count($settlements),
            'details' => [],
        ];

        foreach ($settlements as $s) {
            $order = $this->findMatchingOrder($s);
            $reconciliationResult = $this->processReconciliation($s, $order);

            $results[$reconciliationResult['status']]++;

            if ($reconciliationResult['status'] !== 'matched') {
                $results['details'][] = [
                    'settlement_id' => $s['id'],
                    'status' => $reconciliationResult['status'],
                    'reason' => $reconciliationResult['reason'] ?? null,
                    'expected' => $reconciliationResult['expected'] ?? null,
                    'received' => $s['net_amount'],
                    'difference' => $reconciliationResult['difference'] ?? null,
                ];
            }
        }

        return $results;
    }

    /**
     * Encontra pedido correspondente usando múltiplas estratégias
     */
    private function findMatchingOrder(array $settlement): ?array
    {
        $strategies = [
            'direct_order_id',
            'pack_id_lookup',
            'external_reference',
            'fuzzy_amount_date',
        ];

        foreach ($strategies as $strategy) {
            $order = $this->tryMatchStrategy($settlement, $strategy);
            if ($order) {
                return $order;
            }
        }

        return null;
    }

    /**
     * Tenta encontrar pedido usando estratégia específica
     */
    private function tryMatchStrategy(array $settlement, string $strategy): ?array
    {
        switch ($strategy) {
            case 'direct_order_id':
                if (!empty($settlement['order_id'])) {
                    $stmt = $this->db->prepare("
                        SELECT o.*,
                               (o.total_amount - COALESCE(o.shipping_cost, 0) - COALESCE(o.marketplace_fee, 0)) as calculated_net
                        FROM ml_orders o
                        WHERE o.ml_order_id = :order_id
                        LIMIT 1
                    ");
                    $stmt->execute(['order_id' => $settlement['order_id']]);
                    $order = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($order) {
                        $order['_match_strategy'] = 'direct_order_id';
                        return $order;
                    }
                }
                break;

            case 'pack_id_lookup':
                if (!empty($settlement['pack_id'])) {
                    // Pack pode ter múltiplos pedidos - somar valores
                    $stmt = $this->db->prepare("
                        SELECT
                            GROUP_CONCAT(o.ml_order_id) as order_ids,
                            SUM(o.total_amount) as total_amount,
                            SUM(COALESCE(o.shipping_cost, 0)) as shipping_cost,
                            SUM(COALESCE(o.marketplace_fee, 0)) as marketplace_fee,
                            SUM(o.total_amount - COALESCE(o.shipping_cost, 0) - COALESCE(o.marketplace_fee, 0)) as calculated_net,
                            COUNT(*) as order_count
                        FROM ml_orders o
                        WHERE o.pack_id = :pack_id
                    ");
                    $stmt->execute(['pack_id' => $settlement['pack_id']]);
                    $result = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($result && $result['order_count'] > 0) {
                        $result['_match_strategy'] = 'pack_id_lookup';
                        $result['ml_order_id'] = $result['order_ids'];
                        return $result;
                    }
                }
                break;

            case 'external_reference':
                if (!empty($settlement['external_reference'])) {
                    // Extrair possíveis IDs da referência
                    $possibleIds = $this->extractPossibleIds($settlement['external_reference']);

                    foreach ($possibleIds as $possibleId) {
                        $stmt = $this->db->prepare("
                            SELECT o.*,
                                   (o.total_amount - COALESCE(o.shipping_cost, 0) - COALESCE(o.marketplace_fee, 0)) as calculated_net
                            FROM ml_orders o
                            WHERE o.ml_order_id = :order_id
                               OR o.pack_id = :pack_id
                               OR o.external_reference = :ext_ref
                            LIMIT 1
                        ");
                        $stmt->execute([
                            'order_id' => $possibleId,
                            'pack_id' => $possibleId,
                            'ext_ref' => $possibleId,
                        ]);
                        $order = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($order) {
                            $order['_match_strategy'] = 'external_reference';
                            return $order;
                        }
                    }
                }
                break;

            case 'fuzzy_amount_date':
                // Último recurso: buscar por valor e data aproximados
                if (!empty($settlement['net_amount']) && !empty($settlement['date_released'])) {
                    $date = $settlement['date_released'];
                    $amount = (float)$settlement['net_amount'];

                    $stmt = $this->db->prepare("
                        SELECT o.*,
                               (o.total_amount - COALESCE(o.shipping_cost, 0) - COALESCE(o.marketplace_fee, 0)) as calculated_net,
                               ABS((o.total_amount - COALESCE(o.shipping_cost, 0) - COALESCE(o.marketplace_fee, 0)) - :amount) as diff
                        FROM ml_orders o
                        WHERE o.date_created BETWEEN DATE_SUB(:date, INTERVAL 3 DAY) AND DATE_ADD(:date2, INTERVAL 3 DAY)
                          AND o.status NOT IN ('cancelled', 'refunded')
                          AND o.ml_order_id NOT IN (SELECT DISTINCT order_id FROM financial_settlements WHERE order_id IS NOT NULL AND status = 'CONCILIATED')
                        HAVING diff < 1.00
                        ORDER BY diff ASC
                        LIMIT 1
                    ");
                    $stmt->execute([
                        'amount' => $amount,
                        'date' => $date,
                        'date2' => $date,
                    ]);
                    $order = $stmt->fetch(PDO::FETCH_ASSOC);
                    if ($order) {
                        $order['_match_strategy'] = 'fuzzy_amount_date';
                        return $order;
                    }
                }
                break;
        }

        return null;
    }

    /**
     * Processa a reconciliação entre settlement e order
     */
    private function processReconciliation(array $settlement, ?array $order): array
    {
        if (!$order) {
            return ['status' => 'not_found', 'reason' => 'Pedido não encontrado'];
        }

        // Calcular valor esperado (usar calculated_net se disponível, senão total_amount)
        $expected = (float)($order['calculated_net'] ?? $order['total_amount'] ?? 0);
        $received = (float)$settlement['net_amount'];
        $difference = abs($expected - $received);

        // Determinar status baseado na diferença
        $toleranceCents = self::TOLERANCE_CENTS / 100;
        $newStatus = 'PENDING';
        $reason = null;

        if ($difference <= $toleranceCents) {
            $newStatus = 'CONCILIATED';
        } elseif ($difference <= 5.00) {
            // Diferença pequena - pode ser taxa não contabilizada
            $newStatus = 'CONCILIATED';
            $reason = "Diferença de R$ " . number_format($difference, 2, ',', '.') . " (possível taxa não registrada)";
        } else {
            $newStatus = 'MISMATCH';
            $reason = "Esperado: R$ " . number_format($expected, 2, ',', '.') .
                ", Recebido: R$ " . number_format($received, 2, ',', '.');
        }

        // Atualizar settlement
        $stmt = $this->db->prepare("
            UPDATE financial_settlements
            SET status = :status,
                order_id = :order_id,
                reconciled_at = NOW(),
                reconciliation_notes = :notes
            WHERE id = :id
        ");
        $stmt->execute([
            'status' => $newStatus,
            'order_id' => $order['ml_order_id'] ?? $order['order_ids'] ?? null,
            'notes' => json_encode([
                'strategy' => $order['_match_strategy'] ?? 'unknown',
                'expected' => $expected,
                'received' => $received,
                'difference' => $difference,
                'reason' => $reason,
            ], JSON_UNESCAPED_UNICODE),
            'id' => $settlement['id'],
        ]);

        // Atualizar pedido como reconciliado (opcional)
        if ($newStatus === 'CONCILIATED' && !empty($order['ml_order_id'])) {
            try {
                $stmt = $this->db->prepare("
                    UPDATE ml_orders SET payment_reconciled = 1, updated_at = NOW()
                    WHERE ml_order_id = :order_id
                ");
                $stmt->execute(['order_id' => $order['ml_order_id']]);
            } catch (\Exception $e) {
                // Ignorar se coluna não existir
            }
        }

        return [
            'status' => $newStatus === 'CONCILIATED' ? 'matched' : 'mismatch',
            'reason' => $reason,
            'expected' => $expected,
            'difference' => $difference,
        ];
    }

    /**
     * Extrai possíveis IDs de uma referência
     * Handles: PACK_123456, ORDER_123456, 123456789, etc.
     */
    private function extractPossibleIds(string $reference): array
    {
        $ids = [$reference]; // Incluir original

        // Extrair números
        if (preg_match_all('/\d{8,}/', $reference, $matches)) {
            $ids = array_merge($ids, $matches[0]);
        }

        // Remover prefixos comuns
        $prefixes = ['PACK_', 'ORDER_', 'ORD_', 'MLB', 'MLA', 'MLM', 'MLU'];
        foreach ($prefixes as $prefix) {
            if (stripos($reference, $prefix) === 0) {
                $ids[] = substr($reference, strlen($prefix));
            }
        }

        return array_unique($ids);
    }

    /**
     * Extrai informações de order_id e pack_id de uma referência
     */
    private function extractOrderInfo(string $reference): array
    {
        $info = ['order_id' => null, 'pack_id' => null];

        if (empty($reference)) {
            return $info;
        }

        // Se começa com PACK_, é um pack
        if (stripos($reference, 'PACK_') === 0 || stripos($reference, 'PACK-') === 0) {
            $info['pack_id'] = preg_replace('/[^0-9]/', '', $reference);
        }
        // Se é apenas números, pode ser order_id ou pack_id
        elseif (preg_match('/^\d+$/', $reference)) {
            // ML orders geralmente têm 10+ dígitos
            $info['order_id'] = $reference;
        }
        // Tentar extrair número de qualquer formato
        else {
            if (preg_match('/(\d{8,})/', $reference, $matches)) {
                $info['order_id'] = $matches[1];
            }
        }

        return $info;
    }

    /**
     * Retorna resumo dos settlements
     */
    public function getSummary(): array
    {
        $sql = "SELECT status, COUNT(*) as count, SUM(net_amount) as total FROM financial_settlements";
        if ($this->accountId) {
            $sql .= " WHERE account_id = " . (int)$this->accountId;
        }
        $sql .= " GROUP BY status";

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Retorna detalhes de settlements não reconciliados
     */
    public function getPendingDetails(int $limit = 100): array
    {
        $sql = "
            SELECT fs.*,
                   o.total_amount as order_total,
                   o.status as order_status
            FROM financial_settlements fs
            LEFT JOIN ml_orders o ON fs.order_id = o.ml_order_id
            WHERE fs.status IN ('PENDING', 'MISMATCH')
        ";
        if ($this->accountId) {
            $sql .= " AND fs.account_id = " . (int)$this->accountId;
        }
        $sql .= " ORDER BY fs.date_released DESC LIMIT " . (int)$limit;

        $stmt = $this->db->query($sql);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Marca settlement manualmente como reconciliado
     */
    public function manualReconcile(int $settlementId, ?string $orderId = null, ?string $notes = null): bool
    {
        $stmt = $this->db->prepare("
            UPDATE financial_settlements
            SET status = 'MANUAL',
                order_id = COALESCE(:order_id, order_id),
                reconciled_at = NOW(),
                reconciliation_notes = :notes
            WHERE id = :id
        ");

        return $stmt->execute([
            'order_id' => $orderId,
            'notes' => $notes,
            'id' => $settlementId,
        ]);
    }

    /**
     * Gera relatório de reconciliação por período
     */
    public function getReconciliationReport(string $startDate, string $endDate): array
    {
        $sql = "
            SELECT
                DATE(date_released) as date,
                status,
                COUNT(*) as count,
                SUM(gross_amount) as gross_total,
                SUM(net_amount) as net_total,
                SUM(fee_amount) as fee_total
            FROM financial_settlements
            WHERE date_released BETWEEN :start AND :end
        ";
        if ($this->accountId) {
            $sql .= " AND account_id = " . (int)$this->accountId;
        }
        $sql .= " GROUP BY DATE(date_released), status ORDER BY date DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute(['start' => $startDate, 'end' => $endDate]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}
