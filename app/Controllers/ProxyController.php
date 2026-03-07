<?php
declare(strict_types=1);

namespace App\Controllers;

use App\Database;

class ProxyController extends BaseController
{
    /**
     * Lista todos os proxies e estatísticas
     * GET /api/proxies
     */
    public function index(): void
    {
        header('Content-Type: application/json');

        try {
            $db = Database::getInstance();
            $this->ensureTable($db);

            $proxies = $db->query("
                SELECT id, type, host, port, country, priority, status, username,
                       success_count, failure_count, avg_response_time,
                       last_used_at, last_success_at, last_failure_at,
                       CASE WHEN (success_count + failure_count) > 0
                            THEN ROUND(success_count * 100.0 / (success_count + failure_count), 1)
                            ELSE 0 END AS success_rate,
                       (success_count + failure_count) AS total_requests,
                       'db' AS source
                FROM ml_proxies
                ORDER BY priority DESC, id ASC
            ")->fetchAll(\PDO::FETCH_ASSOC);

            // Include env proxy if configured
            $envProxy = $this->getEnvProxy();
            if ($envProxy) {
                array_unshift($proxies, $envProxy);
            }

            $total = count($proxies);
            $active = count(array_filter($proxies, fn($p) => $p['status'] === 'active'));
            $blacklisted = count(array_filter($proxies, fn($p) => $p['status'] === 'blacklisted'));

            echo json_encode([
                'success' => true,
                'data' => [
                    'proxies' => $proxies,
                    'stats' => [
                        'total_proxies' => $total,
                        'available_proxies' => $active,
                        'blacklisted' => $blacklisted,
                    ],
                ],
            ]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Status do sistema de proxy
     * GET /api/proxies/status
     */
    public function status(): void
    {
        header('Content-Type: application/json');

        $enabled = filter_var(
            $_ENV['ML_PROXY_ENABLED'] ?? getenv('ML_PROXY_ENABLED') ?? false,
            FILTER_VALIDATE_BOOLEAN
        );

        echo json_encode([
            'success' => true,
            'data' => ['enabled' => $enabled],
        ]);
    }

    /**
     * Adicionar novo proxy
     * POST /api/proxies
     */
    public function store(): void
    {
        header('Content-Type: application/json');

        try {
            $data = json_decode(file_get_contents('php://input'), true) ?? [];

            if (empty($data['host']) || empty($data['port'])) {
                http_response_code(400);
                echo json_encode(['success' => false, 'error' => 'Host e porta são obrigatórios']);
                return;
            }

            $db = Database::getInstance();
            $this->ensureTable($db);

            $stmt = $db->prepare("
                INSERT INTO ml_proxies (type, host, port, username, password, country, priority)
                VALUES (:type, :host, :port, :username, :password, :country, :priority)
            ");
            $stmt->execute([
                'type' => $data['type'] ?? 'http',
                'host' => $data['host'],
                'port' => $data['port'],
                'username' => $data['username'] ?? null,
                'password' => $data['password'] ?? null,
                'country' => $data['country'] ?? 'BR',
                'priority' => (int)($data['priority'] ?? 50),
            ]);

            echo json_encode(['success' => true, 'message' => 'Proxy adicionado', 'id' => $db->lastInsertId()]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Testar um proxy específico
     * POST /api/proxies/{id}/test
     */
    public function test(string $id): void
    {
        header('Content-Type: application/json');

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("SELECT * FROM ml_proxies WHERE id = ?");
            $stmt->execute([$id]);
            $proxy = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$proxy) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Proxy não encontrado']);
                return;
            }

            $result = $this->testProxyConnection($proxy);

            // Update proxy stats
            if ($result['success']) {
                $db->prepare("
                    UPDATE ml_proxies SET
                        status = 'active',
                        success_count = success_count + 1,
                        last_success_at = NOW(),
                        last_used_at = NOW(),
                        avg_response_time = ?
                    WHERE id = ?
                ")->execute([$result['response_time'], $id]);
            } else {
                $db->prepare("
                    UPDATE ml_proxies SET
                        failure_count = failure_count + 1,
                        last_failure_at = NOW(),
                        last_used_at = NOW(),
                        last_error = ?
                    WHERE id = ?
                ")->execute([$result['message'] ?? 'Connection failed', $id]);
            }

            echo json_encode(['success' => true, 'data' => $result]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Testar todos os proxies
     * POST /api/proxies/test-all
     */
    public function testAll(): void
    {
        header('Content-Type: application/json');

        try {
            $db = Database::getInstance();
            $proxies = $db->query("SELECT * FROM ml_proxies")->fetchAll(\PDO::FETCH_ASSOC);

            $results = [];
            foreach ($proxies as $proxy) {
                $results[$proxy['id']] = $this->testProxyConnection($proxy);
            }

            echo json_encode(['success' => true, 'data' => $results]);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Remover proxy
     * DELETE /api/proxies/{id}
     */
    public function destroy(string $id): void
    {
        header('Content-Type: application/json');

        try {
            $db = Database::getInstance();
            $stmt = $db->prepare("DELETE FROM ml_proxies WHERE id = ?");
            $stmt->execute([$id]);

            if ($stmt->rowCount() === 0) {
                http_response_code(404);
                echo json_encode(['success' => false, 'error' => 'Proxy não encontrado']);
                return;
            }

            echo json_encode(['success' => true, 'message' => 'Proxy removido']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Limpar blacklist
     * POST /api/proxies/clear-blacklist
     */
    public function clearBlacklist(): void
    {
        header('Content-Type: application/json');

        try {
            $db = Database::getInstance();
            $db->exec("UPDATE ml_proxies SET status = 'active' WHERE status = 'blacklisted'");

            echo json_encode(['success' => true, 'message' => 'Blacklist limpa']);
        } catch (\Exception $e) {
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
    }

    /**
     * Testa conexão de um proxy
     */
    private function testProxyConnection(array $proxy): array
    {
        $start = microtime(true);

        $proxyUrl = "{$proxy['type']}://";
        if (!empty($proxy['username'])) {
            $proxyUrl .= "{$proxy['username']}:{$proxy['password']}@";
        }
        $proxyUrl .= "{$proxy['host']}:{$proxy['port']}";

        $ch = curl_init('https://api.mercadolibre.com/sites/MLB');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_PROXY => $proxyUrl,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);

        $elapsed = round((microtime(true) - $start) * 1000);

        if ($response !== false && $httpCode >= 200 && $httpCode < 400) {
            return ['success' => true, 'response_time' => $elapsed, 'http_code' => $httpCode];
        }

        return ['success' => false, 'response_time' => $elapsed, 'message' => $error ?: "HTTP {$httpCode}"];
    }

    /**
     * Retorna proxy do .env se configurado
     */
    private function getEnvProxy(): ?array
    {
        $host = $_ENV['ML_PROXY_HOST'] ?? getenv('ML_PROXY_HOST') ?? null;
        if (!$host) {
            return null;
        }

        return [
            'id' => 'env',
            'type' => $_ENV['ML_PROXY_TYPE'] ?? getenv('ML_PROXY_TYPE') ?? 'http',
            'host' => $host,
            'port' => $_ENV['ML_PROXY_PORT'] ?? getenv('ML_PROXY_PORT') ?? '8080',
            'country' => 'BR',
            'priority' => 100,
            'status' => 'active',
            'username' => $_ENV['ML_PROXY_USER'] ?? getenv('ML_PROXY_USER') ?? null,
            'success_count' => 0,
            'failure_count' => 0,
            'avg_response_time' => null,
            'last_used_at' => null,
            'last_success_at' => null,
            'last_failure_at' => null,
            'success_rate' => 0,
            'total_requests' => 0,
            'source' => 'env',
        ];
    }

    /**
     * Garante que a tabela ml_proxies existe
     */
    private function ensureTable(\PDO $db): void
    {
        $db->exec("
            CREATE TABLE IF NOT EXISTS ml_proxies (
                id INT AUTO_INCREMENT PRIMARY KEY,
                type ENUM('http', 'https', 'socks4', 'socks5') NOT NULL DEFAULT 'http',
                host VARCHAR(255) NOT NULL,
                port VARCHAR(10) NOT NULL DEFAULT '8080',
                username VARCHAR(255) DEFAULT NULL,
                password VARCHAR(255) DEFAULT NULL,
                country CHAR(2) DEFAULT 'BR',
                priority INT DEFAULT 50,
                status ENUM('active', 'inactive', 'testing', 'blacklisted') DEFAULT 'active',
                success_count INT DEFAULT 0,
                failure_count INT DEFAULT 0,
                last_used_at DATETIME DEFAULT NULL,
                last_success_at DATETIME DEFAULT NULL,
                last_failure_at DATETIME DEFAULT NULL,
                last_error TEXT DEFAULT NULL,
                avg_response_time INT DEFAULT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                UNIQUE KEY unique_proxy (host, port),
                INDEX idx_status (status),
                INDEX idx_priority (priority DESC)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
}
