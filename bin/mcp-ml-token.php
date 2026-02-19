#!/usr/bin/env php
<?php
/**
 * Retorna o Access Token mais recente e válido do Mercado Livre
 * 
 * Usado pelo wrapper do MCP Server para autenticação automática.
 * Busca na tabela ml_accounts a conta ativa com token válido,
 * descriptografa se necessário, e imprime o token no stdout.
 * 
 * Uso: php bin/mcp-ml-token.php [--account-id=N] [--format=bearer|raw]
 * 
 * Saída:
 *   - format=bearer (padrão): "Bearer APP_USR-xxx..."
 *   - format=raw: "APP_USR-xxx..."
 * 
 * Exit codes:
 *   0 = sucesso
 *   1 = nenhum token válido encontrado
 *   2 = erro de conexão/configuração
 */

declare(strict_types=1);

// Bootstrap mínimo — não carrega o framework inteiro
$basePath = dirname(__DIR__);

// Carregar .env manualmente (sem dependência de framework)
$envFile = $basePath . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        if (str_contains($line, '=')) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            // Remover aspas envolventes
            if (preg_match('/^(["\'])(.*)\\1$/', $value, $m)) {
                $value = $m[2];
            }
            $_ENV[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

// Parsear argumentos
$accountId = null;
$format = 'bearer';

foreach ($argv as $arg) {
    if (str_starts_with($arg, '--account-id=')) {
        $accountId = (int) substr($arg, strlen('--account-id='));
    }
    if (str_starts_with($arg, '--format=')) {
        $format = substr($arg, strlen('--format='));
    }
}

try {
    // Conectar ao banco
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        $_ENV['DB_HOST'] ?? 'localhost',
        $_ENV['DB_PORT'] ?? '3306',
        $_ENV['DB_DATABASE'] ?? $_ENV['DB_NAME'] ?? 'meli'
    );

    $pdo = new PDO($dsn, $_ENV['DB_USERNAME'] ?? $_ENV['DB_USER'] ?? 'root', $_ENV['DB_PASSWORD'] ?? $_ENV['DB_PASS'] ?? '', [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // Buscar conta ativa com token válido
    if ($accountId) {
        $stmt = $pdo->prepare("
            SELECT id, access_token, refresh_token, token_expires_at, tokens_encrypted, status, nickname
            FROM ml_accounts 
            WHERE id = :id 
            LIMIT 1
        ");
        $stmt->execute(['id' => $accountId]);
    } else {
        // Prioridade: active > expired com token > qualquer com token
        $stmt = $pdo->query("
            SELECT id, access_token, refresh_token, token_expires_at, tokens_encrypted, status, nickname
            FROM ml_accounts 
            WHERE access_token IS NOT NULL 
              AND access_token != ''
            ORDER BY 
                CASE status 
                    WHEN 'active' THEN 1 
                    WHEN 'expired' THEN 2 
                    ELSE 3 
                END,
                token_expires_at DESC
            LIMIT 1
        ");
    }

    $account = $stmt->fetch();

    if (!$account || empty($account['access_token'])) {
        fwrite(STDERR, "[MCP-ML-Token] Nenhuma conta com token encontrada no banco.\n");
        fwrite(STDERR, "[MCP-ML-Token] Conecte uma conta via: https://eskill.com.br/auth/authorize\n");
        exit(1);
    }

    $accessToken = $account['access_token'];

    // Descriptografar se necessário
    if (!empty($account['tokens_encrypted'])) {
        $appKey = $_ENV['APP_KEY'] ?? '';
        if (empty($appKey)) {
            fwrite(STDERR, "[MCP-ML-Token] APP_KEY não configurada no .env\n");
            exit(2);
        }

        $key = hash('sha256', $appKey, true); // 32 bytes para AES-256
        $raw = base64_decode($accessToken);

        if ($raw === false || strlen($raw) < 28) { // 12 (IV) + 16 (Tag) = mínimo
            fwrite(STDERR, "[MCP-ML-Token] Token criptografado inválido (base64 corrompida)\n");
            exit(2);
        }

        $ivLength = 12;
        $tagLength = 16;
        $iv = substr($raw, 0, $ivLength);
        $tag = substr($raw, $ivLength, $tagLength);
        $ciphertext = substr($raw, $ivLength + $tagLength);

        $decrypted = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($decrypted === false) {
            fwrite(STDERR, "[MCP-ML-Token] Falha na descriptografia do token (APP_KEY incorreta?)\n");
            exit(2);
        }

        $accessToken = $decrypted;
    }

    // Verificar se o token parece válido
    if (empty($accessToken) || strlen($accessToken) < 20) {
        fwrite(STDERR, "[MCP-ML-Token] Token descriptografado está vazio ou muito curto\n");
        exit(1);
    }

    // Verificar expiração
    $expiresAt = $account['token_expires_at'] ?? null;
    if ($expiresAt) {
        $expiresTs = strtotime($expiresAt);
        $now = time();
        if ($expiresTs && $expiresTs < $now) {
            $hoursAgo = round(($now - $expiresTs) / 3600, 1);
            fwrite(STDERR, "[MCP-ML-Token] AVISO: Token da conta '{$account['nickname']}' (#{$account['id']}) expirou há {$hoursAgo}h\n");
            fwrite(STDERR, "[MCP-ML-Token] Reconecte via: https://eskill.com.br/auth/authorize\n");
            // Ainda retorna o token — pode funcionar se o ML não o invalidou
        }
    }

    // Info no stderr (não polui stdout)
    fwrite(STDERR, "[MCP-ML-Token] Conta: {$account['nickname']} (#{$account['id']}) — Status: {$account['status']}\n");

    // Saída no stdout
    if ($format === 'bearer') {
        echo "Bearer {$accessToken}";
    } else {
        echo $accessToken;
    }

    exit(0);

} catch (PDOException $e) {
    fwrite(STDERR, "[MCP-ML-Token] Erro de banco: {$e->getMessage()}\n");
    exit(2);
} catch (Throwable $e) {
    fwrite(STDERR, "[MCP-ML-Token] Erro: {$e->getMessage()}\n");
    exit(2);
}
