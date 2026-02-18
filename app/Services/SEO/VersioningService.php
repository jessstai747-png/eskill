<?php
declare(strict_types=1);

namespace App\Services\SEO;

use App\Database;
use App\Services\MercadoLivreClient;
use Exception;

/**
 * Versioning Service
 * 
 * Manages version control and rollback for listing optimizations
 */
class VersioningService
{
    private const VALID_CHANGE_TYPES = ['title', 'description', 'attributes', 'images', 'price', 'category', 'bulk'];
    
    private \PDO $db;
    private MercadoLivreClient $mlClient;
    private string $snapshotDir;
    
    /**
     * Constructor with optional dependency injection for testing
     * 
     * @param int|null $accountId Account ID for ML API calls
     * @param MercadoLivreClient|null $mlClient Optional injected client (for testing)
     * @param string|null $snapshotDir Optional custom snapshot directory
     */
    public function __construct(
        ?int $accountId = null,
        ?MercadoLivreClient $mlClient = null,
        ?string $snapshotDir = null
    ) {
        $this->db = Database::getInstance();
        $this->mlClient = $mlClient ?? new MercadoLivreClient($accountId);
        $this->snapshotDir = $snapshotDir ?? __DIR__ . '/../../../storage/seo_snapshots';

        $this->ensureSnapshotDirExists();
    }

    private function ensureSnapshotDirExists(): void
    {
        if (is_dir($this->snapshotDir)) {
            return;
        }

        // Avoid noisy warnings; throw a controlled exception instead.
        if (!@mkdir($this->snapshotDir, 0755, true) && !is_dir($this->snapshotDir)) {
            throw new Exception("Failed to create snapshot directory: {$this->snapshotDir}");
        }
    }

    private function requireAccountId(): int
    {
        $accountId = $this->mlClient->getAccountId();
        if (!is_int($accountId) || $accountId <= 0) {
            throw new Exception('No account selected for versioning (account_id missing)');
        }
        return $accountId;
    }

    private function sanitizeFilenameComponent(string $value): string
    {
        $value = preg_replace('/[^A-Za-z0-9_-]/', '_', $value) ?? 'snapshot';
        $value = trim($value, '_');
        if ($value === '') {
            return 'snapshot';
        }
        // Keep filenames reasonably short (avoid filesystem/path limits).
        return substr($value, 0, 80);
    }

    private function jsonEncode(array $data, bool $pretty = false): string
    {
        $flags = JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE;
        if ($pretty) {
            $flags |= JSON_PRETTY_PRINT;
        }

        $json = json_encode($data, $flags);
        if ($json === false) {
            throw new Exception('Failed to encode JSON: ' . json_last_error_msg());
        }

        return $json;
    }
    
