#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Reset de senha de usuário (CLI)
 *
 * - Não imprime senha
 * - Pode ocultar input em TTY (stty)
 *
 * Uso:
 *   php bin/reset-user-password.php --email=usuario@dominio.com [--verify]
 */

require __DIR__ . '/../vendor/autoload.php';
require __DIR__ . '/../autoload.php';

use App\Database;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

$options = getopt('', ['email:', 'verify', 'help']);

if (isset($options['help'])) {
    echo "Reset de senha (CLI)\n";
    echo "Uso: php bin/reset-user-password.php --email=usuario@dominio.com [--verify]\n";
    exit(0);
}

$email = $options['email'] ?? null;
$verify = isset($options['verify']);

function prompt(string $label): string
{
    $value = readline($label);
    return trim((string)$value);
}

function promptHidden(string $label): string
{
    // Best-effort hidden input for TTY
    $isTty = function_exists('posix_isatty') ? @posix_isatty(STDIN) : true;

    if ($isTty) {
        // Disable echo
        @system('stty -echo');
        $value = readline($label);
        // Re-enable echo
        @system('stty echo');
        echo "\n";
        return trim((string)$value);
    }

    // Fallback (may echo)
    return prompt($label);
}

if (!$email) {
    $email = prompt('E-mail do usuário: ');
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "E-mail inválido.\n");
    exit(1);
}

$password = promptHidden('Nova senha: ');
$password2 = promptHidden('Confirmar nova senha: ');

if ($password !== $password2) {
    fwrite(STDERR, "As senhas não coincidem.\n");
    exit(1);
}

if (strlen($password) < 12) {
    fwrite(STDERR, "Senha muito curta. Recomenda-se mínimo de 12 caracteres.\n");
    exit(1);
}

$hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
if (!$hash) {
    fwrite(STDERR, "Falha ao gerar hash de senha.\n");
    exit(1);
}

try {
    $db = Database::getInstance();

    // Ensure user exists
    $stmt = $db->prepare('SELECT id, email_verified_at FROM users WHERE email = :email LIMIT 1');
    $stmt->execute(['email' => $email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        fwrite(STDERR, "Usuário não encontrado para este e-mail.\n");
        exit(1);
    }

    $sql = 'UPDATE users SET password = :password, updated_at = NOW()';
    $params = ['password' => $hash, 'email' => $email];

    if ($verify || empty($user['email_verified_at'])) {
        $sql .= ', email_verified_at = COALESCE(email_verified_at, NOW())';
    }

    $sql .= ' WHERE email = :email';

    $upd = $db->prepare($sql);
    $ok = $upd->execute($params);

    if (!$ok) {
        fwrite(STDERR, "Falha ao atualizar senha.\n");
        exit(1);
    }

    echo "Senha atualizada com sucesso para {$email}.\n";
    if ($verify || empty($user['email_verified_at'])) {
        echo "E-mail marcado como verificado (quando necessário).\n";
    }
    echo "Agora tente fazer login normalmente pela tela /login.\n";
} catch (Throwable $e) {
    fwrite(STDERR, "Erro: {$e->getMessage()}\n");
    exit(1);
}
