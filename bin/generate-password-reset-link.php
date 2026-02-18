#!/usr/bin/env php
<?php
/**
 * Gera link de redefinição de senha (CLI) sem expor o token no console.
 *
 * Útil quando o envio de e-mail está desabilitado ou quando você precisa
 * recuperar acesso administrativo sem revelar senha no chat.
 *
 * Uso:
 *   php bin/generate-password-reset-link.php --email=usuario@dominio.com
 *   php bin/generate-password-reset-link.php --email=usuario@dominio.com --output=/caminho/arquivo.txt
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../autoload.php';

use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$options = getopt('', ['email:', 'output:', 'help']);

if (isset($options['help'])) {
    echo "Gerador de link de redefinição de senha (CLI)\n";
    echo "Uso: php bin/generate-password-reset-link.php --email=usuario@dominio.com [--output=/caminho/arquivo.txt]\n";
    exit(0);
}

$email = $options['email'] ?? null;
if (!$email) {
    $email = trim((string)readline('E-mail do usuário: '));
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "E-mail inválido.\n");
    exit(1);
}

// Default output file under storage/logs
$root = realpath(__DIR__ . '/..') ?: (__DIR__ . '/..');
$defaultName = 'password_reset_link_' . preg_replace('/[^a-z0-9._-]+/i', '_', $email) . '_' . date('Ymd_His') . '.txt';
$defaultOutput = $root . '/storage/logs/' . $defaultName;

$outputPath = $options['output'] ?? $defaultOutput;

// Validate output path — must be under storage/
// IMPORTANT: validate BEFORE creating directories to prevent path traversal mkdir
$outputDir = dirname($outputPath);
$allowedBase = realpath($root . '/storage');

// For non-existing dirs, walk up to find an existing ancestor and check it
$checkDir = $outputDir;
while (!is_dir($checkDir) && $checkDir !== dirname($checkDir)) {
    $checkDir = dirname($checkDir);
}
$realCheckDir = realpath($checkDir);
if ($realCheckDir === false || $allowedBase === false || !str_starts_with($realCheckDir, $allowedBase)) {
    fwrite(STDERR, "Caminho de saída inválido: deve estar dentro de storage/\n");
    exit(1);
}

// Now safe to create directory after validation
@mkdir($outputDir, 0775, true);

try {
    $db = Database::getInstance();

    // Ensure table exists (same schema as PasswordResetService)
    $db->exec(
        "CREATE TABLE IF NOT EXISTS password_resets (\n" .
            "    id INT PRIMARY KEY AUTO_INCREMENT,\n" .
            "    email VARCHAR(255) NOT NULL,\n" .
            "    token VARCHAR(64) NOT NULL UNIQUE,\n" .
            "    expires_at TIMESTAMP NOT NULL,\n" .
            "    used_at TIMESTAMP NULL,\n" .
            "    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,\n" .
            "    INDEX idx_email (email),\n" .
            "    INDEX idx_token (token),\n" .
            "    INDEX idx_expires (expires_at)\n" .
            ") ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
    );

    // Only create reset token for existing users
    $stmt = $db->prepare('SELECT id, name, email FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$user) {
        fwrite(STDERR, "Usuário não encontrado para este e-mail.\n");
        exit(1);
    }

    // Generate 64-char token
    $token = bin2hex(random_bytes(32));
    $expiresAt = date('Y-m-d H:i:s', time() + 3600);

    // Clear previous tokens for this email
    $stmt = $db->prepare('DELETE FROM password_resets WHERE email = :email');
    $stmt->execute(['email' => $email]);

    // Insert new token
    $stmt = $db->prepare('INSERT INTO password_resets (email, token, expires_at) VALUES (:email, :token, :expires_at)');
    $stmt->execute([
        'email' => $email,
        'token' => $token,
        'expires_at' => $expiresAt,
    ]);

    $config = require $root . '/config/app.php';
    $baseUrl = $config['url'] ?? 'https://eskill.com.br';
    $resetUrl = rtrim($baseUrl, '/') . '/auth/reset-password?token=' . $token;

    $content = "LINK DE RESET (sensível)\n";
    $content .= "Email: {$email}\n";
    $content .= "Criado em: " . date('c') . "\n";
    $content .= "Expira em: {$expiresAt}\n\n";
    $content .= $resetUrl . "\n";

    file_put_contents($outputPath, $content);
    @chmod($outputPath, 0600);

    echo "Link de reset gerado e salvo em: {$outputPath}\n";
    echo "(Por segurança, o link não é impresso no console.)\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Erro: {$e->getMessage()}\n");
    exit(1);
}