    /**
     * Create a snapshot before making changes
     * 
     * @param string $itemId
     * @param string $changeType
     * @param array $beforeData
     * @param array $afterData
     * @param string $changedBy 'user', 'ai', or 'automation'
     * @param int|null $userId
     * @return int Version ID
     */
    public function createSnapshot(
        string $itemId,
        string $changeType,
        array $beforeData,
        array $afterData,
        string $changedBy = 'user',
        ?int $userId = null
    ): int {
        // Validate change type
        if (!in_array($changeType, self::VALID_CHANGE_TYPES, true)) {
            throw new Exception("Invalid change_type: {$changeType}. Valid types: " . implode(', ', self::VALID_CHANGE_TYPES));
        }
        
        // Validate changedBy
        if (!in_array($changedBy, ['user', 'ai', 'automation'], true)) {
            throw new Exception("Invalid changed_by: {$changedBy}. Valid values: user, ai, automation");
        }
        
        $accountId = $this->requireAccountId();

        // Ensure atomic version numbering with FOR UPDATE by running inside a transaction.
        $startedTx = false;
        if (!$this->db->inTransaction()) {
            $this->db->beginTransaction();
            $startedTx = true;
        }

        $snapshotPath = '';
        try {
            // Get next version number (locked)
            $version = $this->getNextVersion($itemId);

            // Generate diff
            $diff = $this->generateDiff($changeType, $beforeData, $afterData);

            // Save full snapshot to file (before state)
            $snapshotPath = $this->saveSnapshotFile($itemId, $version, $beforeData);

            // Insert into database
            $stmt = $this->db->prepare(
                "INSERT INTO seo_optimization_history (
                    item_id, account_id, version,
                    change_type, changed_by, user_id,
                    before_data, after_data, diff,
                    snapshot_path, can_rollback
                ) VALUES (
                    :item_id, :account_id, :version,
                    :change_type, :changed_by, :user_id,
                    :before_data, :after_data, :diff,
                    :snapshot_path, TRUE
                )"
            );
            $stmt->execute([
                'item_id' => $itemId,
                'account_id' => $accountId,
                'version' => $version,
                'change_type' => $changeType,
                'changed_by' => $changedBy,
                'user_id' => $userId,
                'before_data' => $this->jsonEncode($beforeData),
                'after_data' => $this->jsonEncode($afterData),
                'diff' => $diff,
                'snapshot_path' => $snapshotPath,
            ]);

            // IMPORTANT: In this environment, PDO::lastInsertId() can return 0 after COMMIT.
            // Capture it before committing.
            $insertId = (int)$this->db->lastInsertId();

            if ($startedTx) {
                $this->db->commit();
            }

            return $insertId;
        } catch (\Throwable $e) {
            if ($startedTx && $this->db->inTransaction()) {
                $this->db->rollBack();
            }

            // Best-effort cleanup of orphan snapshot when DB insert fails
            if ($snapshotPath !== '' && file_exists($snapshotPath)) {
                @unlink($snapshotPath);
            }

            throw $e instanceof Exception ? $e : new Exception($e->getMessage(), (int)$e->getCode(), $e);
        }
    }
    
    /**
     * Get next version number for an item
     * 
     * Uses FOR UPDATE to prevent race conditions when multiple processes
     * try to create versions simultaneously.
     */
    private function getNextVersion(string $itemId): int
    {
        // Use FOR UPDATE to lock the rows and prevent race conditions
        $stmt = $this->db->prepare(
            "SELECT MAX(version) as max_version 
             FROM seo_optimization_history 
             WHERE item_id = :item_id
             FOR UPDATE"
        );
        $stmt->execute(['item_id' => $itemId]);
        $result = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        $maxVersion = $result['max_version'] ?? 0;
        return (int)$maxVersion + 1;
    }
    
    /**
     * Generate human-readable diff
     */
    private function generateDiff(string $changeType, array $before, array $after): string
    {
        $diff = [];
        
        switch ($changeType) {
            case 'title':
                $diff[] = "Título alterado:";
                $diff[] = "- Antes: " . ($before['title'] ?? '');
                $diff[] = "+ Depois: " . ($after['title'] ?? '');
                break;
                
            case 'description':
                $beforeText = $this->extractPlainTextDescription($before);
                $afterText = $this->extractPlainTextDescription($after);
                $beforeLen = $this->safeStrlen(strip_tags($beforeText));
                $afterLen = $this->safeStrlen(strip_tags($afterText));
                $diff[] = "Descrição alterada:";
                $diff[] = "- Tamanho antes: {$beforeLen} caracteres";
                $diff[] = "+ Tamanho depois: {$afterLen} caracteres";
                break;
                
            case 'attributes':
                $beforeAttrs = $before['attributes'] ?? [];
                $afterAttrs = $after['attributes'] ?? [];
                
                $beforeIds = array_column($beforeAttrs, 'id');
                $afterIds = array_column($afterAttrs, 'id');
                
                $added = array_diff($afterIds, $beforeIds);
                $removed = array_diff($beforeIds, $afterIds);
                
                if (!empty($added)) {
                    $diff[] = "+ Atributos adicionados: " . implode(', ', $added);
                }
                if (!empty($removed)) {
                    $diff[] = "- Atributos removidos: " . implode(', ', $removed);
                }
                
                // Check for value changes
                foreach ($afterAttrs as $afterAttr) {
                    $attrId = $afterAttr['id'] ?? '';
                    foreach ($beforeAttrs as $beforeAttr) {
                        if (($beforeAttr['id'] ?? '') === $attrId) {
                            $beforeVal = $beforeAttr['value_name'] ?? '';
                            $afterVal = $afterAttr['value_name'] ?? '';
                            if ($beforeVal !== $afterVal) {
                                $diff[] = "~ {$attrId}: '{$beforeVal}' → '{$afterVal}'";
                            }
                            break;
                        }
                    }
                }
                break;
                
            case 'images':
                $beforeCount = count($before['pictures'] ?? []);
                $afterCount = count($after['pictures'] ?? []);
                $diff[] = "Imagens alteradas:";
                $diff[] = "- Antes: {$beforeCount} imagens";
                $diff[] = "+ Depois: {$afterCount} imagens";
                break;
                
            case 'price':
                $diff[] = "Preço alterado:";
                $diff[] = "- Antes: R$ " . number_format($before['price'] ?? 0, 2, ',', '.');
                $diff[] = "+ Depois: R$ " . number_format($after['price'] ?? 0, 2, ',', '.');
                break;
                
            default:
                $diff[] = "Alteração do tipo: {$changeType}";
        }
        
        return implode("\n", $diff);
    }

    /**
     * Extrai plain_text da descrição a partir de diferentes formatos de snapshot.
     * Aceita:
     * - ['description' => '...']
     * - ['description_plain_text' => '...']
     * - ['description' => ['plain_text' => '...']]
     */
    private function extractPlainTextDescription(array $snapshot): string
    {
        if (isset($snapshot['description_plain_text']) && is_string($snapshot['description_plain_text'])) {
            return $snapshot['description_plain_text'];
        }

        if (isset($snapshot['description']) && is_string($snapshot['description'])) {
            return $snapshot['description'];
        }

        if (
            isset($snapshot['description']) &&
            is_array($snapshot['description']) &&
            isset($snapshot['description']['plain_text']) &&
            is_string($snapshot['description']['plain_text'])
        ) {
            return $snapshot['description']['plain_text'];
        }

        return '';
    }

    /**
     * Safe string length function with mbstring fallback
     * 
     * @param string $string The string to measure
     * @return int The string length in characters (not bytes)
     */
    private function safeStrlen(string $string): int
    {
        if (function_exists('mb_strlen')) {
            return mb_strlen($string, 'UTF-8');
        }
        
        // Fallback for environments without mbstring
        // This counts UTF-8 characters by excluding continuation bytes
        return preg_match_all('/./u', $string, $matches) ?: strlen($string);
    }

    /**
     * Monta endpoint + payload corretos para rollback conforme o tipo de mudança.
     */
    private function buildRollbackRequest(string $itemId, string $changeType, array $snapshotData): array
    {
        switch ($changeType) {
            case 'title':
                $title = trim((string)($snapshotData['title'] ?? ''));
                if ($title === '') {
                    throw new Exception('Snapshot não contém título válido para rollback');
                }
                return [
                    'endpoint' => "/items/{$itemId}",
                    'payload' => ['title' => $title],
                ];

            case 'description':
                $plainText = trim($this->extractPlainTextDescription($snapshotData));
                if ($plainText === '') {
                    throw new Exception('Snapshot não contém descrição válida (plain_text) para rollback');
                }
                return [
                    'endpoint' => "/items/{$itemId}/description",
                    'payload' => ['plain_text' => $plainText],
                ];

            case 'attributes':
                $attributes = $snapshotData['attributes'] ?? [];
                if (empty($attributes)) {
                    throw new Exception('Snapshot não contém atributos válidos para rollback');
                }
                return [
                    'endpoint' => "/items/{$itemId}",
                    'payload' => ['attributes' => $attributes],
                ];

            case 'images':
                $picturesRaw = $snapshotData['pictures'] ?? [];
                if (!is_array($picturesRaw) || empty($picturesRaw)) {
                    throw new Exception('Snapshot não contém imagens válidas para rollback');
                }
                $pictures = $this->normalizePicturesForPut($picturesRaw);
                if (empty($pictures)) {
                    throw new Exception('Snapshot não contém imagens válidas para rollback');
                }
                return [
                    'endpoint' => "/items/{$itemId}",
                    'payload' => ['pictures' => $pictures],
                ];

            case 'price':
                $price = $snapshotData['price'] ?? 0;
                if (!is_numeric($price) || $price <= 0) {
                    throw new Exception('Snapshot não contém preço válido para rollback (deve ser > 0)');
                }
                return [
                    'endpoint' => "/items/{$itemId}",
                    'payload' => ['price' => (float)$price],
                ];

            case 'category':
                $categoryId = trim((string)($snapshotData['category_id'] ?? ''));
                if ($categoryId === '') {
                    throw new Exception('Snapshot não contém category_id válido para rollback');
                }
                return [
                    'endpoint' => "/items/{$itemId}",
                    'payload' => ['category_id' => $categoryId],
                ];

            case 'bulk':
                // Best-effort bulk rollback via /items. Description rollback requires a different endpoint
                // and is handled only when change_type is 'description'.
                $payload = [];

                if (isset($snapshotData['title']) && is_string($snapshotData['title']) && trim($snapshotData['title']) !== '') {
                    $payload['title'] = $snapshotData['title'];
                }
                if (isset($snapshotData['price']) && is_numeric($snapshotData['price']) && (float)$snapshotData['price'] > 0) {
                    $payload['price'] = (float)$snapshotData['price'];
                }
                if (isset($snapshotData['attributes']) && is_array($snapshotData['attributes']) && !empty($snapshotData['attributes'])) {
                    $payload['attributes'] = $snapshotData['attributes'];
                }
                if (isset($snapshotData['pictures']) && is_array($snapshotData['pictures']) && !empty($snapshotData['pictures'])) {
                    $pics = $this->normalizePicturesForPut($snapshotData['pictures']);
                    if (!empty($pics)) {
                        $payload['pictures'] = $pics;
                    }
                }
                if (isset($snapshotData['category_id']) && is_string($snapshotData['category_id']) && trim($snapshotData['category_id']) !== '') {
                    $payload['category_id'] = $snapshotData['category_id'];
                }

                if (empty($payload)) {
                    throw new Exception('Snapshot não contém dados válidos para rollback (bulk)');
                }

                return [
                    'endpoint' => "/items/{$itemId}",
                    'payload' => $payload,
                ];

            default:
                throw new Exception("Unsupported rollback change_type: {$changeType}");
        }
    }

    /**
     * Normaliza "pictures" para PUT no ML.
     * Aceita formatos comuns vindos do GET (url/secure_url/id) e converte para [{source: ...}].
     */
    private function normalizePicturesForPut(array $pictures): array
    {
        $normalized = [];

        foreach ($pictures as $picture) {
            if (is_string($picture)) {
                $source = trim($picture);
                if ($source !== '') {
                    $normalized[] = ['source' => $source];
                }
                continue;
            }

            if (!is_array($picture)) {
                continue;
            }

            $source = '';
            if (isset($picture['source']) && is_string($picture['source'])) {
                $source = $picture['source'];
            } elseif (isset($picture['url']) && is_string($picture['url'])) {
                $source = $picture['url'];
            } elseif (isset($picture['secure_url']) && is_string($picture['secure_url'])) {
                $source = $picture['secure_url'];
            }

            $source = trim($source);
            if ($source === '') {
                continue;
            }

            $normalized[] = ['source' => $source];
        }

        return $normalized;
    }
    
    /**
     * Save full snapshot to file
     */
    private function saveSnapshotFile(string $itemId, int $version, array $data): string
    {
        $this->ensureSnapshotDirExists();

        $safeItemId = $this->sanitizeFilenameComponent($itemId);
        $filename = "{$safeItemId}_v{$version}_" . date('YmdHis') . ".json";
        $filepath = rtrim($this->snapshotDir, '/') . '/' . $filename;

        $json = $this->jsonEncode($data, true);
        $written = @file_put_contents($filepath, $json, LOCK_EX);
        if ($written === false) {
            throw new Exception("Failed to write snapshot file: {$filepath}");
        }

        return $filepath;
    }
    
    /**
     * Rollback to a specific version
     * 
     * @param string $itemId
     * @param int $versionId
     * @param string $reason
     * @return bool Success
     */
    public function rollback(string $itemId, int $versionId, string $reason = '', ?int $userId = null, string $changedBy = 'user'): bool
    {
        // Normalize audit fields
        if (!in_array($changedBy, ['user', 'ai', 'automation'], true)) {
            $changedBy = 'user';
        }

        // Get version data
        $version = $this->getVersion($versionId);
        
        if (!$version) {
            throw new Exception("Version not found: {$versionId}");
        }
        
        if ($version['item_id'] !== $itemId) {
            throw new Exception("Version does not belong to this item");
        }

        // Enforce account isolation when possible
        $accountId = $this->mlClient->getAccountId();
        if (is_int($accountId) && $accountId > 0 && isset($version['account_id']) && (int)$version['account_id'] !== $accountId) {
            throw new Exception("Version does not belong to this account");
        }

        if (!$version['can_rollback']) {
            throw new Exception("This version cannot be rolled back");
        }
        
        // Get snapshot data
        $snapshotData = $this->loadSnapshot($version['snapshot_path']);
        
        if (!$snapshotData) {
            throw new Exception("Snapshot file not found");
        }
        
        // Get current item state
        $changeType = (string)($version['change_type'] ?? '');
        if ($changeType === '') {
            throw new Exception('Invalid change_type for rollback');
        }

        // Validate snapshot content early (avoid creating rollback versions for invalid snapshots)
        $req = $this->buildRollbackRequest($itemId, $changeType, $snapshotData);

        $currentItem = $this->mlClient->get("/items/{$itemId}");
        if (!$currentItem || isset($currentItem['error']) || empty($currentItem['id'])) {
            $message = is_array($currentItem)
                ? (string)($currentItem['message'] ?? $currentItem['error'] ?? 'Falha ao obter estado atual do item')
                : 'Falha ao obter estado atual do item';
            throw new Exception($message);
        }

        // For description changes, capture current description too (ML returns it in a separate endpoint)
        if ($changeType === 'description') {
            $currentDescription = $this->mlClient->get("/items/{$itemId}/description");
            $currentItem['description_plain_text'] = is_array($currentDescription)
                ? (string)($currentDescription['plain_text'] ?? '')
                : '';
        }
        
        // Create a new version for the rollback
        $rollbackVersionId = $this->createSnapshot(
            $itemId,
            $changeType,
            $currentItem,
            $snapshotData,
            $changedBy,
            $userId
        );
        
        // Apply the rollback via ML API
        try {
            $result = $this->mlClient->put($req['endpoint'], $req['payload']);

            if (is_array($result) && isset($result['error'])) {
                $this->cleanupSnapshotVersion($rollbackVersionId);
                return false;
            }

            if ($result) {
                // Mark original version as rolled back
                try {
                    $stmt = $this->db->prepare(
                        "UPDATE seo_optimization_history 
                         SET rolled_back = TRUE,
                             rolled_back_at = NOW(),
                             rollback_reason = :reason
                         WHERE id = :version_id"
                    );
                    $stmt->execute([
                        'version_id' => $versionId,
                        'reason' => $reason,
                    ]);
                } catch (Exception $e) {
                    // Backward-compatible: older schemas might not have rollback columns
                }
                
                return true;
            }

            $this->cleanupSnapshotVersion($rollbackVersionId);
            return false;
            
        } catch (Exception $e) {
            $this->cleanupSnapshotVersion($rollbackVersionId);
            log_error('Falha no rollback de versão SEO', [
                'error' => $e->getMessage(),
            ]);
            throw new Exception("Failed to apply rollback: " . $e->getMessage());
        }
    }

    /**
     * Remove snapshot/version criado quando o rollback falha (evita historico fantasma).
     * 
     * @param int $versionId The version ID to clean up
     */
    private function cleanupSnapshotVersion(int $versionId): void
    {
        try {
            $stmt = $this->db->prepare('SELECT snapshot_path FROM seo_optimization_history WHERE id = :id');
            $stmt->execute(['id' => $versionId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            $snapshotPath = $row['snapshot_path'] ?? null;
            if (is_string($snapshotPath) && $snapshotPath !== '' && file_exists($snapshotPath)) {
                // Security: delete only inside snapshotDir (prevent tampered DB paths)
                $realPath = realpath($snapshotPath);
                $realSnapshotDir = realpath($this->snapshotDir);
                if ($realPath !== false && $realSnapshotDir !== false && str_starts_with($realPath, $realSnapshotDir . DIRECTORY_SEPARATOR)) {
                    if (!@unlink($snapshotPath)) {
                        log_warning('Falha ao deletar snapshot órfão', [
                            'snapshot_path' => $snapshotPath,
                            'version_id' => $versionId,
                        ]);
                    }
                } else {
                    log_warning('Tentativa de deletar snapshot fora do diretório permitido', [
                        'snapshot_path' => $snapshotPath,
                        'version_id' => $versionId,
                    ]);
                }
            }

            $del = $this->db->prepare('DELETE FROM seo_optimization_history WHERE id = :id');
            $del->execute(['id' => $versionId]);
            
            log_info('Cleanup de rollback falhado concluído', [
                'version_id' => $versionId,
            ]);
        } catch (Exception $e) {
            // Log the cleanup failure for monitoring/alerting
            log_error('Falha no cleanup de versão de rollback', [
                'version_id' => $versionId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Prepare update data based on change type
     */
    private function prepareUpdateData(string $changeType, array $snapshotData): array
    {
        switch ($changeType) {
            case 'title':
                return ['title' => $snapshotData['title'] ?? ''];
                
            case 'description':
                return ['description' => $snapshotData['description'] ?? ''];
                
            case 'attributes':
                return ['attributes' => $snapshotData['attributes'] ?? []];
                
            case 'images':
                return ['pictures' => $snapshotData['pictures'] ?? []];
                
            case 'price':
                return ['price' => $snapshotData['price'] ?? 0];
                
            default:
                // For bulk or unknown types, return relevant fields
                return [
                    'title' => $snapshotData['title'] ?? null,
                    'description' => $snapshotData['description'] ?? null,
                    'attributes' => $snapshotData['attributes'] ?? null,
                    'price' => $snapshotData['price'] ?? null,
                ];
        }
    }
    
    /**
     * Load snapshot from file
     * 
     * @param string $filepath Path to the snapshot file
     * @return array|null Decoded snapshot data or null if not found/invalid
     */
    private function loadSnapshot(string $filepath): ?array
    {
        if ($filepath === '' || !file_exists($filepath)) {
            return null;
        }
        
        // Security: Ensure path is within snapshot directory (prevent path traversal)
        $realPath = realpath($filepath);
        $realSnapshotDir = realpath($this->snapshotDir);
        
        if ($realPath === false || $realSnapshotDir === false) {
            log_warning('Não foi possível resolver caminho real do snapshot', [
                'filepath' => $filepath,
            ]);
            return null;
        }
        
        if (!str_starts_with($realPath, $realSnapshotDir . DIRECTORY_SEPARATOR)) {
            log_warning('Tentativa de carregar snapshot fora do diretório permitido', [
                'filepath' => $filepath,
            ]);
            return null;
        }
        
        $content = file_get_contents($filepath);
        if ($content === false) {
            log_warning('Falha ao ler arquivo de snapshot', [
                'filepath' => $filepath,
            ]);
            return null;
        }
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            log_warning('JSON inválido no arquivo de snapshot', [
                'filepath' => $filepath,
            ]);
            return null;
        }
        
        return $data;
    }
    
    /**
     * Get version by ID
     */
    public function getVersion(int $versionId): ?array
    {
        $stmt = $this->db->prepare(
            "SELECT * FROM seo_optimization_history WHERE id = :id"
        );
        $stmt->execute(['id' => $versionId]);
        $result = $stmt->fetchAll();
        
        if (empty($result)) {
            return null;
        }
        
        $version = $result[0];
        $version['before_data'] = json_decode($version['before_data'], true);
        $version['after_data'] = json_decode($version['after_data'], true);
        
        return $version;
    }
    
    /**
     * Get version history for an item
     */
    public function getHistory(string $itemId, int $limit = 50): array
    {
        $accountId = $this->mlClient->getAccountId();

        $limitSql = max(1, min((int)$limit, 500));

        $sql = "SELECT 
                    id, version, change_type, changed_by, user_id,
                    diff, can_rollback, rolled_back, applied_at
                FROM seo_optimization_history 
                WHERE item_id = :item_id";

        // PDO (native prepares) does not allow reusing the same named parameter twice.
        if (is_int($accountId) && $accountId > 0) {
            $sql .= " AND account_id = :account_id";
        }

        $sql .= " ORDER BY version DESC LIMIT {$limitSql}";

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':item_id', $itemId);
        if (is_int($accountId) && $accountId > 0) {
            $stmt->bindValue(':account_id', $accountId, \PDO::PARAM_INT);
        }
        $stmt->execute();
        
        return $stmt->fetchAll();
    }
    
    /**
     * Get version comparison (diff view)
     */
    public function compareVersions(int $versionId1, int $versionId2): array
    {
        $v1 = $this->getVersion($versionId1);
        $v2 = $this->getVersion($versionId2);
        
        if (!$v1 || !$v2) {
            throw new Exception("One or both versions not found");
        }
        
        if ($v1['item_id'] !== $v2['item_id']) {
            throw new Exception("Versions belong to different items");
        }
        
        return [
            'item_id' => $v1['item_id'],
            'version_1' => [
                'id' => $v1['id'],
                'version' => $v1['version'],
                'date' => $v1['applied_at'],
                'data' => $v1['after_data'],
            ],
            'version_2' => [
                'id' => $v2['id'],
                'version' => $v2['version'],
                'date' => $v2['applied_at'],
                'data' => $v2['after_data'],
            ],
            'diff' => $this->generateDiff(
                'bulk',
                $v1['after_data'],
                $v2['after_data']
            ),
        ];
    }
    
    /**
     * Clean old snapshots (retention policy)
     * 
     * @param int $daysToKeep Number of days to keep snapshots (minimum 1)
     * @return int Number of snapshots cleaned
     * @throws Exception If daysToKeep is invalid
     */
    public function cleanOldSnapshots(int $daysToKeep = 90): int
    {
        if ($daysToKeep < 1) {
            throw new Exception("daysToKeep must be at least 1, got: {$daysToKeep}");
        }
        
        $cutoffDate = date('Y-m-d H:i:s', strtotime("-{$daysToKeep} days"));
        $accountId = $this->mlClient->getAccountId();
        
        // Get old snapshots that can still be cleaned (have snapshot_path)
        $sql = "SELECT id, snapshot_path 
                FROM seo_optimization_history 
                WHERE applied_at < :cutoff_date
                  AND snapshot_path IS NOT NULL
                  AND can_rollback = TRUE";

        if (is_int($accountId) && $accountId > 0) {
            $sql .= " AND account_id = :account_id";
        }

        $stmt = $this->db->prepare($sql);
        $stmt->bindValue(':cutoff_date', $cutoffDate);
        if (is_int($accountId) && $accountId > 0) {
            $stmt->bindValue(':account_id', $accountId, \PDO::PARAM_INT);
        }
        $stmt->execute();
        $oldVersions = $stmt->fetchAll(\PDO::FETCH_ASSOC);
        
        $deletedCount = 0;
        
        foreach ($oldVersions as $version) {
            // Delete file safely
            $snapshotPath = $version['snapshot_path'] ?? null;
            if (is_string($snapshotPath) && $snapshotPath !== '' && file_exists($snapshotPath)) {
                $realPath = realpath($snapshotPath);
                $realSnapshotDir = realpath($this->snapshotDir);
                if ($realPath === false || $realSnapshotDir === false || !str_starts_with($realPath, $realSnapshotDir . DIRECTORY_SEPARATOR)) {
                    log_warning('Recusa em deletar snapshot antigo fora do diretório permitido', [
                        'snapshot_path' => $snapshotPath,
                    ]);
                    continue;
                }

                if (!@unlink($snapshotPath)) {
                    log_warning('Falha ao deletar snapshot antigo', [
                        'snapshot_path' => $snapshotPath,
                    ]);
                    continue; // Skip this version if file deletion fails
                }
            }
            
            // Mark as not rollbackable
            $stmtUpdate = $this->db->prepare(
                "UPDATE seo_optimization_history 
                 SET can_rollback = FALSE,
                     snapshot_path = NULL
                 WHERE id = :id"
            );
            $stmtUpdate->execute(['id' => $version['id']]);
            
            $deletedCount++;
        }
        
        if ($deletedCount > 0) {
            log_info('Limpeza de snapshots antigos concluída', [
                'deleted_count' => $deletedCount,
                'days_to_keep' => $daysToKeep,
            ]);
        }
        
        return $deletedCount;
    }
    
    /**
     * Get statistics
     */
    public function getStatistics(int $accountId): array
    {
        $stmt = $this->db->prepare(
            "SELECT 
                COUNT(*) as total_versions,
                SUM(CASE WHEN rolled_back = TRUE THEN 1 ELSE 0 END) as total_rollbacks,
                SUM(CASE WHEN can_rollback = TRUE THEN 1 ELSE 0 END) as rollbackable_versions,
                COUNT(DISTINCT item_id) as items_with_history
             FROM seo_optimization_history 
             WHERE account_id = :account_id"
        );
        $stmt->execute(['account_id' => $accountId]);
        $stats = $stmt->fetch(\PDO::FETCH_ASSOC);
        
        return $stats ?: [];
    }
    
    /**
     * Update impact tracking for a version
     * 
     * @param int $versionId
     * @param array $impactData Measured impact data
     */
    public function updateImpact(int $versionId, array $impactData): void
    {
        $stmt = $this->db->prepare(
            "UPDATE seo_optimization_history 
             SET actual_impact = :impact_data
             WHERE id = :version_id"
        );
        $stmt->execute([
            'version_id' => $versionId,
            'impact_data' => json_encode($impactData),
        ]);
    }
}
