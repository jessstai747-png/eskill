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
use App\Services\StructuredLogService;

class RealTimeDatabaseAPI
{
    private $db;
    private $jwtService;
    private $logger;
    private $allowedTables;
    private $maxResults;

    public function __construct()
    {
        $this->db = Database::getInstance();
        $this->jwtService = new JwtService();
        $this->logger = new StructuredLogService();

        // Define allowed tables for security
        $this->allowedTables = [
            'users',
            'items',
            'orders',
            'products',
            'categories',
            'ml_accounts',
            'account_health_history',
            'seo_analysis_cache',
            'notifications',
            'settings',
            'logs',
            'analytics'
        ];

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
        header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

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

            // Check rate limit
            $rateLimitResult = $this->checkRateLimit($authResult['user_id']);
            if (!$rateLimitResult['allowed']) {
                $this->sendResponse(['error' => 'Rate limit exceeded'], 429);
                return;
            }

            // Route the request
            $this->routeRequest($method, $path, $input, $authResult['user_id']);
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
    private function routeRequest($method, $path, $input, $userId)
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

        // Extract ID if present
        $id = isset($segments[3]) && is_numeric($segments[3]) ? (int)$segments[3] : null;

        switch ($method) {
            case 'GET':
                if ($id) {
                    $this->getRecord($table, $id, $userId);
                } else {
                    $this->getList($table, $input, $userId);
                }
                break;

            case 'POST':
                $this->createRecord($table, $input, $userId);
                break;

            case 'PUT':
            case 'PATCH':
                if (!$id) {
                    $this->sendResponse(['error' => 'ID required for update'], 400);
                    return;
                }
                $this->updateRecord($table, $id, $input, $userId);
                break;

            case 'DELETE':
                if (!$id) {
                    $this->sendResponse(['error' => 'ID required for delete'], 400);
                    return;
                }
                $this->deleteRecord($table, $id, $userId);
                break;

            default:
                $this->sendResponse(['error' => 'Method not allowed'], 405);
                break;
        }
    }

    /**
     * Get a single record by ID
     */
    private function getRecord($table, $id, $userId)
    {
        try {
            $stmt = $this->db->prepare("SELECT * FROM `{$table}` WHERE `id` = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
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
    private function getList($table, $input, $userId)
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

            // Build WHERE clause from filters
            $whereClause = '1=1';
            $params = [];

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

            // Bind filter parameters
            foreach ($params as $key => $value) {
                $stmt->bindValue(":{$key}", $value);
            }

            $stmt->execute();
            $records = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get total count for pagination
            $countSql = "SELECT COUNT(*) as total FROM `{$table}` WHERE {$whereClause}";
            $countStmt = $this->db->prepare($countSql);

            // Bind filter parameters for count
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
    private function createRecord($table, $input, $userId)
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
    private function updateRecord($table, $id, $input, $userId)
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

            $setColumns = array_map(fn($k) => "`{$k}` = ?", array_keys($filteredInput));
            $setClause = implode(', ', $setColumns);
            $sql = "UPDATE `{$table}` SET {$setClause} WHERE `id` = ?";

            $values = array_values($filteredInput);
            $values[] = $id;

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
    private function deleteRecord($table, $id, $userId)
    {
        try {
            // First, check if record exists
            $stmt = $this->db->prepare("SELECT `id` FROM `{$table}` WHERE `id` = :id LIMIT 1");
            $stmt->execute(['id' => $id]);
            $exists = $stmt->fetch();

            if (!$exists) {
                $this->sendResponse(['error' => 'Record not found'], 404);
                return;
            }

            $stmt = $this->db->prepare("DELETE FROM `{$table}` WHERE `id` = :id");
            $stmt->execute(['id' => $id]);

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

            $stmt = $this->db->prepare(
                "SELECT COUNT(*) as count FROM rate_limits WHERE ip_address = :ip AND created_at > :cutoff"
            );
            $stmt->execute([
                'ip' => $ip,
                'cutoff' => $cutoff,
            ]);

            $count = (int)($stmt->fetch(PDO::FETCH_ASSOC)['count'] ?? 0);

            if ($count >= $maxRequests) {
                return ['allowed' => false, 'remaining' => 0, 'reset_time' => time() + $windowSeconds];
            }

            $insertStmt = $this->db->prepare(
                "INSERT INTO rate_limits (ip_address, created_at) VALUES (:ip, NOW())"
            );
            $insertStmt->execute(['ip' => $ip]);

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
     * Garante que a tabela de rate limit exista
     */
    private function ensureRateLimitTable(): void
    {
        $this->db->exec(
            "CREATE TABLE IF NOT EXISTS rate_limits (
                id INT PRIMARY KEY AUTO_INCREMENT,
                ip_address VARCHAR(45) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_ip_created (ip_address, created_at)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );
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
