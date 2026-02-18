<?php

/**
 * Professional Real-Time Database API
 * 
 * A secure, authenticated API for real-time database operations
 * with rate limiting, authentication, and comprehensive error handling.
 * 
 * SECURITY: This file operates outside the main framework pipeline.
 * It implements its own auth, rate limiting, and security headers.
 */

// Security headers applied before any output
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Services\JwtService;
use App\Services\MercadoLivreAuthService;
use App\Services\MercadoLivreClient;
use App\Services\StructuredLogService;

class RealTimeDatabaseAPI
{
    private $db;
    private $jwtService;
    private $logger;
    private $allowedTables;
    private $maxResults;
    private static bool $rateLimitTableVerified = false;

    /** @var array<int> Cached ML account IDs for the authenticated user */
    private array $userMLAccountIds = [];

    /** @var int|null Active ML account resolved from header/query */
    private ?int $activeMLAccountId = null;

    /** @var int Max ML proxy requests per account per hour */
    private int $mlRateLimitMax = 600;

    /** @var int ML proxy rate limit window in seconds */
    private int $mlRateLimitWindow = 3600;

    /**
     * Table ACL — defines per-table access control.
     *
     * Modes:
     *   'owner'     – rows filtered by ownership column (user_id / account_id)
     *   'self'      – user can only access their own row (id = auth user_id)
     *   'admin'     – only admin users can access
     *   'read_only' – any authenticated user can read, only admin can write
     *
     * 'owner_column' defines which column holds the owner reference.
     * When owner_column is 'account_id', the RBAC resolves the user's ML accounts
     * from ml_accounts and filters by those IDs (not the user_id directly).
     */
    private array $tableACL = [
        // Core tables
        'users'                  => ['mode' => 'self'],
        'items'                  => ['mode' => 'owner', 'owner_column' => 'account_id'],
        'orders'                 => ['mode' => 'owner', 'owner_column' => 'account_id'],
        'products'               => ['mode' => 'owner', 'owner_column' => 'account_id'],
        'categories'             => ['mode' => 'read_only'],
        'ml_accounts'            => ['mode' => 'owner', 'owner_column' => 'user_id'],
        'account_health_history' => ['mode' => 'owner', 'owner_column' => 'account_id'],
        'seo_analysis_cache'     => ['mode' => 'owner', 'owner_column' => 'account_id'],
        'notifications'          => ['mode' => 'owner', 'owner_column' => 'user_id'],
        'settings'               => ['mode' => 'admin'],
        'logs'                   => ['mode' => 'read_only'],
        'analytics'              => ['mode' => 'owner', 'owner_column' => 'account_id'],
        // ML Ads
        'ml_ad_campaigns_advanced' => ['mode' => 'owner', 'owner_column' => 'account_id'],
        // ML Pricing
        'ml_pricing_rules'         => ['mode' => 'owner', 'owner_column' => 'account_id'],
        // ML Competitor Intelligence
        'ml_competitor_monitoring' => ['mode' => 'owner', 'owner_column' => 'account_id'],
        'ml_competitor_data'       => ['mode' => 'read_only'],
        'ml_competitor_alerts'     => ['mode' => 'read_only'],
        'ml_market_opportunities'  => ['mode' => 'read_only'],
        // ML Analytics (global, not per-account)
        'ml_search_analytics'    => ['mode' => 'read_only'],
        'ml_customer_journey'    => ['mode' => 'read_only'],
        'ml_conversion_funnel'   => ['mode' => 'read_only'],
        'ml_predictive_analytics' => ['mode' => 'read_only'],
        // ML Unified / Service Management
        'ml_service_executions'  => ['mode' => 'read_only'],
        'ml_service_status'      => ['mode' => 'read_only'],
        'ml_service_alerts'      => ['mode' => 'read_only'],
        // ML Q&A
        'ml_qa_automation'       => ['mode' => 'read_only'],
        'ml_qa_knowledge_base'   => ['mode' => 'read_only'],
    ];

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->jwtService = new JwtService();
        $this->logger = new StructuredLogService();

        // Define allowed tables for security (keys from ACL)
        $this->allowedTables = array_keys($this->tableACL);

