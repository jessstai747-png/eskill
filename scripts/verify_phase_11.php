<?php

require_once __DIR__ . '/../vendor/autoload.php';

use App\Database;
use App\Services\WebhookProcessorService;

$dotenv = Dotenv\Dotenv::createImmutable(__DIR__ . '/../');
$dotenv->load();

echo "=============================================\n";
echo "   ⚡ TESTE DE PROCESSAMENTO (PHASE 11)    \n";
echo "=============================================\n";

$db = Database::getInstance();

// 1. Limpar e Garantir tabela
$db->exec("DELETE FROM webhook_events WHERE resource = '/orders/TESTE123'");

echo "[1] Inserindo Evento Mock (Pedido)... ";
try {
    $stmt = $db->prepare("
        INSERT INTO webhook_events (topic, resource, user_id, application_id, payload, created_at, status)
        VALUES ('orders_v2', '/orders/TESTE123', 123, 456, '{}', NOW(), 'pending')
    ");
    $stmt->execute();
    $id = $db->lastInsertId();
    echo "✅ OK (ID: $id)\n";
} catch (Exception $e) {
    die("❌ ERRO: " . $e->getMessage() . "\n");
}

// 2. Rodar Processador
echo "[2] Executando WebhookProcessor... \n";
$service = new WebhookProcessorService();
$stats = $service->processQueue();

echo "    > Processados: " . $stats['processed'] . "\n";
echo "    > Erros:       " . $stats['errors'] . "\n";

// 3. Verificar Resultado
echo "[3] Verificando Status Final... ";
$stmt = $db->prepare("SELECT status FROM webhook_events WHERE id = :id");
$stmt->execute(['id' => $id]);
$status = $stmt->fetchColumn();

// Como o ID TESTE123 não existe na API real, o OrderService deve retornar false (falha ao buscar), 
// mas o processador deve marcar como 'failed' ou lidar com isso.
// Na implementação atual: se dispatch retornar false -> failed via Strategy.

if ($status === 'failed' || $status === 'completed') {
    echo "✅ OK (Status: $status)\n";
    echo "Nota: 'failed' é esperado pois o ID do pedido é falso, provando que o fluxo rodou.\n";
} else {
    echo "❌ ERRO (Status: $status)\n";
}

echo "=============================================\n";
