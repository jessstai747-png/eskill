<?php
require_once __DIR__ . '/../vendor/autoload.php';

// Carregar variáveis de ambiente
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/..');
$dotenv->safeLoad();

use App\Database;

$db = Database::getInstance();
$stmt = $db->query("SELECT id, nickname, status, token_expires_at, updated_at, last_synced_at FROM ml_accounts");
$accounts = $stmt->fetchAll(PDO::FETCH_ASSOC);

echo "--- Status Atual das Contas ---\n";
foreach ($accounts as $acc) {
    echo "ID: {$acc['id']}\n";
    echo "Nickname: {$acc['nickname']}\n";
    echo "Status: {$acc['status']}\n";
    echo "Expira em: {$acc['token_expires_at']}\n";
    echo "Último Update: {$acc['updated_at']}\n";
    echo "Último Sync: " . ($acc['last_synced_at'] ?? 'N/A') . "\n";
    
    // Calcular tempo expirado
    if ($acc['token_expires_at']) {
        $expires = strtotime($acc['token_expires_at']);
        $diff = time() - $expires;
        if ($diff > 0) {
            $hours = floor($diff / 3600);
            echo "Status Real: EXPIRADO há {$hours} horas\n";
        } else {
            echo "Status Real: VÁLIDO\n";
        }
    }
    echo "---------------------------\n";
}