        $this->maxResults = 1000; // Maximum results per query
    }

    /**
     * Main API handler - routes requests based on method and endpoint
     */
    public function handleRequest()
    {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: ' . ($_ENV['APP_URL'] ?? $_ENV['CORS_ALLOWED_ORIGIN'] ?? 'https://eskill.com.br'));
        header('Access-Control-Allow-Methods: GET, POST, PUT, PATCH, DELETE');
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With, X-ML-Account-Id');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit();
        }

        try {
            $method = $_SERVER['REQUEST_METHOD'];
            $path = $this->getPath();
            $input = $this->getInput();

            // Authenticate request
            $authResult = $this->authenticateRequest();
            if (!$authResult['success']) {
                $this->sendResponse(['error' => $authResult['message']], 401);
                return;
            }

            $userId = $authResult['user_id'];

            // Check rate limit (user_id + IP for defense-in-depth)
            $rateLimitResult = $this->checkRateLimit($userId);
            if (!$rateLimitResult['allowed']) {
                $this->sendResponse(['error' => 'Rate limit exceeded'], 429);
                return;
            }

            // Resolve user role and admin status for RBAC
            $isAdmin = $this->isUserAdmin($userId);

            // Resolve ML account context (sets $this->userMLAccountIds and $this->activeMLAccountId)
            $this->resolveMLAccountContext($userId);

            // Check if this is a ML API proxy request (/api/v1/ml/...)
            $segments = explode('/', trim($path, '/'));
            if (count($segments) >= 3 && $segments[0] === 'api' && $segments[1] === 'v1' && $segments[2] === 'ml') {
                $this->routeMLRequest($method, $segments, $input, $userId);
                return;
            }

            // Route standard DB request
            $this->routeRequest($method, $path, $input, $userId, $isAdmin);
        } catch (Exception $e) {
            $this->logger->error('API Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'request' => [
                    'method' => $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
                    'uri' => $_SERVER['REQUEST_URI'] ?? 'UNKNOWN',
                    'ip' => $_SERVER['REMOTE_ADDR'] ?? 'UNKNOWN'
                ]
            ]);

            $this->sendResponse(['error' => 'Internal server error'], 500);
        }
    }

    /**
     * Route requests to appropriate handlers
     */
    private function routeRequest($method, $path, $input, $userId, bool $isAdmin = false)
    {
        $segments = explode('/', trim($path, '/'));

        if (count($segments) < 2 || $segments[0] !== 'api' || $segments[1] !== 'v1') {
            $this->sendResponse(['error' => 'Invalid API endpoint'], 404);
            return;
        }

        if (count($segments) < 3) {
            $this->sendResponse(['error' => 'Table name required'], 400);
            return;
        }

        $table = $segments[2];

        // Validate table name for security
        if (!$this->isValidTableName($table)) {
            $this->sendResponse(['error' => 'Invalid table name'], 400);
            return;
        }

        // RBAC: check table-level write permission
        $isWrite = in_array($method, ['POST', 'PUT', 'PATCH', 'DELETE'], true);
        if (!$this->checkTableAccess($table, $userId, $isAdmin, $isWrite)) {
            $this->logger->warning('RBAC denied', [
                'table' => $table,
                'user_id' => $userId,
                'method' => $method,
                'is_admin' => $isAdmin,
            ]);
            $this->sendResponse(['error' => 'Forbidden'], 403);
            return;
        }

        // Extract ID if present
        $id = isset($segments[3]) && is_numeric($segments[3]) ? (int)$segments[3] : null;

        switch ($method) {
            case 'GET':
                if ($id) {
                    $this->getRecord($table, $id, $userId, $isAdmin);
                } else {
                    $this->getList($table, $input, $userId, $isAdmin);
                }
                break;

            case 'POST':
                $this->createRecord($table, $input, $userId, $isAdmin);
                break;

            case 'PUT':
            case 'PATCH':
                if (!$id) {
                    $this->sendResponse(['error' => 'ID required for update'], 400);
                    return;
                }
                $this->updateRecord($table, $id, $input, $userId, $isAdmin);
                break;

            case 'DELETE':
                if (!$id) {
                    $this->sendResponse(['error' => 'ID required for delete'], 400);
                    return;
                }
                $this->deleteRecord($table, $id, $userId, $isAdmin);
                break;

            default:
                $this->sendResponse(['error' => 'Method not allowed'], 405);
                break;
        }
    }

    /**
     * Get a single record by ID
     */
    private function getRecord($table, $id, $userId, bool $isAdmin = false)
    {
        try {
            // Build ownership WHERE clause
            $ownerWhere = $this->buildOwnershipWhere($table, $userId, $isAdmin);
            $sql = "SELECT * FROM `{$table}` WHERE `id` = :id" . $ownerWhere['clause'] . " LIMIT 1";
            $params = array_merge(['id' => $id], $ownerWhere['params']);

            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$record) {
                $this->sendResponse(['error' => 'Record not found'], 404);
                return;
            }

            $this->sendResponse(['data' => $record]);
        } catch (Exception $e) {
            $this->logger->error('Get record error', [
                'table' => $table,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            $this->sendResponse(['error' => 'Failed to retrieve record'], 500);
        }
    }

    /**
     * Get a list of records with filtering, sorting, and pagination
     */
    private function getList($table, $input, $userId, bool $isAdmin = false)
    {
        try {
            $page = (int)($input['page'] ?? 1);
            $page = max(1, $page);
            $limit = min((int)($input['limit'] ?? 50), $this->maxResults);
            $limitSql = max(1, min((int)$this->maxResults, (int)$limit));
            $offsetSql = max(0, min(1000000, ($page - 1) * $limitSql));

            $orderBy = $input['order_by'] ?? 'id';
            $orderDir = strtoupper($input['order_dir'] ?? 'ASC');
            if (!in_array($orderDir, ['ASC', 'DESC'])) {
                $orderDir = 'ASC';
            }
            if (!$this->isValidFieldName($orderBy)) {
                $orderBy = 'id';
            }

            // Build WHERE clause from filters + ownership
            $ownerWhere = $this->buildOwnershipWhere($table, $userId, $isAdmin);
            $whereClause = '1=1' . $ownerWhere['clause'];
            $params = $ownerWhere['params'];

            if (isset($input['filters']) && is_array($input['filters'])) {
                foreach ($input['filters'] as $field => $value) {
                    if ($this->isValidFieldName($field)) {
                        $whereClause .= " AND `{$field}` = :filter_{$field}";
                        $params["filter_{$field}"] = $value;
                    }
                }
            }

            // Build query
            $sql = "SELECT * FROM `{$table}` WHERE {$whereClause} ORDER BY `{$orderBy}` {$orderDir} LIMIT {$limitSql} OFFSET {$offsetSql}";

            $stmt = $this->db->prepare($sql);

            // Bind all parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }

            $stmt->execute();
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count for pagination
            $countSql = "SELECT COUNT(*) as total FROM `{$table}` WHERE {$whereClause}";
            $countStmt = $this->db->prepare($countSql);

            // Bind all parameters for count
            foreach ($params as $key => $value) {
                $countStmt->bindValue(":{$key}", $value);
            }

            $countStmt->execute();
            $totalCount = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];

            $pagination = [
                'current_page' => $page,
                'per_page' => $limitSql,
                'total' => $totalCount,
                'total_pages' => ceil($totalCount / $limitSql)
            ];

            $this->sendResponse([
                'data' => $records,
                'pagination' => $pagination
            ]);
        } catch (Exception $e) {
            $this->logger->error('Get list error', [
                'table' => $table,
                'error' => $e->getMessage(),
                'input' => $input
            ]);
            $this->sendResponse(['error' => 'Failed to retrieve records'], 500);
        }
    }

    /**
     * Create a new record
     */
    private function createRecord($table, $input, $userId, bool $isAdmin = false)
    {
        try {
            if (empty($input)) {
                $this->sendResponse(['error' => 'No data provided'], 400);
                return;
            }

            // Filter input to only allow valid field names
            $filteredInput = $this->filterValidFields($table, $input);

            if (empty($filteredInput)) {
                $this->sendResponse(['error' => 'No valid fields provided'], 400);
                return;
            }

            // RBAC: enforce ownership column on insert
            $filteredInput = $this->enforceOwnershipOnInsert($table, $filteredInput, $userId, $isAdmin);

            $columns = array_keys($filteredInput);
            $placeholders = ':' . implode(', :', $columns);

            $backtickColumns = array_map(fn($c) => "`{$c}`", $columns);
            $sql = "INSERT INTO `{$table}` (" . implode(', ', $backtickColumns) . ") VALUES ({$placeholders})";

            $stmt = $this->db->prepare($sql);
            $stmt->execute($filteredInput);

            $newId = $this->db->lastInsertId();

            // Retrieve the newly created record
            $stmt = $this->db->prepare("SELECT * FROM `{$table}` WHERE `id` = :id LIMIT 1");
            $stmt->execute(['id' => $newId]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->logger->info('Record created', [
                'table' => $table,
                'id' => $newId,
                'user_id' => $userId
            ]);

            $this->sendResponse([
                'message' => 'Record created successfully',
                'data' => $record
            ], 201);
        } catch (Exception $e) {
            $this->logger->error('Create record error', [
                'table' => $table,
                'error' => $e->getMessage(),
                'input' => $input
            ]);
            $this->sendResponse(['error' => 'Failed to create record'], 500);
        }
    }

    /**
     * Update an existing record
     */
    private function updateRecord($table, $id, $input, $userId, bool $isAdmin = false)
    {
        try {
            if (empty($input)) {
                $this->sendResponse(['error' => 'No data provided'], 400);
                return;
            }

            // Filter input to only allow valid field names
            $filteredInput = $this->filterValidFields($table, $input);

            if (empty($filteredInput)) {
                $this->sendResponse(['error' => 'No valid fields provided'], 400);
                return;
            }

            // RBAC: build ownership WHERE clause
            $ownerWhere = $this->buildOwnershipWhere($table, $userId, $isAdmin);

            $setColumns = array_map(fn($k) => "`{$k}` = ?", array_keys($filteredInput));
            $setClause = implode(', ', $setColumns);
            $sql = "UPDATE `{$table}` SET {$setClause} WHERE `id` = ?" . $ownerWhere['clause'];

            $values = array_values($filteredInput);
            $values[] = $id;
            // Append ownership params as positional
            foreach ($ownerWhere['params'] as $val) {
                $values[] = $val;
            }

            // Need to convert named owner params to positional for this query
            // Rebuild SQL with positional placeholders for ownership
            $ownerPositional = $this->buildOwnershipWherePositional($table, $userId, $isAdmin);
            $sql = "UPDATE `{$table}` SET {$setClause} WHERE `id` = ?" . $ownerPositional['clause'];
            $values = array_values($filteredInput);
            $values[] = $id;
            $values = array_merge($values, $ownerPositional['values']);

            $stmt = $this->db->prepare($sql);
            $stmt->execute($values);

            if ($stmt->rowCount() === 0) {
                $this->sendResponse(['error' => 'Record not found'], 404);
                return;
            }

            // Retrieve the updated record
            $stmt = $this->db->prepare("SELECT * FROM `{$table}` WHERE `id` = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
            $record = $stmt->fetch(PDO::FETCH_ASSOC);

            $this->logger->info('Record updated', [
                'table' => $table,
                'id' => $id,
                'user_id' => $userId
            ]);

            $this->sendResponse([
                'message' => 'Record updated successfully',
                'data' => $record
            ]);
        } catch (Exception $e) {
            $this->logger->error('Update record error', [
                'table' => $table,
                'id' => $id,
                'error' => $e->getMessage(),
                'input' => $input
            ]);
            $this->sendResponse(['error' => 'Failed to update record'], 500);
        }
    }

    /**
     * Delete a record
     */
    private function deleteRecord($table, $id, $userId, bool $isAdmin = false)
    {
        try {
            // RBAC: check record exists AND belongs to user
            $ownerWhere = $this->buildOwnershipWhere($table, $userId, $isAdmin);
            $checkSql = "SELECT `id` FROM `{$table}` WHERE `id` = :id" . $ownerWhere['clause'] . " LIMIT 1";
            $params = array_merge(['id' => $id], $ownerWhere['params']);

            $stmt = $this->db->prepare($checkSql);
            $stmt->execute($params);
            $exists = $stmt->fetch();

            if (!$exists) {
                $this->sendResponse(['error' => 'Record not found'], 404);
                return;
            }

            $stmt = $this->db->prepare("DELETE FROM `{$table}` WHERE `id` = :id" . $ownerWhere['clause']);
            $stmt->execute($params);

            $this->logger->info('Record deleted', [
                'table' => $table,
                'id' => $id,
                'user_id' => $userId
            ]);

            $this->sendResponse([
                'message' => 'Record deleted successfully'
            ]);
        } catch (Exception $e) {
            $this->logger->error('Delete record error', [
                'table' => $table,
                'id' => $id,
                'error' => $e->getMessage()
            ]);
            $this->sendResponse(['error' => 'Failed to delete record'], 500);
        }
    }

    /**
     * Authenticate the incoming request
     */
    private function authenticateRequest()
    {
        $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

        if (empty($authHeader)) {
            return ['success' => false, 'message' => 'Authorization header required'];
        }

        // Handle "Bearer <token>" format
        if (preg_match('/^Bearer\s+(.*)$/i', $authHeader, $matches)) {
            $token = $matches[1];
        } else {
            return ['success' => false, 'message' => 'Invalid authorization format'];
        }

        try {
            $payload = $this->jwtService->validateToken($token);
            if (!$payload) {
                return ['success' => false, 'message' => 'Token inválido ou expirado'];
            }

            $userId = $this->jwtService->getUserIdFromToken($token);
            if ($userId === null || $userId <= 0) {
                return ['success' => false, 'message' => 'Token sem usuário válido'];
            }

            return ['success' => true, 'user_id' => $userId];
        } catch (Exception $e) {
            return ['success' => false, 'message' => 'Authentication failed'];
        }
    }

    /**
     * Check rate limit for the user
     */
    private function checkRateLimit($userId)
    {
        try {
            $this->ensureRateLimitTable();

            $ip = $this->getClientIp();
            $windowSeconds = 3600;
            $maxRequests = 100;
            $cutoff = date('Y-m-d H:i:s', time() - $windowSeconds);

            // Rate limit by user_id + IP combination for defense-in-depth
            $identifier = 'u' . $userId . '_' . $ip;

            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as count FROM rate_limits WHERE ip_address = :identifier AND created_at > :cutoff"
            );
            $stmt->execute([
                'identifier' => $identifier,
                'cutoff' => $cutoff,
            ]);

            $count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

            if ($count >= $maxRequests) {
                return ['allowed' => false, 'remaining' => 0, 'reset_time' => time() + $windowSeconds];
            }

            $insertStmt = $this->db->prepare(
                "INSERT INTO rate_limits (ip_address, created_at) VALUES (:identifier, NOW())"
            );
            $insertStmt->execute(['identifier' => $identifier]);

            return [
                'allowed' => true,
                'remaining' => max(0, $maxRequests - ($count + 1)),
                'reset_time' => time() + $windowSeconds,
            ];
        } catch (Exception $e) {
            // If rate limiting fails, allow request but log it
            $this->logger->warning('Rate limit check failed', ['error' => $e->getMessage()]);
            return ['allowed' => true, 'remaining' => 0, 'reset_time' => 0];
        }
    }

    /**
     * Obtém IP real do cliente
     */
    private function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_FORWARDED_FOR',
            'HTTP_X_REAL_IP',
            'HTTP_CLIENT_IP',
            'REMOTE_ADDR',
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '0.0.0.0';
    }

    /**
     * Garante que a tabela de rate limit exista (once per process)
     */
    private function ensureRateLimitTable(): void
    {
        if (self::$rateLimitTableVerified) {
            return;
        }

        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS rate_limits (
                id INT PRIMARY KEY AUTO_INCREMENT,
                ip_address VARCHAR(100) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ip_created (ip_address, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        self::$rateLimitTableVerified = true;
    }

    /**
     * Validate table name for security
     */
    private function isValidTableName($table)
    {
        return in_array($table, $this->allowedTables);
    }

    /**
     * Validate field name for security
     */
    private function isValidFieldName($field)
    {
        // Only allow alphanumeric characters and underscores (no hyphens — MySQL parsing issues)
        return preg_match('/^[a-zA-Z][a-zA-Z0-9_]{0,63}$/', $field);
    }

    /**
     * Filter input to only include valid field names
     */
    private function filterValidFields($table, $input)
    {
        $filtered = [];

        foreach ($input as $field => $value) {
            if ($this->isValidFieldName($field) && $field !== 'id') { // Don't allow ID updates
                $filtered[$field] = $value;
            }
        }

        return $filtered;
    }

    /**
     * Check if user is admin
     */
    private function isUserAdmin(int $userId): bool
    {
        try {
            $stmt = $this->db->prepare(
                "SELECT is_admin, role FROM users WHERE id = :id LIMIT 1"
            );
            $stmt->execute(['id' => $userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                return false;
            }

            return !empty($user['is_admin']) || ($user['role'] ?? '') === 'admin';
        } catch (Exception $e) {
            $this->logger->warning('isUserAdmin check failed', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Check table-level access based on ACL.
     * Returns false if access is denied.
     */
    private function checkTableAccess(string $table, int $userId, bool $isAdmin, bool $isWrite): bool
    {
        $acl = $this->tableACL[$table] ?? null;
        if ($acl === null) {
            return false; // Unknown table
        }

        $mode = $acl['mode'];

        // Admin always has access
        if ($isAdmin) {
            return true;
        }

        return match ($mode) {
            'admin' => false, // Non-admin denied
            'read_only' => !$isWrite, // Non-admin can only read
            'owner', 'self' => true, // Row-level check happens in buildOwnershipWhere
            default => false,
        };
    }

    /**
     * Build ownership WHERE clause for row-level access control (named params).
     *
     * When owner_column is 'account_id', resolves the user's ML account IDs
     * and filters with IN() clause. Optionally scoped to activeMLAccountId.
     *
     * @return array{clause: string, params: array}
     */
    private function buildOwnershipWhere(string $table, int $userId, bool $isAdmin): array
    {
        // Admin bypasses row-level checks
        if ($isAdmin) {
            return ['clause' => '', 'params' => []];
        }

        $acl = $this->tableACL[$table] ?? null;
        if ($acl === null) {
            // Deny all — unknown table should never reach here
            return ['clause' => ' AND 1=0', 'params' => []];
        }

        $mode = $acl['mode'];

        if ($mode === 'self') {
            // 'self' tables (e.g. users): user can only see their own row by id
            return [
                'clause' => ' AND `id` = :rbac_owner_id',
                'params' => ['rbac_owner_id' => $userId],
            ];
        }

        if ($mode === 'owner') {
            $col = $acl['owner_column'] ?? 'user_id';
            if (!$this->isValidFieldName($col)) {
                return ['clause' => ' AND 1=0', 'params' => []];
            }

            // For account_id columns, resolve to user's ML account IDs
            if ($col === 'account_id') {
                // If a specific ML account is active, scope to that
                if ($this->activeMLAccountId !== null) {
                    return [
                        'clause' => " AND `{$col}` = :rbac_account_id",
                        'params' => ['rbac_account_id' => $this->activeMLAccountId],
                    ];
                }

                // Otherwise, use all user's ML account IDs
                $accountIds = $this->userMLAccountIds;
                if (empty($accountIds)) {
                    // User has no ML accounts — deny access to account-scoped tables
                    return ['clause' => ' AND 1=0', 'params' => []];
                }

                // Build IN clause with named params
                $placeholders = [];
                $params = [];
                foreach ($accountIds as $i => $accId) {
                    $key = 'rbac_acc_' . $i;
                    $placeholders[] = ':' . $key;
                    $params[$key] = $accId;
                }
                return [
                    'clause' => " AND `{$col}` IN (" . implode(', ', $placeholders) . ')',
                    'params' => $params,
                ];
            }

            // For user_id columns (ml_accounts, notifications), filter directly
            return [
                'clause' => " AND `{$col}` = :rbac_owner_id",
                'params' => ['rbac_owner_id' => $userId],
            ];
        }

        // read_only and admin modes — no extra WHERE needed (table-level check already passed)
        return ['clause' => '', 'params' => []];
    }

    /**
     * Build ownership WHERE clause with positional (?) placeholders.
     * Used by UPDATE which mixes positional params.
     *
     * @return array{clause: string, values: array}
     */
    private function buildOwnershipWherePositional(string $table, int $userId, bool $isAdmin): array
    {
        if ($isAdmin) {
            return ['clause' => '', 'values' => []];
        }

        $acl = $this->tableACL[$table] ?? null;
        if ($acl === null) {
            return ['clause' => ' AND 1=0', 'values' => []];
        }

        $mode = $acl['mode'];

        if ($mode === 'self') {
            return ['clause' => ' AND `id` = ?', 'values' => [$userId]];
        }

        if ($mode === 'owner') {
            $col = $acl['owner_column'] ?? 'user_id';
            if (!$this->isValidFieldName($col)) {
                return ['clause' => ' AND 1=0', 'values' => []];
            }

            // For account_id columns, resolve to user's ML account IDs
            if ($col === 'account_id') {
                if ($this->activeMLAccountId !== null) {
                    return ['clause' => " AND `{$col}` = ?", 'values' => [$this->activeMLAccountId]];
                }

                $accountIds = $this->userMLAccountIds;
                if (empty($accountIds)) {
                    return ['clause' => ' AND 1=0', 'values' => []];
                }

                $placeholders = implode(', ', array_fill(0, count($accountIds), '?'));
                return [
                    'clause' => " AND `{$col}` IN ({$placeholders})",
                    'values' => $accountIds,
                ];
            }

            return ['clause' => " AND `{$col}` = ?", 'values' => [$userId]];
        }

        return ['clause' => '', 'values' => []];
    }

    /**
     * Enforce ownership column on INSERT — automatically set the ownership column
     * to the authenticated user's ID so users cannot create records for others.
     *
     * For account_id tables, uses the active ML account (or first available).
     */
    private function enforceOwnershipOnInsert(string $table, array $input, int $userId, bool $isAdmin): array
    {
        if ($isAdmin) {
            return $input; // Admin can set any owner
        }

        $acl = $this->tableACL[$table] ?? null;
        if ($acl === null) {
            return $input;
        }

        $mode = $acl['mode'];

        if ($mode === 'owner') {
            $col = $acl['owner_column'] ?? 'user_id';

            if ($col === 'account_id') {
                // Resolve to active ML account or validate caller-supplied value
                if ($this->activeMLAccountId !== null) {
                    $input[$col] = $this->activeMLAccountId;
                } elseif (isset($input[$col])) {
                    // Validate that the supplied account_id belongs to this user
                    $suppliedId = (int) $input[$col];
                    if (!in_array($suppliedId, $this->userMLAccountIds, true)) {
                        // Replace with first available account or block
                        $input[$col] = $this->userMLAccountIds[0] ?? 0;
                    }
                } elseif (!empty($this->userMLAccountIds)) {
                    $input[$col] = $this->userMLAccountIds[0];
                }
            } else {
                // user_id columns — force to authenticated user
                $input[$col] = $userId;
            }
        }

        return $input;
    }

    /**
     * Resolve ML account context for the authenticated user.
     *
     * Sets $this->userMLAccountIds (all ML accounts for the user) and
     * $this->activeMLAccountId (specific account from header/query, validated).
     */
    private function resolveMLAccountContext(int $userId): void
    {
        $this->userMLAccountIds = $this->getUserMLAccountIds($userId);

        // Check for specific ML account selection via header or query
        $headerAccountId = (int) ($_SERVER['HTTP_X_ML_ACCOUNT_ID'] ?? 0);
        $queryAccountId = (int) ($_GET['ml_account_id'] ?? 0);
        $requestedId = $headerAccountId > 0 ? $headerAccountId : ($queryAccountId > 0 ? $queryAccountId : 0);

        if ($requestedId > 0) {
            // Validate that the requested account belongs to this user
            if (in_array($requestedId, $this->userMLAccountIds, true)) {
                $this->activeMLAccountId = $requestedId;
            } else {
                $this->logger->warning('ML account access denied — account does not belong to user', [
                    'user_id' => $userId,
                    'requested_account_id' => $requestedId,
                    'user_accounts' => $this->userMLAccountIds,
                ]);
                // Don't set — deny scoping to accounts the user doesn't own
            }
        }
    }

    /**
     * Get all ML account IDs belonging to a user.
     *
     * @return array<int>
     */
    private function getUserMLAccountIds(int $userId): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT id FROM ml_accounts WHERE user_id = :user_id ORDER BY id'
            );
            $stmt->execute(['user_id' => $userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_COLUMN, 0);

            return array_map('intval', $rows);
        } catch (Exception $e) {
            $this->logger->warning('Failed to resolve ML accounts for user', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            return [];
        }
    }

    // =========================================================================
    //  Mercado Livre API Proxy
    // =========================================================================

    /**
     * Route ML API proxy requests: /api/v1/ml/{resource}[/{id}][/{sub}]
     *
     * Proxies requests to Mercado Livre API via MercadoLivreClient,
     * using the authenticated user's ML account for authorization.
     * Implements:
     *  - ML-specific rate limiting (separate from DB API)
     *  - Automatic token refresh on 401
     *  - Accounts listing endpoint (no external call)
     */
    private function routeMLRequest(string $method, array $segments, array $input, int $userId): void
    {
        // segments: ['api', 'v1', 'ml', resource, id?, sub?]
        $resource = $segments[3] ?? '';
        $resourceId = $segments[4] ?? null;
        $subResource = $segments[5] ?? null;

        if ($resource === '') {
            $this->sendResponse(['error' => 'ML resource required (e.g. /api/v1/ml/items)'], 400);
            return;
        }

        // Handle 'accounts' locally — no MercadoLivreClient needed
        if ($resource === 'accounts') {
            $result = $this->handleMLAccounts($method, $userId, $resourceId, $input);
            $this->sendResponse($result);
            return;
        }

        // Handle 'notifications' locally — reads from local DB
        if ($resource === 'notifications') {
            $result = $this->handleMLNotifications($method, $resourceId, $input);
            $this->sendResponse($result);
            return;
        }

        // Resolve which ML account to use
        $accountId = $this->activeMLAccountId;
        if ($accountId === null && !empty($this->userMLAccountIds)) {
            $accountId = $this->userMLAccountIds[0]; // Default to first account
        }

        if ($accountId === null) {
            $this->sendResponse([
                'error' => 'No ML account configured',
                'message' => 'Vincule uma conta do Mercado Livre antes de usar a API.',
            ], 422);
            return;
        }

        // ML-specific rate limiting (separate from DB API limiter)
        $mlRateResult = $this->checkMLRateLimit($userId, $accountId);
        if (!$mlRateResult['allowed']) {
            $this->sendResponse([
                'error' => 'ML API rate limit exceeded',
                'message' => 'Limite de requisições para a API do Mercado Livre atingido.',
                'retry_after' => $mlRateResult['retry_after'] ?? 60,
                'limit' => $this->mlRateLimitMax,
                'remaining' => 0,
            ], 429);
            return;
        }

        // Ensure token is valid before making the call (auto-refresh if needed)
        $this->ensureMLToken($accountId);

        try {
            $client = new MercadoLivreClient($accountId);

            if ($client->getAccessToken() === '') {
                $this->sendResponse([
                    'error' => 'ML account not configured',
                    'message' => 'A conta ML selecionada não possui token válido.',
                    'account_id' => $accountId,
                ], 401);
                return;
            }

            $result = $this->dispatchMLRequest($client, $method, $resource, $resourceId, $subResource, $input);

            // Add rate limit headers to response
            header('X-ML-RateLimit-Limit: ' . $this->mlRateLimitMax);
            header('X-ML-RateLimit-Remaining: ' . ($mlRateResult['remaining'] ?? 0));
            header('X-ML-Account-Id: ' . $accountId);

            $this->sendResponse($result);
        } catch (\GuzzleHttp\Exception\ClientException $e) {
            $response = $e->getResponse();
            $statusCode = $response ? $response->getStatusCode() : 500;
            $body = $response ? json_decode((string) $response->getBody(), true) : [];

            // Auto-refresh on 401 and retry ONCE
            if ($statusCode === 401) {
                $this->logger->info('ML API 401 — attempting token refresh', [
                    'account_id' => $accountId,
                    'resource' => $resource,
                ]);

                $refreshed = $this->refreshMLToken($accountId);
                if ($refreshed) {
                    try {
                        $retryClient = new MercadoLivreClient($accountId);
                        if ($retryClient->getAccessToken() !== '') {
                            $result = $this->dispatchMLRequest($retryClient, $method, $resource, $resourceId, $subResource, $input);
                            $this->sendResponse($result);
                            return;
                        }
                    } catch (\GuzzleHttp\Exception\ClientException $retryEx) {
                        // Retry also failed — fall through to error response
                        $response = $retryEx->getResponse();
                        $statusCode = $response ? $response->getStatusCode() : 500;
                        $body = $response ? json_decode((string) $response->getBody(), true) : [];
                    } catch (Exception $retryEx) {
                        $this->logger->error('ML API retry failed after token refresh', [
                            'error' => $retryEx->getMessage(),
                            'account_id' => $accountId,
                        ]);
                    }
                }
            }

            $this->logger->warning('ML API client error', [
                'resource' => $resource,
                'status' => $statusCode,
                'error' => $body['message'] ?? $e->getMessage(),
                'account_id' => $accountId,
                'user_id' => $userId,
            ]);

            $this->sendResponse([
                'error' => $body['error'] ?? 'ml_api_error',
                'message' => $body['message'] ?? $e->getMessage(),
                'status' => $statusCode,
            ], $statusCode);
        } catch (Exception $e) {
            $this->logger->error('ML API proxy error', [
                'resource' => $resource,
                'error' => $e->getMessage(),
                'account_id' => $accountId,
                'user_id' => $userId,
            ]);

            $this->sendResponse([
                'error' => 'ml_proxy_error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Dispatch ML API request to appropriate MercadoLivreClient method.
     *
     * @return array API response data
     */
    private function dispatchMLRequest(
        MercadoLivreClient $client,
        string $method,
        string $resource,
        ?string $resourceId,
        ?string $subResource,
        array $input
    ): array {
        return match ($resource) {
            'items' => $this->handleMLItems($client, $method, $resourceId, $subResource, $input),
            'search' => $this->handleMLSearch($client, $input),
            'orders' => $this->handleMLOrders($client, $method, $resourceId, $input),
            'questions' => $this->handleMLQuestions($client, $method, $resourceId, $input),
            'categories' => $this->handleMLCategories($client, $resourceId, $subResource, $input),
            'trends' => $this->handleMLTrends($client, $resourceId),
            'me' => $client->getMe(),
            'diagnose' => $client->diagnose(),
            'competitors' => $this->handleMLCompetitors($client, $input),
            default => throw new Exception("Unknown ML resource: {$resource}"),
        };
    }

    /**
     * Handle /api/v1/ml/items[/{id}][/{sub}]
     */
    private function handleMLItems(
        MercadoLivreClient $client,
        string $method,
        ?string $itemId,
        ?string $subResource,
        array $input
    ): array {
        if ($method === 'GET') {
            if ($itemId === null) {
                // GET /ml/items → list seller's items
                $params = array_intersect_key($input, array_flip(['status', 'limit', 'offset', 'category']));
                return $client->getMyItems($params);
            }

            if ($subResource === 'health') {
                return $client->getItemHealth($itemId);
            }

            if ($subResource === 'description') {
                return $client->get("/items/{$itemId}/description", [], 300, true);
            }

            // GET /ml/items/{id} → item details
            return $client->getItemDetails($itemId);
        }

        if ($method === 'PUT' || $method === 'PATCH') {
            if ($itemId === null) {
                throw new Exception('Item ID required for update');
            }

            if ($subResource === 'description') {
                $plainText = $input['plain_text'] ?? $input['description'] ?? '';
                if ($plainText === '') {
                    throw new Exception('plain_text or description field required');
                }
                return $client->updateDescription($itemId, $plainText);
            }

            // PUT /ml/items/{id} → update item
            return $client->updateItem($itemId, $input);
        }

        throw new Exception("Method {$method} not supported for items");
    }

    /**
     * Handle /api/v1/ml/search?q=...&category=...
     */
    private function handleMLSearch(MercadoLivreClient $client, array $input): array
    {
        $keyword = $input['q'] ?? $input['keyword'] ?? '';
        $category = $input['category'] ?? $input['category_id'] ?? '';
        $limit = (int) ($input['limit'] ?? 20);

        if ($keyword === '' && $category === '') {
            throw new Exception('Search requires q (keyword) or category parameter');
        }

        if ($keyword !== '' && $category !== '') {
            return $client->searchByKeyword($keyword, $category, $limit);
        }

        $params = array_intersect_key($input, array_flip(['q', 'category', 'limit', 'offset', 'sort']));
        return $client->searchItems($params);
    }

    /**
     * Handle /api/v1/ml/orders[/{id}]
     */
    private function handleMLOrders(MercadoLivreClient $client, string $method, ?string $orderId, array $input): array
    {
        if ($method !== 'GET') {
            throw new Exception('Only GET is supported for orders');
        }

        $sellerId = $client->getSellerId();
        if (empty($sellerId)) {
            return ['error' => 'seller_not_found', 'orders' => []];
        }

        if ($orderId !== null) {
            return $client->get("/orders/{$orderId}");
        }

        // List orders with optional filters
        $params = [
            'seller' => $sellerId,
            'sort' => $input['sort'] ?? 'date_desc',
        ];

        if (!empty($input['status'])) {
            $params['order.status'] = $input['status'];
        }

        $limit = min((int) ($input['limit'] ?? 50), 50);
        $offset = (int) ($input['offset'] ?? 0);
        $params['limit'] = $limit;
        $params['offset'] = $offset;

        return $client->get('/orders/search', $params);
    }

    /**
     * Handle /api/v1/ml/questions[/{id}]
     */
    private function handleMLQuestions(MercadoLivreClient $client, string $method, ?string $questionId, array $input): array
    {
        $sellerId = $client->getSellerId();
        if (empty($sellerId)) {
            return ['error' => 'seller_not_found', 'questions' => []];
        }

        if ($method === 'GET') {
            if ($questionId !== null) {
                return $client->get("/questions/{$questionId}");
            }

            // List unanswered questions
            $params = [
                'seller_id' => $sellerId,
                'status' => $input['status'] ?? 'UNANSWERED',
                'sort_fields' => 'date_created',
                'sort_types' => 'DESC',
                'limit' => min((int) ($input['limit'] ?? 50), 50),
                'offset' => (int) ($input['offset'] ?? 0),
            ];

            if (!empty($input['item_id'])) {
                $params['item'] = $input['item_id'];
            }

            return $client->get('/questions/search', $params);
        }

        if ($method === 'POST' && $questionId !== null) {
            // Answer a question
            $answerText = $input['text'] ?? $input['answer'] ?? '';
            if ($answerText === '') {
                throw new Exception('Answer text required');
            }

            return $client->post("/answers", [
                'question_id' => (int) $questionId,
                'text' => $answerText,
            ]);
        }

        throw new Exception("Method {$method} not supported for questions");
    }

    /**
     * Handle /api/v1/ml/categories[/{id}][/attributes]
     */
    private function handleMLCategories(MercadoLivreClient $client, ?string $categoryId, ?string $sub, array $input): array
    {
        if ($categoryId === null) {
            // List root categories
            $siteId = $input['site_id'] ?? 'MLB';
            return $client->get("/sites/{$siteId}/categories", [], 3600, true);
        }

        if ($sub === 'attributes') {
            return $client->getCategoryAttributes($categoryId);
        }

        return $client->getCategory($categoryId);
    }

    /**
     * Handle /api/v1/ml/trends/{categoryId}
     */
    private function handleMLTrends(MercadoLivreClient $client, ?string $categoryId): array
    {
        if ($categoryId === null) {
            throw new Exception('Category ID required for trends');
        }

        $trends = $client->getTrends($categoryId);
        return ['trends' => $trends, 'category_id' => $categoryId];
    }

    /**
     * Handle /api/v1/ml/competitors?keyword=...&category=...
     */
    private function handleMLCompetitors(MercadoLivreClient $client, array $input): array
    {
        $keyword = $input['keyword'] ?? $input['q'] ?? '';
        $category = $input['category'] ?? $input['category_id'] ?? '';

        if ($keyword === '' || $category === '') {
            throw new Exception('keyword and category parameters required for competitor analysis');
        }

        return $client->getCompetitorAnalysis($keyword, $category);
    }

    // =========================================================================
    //  ML Account Management
    // =========================================================================

    /**
     * Handle /api/v1/ml/accounts[/{id}]
     *
     * Lists user's ML accounts with token status, or returns details for one.
     * No external ML API call — reads from local DB only.
     *
     * GET /accounts       → list all user's accounts
     * GET /accounts/{id}  → single account detail
     * PUT /accounts/{id}  → set as active account (session/preference)
     */
    private function handleMLAccounts(string $method, int $userId, ?string $accountIdStr, array $input): array
    {
        if ($method === 'GET') {
            if ($accountIdStr !== null) {
                return $this->getMLAccountDetail($userId, (int) $accountIdStr);
            }
            return $this->listMLAccounts($userId);
        }

        if (($method === 'PUT' || $method === 'PATCH') && $accountIdStr !== null) {
            return $this->setActiveMLAccount($userId, (int) $accountIdStr);
        }

        throw new Exception("Method {$method} not supported for accounts");
    }

    /**
     * List all ML accounts for the authenticated user.
     *
     * @return array{accounts: array, active_account_id: int|null, total: int}
     */
    private function listMLAccounts(int $userId): array
    {
        try {
            $stmt = $this->db->prepare(
                'SELECT id, ml_user_id, nickname, email, site_id, status,
                        token_expires_at, tokens_encrypted, created_at, updated_at
                 FROM ml_accounts
                 WHERE user_id = :user_id
                 ORDER BY id'
            );
            $stmt->execute(['user_id' => $userId]);
            $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

            $accounts = [];
            $now = time();

            foreach ($rows as $row) {
                $expiresAt = $row['token_expires_at'] ? strtotime($row['token_expires_at']) : null;
                $tokenStatus = 'unknown';

                if ($row['status'] === 'disconnected' || $row['status'] === 'expired') {
                    $tokenStatus = $row['status'];
                } elseif ($expiresAt !== null) {
                    $secondsLeft = $expiresAt - $now;
                    if ($secondsLeft <= 0) {
                        $tokenStatus = 'expired';
                    } elseif ($secondsLeft <= 3600) {
                        $tokenStatus = 'expiring_soon';
                    } else {
                        $tokenStatus = 'valid';
                    }
                }

                $accounts[] = [
                    'id' => (int) $row['id'],
                    'ml_user_id' => $row['ml_user_id'],
                    'nickname' => $row['nickname'],
                    'email' => $row['email'],
                    'site_id' => $row['site_id'],
                    'status' => $row['status'],
                    'token_status' => $tokenStatus,
                    'token_expires_at' => $row['token_expires_at'],
                    'is_active' => ($this->activeMLAccountId === (int) $row['id']),
                    'created_at' => $row['created_at'],
                    'updated_at' => $row['updated_at'],
                ];
            }

            return [
                'accounts' => $accounts,
                'active_account_id' => $this->activeMLAccountId,
                'total' => count($accounts),
            ];
        } catch (Exception $e) {
            $this->logger->error('Failed to list ML accounts', [
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw new Exception('Failed to list ML accounts');
        }
    }

    /**
     * Get detailed info for a specific ML account (validates ownership).
     */
    private function getMLAccountDetail(int $userId, int $accountId): array
    {
        if (!in_array($accountId, $this->userMLAccountIds, true)) {
            throw new Exception('ML account not found or access denied');
        }

        try {
            $stmt = $this->db->prepare(
                'SELECT id, ml_user_id, nickname, email, site_id, status,
                        token_expires_at, tokens_encrypted, created_at, updated_at
                 FROM ml_accounts
                 WHERE id = :id AND user_id = :user_id
                 LIMIT 1'
            );
            $stmt->execute(['id' => $accountId, 'user_id' => $userId]);
            $row = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$row) {
                throw new Exception('ML account not found');
            }

            $expiresAt = $row['token_expires_at'] ? strtotime($row['token_expires_at']) : null;
            $secondsLeft = $expiresAt !== null ? max(0, $expiresAt - time()) : null;

            return [
                'id' => (int) $row['id'],
                'ml_user_id' => $row['ml_user_id'],
                'nickname' => $row['nickname'],
                'email' => $row['email'],
                'site_id' => $row['site_id'],
                'status' => $row['status'],
                'token_expires_at' => $row['token_expires_at'],
                'token_seconds_remaining' => $secondsLeft,
                'is_active' => ($this->activeMLAccountId === $accountId),
                'created_at' => $row['created_at'],
                'updated_at' => $row['updated_at'],
            ];
        } catch (Exception $e) {
            $this->logger->error('Failed to get ML account detail', [
                'account_id' => $accountId,
                'user_id' => $userId,
                'error' => $e->getMessage(),
            ]);
            throw $e;
        }
    }

    /**
     * Set a specific ML account as the "active" one (stored in session if available).
     */
    private function setActiveMLAccount(int $userId, int $accountId): array
    {
        if (!in_array($accountId, $this->userMLAccountIds, true)) {
            throw new Exception('ML account not found or access denied');
        }

        // Store in session for subsequent requests within the same browser session
        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['active_ml_account_id'] = $accountId;
        }

        $this->activeMLAccountId = $accountId;

        $this->logger->info('ML active account changed', [
            'user_id' => $userId,
            'account_id' => $accountId,
        ]);

        return [
            'success' => true,
            'active_account_id' => $accountId,
            'message' => 'Conta ML ativa alterada com sucesso.',
        ];
    }

    // =========================================================================
    //  ML Notifications (local webhook log)
    // =========================================================================

    /**
     * Handle /api/v1/ml/notifications[/{id}]
     *
     * Lists recent ML webhook notifications stored locally.
     * Only returns notifications for the user's ML accounts.
     *
     * GET /notifications        → list recent notifications
     * GET /notifications/{id}   → single notification detail
     */
    private function handleMLNotifications(string $method, ?string $notificationId, array $input): array
    {
        if ($method !== 'GET') {
            throw new Exception('Only GET is supported for notifications');
        }

        if (empty($this->userMLAccountIds)) {
            return ['notifications' => [], 'total' => 0];
        }

        try {
            if ($notificationId !== null) {
                return $this->getMLNotificationDetail((int) $notificationId);
            }
            return $this->listMLNotifications($input);
        } catch (Exception $e) {
            $this->logger->warning('Failed to fetch ML notifications', [
                'error' => $e->getMessage(),
            ]);
            return ['notifications' => [], 'total' => 0, 'error' => $e->getMessage()];
        }
    }

    /**
     * List recent ML webhook notifications for the user's accounts.
     */
    private function listMLNotifications(array $input): array
    {
        $limit = min((int) ($input['limit'] ?? 50), 100);
        $offset = (int) ($input['offset'] ?? 0);
        $topic = $input['topic'] ?? null;

        // Build IN clause for user's account IDs
        $placeholders = [];
        $params = [];
        foreach ($this->userMLAccountIds as $i => $accId) {
            $key = ':acc_' . $i;
            $placeholders[] = $key;
            $params[$key] = $accId;
        }
        $inClause = implode(', ', $placeholders);

        // Check if the webhook_logs / ml_webhook_events table exists
        // We try ml_webhook_events first (custom), then webhook_logs (generic)
        $table = $this->detectNotificationTable();
        if ($table === null) {
            return ['notifications' => [], 'total' => 0, 'message' => 'No webhook log table found'];
        }

        $where = "account_id IN ({$inClause})";
        if ($topic !== null && $topic !== '') {
            $where .= ' AND topic = :topic';
            $params[':topic'] = $topic;
        }

        $params[':limit'] = $limit;
        $params[':offset'] = $offset;

        $sql = "SELECT * FROM {$table} WHERE {$where} ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Count total
        $countSql = "SELECT COUNT(*) FROM {$table} WHERE {$where}";
        unset($params[':limit'], $params[':offset']);
        $countStmt = $this->db->prepare($countSql);
        $countStmt->execute($params);
        $total = (int) $countStmt->fetchColumn();

        return [
            'notifications' => $rows,
            'total' => $total,
            'limit' => $limit,
            'offset' => $offset,
        ];
    }

    /**
     * Get a single notification detail.
     */
    private function getMLNotificationDetail(int $notificationId): array
    {
        $table = $this->detectNotificationTable();
        if ($table === null) {
            throw new Exception('No webhook log table found');
        }

        // Build IN clause for ownership validation
        $placeholders = [];
        $params = [':id' => $notificationId];
        foreach ($this->userMLAccountIds as $i => $accId) {
            $key = ':acc_' . $i;
            $placeholders[] = $key;
            $params[$key] = $accId;
        }
        $inClause = implode(', ', $placeholders);

        $stmt = $this->db->prepare(
            "SELECT * FROM {$table} WHERE id = :id AND account_id IN ({$inClause}) LIMIT 1"
        );
        $stmt->execute($params);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$row) {
            throw new Exception('Notification not found');
        }

        return $row;
    }

    /**
     * Detect which notification/webhook log table exists.
     *
     * @return string|null Table name or null if none found
     */
    private function detectNotificationTable(): ?string
    {
        $candidates = ['ml_webhook_events', 'webhook_logs'];

        foreach ($candidates as $table) {
            try {
                $stmt = $this->db->query("SHOW TABLES LIKE '{$table}'");
                if ($stmt->rowCount() > 0) {
                    return $table;
                }
            } catch (Exception $e) {
                // Table doesn't exist, try next
            }
        }

        return null;
    }

    // =========================================================================
    //  ML Rate Limiting
    // =========================================================================

    /**
     * ML-specific rate limiting, separate from DB API rate limiter.
     *
     * - 600 requests/hour per ML account (≈10/min sustained)
     * - Prevents hitting ML API limits (which would ban the access_token)
     * - Tracked per account_id, not per user (a user with 3 accounts gets 3x budget)
     *
     * @return array{allowed: bool, remaining: int, retry_after: int}
     */
    private function checkMLRateLimit(int $userId, int $accountId): array
    {
        try {
            $this->ensureRateLimitTable();

            $cutoff = date('Y-m-d H:i:s', time() - $this->mlRateLimitWindow);
            $identifier = 'ml_' . $accountId;

            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as count FROM rate_limits WHERE ip_address = :identifier AND created_at > :cutoff"
            );
            $stmt->execute([
                'identifier' => $identifier,
                'cutoff' => $cutoff,
            ]);

            $count = (int) ($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

            if ($count >= $this->mlRateLimitMax) {
                $this->logger->warning('ML rate limit exceeded', [
                    'user_id' => $userId,
                    'account_id' => $accountId,
                    'count' => $count,
                    'max' => $this->mlRateLimitMax,
                ]);

                return [
                    'allowed' => false,
                    'remaining' => 0,
                    'retry_after' => 60,
                ];
            }

            // Record this ML API call
            $insertStmt = $this->db->prepare(
                "INSERT INTO rate_limits (ip_address, created_at) VALUES (:identifier, NOW())"
            );
            $insertStmt->execute(['identifier' => $identifier]);

            return [
                'allowed' => true,
                'remaining' => max(0, $this->mlRateLimitMax - ($count + 1)),
                'retry_after' => 0,
            ];
        } catch (Exception $e) {
            // Fail open: if rate limit check fails, allow but log
            $this->logger->warning('ML rate limit check failed', [
                'error' => $e->getMessage(),
                'account_id' => $accountId,
            ]);
            return ['allowed' => true, 'remaining' => 0, 'retry_after' => 0];
        }
    }

    // =========================================================================
    //  ML Token Management
    // =========================================================================

    /**
     * Pre-flight token check: ensures the ML account token is valid.
     *
     * Calls MercadoLivreAuthService::ensureValidToken() which checks
     * token_expires_at and auto-refreshes if within buffer window.
     * This prevents unnecessary 401s from ML API.
     */
    private function ensureMLToken(int $accountId): void
    {
        try {
            $authService = new MercadoLivreAuthService();
            $authService->ensureValidToken($accountId, bufferMinutes: 30);
        } catch (Exception $e) {
            // Non-fatal: if pre-flight refresh fails, the actual request
            // will catch the 401 and retry with refreshMLToken()
            $this->logger->warning('ML token pre-flight check failed', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * Force refresh ML token for an account.
     *
     * Used when the proxy gets a 401 from ML API — refreshes token and
     * returns true if successful, allowing a retry.
     */
    private function refreshMLToken(int $accountId): bool
    {
        try {
            $authService = new MercadoLivreAuthService();
            $refreshed = $authService->refreshToken($accountId);

            if ($refreshed) {
                $this->logger->info('ML token refreshed successfully after 401', [
                    'account_id' => $accountId,
                ]);
            } else {
                $this->logger->warning('ML token refresh failed — account may need re-authorization', [
                    'account_id' => $accountId,
                ]);
            }

            return $refreshed;
        } catch (Exception $e) {
            $this->logger->error('ML token refresh exception', [
                'account_id' => $accountId,
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    /**
     * Get the request path
     */
    private function getPath()
    {
        $requestUri = $_SERVER['REQUEST_URI'] ?? '';
        $scriptName = $_SERVER['SCRIPT_NAME'];

        $basePath = dirname($scriptName);
        $requestPath = substr($requestUri, strlen($basePath));

        // Remove query string
        if (strpos($requestPath, '?') !== false) {
            $requestPath = substr($requestPath, 0, strpos($requestPath, '?'));
        }

        return $requestPath;
    }

    /**
     * Get input data from request
     */
    private function getInput()
    {
        $input = [];

        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (str_contains($contentType, 'application/json')) {
            $rawInput = file_get_contents('php://input');
            if ($rawInput !== false) {
                $input = json_decode($rawInput, true);
            }
        } else {
            $input = $_POST;
        }

        return is_array($input) ? $input : [];
    }

    /**
     * Send JSON response
     */
    private function sendResponse($data, $statusCode = 200)
    {
        http_response_code($statusCode);
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit();
    }
}

// Initialize and run the API
$api = new RealTimeDatabaseAPI();
$api->handleRequest();
